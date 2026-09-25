function getProductsList() {
  try {
    const attr = document.body?.dataset?.products;
    if (attr) {
      const parsed = JSON.parse(attr);
      if (Array.isArray(parsed) && parsed.length > 0) return parsed;
    }
  } catch (e) {}
  return [];
}

const CART_KEY = 'techflow_cart_v1';
const CHECKOUT_STEPS = ['Carrinho', 'Endereço', 'Revisão', 'Concluir'];
const CHECKOUT_STATE = {
  step: 0,
  shipping: 'padrao',
  payment: 'cartao',
  address: {
    nome: '',
    email: '',
    telefone: '',
    cep: '',
    rua: '',
    numero: '',
    cidade: '',
    estado: ''
  }
};

const money = (value) => new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL'
}).format(Number(value) || 0);

function requireLoginForPurchase() {
  const body = document.body;
  if (!body || body.dataset.loggedIn !== '1') {
    const base = body?.dataset.base || '';
    window.location.href = `${base}/pages/login.php?redirect=carrinho`;
    return false;
  }
  return true;
}

function showCheckoutMessage(message, title = 'Atenção') {
  const el = document.getElementById('checkout-message');
  if (!el) return;

  const titleEl = document.getElementById('checkout-message-title');
  const textEl = document.getElementById('checkout-message-text');
  if (titleEl) titleEl.textContent = title;
  if (textEl) textEl.textContent = message;
  el.classList.remove('hidden');
}

function hideCheckoutMessage() {
  const el = document.getElementById('checkout-message');
  if (el) el.classList.add('hidden');
}

function getCart() {
  try {
    const storedValue = localStorage.getItem(CART_KEY);
    const hasStoredCart = storedValue !== null;
    const stored = JSON.parse(storedValue || '{}');
    const cart = {};
    Object.entries(stored).forEach(([key, value]) => {
      const id = Number(key);
      const qty = Number(value);
      if (Number.isFinite(id) && id > 0 && Number.isFinite(qty) && qty > 0) {
        cart[id] = Math.min(20, Math.max(1, Math.round(qty)));
      }
    });

    const sessionCart = document.body?.dataset?.sessionCart ? JSON.parse(document.body.dataset.sessionCart) : {};
    const hasSessionItems = Object.keys(sessionCart || {}).length > 0;
    if (!hasStoredCart && hasSessionItems) {
      localStorage.setItem(CART_KEY, JSON.stringify(sessionCart));
      return sessionCart;
    }

    return cart;
  } catch (error) {
    const sessionCart = document.body?.dataset?.sessionCart ? JSON.parse(document.body.dataset.sessionCart) : {};
    return sessionCart || {};
  }
}

function sanitizeCart() {
  const cart = getCart();
  const valid = {};
  let invalid = false;
  const products = getProductsList();

  if (!products || products.length === 0) {
    return cart;
  }

  Object.entries(cart).forEach(([productId, qty]) => {
    const product = products.find((item) => Number(item.id) === Number(productId));
    if (!product) {
      invalid = true;
      return;
    }
    valid[productId] = Math.min(20, Math.max(1, Number(qty) || 1));
  });

  if (Object.keys(valid).length !== Object.keys(cart).length) {
    localStorage.setItem(CART_KEY, JSON.stringify(valid));
  }

  return valid;
}

