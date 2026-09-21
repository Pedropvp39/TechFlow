<?php
/**
 * Atualiza todos os produtos com imagens únicas por categoria
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../php/conexao.php';

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

try {
    $db = db_connect();
    
    // Pegar todos os produtos agrupados por categoria
    $result = $db->query('SELECT id, categoria FROM produtos ORDER BY categoria ASC, id ASC');
    
    if (!$result) {
        throw new Exception($db->error);
    }
    
    $contador_por_categoria = [];
    $updates = 0;
    
    while ($row = $result->fetch_assoc()) {
        $categoria = $row['categoria'];
        $id = $row['id'];
        
        // Inicializar contador da categoria se não existir
        if (!isset($contador_por_categoria[$categoria])) {
            $contador_por_categoria[$categoria] = 0;
        }
        
        $contador_por_categoria[$categoria]++;
        $numero = str_pad($contador_por_categoria[$categoria], 3, '0', STR_PAD_LEFT);
        
        // Gerar nome de imagem único
        $prefixo = $mapeamento_imagens[$categoria] ?? 'produto';
        $imagem = $prefixo . '-' . $numero . '.png';
        
        // Atualizar no banco
        $stmt = $db->prepare('UPDATE produtos SET imagem = ? WHERE id = ?');
        $stmt->bind_param('si', $imagem, $id);
        
        if ($stmt->execute()) {
            $updates++;
        }
    }
    
    echo "<div style='font-family: Arial; padding: 40px; background: #1c0a0d; color: #f7edee; border-radius: 12px; max-width: 600px; margin: 40px auto;'>";
    echo "<h1 style='color: #ec3737; margin-bottom: 20px;'>✅ Imagens Atualizadas!</h1>";
    echo "<p style='font-size: 16px; margin-bottom: 15px;'><strong>$updates produtos</strong> agora têm imagens únicas e sem repetição.</p>";
    
    echo "<h3 style='margin-top: 30px; margin-bottom: 15px;'>📋 Padrão de imagens por categoria:</h3>";
    echo "<ul style='list-style: none; padding: 0;'>";
    
    foreach ($mapeamento_imagens as $categoria => $prefixo) {
        $count = $contador_por_categoria[$categoria] ?? 0;
        if ($count > 0) {
            echo "<li style='padding: 8px 0; border-bottom: 1px solid rgba(236,55,55,0.16);'>";
            echo "<strong>$categoria:</strong> $prefixo-001.png até $prefixo-" . str_pad($count, 3, '0', STR_PAD_LEFT) . ".png";
            echo "</li>";
        }
    }
    
    echo "</ul>";
    
    echo "<p style='color: #c9adb0; margin-top: 25px; font-size: 14px;'>";
    echo "💡 <strong>Próximo passo:</strong> Você pode agora criar/adicionar as imagens correspondentes na pasta <code>/assets/img/</code>";
    echo "</p>";
    
    echo "<a href='" . base_url() . "/pages/produtos.php' style='display: inline-block; background: #ec3737; color: #f7edee; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 20px;'>Ver produtos →</a>";
    echo "</div>";
    
} catch (Throwable $e) {
    echo "<div style='font-family: Arial; padding: 40px; background: #1c0a0d; color: #f7edee; border-radius: 12px; max-width: 500px; margin: 40px auto;'>";
    echo "<h1 style='color: #ec3737; margin-bottom: 20px;'>❌ Erro</h1>";
    echo "<p style='color: #c9adb0;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
