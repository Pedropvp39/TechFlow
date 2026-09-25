<?php
/**
 * ============================================================================
 * AUTENTICAÇÃO DE USUÁRIOS
 * ============================================================================
 *
 * Este arquivo reúne as funções de conta do sistema:
 *   - register_user()      cria uma nova conta (usada em pages/cadastro.php)
 *   - login_user()         valida e-mail e senha (usado em pages/login.php)
 *   - update_user()        atualiza dados do perfil
 *   - get_todos_usuarios() lista as contas para a área administrativa
 *
 * As senhas são guardadas com password_hash() (bcrypt) e conferidas com
 * password_verify(), então qualquer senha nova funciona normalmente — inclusive
 * contas criadas direto no banco por outro computador, desde que a senha tenha
 * sido gravada com o mesmo padrão de hash.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../php/conexao.php';

/**
 * Normaliza a linha de um usuário para o formato usado pelas telas.
 */
function normalize_usuario(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'nome' => (string) ($row['nome'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'nascimento' => (string) ($row['nascimento'] ?? ''),
        'tipo' => (string) ($row['tipo'] ?? 'cliente'),
        'is_admin' => (int) ($row['is_admin'] ?? 0),
        'avatar' => $row['avatar'] ?? null,
        'telefone' => $row['telefone'] ?? null,
        'cep' => $row['cep'] ?? null,
        'rua' => $row['rua'] ?? null,
        'numero' => $row['numero'] ?? null,
        'cidade' => $row['cidade'] ?? null,
        'estado' => $row['estado'] ?? null,
        'status_conta' => (string) ($row['status_conta'] ?? 'ativo'),
    ];
}

/**
 * Busca um usuário pelo e-mail (ou null quando não existe).
 */
function find_user_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT * FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return $row ? normalize_usuario($row) : null;
    } catch (Throwable $e) {
        error_log('find_user_by_email: ' . $e->getMessage());
        return null;
    }
}

/**
 * Calcula a idade a partir da data de nascimento (formato AAAA-MM-DD).
 */
function idade_de_nascimento(string $nascimento): int
{
    $nascimento = trim($nascimento);
    if ($nascimento === '') {
        return 0;
    }

    try {
        $data = new DateTime($nascimento);
    } catch (Throwable $e) {
        return 0;
    }

    $hoje = new DateTime('today');
    if ($data > $hoje) {
        return 0;
    }

    return (int) $data->diff($hoje)->y;
}

/**
 * Cria uma nova conta de cliente.
 *
 * Devolve [true, mensagem] em caso de sucesso ou [false, motivo] em caso de erro.
 * Cria o administrador padrão apenas quando a tabela estiver vazia.
 */
function register_user(string $nome, string $email, string $nascimento, string $senha): array
{
    $nome = trim($nome);
    $email = strtolower(trim($email));

    // --- Validações básicas ---
    if ($nome === '') {
        return [false, 'Informe seu nome completo.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Informe um e-mail válido.'];
    }

    $tamanhoSenha = strlen($senha);
    if ($tamanhoSenha < 8 || $tamanhoSenha > 16) {
        return [false, 'A senha deve ter entre 8 e 16 caracteres.'];
    }

    if ($nascimento === '') {
        return [false, 'Informe sua data de nascimento.'];
    }
    if (idade_de_nascimento($nascimento) < 16) {
        return [false, 'Você deve ter no mínimo 16 anos completos para se cadastrar.'];
    }

    try {
        $db = db_connect();

        // E-mail já cadastrado?
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return [false, 'Este e-mail já está cadastrado.'];
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $db->prepare('INSERT INTO usuarios (nome, email, nascimento, senha, tipo, is_admin, status_conta) VALUES (?, ?, ?, ?, ?, 0, ?)');
        $tipo = 'cliente';
        $status = 'ativo';
        $stmt->bind_param('ssssss', $nome, $email, $nascimento, $hash, $tipo, $status);

        if (!$stmt->execute()) {
            // E-mail repetido pode chegar aqui em caso de corrida entre duas abas.
            if ((int) $db->errno === 1062) {
                return [false, 'Este e-mail já está cadastrado.'];
            }
            return [false, 'Não foi possível criar a conta. Tente novamente.'];
        }

        return [true, 'Conta criada com sucesso! Faça login para continuar.'];
    } catch (Throwable $e) {
        error_log('register_user: ' . $e->getMessage());
        return [false, 'Não foi possível criar a conta agora. Verifique se o banco de dados está ligado.'];
    }
}

