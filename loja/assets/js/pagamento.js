/**
 * Comportamento da tela de pagamento representativo:
 * QR Code do Pix, botões "copiar", máscara/validação/pré-visualização do cartão.
 */
(function () {
  'use strict';

  // ---------- Pix: desenha o QR Code a partir do código "copia e cola" ----------
  var qr = document.getElementById('pix-qr');
  if (qr && window.NucleoQR) {
    try {
      qr.innerHTML = window.NucleoQR.svg(qr.getAttribute('data-qr'), { escala: 6, margem: 2 });
    } catch (e) {
      qr.textContent = 'Não foi possível gerar o QR Code. Use o código copia e cola.';
    }
  }

  // ---------- Contador regressivo (validade do QR Pix / reserva do cartão) ----------
  var timer = document.getElementById('pay-timer');
  var timerValor = document.getElementById('pay-timer-valor');
  if (timer && timerValor) {
    var restante = parseInt(timer.getAttribute('data-restante'), 10) || 0;
    var tick = function () {
      if (restante <= 0) {
        timerValor.textContent = '00:00';
        timer.classList.add('pay-timer-acabando');
        clearInterval(intervalo);
        timer.insertAdjacentHTML('beforeend', ' — atualizando...');
        setTimeout(function () { window.location.reload(); }, 1500);
        return;
      }
      restante -= 1;
      var m = Math.floor(restante / 60), s = restante % 60;
      timerValor.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      if (restante <= 60) { timer.classList.add('pay-timer-acabando'); }
    };
    var intervalo = setInterval(tick, 1000);
  }

  // ---------- Botões "Copiar" ----------
  document.querySelectorAll('[data-copiar]').forEach(function (botao) {
    botao.addEventListener('click', function () {
      var alvo = document.getElementById(botao.getAttribute('data-copiar'));
      if (!alvo) return;
      var texto = ('value' in alvo && alvo.value) ? alvo.value : alvo.textContent.trim();
      var original = botao.textContent;

      function avisar() { botao.textContent = 'Copiado ✓'; setTimeout(function () { botao.textContent = original; }, 1800); }
      function alternativa() {
        var t = document.createElement('textarea');
        t.value = texto; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); avisar(); } catch (e) { window.prompt('Copie o código:', texto); }
        document.body.removeChild(t);
      }
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(texto).then(avisar, alternativa);
      } else { alternativa(); }
    });
  });

  // ---------- Cartão ----------
  var form = document.getElementById('form-cartao');
  if (!form) return;

  var campoNumero = document.getElementById('cc-input-numero');
  var campoNome = document.getElementById('cc-input-nome');
  var campoValidade = document.getElementById('cc-input-validade');
  var campoCvv = document.getElementById('cc-input-cvv');
  var pNumero = document.getElementById('cc-numero');
  var pNome = document.getElementById('cc-nome');
  var pValidade = document.getElementById('cc-validade');
  var pBandeira = document.getElementById('cc-bandeira');
  var caixaErro = document.getElementById('cartao-erro');

  function soDigitos(v) { return (v || '').replace(/\D+/g, ''); }

  function bandeira(n) {
    if (/^(636368|438935|504175|451416|636297|5067|4576|4011)/.test(n)) return 'Elo';
    if (/^(606282|3841)/.test(n)) return 'Hipercard';
    if (/^3[47]/.test(n)) return 'Amex';
    var p4 = parseInt(n.slice(0, 4), 10);
    if (/^5[1-5]/.test(n) || (p4 >= 2221 && p4 <= 2720)) return 'Mastercard';
    if (/^4/.test(n)) return 'Visa';
    return 'Cartão';
  }

  function luhn(n) {
    if (n.length < 13 || n.length > 19) return false;
    var soma = 0;
    for (var i = 0; i < n.length; i++) {
      var d = parseInt(n.charAt(n.length - 1 - i), 10);
      if (i % 2 === 1) { d *= 2; if (d > 9) d -= 9; }
      soma += d;
    }
    return soma % 10 === 0;
  }

  function agrupar(n, marca) {
    if (marca === 'Amex') return [n.slice(0, 4), n.slice(4, 10), n.slice(10, 15)].filter(Boolean).join(' ');
    return (n.match(/.{1,4}/g) || []).join(' ');
  }

  function atualizar() {
    var n = soDigitos(campoNumero.value).slice(0, 19), marca = bandeira(n);
    campoNumero.value = agrupar(n, marca);
    campoCvv.maxLength = marca === 'Amex' ? 4 : 3;
    pBandeira.textContent = marca;
    var mascara = (marca === 'Amex' ? '•••• •••••• •••••' : '•••• •••• •••• ••••').split('');
    var digs = n.split(''), k = 0;
    pNumero.textContent = mascara.map(function (c) { return (c === '•' && k < digs.length) ? digs[k++] : c; }).join('');
    pNome.textContent = campoNome.value.trim() ? campoNome.value.toUpperCase() : 'NOME NO CARTÃO';
    pValidade.textContent = campoValidade.value || 'MM/AA';
  }

  campoNumero.addEventListener('input', atualizar);
  campoNome.addEventListener('input', atualizar);
  campoValidade.addEventListener('input', function () {
    var d = soDigitos(campoValidade.value).slice(0, 4);
    if (d.length >= 3) d = d.slice(0, 2) + '/' + d.slice(2);
    campoValidade.value = d;
    atualizar();
  });
  campoCvv.addEventListener('input', function () { campoCvv.value = soDigitos(campoCvv.value); });

  var botaoTeste = document.getElementById('btn-cartao-teste');
  if (botaoTeste) {
    botaoTeste.addEventListener('click', function () {
      campoNumero.value = '4242424242424242';
      campoNome.value = 'CLIENTE TESTE';
      campoValidade.value = '12' + String((new Date().getFullYear() + 3) % 100).padStart(2, '0');
      campoValidade.dispatchEvent(new Event('input'));
      campoCvv.value = '123';
      atualizar();
    });
  }

  function mostrarErro(msg) { caixaErro.textContent = msg; caixaErro.hidden = false; caixaErro.scrollIntoView({ block: 'nearest' }); }

  form.addEventListener('submit', function (ev) {
    var n = soDigitos(campoNumero.value), marca = bandeira(n);
    var m = /^(0[1-9]|1[0-2])\/(\d{2})$/.exec(campoValidade.value);
    var msg = '';
    if (!luhn(n)) msg = 'Número de cartão inválido. Confira os dígitos.';
    else if (campoNome.value.trim().length < 3) msg = 'Informe o nome impresso no cartão.';
    else if (!m) msg = 'Validade inválida. Use o formato MM/AA.';
    else if (new Date(2000 + parseInt(m[2], 10), parseInt(m[1], 10), 1) <= new Date()) msg = 'Cartão vencido.';
    else if (campoCvv.value.length !== (marca === 'Amex' ? 4 : 3)) msg = 'Código de segurança (CVV) inválido.';
    if (msg) { ev.preventDefault(); mostrarErro(msg); } else { caixaErro.hidden = true; }
  });

  atualizar();
})();
