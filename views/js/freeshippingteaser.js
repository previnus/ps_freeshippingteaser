(function () {
  'use strict';

  var ajaxUrl = null;
  var miniObserver = null;

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
  }

  function buildInnerHtml(data) {
    if (!data || !data.threshold) { return ''; }
    if (data.qualified) {
      return (
        '<div class="fst-success">' +
          '<span>' + escapeHtml(data.success_text) + '</span>' +
        '</div>'
      );
    }
    var barClass = data.percent >= 100 ? 'fst-bar fst-bar--complete' : 'fst-bar';
    return (
      '<div class="fst-bar-wrap">' +
        '<div class="' + barClass + '" style="width:' + data.percent + '%"></div>' +
      '</div>' +
      '<p class="fst-message">' + escapeHtml(data.teaser_text) + '</p>'
    );
  }

  function getAjaxUrl() {
    if (ajaxUrl) { return ajaxUrl; }
    var el = document.querySelector('.freeshipping-teaser[data-ajax-url]');
    if (el) { ajaxUrl = el.getAttribute('data-ajax-url'); }
    return ajaxUrl;
  }

  /* --- Cart page: move rendered teaser above the items list --- */
  function repositionCartPage() {
    var teaser = document.querySelector('.freeshipping-teaser');
    if (!teaser) { return; }
    var anchor = document.querySelector('.cart-items, .cart-overview');
    if (anchor && anchor.parentNode) {
      anchor.parentNode.insertBefore(teaser, anchor);
    }
  }

  /*
   * Find a suitable insertion slot inside the visible mini/sidebar cart.
   * Tries every common selector across PS Classic, blockcart variants, and
   * popular sidebar-cart themes.
   */
  function findMiniCartSlot() {
    var candidates = [
      /* PS Classic blockcart modal */
      '#blockcart-modal .modal-body',
      '#blockcart-modal .cart-content',
      /* Slide-out / drawer cart variants */
      '.cart-sidebar .cart-body',
      '.cart-sidebar .cart-products',
      '.cart-drawer .cart-body',
      '.cart-drawer .cart-products',
      /* Generic offcanvas patterns */
      '[id*="cart"][class*="sidebar"] .cart-products',
      '[id*="cart"][class*="modal"] .modal-body',
      '[id*="cart"][class*="offcanvas"] .offcanvas-body',
      /* Fallback: any visible blockcart container */
      '.blockcart .modal-body',
      '.blockcart-content',
    ];

    for (var i = 0; i < candidates.length; i++) {
      var el = document.querySelector(candidates[i]);
      if (el) { return el; }
    }
    return null;
  }

  function injectOrUpdateMini(innerHtml) {
    var existing = document.querySelector('.fst-mini');
    if (existing) {
      existing.innerHTML = innerHtml;
      return;
    }

    var slot = findMiniCartSlot();
    if (!slot) { return; }

    var mini = document.createElement('div');
    mini.className = 'freeshipping-teaser fst-mini';
    var url = getAjaxUrl();
    if (url) { mini.setAttribute('data-ajax-url', url); }
    mini.innerHTML = innerHtml;
    slot.insertAdjacentElement('afterbegin', mini);
  }

  /* --- Fetch fresh data and push to all teaser containers --- */
  function fetchAndUpdate() {
    var url = getAjaxUrl();
    if (!url) { return; }
    if (typeof prestashop === 'undefined' || !prestashop.static_token) { return; }

    var fd = new FormData();
    fd.append('token', prestashop.static_token);

    fetch(url, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var html = buildInnerHtml(data);

        /* Update every server-rendered teaser on the page */
        document.querySelectorAll('.freeshipping-teaser').forEach(function (el) {
          el.innerHTML = html;
        });

        /* Inject / update in mini cart */
        injectOrUpdateMini(html);
      })
      .catch(function () { /* non-critical */ });
  }

  /*
   * Watch the mini cart container for visibility changes (class / style).
   * The blockcart sidebar is typically already in the DOM, just hidden — so
   * childList MutationObserver won't catch it opening; attribute changes will.
   */
  function observeMiniCart() {
    /* Try to find the top-level mini cart wrapper to observe */
    var wrappers = [
      document.getElementById('blockcart-modal'),
      document.querySelector('.cart-sidebar'),
      document.querySelector('.cart-drawer'),
      document.querySelector('[id*="cart"][class*="offcanvas"]'),
    ];

    for (var i = 0; i < wrappers.length; i++) {
      if (!wrappers[i]) { continue; }
      (function (wrapper) {
        var obs = new MutationObserver(function () {
          var visible =
            wrapper.classList.contains('show') ||
            wrapper.classList.contains('active') ||
            wrapper.classList.contains('is-open') ||
            (wrapper.style.display !== '' && wrapper.style.display !== 'none') ||
            wrapper.offsetParent !== null;

          if (visible) {
            setTimeout(fetchAndUpdate, 100);
          }
        });
        obs.observe(wrapper, { attributes: true, attributeFilter: ['class', 'style'] });
      })(wrappers[i]);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    /* Capture ajax url from PHP-rendered teaser */
    var rendered = document.querySelector('.freeshipping-teaser[data-ajax-url]');
    if (rendered) { ajaxUrl = rendered.getAttribute('data-ajax-url'); }

    /* Reposition on full cart page */
    if (document.querySelector('.cart-items, .cart-overview')) {
      repositionCartPage();
    }

    /* Watch mini cart wrapper for open/close */
    observeMiniCart();

    if (typeof prestashop === 'undefined' || typeof prestashop.on !== 'function') {
      return;
    }

    prestashop.on('updateCart', function () {
      /* Remove stale mini teaser so injectOrUpdateMini re-detects the slot */
      var stale = document.querySelector('.fst-mini');
      if (stale) { stale.remove(); }
      setTimeout(fetchAndUpdate, 250);
    });
  });
}());
