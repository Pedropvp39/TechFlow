<?php
require_once __DIR__ . '/../includes/config.php';

$page_title = 'Ajuda e informações';
$base = base_url();
require __DIR__ . '/../includes/header.php';
?>

<section class="section help-page" aria-labelledby="help-title">
    <div class="section-head">
        <div>
            <h1 id="help-title">Ajuda e informações</h1>
            <p>Respostas rápidas para suas compras na TechFlow.</p>
        </div>
    </div>

    <nav class="help-nav" aria-label="Navegação de ajuda">
        <a href="#trocas">Trocas</a>
        <a href="#termos">Termos</a>
        <a href="#privacidade">Privacidade</a>
        <a href="#faq">Dúvidas frequentes</a>
    </nav>

    <div class="help-sections">
        <article class="help-section" id="trocas">
            <h2>Política de trocas e reembolso</h2>
            <p>Você pode solicitar troca ou devolução em até 7 dias após o recebimento. O item deve estar completo, sem sinais de uso e com a nota fiscal.</p>
            <p>Para iniciar, fale com nosso atendimento e informe o número do pedido.</p>
        </article>

        <article class="help-section" id="termos">
            <h2>Termos e condições de uso</h2>
            <p>Os preços e a disponibilidade podem mudar sem aviso. A compra é confirmada após a aprovação do pagamento e os pedidos seguem para separação conforme o estoque.</p>
        </article>

        <article class="help-section" id="privacidade">
            <h2>Política de privacidade</h2>
            <p>Usamos seus dados apenas para processar pedidos, entregar compras e oferecer suporte. Seus dados não são vendidos a terceiros.</p>
        </article>

        <article class="help-section" id="faq">
            <h2>Dúvidas frequentes</h2>
            <details>
                <summary>Como acompanho meu pedido?</summary>
                <p>Acesse sua conta e consulte a área de pedidos para ver o status da compra.</p>
            </details>
            <details>
                <summary>Quais formas de pagamento são aceitas?</summary>
                <p>Aceitamos Pix, cartão de crédito e boleto bancário.</p>
            </details>
            <details>
                <summary>Como falo com o atendimento?</summary>
                <p>Envie um e-mail para suporte@techflow.com ou utilize o telefone exibido no rodapé.</p>
            </details>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
