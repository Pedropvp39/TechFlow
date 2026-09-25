
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

no_cache();
require_login();

$base = base_url();
$user = current_user();
$aviso = '';
$sucesso = '';
$flash = get_flash();
$pedidos = get_meus_pedidos((int) ($user['id'] ?? 0));
$enderecos = get_enderecos_usuario((int) ($user['id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $aviso = 'Sessão expirada. Tente novamente.';
    } elseif (isset($_POST['excluir'])) {
        delete_user($user['email']);
        header('Location: ' . $base . '/index.php');
        exit();
    } elseif (isset($_POST['adicionar_endereco'])) {
        $userId = (int) ($user['id'] ?? 0);
        $resEnd = adicionar_endereco_usuario($userId, [
            'cep' => $_POST['cep'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
            'estado' => $_POST['estado'] ?? '',
            'numero' => $_POST['numero'] ?? '',
            'rua' => $_POST['rua'] ?? '',
        ]);
        if ($resEnd['ok']) {
            $sucesso = $resEnd['mensagem'];
            $enderecos = get_enderecos_usuario($userId);
        } else {
            $aviso = $resEnd['mensagem'];
        }
    } elseif (isset($_POST['excluir_endereco'])) {
        $userId = (int) ($user['id'] ?? 0);
        $endId = (int) ($_POST['endereco_id'] ?? 0);
        if (excluir_endereco_usuario($userId, $endId)) {
            $sucesso = 'Endereço removido com sucesso!';
            $enderecos = get_enderecos_usuario($userId);
        } else {
            $aviso = 'Não foi possível remover o endereço.';
        }
    } elseif (isset($_POST['remover_avatar'])) {
        if (!empty($user['avatar'])) {
            $avatarFile = __DIR__ . '/../' . $user['avatar'];
            if (file_exists($avatarFile) && is_file($avatarFile)) {
                @unlink($avatarFile);
            }
        }
        update_user($user['email'], ['avatar' => null]);
        $user = current_user();
        $sucesso = 'Foto de perfil removida com sucesso!';
    } elseif (isset($_POST['salvar_avatar'])) {
        $erroUpload = '';
        $avatarRelPath = $user['avatar'] ?? null;

        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['avatar_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $erroUpload = 'Erro ao carregar a imagem. Tente novamente.';
            } else {
                $maxSize = 3 * 1024 * 1024; // 3MB
                if ($file['size'] > $maxSize) {
                    $erroUpload = 'A imagem do avatar deve ter no máximo 3MB.';
                } else {
                    $allowedTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                    ];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!isset($allowedTypes[$mimeType])) {
                        $erroUpload = 'Formato de imagem inválido. Use JPG, PNG, WEBP ou GIF.';
                    } else {
                        $ext = $allowedTypes[$mimeType];
                        $uploadDir = __DIR__ . '/../assets/img/avatars/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0777, true);
                        }
                        $userId = (int) ($user['id'] ?? 0);
                        $safeFileName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
                        $targetPath = $uploadDir . $safeFileName;

                        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                            if (!empty($user['avatar'])) {
                                $oldFile = __DIR__ . '/../' . $user['avatar'];
                                if (file_exists($oldFile) && is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                            $avatarRelPath = 'assets/img/avatars/' . $safeFileName;
                            update_user($user['email'], ['avatar' => $avatarRelPath]);
                            $user = current_user();
                            $sucesso = 'Foto de perfil (avatar) atualizada com sucesso!';
                        } else {
                            $erroUpload = 'Não foi possível salvar o avatar enviado.';
                        }
                    }
                }
            }
        } else {
            $erroUpload = 'Selecione uma imagem para enviar.';
        }

        if ($erroUpload !== '') {
            $aviso = $erroUpload;
        }
    } elseif (isset($_POST['salvar_perfil'])) {
        $novaSenha = $_POST['senha_nova'] ?? '';
        $nascimentoPost = $_POST['nascimento'] ?? '';
        [$validaNasc, $msgNasc] = validar_data_nascimento($nascimentoPost);
        [$validaSenha, $msgSenha] = validar_senha($novaSenha, false);

        if (!$validaNasc) {
            $aviso = $msgNasc;
        } elseif (!$validaSenha) {
            $aviso = $msgSenha;
        } else {
            $dadosUpdate = [
                'nascimento' => $nascimentoPost,
                'senha_nova' => $novaSenha,
                'telefone' => $_POST['telefone'] ?? '',
            ];
            update_user($user['email'], $dadosUpdate);
            $user = current_user();
            $sucesso = 'Informações do perfil atualizadas com sucesso!';
        }
    }
}

