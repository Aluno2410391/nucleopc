<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Validacao.php';
require_once __DIR__ . '/../services/VerificadorService.php';

/**
 * ApiController (Painel Admin)
 * API JSON interna para verificar informações nos formulários do painel
 * (CEP e CNPJ de fornecedores, e-mail de usuários). Exige login.
 *
 *   index.php?url=api/cep&valor=20040020
 *   index.php?url=api/cnpj&valor=00.000.000/0001-00
 *   index.php?url=api/cpf&valor=123.456.789-09
 *   index.php?url=api/email&valor=nome@dominio.com[&contexto=usuario&ignorar=ID]
 */
class ApiController extends Controller
{
    private const LIMITE_POR_MINUTO = 60;

    public function index(): void
    {
        $this->json(['ok' => false, 'mensagem' => 'Use api/cep, api/cnpj, api/cpf ou api/email.'], 404);
    }

    public function cep(): void
    {
        $this->preparar();
        $this->json(VerificadorService::cep($_GET['valor'] ?? ''));
    }

    public function cnpj(): void
    {
        $this->preparar();
        $resp = VerificadorService::cnpj($_GET['valor'] ?? '');

        // Fornecedor com o mesmo CNPJ já cadastrado? (RN03)
        if ($resp['ok'] && ($_GET['contexto'] ?? '') === 'fornecedor') {
            $existente = $this->model('Fornecedor')->buscarPorCnpj(trim($_GET['valor']));
            if ($existente && (int) $existente['id'] !== (int) ($_GET['ignorar'] ?? 0)) {
                $resp['ok'] = false;
                $resp['mensagem'] = 'Já existe um fornecedor cadastrado com este CNPJ.';
                $resp['fonte'] = 'banco';
            }
        }
        $this->json($resp);
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

        if ($resp['ok'] && ($_GET['contexto'] ?? '') === 'usuario') {
            $existente = $this->model('Usuario')->buscarPorEmailQualquerStatus($email);
            if ($existente && (int) $existente['id'] !== (int) ($_GET['ignorar'] ?? 0)) {
                $resp['ok'] = false;
                $resp['mensagem'] = 'Já existe um usuário cadastrado com este e-mail.';
                $resp['fonte'] = 'banco';
            }
        }
        $this->json($resp);
    }

    // ------------------------------------------------------------------

    private function preparar(): void
    {
        @ini_set('display_errors', '0'); // um aviso do PHP não pode quebrar o JSON
        if (empty($_SESSION['usuario_id'])) {
            $this->json(['ok' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.'], 401);
        }
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
