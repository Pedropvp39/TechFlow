<?php
/**
 * Script de manutencao (linha de comando):
 * Conserta textos "mojibake" de forma SEGURA, trecho por trecho.
 *
 * O problema: alguns arquivos foram salvos por um editor que tratou o
 * conteudo UTF-8 como Latin-1, duplicando cada acento. Exemplo:
 *      "UsuÃ¡rio"   (deveria ser "Usuário")
 *      "SessÃ£o"    (deveria ser "Sessão")
 *
 * Por que NAO converter o arquivo inteiro de uma vez:
 * parte do arquivo esta CORRETA. Converter tudo estragaria os acentos
 * que ja estavam bons.
 *
 * Como este script resolve:
 *   1) Percorre o texto buscando apenas as sequencias corrompidas
 *      ("Ã", "ð", "â€" e "ï¸" seguidas dos bytes de continuacao);
 *   2) Junta cada bloco corrompido;
 *   3) Converte SOMENTE aquele bloco de volta para o caractere certo;
 *   4) Mantem todo o resto do arquivo exatamente como estava.
 *
 * Uso: php php/corrigir-codificacao.php
 */

$arquivos = [
    __DIR__ . '/../pages/admin-produtos.php',
    __DIR__ . '/../pages/painel.php',
];

/**
 * Corrige as sequencias corrompidas dentro de um texto,
 * tocando somente nos trechos que realmente estao com problema.
 */
function corrigir_mojibake(string $texto): string
{
    // O trecho corrompido funciona assim:
    //   "SessÃ£o" está gravado em bytes como:  53 65 73 73 C3 83 C2 A3 6F
    //   onde "C3 83" representa "Ã" e "C2 A3" representa "£".
    //   O caractere ORIGINAL correto é "ã" = C3 A3.
    //
    // Ou seja: cada caractere foi gravado duas vezes em UTF-8.
    // O conserto é ler esses pares e reconstruir o byte original.
    //
    // O padrão cobre os três tamanhos possíveis de caractere UTF-8:
    //   2 bytes (ex.: C3 A3 = ã, C2 A3 = £)
    //   3 bytes (ex.: E2 80 9C = " )
    //   4 bytes (ex.: F0 9F 97 91 = 🗑 )
    // Assim emojis e aspas curvas também são corrigidos.
    $padrao = '/(?:[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF4][\x80-\xBF]{3})+/u';

    return preg_replace_callback($padrao, function (array $m) {
        $bloco = $m[0];

        // Desfaz a gravação dupla:
        // 1) interpreta os bytes como caractere Latin-1 (recupera o byte real);
        // 2) lê esses bytes como UTF-8 (obtém o caractere correto).
        $bytes = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $bloco);
        if ($bytes === false || $bytes === '') {
            return $bloco;
        }

        // Só aceita o resultado se ele formar um caractere UTF-8 válido;
        // caso contrário, devolve o texto original sem mexer.
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            return $bloco;
        }

        return $bytes;
    }, $texto);
}

foreach ($arquivos as $arquivo) {
    // Quando este arquivo é incluído por outro script (ex.: um teste),
    // não executa a correção: apenas disponibiliza a função.
    if (defined('CORRIGIR_CODIFICACAO_SEM_EXECUTAR') && CORRIGIR_CODIFICACAO_SEM_EXECUTAR) {
        break;
    }

    if (!is_readable($arquivo)) {
        echo "IGNORADO (nao encontrado): $arquivo\n";
        continue;
    }

    // Le o arquivo e conta as corrupcoes antes do conserto
    $conteudo = file_get_contents($arquivo);
    $antes = preg_match_all('/Ã[\x{0080}-\x{00BF}]|ð[\x{0080}-\x{00FF}]|â€|ï¸/u', $conteudo);

    if ($antes === 0) {
        echo "OK (sem corrupcao): " . basename($arquivo) . "\n";
        continue;
    }

    // Aplica o conserto trecho por trecho
    $corrigido = corrigir_mojibake($conteudo);

    // Seguranca 1: o resultado precisa continuar sendo UTF-8 valido
    if (!mb_check_encoding($corrigido, 'UTF-8')) {
        echo "ATENCAO: conserto abortado em " . basename($arquivo) . "\n";
        continue;
    }

    // Seguranca 2: testa a sintaxe PHP antes de substituir o original
    $temporario = $arquivo . '.tmp';
    file_put_contents($temporario, $corrigido);

    $saida = [];
    $codigo = 0;
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($temporario) . ' 2>&1', $saida, $codigo);
    if ($codigo !== 0) {
        @unlink($temporario);
        echo "ATENCAO: sintaxe invalida apos conserto em " . basename($arquivo) . ", nada foi gravado.\n";
        continue;
    }

    // Tudo certo: substitui o arquivo original pelo corrigido
    rename($temporario, $arquivo);

    // Conta as corrupcoes restantes
    $depois = preg_match_all('/Ã[\x{0080}-\x{00BF}]|ð[\x{0080}-\x{00FF}]|â€|ï¸/u', $corrigido);

    echo basename($arquivo) . ": antes=$antes depois=$depois\n";
}