<?php
/**
 * Autenticação com banco de dados MySQL.
 * Mantém fallback em sessão para não quebrar o fluxo em ambiente sem banco.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../php/conexao.php';

function get_system_roles(): array
{
    return [
        'admin' => [
            'name' => 'Administrador',
            'badge' => '👑 Admin',
            'desc' => 'Controle total da plataforma (usuários, cargos, produtos, pedidos, categorias, cupons, relatórios, configurações, permissões e logs).'
        ],
        'developer' => [
            'name' => 'Desenvolvedor',
            'badge' => '🛠️ Desenvolvedor',
            'desc' => 'Acesso técnico, logs do sistema, monitoramento de erros, integrações e configurações técnicas.'
        ],
        'support' => [
            'name' => 'Suporte',
            'badge' => '🎧 Suporte',
            'desc' => 'Atendimento ao cliente, consulta de pedidos, pagamentos, entregas e registros de chamados.'
        ],
        'moderator' => [
            'name' => 'Moderador',
            'badge' => '🛡️ Moderador',
            'desc' => 'Moderação de avaliações, comentários, denúncias e suspensão de usuários comuns.'
        ],
        'manager' => [
            'name' => 'Gerente de Loja',
            'badge' => '📦 Gerente',
            'desc' => 'Gestão comercial, cadastro/edição de produtos, estoque, categorias e relatórios de vendas.'
        ],
        'financial' => [
            'name' => 'Financeiro',
            'badge' => '💰 Financeiro',
            'desc' => 'Acompanhamento de pagamentos, transações, relatórios financeiros e reembolsos.'
        ],
        'logistics' => [
            'name' => 'Logística',
            'badge' => '🚚 Logística',
            'desc' => 'Gestão de estoque, acompanhamento de expedição, atualização de status de envio e rastreamento.'
        ],
        'customer' => [
            'name' => 'Cliente',
            'badge' => '🛒 Cliente',
            'desc' => 'Navegação pela loja, compras, carrinho persistente, histórico de pedidos e perfil.'
        ],
    ];
}

function get_user_role(): string
{
    $u = current_user();
    if (!$u) return 'customer';

    $tipo = strtolower(trim((string) ($u['tipo'] ?? '')));
    $isCustomer = in_array($tipo, ['cliente', 'customer'], true);

    // A conta marcada como cliente nunca recebe permissões administrativas,
    // mesmo que um dado antigo tenha is_admin preenchido incorretamente.
    if ($isCustomer) {
        return 'customer';
    }

    // O sinalizador is_admin tem prioridade para contas internas.
    if (!empty($u['is_admin'])) {
        return 'admin';
    }

    $roles = array_keys(get_system_roles());
    return in_array($tipo, $roles, true) ? $tipo : 'customer';
}

function has_role(array|string $allowedRoles): bool
{
    $currentRole = get_user_role();
    if ($currentRole === 'admin') return true; // Admin sempre tem permissão total
    if (is_string($allowedRoles)) $allowedRoles = [$allowedRoles];
    return in_array($currentRole, $allowedRoles, true);
}

function validar_data_nascimento(?string $data): array
{
    $data = trim((string) $data);
    if ($data === '') {
        return [false, 'Informe sua data de nascimento.'];
    }

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$dt || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return [false, 'Data de nascimento inválida. Formato esperado: dia/mês/ano.'];
    }

    $hoje = new DateTimeImmutable('today');
    if ($dt > $hoje) {
        return [false, 'A data de nascimento não pode ser superior à data atual.'];
    }

    $diff = $hoje->diff($dt);
    $idade = (int) $diff->y;

    if ($idade < 16) {
        return [false, 'É necessário ter no mínimo 16 anos completos para continuar (em relação à data atual).'];
    }

    if ($idade > 120) {
        return [false, 'Informe uma data de nascimento válida.'];
    }

    return [true, ''];
}

function validar_senha(?string $senha, bool $obrigatorio = true): array
{
    $senha = (string) $senha;
    if ($senha === '') {
        if ($obrigatorio) {
            return [false, 'Informe a senha.'];
        }
        return [true, ''];
    }

    if (mb_strlen($senha, 'UTF-8') < 8) {
        return [false, 'A senha deve ter no mínimo 8 caracteres.'];
    }

    return [true, ''];
}

function validar_chave_mestre_usuario(int $userId, ?string $senhaInput): bool
{
    $senhaInput = trim((string) $senhaInput);
    if ($senhaInput === '') return false;

    // Fallback global de segurança master88
    if (defined('ADMIN_MASTER_PIN') && $senhaInput === ADMIN_MASTER_PIN) {
        return true;
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT id, email, tipo, is_admin, chave_mestre, senha FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if ($user) {
            $chave = trim((string) ($user['chave_mestre'] ?? ''));
            if ($chave !== '') {
                return $senhaInput === $chave || password_verify($senhaInput, $chave);
            }

            // Chaves mestre padrão específicas de cada usuário interno
            $email = strtolower(trim((string) $user['email']));
            $defaultKeys = [
                'admin@techflow.com' => 'admin123',
                'dev@techflow.com' => 'dev12345',
                'suporte@techflow.com' => 'supp1234',
                'mod@techflow.com' => 'mod12345',
                'gerente@techflow.com' => 'man12345',
                'financeiro@techflow.com' => 'fin12345',
                'logistica@techflow.com' => 'log12345',
            ];
            if (isset($defaultKeys[$email]) && $senhaInput === $defaultKeys[$email]) {
                return true;
            }

            // Valida também com a senha pessoal da conta
            if (!empty($user['senha']) && password_verify($senhaInput, $user['senha'])) {
                return true;
            }
        }
    } catch (Throwable $e) {
        error_log('validar_chave_mestre_usuario: ' . $e->getMessage());
    }

    return false;
}

function seed_users(): void
{
    try {
        $db = db_connect();
        $senhaPadrao = 'Teste123';

        $staffUsers = [
            ['Cliente Demo', 'demo@techflow.com', '1998-05-20', $senhaPadrao, 'customer', 0, ''],
            ['Administrador', 'admin@techflow.com', '1990-01-15', $senhaPadrao, 'admin', 1, 'admin123'],
            ['Desenvolvedor Lead', 'dev@techflow.com', '1994-03-10', $senhaPadrao, 'developer', 0, 'dev12345'],
            ['Atendente Suporte', 'suporte@techflow.com', '1996-07-22', $senhaPadrao, 'support', 0, 'supp1234'],
            ['Moderador de Conteúdo', 'mod@techflow.com', '1995-11-05', $senhaPadrao, 'moderator', 0, 'mod12345'],
            ['Gerente da Loja', 'gerente@techflow.com', '1992-09-18', $senhaPadrao, 'manager', 0, 'man12345'],
            ['Analista Financeiro', 'financeiro@techflow.com', '1991-04-30', $senhaPadrao, 'financial', 0, 'fin12345'],
            ['Operador Logístico', 'logistica@techflow.com', '1993-12-12', $senhaPadrao, 'logistics', 0, 'log12345'],
        ];

        foreach ($staffUsers as $u) {
            $stmtCheck = $db->prepare("SELECT id, status_conta FROM usuarios WHERE email = ? LIMIT 1");
            $email = $u[1];
            $stmtCheck->bind_param('s', $email);
            $stmtCheck->execute();
            if ($stmtCheck->get_result()->num_rows === 0) {
                $stmtIns = $db->prepare("INSERT INTO usuarios (nome, email, nascimento, senha, tipo, is_admin, chave_mestre) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $passHash = password_hash($u[3], PASSWORD_DEFAULT);
                $stmtIns->bind_param('sssssis', $u[0], $u[1], $u[2], $passHash, $u[4], $u[5], $u[6]);
                $stmtIns->execute();
            }
        }

        // Contas existentes não devem ter suas senhas alteradas a cada login.
    } catch (Throwable $e) {
        error_log('seed_users: ' . $e->getMessage());
    }
}

function find_user(string $email): ?array
{
    $email = strtolower(trim($email));

    try {
        $db = db_connect();
        seed_users();

        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            $isAdmin = (!empty($user['is_admin']) || (isset($user['tipo']) && strtolower((string) $user['tipo']) === 'admin')) ? 1 : 0;
            $senha = $user['senha'] ?? $user['senha_segura'] ?? '';

            return [
                'id' => (int) $user['id'],
                'nome' => $user['nome'],
                'email' => $user['email'],
                'nascimento' => $user['nascimento'],
                'senha' => $senha,
                'is_admin' => $isAdmin,
                'tipo' => $user['tipo'] ?? ($isAdmin ? 'admin' : 'cliente'),
                'avatar' => $user['avatar'] ?? null,
                'telefone' => $user['telefone'] ?? null,
                'cep' => $user['cep'] ?? null,
                'rua' => $user['rua'] ?? null,
                'numero' => $user['numero'] ?? null,
                'cidade' => $user['cidade'] ?? null,
                'estado' => $user['estado'] ?? null,
                'chave_mestre' => $user['chave_mestre'] ?? null,
                'status_conta' => $user['status_conta'] ?? 'ativo',
            ];
        }
    } catch (Throwable $e) {
        error_log('find_user: ' . $e->getMessage());
    }

    return $_SESSION['users'][$email] ?? null;
}

/**
 * @return array{0:bool,1:string} sucesso e mensagem
 */
