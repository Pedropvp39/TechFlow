<?php
// Arquivo do cabeçalho reútilizado em todas as páginas do site

// Inclui o arquivo de configurações globais e funções utilitárias
require_once __DIR__ . '/config.php';

// Inclui o sistema de autenticação de usuários
require_once __DIR__ . '/auth.php';

// Inclui o gerenciador do carrinho de compras
require_once __DIR__ . '/cart.php';

// Obtém a URL base dinâmica do sistema
$base = base_url();

// Obtém os dados do usuário atualmente logado (ou null caso seja visitante)
$user = current_user();

// Calcula a quantidade total de itens no carrinho
$cartCount = cart_count();

// Define o título final da página
$title = isset($page_title) ? $page_title . ' — TechFlow' : 'TechFlow — Peças de PC';

// Define classe CSS personalizada para a tag <body> caso informada
$bodyClass = $body_class ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <!-- Define a codificação de caracteres como UTF-8 -->
    <meta charset="UTF-8">

    <!-- Configura a viewport para responsividade em dispositivos móveis -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Descrição SEO da loja -->
    <meta name="description" content="TechFlow: processadores, placas de vídeo, memória, SSD e mais para montar seu PC de alta performance.">

    <!-- Cor do tema da barra de navegação no celular -->
    <meta name="theme-color" content="#120406">

    <!-- Pré-conexão para otimização de carregamento das fontes do Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap">
    <!-- Ícones (Font Awesome) usados no botão de tema sol/lua -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Folha de estilos CSS principal da loja -->
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">

    <!-- Sistema de tema claro/escuro (padrão = escuro). Carrega cedo p/ evitar flash -->
    <script src="<?= e($base) ?>/assets/js/theme.js"></script>

    <!-- Título da aba da página -->
    <title><?= e($title) ?></title>
</head>
<body class="<?= e($bodyClass) ?>" data-base="<?= e($base) ?>" data-csrf="<?= e(csrf_token()) ?>" data-logged-in="<?= current_user() ? '1' : '0' ?>" data-user-name="<?= e($user['nome'] ?? '') ?>" data-user-email="<?= e($user['email'] ?? '') ?>" data-user-phone="<?= e($user['telefone'] ?? '') ?>" data-user-cep="<?= e($user['cep'] ?? '') ?>" data-user-rua="<?= e($user['rua'] ?? '') ?>" data-user-numero="<?= e($user['numero'] ?? '') ?>" data-user-cidade="<?= e($user['cidade'] ?? '') ?>" data-user-estado="<?= e($user['estado'] ?? '') ?>" data-session-cart='<?= e(json_encode($_SESSION['cart'] ?? [])) ?>' data-products='<?= e(json_encode(get_produtos())) ?>' data-user-addresses='<?= e(json_encode(get_enderecos_usuario($user['id'] ?? 0))) ?>'>
    <!-- Cabeçalho topo do site -->
    <header class="site-header">
        <nav class="site-nav" aria-label="Navegação principal">
            <!-- Logotipo da marca -->
            <a class="logo" href="<?= e($base) ?>/index.php">
                <span class="logo-mark" aria-hidden="true">◆</span> TechFlow
            </a>

            <!-- Formulário da barra de pesquisa com busca em tempo real no topo -->
            <form method="get" action="<?= e($base) ?>/pages/produtos.php" class="header-search-form" style="max-width: 360px; flex: 1; margin: 0 12px;">
                <div class="search-input-wrap">
                    <span class="search-icon-left">🔍</span>
                    <input type="text" name="q" placeholder="Buscar PC, componentes..." data-live-search="header" autocomplete="off" value="<?= e($_GET['q'] ?? '') ?>">
                    <button type="button" class="search-clear-btn" aria-label="Limpar busca">✕</button>
                </div>
            </form>

            <!-- Área da direita: menu + botão de tema + hambúrguer -->
            <div class="nav-right">

            <!-- Links de navegação do menu superior -->
            <div class="menu" id="menuLinks">
                <a href="<?= e($base) ?>/pages/produtos.php">Produtos</a>
                <a class="cart-link" href="<?= e($base) ?>/pages/carrinho.php" aria-label="Carrinho">
                    Carrinho
                </a>
                <?php if ($user): ?>
                    <?php if (has_role(['admin', 'developer', 'support', 'moderator', 'manager', 'financial', 'logistics'])): ?>
                        <a href="<?= e($base) ?>/pages/painel.php">Painel Staff</a>
                    <?php endif; ?>
                    <a href="<?= e($base) ?>/pages/dashboard.php" class="nav-profile-link">
                        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])): ?>
                            <img src="<?= e($base . '/' . $user['avatar']) ?>" alt="Foto de perfil" class="nav-avatar-img">
                        <?php endif; ?>
                        Meu perfil
                    </a>
                    <a class="menu-cta" href="<?= e($base) ?>/php/logout.php">Sair</a>
                <?php else: ?>
                    <a href="<?= e($base) ?>/pages/login.php">Entrar</a>
                    <a class="menu-cta" href="<?= e($base) ?>/pages/cadastro.php">Criar conta</a>
                <?php endif; ?>
            </div>

            <!-- Botão de tema (visível para TODOS, logado ou não) -->
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Alternar tema claro/escuro" aria-pressed="false"></button>

            <!-- Botão hambúrguer (só aparece no responsivo) -->
            <button class="menu-hamburger" id="menuHamburger" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="menuLinks">
                <span></span>
                <span></span>
                <span></span>
            </button>
            </div>
        </nav>
    </header>
    <!-- Elemento principal da página onde o conteúdo é renderizado -->
    <main class="site-main">
