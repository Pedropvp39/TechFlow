<?php
/**
 * Gera imagens PNG únicas para cada produto
 * Cria um gradiente diferente com número sequencial
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../php/conexao.php';

$img_dir = __DIR__ . '../assets/img';
if (!is_dir($img_dir)) {
    mkdir($img_dir, 0755, true);
}

$cores_por_categoria = [
    'Processadores' => ['#1a4d94', '#2d7fb8'],
    'Placas de vídeo' => ['#2d5a3d', '#3d8c52'],
    'Memória RAM' => ['#6b2c5e', '#9b4a7d'],
    'Armazenamento' => ['#8b4513', '#cd853f'],
    'Placas-mãe' => ['#1a1a3e', '#2d2d5f'],
    'Gabinetes' => ['#2f4f4f', '#556b6f'],
    'Fontes' => ['#5a4a00', '#8b7500'],
    'Refrigeração' => ['#003d66', '#0066cc'],
    'Monitors' => ['#1a1a1a', '#404040'],
    'Teclados' => ['#4a0e0e', '#8b1a1a'],
    'Mouses' => ['#1a3a1a', '#2d5c2d'],
    'Headsets' => ['#3a1a4a', '#5c2d7d'],
];

$mapeamento_imagens = [
    'Processadores' => 'processador',
    'Placas de vídeo' => 'placa-video',
    'Memória RAM' => 'memoria-ram',
    'Armazenamento' => 'armazenamento',
    'Placas-mãe' => 'placa-mae',
    'Gabinetes' => 'gabinete',
    'Fontes' => 'fonte',
    'Refrigeração' => 'refrigeracao',
    'Monitors' => 'monitor',
    'Teclados' => 'teclado',
    'Mouses' => 'mouse',
    'Headsets' => 'headset',
];

function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    return sscanf($hex, "%02x%02x%02x");
}

function gerarImagem($largura, $altura, $cor1, $cor2, $numero, $nome_categoria) {
    $img = imagecreatetruecolor($largura, $altura);
    
    [$r1, $g1, $b1] = hexToRgb($cor1);
    [$r2, $g2, $b2] = hexToRgb($cor2);
    
    for ($y = 0; $y < $altura; $y++) {
        $ratio = $y / $altura;
        $r = (int)($r1 + ($r2 - $r1) * $ratio);
        $g = (int)($g1 + ($g2 - $g1) * $ratio);
        $b = (int)($b1 + ($b2 - $b1) * $ratio);
        
        $cor = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $largura, $y, $cor);
    }
    
    $branco = imagecolorallocate($img, 255, 255, 255);
    $cinza = imagecolorallocate($img, 200, 200, 200);
    
    $fontsize = 3;
    $x = ($largura - (strlen($numero) * 8)) / 2;
    $y = ($altura / 2) - 10;
    imagestring($img, $fontsize, $x, $y, $numero, $branco);
    
    imagestring($img, 2, 10, $altura - 25, $nome_categoria, $cinza);
    
    return $img;
}

try {
    $db = db_connect();
    $result = $db->query('SELECT DISTINCT categoria FROM produtos ORDER BY categoria ASC');
    
    if (!$result) {
        throw new Exception($db->error);
    }
    
    $contador_gerado = 0;
    
    while ($row = $result->fetch_assoc()) {
        $categoria = $row['categoria'];
        $prefixo = $mapeamento_imagens[$categoria] ?? 'produto';
        $cores = $cores_por_categoria[$categoria] ?? ['#4a4a4a', '#7a7a7a'];
        
        $stmt = $db->prepare('SELECT COUNT(*) as total FROM produtos WHERE categoria = ?');
        $stmt->bind_param('s', $categoria);
        $stmt->execute();
        $countResult = $stmt->get_result();
        $countRow = $countResult->fetch_assoc();
        $total = $countRow['total'];
        
        for ($i = 1; $i <= $total; $i++) {
            $numero = str_pad($i, 3, '0', STR_PAD_LEFT);
            $nomeArquivo = $prefixo . '-' . $numero . '.png';
            $caminhoCompleto = $img_dir . '/' . $nomeArquivo;
            
            if (!file_exists($caminhoCompleto)) {
                $img = gerarImagem(400, 300, $cores[0], $cores[1], $numero, $categoria);
                imagepng($img, $caminhoCompleto);
                imagedestroy($img);
                $contador_gerado++;
            }
        }
    }
    
    echo "<div style='font-family: Arial; padding: 40px; background: #1c0a0d; color: #f7edee; border-radius: 12px; max-width: 600px; margin: 40px auto;'>";
    echo "<h1 style='color: #ec3737; margin-bottom: 20px;'>🖼️ Imagens Geradas!</h1>";
    echo "<p style='font-size: 16px; margin-bottom: 15px;'><strong>$contador_gerado imagens PNG</strong> foram criadas com sucesso.</p>";
    echo "<p style='color: #c9adb0; margin-bottom: 20px;'>Cada imagem tem um gradiente único com número e categoria.</p>";
    
    echo "<h3 style='margin-top: 30px; margin-bottom: 15px;'>📁 Localização:</h3>";
    echo "<p style='background: rgba(236,55,55,0.1); padding: 15px; border-radius: 8px; font-family: monospace; word-break: break-all;'>";
    echo htmlspecialchars($img_dir);
    echo "</p>";
    
    echo "<a href='" . base_url() . "/pages/produtos.php' style='display: inline-block; background: #ec3737; color: #f7edee; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 20px;'>Ver produtos com imagens →</a>";
    echo "</div>";
    
} catch (Throwable $e) {
    echo "<div style='font-family: Arial; padding: 40px; background: #1c0a0d; color: #f7edee; border-radius: 12px; max-width: 500px; margin: 40px auto;'>";
    echo "<h1 style='color: #ec3737; margin-bottom: 20px;'>❌ Erro</h1>";
    echo "<p style='color: #c9adb0;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
