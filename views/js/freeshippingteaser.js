(function () {
  'use strict';

  var ajaxUrl = null;

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
  }

  function buildInnerHtml(data) {
    if (!data || !data.threshold) {
      return '';
    }
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

  /* --- Cart-page: move rendered teaser to top of cart items --- */
  function repositionCartPage() {
    var teaser = document.querySelector('.freeshipping-teaser');
    if (!teaser) { return; }
    var anchor = document.querySelector('.cart-items, .cart-overview');
    if (anchor && anchor.parentNode) {
      anchor.parentNode.insertBefore(teaser, anchor);
    }
  }

  /* --- Mini cart: inject or update teaser inside the add-to-cart modal --- */
  function getOrCreateMiniTeaser() {
    var existing = document.querySelector('.fst-mini');
    if (existing) { return existing; }

    /* PS Classic: modal shown after adding a product */
    var slot = document.querySelector('#blockcart-modal .cart-content');
    if (!slot) {
      /* Fallback: cart preview dropdown */
      slot = document.querySelector('.cart-preview .cart-detailed-totals, .cart-preview .cart-subtotals');
    }
    if (!slot) { return null; }

    var mini = document.createElement('div');
    mini.className = 'freeshipping-teaser fst-mini';
    slot.insertAdjacentElement('afterbegin', mini);
    return mini;
  }

  /* --- Fetch fresh data and update all teaser containers --- */
  function fetchAndUpdate() {
    var url = getAjaxUrl();
    if (!url) { return; }
    if (typeof prestashop === 'undefined' || !prestashop.static_token) { return; }

    var formData = new FormData();
    formData.append('token', prestashop.static_token);

    fetch(url, { method: 'POST', body: formData })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        /* Update all server-rendered teasers */
        document.querySelectorAll('.freeshipping-teaser').forEach(function (el) {
          el.innerHTML = buildInnerHtml(data);
        });
        /* Inject / update mini cart teaser */
        var mini = getOrCreateMiniTeaser();
        if (mini) {
          mini.innerHTML = buildInnerHtml(data);
        }
      })
      .catch(function () { /* teaser is non-critical */ });
  }

  document.addEventListener('DOMContentLoaded', function () {
    /* Move teaser to top of cart page if we're on the cart */
    if (document.querySelector('.cart-items, .cart-overview')) {
      repositionCartPage();
    }

    if (typeof prestashop === 'undefined' || typeof prestashop.on !== 'function') {
      return;
    }

    prestashop.on('updateCart', function () {
      setTimeout(fetchAndUpdate, 200);
    });
  });
}());
