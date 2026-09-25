<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../php/conexao.php';

function normalize_produto(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'nome' => (string) ($row['nome'] ?? ''),
        'categoria' => (string) ($row['categoria'] ?? ''),
        'preco' => (float) ($row['preco'] ?? 0),
        'descricao' => (string) ($row['descricao'] ?? ''),
        'imagem' => (string) ($row['imagem'] ?? 'default.png'),
        'destaque' => (bool) ((int) ($row['destaque'] ?? 0)),
    ];
}

function seed_initial_produtos(mysqli $db): void
{
    try {
        $check = $db->query("SELECT COUNT(*) AS total FROM produtos");
        if ($check) {
            // Não interrompe quando já existem produtos: registros de demonstração
            // ausentes ainda precisam ser criados para manter os links válidos.
        }

        $catalog = [
            [1, 'AMD Ryzen 5 5600', 'Processadores', 899.00, 'Ideal para montar um PC equilibrado e rápido para games e trabalho.', 'cpu-ryzen.png', 1],
            [2, 'GeForce RTX 4060', 'Placas de vídeo', 2199.00, 'Excelente desempenho para jogos em Full HD e 2K com ray tracing.', 'gpu-rtx.png', 1],
            [3, 'SSD NVMe 1TB', 'Armazenamento', 349.00, 'Mais velocidade de boot e carregamento de jogos e programas.', 'ssd.png', 1],
            [4, 'Memória RAM DDR5 32GB', 'Memória RAM', 749.00, 'Kit 2x16GB com mais velocidade e estabilidade para multitarefas.', 'ram.png', 0],
            [5, 'Placa-mãe B650 Gaming', 'Placas-mãe', 1099.00, 'Suporte a DDR5 e PCIe 4.0 para builds modernas e expansíveis.', 'motherboard.png', 0],
            [6, 'Gabinete Gamer Mid-Tower', 'Gabinetes', 459.00, 'Lateral em vidro temperado e fans RGB para exibir seu setup com estilo.', 'gabinete.png', 0],
            [7, 'Fonte 750W 80 Plus Gold', 'Fontes', 629.00, 'Fonte modular com alta eficiência e proteção para seus componentes.', 'fonte.png', 0],
            [8, 'Water Cooler 240mm', 'Refrigeração', 539.00, 'Refrigeração líquida com iluminação RGB para manter a CPU fria.', 'cooler.png', 0],
            [9, 'PC Gamer TechFlow RGB RTX 4060', 'PC', 4599.00, 'PC Gamer completo montado e testado com Ryzen 5, RTX 4060, 16GB RAM DDR5 e SSD 1TB.', 'gabinete.png', 1],
        ];

        // Quatro produtos adicionais por categoria, persistidos com IDs fixos.
        $extras = [
            ['Ryzen 7 5700X', 'Processadores', 1299, 'cpu-ryzen.png'], ['Ryzen 7 7800X3D', 'Processadores', 2399, 'cpu-ryzen.png'], ['Core i5 14400F', 'Processadores', 1199, 'cpu-ryzen.png'], ['Core i7 14700K', 'Processadores', 2499, 'cpu-ryzen.png'],
            ['GeForce RTX 4060 Ti', 'Placas de vídeo', 2699, 'gpu-rtx.png'], ['GeForce RTX 4070', 'Placas de vídeo', 3999, 'gpu-rtx.png'], ['Radeon RX 7600', 'Placas de vídeo', 2299, 'gpu-rtx.png'], ['GeForce RTX 4080 Super', 'Placas de vídeo', 6999, 'gpu-rtx.png'],
            ['Memória DDR4 16GB', 'Memória RAM', 299, 'ram.png'], ['Memória DDR5 16GB', 'Memória RAM', 399, 'ram.png'], ['Kit RAM DDR5 64GB', 'Memória RAM', 1299, 'ram.png'], ['Memória RGB 32GB', 'Memória RAM', 849, 'ram.png'],
            ['SSD NVMe 500GB', 'Armazenamento', 229, 'ssd.png'], ['SSD NVMe 2TB', 'Armazenamento', 699, 'ssd.png'], ['HD 2TB Sata', 'Armazenamento', 429, 'ssd.png'], ['SSD Sata 1TB', 'Armazenamento', 389, 'ssd.png'],
            ['Placa-mãe B550M', 'Placas-mãe', 699, 'motherboard.png'], ['Placa-mãe X670 Gaming', 'Placas-mãe', 1899, 'motherboard.png'], ['Placa-mãe H610M', 'Placas-mãe', 499, 'motherboard.png'], ['Placa-mãe Z790 Wi-Fi', 'Placas-mãe', 2199, 'motherboard.png'],
            ['Gabinete Compacto Airflow', 'Gabinetes', 329, 'gabinete.png'], ['Gabinete RGB Glass', 'Gabinetes', 579, 'gabinete.png'], ['Gabinete Full Tower', 'Gabinetes', 899, 'gabinete.png'], ['Gabinete Mesh Branco', 'Gabinetes', 649, 'gabinete.png'],
            ['Fonte 550W 80 Plus', 'Fontes', 399, 'fonte.png'], ['Fonte 850W Modular', 'Fontes', 899, 'fonte.png'], ['Fonte 1000W Gold', 'Fontes', 1199, 'fonte.png'], ['Fonte 650W Bronze', 'Fontes', 479, 'fonte.png'],
            ['Air Cooler 120mm', 'Refrigeração', 159, 'cooler.png'], ['Water Cooler 120mm', 'Refrigeração', 299, 'cooler.png'], ['Water Cooler 360mm', 'Refrigeração', 799, 'cooler.png'], ['Kit 3 Fans RGB', 'Refrigeração', 219, 'cooler.png'],
            ['PC Gamer Ryzen 5', 'PC', 3299, 'gabinete.png'], ['PC Gamer RTX 4060 Ti', 'PC', 5499, 'gabinete.png'], ['PC Gamer Ryzen 7', 'PC', 6499, 'gabinete.png'], ['PC Gamer Black Edition', 'PC', 7999, 'gabinete.png'],
        ];
        foreach ($extras as $offset => $extra) {
            $catalog[] = [$offset + 10, $extra[0], $extra[1], $extra[2], 'Componente selecionado para montar um computador rápido e confiável.', $extra[3], 0];
        }

        foreach ($catalog as $item) {
            $stmt = $db->prepare("INSERT INTO produtos (id, nome, categoria, preco, descricao, imagem, destaque) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE nome = VALUES(nome), preco = VALUES(preco)");
            $stmt->bind_param('issdssi', $item[0], $item[1], $item[2], $item[3], $item[4], $item[5], $item[6]);
            $stmt->execute();
        }
    } catch (Throwable $e) {
        error_log('seed_initial_produtos: ' . $e->getMessage());
    }
}

