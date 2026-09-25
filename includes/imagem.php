<?php
/**
 * ============================================================================
 * ARMAZENAMENTO DE IMAGENS NO BANCO DE DADOS
 * ============================================================================
 *
 * Por que este arquivo existe:
 *   Originalmente as imagens ficavam em arquivos dentro de assets/img/.
 *   Isso significa que, ao copiar o site para outro computador, era preciso
 *   lembrar de copiar também a pasta de imagens — e se algum arquivo faltasse,
 *   os produtos apareciam "quebrados".
 *
 * Como resolvemos:
 *   As imagens passam a ser guardadas DENTRO do banco de dados MySQL, na
 *   tabela `imagens`. Assim, quem tem o banco tem as imagens: o site funciona
 *   igual em qualquer máquina, sem depender de arquivos soltos.
 *
 * Como o site mostra a imagem:
 *   1) O navegador pede a imagem em `imagem.php?nome=arquivo.png`;
 *   2) O script procura primeiro NO BANCO (tabela `imagens`);
 *   3) Se não achar no banco, ele usa o ARQUIVO tradicional em assets/img/.
 *   Esse plano B garante que nada quebre: imagens antigas continuam abrindo.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../php/conexao.php';

/**
 * Cria a tabela `imagens` caso ela ainda não exista.
 *
 * Colunas:
 *   id          -> número único da imagem
 *   nome        -> nome do arquivo (ex.: "prod_123.png"), usado na URL
 *   tipo        -> tipo do arquivo (ex.: "image/png"), para o navegador abrir
 *   dados       -> o conteúdo da imagem em si (formato LONGBLOB = bytes puros)
 *   tamanho     -> tamanho em bytes (útil para conferência e diagnóstico)
 *   criado_em   -> data de gravação
 */
function imagens_garantir_tabela(): void
{
    try {
        $db = db_connect();

        $db->query("CREATE TABLE IF NOT EXISTS imagens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL UNIQUE,
            tipo VARCHAR(100) NOT NULL,
            dados LONGBLOB NOT NULL,
            tamanho INT NOT NULL DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY nome_idx (nome)
        )");
    } catch (Throwable $e) {
        // Falha aqui não deve derrubar o site: apenas registra no log.
        error_log('imagens_garantir_tabela: ' . $e->getMessage());
    }
}

/**
 * Descobre o tipo da imagem (MIME) a partir do nome do arquivo.
 * Ex.: "foto.png" -> "image/png"
 */
function imagens_tipo_por_nome(string $nome): string
{
    // Extensão em letras minúsculas
    $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

    // Tabela de extensão -> tipo de conteúdo
    $tipos = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'bmp'  => 'image/bmp',
        'ico'  => 'image/x-icon',
    ];

    // Se não reconhecer, usa um tipo genérico de imagem.
    return $tipos[$extensao] ?? 'application/octet-stream';
}

/**
 * Guarda uma imagem no banco de dados.
 *
 * $nome  = nome do arquivo (chave usada para buscar depois)
 * $dados = conteúdo binário da imagem
 *
 * Devolve true quando gravou com sucesso.
 */
function imagem_salvar_no_banco(string $nome, string $dados): bool
{
    // Não grava nome vazio nem conteúdo vazio (evita lixo no banco)
    if ($nome === '' || $dados === '') {
        return false;
    }

    // Grava somente arquivos que são realmente imagem (evita salvar
    // .htaccess, .php e outros arquivos que não são figuras).
    if (!imagem_e_arquivo_de_imagem($nome)) {
        return false;
    }

    try {
        imagens_garantir_tabela();

        $db = db_connect();
        $tipo = imagens_tipo_por_nome($nome);
        $tamanho = strlen($dados);

        // Observação técnica importante:
        // não usamos bind_param com dados binários porque o MySQL pode
        // gravar um blob VAZIO nesse caso. Em vez disso, enviamos o conteúdo
        // já escapado dentro do próprio comando SQL. É a forma confiável de
        // guardar uma imagem (LONGBLOB) sem corromper os bytes.
        $nomeEsc  = $db->real_escape_string($nome);
        $tipoEsc  = $db->real_escape_string($tipo);
        $dadosEsc = $db->real_escape_string($dados);

        // ON DUPLICATE KEY UPDATE: se a imagem já existir (mesmo nome),
        // atualiza o conteúdo em vez de dar erro. Assim reenviar é seguro.
        $sql = "INSERT INTO imagens (nome, tipo, dados, tamanho)
                VALUES ('$nomeEsc', '$tipoEsc', '$dadosEsc', $tamanho)
                ON DUPLICATE KEY UPDATE tipo = VALUES(tipo), dados = VALUES(dados), tamanho = VALUES(tamanho)";

        return (bool) $db->query($sql);
    } catch (Throwable $e) {
        error_log('imagem_salvar_no_banco: ' . $e->getMessage());
        return false;
    }
}

