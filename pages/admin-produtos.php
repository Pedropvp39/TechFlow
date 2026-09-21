<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/pedidos.php';

no_cache();
// Esta tela administra produtos, usuários e categorias; exige administrador real.
require_admin();

$base = base_url();
$mensagem = '';
$tipoMensagem = 'success';

// Processamento de Ações do Administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $mensagem = 'Sessão expirada. Tente novamente.';
        $tipoMensagem = 'error';
    } else {
        $senhaMaster = $_POST['senha_master'] ?? '';
        $acao = $_POST['acao_override'] ?? $_POST['acao'] ?? '';

        // Validação obrigatória da Senha de Confirmação Master
        if (!validar_senha_mestre_admin($senhaMaster)) {
            $mensagem = '🔒 Senha de confirmação de administrador incorreta! Operação cancelada por segurança. (Chave padrão de segurança: master88)';
            $tipoMensagem = 'error';
        } else {
            switch ($acao) {
                // --- PRODUTOS ---
                case 'adicionar_produto':
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
                    break;

                case 'editar_produto':
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
                    break;

                case 'excluir_produto':
                    $prodId = (int) ($_POST['produto_id'] ?? 0);
                    $res = excluir_produto($prodId);
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                // --- USUÁRIOS ---
                case 'adicionar_usuario':
                    $res = admin_criar_usuario([
                        'nome' => $_POST['nome'] ?? '',
                        'email' => $_POST['email'] ?? '',
                        'nascimento' => $_POST['nascimento'] ?? '',
                        'senha' => $_POST['senha'] ?? '',
                        'tipo' => $_POST['tipo'] ?? 'customer',
                        'is_admin' => !empty($_POST['is_admin']) || (isset($_POST['tipo']) && $_POST['tipo'] === 'admin'),
                    ]);
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                case 'editar_usuario':
                    $userId = (int) ($_POST['usuario_id'] ?? 0);
                    $res = admin_atualizar_usuario($userId, [
                        'nome' => $_POST['nome'] ?? '',
                        'email' => $_POST['email'] ?? '',
                        'nascimento' => $_POST['nascimento'] ?? '',
                        'tipo' => $_POST['tipo'] ?? 'customer',
                        'is_admin' => !empty($_POST['is_admin']),
                        'senha_nova' => $_POST['senha_nova'] ?? '',
                    ]);
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                case 'excluir_usuario':
                    $userId = (int) ($_POST['usuario_id'] ?? 0);
                    $res = admin_excluir_usuario($userId);
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                // --- PEDIDOS ---
                case 'atualizar_pedido':
                    $pedId = (int) ($_POST['pedido_id'] ?? 0);
                    $novoStatus = trim((string) ($_POST['status'] ?? ''));
                    if ($pedId > 0 && $novoStatus !== '') {
                        admin_atualizar_status_pedido($pedId, $novoStatus);
                        $mensagem = "Status do pedido #$pedId atualizado para '$novoStatus' com sucesso!";
                        $tipoMensagem = 'success';
                    }
                    break;

                case 'excluir_pedido':
                    $pedId = (int) ($_POST['pedido_id'] ?? 0);
                    if ($pedId > 0) {
                        admin_excluir_pedido($pedId);
                        $mensagem = "Pedido #$pedId excluído permanentemente com sucesso!";
                        $tipoMensagem = 'success';
                    }
                    break;

                case 'moderar_avaliacao_produto':
                    $avalId = (int) ($_POST['avaliacao_id'] ?? 0);
                    $novoSt = trim((string) ($_POST['status_moderacao'] ?? 'Aprovado'));
                    if ($avalId > 0 && in_array($novoSt, ['Aprovado', 'Rejeitado'], true)) {
                        $db = db_connect();
                        $stmt = $db->prepare('UPDATE avaliacoes_produtos SET status = ? WHERE id = ?');
                        $stmt->bind_param('si', $novoSt, $avalId);
                        $stmt->execute();
                        $mensagem = 'Status da avaliação atualizado.';
                    }
                    break;

                case 'excluir_avaliacao':
                    $res = excluir_avaliacao_moderacao((int) ($_POST['avaliacao_id'] ?? 0));
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                case 'bloquear_usuario':
                case 'aprovar_usuario':
                    $res = moderacao_atualizar_conta((int) ($_POST['usuario_id'] ?? 0), $acao === 'bloquear_usuario' ? 'bloqueado' : 'ativo');
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                // --- CATEGORIAS ---
                case 'adicionar_categoria':
                    $res = adicionar_categoria($_POST['nome'] ?? '', $_POST['descricao'] ?? '', $_POST['icone'] ?? '');
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                case 'editar_categoria':
                    $catId = (int) ($_POST['categoria_id'] ?? 0);
                    $res = atualizar_categoria($catId, $_POST['nome'] ?? '', $_POST['descricao'] ?? '');
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;

                case 'excluir_categoria':
                    $catId = (int) ($_POST['categoria_id'] ?? 0);
                    $res = excluir_categoria($catId);
                    $mensagem = $res['mensagem'];
                    $tipoMensagem = $res['ok'] ? 'success' : 'error';
                    break;
            }
        }
    }
}