/**
 * Valida o login e monta a sessão do usuário.
 *
 * Devolve [true, mensagem] quando entra, ou [false, motivo] quando falha.
 */
function login_user(string $email, string $senha): array
{
    $email = strtolower(trim($email));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Informe um e-mail válido.'];
    }
    if ($senha === '') {
        return [false, 'Informe sua senha.'];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT * FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if (!$row) {
            return [false, 'E-mail ou senha incorretos.'];
        }

        $hashArmazenado = (string) ($row['senha'] ?? '');

        // Aceita senha gravada com password_hash() (formato atual do sistema)
        // e também texto puro, para bases antigas importadas de outro PC.
        $senhaConfere = password_verify($senha, $hashArmazenado);
        $senhaAntiga = false;
        if (!$senhaConfere && $hashArmazenado !== '' && $hashArmazenado === $senha) {
            $senhaConfere = true;
            $senhaAntiga = true;
        }

        if (!$senhaConfere) {
            return [false, 'E-mail ou senha incorretos.'];
        }

        // Conta bloqueada ou aguardando aprovação não entra.
        $statusConta = strtolower((string) ($row['status_conta'] ?? 'ativo'));
        if ($statusConta === 'bloqueado') {
            return [false, 'Esta conta está bloqueada. Fale com o administrador.'];
        }
        if ($statusConta === 'pendente') {
            return [false, 'Sua conta ainda aguarda aprovação do administrador.'];
        }

        $usuario = normalize_usuario($row);

        // Migra a senha antiga (texto puro) para hash automaticamente no login.
        if ($senhaAntiga) {
            $novoHash = password_hash($senha, PASSWORD_DEFAULT);
            $up = $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
            $up->bind_param('si', $novoHash, $usuario['id']);
            $up->execute();
        }

        // Guarda o usuário na sessão (usado por current_user() e is_admin()).
        $_SESSION['user'] = $usuario;

        // Renova o id da sessão para evitar fixação de sessão. Só executa
        // quando ainda é possível enviar cabeçalhos (evita warning).
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        return [true, 'Login realizado com sucesso!'];
    } catch (Throwable $e) {
        error_log('login_user: ' . $e->getMessage());
        return [false, 'Não foi possível entrar agora. Verifique se o banco de dados está ligado.'];
    }
}

/**
 * Atualiza os dados de um usuário identificado pelo e-mail.
 * Aceita também a chave 'senha_nova' para redefinir a senha.
 *
 * Devolve true quando conseguiu salvar.
 */
