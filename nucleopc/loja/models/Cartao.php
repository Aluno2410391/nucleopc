<?php
require_once __DIR__ . '/../config/Database.php';

/**
 * Cartao
 * Cartões "salvos" do cliente para compras futuras. Por segurança guarda APENAS
 * bandeira, últimos 4 dígitos, nome do titular e validade — o número completo e o
 * CVV nunca são gravados (nem em banco, nem em log).
 */
class Cartao
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarPorCliente(int $clienteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM cartoes WHERE cliente_id = :c ORDER BY id DESC');
        $stmt->execute(['c' => $clienteId]);
        return $stmt->fetchAll();
    }

    /** Busca um cartão garantindo que ele pertence ao cliente informado. */
    public function buscar(int $id, int $clienteId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM cartoes WHERE id = :id AND cliente_id = :c');
        $stmt->execute(['id' => $id, 'c' => $clienteId]);
        return $stmt->fetch() ?: null;
    }

    /** Salva o cartão (ou apenas devolve o já existente com mesma bandeira e final). */
    public function salvar(int $clienteId, string $bandeira, string $ultimos4, string $titular, string $validade): int
    {
        $stmt = $this->db->prepare('SELECT id FROM cartoes WHERE cliente_id = :c AND bandeira = :b AND ultimos4 = :u');
        $stmt->execute(['c' => $clienteId, 'b' => $bandeira, 'u' => $ultimos4]);
        $existente = $stmt->fetchColumn();
        if ($existente) {
            return (int) $existente;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO cartoes (cliente_id, bandeira, ultimos4, titular, validade)
             VALUES (:c, :b, :u, :t, :v)'
        );
        $stmt->execute(['c' => $clienteId, 'b' => $bandeira, 'u' => $ultimos4, 't' => mb_substr($titular, 0, 80), 'v' => $validade]);
        return (int) $this->db->lastInsertId();
    }

    public function excluir(int $id, int $clienteId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM cartoes WHERE id = :id AND cliente_id = :c');
        return $stmt->execute(['id' => $id, 'c' => $clienteId]);
    }
}
