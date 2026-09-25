<?php
// Script PHP da página visual do Carrinho de Compras e Checkout em etapas

// Carrega as configurações globais de sessão e URL base
require_once __DIR__ . '/../includes/config.php';

// Define o título da aba do navegador
$page_title = 'Carrinho';

// Inclui o cabeçalho do site
require __DIR__ . '/../includes/header.php';
?>

<!-- Seção principal do carrinho de compras -->
<section class="cart-page">
    <!-- Indicador de progresso do checkout em etapas -->
    <div class="checkout-steps" aria-label="Etapas do checkout">
        <span class="step active">Carrinho</span>
        <span class="step">Endereço</span>
        <span class="step">Revisão</span>
        <span class="step">Concluir</span>
    </div>

    <!-- Layout principal do carrinho (Painel de itens à esquerda e Resumo à direita) -->
    <div class="cart-layout">
        <!-- Painel com a lista de itens selecionados -->
        <div class="cart-panel">
            <div class="cart-panel-head">
                <h2>Produto e serviço</h2>
                <!-- Botão para Esvaziar todo o carrinho de compras -->
                <button type="button" class="btn-remove-all" data-cart-clear>Remover todos</button>
            </div>
            <!-- Container onde o JavaScript cart.js renderiza os itens dinamicamente -->
            <div id="cart-items" class="cart-items" aria-live="polite"></div>
        </div>

        <!-- Painel lateral com o resumo financeiro do pedido -->
        <aside class="summary-panel" aria-label="Resumo do pedido">
            <h3>Resumo do pedido</h3>
            <div class="summary-row">
                <!-- Rótulo atualizado dinamicamente pelo JS com a quantidade de itens -->
                <span id="summary-label">Valor dos produtos (0):</span>
                <!-- Subtotal dos produtos -->
                <strong id="cart-subtotal">R$ 0,00</strong>
            </div>
            <div class="summary-row">
                <span>Frete:</span>
                <!-- Valor ou status do frete -->
                <strong id="cart-shipping">À calcular</strong>
            </div>
            <div class="summary-total">
                <span>Total:</span>
                <!-- Valor total a ser pago -->
                <span id="cart-total">R$ 0,00</span>
            </div>
            <!-- Nota informativa com o valor do desconto Pix ou parcelamento -->
            <p class="summary-note" id="summary-note">À vista no Pix: R$ 0,00</p>

            <!-- Botão principal de avanço na etapa do checkout (Continuar / Finalizar compra) -->
            <button type="button" class="btn-primary" data-cart-checkout>Continuar</button>

            <!-- Botão secundário de retorno de etapa -->
            <button type="button" class="btn secondary" data-cart-back>Voltar</button>
        </aside>
    </div>
</section>

<!-- Modal de mensagens de alerta e validação do checkout -->
<div id="checkout-message" class="checkout-message hidden" role="dialog" aria-modal="true" aria-live="assertive">
    <div class="checkout-message-card">
        <!-- Botão 'x' para fechar a mensagem -->
        <button type="button" class="checkout-close" data-close-message aria-label="Fechar mensagem">×</button>
        <!-- Título da mensagem -->
        <h3 id="checkout-message-title">Atenção</h3>
        <!-- Texto explicativo da mensagem -->
        <p id="checkout-message-text">O carrinho está vazio.</p>
        <!-- Botão OK de confirmação -->
        <button type="button" class="btn-primary btn-small" data-close-message>OK</button>
    </div>
</div>

<!-- Inclui o rodapé do site -->
<?php require __DIR__ . '/../includes/footer.php'; ?>
