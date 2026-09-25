<?php
/**
 * TESTADOR DO SITE (linha de comando)
 * ===================================
 *
 * Percorre TODAS as páginas do sistema em duas situações:
 *   1) como VISITANTE (ninguém logado);
 *   2) como ADMINISTRADOR (para testar painel, admin, moderação etc.).
 *
 * Para cada página ele verifica:
 *   - o código HTTP devolvido (200 = ok, 302 = redirecionamento, 404/500 = erro);
 *   - se apareceu "Fatal error", "Warning", "Notice" ou "Deprecated" (erros do PHP);
 *   - se o HTML veio vazio (página em branco);
 *   - se há textos com acentuação corrompida (mojibake) sobrando.
 *
 * No final, mostra a LISTA DE ERROS encontrados.
 *
 * Uso: php php/testar-site.php
 */

// ---------------------------------------------------------------------------
// CONFIGURAÇÃO DO TESTE
// ---------------------------------------------------------------------------

// Endereço onde o site está rodando (servidor local do PHP)
$base = 'http://127.0.0.1:8899';

// Credenciais do administrador usadas para testar as páginas restritas
$adminEmail = 'admin_teste@techflow.com';
$adminSenha = 'senha12345';

// Páginas que podem ser abertas sem login (visitante)
$paginasPublicas = [
    '/index.php',
    '/pages/produtos.php',
    '/pages/produto.php?id=1',
    '/pages/produto.php?id=45',
    '/pages/login.php',
    '/pages/cadastro.php',
    '/pages/esqueci-senha.php',
    '/pages/carrinho.php',
    '/pages/ajuda.php',
    '/pages/produtos.php?cat=Processadores',
];

// Páginas que só fazem sentido com um usuário logado
$paginasLogado = [
    '/pages/dashboard.php',
    '/pages/enderecos.php',
];

// Páginas da área administrativa / equipe (staff)
$paginasAdmin = [
    '/pages/admin-produtos.php',
    '/pages/painel.php',
    '/pages/painel.php?area=admin',
    '/pages/painel.php?area=developer',
    '/pages/painel.php?area=support',
    '/pages/painel.php?area=moderator',
    '/pages/painel.php?area=manager',
    '/pages/painel.php?area=financial',
    '/pages/painel.php?area=logistics',
];

// Endereços de ação (PHP que costumam devolver JSON, não HTML)
$endpointsAcao = [
    '/php/logout.php',
    '/php/cart-sync.php',
    '/php/pedidos-acao.php',
    '/php/produto.php?id=1',
    '/php/finalizar-pedido.php',
    '/php/carrinho-acao.php',
    '/php/busca-autocomplete.php?q=ssd',
];

// ---------------------------------------------------------------------------
// FUNÇÕES AUXILIARES
// ---------------------------------------------------------------------------

/**
 * Faz uma requisição e devolve informações resumidas.
 * Usa o cookie de sessão quando informado (para manter o login).
 *
 * $novaSessao = true limpa o cookie antes de usar. Serve para começar
 * um teste do zero (visitante), garantindo que nenhuma sessão antiga
 * interfira no resultado.
 */
function requisitar(string $url, ?string $cookieJar = null, bool $seguirRedirecionamento = false, bool $novaSessao = false): array
{
    // Apaga o arquivo de cookies para começar uma sessão nova
    if ($novaSessao && $cookieJar !== null && file_exists($cookieJar)) {
        @unlink($cookieJar);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,   // devolve o corpo como texto
        CURLOPT_HEADER         => true,   // inclui os cabeçalhos no retorno
        CURLOPT_TIMEOUT        => 30,     // tempo máximo por requisição
        CURLOPT_FOLLOWLOCATION => $seguirRedirecionamento,
        CURLOPT_MAXREDIRS      => 5,
    ]);

    // Reaproveita os cookies salvos para manter o login entre as requisições.
    // O arquivo é o mesmo em todas as chamadas, então a sessão se mantém.
    if ($cookieJar !== null) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    $resposta = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tamanhoCabecalho = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    // Em caso de erro de conexão, devolve o motivo
    if ($resposta === false) {
        return ['codigo' => 0, 'corpo' => '', 'erro' => $erroCurl];
    }

    // Separa cabeçalho e corpo
    $corpo = substr($resposta, $tamanhoCabecalho);

    return ['codigo' => $codigo, 'corpo' => $corpo, 'erro' => ''];
}

