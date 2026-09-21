<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/data.php';

$page_title = 'Produtos';
$base = base_url();
$produtos = get_produtos();

// Filtro por categoria (via querystring)
$categoriasUnicas = array_values(array_unique(array_map(fn ($p) => $p['categoria'], $produtos)));
$filtro = isset($_GET['cat']) ? trim((string) $_GET['cat']) : '';
if ($filtro !== '') {
    $produtos = array_values(array_filter($produtos, fn ($p) => $p['categoria'] === $filtro));
}

require __DIR__ . '/../includes/header.php';
?>

<section class="section" aria-labelledby="prod-title">
    <div class="section-head">
        <div>
            <h1 id="prod-title">Lista de produtos</h1>
            <p>Confira os produtos disponíveis para o seu setup.</p>
        </div>
    </div>

    <div class="filters" role="group" aria-label="Filtrar por categoria">
        <a class="filter-chip <?= $filtro === '' ? 'active' : '' ?>" href="<?= e($base) ?>/pages/produtos.php">Todos</a>
        <?php foreach ($categoriasUnicas as $cat): ?>
            <a class="filter-chip <?= $filtro === $cat ? 'active' : '' ?>"
               href="<?= e($base) ?>/pages/produtos.php?cat=<?= urlencode($cat) ?>"><?= e($cat) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (count($produtos) > 0): ?>
        <div class="produtos">
            <?php foreach ($produtos as $p): ?>
                <article class="produto">
                    <a class="produto-media" href="<?= e($base) ?>/pages/produto.php?id=<?= $p['id'] ?>">
                        <img src="<?= e($base) ?>/assets/img/<?= e($p['imagem']) ?>" alt="<?= e($p['nome']) ?>" loading="lazy">
                    </a>
                    <div class="produto-body">
                        <span class="produto-cat"><?= e($p['categoria']) ?></span>
                        <h3><a href="<?= e($base) ?>/pages/produto.php?id=<?= $p['id'] ?>"><?= e($p['nome']) ?></a></h3>
                        <p><?= e($p['descricao']) ?></p>
                        <div class="produto-footer">
                            <span class="preco"><?= e(money($p['preco'])) ?></span>
                            <form method="post" action="<?= e($base) ?>/php/carrinho-acao.php" data-cart-form>
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="acao" value="add">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-buy">Comprar</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-message">Nenhum produto disponível nesta categoria.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