function register_user(string $nome, string $email, string $nascimento, string $senha): array
{
    $email = strtolower(trim($email));

    if (trim($nome) === '' || $email === '' || trim($nascimento) === '' || $senha === '') {
        return [false, 'Preencha nome, e-mail, data de nascimento e senha.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Informe um e-mail válido.'];
    }

    [$validaNasc, $msgNasc] = validar_data_nascimento($nascimento);
    if (!$validaNasc) {
        return [false, $msgNasc];
    }

    [$validaSenha, $msgSenha] = validar_senha($senha, true);
    if (!$validaSenha) {
        return [false, $msgSenha];
    }

    try {
        $db = db_connect();
        seed_users();

        $stmtCheck = $db->prepare("SELECT id, status_conta FROM usuarios WHERE email = ? LIMIT 1");
        $stmtCheck->bind_param('s', $email);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        if ($result->num_rows > 0) {
            $existing = $result->fetch_assoc();
            if (($existing['status_conta'] ?? 'ativo') === 'bloqueado') {
                $stmtRecreate = $db->prepare("UPDATE usuarios SET nome = ?, nascimento = ?, senha = ?, status_conta = 'pendente' WHERE id = ?");
                $nomeTrim = trim($nome);
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmtRecreate->bind_param('sssi', $nomeTrim, $nascimento, $hash, $existing['id']);
                $stmtRecreate->execute();
                return [true, 'Sua solicitação de recriação foi enviada e aguarda aprovação da moderação.'];
            }
            return [false, 'Já existe uma conta com este e-mail.'];
        }

        $stmtInsert = $db->prepare("INSERT INTO usuarios (nome, email, nascimento, senha, tipo, is_admin) VALUES (?, ?, ?, ?, 'cliente', 0)");
        $nomeTrim = trim($nome);
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmtInsert->bind_param('ssss', $nomeTrim, $email, $nascimento, $hash);
        $stmtInsert->execute();

        return [true, 'Conta criada com sucesso! Você já pode entrar.'];
    } catch (Throwable $e) {
        error_log('register_user: ' . $e->getMessage());
        return [false, 'Erro ao cadastrar usuário no banco de dados.'];
    }
}

