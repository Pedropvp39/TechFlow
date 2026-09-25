<?php
// Carrega as configurações gerais do sistema (sessão, funções de URL, segurança)
require_once __DIR__ . '/includes/config.php';

// Carrega as funções que buscam produtos e categorias no banco de dados
require_once __DIR__ . '/includes/data.php';

// Título que aparece na aba do navegador (o header.php acrescenta " — TechFlow")
$page_title = 'Peças de PC de alta performance';

// Classe extra aplicada na tag <body> (usada pelo CSS para ajustar a home)
$body_class = 'home-page';

// ---------------------------------------------------------------------------
// BUSCA DOS PRODUTOS EM DESTAQUE NO BANCO DE DADOS
// ---------------------------------------------------------------------------
// get_produtos_destaque() devolve apenas os produtos marcados como destaque.
// Se a função não existir em alguma versão antiga, usamos get_produtos() e
// filtramos na hora, evitando erro fatal na página inicial.
$destaques = function_exists('get_produtos_destaque')
    ? get_produtos_destaque()
    : array_values(array_filter(get_produtos(), static function ($produto) {
        // Mantém somente os itens cujo campo "destaque" seja verdadeiro
        return !empty($produto['destaque']);
    }));

// Mostra no máximo 4 produtos na grade da home (para o layout não quebrar)
$destaques = array_slice($destaques, 0, 4);

// ---------------------------------------------------------------------------
// IMAGENS DA CAPA (CARROSSEL DO TOPO)
// ---------------------------------------------------------------------------
// Cada slide tem a imagem de fundo e o caminho é montado com base_url(),
// garantindo que funcione tanto na raiz quanto dentro de uma subpasta.
$base = base_url();

// Lista de imagens usadas no carrossel grande do topo (ordem de exibição)
$heroSlides = [
    'assets/img/gabinete.png',              // Imagem principal (gabinete/PC)
    'assets/img/prod_1789733251_8257.jpg',  // Segunda imagem do carrossel
    'assets/img/prod_1789734141_2359.jpg',  // Terceira imagem do carrossel
];

// ---------------------------------------------------------------------------
// NÚMEROS DA FAIXA DE ESTATÍSTICAS
// ---------------------------------------------------------------------------
// get_loja_estatisticas() lê do banco a quantidade de produtos, o total de
// avaliações aprovadas e a nota média. Se o banco estiver fora do ar, ela
// mesma devolve valores seguros, então a home nunca quebra por causa disso.
$estatisticas = get_loja_estatisticas();

// Quantidade total de produtos no catálogo (mostrada na faixa de estatísticas)
$totalProdutos = (int) ($estatisticas['produtos'] ?? count(get_produtos()));

// Nota média das avaliações aprovadas (ex.: 5.0), formatada com vírgula
$notaMedia = number_format((float) ($estatisticas['nota'] ?? 0), 1, ',', '.');

