<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$base = base_url();
$erro = '';
$sucesso = '';

if (current_user()) {
    header('Location: ' . $base . '/pages/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Tente novamente.';
    } else {
        [$ok, $msg] = register_user(
            $_POST['nome'] ?? '',
            $_POST['email'] ?? '',
            $_POST['nascimento'] ?? '',
            $_POST['senha'] ?? ''
        );
        if ($ok) {
            $sucesso = $msg;
            $_POST = [];
        } else {
            $erro = $msg;
        }
    }
}

$page_title = 'Criar conta';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
    <div class="form-card">
        <h1>Criar conta</h1>
        <p class="sub">Cadastre-se para acompanhar pedidos e favoritos.</p>

        <?php if ($erro): ?>
            <p class="alert alert-error" role="alert"><?= e($erro) ?></p>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <p class="alert alert-success" role="status">
                <?= e($sucesso) ?> <a href="<?= e($base) ?>/pages/login.php">Entrar</a>
            </p>
        <?php endif; ?>

        <form method="post" action="<?= e($base) ?>/pages/cadastro.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="field">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required autocomplete="name"
                       placeholder="Digite seu nome" value="<?= e($_POST['nome'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       placeholder="Digite seu e-mail" value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="nascimento">Data de nascimento</label>
                <input type="date" id="nascimento" name="nascimento" required value="<?= e($_POST['nascimento'] ?? '') ?>">
                <small class="checkout-form-hint" id="nasc-hint">Idade mínima: 16 anos</small>
            </div>
            <div class="field field-password">
                <label for="senha">Senha</label>
                <div class="password-wrapper">
                    <input type="password" id="senha" name="senha" required autocomplete="new-password"
                           placeholder="Digite no minimo 8 caracteres" minlength="8" maxlength="16" value="<?= e($_POST['senha'] ?? '') ?>">
                    <button type="button" class="toggle-password" data-target="senha" aria-label="Mostrar/esconder senha">👁️</button>
                </div>
                <small class="checkout-form-hint">Mínimo de 8 e máximo de 16 dígitos/caracteres</small>
            </div>
            <button type="submit" class="btn">Cadastrar</button>
        </form>

        <p class="form-foot">Já tem conta? <a href="<?= e($base) ?>/pages/login.php">Entrar</a></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var nascInput = document.getElementById('nascimento');
    var senhaInput = document.getElementById('senha');
    var form = document.querySelector('form');

    // Configura os limites de data de nascimento com base no relógio do PC do usuário
    function updateDateLimits() {
        var now = new Date();
        var currentYear = now.getFullYear();
        var currentMonth = String(now.getMonth() + 1).padStart(2, '0');
        var currentDay = String(now.getDate()).padStart(2, '0');

        // Data máxima permitida = 16 anos atrás a partir da data de hoje do PC
        var maxYear = currentYear - 16;
        var maxDateStr = maxYear + '-' + currentMonth + '-' + currentDay;

        // Data mínima = 120 anos atrás
        var minYear = currentYear - 120;
        var minDateStr = minYear + '-' + currentMonth + '-' + currentDay;

        if (nascInput) {
            nascInput.setAttribute('max', maxDateStr);
            nascInput.setAttribute('min', minDateStr);
        }
    }

    updateDateLimits();

    function validarNascimento() {
        if (!nascInput || !nascInput.value) return false;
        var parts = nascInput.value.split('-');
        if (parts.length !== 3) return false;

        var birthYear = parseInt(parts[0], 10);
        var birthMonth = parseInt(parts[1], 10) - 1;
        var birthDay = parseInt(parts[2], 10);

        var birthDate = new Date(birthYear, birthMonth, birthDay);
        var now = new Date();
        now.setHours(0, 0, 0, 0);

        if (birthDate > now) {
            nascInput.setCustomValidity('A data de nascimento não pode ser no futuro.');
            return false;
        }

        var age = now.getFullYear() - birthDate.getFullYear();
        var m = now.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < birthDate.getDate())) {
            age--;
        }

        if (age < 16) {
            nascInput.setCustomValidity('Você deve ter no mínimo 16 anos completos.');
            return false;
        }

        nascInput.setCustomValidity('');
        return true;
    }

    function validarSenha() {
        if (!senhaInput) return true;
        var val = senhaInput.value;
        if (val.length < 8 || val.length > 16) {
            senhaInput.setCustomValidity('A senha deve ter entre 8 e 16 caracteres.');
            return false;
        }
        senhaInput.setCustomValidity('');
        return true;
    }

    if (nascInput) {
        nascInput.addEventListener('change', validarNascimento);
        nascInput.addEventListener('input', validarNascimento);
    }

    if (senhaInput) {
        senhaInput.addEventListener('input', function() {
            if (senhaInput.value.length > 16) {
                senhaInput.value = senhaInput.value.substring(0, 16);
            }
            validarSenha();
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            updateDateLimits();
            var okNasc = validarNascimento();
            var okSenha = validarSenha();
            if (!okNasc || !okSenha) {
                if (!okNasc && nascInput) {
                    nascInput.reportValidity();
                } else if (!okSenha && senhaInput) {http://localhost/projeto-Integrador/pages/produto.php?id=1 
                    senhaInput.reportValidity();
                }
                e.preventDefault();
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>