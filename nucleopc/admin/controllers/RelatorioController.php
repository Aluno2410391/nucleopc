<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/XlsxWriter.php';

class RelatorioController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();

        $pedidoModel  = $this->model('Pedido');
        $produtoModel = $this->model('Produto');
        $entradaModel = $this->model('Entrada');

        $this->view('relatorios/index', [
            'maisVendidos'      => $pedidoModel->produtosMaisVendidos(10),
            'vendasPorOrigem'   => $pedidoModel->totalVendasPorOrigem(),
            'fornecedores'      => $pedidoModel->fornecedoresResumo(),
            'entradasRecentes'  => $entradaModel->listarRecentes(10),
            'estoqueBaixo'      => $produtoModel->listarEstoqueBaixo(),
            'geradoEm'          => date('d/m/Y H:i'),
        ]);
    }

    /**
     * Exporta o relatório completo em Excel (.xlsx de verdade), uma aba por quadro da tela.
     * Gerado só com PHP nativo (ZipArchive) — veja core/XlsxWriter.php.
     */
    public function exportarExcel(): void
    {
        $this->requireAdmin();
        $this->silenciarErros();

        $pedidoModel  = $this->model('Pedido');
        $produtoModel = $this->model('Produto');

        $x = new XlsxWriter();

        $x->addSheet('Mais vendidos',
            ['Produto', 'Qtde. vendida'],
            array_map(fn($p) => [$p['nome'], $p['qtde_vendida']], $pedidoModel->produtosMaisVendidos(50)),
            ['text', 'int']
        );

        $x->addSheet('Vendas por origem',
            ['Origem', 'Pedidos', 'Valor total'],
            array_map(fn($v) => [
                $v['origem'] === 'online' ? 'Loja Online' : 'Loja Física',
                $v['total_pedidos'],
                $v['valor_total'],
            ], $pedidoModel->totalVendasPorOrigem()),
            ['text', 'int', 'money']
        );

        $x->addSheet('Fornecedores',
            ['Fornecedor', 'Entradas', 'Valor total'],
            array_map(fn($f) => [$f['nome'], $f['entradas'], $f['valor_total']], $pedidoModel->fornecedoresResumo()),
            ['text', 'int', 'money']
        );

        $x->addSheet('Estoque baixo',
            ['Produto', 'SKU', 'Qtde.', 'Mínimo'],
            array_map(fn($p) => [$p['nome'], $p['sku'], $p['estoque'], $p['estoque_minimo']], $produtoModel->listarEstoqueBaixo()),
            ['text', 'text', 'int', 'int']
        );

        $x->download('relatorio_nucleopc_' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Alternativa em CSV com TODAS as seções do relatório, uma abaixo da outra.
     * (CSV não tem abas; por isso cada quadro vira um bloco com título.)
     *  - BOM UTF-8 (senão "Núcleo" vira "NÃºcleo")
     *  - separador ";" (o Excel em pt-BR usa ";" e não ",")
     *  - sem nenhum byte extra antes (erros/espaços na tela corrompem o arquivo)
     */
    public function exportarCsv(): void
    {
        $this->requireAdmin();
        $this->silenciarErros();
        $pedidoModel  = $this->model('Pedido');
        $produtoModel = $this->model('Produto');

        $dinheiro = fn($v) => number_format((float) $v, 2, ',', '');

        $maisVendidos = $pedidoModel->produtosMaisVendidos(50);
        $porOrigem    = $pedidoModel->totalVendasPorOrigem();
        $fornecedores = $pedidoModel->fornecedoresResumo();
        $estoqueBaixo = $produtoModel->listarEstoqueBaixo();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="relatorio_nucleopc_' . date('Y-m-d') . '.csv"');

        $o = fopen('php://output', 'w');
        fwrite($o, "\xEF\xBB\xBF"); // BOM UTF-8

        fputcsv($o, ['PRODUTOS MAIS VENDIDOS'], ';');
        fputcsv($o, ['Produto', 'Qtde. vendida'], ';');
        foreach ($maisVendidos as $p) {
            fputcsv($o, [$p['nome'], $p['qtde_vendida']], ';');
        }

        fputcsv($o, [], ';');
        fputcsv($o, ['VENDAS POR ORIGEM'], ';');
        fputcsv($o, ['Origem', 'Pedidos', 'Valor total (R$)'], ';');
        foreach ($porOrigem as $v) {
            fputcsv($o, [$v['origem'] === 'online' ? 'Loja Online' : 'Loja Física', $v['total_pedidos'], $dinheiro($v['valor_total'])], ';');
        }

        fputcsv($o, [], ';');
        fputcsv($o, ['FORNECEDORES'], ';');
        fputcsv($o, ['Fornecedor', 'Entradas', 'Valor total (R$)'], ';');
        foreach ($fornecedores as $f) {
            fputcsv($o, [$f['nome'], $f['entradas'], $dinheiro($f['valor_total'])], ';');
        }

        fputcsv($o, [], ';');
        fputcsv($o, ['ESTOQUE BAIXO'], ';');
        fputcsv($o, ['Produto', 'SKU', 'Qtde.', 'Mínimo'], ';');
        foreach ($estoqueBaixo as $p) {
            fputcsv($o, [$p['nome'], $p['sku'], $p['estoque'], $p['estoque_minimo']], ';');
        }

        fclose($o);
        exit;
    }

    /** O index.php liga display_errors; num download isso jogaria o texto do erro dentro do arquivo. */
    private function silenciarErros(): void
    {
        ini_set('display_errors', '0');
        ob_start();
    }
}