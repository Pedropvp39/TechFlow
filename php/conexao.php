<?php
/**
 * ============================================================================
 * CONEXÃO LOCAL COM O BANCO DE DADOS (AUTO-DETECÇÃO)
 * ============================================================================
 *
 * Objetivo: o site funciona em QUALQUER máquina, independente do computador,
 * desde que exista um MySQL instalado (XAMPP, WAMP, Laragon, MySQL do sistema).
 *
 * Como funciona:
 *   1) Descobre sozinho em qual PORTA o MySQL está rodando (3306, 3307, 3308...),
 *      inclusive lendo a configuração do XAMPP/WAMP quando existir.
 *   2) Testa automaticamente combinações de usuário e senha (root sem senha,
 *      senac, root, admin...) para aceitar uma senha nova sem editar nada aqui.
 *   3) Escolhe o banco que REALMENTE tem os dados do sistema (tabela `usuarios`),
 *      e não apenas o banco com o nome "techflow".
 *   4) Cria o banco e as tabelas do zero quando estiver em uma máquina nova,
 *      permitindo cadastrar novas contas normalmente.
 *
 * Os dados da hospedagem (InfinityFree) ficam no arquivo SEPARADO:
 *   php/conexao-preferencias.php
 */

// ---------------------------------------------------------------------------
// CONFIGURAÇÃO OPCIONAL (só usada se você quiser forçar um valor específico)
// ---------------------------------------------------------------------------
// O '?:' abaixo significa: se a variável de ambiente existir, usa ela; se não,
// usa o valor padrão. Conforme o valor é vazio, o sistema faz a auto-detecção.
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}

// Define o usuário de acesso ao MySQL
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}

// Senha vazia de propósito: a lista de tentativas abaixo resolve sozinha.
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

// Nome preferido do banco (se não existir, o banco com dados é encontrado).
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'techflow');
}

// Porta preferida de comunicação com o MySQL.
if (!defined('DB_PORT')) {
    define('DB_PORT', (int) (getenv('DB_PORT') ?: 3307));
}

// ---------------------------------------------------------------------------
// CONEXÃO DE PREFERÊNCIA (HOSPEDAGEM) — arquivo separado
// ---------------------------------------------------------------------------
require_once __DIR__ . '/conexao-preferencias.php';

/**
 * Descobre em quais portas o MySQL pode estar rodando nesta máquina.
 *
 * Não basta testar 3306: muitos XAMPP ficam em 3307 (e o WAMP troca de porta).
 * Aqui juntamos a porta configurada, as portas típicas, as portas realmente
 * ABERTAS no computador e o que estiver escrito nos arquivos my.ini do XAMPP.
 */
function db_candidate_ports(): array
{
    $ports = [];

    // 1) Porta configurada + portas padrão conhecidas.
    foreach ([DB_PORT, 3307, 3306, 3308, 3309, 3310, 3316] as $port) {
        $ports[] = (int) $port;
    }

    // 2) Lê a porta real dos arquivos de configuração do XAMPP/WAMP/Laragon.
    $configFiles = [
        'C:/xampp/mysql/bin/my.ini',
        'C:/xampp/mysql/data/my.ini',
        'C:/wamp64/bin/mysql/mysql8.0.31/my.ini',
        'C:/laragon/bin/mysql/mysql-8.0.30-winx64/my.ini',
        '/Applications/XAMPP/xamppfiles/etc/my.cnf',
        '/etc/mysql/my.cnf',
    ];
    foreach ($configFiles as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            continue;
        }
        // Procura linhas do tipo "port=3307".
        if (preg_match_all('/^\s*port\s*=\s*(\d{2,5})/mi', $content, $matches)) {
            foreach ($matches[1] as $found) {
                $ports[] = (int) $found;
            }
        }
    }

    // 3) Portas realmente abertas (escutando) nesta máquina.
    foreach (db_scan_listening_ports() as $openPort) {
        $ports[] = $openPort;
    }

    // Remove repetidos mantendo a ordem de prioridade.
    $unique = [];
    foreach ($ports as $port) {
        if ($port > 0 && $port < 65536 && !in_array($port, $unique, true)) {
            $unique[] = $port;
        }
    }

    return $unique;
}

/**
 * Lê as portas que estão escutando na máquina (Windows via netstat; Linux/macOS
 * via netstat/ss) e devolve primeiro as portas típicas de MySQL/MariaDB.
 */
