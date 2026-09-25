<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pedidos.php';

no_cache();
require_login();

$base = base_url();
$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0 && !empty($user['email'])) {
    $dbUser = find_user($user['email']);
    if ($dbUser && !empty($dbUser['id'])) {
        $userId = (int) $dbUser['id'];
        $_SESSION['user']['id'] = $userId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        set_flash('error', 'Sessão expirada. Tente novamente.');
        header('Location: ' . $base . '/pages/dashboard.php#meus-pedidos-title');
        exit();
    }

    $acao = $_POST['acao'] ?? '';
    $pedidoId = (int) ($_POST['pedido_id'] ?? 0);

    if ($pedidoId > 0 && $userId > 0) {
        switch ($acao) {
            case 'entregue':
                atualizar_status_pedido($userId, $pedidoId, 'Entregue');
                set_flash('success', '✓ Entrega confirmada! O status da sua encomenda foi atualizado para "Entregue".');
                break;

            case 'nao_recebi':
                atualizar_status_pedido($userId, $pedidoId, 'Não recebido');
                set_flash('error', 'Aviso registrado! Seu pedido foi marcado como "Não recebido". Nossa equipe de suporte foi notificada.');
                break;

            case 'reembolsar':
            case 'desfazer':
                atualizar_status_pedido($userId, $pedidoId, 'Reembolsado');
                set_flash('success', ' Reembolso feito com sucesso! A compra foi desfeita e o valor total estornado.');
                break;

            case 'remover':
                remover_pedido_usuario($userId, $pedidoId);
                set_flash('success', 'Item removido do seu histórico de compras.');
                break;
        }
    }
}

header('Location: ' . $base . '/pages/dashboard.php#meus-pedidos-title');
exit();

