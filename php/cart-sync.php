<?php
ob_start();
require_once __DIR__ . '/../includes/config.php';
//pega as configurações gerais do sistema, incluindo sessão, segurança e URL base
require_once __DIR__ . '/../includes/auth.php';
//pega as funções de autenticação e verificação de login do usuário
require_once __DIR__ . '/../includes/cart.php';
//pega as funções de manipulação do carrinho de compras na sessão e no banco de dados

no_cache();//nao armazena em cache a resposta da requisição, garantindo que os dados sejam sempre atualizados

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'mensagem' => 'Requisição inválida ou sessão expirada.']);
    exit();
}

//faz a sincronização do carrinho de compras entre a sessão e o banco de dados, garantindo que os itens adicionados ou removidos sejam refletidos corretamente em ambas as fontes de dados
if (ob_get_length()) ob_clean();
//limpa qualquer buffer de saída ativo antes de enviar o cabeçalho HTTP, evitando erros de cabeçalho já enviado
header('Content-Type: application/json; charset=utf-8');
//faz a definição do cabeçalho HTTP especificando que a resposta será enviada em formato JSON UTF-8
$user = current_user();
//pega as informações do usuário atualmente logado, se houver, incluindo ID e email
$userId = (int) ($user['id'] ?? 0);
//pega o ID do usuário logado, ou 0 se não houver usuário logado
 
  //faz a verificação se o usuário não está logado e se o email do usuário está disponível, caso contrário, tenta buscar o ID do usuário no banco de dados usando o email fornecido
if ($userId <= 0 && !empty($user['email'])) { 
    //se o usuário não estiver logado e o email estiver disponível
    $dbUser = find_user($user['email']); 
    //tenta buscar o ID do usuário no banco de dados usando o email fornecido
    if ($dbUser && !empty($dbUser['id'])) {
        //se o usuário for encontrado no banco de dados e o ID estiver disponível
        $userId = (int) $dbUser['id']; 
        //pega o ID do usuário encontrado no banco de dados
    }
}

$rawInput = file_get_contents('php://input');
//pega o conteúdo bruto da requisição HTTP, que pode ser enviado como JSON ou outro formato, e armazena em uma variável para posterior processamento
$jsonBody = json_decode($rawInput, true);
//pega o conteúdo bruto da requisição HTTP e decodifica como JSON, armazenando em uma variável para posterior processamento
//JSON e basicamente um formato de troca de dados leve e fácil de ler e escrever, usado para enviar informações entre cliente e servidor em aplicações web
$cartData = $_POST['cart'] ?? ($jsonBody['cart'] ?? null);
//pega os dados do carrinho de compras enviados via POST, GET ou JSON, e armazena em uma variável para posterior processamento
    
   //faz a verificação se os dados do carrinho de compras são válidos, convertendo para um array associativo e filtrando apenas os produtos existentes no banco de dados com quantidade positiva
if (is_string($cartData)) {
    //se os dados do carrinho de compras forem enviados como uma string JSON
    $cartMap = json_decode($cartData, true);
    //decodifica a string JSON em um array associativo para posterior processamento
} elseif (is_array($cartData)) { 
    //se os dados do carrinho de compras forem enviados como um array associativo
    $cartMap = $cartData;
    //usa o array associativo diretamente para posterior processamento
} else {
    // se os dados do carrinho de compras não forem válidos, inicializa um array vazio para evitar erros de processamento
    $cartMap = [];
    //filtra apenas os produtos existentes no banco de dados com quantidade positiva, garantindo que o carrinho de compras seja consistente e válido
}

$validCart = []; 
//inicializa um array vazio para armazenar os produtos válidos do carrinho de compras, que serão sincronizados com a sessão e o banco de dados
if (is_array($cartMap)) { 
    //se a variável $cartMap for um array associativo, percorre cada item do carrinho de compras e valida se o produto existe no banco de dados e se a quantidade é positiva, adicionando apenas os produtos válidos ao array $validCart
    foreach ($cartMap as $id => $qty) { //faz um loop em cada item do carrinho de compras, onde $id é o ID do produto e $qty é a quantidade desejada
        $pId = (int) $id; 
        //em seguida, converte o ID do produto para inteiro para garantir que seja um valor numérico válido
        $q = (int) $qty;  
        // enquanto a quantidade desejada é convertida para inteiro para garantir que seja um valor numérico válido
        if ($pId > 0 && $q > 0 && get_produto($pId)) {
        //se o ID do produto for maior que zero, a quantidade desejada for maior que zero e o produto existir no banco de dados, adiciona o produto ao array $validCart com a quantidade limitada ao valor máximo permitido com o (CART_MAX_QTY)
            $validCart[$pId] = min(CART_MAX_QTY, $q);
            //valida a quantidade desejada do produto, limitando-a ao valor máximo permitido com o (CART_MAX_QTY) para evitar que o usuário adicione uma quantidade excessiva de um produto ao carrinho de compras
        }
    }
}

$_SESSION['cart'] = $validCart;
//atualiza a sessão do usuário com os produtos válidos do carrinho de compras, garantindo que a sessão esteja sempre sincronizada com os dados enviados pelo cliente

if ($userId > 0) { 
//se o usuário estiver logado, sincroniza o carrinho de compras com o banco de dados, garantindo que os produtos adicionados ou removidos sejam refletidos corretamente em ambas as fontes de dados
    db_cart_sync_to_db($userId, $validCart);
    //faz a sincronização do carrinho de compras com o banco de dados, garantindo que os produtos adicionados ou removidos sejam refletidos corretamente em ambas as fontes de dados
}

if (ob_get_length()) ob_clean();
//faz a limpeza de qualquer buffer de saída ativo antes de enviar a resposta JSON, evitando erros de cabeçalho já enviado
echo json_encode(['ok' => true, 'count' => cart_count(), 'total' => cart_total(), 'cart' => $_SESSION['cart']]);
//faz a codificação dos dados do carrinho de compras em formato JSON e envia como resposta para o cliente, incluindo o status da operação, a contagem de itens no carrinho, o valor total do carrinho e os produtos válidos do carrinho armazenados na sessão
