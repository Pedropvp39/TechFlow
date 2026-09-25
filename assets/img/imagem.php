<?php
/**
 * ============================================================================
 * IMAGEM.PHP — SERVIDOR DE IMAGENS DO SITE
 * ============================================================================
 *
 * Este arquivo entrega uma imagem para o navegador.
 *
 * A ordem de busca é:
 *   1) BANCO DE DADOS (tabela `imagens`) — garante que o site funciona em
 *      qualquer computador, pois a imagem vem junto com o banco;
 *   2) ARQUIVO em assets/img/ — plano B, usado apenas se a imagem ainda
 *      não tiver sido importada para o banco;
 *   3) Imagem padrão — último recurso, para nunca aparecer um "X" quebrado.
 *
 * Como usar:
 *   imagem.php?nome=cpu-ryzen.png
 *
 * Dica: o arquivo .htaccess na pasta assets/img/ faz com que os endereços
 * antigos (assets/img/cpu-ryzen.png) cheguem até aqui automaticamente.
 */

// Carrega a conexão e as funções de imagem
require_once __DIR__ . '/../includes/imagem.php';

/**
 * Envia a imagem para o navegador e encerra o script.
 *
 * $dados = conteúdo binário da imagem
 * $tipo  = tipo do arquivo (ex.: image/png)
 */
function imagem_responder(string $dados, string $tipo): void
{
    // Não deixa o navegador guardar versões antigas em cache por muito tempo
    header('Cache-Control: public, max-age=86400');
    header('Content-Type: ' . $tipo);
    header('Content-Length: ' . strlen($dados));

    // Envia os bytes da imagem
    echo $dados;
    exit();
}

// ---------------------------------------------------------------------------
// 1) Descobre qual imagem foi pedida
// ---------------------------------------------------------------------------
// Aceita ?nome=arquivo.png (nosso padrão).
$nome = isset($_GET['nome']) ? (string) $_GET['nome'] : '';

// Medida de segurança: impede que alguém tente sair da pasta com "../" (path traversal).
// basename() devolve apenas o nome do arquivo, sem nenhum diretório.
$nome = basename($nome);

// Se vier algo suspeito ou vazio, tratamos como "imagem padrão".
if ($nome === '' || $nome === '.' || $nome === '..') {
    $nome = 'default.png';
}

// ---------------------------------------------------------------------------
// 2) Tenta no BANCO DE DADOS (caminho principal)
// ---------------------------------------------------------------------------
$doBanco = imagem_ler_do_banco($nome);
if ($doBanco !== null && $doBanco['dados'] !== '') {
    imagem_responder($doBanco['dados'], $doBanco['tipo']);
}

// ---------------------------------------------------------------------------
// 3) Plano B: arquivo tradicional em assets/img/
// ---------------------------------------------------------------------------
// Só aceita nomes simples (sem barras) para manter a segurança.
$seguro = preg_match('/^[A-Za-z0-9._-]+$/', $nome) === 1;
$caminhoArquivo = __DIR__ . '/' . $nome;

if ($seguro && is_file($caminhoArquivo)) {
    // Descobre o tipo pelo próprio arquivo (mais confiável que pela extensão)
    $tipo = imagens_tipo_por_nome($nome);

    // Usa a detecção do PHP quando disponível
    if (function_exists('mime_content_type')) {
        $detectado = @mime_content_type($caminhoArquivo);
        if (is_string($detectado) && $detectado !== '') {
            $tipo = $detectado;
        }
    }

    $dados = @file_get_contents($caminhoArquivo);
    if ($dados !== false) {
        imagem_responder($dados, $tipo);
    }
}

// ---------------------------------------------------------------------------
// 4) Último recurso: imagem padrão do sistema
// ---------------------------------------------------------------------------
// Assim nenhuma página fica com imagem quebrada.
$padraoNoBanco = imagem_ler_do_banco('default.png');
if ($padraoNoBanco !== null) {
    imagem_responder($padraoNoBanco['dados'], $padraoNoBanco['tipo']);
}

$padraoArquivo = __DIR__ . '/default.png';
if (is_file($padraoArquivo)) {
    imagem_responder((string) file_get_contents($padraoArquivo), 'image/png');
}

// Se nem a imagem padrão existir, devolve "não encontrado" (404).
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Imagem não encontrada.';