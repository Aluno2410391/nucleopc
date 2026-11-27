<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../services/PagamentoSimulado.php';

/**
 * PedidoController
 * Checkout e pagamento da Loja Online.
 *
 * O pagamento é REPRESENTATIVO: Pix, cartão e boleto têm o fluxo completo (telas,
 * validações, códigos, baixa de estoque, confirmação), mas nada é cobrado de verdade.
 *
 * Fluxo:  checkout -> finalizar (cria o pedido "aguardando_pagamento" e reserva o estoque)
 *         -> pagamento/{id} (Pix / cartão / boleto) -> retorno (confirmado ou cancelado)
 */
class PedidoController extends Controller
{
    // ------------------------------------------------------------------
    // Checkout
    // ------------------------------------------------------------------

    public function checkout(): void
    {
        $this->requireCliente();
        $this->model('Pedido')->expirarPendentes(); // libera o estoque de pedidos vencidos

        $carrinho = $this->model('Carrinho');
        if (empty($carrinho->itens())) {
            $this->redirect('carrinho/index');
        }

        $this->exibirCheckout($carrinho, null);
    }

    /**
     * Cria o pedido (status "aguardando_pagamento", com o estoque reservado) e
     * abre a tela de pagamento da forma escolhida.
     */
    public function finalizar(): void
    {
        $this->requireCliente();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pedido/checkout');
        }

        $carrinho = $this->model('Carrinho');
        $itens = $carrinho->itens();
        if (empty($itens)) {
            $this->redirect('carrinho/index');
        }

        $endereco = trim($_POST['endereco_entrega'] ?? '');
        $forma = $_POST['forma_pagamento'] ?? '';

        if ($endereco === '') {
            $this->exibirCheckout($carrinho, 'Informe o endereço de entrega.');
            return;
        }
        $codigosAtivos = array_column($this->model('Pedido')->formasAtivas(), 'codigo');
        if (!in_array($forma, $codigosAtivos, true) || !isset(PagamentoSimulado::FORMAS[$forma])) {
            $this->exibirCheckout($carrinho, 'Escolha uma forma de pagamento.');
            return;
        }

        $pedidoModel = $this->model('Pedido');

        // O cliente voltou da tela de pagamento para trocar a forma de pagar? Em vez de criar um
        // segundo pedido (e reservar o estoque de novo), atualiza o pedido que já está pendente.
        if ($pendente = $this->pedidoPendenteDaSessao()) {
            if ($pedidoModel->mesmosItens((int) $pendente['id'], $itens)) {
                try {
                    $pedidoModel->alterarPedidoPendente((int) $pendente['id'], $endereco, $forma);
                } catch (Exception $e) {
                    $this->exibirCheckout($carrinho, $e->getMessage());
                    return;
                }
                $this->redirect('pedido/pagamento/' . $pendente['id']);
            }
            // O carrinho foi alterado depois de gerar o pedido: cancela o antigo (o estoque volta) e cria outro.
            $pedidoModel->registrarResultado((int) $pendente['id'], 'cancelado', 'recusado', null, 'Substituído: o cliente alterou o carrinho antes de pagar');
            unset($_SESSION['pedido_pendente_id']);
        }

        try {
            $pedidoId = $pedidoModel->criarPedidoOnline((int) $_SESSION['cliente_id'], $itens, $endereco, $forma);
        } catch (Exception $e) {
            $this->exibirCheckout($carrinho, $e->getMessage());
            return;
        }

