<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/empresa.php';

/**
 * Pedido (Painel Admin)
 * Vendas de balcão e pedidos online — a mesma tabela "pedidos" (RN04).
 *
 * Status do PAGAMENTO (RN09, coluna pagamento_status):
 *   aguardando_pagamento | aprovado | recusado | confirmado_manual_admin | cancelado_expirado
 * Toda mudança de pagamento é gravada em pagamento_log (auditoria, UC08).
 * A nota fiscal é emitida automaticamente quando o pedido é confirmado (RF06, RN05).
 */
class Pedido
{
    public const FORMAS_PAGAMENTO = [
        'pix'            => 'Pix',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito'  => 'Cartão de débito',
        'boleto'         => 'Boleto bancário',
    ];

    public const ROTULO_PAGAMENTO_STATUS = [
        'aguardando_pagamento'    => 'Aguardando pagamento',
        'aprovado'                => 'Pagamento aprovado',
        'confirmado_manual_admin' => 'Confirmado manualmente',
        'recusado'                => 'Recusado / cancelado',
        'cancelado_expirado'      => 'Prazo expirado',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ---------------------------------------------------------------- Consultas

    /** Lista pedidos com dados do pagamento (view vw_pedidos_pagamento) e número da nota fiscal. */
    public function listarRecentes(int $limite = 20): array
    {
        
        $sql = <<<'SQL'
            SELECT
                p.*,
                c.nome AS cliente_nome,
                u.nome AS vendedor_nome,
                v.forma_nome,
                v.status_real,
                v.minutos_restantes,
                nf.numero AS nf_numero

            FROM pedidos p

            LEFT JOIN clientes c ON c.id = p.cliente_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id

            LEFT JOIN (
                SELECT
                    p2.id,
                    f.nome AS forma_nome,

                    CASE
                        WHEN p2.status = 'aguardando_pagamento'
                            AND p2.pagamento_expira_em IS NOT NULL
                            AND p2.pagamento_expira_em < CURRENT_TIMESTAMP()
                        THEN 'cancelado'
                        ELSE p2.status
                    END AS status_real,

                    CASE
                        WHEN p2.status = 'aguardando_pagamento'
                            AND p2.pagamento_expira_em IS NOT NULL
                        THEN GREATEST(
                            0,
                            TIMESTAMPDIFF(
                                MINUTE,
                                CURRENT_TIMESTAMP(),
                                p2.pagamento_expira_em
                            )
                        )
                        ELSE NULL
                    END AS minutos_restantes

                FROM pedidos p2

                LEFT JOIN formas_pagamento_config f
                    ON f.codigo = p2.forma_pagamento

            ) v ON v.id = p.id

            LEFT JOIN notas_fiscais nf ON nf.pedido_id = p.id

            ORDER BY p.id DESC
            LIMIT :limite
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();

    }

    public function buscarPorId(int $id): ?array
    {
        $sql = 'SELECT p.*, c.nome AS cliente_nome, c.cpf AS cliente_cpf, c.email AS cliente_email,
                       u.nome AS vendedor_nome
                FROM pedidos p
                LEFT JOIN clientes c ON c.id = p.cliente_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                WHERE p.id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function itensDoPedido(int $pedidoId): array
    {
        $sql = 'SELECT pi.*, pr.nome AS produto_nome, pr.sku
                FROM pedido_itens pi
                JOIN produtos pr ON pr.id = pi.produto_id
                WHERE pi.pedido_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $pedidoId]);
        return $stmt->fetchAll();
    }

    /** Histórico de pagamento do pedido (auditoria). */
    public function historicoPagamento(int $pedidoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, u.nome AS usuario_nome FROM pagamento_log l
             LEFT JOIN usuarios u ON u.id = l.usuario_id
             WHERE l.pedido_id = :id ORDER BY l.id ASC'
        );
        $stmt->execute(['id' => $pedidoId]);
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------ Venda de balcão

