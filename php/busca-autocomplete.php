<?php
// Inicia o script PHP responsável pela busca em tempo real para a barra de pesquisa

// Inclui o arquivo de configurações gerais sessão, segurança, URL base
require_once __DIR__ . '/../includes/config.php';

// Inclui as funções de manipulação e filtragem de dados de produtos no MySQL
require_once __DIR__ . '/../includes/data.php';

// Desabilita o cache de navegação para garantir dados atualizados a cada digitação
no_cache();

// Limpa qualquer buffer de saída ativo antes de enviar o cabeçalho HTTP, evitando erros de cabeçalho já enviado
if (ob_get_length()) ob_clean();

// Define o cabeçalho HTTP especificando que a resposta será enviada em formato JSON UTF-8 
header('Content-Type: application/json; charset=utf-8');

// Obtém e higieniza a palavra-chave de busca enviada via GET na requisição
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

// Caso o termo digitado esteja vazio, retorna um JSON com array de produtos vazio
if ($q === '') {
    echo json_encode(['ok' => true, 'produtos' => []]);
    exit();
}

// Executa a busca no MySQL filtrando produtos por nome, descrição ou categoria
$produtos = get_produtos_filtrados(null, $q, null, null, null);

// Limita o resultado às primeiras 4 sugestões para manter o menu suspenso compacto e leve evitar sobrecarga de dados na resposta JSON
$sugestoes = array_slice($produtos, 0, 4);

// Retorna a resposta JSON estruturada contendo o status, o termo buscado, o total e os produtos encontrados
echo json_encode([
    'ok' => true,
    'q' => $q,
    'total' => count($produtos),
    'produtos' => $sugestoes
]);