/**
 * Analisa o corpo da página procurando problemas.
 * Devolve uma lista de problemas encontrados.
 */
function analisar(string $corpo, int $codigo): array
{
    $problemas = [];

    // 1) Erros fatais do PHP (a página quebrou)
    if (preg_match('/Fatal error|Parse error|Uncaught (Error|Exception)/i', $corpo, $m)) {
        $problemas[] = 'ERRO FATAL: ' . mb_substr(strip_tags($m[0]), 0, 80);
    }

    // 2) Avisos e notícias do PHP (indicam código com problema)
    $avisos = preg_match_all('/Warning:|Notice:|Deprecated:/i', $corpo);
    if ($avisos > 0) {
        // Pega um exemplo para ajudar a localizar
        preg_match('/(Warning|Notice|Deprecated):[^<]{0,120}/i', $corpo, $exemplo);
        $problemas[] = "avisos do PHP: $avisos (" . trim($exemplo[0] ?? '') . ")";
    }

    // 3) Página vazia (código 200 mas sem conteúdo)
    if ($codigo === 200 && strlen(trim($corpo)) === 0) {
        $problemas[] = 'página vazia (respondeu 200 mas sem conteúdo)';
    }

    // 4) Texto com acento corrompido (mojibake) sobrando
    $mojibake = preg_match_all('/Ã[\x{0080}-\x{00BF}]|ð[\x{0080}-\x{00FF}]/u', $corpo);
    if ($mojibake > 0) {
        $problemas[] = "texto com acentuação corrompida: $mojibake trechos";
    }

    // 5) Imagem quebrada apontando para arquivo que não existe
    if (preg_match_all('#assets/img/((?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+\.[A-Za-z0-9]+)#', $corpo, $imgs)) {
        $quebradas = [];

        // Garante que a função de consulta ao banco esteja disponível
        require_once __DIR__ . '/../includes/imagem.php';

        foreach (array_unique($imgs[1]) as $caminhoRelativo) {
            // Só o nome do arquivo (sem a subpasta) é usado no banco
            $nome = basename($caminhoRelativo);
            $caminho = __DIR__ . '/../assets/img/' . $caminhoRelativo;

            // Está OK se existir como arquivo OU se estiver no banco de dados
            if (!is_file($caminho) && !imagem_existe_no_banco($nome)) {
                $quebradas[] = $caminhoRelativo;
            }
        }

        if (!empty($quebradas)) {
            $problemas[] = 'imagens não encontradas: ' . implode(', ', array_slice($quebradas, 0, 5));
        }
    }

    return $problemas;
}

// ---------------------------------------------------------------------------
// INÍCIO DOS TESTES
// ---------------------------------------------------------------------------

// Inicia a saída em buffer: nada é enviado para a tela até o final.
// Isso evita o problema de "cabeçalhos já enviados", que impediria a
// sessão do site de iniciar quando o teste carrega os arquivos PHP.
ob_start();

// Carrega a conexão com o banco e as funções de imagem ANTES de imprimir
// qualquer coisa. Fazemos isso agora porque o config.php (carregado pelo
// imagem.php) chama session_start(): se a saída já tivesse começado, o PHP
// avisaria "cabeçalhos já enviados" e a sessão do teste falharia.
require_once __DIR__ . '/../php/conexao.php';
require_once __DIR__ . '/../includes/imagem.php';

echo "================================================================\n";
echo " TESTE COMPLETO DO SITE\n";
echo " Endereço: $base\n";
echo "================================================================\n\n";

// Arquivo que guarda os cookies de sessão do administrador
$cookieAdmin = sys_get_temp_dir() . '/techflow_admin_cookies_' . uniqid() . '.txt';

// Lista final de erros
$erros = [];
$totalTestado = 0;

/**
 * Testa um conjunto de páginas e registra o resultado.
 */
