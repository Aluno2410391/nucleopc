<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Validacao.php';
require_once __DIR__ . '/../services/VerificadorService.php';

class ClienteController extends Controller
{
    /** Segurança extra: qualquer acesso sem ação definida cai no login. */
    public function index(): void
    {
        $this->redirect('cliente/login');
    }

    public function login(): void
    {
        if (!empty($_SESSION['cliente_id'])) {
            $this->redirect('home/index');
        }

        $erro = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            $clienteModel = $this->model('Cliente');
            $cliente = $clienteModel->autenticar($email, $senha);

            if ($cliente) {
                $_SESSION['cliente_id']   = $cliente['id'];
                $_SESSION['cliente_nome'] = $cliente['nome'];
                $destino = $_GET['redirect'] ?? 'home/index';
                $this->redirect($destino);
            } else {
                $erro = 'E-mail ou senha inválidos.';
            }
        }

        $this->view('cliente/login', ['erro' => $erro]);
    }

    public function cadastro(): void
    {
        $erro = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $clienteModel = $this->model('Cliente');

            $dados = [
                'nome'        => trim($_POST['nome'] ?? ''),
                'email'       => trim($_POST['email'] ?? ''),
                'senha'       => $_POST['senha'] ?? '',
                'cpf'         => trim($_POST['cpf'] ?? ''),
                'telefone'    => trim($_POST['telefone'] ?? ''),
                'cep'         => trim($_POST['cep'] ?? ''),
                'endereco'    => trim($_POST['endereco'] ?? ''),
                'numero'      => trim($_POST['numero'] ?? ''),
                'complemento' => trim($_POST['complemento'] ?? ''),
                'bairro'      => trim($_POST['bairro'] ?? ''),
                'cidade'      => trim($_POST['cidade'] ?? ''),
                'uf'          => trim($_POST['uf'] ?? ''),
            ];

            $confirmacao = $_POST['confirmacao'] ?? '';
            $dados['uf'] = strtoupper($dados['uf']);

            if ($dados['nome'] === '' || $dados['email'] === '' || $dados['senha'] === '') {
                $erro = 'Preencha nome, e-mail e senha.';
            } elseif ($erroSenha = Validacao::senha($dados['senha'], $confirmacao)) {
                $erro = $erroSenha;
            } elseif (!($verEmail = VerificadorService::email($dados['email']))['ok']) {
                $erro = $verEmail['mensagem'];
            } elseif ($dados['cpf'] !== '' && !Validacao::cpf($dados['cpf'])) {
                $erro = 'CPF deve ter 11 dígitos (formato 000.000.000-00).';
            } elseif ($dados['cep'] !== '' && !Validacao::cep($dados['cep'])) {
                $erro = 'CEP inválido. Informe 8 dígitos.';
            } elseif ($dados['telefone'] !== '' && !Validacao::telefone($dados['telefone'])) {
                $erro = 'Telefone inválido. Use DDD + número, ex.: (12)12345-6789.';
            } elseif ($dados['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $dados['uf'])) {
                $erro = 'UF inválida. Use a sigla com 2 letras (ex.: RJ).';
            } elseif ($clienteModel->buscarPorEmail($dados['email'])) {
                $erro = 'Já existe uma conta cadastrada com este e-mail.';
            } else {
                if ($dados['cpf'] !== '') { $dados['cpf'] = Validacao::formatarCpf($dados['cpf']); }
                if ($dados['telefone'] !== '') { $dados['telefone'] = Validacao::formatarTelefone($dados['telefone']); }
                if ($dados['cep'] !== '') { $dados['cep'] = Validacao::formatarCep($dados['cep']); }
                $id = $clienteModel->cadastrar($dados);
                $_SESSION['cliente_id']   = $id;
                $_SESSION['cliente_nome'] = $dados['nome'];
                $this->redirect('home/index');
            }
        }

        $this->view('cliente/cadastro', ['erro' => $erro, 'dados' => $dados ?? []]);
    }

    public function logout(): void
    {
        unset($_SESSION['cliente_id'], $_SESSION['cliente_nome']);
        $this->redirect('home/index');
    }

    /** Formulário para solicitar o link de redefinição de senha. */
    public function esqueciSenha(): void
    {
        $mensagem = null;
        $linkTeste = null; // exibido apenas em ambiente de desenvolvimento (ver config/mail.php)

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $clienteModel = $this->model('Cliente');
            $token = $clienteModel->gerarTokenRecuperacao($email);

            if ($token) {
                $link = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/index.php?url=cliente/redefinirSenha/' . $token;
                $corpo = '<p>Você solicitou a redefinição da sua senha na Núcleo PC.</p>'
                       . '<p><a href="' . $link . '">Clique aqui para criar uma nova senha</a> (válido por 1 hora).</p>'
                       . '<p>Se você não solicitou, ignore este e-mail.</p>';
                enviarEmail($email, 'Recuperação de senha — Núcleo PC', $corpo);
                $linkTeste = $link;
            }

            $mensagem = 'Se o e-mail informado estiver cadastrado, enviamos um link de redefinição de senha.';
        }

        $this->view('cliente/esqueci_senha', ['mensagem' => $mensagem, 'linkTeste' => $linkTeste]);
    }

    /** Formulário para definir uma nova senha a partir de um token válido. */
    public function redefinirSenha(string $token): void
    {
        $clienteModel = $this->model('Cliente');
        $cliente = $clienteModel->buscarPorTokenValido($token);

        if (!$cliente) {
            $this->view('cliente/redefinir_senha', ['token' => $token, 'valido' => false, 'erro' => null]);
            return;
        }

        $erro = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $senha = $_POST['senha'] ?? '';
            $confirmacao = $_POST['confirmacao'] ?? '';

            if ($erroSenha = Validacao::senha($senha, $confirmacao)) {
                $erro = $erroSenha;
            } else {
                $clienteModel->redefinirSenhaComToken($token, $senha);
                $this->redirect('cliente/login?msg=senha_redefinida');
                return;
            }
        }

        $this->view('cliente/redefinir_senha', ['token' => $token, 'valido' => true, 'erro' => $erro]);
    }

    public function meusPedidos(): void
    {
        $this->requireCliente();
        $pedidoModel = $this->model('Pedido');
        $pedidoModel->expirarPendentes(); // cancela pedidos não pagos no prazo e devolve o estoque
        $this->view('cliente/pedidos', [
            'pedidos' => $pedidoModel->listarPorCliente((int) $_SESSION['cliente_id']),
        ]);
    }
}
