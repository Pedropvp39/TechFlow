<?php
// Script PHP da página de catálogo de produtos com filtros em tempo real conectados ao MySQL

// Carrega as configurações gerais do sistema (Sessão, URL base e sanitização)
require_once __DIR__ . '/../includes/config.php';

// Carrega as funções que realizam consultas no banco de dados MySQL
require_once __DIR__ . '/../includes/data.php';

// Impede o armazenamento em cache no navegador para garantir que os resultados do filtro sejam sempre atualizados
no_cache();

// Define o título da aba do navegador
$page_title = 'Produtos';

// Obtém a URL base do site
$base = base_url();

// Captura a categoria informada na URL via GET (ou string vazia se não informada)
$filtroCat = isset($_GET['cat'])
    ? trim((string) $_GET['cat'])
    : (isset($_GET['categoria']) ? trim((string) $_GET['categoria']) : '');

// Captura o termo de busca por palavra-chave informado na URL via GET
$buscaQuery = isset($_GET['q']) ? trim((string) $_GET['q']) : (isset($_GET['busca']) ? trim((string) $_GET['busca']) : '');

// Captura o valor de preço mínimo informado na URL via GET
$precoMin = isset($_GET['preco_min']) && $_GET['preco_min'] !== '' ? (float) $_GET['preco_min'] : null;

// Captura o valor de preço máximo informado na URL via GET
$precoMax = isset($_GET['preco_max']) && $_GET['preco_max'] !== '' ? (float) $_GET['preco_max'] : null;

// Captura o tipo de ordenação escolhido (menor_preco, maior_preco, nome_asc, etc.)
$ordem = isset($_GET['ordem']) ? trim((string) $_GET['ordem']) : '';

// Executa a consulta dinâmica no MySQL passando todos os parâmetros de filtro informados pelo usuário
$produtos = get_produtos_filtrados($filtroCat, $buscaQuery, $precoMin, $precoMax, $ordem);

// Carrega a lista completa de categorias direto da tabela 'categorias' do MySQL para preencher os selects
$categoriasDb = get_categorias();

// Inclui o cabeçalho HTML padrão do site
require __DIR__ . '/../includes/header.php';
?>

