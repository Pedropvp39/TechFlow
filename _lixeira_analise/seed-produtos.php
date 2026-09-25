<?php
/**
 * Script para popular o banco com 15 produtos
 * Acesse: http://localhost/Projeto-Integrador/php/seed-produtos.php
 * Execute apenas UMA vez
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../php/conexao.php';

require_admin();

// Categorias base e suas imagens
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
    'Pads' => ['mousepad.png', 'mousepad-grande.png'],
    'Cabos' => ['cabo-hdmi.png', 'cabo-usb.png'],
    'Adaptadores' => ['adaptador-dp.png', 'adaptador-usb-c.png'],
];

// Nomes de produtos por categoria
$nomes_produtos = [
    'Processadores' => ['AMD Ryzen', 'Intel Core', 'AMD EPYC', 'Intel Xeon'],
    'Placas de vídeo' => ['NVIDIA RTX', 'AMD Radeon', 'Intel Arc', 'NVIDIA GTX'],
    'Memória RAM' => ['Corsair Vengeance', 'Kingston HyperX', 'G.Skill Trident', 'Team Elite', 'ADATA XPG'],
    'Armazenamento' => ['Samsung SSD', 'WD Black', 'Crucial MX', 'Kingston NV', 'Sabrent Rocket'],
    'Placas-mãe' => ['ASUS ROG', 'MSI MPG', 'Gigabyte Aorus', 'ASRock Phantom', 'EVGA Z790'],
    'Gabinetes' => ['NZXT H510', 'Corsair Crystal', 'Thermaltake Core', 'Fractal Design', 'Lian Li'],
    'Fontes' => ['Corsair RM', 'EVGA SuperNOVA', 'Seasonic Focus', 'Thermaltake Toughpower', 'MSI MPG'],
    'Refrigeração' => ['Noctua NH', 'BeQuiet! Dark Rock', 'Corsair iCUE', 'NZXT Kraken', 'Thermaltake Liquid'],
    'Monitors' => ['ASUS ROG', 'LG UltraWide', 'Dell S2721', 'BenQ EW', 'AOC G2'],
    'Teclados' => ['Corsair K95', 'Razer DeathStalker', 'SteelSeries Apex', 'Logitech G Pro', 'ASUS ROG'],
    'Mouses' => ['Razer DeathAdder', 'SteelSeries Rival', 'Corsair Dark Core', 'Logitech G502', 'ASUS ROG'],
    'Headsets' => ['HyperX Cloud', 'SteelSeries Arctis', 'Corsair Virtuoso', 'Razer Kraken', 'ASUS ROG'],
    'Pads' => ['SteelSeries QcK', 'Corsair MM', 'ASUS ROG Scabbard', 'Razer Gigantus', 'Logitech G440'],
    'Cabos' => ['Belkin HDMI', 'Corsair USB-C', 'AmazonBasics DP', 'Anker USB-A', 'Tripp Lite HDMI'],
    'Adaptadores' => ['Belkin USB-C HUB', 'Corsair TB3', 'Anker USB Adapter', 'StarTech DP', 'Tripp Lite USB'],
];

$descricoes = [
    'Processadores' => 'Ideal para montar um PC equilibrado e rápido.',
    'Placas de vídeo' => 'Excelente desempenho para jogos em alta qualidade.',
    'Memória RAM' => 'Mais velocidade e estabilidade para o seu setup.',
    'Armazenamento' => 'Velocidade de boot e carregamento garantidos.',
    'Placas-mãe' => 'Suporte a componentes modernos e expansível.',
    'Gabinetes' => 'Espaço e circulação de ar para builds profissionais.',
    'Fontes' => 'Alta eficiência e proteção para seus componentes.',
    'Refrigeração' => 'Mantém sua CPU fria em qualquer situação.',
    'Monitors' => 'Imagem clara e cores precisas para produção.',
    'Teclados' => 'Digitação precisa e confortável para longas sessões.',
    'Mouses' => 'Precisão e conforto para gaming e trabalho.',
    'Headsets' => 'Áudio cristalino e microfone de qualidade.',
    'Pads' => 'Superfície otimizada para movimento fluido.',
    'Cabos' => 'Transmissão estável e rápida de dados.',
    'Adaptadores' => 'Compatibilidade com múltiplos dispositivos.',
];

$precos_base = [
    'Processadores' => [399, 599, 899, 1099, 1299, 1599, 1899, 2499, 3999, 9999],
    'Placas de vídeo' => [1299, 1699, 1999, 2499, 2999, 3499, 4499, 5999, 7999, 12999],
    'Memória RAM' => [149, 199, 299, 399, 499, 649, 799, 999, 1299, 1799],
    'Armazenamento' => [149, 249, 349, 499, 649, 799, 1099, 1499, 1999, 2999],
    'Placas-mãe' => [349, 499, 699, 899, 1099, 1399, 1699, 1999, 2499, 3499],
    'Gabinetes' => [199, 299, 399, 499, 599, 799, 999, 1299, 1699, 2499],
    'Fontes' => [249, 349, 449, 599, 799, 999, 1299, 1599, 1999, 2999],
    'Refrigeração' => [99, 149, 249, 349, 499, 699, 999, 1299, 1599, 1999],
    'Monitors' => [499, 699, 899, 1099, 1299, 1599, 1999, 2499, 2999, 3999],
    'Teclados' => [199, 299, 399, 499, 699, 899, 1099, 1399, 1699, 1999],
    'Mouses' => [99, 149, 199, 299, 399, 499, 699, 899, 1099, 1299],
    'Headsets' => [199, 299, 399, 499, 699, 899, 1099, 1399, 1699, 1999],
    'Pads' => [49, 79, 99, 129, 149, 199, 249, 299, 399, 499],
    'Cabos' => [19, 29, 39, 49, 59, 79, 99, 129, 159, 199],
    'Adaptadores' => [29, 49, 59, 79, 99, 149, 199, 249, 299, 399],
];

// Limpar e recriar com seed
try {
    $db = db_connect();
    
    // Limpa produtos existentes para não acumular com os antigos
    $db->query('TRUNCATE TABLE produtos');
    
    $count = 0;
    $total_desejado = 15;
    $categorias_keys = array_keys($categorias_base);
    
    for ($i = 1; $i <= $total_desejado; $i++) {
        // Seleciona uma categoria
        $categoria = $categorias_keys[array_rand($categorias_keys)];
        
        $imagens = $categorias_base[$categoria];
        $nomes = $nomes_produtos[$categoria] ?? ['Produto'];
        $precos = $precos_base[$categoria] ?? [99.99];
        $desc = $descricoes[$categoria] ?? 'Produto de qualidade superior.';
        
        $nome = $nomes[array_rand($nomes)];
        $suffix = "v" . $i;
        $nome_completo = "$nome $suffix";
        
        $preco = $precos[array_rand($precos)] + (rand(-20, 50));
        $imagem = $imagens[array_rand($imagens)];
        $destaque = rand(0, 100) < 15 ? 1 : 0;
        
        $stmt = $db->prepare('INSERT INTO produtos (nome, categoria, preco, descricao, imagem, destaque) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssdssi', $nome_completo, $categoria, $preco, $desc, $imagem, $destaque);
        
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    http_response_code(200);
    echo "<h1 style='color: #ec3737; font-family: Arial;'>✅ Seed concluído!</h1>";
    echo "<p style='font-family: Arial; font-size: 16px;'><strong>$count produtos</strong> foram adicionados ao banco de dados.</p>";
    echo "<p style='font-family: Arial; color: #8a6d70;'><a href='" . base_url() . "/pages/produtos.php'>Ver produtos</a></p>";
    
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1 style='color: #ec3737; font-family: Arial;'>❌ Erro ao popular banco</h1>";
    echo "<p style='font-family: Arial; color: #c9adb0;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit();
}