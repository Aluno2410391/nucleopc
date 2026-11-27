<?php
require_once __DIR__ . '/../core/Controller.php';

class CarrinhoController extends Controller
{
    public function index(): void
    {
        $carrinho = $this->model('Carrinho');
        $this->view('carrinho/index', [
            'itens' => $carrinho->itens(),
            'total' => $carrinho->total(),
        ]);
    }

    public function adicionar(): void
    {
        $produtoId  = (int) ($_POST['produto_id'] ?? 0);
        $quantidade = max(1, (int) ($_POST['quantidade'] ?? 1));

        $produtoModel = $this->model('Produto');
        $produto = $produtoModel->buscarPorId($produtoId);

        if ($produto) {
            $carrinho = $this->model('Carrinho');
            $carrinho->adicionar($produto, $quantidade);
        }

        $this->redirect('carrinho/index');
    }

    public function atualizar(): void
    {
        $carrinho = $this->model('Carrinho');
        $quantidades = $_POST['quantidade'] ?? [];
        foreach ($quantidades as $produtoId => $qtd) {
            $carrinho->atualizarQuantidade((int) $produtoId, (int) $qtd);
        }
        $this->redirect('carrinho/index');
    }

    /**
     * Atualiza a quantidade de UM item sem recarregar a página (chamado por assets/js/carrinho.js).
     * Respeita o estoque: se pedirem mais do que existe, ajusta para o máximo disponível.
     */
    public function atualizarAjax(): void
    {
        @ini_set('display_errors', '0');
        $carrinho  = $this->model('Carrinho');
        $produtoId = (int) ($_POST['produto_id'] ?? 0);
        $qtd       = max(1, (int) ($_POST['quantidade'] ?? 1));

        if (!isset($carrinho->itens()[$produtoId])) {
            $this->responderJson(['ok' => false], 404);
        }

        $aviso = null;
        $produto = $this->model('Produto')->buscarPorId($produtoId);
        if ($produto && (int) $produto['estoque'] > 0 && $qtd > (int) $produto['estoque']) {
            $qtd = (int) $produto['estoque'];
            $aviso = 'Só temos ' . $qtd . ' unidade(s) em estoque deste produto.';
        }

        $carrinho->atualizarQuantidade($produtoId, $qtd);
        $item = $carrinho->itens()[$produtoId];

        $this->responderJson([
            'ok'         => true,
            'quantidade' => $qtd,
            'subtotal'   => number_format($item['preco'] * $qtd, 2, ',', '.'),
            'total'      => number_format($carrinho->total(), 2, ',', '.'),
            'itens'      => $carrinho->quantidadeTotal(),
            'aviso'      => $aviso,
        ]);
    }

    private function responderJson(array $dados, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function remover(string $id): void
    {
        $carrinho = $this->model('Carrinho');
        $carrinho->remover((int) $id);
        $this->redirect('carrinho/index');
    }
}