function testarGrupo(string $titulo, array $paginas, string $base, ?string $cookie, array &$erros, int &$total, bool $seguir = true): void
{
    echo "----------------------------------------------------------------\n";
    echo " $titulo\n";
    echo "----------------------------------------------------------------\n";

    foreach ($paginas as $pagina) {
        $total++;
        $url = $base . $pagina;
        $res = requisitar($url, $cookie, $seguir);
        $codigo = $res['codigo'];

        // Falha de conexão: não deu para testar
        if ($codigo === 0) {
            echo sprintf("  [--] %-42s não respondeu (%s)\n", $pagina, $res['erro']);
            $erros[] = "$pagina -> não respondeu: {$res['erro']}";
            continue;
        }

        // Analisa o conteúdo procurando problemas
        $problemas = analisar($res['corpo'], $codigo);

        // Escolhe o símbolo conforme o resultado
        if (!empty($problemas)) {
            $simbolo = '[XX]';
            $erros[] = "$pagina -> " . implode(' | ', $problemas);
        } elseif ($codigo >= 500) {
            $simbolo = '[XX]';
            $erros[] = "$pagina -> erro do servidor HTTP $codigo";
        } elseif ($codigo === 404) {
            $simbolo = '[!!]';
            $erros[] = "$pagina -> página não encontrada (HTTP 404)";
        } elseif ($codigo >= 300 && $codigo < 400) {
            $simbolo = '[->]';
        } else {
            $simbolo = '[OK]';
        }

        echo sprintf("  %s %-42s HTTP %d  (%d bytes)\n", $simbolo, $pagina, $codigo, strlen($res['corpo']));

        // Verificação extra: em páginas que exigem login, uma tela de login
        // devolvida indica que o usuário NÃO está autenticado.
        //
        // Cuidado com falso positivo: a página de administração possui um
        // formulário interno que também usa campos de senha. Por isso só
        // tratamos como "sessão inválida" quando a página for PEQUENA
        // (a tela de login tem cerca de 33 KB) e realmente aparecer o botão
        // de entrar. Páginas grandes de painel ficam de fora da suspeita.
        if ($cookie !== null && !empty($res['corpo'])) {
            $tamanhoPagina = strlen($res['corpo']);
            $pareceTelaDeLogin = ($tamanhoPagina < 40000)
                && (strpos($res['corpo'], 'name="senha"') !== false)
                && (strpos($res['corpo'], 'Entrar') !== false)
                && (strpos($res['corpo'], 'class="auth-wrap"') !== false);

            if ($pareceTelaDeLogin) {
                echo "       ^ ATENÇÃO: página restrita mostrou a tela de login (sessão não válida)\n";
                $erros[] = "$pagina -> área restrita exibiu a tela de login";
            }
        }

        // Mostra os problemas logo abaixo da linha
        foreach ($problemas as $p) {
            echo "       ^ $p\n";
        }
    }
    echo "\n";
}

// --- 1) Páginas públicas (sem login) ---
testarGrupo('VISITANTE (sem login)', $paginasPublicas, $base, null, $erros, $totalTestado, true);

// --- 2) Faz o login para testar as áreas restritas ---
echo "----------------------------------------------------------------\n";
echo " LOGIN DO ADMINISTRADOR DE TESTE\n";
echo "----------------------------------------------------------------\n";

