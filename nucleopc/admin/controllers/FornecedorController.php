<?php
require_once __DIR__ . '/../core/Controller.php';

class FornecedorController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $model = $this->model('Fornecedor');
        $this->view('fornecedores/index', [
            'fornecedores' => $model->listarTodos(),
            'mensagem'     => $_GET['msg'] ?? null,
        ]);
    }

    public function novo(): void
    {
        $this->requireAdmin();
        $this->view('fornecedores/form', ['fornecedor' => null, 'erro' => null]);
    }

    public function salvar(): void
    {
        $this->requireAdmin();
        $model = $this->model('Fornecedor');

        $dados = [
            'nome'     => trim($_POST['nome'] ?? ''),
            'cnpj'     => trim($_POST['cnpj'] ?? ''),
            'telefone' => trim($_POST['telefone'] ?? ''),
            'email'    => trim($_POST['email'] ?? ''),
            'cep'      => trim($_POST['cep'] ?? ''),
            'endereco' => trim($_POST['endereco'] ?? ''),
        ];

        $id = $_POST['id'] ?? null;
        $formDados = $dados + ['id' => $id];

        if ($dados['nome'] === '') {
            $this->view('fornecedores/form', ['fornecedor' => $formDados, 'erro' => 'O nome é obrigatório.']);
            return;
        }

        // Verificação das informações (mesmas regras da API api/cnpj, api/cep e api/email)
        if ($dados['cnpj'] !== '') {
            if (!Validacao::cnpj($dados['cnpj'])) {
                $this->view('fornecedores/form', ['fornecedor' => $formDados, 'erro' => 'CNPJ deve ter 14 dígitos (formato 00.000.000/0000-00).']);
                return;
            }
            $dados['cnpj'] = Validacao::formatarCnpj($dados['cnpj']);

            // RN03: evita dois fornecedores com o mesmo CNPJ (ignorando o próprio, ao editar)
            $existente = $model->buscarPorCnpj($dados['cnpj']);
            if ($existente && (int) $existente['id'] !== (int) $id) {
                $this->view('fornecedores/form', ['fornecedor' => $formDados, 'erro' => 'Já existe um fornecedor cadastrado com este CNPJ.']);
                return;
            }
        }
        if ($dados['cep'] !== '') {
            if (!Validacao::cep($dados['cep'])) {
                $this->view('fornecedores/form', ['fornecedor' => $formDados, 'erro' => 'CEP inválido. Informe 8 dígitos.']);
                return;
            }
            $dados['cep'] = Validacao::formatarCep($dados['cep']);
        }
        if ($dados['telefone'] !== '') {
            if (!Validacao::telefone($dados['telefone'])) {
                $this->view('fornecedores/form', ['fornecedor' => $formDados, 'erro' => 'Telefone inválido. Use DDD + número, ex.: (21)3333-4444.']);
                return;
            }
            $dados['telefone'] = Validacao::formatarTelefone($dados['telefone']);
        }
        if ($dados['email'] !== '' && !Validacao::email($dados['email'])) {
            $this->view('fornecedores/form', ['fornecedor' => $formDados, 'erro' => 'E-mail do fornecedor inválido.']);
            return;
        }

        if ($id) {
            $model->atualizar((int) $id, $dados);
            $this->redirect('fornecedor/index?msg=atualizado');
        } else {
            $model->criar($dados);
            $this->redirect('fornecedor/index?msg=criado');
        }
    }

    public function editar(string $id): void
    {
        $this->requireAdmin();
        $model = $this->model('Fornecedor');
        $fornecedor = $model->buscarPorId((int) $id);
        if (!$fornecedor) {
            $this->redirect('fornecedor/index');
        }
        $this->view('fornecedores/form', ['fornecedor' => $fornecedor, 'erro' => null]);
    }

    public function inativar(string $id): void
    {
        $this->requireAdmin();
        $model = $this->model('Fornecedor');
        $model->alterarStatus((int) $id, 'inativo');
        $this->redirect('fornecedor/index?msg=inativado');
    }

    public function ativar(string $id): void
    {
        $this->requireAdmin();
        $model = $this->model('Fornecedor');
        $model->alterarStatus((int) $id, 'ativo');
        $this->redirect('fornecedor/index?msg=ativado');
    }
}
