<?php
require_once __DIR__ . '/../includes/config.php';
// Inclui as funções de autenticação e verificação de permissões de administrador
require_once __DIR__ . '/../includes/data.php';
// Inclui as funções de manipulação de dados dos produtos e categorias

no_cache();
// Impede que o navegador armazene em cache a resposta da requisição, garantindo que os dados sejam sempre atualizados
require_admin();
// Verifica se o usuário atual possui privilégios de administrador; caso contrário, redireciona para a página de login

$base = base_url();
// Obtém a URL base do projeto para construir links e redirecionamentos corretamente

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    //pega o método da requisição HTTP e verifica se não é do tipo POST; caso seja diferente, redireciona para a página de administração de produtos
    header('Location: ' . $base . '/pages/admin-produtos.php');
    // Se a requisição não for do tipo POST, redireciona para a página de administração de produtos
    exit();
    // Encerra a execução do script após o redirecionamento
}

if (!csrf_check($_POST['csrf'] ?? null)) {
    // Verifica se o token CSRF enviado no formulário é válido; caso contrário, redireciona com uma mensagem de erro
    header('Location: ' . $base . '/pages/admin-produtos.php?msg=csrf');
    // Redireciona para a página de administração de produtos com uma mensagem indicando que a sessão expirou ou que houve uma tentativa de ataque CSRF
    exit();
}

$resultado = adicionar_produto([
    //  Chama a função para adicionar um novo produto ao banco de dados, passando os dados do formulário e o arquivo de imagem enviado
    'nome' => $_POST['nome'] ?? '', 
    //pega o valor do campo 'nome' do formulário, ou uma string vazia se não estiver definido
    'categoria' => $_POST['categoria'] ?? '',
    //pega o valor do campo 'categoria' do formulário, ou uma string vazia se não estiver definido
    'preco' => $_POST['preco'] ?? 0, 
    //pega o valor do campo 'preco' do formulário, ou 0 se não estiver definido
    'descricao' => $_POST['descricao'] ?? '', 
    //pega o valor do campo 'descricao' do formulário, ou uma string vazia se não estiver definido
    'imagem' => $_POST['imagem'] ?? 'default.png',
    // pega o valor do campo 'imagem' do formulário, ou 'default.png' se não estiver definido
    'destaque' => !empty($_POST['destaque']),
    // mostra se o produto deve ser marcado como destaque com base na presença do campo 'destaque' no formulário
], $_FILES['imagem_file'] ?? null);
//manda o arquivo de imagem enviado no formulário, ou null se não houver arquivo enviado

header('Location: ' . $base . '/pages/admin-produtos.php?msg=' . urlencode($resultado['mensagem']));
// Redireciona para a página de administração de produtos com uma mensagem indicando o resultado da operação com sucesso ou falha
exit();