// Carregamento de dados atualizados
$produtos = get_produtos();
$categorias = get_categorias();
$usuarios = get_todos_usuarios();
$pedidos = get_todos_pedidos_admin();
$avaliacoesProdutos = [];
$dbAvaliacoes = db_connect();
$resAvaliacoes = $dbAvaliacoes->query("SELECT a.*, p.nome AS produto_nome, COALESCE(SUM(CASE WHEN i.tipo = 'like' THEN 1 ELSE 0 END), 0) AS likes, COALESCE(SUM(CASE WHEN i.tipo = 'denuncia' THEN 1 ELSE 0 END), 0) AS denuncias, GROUP_CONCAT(CASE WHEN i.tipo = 'denuncia' THEN CONCAT(COALESCE(i.motivo_denuncia, 'Sem motivo'), ' - ', COALESCE(i.detalhes_denuncia, 'Sem detalhes'), ' (', COALESCE(i.denunciante_nome, 'Usuário'), ')') END SEPARATOR ' | ') AS denuncias_info FROM avaliacoes_produtos a LEFT JOIN produtos p ON p.id = a.produto_id LEFT JOIN avaliacoes_interacoes i ON i.avaliacao_id = a.id GROUP BY a.id ORDER BY a.id DESC");
if ($resAvaliacoes) {
    while ($row = $resAvaliacoes->fetch_assoc()) $avaliacoesProdutos[] = $row;
}

$page_title = 'Painel Administrativo Geral';
require __DIR__ . '/../includes/header.php';
?>

