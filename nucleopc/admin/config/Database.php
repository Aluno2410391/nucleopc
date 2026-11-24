<?php
/**
 * Database
 * Classe responsável por fornecer uma conexão PDO única (Singleton)
 * com o banco de dados MySQL utilizado pelo Painel Admin.
 */
class Database
{
    private static ?PDO $instance = null;

    // Ajuste estes dados conforme sua instalação do XAMPP
    private const HOST   = 'sql109.infinityfree.com';
    private const DBNAME = 'if0_43064442_nucleopc_db';
    private const USER   = 'if0_43064442';
    private const PASS   = 'Umy4YJy5QTl42x';
    private const CHARSET = 'utf8mb4';

    private function __construct()
    {
        // Construtor privado: impede instanciar a classe diretamente
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . self::HOST . ';dbname=' . self::DBNAME . ';charset=' . self::CHARSET;
            try {
                self::$instance = new PDO($dsn, self::USER, self::PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                die('Erro de conexão com o banco de dados: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
