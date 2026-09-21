<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../php/conexao.php';

$produtos = [];

// Expandir com mais produtos variados
$categorias_base = [
    'Processadores' => ['cpu-ryzen.png', 'cpu-intel.png'],
    'Placas de vídeo' => ['gpu-rtx.png', 'gpu-radeon.png'],
    'Memória RAM' => ['ram-ddr5.png', 'ram-ddr4.png'],
    'Armazenamento' => ['ssd.png', 'hdd.png'],
    'Placas-mãe' => ['motherboard.png', 'motherboard-b650.png'],
    'Gabinetes' => ['gabinete.png', 'gabinete-gamer.png'],
    'Fontes' => ['fonte.png', 'fonte-modular.png'],
    'Refrigeração' => ['cooler.png', 'water-cooler.png'],
    'Monitors' => ['monitor.png', 'monitor-4k.png'],
    'Teclados' => ['teclado-mecanico.png', 'teclado-wireless.png'],
    'Mouses' => ['mouse-gamer.png', 'mouse-wireless.png'],
    'Headsets' => ['headset-gamer.png', 'headset-wireless.png'],
];

$nomes_base = [
    'Processadores' => ['AMD Ryzen', 'Intel Core', 'AMD EPYC', 'Intel Xeon'],
    'Placas de vídeo' => ['NVIDIA RTX', 'AMD Radeon', 'Intel Arc', 'NVIDIA GTX'],
    'Memória RAM' => ['Corsair', 'Kingston', 'G.Skill', 'Team', 'ADATA'],
    'Armazenamento' => ['Samsung', 'WD', 'Crucial', 'Kingston', 'Sabrent'],
    'Placas-mãe' => ['ASUS ROG', 'MSI MPG', 'Gigabyte', 'ASRock', 'EVGA'],
    'Gabinetes' => ['NZXT', 'Corsair', 'Thermaltake', 'Fractal', 'Lian Li'],
    'Fontes' => ['Corsair', 'EVGA', 'Seasonic', 'Thermaltake', 'MSI'],
    'Refrigeração' => ['Noctua', 'BeQuiet', 'Corsair', 'NZXT', 'Thermaltake'],
    'Monitors' => ['ASUS', 'LG', 'Dell', 'BenQ', 'AOC'],
    'Teclados' => ['Corsair', 'Razer', 'SteelSeries', 'Logitech', 'ASUS'],
    'Mouses' => ['Razer', 'SteelSeries', 'Corsair', 'Logitech', 'ASUS'],
    'Headsets' => ['HyperX', 'SteelSeries', 'Corsair', 'Razer', 'ASUS'],
];

$descricoes = [
    'Processadores' => 'Processador de alta performance para seu PC.',
    'Placas de vídeo' => 'Placa de vídeo profissional para gaming e renderização.',
    'Memória RAM' => 'Memória RAM rápida e confiável.',
    'Armazenamento' => 'Armazenamento SSD/HDD de qualidade superior.',
    'Placas-mãe' => 'Placa-mãe robusta e compatível.',
    'Gabinetes' => 'Gabinete espaçoso com boa circulação de ar.',
    'Fontes' => 'Fonte de alimentação confiável e eficiente.',
    'Refrigeração' => 'Sistema de refrigeração eficaz.',
    'Monitors' => 'Monitor com excelente qualidade de imagem.',
    'Teclados' => 'Teclado mecânico/wireless de alta qualidade.',
    'Mouses' => 'Mouse preciso e confortável.',
    'Headsets' => 'Headset com áudio cristalino.',
];

$precos_base = [
    'Processadores' => [399, 599, 899, 1099, 1299, 1599, 1899, 2499],
    'Placas de vídeo' => [1299, 1699, 1999, 2499, 2999, 3499, 4499, 5999],
    'Memória RAM' => [149, 199, 299, 399, 499, 649, 799, 999],
    'Armazenamento' => [149, 249, 349, 499, 649, 799, 1099, 1499],
    'Placas-mãe' => [349, 499, 699, 899, 1099, 1399, 1699, 1999],
    'Gabinetes' => [199, 299, 399, 499, 599, 799, 999, 1299],
    'Fontes' => [249, 349, 449, 599, 799, 999, 1299, 1599],
    'Refrigeração' => [99, 149, 249, 349, 499, 699, 999, 1299],
    'Monitors' => [499, 699, 899, 1099, 1299, 1599, 1999, 2499],
    'Teclados' => [199, 299, 399, 499, 699, 899, 1099, 1399],
    'Mouses' => [99, 149, 199, 299, 399, 499, 699, 899],
    'Headsets' => [199, 299, 399, 499, 699, 899, 1099, 1399],
];

try {
    $db = db_connect();
    
    // Forçar recriação da tabela de produtos
    $db->query("DROP TABLE IF EXISTS produtos");
    $db->query("CREATE TABLE produtos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        categoria VARCHAR(80) NOT NULL,
        preco DECIMAL(10,2) NOT NULL,
        descricao TEXT NOT NULL,
        imagem VARCHAR(255) NOT NULL,
        destaque TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $count = 0;
    foreach ($categorias_base as $categoria => $imagens) {
        $nomes = $nomes_base[$categoria] ?? ['Produto'];
        $precos = $precos_base[$categoria] ?? [99.99];
        $desc = $descricoes[$categoria] ?? 'Produto de qualidade superior.';
        
        for ($i = 1; $i <= 50; $i++) {
            $nome = $nomes[array_rand($nomes)];
            $numero = str_pad($i, 3, '0', STR_PAD_LEFT);
            $nome_completo = "$nome $numero";
            
            $preco = $precos[array_rand($precos)] + (rand(-10, 30));
            $imagem = $imagens[array_rand($imagens)];
            $destaque = rand(0, 100) < 12 ? 1 : 0;
            
            $stmt = $db->prepare('INSERT INTO produtos (nome, categoria, preco, descricao, imagem, destaque) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssdssi', $nome_completo, $categoria, $preco, $desc, $imagem, $destaque);
            
            if ($stmt->execute()) {
                $count++;
            }
        }
    }
    
    echo "<div style='font-family: Arial; padding: 40px; background: #1c0a0d; color: #f7edee; border-radius: 12px; max-width: 500px; margin: 40px auto;'>";
    echo "<h1 style='color: #ec3737; margin-bottom: 20px;'>✅ Seed Concluído!</h1>";
    echo "<p style='font-size: 18px; margin-bottom: 15px;'><strong>$count produtos</strong> foram adicionados ao banco de dados.</p>";
    echo "<p style='color: #c9adb0; margin-bottom: 20px;'>12 categorias diferentes com produtos variados e preços realistas.</p>";
    echo "<a href='" . base_url() . "/pages/produtos.php' style='display: inline-block; background: #ec3737; color: #f7edee; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 15px;'>Ver todos os produtos →</a>";
    echo "</div>";
    
} catch (Throwable $e) {
    echo "<div style='font-family: Arial; padding: 40px; background: #1c0a0d; color: #f7edee; border-radius: 12px; max-width: 500px; margin: 40px auto;'>";
    echo "<h1 style='color: #ec3737; margin-bottom: 20px;'>❌ Erro</h1>";
    echo "<p style='color: #c9adb0;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
