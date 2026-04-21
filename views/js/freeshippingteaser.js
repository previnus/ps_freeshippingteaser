(function () {
  'use strict';

  /* ── Helpers ─────────────────────────────────────────────── */

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
  }

  function formatMoney(sign, amount) {
    return sign + amount.toFixed(2);
  }

  function replaceAll(str, token, value) {
    return str.split(token).join(value);
  }

  function replaceTokens(tpl, remaining, threshold, sign) {
    tpl = replaceAll(tpl, '{amount}',    formatMoney(sign, remaining));
    tpl = replaceAll(tpl, '{threshold}', formatMoney(sign, threshold));
    tpl = replaceAll(tpl, '{currency}',  sign);
    return tpl;
  }

  /* Build inner HTML purely from local data — no HTTP request */
  function buildHtmlLocal(threshold, cartTotal, currency, teaserTpl, successTpl) {
    if (!threshold) { return ''; }
    var qualified  = cartTotal >= threshold;
    var remaining  = qualified ? 0 : Math.round((threshold - cartTotal) * 100) / 100;
    var percent    = Math.min(100, Math.floor((cartTotal / threshold) * 100));
    var barClass   = percent >= 100 ? 'fst-bar fst-bar--complete' : 'fst-bar';

    if (qualified) {
      return (
        '<div class="fst-success"><span>' +
          escapeHtml(replaceTokens(successTpl, remaining, threshold, currency)) +
        '</span></div>'
      );
    }
    return (
      '<div class="fst-bar-wrap">' +
        '<div class="' + barClass + '" style="width:' + percent + '%"></div>' +
      '</div>' +
      '<p class="fst-message">' +
        escapeHtml(replaceTokens(teaserTpl, remaining, threshold, currency)) +
      '</p>'
    );
  }

  /* Read config embedded by PHP in data attributes */
  function getConfig() {
    var el = document.querySelector('.freeshipping-teaser[data-threshold]');
    if (!el) { return null; }
    return {
      ajaxUrl:    el.getAttribute('data-ajax-url')    || '',
      threshold:  parseFloat(el.getAttribute('data-threshold'))  || 0,
      currency:   el.getAttribute('data-currency')    || '',
      teaserTpl:  el.getAttribute('data-teaser-tpl')  || '',
      successTpl: el.getAttribute('data-success-tpl') || '',
    };
  }

  /* ── Cart-total extraction from PS updateCart event ─────── */

  function cartTotalFromEvent(event) {
    try {
      var cart = event && event.resp && event.resp.cart;
      if (!cart) { return null; }
      /* PS9 Classic: subtotals.products.amount */
      if (cart.subtotals && cart.subtotals.products) {
        var v = parseFloat(cart.subtotals.products.amount);
        if (!isNaN(v)) { return v; }
      }
      /* Fallback: totals.total_excluding_tax */
      if (cart.totals && cart.totals.total_excluding_tax) {
        var v2 = parseFloat(cart.totals.total_excluding_tax.amount);
        if (!isNaN(v2)) { return v2; }
      }
    } catch (e) { /* ignore */ }
    return null;
  }

  /* ── DOM update helpers ──────────────────────────────────── */

  /* Excludes .fst-mini — handled separately by injectOrUpdateElementorCart */
  function updateAllTeasers(html) {
    document.querySelectorAll('.freeshipping-teaser:not(.fst-mini)').forEach(function (el) {
      el.innerHTML = html;
    });
  }

  function injectOrUpdateElementorCart(html) {
    var existing = document.querySelector('.elementor-cart__main .fst-mini');
    if (existing) { existing.innerHTML = html; return; }

    var summary = document.querySelector('.elementor-cart__summary');
    if (!summary) { return; }

    var mini = document.createElement('div');
    mini.className = 'freeshipping-teaser fst-mini';
    var cfg = getConfig();
    if (cfg) { mini.setAttribute('data-ajax-url', cfg.ajaxUrl); }
    mini.innerHTML = html;
    summary.insertAdjacentElement('beforebegin', mini);
  }

  /* ── AJAX fetch (only used when Elementor drawer opens) ──── */

  function fetchAndUpdate(cfg) {
    if (!cfg || !cfg.ajaxUrl) { return; }
    if (typeof prestashop === 'undefined' || !prestashop.static_token) { return; }

    var fd = new FormData();
    fd.append('token', prestashop.static_token);

    fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.threshold) { return; }
        var html = buildHtmlLocal(
          data.threshold, data.cart_total,
          cfg.currency, cfg.teaserTpl, cfg.successTpl
        );
        updateAllTeasers(html);
        injectOrUpdateElementorCart(html);
      })
      .catch(function () { /* non-critical */ });
  }

  /* ── Cart-page repositioning ─────────────────────────────── */

  function repositionCartPage() {
    var teaser = document.querySelector('.freeshipping-teaser');
    if (!teaser) { return; }
    var anchor = document.querySelector('.cart-items, .cart-overview');
    if (anchor && anchor.parentNode) {
      anchor.parentNode.insertBefore(teaser, anchor);
    }
  }

  /* ── Elementor drawer observer ───────────────────────────── */

  function observeElementorCart(cfg) {
    var main = document.querySelector('.elementor-cart__main');
    if (!main) { return; }

    var widget   = main.closest('[data-widget_type]') || main.parentElement;
    if (!widget) { return; }

    var pending = false;
    var wasOpen = false;

    new MutationObserver(function () {
      if (pending) { return; }
      var isOpen = widget.classList.contains('elementor-cart--shown') ||
                   widget.classList.contains('elementor--shown') ||
                   main.classList.contains('elementor-cart--shown');

      if (isOpen && !wasOpen) {
        wasOpen = true;
        pending = true;
        setTimeout(function () { fetchAndUpdate(cfg); pending = false; }, 200);
      } else if (!isOpen) {
        wasOpen = false;
      }
    }).observe(widget, { attributes: true, attributeFilter: ['class', 'style'] });
  }

  /* ── Boot ────────────────────────────────────────────────── */

  document.addEventListener('DOMContentLoaded', function () {
    var cfg = getConfig();

    if (document.querySelector('.cart-items, .cart-overview')) {
      repositionCartPage();
    }

    observeElementorCart(cfg);

    if (typeof prestashop === 'undefined' || typeof prestashop.on !== 'function') {
      return;
    }

    prestashop.on('updateCart', function (event) {
      if (!cfg) { return; }

      /* Remove stale mini teaser — will be re-injected below */
      var stale = document.querySelector('.fst-mini');
      if (stale) { stale.remove(); }

      /* Get the new cart total from PS event data (no extra HTTP request) */
      var cartTotal = cartTotalFromEvent(event);
      if (cartTotal === null) { return; }

      var html = buildHtmlLocal(
        cfg.threshold, cartTotal,
        cfg.currency, cfg.teaserTpl, cfg.successTpl
      );
      updateAllTeasers(html);
      injectOrUpdateElementorCart(html);
    });
  });
}());