function get_produtos(): array
{
    try {
        $db = db_connect();
        seed_initial_produtos($db);

        $result = $db->query('SELECT * FROM produtos ORDER BY id ASC');
        if ($result && $result->num_rows > 0) {
            $produtos = [];
            while ($row = $result->fetch_assoc()) {
                $produtos[] = normalize_produto($row);
            }
            return $produtos;
        }
    } catch (Throwable $e) {
        error_log('get_produtos: ' . $e->getMessage());
    }

    return [
        [
            'id' => 1,
            'nome' => 'AMD Ryzen 5 5600',
            'categoria' => 'Processadores',
            'preco' => 899.00,
            'descricao' => 'Ideal para montar um PC equilibrado e rápido para games e trabalho.',
            'imagem' => 'cpu-ryzen.png',
            'destaque' => true,
        ],
        [
            'id' => 2,
            'nome' => 'GeForce RTX 4060',
            'categoria' => 'Placas de vídeo',
            'preco' => 2199.00,
            'descricao' => 'Excelente desempenho para jogos em Full HD e 2K com ray tracing.',
            'imagem' => 'gpu-rtx.png',
            'destaque' => true,
        ],
        [
            'id' => 3,
            'nome' => 'SSD NVMe 1TB',
            'categoria' => 'Armazenamento',
            'preco' => 349.00,
            'descricao' => 'Mais velocidade de boot e carregamento de jogos e programas.',
            'imagem' => 'ssd.png',
            'destaque' => true,
        ],
        [
            'id' => 4,
            'nome' => 'Memória RAM DDR5 32GB',
            'categoria' => 'Memória RAM',
            'preco' => 749.00,
            'descricao' => 'Kit 2x16GB com mais velocidade e estabilidade para multitarefas.',
            'imagem' => 'ram.png',
            'destaque' => false,
        ],
        [
            'id' => 5,
            'nome' => 'Placa-mãe B650 Gaming',
            'categoria' => 'Placas-mãe',
            'preco' => 1099.00,
            'descricao' => 'Suporte a DDR5 e PCIe 4.0 para builds modernas e expansíveis.',
            'imagem' => 'motherboard.png',
            'destaque' => false,
        ],
        [
            'id' => 6,
            'nome' => 'Gabinete Gamer Mid-Tower',
            'categoria' => 'Gabinetes',
            'preco' => 459.00,
            'descricao' => 'Lateral em vidro temperado e fans RGB para exibir seu setup com estilo.',
            'imagem' => 'gabinete.png',
            'destaque' => false,
        ],
        [
            'id' => 7,
            'nome' => 'Fonte 750W 80 Plus Gold',
            'categoria' => 'Fontes',
            'preco' => 629.00,
            'descricao' => 'Fonte modular com alta eficiência e proteção para seus componentes.',
            'imagem' => 'fonte.png',
            'destaque' => false,
        ],
        [
            'id' => 8,
            'nome' => 'Water Cooler 240mm',
            'categoria' => 'Refrigeração',
            'preco' => 539.00,
            'descricao' => 'Refrigeração líquida com iluminação RGB para manter a CPU fria.',
            'imagem' => 'cooler.png',
            'destaque' => false,
        ],
    ];
}

