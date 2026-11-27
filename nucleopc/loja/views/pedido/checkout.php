<?php
$tituloPagina = 'Finalizar compra — Núcleo PC';
require __DIR__ . '/../layout/header.php';

$enderecoSugerido = trim(($cliente['endereco'] ?? '') . ', ' . ($cliente['numero'] ?? '') . ' - ' . ($cliente['bairro'] ?? '') . ' - ' . ($cliente['cidade'] ?? '') . '/' . ($cliente['uf'] ?? ''), ', -');
?>

<div class="container">
  <section class="section">
    <div class="section-head"><h2>Finalizar compra</h2></div>

    <?php if (!empty($erro)): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if (!empty($pendente)): ?>
      <div class="alert alert-success" style="text-align:left;">
        Seu pedido <strong>#<?= (int) $pendente['id'] ?></strong> está aguardando pagamento e os itens estão reservados.
        Escolha abaixo a forma de pagamento que preferir e clique em continuar — o pedido será atualizado, sem criar outro.
      </div>
    <?php endif; ?>

    <div class="cart-layout">
      <form method="post" action="index.php?url=pedido/finalizar">
        <div class="form-card wide" style="margin:0;">
          <h1 style="font-size:17px;">Endereço de entrega</h1>
          <div class="form-row">
            <label for="endereco_entrega">Endereço completo</label>
            <textarea id="endereco_entrega" name="endereco_entrega" required><?= htmlspecialchars(!empty($pendente['endereco_entrega']) ? $pendente['endereco_entrega'] : $enderecoSugerido) ?></textarea>
          </div>

          <h1 style="font-size:17px; margin-top:22px;">Forma de pagamento</h1>
          <?php
          // Formas ativas em formas_pagamento_config (RN10 define o prazo de cada uma)
          $siglas = ['pix' => ['PIX', 'pix'], 'cartao_credito' => ['CRÉD.', 'credito'], 'cartao_debito' => ['DÉB.', 'debito'], 'boleto' => ['BOLETO', 'boleto']];
          $formaSelecionada = $_POST['forma_pagamento'] ?? ($pendente['forma_pagamento'] ?? 'pix');
          $prazoTexto = function (int $min): string {
            if ($min >= 1440 && $min % 1440 === 0) { return 'vale por ' . intdiv($min, 1440) . ' dia(s)'; }
            return 'vale por ' . $min . ' min';
          };
          ?>
          <div class="pay-options" role="radiogroup" aria-label="Forma de pagamento">
            <?php foreach ($formas as $f): $cod = $f['codigo']; [$sigla, $classe] = $siglas[$cod] ?? [strtoupper($cod), $cod]; ?>
              <label class="pay-opt">
                <input type="radio" name="forma_pagamento" value="<?= htmlspecialchars($cod) ?>" <?= $formaSelecionada === $cod ? 'checked' : '' ?>>
                <span class="pay-body">
                  <span class="pay-icon <?= $classe ?>"><?= $sigla ?></span>
                  <span class="pay-text"><strong><?= htmlspecialchars($f['nome']) ?></strong><small><?= htmlspecialchars(trim(($f['descricao'] ?? '') . ' · ' . $prazoTexto((int) $f['prazo_minutos']), ' ·')) ?></small></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="pay-note">Na próxima tela você vê o <strong>QR Code</strong> (Pix), informa os <strong>dados do cartão</strong> ou gera o <strong>boleto</strong>. O pagamento é <strong>representativo</strong>: nenhuma cobrança real é feita.</p>

          <button type="submit" class="btn btn-accent btn-block" style="margin-top:16px;">Continuar para o pagamento</button>
        </div>
      </form>

      <aside class="cart-summary">
        <h3 style="margin-top:0; font-size:14px;">Resumo</h3>
        <?php foreach ($itens as $item): ?>
          <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:8px;">
            <span><?= $item['quantidade'] ?>x <?= htmlspecialchars($item['nome']) ?></span>
            <span>R$ <?= number_format($item['preco'] * $item['quantidade'], 2, ',', '.') ?></span>
          </div>
        <?php endforeach; ?>
        <div class="total-row">
          <span>Total</span>
          <span>R$ <?= number_format($total, 2, ',', '.') ?></span>
        </div>
      </aside>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