<div class="section">
    <div class="section-head">
        <div>
            <h1>Painel de Controle do Administrador</h1>
            <p>Acesso total para gerenciar produtos, usuários, pedidos e categorias do sistema.</p>
        </div>
        <div class="admin-master-badge">
            <span>🔒 Chave de Confirmação Ativa</span>
        </div>
    </div>

    <nav class="admin-nav-tabs" aria-label="Módulos administrativos">
        <a class="admin-tab-btn" href="<?= e($base) ?>/pages/painel.php?area=developer">Desenvolvimento</a>
        <a class="admin-tab-btn" href="<?= e($base) ?>/pages/painel.php?area=support">Suporte</a>
        <a class="admin-tab-btn" href="<?= e($base) ?>/pages/painel.php?area=moderator">Moderação</a>
        <a class="admin-tab-btn" href="<?= e($base) ?>/pages/painel.php?area=manager">Loja e Cupons</a>
        <a class="admin-tab-btn" href="<?= e($base) ?>/pages/painel.php?area=financial">Financeiro</a>
        <a class="admin-tab-btn" href="<?= e($base) ?>/pages/painel.php?area=logistics">Logística</a>
    </nav>

    <?php if ($mensagem): ?>
        <p class="alert <?= $tipoMensagem === 'success' ? 'alert-success' : 'alert-error' ?>" role="status"><?= e($mensagem) ?></p>
    <?php endif; ?>

    <!-- Abas do Painel Administrativo -->
    <div class="admin-nav-tabs" role="tablist">
        <button type="button" class="admin-tab-btn active" data-tab="produtos">Produtos (<?= count($produtos) ?>)</button>
        <button type="button" class="admin-tab-btn" data-tab="usuarios">Usuários (<?= count($usuarios) ?>)</button>
        <button type="button" class="admin-tab-btn" data-tab="pedidos">Vendas e Pedidos (<?= count($pedidos) ?>)</button>
        <button type="button" class="admin-tab-btn" data-tab="categorias">Categorias (<?= count($categorias) ?>)</button>
        <button type="button" class="admin-tab-btn" data-tab="avaliacoes">Avaliações (<?= count($avaliacoesProdutos) ?>)</button>
        <button type="button" class="admin-tab-btn" data-tab="novo-produto">Cadastrar Produto</button>
    </div>

    <!-- ==================== TAB 1: PRODUTOS ==================== -->
    <div id="tab-produtos" class="admin-tab-content active">
        <div class="panel">
            <div class="panel-head-flex">
                <h2>Gerenciamento de Produtos</h2>
                <button type="button" class="btn btn-sm" onclick="openTab('novo-produto')">Novo Produto</button>
            </div>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>ID</th>
                            <th>Nome do Produto</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Destaque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $p): ?>
                            <tr>
                                <td>
                                    <img src="<?= e($base) ?>/assets/img/<?= e($p['imagem']) ?>" alt="" class="admin-thumb">
                                </td>
                                <td>#<?= (int) $p['id'] ?></td>
                                <td><strong><?= e($p['nome']) ?></strong></td>
                                <td><span class="produto-cat"><?= e($p['categoria']) ?></span></td>
                                <td><strong class="preco-admin"><?= e(money($p['preco'])) ?></strong></td>
                                <td>
                                    <?= !empty($p['destaque']) ? '<span class="badge-status status-entregue">Sim</span>' : '<span class="badge-status status-pago">Não</span>' ?>
                                </td>
                                <td>
                                    <div class="admin-actions-cell">
                                        <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditProductModal(<?= json_encode($p) ?>)'>Editar</button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteProductModal(<?= (int) $p['id'] ?>, '<?= e(addslashes($p['nome'])) ?>')">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 2: USUÁRIOS ==================== -->
    <div id="tab-usuarios" class="admin-tab-content">
        <div class="panel">
            <div class="panel-head-flex">
                <h2>Gerenciamento de Usuários</h2>
                <button type="button" class="btn btn-sm" onclick="openAddUserModal()">➕ Novo Usuário</button>
            </div>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Data Nasc.</th>
                            <th>Perfil / Cargo</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td>#<?= (int) $u['id'] ?></td>
                                <td><strong><?= e($u['nome']) ?></strong></td>
                                <td><?= e($u['email']) ?></td>
                                <td><?= e($u['nascimento'] ? date('d/m/Y', strtotime($u['nascimento'])) : '-') ?></td>
                                <td>
                                    <?php
                                        $uTipo = strtolower((string) ($u['tipo'] ?? 'customer'));
                                        if ($uTipo === 'cliente') $uTipo = 'customer';
                                        $rolesInfo = get_system_roles();
                                        $rBadge = $rolesInfo[$uTipo]['badge'] ?? '🛒 Cliente';
                                    ?>
                                    <span class="badge-status status-reembolsado"><?= e($rBadge) ?></span>
                                </td>
                                <td><span class="badge-status <?= ($u['status_conta'] ?? 'ativo') === 'bloqueado' ? 'status-reembolsado' : (($u['status_conta'] ?? 'ativo') === 'pendente' ? 'status-pago' : 'status-entregue') ?>"><?= e($u['status_conta'] ?? 'ativo') ?></span></td>
                                <td><small><?= e($u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '-') ?></small></td>
                                <td>
                                    <div class="admin-actions-cell">
                                        <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditUserModal(<?= json_encode($u) ?>)'>Editar</button>
                                        <?php if ((int) $u['id'] !== (int) ($_SESSION['user']['id'] ?? 0)): ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteUserModal(<?= (int) $u['id'] ?>, '<?= e(addslashes($u['nome'])) ?>')">Excluir</button>
                                            <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php" class="admin-inline-form">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                                                <input type="password" name="senha_master" required placeholder="Chave mestre" aria-label="Chave mestre para alterar status">
                                                <?php if (in_array(($u['status_conta'] ?? 'ativo'), ['bloqueado', 'pendente'], true)): ?>
                                                    <button type="submit" name="acao" value="aprovar_usuario" class="btn btn-sm">Aprovar</button>
                                                <?php else: ?>
                                                    <button type="submit" name="acao" value="bloquear_usuario" class="btn btn-sm btn-danger" onclick="return confirm('Banir este usuário?');">Banir</button>
                                                <?php endif; ?>
                                            </form>
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

    <!-- ==================== TAB 3: PEDIDOS ==================== -->
    <div id="tab-pedidos" class="admin-tab-content">
        <div class="panel">
            <h2>Todas as Vendas e Pedidos dos Clientes</h2>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Produto</th>
                            <th>Qtd</th>
                            <th>Valor Total</th>
                            <th>Status Atual</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 24px; color: var(--muted);">Nenhum pedido registrado no sistema ainda.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $ped): ?>
                                <tr>
                                    <td>#<?= (int) $ped['id'] ?></td>
                                    <td>
                                        <strong><?= e($ped['usuario_nome']) ?></strong><br>
                                        <small style="color:var(--muted);"><?= e($ped['usuario_email']) ?></small>
                                        <?php if (!empty($ped['rua'])): ?>
                                            <br><small style="color:var(--accent-2);">📍 <?= e($ped['rua']) ?>, nº <?= e($ped['numero']) ?> — <?= e($ped['cidade']) ?>/<?= e($ped['estado']) ?> (CEP: <?= e($ped['cep']) ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($ped['produto_nome']) ?></td>
                                    <td><?= (int) $ped['quantidade'] ?>x</td>
                                    <td><strong><?= e(money($ped['preco'] * $ped['quantidade'])) ?></strong></td>
                                    <td>
                                        <?php
                                            $stClass = 'status-pago';
                                            if ($ped['status'] === 'Entregue') $stClass = 'status-entregue';
                                            elseif ($ped['status'] === 'Não recebido') $stClass = 'status-nao-recebido';
                                            elseif ($ped['status'] === 'Reembolsado') $stClass = 'status-reembolsado';
                                        ?>
                                        <span class="badge-status <?= $stClass ?>"><?= e($ped['status']) ?></span>
                                    </td>
                                    <td><small><?= e($ped['criado_em'] ? date('d/m/Y H:i', strtotime($ped['criado_em'])) : '-') ?></small></td>
                                    <td>
                                        <div class="admin-actions-cell">
                                            <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditOrderModal(<?= json_encode($ped) ?>)'>Alterar Status</button>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteOrderModal(<?= (int) $ped['id'] ?>)">Excluir</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 4: CATEGORIAS ==================== -->
    <div id="tab-categorias" class="admin-tab-content">
        <div class="panel">
            <div class="panel-head-flex">
                <h2>Categorias do Catálogo</h2>
                <button type="button" class="btn btn-sm" onclick="document.getElementById('modalAddCategory').classList.remove('hidden')">➕ Nova Categoria</button>
            </div>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome da Categoria</th>
                            <th>Descrição</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td>#<?= (int) $cat['id'] ?></td>
                                <td><strong><?= e($cat['nome']) ?></strong></td>
                                <td><?= e($cat['desc'] ?? $cat['descricao']) ?></td>
                                <td>
                                    <div class="admin-actions-cell">
                                        <button type="button" class="btn btn-sm btn-status-entregue" onclick='openEditCategoryModal(<?= json_encode($cat) ?>)'>Editar</button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteCategoryModal(<?= (int) $cat['id'] ?>, '<?= e(addslashes($cat['nome'])) ?>')">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="tab-avaliacoes" class="admin-tab-content">
        <div class="panel">
            <h2>Avaliações, Curtidas e Denúncias</h2>
            <p class="sub">Acompanhe a participação dos clientes e o estado de cada avaliação.</p>
            <div class="admin-table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr><th>ID</th><th>Produto</th><th>Cliente</th><th>Nota</th><th>Comentário / Denúncia</th><th>Status</th><th>Curtidas</th><th>Denúncias</th><th>Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avaliacoesProdutos as $aval): ?>
                            <tr>
                                <td>#<?= (int) $aval['id'] ?></td>
                                <td><?= e($aval['produto_nome'] ?? 'Produto') ?></td>
                                <td><?= e($aval['usuario_nome']) ?></td>
                                <td><?= str_repeat('⭐', (int) $aval['nota']) ?></td>
                                <td style="max-width:260px;"><?= e($aval['comentario']) ?><?php if (!empty($aval['denuncias_info'])): ?><br><small style="color:#fca5a5;">Denúncia: <?= e($aval['denuncias_info']) ?></small><?php endif; ?></td>
                                <td><?= e($aval['status']) ?></td>
                                <td><?= (int) $aval['likes'] ?></td>
                                <td><?= (int) $aval['denuncias'] ?></td>
                                <td>
                                    <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php" style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="avaliacao_id" value="<?= (int) $aval['id'] ?>">
                                        <input type="hidden" name="status_moderacao" value="Aprovado">
                                        <input type="password" name="senha_master" required placeholder="Chave mestre" style="width:110px;">
                                        <button type="submit" name="acao" value="moderar_avaliacao_produto" class="btn btn-sm">Aprovar</button>
                                        <button type="submit" name="acao" value="excluir_avaliacao" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este comentário e suas interações?');">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ==================== TAB 6: NOVO PRODUTO ==================== -->
    <div id="tab-novo-produto" class="admin-tab-content">
        <div class="panel" style="max-width: 820px; margin: 0 auto;">
            <h2>Cadastrar Novo Produto</h2>
            <p class="sub">Adicione um novo componente ao catálogo com persistência.</p>

            <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php" class="form-grid" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="acao" value="adicionar_produto">

                <div class="field">
                    <label for="nome">Nome do produto</label>
                    <input id="nome" name="nome" type="text" placeholder="Ex: AMD Ryzen 7 7800X3D" required>
                </div>

                <div class="field">
                    <label for="categoria">Categoria</label>
                    <input id="categoria" name="categoria" list="cat-list" type="text" placeholder="Selecione ou digite nova" required>
                    <datalist id="cat-list">
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= e($cat['nome']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="field">
                    <label for="preco">Preço (R$)</label>
                    <input id="preco" name="preco" type="number" min="0.01" step="0.01" placeholder="Ex: 1999.90" required>
                </div>

                <div class="field">
                    <label for="imagem_file">Upload de imagem ou nome do arquivo</label>
                    <input id="imagem_file" name="imagem_file" type="file" accept="image/*" style="margin-bottom:6px;">
                    <input id="imagem" name="imagem" type="text" value="default.png" placeholder="Ex: cpu-ryzen.png ou default.png" required>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label for="descricao">Descrição detalhada</label>
                    <textarea id="descricao" name="descricao" rows="3" placeholder="Informações técnicas e destaques do componente..." required></textarea>
                </div>

                <div class="field" style="grid-column: 1 / -1;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="destaque" value="1" style="width:auto;"> Exibir como produto em destaque na Home
                    </label>
                </div>

                <!-- Campo de Senha Master de Segurança -->
                <div class="field-master-security" style="grid-column: 1 / -1;">
                    <label for="senha_master_add">Senha de confirmação do Administrador</label>
                    <div class="password-wrapper">
                        <input type="password" id="senha_master_add" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre de 8 dígitos (padrão: master88)">
                        <button type="button" class="toggle-password" data-target="senha_master_add" aria-label="Mostrar/esconder senha">👁️</button>
                    </div>
                    <small class="checkout-form-hint">Necessária para confirmar qualquer alteração crítica no sistema.</small>
                </div>

                <div class="panel-actions" style="grid-column: 1 / -1;">
                    <button type="submit" class="btn">Cadastrar Produto no Catálogo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: EDITAR PRODUTO ==================== -->
<div id="modalEditProduct" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card admin-modal-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalEditProduct')">×</button>
        <h3>✏️ Editar Produto</h3>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="editar_produto">
            <input type="hidden" name="produto_id" id="edit_prod_id">

            <div class="field" style="grid-column: 1 / -1;">
                <label for="edit_prod_nome">Nome do produto</label>
                <input id="edit_prod_nome" name="nome" type="text" required>
            </div>
            <div class="field">
                <label for="edit_prod_cat">Categoria</label>
                <input id="edit_prod_cat" name="categoria" list="cat-list" type="text" required>
            </div>
            <div class="field">
                <label for="edit_prod_preco">Preço (R$)</label>
                <input id="edit_prod_preco" name="preco" type="number" min="0.01" step="0.01" required>
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label for="edit_prod_img">Imagem do Produto (Upload ou Nome do arquivo)</label>
                <input id="edit_prod_file" name="imagem_file" type="file" accept="image/*" style="margin-bottom:6px;">
                <input id="edit_prod_img" name="imagem" type="text" required>
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label for="edit_prod_desc">Descrição</label>
                <textarea id="edit_prod_desc" name="descricao" rows="3" required></textarea>
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="destaque" id="edit_prod_destaque" value="1" style="width:auto;"> Destaque na Home
                </label>
            </div>
            <div class="field-master-security" style="grid-column: 1 / -1;">
                <label for="edit_prod_master">🔒 Senha de confirmação do Administrador</label>
                <input type="password" id="edit_prod_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre (master88)">
            </div>
            <div class="panel-actions" style="grid-column: 1 / -1; display:flex; gap:10px;">
                <button type="submit" class="btn">Salvar Alterações</button>
                <button type="button" class="btn secondary" onclick="closeModal('modalEditProduct')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EXCLUIR PRODUTO ==================== -->
<div id="modalDeleteProduct" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalDeleteProduct')">×</button>
        <h3 style="color:#ff6b6b;">Excluir Produto</h3>
        <p>Tem certeza que deseja excluir o produto <strong id="del_prod_name"></strong> do catálogo?</p>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="excluir_produto">
            <input type="hidden" name="produto_id" id="del_prod_id">

            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="del_prod_master">Senha de confirmação do Administrador</label>
                <input type="password" id="del_prod_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre (master88)">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalDeleteProduct')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir Produto</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: NOVO USUÁRIO ==================== -->
<div id="modalAddUser" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card admin-modal-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalAddUser')">×</button>
        <h3>➕ Cadastrar Novo Usuário</h3>
        <p class="sub" style="margin-bottom:14px; font-size:0.88rem; color:var(--muted);">Crie uma nova conta no sistema definindo se será Cliente ou Administrador.</p>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="adicionar_usuario">

            <div class="field">
                <label for="add_user_nome">Nome Completo</label>
                <input id="add_user_nome" name="nome" type="text" placeholder="Ex: Lucas Ferreira" required>
            </div>
            <div class="field">
                <label for="add_user_email">E-mail</label>
                <input id="add_user_email" name="email" type="email" placeholder="lucas@exemplo.com" required>
            </div>
            <div class="field">
                <label for="add_user_nasc">Data de Nascimento</label>
                <input id="add_user_nasc" name="nascimento" type="date" required>
                <small class="checkout-form-hint">Mínimo 16 anos (em relação à data atual)</small>
            </div>
            <div class="field field-password">
                <label for="add_user_senha">Senha (8 caracteres)</label>
                <input id="add_user_senha" name="senha" type="password" minlength="8" maxlength="8" placeholder="Exatamente 8 caracteres" required>
                <small class="checkout-form-hint">Mínimo e máximo de 8 caracteres</small>
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
                <label for="add_user_master">🔒 Senha de confirmação do Administrador (Chave Mestre)</label>
                <input type="password" id="add_user_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre (master88)">
                <small class="checkout-form-hint">Necessária para autorizar o cadastro de novas contas.</small>
            </div>
            <div class="panel-actions" style="grid-column: 1 / -1; display:flex; gap:10px;">
                <button type="submit" class="btn">Cadastrar Usuário</button>
                <button type="button" class="btn secondary" onclick="closeModal('modalAddUser')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EDITAR USUÁRIO ==================== -->
<div id="modalEditUser" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card admin-modal-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalEditUser')">×</button>
        <h3>✏️ Editar Usuário</h3>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="editar_usuario">
            <input type="hidden" name="usuario_id" id="edit_user_id">

            <div class="field">
                <label for="edit_user_nome">Nome</label>
                <input id="edit_user_nome" name="nome" type="text" required>
            </div>
            <div class="field">
                <label for="edit_user_email">E-mail</label>
                <input id="edit_user_email" name="email" type="email" required>
            </div>
            <div class="field">
                <label for="edit_user_nasc">Data de Nascimento</label>
                <input id="edit_user_nasc" name="nascimento" type="date">
            </div>
            <div class="field">
                <label for="edit_user_senha">Redefinir Senha (opcional)</label>
                <input id="edit_user_senha" name="senha_nova" type="password" minlength="8" maxlength="8" placeholder="8 caracteres (ou deixe em branco)">
            </div>
            <div class="field" style="grid-column: 1 / -1;">
                <label for="edit_user_tipo">Perfil de Acesso / Cargo do Usuário</label>
                <select id="edit_user_tipo" name="tipo" required style="background: rgba(16, 3, 5, 0.45); border: 1px solid var(--border); color: var(--text); border-radius: 10px; padding: 12px 14px; width: 100%;">
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
                <label for="edit_user_master">Senha de confirmação do Administrador</label>
                <input type="password" id="edit_user_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre (master88)">
            </div>
            <div class="panel-actions" style="grid-column: 1 / -1; display:flex; gap:10px;">
                <button type="submit" class="btn">Salvar Usuário</button>
                <button type="button" class="btn secondary" onclick="closeModal('modalEditUser')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EXCLUIR USUÁRIO ==================== -->
<div id="modalDeleteUser" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalDeleteUser')">×</button>
        <h3 style="color:#ff6b6b;">Excluir Usuário</h3>
        <p>Tem certeza que deseja excluir o usuário <strong id="del_user_name"></strong> e todos os seus pedidos vinculados?</p>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="excluir_usuario">
            <input type="hidden" name="usuario_id" id="del_user_id">

            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="del_user_master">Senha de confirmação do Administrador</label>
                <input type="password" id="del_user_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre (master88)">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalDeleteUser')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir Conta e Pedidos</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: ALTERAR STATUS PEDIDO ==================== -->
<div id="modalEditOrder" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalEditOrder')">×</button>
        <h3>Alterar Status do Pedido</h3>
        <p id="edit_order_info" style="color:var(--muted); margin-bottom:14px;"></p>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="atualizar_pedido">
            <input type="hidden" name="pedido_id" id="edit_ped_id">

            <div class="field" style="margin-bottom: 14px;">
                <label for="edit_ped_status">Novo Status</label>
                <select id="edit_ped_status" name="status" required>
                    <option value="Pago">Pago</option>
                    <option value="Separando">Separando</option>
                    <option value="Em transporte">Em transporte</option>
                    <option value="Entregue">Entregue</option>
                    <option value="Não recebido">Não recebido</option>
                    <option value="Reembolsado">Reembolsado</option>
                    <option value="Cancelado">Cancelado</option>
                </select>
            </div>

            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="edit_ped_master">🔒 Senha de confirmação do Administrador</label>
                <input type="password" id="edit_ped_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre (master88)">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalEditOrder')">Cancelar</button>
                <button type="submit" class="btn">Atualizar Status</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EXCLUIR PEDIDO ==================== -->
<div id="modalDeleteOrder" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalDeleteOrder')">×</button>
        <h3 style="color:#ff6b6b;">🗑️ Excluir Pedido</h3>
        <p>Tem certeza que deseja excluir o registro deste pedido do banco de dados?</p>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="excluir_pedido">
            <input type="hidden" name="pedido_id" id="del_ped_id">

            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="del_ped_master">🔒 Senha de confirmação do Administrador</label>
                <input type="password" id="del_ped_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre (master88)">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalDeleteOrder')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir Pedido</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: NOVA CATEGORIA ==================== -->
<div id="modalAddCategory" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalAddCategory')">×</button>
        <h3>Nova Categoria</h3>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="adicionar_categoria">

            <div class="field" style="margin-bottom: 12px;">
                <label for="add_cat_nome">Nome da Categoria</label>
                <input id="add_cat_nome" name="nome" type="text" placeholder="Ex: Monitores" required>
            </div>
            <div class="field" style="margin-bottom: 14px;">
                <label for="add_cat_desc">Descrição</label>
                <textarea id="add_cat_desc" name="descricao" rows="2" placeholder="Descrição para os cards da home..."></textarea>
            </div>
            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="add_cat_master">Senha de confirmação do Administrador</label>
                <input type="password" id="add_cat_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalAddCategory')">Cancelar</button>
                <button type="submit" class="btn">Cadastrar Categoria</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EDITAR CATEGORIA ==================== -->
<div id="modalEditCategory" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalEditCategory')">×</button>
        <h3>✏️ Editar Categoria</h3>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="editar_categoria">
            <input type="hidden" name="categoria_id" id="edit_cat_id">

            <div class="field" style="margin-bottom: 12px;">
                <label for="edit_cat_nome">Nome da Categoria</label>
                <input id="edit_cat_nome" name="nome" type="text" required>
            </div>
            <div class="field" style="margin-bottom: 14px;">
                <label for="edit_cat_desc">Descrição</label>
                <textarea id="edit_cat_desc" name="descricao" rows="2"></textarea>
            </div>
            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="edit_cat_master">🔒 Senha de confirmação do Administrador</label>
                <input type="password" id="edit_cat_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalEditCategory')">Cancelar</button>
                <button type="submit" class="btn">Salvar Categoria</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL: EXCLUIR CATEGORIA ==================== -->
<div id="modalDeleteCategory" class="checkout-message hidden" role="dialog" aria-modal="true">
    <div class="checkout-message-card">
        <button type="button" class="checkout-close" onclick="closeModal('modalDeleteCategory')">×</button>
        <h3 style="color:#ff6b6b;">🗑️ Excluir Categoria</h3>
        <p>Tem certeza que deseja excluir a categoria <strong id="del_cat_name"></strong> do banco de dados?</p>
        <form method="post" action="<?= e($base) ?>/pages/admin-produtos.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="excluir_categoria">
            <input type="hidden" name="categoria_id" id="del_cat_id">

            <div class="field-master-security" style="margin-bottom: 16px;">
                <label for="del_cat_master">🔒 Senha de confirmação do Administrador</label>
                <input type="password" id="del_cat_master" name="senha_master" required minlength="8" maxlength="8" placeholder="Digite a chave mestre">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn secondary" onclick="closeModal('modalDeleteCategory')">Cancelar</button>
                <button type="submit" class="btn btn-danger">Excluir Categoria</button>
            </div>
        </form>
    </div>
</div>

<script>
function openTab(tabId) {
    document.querySelectorAll('.admin-tab-btn').forEach(function(b) {
        b.classList.toggle('active', b.dataset.tab === tabId);
    });
    document.querySelectorAll('.admin-tab-content').forEach(function(c) {
        c.classList.toggle('active', c.id === 'tab-' + tabId);
    });
}

document.querySelectorAll('.admin-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        openTab(btn.dataset.tab);
    });
});