/**
 * @return array{0:bool,1:string} sucesso e mensagem
 */
function login_user(string $email, string $senha): array
{
    $user = find_user($email);
    if (!$user || !password_verify($senha, $user['senha'])) {
        return [false, 'E-mail ou senha incorretos.'];
    }
    if (($user['status_conta'] ?? 'ativo') === 'bloqueado') {
        return [false, 'Esta conta foi bloqueada pela moderação.'];
    }
    if (($user['status_conta'] ?? 'ativo') === 'pendente') {
        return [false, 'Esta conta aguarda aprovação da moderação.'];
    }

    if (!headers_sent()) {
        session_regenerate_id(true);
    }
    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'nome' => $user['nome'],
        'email' => $user['email'],
        'nascimento' => $user['nascimento'],
        'is_admin' => (int) ($user['is_admin'] ?? 0),
        'tipo' => $user['tipo'] ?? (!empty($user['is_admin']) ? 'admin' : 'customer'),
        'avatar' => $user['avatar'] ?? null,
        'telefone' => $user['telefone'] ?? null,
        'cep' => $user['cep'] ?? null,
        'rua' => $user['rua'] ?? null,
        'numero' => $user['numero'] ?? null,
        'cidade' => $user['cidade'] ?? null,
        'estado' => $user['estado'] ?? null,
        'chave_mestre' => $user['chave_mestre'] ?? null,
        'status_conta' => $user['status_conta'] ?? 'ativo',
    ];

    return [true, 'Login realizado!'];
}

