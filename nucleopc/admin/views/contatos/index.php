<?php
$tituloPagina = 'Mensagens de contato';
$subtituloPagina = 'Dúvidas e mensagens enviadas pelos clientes na Loja Online';
require __DIR__ . '/../layout/header.php';

$avisos = [
  'respondido' => ['ok', 'Mensagem marcada como respondida.'],
  'reaberto'   => ['ok', 'Mensagem reaberta.'],
  'excluido'   => ['ok', 'Mensagem excluída.'],
];
$aviso = $avisos[$msg ?? ''] ?? null;
?>

<?php if ($aviso): ?>
  <div class="alert alert-success"><?= htmlspecialchars($aviso[1]) ?></div>
<?php endif; ?>

<div class="card">
  <table>
    <thead>
      <tr><th>Status</th><th>Remetente</th><th>Assunto</th><th>Recebida em</th><th></th></tr>
    </thead>
    <tbody>
      <?php if (empty($mensagens)): ?>
        <tr><td colspan="5" class="text-muted">Nenhuma mensagem recebida ainda.</td></tr>
      <?php else: foreach ($mensagens as $m): ?>
        <tr>
          <td>
            <?php if ((int) $m['respondido'] === 1): ?>
              <span class="badge badge-off">Respondida</span>
            <?php else: ?>
              <span class="badge badge-warn">Nova</span>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= htmlspecialchars($m['nome']) ?></strong>
            <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars($m['email']) ?></div>
          </td>
          <td><?= htmlspecialchars($m['assunto'] !== '' ? $m['assunto'] : '(sem assunto)') ?></td>
          <td class="mono"><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="index.php?url=contato/ver/<?= $m['id'] ?>">Ver mensagem</a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
