<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    $db = db_connect();
    echo "✅ Conexao OK\n";

    echo "--- TABELAS ---\n";
    $tables = $db->query('SHOW TABLES');
    while ($t = $tables->fetch_array()) {
        echo "Tabela: " . $t[0] . "\n";
    }

    echo "--- ESTRUTURA DA TABELA USUARIOS ---\n";
    $res = $db->query('DESCRIBE usuarios');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "Campo: {$row['Field']} | Tipo: {$row['Type']} | Null: {$row['Null']} | Key: {$row['Key']} | Default: {$row['Default']}\n";
        }
    } else {
        echo "Tabela usuarios nao existe ou erro: " . $db->error . "\n";
    }

    echo "--- REGISTRO DE TESTE ---\n";
    $reg = register_user('Teste Usuario', 'teste' . time() . '@email.com', '2000-01-01', 'senha123456');
    echo "Resultado do register_user: " . json_encode($reg) . "\n";

    echo "--- CONTEUDO DA TABELA USUARIOS ---\n";
    $users = $db->query('SELECT id, nome, email, nascimento, is_admin FROM usuarios');
    if ($users) {
        while ($u = $users->fetch_assoc()) {
            echo "User ID: {$u['id']} | Nome: {$u['nome']} | Email: {$u['email']}\n";
        }
    }

} catch (Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