function db_scan_listening_ports(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = [];
    $output = [];

    // Tenta capturar as portas em escuta. Alguns servidores deixam o exec()
    // desativado, por isso o resultado é validado antes de ser usado.
    if (function_exists('exec')) {
        if (stripos(PHP_OS, 'WIN') === 0) {
            @exec('netstat -ano -p tcp 2>NUL', $output);
        } else {
            @exec('netstat -lnt 2>/dev/null', $output);
            if (empty($output)) {
                @exec('ss -lnt 2>/dev/null', $output);
            }
        }
    }

    if (empty($output)) {
        return $cached;
    }

    $found = [];
    foreach ($output as $line) {
        // Considera apenas linhas em estado de escuta.
        if (stripos($line, 'LISTEN') === false) {
            continue;
        }
        // Captura a porta local da linha (ex.: 0.0.0.0:3307).
        if (!preg_match('/[:\s](\d{2,5})[\s]/', $line . ' ', $m)) {
            continue;
        }
        $port = (int) $m[1];
        if ($port <= 0 || $port >= 65536) {
            continue;
        }
        $found[$port] = true;
    }

    // Portas típicas de MySQL primeiro, depois as demais portas abertas.
    foreach (array_keys($found) as $port) {
        if (in_array($port, [3306, 3307, 3308, 3309, 3310, 3316], true)) {
            $cached[] = $port;
            unset($found[$port]);
        }
    }
    foreach (array_keys($found) as $port) {
        $cached[] = $port;
    }

    return $cached;
}

/**
 * Gera combinações de host, usuário, senha e porta para tentar conectar.
 * A ordem importa: o que funciona mais fácil é testado primeiro.
 */
