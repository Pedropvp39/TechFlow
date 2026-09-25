// Aguarda todo o carregamento do DOM (HTML da página) antes de inicializar o script de busca
document.addEventListener('DOMContentLoaded', () => {
  // Obtém a URL base do projeto armazenada no atributo data-base da tag body
  const base = document.body?.dataset?.base || '';

  // Função utilitária para formatar valores numéricos em moeda brasileira Real (R$)
  const money = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(val) || 0);

  // Seleciona todos os campos de texto com o atributo data-live-search na página
  document.querySelectorAll('[data-live-search]').forEach((input) => {
    // Localiza o container pai envolvido pela classe .search-input-wrap
    const wrap = input.closest('.search-input-wrap') || input.parentElement;

    // Localiza o botão '✕' para limpar a busca dentro do mesmo container
    const clearBtn = wrap.querySelector('.search-clear-btn');

    // Obtém o formulário do qual este campo faz parte
    const form = input.closest('form');

    // Procura por um menu suspenso de resultados existente ou cria um novo elemento HTML div
    let dropdown = wrap.querySelector('.search-autocomplete-dropdown');
    if (!dropdown) {
      dropdown = document.createElement('div'); // Cria a div do menu suspenso
      dropdown.className = 'search-autocomplete-dropdown'; // Atribui a classe CSS
      wrap.appendChild(dropdown); // Adiciona o menu suspenso dentro do container
    }

    // Variável para armazenar o temporizador de atraso (debounce)
    let debounceTimer = null;

    // Função que exibe ou esconde o botão '✕' de limpar com base no conteúdo digitado
    function updateClearBtn() {
      if (clearBtn) {
        clearBtn.style.display = input.value.trim().length > 0 ? 'inline-flex' : 'none';
      }
    }

    // Adiciona evento ao clicar no botão '✕' de limpar busca
    if (clearBtn) {
      clearBtn.addEventListener('click', (e) => {
        e.preventDefault(); // Impede o envio do formulário
        e.stopPropagation(); // Evita a propagação do evento de clique
        input.value = ''; // Reseta o texto do campo de entrada
        updateClearBtn(); // Esconde o botão de limpar
        dropdown.style.display = 'none'; // Oculta o menu de sugestões
        input.focus(); // Devolve o foco do cursor para o campo de texto
      });
    }

    // Ouve o evento de digitação do usuário no campo de busca
    input.addEventListener('input', () => {
      updateClearBtn(); // Atualiza o botão de limpar a cada caractere
      const q = input.value.trim(); // Obtém o texto sem espaços extras nas pontas

      clearTimeout(debounceTimer); // Cancela o temporizador anterior para evitar requisições acumuladas

      // Se o campo estiver vazio, oculta o menu suspenso e encerra a execução
      if (q.length === 0) {
        dropdown.style.display = 'none';
        return;
      }

      // Define um atraso inteligente de 180 milissegundos antes de enviar a requisição de busca
      debounceTimer = setTimeout(async () => {
        try {
          // Faz a requisição AJAX assíncrona para o script PHP do banco de dados MySQL
          const res = await fetch(`${base}/php/busca-autocomplete.php?q=${encodeURIComponent(q)}`);
          const data = await res.json(); // Converte a resposta recebida em JSON

          dropdown.replaceChildren();

          // Use textContent para que nomes e categorias vindos da API nunca sejam interpretados como HTML.
          if (!data.ok || !Array.isArray(data.produtos) || data.produtos.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'search-autocomplete-item search-autocomplete-empty';
            empty.textContent = `Nenhum produto encontrado para "${data.q || q}"`;
            dropdown.appendChild(empty);
            dropdown.style.display = 'block';
            return;
          }

          data.produtos.forEach((p) => {
            const link = document.createElement('a');
            link.href = `${base}/pages/produto.php?id=${encodeURIComponent(p.id)}`;
            link.className = 'search-autocomplete-item';

            const image = document.createElement('img');
            image.src = `${base}/assets/img/${encodeURIComponent(p.imagem || '')}`;
            image.alt = p.nome || 'Produto';
            image.className = 'search-autocomplete-thumb';

            const info = document.createElement('div');
            info.className = 'search-autocomplete-info';
            const title = document.createElement('div');
            title.className = 'search-autocomplete-title';
            title.textContent = p.nome || 'Produto';
            const meta = document.createElement('div');
            meta.className = 'search-autocomplete-meta';
            const category = document.createElement('span');
            category.textContent = p.categoria || '';
            const price = document.createElement('span');
            price.className = 'search-autocomplete-price';
            price.textContent = money(p.preco);
            meta.append(category, price);
            info.append(title, meta);
            link.append(image, info);
            dropdown.appendChild(link);
          });

          if (Number(data.total) > data.produtos.length) {
            const footer = document.createElement('button');
            footer.type = 'button';
            footer.className = 'search-autocomplete-footer';
            footer.textContent = `Ver todos os ${data.total} resultados`;
            footer.addEventListener('click', () => {
              if (form) form.submit();
              else window.location.href = `${base}/pages/produtos.php?q=${encodeURIComponent(q)}`;
            });
            dropdown.appendChild(footer);
          }

          dropdown.style.display = 'block'; // Torna o menu de resultados visível
        } catch (err) {
          dropdown.style.display = 'none'; // Oculta em caso de erro de conexão
        }
      }, 180);
    });

    // Ao re-focar no campo de busca, re-executa a busca se já houver texto digitado
    input.addEventListener('focus', () => {
      if (input.value.trim().length > 0) {
        input.dispatchEvent(new Event('input'));
      }
    });
  });

  // Ouve cliques fora do container de busca para fechar o menu suspenso de resultados
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-input-wrap')) {
      document.querySelectorAll('.search-autocomplete-dropdown').forEach((d) => {
        d.style.display = 'none'; // Oculta todos os menus de autocomplete abertos
      });
    }
  });
});
