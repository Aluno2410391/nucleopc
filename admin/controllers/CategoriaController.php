<?php
require_once __DIR__ . '/../core/Controller.php';

class CategoriaController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $model = $this->model('Categoria');
        $this->view('categorias/index', [
            'categorias' => $model->listarComContagemProdutos(),
            'mensagem'   => $_GET['msg'] ?? null,
            'erro'       => $_GET['erro'] ?? null,
        ]);
    }

    public function novo(): void
    {
        $this->requireAdmin();
        $this->view('categorias/form', ['categoria' => null, 'erro' => null]);
    }

    public function salvar(): void
    {
        $this->requireAdmin();
        $model = $this->model('Categoria');
        $id = $_POST['id'] ?? null;

        $dados = [
            'nome'      => trim($_POST['nome'] ?? ''),
            'descricao' => trim($_POST['descricao'] ?? ''),
        ];

        if ($dados['nome'] === '') {
            $this->view('categorias/form', ['categoria' => $dados + ['id' => $id], 'erro' => 'O nome da categoria é obrigatório.']);
            return;
        }

        // Valida a imagem ANTES de gravar: arquivo enviado (tem prioridade) ou link (URL)
        $arquivo = $_FILES['imagem_arquivo'] ?? null;
        $temImagem = $arquivo && $arquivo['error'] !== UPLOAD_ERR_NO_FILE;
        $urlImagem = trim($_POST['imagem_url'] ?? '');
        if ($temImagem) {
            $erroImg = $this->validarImagemCategoria($arquivo);
            if ($erroImg) {
                $this->view('categorias/form', ['categoria' => $dados + ['id' => $id], 'erro' => $erroImg]);
                return;
            }
        } elseif ($urlImagem !== '' && !$this->urlImagemValida($urlImagem)) {
            $this->view('categorias/form', ['categoria' => $dados + ['id' => $id],
                'erro' => 'O link da imagem é inválido. Use um endereço completo começando com http:// ou https:// (até 255 caracteres).']);
            return;
        }

        if ($id) {
            $model->atualizar((int) $id, $dados);
            $categoriaId = (int) $id;
            $msg = 'atualizada';
        } else {
            $categoriaId = $model->criar($dados);
            $msg = 'criada';
        }

        if ($temImagem) {
            // Arquivo enviado: tem prioridade sobre o link
            $this->salvarImagemCategoria($arquivo, $categoriaId);
        } elseif ($urlImagem !== '') {
            $this->salvarLinkCategoria($urlImagem, $categoriaId);
        } elseif (!empty($_POST['remover_imagem']) && $id) {
            $this->apagarImagensCategoria((int) $id);
        }
        $this->redirect('categoria/index?msg=' . $msg);
    }

    /** Aceita só links http/https bem formados, de até 255 caracteres (mesma regra dos produtos). */
    private function urlImagemValida(string $url): bool
    {
        if (strlen($url) > 255 || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private const EXT_IMAGEM = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    private function validarImagemCategoria(array $arquivo): ?string
    {
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            return 'Falha ao enviar a imagem (código ' . $arquivo['error'] . ').';
        }
        if ($arquivo['size'] > 2 * 1024 * 1024) {
            return 'A imagem deve ter no máximo 2MB.';
        }
        $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        if (!isset(self::EXT_IMAGEM[$ext]) || mime_content_type($arquivo['tmp_name']) !== self::EXT_IMAGEM[$ext]) {
            return 'Formato de imagem inválido. Envie um arquivo JPG, PNG ou WEBP.';
        }
        return null;
    }

    /** Guarda a imagem como uploads/categorias/categoria_{id}.ext (sem mudar o banco de dados). */
    private function salvarImagemCategoria(array $arquivo, int $categoriaId): void
    {
        if (!is_dir(CATEGORIA_DIR)) {
            @mkdir(CATEGORIA_DIR, 0775, true);
        }
        $this->apagarImagensCategoria($categoriaId);
        $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
        @move_uploaded_file($arquivo['tmp_name'], CATEGORIA_DIR . 'categoria_' . $categoriaId . '.' . $ext);
    }

    /** Guarda o link da imagem como uploads/categorias/categoria_{id}.url (substitui o arquivo atual, se houver). */
    private function salvarLinkCategoria(string $url, int $categoriaId): void
    {
        if (!is_dir(CATEGORIA_DIR)) {
            @mkdir(CATEGORIA_DIR, 0775, true);
        }
        $this->apagarImagensCategoria($categoriaId);
        @file_put_contents(CATEGORIA_DIR . 'categoria_' . $categoriaId . '.url', $url);
    }

    /** Remove a imagem da categoria, seja ela um arquivo enviado ou um link. */
    private function apagarImagensCategoria(int $categoriaId): void
    {
        foreach (['png', 'jpg', 'jpeg', 'webp', 'svg', 'url'] as $ext) {
            $arq = CATEGORIA_DIR . 'categoria_' . $categoriaId . '.' . $ext;
            if (is_file($arq)) { @unlink($arq); }
        }
    }

    public function editar(string $id): void
    {
        $this->requireAdmin();
        $model = $this->model('Categoria');
        $categoria = $model->buscarPorId((int) $id);
        if (!$categoria) {
            $this->redirect('categoria/index');
        }
        $this->view('categorias/form', ['categoria' => $categoria, 'erro' => null]);
    }

    public function excluir(string $id): void
    {
        $this->requireAdmin();
        $model = $this->model('Categoria');
        try {
            $model->excluir((int) $id);
            $this->apagarImagensCategoria((int) $id);
            $this->redirect('categoria/index?msg=excluida');
        } catch (Exception $e) {
            $this->redirect('categoria/index?erro=' . urlencode($e->getMessage()));
        }
    }
}