function update_user(?string $email, array $dados): bool
{
    $email = strtolower(trim((string) $email));
    if ($email === '') {
        return false;
    }

    try {
        $db = db_connect();

        // Colunas que podem ser atualizadas e o seu tipo de bind.
        $colunasPermitidas = [
            'nome' => 's',
            'nascimento' => 's',
            'tipo' => 's',
            'is_admin' => 'i',
            'avatar' => 's',
            'telefone' => 's',
            'cep' => 's',
            'rua' => 's',
            'numero' => 's',
            'cidade' => 's',
            'estado' => 's',
            'chave_mestre' => 's',
            'status_conta' => 's',
        ];

        $sets = [];
        $tipos = '';
        $valores = [];

        foreach ($dados as $campo => $valor) {
            if ($campo === 'senha_nova') {
                if (is_string($valor) && trim($valor) !== '') {
                    $sets[] = 'senha = ?';
                    $tipos .= 's';
                    $valores[] = password_hash(trim($valor), PASSWORD_DEFAULT);
                }
                continue;
            }

            if (!isset($colunasPermitidas[$campo])) {
                continue;
            }

            $sets[] = "`$campo` = ?";
            $tipos .= $colunasPermitidas[$campo];

            if ($colunasPermitidas[$campo] === 'i') {
                $valores[] = (int) $valor;
            } elseif ($valor === null) {
                $valores[] = null;
            } else {
                $valores[] = (string) $valor;
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = 'UPDATE usuarios SET ' . implode(', ', $sets) . ' WHERE LOWER(email) = ?';
        $tipos .= 's';
        $valores[] = $email;

        $stmt = $db->prepare($sql);
        $stmt->bind_param($tipos, ...$valores);
        $ok = $stmt->execute();

        // Mantém a sessão sincronizada com o que foi salvo no banco.
        if ($ok && isset($_SESSION['user']['email']) && strtolower((string) $_SESSION['user']['email']) === $email) {
            $atualizado = find_user_by_email($email);
            if ($atualizado) {
                $_SESSION['user'] = $atualizado;
            }
        }

        return $ok;
    } catch (Throwable $e) {
        error_log('update_user: ' . $e->getMessage());
        return false;
    }
}

/**
 * Lista todas as contas para as telas de administração.
 */
function get_todos_usuarios(): array
{
    try {
        $db = db_connect();
        $result = $db->query('SELECT * FROM usuarios ORDER BY id DESC');
        if (!$result) {
            return [];
        }

        $usuarios = [];
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = normalize_usuario($row);
        }
        return $usuarios;
    } catch (Throwable $e) {
        error_log('get_todos_usuarios: ' . $e->getMessage());
        return [];
    }
}

/**
 * Exclui uma conta pelo id (usado na área administrativa).
 */
function delete_user(int $id): bool
{
    if ($id <= 0) {
        return false;
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    } catch (Throwable $e) {
        error_log('delete_user: ' . $e->getMessage());
        return false;
    }
}/**
 * ============================================================================
 * PERFIS (CARGOS) DO SISTEMA
 * ============================================================================
 *
 * Cada perfil possui:
 *   name  -> nome exibido no painel
 *   badge -> etiqueta curta mostrada nas tabelas
 *   desc  -> descrição usada no topo do painel
 */
function get_system_roles(): array
{
    return [
        'admin' => [
            'name' => 'Administrador',
            'badge' => '👑 Administrador',
            'desc' => 'Acesso total: produtos, usuários, pedidos, cupons e moderação.',
        ],
        'developer' => [
            'name' => 'Desenvolvedor',
            'badge' => '💻 Desenvolvedor',
            'desc' => 'Manutenção técnica do sistema e ajustes de estrutura.',
        ],
        'support' => [
            'name' => 'Suporte',
            'badge' => '🎧 Suporte',
            'desc' => 'Atendimento aos chamados e ajuda aos clientes.',
        ],
        'moderator' => [
            'name' => 'Moderador',
            'badge' => '🛡️ Moderador',
            'desc' => 'Aprovação e revisão de avaliações e denúncias.',
        ],
        'manager' => [
            'name' => 'Gerente',
            'badge' => '📊 Gerente',
            'desc' => 'Acompanhamento de vendas, produtos e desempenho da loja.',
        ],
        'financial' => [
            'name' => 'Financeiro',
            'badge' => '💰 Financeiro',
            'desc' => 'Controle de valores, pedidos pagos e relatórios.',
        ],
        'logistics' => [
            'name' => 'Logística',
            'badge' => '🚚 Logística',
            'desc' => 'Expedição, rastreio e status de entrega dos pedidos.',
        ],
        'customer' => [
            'name' => 'Cliente',
            'badge' => '🛒 Cliente',
            'desc' => 'Compra de produtos e acompanhamento dos próprios pedidos.',
        ],
    ];
}

/**
 * Descobre a chave do perfil do usuário logado.
 *
 * O banco guarda 'cliente' (padrão) ou 'admin'; aqui o valor é traduzido para
 * as chaves usadas pelo sistema de painéis ('customer', 'admin', etc.).
 */
function get_user_role(?array $user = null): string
{
    $user = $user ?? current_user();
    if (!$user) {
        return 'customer';
    }

    // Administrador manda em tudo.
    if (!empty($user['is_admin'])) {
        return 'admin';
    }

    $tipo = strtolower(trim((string) ($user['tipo'] ?? '')));
    if ($tipo === '' || $tipo === 'cliente') {
        return 'customer';
    }
    if ($tipo === 'admin') {
        return 'admin';
    }

    $roles = get_system_roles();
    return isset($roles[$tipo]) ? $tipo : 'customer';
}

/**
 * Valida a chave mestre pessoal de uma conta (usada nas ações do painel).
 *
 * Aceita a chave gravada com password_hash() no campo `chave_mestre`.
 * Quando a conta ainda não definiu uma chave própria, aceita a chave mestre
 * global definida em ADMIN_MASTER_PIN (padrão: master88).
 */
function validar_chave_mestre_usuario(int $userId, ?string $chave): bool
{
    $chave = trim((string) $chave);
    if ($userId <= 0 || $chave === '') {
        return false;
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT chave_mestre FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $hash = $row['chave_mestre'] ?? null;

        // A conta já possui chave própria cadastrada.
        if (is_string($hash) && $hash !== '') {
            return password_verify($chave, $hash) || $hash === $chave;
        }
    } catch (Throwable $e) {
        error_log('validar_chave_mestre_usuario: ' . $e->getMessage());
    }

    // Sem chave própria: usa a chave mestre global do sistema.
    return validar_senha_mestre_admin($chave);
}

/**
 * ============================================================================
 * ENDEREÇOS DO USUÁRIO
 * ============================================================================
 */

/**
 * Lista os endereços cadastrados de um usuário.
 */
function get_enderecos_usuario(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY id DESC');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            return [];
        }

        $enderecos = [];
        while ($row = $result->fetch_assoc()) {
            $enderecos[] = [
                'id' => (int) ($row['id'] ?? $row['id_endereco'] ?? 0),
                'usuario_id' => (int) ($row['usuario_id'] ?? 0),
                'cep' => (string) ($row['cep'] ?? ''),
                'cidade' => (string) ($row['cidade'] ?? ''),
                'estado' => (string) ($row['estado'] ?? ''),
                'numero' => (string) ($row['numero'] ?? ''),
                'rua' => (string) ($row['rua'] ?? ''),
            ];
        }
        return $enderecos;
    } catch (Throwable $e) {
        error_log('get_enderecos_usuario: ' . $e->getMessage());
        return [];
    }
}

