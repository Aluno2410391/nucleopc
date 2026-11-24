<?php
$tituloPagina = 'Mensagem de contato';
$subtituloPagina = 'Recebida pela Loja Online em ' . date('d/m/Y \à\s H:i', strtotime($mensagem['criado_em']));
require __DIR__ . '/../layout/header.php';

$assuntoEmail = $mensagem['assunto'] !== '' ? $mensagem['assunto'] : 'sua mensagem';
$linkResposta = 'mailto:' . rawurlencode($mensagem['email']) . '?subject=' . rawurlencode('Re: ' . $assuntoEmail);
?>

<div class="card" style="max-width:680px;">
  <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px;">
    <div>
      <h2 style="margin:0 0 4px;"><?= htmlspecialchars($mensagem['nome']) ?></h2>
      <div class="text-muted" style="font-size:13px;"><?= htmlspecialchars($mensagem['email']) ?></div>
    </div>
    <?php if ((int) $mensagem['respondido'] === 1): ?>
      <span class="badge badge-off">Respondida</span>
    <?php else: ?>
      <span class="badge badge-warn">Nova</span>
    <?php endif; ?>
  </div>

  <div class="form-row">
    <label>Assunto</label>
    <div><?= htmlspecialchars($mensagem['assunto'] !== '' ? $mensagem['assunto'] : '(sem assunto)') ?></div>
  </div>

  <div class="form-row">
    <label>Mensagem</label>
    <div style="background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); padding:14px; white-space:pre-wrap; line-height:1.6;"><?= htmlspecialchars($mensagem['mensagem']) ?></div>
  </div>

  <div class="row-actions" style="margin-top:18px; flex-wrap:wrap;">
    <a href="<?= htmlspecialchars($linkResposta) ?>" class="btn btn-primary">Responder por e-mail</a>

    <?php if ((int) $mensagem['respondido'] === 1): ?>
      <form method="post" action="index.php?url=contato/reabrir/<?= $mensagem['id'] ?>" style="display:inline;">
        <button type="submit" class="btn btn-ghost">Reabrir</button>
      </form>
    <?php else: ?>
      <form method="post" action="index.php?url=contato/marcarRespondido/<?= $mensagem['id'] ?>" style="display:inline;">
        <button type="submit" class="btn btn-ghost">Marcar como respondida</button>
      </form>
    <?php endif; ?>

    <form method="post" action="index.php?url=contato/excluir/<?= $mensagem['id'] ?>" style="display:inline;" onsubmit="return confirm('Excluir esta mensagem definitivamente?');">
      <button type="submit" class="btn btn-danger">Excluir</button>
    </form>

    <a href="index.php?url=contato/index" class="btn btn-ghost">Voltar</a>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