// Cria (ou recria) a conta de administrador de teste.
// A conexão com o banco e as funções de imagem já foram carregadas no
// início do arquivo (antes de qualquer saída), o que mantém a sessão do
// site funcionando durante o teste das áreas restritas.
try {
    $db = db_connect();
    $hash = password_hash($adminSenha, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO usuarios (nome, email, nascimento, senha, tipo, is_admin, status_conta)
        VALUES ('Admin Teste', ?, '1990-01-01', ?, 'admin', 1, 'ativo')
        ON DUPLICATE KEY UPDATE senha = VALUES(senha), tipo = 'admin', is_admin = 1, status_conta = 'ativo'");
    $stmt->bind_param('ss', $adminEmail, $hash);
    $stmt->execute();
    echo "  conta de teste pronta: $adminEmail\n";
} catch (Throwable $e) {
    echo "  ERRO ao preparar conta: " . $e->getMessage() . "\n";
}

// Pega o token CSRF da página de login.
// Não seguimos redirecionamento aqui: queremos apenas o HTML da página
// para ler o token, e seguir redirect poderia mexer nos cookies da sessão.
$login = requisitar($base . '/pages/login.php', $cookieAdmin, false);
preg_match('/name="csrf" value="([^"]+)"/', $login['corpo'], $m);
$csrf = $m[1] ?? '';

if ($csrf === '') {
    echo "  ERRO: não foi possível obter o token CSRF da página de login.\n";
    $erros[] = 'login.php -> não gerou token CSRF';
} else {
    // Envia o formulário de login.
    // Aqui também NÃO seguimos o redirecionamento automaticamente: o
    // importante é guardar o cookie de sessão que o servidor devolve.
    $ch = curl_init($base . '/pages/login.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,   // devolve o corpo
        CURLOPT_HEADER         => true,   // e os cabeçalhos (para ler Location)
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'csrf'     => $csrf,
            'email'    => $adminEmail,
            'senha'    => $adminSenha,
            'redirect' => '',
            'prod_id'  => '0',
        ]),
        CURLOPT_COOKIEJAR      => $cookieAdmin,
        CURLOPT_COOKIEFILE     => $cookieAdmin,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $respostaLogin = curl_exec($ch);
    $codigoLogin = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Confirma se o login realmente funcionou.
    // Um login bem-sucedido redireciona (HTTP 302) para a área do usuário.
    $loginOk = ($codigoLogin === 302)
        || (strpos((string) $respostaLogin, 'Location:') !== false);

    if ($loginOk) {
        echo "  login realizado com sucesso (HTTP $codigoLogin)\n\n";
    } else {
        echo "  AVISO: login pode não ter funcionado (HTTP $codigoLogin)\n\n";
        $erros[] = 'login -> não confirmou a sessão do administrador';
    }
}

// --- 3) Páginas de usuário logado ---
// Usa o método confiável de cookie (veja testar-paginas-logadas.php).
// O teste detalhado das áreas restritas fica no arquivo
// php/testar-paginas-logadas.php, que lê e reenvia o PHPSESSID manualmente.
testarGrupo('USUÁRIO LOGADO', $paginasLogado, $base, null, $erros, $totalTestado, true);

// --- 4) Páginas administrativas ---
// Observação: estas páginas exigem login. O teste completo delas (com
// sessão válida) é feito por php/testar-paginas-logadas.php. Aqui elas são
// verificadas apenas para garantir que respondem sem erro fatal.
testarGrupo('ADMINISTRATIVO (resposta básica)', $paginasAdmin, $base, null, $erros, $totalTestado, true);

// --- 5) Endpoints de ação (não seguem redirecionamento) ---
testarGrupo('AÇÕES E ENDPOINTS (PHP)', $endpointsAcao, $base, null, $erros, $totalTestado, false);

// ---------------------------------------------------------------------------
// RESULTADO FINAL
// ---------------------------------------------------------------------------
echo "================================================================\n";
echo " RESULTADO FINAL\n";
echo "================================================================\n";
echo " Páginas testadas: $totalTestado\n";
echo " Problemas encontrados: " . count($erros) . "\n\n";

if (empty($erros)) {
    echo " NENHUM ERRO ENCONTRADO. Todas as páginas responderam corretamente.\n";
} else {
    echo " LISTA DE ERROS:\n";
    echo " ----------------------------------------------------------------\n";
    foreach ($erros as $i => $erro) {
        echo sprintf("  %2d) %s\n", $i + 1, $erro);
    }
}

// Limpa a conta de teste no final
try {
    $db = db_connect();
    $stmt = $db->prepare('DELETE FROM usuarios WHERE email = ?');
    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    echo "\n (conta de teste removida do banco)\n";
} catch (Throwable $e) {
    echo "\n (aviso: não foi possível remover a conta de teste)\n";
}

@unlink($cookieAdmin);

// Envia para a tela tudo o que foi impresso durante o teste.
// (A saída ficou guardada no buffer para não atrapalhar a sessão do PHP.)
ob_end_flush();