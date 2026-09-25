<?php
/**
 * Script de manutenção (linha de comando):
 * Corrige as imagens e as categorias dos produtos no banco de dados.
 *
 * PROBLEMAS QUE ESTE SCRIPT RESOLVE:
 *
 *  1) Produtos apontando para imagens que NÃO existem.
 *     O banco guardava nomes como "cpu-ryzen.png" e "gpu-rtx.png", mas
 *     esses arquivos nunca existiram na pasta assets/img/. Resultado: os
 *     produtos apareciam com imagem quebrada no site.
 *     Solução: apontar cada produto para uma FOTO REAL que já está
 *     cadastrada no banco, da mesma categoria do produto.
 *
 *  2) Categorias com acentuação corrompida (mojibake).
 *     Ex.: "Memria RAM" e "Placas de vdeo", que deveriam ser
 *     "Memória RAM" e "Placas de vídeo".
 *
 * Uso: php php/corrigir-imagens-produtos.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/imagem.php';

$db = db_connect();

echo "=== 1) Corrigindo a acentuação das categorias ===\n";

// Lista de correções: nome errado => nome certo
$categoriasCorretas = [
    'Processadores'    => 'Processadores',
    'Placas de vdeo'   => 'Placas de vídeo',
    'Placas de vídeo'  => 'Placas de vídeo',
    'Memria RAM'       => 'Memória RAM',
    'Memória RAM'      => 'Memória RAM',
    'Armazenamento'    => 'Armazenamento',
    'Placas-me'        => 'Placas-mãe',
    'Placas-mãe'       => 'Placas-mãe',
    'Gabinetes'        => 'Gabinetes',
    'Fontes'           => 'Fontes',
    'Refrigerao'       => 'Refrigeração',
    'Refrigeração'     => 'Refrigeração',
    'PC'               => 'PC',
];

foreach ($categoriasCorretas as $errada => $certa) {
    // Não faz nada quando o nome já está certo
    if ($errada === $certa) {
        continue;
    }

    $stmt = $db->prepare('UPDATE produtos SET categoria = ? WHERE categoria = ?');
    $stmt->bind_param('ss', $certa, $errada);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "  \"$errada\" -> \"$certa\" ($stmt->affected_rows produtos)\n";
    }
}

echo "\n=== 2) Escolhendo uma foto real para cada categoria ===\n";

// Este é o mapa: categoria do produto => arquivo de imagem que será usado.
//
// IMPORTANTE: cada imagem abaixo foi CONFERIDA visualmente antes de ser
// escolhida, para garantir que mostra a peça certa. Não são suposições.
// O que foi identificado em cada foto:
//   prod_1789396820_2273.png -> CPU AMD Ryzen 5 (caixa)
//   prod_1789396833_4914.png -> GPU Gigabyte GeForce RTX 4060
//   prod_1789394997_2116.png -> SSD NVMe Western Digital (WD Green)
//   prod_1789733219_6138.jpg -> Memória Kingston Fury Beast DDR5 RGB
//   prod_1789733251_8257.jpg -> Placa-mãe MSI B650M Gaming WiFi
//   prod_1789733264_1198.jpg -> Gabinete de PC
//   prod_1789733280_5578.jpg -> Fonte Cooler Master MWE Gold 750 V3
//   prod_1789733296_3051.jpg -> Water Cooler 240mm RGB
//   prod_1789733306_4941.jpg -> PC Gamer completo montado
$fotoPorCategoria = [
    'Processadores'   => 'prod_1789396820_2273.png', // CPU AMD Ryzen (conferido)
    'Placas de vídeo' => 'prod_1789396833_4914.png', // GPU RTX 4060 (conferido)
    'Armazenamento'   => 'prod_1789394997_2116.png', // SSD NVMe WD (conferido)
    'Memória RAM'     => 'prod_1789733219_6138.jpg', // Memória DDR5 RGB (conferido)
    'Placas-mãe'      => 'prod_1789733251_8257.jpg', // Placa-mãe MSI B650M (conferido)
    'Gabinetes'       => 'prod_1789733264_1198.jpg', // Gabinete de PC (conferido)
    'Fontes'          => 'prod_1789733280_5578.jpg', // Fonte 750W Gold (conferido)
    'Refrigeração'    => 'prod_1789733296_3051.jpg', // Water Cooler 240mm (conferido)
    'PC'              => 'prod_1789733306_4941.jpg', // PC Gamer montado (conferido)
];

// Mostra quais dessas imagens realmente existem (no banco ou em arquivo)
foreach ($fotoPorCategoria as $categoria => $arquivo) {
    $noBanco = imagem_existe_no_banco($arquivo);
    $ehArquivo = is_file(__DIR__ . '/../assets/img/' . $arquivo);
    $situacao = $noBanco ? 'no banco' : ($ehArquivo ? 'em arquivo' : 'NÃO EXISTE');
    echo sprintf("  %-16s -> %-30s (%s)\n", $categoria, $arquivo, $situacao);
}

echo "\n=== 3) Atualizando os produtos ===\n";

// Busca todos os produtos e corrige a imagem de cada um
$resultado = $db->query('SELECT id, nome, categoria, imagem FROM produtos ORDER BY id');
$atualizados = 0;
$jaCorretos = 0;
$semCategoria = 0;

while ($produto = $resultado->fetch_assoc()) {
    $categoria = $produto['categoria'];
    $imagemAtual = $produto['imagem'];

    // A imagem atual funciona? (existe no banco ou como arquivo)
    $funciona = imagem_existe_no_banco($imagemAtual)
        || is_file(__DIR__ . '/../assets/img/' . $imagemAtual);

    if ($funciona) {
        $jaCorretos++;
        continue;
    }

    // Não existe foto definida para esta categoria
    if (!isset($fotoPorCategoria[$categoria])) {
        $semCategoria++;
        echo "  [sem mapa] produto {$produto['id']} ({$produto['nome']}) categoria \"$categoria\"\n";
        continue;
    }

    // Troca pela foto certa da categoria
    $novaImagem = $fotoPorCategoria[$categoria];
    $stmt = $db->prepare('UPDATE produtos SET imagem = ? WHERE id = ?');
    $stmt->bind_param('si', $novaImagem, $produto['id']);
    $stmt->execute();
    $atualizados++;
}

echo "  produtos com imagem já correta: $jaCorretos\n";
echo "  produtos corrigidos agora:      $atualizados\n";
if ($semCategoria > 0) {
    echo "  produtos sem mapa de imagem:    $semCategoria\n";
}

echo "\n=== 4) Conferência final ===\n";
$r = $db->query('SELECT DISTINCT categoria, imagem FROM produtos ORDER BY categoria');
while ($linha = $r->fetch_assoc()) {
    $ok = imagem_existe_no_banco($linha['imagem']) || is_file(__DIR__ . '/../assets/img/' . $linha['imagem']);
    echo sprintf("  %-16s -> %-30s %s\n", $linha['categoria'], $linha['imagem'], $ok ? 'OK' : 'QUEBRADA');
}