async function saveCart(cart) {
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  updateCartBadge();

  const base = document.body?.dataset?.base || '';
  if (base) {
    try {
      await fetch(`${base}/php/cart-sync.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ csrf: document.body?.dataset?.csrf || '', cart: JSON.stringify(cart) })
      });
    } catch (e) {}
  }
}

async function cartAdd(productId, quantity = 1) {
  const id = Number(productId);
  const products = getProductsList();
  const product = products.find((item) => Number(item.id) === id);
  if (!product) {
    showCheckoutMessage('Este produto não existe mais ou está indisponível no momento.');
    return false;
  }

  const cart = sanitizeCart();
  const qty = Number(quantity) || 1;
  const currentQty = Number(cart[id] || 0);
  cart[id] = Math.min(20, currentQty + qty);
  await saveCart(cart);
  return true;
}

function cartSet(productId, quantity) {
  const id = Number(productId);
  const products = getProductsList();
  const product = products.find((item) => Number(item.id) === id);
  if (!product) {
    showCheckoutMessage('Produto inválido ou indisponível no momento.');
    return false;
  }

  const cart = sanitizeCart();
  const qty = Number(quantity) || 0;
  if (qty <= 0) {
    delete cart[id];
  } else {
    cart[id] = Math.min(20, Math.max(1, Math.round(qty)));
  }
  saveCart(cart);
  return true;
}

function cartRemove(productId) {
  const cart = sanitizeCart();
  delete cart[Number(productId)];
  saveCart(cart);
}

function cartClear() {
  saveCart({});
  CHECKOUT_STATE.step = 0;
  renderCartPage();
}

function cartCount() {
  return Object.values(sanitizeCart()).reduce((sum, qty) => sum + Number(qty || 0), 0);
}

function getCartItems() {
  const cart = sanitizeCart();
  const products = getProductsList();
  return Object.entries(cart)
    .map(([productId, qty]) => {
      const product = products.find((item) => Number(item.id) === Number(productId));
      if (!product) return null;
      return {
        ...product,
        qty: Number(qty),
        subtotal: product.preco * Number(qty),
      };
    })
    .filter(Boolean);
}

function updateCartBadge() {
  const count = cartCount();
  document.querySelectorAll('.cart-badge').forEach((badge) => {
    badge.textContent = String(count);
    badge.hidden = count <= 0;
  });

  const cartLinks = document.querySelectorAll('.cart-link');
  cartLinks.forEach((link) => {
    const label = link.getAttribute('aria-label') || 'Carrinho';
    link.setAttribute('aria-label', label.replace(/\d+ item\(ns\)/, `${count} item(ns)`));
  });
}

function getShippingPrice(method) {
  return method === 'expressa' ? 19.90 : 0;
}

function getPaymentDiscount(method) {
  return method === 'pix' ? 0.10 : 0;
}

function updateCheckoutSummary(items) {
  const subtotal = items.reduce((sum, item) => sum + item.subtotal, 0);
  const shipping = getShippingPrice(CHECKOUT_STATE.shipping);
  const discount = getPaymentDiscount(CHECKOUT_STATE.payment) * subtotal;
  const total = Math.max(0, subtotal + shipping - discount);

  const subtotalEl = document.getElementById('cart-subtotal');
  const totalEl = document.getElementById('cart-total');
  const shippingEl = document.getElementById('cart-shipping');
  const summaryNoteEl = document.getElementById('summary-note');
  const summaryLabelEl = document.getElementById('summary-label');

  if (summaryLabelEl) {
    summaryLabelEl.textContent = `Valor dos produtos (${items.length}):`;
  }

  if (subtotalEl) subtotalEl.textContent = money(subtotal);
  if (totalEl) totalEl.textContent = money(total);
  if (shippingEl) shippingEl.textContent = shipping === 0 ? 'Grátis' : money(shipping);
  if (summaryNoteEl) {
    if (CHECKOUT_STATE.payment === 'pix') {
      summaryNoteEl.textContent = `À vista no Pix: ${money(total)}`;
    } else {
      summaryNoteEl.textContent = `Em até 12x sem juros: ${money(total)}`;
    }
  }

  return { subtotal, shipping, discount, total };
}

function updateCheckoutStepsUI() {
  document.querySelectorAll('.step').forEach((step, index) => {
    step.classList.toggle('active', index <= CHECKOUT_STATE.step);
  });
}

function validateAddressForm() {
  const addr = CHECKOUT_STATE.address;

  const cepEl = document.getElementById('cart_cep');
  const ruaEl = document.getElementById('cart_rua');
  const numEl = document.getElementById('cart_numero');
  const cidEl = document.getElementById('cart_cidade');
  const estEl = document.getElementById('cart_estado');

  if (cepEl) addr.cep = cepEl.value.trim();
  if (ruaEl) addr.rua = ruaEl.value.trim();
  if (numEl) addr.numero = numEl.value.trim();
  if (cidEl) addr.cidade = cidEl.value.trim();
  if (estEl) addr.estado = estEl.value.trim();

  const cep = (addr.cep || '').trim();
  const rua = (addr.rua || '').trim();
  const numero = (addr.numero || '').trim();

  if (!cep || !rua || !numero) {
    showCheckoutMessage('Por favor, informe ou selecione o endereço de entrega (CEP, rua e número).', 'Endereço Incompleto');
    return false;
  }

  return true;
}

function renderCartStepContent(items) {
  const container = document.getElementById('cart-items');
  if (!container) return;

  const step = CHECKOUT_STATE.step;

  if (!items.length) {
    container.innerHTML = `
      <div class="empty-cart">
        <h3>Seu carrinho está vazio</h3>
        <p>Adicione algum produto para continuar a compra.</p>
        <a class="btn secondary" href="${document.body.dataset.base || ''}/pages/produtos.php">Ver produtos</a>
      </div>
    `;
    return;
  }

  if (step === 0) {
    container.innerHTML = `
      <div class="checkout-options" style="margin-bottom: 20px;">
        <div class="option-group">
          <h4>Opção de Frete</h4>
          <div class="option-list">
            <div class="option-item">
              <label>
                <input type="radio" name="frete-inicio" value="padrao" ${CHECKOUT_STATE.shipping === 'padrao' ? 'checked' : ''}>
                <span><strong>Entrega padrão</strong><br><small>3 a 5 dias úteis</small></span>
              </label>
              <strong>Grátis</strong>
            </div>
            <div class="option-item">
              <label>
                <input type="radio" name="frete-inicio" value="expressa" ${CHECKOUT_STATE.shipping === 'expressa' ? 'checked' : ''}>
                <span><strong>Entrega expressa</strong><br><small>1 a 2 dias úteis</small></span>
              </label>
              <strong>R$ 19,90</strong>
            </div>
          </div>
        </div>

        <div class="option-group">
          <h4>Forma de Pagamento</h4>
          <div class="option-list">
            <div class="option-item">
              <label>
                <input type="radio" name="pagamento-inicio" value="cartao" ${CHECKOUT_STATE.payment === 'cartao' ? 'checked' : ''}>
                <span><strong>Cartão de crédito</strong><br><small>até 12x sem juros</small></span>
              </label>
            </div>
            <div class="option-item">
              <label>
                <input type="radio" name="pagamento-inicio" value="pix" ${CHECKOUT_STATE.payment === 'pix' ? 'checked' : ''}>
                <span><strong>Pix</strong><br><small>10% de desconto</small></span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <h3 style="margin-bottom: 12px; font-size: 1.05rem;">Itens selecionados</h3>

      ${items.map((item) => `
        <article class="cart-item" data-product-id="${item.id}">
          <div class="cart-item-image">
            <img src="${document.body.dataset.base || ''}/assets/img/${item.imagem}" alt="${item.nome}">
          </div>
          <div class="cart-item-body">
            <h4>${item.nome}</h4>
            <p class="cart-item-meta">${item.categoria}</p>
            <div class="cart-item-price">${money(item.preco)}</div>
          </div>
          <div class="cart-item-actions">
            <div class="qty-control" aria-label="Controle de quantidade">
              <button type="button" data-action="decrease" data-id="${item.id}" aria-label="Diminuir quantidade">−</button>
              <span>${item.qty}</span>
              <button type="button" data-action="increase" data-id="${item.id}" aria-label="Aumentar quantidade">+</button>
            </div>
            <button type="button" class="link-like" data-action="remove" data-id="${item.id}">Remover</button>
          </div>
        </article>
      `).join('')}
    `;

    document.querySelectorAll('input[name="frete-inicio"]').forEach((input) => {
      input.addEventListener('change', (event) => {
        CHECKOUT_STATE.shipping = event.target.value;
        renderCartPage();
      });
    });

    document.querySelectorAll('input[name="pagamento-inicio"]').forEach((input) => {
      input.addEventListener('change', (event) => {
        CHECKOUT_STATE.payment = event.target.value;
        renderCartPage();
      });
    });

    return;
  }

  if (step === 1) {
    if (!CHECKOUT_STATE.address.nome && document.body.dataset.userName) {
      CHECKOUT_STATE.address.nome = document.body.dataset.userName;
    }
    if (!CHECKOUT_STATE.address.email && document.body.dataset.userEmail) {
      CHECKOUT_STATE.address.email = document.body.dataset.userEmail;
    }
    if (!CHECKOUT_STATE.address.telefone && document.body.dataset.userPhone) {
      CHECKOUT_STATE.address.telefone = document.body.dataset.userPhone;
    }

    const savedAddressesStr = document.body?.dataset?.userAddresses;
    let savedAddresses = [];
    try {
      if (savedAddressesStr) savedAddresses = JSON.parse(savedAddressesStr);
    } catch(e) {}

    if (savedAddresses.length > 0 && (!CHECKOUT_STATE.address.cep || !CHECKOUT_STATE.address.rua)) {
      const first = savedAddresses[0];
      CHECKOUT_STATE.address.cep = first.cep;
      CHECKOUT_STATE.address.rua = first.rua;
      CHECKOUT_STATE.address.numero = first.numero;
      CHECKOUT_STATE.address.cidade = first.cidade;
      CHECKOUT_STATE.address.estado = first.estado;
    }

    const base = document.body?.dataset?.base || '';

    if (savedAddresses.length === 0) {
      container.innerHTML = `
        <div class="checkout-step-panel">
          <div>
            <h3>Endereço de entrega</h3>
            <p>Preencha abaixo o seu endereço para entrega do pedido.</p>
          </div>
          <div style="margin-top: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div class="field" style="margin: 0; grid-column: span 2;">
              <label for="cart_rua" style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 4px; display: block;">Rua / Logradouro *</label>
              <input type="text" id="cart_rua" value="${CHECKOUT_STATE.address.rua || document.body.dataset.userRua || ''}" placeholder="Ex: Av. Paulista" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text);">
            </div>
            <div class="field" style="margin: 0;">
              <label for="cart_numero" style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 4px; display: block;">Número *</label>
              <input type="text" id="cart_numero" value="${CHECKOUT_STATE.address.numero || document.body.dataset.userNumero || ''}" placeholder="Ex: 1000" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text);">
            </div>
            <div class="field" style="margin: 0;">
              <label for="cart_cep" style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 4px; display: block;">CEP *</label>
              <input type="text" id="cart_cep" value="${CHECKOUT_STATE.address.cep || document.body.dataset.userCep || ''}" placeholder="00000-000" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text);">
            </div>
            <div class="field" style="margin: 0;">
              <label for="cart_cidade" style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 4px; display: block;">Cidade</label>
              <input type="text" id="cart_cidade" value="${CHECKOUT_STATE.address.cidade || document.body.dataset.userCidade || ''}" placeholder="São Paulo" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text);">
            </div>
            <div class="field" style="margin: 0;">
              <label for="cart_estado" style="font-size: 0.85rem; font-weight: 600; color: var(--muted); margin-bottom: 4px; display: block;">Estado (UF)</label>
              <input type="text" id="cart_estado" value="${CHECKOUT_STATE.address.estado || document.body.dataset.userEstado || ''}" placeholder="SP" maxlength="2" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--panel); color: var(--text);">
            </div>
          </div>
        </div>
      `;
      return;
    }

    container.innerHTML = `
      <div class="checkout-step-panel">
        <div>
          <h3>Escolha o endereço de entrega</h3>
          <p>Selecione um dos seus endereços cadastrados abaixo para receber seu pedido.</p>
        </div>

        <div class="address-grid" style="margin-top: 16px;">
          ${savedAddresses.map((addr) => {
            const isSelected = CHECKOUT_STATE.address.cep === addr.cep && CHECKOUT_STATE.address.rua === addr.rua && CHECKOUT_STATE.address.numero === addr.numero;
            return `
              <div class="address-card ${isSelected ? 'selected' : ''}" data-select-address='${JSON.stringify(addr)}' style="cursor: pointer;">
                <div>
                  <div class="address-card-header">
                    <span class="address-card-title">${isSelected ? '✅ Endereço Selecionado' : '📍 Endereço'}</span>
                  </div>
                  <div class="address-card-body">
                    <strong>${addr.rua}, nº ${addr.numero}</strong><br>
                    ${addr.cidade} / ${addr.estado}<br>
                    <span>CEP: ${addr.cep}</span>
                  </div>
                </div>
                <button type="button" class="btn btn-sm ${isSelected ? '' : 'secondary'}" style="width: 100%; margin-top: 8px;">
                  ${isSelected ? 'Selecionado' : 'Usar este endereço'}
                </button>
              </div>
            `;
          }).join('')}
        </div>

        <div style="margin-top: 20px; text-align: right;">
          <a href="${base}/pages/enderecos.php" class="link-like" style="font-size: 0.88rem; color: var(--accent-2);">➕ Gerenciar / Cadastrar mais endereços</a>
        </div>
      </div>
    `;

    document.querySelectorAll('[data-select-address]').forEach((card) => {
      card.addEventListener('click', () => {
        try {
          const addr = JSON.parse(card.dataset.selectAddress);
          CHECKOUT_STATE.address.cep = addr.cep;
          CHECKOUT_STATE.address.rua = addr.rua;
          CHECKOUT_STATE.address.numero = addr.numero;
          CHECKOUT_STATE.address.cidade = addr.cidade;
          CHECKOUT_STATE.address.estado = addr.estado;
          renderCartPage();
        } catch(e) {}
      });
    });

    return;
  }

  if (step === 2) {
    const { subtotal, shipping, discount, total } = updateCheckoutSummary(items);
    const addr = CHECKOUT_STATE.address;
    const nome = addr.nome || 'Cliente';
    const email = addr.email || '-';
    const telefone = addr.telefone || '-';
    const rua = addr.rua || '';
    const numero = addr.numero || '';
    const cidade = addr.cidade || '';
    const estado = addr.estado || '';
    const cep = addr.cep || '';

    container.innerHTML = `
      <div class="checkout-step-panel">
        <div>
          <h3>Revisão do pedido</h3>
          <p>Confirme seus dados e produtos antes de finalizar a compra.</p>
        </div>
        <div class="checkout-summary-box">
          <div><span>Cliente</span><strong>${nome}</strong></div>
          <div><span>E-mail</span><strong>${email}</strong></div>
          <div><span>Telefone</span><strong>${telefone}</strong></div>
          <div><span>Endereço de Entrega</span><strong>${rua}, nº ${numero} — ${cidade}/${estado} (CEP: ${cep})</strong></div>
          <div><span>Forma de Frete</span><strong>${CHECKOUT_STATE.shipping === 'expressa' ? 'Entrega Expressa (R$ 19,90)' : 'Entrega Padrão (Grátis)'}</strong></div>
          <div><span>Forma de Pagamento</span><strong>${CHECKOUT_STATE.payment === 'pix' ? 'Pix (10% OFF)' : 'Cartão de Crédito'}</strong></div>
          <div><span>Subtotal</span><strong>${money(subtotal)}</strong></div>
          <div><span>Total</span><strong>${money(total)}</strong></div>
        </div>
      </div>
    `;
    return;
  }

  container.innerHTML = `
    <div class="checkout-success">
      <h3>Pedido concluído com sucesso!</h3>
      <p>Obrigado por comprar na TechFlow.</p>
      <p>Seu pedido e dados de entrega foram salvos no banco de dados e já estão disponíveis em "Minhas compras".</p>
    </div>
  `;
}

function renderCartPage() {
  const items = getCartItems();
  const checkoutButton = document.querySelector('[data-cart-checkout]');
  const backButton = document.querySelector('[data-cart-back]');

  updateCheckoutSummary(items);
  updateCheckoutStepsUI();
  renderCartStepContent(items);

  if (checkoutButton) {
    const buttonLabels = ['Continuar', 'Continuar', 'Finalizar compra', 'Novo pedido'];
    checkoutButton.textContent = buttonLabels[CHECKOUT_STATE.step] || 'Continuar';
    checkoutButton.disabled = false;
  }

  if (backButton) {
    if (CHECKOUT_STATE.step === 0) {
      backButton.textContent = 'Voltar';
    } else {
      backButton.textContent = 'Voltar';
    }
  }
}

async function handleCartAction(id, action) {
  if (action === 'increase') {
    await cartAdd(id, 1);
  } else if (action === 'decrease') {
    const cart = sanitizeCart();
    const current = Number(cart[id] || 0);
    if (current <= 1) {
      await cartRemove(id);
    } else {
      await cartSet(id, current - 1);
    }
  } else if (action === 'remove') {
    await cartRemove(id);
  }
  renderCartPage();
}

async function finalizeCheckout() {
  const items = getCartItems();
  if (!items.length) {
    showCheckoutMessage('Seu carrinho está vazio. Adicione pelo menos um produto antes de continuar.');
    return;
  }

  const checkoutButton = document.querySelector('[data-cart-checkout]');
  if (checkoutButton) {
    checkoutButton.disabled = true;
    checkoutButton.textContent = 'Processando pedido...';
  }

  const base = document.body.dataset.base || '';
  try {
    const cartMap = {};
    items.forEach((item) => {
      cartMap[String(item.id)] = item.qty;
    });

    const payload = new URLSearchParams({
      csrf: document.body?.dataset?.csrf || '',
      cart: JSON.stringify(cartMap),
      address: JSON.stringify(CHECKOUT_STATE.address)
    });
    const response = await fetch(`${base}/php/finalizar-pedido.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: payload.toString()
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.mensagem || 'Não foi possível concluir a compra.');
    }

    localStorage.removeItem(CART_KEY);
    updateCartBadge();
    CHECKOUT_STATE.step = 3;

    // Redireciona diretamente para o painel com o histórico de compras
    const redirectUrl = data.redirect || `${base}/pages/dashboard.php`;
    window.location.href = redirectUrl;
  } catch (error) {
    if (checkoutButton) {
      checkoutButton.disabled = false;
      checkoutButton.textContent = 'Finalizar compra';
    }
    showCheckoutMessage(error.message || 'Não foi possível concluir a compra. Verifique sua conexão e tente novamente.', 'Erro no Pedido');
  }
}