<section class="section" aria-labelledby="prod-title">
    <div class="section-head">
        <div>
            <h1 id="prod-title">Catálogo de Produtos</h1>
            <p>Filtre e encontre as melhores peças para o seu computador.</p>
        </div>
    </div>

    <?php $filtrosAtivos = $filtroCat !== '' || $buscaQuery !== '' || $precoMin !== null || $precoMax !== null || $ordem !== ''; ?>
    <!-- Painel recolhível de filtros -->
    <div class="catalog-toolbar">
        <span class="catalog-results"><strong><?= count($produtos) ?></strong> produto(s) encontrado(s)</span>
        <button class="filter-toggle" type="button" data-filter-toggle aria-controls="catalog-filters" aria-expanded="<?= $filtrosAtivos ? 'true' : 'false' ?>">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M7 12h10M10 18h4"></path></svg>
            <span>Filtros</span>
            <?php if ($filtrosAtivos): ?><span class="filter-toggle-badge" aria-label="Filtros ativos"></span><?php endif; ?>
        </button>
    </div>
    <div class="filter-panel<?= $filtrosAtivos ? ' is-open' : '' ?>" id="catalog-filters" data-filter-panel<?= $filtrosAtivos ? '' : ' hidden' ?>>
        <div class="filter-panel-head">
            <div>
                <span class="filter-kicker">Refine sua busca</span>
                <h2>Filtros</h2>
            </div>
            <span class="filter-count">Escolha os critérios desejados</span>
        </div>
        <form method="get" action="<?= e($base) ?>/pages/produtos.php" class="filter-form">
            <div class="filter-grid">
                <div class="field filter-search-field">
                    <label for="q_input">Buscar produto</label>
                    <div class="search-input-wrap">
                        <input type="text" id="q_input" name="q" value="<?= e($buscaQuery) ?>" placeholder="Nome, modelo ou categoria..." data-live-search="catalog" autocomplete="off">
                        <button type="button" class="search-clear-btn" aria-label="Limpar busca">✕</button>
                    </div>
                </div>

                <!-- Categoria (MySQL) -->
                <div class="field">
                    <label for="cat_select">Categoria</label>
                    <select id="cat_select" name="cat">
                        <option value="">Todas as Categorias</option>
                        <?php foreach ($categoriasDb as $catItem): ?>
                            <option value="<?= e($catItem['nome']) ?>" <?= $filtroCat === $catItem['nome'] ? 'selected' : '' ?>>
                                <?= e($catItem['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Preço Mínimo -->
                <div class="field">
                    <label for="pmin_input">Preço mínimo</label>
                    <input type="number" id="pmin_input" name="preco_min" value="<?= $precoMin !== null ? e($precoMin) : '' ?>" placeholder="R$ 0,00" step="0.01" min="0">
                </div>

                <!-- Preço Máximo -->
                <div class="field">
                    <label for="pmax_input">Preço máximo</label>
                    <input type="number" id="pmax_input" name="preco_max" value="<?= $precoMax !== null ? e($precoMax) : '' ?>" placeholder="R$ 9.999,00" step="0.01" min="0">
                </div>

                <!-- Ordenação -->
                <div class="field">
                    <label for="ordem_select">Ordenar por</label>
                    <select id="ordem_select" name="ordem">
                        <option value="" <?= $ordem === '' ? 'selected' : '' ?>>Padrão</option>
                        <option value="menor_preco" <?= $ordem === 'menor_preco' ? 'selected' : '' ?>>Menor Preço</option>
                        <option value="maior_preco" <?= $ordem === 'maior_preco' ? 'selected' : '' ?>>Maior Preço</option>
                        <option value="nome_asc" <?= $ordem === 'nome_asc' ? 'selected' : '' ?>>Nome (A-Z)</option>
                        <option value="nome_desc" <?= $ordem === 'nome_desc' ? 'selected' : '' ?>>Nome (Z-A)</option>
                    </select>
                </div>

                <!-- Botões de Ação -->
                <div class="filter-actions">
                    <button type="submit" class="btn">Aplicar filtros</button>
                    <a href="<?= e($base) ?>/pages/produtos.php" class="btn secondary" title="Limpar filtros">Limpar</a>
                </div>
            </div>
        </form>

        <!-- Chips de Categoria para Acesso Rápido -->
        <div class="filters category-filters" role="group" aria-label="Filtrar por categoria">
            <span class="category-filter-label">Categorias</span>
            <a class="filter-chip <?= $filtroCat === '' ? 'active' : '' ?>" href="<?= e($base) ?>/pages/produtos.php<?= $buscaQuery ? '?q=' . urlencode($buscaQuery) : '' ?>">Todas</a>
            <?php foreach ($categoriasDb as $catItem): ?>
                <?php
                    $queryParams = [];
                    if ($buscaQuery !== '') $queryParams['q'] = $buscaQuery;
                    $queryParams['cat'] = $catItem['nome'];
                    if ($precoMin !== null) $queryParams['preco_min'] = $precoMin;
                    if ($precoMax !== null) $queryParams['preco_max'] = $precoMax;
                    if ($ordem !== '') $queryParams['ordem'] = $ordem;
                    $catUrl = $base . '/pages/produtos.php?' . http_build_query($queryParams);
                ?>
                <a class="filter-chip <?= $filtroCat === $catItem['nome'] ? 'active' : '' ?>" href="<?= e($catUrl) ?>">
                    <?= e($catItem['nome']) ?>
                </a>
            <?php endforeach; ?>
        </div>
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
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="btn-buy">Comprar</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-cart" style="text-align: center; padding: 48px 20px;">
            <h3>Nenhum produto encontrado</h3>
            <p>Nenhum produto no banco de dados corresponde aos filtros selecionados.</p>
            <a class="btn secondary" href="<?= e($base) ?>/pages/produtos.php" style="margin-top: 16px; display: inline-block;">Limpar Filtros</a>
        </div>
    <?php endif; ?>
</section>

<script src="<?= e($base) ?>/assets/js/catalog-filters.js" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>