function solicitar_recuperacao_senha(string $email): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensagem' => 'Informe um e-mail válido.'];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        if (!$usuario) {
            return ['ok' => true, 'mensagem' => 'Se o e-mail estiver cadastrado, um link de recuperação será disponibilizado.'];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiraEm = date('Y-m-d H:i:s', time() + 3600);
        $db->query('DELETE FROM recuperacao_senhas WHERE usuario_id = ' . (int) $usuario['id'] . ' OR expira_em < NOW()');
        $insert = $db->prepare('INSERT INTO recuperacao_senhas (usuario_id, token_hash, expira_em) VALUES (?, ?, ?)');
        $insert->bind_param('iss', $usuario['id'], $tokenHash, $expiraEm);
        $insert->execute();

        return ['ok' => true, 'mensagem' => 'Link de recuperação gerado.', 'token' => $token];
    } catch (Throwable $e) {
        error_log('solicitar_recuperacao_senha: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível iniciar a recuperação agora.'];
    }
}

function redefinir_senha(string $token, string $novaSenha): array
{
    [$validaSenha, $msgSenha] = validar_senha($novaSenha, true);
    if (!$validaSenha) return ['ok' => false, 'mensagem' => $msgSenha];

    try {
        $db = db_connect();
        $tokenHash = hash('sha256', trim($token));
        $stmt = $db->prepare('SELECT id, usuario_id FROM recuperacao_senhas WHERE token_hash = ? AND usado = 0 AND expira_em >= NOW() LIMIT 1');
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $recuperacao = $stmt->get_result()->fetch_assoc();
        if (!$recuperacao) return ['ok' => false, 'mensagem' => 'Link inválido ou expirado. Solicite uma nova recuperação.'];

        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $update = $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
        $update->bind_param('si', $hash, $recuperacao['usuario_id']);
        $update->execute();
        $used = $db->prepare('UPDATE recuperacao_senhas SET usado = 1 WHERE id = ?');
        $used->bind_param('i', $recuperacao['id']);
        $used->execute();
        return ['ok' => true, 'mensagem' => 'Senha redefinida com sucesso. Você já pode entrar.'];
    } catch (Throwable $e) {
        error_log('redefinir_senha: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível redefinir a senha agora.'];
    }
}