/**
 * Cadastra um novo endereço para o usuário.
 * Devolve ['ok' => bool, 'mensagem' => string, 'id' => int].
 */
function adicionar_endereco_usuario(int $userId, array $dados): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'mensagem' => 'Usuário inválido.', 'id' => 0];
    }

    $cep = trim((string) ($dados['cep'] ?? ''));
    $cidade = trim((string) ($dados['cidade'] ?? ''));
    $estado = trim((string) ($dados['estado'] ?? ''));
    $numero = trim((string) ($dados['numero'] ?? ''));
    $rua = trim((string) ($dados['rua'] ?? ''));

    if ($cep === '' || $cidade === '' || $estado === '' || $numero === '' || $rua === '') {
        return ['ok' => false, 'mensagem' => 'Preencha todos os campos do endereço.', 'id' => 0];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('INSERT INTO enderecos (usuario_id, cep, cidade, estado, numero, rua) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssss', $userId, $cep, $cidade, $estado, $numero, $rua);

        if (!$stmt->execute()) {
            return ['ok' => false, 'mensagem' => 'Não foi possível salvar o endereço.', 'id' => 0];
        }

        return ['ok' => true, 'mensagem' => 'Endereço adicionado com sucesso!', 'id' => (int) $db->insert_id];
    } catch (Throwable $e) {
        error_log('adicionar_endereco_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível salvar o endereço agora.', 'id' => 0];
    }
}

/**
 * Remove um endereço, garantindo que ele pertence ao usuário informado.
 */
function excluir_endereco_usuario(int $userId, int $enderecoId): bool
{
    if ($userId <= 0 || $enderecoId <= 0) {
        return false;
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('DELETE FROM enderecos WHERE id = ? AND usuario_id = ?');
        $stmt->bind_param('ii', $enderecoId, $userId);
        return $stmt->execute() && $stmt->affected_rows > 0;
    } catch (Throwable $e) {
        error_log('excluir_endereco_usuario: ' . $e->getMessage());
        return false;
    }
}/**
 * ============================================================================
 * PERMISSÕES
 * ============================================================================
 */

/**
 * Verifica se o usuário logado possui um dos papéis informados.
 * Aceita tanto uma lista (array) quanto um único papel (string).
 *
 * Exemplos:
 *   has_role('admin')
 *   has_role(['admin', 'manager'])
 */
function has_role($papeis): bool
{
    if (is_string($papeis)) {
        $papeis = [$papeis];
    }
    if (!is_array($papeis) || empty($papeis)) {
        return false;
    }

    $atual = get_user_role();
    foreach ($papeis as $papel) {
        if (strtolower(trim((string) $papel)) === $atual) {
            return true;
        }
    }

    return false;
}