/**
 * Busca produtos do banco MySQL com suporte a filtros de categoria, busca por nome/descrição,
 * faixa de preço e ordenação.
 */
function get_produtos_filtrados(
    ?string $categoria = null,
    ?string $busca = null,
    ?float $precoMin = null,
    ?float $precoMax = null,
    ?string $ordem = null
): array {
    try {
        $db = db_connect();
        seed_initial_produtos($db);

        $sql = "SELECT * FROM produtos WHERE 1=1";
        $types = "";
        $params = [];

        if ($categoria !== null && $categoria !== '' && $categoria !== 'Todos') {
            $sql .= " AND categoria = ?";
            $types .= "s";
            $params[] = $categoria;
        }

        if ($busca !== null && trim($busca) !== '') {
            $likeBusca = '%' . trim($busca) . '%';
            $sql .= " AND (nome LIKE ? OR descricao LIKE ? OR categoria LIKE ?)";
            $types .= "sss";
            $params[] = $likeBusca;
            $params[] = $likeBusca;
            $params[] = $likeBusca;
        }

        if ($precoMin !== null && $precoMin > 0) {
            $sql .= " AND preco >= ?";
            $types .= "d";
            $params[] = $precoMin;
        }

        if ($precoMax !== null && $precoMax > 0) {
            $sql .= " AND preco <= ?";
            $types .= "d";
            $params[] = $precoMax;
        }

        if ($ordem === 'menor_preco') {
            $sql .= " ORDER BY preco ASC";
        } elseif ($ordem === 'maior_preco') {
            $sql .= " ORDER BY preco DESC";
        } elseif ($ordem === 'nome_asc') {
            $sql .= " ORDER BY nome ASC";
        } elseif ($ordem === 'nome_desc') {
            $sql .= " ORDER BY nome DESC";
        } else {
            $sql .= " ORDER BY id ASC";
        }

        $stmt = $db->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $produtos = [];
        while ($row = $result->fetch_assoc()) {
            $produtos[] = normalize_produto($row);
        }
        return $produtos;
    } catch (Throwable $e) {
        error_log('get_produtos_filtrados: ' . $e->getMessage());

        $produtos = get_produtos();
        $buscaNormalizada = mb_strtolower(trim((string) $busca));

        $produtos = array_filter($produtos, static function (array $produto) use ($categoria, $buscaNormalizada, $precoMin, $precoMax): bool {
            if ($categoria !== null && $categoria !== '' && $categoria !== 'Todos' && $produto['categoria'] !== $categoria) {
                return false;
            }

            $texto = mb_strtolower(implode(' ', [$produto['nome'], $produto['descricao'], $produto['categoria']]));
            if ($buscaNormalizada !== '' && !str_contains($texto, $buscaNormalizada)) {
                return false;
            }

            if ($precoMin !== null && $precoMin > 0 && $produto['preco'] < $precoMin) {
                return false;
            }

            return !($precoMax !== null && $precoMax > 0 && $produto['preco'] > $precoMax);
        });

        $produtos = array_values($produtos);
        usort($produtos, static function (array $a, array $b) use ($ordem): int {
            return match ($ordem) {
                'menor_preco' => $a['preco'] <=> $b['preco'],
                'maior_preco' => $b['preco'] <=> $a['preco'],
                'nome_asc' => strcasecmp($a['nome'], $b['nome']),
                'nome_desc' => strcasecmp($b['nome'], $a['nome']),
                default => $a['id'] <=> $b['id'],
            };
        });

        return $produtos;
    }
}

