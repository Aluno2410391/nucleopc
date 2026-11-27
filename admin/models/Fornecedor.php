<?php
require_once __DIR__ . '/../config/Database.php';

class Fornecedor
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function listarTodos(): array
    {
        return $this->db->query('SELECT * FROM fornecedores ORDER BY nome ASC')->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM fornecedores WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function buscarPorCnpj(string $cnpj): ?array
    {
        // Compara só os dígitos, para "12.345.678/0001-95" e "12345678000195" serem o mesmo CNPJ (RN03)
        $stmt = $this->db->prepare(
            "SELECT * FROM fornecedores
             WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = :cnpj LIMIT 1"
        );
        $stmt->execute(['cnpj' => preg_replace('/\D+/', '', $cnpj)]);
        return $stmt->fetch() ?: null;
    }

    public function criar(array $dados): bool
    {
        $sql = 'INSERT INTO fornecedores (nome, cnpj, telefone, email, cep, endereco)
                VALUES (:nome, :cnpj, :telefone, :email, :cep, :endereco)';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'nome'     => $dados['nome'],
            'cnpj'     => $dados['cnpj'],
            'telefone' => $dados['telefone'],
            'email'    => $dados['email'],
            'cep'      => $dados['cep'],
            'endereco' => $dados['endereco'],
        ]);
    }

    public function atualizar(int $id, array $dados): bool
    {
        $sql = 'UPDATE fornecedores SET nome = :nome, cnpj = :cnpj, telefone = :telefone,
                email = :email, cep = :cep, endereco = :endereco WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'nome'     => $dados['nome'],
            'cnpj'     => $dados['cnpj'],
            'telefone' => $dados['telefone'],
            'email'    => $dados['email'],
            'cep'      => $dados['cep'],
            'endereco' => $dados['endereco'],
            'id'       => $id,
        ]);
    }

    public function alterarStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE fornecedores SET status = :status WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
