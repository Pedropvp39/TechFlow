<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$base = base_url();
$erro = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
$prodId = (int) ($_GET['id'] ?? $_POST['prod_id'] ?? 0);

if ($redirect === 'carrinho') {
    $targetUrl = $base . '/pages/carrinho.php';
} elseif ($redirect === 'produto' && $prodId > 0) {
    $targetUrl = $base . '/pages/produto.php?id=' . $prodId . '#avaliacoes';
} else {
    $targetUrl = $base . '/pages/dashboard.php';
}

if (current_user()) {
    header('Location: ' . $targetUrl);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        [$ok, $msg] = login_user($_POST['email'] ?? '', $_POST['senha'] ?? '');
        if ($ok) {
            header('Location: ' . $targetUrl);
            exit();
        }
        $erro = $msg;
    }
}

$page_title = 'Entrar';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
    <div class="form-card">
        <h1>Entrar</h1>
        <p class="sub">Acesse sua conta para gerenciar seu perfil.</p>

        <?php if ($erro): ?>
            <p class="alert alert-error" role="alert"><?= e($erro) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e($base) ?>/pages/login.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <input type="hidden" name="prod_id" value="<?= e($prodId) ?>">
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       placeholder="exemplo@gmail.com" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="field field-password">
                <label for="senha">Senha</label>
                <div class="password-wrapper">
                    <input type="password" id="senha" name="senha" required autocomplete="current-password"
                           placeholder="Digite sua senha" minlength="8" maxlength="16" value="<?= e($_POST['senha'] ?? '') ?>">
                    <button type="button" class="toggle-password" data-target="senha" aria-label="Mostrar/esconder senha">👁️</button>
                </div>
                <small class="checkout-form-hint">Sua senha de acesso</small>
            </div>
            <button type="submit" class="btn">Entrar</button>
        </form>

        <p class="form-foot"><a href="<?= e($base) ?>/pages/esqueci-senha.php">Esqueci minha senha</a></p>
        <p class="form-foot">Não tem conta? <a href="<?= e($base) ?>/pages/cadastro.php">Criar agora</a></p>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>