function get_produtos_destaque(): array
{
    return array_values(array_filter(get_produtos(), fn ($p) => $p['destaque']));
}

function get_loja_estatisticas(): array
{
    try {
        $db = db_connect();
        $produtos = $db->query('SELECT COUNT(*) AS total FROM produtos');
        $avaliacoes = $db->query("SELECT COUNT(*) AS total, COALESCE(AVG(nota), 0) AS media FROM avaliacoes_produtos WHERE status = 'Aprovado'");
        $produtoRow = $produtos ? $produtos->fetch_assoc() : [];
        $avaliacaoRow = $avaliacoes ? $avaliacoes->fetch_assoc() : [];

        return [
            'produtos' => (int) ($produtoRow['total'] ?? 0),
            'avaliacoes' => (int) ($avaliacaoRow['total'] ?? 0),
            'nota' => round((float) ($avaliacaoRow['media'] ?? 0), 1),
        ];
    } catch (Throwable $e) {
        error_log('get_loja_estatisticas: ' . $e->getMessage());
        $produtos = get_produtos();
        return ['produtos' => count($produtos), 'avaliacoes' => 0, 'nota' => 0.0];
    }
}

function get_produto(int $id): ?array
{
    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT * FROM produtos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) {
            return normalize_produto($row);
        }
    } catch (Throwable $e) {
        error_log('get_produto: ' . $e->getMessage());
    }

    foreach (get_produtos() as $p) {
        if ($p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function processar_upload_imagem(?array $file, string $default = 'default.png'): string
{
    if (!$file || empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    if (!in_array($ext, $allowed, true)) {
        return '';
    }

    $targetDir = __DIR__ . '/../assets/img/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $filename = 'prod_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $targetFile = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $filename;
    }

    return '';
}

function adicionar_produto(array $dados, ?array $file = null): array
{
    $nome = trim((string) ($dados['nome'] ?? ''));
    $categoria = trim((string) ($dados['categoria'] ?? ''));
    $preco = (float) ($dados['preco'] ?? 0);
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $destaque = !empty($dados['destaque']) ? 1 : 0;

    $imagemUpload = processar_upload_imagem($file);
    $imagem = $imagemUpload !== '' ? $imagemUpload : trim((string) ($dados['imagem'] ?? 'default.png'));
    if ($imagem === '') {
        $imagem = 'default.png';
    }

    if ($nome === '' || $categoria === '' || $preco <= 0 || $descricao === '') {
        return ['ok' => false, 'mensagem' => 'Preencha nome, categoria, preço e descrição do produto.'];
    }

    try {
        $db = db_connect();

        // Garante que a categoria exista na tabela categorias do MySQL
        adicionar_categoria($categoria, 'Produtos da categoria ' . $categoria);

        $stmt = $db->prepare('INSERT INTO produtos (nome, categoria, preco, descricao, imagem, destaque) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssdssi', $nome, $categoria, $preco, $descricao, $imagem, $destaque);
        $stmt->execute();
        return ['ok' => true, 'mensagem' => '✅ Produto "' . $nome . '" cadastrado e salvo com sucesso no MySQL!'];
    } catch (Throwable $e) {
        error_log('adicionar_produto: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível salvar o produto no MySQL: ' . $e->getMessage()];
    }
}

/**
 * Produtos relacionados (mesma categoria), excluindo o atual.
 */
function get_relacionados(array $produto, int $limite = 3): array
{
    $rel = array_filter(
        get_produtos(),
        fn ($p) => $p['categoria'] === $produto['categoria'] && $p['id'] !== $produto['id']
    );
    // Completa com outros produtos se houver poucos da mesma categoria.
    if (count($rel) < $limite) {
        foreach (get_produtos() as $p) {
            if ($p['id'] !== $produto['id'] && !isset($rel[$p['id'] - 1])) {
                $rel[] = $p;
            }
        }
    }
    return array_slice(array_values($rel), 0, $limite);
}

function normalize_categoria(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'nome' => (string) ($row['nome'] ?? ''),
        'desc' => (string) ($row['descricao'] ?? $row['desc'] ?? ''),
        'descricao' => (string) ($row['descricao'] ?? $row['desc'] ?? ''),
        'icone' => (string) ($row['icone'] ?? ''),
    ];
}

function seed_initial_categorias(mysqli $db): void
{
    try {
        $check = $db->query("SELECT COUNT(*) AS total FROM categorias");
        if ($check) {
            $row = $check->fetch_assoc();
            if ((int) ($row['total'] ?? 0) > 0) {
                return;
            }
        }

        $catalogoCategorias = [
            ['PCs Gamer', 'Computadores montados e configurados prontos para jogar.', 'gabinete.png'],
            ['Processadores', 'Desempenho para games, edição e multitarefas.', 'cpu-ryzen.png'],
            ['Placas de vídeo', 'Potência para jogos em alta qualidade.', 'gpu-rtx.png'],
            ['Memória RAM', 'Mais velocidade e estabilidade para o seu setup.', 'ram.png'],
            ['Armazenamento', 'SSD e HD com performance superior.', 'ssd.png'],
            ['Placas-mãe', 'Suporte a componentes modernos e expansível.', 'motherboard.png'],
            ['Gabinetes', 'Espaço e circulação de ar para builds profissionais.', 'gabinete.png'],
            ['Fontes', 'Alta eficiência e proteção para seus componentes.', 'fonte.png'],
            ['Refrigeração', 'Mantém sua CPU fria em qualquer situação.', 'cooler.png'],
        ];

        foreach ($catalogoCategorias as $cat) {
            $stmt = $db->prepare("INSERT INTO categorias (nome, descricao, icone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE descricao = VALUES(descricao)");
            $stmt->bind_param('sss', $cat[0], $cat[1], $cat[2]);
            $stmt->execute();
        }
    } catch (Throwable $e) {
        error_log('seed_initial_categorias: ' . $e->getMessage());
    }
}

function get_categorias(): array
{
    try {
        $db = db_connect();
        seed_initial_categorias($db);

        $result = $db->query('SELECT * FROM categorias ORDER BY id ASC');
        if ($result && $result->num_rows > 0) {
            $categorias = [];
            while ($row = $result->fetch_assoc()) {
                $categorias[] = normalize_categoria($row);
            }
            return $categorias;
        }
    } catch (Throwable $e) {
        error_log('get_categorias: ' . $e->getMessage());
    }

    return [
        ['id' => 1, 'nome' => 'Processadores', 'desc' => 'Desempenho para games, edição e multitarefas.', 'descricao' => 'Desempenho para games, edição e multitarefas.'],
        ['id' => 2, 'nome' => 'Placas de vídeo', 'desc' => 'Potência para jogos em alta qualidade.', 'descricao' => 'Potência para jogos em alta qualidade.'],
        ['id' => 3, 'nome' => 'Memória RAM', 'desc' => 'Mais velocidade e estabilidade para o seu setup.', 'descricao' => 'Mais velocidade e estabilidade para o seu setup.'],
        ['id' => 4, 'nome' => 'Armazenamento', 'desc' => 'SSD e HD com performance superior.', 'descricao' => 'SSD e HD com performance superior.'],
    ];
}

function adicionar_categoria(string $nome, ?string $descricao = null, ?string $icone = null): array
{
    $nome = trim($nome);
    $descricao = trim((string) ($descricao ?? ''));
    $icone = trim((string) ($icone ?? ''));

    if ($nome === '') {
        return ['ok' => false, 'mensagem' => 'Informe o nome da categoria.'];
    }

    try {
        $db = db_connect();
        $stmtCheck = $db->prepare('SELECT id FROM categorias WHERE nome = ? LIMIT 1');
        $stmtCheck->bind_param('s', $nome);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            return ['ok' => true, 'mensagem' => 'Categoria já existente.'];
        }

        $stmt = $db->prepare('INSERT INTO categorias (nome, descricao, icone) VALUES (?, ?, ?)');
        $stmt->bind_param('sss', $nome, $descricao, $icone);
        $stmt->execute();
        return ['ok' => true, 'mensagem' => 'Categoria cadastrada com sucesso!'];
    } catch (Throwable $e) {
        error_log('adicionar_categoria: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao salvar categoria no banco de dados.'];
    }
}

