<?php
/**
 * Funções de apoio para as views.
 */

/** URL da imagem de um produto: aceita um link completo (http/https) ou o nome de um arquivo em uploads/produtos/. */
function imagem_url(?string $imagem): string
{
    $imagem = trim((string) $imagem);
    if ($imagem === '') {
        return UPLOAD_URL . 'sem-imagem.png';
    }
    if (preg_match('#^https?://#i', $imagem)) {
        return $imagem;
    }
    return UPLOAD_URL . $imagem;
}

/**
 * Link (http/https) da imagem de uma categoria, guardado em uploads/categorias/categoria_{id}.url
 * (mesma pasta da imagem enviada, sem coluna nova no banco). Retorna null se não houver link válido.
 */
function categoria_link_imagem(int $id): ?string
{
    $arq = CATEGORIA_DIR . 'categoria_' . $id . '.url';
    if (!is_file($arq)) {
        return null;
    }
    $url = trim((string) @file_get_contents($arq));
    return preg_match('#^https?://#i', $url) ? $url : null;
}

/**
 * Imagem de uma categoria (ou null se não houver). Ordem: arquivo enviado pelo painel
 * (categoria_{id}.ext), link informado no painel (categoria_{id}.url) e, por último,
 * arquivo colocado à mão ({slug}.ext) em uploads/categorias/.
 */
function categoria_imagem(int $id, string $slug = ''): ?string
{
    foreach (['categoria_' . $id, $slug] as $base) {
        if ($base === '') { continue; }
        foreach (['png', 'jpg', 'jpeg', 'webp', 'svg'] as $ext) {
            if (is_file(CATEGORIA_DIR . $base . '.' . $ext)) {
                return CATEGORIA_URL . $base . '.' . $ext;
            }
        }
        if ($base === 'categoria_' . $id && ($link = categoria_link_imagem($id))) {
            return $link;
        }
    }
    return null;
}
