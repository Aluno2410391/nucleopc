<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Validacao.php';
require_once __DIR__ . '/../services/VerificadorService.php';

/**
 * ApiController
 * Pequena API JSON interna usada pelos formulários da Loja Online para verificar
 * informações em tempo real (o navegador chama o NOSSO servidor, que por sua vez
 * consulta ViaCEP / BrasilAPI / DNS — ver services/VerificadorService.php).
 *
 *   index.php?url=api/cep&valor=20040020
 *   index.php?url=api/cpf&valor=123.456.789-09
 *   index.php?url=api/email&valor=nome@dominio.com[&contexto=cadastro]
 *
 * Tem limite de uso por sessão (40 consultas/minuto) para não virar um "proxy aberto".
 */
class ApiController extends Controller
{
    private const LIMITE_POR_MINUTO = 40;

    public function index(): void
    {
        $this->json(['ok' => false, 'mensagem' => 'Use api/cep, api/cpf ou api/email.'], 404);
    }

    public function cep(): void
    {
        $this->preparar();
        $this->json(VerificadorService::cep($_GET['valor'] ?? ''));
    }

    public function cpf(): void
    {
        $this->preparar();
        $this->json(VerificadorService::cpf($_GET['valor'] ?? ''));
    }

    public function email(): void
    {
        $this->preparar();
        $email = trim($_GET['valor'] ?? '');
        $resp = VerificadorService::email($email);

        // No cadastro, também informa se o e-mail já está em uso.
        if ($resp['ok'] && ($_GET['contexto'] ?? '') === 'cadastro') {
            if ($this->model('Cliente')->buscarPorEmail($email)) {
                $resp['ok'] = false;
                $resp['mensagem'] = 'Já existe uma conta cadastrada com este e-mail.';
                $resp['fonte'] = 'banco';
            }
        }
        $this->json($resp);
    }

    // ------------------------------------------------------------------

    private function preparar(): void
    {
        @ini_set('display_errors', '0'); // um aviso do PHP não pode quebrar o JSON
        $agora = time();
        $hits = array_filter($_SESSION['api_hits'] ?? [], fn ($t) => $t > $agora - 60);
        if (count($hits) >= self::LIMITE_POR_MINUTO) {
            $this->json(['ok' => false, 'mensagem' => 'Muitas consultas seguidas. Aguarde um instante.'], 429);
        }
        $hits[] = $agora;
        $_SESSION['api_hits'] = array_values($hits);
    }

    private function json(array $dados, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
