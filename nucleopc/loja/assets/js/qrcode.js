/**
 * NucleoQR — gerador de QR Code (sem dependências e sem internet).
 * Implementa o padrão QR Code Model 2 (modo byte/UTF-8, versões 1 a 10,
 * correção de erros M ou L, com escolha automática da melhor máscara).
 * Uso:  NucleoQR.svg("texto", { escala: 5, margem: 4 })  ->  string <svg>...
 */
(function (global) {
  'use strict';

  // Tabelas (índice = versão 1..10): bytes de correção por bloco e número de blocos
  var ECC_POR_BLOCO = { L: [0, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18], M: [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26] };
  var NUM_BLOCOS = { L: [0, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4], M: [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5] };
  var BITS_FORMATO = { L: 1, M: 0 };

  function modulosBrutos(v) {
    var r = (16 * v + 128) * v + 64;
    if (v >= 2) { var n = Math.floor(v / 7) + 2; r -= (25 * n - 10) * n - 55; if (v >= 7) r -= 36; }
    return r;
  }
  function codewordsDados(v, ec) { return Math.floor(modulosBrutos(v) / 8) - ECC_POR_BLOCO[ec][v] * NUM_BLOCOS[ec][v]; }

  // Aritmética de Galois GF(256) e Reed-Solomon
  function gfMul(x, y) { var z = 0; for (var i = 7; i >= 0; i--) { z = (z << 1) ^ ((z >>> 7) * 0x11D); z ^= ((y >>> i) & 1) * x; } return z; }
  function rsDivisor(grau) {
    var r = []; for (var i = 0; i < grau - 1; i++) r.push(0); r.push(1);
    var raiz = 1;
    for (var k = 0; k < grau; k++) {
      for (var j = 0; j < r.length; j++) { r[j] = gfMul(r[j], raiz); if (j + 1 < r.length) r[j] ^= r[j + 1]; }
      raiz = gfMul(raiz, 2);
    }
    return r;
  }
  function rsResto(dados, div) {
    var r = div.map(function () { return 0; });
    dados.forEach(function (b) {
      var f = b ^ r.shift(); r.push(0);
      div.forEach(function (c, i) { r[i] ^= gfMul(c, f); });
    });
    return r;
  }

  function utf8(str) {
    var out = [], s = unescape(encodeURIComponent(str));
    for (var i = 0; i < s.length; i++) out.push(s.charCodeAt(i));
    return out;
  }

  function codificar(bytes, ec) {
    for (var v = 1; v <= 10; v++) {
      var bitsContagem = v < 10 ? 8 : 16;
      var capacidade = codewordsDados(v, ec) * 8;
      if (4 + bitsContagem + bytes.length * 8 <= capacidade) return montar(bytes, v, ec, bitsContagem, capacidade);
    }
    return null;
  }

  function montar(bytes, v, ec, bitsContagem, capacidade) {
    var bits = [];
    function push(val, n) { for (var i = n - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
    push(4, 4); push(bytes.length, bitsContagem);
    bytes.forEach(function (b) { push(b, 8); });
    push(0, Math.min(4, capacidade - bits.length));
    while (bits.length % 8) bits.push(0);
    for (var pad = 0xEC; bits.length < capacidade; pad ^= 0xEC ^ 0x11) push(pad, 8);
    var dados = [];
    for (var i = 0; i < bits.length; i += 8) { var b = 0; for (var j = 0; j < 8; j++) b = (b << 1) | bits[i + j]; dados.push(b); }

    // blocos + correção de erros + intercalação
    var nb = NUM_BLOCOS[ec][v], eccLen = ECC_POR_BLOCO[ec][v];
    var brutos = Math.floor(modulosBrutos(v) / 8), curtos = nb - (brutos % nb), lenCurto = Math.floor(brutos / nb);
    var blocos = [], div = rsDivisor(eccLen), k = 0;
    for (var bl = 0; bl < nb; bl++) {
      var d = dados.slice(k, k + lenCurto - eccLen + (bl < curtos ? 0 : 1)); k += d.length;
      var ecc = rsResto(d, div);
      if (bl < curtos) d.push(0);
      blocos.push(d.concat(ecc));
    }
    var final = [];
    for (var x = 0; x < blocos[0].length; x++) blocos.forEach(function (blk, y) { if (x !== lenCurto - eccLen || y >= curtos) final.push(blk[x]); });
    return { versao: v, ec: ec, codewords: final };
  }

  function posicoesAlinhamento(v) {
    if (v === 1) return [];
    var n = Math.floor(v / 7) + 2, tam = v * 4 + 17;
    var passo = Math.ceil((v * 4 + 4) / (n * 2 - 2)) * 2, r = [6];
    for (var pos = tam - 7; r.length < n; pos -= passo) r.splice(1, 0, pos);
    return r;
  }

  function construirMatriz(info) {
    var v = info.versao, tam = v * 4 + 17, m = [], fn = [];
    for (var i = 0; i < tam; i++) { m.push(new Array(tam).fill(false)); fn.push(new Array(tam).fill(false)); }
    function set(x, y, val) { m[y][x] = val; fn[y][x] = true; }

    for (var t = 0; t < tam; t++) { set(6, t, t % 2 === 0); set(t, 6, t % 2 === 0); }
    function localizador(cx, cy) {
      for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
        var dist = Math.max(Math.abs(dx), Math.abs(dy)), xx = cx + dx, yy = cy + dy;
        if (xx >= 0 && xx < tam && yy >= 0 && yy < tam) set(xx, yy, dist !== 2 && dist !== 4);
      }
    }
    localizador(3, 3); localizador(tam - 4, 3); localizador(3, tam - 4);
    var al = posicoesAlinhamento(v), na = al.length;
    for (var a = 0; a < na; a++) for (var b = 0; b < na; b++) {
      if ((a === 0 && b === 0) || (a === 0 && b === na - 1) || (a === na - 1 && b === 0)) continue;
      for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) set(al[a] + dx, al[b] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
    }

    function formato(mascara) {
      var dado = (BITS_FORMATO[info.ec] << 3) | mascara, rem = dado;
      for (var i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
      var bits = ((dado << 10) | rem) ^ 0x5412;
      function bit(i) { return ((bits >>> i) & 1) !== 0; }
      for (var i2 = 0; i2 <= 5; i2++) set(8, i2, bit(i2));
      set(8, 7, bit(6)); set(8, 8, bit(7)); set(7, 8, bit(8));
      for (var i3 = 9; i3 < 15; i3++) set(14 - i3, 8, bit(i3));
      for (var i4 = 0; i4 < 8; i4++) set(tam - 1 - i4, 8, bit(i4));
      for (var i5 = 8; i5 < 15; i5++) set(8, tam - 15 + i5, bit(i5));
      set(8, tam - 8, true);
    }
    formato(0);
    if (v >= 7) {
      var rem = v;
      for (var q = 0; q < 12; q++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
      var bitsV = (v << 12) | rem;
      for (var w = 0; w < 18; w++) {
        var cor = ((bitsV >>> w) & 1) !== 0, aa = tam - 11 + (w % 3), bb = Math.floor(w / 3);
        set(aa, bb, cor); set(bb, aa, cor);
      }
    }

    // posiciona os codewords em zigue-zague
    var idx = 0, total = info.codewords.length * 8;
    for (var direita = tam - 1; direita >= 1; direita -= 2) {
      if (direita === 6) direita = 5;
      for (var vert = 0; vert < tam; vert++) for (var j = 0; j < 2; j++) {
        var x = direita - j, sobe = ((direita + 1) & 2) === 0, y = sobe ? tam - 1 - vert : vert;
        if (!fn[y][x] && idx < total) { m[y][x] = ((info.codewords[idx >>> 3] >>> (7 - (idx & 7))) & 1) !== 0; idx++; }
      }
    }

    function aplicarMascara(k) {
      for (var y = 0; y < tam; y++) for (var x = 0; x < tam; x++) {
        var inv;
        switch (k) {
          case 0: inv = (x + y) % 2 === 0; break;
          case 1: inv = y % 2 === 0; break;
          case 2: inv = x % 3 === 0; break;
          case 3: inv = (x + y) % 3 === 0; break;
          case 4: inv = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
          case 5: inv = (x * y) % 2 + (x * y) % 3 === 0; break;
          case 6: inv = ((x * y) % 2 + (x * y) % 3) % 2 === 0; break;
          default: inv = ((x + y) % 2 + (x * y) % 3) % 2 === 0;
        }
        if (inv && !fn[y][x]) m[y][x] = !m[y][x];
      }
    }
    function penalidade() {
      var p = 0, n = tam, linhas = [], cols = [], y, x;
      for (y = 0; y < n; y++) { var s = ''; for (x = 0; x < n; x++) s += m[y][x] ? '1' : '0'; linhas.push(s); }
      for (x = 0; x < n; x++) { var c = ''; for (y = 0; y < n; y++) c += m[y][x] ? '1' : '0'; cols.push(c); }
      linhas.concat(cols).forEach(function (s) {
        var run = 1;
        for (var i = 1; i <= s.length; i++) {
          if (i < s.length && s[i] === s[i - 1]) run++; else { if (run >= 5) p += run - 2; run = 1; }
        }
        var pos = -1; while ((pos = s.indexOf('10111010000', pos + 1)) !== -1) p += 40;
        pos = -1; while ((pos = s.indexOf('00001011101', pos + 1)) !== -1) p += 40;
      });
      for (y = 0; y < n - 1; y++) for (x = 0; x < n - 1; x++) if (m[y][x] === m[y][x + 1] && m[y][x] === m[y + 1][x] && m[y][x] === m[y + 1][x + 1]) p += 3;
      var escuros = 0; for (y = 0; y < n; y++) for (x = 0; x < n; x++) if (m[y][x]) escuros++;
      p += Math.floor(Math.abs(escuros * 20 - n * n * 10) / (n * n)) * 10;
      return p;
    }
    var melhor = 0, melhorPen = Infinity;
    for (var k = 0; k < 8; k++) {
      aplicarMascara(k); formato(k);
      var pen = penalidade();
      if (pen < melhorPen) { melhorPen = pen; melhor = k; }
      aplicarMascara(k);
    }
    aplicarMascara(melhor); formato(melhor);
    return m;
  }

  function matriz(texto) {
    var bytes = utf8(texto), info = codificar(bytes, 'M') || codificar(bytes, 'L');
    if (!info) throw new Error('Texto longo demais para o QR Code (máx. ~270 caracteres).');
    return construirMatriz(info);
  }

  function svg(texto, opcoes) {
    opcoes = opcoes || {};
    var escala = opcoes.escala || 5, margem = opcoes.margem === undefined ? 4 : opcoes.margem;
    var m = matriz(texto), tam = m.length, total = (tam + margem * 2) * escala, d = '';
    for (var y = 0; y < tam; y++) for (var x = 0; x < tam; x++) if (m[y][x]) d += 'M' + ((x + margem) * escala) + ' ' + ((y + margem) * escala) + 'h' + escala + 'v' + escala + 'h-' + escala + 'z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + total + '" width="' + total + '" height="' + total + '" shape-rendering="crispEdges" role="img" aria-label="QR Code">' +
      '<rect width="100%" height="100%" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
  }

  global.NucleoQR = { svg: svg, matriz: matriz };
  if (typeof module !== 'undefined') module.exports = global.NucleoQR;
})(typeof window !== 'undefined' ? window : globalThis);
