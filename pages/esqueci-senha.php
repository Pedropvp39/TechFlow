<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$base = base_url();
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $mensagem = 'Sessão expirada. Tente novamente.';
    } else {
        $resultado = solicitar_recuperacao_senha($_POST['email'] ?? '');
        if (!empty($resultado['token'])) {
            $resultado = redefinir_senha($resultado['token'], (string) ($_POST['senha_nova'] ?? ''));
        }
        $mensagem = $resultado['mensagem'];
        $sucesso = !empty($resultado['ok']) && !empty($_POST['senha_nova']);
    }
}

$page_title = 'Esqueci minha senha';
require __DIR__ . '/../includes/header.php';
?>
<div class="auth-wrap">
    <div class="form-card">
        <h1>Esqueci minha senha</h1>
        <p class="sub">Informe seu e-mail e escolha uma nova senha.</p>
        <?php if ($mensagem): ?><p class="alert <?= !empty($sucesso) ? 'alert-success' : 'alert-error' ?>" role="status"><?= e($mensagem) ?></p><?php endif; ?>
        <form method="post" action="<?= e($base) ?>/pages/esqueci-senha.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" required autocomplete="email"></div>
            <div class="field field-password"><label for="senha_nova">Nova senha</label><div class="password-wrapper"><input type="password" id="senha_nova" name="senha_nova" required minlength="8" autocomplete="new-password"><button type="button" class="toggle-password" data-target="senha_nova" aria-label="Mostrar/esconder senha">👁️</button></div></div>
            <button type="submit" class="btn">Alterar senha</button>
        </form>
        <p class="form-foot"><a href="<?= e($base) ?>/pages/login.php">Voltar para o login</a></p>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