/**
 * Encerra a sessão do usuário atual.
 */
function logout_user(): void
{
    // Limpa os dados da sessão atual.
    $_SESSION = [];

    // Invalida o cookie da sessão no navegador.
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'] ?? '/',
            $parametros['domain'] ?? '',
            (bool) ($parametros['secure'] ?? false),
            (bool) ($parametros['httponly'] ?? true)
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * ============================================================================
 * COMPATIBILIDADE
 * ============================================================================
 */

/**
 * Busca um usuário pelo e-mail ou id.
 * (Nome usado por cart-sync.php, finalizar-pedido.php e pedidos-acao.php.)
 */
function find_user($emailOrId): ?array
{
    if (is_numeric($emailOrId)) {
        $id = (int) $emailOrId;
        if ($id <= 0) {
            return null;
        }
        try {
            $db = db_connect();
            $stmt = $db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            return $row ? normalize_usuario($row) : null;
        } catch (Throwable $e) {
            error_log('find_user: ' . $e->getMessage());
            return null;
        }
    }

    return find_user_by_email((string) $emailOrId);
}

/**
 * Valida os campos de cadastro do painel do usuário.
 * Devolve true quando os dados são aceitáveis.
 */
function validar_data_nascimento(?string $nascimento): bool
{
    $nascimento = trim((string) $nascimento);
    if ($nascimento === '') {
        return false;
    }

    $idade = idade_de_nascimento($nascimento);
    return $idade >= 16 && $idade <= 120;
}

/**
 * Valida uma senha (8 a 16 caracteres).
 */
function validar_senha(?string $senha): bool
{
    $senha = (string) $senha;
    return strlen($senha) >= 8 && strlen($senha) <= 16;
}

/**
 * ============================================================================
 * RECUPERAÇÃO DE SENHA
 * ============================================================================
 */

/**
 * Gera um token de recuperação de senha para o e-mail informado.
 *
 * Devolve ['ok' => bool, 'mensagem' => string, 'token' => ?string].
 * O token só é devolvido para ser usado na mesma requisição de redefinição.
 */
function solicitar_recuperacao_senha(string $email): array
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensagem' => 'Informe um e-mail válido.', 'token' => null];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        // Não revela se o e-mail existe ou não (segurança).
        if (!$row) {
            return ['ok' => false, 'mensagem' => 'E-mail não encontrado em nosso sistema.', 'token' => null];
        }

        $usuarioId = (int) $row['id'];
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expira = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

        // Invalida tokens anteriores não usados deste usuário.
        $up = $db->prepare('UPDATE recuperacao_senhas SET usado = 1 WHERE usuario_id = ? AND usado = 0');
        $up->bind_param('i', $usuarioId);
        $up->execute();

        $ins = $db->prepare('INSERT INTO recuperacao_senhas (usuario_id, token_hash, expira_em, usado) VALUES (?, ?, ?, 0)');
        $ins->bind_param('iss', $usuarioId, $tokenHash, $expira);
        $ins->execute();

        return ['ok' => true, 'mensagem' => 'Informe a nova senha para concluir.', 'token' => $token];
    } catch (Throwable $e) {
        error_log('solicitar_recuperacao_senha: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível iniciar a recuperação agora.', 'token' => null];
    }
}

/**
 * Redefine a senha a partir de um token válido.
 *
 * Devolve ['ok' => bool, 'mensagem' => string].
 */
