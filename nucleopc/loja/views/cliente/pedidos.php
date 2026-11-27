<?php
require_once __DIR__ . '/../../services/PagamentoSimulado.php';
$tituloPagina = 'Meus pedidos — Núcleo PC';
require __DIR__ . '/../layout/header.php';

$rotulosStatus = [
  'aguardando_pagamento' => 'Aguardando pagamento',
  'confirmado'           => 'Confirmado',
  'enviado'              => 'Enviado',
  'entregue'             => 'Entregue',
  'cancelado'            => 'Cancelado',
];
?>

<div class="container">
  <section class="section">
    <div class="section-head"><h2>Meus pedidos</h2></div>

    <?php if (empty($pedidos)): ?>
      <p style="color:var(--muted);">Você ainda não fez nenhum pedido. <a href="index.php?url=produto/index" style="color:var(--accent); font-weight:600;">Ver catálogo</a></p>
    <?php else: ?>
      <table class="cart-table">
        <thead><tr><th>Pedido</th><th>Data</th><th>Pagamento</th><th>Status</th><th>Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($pedidos as $p): ?>
            <tr>
              <td>#<?= $p['id'] ?></td>
              <td><?= date('d/m/Y H:i', strtotime($p['data_pedido'])) ?></td>
              <td><?= htmlspecialchars(PagamentoSimulado::rotulo($p['forma_pagamento'])) ?></td>
              <td><span class="status-pill st-<?= htmlspecialchars($p['status']) ?>"><?= $rotulosStatus[$p['status']] ?? ucfirst(str_replace('_', ' ', $p['status'])) ?></span></td>
              <td>R$ <?= number_format($p['valor_total'], 2, ',', '.') ?></td>
              <td>
                <?php if ($p['status'] === 'aguardando_pagamento' && PagamentoSimulado::ehSimulada($p['forma_pagamento'])): ?>
                  <a class="btn btn-accent btn-sm" href="index.php?url=pedido/pagamento/<?= $p['id'] ?>">Pagar agora</a>
                <?php else: ?>
                  <a class="link-btn" href="index.php?url=pedido/retorno&amp;pedido=<?= $p['id'] ?>">Detalhes</a>
                  <?php if (in_array($p['status'], ['confirmado', 'enviado', 'entregue'], true)): ?>
                    · <a class="link-btn" href="index.php?url=pedido/notaFiscal/<?= $p['id'] ?>">Nota fiscal<?= $p['nf_numero'] ? ' nº ' . (int) $p['nf_numero'] : '' ?></a>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