/**
 * Informa se o arquivo é mesmo uma imagem, olhando pela extensão.
 * Isso evita importar arquivos como .htaccess, .php ou .txt por engano.
 */
function imagem_e_arquivo_de_imagem(string $nome): bool
{
    // Extensão em letras minúsculas
    $extensao = strtolower(pathinfo($nome, PATHINFO_EXTENSION));

    // Extensões aceitas como imagem
    $extensoesValidas = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'ico', 'avif'];

    return in_array($extensao, $extensoesValidas, true);
}

/**
 * Lê uma imagem do banco de dados pelo nome.
 *
 * Devolve um array com 'tipo' e 'dados', ou null quando não existe.
 */
function imagem_ler_do_banco(string $nome): ?array
{
    if ($nome === '') {
        return null;
    }

    try {
        $db = db_connect();

        // Verifica rapidamente se a tabela existe; se não existir, retorna null.
        $checa = $db->query("SHOW TABLES LIKE 'imagens'");
        if (!$checa || $checa->num_rows === 0) {
            return null;
        }

        $stmt = $db->prepare('SELECT tipo, dados FROM imagens WHERE nome = ? LIMIT 1');
        $stmt->bind_param('s', $nome);
        $stmt->execute();

        // Lê o resultado como array associativo. Importante: usar fetch_assoc()
        // (e não bind_result) para que o conteúdo binário do LONGBLOB chegue
        // completo, sem ser cortado.
        $resultado = $stmt->get_result();
        if (!$resultado) {
            return null;
        }

        $linha = $resultado->fetch_assoc();
        if (!$linha || $linha['dados'] === null || $linha['dados'] === '') {
            return null;
        }

        return ['tipo' => (string) $linha['tipo'], 'dados' => (string) $linha['dados']];
    } catch (Throwable $e) {
        error_log('imagem_ler_do_banco: ' . $e->getMessage());
        return null;
    }
}

/**
 * Informa se uma imagem já está guardada no banco de dados.
 */
function imagem_existe_no_banco(string $nome): bool
{
    if ($nome === '') {
        return false;
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT 1 FROM imagens WHERE nome = ? LIMIT 1');
        $stmt->bind_param('s', $nome);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Importa para o banco TODAS as imagens que estão na pasta assets/img/.
 *
 * Roda uma única vez e é seguro repetir: se a imagem já estiver no banco,
 * ela é ignorada. Pode ser chamada pela página de administração ou pela
 * linha de comando (php includes/imagem.php).
 *
 * Devolve um resumo: ['enviadas' => n, 'ignoradas' => n, 'falhas' => n]
 */
function imagens_importar_da_pasta(): array
{
    // Contadores do resumo final
    $enviadas = 0;
    $ignoradas = 0;
    $falhas = 0;

    // Pasta onde estão as imagens do site
    $pasta = __DIR__ . '/../assets/img/';

    if (!is_dir($pasta)) {
        return ['enviadas' => 0, 'ignoradas' => 0, 'falhas' => 0];
    }

    imagens_garantir_tabela();

    // Percorre a pasta e todas as subpastas (ex.: assets/img/pagamento/)
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterador as $arquivo) {
        // Ignora pastas (só queremos arquivos)
        if (!$arquivo->isFile()) {
            continue;
        }

        // Nome do arquivo, ex.: "cpu-ryzen.png"
        $nome = $arquivo->getFilename();

        // Se já estiver no banco, não faz nada (evita retrabalho)
        if (imagem_existe_no_banco($nome)) {
            $ignoradas++;
            continue;
        }

        // Lê o conteúdo do arquivo e grava no banco
        $dados = @file_get_contents($arquivo->getPathname());
        if ($dados === false) {
            $falhas++;
            continue;
        }

        if (imagem_salvar_no_banco($nome, $dados)) {
            $enviadas++;
        } else {
            $falhas++;
        }
    }

    return ['enviadas' => $enviadas, 'ignoradas' => $ignoradas, 'falhas' => $falhas];
}

/**
 * Monta o endereço (URL) que o navegador deve usar para exibir a imagem.
 *
 * IMPORTANTE: devolvemos o mesmo caminho de antes (assets/img/arquivo.png),
 * porém servido pelo script imagem.php. Assim nenhuma página precisa mudar:
 * o endereço continua igual, mas quem responde é o banco de dados.
 *
 * Uso nas páginas:  <img src="<?= imagem_url('cpu-ryzen.png') ?>">
 */
function imagem_url(string $nome): string
{
    // Nome vazio não pode gerar link quebrado
    if ($nome === '') {
        return '';
    }

    // Caminho antigo: assets/img/arquivo.png
    // O arquivo imagem.php entende esse endereço e busca no banco primeiro.
    return base_url() . '/assets/img/' . rawurlencode($nome);
}