function redefinir_senha(string $token, string $novaSenha): array
{
    $token = trim($token);

    if ($token === '') {
        return ['ok' => false, 'mensagem' => 'Token inválido ou expirado.'];
    }
    if (!validar_senha($novaSenha)) {
        return ['ok' => false, 'mensagem' => 'A senha deve ter entre 8 e 16 caracteres.'];
    }

    try {
        $db = db_connect();
        $tokenHash = hash('sha256', $token);

        $stmt = $db->prepare('SELECT id, usuario_id, expira_em, usado FROM recuperacao_senhas WHERE token_hash = ? LIMIT 1');
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if (!$row || (int) $row['usado'] === 1) {
            return ['ok' => false, 'mensagem' => 'Token inválido ou já utilizado.'];
        }

        // Token expirado?
        try {
            $expira = new DateTime((string) $row['expira_em']);
        } catch (Throwable $e) {
            return ['ok' => false, 'mensagem' => 'Token inválido.'];
        }
        if ($expira < new DateTime('now')) {
            return ['ok' => false, 'mensagem' => 'Este link expirou. Solicite novamente.'];
        }

        $usuarioId = (int) $row['usuario_id'];
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

        $up = $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?');
        $up->bind_param('si', $hash, $usuarioId);
        if (!$up->execute()) {
            return ['ok' => false, 'mensagem' => 'Não foi possível salvar a nova senha.'];
        }

        // Marca o token como usado (não pode ser reaproveitado).
        $marca = $db->prepare('UPDATE recuperacao_senhas SET usado = 1 WHERE id = ?');
        $recId = (int) $row['id'];
        $marca->bind_param('i', $recId);
        $marca->execute();

        return ['ok' => true, 'mensagem' => 'Senha redefinida com sucesso! Faça login com a nova senha.'];
    } catch (Throwable $e) {
        error_log('redefinir_senha: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível redefinir a senha agora.'];
    }
}

/**
 * ============================================================================
 * ADMINISTRAÇÃO DE USUÁRIOS
 * ============================================================================
 */

/**
 * Cria uma conta pela área administrativa.
 * Devolve ['ok' => bool, 'mensagem' => string].
 */
function admin_criar_usuario(array $dados): array
{
    $nome = trim((string) ($dados['nome'] ?? ''));
    $email = strtolower(trim((string) ($dados['email'] ?? '')));
    $nascimento = trim((string) ($dados['nascimento'] ?? ''));
    $senha = (string) ($dados['senha'] ?? '');
    $tipo = strtolower(trim((string) ($dados['tipo'] ?? 'customer')));
    $isAdmin = !empty($dados['is_admin']) ? 1 : 0;

    if ($nome === '') {
        return ['ok' => false, 'mensagem' => 'Informe o nome do usuário.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensagem' => 'Informe um e-mail válido.'];
    }
    if (!validar_senha($senha)) {
        return ['ok' => false, 'mensagem' => 'A senha deve ter entre 8 e 16 caracteres.'];
    }
    if ($nascimento === '') {
        $nascimento = date('Y-m-d', strtotime('-20 years'));
    }

    // O painel usa 'customer'; o banco guarda 'cliente' como padrão.
    $tipoBanco = ($tipo === 'customer' || $tipo === '') ? 'cliente' : $tipo;
    if ($tipo === 'admin') {
        $isAdmin = 1;
    }

    try {
        $db = db_connect();

        $chk = $db->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            return ['ok' => false, 'mensagem' => 'Este e-mail já está cadastrado.'];
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $status = 'ativo';

        $stmt = $db->prepare('INSERT INTO usuarios (nome, email, nascimento, senha, tipo, is_admin, status_conta) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssis', $nome, $email, $nascimento, $hash, $tipoBanco, $isAdmin, $status);

        if (!$stmt->execute()) {
            return ['ok' => false, 'mensagem' => 'Não foi possível criar o usuário.'];
        }

        return ['ok' => true, 'mensagem' => 'Usuário criado com sucesso!'];
    } catch (Throwable $e) {
        error_log('admin_criar_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível criar o usuário agora.'];
    }
}

/**
 * Atualiza uma conta pela área administrativa.
 * Devolve ['ok' => bool, 'mensagem' => string].
 */