$primeiraLetra = function_exists('mb_substr')
    ? mb_substr($user['nome'] ?? 'U', 0, 1)
    : substr($user['nome'] ?? 'U', 0, 1);
$inicial = strtoupper($primeiraLetra);
$hasAvatar = !empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar']);

$page_title = 'Meu perfil';
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
        <?php
            $userRoleKey = get_user_role();
            $allRolesInfo = get_system_roles();
            $currentRoleInfo = $allRolesInfo[$userRoleKey] ?? $allRolesInfo['customer'];
        ?>
        <div style="margin: 8px 0 16px;">
            <span style="display: inline-block; padding: 4px 12px; background: var(--success-soft); border: 1px solid var(--success-line); border-radius: 20px; font-size: 0.8rem; font-weight: 700; color: var(--success);">
                <?= e($currentRoleInfo['badge']) ?>
            </span>
        </div>
        <nav aria-label="Menu do perfil">
            <ul>
                <li><a class="active" href="<?= e($base) ?>/pages/dashboard.php#perfil">Meu perfil</a></li>
                <li><a href="<?= e($base) ?>/pages/dashboard.php#avatar-sec">Foto de perfil</a></li>
                <li><a href="<?= e($base) ?>/pages/enderecos.php">Meus endereços</a></li>
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

    <section class="panel" id="perfil" aria-labelledby="perfil-title">
        <h1 id="perfil-title">Meu perfil</h1>
        <p class="sub">Gerencie suas informações pessoais abaixo.</p>

        <?php if ($flash): ?>
            <p class="alert alert-success" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>
        <?php if ($aviso): ?>
            <p class="alert alert-error" role="alert"><?= e($aviso) ?></p>
        <?php endif; ?>
        <?php if ($sucesso): ?>
            <p class="alert alert-success" role="status"><?= e($sucesso) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e($base) ?>/pages/dashboard.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="salvar_perfil" value="1">

            <div class="form-grid">
                <div class="field">
                    <label for="nome">Nome (Apenas confirmação)</label>
                    <input type="text" id="nome" name="nome" value="<?= e($user['nome']) ?>" readonly style="background: rgba(255,255,255,0.05); cursor: not-allowed;">
                    <small class="checkout-form-hint">O nome não pode ser alterado após o cadastro.</small>
                </div>
                <div class="field">
                    <label for="email">E-mail (Apenas confirmação)</label>
                    <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" readonly style="background: rgba(255,255,255,0.05); cursor: not-allowed;">
                    <small class="checkout-form-hint">O e-mail não pode ser alterado.</small>
                </div>
                <div class="field">
                    <label for="nascimento">Data de nascimento</label>
                    <input type="date" id="nascimento" name="nascimento" required value="<?= e($user['nascimento'] ?? '') ?>">
                    <small class="checkout-form-hint">Mínimo 16 anos</small>
                </div>
                <div class="field field-password">
                    <label for="senha_nova">Nova senha</label>
                    <div class="password-wrapper">
                        <input type="password" id="senha_nova" name="senha_nova" autocomplete="new-password"
                               placeholder="Mínimo de 8 caracteres " minlength="8" maxlength="16">
                        <button type="button" class="toggle-password" data-target="senha_nova" aria-label="Mostrar/esconder senha">👁️</button>
                    </div>
                    <small class="checkout-form-hint">Mínimo de 8 caracteres</small>
                </div>
                <div class="field">
                    <label for="telefone">Telefone / Celular</label>
                    <input type="text" id="telefone" name="telefone" value="<?= e($user['telefone'] ?? '') ?>" placeholder="(11) 99999-9999">
                </div>
            </div>

            <div class="panel-actions">
                <button type="submit" class="btn">Salvar perfil</button>
                <button type="submit" name="excluir" value="1" class="btn btn-danger"
                        onclick="return confirm('Tem certeza que deseja excluir sua conta? Esta ação não pode ser desfeita.');">
                    Excluir conta
                </button>
            </div>
        </form>
    </section>
