/**
 * verificacao.js — verificação de informações nos formulários (Núcleo PC)
 *
 * Funciona por atributos HTML, sem precisar escrever JavaScript em cada tela:
 *
 *   <input id="cep"   data-verificar="cep"   data-preencher="endereco:logradouro,bairro:bairro,cidade:cidade,uf:uf">
 *   <input id="cpf"   data-verificar="cpf">
 *   <input id="cnpj"  data-verificar="cnpj"  data-contexto="fornecedor" data-ignorar="3">
 *   <input id="email" data-verificar="email" data-contexto="cadastro">
 *   <input id="senha" type="password" data-senha-min="8">
 *   <input id="confirmacao" type="password" data-confirma="senha">
 *
 * Os dados são consultados no NOSSO servidor (index.php?url=api/...), que fala com ViaCEP,
 * BrasilAPI e DNS. Se o servidor não conseguir consultar o CEP, o navegador tenta o ViaCEP direto.
 * O servidor SEMPRE valida de novo ao salvar — esta camada serve só para dar retorno imediato.
 */
(function () {
  'use strict';

  var BASE = 'index.php?url=api/';

  function digitos(v) { return (v || '').replace(/\D+/g, ''); }

  // ---------------------------------------------------------------- máscaras
  var MASCARAS = {
    cpf: function (v) {
      v = digitos(v).slice(0, 11);
      return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    },
    cnpj: function (v) {
      v = digitos(v).slice(0, 14);
      return v.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
              .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    },
    telefone: function (v) {            // (12)12345-6789  ou  (12)1234-5678
      v = digitos(v).slice(0, 11);
      if (v.length === 0) { return ''; }
      if (v.length <= 2) { return '(' + v; }
      if (v.length <= 6) { return v.replace(/^(\d{2})(\d+)/, '($1)$2'); }
      if (v.length <= 10) { return v.replace(/^(\d{2})(\d{4})(\d+)/, '($1)$2-$3'); }
      return v.replace(/^(\d{2})(\d{5})(\d+)/, '($1)$2-$3');
    },
    cep: function (v) {
      v = digitos(v).slice(0, 8);
      return v.replace(/^(\d{5})(\d)/, '$1-$2');
    }
  };

  // --------------------------------------------------------------- mensagens
  function msgEl(input) {
    var id = (input.id || input.name) + '-vf';
    var el = document.getElementById(id);
    if (!el) {
      el = document.createElement('small');
      el.id = id;
      el.className = 'vf-msg';
      el.setAttribute('aria-live', 'polite');
      input.parentNode.appendChild(el);
    }
    return el;
  }

  function mostrar(input, estado, texto) {
    var el = msgEl(input);
    el.className = 'vf-msg vf-' + estado; // info | ok | aviso | erro
    el.textContent = texto || '';
    input.setCustomValidity(estado === 'erro' ? texto : '');
  }

  // ------------------------------------------------------------ chamadas API
  function consultar(tipo, valor, extra) {
    var url = BASE + tipo + '&valor=' + encodeURIComponent(valor) + (extra ? '&' + extra : '');
    return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); });
  }

  // Reserva: se o servidor não conseguiu falar com a API, o navegador consulta o ViaCEP direto.
  function cepDireto(cep) {
    return fetch('https://viacep.com.br/ws/' + cep + '/json/')
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.erro) { return { ok: false, mensagem: 'CEP não encontrado.', dados: null, verificado_online: true }; }
        return {
          ok: true, verificado_online: true, mensagem: 'CEP encontrado.',
          dados: { logradouro: j.logradouro, bairro: j.bairro, cidade: j.localidade, uf: j.uf }
        };
      });
  }

  // --------------------------------------------------------------- preenchimento
  function preencher(input, dados) {
    var mapa = input.getAttribute('data-preencher');
    if (!mapa || !dados) { return; }
    mapa.split(',').forEach(function (par) {
      var p = par.split(':');
      var alvo = document.getElementById(p[0].trim());
      if (!alvo) { return; }
      var chave = (p[1] || '').trim();
      var valor = '';
      if (chave === 'completo') {
        valor = [dados.logradouro, dados.bairro].filter(Boolean).join(', ');
        if (dados.cidade) { valor += (valor ? ' - ' : '') + dados.cidade + (dados.uf ? '/' + dados.uf : ''); }
      } else {
        valor = dados[chave] || '';
      }
      if (valor) { alvo.value = valor; }
    });
  }

  // ------------------------------------------------------------- campo genérico
  function vincular(input) {
    var tipo = input.getAttribute('data-verificar');
    var ultimo = '';

    if (MASCARAS[tipo]) {
      input.addEventListener('input', function () { input.value = MASCARAS[tipo](input.value); });
    }
    input.addEventListener('input', function () { input.setCustomValidity(''); });

    input.addEventListener('blur', function () {
      var valor = input.value.trim();
      if (valor === '') { mostrar(input, 'info', ''); ultimo = ''; return; }
      if (valor === ultimo) { return; }
      ultimo = valor;

      var minimo = { cep: 8, cpf: 11, cnpj: 14 }[tipo];
      if (minimo && digitos(valor).length !== minimo) {
        mostrar(input, 'erro', tipo.toUpperCase() + ' deve ter ' + minimo + ' dígitos.');
        return;
      }

      var extra = [];
      if (input.getAttribute('data-contexto')) { extra.push('contexto=' + encodeURIComponent(input.getAttribute('data-contexto'))); }
      if (input.getAttribute('data-ignorar')) { extra.push('ignorar=' + encodeURIComponent(input.getAttribute('data-ignorar'))); }

      mostrar(input, 'info', 'Verificando…');
      consultar(tipo, valor, extra.join('&'))
        .then(function (r) {
          if (tipo === 'cep' && r.ok && !r.verificado_online) {
            return cepDireto(digitos(valor)).catch(function () { return r; });
          }
          return r;
        })
        .then(function (r) {
          if (!r.ok) { mostrar(input, 'erro', r.mensagem); return; }
          if (r.aviso) { mostrar(input, 'aviso', r.aviso); }
          else { mostrar(input, r.verificado_online || r.fonte === 'local' ? 'ok' : 'info', r.mensagem); }
          if (r.dados) { preencher(input, r.dados); }
        })
        .catch(function () {
          // Sem rede ou servidor fora: não bloqueia o usuário; o servidor valida ao salvar.
          mostrar(input, 'info', '');
        });
    });
  }

  // --------------------------------------------------------------------- senha
  function vincularSenha(input) {
    var min = parseInt(input.getAttribute('data-senha-min'), 10) || 8;
    input.setAttribute('minlength', String(min));
    var el = msgEl(input);
    el.className = 'vf-msg vf-info';
    el.textContent = 'Mínimo de ' + min + ' caracteres.';

    input.addEventListener('input', function () {
      var n = input.value.length;
      if (n === 0) { el.className = 'vf-msg vf-info'; el.textContent = 'Mínimo de ' + min + ' caracteres.'; input.setCustomValidity(''); return; }
      if (n < min) {
        el.className = 'vf-msg vf-erro';
        el.textContent = 'Faltam ' + (min - n) + ' caractere' + (min - n > 1 ? 's' : '') + ' (mínimo de ' + min + ').';
        input.setCustomValidity('A senha deve ter no mínimo ' + min + ' caracteres.');
      } else {
        el.className = 'vf-msg vf-ok';
        el.textContent = 'Tamanho da senha ok (' + n + ' caracteres).';
        input.setCustomValidity('');
      }
    });
  }

  function vincularConfirmacao(input) {
    var alvo = document.getElementById(input.getAttribute('data-confirma'));
    if (!alvo) { return; }
    function checar() {
      if (input.value === '') { mostrar(input, 'info', ''); return; }
      if (input.value !== alvo.value) { mostrar(input, 'erro', 'A confirmação não confere com a senha.'); }
      else { mostrar(input, 'ok', 'As senhas conferem.'); }
    }
    input.addEventListener('input', checar);
    alvo.addEventListener('input', function () { if (input.value !== '') { checar(); } });
  }

  // Campos que só precisam de máscara (ex.: telefone), sem consulta ao servidor.
  function vincularMascara(input) {
    var tipo = input.getAttribute('data-mascara');
    if (!MASCARAS[tipo]) { return; }
    input.value = MASCARAS[tipo](input.value);
    input.addEventListener('input', function () { input.value = MASCARAS[tipo](input.value); input.setCustomValidity(''); });
    if (tipo === 'telefone') {
      input.addEventListener('blur', function () {
        var n = digitos(input.value).length;
        if (n !== 0 && n !== 10 && n !== 11) { mostrar(input, 'erro', 'Telefone deve ter DDD + 8 ou 9 dígitos, ex.: (12)12345-6789.'); }
        else { mostrar(input, 'info', ''); }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    [].forEach.call(document.querySelectorAll('[data-mascara]'), vincularMascara);
    [].forEach.call(document.querySelectorAll('[data-verificar]'), vincular);
    [].forEach.call(document.querySelectorAll('input[type=password][data-senha-min]'), vincularSenha);
    [].forEach.call(document.querySelectorAll('input[data-confirma]'), vincularConfirmacao);
  });
})();