function atualizar_produto(int $id, array $dados, ?array $file = null): array
{
    $nome = trim((string) ($dados['nome'] ?? ''));
    $categoria = trim((string) ($dados['categoria'] ?? ''));
    $preco = (float) ($dados['preco'] ?? 0);
    $descricao = trim((string) ($dados['descricao'] ?? ''));
    $destaque = !empty($dados['destaque']) ? 1 : 0;

    $imagemUpload = processar_upload_imagem($file);
    if ($imagemUpload !== '') {
        $imagem = $imagemUpload;
    } else {
        $imagem = trim((string) ($dados['imagem'] ?? 'default.png'));
        if ($imagem === '') {
            $imagem = 'default.png';
        }
        if ($imagem === 'default.png') {
            try {
                $dbImagem = db_connect();
                $stmtImagem = $dbImagem->prepare('SELECT imagem FROM produtos WHERE id = ? LIMIT 1');
                $stmtImagem->bind_param('i', $id);
                $stmtImagem->execute();
                $imagemAtual = $stmtImagem->get_result()->fetch_assoc();
                if (!empty($imagemAtual['imagem'])) {
                    $imagem = $imagemAtual['imagem'];
                }
            } catch (Throwable $e) {
                error_log('atualizar_produto imagem: ' . $e->getMessage());
            }
        }
    }

    if ($id <= 0 || $nome === '' || $categoria === '' || $preco <= 0 || $descricao === '') {
        return ['ok' => false, 'mensagem' => 'Preencha todos os campos obrigatórios do produto.'];
    }

    try {
        $db = db_connect();
        adicionar_categoria($categoria, 'Produtos da categoria ' . $categoria);
        $stmt = $db->prepare('UPDATE produtos SET nome = ?, categoria = ?, preco = ?, descricao = ?, imagem = ?, destaque = ? WHERE id = ?');
        $stmt->bind_param('ssdssii', $nome, $categoria, $preco, $descricao, $imagem, $destaque, $id);
        if (!$stmt->execute()) {
            return ['ok' => false, 'mensagem' => 'Erro ao atualizar produto no banco de dados.'];
        }
        return ['ok' => true, 'mensagem' => '✅ Produto #' . $id . ' atualizado com sucesso no MySQL!'];
    } catch (Throwable $e) {
        error_log('atualizar_produto: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao atualizar produto no banco de dados: ' . $e->getMessage()];
    }
}

