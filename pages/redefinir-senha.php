<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$base = base_url();
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$mensagem = '';
$sucesso = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $mensagem = 'Sessão expirada. Tente novamente.';
    } else {
        $resultado = redefinir_senha($token, (string) ($_POST['senha_nova'] ?? ''));
        $mensagem = $resultado['mensagem'];
        $sucesso = $resultado['ok'];
    }
}

$page_title = 'Redefinir senha';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
    <div class="form-card">
        <h1>Nova senha</h1>
        <p class="sub">Escolha uma senha com no mínimo 8 caracteres.</p>
        <?php if ($mensagem): ?><p class="alert <?= $sucesso ? 'alert-success' : 'alert-error' ?>" role="status"><?= e($mensagem) ?></p><?php endif; ?>
        <?php if (!$sucesso): ?>
            <form method="post" action="<?= e($base) ?>/pages/redefinir-senha.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="field field-password"><label for="senha_nova">Nova senha</label><div class="password-wrapper"><input type="password" id="senha_nova" name="senha_nova" required minlength="8" autocomplete="new-password"><button type="button" class="toggle-password" data-target="senha_nova" aria-label="Mostrar/esconder senha">👁️</button></div></div>
                <button type="submit" class="btn">Redefinir senha</button>
            </form>
        <?php endif; ?>
        <p class="form-foot"><a href="<?= e($base) ?>/pages/login.php">Ir para o login</a></p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