function goToNextStep() {
  if (!requireLoginForPurchase()) {
    return;
  }

  const items = getCartItems();
  if (!items.length) {
    showCheckoutMessage('Seu carrinho está vazio. Adicione pelo menos um produto antes de continuar.');
    return;
  }

  if (CHECKOUT_STATE.step === 1 && !validateAddressForm()) {
    return;
  }

  if (CHECKOUT_STATE.step === CHECKOUT_STEPS.length - 2) {
    finalizeCheckout();
    return;
  }

  if (CHECKOUT_STATE.step < CHECKOUT_STEPS.length - 1) {
    CHECKOUT_STATE.step += 1;
    renderCartPage();
  }
}

function goToPreviousStep() {
  if (CHECKOUT_STATE.step === 0) {
    const base = document.body.dataset.base || '';
    window.location.href = `${base}/pages/produtos.php`;
    return;
  }

  CHECKOUT_STATE.step = Math.max(0, CHECKOUT_STATE.step - 1);
  renderCartPage();
}

function bindCartInteractions() {
  document.querySelectorAll('[data-cart-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const idInput = form.querySelector('input[name="id"]');
      const qtyInput = form.querySelector('input[name="qty"]');
      const id = Number(idInput?.value || 0);
      const qty = Number(qtyInput?.value || 1);

      if (!id || id <= 0) {
        showCheckoutMessage('Produto inválido. Não foi possível adicionar este item ao carrinho.');
        return;
      }

      const added = await cartAdd(id, qty);
      if (added) {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
          const originalLabel = submitButton.textContent;
          submitButton.textContent = 'Adicionado';
          submitButton.disabled = true;
          window.setTimeout(() => {
            submitButton.textContent = originalLabel;
            submitButton.disabled = false;
          }, 1200);
        }
      }
    });
  });

  document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-action]');
    if (!target) return;
    const id = Number(target.dataset.id || 0);
    const action = target.dataset.action;
    if (target.disabled) return;
    target.disabled = true;
    handleCartAction(id, action).finally(() => {
      target.disabled = false;
    });
  });

  const closeButtons = document.querySelectorAll('[data-close-message]');
  closeButtons.forEach((button) => {
    button.addEventListener('click', hideCheckoutMessage);
  });

  const clearButton = document.querySelector('[data-cart-clear]');
  if (clearButton) {
    clearButton.addEventListener('click', () => {
      cartClear();
    });
  }

  const backButton = document.querySelector('[data-cart-back]');
  if (backButton) {
    backButton.addEventListener('click', goToPreviousStep);
  }

  const checkoutButton = document.querySelector('[data-cart-checkout]');
  if (checkoutButton) {
    checkoutButton.addEventListener('click', () => {
      if (CHECKOUT_STATE.step === 5) {
        CHECKOUT_STATE.step = 0;
        CHECKOUT_STATE.shipping = 'padrao';
        CHECKOUT_STATE.payment = 'cartao';
        CHECKOUT_STATE.address = {
          nome: '',
          email: '',
          telefone: '',
          cep: '',
          rua: '',
          numero: '',
          cidade: '',
          estado: ''
        };
        renderCartPage();
        return;
      }

      goToNextStep();
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  updateCartBadge();
  bindCartInteractions();
  renderCartPage();
});

// Expõe as operações para integrações e testes sem depender do escopo do script.
window.TechFlowCart = { getCart, cartAdd, cartSet, cartRemove, cartClear, cartCount, renderCartPage };
