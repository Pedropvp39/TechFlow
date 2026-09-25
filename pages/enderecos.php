<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

no_cache();
require_login();

$base = base_url();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$aviso = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $aviso = 'Sessão expirada. Tente novamente.';
    } elseif (isset($_POST['adicionar_endereco'])) {
        $resEnd = adicionar_endereco_usuario($userId, [
            'cep' => $_POST['cep'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
            'estado' => $_POST['estado'] ?? '',
            'numero' => $_POST['numero'] ?? '',
            'rua' => $_POST['rua'] ?? '',
        ]);
        if ($resEnd['ok']) {
            $sucesso = $resEnd['mensagem'];
        } else {
            $aviso = $resEnd['mensagem'];
        }
    } elseif (isset($_POST['excluir_endereco'])) {
        $endId = (int) ($_POST['endereco_id'] ?? 0);
        if (excluir_endereco_usuario($userId, $endId)) {
            $sucesso = 'Endereço removido com sucesso!';
        } else {
            $aviso = 'Não foi possível remover o endereço.';
        }
    }
}

$enderecos = get_enderecos_usuario($userId);
$hasAvatar = !empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar']);
$primeiraLetra = function_exists('mb_substr')
    ? mb_substr($user['nome'] ?? 'U', 0, 1)
    : substr($user['nome'] ?? 'U', 0, 1);
$inicial = strtoupper($primeiraLetra);

$userRoleKey = get_user_role();
$allRolesInfo = get_system_roles();
$currentRoleInfo = $allRolesInfo[$userRoleKey] ?? $allRolesInfo['customer'];

$page_title = 'Meus Endereços';
require __DIR__ . '/../includes/header.php';
?>