function excluir_produto(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'mensagem' => 'ID de produto inválido.'];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('DELETE FROM produtos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return ['ok' => true, 'mensagem' => 'Produto excluído com sucesso!'];
    } catch (Throwable $e) {
        error_log('excluir_produto: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao excluir produto no banco de dados.'];
    }
}

function atualizar_categoria(int $id, string $nome, ?string $descricao = null): array
{
    $nome = trim($nome);
    $descricao = trim((string) ($descricao ?? ''));

    if ($id <= 0 || $nome === '') {
        return ['ok' => false, 'mensagem' => 'Nome da categoria é obrigatório.'];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('UPDATE categorias SET nome = ?, descricao = ? WHERE id = ?');
        $stmt->bind_param('ssi', $nome, $descricao, $id);
        $stmt->execute();
        return ['ok' => true, 'mensagem' => 'Categoria atualizada com sucesso!'];
    } catch (Throwable $e) {
        error_log('atualizar_categoria: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao atualizar categoria no banco de dados.'];
    }
}

function excluir_categoria(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'mensagem' => 'ID de categoria inválido.'];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('DELETE FROM categorias WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return ['ok' => true, 'mensagem' => 'Categoria excluída com sucesso!'];
    } catch (Throwable $e) {
        error_log('excluir_categoria: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao excluir categoria no banco de dados.'];
    }
}


function get_avaliacoes_produto(int $produtoId): array
{
    if ($produtoId <= 0) return [];
    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT a.*, SUM(CASE WHEN i.tipo = 'like' THEN 1 ELSE 0 END) AS likes, SUM(CASE WHEN i.tipo = 'denuncia' THEN 1 ELSE 0 END) AS denuncias FROM avaliacoes_produtos a LEFT JOIN avaliacoes_interacoes i ON i.avaliacao_id = a.id WHERE a.produto_id = ? AND a.status = 'Aprovado' GROUP BY a.id ORDER BY a.id DESC");
        $stmt->bind_param('i', $produtoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $list[] = [
                'id' => (int) $row['id'],
                'produto_id' => (int) $row['produto_id'],
                'usuario_id' => (int) $row['usuario_id'],
                'usuario_nome' => (string) ($row['usuario_nome'] ?? 'Cliente'),
                'nota' => (int) ($row['nota'] ?? 5),
                'comentario' => (string) ($row['comentario'] ?? ''),
                'criado_em' => (string) ($row['criado_em'] ?? ''),
                'likes' => (int) ($row['likes'] ?? 0),
                'denuncias' => (int) ($row['denuncias'] ?? 0),
                'motivo_denuncia' => (string) ($row['motivo_denuncia'] ?? ''),
                'detalhes_denuncia' => (string) ($row['detalhes_denuncia'] ?? ''),
                'denunciante_nome' => (string) ($row['denunciante_nome'] ?? ''),
                'denunciante_email' => (string) ($row['denunciante_email'] ?? ''),
            ];
        }
        return $list;
    } catch (Throwable $e) {
        error_log('get_avaliacoes_produto: ' . $e->getMessage());
        return [];
    }
}