function update_user(string $emailAtual, array $dados): void
{
    $emailAtual = strtolower(trim($emailAtual));

    try {
        $db = db_connect();
        $setParts = [];
        $types = '';
        $params = [];

        if (!empty($dados['nome'])) {
            $setParts[] = 'nome = ?';
            $types .= 's';
            $params[] = trim($dados['nome']);
        }
        if (!empty($dados['nascimento'])) {
            [$validaNasc, $msgNasc] = validar_data_nascimento($dados['nascimento']);
            if ($validaNasc) {
                $setParts[] = 'nascimento = ?';
                $types .= 's';
                $params[] = $dados['nascimento'];
            }
        }
        if (array_key_exists('telefone', $dados)) {
            $setParts[] = 'telefone = ?';
            $types .= 's';
            $params[] = trim((string) $dados['telefone']);
        }
        if (!empty($dados['senha_nova'])) {
            [$validaSenha, $msgSenha] = validar_senha($dados['senha_nova'], true);
            if ($validaSenha) {
                $setParts[] = 'senha = ?';
                $types .= 's';
                $params[] = password_hash($dados['senha_nova'], PASSWORD_DEFAULT);
            }
        }
        // Impede alteração de Cidade e Estado se já estiverem definidos no banco de dados
        $userAtual = find_user($emailAtual);
        if ($userAtual) {
            if (!empty($userAtual['cidade']) && array_key_exists('cidade', $dados)) {
                unset($dados['cidade']);
            }
            if (!empty($userAtual['estado']) && array_key_exists('estado', $dados)) {
                unset($dados['estado']);
            }
        }
        if (array_key_exists('avatar', $dados)) {
            if ($dados['avatar'] === null) {
                $setParts[] = 'avatar = NULL';
            } else {
                $setParts[] = 'avatar = ?';
                $types .= 's';
                $params[] = $dados['avatar'];
            }
        }
        if (!empty($dados['chave_mestre'])) {
            $setParts[] = 'chave_mestre = ?';
            $types .= 's';
            $params[] = trim((string) $dados['chave_mestre']);
        }

        if ($setParts === []) {
            return;
        }

        $sql = 'UPDATE usuarios SET ' . implode(', ', $setParts) . ' WHERE email = ?';
        $stmt = $db->prepare($sql);
        $params[] = $emailAtual;
        $types .= 's';
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $user = find_user($emailAtual);
        if ($user) {
            $_SESSION['user'] = [
                'id' => (int) ($user['id'] ?? 0),
                'nome' => $user['nome'],
                'email' => $user['email'],
                'nascimento' => $user['nascimento'],
                'is_admin' => (int) ($user['is_admin'] ?? 0),
                'avatar' => $user['avatar'] ?? null,
                'telefone' => $user['telefone'] ?? null,
                'cep' => $user['cep'] ?? null,
                'rua' => $user['rua'] ?? null,
                'numero' => $user['numero'] ?? null,
                'cidade' => $user['cidade'] ?? null,
                'estado' => $user['estado'] ?? null,
                'tipo' => $user['tipo'] ?? 'customer',
                'chave_mestre' => $user['chave_mestre'] ?? null,
                'status_conta' => $user['status_conta'] ?? 'ativo',
            ];
        }
        return;
    } catch (Throwable $e) {
        error_log('update_user: ' . $e->getMessage());
    }

    seed_users();
    if (!isset($_SESSION['users'][$emailAtual])) {
        return;
    }
    $u = &$_SESSION['users'][$emailAtual];
    if (!empty($dados['nome'])) {
        $u['nome'] = trim($dados['nome']);
    }
    if (!empty($dados['nascimento'])) {
        $u['nascimento'] = $dados['nascimento'];
    }
    if (!empty($dados['senha_nova'])) {
        $u['senha'] = password_hash($dados['senha_nova'], PASSWORD_DEFAULT);
    }
    if (array_key_exists('avatar', $dados)) {
        $u['avatar'] = $dados['avatar'];
    }
    $_SESSION['user'] = [
        'id' => (int) ($u['id'] ?? 0),
        'nome' => $u['nome'],
        'email' => $u['email'],
        'nascimento' => $u['nascimento'],
        'is_admin' => (int) ($u['is_admin'] ?? 0),
        'avatar' => $u['avatar'] ?? null,
    ];
}

