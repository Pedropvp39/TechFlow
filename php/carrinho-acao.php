<?php
// Script processador das ações do carrinho de compras podendo adicionar, alterar quantidade, remover item e limpar carrinho

// Carrega as configurações de sessão e segurança
require_once __DIR__ . '/../includes/config.php';

// Carrega as funções que manipulam o carrinho na sessão e no MySQL
require_once __DIR__ . '/../includes/cart.php';

// Obtém a URL base para redirecionamento
$base = base_url();
$redirectUrl = $base . '/pages/carrinho.php';
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$refererPath = parse_url($referer, PHP_URL_PATH);
if (is_string($refererPath) && str_starts_with($refererPath, $base . '/') && !str_ends_with($refererPath, '/php/carrinho-acao.php')) {
    $redirectUrl = $referer;
}

// Garante que o arquivo aceite apenas requisições via formulário POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit();
}

// Valida o token CSRF enviado no formulário contra ataques de falsificação de requisição
if (!csrf_check($_POST['csrf'] ?? null)) {
    header('Location: ' . $redirectUrl);
    exit();
}

// Identifica a ação solicitada que sao (add, remove, set, clear)
$acao = $_POST['acao'] ?? 'add';

// Converte e valida o ID do produto para inteiro
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

// Converte e valida a quantidade desejada (mínimo 1)
$qty = isset($_POST['qty']) ? max(1, (int) $_POST['qty']) : 1;

// Trata o comando de limpar todos os itens do carrinho
if ($acao === 'clear') {
    cart_clear();
    header('Location: ' . $redirectUrl);
    // Encerra a execução do script após o redirecionamento
    exit();
}

// Redireciona de volta caso o ID do produto seja inválido
if ($id <= 0) {
    header('Location: ' . $base . '/pages/carrinho.php');
    // Encerra a execução do script após o redirecionamento
    exit();
}

// Executa a ação solicitada no carrinho
if ($acao === 'remove') {
    cart_remove($id); // Remove o produto do carrinho
} elseif ($acao === 'set') {
    cart_set($id, $qty); // Define a quantidade exata
} else {
    cart_add($id, $qty); // Adiciona ou incrementa a quantidade do produto
}

// Redireciona o usuário para a página visual do carrinho de compras
header('Location: ' . $redirectUrl);
exit();
