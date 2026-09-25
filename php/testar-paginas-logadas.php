<?php
/**
 * TESTADOR DAS ÁREAS RESTRITAS (login)
 * ====================================
 *
 * Este teste verifica as páginas que exigem um usuário logado
 * (dashboard, endereços e todas as áreas do painel administrativo).
 *
 * Por que existe um teste separado:
 *   Manter a sessão de login com a biblioteca curl é delicado: cada
 *   requisição precisa reaproveitar exatamente o mesmo cookie. Aqui o
 *   cookie é mantido de forma explícita (lemos o PHPSESSID da resposta e
 *   enviamos de volta no cabeçalho Cookie), o que é o método mais confiável.
 *
 * Como identificar sucesso:
 *   - A tela de login tem cerca de 32 KB. Uma página de painel tem bem mais
 *     (a de administração chega a ~150 KB).
 *   - Então comparamos o tamanho: se vier a tela de login, o teste aponta.
 *
 * Uso: php php/testar-paginas-logadas.php
 */

$base = 'http://127.0.0.1:8899';
$email = 'admin_login@techflow.com';
$senha = 'senha12345';

require_once __DIR__ . '/../php/conexao.php';

// ---------------------------------------------------------------------------
// Função que faz uma requisição mantendo o cookie de sessão manualmente.
// ---------------------------------------------------------------------------
function pedir(string $url, string $cookie = '', ?array $post = null): array
{
    $ch = curl_init($url);
    $cabecalhos = [];
    if ($cookie !== '') {
        $cabecalhos[] = 'Cookie: ' . $cookie;   // devolve a sessão ao servidor
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $cabecalhos,
        CURLOPT_TIMEOUT        => 30,
    ]);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $bruto = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tamCabecalho = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($bruto === false) {
        return ['codigo' => 0, 'corpo' => '', 'cookie' => $cookie, 'local' => ''];
    }

    $cabecalho = substr($bruto, 0, $tamCabecalho);
    $corpo = substr($bruto, $tamCabecalho);

    // Lê o PHPSESSID devolvido pelo servidor (para manter a sessão)
    if (preg_match('/Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $cabecalho, $m)) {
        $cookie = $m[1];
    }

    // Lê o cabeçalho Location (usado nos redirecionamentos)
    $local = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $cabecalho, $lm)) {
        $local = trim($lm[1]);
    }

    return ['codigo' => $codigo, 'corpo' => $corpo, 'cookie' => $cookie, 'local' => $local];
}

