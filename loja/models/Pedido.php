<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/empresa.php';
require_once __DIR__ . '/../services/PagamentoSimulado.php';

/**
 * Pedido (Loja Online)
 *
 * Status do PAGAMENTO (RN09, coluna pagamento_status):
 *   aguardando_pagamento | aprovado | recusado | confirmado_manual_admin | cancelado_expirado
 * O prazo para pagar (RN10) vem de formas_pagamento_config e fica gravado em
 * pedidos.pagamento_expira_em. Toda mudança é registrada em pagamento_log (auditoria)
 * e a nota fiscal é emitida automaticamente quando o pedido é confirmado (RF06, RN05).
 */
class Pedido
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ------------------------------------------------------ Formas de pagamento (RN10)

    /** Formas ativas no checkout. Se a tabela de configuração não existir, usa a lista padrão. */
    public function formasAtivas(): array
    {
        try {
            $linhas = $this->db->query(
                "SELECT * FROM formas_pagamento_config WHERE ativo = 1
                 ORDER BY FIELD(codigo, 'pix', 'cartao_credito', 'cartao_debito', 'boleto')"
            )->fetchAll();
        } catch (PDOException $e) {
            $linhas = [];
        }
        if ($linhas) {
            return $linhas;
        }
        $padrao = [];
        foreach (PagamentoSimulado::FORMAS as $codigo => $nome) {
            $padrao[] = ['codigo' => $codigo, 'nome' => $nome, 'descricao' => '', 'prazo_minutos' => $this->prazoPadrao($codigo)];
        }
        return $padrao;
    }

    private function prazoPadrao(string $forma): int
    {
        return match ($forma) {
            'boleto' => BOLETO_DIAS_VENCIMENTO * 1440,
            'pix'    => PIX_MINUTOS_VALIDADE,
            default  => CARTAO_MINUTOS_RESERVA,
        };
    }

    private function prazoMinutos(string $forma): int
    {
        try {
            $stmt = $this->db->prepare('SELECT prazo_minutos FROM formas_pagamento_config WHERE codigo = :c AND ativo = 1');
            $stmt->execute(['c' => $forma]);
            $min = $stmt->fetchColumn();
            if ($min !== false && (int) $min > 0) {
                return (int) $min;
            }
        } catch (PDOException $e) {
            // tabela ausente: usa o padrão
        }
        return $this->prazoPadrao($forma);
    }

    // ------------------------------------------------------------ Criação do pedido

    /**
     * Cria o pedido online a partir dos itens do carrinho, dentro de uma transação:
     * cabeçalho + itens + reserva de estoque + dados do pagamento (Pix/boleto) + log.
     * O pedido nasce "aguardando_pagamento" e só vira "confirmado" quando o pagamento
     * é concluído (ver PedidoController).
     */
    public function criarPedidoOnline(int $clienteId, array $itensCarrinho, string $enderecoEntrega, string $formaPagamento): int
    {
        $prazo = $this->prazoMinutos($formaPagamento);

        $this->db->beginTransaction();
        try {
            $valorTotal = 0;
            foreach ($itensCarrinho as $item) {
                $valorTotal += $item['preco'] * $item['quantidade'];
            }

            $stmt = $this->db->prepare(
                'INSERT INTO pedidos (cliente_id, origem, status, valor_total, endereco_entrega, forma_pagamento, pagamento_status, pagamento_expira_em)
                 VALUES (:cliente_id, "online", "aguardando_pagamento", :valor_total, :endereco, :forma, "aguardando_pagamento",
                         DATE_ADD(NOW(), INTERVAL :prazo MINUTE))'
            );
            $stmt->execute([
                'cliente_id'  => $clienteId,
                'valor_total' => $valorTotal,
                'endereco'    => $enderecoEntrega,
                'forma'       => $formaPagamento,
                'prazo'       => $prazo,
            ]);
            $pedidoId = (int) $this->db->lastInsertId();

            $stmtItem = $this->db->prepare(
                'INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario)
                 VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario)'
            );
            $stmtEstoque = $this->db->prepare(
                'UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id AND estoque >= :qtd_min'
            );

            foreach ($itensCarrinho as $item) {
                $stmtItem->execute([
                    'pedido_id'      => $pedidoId,
                    'produto_id'     => $item['produto_id'],
                    'quantidade'     => $item['quantidade'],
                    'preco_unitario' => $item['preco'],
                ]);
                // Reserva o estoque já na criação (RN01/RN02); se o pagamento falhar ou
                // vencer, o estoque volta em restaurarEstoque().
                $stmtEstoque->execute([
                    'qtd'     => $item['quantidade'],
                    'qtd_min' => $item['quantidade'], // parâmetro nomeado não pode se repetir (PDO nativo)
                    'id'      => $item['produto_id'],
                ]);
                if ($stmtEstoque->rowCount() === 0) {
                    throw new Exception('Estoque insuficiente para "' . $item['nome'] . '".');
                }
            }

            $this->gravarDadosPagamento($pedidoId, $formaPagamento, (float) $valorTotal);
            $this->registrarLog($pedidoId, null, 'aguardando_pagamento', $formaPagamento,
                'Pedido criado no checkout (prazo de ' . $prazo . ' min para pagar)');

            $this->db->commit();
            return $pedidoId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Grava no pedido o código Pix / linha digitável do boleto (RF18) e o ID representativo
     * do pagamento: txid (Pix), linha digitável (boleto). Para cartão, o ID é criado ao pagar.
     */
    private function gravarDadosPagamento(int $pedidoId, string $forma, float $valor): void
    {
        if ($forma === 'pix') {
            $payload = PagamentoSimulado::pixPayload($pedidoId, $valor);
            $stmt = $this->db->prepare(
                'UPDATE pedidos SET pagamento_codigo_pix = :cod, pagamento_qr_code = :qr, pagamento_id = :id WHERE id = :pedido'
            );
            $stmt->execute(['cod' => $payload, 'qr' => $payload, 'id' => PagamentoSimulado::pixTxid($pedidoId), 'pedido' => $pedidoId]);
        } elseif ($forma === 'boleto') {
            $venc = $this->db->query('SELECT pagamento_expira_em FROM pedidos WHERE id = ' . (int) $pedidoId)->fetchColumn();
            $b = PagamentoSimulado::boleto($pedidoId, $valor, (string) $venc);
            $stmt = $this->db->prepare(
                'UPDATE pedidos SET pagamento_boleto_linha = :linha, pagamento_boleto_barra = :barra, pagamento_id = :id WHERE id = :pedido'
            );
            $stmt->execute(['linha' => $b['linha'], 'barra' => $b['codigo'], 'id' => $b['linha'], 'pedido' => $pedidoId]);
        }
    }

    /** O carrinho tem exatamente os mesmos produtos e quantidades do pedido? */
    public function mesmosItens(int $pedidoId, array $itensCarrinho): bool
    {
        $atual = [];
        foreach ($this->itensDoPedido($pedidoId) as $i) {
            $atual[(int) $i['produto_id']] = (int) $i['quantidade'];
        }
        $novo = [];
        foreach ($itensCarrinho as $i) {
            $novo[(int) $i['produto_id']] = (int) $i['quantidade'];
        }
        ksort($atual);
        ksort($novo);
        return $atual === $novo;
    }

    /**
     * Muda a forma de pagamento (e o endereço) de um pedido que ainda aguarda pagamento:
     * gera de novo o Pix/boleto, reinicia o prazo (RN10) e registra a troca no log.
     * Os itens e o estoque reservado continuam os mesmos.
     */
    public function alterarPedidoPendente(int $pedidoId, string $endereco, string $novaForma): bool
    {
        $pedido = $this->buscarPorId($pedidoId);
        if (!$pedido || $pedido['status'] !== 'aguardando_pagamento') {
            return false;
        }
        $prazo = $this->prazoMinutos($novaForma);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE pedidos SET endereco_entrega = :endereco, forma_pagamento = :forma,
                        pagamento_expira_em = DATE_ADD(NOW(), INTERVAL :prazo MINUTE),
                        pagamento_id = NULL, pagamento_codigo_pix = NULL, pagamento_qr_code = NULL,
                        pagamento_boleto_linha = NULL, pagamento_boleto_barra = NULL
                 WHERE id = :id AND status = 'aguardando_pagamento'"
            );
            $stmt->execute(['endereco' => $endereco, 'forma' => $novaForma, 'prazo' => $prazo, 'id' => $pedidoId]);
            $this->gravarDadosPagamento($pedidoId, $novaForma, (float) $pedido['valor_total']);
            $obs = $pedido['forma_pagamento'] === $novaForma
                ? 'Cliente voltou ao checkout e manteve a forma de pagamento (prazo reiniciado)'
                : 'Forma de pagamento alterada de ' . $pedido['forma_pagamento'] . ' para ' . $novaForma;
            $this->registrarLog($pedidoId, 'aguardando_pagamento', 'aguardando_pagamento', $novaForma, $obs);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    // ------------------------------------------------------- Resultado do pagamento

    /**
     * Registra o resultado do pagamento de um pedido ainda "aguardando_pagamento".
     *  - $statusPedido "confirmado": pagamento aprovado (emite a nota fiscal);
     *  - $statusPedido "cancelado": recusado, expirado ou cancelado (o estoque reservado volta).
     * A troca só acontece UMA vez (o UPDATE exige status atual = aguardando_pagamento), então
     * um duplo clique nunca confirma ou devolve estoque em dobro. Retorna true se realizou a transição.
     */
    public function registrarResultado(int $pedidoId, string $statusPedido, string $pagamentoStatus, ?string $referencia, string $observacao): bool
    {
        $this->db->beginTransaction();
        try {
            $pedido = $this->buscarPorId($pedidoId);
            $stmt = $this->db->prepare(
                "UPDATE pedidos SET status = :status, pagamento_status = :pstatus, pagamento_id = COALESCE(:ref, pagamento_id)
                 WHERE id = :id AND status = 'aguardando_pagamento'"
            );
            $stmt->execute(['status' => $statusPedido, 'pstatus' => $pagamentoStatus, 'ref' => $referencia, 'id' => $pedidoId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return false; // já havia sido confirmado/cancelado antes
            }
            if ($statusPedido === 'cancelado') {
                $this->restaurarEstoque($pedidoId);
            }
            $this->registrarLog($pedidoId, 'aguardando_pagamento', $pagamentoStatus, $pedido['forma_pagamento'] ?? null, $observacao);
            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        if ($statusPedido === 'confirmado') {
            $this->emitirNotaFiscalSilencioso($pedidoId);
        }
        return true;
    }

    /** Tentativa de cartão recusada: o pedido continua pendente, mas a tentativa fica na auditoria. */
    public function registrarTentativaRecusada(int $pedidoId, string $forma, string $observacao): void
    {
        $this->registrarLog($pedidoId, 'aguardando_pagamento', 'recusado', $forma, $observacao);
    }

    private function registrarLog(int $pedidoId, ?string $anterior, string $novo, ?string $forma, string $observacao): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pagamento_log (pedido_id, status_anterior, status_novo, forma_pagamento, observacao)
             VALUES (:pedido, :anterior, :novo, :forma, :obs)'
        );
        $stmt->execute([
            'pedido'   => $pedidoId,
            'anterior' => $anterior,
            'novo'     => $novo,
            'forma'    => $forma,
            'obs'      => mb_substr($observacao, 0, 255),
        ]);
    }

    /** Devolve ao estoque os itens de um pedido que não teve o pagamento aprovado. */
    public function restaurarEstoque(int $pedidoId): void
    {
        $stmt = $this->db->prepare('UPDATE produtos SET estoque = estoque + :qtd WHERE id = :id');
        foreach ($this->itensDoPedido($pedidoId) as $item) {
            $stmt->execute(['qtd' => $item['quantidade'], 'id' => $item['produto_id']]);
        }
    }

    /** Segundos que faltam para o pagamento expirar (RN10). */
    public function segundosRestantes(int $pedidoId): int
    {
        $sql = 'SELECT TIMESTAMPDIFF(SECOND, NOW(),
                       COALESCE(p.pagamento_expira_em, DATE_ADD(p.data_pedido, INTERVAL 30 MINUTE)))
                FROM pedidos p WHERE p.id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $pedidoId]);
        return max(0, (int) $stmt->fetchColumn());
    }

    /**
     * Cancela os pedidos online cujo prazo de pagamento venceu (RN10) e devolve o estoque.
     * É chamado quando o cliente abre "Meus pedidos", o checkout ou a tela de pagamento.
     */
    public function expirarPendentes(): int
    {
        $sql = "SELECT p.id FROM pedidos p
                LEFT JOIN formas_pagamento_config f ON f.codigo = p.forma_pagamento
                WHERE p.origem = 'online' AND p.status = 'aguardando_pagamento'
                  AND COALESCE(p.pagamento_expira_em, DATE_ADD(p.data_pedido, INTERVAL COALESCE(f.prazo_minutos, 30) MINUTE)) < NOW()";
        try {
            $ids = $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            return 0;
        }
        $total = 0;
        foreach ($ids as $id) {
            if ($this->registrarResultado((int) $id, 'cancelado', 'cancelado_expirado', null, 'Prazo de pagamento expirado (RN10)')) {
                $total++;
            }
        }
        return $total;
    }

    // ------------------------------------------------------------------ Consultas

    public function listarPorCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, nf.numero AS nf_numero FROM pedidos p
             LEFT JOIN notas_fiscais nf ON nf.pedido_id = p.id
             WHERE p.cliente_id = :id ORDER BY p.id DESC'
        );
        $stmt->execute(['id' => $clienteId]);
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pedidos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function itensDoPedido(int $pedidoId): array
    {
        $sql = 'SELECT pi.*, p.nome, p.sku, p.imagem
                FROM pedido_itens pi JOIN produtos p ON p.id = pi.produto_id
                WHERE pi.pedido_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $pedidoId]);
        return $stmt->fetchAll();
    }

    /** Última observação do log de pagamento (usada para explicar o motivo de um cancelamento). */
    public function ultimaObservacao(int $pedidoId): ?string
    {
        $stmt = $this->db->prepare('SELECT observacao FROM pagamento_log WHERE pedido_id = :id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id' => $pedidoId]);
        $obs = $stmt->fetchColumn();
        return $obs === false ? null : $obs;
    }

    // -------------------------------------------------------------- Nota fiscal

    public function buscarNotaFiscal(int $pedidoId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM notas_fiscais WHERE pedido_id = :id');
        $stmt->execute(['id' => $pedidoId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Emite a nota fiscal do pedido (RF06). RN05: no máximo uma nota por pedido — se já
     * existir, devolve a existente. Só pedidos pagos (confirmado/enviado/entregue) têm nota.
     */
    public function emitirNotaFiscal(int $pedidoId): array
    {
        $pedido = $this->buscarPorId($pedidoId);
        if (!$pedido) {
            throw new Exception('Pedido não encontrado.');
        }
        if (!in_array($pedido['status'], ['confirmado', 'enviado', 'entregue'], true)) {
            throw new Exception('A nota fiscal só existe para pedidos com pagamento confirmado.');
        }
        if ($existente = $this->buscarNotaFiscal($pedidoId)) {
            return $existente;
        }

        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            try {
                $numero = (int) $this->db->query('SELECT COALESCE(MAX(numero), 0) + 1 FROM notas_fiscais WHERE serie = 1')->fetchColumn();
                $stmt = $this->db->prepare(
                    'INSERT INTO notas_fiscais (pedido_id, numero, serie, valor_total) VALUES (:pedido_id, :numero, 1, :valor_total)'
                );
                $stmt->execute(['pedido_id' => $pedidoId, 'numero' => $numero, 'valor_total' => $pedido['valor_total']]);
                return $this->buscarNotaFiscal($pedidoId);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                if ($existente = $this->buscarNotaFiscal($pedidoId)) {
                    return $existente;
                }
            }
        }
        throw new Exception('Não foi possível gerar o número da nota fiscal. Tente novamente.');
    }

    /** Emissão automática: uma falha aqui não pode desfazer o pagamento; a nota sai ao ser consultada. */
    private function emitirNotaFiscalSilencioso(int $pedidoId): void
    {
        try {
            $this->emitirNotaFiscal($pedidoId);
        } catch (Throwable $e) {
            // será emitida sob demanda em pedido/notaFiscal/{id}
        }
    }

    /** Chave de acesso representativa de 44 dígitos (UF 33, AAMM, CNPJ, modelo 65, série, nº, tpEmis, cNF, DV mód. 11). */
    public static function chaveAcesso(array $nota): string
    {
        $base = '33'
              . date('ym', strtotime($nota['data_emissao']))
              . preg_replace('/\D+/', '', EMPRESA_CNPJ)
              . '65'
              . str_pad((string) $nota['serie'], 3, '0', STR_PAD_LEFT)
              . str_pad((string) $nota['numero'], 9, '0', STR_PAD_LEFT)
              . '1'
              . str_pad((string) $nota['pedido_id'], 8, '0', STR_PAD_LEFT);

        $soma = 0;
        $peso = 2;
        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $soma += (int) $base[$i] * $peso;
            $peso = ($peso === 9) ? 2 : $peso + 1;
        }
        $resto = $soma % 11;
        return $base . (($resto < 2) ? 0 : 11 - $resto);
    }
}
