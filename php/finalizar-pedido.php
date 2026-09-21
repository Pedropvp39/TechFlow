<?php
ob_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/pedidos.php';
require_once __DIR__ . '/../includes/data.php';

no_cache();
require_login();

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);

if (!csrf_check($_POST['csrf'] ?? $jsonBody['csrf'] ?? null)) {
    http_response_code(403);
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok' => false, 'mensagem' => 'Sessão expirada. Atualize a página e tente novamente.']);
    exit();
}

$cartData = $_POST['cart'] ?? ($jsonBody['cart'] ?? null);
$addressData = $_POST['address'] ?? ($jsonBody['address'] ?? null);

if (is_string($cartData)) {
    $cart = json_decode($cartData, true);
} elseif (is_array($cartData)) {
    $cart = $cartData;
} else {
    $cart = [];
}

if (is_string($addressData)) {
    $address = json_decode($addressData, true);
} elseif (is_array($addressData)) {
    $address = $addressData;
} else {
    $address = [];
}

if (!is_array($cart) || empty($cart)) {
    http_response_code(400);
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok' => false, 'mensagem' => 'Carrinho vazio. Adicione produtos antes de finalizar.']);
    exit();
}

$itens = [];
foreach ($cart as $id => $qty) {
    $produto = get_produto((int) $id);
    if (!$produto) {
        continue;
    }

    $itens[] = [
        'id' => (int) $produto['id'],
        'nome' => (string) $produto['nome'],
        'categoria' => (string) $produto['categoria'],
        'preco' => (float) $produto['preco'],
        'qty' => max(1, (int) $qty),
    ];
}

if (empty($itens)) {
    http_response_code(400);
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok' => false, 'mensagem' => 'Nenhum produto válido encontrado no carrinho.']);
    exit();
}

$user = current_user();
$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0 && !empty($user['email'])) {
    $dbUser = find_user($user['email']);
    if ($dbUser && !empty($dbUser['id'])) {
        $userId = (int) $dbUser['id'];
        $_SESSION['user']['id'] = $userId;
    }
}

if ($userId <= 0) {
    http_response_code(401);
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok' => false, 'mensagem' => 'Sessão de usuário não identificada. Por favor, refaça o login.']);
    exit();
}

$resultado = registrar_pedidos_usuario($userId, $itens, $address);
if (!$resultado['ok']) {
    http_response_code(500);
    if (ob_get_length()) ob_clean();
    echo json_encode(['ok' => false, 'mensagem' => $resultado['mensagem']]);
    exit();
}

$_SESSION['cart'] = [];
db_cart_finalize($userId);
set_flash('success', '🎉 Obrigado pela compra! Seu pedido foi finalizado com sucesso e já está disponível em "Minhas compras".');

if (ob_get_length()) ob_clean();
echo json_encode([
    'ok' => true,
    'mensagem' => 'Obrigado pela compra! Seu pedido foi concluído com sucesso.',
    'redirect' => base_url() . '/pages/dashboard.php#meus-pedidos-title'
]);

