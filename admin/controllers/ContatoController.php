<?php
require_once __DIR__ . '/../core/Controller.php';

class ContatoController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $model = $this->model('Contato');
        $this->view('contatos/index', [
            'mensagens' => $model->listarTodos(),
            'msg'       => $_GET['msg'] ?? null,
        ]);
    }

    public function ver(string $id): void
    {
        $this->requireLogin();
        $model = $this->model('Contato');
        $mensagem = $model->buscarPorId((int) $id);
        if (!$mensagem) {
            $this->redirect('contato/index');
        }
        $this->view('contatos/ver', ['mensagem' => $mensagem]);
    }

    public function marcarRespondido(string $id): void
    {
        $this->requireLogin();
        $this->model('Contato')->marcarStatus((int) $id, 1);
        $this->redirect('contato/index?msg=respondido');
    }

    public function reabrir(string $id): void
    {
        $this->requireLogin();
        $this->model('Contato')->marcarStatus((int) $id, 0);
        $this->redirect('contato/index?msg=reaberto');
    }

    public function excluir(string $id): void
    {
        $this->requireLogin();
        $this->model('Contato')->excluir((int) $id);
        $this->redirect('contato/index?msg=excluido');
    }
}
