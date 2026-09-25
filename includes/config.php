<?php
/**
 * Configuração central do TechFlow.
 * Projeto de demonstração: usa dados mock em sessão (sem banco de dados).
 */

if (session_status() === PHP_SESSION_NONE) {
    // As configurações do cookie de sessão só podem ser alteradas ANTES de
    // qualquer conteúdo ser enviado ao navegador. Quando os cabeçalhos já
    // foram enviados (por exemplo, em scripts de linha de comando, que
    // imprimem texto antes de carregar este arquivo), o PHP emite um aviso.
    // A verificação abaixo evita esse aviso e deixa a página limpa.
    if (!headers_sent()) {
        // Cookie de sessão mais seguro
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

/**
 * Impede cache da página (usado nas áreas logadas).
 */
function no_cache(): void
{
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/**
 * Caminho base da aplicação, calculado a partir do script atual.
 * Assim os links funcionam mesmo servindo de subpastas.
 */
function base_url(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $scriptName = str_replace('\\', '/', $scriptName);
    $dir = dirname($scriptName);
    $dir = str_replace('\\', '/', $dir);

    // Se estivermos dentro de /pages ou /php, sobe um nível.
    if (preg_match('#/(pages|php)$#', $dir)) {
        $dir = dirname($dir);
    }

    $dir = str_replace('\\', '/', $dir);
    $dir = rtrim($dir, '/');

    return $dir === '' || $dir === '.' ? '' : $dir;
}

/**
 * Escapa strings para saída em HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Formata preço em reais.
 */
function money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Token CSRF para proteger formulários.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): bool
{
    return isset($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/**
 * Retorna o usuário logado (ou null).
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

if (!defined('ADMIN_MASTER_PIN')) {
    define('ADMIN_MASTER_PIN', getenv('ADMIN_MASTER_PIN') ?: 'master88');
}

function validar_senha_mestre_admin(?string $senha): bool
{
    return is_string($senha) && trim($senha) === ADMIN_MASTER_PIN;
}

function is_admin(): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $tipo = strtolower(trim((string) ($user['tipo'] ?? '')));
    if (in_array($tipo, ['cliente', 'customer'], true)) {
        return false;
    }

    return !empty($user['is_admin']) || $tipo === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: ' . base_url() . '/pages/login.php');
        exit();
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ' . base_url() . '/index.php');
        exit();
    }
}