    /**
     * Registra uma venda feita no balcão da loja física. A venda nasce paga
     * (status "confirmado", pagamento "aprovado") e a nota fiscal é emitida na hora.
     * @param array $itens cada item: ['produto_id' => int, 'quantidade' => int, 'preco_unitario' => float]
     */
    public function registrarVendaBalcao(?int $clienteId, int $usuarioId, array $itens, string $formaPagamento): int
    {
        if (!isset(self::FORMAS_PAGAMENTO[$formaPagamento])) {
            throw new Exception('Escolha uma forma de pagamento válida.');
        }

        $this->db->beginTransaction();
        try {
            $valorTotal = 0;
            foreach ($itens as $item) {
                $valorTotal += $item['quantidade'] * $item['preco_unitario'];
            }

            $stmt = $this->db->prepare(
                'INSERT INTO pedidos (cliente_id, usuario_id, origem, status, valor_total, forma_pagamento, pagamento_status)
                 VALUES (:cliente_id, :usuario_id, "loja_fisica", "confirmado", :valor_total, :forma_pagamento, "aprovado")'
            );
            $stmt->execute([
                'cliente_id'      => $clienteId,
                'usuario_id'      => $usuarioId,
                'valor_total'     => $valorTotal,
                'forma_pagamento' => $formaPagamento,
            ]);
            $pedidoId = (int) $this->db->lastInsertId();

            $stmtItem = $this->db->prepare(
                'INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario)
                 VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario)'
            );
            $stmtEstoque = $this->db->prepare(
                'UPDATE produtos SET estoque = estoque - :qtd WHERE id = :id AND estoque >= :qtd_min'
            );

            foreach ($itens as $item) {
                $stmtItem->execute([
                    'pedido_id'      => $pedidoId,
                    'produto_id'     => $item['produto_id'],
                    'quantidade'     => $item['quantidade'],
                    'preco_unitario' => $item['preco_unitario'],
                ]);
                $stmtEstoque->execute([
                    'qtd'     => $item['quantidade'],
                    'qtd_min' => $item['quantidade'], // parâmetro nomeado não pode se repetir (PDO nativo)
                    'id'      => $item['produto_id'],
                ]);
                if ($stmtEstoque->rowCount() === 0) {
                    throw new Exception('Quantidade indisponível em estoque para o produto ID ' . $item['produto_id']);
                }
            }

            $this->registrarLog($pedidoId, null, 'aprovado', $formaPagamento, 'Venda de balcão paga no ato', $usuarioId);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->emitirNotaFiscalSilencioso($pedidoId);
        return $pedidoId;
    }

    // -------------------------------------------------- Pagamento de pedido online (UC08)

    /**
     * Confirma manualmente o pagamento de um pedido online "aguardando_pagamento"
     * (uso típico: boleto, cuja compensação real não é avisada ao sistema). A troca só
     * ocorre se o pedido ainda estiver pendente, evitando confirmar duas vezes.
     * Grava quem confirmou no pagamento_log e emite a nota fiscal.
     */
    public function confirmarPagamento(int $pedidoId, ?int $usuarioId): bool
    {
        return $this->transicionar(
            $pedidoId, 'confirmado', 'confirmado_manual_admin', 'Pagamento confirmado manualmente no painel', $usuarioId
        );
    }

    /** Cancela um pedido online ainda não pago e devolve os itens ao estoque. */
    public function cancelarPedido(int $pedidoId, ?int $usuarioId): bool
    {
        return $this->transicionar(
            $pedidoId, 'cancelado', 'recusado', 'Pedido cancelado no painel antes do pagamento', $usuarioId
        );
    }

    /**
     * Cancela os pedidos online cujo prazo de pagamento venceu (RN10) e devolve o estoque.
     * Chamado ao abrir a lista de vendas, para o painel nunca mostrar pedidos "vencidos".
     */
    public function expirarPendentes(): int
    {
        $sql = "SELECT p.id FROM pedidos p
                LEFT JOIN formas_pagamento_config f ON f.codigo = p.forma_pagamento
                WHERE p.origem = 'online' AND p.status = 'aguardando_pagamento'
                  AND COALESCE(p.pagamento_expira_em, DATE_ADD(p.data_pedido, INTERVAL COALESCE(f.prazo_minutos, 30) MINUTE)) < NOW()";
        $ids = $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        $total = 0;
        foreach ($ids as $id) {
            if ($this->transicionar((int) $id, 'cancelado', 'cancelado_expirado', 'Prazo de pagamento expirado (RN10)', null)) {
                $total++;
            }
        }
        return $total;
    }

