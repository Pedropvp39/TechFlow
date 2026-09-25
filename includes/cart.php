<?php
/**
 * Carrinho de compras (armazenado na sessão).
 * Estrutura: $_SESSION['cart'] = [ produtoId => quantidade ].
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';

const CART_MAX_QTY = 20;

function cart_raw(): array
{
    if (!isset($_SESSION['cart_synced_from_db']) && !empty($_SESSION['user']['id'])) {
        db_cart_sync_from_db((int) $_SESSION['user']['id']);
        $_SESSION['cart_synced_from_db'] = true;
    }
    return $_SESSION['cart'] ?? [];
}

/**
 * Adiciona (ou incrementa) um produto no carrinho.
 */
function cart_add(int $id, int $qty = 1): void
{
    if (!get_produto($id) || $qty < 1) {
        return;
    }
    $cart = cart_raw();
    $novo = ($cart[$id] ?? 0) + $qty;
    $cart[$id] = max(1, min(CART_MAX_QTY, $novo));
    $_SESSION['cart'] = $cart;

    if (!empty($_SESSION['user']['id'])) {
        db_cart_sync_to_db((int) $_SESSION['user']['id'], $cart);
    }
}

/**
 * Define a quantidade exata de um item (0 remove).
 */
function cart_set(int $id, int $qty): void
{
    $cart = cart_raw();
    if ($qty <= 0) {
        unset($cart[$id]);
    } elseif (get_produto($id)) {
        $cart[$id] = min(CART_MAX_QTY, $qty);
    }
    $_SESSION['cart'] = $cart;

    if (!empty($_SESSION['user']['id'])) {
        db_cart_sync_to_db((int) $_SESSION['user']['id'], $cart);
    }
}

function cart_remove(int $id): void
{
    $cart = cart_raw();
    unset($cart[$id]);
    $_SESSION['cart'] = $cart;

    if (!empty($_SESSION['user']['id'])) {
        db_cart_sync_to_db((int) $_SESSION['user']['id'], $cart);
    }
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
    if (!empty($_SESSION['user']['id'])) {
        db_cart_sync_to_db((int) $_SESSION['user']['id'], []);
    }
}

/**
 * Itens do carrinho com dados do produto e subtotal.
 */
function cart_items(): array
{
    $items = [];
    foreach (cart_raw() as $id => $qty) {
        $produto = get_produto((int) $id);
        if (!$produto) {
            continue;
        }
        $produto['qty'] = (int) $qty;
        $produto['subtotal'] = $produto['preco'] * (int) $qty;
        $items[] = $produto;
    }
    return $items;
}

/**
 * Quantidade total de itens (soma das quantidades).
 */
function cart_count(): int
{
    return array_sum(cart_raw());
}

function cart_total(): float
{
    $total = 0.0;
    foreach (cart_items() as $item) {
        $total += $item['subtotal'];
    }
    return $total;
}


function db_get_active_cart_id(int $userId): int
{
    if ($userId <= 0) return 0;
    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ? AND status = 'ativo' ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return (int) $row['id'];
        }

        $stmtIns = $db->prepare("INSERT INTO carts (user_id, status) VALUES (?, 'ativo')");
        $stmtIns->bind_param('i', $userId);
        $stmtIns->execute();
        return (int) $stmtIns->insert_id;
    } catch (Throwable $e) {
        error_log('db_get_active_cart_id: ' . $e->getMessage());
        return 0;
    }
}

function db_cart_sync_from_db(int $userId): void
{
    if ($userId <= 0) return;
    $cartId = db_get_active_cart_id($userId);
    if ($cartId <= 0) return;

    try {
        $db = db_connect();
        $stmt = $db->prepare("SELECT product_id, quantity FROM cart_items WHERE cart_id = ?");
        $stmt->bind_param('i', $cartId);
        $stmt->execute();
        $res = $stmt->get_result();

        $cart = [];
        while ($row = $res->fetch_assoc()) {
            $pId = (int) $row['product_id'];
            $qty = (int) $row['quantity'];
            if ($pId > 0 && $qty > 0) {
                $cart[$pId] = $qty;
            }
        }

        if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $pId => $qty) {
                $pId = (int) $pId;
                $qty = (int) $qty;
                if ($pId > 0 && $qty > 0) {
                    $cart[$pId] = isset($cart[$pId]) ? max($cart[$pId], $qty) : $qty;
                }
            }
        }

        $_SESSION['cart'] = $cart;
        if (!empty($cart)) {
            db_cart_sync_to_db($userId, $cart);
        }
    } catch (Throwable $e) {
        error_log('db_cart_sync_from_db: ' . $e->getMessage());
    }
}

function db_cart_sync_to_db(int $userId, array $sessionCart): void
{
    if ($userId <= 0) return;
    $cartId = db_get_active_cart_id($userId);
    if ($cartId <= 0) return;

    try {
        $db = db_connect();
        $stmtDel = $db->prepare("DELETE FROM cart_items WHERE cart_id = ?");
        $stmtDel->bind_param('i', $cartId);
        $stmtDel->execute();

        $stmtIns = $db->prepare("INSERT INTO cart_items (cart_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($sessionCart as $pId => $qty) {
            $pId = (int) $pId;
            $qty = (int) $qty;
            if ($pId <= 0 || $qty <= 0) continue;
            $prod = get_produto($pId);
            $price = $prod ? (float) $prod['preco'] : 0.00;
            $stmtIns->bind_param('iiid', $cartId, $pId, $qty, $price);
            $stmtIns->execute();
        }
    } catch (Throwable $e) {
        error_log('db_cart_sync_to_db: ' . $e->getMessage());
    }
}

function db_cart_finalize(int $userId): void
{
    if ($userId <= 0) return;
    try {
        $db = db_connect();
        $stmt = $db->prepare("UPDATE carts SET status = 'finalizado' WHERE user_id = ? AND status = 'ativo'");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('db_cart_finalize: ' . $e->getMessage());
    }
}
