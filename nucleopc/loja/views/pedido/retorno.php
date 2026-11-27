<?php
require_once __DIR__ . '/../../services/PagamentoSimulado.php';
$tituloPagina = 'Resultado do pedido — Núcleo PC';
require __DIR__ . '/../layout/header.php';

$status  = $pedido['status'];
$motivo  = $pedido['pagamento_status'] ?? '';
$formaRotulo = PagamentoSimulado::rotulo($pedido['forma_pagamento']);
$ref = $pedido['pagamento_id'] ?? '';
$motivo_texto = $motivo_cancelamento ?? '';

$motivosCancelamento = [
  'cancelado_expirado' => 'O prazo para pagar terminou, então o pedido foi cancelado automaticamente.',
];

$config = [
  'confirmado' => [
    'icone'  => '✅',
    'titulo' => 'Pagamento aprovado!',
    'texto'  => 'Seu pedido #' . $pedido['id'] . ' foi confirmado e já está sendo preparado para envio.',
    'classe' => 'alert-success',
  ],
  'aguardando_pagamento' => [
    'icone'  => '⏳',
    'titulo' => 'Aguardando pagamento',
    'texto'  => 'O pedido #' . $pedido['id'] . ' foi criado, mas o pagamento ainda não foi concluído.',
    'classe' => 'alert-warn',
  ],
  'cancelado' => [
    'icone'  => '❌',
    'titulo' => 'Pedido cancelado',
    'texto'  => ($motivosCancelamento[$motivo] ?? (!empty($motivo_texto) ? $motivo_texto . '.' : 'O pagamento do pedido #' . $pedido['id'] . ' não foi concluído.'))
              . ' Os itens voltaram ao estoque e nenhuma cobrança foi feita.',
    'classe' => 'alert-error',
  ],
][$status] ?? [
  'icone' => 'ℹ️', 'titulo' => 'Pedido #' . $pedido['id'], 'texto' => 'Situação atual: ' . str_replace('_', ' ', $status), 'classe' => 'alert-warn',
];
?>

<div class="container">
  <section class="section">
    <div class="form-card wide" style="text-align:center;">
      <div style="font-size:40px; margin-bottom:10px;"><?= $config['icone'] ?></div>
      <h1><?= htmlspecialchars($config['titulo']) ?></h1>
      <div class="alert <?= $config['classe'] ?>" style="text-align:left;"><?= htmlspecialchars($config['texto']) ?></div>

      <table class="cart-table" style="text-align:left; margin-top:18px;">
        <thead><tr><th>Produto</th><th>Qtd</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($itens as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['nome']) ?></td>
              <td><?= $item['quantidade'] ?></td>
              <td>R$ <?= number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="total-row" style="justify-content:flex-end; gap:10px;">
        <span>Total do pedido:</span>
        <strong>R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></strong>
      </div>

      <?php if (!empty($nota)): ?>
        <div class="alert alert-success" style="text-align:left;">
          Nota fiscal nº <strong><?= (int) $nota['numero'] ?></strong> (série <?= (int) $nota['serie'] ?>) emitida em
          <?= date('d/m/Y H:i', strtotime($nota['data_emissao'])) ?>.
        </div>
      <?php endif; ?>

      <p class="pay-hint" style="text-align:left;">
        Pagamento: <strong><?= htmlspecialchars($formaRotulo) ?></strong>
        <?php if ($status === 'confirmado' && $ref !== ''): ?> — <?= htmlspecialchars($ref) ?><?php endif; ?>
        <br>Entrega em: <?= htmlspecialchars($pedido['endereco_entrega'] ?? '') ?>
      </p>

      <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:16px;">
        <?php if ($status === 'aguardando_pagamento' && PagamentoSimulado::ehSimulada($pedido['forma_pagamento'])): ?>
          <a href="index.php?url=pedido/pagamento/<?= (int) $pedido['id'] ?>" class="btn btn-accent">Ir para o pagamento</a>
        <?php endif; ?>
        <?php if (!empty($nota)): ?>
          <a href="index.php?url=pedido/notaFiscal/<?= (int) $pedido['id'] ?>" class="btn btn-accent">Ver / imprimir nota fiscal</a>
        <?php endif; ?>
        <?php if ($status === 'cancelado'): ?>
          <a href="index.php?url=produto/index" class="btn btn-accent">Comprar novamente</a>
        <?php endif; ?>
        <a href="index.php?url=cliente/meusPedidos" class="btn btn-ghost">Ver meus pedidos</a>
        <a href="index.php?url=produto/index" class="btn btn-ghost">Continuar comprando</a>
      </div>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