<div class="dash">
    <aside class="sidebar">
        <div class="avatar <?= $hasAvatar ? 'avatar-img-wrap' : '' ?>" aria-hidden="true">
            <?php if ($hasAvatar): ?>
                <img src="<?= e($base . '/' . $user['avatar']) ?>" alt="Avatar de <?= e($user['nome']) ?>" class="avatar-photo">
            <?php else: ?>
                <?= e($inicial) ?>
            <?php endif; ?>
        </div>
        <div class="who"><?= e($user['nome']) ?></div>
        <div class="who-mail"><?= e($user['email']) ?></div>
        <div style="margin: 8px 0 16px;">
            <span style="display: inline-block; padding: 4px 12px; background: var(--success-soft); border: 1px solid var(--success-line); border-radius: 20px; font-size: 0.8rem; font-weight: 700; color: var(--success);">
                <?= e($currentRoleInfo['badge']) ?>
            </span>
        </div>
        <nav aria-label="Menu do perfil">
            <ul>
                <li><a href="<?= e($base) ?>/pages/dashboard.php#perfil">Meu perfil</a></li>
                <li><a class="active" href="<?= e($base) ?>/pages/enderecos.php">Meus endereços</a></li>
                <li><a href="<?= e($base) ?>/pages/dashboard.php#meus-pedidos-title">Minhas compras</a></li>
                <?php if ($userRoleKey !== 'customer'): ?>
                    <li><a href="<?= e($base) ?>/pages/painel.php" style="color: var(--accent-2); font-weight: 600;">Painel <?= e($currentRoleInfo['name']) ?></a></li>
                <?php endif; ?>
                <li><a href="<?= e($base) ?>/pages/produtos.php">Ver produtos</a></li>
                <li><a href="<?= e($base) ?>/index.php">Início</a></li>
                <li><a href="<?= e($base) ?>/php/logout.php">Sair</a></li>
            </ul>
        </nav>
    </aside>

    <section class="panel" aria-labelledby="end-page-title">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h1 id="end-page-title" style="font-size: 1.5rem;">Meus Endereços</h1>
                <p class="sub">Cadastre seus endereços de entrega. Eles ficarão salvos para suas compras.</p>
            </div>
            <button type="button" class="btn btn-sm" onclick="toggleAddressForm()">➕ Adicionar Novo Endereço</button>
        </div>

        <?php if ($aviso): ?>
            <p class="alert alert-error" role="alert"><?= e($aviso) ?></p>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <p class="alert alert-success" role="status"><?= e($sucesso) ?></p>
        <?php endif; ?>

        <!-- Formulário para Cadastro de Novo Endereço (Único no sistema) -->
        <div id="newAddressFormContainer" class="address-form-box <?= !empty($_POST['adicionar_endereco']) && !$sucesso ? '' : 'hidden' ?>">
            <h3 style="margin-bottom: 14px; font-size: 1.1rem; color: var(--text);">Cadastrar Novo Endereço</h3>
            <form method="post" action="<?= e($base) ?>/pages/enderecos.php" class="form-grid">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="adicionar_endereco" value="1">

                <div class="field">
                    <label for="add_cep">CEP</label>
                    <input type="text" id="add_cep" name="cep" placeholder="00000-000" maxlength="9" required>
                </div>
                <div class="field">
                    <label for="add_cidade">Cidade</label>
                    <input type="text" id="add_cidade" name="cidade" placeholder="Ex: São Paulo" required>
                </div>
                <div class="field">
                    <label for="add_estado">Estado (UF)</label>
                    <input type="text" id="add_estado" name="estado" placeholder="Ex: SP" maxlength="2" required style="text-transform: uppercase;">
                </div>
                <div class="field">
                    <label for="add_numero">Número</label>
                    <input type="text" id="add_numero" name="numero" placeholder="Ex: 123 ou S/N" required>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label for="add_rua">Rua / Logradouro</label>
                    <input type="text" id="add_rua" name="rua" placeholder="Ex: Avenida Paulista" required>
                </div>

                <div class="panel-actions" style="grid-column: 1 / -1; display:flex; gap:10px;">
                    <button type="submit" class="btn">Salvar Endereço</button>
                    <button type="button" class="btn secondary" onclick="toggleAddressForm()">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Lista de Cards de Endereços Cadastrados -->
        <div class="address-grid">
            <?php if (empty($enderecos)): ?>
                <div style="grid-column: 1 / -1; padding: 24px; text-align: center; color: var(--muted); background: rgba(30,10,15,0.4); border-radius: 12px; border: 1px dashed var(--border);">
                    <p style="margin-bottom: 12px;">Você ainda não possui nenhum endereço cadastrado.</p>
                    <button type="button" class="btn btn-sm" onclick="toggleAddressForm()">➕ Cadastrar Meu Primeiro Endereço</button>
                </div>
            <?php else: ?>
                <?php foreach ($enderecos as $end): ?>
                    <div class="address-card">
                        <div>
                            <div class="address-card-header">
                                <span class="address-card-title">📍 Endereço #<?= (int) $end['id'] ?></span>
                                <form method="post" action="<?= e($base) ?>/pages/enderecos.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="excluir_endereco" value="1">
                                    <input type="hidden" name="endereco_id" value="<?= (int) $end['id'] ?>">
                                    <button type="submit" class="link-like" style="color:var(--danger-text); font-size:0.8rem;" onclick="return confirm('Excluir este endereço do seu cadastro?');">Excluir</button>
                                </form>
                            </div>
                            <div class="address-card-body">
                                <strong><?= e($end['rua']) ?>, nº <?= e($end['numero']) ?></strong><br>
                                <?= e($end['cidade']) ?> / <?= e($end['estado']) ?><br>
                                <span>CEP: <?= e($end['cep']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
function toggleAddressForm() {
    var box = document.getElementById('newAddressFormContainer');
    if (box) {
        box.classList.toggle('hidden');
        if (!box.classList.contains('hidden')) {
            var firstInput = document.getElementById('add_cep');
            if (firstInput) firstInput.focus();
        }
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