// ---------------------------------------------------------------------------
// 1) Prepara a conta de administrador
// ---------------------------------------------------------------------------
$db = db_connect();
$hash = password_hash($senha, PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT INTO usuarios (nome,email,nascimento,senha,tipo,is_admin,status_conta)
    VALUES ('Admin Login', ?, '1990-01-01', ?, 'admin', 1, 'ativo')
    ON DUPLICATE KEY UPDATE senha=VALUES(senha), tipo='admin', is_admin=1, status_conta='ativo'");
$stmt->bind_param('ss', $email, $hash);
$stmt->execute();

echo "Conta de teste: $email\n";

// ---------------------------------------------------------------------------
// 2) Faz o login e guarda o cookie
// ---------------------------------------------------------------------------
$paginaLogin = pedir($base . '/pages/login.php');
preg_match('/name="csrf" value="([^"]+)"/', $paginaLogin['corpo'], $m);
$csrf = $m[1] ?? '';
$cookieAtual = $paginaLogin['cookie'];

echo "Token CSRF: " . ($csrf !== '' ? 'obtido' : 'FALHOU') . "\n";

$resLogin = pedir($base . '/pages/login.php', $cookieAtual, [
    'csrf' => $csrf, 'email' => $email, 'senha' => $senha,
    'redirect' => '', 'prod_id' => '0',
]);

echo "Login: HTTP {$resLogin['codigo']}";
if ($resLogin['local'] !== '') {
    echo " -> {$resLogin['local']}";
}
echo "\n";
echo "Cookie de sessão guardado: " . ($resLogin['cookie'] !== '' ? 'sim' : 'NAO') . "\n\n";

// Guarda o cookie para as próximas requisições
$cookieSessao = $resLogin['cookie'];

// ---------------------------------------------------------------------------
// 3) Testa as páginas restritas
// ---------------------------------------------------------------------------
$paginas = [
    '/pages/dashboard.php'          => 'Painel do cliente',
    '/pages/enderecos.php'          => 'Meus endereços',
    '/pages/admin-produtos.php'     => 'Administração de produtos',
    '/pages/painel.php?area=admin'  => 'Painel - área admin',
    '/pages/painel.php?area=developer' => 'Painel - área developer',
    '/pages/painel.php?area=support'   => 'Painel - área support',
    '/pages/painel.php?area=moderator' => 'Painel - área moderator',
    '/pages/painel.php?area=manager'   => 'Painel - área manager',
    '/pages/painel.php?area=financial' => 'Painel - área financial',
    '/pages/painel.php?area=logistics' => 'Painel - área logistics',
];

// Tamanho aproximado da tela de login (usado para detectar sessão inválida)
$tamanhoTelaLogin = strlen($paginaLogin['corpo']);

$erros = [];
echo "----------------------------------------------------------------\n";
echo " PÁGINAS RESTRITAS\n";
echo "----------------------------------------------------------------\n";

foreach ($paginas as $caminho => $descricao) {
    $r = pedir($base . $caminho, $cookieSessao);

    // Atualiza o cookie caso o servidor tenha renovado a sessão
    if ($r['cookie'] !== '') {
        $cookieSessao = $r['cookie'];
    }

    $tamanho = strlen($r['corpo']);
    $pareceLogin = ($tamanho < ($tamanhoTelaLogin + 2000));

    if ($r['codigo'] >= 500) {
        echo sprintf("  [XX] %-34s HTTP %d (erro do servidor)\n", $caminho, $r['codigo']);
        $erros[] = "$caminho -> erro HTTP {$r['codigo']}";
        continue;
    }

    if ($pareceLogin) {
        echo sprintf("  [XX] %-34s HTTP %d (%d bytes) -> MOSTROU A TELA DE LOGIN\n", $caminho, $r['codigo'], $tamanho);
        $erros[] = "$caminho -> sessão inválida (mostrou a tela de login)";
        continue;
    }

    // Procura erros do PHP dentro da página
    $problemas = [];
    if (preg_match('/Fatal error|Parse error|Uncaught/i', $r['corpo'])) {
        $problemas[] = 'ERRO FATAL';
        $erros[] = "$caminho -> erro fatal do PHP";
    }
    $avisos = preg_match_all('/Warning:|Notice:|Deprecated:/i', $r['corpo']);
    if ($avisos > 0) {
        $problemas[] = "$avisos avisos do PHP";
        $erros[] = "$caminho -> $avisos avisos do PHP";
    }
    $moji = preg_match_all('/Ã[\x{0080}-\x{00BF}]|ð[\x{0080}-\x{00FF}]/u', $r['corpo']);
    if ($moji > 0) {
        $problemas[] = "$moji trechos com acentuação corrompida";
        $erros[] = "$caminho -> $moji trechos com acentuação corrompida";
    }

    echo sprintf("  [OK] %-34s HTTP %d (%d bytes) - %s%s\n",
        $caminho, $r['codigo'], $tamanho, $descricao,
        empty($problemas) ? '' : ' | ' . implode(', ', $problemas));
}

// ---------------------------------------------------------------------------
// 4) Resultado
// ---------------------------------------------------------------------------
echo "\n----------------------------------------------------------------\n";
echo " RESULTADO DAS ÁREAS RESTRITAS\n";
echo "----------------------------------------------------------------\n";
echo " Páginas testadas: " . count($paginas) . "\n";
echo " Problemas: " . count($erros) . "\n\n";

if (empty($erros)) {
    echo " NENHUM ERRO. Todas as páginas restritas abriram corretamente.\n";
} else {
    foreach ($erros as $i => $e) {
        echo sprintf("  %2d) %s\n", $i + 1, $e);
    }
}

// Limpeza
$s = $db->prepare('DELETE FROM usuarios WHERE email = ?');
$s->bind_param('s', $email);
$s->execute();
echo "\n(conta de teste removida do banco)\n";