<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/pedidos.php';

no_cache();
require_login();

if (!has_role(['admin', 'developer', 'support', 'moderator', 'manager', 'financial', 'logistics'])) {
    header('Location: ' . base_url() . '/pages/dashboard.php');
    exit();
}

$base = base_url();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
$userRoleKey = get_user_role();

if ($userRoleKey === 'admin' && (!isset($_GET['area']) || $_GET['area'] === 'admin')) {
    header('Location: ' . $base . '/pages/admin-produtos.php');
    exit();
}

$viewRoleKey = $userRoleKey;
$staffAreas = ['admin', 'developer', 'support', 'moderator', 'manager', 'financial', 'logistics'];
 $requestedArea = $_GET['area'] ?? $_POST['area'] ?? '';
if ($userRoleKey === 'admin' && in_array($requestedArea, $staffAreas, true)) {
    $viewRoleKey = $requestedArea;
}
$panelAction = $base . '/pages/painel.php?area=' . urlencode($viewRoleKey);

$rolesInfo = get_system_roles();
$currentRoleInfo = $rolesInfo[$viewRoleKey] ?? $rolesInfo['customer'];

$mensagem = '';
$tipoMensagem = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $mensagem = 'Sessão expirada. Tente novamente.';
        $tipoMensagem = 'error';
    } else {
        $senhaMaster = $_POST['senha_master'] ?? '';
        $acao = $_POST['acao_override'] ?? $_POST['acao'] ?? '';

        // Validação da Senha/Chave Mestre Pessoal do Usuário
        if (!validar_chave_mestre_usuario($userId, $senhaMaster)) {
            $mensagem = '🔒 Chave Mestre de confirmação incorreta para a sua conta (' . e($user['nome']) . ')! Operação cancelada por segurança.';
            $tipoMensagem = 'error';
        } else {
            switch ($acao) {
                case 'alterar_minha_chave_mestre':
                    $novaChave = trim((string) ($_POST['nova_chave_mestre'] ?? ''));
                    if (strlen($novaChave) < 8) {
                        $mensagem = 'A nova chave mestre deve ter no mínimo 8 caracteres.';
                        $tipoMensagem = 'error';
                    } else {
                        update_user($user['email'], ['chave_mestre' => password_hash($novaChave, PASSWORD_DEFAULT)]);
                        $mensagem = 'Chave mestre atualizada com sucesso!';
                    }
                    break;

                // --- PRODUTOS & ESTOQUE (Admin / Manager) ---
                case 'adicionar_produto':
                    if (has_role(['admin', 'manager'])) {
                        $res = adicionar_produto([
                            'nome' => $_POST['nome'] ?? '',
                            'categoria' => $_POST['categoria'] ?? '',
                            'preco' => (float) ($_POST['preco'] ?? 0),
                            'descricao' => $_POST['descricao'] ?? '',
                            'imagem' => $_POST['imagem'] ?? 'default.png',
                            'destaque' => !empty($_POST['destaque']),
                        ], $_FILES['imagem_file'] ?? null);
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                case 'editar_produto':
                    if (has_role(['admin', 'manager'])) {
                        $prodId = (int) ($_POST['produto_id'] ?? 0);
                        $res = atualizar_produto($prodId, [
                            'nome' => $_POST['nome'] ?? '',
                            'categoria' => $_POST['categoria'] ?? '',
                            'preco' => (float) ($_POST['preco'] ?? 0),
                            'descricao' => $_POST['descricao'] ?? '',
                            'imagem' => $_POST['imagem'] ?? 'default.png',
                            'destaque' => !empty($_POST['destaque']),
                        ], $_FILES['imagem_file'] ?? null);
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                case 'excluir_produto':
                    if (has_role(['admin', 'manager'])) {
                        $prodId = (int) ($_POST['produto_id'] ?? 0);
                        $res = excluir_produto($prodId);
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                // --- USUÁRIOS E CARGOS (Admin) ---
                case 'adicionar_usuario':
                    if (has_role('admin')) {
                        $res = admin_criar_usuario([
                            'nome' => $_POST['nome'] ?? '',
                            'email' => $_POST['email'] ?? '',
                            'nascimento' => $_POST['nascimento'] ?? '',
                            'senha' => $_POST['senha'] ?? '',
                            'tipo' => $_POST['tipo'] ?? 'customer',
                        ]);
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                case 'editar_usuario':
                    if (has_role('admin')) {
                        $uId = (int) ($_POST['usuario_id'] ?? 0);
                        $res = admin_atualizar_usuario($uId, [
                            'nome' => $_POST['nome'] ?? '',
                            'email' => $_POST['email'] ?? '',
                            'nascimento' => $_POST['nascimento'] ?? '',
                            'tipo' => $_POST['tipo'] ?? 'customer',
                            'senha_nova' => $_POST['senha_nova'] ?? '',
                        ]);
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                case 'excluir_usuario':
                    if (has_role('admin')) {
                        $uId = (int) ($_POST['usuario_id'] ?? 0);
                        $res = admin_excluir_usuario($uId);
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                // --- STATUS PEDIDOS (Admin, Manager, Logistics, Support, Financial) ---
                case 'atualizar_pedido':
                    if (!has_role(['admin', 'manager', 'logistics', 'support', 'financial'])) {
                        break;
                    }
                    $pedId = (int) ($_POST['pedido_id'] ?? 0);
                    $novoStatus = trim((string) ($_POST['status'] ?? ''));
                    if ($pedId > 0 && $novoStatus !== '') {
                        admin_atualizar_status_pedido($pedId, $novoStatus);
                        $mensagem = "Status do pedido #$pedId atualizado para '$novoStatus' com sucesso!";
                        $tipoMensagem = 'success';
                    }
                    break;

                // --- ACOES SUPORTE ---
                case 'responder_chamado_suporte':
                    if (has_role(['admin', 'support'])) {
                        $chamadoId = (int) ($_POST['chamado_id'] ?? 0);
                        $respText = trim((string) ($_POST['resposta'] ?? ''));
                        if ($chamadoId > 0 && $respText !== '') {
                            $db = db_connect();
                            $stmtSup = $db->prepare("UPDATE chamados_suporte SET resposta = ?, status = 'Respondido' WHERE id = ?");
                            $stmtSup->bind_param('si', $respText, $chamadoId);
                            $stmtSup->execute();
                            $mensagem = "Chamado #$chamadoId respondido com sucesso!";
                            $tipoMensagem = 'success';
                        }
                    }
                    break;

                // --- ACOES MODERADOR ---
                case 'moderar_avaliacao_produto':
                    if (has_role(['admin', 'moderator'])) {
                        $avalId = (int) ($_POST['avaliacao_id'] ?? 0);
                        $novoSt = trim((string) ($_POST['status_moderacao'] ?? 'Aprovado'));
                        if ($avalId > 0) {
                            $db = db_connect();
                            $stmtMod = $db->prepare("UPDATE avaliacoes_produtos SET status = ? WHERE id = ?");
                            $stmtMod->bind_param('si', $novoSt, $avalId);
                            $stmtMod->execute();
                            $mensagem = "Avaliação #$avalId $novoSt com sucesso!";
                            $tipoMensagem = 'success';
                        }
                    }
                    break;

                case 'excluir_avaliacao':
                    if (has_role(['admin', 'moderator'])) {
                        $res = excluir_avaliacao_moderacao((int) ($_POST['avaliacao_id'] ?? 0));
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                case 'bloquear_usuario':
                case 'aprovar_usuario':
                    if (has_role(['admin', 'moderator'])) {
                        $statusConta = $acao === 'bloquear_usuario' ? 'bloqueado' : 'ativo';
                        $res = moderacao_atualizar_conta((int) ($_POST['usuario_id'] ?? 0), $statusConta);
                        $mensagem = $res['mensagem'];
                        $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    }
                    break;

                // --- ACOES GERENTE / CUPONS ---
                case 'adicionar_cupom':
                    if (has_role(['admin', 'manager', 'financial'])) {
                        $codCupom = strtoupper(trim((string) ($_POST['codigo_cupom'] ?? '')));
                        $descPerc = (float) ($_POST['desconto_percentual'] ?? 10);
                        if ($codCupom !== '' && $descPerc > 0) {
                            $db = db_connect();
                            $stmtCup = $db->prepare("INSERT INTO cupons (codigo, desconto_percentual, ativo) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE desconto_percentual = VALUES(desconto_percentual), ativo = 1");
                            $stmtCup->bind_param('sd', $codCupom, $descPerc);
                            $okCupom = $stmtCup->execute();
                            $mensagem = $okCupom ? "Cupom '$codCupom' registrado com sucesso!" : 'Não foi possível salvar o cupom.';
                            $tipoMensagem = $okCupom ? 'success' : 'error';
                        }
                    }
                    break;

                case 'editar_cupom':
                    if (has_role(['admin', 'manager', 'financial'])) {
                        $cupomId = (int) ($_POST['cupom_id'] ?? 0);
                        $descPerc = (float) ($_POST['desconto_percentual'] ?? 0);
                        $ativo = !empty($_POST['ativo']) ? 1 : 0;
                        if ($cupomId > 0 && $descPerc > 0 && $descPerc <= 90) {
                            $db = db_connect();
                            $stmtCup = $db->prepare('UPDATE cupons SET desconto_percentual = ?, ativo = ? WHERE id = ?');
                            $stmtCup->bind_param('dii', $descPerc, $ativo, $cupomId);
                            $okCupom = $stmtCup->execute();
                            $mensagem = $okCupom ? 'Cupom atualizado com sucesso!' : 'Não foi possível atualizar o cupom.';
                            $tipoMensagem = $okCupom ? 'success' : 'error';
                        }
                    }
                    break;

                case 'excluir_cupom':
                    if (has_role(['admin', 'manager', 'financial'])) {
                        $cupomId = (int) ($_POST['cupom_id'] ?? 0);
                        $db = db_connect();
                        $stmtCup = $db->prepare('DELETE FROM cupons WHERE id = ?');
                        $stmtCup->bind_param('i', $cupomId);
                        $okCupom = $stmtCup->execute();
                        $mensagem = $okCupom ? 'Cupom excluído com sucesso!' : 'Não foi possível excluir o cupom.';
                        $tipoMensagem = $okCupom ? 'success' : 'error';
                    }
                    break;

                // --- ACOES FINANCEIRO ---
                case 'processar_reembolso_financeiro':
                    if (has_role(['admin', 'financial'])) {
                        $pedId = (int) ($_POST['pedido_id'] ?? 0);
                        if ($pedId > 0) {
                            admin_atualizar_status_pedido($pedId, 'Reembolsado');
                            $mensagem = " Reembolso do pedido #$pedId processado e registrado no financeiro!";
                            $tipoMensagem = 'success';
                        }
                    }
                    break;

                // --- ACOES LOGISTICA ---
                case 'atualizar_rastreio_logistica':
                    if (has_role(['admin', 'logistics'])) {
                        $pedId = (int) ($_POST['pedido_id'] ?? 0);
                        $codRastreio = trim((string) ($_POST['codigo_rastreio'] ?? ''));
                        $stExp = trim((string) ($_POST['status_expedicao'] ?? 'Enviado'));
                        if ($pedId > 0) {
                            $db = db_connect();
                            $checkLog = $db->prepare('SELECT id FROM logistica_pedidos WHERE pedido_id = ? LIMIT 1');
                            $checkLog->bind_param('i', $pedId);
                            $checkLog->execute();
                            if ($checkLog->get_result()->num_rows > 0) {
                                $stmtLog = $db->prepare('UPDATE logistica_pedidos SET codigo_rastreio = ?, status_expedicao = ? WHERE pedido_id = ?');
                                $stmtLog->bind_param('ssi', $codRastreio, $stExp, $pedId);
                            } else {
                                $stmtLog = $db->prepare('INSERT INTO logistica_pedidos (pedido_id, codigo_rastreio, status_expedicao) VALUES (?, ?, ?)');
                                $stmtLog->bind_param('iss', $pedId, $codRastreio, $stExp);
                            }
                            $stmtLog->execute();

                            admin_atualizar_status_pedido($pedId, $stExp);
                            $mensagem = " Expedição do pedido #$pedId atualizada ($stExp) com código: $codRastreio!";
                            $tipoMensagem = 'success';
                        }
                    }
                    break;

                // --- ACOES DESENVOLVEDOR ---
                case 'limpar_cache_sistema':
                    if (has_role(['admin', 'developer'])) {
                        unset($_SESSION['cart_synced_from_db']);
                        $mensagem = " Cache de sistema e sincronização limpos com sucesso!";
                        $tipoMensagem = 'success';
                    }
                    break;
            }
        }
    }
}

// Dados para os painéis
$produtos = get_produtos();
$categorias = get_categorias();
$usuarios = get_todos_usuarios();
$pedidos = get_todos_pedidos_admin();

$db = db_connect();

$chamadosSuporte = [];
$resSup = $db->query("SELECT s.*, u.nome AS usuario_nome, u.email AS usuario_email FROM chamados_suporte s LEFT JOIN usuarios u ON u.id = s.usuario_id ORDER BY s.id DESC");
if ($resSup) {
    while ($row = $resSup->fetch_assoc()) $chamadosSuporte[] = $row;
}

$avaliacoesProdutos = [];
$resAval = $db->query("SELECT a.*, p.nome AS produto_nome, COALESCE(SUM(CASE WHEN i.tipo = 'like' THEN 1 ELSE 0 END), 0) AS likes, COALESCE(SUM(CASE WHEN i.tipo = 'denuncia' THEN 1 ELSE 0 END), 0) AS denuncias, GROUP_CONCAT(CASE WHEN i.tipo = 'denuncia' THEN CONCAT(COALESCE(i.motivo_denuncia, 'Sem motivo'), ' - ', COALESCE(i.detalhes_denuncia, 'Sem detalhes'), ' (', COALESCE(i.denunciante_nome, 'Usuário'), ')') END SEPARATOR ' | ') AS denuncias_info FROM avaliacoes_produtos a LEFT JOIN produtos p ON p.id = a.produto_id LEFT JOIN avaliacoes_interacoes i ON i.avaliacao_id = a.id GROUP BY a.id ORDER BY a.id DESC");
if ($resAval) {
    while ($row = $resAval->fetch_assoc()) $avaliacoesProdutos[] = $row;
}

$cuponsLoja = [];
$resCup = $db->query("SELECT * FROM cupons ORDER BY id DESC");
if ($resCup) {
    while ($row = $resCup->fetch_assoc()) $cuponsLoja[] = $row;
}

$logisticaList = [];
$resLog = $db->query("SELECT l.*, p.produto_nome, p.quantidade, p.nome_cliente, p.rua, p.numero, p.cidade, p.estado, p.cep FROM logistica_pedidos l LEFT JOIN pedidos p ON p.id = l.pedido_id ORDER BY l.id DESC");
if ($resLog) {
    while ($row = $resLog->fetch_assoc()) $logisticaList[] = $row;
}
$logisticaPorPedido = [];
foreach ($logisticaList as $logistica) {
    $logisticaPorPedido[(int) $logistica['pedido_id']] = $logistica;
}

$page_title = 'Painel ' . $currentRoleInfo['name'];
require __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="section-head" style="margin-bottom: 20px;">
        <div>
            <h1>Painel <?= e($currentRoleInfo['name']) ?></h1>
            <p><?= e($currentRoleInfo['desc']) ?></p>
        </div>
        <div style="text-align: right;">
            <span style="display: inline-block; padding: 6px 14px; background: rgba(34,197,94,0.2); border: 1px solid rgba(34,197,94,0.5); border-radius: 20px; font-weight: 700; color: #86efac;">
                <?= e($currentRoleInfo['badge']) ?>
            </span>
            <div style="font-size: 0.78rem; color: var(--muted); margin-top: 4px;">
                Usuário: <strong><?= e($user['nome']) ?></strong>
            </div>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <p class="alert <?= $tipoMensagem === 'success' ? 'alert-success' : 'alert-error' ?>" role="status"><?= e($mensagem) ?></p>
    <?php endif; ?>

    <!-- Modal / Caixa para alterar Chave Mestre Pessoal do Usuário -->
    <div style="background: rgba(30,10,15,0.5); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div>
            <strong style="font-size: 0.92rem; color: var(--text);">Sua Chave Mestre Pessoal de Confirmação</strong>
            <p style="font-size: 0.8rem; color: var(--muted); margin: 2px 0 0;">Exigida para autorizar ações e alterações neste painel.</p>
        </div>
        <form method="post" action="<?= e($panelAction) ?>" style="display: flex; gap: 8px; align-items: center;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="alterar_minha_chave_mestre">
            <input type="hidden" name="area" value="<?= e($viewRoleKey) ?>">
            <input type="password" name="senha_master" required minlength="4" placeholder="Chave atual" style="padding: 6px 10px; font-size: 0.82rem; border-radius: 6px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff;">
            <input type="password" name="nova_chave_mestre" required minlength="4" placeholder="Nova chave mestre" style="padding: 6px 10px; font-size: 0.82rem; border-radius: 6px; background: rgba(0,0,0,0.3); border: 1px solid var(--border); color: #fff;">
            <button type="submit" class="btn btn-sm secondary">Atualizar Chave</button>
        </form>
    </div>

    <!-- ==================== TELA DEDICADA 1: ADMINISTRADOR ==================== -->
    <?php if ($viewRoleKey === 'admin'): ?>
        <div class="admin-nav-tabs" role="tablist">
            <button type="button" class="admin-tab-btn active" data-tab="usuarios">Usuários &amp; Cargos (<?= count($usuarios) ?>)</button>
            <button type="button" class="admin-tab-btn" data-tab="produtos">Produtos (<?= count($produtos) ?>)</button>
            <button type="button" class="admin-tab-btn" data-tab="pedidos">Vendas &amp; Pedidos (<?= count($pedidos) ?>)</button>
            <button type="button" class="admin-tab-btn" data-tab="categorias">Categorias (<?= count($categorias) ?>)</button>
        </div>

        <div id="tab-usuarios" class="admin-tab-content active">
            <div class="panel">
                <div class="panel-head-flex">
                    <h2>Gerenciamento de Usuários e Níveis de Acesso</h2>
                    <button type="button" class="btn btn-sm" onclick="openAddUserModal()">Novo Usuário/Cargo</button>
                </div>
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>E-mail</th>
                                <th>Cargo / Nível de Acesso</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td>#<?= (int) $u['id'] ?></td>
                                    <td><strong><?= e($u['nome']) ?></strong></td>
                                    <td><?= e($u['email']) ?></td>
                                    <td>
                                        <?php
                                            $uTipo = strtolower((string) ($u['tipo'] ?? 'customer'));
                                            if ($uTipo === 'cliente') $uTipo = 'customer';
                                            $rBadge = $rolesInfo[$uTipo]['badge'] ?? '🛒 Cliente';
                                        ?>
                                        <span class="badge-status status-reembolsado"><?= e($rBadge) ?></span>
                                    </td>
                                    <td>
                                        <div class="admin-actions-cell">
                                            <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditUserModal(<?= json_encode($u) ?>)'>Alterar Cargo / Dados</button>
                                            <?php if ((int) $u['id'] !== $userId): ?>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteUserModal(<?= (int) $u['id'] ?>, '<?= e(addslashes($u['nome'])) ?>')">Excluir</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="tab-produtos" class="admin-tab-content">
            <div class="panel">
                <h2>Catálogo de Produtos</h2>
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr><th>ID</th><th>Produto</th><th>Categoria</th><th>Preço</th><th>Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($produtos as $p): ?>
                                <tr>
                                    <td>#<?= (int) $p['id'] ?></td>
                                    <td><strong><?= e($p['nome']) ?></strong></td>
                                    <td><?= e($p['categoria']) ?></td>
                                    <td><?= e(money($p['preco'])) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditProductModal(<?= json_encode($p) ?>)'>Editar</button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteProductModal(<?= (int) $p['id'] ?>, '<?= e(addslashes($p['nome'])) ?>')">Excluir</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="tab-pedidos" class="admin-tab-content">
            <div class="panel">
                <h2>Vendas e Pedidos dos Clientes</h2>
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr><th>ID</th><th>Cliente</th><th>Produto</th><th>Valor</th><th>Status</th><th>Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedidos as $ped): ?>
                                <tr>
                                    <td>#<?= (int) $ped['id'] ?></td>
                                    <td><strong><?= e($ped['usuario_nome']) ?></strong></td>
                                    <td><?= e($ped['produto_nome']) ?></td>
                                    <td><?= e(money($ped['preco'] * $ped['quantidade'])) ?></td>
                                    <td><span class="badge-status status-pago"><?= e($ped['status']) ?></span></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditOrderModal(<?= json_encode($ped) ?>)'>Alterar Status</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="tab-categorias" class="admin-tab-content">
            <div class="panel">
                <h2>Categorias</h2>
                <ul>
                    <?php foreach ($categorias as $cat): ?>
                        <li style="padding: 8px 0; border-bottom: 1px solid var(--border);"><strong><?= e($cat['nome']) ?></strong> - <?= e($cat['desc'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

    <!-- ==================== TELA DEDICADA 2: DESENVOLVEDOR ==================== -->
    <?php elseif ($viewRoleKey === 'developer'): ?>
        <div class="panel" style="border-left: 4px solid #06b6d4;">
            <h2 style="color: #67e8f9;">🛠️ Painel do Desenvolvedor &amp; Engenharia de Sistemas</h2>
            <p class="sub">Informações técnicas em tempo real, estado de tabelas e diagnóstico do ambiente PHP/MySQL.</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-top: 16px;">
                <div style="background: rgba(6,182,212,0.1); padding: 14px; border-radius: 8px; border: 1px solid rgba(6,182,212,0.3);">
                    <small style="color: #a5f3fc;">Versão PHP</small>
                    <div style="font-size: 1.2rem; font-weight: 700; color: #fff;"><?= PHP_VERSION ?></div>
                </div>
                <div style="background: rgba(6,182,212,0.1); padding: 14px; border-radius: 8px; border: 1px solid rgba(6,182,212,0.3);">
                    <small style="color: #a5f3fc;">Servidor Web</small>
                    <div style="font-size: 0.92rem; font-weight: 700; color: #fff;"><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?></div>
                </div>
                <div style="background: rgba(6,182,212,0.1); padding: 14px; border-radius: 8px; border: 1px solid rgba(6,182,212,0.3);">
                    <small style="color: #a5f3fc;">Status Banco MySQL</small>
                    <div style="font-size: 1.1rem; font-weight: 700; color: #86efac;">🟢 Ativo e Conectado</div>
                </div>
                <div style="background: rgba(6,182,212,0.1); padding: 14px; border-radius: 8px; border: 1px solid rgba(6,182,212,0.3);">
                    <small style="color: #a5f3fc;">Sessão PHP</small>
                    <div style="font-size: 0.85rem; font-weight: 600; color: #67e8f9; overflow: hidden; text-overflow: ellipsis;"><?= e(session_id()) ?></div>
                </div>
            </div>

            <h3 style="margin-top: 24px; color: #a5f3fc;">Contagem de Registros por Tabela MySQL</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-top: 12px;">
                <?php
                    $tables = ['usuarios', 'produtos', 'pedidos', 'carts', 'cart_items', 'enderecos', 'categorias', 'cupons', 'chamados_suporte', 'avaliacoes_produtos', 'logistica_pedidos'];
                    foreach ($tables as $tbl) {
                        $q = $db->query("SELECT COUNT(*) AS total FROM $tbl");
                        $cnt = $q ? (int) ($q->fetch_assoc()['total'] ?? 0) : 0;
                        echo "<div style='background: rgba(0,0,0,0.3); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border); display:flex; justify-content:space-between; align-items:center;'>";
                        echo "<span style='font-size:0.85rem; color:var(--muted);'>$tbl</span>";
                        echo "<strong style='color:#86efac; font-size:1rem;'>$cnt</strong>";
                        echo "</div>";
                    }
                ?>
            </div>

            <div style="margin-top: 24px;">
                <form method="post" action="<?= e($panelAction) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="limpar_cache_sistema">
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="password" name="senha_master" required placeholder="🔒 Sua Chave Mestre Desenvolvedor" style="padding: 8px 12px; border-radius: 6px; background: rgba(0,0,0,0.4); border: 1px solid var(--border); color:#fff;">
                        <button type="submit" class="btn btn-sm">⚡ Limpar Cache e Re-sincronizar Carrinho</button>
                    </div>
                </form>
            </div>
        </div>

    <!-- ==================== TELA DEDICADA 3: SUPORTE ==================== -->
    <?php elseif ($viewRoleKey === 'support'): ?>
        <div class="panel" style="border-left: 4px solid #3b82f6;">
            <h2 style="color: #93c5fd;">🎧 Central de Atendimento &amp; Suporte ao Cliente</h2>
            <p class="sub">Atenda chamados, consulte compras de clientes e atualize informações de atendimento.</p>

            <h3 style="margin-top: 20px; color: #93c5fd;">Chamados de Atendimento Recebidos (<?= count($chamadosSuporte) ?>)</h3>
            <div class="admin-table-responsive" style="margin-top: 10px;">
                <table class="admin-table">
                    <thead>
                        <tr><th>ID</th><th>Cliente</th><th>Assunto</th><th>Mensagem do Cliente</th><th>Status</th><th>Ação de Resposta</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chamadosSuporte as $cham): ?>
                            <tr>
                                <td>#<?= (int) $cham['id'] ?></td>
                                <td><strong><?= e($cham['usuario_nome'] ?? 'Cliente') ?></strong><br><small><?= e($cham['usuario_email'] ?? '') ?></small></td>
                                <td><strong><?= e($cham['assunto']) ?></strong></td>
                                <td style="max-width:280px; font-size:0.85rem;"><?= e($cham['mensagem']) ?><?php if (!empty($cham['resposta'])): ?><br><small style="color:#86efac;"><strong>Resposta enviada:</strong> <?= e($cham['resposta']) ?></small><?php endif; ?></td>
                                <td><span class="badge-status <?= $cham['status'] === 'Respondido' ? 'status-entregue' : 'status-pago' ?>"><?= e($cham['status']) ?></span></td>
                                <td>
                                        <form method="post" action="<?= e($panelAction) ?>" style="display:flex; flex-direction:column; gap:6px;">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="acao" value="responder_chamado_suporte">
                                        <input type="hidden" name="chamado_id" value="<?= (int) $cham['id'] ?>">
                                        <input type="text" name="resposta" placeholder="Digite a resposta do suporte..." required style="padding:4px 8px; font-size:0.8rem; border-radius:4px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
                                        <input type="password" name="senha_master" placeholder="🔑 Sua Chave Mestre" required style="padding:4px 8px; font-size:0.8rem; border-radius:4px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
                                        <button type="submit" class="btn btn-sm">Enviar Resposta</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ==================== TELA DEDICADA 4: MODERADOR ==================== -->
    <?php elseif ($viewRoleKey === 'moderator'): ?>
        <div class="panel" style="border-left: 4px solid #a855f7;">
            <h2 style="color: #c084fc;">🛡️ Central de Moderação de Conteúdo &amp; Comunidade</h2>
            <p class="sub">Aprove ou rejeite avaliações de produtos e modere publicações de clientes.</p>

            <h3 style="margin-top: 20px; color: #c084fc;">Avaliações de Produtos para Moderação (<?= count($avaliacoesProdutos) ?>)</h3>
            <div class="admin-table-responsive" style="margin-top: 10px;">
                <table class="admin-table">
                    <thead>
                        <tr><th>ID</th><th>Produto</th><th>Cliente</th><th>Nota</th><th>Comentário</th><th>Curtidas</th><th>Denúncias</th><th>Status</th><th>Ações de Moderação</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avaliacoesProdutos as $aval): ?>
                            <tr>
                                <td>#<?= (int) $aval['id'] ?></td>
                                <td><strong><?= e($aval['produto_nome'] ?? 'Produto') ?></strong></td>
                                <td><?= e($aval['usuario_nome']) ?></td>
                                <td><?= str_repeat('⭐', (int) $aval['nota']) ?></td>
                                <td style="max-width:250px; font-size:0.85rem;"><?= e($aval['comentario']) ?><?php if (!empty($aval['denuncias_info'])): ?><br><small style="color:#fca5a5;">Denúncia: <?= e($aval['denuncias_info']) ?></small><?php endif; ?></td>
                                <td><?= (int) $aval['likes'] ?></td>
                                <td><?= (int) $aval['denuncias'] ?></td>
                                <td><span class="badge-status <?= $aval['status'] === 'Aprovado' ? 'status-entregue' : 'status-reembolsado' ?>"><?= e($aval['status']) ?></span></td>
                                <td>
                                    <form method="post" action="<?= e($panelAction) ?>" style="display:flex; gap:6px; align-items:center;">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="acao" value="moderar_avaliacao_produto">
                                        <input type="hidden" name="avaliacao_id" value="<?= (int) $aval['id'] ?>">
                                        <input type="hidden" name="usuario_id" value="<?= (int) $aval['usuario_id'] ?>">
                                        <input type="password" name="senha_master" placeholder="🔑 Sua Chave Mestre" required style="padding:4px 8px; font-size:0.8rem; border-radius:4px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff; width:130px;">
                                        <button type="submit" name="status_moderacao" value="Aprovado" class="btn btn-sm">Aprovar</button>
                                        <button type="submit" name="status_moderacao" value="Rejeitado" class="btn btn-sm btn-danger">Rejeitar</button>
                                        <button type="submit" name="acao_override" value="excluir_avaliacao" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este comentário e suas interações?');">Excluir comentário</button>
                                        <button type="submit" name="acao_override" value="bloquear_usuario" class="btn btn-sm btn-danger" onclick="return confirm('Bloquear este usuário? Ele não poderá entrar até ser aprovado novamente.');">Bloquear usuário</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel" style="margin-top: 18px;">
            <h3>Contas aguardando aprovação</h3>
            <?php $contasPendentes = array_filter($usuarios, static fn ($conta) => ($conta['status_conta'] ?? 'ativo') === 'pendente'); ?>
            <?php if (empty($contasPendentes)): ?>
                <p class="sub">Nenhuma recriação de conta aguardando análise.</p>
            <?php else: ?>
                <div class="admin-table-responsive">
                    <table class="admin-table">
                        <thead><tr><th>Usuário</th><th>E-mail</th><th>Ação</th></tr></thead>
                        <tbody>
                            <?php foreach ($contasPendentes as $conta): ?>
                                <tr>
                                    <td><?= e($conta['nome']) ?></td>
                                    <td><?= e($conta['email']) ?></td>
                                    <td><form method="post" action="<?= e($base) ?>/pages/painel.php?area=moderator"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="aprovar_usuario"><input type="hidden" name="usuario_id" value="<?= (int) $conta['id'] ?>"><input type="password" name="senha_master" required placeholder="Chave mestre"><button type="submit" class="btn btn-sm">Aprovar conta</button></form></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <!-- ==================== TELA DEDICADA 5: GERENTE DE LOJA ==================== -->
    <?php elseif ($viewRoleKey === 'manager'): ?>
        <div class="panel" style="border-left: 4px solid #10b981;">
            <h2 style="color: #6ee7b7;">📦 Gestão Comercial, Estoque e Promoções</h2>
            <p class="sub">Controle de catálogo, alteração rápida de preço/estoque e cadastro de cupons de desconto.</p>

            <div style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); border-radius: 12px; padding: 16px; margin-top: 16px;">
                <h3 style="color: #6ee7b7; font-size: 1rem; margin-bottom: 10px;">🎟️ Cadastrar Novo Cupom de Desconto</h3>
                <form method="post" action="<?= e($panelAction) ?>" class="manager-coupon-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="adicionar_cupom">
                    <input type="text" name="codigo_cupom" placeholder="Ex: PROMO15" required style="padding:8px 12px; border-radius:6px; background:rgba(0,0,0,0.4); border:1px solid var(--border); color:#fff; text-transform:uppercase;">
                    <input type="number" name="desconto_percentual" placeholder="Desconto %" min="1" max="90" required style="padding:8px 12px; border-radius:6px; background:rgba(0,0,0,0.4); border:1px solid var(--border); color:#fff; width:120px;">
                    <input type="password" name="senha_master" placeholder="🔑 Sua Chave Mestre" required style="padding:8px 12px; border-radius:6px; background:rgba(0,0,0,0.4); border:1px solid var(--border); color:#fff;">
                    <button type="submit" class="btn btn-sm">Criar Cupom</button>
                </form>
            </div>

            <h3 style="margin-top: 24px; color: #6ee7b7;">Cupons Ativos na Loja</h3>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
                <?php foreach ($cuponsLoja as $cup): ?>
                    <div class="manager-coupon-card">
                        <strong style="color:#86efac;"><?= e($cup['codigo']) ?></strong> — <?= (float)$cup['desconto_percentual'] ?>% OFF
                        <form method="post" action="<?= e($panelAction) ?>" class="manager-coupon-edit">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="editar_cupom"><input type="hidden" name="cupom_id" value="<?= (int) $cup['id'] ?>"><input type="hidden" name="ativo" value="1">
                            <input type="number" name="desconto_percentual" value="<?= (float) $cup['desconto_percentual'] ?>" min="1" max="90" step="0.01" style="width:70px;"><input type="password" name="senha_master" required placeholder="Chave" style="width:80px;"><button type="submit" class="btn btn-sm">Salvar</button>
                        </form>
                        <form method="post" action="<?= e($panelAction) ?>" class="manager-coupon-edit"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="acao" value="excluir_cupom"><input type="hidden" name="cupom_id" value="<?= (int) $cup['id'] ?>"><input type="password" name="senha_master" required placeholder="Chave"><button type="submit" class="btn btn-sm btn-danger">Excluir</button></form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="panel-head-flex" style="margin-top: 24px;">
                <h3>Catálogo de Produtos (<?= count($produtos) ?>)</h3>
                <button type="button" class="btn btn-sm" onclick="document.getElementById('manager-new-product').scrollIntoView({ behavior: 'smooth' })">➕ Cadastrar Produto</button>
            </div>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td>#<?= (int) $p['id'] ?></td>
                                <td><strong><?= e($p['nome']) ?></strong></td>
                                <td><?= e($p['categoria']) ?></td>
                                <td><?= e(money($p['preco'])) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditProductModal(<?= json_encode($p) ?>)'>Editar Produto/Preço</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="manager-new-product" class="staff-form-panel">
                <h3>Cadastrar produto</h3>
                <form method="post" action="<?= e($base) ?>/pages/painel.php?area=manager" class="form-grid" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="acao" value="adicionar_produto">
                    <div class="field"><label for="manager_product_name">Nome</label><input id="manager_product_name" name="nome" required></div>
                    <div class="field"><label for="manager_product_category">Categoria</label><input id="manager_product_category" name="categoria" required></div>
                    <div class="field"><label for="manager_product_price">Preço</label><input id="manager_product_price" name="preco" type="number" min="0.01" step="0.01" required></div>
                    <input type="hidden" id="manager_product_image" name="imagem" value="default.png">
                    <div class="field" style="grid-column: 1 / -1;"><label for="manager_product_description">Descrição</label><textarea id="manager_product_description" name="descricao" rows="3" required></textarea></div>
                    <label class="checkbox-field"><input type="checkbox" name="destaque" value="1"> Produto em destaque</label>
                    <div class="field-master-security"><label for="manager_product_master">Chave mestre</label><input id="manager_product_master" type="password" name="senha_master" required></div>
                    <button type="submit" class="btn">Cadastrar produto</button>
                </form>
            </div>
        </div>

    <!-- ==================== TELA DEDICADA 6: FINANCEIRO ==================== -->
    <?php elseif ($viewRoleKey === 'financial'): ?>
        <div class="panel" style="border-left: 4px solid #f59e0b;">
            <h2 style="color: #fcd34d;">💰 Painel Financeiro &amp; Balanço Comercial</h2>
            <p class="sub">Métricas financeiras, faturamento bruto e processamento de solicitações de reembolso.</p>

            <?php
                $faturamentoTotal = array_reduce($pedidos, fn($sum, $p) => $sum + ($p['preco'] * $p['quantidade']), 0);
                $qtdPedidosPagos = count(array_filter($pedidos, fn($p) => $p['status'] !== 'Reembolsado'));
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 16px;">
                <div style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 18px;">
                    <small style="color: #fcd34d; font-weight: 600;">Faturamento Bruto Total em Vendas</small>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #fff; margin-top: 4px;"><?= e(money($faturamentoTotal)) ?></div>
                </div>
                <div style="background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.3); border-radius: 12px; padding: 18px;">
                    <small style="color: #fcd34d; font-weight: 600;">Vendas Confirmadas</small>
                    <div style="font-size: 1.6rem; font-weight: 700; color: #86efac; margin-top: 4px;"><?= $qtdPedidosPagos ?> pedidos</div>
                </div>
            </div>

            <h3 style="margin-top: 24px; color: #fcd34d;">Transações de Pedidos e Ações Financeiras</h3>
            <div class="admin-table-responsive" style="margin-top: 10px;">
                <table class="admin-table">
                    <thead>
                        <tr><th>ID Pedido</th><th>Cliente</th><th>Valor Bruto</th><th>Status Atual</th><th>Ação Financeira</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $ped): ?>
                            <tr>
                                <td>#<?= (int) $ped['id'] ?></td>
                                <td><strong><?= e($ped['usuario_nome']) ?></strong></td>
                                <td><strong><?= e(money($ped['preco'] * $ped['quantidade'])) ?></strong></td>
                                <td><span class="badge-status <?= $ped['status'] === 'Reembolsado' ? 'status-reembolsado' : 'status-entregue' ?>"><?= e($ped['status']) ?></span></td>
                                <td>
                                    <?php if ($ped['status'] !== 'Reembolsado'): ?>
                                        <form method="post" action="<?= e($panelAction) ?>" style="display:flex; gap:6px; align-items:center;">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="acao" value="processar_reembolso_financeiro">
                                            <input type="hidden" name="pedido_id" value="<?= (int) $ped['id'] ?>">
                                            
                                            <input type="password" name="senha_master" placeholder="🔑 Sua Chave Mestre" required style="padding:4px 8px; font-size:0.8rem; border-radius:4px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff; width:130px;">
                                            <button type="submit" name="acao" value="atualizar_pedido" class="btn btn-sm">Atualizar status</button>
                                            <button type="submit" name="acao" value="processar_reembolso_financeiro" class="btn btn-sm btn-status-reembolso" onclick="return confirm('Confirmar reembolso e estorno financeiro deste pedido?');">Reembolsar</button>
                                        </form>
                                    <?php else: ?>
                                        <small style="color:#86efac;">Reembolso concluído</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ==================== TELA DEDICADA 7: LOGÍSTICA ==================== -->
    <?php elseif ($viewRoleKey === 'logistics'): ?>
        <div class="panel" style="border-left: 4px solid #f97316;">
            <h2 style="color: #fdba74;">🚚 Central de Logística &amp; Expedição de Cargas</h2>
            <p class="sub">Atualização do status de separação, envio e atribuição do código de rastreamento dos Correios/Transportadora.</p>

            <h3 style="margin-top: 20px; color: #fdba74;">Fila de Expedição de Pedidos</h3>
            <div class="admin-table-responsive" style="margin-top: 10px;">
                <table class="admin-table">
                    <thead>
                        <tr><th>ID Pedido</th><th>Destinatário &amp; Endereço</th><th>Status Atual</th><th>Atualizar Expedição / Código de Rastreio</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $ped): ?>
                            <tr>
                                <td>#<?= (int) $ped['id'] ?></td>
                                <td>
                                    <strong><?= e($ped['usuario_nome']) ?></strong><br>
                                    <?php if (!empty($ped['rua'])): ?>
                                        <small><?= e($ped['rua']) ?>, nº <?= e($ped['numero']) ?> — <?= e($ped['cidade']) ?>/<?= e($ped['estado']) ?> (CEP: <?= e($ped['cep']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge-status status-pago"><?= e($ped['status']) ?></span></td>
                                <td>
                                    <form method="post" action="<?= e($panelAction) ?>" style="display:flex; flex-direction:column; gap:6px;">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="acao" value="atualizar_rastreio_logistica">
                                        <input type="hidden" name="pedido_id" value="<?= (int) $ped['id'] ?>">
                                        <div style="display:flex; gap:6px;">
                                            <?php $logisticaAtual = $logisticaPorPedido[(int) $ped['id']] ?? []; ?>
                                            <input type="text" name="codigo_rastreio" placeholder="Ex: TF123456789BR" value="<?= e($logisticaAtual['codigo_rastreio'] ?? '') ?>" required style="padding:4px 8px; font-size:0.8rem; border-radius:4px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff; width:140px;">
                                            <select name="status_expedicao" style="padding:4px 8px; font-size:0.8rem; border-radius:4px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
                                                <option value="Em Separação">Em Separação no Estoque</option>
                                                <option value="Enviado">Enviado / Em Trânsito</option>
                                                <option value="Entregue">Entregue ao Cliente</option>
                                            </select>
                                        </div>
                                        <div style="display:flex; gap:6px;">
                                            <input type="password" name="senha_master" placeholder="🔑 Sua Chave Mestre" required style="padding:4px 8px; font-size:0.8rem; border-radius:4px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
                                            <button type="submit" class="btn btn-sm">Atualizar Rastreio</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="modalManagerEditProduct" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card admin-modal-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalManagerEditProduct')">×</button>
        <h3>Editar produto</h3>
        <form method="post" action="<?= e($base) ?>/pages/painel.php?area=manager" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="editar_produto">
            <input type="hidden" name="produto_id" id="manager_edit_product_id">
            <div class="field"><label for="manager_edit_product_name">Nome</label><input id="manager_edit_product_name" name="nome" required></div>
            <div class="field"><label for="manager_edit_product_category">Categoria</label><input id="manager_edit_product_category" name="categoria" required></div>
            <div class="field"><label for="manager_edit_product_price">Preço</label><input id="manager_edit_product_price" name="preco" type="number" min="0.01" step="0.01" required></div>
            <input type="hidden" id="manager_edit_product_image" name="imagem" value="default.png">
            <div class="field" style="grid-column: 1 / -1;"><label for="manager_edit_product_description">Descrição</label><textarea id="manager_edit_product_description" name="descricao" rows="3" required></textarea></div>
            <label class="checkbox-field"><input id="manager_edit_product_featured" type="checkbox" name="destaque" value="1"> Produto em destaque</label>
            <div class="field-master-security"><label for="manager_edit_product_master">Chave mestre</label><input id="manager_edit_product_master" type="password" name="senha_master" required></div>
            <button type="submit" class="btn">Salvar alterações</button>
        </form>
    </div>
</div>

<!-- ==================== MODAL GLOBAL: ALTERAR STATUS PEDIDO ==================== -->
<div id="modalEditOrder" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalEditOrder')">×</button>
        <h3>Alterar Status do Pedido</h3>
        <form method="post" action="<?= e($panelAction) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="atualizar_pedido">
            <input type="hidden" name="pedido_id" id="edit_order_id">

            <div class="field" style="margin-bottom: 14px;">
                <label for="edit_order_status">Selecione o Novo Status</label>
                <select id="edit_order_status" name="status" required style="width: 100%; padding: 10px; border-radius: 8px; background: rgba(0,0,0,0.5); color: #fff; border: 1px solid var(--border);">
                    <option value="Pago">Pago / Confirmado</option>
                    <option value="Em Separação">Em Separação no Estoque</option>
                    <option value="Enviado">Enviado em Trânsito</option>
                    <option value="Entregue">Entregue ao Cliente</option>
                    <option value="Não recebido">Não Recebido</option>
                    <option value="Reembolsado">Reembolsado</option>
                </select>
            </div>

            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="edit_order_master">🔒 Sua Chave Mestre de Confirmação</label>
                <input type="password" id="edit_order_master" name="senha_master" required placeholder="Digite a sua chave mestre">
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalEditOrder')">Cancelar</button>
                <button type="submit" class="btn">Confirmar Alteração</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL NOVO USUARIO (ADMIN) -->
<div id="modalAddUser" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card admin-modal-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalAddUser')">×</button>
        <h3>➕ Cadastrar Novo Usuário e Cargo</h3>
        <form method="post" action="<?= e($panelAction) ?>" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="adicionar_usuario">

            <div class="field">
                <label for="add_user_nome">Nome Completo</label>
                <input id="add_user_nome" name="nome" type="text" required>
            </div>
            <div class="field">
                <label for="add_user_email">E-mail</label>
                <input id="add_user_email" name="email" type="email" required>
            </div>
            <div class="field">
                <label for="add_user_nasc">Data de Nascimento</label>
                <input id="add_user_nasc" name="nascimento" type="date" required>
            </div>
            <div class="field field-password">
                <label for="add_user_senha">Senha (8-16 caracteres)</label>
                <input id="add_user_senha" name="senha" type="password" minlength="8" maxlength="16" required>
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label for="add_user_tipo">Perfil de Acesso / Cargo do Usuário</label>
                <select id="add_user_tipo" name="tipo" required style="background: rgba(16, 3, 5, 0.45); border: 1px solid var(--border); color: var(--text); border-radius: 10px; padding: 12px 14px; width: 100%;">
                    <option value="customer">🛒 8. Cliente (Acesso comum a compras e perfil)</option>
                    <option value="admin">👑 1. Administrador (Controle total da plataforma)</option>
                    <option value="developer">🛠️ 2. Desenvolvedor (Acesso técnico, logs e configurações)</option>
                    <option value="support">🎧 3. Suporte (Atendimento, consulta de pedidos e entregas)</option>
                    <option value="moderator">🛡️ 4. Moderador (Moderação de conteúdo e suspensão de usuários)</option>
                    <option value="manager">📦 5. Gerente de Loja (Produtos, estoque, categorias e promoções)</option>
                    <option value="financial">💰 6. Financeiro (Acompanhamento financeiro e reembolsos)</option>
                    <option value="logistics">🚚 7. Logística (Expedição, rastreamento e status de envio)</option>
                </select>
            </div>
            <div class="field-master-security" style="grid-column: 1 / -1;">
                <label for="add_user_master">🔒 Sua Chave Mestre de Confirmação</label>
                <input type="password" id="add_user_master" name="senha_master" required placeholder="Digite a sua chave mestre">
            </div>
            <div class="panel-actions" style="grid-column: 1 / -1; display:flex; gap:10px;">
                <button type="submit" class="btn">Cadastrar Usuário</button>
                <button type="button" class="btn secondary" onclick="closeModal('modalAddUser')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeModal(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}
function openEditProductModal(product) {
    document.getElementById('manager_edit_product_id').value = product.id;
    document.getElementById('manager_edit_product_name').value = product.nome || '';
    document.getElementById('manager_edit_product_category').value = product.categoria || '';
    document.getElementById('manager_edit_product_price').value = product.preco || '';
    document.getElementById('manager_edit_product_image').value = product.imagem || 'default.png';
    document.getElementById('manager_edit_product_description').value = product.descricao || '';
    document.getElementById('manager_edit_product_featured').checked = !!Number(product.destaque);
    document.getElementById('manager_edit_product_master').value = '';
    document.getElementById('modalManagerEditProduct').classList.remove('hidden');
}
function openAddUserModal() {
    var el = document.getElementById('modalAddUser');
    if (el) el.classList.remove('hidden');
}
function openEditOrderModal(ped) {
    document.getElementById('edit_order_id').value = ped.id;
    if (document.getElementById('edit_order_status')) {
        document.getElementById('edit_order_status').value = ped.status || 'Pago';
    }
    document.getElementById('modalEditOrder').classList.remove('hidden');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
