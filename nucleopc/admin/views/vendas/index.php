<?php
require_once __DIR__ . '/../../models/Pedido.php';
$tituloPagina = 'Vendas';
$subtituloPagina = 'Vendas realizadas no balcão da loja física e pedidos online';
require __DIR__ . '/../layout/header.php';

$rotuloPagamento = fn (?string $f) => Pedido::FORMAS_PAGAMENTO[$f ?? ''] ?? ($f ?: '—');
$rotuloPagStatus = fn (?string $s) => Pedido::ROTULO_PAGAMENTO_STATUS[$s ?? ''] ?? ($s ?: '—');

$mensagens = [
  'pagamento_confirmado' => ['ok', 'Pagamento confirmado manualmente. O pedido já pode receber nota fiscal.'],
  'pedido_cancelado'     => ['ok', 'Pedido cancelado e itens devolvidos ao estoque.'],
  'erro_pagamento'       => ['erro', 'Não foi possível confirmar: o pedido já havia sido pago ou cancelado.'],
  'erro_cancelamento'    => ['erro', 'Não foi possível cancelar: o pedido já havia sido pago ou cancelado.'],
  'erro_nota'            => ['erro', 'Só é possível emitir nota fiscal de um pedido confirmado (pago).'],
];
$msg = $mensagens[$_GET['msg'] ?? ''] ?? null;

$statusInfo = [
  'confirmado'           => ['badge-ok', 'Confirmado'],
  'aguardando_pagamento' => ['badge-warn', 'Aguardando pagamento'],
  'cancelado'            => ['badge-off', 'Cancelado'],
  'enviado'              => ['badge-ok', 'Enviado'],
  'entregue'             => ['badge-ok', 'Entregue'],
];
?>

<?php if ($msg): ?>
  <div class="alert <?= $msg[0] === 'ok' ? 'alert-success' : 'alert-error' ?>"><?= htmlspecialchars($msg[1]) ?></div>
<?php endif; ?>

<div style="display:flex; justify-content:flex-end; margin-bottom:14px;">
  <a href="index.php?url=venda/nova" class="btn btn-primary">+ Nova venda (balcão)</a>
</div>

<div class="card">
  <table>
    <thead>
      <tr><th>ID</th><th>Origem</th><th>Cliente / Vendedor</th><th>Pagamento</th><th>Status</th><th>Data</th><th class="text-right">Valor total</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (empty($vendas)): ?>
        <tr><td colspan="8" class="text-muted">Nenhuma venda registrada.</td></tr>
      <?php else: foreach ($vendas as $v): ?>
        <?php [$badge, $label] = $statusInfo[$v['status']] ?? ['badge-off', $v['status']]; ?>
        <tr>
          <td class="mono">#<?= $v['id'] ?></td>
          <td><span class="badge <?= $v['origem'] === 'online' ? 'badge-ok' : 'badge-off' ?>"><?= $v['origem'] === 'online' ? 'Online' : 'Balcão' ?></span></td>
          <td><?= htmlspecialchars($v['cliente_nome'] ?? $v['vendedor_nome'] ?? 'Cliente Balcão') ?></td>
          <td class="text-muted">
            <?= htmlspecialchars($rotuloPagamento($v['forma_pagamento'])) ?><br>
            <small><?= htmlspecialchars($rotuloPagStatus($v['pagamento_status'])) ?><?php if ($v['status'] === 'aguardando_pagamento' && $v['minutos_restantes'] !== null): ?> · restam <?= (int) $v['minutos_restantes'] >= 120 ? floor($v['minutos_restantes'] / 60) . ' h' : (int) $v['minutos_restantes'] . ' min' ?><?php endif; ?></small>
          </td>
          <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
          <td class="mono"><?= date('d/m/Y H:i', strtotime($v['data_pedido'])) ?></td>
          <td class="text-right mono">R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></td>
          <td>
            <div class="row-actions">
              <?php if ($v['status'] === 'confirmado'): ?>
                <a class="btn btn-ghost btn-sm" href="index.php?url=venda/notaFiscal/<?= $v['id'] ?>"><?= $v['nf_numero'] ? 'NF nº ' . (int) $v['nf_numero'] : 'Emitir NF' ?></a>
              <?php elseif ($v['status'] === 'aguardando_pagamento'): ?>
                <form method="post" action="index.php?url=venda/confirmarPagamento/<?= $v['id'] ?>" style="display:inline;">
                  <button type="submit" class="btn btn-ghost btn-sm" title="Use para boleto: confirme quando o pagamento compensar">Confirmar pagamento</button>
                </form>
                <form method="post" action="index.php?url=venda/cancelarPedido/<?= $v['id'] ?>" style="display:inline;" onsubmit="return confirm('Cancelar este pedido e devolver os itens ao estoque?');">
                  <button type="submit" class="btn btn-danger btn-sm">Cancelar</button>
                </form>
              <?php else: ?>
                <span class="text-muted" style="font-size:12px;">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
