<?php
require_once __DIR__ . '/../includes/cart.php';

$base = base_url();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$produto = get_produto($id);

if (!$produto) {
    http_response_code(404);
    $page_title = 'Produto não encontrado';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section"><h1>Produto não encontrado</h1>'
        . '<p class="empty-message">O produto que você procura não existe ou saiu de linha.</p>'
        . '<a class="btn-primary" href="' . e($base) . '/pages/produtos.php">Voltar aos produtos</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit();
}

$page_title = $produto['nome'];
$relacionados = get_relacionados($produto);
require __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb" aria-label="Trilha de navegação">
    <a href="<?= e($base) ?>/index.php">Início</a>
    <span aria-hidden="true">/</span>
    <a href="<?= e($base) ?>/pages/produtos.php">Produtos</a>
    <span aria-hidden="true">/</span>
    <a href="<?= e($base) ?>/pages/produtos.php?cat=<?= urlencode($produto['categoria']) ?>"><?= e($produto['categoria']) ?></a>
    <span aria-hidden="true">/</span>
    <span aria-current="page"><?= e($produto['nome']) ?></span>
</nav>

<section class="section product-detail" aria-labelledby="detail-title">
    <div class="detail-media">
        <img src="<?= e($base) ?>/assets/img/<?= e($produto['imagem']) ?>" alt="<?= e($produto['nome']) ?>">
    </div>
    <div class="detail-info">
        <span class="produto-cat"><?= e($produto['categoria']) ?></span>
        <h1 id="detail-title"><?= e($produto['nome']) ?></h1>
        <p class="detail-desc"><?= e($produto['descricao']) ?></p>
        <div class="detail-price"><?= e(money($produto['preco'])) ?></div>
        <p class="detail-installments">
            em até 12x de <?= e(money($produto['preco'] / 12)) ?> sem juros
        </p>

        <form class="detail-buy" method="post" action="<?= e($base) ?>/php/carrinho-acao.php" data-cart-form>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="acao" value="add">
            <input type="hidden" name="id" value="<?= $produto['id'] ?>">
            <div class="qty-field">
                <label for="qty">Quantidade</label>
                <input id="qty" type="number" name="qty" value="1" min="1" max="<?= CART_MAX_QTY ?>" inputmode="numeric">
            </div>
            <button type="submit" class="btn-primary btn-buy-lg">Adicionar ao carrinho</button>
        </form>

        <ul class="detail-perks">
            <li>Frete grátis para todo o Brasil</li>
            <li>Garantia de 12 meses</li>
            <li>Compra 100% segura</li>
        </ul>
    </div>
</section>

<?php if (count($relacionados) > 0): ?>
<section class="section" aria-labelledby="rel-title">
    <div class="section-head">
        <div>
            <h2 id="rel-title">Você também pode gostar</h2>
            <p>Sugestões para complementar sua build.</p>
        </div>
    </div>
    <div class="produtos">
        <?php foreach ($relacionados as $p): ?>
            <article class="produto">
                <a class="produto-media" href="<?= e($base) ?>/pages/produto.php?id=<?= $p['id'] ?>">
                    <img src="<?= e($base) ?>/assets/img/<?= e($p['imagem']) ?>" alt="<?= e($p['nome']) ?>" loading="lazy">
                </a>
                <div class="produto-body">
                    <span class="produto-cat"><?= e($p['categoria']) ?></span>
                    <h3><a href="<?= e($base) ?>/pages/produto.php?id=<?= $p['id'] ?>"><?= e($p['nome']) ?></a></h3>
                    <div class="produto-footer">
                        <span class="preco"><?= e(money($p['preco'])) ?></span>
                        <a class="btn-buy" href="<?= e($base) ?>/pages/produto.php?id=<?= $p['id'] ?>">Ver</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
