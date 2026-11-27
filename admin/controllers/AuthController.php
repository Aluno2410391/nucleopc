<?php
require_once __DIR__ . '/../core/Controller.php';

class AuthController extends Controller
{
    /** Segurança extra: qualquer acesso sem ação definida cai no login. */
    public function index(): void
    {
        $this->redirect('auth/login');
    }

    public function login(): void
    {
        if (!empty($_SESSION['usuario_id'])) {
            $this->redirect('dashboard');
        }

        $erro = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            $usuarioModel = $this->model('Usuario');
            $usuario = $usuarioModel->autenticar($email, $senha);

            if ($usuario) {
                $_SESSION['usuario_id']   = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];
                $this->redirect('dashboard');
            } else {
                $erro = 'E-mail ou senha inválidos.';
            }
        }

        $this->view('auth/login', ['erro' => $erro]);
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        header('Location: index.php?url=auth/login');
        exit;
    }

    /**
     * Criar conta no Painel Admin. A conta criada aqui é SEMPRE de vendedor: o perfil
     * administrador só pode ser concedido por um administrador, em Usuários.
     */
    public function cadastro(): void
    {
        if (!empty($_SESSION['usuario_id'])) {
            $this->redirect('dashboard');
        }

        $erro = null;
        $dados = ['nome' => '', 'email' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $dados['nome']  = trim($_POST['nome'] ?? '');
            $dados['email'] = trim($_POST['email'] ?? '');
            $senha          = $_POST['senha'] ?? '';
            $confirmacao    = $_POST['confirmacao'] ?? '';

            $usuarioModel = $this->model('Usuario');

            if ($dados['nome'] === '' || $dados['email'] === '') {
                $erro = 'Nome e e-mail são obrigatórios.';
            } elseif (!Validacao::email($dados['email'])) {
                $erro = 'E-mail inválido. Use o formato nome@dominio.com.';
            } elseif ($usuarioModel->buscarPorEmailQualquerStatus($dados['email'])) {
                $erro = 'Já existe um usuário cadastrado com este e-mail.';
            } elseif ($erroSenha = Validacao::senha($senha, $confirmacao)) {
                $erro = $erroSenha;
            } else {
                $usuarioModel->criar([
                    'nome'  => $dados['nome'],
                    'email' => $dados['email'],
                    'senha' => $senha,
                    'tipo'  => 'vendedor', // nunca vem do formulário
                ]);
                $this->redirect('auth/login?msg=conta_criada');
                return;
            }
        }

        $this->view('auth/cadastro', ['erro' => $erro, 'dados' => $dados]);
    }

    /** Formulário para solicitar o link de redefinição de senha. */
    public function esqueciSenha(): void
    {
        $mensagem = null;
        $linkTeste = null; // exibido apenas em ambiente de desenvolvimento (ver config/mail.php)

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $usuarioModel = $this->model('Usuario');
            $token = $usuarioModel->gerarTokenRecuperacao($email);

            if ($token) {
                $link = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/index.php?url=auth/redefinirSenha/' . $token;
                $corpo = '<p>Você solicitou a redefinição da sua senha no painel Núcleo PC.</p>'
                       . '<p><a href="' . $link . '">Clique aqui para criar uma nova senha</a> (válido por 1 hora).</p>'
                       . '<p>Se você não solicitou, ignore este e-mail.</p>';
                enviarEmail($email, 'Recuperação de senha — Núcleo PC', $corpo);
                $linkTeste = $link;
            }

            // Mensagem genérica sempre, para não revelar quais e-mails existem na base.
            $mensagem = 'Se o e-mail informado estiver cadastrado, enviamos um link de redefinição de senha.';
        }

        $this->view('auth/esqueci_senha', ['mensagem' => $mensagem, 'linkTeste' => $linkTeste]);
    }

    /** Formulário para definir uma nova senha a partir de um token válido. */
    public function redefinirSenha(string $token): void
    {
        $usuarioModel = $this->model('Usuario');
        $usuario = $usuarioModel->buscarPorTokenValido($token);

        if (!$usuario) {
            $this->view('auth/redefinir_senha', ['token' => $token, 'valido' => false, 'erro' => null]);
            return;
        }

        $erro = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $senha = $_POST['senha'] ?? '';
            $confirmacao = $_POST['confirmacao'] ?? '';

            if ($erroSenha = Validacao::senha($senha, $confirmacao)) {
                $erro = $erroSenha;
            } else {
                $usuarioModel->redefinirSenhaComToken($token, $senha);
                $this->redirect('auth/login?msg=senha_redefinida');
                return;
            }
        }

        $this->view('auth/redefinir_senha', ['token' => $token, 'valido' => true, 'erro' => $erro]);
    }
}