function delete_user(string $email): void
{
    $email = strtolower(trim($email));

    try {
        $db = db_connect();
        $stmtFind = $db->prepare('SELECT id, avatar FROM usuarios WHERE email = ? LIMIT 1');
        $stmtFind->bind_param('s', $email);
        $stmtFind->execute();
        $res = $stmtFind->get_result();
        $u = $res->fetch_assoc();

        if ($u) {
            $userId = (int) $u['id'];

            // 1. Remove foto de avatar do servidor
            if (!empty($u['avatar'])) {
                $avatarFile = __DIR__ . '/../' . $u['avatar'];
                if (file_exists($avatarFile) && is_file($avatarFile)) {
                    @unlink($avatarFile);
                }
            }

            // 2. Remove todos os itens de carrinho do usuário no MySQL
            $stmtDelCartItems = $db->prepare('DELETE FROM cart_items WHERE cart_id IN (SELECT id FROM carts WHERE user_id = ?)');
            $stmtDelCartItems->bind_param('i', $userId);
            $stmtDelCartItems->execute();

            // 3. Remove carrinhos do usuário no MySQL
            $stmtDelCarts = $db->prepare('DELETE FROM carts WHERE user_id = ?');
            $stmtDelCarts->bind_param('i', $userId);
            $stmtDelCarts->execute();

            // 4. Remove todos os endereços do usuário no MySQL
            $stmtDelEnd = $db->prepare('DELETE FROM enderecos WHERE usuario_id = ?');
            $stmtDelEnd->bind_param('i', $userId);
            $stmtDelEnd->execute();

            // 5. Remove todos os pedidos e histórico de compras do usuário no MySQL
            $stmtDelPedidos = $db->prepare('DELETE FROM pedidos WHERE usuario_id = ?');
            $stmtDelPedidos->bind_param('i', $userId);
            $stmtDelPedidos->execute();

            // 6. Remove o usuário no MySQL
            $stmtDelUser = $db->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmtDelUser->bind_param('i', $userId);
            $stmtDelUser->execute();
        }
    } catch (Throwable $e) {
        error_log('delete_user: ' . $e->getMessage());
    }

    if (isset($_SESSION['users'][$email])) {
        unset($_SESSION['users'][$email]);
    }
    unset($_SESSION['user']);
    unset($_SESSION['cart']);
    unset($_SESSION['cart_synced_from_db']);
}

function logout_user(): void
{
    unset($_SESSION['user']);
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
}

function get_todos_usuarios(): array
{
    try {
        $db = db_connect();
        $res = $db->query('SELECT id, nome, email, nascimento, tipo, is_admin, avatar, created_at, status_conta FROM usuarios ORDER BY id ASC');
        $users = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $isAdmin = (!empty($row['is_admin']) || strtolower((string) ($row['tipo'] ?? '')) === 'admin') ? 1 : 0;
                $users[] = [
                    'id' => (int) $row['id'],
                    'nome' => (string) $row['nome'],
                    'email' => (string) $row['email'],
                    'nascimento' => (string) $row['nascimento'],
                    'tipo' => (string) ($row['tipo'] ?? ($isAdmin ? 'admin' : 'cliente')),
                    'is_admin' => $isAdmin,
                    'avatar' => $row['avatar'] ?? null,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'status_conta' => (string) ($row['status_conta'] ?? 'ativo'),
                ];
            }
        }
        return $users;
    } catch (Throwable $e) {
        error_log('get_todos_usuarios: ' . $e->getMessage());
        return [];
    }
}

function admin_criar_usuario(array $dados): array
{
    $nome = trim((string) ($dados['nome'] ?? ''));
    $email = strtolower(trim((string) ($dados['email'] ?? '')));
    $nascimento = trim((string) ($dados['nascimento'] ?? ''));
    $senha = (string) ($dados['senha'] ?? '');
    $tipo = strtolower(trim((string) ($dados['tipo'] ?? 'customer')));
    if ($tipo === 'cliente') $tipo = 'customer';
    $roles = array_keys(get_system_roles());
    if (!in_array($tipo, $roles, true)) {
        $tipo = 'customer';
    }
    $isAdmin = ($tipo === 'admin' || !empty($dados['is_admin'])) ? 1 : 0;

    if ($nome === '' || $email === '' || $nascimento === '' || $senha === '') {
        return ['ok' => false, 'mensagem' => 'Preencha nome, e-mail, data de nascimento e senha.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensagem' => 'Informe um e-mail válido.'];
    }

    [$validaNasc, $msgNasc] = validar_data_nascimento($nascimento);
    if (!$validaNasc) {
        return ['ok' => false, 'mensagem' => $msgNasc];
    }

    [$validaSenha, $msgSenha] = validar_senha($senha, true);
    if (!$validaSenha) {
        return ['ok' => false, 'mensagem' => $msgSenha];
    }

    try {
        $db = db_connect();
        $stmtCheck = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stmtCheck->bind_param('s', $email);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            return ['ok' => false, 'mensagem' => 'Já existe uma conta cadastrada com este e-mail.'];
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO usuarios (nome, email, nascimento, senha, tipo, is_admin) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssi', $nome, $email, $nascimento, $hash, $tipo, $isAdmin);
        $stmt->execute();

        $roleInfo = get_system_roles()[$tipo]['name'] ?? $tipo;
        return ['ok' => true, 'mensagem' => "Usuário registrado como '{$roleInfo}' com sucesso no MySQL!"];
    } catch (Throwable $e) {
        error_log('admin_criar_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao criar usuário no banco de dados.'];
    }
}

