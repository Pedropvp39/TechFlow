<?php
// Arquivo de conexão principal com o banco de dados MySQL e criação dinâmica da estrutura de tabelas

// Define a constante do servidor MySQL caso ainda não esteja definida
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}

// Define o usuário de acesso ao MySQL
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}

// Define a senha de acesso ao MySQL
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: 'senac');
}

// Define o nome do banco de dados (padrão: techflow)
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'techflow');
}

// Define a porta de comunicação com o MySQL (padrão: 3307 ou 3306)
if (!defined('DB_PORT')) {
    define('DB_PORT', (int) (getenv('DB_PORT') ?: 3307));
}

// Gera combinações de hosts, usuários, senhas e portas para tentar conectar com resiliência
function db_connection_candidates(): array
{
    $hosts = [DB_HOST, 'localhost', '127.0.0.1'];
    $users = [DB_USER, 'root'];
    $passes = [DB_PASS, '', 'senac', 'root'];
    $ports = [DB_PORT, 3307, 3306];

    $seen = [];
    $candidates = [];
    foreach ($hosts as $host) {
        foreach ($users as $user) {
            foreach ($passes as $pass) {
                foreach ($ports as $port) {
                    $key = $host . '|' . $user . '|' . $pass . '|' . $port;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $candidates[] = [$host, $user, $pass, (int) $port];
                }
            }
        }
    }

    return $candidates;
}

// Função que estabelece e retorna a conexão mysqli ativa com o banco MySQL
function db_connect(): mysqli
{
    static $conexao = null;
    static $lastConfig = null;

    // Se já houver uma conexão aberta e válida, reaproveita a mesma instância
    if ($conexao instanceof mysqli && !$conexao->connect_errno) {
        return $conexao;
    }

    // Tenta conectar usando a lista de credenciais até obter sucesso
    foreach (db_connection_candidates() as [$host, $user, $pass, $port]) {
        $try = @new mysqli($host, $user, $pass, '', $port);
        if ($try->connect_error) {
            $try->close();
            continue;
        }

        $lastConfig = [$host, $user, $pass, $port];
        $conexao = @new mysqli($host, $user, $pass, DB_NAME, $port);
        if ($conexao && !$conexao->connect_error) {
            $conexao->set_charset('utf8mb4'); // Define o conjunto de caracteres como UTF-8
            return $conexao;
        }
    }

    if ($lastConfig) {
        [$host, $user, $pass, $port] = $lastConfig;
        $conexao = @new mysqli($host, $user, $pass, DB_NAME, $port);
        if ($conexao && !$conexao->connect_error) {
            $conexao->set_charset('utf8mb4');
            return $conexao;
        }
    }

    // Lança exceção caso nenhuma tentativa de conexão tenha obtido sucesso
    throw new RuntimeException('Erro na conexão com o MySQL. Verifique se o XAMPP/MySQL está ligado e se a senha do usuário root está correta.');
}

function db_add_column_if_missing(mysqli $conexao, string $table, string $column, string $definition): void
{
    try {
        $check = $conexao->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check && $check->num_rows === 0) {
            $conexao->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    } catch (Throwable $e) {
        error_log("db_add_column_if_missing ($table.$column): " . $e->getMessage());
    }
}

