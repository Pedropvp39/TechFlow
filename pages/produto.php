<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/data.php';

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

$msgAval = '';
$erroAval = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['interagir_avaliacao'])) {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $erroAval = 'Sessão expirada. Tente novamente.';
    } elseif (!($u = current_user())) {
        $erroAval = 'Faça login para interagir com um comentário.';
    } else {
        $tipoInteracao = (string) ($_POST['tipo_interacao'] ?? '');
        $motivoDenuncia = trim((string) ($_POST['motivo_denuncia'] ?? ''));
        $detalhesDenuncia = trim((string) ($_POST['detalhes_denuncia'] ?? ''));
        if ($tipoInteracao === 'denuncia' && $motivoDenuncia === '') {
            $erroAval = 'Escolha o motivo da denúncia.';
        } else {
            $resInteracao = interagir_avaliacao((int) ($_POST['avaliacao_id'] ?? 0), (int) $u['id'], $tipoInteracao, $motivoDenuncia, $detalhesDenuncia, (string) $u['nome'], (string) $u['email']);
        }
        if (isset($resInteracao)) {
            if ($resInteracao['ok']) {
                $msgAval = $resInteracao['mensagem'];
            } else {
                $erroAval = $resInteracao['mensagem'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_avaliacao'])) {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $erroAval = 'Sessão expirada. Tente novamente.';
    } else {
        $u = current_user();
        if (!$u) {
            $erroAval = 'Você precisa estar logado para deixar uma avaliação.';
        } else {
            $notaPost = (int) ($_POST['nota'] ?? 5);
            $comentarioPost = (string) ($_POST['comentario'] ?? '');
            $res = adicionar_avaliacao_produto($produto['id'], (int) $u['id'], (string) $u['nome'], $notaPost, $comentarioPost);
            if ($res['ok']) {
                $msgAval = $res['mensagem'];
            } else {
                $erroAval = $res['mensagem'];
            }
        }
    }
}

$avaliacoes = get_avaliacoes_produto($produto['id']);

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
            <li>✓ Frete grátis para todo o Brasil</li>
            <li>✓ Garantia de 12 meses nacional</li>
            <li>✓ Compra 100% segura com suporte dedicado</li>
        </ul>
    </div>
</section>

<!-- Seção Pública de Avaliações e Comentários -->
<section class="section" aria-labelledby="reviews-title" id="avaliacoes">
    <div class="section-head">
        <div>
            <h2 id="reviews-title">💬 Avaliações e Comentários dos Clientes</h2>
            <p>Veja a opinião de quem já comprou este produto.</p>
        </div>
    </div>

    <?php if ($msgAval): ?>
        <p class="alert alert-success" role="status"><?= e($msgAval) ?></p>
    <?php endif; ?>
    <?php if ($erroAval): ?>
        <p class="alert alert-error" role="alert"><?= e($erroAval) ?></p>
    <?php endif; ?>

    <!-- Form de envio de avaliação -->
    <div class="review-form-box" style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 28px;">
        <?php if ($currentUser = current_user()): ?>
            <h3 style="font-size: 1.1rem; margin-bottom: 12px; color: var(--text);">Deixe sua avaliação sobre este produto</h3>
            <form method="post" action="<?= e($base) ?>/pages/produto.php?id=<?= $produto['id'] ?>#avaliacoes">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="enviar_avaliacao" value="1">

                <div class="field" style="margin-bottom: 14px;">
                    <label style="font-size: 0.88rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; display: block;">Nota / Estrelas</label>
                    <select name="nota" required style="padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text); font-size: 1rem; width: min(200px, 100%);">
                        <option value="5">⭐⭐⭐⭐⭐</option>
                        <option value="4">⭐⭐⭐⭐</option>
                        <option value="3">⭐⭐⭐</option>
                        <option value="2">⭐⭐</option>
                        <option value="1">⭐</option>
                    </select>
                </div>

                <div class="field" style="margin-bottom: 14px;">
                    <label for="comentario_input" style="font-size: 0.88rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; display: block;">Seu comentário sobre o produto</label>
                    <textarea id="comentario_input" name="comentario" rows="3" required placeholder="Conte como foi sua experiência com este produto..." style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text); resize: vertical;"></textarea>
                </div>

                <button type="submit" class="btn" style="padding: 10px 24px;">Publicar Avaliação</button>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 12px;">
                <p style="margin-bottom: 12px; color: var(--muted);">Faça login para compartilhar sua opinião e avaliar este produto.</p>
                <a class="btn secondary" href="<?= e($base) ?>/pages/login.php?redirect=produto&id=<?= $produto['id'] ?>">Entrar para Avaliar</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lista pública de avaliações -->
    <?php if (empty($avaliacoes)): ?>
        <p class="empty-message" style="text-align: center; padding: 24px; color: var(--muted);">Este produto ainda não possui avaliações. Seja o primeiro a avaliar!</p>
    <?php else: ?>
        <div class="reviews-list" style="display: flex; flex-direction: column; gap: 10px;">
            <?php foreach ($avaliacoes as $rev): ?>
            <div class="review-card" style="background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 12px 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <strong style="font-size: 1rem; color: var(--text);"><?= e($rev['usuario_nome']) ?></strong>
                            <span style="display: inline-block; margin-left: 8px; font-size: 0.8rem; color: var(--success); background: var(--success-soft); padding: 2px 8px; border-radius: 12px; border: 1px solid var(--success-line);">Comprador verificado</span>
                        </div>
                        <div style="font-size: 1.1rem; color: var(--star);">
                            <?= str_repeat('⭐', $rev['nota']) ?>
                        </div>
                    </div>
                    <p style="color: var(--muted); font-size: 0.88rem; line-height: 1.4; margin-bottom: 6px; white-space: pre-line;"><?= e($rev['comentario']) ?></p>
                    <div class="review-actions">
                        <form method="post" action="<?= e($base) ?>/pages/produto.php?id=<?= $produto['id'] ?>#avaliacoes">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="interagir_avaliacao" value="1">
                            <input type="hidden" name="avaliacao_id" value="<?= (int) $rev['id'] ?>">
                            <input type="hidden" name="tipo_interacao" value="like">
                            <button type="submit" class="review-action-button" aria-label="Curtir comentário">Curtir <span><?= (int) $rev['likes'] ?></span></button>
                        </form>
                        <form method="post" action="<?= e($base) ?>/pages/produto.php?id=<?= $produto['id'] ?>#avaliacoes" class="review-report-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="interagir_avaliacao" value="1">
                            <input type="hidden" name="avaliacao_id" value="<?= (int) $rev['id'] ?>">
                            <input type="hidden" name="tipo_interacao" value="denuncia">
                            <details class="review-report-details">
                                <summary>Denunciar <span><?= (int) $rev['denuncias'] ?></span></summary>
                                <select name="motivo_denuncia" required aria-label="Motivo da denúncia" class="review-report-field">
                                    <option value="">Motivo da denúncia</option>
                                    <option value="Ofensa ou assédio">Ofensa ou assédio</option>
                                    <option value="Spam ou propaganda">Spam ou propaganda</option>
                                    <option value="Conteúdo impróprio">Conteúdo impróprio</option>
                                    <option value="Informação falsa">Informação falsa</option>
                                    <option value="Outro">Outro</option>
                                </select>
                                <textarea name="detalhes_denuncia" rows="2" maxlength="500" placeholder="Detalhes (opcional)" class="review-report-field"></textarea>
                                <button type="submit" class="review-action-button review-report" aria-label="Enviar denúncia">Enviar denúncia</button>
                            </details>
                        </form>
                    </div>
                    <?php if (!empty($rev['criado_em'])): ?>
                        <small style="color: var(--muted); font-size: 0.78rem;">Publicado em <?= date('d/m/Y \à\s H:i', strtotime($rev['criado_em'])) ?></small>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