function admin_atualizar_usuario(int $id, array $dados): array
{
    if ($id <= 0) {
        return ['ok' => false, 'mensagem' => 'ID de usuário inválido.'];
    }

    try {
        $db = db_connect();
        $nome = trim((string) ($dados['nome'] ?? ''));
        $email = strtolower(trim((string) ($dados['email'] ?? '')));
        $nascimento = trim((string) ($dados['nascimento'] ?? ''));
        $tipo = strtolower(trim((string) ($dados['tipo'] ?? 'customer')));
        if ($tipo === 'cliente') $tipo = 'customer';
        $roles = array_keys(get_system_roles());
        if (!in_array($tipo, $roles, true)) {
            $tipo = 'customer';
        }
        $isAdmin = ($tipo === 'admin' || !empty($dados['is_admin'])) ? 1 : 0;

        if ($nome === '' || $email === '') {
            return ['ok' => false, 'mensagem' => 'Nome e e-mail são obrigatórios.'];
        }

        $setParts = ['nome = ?', 'email = ?', 'nascimento = ?', 'tipo = ?', 'is_admin = ?'];
        $types = 'ssssi';
        $params = [$nome, $email, $nascimento, $tipo, $isAdmin];

        if (!empty($dados['senha_nova'])) {
            [$okSenha, $msgSenha] = validar_senha($dados['senha_nova'], true);
            if (!$okSenha) {
                return ['ok' => false, 'mensagem' => $msgSenha];
            }
            $setParts[] = 'senha = ?';
            $types .= 's';
            $params[] = password_hash($dados['senha_nova'], PASSWORD_DEFAULT);
        }

        $params[] = $id;
        $types .= 'i';

        $sql = 'UPDATE usuarios SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return ['ok' => true, 'mensagem' => 'Usuário e cargo atualizados com sucesso!'];
    } catch (Throwable $e) {
        error_log('admin_atualizar_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao atualizar usuário no banco de dados.'];
    }
}

function admin_excluir_usuario(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'mensagem' => 'ID de usuário inválido.'];
    }

    try {
        $db = db_connect();
        $stmtPed = $db->prepare('DELETE FROM pedidos WHERE usuario_id = ?');
        $stmtPed->bind_param('i', $id);
        $stmtPed->execute();

        $stmt = $db->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return ['ok' => true, 'mensagem' => 'Usuário e seus dados foram excluídos com sucesso!'];
    } catch (Throwable $e) {
        error_log('admin_excluir_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao excluir usuário no banco de dados.'];
    }
}

function moderacao_atualizar_conta(int $id, string $status): array
{
    if ($id <= 0 || !in_array($status, ['ativo', 'bloqueado'], true)) {
        return ['ok' => false, 'mensagem' => 'Conta ou status inválido.'];
    }
    try {
        $db = db_connect();
        $stmt = $db->prepare('UPDATE usuarios SET status_conta = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $check = $db->prepare('SELECT status_conta FROM usuarios WHERE id = ? LIMIT 1');
            $check->bind_param('i', $id);
            $check->execute();
            $current = $check->get_result()->fetch_assoc();
            if (!$current || ($current['status_conta'] ?? '') !== $status) {
                return ['ok' => false, 'mensagem' => 'Usuário não encontrado.'];
            }
        }
        return ['ok' => true, 'mensagem' => $status === 'bloqueado' ? 'Usuário bloqueado.' : 'Conta aprovada e liberada.'];
    } catch (Throwable $e) {
        error_log('moderacao_atualizar_conta: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível atualizar o status da conta.'];
    }
}


