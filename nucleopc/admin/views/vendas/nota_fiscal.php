<?php
require_once __DIR__ . '/../../models/Pedido.php';
$tituloPagina = 'Nota Fiscal';
$subtituloPagina = 'Documento emitido automaticamente na confirmação do pagamento';
require __DIR__ . '/../layout/header.php';

$formaRotulo = Pedido::FORMAS_PAGAMENTO[$pedido['forma_pagamento']] ?? $pedido['forma_pagamento'];
$chaveFmt = trim(chunk_split($chaveAcesso, 4, ' '));
?>

<?php if (($_GET['msg'] ?? '') === 'venda_registrada'): ?>
  <div class="alert alert-success">Venda registrada e nota fiscal emitida automaticamente.</div>
<?php endif; ?>

<div class="card" style="max-width:720px; background:#fff; color:#111; font-family: var(--sans);" id="nota-imprimivel">
  <div style="display:flex; justify-content:space-between; gap:16px; border-bottom:2px solid #111; padding-bottom:12px; margin-bottom:16px;">
    <div style="display:flex; gap:12px; align-items:center;">
      <img src="assets/img/logo-icon.svg" alt="Núcleo PC" style="width:54px; height:54px;">
      <div>
        <strong style="font-size:16px;"><?= EMPRESA_NOME ?></strong><br>
        <span style="font-size:12px;">CNPJ: <?= EMPRESA_CNPJ ?> · IE: <?= EMPRESA_IE ?></span><br>
        <span style="font-size:12px;"><?= EMPRESA_ENDERECO ?></span>
      </div>
    </div>
    <div style="text-align:right;">
      <strong>NOTA FISCAL</strong><br>
      <span style="font-size:12px;">Número: <?= (int) $nota['numero'] ?></span><br>
      <span style="font-size:12px;">Série: <?= (int) $nota['serie'] ?></span><br>
      <span style="font-size:12px;">Modelo: NFC-e</span>
    </div>
  </div>

  <div style="display:flex; flex-wrap:wrap; gap:8px 24px; font-size:12.5px; margin-bottom:14px;">
    <span><strong>Emissão:</strong> <?= date('d/m/Y H:i', strtotime($nota['data_emissao'])) ?></span>
    <span><strong>Referência:</strong> pedido #<?= (int) $pedido['id'] ?> (<?= $pedido['origem'] === 'online' ? 'online' : 'balcão' ?>)</span>
    <span><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_nome'] ?? 'Cliente Balcão') ?></span>
    <?php if (!empty($pedido['cliente_cpf'])): ?><span><strong>CPF:</strong> <?= htmlspecialchars($pedido['cliente_cpf']) ?></span><?php endif; ?>
    <span><strong>Pagamento:</strong> <?= htmlspecialchars($formaRotulo) ?></span>
    <?php if (!empty($pedido['endereco_entrega'])): ?><span style="flex-basis:100%;"><strong>Entrega:</strong> <?= htmlspecialchars($pedido['endereco_entrega']) ?></span><?php endif; ?>
  </div>

  <table style="font-size:12.5px;">
    <thead>
      <tr style="border-bottom:1px solid #ccc;">
        <th style="text-align:left; padding:6px 4px; color:#555;">Código</th>
        <th style="text-align:left; padding:6px 4px; color:#555;">Descrição do produto</th>
        <th style="text-align:right; padding:6px 4px; color:#555;">Qtd</th>
        <th style="text-align:right; padding:6px 4px; color:#555;">V. Unitário</th>
        <th style="text-align:right; padding:6px 4px; color:#555;">V. Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($itens as $item): ?>
        <tr style="border-bottom:1px solid #eee;">
          <td style="padding:6px 4px;"><?= htmlspecialchars($item['sku']) ?></td>
          <td style="padding:6px 4px;"><?= htmlspecialchars($item['produto_nome']) ?></td>
          <td style="padding:6px 4px; text-align:right;"><?= (int) $item['quantidade'] ?></td>
          <td style="padding:6px 4px; text-align:right;">R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
          <td style="padding:6px 4px; text-align:right;">R$ <?= number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="display:flex; justify-content:flex-end; margin-top:16px;">
    <div style="background:#111; color:#fff; padding:10px 16px; text-align:right;">
      VALOR TOTAL DA NOTA<br>
      <strong style="font-size:18px;">R$ <?= number_format($nota['valor_total'], 2, ',', '.') ?></strong>
    </div>
  </div>

  <p style="font-size:11px; margin:16px 0 0; word-break:break-all;"><strong>Chave de acesso:</strong> <?= $chaveFmt ?></p>
  <p style="font-size:10.5px; color:#777; margin:6px 0 0;">Documento representativo (projeto acadêmico), sem valor fiscal.</p>
</div>

<?php if (!empty($historico)): ?>
<div class="card no-print" style="max-width:720px; margin-top:16px;">
  <strong style="font-size:13px;">Histórico do pagamento</strong>
  <table style="font-size:12.5px; margin-top:8px;">
    <thead><tr><th>Quando</th><th>De → Para</th><th>Detalhe</th></tr></thead>
    <tbody>
      <?php foreach ($historico as $h): ?>
        <tr>
          <td class="mono"><?= date('d/m/Y H:i', strtotime($h['criado_em'])) ?></td>
          <td><?= htmlspecialchars(($h['status_anterior'] ?? '—') . ' → ' . $h['status_novo']) ?></td>
          <td class="text-muted"><?= htmlspecialchars($h['observacao'] ?? '') ?><?= $h['usuario_nome'] ? ' (' . htmlspecialchars($h['usuario_nome']) . ')' : '' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="row-actions no-print" style="margin-top:16px;">
  <button onclick="window.print()" class="btn btn-primary">Imprimir Nota Fiscal</button>
  <a href="index.php?url=venda/index" class="btn btn-ghost">Voltar</a>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
