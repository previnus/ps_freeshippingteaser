(function () {
  'use strict';

  var ajaxUrl = null;

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
   * Inject or update the teaser inside the Elementor sidebar cart.
   * Target: between .elementor-cart__products and .elementor-cart__summary.
   */
  function injectOrUpdateElementorCart(innerHtml) {
    var existing = document.querySelector('.elementor-cart__main .fst-mini');
    if (existing) {
      existing.innerHTML = innerHtml;
      return;
    }

    /* Insert before the totals summary block */
    var summary = document.querySelector('.elementor-cart__summary');
    if (!summary) { return; }

    var mini = document.createElement('div');
    mini.className = 'freeshipping-teaser fst-mini';
    var url = getAjaxUrl();
    if (url) { mini.setAttribute('data-ajax-url', url); }
    mini.innerHTML = innerHtml;
    summary.insertAdjacentElement('beforebegin', mini);
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

        /* Elementor sidebar cart */
        injectOrUpdateElementorCart(html);
      })
      .catch(function () { /* non-critical */ });
  }

  /*
   * Elementor cart drawer: watch only the widget wrapper (not body) for the
   * open-state class toggle. A debounce flag prevents re-entry when our own
   * DOM injection triggers the observer.
   */
  function observeElementorCart() {
    var main = document.querySelector('.elementor-cart__main');
    if (!main) { return; }

    var widget = main.closest('[data-widget_type]') || main.parentElement;
    if (!widget) { return; }

    var pending = false;
    var wasOpen = false;

    new MutationObserver(function () {
      if (pending) { return; }
      var isOpen = widget.classList.contains('elementor-cart--shown') ||
                   widget.classList.contains('elementor--shown') ||
                   main.offsetParent !== null;

      /* Only fire on the transition from closed → open */
      if (isOpen && !wasOpen) {
        wasOpen = true;
        pending = true;
        setTimeout(function () {
          fetchAndUpdate();
          pending = false;
        }, 200);
      } else if (!isOpen) {
        wasOpen = false;
      }
    }).observe(widget, { attributes: true, attributeFilter: ['class', 'style'] });
  }

  document.addEventListener('DOMContentLoaded', function () {
    /* Capture ajax url from any PHP-rendered teaser */
    var rendered = document.querySelector('.freeshipping-teaser[data-ajax-url]');
    if (rendered) { ajaxUrl = rendered.getAttribute('data-ajax-url'); }

    /* Reposition on full cart page */
    if (document.querySelector('.cart-items, .cart-overview')) {
      repositionCartPage();
    }

    /* Watch Elementor sidebar cart for open event */
    observeElementorCart();

    if (typeof prestashop === 'undefined' || typeof prestashop.on !== 'function') {
      return;
    }

    prestashop.on('updateCart', function () {
      /* Remove stale mini teaser so the next fetchAndUpdate re-injects cleanly */
      var stale = document.querySelector('.fst-mini');
      if (stale) { stale.remove(); }
      setTimeout(fetchAndUpdate, 250);
    });
  });
}());