</div>

<!-- ==================== SEÇÃO DEDICADA: FOTO DE PERFIL / AVATAR ==================== -->
<section class="section" id="avatar-sec" aria-labelledby="avatar-title" style="margin-top: 24px;">
    <div class="panel">
        <h2 id="avatar-title">Foto de Perfil (Avatar)</h2>
        <p class="sub">Atualize ou remova sua foto de perfil do sistema.</p>

        <form method="post" action="<?= e($base) ?>/pages/dashboard.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="salvar_avatar" value="1">

            <div class="avatar-edit-box" style="margin-top: 16px;">
                <div class="avatar-preview-container">
                    <div class="avatar avatar-preview" id="avatarPreviewContainer">
                        <?php if ($hasAvatar): ?>
                            <img src="<?= e($base . '/' . $user['avatar']) ?>" alt="Foto de perfil" class="avatar-photo" id="avatarPreviewImg">
                        <?php else: ?>
                            <span id="avatarPreviewInitial"><?= e($inicial) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-file-input">
                        <label for="avatar_file" class="avatar-upload-btn">
                            <span>📷 Escolher nova foto</span>
                            <input type="file" id="avatar_file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;">
                        </label>
                        <span class="avatar-file-name" id="avatarFileName">Nenhum arquivo selecionado</span>
                        <span class="avatar-help-text">JPG, PNG, WEBP ou GIF (máximo 3MB)</span>
                    </div>
                </div>
                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-sm">Salvar nova foto</button>
                    <?php if ($hasAvatar): ?>
                        <button type="submit" name="remover_avatar" value="1" class="avatar-remove-btn" onclick="return confirm('Tem certeza que deseja remover sua foto de perfil?');">
                            Remover foto
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</section>

