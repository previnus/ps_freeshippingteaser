(function () {
  'use strict';

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
  }

  function renderTeaser(data) {
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

  function updateTeasers() {
    var containers = document.querySelectorAll('.freeshipping-teaser');
    if (!containers.length) { return; }

    var ajaxUrl = containers[0].getAttribute('data-ajax-url');
    if (!ajaxUrl) { return; }

    if (!prestashop.static_token) { return; }
    var formData = new FormData();
    formData.append('token', prestashop.static_token);

    fetch(ajaxUrl, { method: 'POST', body: formData })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        containers.forEach(function (el) {
          el.innerHTML = renderTeaser(data);
        });
      })
      .catch(function () {
        /* teaser is non-critical — silent failure is acceptable */
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (typeof prestashop === 'undefined' || typeof prestashop.on !== 'function') {
      return;
    }
    prestashop.on('updateCart', function () {
      setTimeout(updateTeasers, 150);
    });
  });
}());