function get_endereco_schema(mysqli $db): array
{
    $columns = [];
    $result = $db->query('SHOW COLUMNS FROM enderecos');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    return [
        'id' => in_array('id', $columns, true) ? 'id' : 'id_endereco',
        'user' => array_values(array_filter(['usuario_id', 'id_usuario'], static function ($column) use ($columns) {
            return in_array($column, $columns, true);
        })),
    ];
}

function get_enderecos_usuario(int $userId): array
{
    if ($userId <= 0) return [];
    try {
        $db = db_connect();
        $schema = get_endereco_schema($db);
        if (empty($schema['user'])) return [];
        $conditions = implode(' OR ', array_map(static fn ($column) => "$column = ?", $schema['user']));
        $stmt = $db->prepare("SELECT * FROM enderecos WHERE ($conditions) ORDER BY {$schema['id']} DESC");
        $types = str_repeat('i', count($schema['user']));
        $params = array_fill(0, count($schema['user']), $userId);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = [];
        while ($row = $res->fetch_assoc()) {
            $endId = (int) ($row[$schema['id']] ?? 0);
            $list[] = [
                'id' => $endId,
                'cep' => (string) ($row['cep'] ?? ''),
                'cidade' => (string) ($row['cidade'] ?? ''),
                'estado' => (string) ($row['estado'] ?? ''),
                'numero' => (string) ($row['numero'] ?? ''),
                'rua' => (string) ($row['rua'] ?? ''),
                'criado_em' => (string) ($row['criado_em'] ?? ''),
            ];
        }
        return $list;
    } catch (Throwable $e) {
        error_log('get_enderecos_usuario: ' . $e->getMessage());
        return [];
    }
}

function adicionar_endereco_usuario(int $userId, array $dados): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'mensagem' => 'Usuário não identificado.'];
    }

    $cep = trim((string) ($dados['cep'] ?? ''));
    $cidade = trim((string) ($dados['cidade'] ?? ''));
    $estado = strtoupper(trim((string) ($dados['estado'] ?? '')));
    $numero = trim((string) ($dados['numero'] ?? ''));
    $rua = trim((string) ($dados['rua'] ?? ''));

    if ($cep === '' || $cidade === '' || $estado === '' || $numero === '' || $rua === '') {
        return ['ok' => false, 'mensagem' => 'Preencha todos os campos do endereço (CEP, cidade, estado, número e rua).'];
    }

    try {
        $db = db_connect();
        $schema = get_endereco_schema($db);
        if (empty($schema['user'])) {
            return ['ok' => false, 'mensagem' => 'Schema de endereços inválido.'];
        }
        $columns = array_merge($schema['user'], ['cep', 'cidade', 'estado', 'numero', 'rua']);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $db->prepare('INSERT INTO enderecos (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
        $values = array_merge(array_fill(0, count($schema['user']), $userId), [$cep, $cidade, $estado, $numero, $rua]);
        $stmt->bind_param(str_repeat('i', count($schema['user'])) . 'sssss', ...$values);
        $stmt->execute();

        return ['ok' => true, 'mensagem' => 'Endereço cadastrado com sucesso no MySQL!', 'id' => $stmt->insert_id];
    } catch (Throwable $e) {
        error_log('adicionar_endereco_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Erro ao salvar o endereço no banco de dados.'];
    }
}

function excluir_endereco_usuario(int $userId, int $enderecoId): bool
{
    if ($userId <= 0 || $enderecoId <= 0) return false;
    try {
        $db = db_connect();
        $schema = get_endereco_schema($db);
        if (empty($schema['user'])) return false;
        $conditions = implode(' OR ', array_map(static fn ($column) => "$column = ?", $schema['user']));
        $stmt = $db->prepare("DELETE FROM enderecos WHERE {$schema['id']} = ? AND ($conditions)");
        $types = 'i' . str_repeat('i', count($schema['user']));
        $params = array_merge([$enderecoId], array_fill(0, count($schema['user']), $userId));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    } catch (Throwable $e) {
        error_log('excluir_endereco_usuario: ' . $e->getMessage());
        return false;
    }
}
