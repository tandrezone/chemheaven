/**
 * CartOfficer – public/js/cart.js
 *
 * Handles all client-side cart interactions:
 *  – "Add to cart" buttons
 *  – Opening / closing the cart sidebar
 *  – Updating quantities
 *  – Deleting items
 *  – Clearing the cart
 *  – Creating an order (POST to orderRoute)
 *
 * Configuration (set before including this script, or use data attributes):
 *
 *   window.CartOfficer = {
 *     cartEndpoint : '/cart',        // URL of CartController::handle()
 *     orderRoute   : '/orders',      // URL to POST the order to
 *     currency     : 'USD',          // ISO 4217 code
 *     locale       : 'en-US',        // BCP 47 locale
 *   };
 */
(function () {
  'use strict';

  /* ── Config ───────────────────────────────────────────── */
  var cfg = Object.assign(
    {
      cartEndpoint: '/cart',
      orderRoute: '/orders',
      currency: 'USD',
      locale: 'en-US',
    },
    window.CartOfficer || {}
  );

  /* ── Helpers ──────────────────────────────────────────── */
  function fmt(amount) {
    return new Intl.NumberFormat(cfg.locale, {
      style: 'currency',
      currency: cfg.currency,
    }).format(amount);
  }

  /** Read the CSRF token from the meta tag. */
  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function ajax(body) {
    return fetch(cfg.cartEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(body),
    }).then(function (r) {
      if (!r.ok) throw new Error('Network error ' + r.status);
      return r.json();
    });
  }

  /* ── Toast ────────────────────────────────────────────── */
  var toastContainer = null;

  function toast(message, type) {
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.className = 'co-toast-container';
      document.body.appendChild(toastContainer);
    }
    var el = document.createElement('div');
    el.className = 'co-toast co-toast--' + (type || 'success');
    el.textContent = message;
    toastContainer.appendChild(el);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        el.classList.add('co-toast--show');
      });
    });
    setTimeout(function () {
      el.classList.remove('co-toast--show');
      setTimeout(function () { el.remove(); }, 400);
    }, 3000);
  }

  /* ── Sidebar / Overlay elements ───────────────────────── */
  var sidebar = document.getElementById('co-sidebar');
  var overlay = document.getElementById('co-overlay');
  var badge = document.getElementById('co-badge');
  var tbody = document.getElementById('co-cart-body');
  var totalEl = document.getElementById('co-cart-total');
  var orderBtn = document.getElementById('co-order-btn');
  var emptyEl = document.getElementById('co-cart-empty');
  var tableEl = document.getElementById('co-cart-table');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('co-open');
    if (overlay) overlay.classList.add('co-open');
    document.body.style.overflow = 'hidden';
    fetchCart();
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('co-open');
    if (overlay) overlay.classList.remove('co-open');
    document.body.style.overflow = '';
  }

  /* ── Render cart ──────────────────────────────────────── */
  function renderCart(data) {
    // Update badge
    if (badge) {
      badge.textContent = data.item_count || 0;
      badge.dataset.count = data.item_count || 0;
    }

    // Update total
    if (totalEl) {
      totalEl.textContent = fmt(data.total || 0);
    }

    // Enable / disable order button
    if (orderBtn) {
      orderBtn.disabled = !data.items || data.items.length === 0;
    }

    var isEmpty = !data.items || data.items.length === 0;

    if (emptyEl) emptyEl.style.display = isEmpty ? 'flex' : 'none';
    if (tableEl) tableEl.style.display = isEmpty ? 'none' : 'table';

    if (!tbody) return;

    tbody.innerHTML = '';
    (data.items || []).forEach(function (item) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' +
        '<div class="co-product-name">' + escHtml(item.product_name) + '</div>' +
        (item.product_variant
          ? '<div class="co-product-variant">' + escHtml(item.product_variant) + '</div>'
          : '') +
        '</td>' +
        '<td>' + fmt(item.price) + '</td>' +
        '<td>' +
        '<div class="co-qty">' +
        '<button class="co-qty__btn" data-key="' + escHtml(item.key) + '" data-delta="-1" aria-label="Decrease">&#8722;</button>' +
        '<input class="co-qty__input" type="number" min="1" value="' + item.quantity + '" data-key="' + escHtml(item.key) + '" aria-label="Quantity">' +
        '<button class="co-qty__btn" data-key="' + escHtml(item.key) + '" data-delta="1" aria-label="Increase">&#43;</button>' +
        '</div>' +
        '</td>' +
        '<td>' + fmt(item.line_total) + '</td>' +
        '<td>' +
        '<button class="co-delete-btn" data-key="' + escHtml(item.key) + '" aria-label="Remove item">' +
        '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>' +
        '</button>' +
        '</td>';
      tbody.appendChild(tr);
    });
  }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ── Fetch / refresh cart ─────────────────────────────── */
  function fetchCart() {
    fetch(cfg.cartEndpoint + '?action=get', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(renderCart)
      .catch(function (e) { toast('Could not load cart.', 'error'); });
  }

  /* ── Event delegation for sidebar ────────────────────── */
  if (sidebar) {
    sidebar.addEventListener('click', function (e) {
      // ── Quantity ± buttons
      var deltaBtn = e.target.closest('[data-delta]');
      if (deltaBtn) {
        var key = deltaBtn.dataset.key;
        var input = sidebar.querySelector('input.co-qty__input[data-key="' + key + '"]');
        if (input) {
          var newQty = Math.max(1, parseInt(input.value, 10) + parseInt(deltaBtn.dataset.delta, 10));
          input.value = newQty;
          ajax({ action: 'update', key: key, quantity: newQty })
            .then(renderCart)
            .catch(function () { toast('Update failed.', 'error'); });
        }
        return;
      }

      // ── Delete button
      var delBtn = e.target.closest('.co-delete-btn');
      if (delBtn) {
        ajax({ action: 'delete', key: delBtn.dataset.key })
          .then(function (data) { renderCart(data); toast('Item removed.'); })
          .catch(function () { toast('Could not remove item.', 'error'); });
        return;
      }

      // ── Order button
      if (e.target.closest('#co-order-btn')) {
        handleOrder();
        return;
      }

      // ── Clear button
      if (e.target.closest('#co-clear-btn')) {
        if (confirm('Clear the entire cart?')) {
          ajax({ action: 'clear' })
            .then(function (data) { renderCart(data); toast('Cart cleared.'); })
            .catch(function () { toast('Could not clear cart.', 'error'); });
        }
        return;
      }

      // ── Close button
      if (e.target.closest('.co-sidebar__close')) {
        closeSidebar();
        return;
      }
    });

    // Qty input – blur commit
    sidebar.addEventListener('change', function (e) {
      var input = e.target.closest('input.co-qty__input');
      if (input) {
        var qty = Math.max(1, parseInt(input.value, 10) || 1);
        input.value = qty;
        ajax({ action: 'update', key: input.dataset.key, quantity: qty })
          .then(renderCart)
          .catch(function () { toast('Update failed.', 'error'); });
      }
    });
  }

  /* ── Order ────────────────────────────────────────────── */
  function handleOrder() {
    if (orderBtn) orderBtn.disabled = true;

    ajax({ action: 'order' })
      .then(function (data) {
        if (data.error) {
          toast(data.error, 'error');
          if (orderBtn) orderBtn.disabled = false;
          return;
        }

        // Build a hidden form and POST the payload to the order route
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = data.redirect || cfg.orderRoute;
        form.style.display = 'none';

        var payloadInput = document.createElement('input');
        payloadInput.type = 'hidden';
        payloadInput.name = 'cart_payload';
        payloadInput.value = JSON.stringify(data.payload);
        form.appendChild(payloadInput);

        // CSRF token support
        var token = csrfToken();
        if (token) {
          var csrf = document.createElement('input');
          csrf.type = 'hidden';
          csrf.name = '_csrf_token';
          csrf.value = token;
          form.appendChild(csrf);
        }

        document.body.appendChild(form);
        form.submit();
      })
      .catch(function () {
        toast('Could not create order.', 'error');
        if (orderBtn) orderBtn.disabled = false;
      });
  }

  /* ── Add-to-cart buttons (document-wide delegation) ───── */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.co-add-btn');
    if (!btn) return;

    var productId = btn.dataset.productId || btn.dataset.id || '';
    var productVariant = btn.dataset.productVariant || btn.dataset.variant || '';
    var productName = btn.dataset.productName || btn.dataset.name || '';
    var price = parseFloat(btn.dataset.price || '0');
    var quantity = parseInt(btn.dataset.quantity || '1', 10);

    if (!productId || !productName) {
      toast('Product data is missing.', 'error');
      return;
    }

    btn.classList.add('co-loading');

    ajax({
      action: 'add',
      product_id: productId,
      product_variant: productVariant,
      product_name: productName,
      price: price,
      quantity: quantity,
    })
      .then(function (data) {
        if (data.error) {
          toast(data.error, 'error');
        } else {
          renderCart(data);
          toast('Added to cart: ' + productName);
        }
      })
      .catch(function () { toast('Could not add to cart.', 'error'); })
      .finally(function () { btn.classList.remove('co-loading'); });
  });

  /* ── Cart icon button ─────────────────────────────────── */
  document.addEventListener('click', function (e) {
    if (e.target.closest('#co-cart-btn')) {
      openSidebar();
    }
  });

  /* ── Close on overlay click ───────────────────────────── */
  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  /* ── Close on Escape ──────────────────────────────────── */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });

  /* ── Initial badge load ───────────────────────────────── */
  fetchCart();

})();