function adicionar_avaliacao_produto(int $produtoId, int $usuarioId, string $usuarioNome, int $nota, string $comentario): array
{
    if ($produtoId <= 0 || $usuarioId <= 0) {
        return ['ok' => false, 'mensagem' => 'Você precisa estar logado para enviar uma avaliação.'];
    }

    $comentario = trim($comentario);
    $nota = max(1, min(5, (int) $nota));

    if ($comentario === '') {
        return ['ok' => false, 'mensagem' => 'Escreva um comentário sobre o produto.'];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare("INSERT INTO avaliacoes_produtos (produto_id, usuario_id, usuario_nome, nota, comentario, status) VALUES (?, ?, ?, ?, ?, 'Aprovado')");
        $stmt->bind_param('iisis', $produtoId, $usuarioId, $usuarioNome, $nota, $comentario);
        $stmt->execute();

        return ['ok' => true, 'mensagem' => '⭐ Sua avaliação e comentário foram publicados com sucesso!'];
    } catch (Throwable $e) {
        error_log('adicionar_avaliacao_produto: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao salvar avaliação no banco de dados.'];
    }
}

function interagir_avaliacao(int $avaliacaoId, int $usuarioId, string $tipo, string $motivo = '', string $detalhes = '', string $denuncianteNome = '', string $denuncianteEmail = ''): array
{
    if ($avaliacaoId <= 0 || $usuarioId <= 0 || !in_array($tipo, ['like', 'denuncia'], true)) {
        return ['ok' => false, 'mensagem' => 'Interação inválida.'];
    }

    try {
        $db = db_connect();
        $check = $db->prepare('SELECT id FROM avaliacoes_produtos WHERE id = ? LIMIT 1');
        $check->bind_param('i', $avaliacaoId);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            return ['ok' => false, 'mensagem' => 'Comentário não encontrado.'];
        }

        $stmt = $db->prepare('SELECT id FROM avaliacoes_interacoes WHERE avaliacao_id = ? AND usuario_id = ? AND tipo = ? LIMIT 1');
        $stmt->bind_param('iis', $avaliacaoId, $usuarioId, $tipo);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['ok' => true, 'mensagem' => $tipo === 'like' ? 'Você já curtiu este comentário.' : 'Você já denunciou este comentário.'];
        }

        $insert = $db->prepare('INSERT INTO avaliacoes_interacoes (avaliacao_id, usuario_id, tipo, motivo_denuncia, detalhes_denuncia, denunciante_nome, denunciante_email) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->bind_param('iisssss', $avaliacaoId, $usuarioId, $tipo, $motivo, $detalhes, $denuncianteNome, $denuncianteEmail);
        $insert->execute();
        return ['ok' => true, 'mensagem' => $tipo === 'like' ? 'Comentário curtido!' : 'Denúncia registrada para análise.'];
    } catch (Throwable $e) {
        error_log('interagir_avaliacao: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível salvar sua interação.'];
    }
}

function excluir_avaliacao_moderacao(int $avaliacaoId): array
{
    if ($avaliacaoId <= 0) return ['ok' => false, 'mensagem' => 'Avaliação inválida.'];
    try {
        $db = db_connect();
        $stmtInteractions = $db->prepare('DELETE FROM avaliacoes_interacoes WHERE avaliacao_id = ?');
        $stmtInteractions->bind_param('i', $avaliacaoId);
        $stmtInteractions->execute();
        $stmt = $db->prepare('DELETE FROM avaliacoes_produtos WHERE id = ?');
        $stmt->bind_param('i', $avaliacaoId);
        $stmt->execute();
        return ['ok' => $stmt->affected_rows > 0, 'mensagem' => $stmt->affected_rows > 0 ? 'Comentário excluído.' : 'Comentário não encontrado.'];
    } catch (Throwable $e) {
        error_log('excluir_avaliacao_moderacao: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível excluir o comentário.'];
    }
}

