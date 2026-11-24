<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Redefinir senha · Núcleo PC</title>
  <link rel="stylesheet" href="assets/css/admin.css">
  <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon-32.png">
  <link rel="apple-touch-icon" href="assets/img/logo-192.png">
  <script src="assets/js/verificacao.js" defer></script>
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand">
      <img class="brand-img" src="assets/img/logo-icon.svg" alt="Núcleo PC">
      <div class="brand-text">
        <strong>Núcleo PC</strong>
        <span>nova senha</span>
      </div>
    </div>

    <?php if (!$valido): ?>
      <div class="alert alert-error">Este link de redefinição é inválido ou já expirou. Solicite um novo link.</div>
      <div style="text-align:center; margin-top:14px;">
        <a href="index.php?url=auth/esqueciSenha" class="btn btn-primary" style="width:100%; justify-content:center;">Solicitar novo link</a>
      </div>
    <?php else: ?>
      <?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
      <form method="post" action="index.php?url=auth/redefinirSenha/<?= htmlspecialchars($token) ?>">
        <div class="form-row">
          <label for="senha">Nova senha</label>
          <input type="password" id="senha" name="senha" minlength="8" data-senha-min="8" required autofocus>
        </div>
        <div class="form-row">
          <label for="confirmacao">Confirme a nova senha</label>
          <input type="password" id="confirmacao" name="confirmacao" minlength="8" data-confirma="senha" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Salvar nova senha</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