function closeModal(modalId) {
    var m = document.getElementById(modalId);
    if (m) m.classList.add('hidden');
}

function openEditProductModal(p) {
    document.getElementById('edit_prod_id').value = p.id;
    document.getElementById('edit_prod_nome').value = p.nome;
    document.getElementById('edit_prod_cat').value = p.categoria;
    document.getElementById('edit_prod_preco').value = p.preco;
    document.getElementById('edit_prod_img').value = p.imagem;
    document.getElementById('edit_prod_desc').value = p.descricao;
    document.getElementById('edit_prod_destaque').checked = !!p.destaque;
    document.getElementById('edit_prod_master').value = '';
    document.getElementById('modalEditProduct').classList.remove('hidden');
}

function openDeleteProductModal(id, name) {
    document.getElementById('del_prod_id').value = id;
    document.getElementById('del_prod_name').textContent = name;
    document.getElementById('del_prod_master').value = '';
    document.getElementById('modalDeleteProduct').classList.remove('hidden');
}

function openEditUserModal(u) {
    document.getElementById('edit_user_id').value = u.id;
    document.getElementById('edit_user_nome').value = u.nome;
    document.getElementById('edit_user_email').value = u.email;
    document.getElementById('edit_user_nasc').value = u.nascimento || '';
    if (document.getElementById('edit_user_tipo')) {
        document.getElementById('edit_user_tipo').value = u.tipo || (u.is_admin ? 'admin' : 'customer');
    }
    document.getElementById('edit_user_senha').value = '';
    document.getElementById('edit_user_master').value = '';
    document.getElementById('modalEditUser').classList.remove('hidden');
}