function db_ensure_schema(): void
{
    try {
        $admin = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
        if (!$admin || $admin->connect_error) {
            foreach (db_connection_candidates() as [$host, $user, $pass, $port]) {
                $admin = @new mysqli($host, $user, $pass, '', $port);
                if ($admin && !$admin->connect_error) {
                    break;
                }
            }
        }

        if (!$admin || $admin->connect_error) {
            return;
        }

        $dbName = str_replace('`', '``', DB_NAME);
        $admin->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $admin->close();

        $conexao = db_connect();
        $conexao->query("CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            nascimento VARCHAR(50) NOT NULL,
            senha VARCHAR(255) NOT NULL,
            tipo VARCHAR(50) NOT NULL DEFAULT 'cliente',
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            avatar VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        // Garante compatibilidade adicionando colunas faltantes com segurança
        db_add_column_if_missing($conexao, 'usuarios', 'tipo', "VARCHAR(50) NOT NULL DEFAULT 'cliente'");
        //faz a verificação se a coluna 'tipo' existe na tabela 'usuarios', caso não exista, adiciona a coluna com o tipo VARCHAR(50) e valor padrão 'cliente' e segue o mesmo padrao para o restante
        db_add_column_if_missing($conexao, 'usuarios', 'is_admin', "TINYINT(1) NOT NULL DEFAULT 0");
        db_add_column_if_missing($conexao, 'usuarios', 'avatar', "VARCHAR(255) NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'senha', "VARCHAR(255) NOT NULL DEFAULT ''");
        db_add_column_if_missing($conexao, 'usuarios', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        db_add_column_if_missing($conexao, 'usuarios', 'telefone', "VARCHAR(20) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'cep', "VARCHAR(20) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'rua', "VARCHAR(255) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'numero', "VARCHAR(20) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'cidade', "VARCHAR(100) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'estado', "VARCHAR(10) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'chave_mestre', "VARCHAR(255) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'usuarios', 'status_conta', "VARCHAR(20) NOT NULL DEFAULT 'ativo'");

        $conexao->query("CREATE TABLE IF NOT EXISTS recuperacao_senhas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expira_em DATETIME NOT NULL,
            usado TINYINT(1) NOT NULL DEFAULT 0,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY usuario_id_idx (usuario_id),
            KEY expira_em_idx (expira_em)
        )");

        @$conexao->query("ALTER TABLE usuarios MODIFY telefone VARCHAR(20) NULL DEFAULT NULL");
        //faz a modificação da coluna 'telefone' na tabela 'usuarios' para permitir valores nulos e definir o valor padrão como NULL
        @$conexao->query("ALTER TABLE usuarios MODIFY cep VARCHAR(20) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE usuarios MODIFY rua VARCHAR(255) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE usuarios MODIFY numero VARCHAR(20) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE usuarios MODIFY cidade VARCHAR(100) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE usuarios MODIFY estado VARCHAR(10) NULL DEFAULT NULL");

        //faz a verificação se a tabela 'categorias' existe, caso não exista, cria a tabela com as colunas 'id', 'nome', 'descricao', 'icone' e 'created_at'
        $conexao->query("CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL UNIQUE,
            descricao TEXT NULL,
            icone VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        db_add_column_if_missing($conexao, 'categorias', 'descricao', "TEXT NULL");
        db_add_column_if_missing($conexao, 'categorias', 'icone', "VARCHAR(255) NULL");

        $conexao->query("CREATE TABLE IF NOT EXISTS produtos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(150) NOT NULL,
            categoria VARCHAR(80) NOT NULL,
            preco DECIMAL(10,2) NOT NULL,
            descricao TEXT NOT NULL,
            imagem VARCHAR(255) NOT NULL,
            destaque TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $conexao->query("CREATE TABLE IF NOT EXISTS pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            produto_id INT NOT NULL,
            produto_nome VARCHAR(150) NOT NULL,
            categoria VARCHAR(80) NOT NULL,
            preco DECIMAL(10,2) NOT NULL,
            quantidade INT NOT NULL DEFAULT 1,
            status VARCHAR(30) NOT NULL DEFAULT 'Pago',
            removido TINYINT(1) NOT NULL DEFAULT 0,
            nome_cliente VARCHAR(255) NULL,
            email_cliente VARCHAR(255) NULL,
            telefone VARCHAR(20) NULL,
            cep VARCHAR(20) NULL,
            rua VARCHAR(255) NULL,
            numero VARCHAR(20) NULL,
            cidade VARCHAR(100) NULL,
            estado VARCHAR(10) NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        db_add_column_if_missing($conexao, 'pedidos', 'nome_cliente', "VARCHAR(255) NULL");
        db_add_column_if_missing($conexao, 'pedidos', 'email_cliente', "VARCHAR(255) NULL");
        db_add_column_if_missing($conexao, 'pedidos', 'telefone', "VARCHAR(20) NULL");
        db_add_column_if_missing($conexao, 'pedidos', 'cep', "VARCHAR(20) NULL");
        db_add_column_if_missing($conexao, 'pedidos', 'rua', "VARCHAR(255) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'pedidos', 'numero', "VARCHAR(20) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'pedidos', 'cidade', "VARCHAR(100) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'pedidos', 'estado', "VARCHAR(10) NULL DEFAULT NULL");

        @$conexao->query("ALTER TABLE pedidos MODIFY nome_cliente VARCHAR(255) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE pedidos MODIFY email_cliente VARCHAR(255) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE pedidos MODIFY telefone VARCHAR(20) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE pedidos MODIFY cep VARCHAR(20) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE pedidos MODIFY rua VARCHAR(255) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE pedidos MODIFY numero VARCHAR(20) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE pedidos MODIFY cidade VARCHAR(100) NULL DEFAULT NULL");
        @$conexao->query("ALTER TABLE pedidos MODIFY estado VARCHAR(10) NULL DEFAULT NULL");

        // Tabelas de carrinho persistente no banco de dados
        $conexao->query("CREATE TABLE IF NOT EXISTS carts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ativo',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY user_id_idx (user_id),
            KEY status_idx (status)
        )");

        db_add_column_if_missing($conexao, 'carts', 'user_id', "INT NOT NULL DEFAULT 0");
        db_add_column_if_missing($conexao, 'carts', 'status', "VARCHAR(20) NOT NULL DEFAULT 'ativo'");
        db_add_column_if_missing($conexao, 'carts', 'session_id', "VARCHAR(255) NULL DEFAULT NULL");

        @$conexao->query("ALTER TABLE carts MODIFY session_id VARCHAR(255) NULL DEFAULT NULL");

        db_add_column_if_missing($conexao, 'cart_items', 'product_id', "INT NOT NULL DEFAULT 0");
        db_add_column_if_missing($conexao, 'cart_items', 'produto_id', "INT NOT NULL DEFAULT 0");

        // Tabela de endereços cadastrados do usuário
        $conexao->query("CREATE TABLE IF NOT EXISTS enderecos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            cep VARCHAR(20) NOT NULL,
            cidade VARCHAR(100) NOT NULL,
            estado VARCHAR(10) NOT NULL,
            numero VARCHAR(20) NOT NULL,
            rua VARCHAR(255) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY usuario_id_idx (usuario_id)
        )");

        db_add_column_if_missing($conexao, 'enderecos', 'usuario_id', "INT NOT NULL DEFAULT 0");
        db_add_column_if_missing($conexao, 'enderecos', 'cep', "VARCHAR(20) NOT NULL DEFAULT ''");
        db_add_column_if_missing($conexao, 'enderecos', 'cidade', "VARCHAR(100) NOT NULL DEFAULT ''");
        db_add_column_if_missing($conexao, 'enderecos', 'estado', "VARCHAR(10) NOT NULL DEFAULT ''");
        db_add_column_if_missing($conexao, 'enderecos', 'numero', "VARCHAR(20) NOT NULL DEFAULT ''");
        db_add_column_if_missing($conexao, 'enderecos', 'rua', "VARCHAR(255) NOT NULL DEFAULT ''");

        // Tabelas de Módulos Específicos do Sistema (Cupons, Suporte, Moderação e Logística)
        $conexao->query("CREATE TABLE IF NOT EXISTS cupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(50) NOT NULL UNIQUE,
            desconto_percentual DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $conexao->query("CREATE TABLE IF NOT EXISTS chamados_suporte (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            pedido_id INT NULL,
            assunto VARCHAR(255) NOT NULL,
            mensagem TEXT NOT NULL,
            resposta TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Aberto',
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $conexao->query("CREATE TABLE IF NOT EXISTS avaliacoes_produtos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produto_id INT NOT NULL,
            usuario_id INT NOT NULL,
            usuario_nome VARCHAR(255) NOT NULL,
            nota INT NOT NULL DEFAULT 5,
            comentario TEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Aprovado',
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $conexao->query("CREATE TABLE IF NOT EXISTS logistica_pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            codigo_rastreio VARCHAR(100) NULL,
            status_expedicao VARCHAR(50) NOT NULL DEFAULT 'Aguardando Separação',
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        $conexao->query("CREATE TABLE IF NOT EXISTS avaliacoes_interacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            avaliacao_id INT NOT NULL,
            usuario_id INT NOT NULL,
            tipo ENUM('like', 'denuncia') NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY avaliacao_usuario_tipo (avaliacao_id, usuario_id, tipo),
            KEY avaliacao_id_idx (avaliacao_id),
            KEY usuario_id_idx (usuario_id)
        )");
        db_add_column_if_missing($conexao, 'avaliacoes_interacoes', 'motivo_denuncia', "VARCHAR(80) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'avaliacoes_interacoes', 'detalhes_denuncia', "TEXT NULL");
        db_add_column_if_missing($conexao, 'avaliacoes_interacoes', 'denunciante_nome', "VARCHAR(255) NULL DEFAULT NULL");
        db_add_column_if_missing($conexao, 'avaliacoes_interacoes', 'denunciante_email', "VARCHAR(255) NULL DEFAULT NULL");

        $conexao->query("CREATE TABLE IF NOT EXISTS cart_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cart_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY cart_id_idx (cart_id),
            KEY product_id_idx (product_id)
        )");
    } catch (Throwable $e) {
        error_log('db_ensure_schema: ' . $e->getMessage());
    }
}

db_ensure_schema();

