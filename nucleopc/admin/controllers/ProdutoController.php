<?php
require_once __DIR__ . '/../core/Controller.php';

class ProdutoController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        $model = $this->model('Produto');
        $this->view('produtos/index', [
            'produtos' => $model->listarTodos(),
            'mensagem' => $_GET['msg'] ?? null,
        ]);
    }

    public function novo(): void
    {
        $this->requireAdmin();
        $categorias = $this->model('Categoria')->listarTodas();
        $this->view('produtos/form', ['produto' => null, 'categorias' => $categorias, 'erro' => null]);
    }

    public function salvar(): void
    {
        $this->requireAdmin();
        $model = $this->model('Produto');
        $categorias = $this->model('Categoria')->listarTodas();
        $id = $_POST['id'] ?? null;

        $imagemAtual = trim($_POST['imagem_atual'] ?? '') ?: 'sem-imagem.png';

        $dados = [
            'sku'            => trim($_POST['sku'] ?? ''),
            'nome'           => trim($_POST['nome'] ?? ''),
            'descricao'      => trim($_POST['descricao'] ?? ''),
            'categoria_id'   => (int) ($_POST['categoria_id'] ?? 0),
            'marca'          => trim($_POST['marca'] ?? ''),
            'preco'          => (float) str_replace(',', '.', $_POST['preco'] ?? '0'),
            'custo'          => (float) str_replace(',', '.', $_POST['custo'] ?? '0'),
            'estoque'        => (int) ($_POST['estoque'] ?? 0),
            'estoque_minimo' => (int) ($_POST['estoque_minimo'] ?? 3),
            'imagem'         => $imagemAtual,
        ];

        if ($dados['sku'] === '' || $dados['nome'] === '') {
            $this->view('produtos/form', ['produto' => $dados, 'categorias' => $categorias, 'erro' => 'SKU e nome são obrigatórios.']);
            return;
        }

        // Processa o upload da imagem real do produto (se um arquivo foi enviado).
        $resultadoUpload = $this->processarUploadImagem($_FILES['imagem_arquivo'] ?? null);
        if ($resultadoUpload['erro']) {
            $this->view('produtos/form', ['produto' => $dados, 'categorias' => $categorias, 'erro' => $resultadoUpload['erro']]);
            return;
        }
        if ($resultadoUpload['arquivo']) {
            // Foto enviada como arquivo: tem prioridade sobre o link
            $dados['imagem'] = $resultadoUpload['arquivo'];
        } else {
            // Sem arquivo novo: se informaram um link de imagem, usa o link
            $urlImagem = trim($_POST['imagem_url'] ?? '');
            if ($urlImagem !== '') {
                if (!$this->urlImagemValida($urlImagem)) {
                    $this->view('produtos/form', ['produto' => $dados + ['id' => $id], 'categorias' => $categorias,
                        'erro' => 'O link da imagem é inválido. Use um endereço completo começando com http:// ou https:// (até 255 caracteres).']);
                    return;
                }
                $dados['imagem'] = $urlImagem;
            }
        }

        if ($id) {
            $model->atualizar((int) $id, $dados);
            $this->redirect('produto/index?msg=atualizado');
        } else {
            if ($model->buscarPorSku($dados['sku'])) {
                $this->view('produtos/form', ['produto' => $dados, 'categorias' => $categorias, 'erro' => 'Já existe um produto com este SKU.']);
                return;
            }
            $model->criar($dados);
            $this->redirect('produto/index?msg=criado');
        }
    }

    /** Aceita só links http/https bem formados, que cabem na coluna produtos.imagem (255). */
    private function urlImagemValida(string $url): bool
    {
        if (strlen($url) > 255 || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Valida e move a imagem enviada para a pasta compartilhada de uploads
     * (UPLOAD_DIR). Retorna ['arquivo' => nomeDoArquivo|null, 'erro' => string|null].
     */
    private function processarUploadImagem(?array $arquivo): array
    {
        if (!$arquivo || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
            return ['arquivo' => null, 'erro' => null]; // nenhuma imagem nova enviada, mantém a atual
        }

        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            return ['arquivo' => null, 'erro' => 'Falha ao enviar a imagem (código ' . $arquivo['error'] . ').'];
        }

        $tamanhoMaximo = 2 * 1024 * 1024; // 2 MB
        if ($arquivo['size'] > $tamanhoMaximo) {
            return ['arquivo' => null, 'erro' => 'A imagem deve ter no máximo 2MB.'];
        }

        $extensoesPermitidas = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

        $tipoReal = mime_content_type($arquivo['tmp_name']);
        if (!isset($extensoesPermitidas[$extensao]) || $tipoReal !== $extensoesPermitidas[$extensao]) {
            return ['arquivo' => null, 'erro' => 'Formato de imagem inválido. Envie um arquivo JPG, PNG ou WEBP.'];
        }

        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0775, true);
        }

        $nomeArquivo = 'produto_' . uniqid() . '.' . $extensao;
        if (!move_uploaded_file($arquivo['tmp_name'], UPLOAD_DIR . $nomeArquivo)) {
            return ['arquivo' => null, 'erro' => 'Não foi possível salvar a imagem no servidor. Verifique as permissões da pasta uploads/.'];
        }

        return ['arquivo' => $nomeArquivo, 'erro' => null];
    }

    public function editar(string $id): void
    {
        $this->requireAdmin();
        $model = $this->model('Produto');
        $produto = $model->buscarPorId((int) $id);
        if (!$produto) {
            $this->redirect('produto/index');
        }
        $categorias = $this->model('Categoria')->listarTodas();
        $this->view('produtos/form', ['produto' => $produto, 'categorias' => $categorias, 'erro' => null]);
    }

    public function inativar(string $id): void
    {
        $this->requireAdmin();
        $this->model('Produto')->alterarStatus((int) $id, 0);
        $this->redirect('produto/index?msg=inativado');
    }

    public function ativar(string $id): void
    {
        $this->requireAdmin();
        $this->model('Produto')->alterarStatus((int) $id, 1);
        $this->redirect('produto/index?msg=ativado');
    }
}
