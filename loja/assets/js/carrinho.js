/**
 * carrinho.js — atualiza o carrinho sozinho quando a quantidade muda
 * (sem botão "Atualizar quantidades"). Envia a nova quantidade ao servidor
 * (carrinho/atualizarAjax) e atualiza subtotal, total e o contador do cabeçalho.
 */
(function () {
  'use strict';

  function enviar(input) {
    var linha = input.closest('tr');
    var qtd = parseInt(input.value, 10);
    if (!linha || isNaN(qtd) || qtd < 1) { return; }     // campo vazio/zero: espera o usuário terminar de digitar
    if (input.dataset.enviado === String(qtd)) { return; }
    input.dataset.enviado = String(qtd);

    var corpo = new URLSearchParams();
    corpo.set('produto_id', linha.getAttribute('data-produto'));
    corpo.set('quantidade', String(qtd));

    fetch('index.php?url=carrinho/atualizarAjax', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
      body: corpo.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.ok) { return; }
        input.value = r.quantidade;
        input.dataset.enviado = String(r.quantidade);
        linha.querySelector('.js-subtotal').textContent = 'R$ ' + r.subtotal;
        document.getElementById('cart-total').textContent = 'R$ ' + r.total;
        var badge = document.querySelector('.cart-badge');
        if (badge) { badge.textContent = r.itens; }
        var aviso = document.getElementById('cart-aviso');
        if (aviso) { aviso.textContent = r.aviso || ''; aviso.className = 'vf-msg ' + (r.aviso ? 'vf-aviso' : ''); }
      })
      .catch(function () {
        var aviso = document.getElementById('cart-aviso');
        if (aviso) { aviso.textContent = 'Não foi possível atualizar agora. Tente novamente.'; aviso.className = 'vf-msg vf-erro'; }
        input.dataset.enviado = '';
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    [].forEach.call(document.querySelectorAll('.js-qtd'), function (input) {
      var t;
      input.dataset.enviado = input.value;
      input.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { enviar(input); }, 350); });
      input.addEventListener('change', function () { clearTimeout(t); enviar(input); });
    });
  });
})();