function db_connection_candidates(): array
{
    // Hosts possíveis: localhost (socket/named pipe) e 127.0.0.1 (TCP).
    $hosts = [DB_HOST, '127.0.0.1', 'localhost'];

    // Usuários possíveis (root é o padrão do XAMPP/WAMP).
    $users = [DB_USER, 'root', 'mysql', 'admin'];

    // Senhas possíveis. Inclui vazio e as mais usadas em projetos locais.
    // Se a sua senha for outra, adicione-a nesta lista (ou defina DB_PASS).
    $passes = [DB_PASS, '', 'senac', 'root', 'admin', '1234', '123456', 'password', 'mysql'];

    // Apenas portas que respondem de verdade. Testar portas fechadas era o que
    // deixava a primeira página lenta (cada porta morta esperava timeout).
    $ports = db_reachable_ports();

    // Ordem de tentativa: para CADA porta e usuário, tenta todas as senhas.
    // Assim a senha correta é encontrada rapidamente, sem repetir a lista de
    // senhas inteira para cada porta.
    $seen = [];
    $candidates = [];
    foreach ($ports as $port) {
        foreach ($hosts as $host) {
            foreach ($users as $user) {
                foreach ($passes as $pass) {
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

/**
 * Devolve somente as portas que estão realmente aceitando conexão.
 *
 * Isso é essencial para a página abrir rápido: antes, cada porta fechada
 * (3306, 3308, 3309...) era testada com todas as senhas até dar timeout.
 * Agora a porta é testada UMA vez com uma conexão de 0,5s.
 */
function db_reachable_ports(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = [];
    foreach (db_candidate_ports() as $port) {
        // Timeout curto: porta que não responde em 0,5s é descartada.
        $test = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if (is_resource($test)) {
            fclose($test);
            $cached[] = (int) $port;
        }
    }

    // Se nada respondeu (firewall bloqueando o teste de porta), mantém as
    // portas configuradas para não deixar o site sem nenhuma tentativa.
    if (empty($cached)) {
        $cached = [DB_PORT, 3306, 3307];
    }

    return $cached;
}

/**
 * Confere se um banco já possui as tabelas principais do site.
 */
function db_database_has_data(mysqli $conn, string $database): bool
{
    try {
        $escaped = $conn->real_escape_string($database);
        $result = $conn->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = '$escaped' AND table_name IN ('usuarios', 'produtos')");
        if (!$result) {
            return false;
        }
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0) >= 1;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Seleciona automaticamente o banco de dados correto nesta máquina.
 *
 * Primeiro tenta o nome preferido (DB_NAME = "techflow"). Se ele não existir
 * ou estiver vazio, varre os outros bancos do servidor e escolhe o que tiver a
 * tabela `usuarios` — é ali que ficam as contas e senhas do sistema.
 */
function db_resolve_database(mysqli $conn): mysqli
{
    $preferred = [];
    foreach ([DB_NAME, 'techflow', 'teckflow', 'projeto_integrador'] as $name) {
        if (!in_array($name, $preferred, true)) {
            $preferred[] = $name;
        }
    }

    // Bancos existentes no servidor.
    $existing = [];
    if ($result = $conn->query('SHOW DATABASES')) {
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $existing[] = (string) $row[0];
        }
    }

    // Bancos internos que nunca são o projeto.
    $ignored = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin', 'test'];

    // ------------------------------------------------------------------
    // ORDEM DE ESCOLHA
    // 1) um banco PREFERIDO que já tenha os dados (caso normal);
    // 2) qualquer outro banco que tenha os dados do sistema;
    // 3) um banco preferido que exista, mesmo vazio (primeira instalação);
    // 4) nada encontrado -> usa o nome preferido (será criado depois).
    //
    // Essa ordem é o que faz o site funcionar ao ser baixado em outro PC:
    // mesmo que o nome do banco esteja configurado errado, o banco que
    // REALMENTE tem as tabelas é encontrado e usado.
    // ------------------------------------------------------------------

    // 1) Banco preferido com dados.
    foreach ($preferred as $name) {
        if (in_array($name, $existing, true) && $conn->select_db($name) && db_database_has_data($conn, $name)) {
            $conn->set_charset('utf8mb4');
            return $conn;
        }
    }

    // 2) Qualquer banco do servidor que tenha os dados do sistema.
    foreach ($existing as $name) {
        if (in_array($name, $ignored, true)) {
            continue;
        }
        if (in_array($name, $preferred, true)) {
            continue; // já foi testado no passo 1
        }
        if ($conn->select_db($name) && db_database_has_data($conn, $name)) {
            $conn->set_charset('utf8mb4');
            return $conn;
        }
    }

    // 3) Banco preferido que existe (mesmo vazio) — primeira instalação.
    foreach ($preferred as $name) {
        if (in_array($name, $existing, true) && $conn->select_db($name)) {
            $conn->set_charset('utf8mb4');
            return $conn;
        }
    }

    // 4) Nada encontrado: volta para o banco preferido (será criado depois).
    $conn->select_db($preferred[0]);
    $conn->set_charset('utf8mb4');
    return $conn;
}

/**
 * Estabelece e retorna a conexão mysqli ativa com o banco MySQL.
 * Percorre as combinações possíveis até encontrar uma que funcione.
 */
function db_connect(): mysqli
{
    static $conexao = null;

    // Se já houver uma conexão aberta e válida, reaproveita a mesma instância.
    if ($conexao instanceof mysqli && !$conexao->connect_errno) {
        return $conexao;
    }
    // Conexão anterior inválida: descarta para não reutilizar objeto fechado.
    $conexao = null;

    // Tenta conectar usando a lista de credenciais até obter sucesso.
    // O timeout de 2s evita que uma tentativa lenta trave a página.
    foreach (db_connection_candidates() as [$host, $user, $pass, $port]) {
        $try = null;
        try {
            $try = mysqli_init();
            if (!$try instanceof mysqli) {
                continue;
            }
            // Não deixa o MySQL esperar por nome de host (evita lentidão).
            @$try->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
            @$try->real_connect($host, $user, $pass, '', $port);
        } catch (Throwable $e) {
            $try = null;
        }

        // Descarta a tentativa que falhou. Observação importante: quando uma
        // conexão mysqli falha, o PHP já a fecha sozinha — chamar ->close()
        // nela lança "mysqli object is already closed". Por isso não fechamos.
        if (!$try instanceof mysqli || $try->connect_errno) {
            continue;
        }

        // Conectou: agora descobre qual é o banco de dados correto da máquina.
        $conexao = db_resolve_database($try);
        $conexao->set_charset('utf8mb4'); // Define o conjunto de caracteres como UTF-8
        return $conexao;
    }

    // Nenhuma combinação local funcionou: tenta a hospedagem (InfinityFree).
    $remote = db_connect_preference();
    if ($remote instanceof mysqli) {
        $conexao = $remote;
        return $conexao;
    }

    // Lança exceção caso nenhuma tentativa de conexão tenha obtido sucesso.
    throw new RuntimeException('Erro na conexão com o MySQL. Verifique se o XAMPP/MySQL está ligado (botão Start do MySQL) e se o banco do projeto já foi importado.');
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
        // Garante que o banco de dados escolhido existe antes das tabelas.
        // Em uma máquina nova, o banco é criado aqui automaticamente.
        $admin = null;
        foreach (db_connection_candidates() as [$host, $user, $pass, $port]) {
            $tentativa = null;
            try {
                $tentativa = mysqli_init();
                if (!$tentativa instanceof mysqli) {
                    continue;
                }
                // Timeout curto para não travar a primeira visita ao site.
                @$tentativa->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
                @$tentativa->real_connect($host, $user, $pass, '', $port);
            } catch (Throwable $e) {
                $tentativa = null;
            }

            // Só aceita a tentativa que realmente conectou.
            if ($tentativa instanceof mysqli && !$tentativa->connect_errno) {
                $admin = $tentativa;
                break;
            }

            // A tentativa que falhou é descartada sem close(): uma conexão
            // mysqli que falhou já vem fechada pelo próprio PHP.
            $tentativa = null;
        }

        if ($admin instanceof mysqli && !$admin->connect_errno) {
            $dbName = str_replace('`', '``', DB_NAME);
            $admin->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $admin->close();
        }

        $conexao = db_connect();
        $conexao->set_charset('utf8mb4');
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