function openDeleteUserModal(id, name) {
    document.getElementById('del_user_id').value = id;
    document.getElementById('del_user_name').textContent = name;
    document.getElementById('del_user_master').value = '';
    document.getElementById('modalDeleteUser').classList.remove('hidden');
}

function openEditOrderModal(ped) {
    document.getElementById('edit_ped_id').value = ped.id;
    document.getElementById('edit_order_info').textContent = 'Pedido #' + ped.id + ' - ' + ped.usuario_nome + ' (' + ped.produto_nome + ')';
    document.getElementById('edit_ped_status').value = ped.status;
    document.getElementById('edit_ped_master').value = '';
    document.getElementById('modalEditOrder').classList.remove('hidden');
}

function openDeleteOrderModal(id) {
    document.getElementById('del_ped_id').value = id;
    document.getElementById('del_ped_master').value = '';
    document.getElementById('modalDeleteOrder').classList.remove('hidden');
}

function openAddUserModal() {
    var nasc = document.getElementById('add_user_nasc');
    if (nasc) {
        var now = new Date();
        var maxYear = now.getFullYear() - 16;
        var m = String(now.getMonth() + 1).padStart(2, '0');
        var d = String(now.getDate()).padStart(2, '0');
        nasc.setAttribute('max', maxYear + '-' + m + '-' + d);
    }
    document.getElementById('add_user_nome').value = '';
    document.getElementById('add_user_email').value = '';
    document.getElementById('add_user_senha').value = '';
    document.getElementById('add_user_tipo').value = 'customer';
    document.getElementById('add_user_master').value = '';
    document.getElementById('modalAddUser').classList.remove('hidden');
}

function openEditCategoryModal(cat) {
    document.getElementById('edit_cat_id').value = cat.id;
    document.getElementById('edit_cat_nome').value = cat.nome;
    document.getElementById('edit_cat_desc').value = cat.desc || cat.descricao || '';
    document.getElementById('edit_cat_master').value = '';
    document.getElementById('modalEditCategory').classList.remove('hidden');
}

function openDeleteCategoryModal(id, name) {
    document.getElementById('del_cat_id').value = id;
    document.getElementById('del_cat_name').textContent = name;
    document.getElementById('del_cat_master').value = '';
    document.getElementById('modalDeleteCategory').classList.remove('hidden');
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

