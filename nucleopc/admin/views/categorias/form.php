<?php
$tituloPagina = !empty($categoria['id']) ? 'Editar categoria' : 'Nova categoria';
$subtituloPagina = 'O identificador (slug) é gerado automaticamente a partir do nome';
require __DIR__ . '/../layout/header.php';
?>

<?php if ($erro): ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

<div class="card" style="max-width:560px;">
  <form method="post" action="index.php?url=categoria/salvar" enctype="multipart/form-data">
    <?php if (!empty($categoria['id'])): ?>
      <input type="hidden" name="id" value="<?= $categoria['id'] ?>">
    <?php endif; ?>

    <div class="form-row">
      <label for="nome">Nome da categoria</label>
      <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($categoria['nome'] ?? '') ?>" required autofocus>
    </div>

    <div class="form-row">
      <label for="descricao">Descrição (opcional)</label>
      <textarea id="descricao" name="descricao"><?= htmlspecialchars($categoria['descricao'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <label for="imagem_arquivo">Imagem da categoria (JPG, PNG ou WEBP, até 2MB) — aparece na página inicial da loja</label>
      <input type="file" id="imagem_arquivo" name="imagem_arquivo" accept=".jpg,.jpeg,.png,.webp">
    </div>
    <div class="form-row">
      <label for="imagem_url">ou usar um link de imagem (URL)</label>
      <input type="url" id="imagem_url" name="imagem_url" maxlength="255" placeholder="https://exemplo.com/categoria.jpg"
             value="<?= (!empty($categoria['id']) && ($linkAtual = categoria_link_imagem((int) $categoria['id']))) ? htmlspecialchars($linkAtual) : '' ?>">
      <small class="text-muted">Se você enviar um arquivo, ele vale mais que o link. Para voltar a usar arquivo, apague o link e envie a imagem.</small>
    </div>
    <?php if (!empty($categoria['id']) && ($imgAtual = categoria_imagem((int) $categoria['id'], $categoria['slug'] ?? ''))): ?>
      <div class="form-row">
        <label>Imagem atual</label>
        <img src="<?= htmlspecialchars($imgAtual) ?>" alt="" style="width:120px; height:80px; object-fit:cover; border-radius:6px; border:1px solid var(--border);">
        <label style="font-weight:400; margin-top:6px;"><input type="checkbox" name="remover_imagem" value="1"> Remover imagem</label>
      </div>
    <?php endif; ?>

    <?php if (!empty($categoria['slug'])): ?>
      <p class="text-muted" style="font-size:12px;">Slug atual: <span class="mono"><?= htmlspecialchars($categoria['slug']) ?></span> (atualizado automaticamente se o nome mudar)</p>
    <?php endif; ?>

    <div class="row-actions" style="margin-top:8px;">
      <button type="submit" class="btn btn-primary">Salvar</button>
      <a href="index.php?url=categoria/index" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