<section class="section" aria-labelledby="meus-pedidos-title" id="meus-pedidos-title">
    <div class="section-head">
        <div>
            <h2>Minhas compras</h2>
            <p>Acompanhe e gerencie a entrega dos seus pedidos em tempo real.</p>
        </div>
    </div>

    <?php if (empty($pedidos)): ?>
        <p class="empty-message">Você ainda não realizou nenhuma compra.</p>
    <?php else: ?>
        <div class="produtos">
            <?php foreach ($pedidos as $pedido): ?>
                <?php
                    $status = (string) $pedido['status'];
                    $statusClass = 'status-pago';
                    if ($status === 'Entregue') {
                        $statusClass = 'status-entregue';
                    } elseif ($status === 'Não recebido') {
                        $statusClass = 'status-nao-recebido';
                    } elseif ($status === 'Reembolsado') {
                        $statusClass = 'status-reembolsado';
                    }
                ?>
                <article class="produto pedido-card">
                    <div class="produto-body">
                        <div class="pedido-head">
                            <span class="produto-cat"><?= e($pedido['categoria']) ?></span>
                            <span class="badge-status <?= $statusClass ?>"><?= e($status) ?></span>
                        </div>
                        <h3><?= e($pedido['produto_nome']) ?></h3>
                        <p class="pedido-meta">Quantidade: <?= (int) $pedido['quantidade'] ?> · Valor unitário: <?= e(money($pedido['preco'])) ?></p>
                        <p class="pedido-meta">Valor total: <strong><?= e(money($pedido['preco'] * $pedido['quantidade'])) ?></strong></p>
                        <?php if (!empty($pedido['rua'])): ?>
                            <p class="pedido-meta"><strong>Endereço de Entrega:</strong> <?= e($pedido['rua']) ?>, nº <?= e($pedido['numero']) ?> — <?= e($pedido['cidade']) ?>/<?= e($pedido['estado']) ?> (CEP: <?= e($pedido['cep']) ?>)<?= !empty($pedido['telefone']) ? ' · Tel: ' . e($pedido['telefone']) : '' ?></p>
                        <?php endif; ?>
                        <?php if (!empty($pedido['criado_em'])): ?>
                            <p class="pedido-data"><small>Data do pedido: <?= date('d/m/Y \à\s H:i', strtotime($pedido['criado_em'])) ?></small></p>
                        <?php endif; ?>

                        <div class="pedido-acoes">
                            <?php if ($status !== 'Entregue' && $status !== 'Reembolsado'): ?>
                                <form method="post" action="<?= e($base) ?>/php/pedidos-acao.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="entregue">
                                    <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-status-entregue" onclick="return confirm('Confirmar que a sua encomenda foi entregue com sucesso?');">
                                        ✓ Marcar como Entregue
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($status !== 'Não recebido' && $status !== 'Reembolsado' && $status !== 'Entregue'): ?>
                                <form method="post" action="<?= e($base) ?>/php/pedidos-acao.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="nao_recebi">
                                    <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-status-nao-recebi" onclick="return confirm('Deseja marcar que você não recebeu esta encomenda?');">
                                        Não recebi
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($status !== 'Reembolsado'): ?>
                                <form method="post" action="<?= e($base) ?>/php/pedidos-acao.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="reembolsar">
                                    <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-status-reembolso" onclick="return confirm('Deseja realmente desfazer a compra e receber o reembolso total?');">
                                        Desfazer compra (Reembolso)
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="reembolso-confirmado">
                                    <span><strong>Reembolso feito</strong> (Valor estornado)</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($status === 'Entregue' || $status === 'Reembolsado' || $status === 'Não recebido'): ?>
                                <form method="post" action="<?= e($base) ?>/php/pedidos-acao.php" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="acao" value="remover">
                                    <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Remover este item do histórico de compras?');">
                                        Remover
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

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

document.addEventListener('DOMContentLoaded', function() {
    var avatarInput = document.getElementById('avatar_file');
    var avatarFileName = document.getElementById('avatarFileName');
    var previewContainer = document.getElementById('avatarPreviewContainer');
    var nascInput = document.getElementById('nascimento');
    var senhaInput = document.getElementById('senha_nova');
    var form = document.querySelector('form');

    if (avatarInput && previewContainer) {
        avatarInput.addEventListener('change', function(e) {
            var file = e.target.files && e.target.files[0];
            if (file) {
                if (avatarFileName) {
                    avatarFileName.textContent = file.name;
                }
                var reader = new FileReader();
                reader.onload = function(evt) {
                    previewContainer.innerHTML = '<img src="' + evt.target.result + '" alt="Prévia da foto" class="avatar-photo" id="avatarPreviewImg">';
                };
                reader.readAsDataURL(file);
            }
        });
    }

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

    function validarSenhaNova() {
        if (!senhaInput) return true;
        var val = senhaInput.value;
        if (val === '') {
            senhaInput.setCustomValidity('');
            return true;
        }
        if (val.length !== 8) {
            senhaInput.setCustomValidity('A nova senha deve ter exatamente 8 caracteres (mínimo 8 e máximo 16).');
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
            if (senhaInput.value.length >= 8 && senhaInput.value.length > 16) {
                senhaInput.value = senhaInput.value.substring(0, 8);
            }
            validarSenhaNova();
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            updateDateLimits();
            var okNasc = validarNascimento();
            var okSenha = validarSenhaNova();
            if (!okNasc || !okSenha) {
                if (!okNasc && nascInput) {
                    nascInput.reportValidity();
                } else if (!okSenha && senhaInput) {
                    senhaInput.reportValidity();
                }
                e.preventDefault();
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>