function admin_atualizar_usuario(int $userId, array $dados): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'mensagem' => 'Usuário inválido.'];
    }

    $nome = trim((string) ($dados['nome'] ?? ''));
    $email = strtolower(trim((string) ($dados['email'] ?? '')));
    $nascimento = trim((string) ($dados['nascimento'] ?? ''));
    $tipo = strtolower(trim((string) ($dados['tipo'] ?? 'customer')));
    $isAdmin = !empty($dados['is_admin']) ? 1 : 0;
    $senhaNova = (string) ($dados['senha_nova'] ?? '');

    if ($nome === '') {
        return ['ok' => false, 'mensagem' => 'Informe o nome do usuário.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mensagem' => 'Informe um e-mail válido.'];
    }
    if ($senhaNova !== '' && !validar_senha($senhaNova)) {
        return ['ok' => false, 'mensagem' => 'A nova senha deve ter entre 8 e 16 caracteres.'];
    }

    $tipoBanco = ($tipo === 'customer' || $tipo === '') ? 'cliente' : $tipo;
    if ($tipo === 'admin') {
        $isAdmin = 1;
    }

    try {
        $db = db_connect();

        // E-mail não pode pertencer a outra conta.
        if ($email !== '') {
            $chk = $db->prepare('SELECT id FROM usuarios WHERE LOWER(email) = ? AND id <> ? LIMIT 1');
            $chk->bind_param('si', $email, $userId);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                return ['ok' => false, 'mensagem' => 'Este e-mail já pertence a outro usuário.'];
            }
        }

        $campos = ['nome = ?', 'tipo = ?', 'is_admin = ?'];
        $tipos = 'ssi';
        $valores = [$nome, $tipoBanco, $isAdmin];

        if ($email !== '') {
            $campos[] = 'email = ?';
            $tipos .= 's';
            $valores[] = $email;
        }
        if ($nascimento !== '') {
            $campos[] = 'nascimento = ?';
            $tipos .= 's';
            $valores[] = $nascimento;
        }
        if ($senhaNova !== '') {
            $campos[] = 'senha = ?';
            $tipos .= 's';
            $valores[] = password_hash($senhaNova, PASSWORD_DEFAULT);
        }

        $sql = 'UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = ?';
        $tipos .= 'i';
        $valores[] = $userId;

        $stmt = $db->prepare($sql);
        $stmt->bind_param($tipos, ...$valores);

        if (!$stmt->execute()) {
            return ['ok' => false, 'mensagem' => 'Não foi possível atualizar o usuário.'];
        }

        return ['ok' => true, 'mensagem' => 'Usuário atualizado com sucesso!'];
    } catch (Throwable $e) {
        error_log('admin_atualizar_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível atualizar o usuário agora.'];
    }
}

/**
 * Exclui uma conta pela área administrativa.
 * Devolve ['ok' => bool, 'mensagem' => string].
 */
function admin_excluir_usuario(int $userId): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'mensagem' => 'Usuário inválido.'];
    }

    try {
        $db = db_connect();

        // Não permite excluir a própria conta que está logada.
        $atual = current_user();
        if ($atual && (int) ($atual['id'] ?? 0) === $userId) {
            return ['ok' => false, 'mensagem' => 'Você não pode excluir a própria conta que está usando.'];
        }

        $stmt = $db->prepare('DELETE FROM usuarios WHERE id = ?');
        $stmt->bind_param('i', $userId);

        if (!$stmt->execute()) {
            return ['ok' => false, 'mensagem' => 'Não foi possível excluir o usuário.'];
        }

        return ['ok' => true, 'mensagem' => 'Usuário excluído com sucesso!'];
    } catch (Throwable $e) {
        error_log('admin_excluir_usuario: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível excluir o usuário agora.'];
    }
}

/**
 * Altera o status da conta (ativo / bloqueado / pendente) — moderação.
 * Devolve ['ok' => bool, 'mensagem' => string].
 */
function moderacao_atualizar_conta(int $userId, string $novoStatus): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'mensagem' => 'Usuário inválido.'];
    }

    $permitidos = ['ativo', 'bloqueado', 'pendente'];
    $novoStatus = strtolower(trim($novoStatus));
    if (!in_array($novoStatus, $permitidos, true)) {
        return ['ok' => false, 'mensagem' => 'Status de conta inválido.'];
    }

    try {
        $db = db_connect();
        $stmt = $db->prepare('UPDATE usuarios SET status_conta = ? WHERE id = ?');
        $stmt->bind_param('si', $novoStatus, $userId);

        if (!$stmt->execute()) {
            return ['ok' => false, 'mensagem' => 'Não foi possível alterar o status da conta.'];
        }

        $rotulos = [
            'ativo' => 'ativada',
            'bloqueado' => 'bloqueada',
            'pendente' => 'colocada em análise',
        ];

        return ['ok' => true, 'mensagem' => 'Conta ' . $rotulos[$novoStatus] . ' com sucesso!'];
    } catch (Throwable $e) {
        error_log('moderacao_atualizar_conta: ' . $e->getMessage());
        return ['ok' => false, 'mensagem' => 'Não foi possível alterar o status agora.'];
    }
}