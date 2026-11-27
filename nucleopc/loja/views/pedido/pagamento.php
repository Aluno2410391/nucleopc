<?php
require_once __DIR__ . '/../../services/PagamentoSimulado.php';
$tituloPagina = 'Pagamento do pedido #' . $pedido['id'] . ' — Núcleo PC';
require __DIR__ . '/../layout/header.php';

$totalFmt = 'R$ ' . number_format($pedido['valor_total'], 2, ',', '.');
$idPedido = (int) $pedido['id'];
$credito  = ($forma === 'cartao_credito');
?>

<div class="container">
  <section class="section">
    <div class="section-head"><h2>Pagamento — <?= htmlspecialchars(PagamentoSimulado::rotulo($forma)) ?></h2></div>

    <div class="alert alert-warn">
      <strong>Pagamento representativo.</strong> Este fluxo simula o processo real, mas <strong>nenhuma cobrança é feita</strong>.
      O pedido #<?= $idPedido ?> está reservado e aguardando a confirmação.
    </div>

    <?php if (!empty($erros)): ?>
      <div class="alert alert-error">
        <?php foreach ($erros as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="cart-layout">
      <div class="pay-card">

        <?php if ($forma !== 'boleto'): ?>
          <p class="pay-timer" id="pay-timer" data-restante="<?= (int) $restante ?>">
            <?= $forma === 'pix' ? 'Este QR Code vale por' : 'Reserva do pedido por' ?>
            <strong id="pay-timer-valor"><?= sprintf('%02d:%02d', intdiv((int) $restante, 60), (int) $restante % 60) ?></strong>
          </p>
        <?php endif; ?>

        <?php if ($forma === 'pix'): ?>
          <!-- ================= PIX ================= -->
          <div class="pix-box">
            <div class="pix-qr" id="pix-qr" data-qr="<?= htmlspecialchars($pixPayload) ?>" aria-live="polite">
              <noscript>Ative o JavaScript para ver o QR Code ou use o código ao lado.</noscript>
            </div>
            <div class="pix-info">
              <h3>Pague com Pix em 3 passos</h3>
              <ol class="pay-steps">
                <li>Abra o app do seu banco e escolha <strong>Pagar com Pix</strong>.</li>
                <li>Escaneie o QR Code ou cole o código "copia e cola".</li>
                <li>Confira o valor de <strong><?= $totalFmt ?></strong> e confirme.</li>
              </ol>
              <label for="pix-code">Pix copia e cola</label>
              <textarea id="pix-code" class="copy-field" rows="4" readonly><?= htmlspecialchars($pixPayload) ?></textarea>
              <button type="button" class="btn btn-ghost" data-copiar="pix-code">Copiar código</button>
            </div>
          </div>

          <form method="post" action="index.php?url=pedido/simularPagamento/<?= $idPedido ?>" class="pay-actions">
            <button type="submit" class="btn btn-accent">Já paguei — simular confirmação</button>
          </form>

        <?php elseif ($forma === 'cartao_credito' || $forma === 'cartao_debito'): ?>
          <!-- ================= CARTÃO ================= -->
          <?php $salvoSel = (int) ($form['cartao_salvo'] ?? (empty($cartoes) ? 0 : $cartoes[0]['id'])); ?>
          <div class="cc-layout">
            <div>
              <div class="cc-preview" id="cc-preview" aria-hidden="true">
                <div class="cc-top"><span id="cc-bandeira">Cartão</span><span class="cc-chip"></span></div>
                <div class="cc-number" id="cc-numero">•••• •••• •••• ••••</div>
                <div class="cc-row"><span id="cc-nome">Nome no cartão</span><span id="cc-validade">MM/AA</span></div>
              </div>
              <p class="pay-hint">
                Cartão de teste: <button type="button" class="link-btn" id="btn-cartao-teste">preencher automaticamente</button><br>
                Cartões terminados em <strong>0002</strong> simulam pagamento recusado.
              </p>
            </div>

            <div>
              <form method="post" action="index.php?url=pedido/pagarCartao/<?= $idPedido ?>" id="form-cartao" autocomplete="off" novalidate>

                <?php if (!empty($cartoes)): ?>
                  <div class="saved-cards" role="radiogroup" aria-label="Cartões salvos">
                    <?php foreach ($cartoes as $c): ?>
                      <label class="saved-card">
                        <input type="radio" name="cartao_salvo" value="<?= (int) $c['id'] ?>" <?= $salvoSel === (int) $c['id'] ? 'checked' : '' ?>
                               data-bandeira="<?= htmlspecialchars($c['bandeira']) ?>" data-final="<?= htmlspecialchars($c['ultimos4']) ?>"
                               data-titular="<?= htmlspecialchars($c['titular']) ?>" data-validade="<?= htmlspecialchars($c['validade']) ?>">
                        <span class="saved-body">
                          <strong><?= htmlspecialchars($c['bandeira']) ?></strong> final <?= htmlspecialchars($c['ultimos4']) ?>
                          <small><?= htmlspecialchars($c['titular']) ?> · validade <?= htmlspecialchars($c['validade']) ?></small>
                        </span>
                      </label>
                    <?php endforeach; ?>
                    <label class="saved-card">
                      <input type="radio" name="cartao_salvo" value="0" <?= $salvoSel === 0 ? 'checked' : '' ?>>
                      <span class="saved-body"><strong>Usar outro cartão</strong><small>Informe os dados de um novo cartão</small></span>
                    </label>
                  </div>
                <?php else: ?>
                  <input type="hidden" name="cartao_salvo" value="0">
                <?php endif; ?>

                <div id="campos-novo-cartao">
                  <div class="form-row">
                    <label for="cc-input-numero">Número do cartão</label>
                    <input type="text" id="cc-input-numero" name="numero" inputmode="numeric" autocomplete="off" maxlength="23"
                           placeholder="0000 0000 0000 0000" value="<?= htmlspecialchars($form['numero'] ?? '') ?>">
                  </div>
                  <div class="form-row">
                    <label for="cc-input-nome">Nome impresso no cartão</label>
                    <input type="text" id="cc-input-nome" name="nome" autocomplete="off" placeholder="COMO ESTÁ NO CARTÃO"
                           value="<?= htmlspecialchars($form['nome'] ?? '') ?>">
                  </div>
                </div>

                <div class="form-2col">
                  <div class="form-row" id="campo-validade">
                    <label for="cc-input-validade">Validade</label>
                    <input type="text" id="cc-input-validade" name="validade" inputmode="numeric" maxlength="5" autocomplete="off"
                           placeholder="MM/AA" value="<?= htmlspecialchars($form['validade'] ?? '') ?>">
                  </div>
                  <div class="form-row">
                    <label for="cc-input-cvv">CVV</label>
                    <input type="password" id="cc-input-cvv" name="cvv" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="123">
                  </div>
                </div>

                <label class="check-row" id="campo-salvar">
                  <input type="checkbox" name="salvar_cartao" value="1" <?= !empty($form['salvar']) ? 'checked' : '' ?>>
                  Salvar este cartão para as próximas compras <small>(guardamos só a bandeira e os 4 últimos dígitos)</small>
                </label>

                <?php if ($credito): ?>
                  <div class="form-row">
                    <label for="cc-parcelas">Parcelas</label>
                    <select id="cc-parcelas" name="parcelas">
                      <?php for ($n = 1; $n <= $maxParcelas; $n++): ?>
                        <option value="<?= $n ?>" <?= (int) ($form['parcelas'] ?? 1) === $n ? 'selected' : '' ?>>
                          <?= $n ?>x de R$ <?= number_format($pedido['valor_total'] / $n, 2, ',', '.') ?> sem juros
                        </option>
                      <?php endfor; ?>
                    </select>
                  </div>
                <?php else: ?>
                  <p class="pay-hint">Débito: pagamento à vista de <strong><?= $totalFmt ?></strong>.</p>
                <?php endif; ?>

                <div class="alert alert-error" id="cartao-erro" hidden></div>
                <button type="submit" class="btn btn-accent btn-block">Pagar <?= $totalFmt ?></button>
                <p class="pay-hint" style="margin-top:10px;">Por segurança, o número completo e o CVV <strong>não são armazenados</strong>.</p>
              </form>

              <?php if (!empty($cartoes)): ?>
                <details class="saved-manage">
                  <summary>Gerenciar cartões salvos</summary>
                  <?php foreach ($cartoes as $c): ?>
                    <form method="post" action="index.php?url=pedido/removerCartao/<?= (int) $c['id'] ?>" class="saved-remove"
                          onsubmit="return confirm('Remover este cartão salvo?');">
                      <input type="hidden" name="pedido" value="<?= $idPedido ?>">
                      <span><?= htmlspecialchars($c['bandeira']) ?> final <?= htmlspecialchars($c['ultimos4']) ?></span>
                      <button type="submit" class="link-btn danger">Remover</button>
                    </form>
                  <?php endforeach; ?>
                </details>
              <?php endif; ?>
            </div>
          </div>

        <?php elseif ($forma === 'boleto'): ?>
          <!-- ================= BOLETO ================= -->
          <div class="boleto" id="boleto">
            <div class="boleto-head">
              <strong>Boleto bancário</strong>
              <span>999 | Banco Núcleo (fictício)</span>
            </div>
            <div class="boleto-grid">
              <div><span>Beneficiário</span><?= htmlspecialchars(BOLETO_BENEFICIARIO) ?></div>
              <div><span>Pagador</span><?= htmlspecialchars($cliente['nome'] ?? '') ?></div>
              <div><span>Nosso número</span><?= $boleto['nosso_numero'] ?></div>
              <div><span>Data do documento</span><?= date('d/m/Y', strtotime($pedido['data_pedido'])) ?></div>
              <div><span>Vencimento</span><strong><?= $boleto['vencimento'] ?></strong></div>
              <div><span>Valor do documento</span><strong><?= $totalFmt ?></strong></div>
            </div>
            <span class="boleto-label">Linha digitável</span>
            <div class="boleto-linha" id="boleto-linha"><?= $boleto['linha'] ?></div>
            <?= PagamentoSimulado::codigoBarrasSvg($boleto['codigo']) ?>
          </div>

          <div class="pay-actions">
            <button type="button" class="btn btn-ghost" data-copiar="boleto-linha">Copiar linha digitável</button>
            <button type="button" class="btn btn-ghost" onclick="window.print()">Imprimir boleto</button>
            <form method="post" action="index.php?url=pedido/simularPagamento/<?= $idPedido ?>" style="display:inline;">
              <button type="submit" class="btn btn-accent">Já paguei — simular confirmação</button>
            </form>
          </div>
          <p class="pay-hint">Em um boleto real, a compensação leva até 3 dias úteis após o pagamento.</p>
        <?php endif; ?>

        <form method="post" action="index.php?url=pedido/cancelar/<?= $idPedido ?>" class="pay-cancel"
              onsubmit="return confirm('Cancelar este pedido? Os itens voltam ao estoque.');">
          <button type="submit" class="link-btn danger">Cancelar pedido</button>
          <a href="index.php?url=pedido/checkout" class="link-btn">Trocar forma de pagamento</a>
          <a href="index.php?url=cliente/meusPedidos" class="link-btn">Pagar depois (ver meus pedidos)</a>
        </form>
      </div>

      <aside class="cart-summary">
        <h3 style="margin-top:0; font-size:14px;">Pedido #<?= $idPedido ?></h3>
        <?php foreach ($itens as $item): ?>
          <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:8px;">
            <span><?= $item['quantidade'] ?>x <?= htmlspecialchars($item['nome']) ?></span>
            <span>R$ <?= number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.') ?></span>
          </div>
        <?php endforeach; ?>
        <div class="total-row"><span>Total</span><span><?= $totalFmt ?></span></div>
        <p style="font-size:12px; color:var(--muted); margin:12px 0 0;">Entrega em: <?= htmlspecialchars($pedido['endereco_entrega']) ?></p>
      </aside>
    </div>
  </section>
</div>

<script src="assets/js/qrcode.js"></script>
<script src="assets/js/pagamento.js"></script>
<?php require __DIR__ . '/../layout/footer.php'; ?>