function seed_all_tables_if_empty(): void
{
    try {
        $db = db_connect();

        // 1. Endereços
        $check1 = $db->query("SELECT COUNT(*) AS total FROM enderecos");
        if ($check1 && (int) ($check1->fetch_assoc()['total'] ?? 0) === 0) {
            $db->query("INSERT INTO enderecos (usuario_id, id_usuario, cep, cidade, estado, numero, rua) VALUES (1, 1, '01001-000', 'São Paulo', 'SP', '1000', 'Avenida Paulista')");
        }

        // 2. Pedidos
        $check2 = $db->query("SELECT COUNT(*) AS total FROM pedidos");
        if ($check2 && (int) ($check2->fetch_assoc()['total'] ?? 0) === 0) {
            $db->query("INSERT INTO pedidos (usuario_id, produto_id, produto_nome, categoria, preco, quantidade, status, nome_cliente, email_cliente, telefone, cep, rua, numero, cidade, estado) VALUES (1, 9, 'PC Gamer TechFlow RGB RTX 4060', 'PCs Gamer', 4599.00, 1, 'Pago', 'Cliente Demo', 'demo@techflow.com', '11999998888', '01001-000', 'Avenida Paulista', '1000', 'São Paulo', 'SP')");
        }

        // 3. Carts & Cart Items
        $check3 = $db->query("SELECT COUNT(*) AS total FROM carts");
        if ($check3 && (int) ($check3->fetch_assoc()['total'] ?? 0) === 0) {
            $db->query("INSERT INTO carts (id, user_id, status) VALUES (1, 1, 'ativo')");
            $db->query("INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (1, 9, 1, 4599.00)");
        }

        // 4. Cupons
        $check4 = $db->query("SELECT COUNT(*) AS total FROM cupons");
        if ($check4 && (int) ($check4->fetch_assoc()['total'] ?? 0) === 0) {
            $db->query("INSERT INTO cupons (codigo, desconto_percentual, ativo) VALUES ('TECH10', 10.00, 1), ('GAMER15', 15.00, 1)");
        }

        // 5. Suporte
        $check5 = $db->query("SELECT COUNT(*) AS total FROM chamados_suporte");
        if ($check5 && (int) ($check5->fetch_assoc()['total'] ?? 0) === 0) {
            $db->query("INSERT INTO chamados_suporte (usuario_id, pedido_id, assunto, mensagem, status) VALUES (1, 1, 'Dúvida sobre a entrega do meu PC Gamer', 'Gostaria de confirmar a previsão de entrega do pedido #1.', 'Aberto')");
        }

        // 6. Avaliações
        $check6 = $db->query("SELECT COUNT(*) AS total FROM avaliacoes_produtos");
        if ($check6 && (int) ($check6->fetch_assoc()['total'] ?? 0) === 0) {
            $db->query("INSERT INTO avaliacoes_produtos (produto_id, usuario_id, usuario_nome, nota, comentario, status) VALUES
                (1, 1, 'Lucas Silva', 5, 'Processador sensacional! Entrega muito rápida e temperatura excelente.', 'Aprovado'),
                (2, 1, 'Gabriel Ramos', 5, 'Placa de vídeo rodando tudo no ultra em 1080p e 144Hz.', 'Aprovado'),
                (3, 1, 'Mariana Costa', 5, 'SSD NVMe super veloz. O Windows inicializa em 5 segundos!', 'Aprovado'),
                (9, 1, 'Cliente Demo', 5, 'PC Gamer excelente! Chegou muito rápido e muito bem embalado.', 'Aprovado')");
        }

        // 7. Logística
        $check7 = $db->query("SELECT COUNT(*) AS total FROM logistica_pedidos");
        if ($check7 && (int) ($check7->fetch_assoc()['total'] ?? 0) === 0) {
            $db->query("INSERT INTO logistica_pedidos (pedido_id, codigo_rastreio, status_expedicao) VALUES (1, 'TF123456789BR', 'Em Separação no Estoque')");
        }
    } catch (Throwable $e) {
        error_log('seed_all_tables_if_empty: ' . $e->getMessage());
    }
}

seed_all_tables_if_empty();
