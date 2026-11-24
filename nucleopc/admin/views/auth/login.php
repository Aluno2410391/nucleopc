<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar · Núcleo PC</title>
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
        <span>painel interno</span>
      </div>
    </div>

    <?php if (!empty($erro)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'senha_redefinida'): ?>
      <div class="alert alert-success">Senha redefinida com sucesso. Faça login com a nova senha.</div>
    <?php endif; ?>

    <?php if (($_GET['msg'] ?? '') === 'conta_criada'): ?>
      <div class="alert alert-success">Conta criada com sucesso. Faça login para entrar.</div>
    <?php endif; ?>

    <form method="post" action="index.php?url=auth/login">
      <div class="form-row">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus>
      </div>
      <div class="form-row">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Entrar</button>
    </form>

    <div style="text-align:center; margin-top:14px;">
      <a href="index.php?url=auth/esqueciSenha" style="font-size:12.5px;">Esqueci minha senha</a>
    </div>

    <p style="text-align:center; font-size:12.5px; color:var(--muted); margin-top:10px;">
      O acesso ao painel é concedido pelo administrador da loja
    </p>
  </div>
</div>
</body>
</html>
