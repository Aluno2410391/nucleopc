// A busca de endereço por CEP (e a verificação de CNPJ, CPF e e-mail) agora está em
// assets/js/verificacao.js, que usa a API interna index.php?url=api/...

// Adiciona uma nova linha de item em formulários de entrada/venda
function adicionarLinhaItem(containerId, templateId) {
  const container = document.getElementById(containerId);
  const template = document.getElementById(templateId);
  if (!container || !template) return;
  const clone = template.content.cloneNode(true);
  container.appendChild(clone);
}

// Recalcula o total ao digitar quantidade/preço
function recalcularTotal(containerId, totalId) {
  const container = document.getElementById(containerId);
  const totalEl = document.getElementById(totalId);
  if (!container || !totalEl) return;

  let total = 0;
  container.querySelectorAll('.item-line').forEach((linha) => {
    const qtd = parseFloat(linha.querySelector('.qtd-input')?.value || 0);
    const preco = parseFloat((linha.querySelector('.preco-input')?.value || '0').replace(',', '.'));
    total += qtd * preco;
  });
  totalEl.textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
}

// ---------- Menu mobile (sidebar em overlay) ----------
// Em telas estreitas o menu lateral vira um painel deslizante, aberto
// pelo botão "hambúrguer" no topo. Sem isso, no celular o usuário ficava
// preso na tela atual, sem conseguir navegar para as outras seções.
(function () {
  const botao = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  if (!botao || !sidebar || !backdrop) return;

  function fechar() {
    sidebar.classList.remove('open');
    backdrop.classList.remove('visible');
    botao.setAttribute('aria-expanded', 'false');
  }
  function alternar() {
    const abrindo = !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', abrindo);
    backdrop.classList.toggle('visible', abrindo);
    botao.setAttribute('aria-expanded', abrindo ? 'true' : 'false');
  }

  botao.addEventListener('click', alternar);
  backdrop.addEventListener('click', fechar);
  // Fecha o menu ao navegar para outra página do painel
  sidebar.querySelectorAll('a.nav-link').forEach((link) => link.addEventListener('click', fechar));
  // Se a tela for redimensionada para desktop, garante que o overlay não fique "preso" aberto
  window.addEventListener('resize', function () {
    if (window.innerWidth > 860) fechar();
  });
})();

