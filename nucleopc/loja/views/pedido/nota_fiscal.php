<?php
require_once __DIR__ . '/../../services/PagamentoSimulado.php';
$tituloPagina = 'Nota fiscal nº ' . (int) $nota['numero'] . ' — Núcleo PC';
require __DIR__ . '/../layout/header.php';

$formaRotulo = PagamentoSimulado::rotulo($pedido['forma_pagamento']);
$chaveFmt = trim(chunk_split($chaveAcesso, 4, ' '));
$enderecoCliente = trim(($cliente['endereco'] ?? '') . ($cliente['numero'] ? ', ' . $cliente['numero'] : '') . ($cliente['bairro'] ? ' - ' . $cliente['bairro'] : '') . ($cliente['cidade'] ? ' - ' . $cliente['cidade'] . '/' . ($cliente['uf'] ?? '') : ''), ' ,-');
?>

<div class="container">
  <section class="section">
    <div class="nf-sheet" id="nota-imprimivel">
      <div class="nf-head">
        <div class="nf-emitente">
          <img src="assets/img/logo-icon.svg" alt="Núcleo PC" class="nf-logo">
          <div>
            <strong><?= EMPRESA_NOME ?></strong>
            <span>CNPJ: <?= EMPRESA_CNPJ ?> · IE: <?= EMPRESA_IE ?></span>
            <span><?= EMPRESA_ENDERECO ?></span>
            <span>Tel.: <?= EMPRESA_TELEFONE ?></span>
          </div>
        </div>
        <div class="nf-numero">
          <strong>NOTA FISCAL</strong>
          <span>Nº <?= (int) $nota['numero'] ?> · Série <?= (int) $nota['serie'] ?></span>
          <span>Modelo NFC-e</span>
          <span>Emissão: <?= date('d/m/Y H:i', strtotime($nota['data_emissao'])) ?></span>
        </div>
      </div>

      <div class="nf-bloco">
        <h4>Destinatário</h4>
        <p><strong><?= htmlspecialchars($cliente['nome'] ?? '') ?></strong>
          <?php if (!empty($cliente['cpf'])): ?> · CPF <?= htmlspecialchars($cliente['cpf']) ?><?php endif; ?></p>
        <p><?= htmlspecialchars($pedido['endereco_entrega'] ?: $enderecoCliente) ?></p>
      </div>

      <table class="nf-table">
        <thead>
          <tr><th>Código</th><th>Descrição</th><th class="r">Qtd</th><th class="r">V. unit.</th><th class="r">V. total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($itens as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['sku']) ?></td>
              <td><?= htmlspecialchars($item['nome']) ?></td>
              <td class="r"><?= (int) $item['quantidade'] ?></td>
              <td class="r">R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
              <td class="r">R$ <?= number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="nf-total">
        <span>Pedido #<?= (int) $pedido['id'] ?> · Pagamento: <?= htmlspecialchars($formaRotulo) ?><?= !empty($pedido['pagamento_id']) && $pedido['forma_pagamento'] !== 'boleto' ? ' (' . htmlspecialchars($pedido['pagamento_id']) . ')' : '' ?></span>
        <div class="nf-total-box">VALOR TOTAL DA NOTA<strong>R$ <?= number_format($nota['valor_total'], 2, ',', '.') ?></strong></div>
      </div>

      <p class="nf-chave"><strong>Chave de acesso:</strong> <?= $chaveFmt ?></p>
      <p class="nf-aviso">Documento representativo (projeto acadêmico), sem valor fiscal. Emitido automaticamente na confirmação do pagamento.</p>
    </div>

    <div class="nf-actions no-print">
      <button type="button" class="btn btn-accent" onclick="window.print()">Imprimir / salvar em PDF</button>
      <a href="index.php?url=pedido/retorno&amp;pedido=<?= (int) $pedido['id'] ?>" class="btn btn-ghost">Voltar ao pedido</a>
      <a href="index.php?url=cliente/meusPedidos" class="btn btn-ghost">Meus pedidos</a>
    </div>
  </section>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
