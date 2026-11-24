<?php
require_once __DIR__ . '/../config/Database.php';

class Contato
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Não respondidas primeiro, depois as mais recentes. */
    public function listarTodos(): array
    {
        $sql = 'SELECT * FROM contatos ORDER BY respondido ASC, criado_em DESC';
        return $this->db->query($sql)->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM contatos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function contarNaoRespondidas(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM contatos WHERE respondido = 0')->fetchColumn();
    }

    public function marcarStatus(int $id, int $respondido): bool
    {
        $stmt = $this->db->prepare('UPDATE contatos SET respondido = :respondido WHERE id = :id');
        return $stmt->execute(['respondido' => $respondido, 'id' => $id]);
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM contatos WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