    /**
     * Única porta de saída de "aguardando_pagamento": muda status do pedido e do pagamento
     * numa transação, devolve o estoque se cancelou e registra o log. O UPDATE exige
     * status atual = aguardando_pagamento, então nunca confirma/devolve estoque em dobro.
     */
    private function transicionar(int $pedidoId, string $statusPedido, string $pagamentoStatus, string $observacao, ?int $usuarioId): bool
    {
        $this->db->beginTransaction();
        try {
            $pedido = $this->buscarPorId($pedidoId);

            $stmt = $this->db->prepare(
                "UPDATE pedidos SET status = :status, pagamento_status = :pstatus
                 WHERE id = :id AND status = 'aguardando_pagamento'"
            );
            $stmt->execute(['status' => $statusPedido, 'pstatus' => $pagamentoStatus, 'id' => $pedidoId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return false; // já havia sido confirmado/cancelado antes
            }

            if ($statusPedido === 'cancelado') {
                $stmtEstoque = $this->db->prepare('UPDATE produtos SET estoque = estoque + :qtd WHERE id = :id');
                foreach ($this->itensDoPedido($pedidoId) as $item) {
                    $stmtEstoque->execute(['qtd' => $item['quantidade'], 'id' => $item['produto_id']]);
                }
            }

            $this->registrarLog($pedidoId, 'aguardando_pagamento', $pagamentoStatus, $pedido['forma_pagamento'] ?? null, $observacao, $usuarioId);
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

    private function registrarLog(int $pedidoId, ?string $anterior, string $novo, ?string $forma, string $observacao, ?int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pagamento_log (pedido_id, status_anterior, status_novo, forma_pagamento, observacao, usuario_id)
             VALUES (:pedido, :anterior, :novo, :forma, :obs, :usuario)'
        );
        $stmt->execute([
            'pedido'   => $pedidoId,
            'anterior' => $anterior,
            'novo'     => $novo,
            'forma'    => $forma,
            'obs'      => mb_substr($observacao, 0, 255),
            'usuario'  => $usuarioId,
        ]);
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
            throw new Exception('Só é possível emitir nota fiscal de um pedido confirmado (pago).');
        }

        if ($existente = $this->buscarNotaFiscal($pedidoId)) {
            return $existente;
        }

        // O índice único (pedido_id e série+número) protege contra duas emissões simultâneas:
        // se der conflito, tenta de novo com o próximo número ou devolve a nota que já existe.
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

    /** Emissão automática: uma falha aqui não pode desfazer a venda; a nota sai ao ser consultada. */
    private function emitirNotaFiscalSilencioso(int $pedidoId): void
    {
        try {
            $this->emitirNotaFiscal($pedidoId);
        } catch (Throwable $e) {
            // será emitida sob demanda em venda/notaFiscal/{id}
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
        $dv = ($resto < 2) ? 0 : 11 - $resto;
        return $base . $dv;
    }

    // ---------------- Relatórios ----------------

    public function produtosMaisVendidos(int $limite = 10): array
    {
        $sql = 'SELECT pr.nome, SUM(pi.quantidade) AS qtde_vendida
                FROM pedido_itens pi
                JOIN produtos pr ON pr.id = pi.produto_id
                JOIN pedidos p ON p.id = pi.pedido_id
                WHERE p.status IN ("confirmado","enviado","entregue")
                GROUP BY pi.produto_id
                ORDER BY qtde_vendida DESC
                LIMIT :limite';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function totalVendasPorOrigem(): array
    {
        $sql = 'SELECT origem, COUNT(*) AS total_pedidos, COALESCE(SUM(valor_total),0) AS valor_total
                FROM pedidos WHERE status IN ("confirmado","enviado","entregue") GROUP BY origem';
        return $this->db->query($sql)->fetchAll();
    }

    public function fornecedoresResumo(): array
    {
        $sql = 'SELECT f.nome, COUNT(e.id) AS entradas, COALESCE(SUM(e.valor_total),0) AS valor_total
                FROM fornecedores f
                LEFT JOIN entradas e ON e.fornecedor_id = f.id
                GROUP BY f.id
                ORDER BY valor_total DESC';
        return $this->db->query($sql)->fetchAll();
    }
}
