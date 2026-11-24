<?php
require_once __DIR__ . '/../core/Controller.php';

class VendaController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $pedidoModel = $this->model('Pedido');
        $pedidoModel->expirarPendentes(); // cancela pedidos online vencidos (RN10) e devolve o estoque
        $this->view('vendas/index', [
            'vendas' => $pedidoModel->listarRecentes(20),
        ]);
    }

    public function nova(): void
    {
        $this->requireLogin();
        $this->exibirFormulario(null, 'pix');
    }

    public function finalizar(): void
    {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('venda/nova');
        }

        $produtosId  = $_POST['produto_id'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $precos      = $_POST['preco_unitario'] ?? [];
        $forma       = $_POST['forma_pagamento'] ?? '';

        $itens = [];
        foreach ($produtosId as $i => $produtoId) {
            $qtd = (int) ($quantidades[$i] ?? 0);
            if ($produtoId !== '' && $qtd > 0) {
                $itens[] = [
                    'produto_id'     => (int) $produtoId,
                    'quantidade'     => $qtd,
                    'preco_unitario' => (float) str_replace(',', '.', $precos[$i] ?? '0'),
                ];
            }
        }

        if (empty($itens)) {
            $this->exibirFormulario('Adicione ao menos um item com quantidade maior que zero.', $forma);
            return;
        }
        $pedidoModel = $this->model('Pedido'); // também carrega a classe (constante FORMAS_PAGAMENTO)
        if (!isset(Pedido::FORMAS_PAGAMENTO[$forma])) {
            $this->exibirFormulario('Escolha a forma de pagamento da venda.', 'pix');
            return;
        }

        try {
            $pedidoId = $pedidoModel->registrarVendaBalcao(null, (int) $_SESSION['usuario_id'], $itens, $forma);
            // A nota fiscal já foi emitida automaticamente: abre o documento para entregar ao cliente.
            $this->redirect('venda/notaFiscal/' . $pedidoId . '?msg=venda_registrada');
        } catch (Exception $e) {
            $this->exibirFormulario($e->getMessage(), $forma);
        }
    }

    public function notaFiscal(string $id): void
    {
        $this->requireLogin();
        $pedidoModel = $this->model('Pedido');

        $pedido = $pedidoModel->buscarPorId((int) $id);
        if (!$pedido) {
            $this->redirect('venda/index');
        }

        try {
            $nota = $pedidoModel->emitirNotaFiscal((int) $id);
        } catch (Exception $e) {
            $this->redirect('venda/index?msg=erro_nota');
            return;
        }

        $this->view('vendas/nota_fiscal', [
            'pedido'       => $pedido,
            'nota'         => $nota,
            'itens'        => $pedidoModel->itensDoPedido((int) $id),
            'chaveAcesso'  => Pedido::chaveAcesso($nota),
            'historico'    => $pedidoModel->historicoPagamento((int) $id),
        ]);
    }

    /**
     * UC08 — Confirma manualmente o pagamento de um pedido online "aguardando
     * pagamento" (uso típico: boleto). Registra quem confirmou e emite a nota fiscal.
     */
    public function confirmarPagamento(string $id): void
    {
        $this->requireLogin();
        $this->exigirPost();
        $confirmado = $this->model('Pedido')->confirmarPagamento((int) $id, (int) $_SESSION['usuario_id']);
        $this->redirect('venda/index?msg=' . ($confirmado ? 'pagamento_confirmado' : 'erro_pagamento'));
    }

    /** Cancela um pedido online ainda não pago e devolve os itens ao estoque. */
    public function cancelarPedido(string $id): void
    {
        $this->requireLogin();
        $this->exigirPost();
        $cancelado = $this->model('Pedido')->cancelarPedido((int) $id, (int) $_SESSION['usuario_id']);
        $this->redirect('venda/index?msg=' . ($cancelado ? 'pedido_cancelado' : 'erro_cancelamento'));
    }

    private function exigirPost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('venda/index');
        }
    }

    private function exibirFormulario(?string $erro, string $forma): void
    {
        $this->model('Pedido'); // carrega a classe (constante FORMAS_PAGAMENTO usada na view)
        $this->view('vendas/form', [
            'produtos' => $this->model('Produto')->listarTodos(),
            'erro'     => $erro,
            'forma'    => $forma,
        ]);
    }
}
