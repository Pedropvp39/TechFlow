/**
 * theme.js — Sistema de tema claro/escuro compartilhado por TODO o site.
 *
 * O MODO ESCURO é o padrão do site (igual ao layout original).
 * O botão alterna para o MODO CLARO e a escolha fica salva no navegador.
 *
 * Este arquivo é carregado tanto no index.html quanto nas páginas PHP
 * (via includes/header.php), garantindo o mesmo comportamento em todo o site.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'tema';       // Chave usada no localStorage
  var DEFAULT_THEME = 'dark';     // Tema padrão do site
  var root = document.documentElement;

  /**
   * Lê o tema salvo pelo usuário.
   * Se não houver nada salvo, retorna o padrão (escuro).
   */
  function getStoredTheme() {
    try {
      return localStorage.getItem(STORAGE_KEY) || DEFAULT_THEME;
    } catch (e) {
      return DEFAULT_THEME;       // localStorage bloqueado -> usa o padrão
    }
  }

  /**
   * Aplica o atributo data-theme no <html>.
   * É esse atributo que o CSS usa para trocar as cores.
   */
  function applyTheme(theme) {
    root.setAttribute('data-theme', theme === 'light' ? 'light' : 'dark');
  }

  /**
   * Atualiza o ícone do botão conforme o tema ATUAL.
   * - No escuro mostra LUA  (você está no escuro)
   * - No claro mostra SOL   (você está no claro)
   * Assim o ícone representa o estado atual, sem confusão de "invertido".
   */
  function updateIcon() {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    var isDark = root.getAttribute('data-theme') === 'dark';
    // Ícone = tema atual; o título/rótulo diz para onde o clique vai
    btn.innerHTML = isDark
      ? '<i class="fa-solid fa-moon"></i>'   // Está escuro -> mostra lua
      : '<i class="fa-solid fa-sun"></i>';   // Está claro  -> mostra sol
    btn.setAttribute('aria-pressed', String(isDark));
    // Rótulo acessível que explica a AÇÃO do clique
    btn.setAttribute('aria-label', isDark ? 'Ativar tema claro' : 'Ativar tema escuro');
    btn.setAttribute('title', isDark ? 'Ativar tema claro' : 'Ativar tema escuro');
  }

  /**
   * Alterna entre claro e escuro e salva a escolha.
   */
  function toggleTheme() {
    var atual = root.getAttribute('data-theme') || DEFAULT_THEME;
    var novo = atual === 'dark' ? 'light' : 'dark';
    applyTheme(novo);
    try {
      localStorage.setItem(STORAGE_KEY, novo);
    } catch (e) { /* ignora se o storage estiver bloqueado */ }
    updateIcon();
    // Avisa o resto da pagina (ex.: botao "Criar conta") que o tema mudou
    document.dispatchEvent(new CustomEvent('tema-alterado', { detail: { tema: novo }}));
  }

  // Expõe a função de alternância para o onclick do HTML (nas páginas PHP)
  window.toggleTheme = toggleTheme;

  /**
   * Menu hambúrguer (aparece apenas em telas pequenas).
   * Abre/fecha a lista de links e anima o ícone.
   */
  function setupHamburger() {
    var toggle = document.getElementById('menuHamburger'); // Botão hambúrguer
    var menu = document.getElementById('menuLinks');        // Lista de links
    if (!toggle || !menu) return;

    // Abre/fecha ao clicar no botão
    toggle.addEventListener('click', function () {
      var aberto = menu.classList.toggle('is-open');
      toggle.classList.toggle('is-active', aberto);
      toggle.setAttribute('aria-expanded', String(aberto));
      toggle.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
    });

    // Fecha automaticamente ao clicar em um link
    menu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        menu.classList.remove('is-open');
        toggle.classList.remove('is-active');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  // Aplica o tema o mais cedo possível para evitar "flash"
  applyTheme(getStoredTheme());

  // Quando o DOM estiver pronto, liga os controles
  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('themeToggle');
    if (btn) btn.addEventListener('click', toggleTheme);
    updateIcon();
    setupHamburger();
  });
})();