        // O carrinho NÃO é esvaziado aqui: continua disponível caso o cliente volte para trocar a forma
        // de pagamento. Ele só é esvaziado quando o pagamento é concluído (ver retorno()).
        $_SESSION['pedido_pendente_id'] = $pedidoId;
        $this->redirect('pedido/pagamento/' . $pedidoId);
    }

    /** Pedido pendente (aguardando pagamento) do cliente logado criado a partir do carrinho atual, se houver. */
    private function pedidoPendenteDaSessao(): ?array
    {
        $id = (int) ($_SESSION['pedido_pendente_id'] ?? 0);
        if ($id > 0) {
            $pedido = $this->model('Pedido')->buscarPorId($id);
            if ($pedido && (int) $pedido['cliente_id'] === (int) $_SESSION['cliente_id'] && $pedido['status'] === 'aguardando_pagamento') {
                return $pedido;
            }
        }
        unset($_SESSION['pedido_pendente_id']);
        return null;
    }

    private function exibirCheckout($carrinho, ?string $erro): void
    {
        $cliente = $this->model('Cliente')->buscarPorId((int) $_SESSION['cliente_id']);
        $this->view('pedido/checkout', [
            'itens'   => $carrinho->itens(),
            'total'   => $carrinho->total(),
            'cliente' => $cliente,
            'erro'    => $erro,
            'formas'  => $this->model('Pedido')->formasAtivas(),
            'pendente' => $this->pedidoPendenteDaSessao(),
        ]);
    }

    // ------------------------------------------------------------------
    // Pagamento (Pix, cartão de crédito/débito e boleto)
    // ------------------------------------------------------------------

    /** Tela de pagamento da forma escolhida: QR Code Pix, formulário de cartão ou boleto. */
    public function pagamento(string $id): void
    {
        $this->requireCliente();
        [$pedido, $pedidoModel] = $this->pedidoPendente((int) $id);
        $this->exibirPagamento($pedido, $pedidoModel, []);
    }

    /** Recebe os dados do cartão (novo ou salvo), valida e "processa" o pagamento (simulação). */
    public function pagarCartao(string $id): void
    {
        $this->requireCliente();
        [$pedido, $pedidoModel] = $this->pedidoPendente((int) $id);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($pedido['forma_pagamento'], ['cartao_credito', 'cartao_debito'], true)) {
            $this->redirect('pedido/pagamento/' . $pedido['id']);
        }

        $clienteId = (int) $_SESSION['cliente_id'];
        $credito = $pedido['forma_pagamento'] === 'cartao_credito';
        $total = (float) $pedido['valor_total'];
        $cartaoModel = $this->model('Cartao');
        $salvoId = (int) ($_POST['cartao_salvo'] ?? 0);
        $parcelas = $credito ? (int) ($_POST['parcelas'] ?? 1) : 1;
        $erros = [];

        if ($salvoId > 0) {
            // ---- Cartão já cadastrado: só pede o CVV ----
            $cartao = $cartaoModel->buscar($salvoId, $clienteId);
            if (!$cartao) {
                $erros[] = 'Cartão salvo não encontrado. Escolha outro ou informe um novo.';
            } else {
                if (!PagamentoSimulado::cvvValido($_POST['cvv'] ?? '', $cartao['bandeira'])) {
                    $erros[] = 'Código de segurança (CVV) inválido.';
                }
                if ($credito && ($parcelas < 1 || $parcelas > PagamentoSimulado::maxParcelas($total))) {
                    $erros[] = 'Número de parcelas inválido.';
                }
                if (!$erros && PagamentoSimulado::cartaoRecusado($cartao['ultimos4'])) {
                    $erros[] = 'Pagamento recusado pela operadora do cartão (simulação). Use outro cartão.';
                    $pedidoModel->registrarTentativaRecusada((int) $pedido['id'], $pedido['forma_pagamento'], 'Cartão ' . $cartao['bandeira'] . ' final ' . $cartao['ultimos4'] . ' recusado pela operadora (simulação)');
                }
            }
            $bandeira = $cartao['bandeira'] ?? '';
            $final = $cartao['ultimos4'] ?? '';
        } else {
            // ---- Cartão novo: valida todos os campos ----
            $erros = PagamentoSimulado::validarCartao($_POST, $credito, $total);
            if (!$erros && PagamentoSimulado::cartaoRecusado($_POST['numero'] ?? '')) {
                $erros[] = 'Pagamento recusado pela operadora do cartão (simulação). Confira os dados ou use outro cartão.';
                $pedidoModel->registrarTentativaRecusada((int) $pedido['id'], $pedido['forma_pagamento'], 'Cartão novo final ' . substr(PagamentoSimulado::soDigitos($_POST['numero'] ?? ''), -4) . ' recusado pela operadora (simulação)');
            }
            $numero = PagamentoSimulado::soDigitos($_POST['numero'] ?? '');
            $bandeira = PagamentoSimulado::bandeira($numero);
            $final = substr($numero, -4);
        }

        if ($erros) {
            // O número completo só volta para a tela se o cliente estiver digitando um cartão novo;
            // o CVV nunca volta preenchido.
            $form = [
                'cartao_salvo' => $salvoId,
                'numero'       => $salvoId > 0 ? '' : ($_POST['numero'] ?? ''),
                'nome'         => $_POST['nome'] ?? '',
                'validade'     => $_POST['validade'] ?? '',
                'parcelas'     => $parcelas,
                'salvar'       => !empty($_POST['salvar_cartao']),
            ];
            $this->exibirPagamento($pedido, $pedidoModel, $erros, $form);
            return;
        }

        // Cartão novo aprovado + cliente pediu para guardá-lo: grava só bandeira, final, titular e validade.
        if ($salvoId === 0 && !empty($_POST['salvar_cartao'])) {
            $cartaoModel->salvar($clienteId, $bandeira, $final, trim($_POST['nome']), trim($_POST['validade']));
        }

        $detalhe = $bandeira . ' final ' . $final . ' - ' . ($credito ? $parcelas . 'x' : 'a vista');
        // pagamento_id guarda o ID representativo da transação do cartão (RF18)
        $idTransacao = 'TX' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        $pedidoModel->registrarResultado((int) $pedido['id'], 'confirmado', 'aprovado', $idTransacao, 'Cartão aprovado (simulação): ' . $detalhe);
        $this->redirect('pedido/retorno?pedido=' . $pedido['id']);
    }

    /** Botão "Já paguei" do Pix e do boleto: confirma o pedido sem cobrança real. */
    public function simularPagamento(string $id): void
    {
        $this->requireCliente();
        [$pedido, $pedidoModel] = $this->pedidoPendente((int) $id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($pedido['forma_pagamento'], ['pix', 'boleto'], true)) {
            $pedidoModel->registrarResultado((int) $pedido['id'], 'confirmado', 'aprovado', null,
                strtoupper($pedido['forma_pagamento']) . ' confirmado pelo cliente (simulação)');
            $this->redirect('pedido/retorno?pedido=' . $pedido['id']);
        }
        $this->redirect('pedido/pagamento/' . $pedido['id']);
    }

    /** Cancela um pedido ainda não pago e devolve os itens ao estoque. */
    public function cancelar(string $id): void
    {
        $this->requireCliente();
        [$pedido, $pedidoModel] = $this->pedidoPendente((int) $id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // RN09 não tem um status próprio para cancelamento: usa "recusado" e explica no log
            $pedidoModel->registrarResultado((int) $pedido['id'], 'cancelado', 'recusado', null, 'Cancelado pelo cliente antes do pagamento');
            $this->redirect('pedido/retorno?pedido=' . $pedido['id']);
        }
        $this->redirect('pedido/pagamento/' . $pedido['id']);
    }

    /** Remove um cartão salvo do cliente e volta para a tela de pagamento. */
    public function removerCartao(string $cartaoId): void
    {
        $this->requireCliente();
        $pedidoId = (int) ($_POST['pedido'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->model('Cartao')->excluir((int) $cartaoId, (int) $_SESSION['cliente_id']);
        }
        $this->redirect($pedidoId > 0 ? 'pedido/pagamento/' . $pedidoId : 'home/index');
    }

    // ------------------------------------------------------------------
    // Resultado
    // ------------------------------------------------------------------

    /** Resultado do pedido: pagamento aprovado, cancelado (recusado/expirado/cancelado) ou pendente. */
    public function retorno(): void
    {
        $this->requireCliente();
        $pedidoModel = $this->model('Pedido');
        $pedidoModel->expirarPendentes();

        $pedidoId = (int) ($_GET['pedido'] ?? 0);
        $pedido = $pedidoModel->buscarPorId($pedidoId);

        if (!$pedido || (int) $pedido['cliente_id'] !== (int) $_SESSION['cliente_id']) {
            $this->redirect('home/index');
        }

        $pago = in_array($pedido['status'], ['confirmado', 'enviado', 'entregue'], true);

        // Pagamento concluído: agora sim o carrinho é esvaziado. Se o pedido foi cancelado/venceu,
        // o carrinho é mantido para o cliente tentar de novo.
        if ((int) ($_SESSION['pedido_pendente_id'] ?? 0) === $pedidoId && $pedido['status'] !== 'aguardando_pagamento') {
            if ($pago) {
                $this->model('Carrinho')->esvaziar();
            }
            unset($_SESSION['pedido_pendente_id']);
        }

        $this->view('pedido/retorno', [
            'pedido' => $pedido,
            'itens'  => $pedidoModel->itensDoPedido($pedidoId),
            'nota'   => $pago ? $this->notaOuNull($pedidoModel, $pedidoId) : null,
            'motivo_cancelamento' => $pedido['status'] === 'cancelado' ? $pedidoModel->ultimaObservacao($pedidoId) : null,
        ]);
    }

    /**
     * Nota fiscal do pedido (RF06): é emitida automaticamente quando o pagamento é confirmado;
     * aqui o cliente a visualiza e imprime. Pedidos antigos sem nota têm a nota emitida na hora.
     */
    public function notaFiscal(string $id): void
    {
        $this->requireCliente();
        $pedidoModel = $this->model('Pedido');
        $pedido = $pedidoModel->buscarPorId((int) $id);

        if (!$pedido || (int) $pedido['cliente_id'] !== (int) $_SESSION['cliente_id']) {
            $this->redirect('cliente/meusPedidos');
        }
        $nota = $this->notaOuNull($pedidoModel, (int) $id);
        if (!$nota) {
            $this->redirect('pedido/retorno?pedido=' . (int) $id);
        }

        $this->view('pedido/nota_fiscal', [
            'pedido'      => $pedido,
            'nota'        => $nota,
            'itens'       => $pedidoModel->itensDoPedido((int) $id),
            'cliente'     => $this->model('Cliente')->buscarPorId((int) $pedido['cliente_id']),
            'chaveAcesso' => Pedido::chaveAcesso($nota),
        ]);
    }

    private function notaOuNull($pedidoModel, int $pedidoId): ?array
    {
        try {
            return $pedidoModel->emitirNotaFiscal($pedidoId);
        } catch (Exception $e) {
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Apoio
    // ------------------------------------------------------------------

    /**
     * Carrega o pedido do cliente logado. Só segue se ainda estiver aguardando pagamento
     * (e for de uma das formas de pagamento atuais); caso contrário mostra o resultado.
     */
    private function pedidoPendente(int $id): array
    {
        $pedidoModel = $this->model('Pedido');
        $pedidoModel->expirarPendentes();
        $pedido = $pedidoModel->buscarPorId($id);

        if (!$pedido || (int) $pedido['cliente_id'] !== (int) $_SESSION['cliente_id']) {
            $this->redirect('home/index');
        }
        if ($pedido['status'] !== 'aguardando_pagamento' || !PagamentoSimulado::ehSimulada($pedido['forma_pagamento'])) {
            $this->redirect('pedido/retorno?pedido=' . $pedido['id']);
        }
        return [$pedido, $pedidoModel];
    }

    /** Dados do boleto: prefere o que está gravado no pedido; senão recalcula a partir do vencimento. */
    private function dadosBoleto(array $pedido, float $valor): array
    {
        $venc = $pedido['pagamento_expira_em'] ?: date('Y-m-d H:i:s', strtotime($pedido['data_pedido'] . ' +' . BOLETO_DIAS_VENCIMENTO . ' days'));
        $b = PagamentoSimulado::boleto((int) $pedido['id'], $valor, $venc);
        if (!empty($pedido['pagamento_boleto_linha'])) {
            $b['linha'] = $pedido['pagamento_boleto_linha'];
        }
        if (!empty($pedido['pagamento_boleto_barra'])) {
            $b['codigo'] = $pedido['pagamento_boleto_barra'];
        }
        return $b;
    }

    private function exibirPagamento(array $pedido, $pedidoModel, array $erros, array $form = []): void
    {
        $forma = $pedido['forma_pagamento'];
        $valor = (float) $pedido['valor_total'];
        $restante = $pedidoModel->segundosRestantes((int) $pedido['id']);
        $ehCartao = in_array($forma, ['cartao_credito', 'cartao_debito'], true);

        $this->view('pedido/pagamento', [
            'pedido'      => $pedido,
            'itens'       => $pedidoModel->itensDoPedido((int) $pedido['id']),
            'cliente'     => $this->model('Cliente')->buscarPorId((int) $pedido['cliente_id']),
            'forma'       => $forma,
            'erros'       => $erros,
            'form'        => $form,
            'restante'    => max(0, $restante),
            'cartoes'     => $ehCartao ? $this->model('Cartao')->listarPorCliente((int) $_SESSION['cliente_id']) : [],
            // Usa os códigos gravados no pedido (RF18); pedidos antigos sem código são recalculados.
            'pixPayload'  => $forma === 'pix' ? ($pedido['pagamento_codigo_pix'] ?: PagamentoSimulado::pixPayload((int) $pedido['id'], $valor)) : null,
            'boleto'      => $forma === 'boleto' ? $this->dadosBoleto($pedido, $valor) : null,
            'maxParcelas' => PagamentoSimulado::maxParcelas($valor),
        ]);
    }
}