// Desenha o cabeçalho (topo do site, menu, busca e botão de tema)
require __DIR__ . '/includes/header.php';
?>

        <!-- ============================================================ -->
        <!-- SEÇÃO HERO (CAPA): imagem de fundo em tela cheia com o texto  -->
        <!-- principal por cima. O carrossel é controlado por JS.          -->
        <!-- ============================================================ -->
        <section class="hero hero-full" data-hero-carousel aria-label="Destaques da TechFlow">
            <!-- Fundo: carrossel de imagens que cobre toda a área da capa -->
            <div class="hero-visual" aria-hidden="true">
                <div class="hero-image-slides">
                    <?php // Percorre a lista de imagens criando um slide para cada uma ?>
                    <?php foreach ($heroSlides as $indice => $imagem): ?>
                        <?php // O primeiro slide já começa ativo (visível) ?>
                        <div class="hero-image-slide <?= $indice === 0 ? 'is-active' : '' ?>" data-slide<?= $indice === 0 ? '' : ' aria-hidden="true"' ?>>
                            <?php // Imagem de fundo do slide, montada com a URL base do site ?>
                            <img src="<?= e($base . '/' . $imagem) ?>" alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Camada escura por cima da imagem para o texto ficar legível -->
                <span class="hero-overlay" aria-hidden="true"></span>
            </div>

            <!-- Texto centralizado que fica sobreposto à imagem de fundo -->
            <div class="hero-content">
                <div class="hero-slides">
                    <!-- Slide de texto 1: chamada principal da loja -->
                    <article class="hero-slide is-active" data-slide>
                        <span class="hero-eyebrow">◆ Montagem &amp; upgrade de PCs</span>
                        <h1>Monte o seu PC com peças de <span class="grad">alta performance</span></h1>
                        <p>Processadores, placas de vídeo, memória RAM, SSD e muito mais, com preços competitivos e entrega rápida para o seu setup dos sonhos.</p>
                        <div class="hero-actions">
                            <!-- Botão que leva para a listagem completa de produtos -->
                            <a class="btn" href="<?= e($base) ?>/pages/produtos.php">Ver produtos</a>
                            <!-- Botão que leva para a criação de conta -->
                            <a class="btn secondary" href="<?= e($base) ?>/pages/cadastro.php">Criar conta</a>
                        </div>
                    </article>

                    <!-- Slide de texto 2: foco em jogos (aparece no 2º giro) -->
                    <article class="hero-slide" data-slide aria-hidden="true">
                        <span class="hero-eyebrow">◆ Desempenho para jogar</span>
                        <h1>Mais velocidade para o seu <span class="grad">setup gamer</span></h1>
                        <p>Encontre placas de vídeo, processadores e memória para jogar com estabilidade e aproveitar cada frame.</p>
                        <div class="hero-actions">
                            <!-- Link direto para a categoria de placas de vídeo -->
                            <a class="btn" href="<?= e($base) ?>/pages/produtos.php?cat=<?= urlencode('Placas de vídeo') ?>">Ver GPUs</a>
                        </div>
                    </article>

                    <!-- Slide de texto 3: destaca ofertas e entrega rápida -->
                    <article class="hero-slide" data-slide aria-hidden="true">
                        <span class="hero-eyebrow">◆ Oferta da semana</span>
                        <h1>Atualize seu computador com <span class="grad">entrega rápida</span></h1>
                        <p>Componentes selecionados, produtos originais e condições especiais para você montar sem complicação.</p>
                        <div class="hero-actions">
                            <!-- Leva o visitante para o catálogo completo -->
                            <a class="btn" href="<?= e($base) ?>/pages/produtos.php">Explorar ofertas</a>
                        </div>
                    </article>
                </div>
            </div>

            <!-- Controle esquerdo do carrossel (volta um slide) -->
            <button class="carousel-control carousel-prev" type="button" data-carousel-prev aria-label="Slide anterior">&#8249;</button>
            <!-- Controle direito do carrossel (avança um slide) -->
            <button class="carousel-control carousel-next" type="button" data-carousel-next aria-label="Próximo slide">&#8250;</button>

            <!-- Bolinhas indicadoras: uma para cada slide do carrossel -->
            <div class="carousel-dots" role="tablist" aria-label="Selecionar slide">
                <?php // Cria uma bolinha para cada imagem do carrossel ?>
                <?php foreach ($heroSlides as $indice => $imagem): ?>
                    <button class="carousel-dot <?= $indice === 0 ? 'is-active' : '' ?>"
                            type="button"
                            data-carousel-dot="<?= (int) $indice ?>"
                            role="tab"
                            aria-label="Ir para slide <?= (int) ($indice + 1) ?>"
                            aria-selected="<?= $indice === 0 ? 'true' : 'false' ?>"></button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- FAIXA DE ESTATÍSTICAS: uma linha que desliza lentamente e     -->
        <!-- mostra números da loja. Os itens são repetidos duas vezes     -->
        <!-- para o movimento de rolagem ficar contínuo (sem "pulos").     -->
        <!-- ============================================================ -->
        <section class="stats-strip" aria-label="Destaques da loja">
            <div class="stats-track">
                <!-- Primeiro grupo de cards de números -->
                <div class="hero-stat"><strong><?= $totalProdutos ?></strong><span>peças no catálogo</span></div>
                <div class="hero-stat"><strong>24h</strong><span>envio para todo o Brasil</span></div>
                <div class="hero-stat"><strong><?= e($notaMedia) ?>/5</strong><span>avaliações verificadas</span></div>
                <div class="hero-stat"><strong>100%</strong><span>produtos originais</span></div>
                <div class="hero-stat"><strong>12x</strong><span>sem juros no cartão</span></div>

                <!-- Segundo grupo: cópia idêntica e invisível, só para o loop -->
                <div class="hero-stat" aria-hidden="true"><strong><?= $totalProdutos ?></strong><span>peças no catálogo</span></div>
                <div class="hero-stat" aria-hidden="true"><strong>24h</strong><span>envio para todo o Brasil</span></div>
                <div class="hero-stat" aria-hidden="true"><strong><?= e($notaMedia) ?>/5</strong><span>avaliações verificadas</span></div>
                <div class="hero-stat" aria-hidden="true"><strong>100%</strong><span>produtos originais</span></div>
                <div class="hero-stat" aria-hidden="true"><strong>12x</strong><span>sem juros no cartão</span></div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- SEÇÃO "TEMOS AQUI": banner grande clicável que leva para o    -->
        <!-- catálogo de produtos.                                         -->
        <!-- ============================================================ -->
        <section class="section" aria-labelledby="temos-title">
            <!-- Cabeçalho da seção: título à esquerda e link à direita -->
            <div class="section-head">
                <div>
                    <h2 id="temos-title">Temos aqui</h2>
                    <p>Tudo o que você precisa para montar o PC dos seus sonhos.</p>
                </div>
                <!-- Link rápido para o catálogo completo -->
                <a class="link-more" href="<?= e($base) ?>/pages/produtos.php">Ver todo o catálogo →</a>
            </div>

            <!-- Banner grande: a tag <a> inteira é clicável -->
            <a class="big-banner" href="<?= e($base) ?>/pages/produtos.php" aria-label="Ir para os principais produtos">
                <!-- Imagem de fundo do banner (recebe um zoom lento pelo CSS) -->
                <img class="big-banner-img" src="<?= e($base) ?>/assets/img/gabinete.png" alt="Setup gamer TechFlow com componentes de alta performance">
                <!-- Textos e botão sobrepostos à imagem -->
                <span class="big-banner-content">
                    <span class="big-banner-eyebrow">◆ Confira a seleção</span>
                    <span class="big-banner-title">Os principais produtos para o seu setup</span>
                    <span class="big-banner-text">Processadores, placas de vídeo, memórias e muito mais em um só lugar.</span>
                    <span class="btn big-banner-btn">Ver produtos <span aria-hidden="true">→</span></span>
                </span>
            </a>
        </section>

        <!-- ============================================================ -->
        <!-- SEÇÃO DE PRODUTOS EM DESTAQUE: a lista vem do BANCO DE DADOS. -->
        <!-- Basta marcar "destaque" no produto (no painel admin) que ele   -->
        <!-- aparece automaticamente nesta grade.                          -->
        <!-- ============================================================ -->
        <section class="section" aria-labelledby="dest-title">
            <!-- Cabeçalho da seção de destaques -->
            <div class="section-head">
                <div>
                    <h2 id="dest-title">Produtos em destaque</h2>
                    <p>Ofertas da semana com preços especiais.</p>
                </div>
                <!-- Link para ver o catálogo inteiro -->
                <a class="link-more" href="<?= e($base) ?>/pages/produtos.php">Ver todos →</a>
            </div>

            <!-- Grade que recebe os cards dos produtos em destaque -->
            <div class="destaque-grid">
                <?php // Se não houver nenhum destaque cadastrado, mostra um aviso simples ?>
                <?php if (empty($destaques)): ?>
                    <p class="destaque-vazio">Nenhum produto em destaque no momento.</p>
                <?php endif; ?>

                <?php // Percorre os produtos em destaque criando um card para cada um ?>
                <?php foreach ($destaques as $produto): ?>
                    <?php
                        // Caminho da imagem do produto vindo do banco de dados.
                        // Se o campo estiver vazio, usa a imagem padrão do sistema.
                        $imagemProduto = $produto['imagem'] !== '' ? $produto['imagem'] : 'default.png';
                    ?>
                    <!-- Cada produto é um link que leva para a página de detalhes -->
                    <a class="destaque-card" href="<?= e($base) ?>/pages/produto.php?id=<?= (int) $produto['id'] ?>">
                        <span class="destaque-media">
                            <?php // Imagem do produto, com carregamento preguiçoso para o site abrir mais rápido ?>
                            <img src="<?= e($base) ?>/assets/img/<?= e($imagemProduto) ?>"
                                 alt="<?= e($produto['nome']) ?>"
                                 loading="lazy">
                            <!-- Etiqueta "Destaque" no canto da imagem -->
                            <span class="destaque-tag">Destaque</span>
                        </span>
                        <span class="destaque-body">
                            <!-- Categoria do produto (ex.: Processadores) -->
                            <span class="produto-cat"><?= e($produto['categoria']) ?></span>
                            <!-- Nome do produto -->
                            <strong class="destaque-nome"><?= e($produto['nome']) ?></strong>
                            <span class="destaque-footer">
                                <!-- Preço já formatado em reais pela função money() -->
                                <span class="preco"><?= e(money($produto['preco'])) ?></span>
                                <span class="destaque-btn">Ver produto</span>
                            </span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Script do carrossel da capa (setas, bolinhas e troca automática).
             Carregado somente nesta página, porque só a capa usa carrossel. -->
        <script src="<?= e($base) ?>/assets/js/hero-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/hero-carousel.js') ?>" defer></script>

<?php
// Desenha o rodapé do site (dados da loja, links e formas de pagamento).
// O rodapé já fecha as tags </body> e </html>.
require __DIR__ . '/includes/footer.php';