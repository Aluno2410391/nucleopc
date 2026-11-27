<?php
/**
 * Controller
 * Classe base de todas as controllers da Loja Online.
 */
class Controller
{
    protected function model(string $model)
    {
        require_once __DIR__ . '/../models/' . $model . '.php';
        return new $model();
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (file_exists($viewFile)) {
            require_once $viewFile;
        } else {
            die('View não encontrada: ' . htmlspecialchars($view));
        }
    }

    protected function requireCliente(): void
    {
        if (empty($_SESSION['cliente_id'])) {
            $this->redirect('cliente/login');
        }
    }

    /**
     * Redireciona para outra rota interna. Aceita uma querystring própria
     * (ex.: "cliente/login?msg=ok") e a separa corretamente do parâmetro
     * "url" do roteador — caso contrário, "index.php?url=cliente/login?msg=ok"
     * vira um único valor de URL, o roteador não encontra o método
     * "login?msg=ok" e cai no método padrão "index()", que não existe em
     * todas as controllers (gerando erro fatal).
     */
    protected function redirect(string $url): void
    {
        [$rota, $querystring] = array_pad(explode('?', $url, 2), 2, null);
        $destino = 'index.php?url=' . $rota;
        if ($querystring) {
            $destino .= '&' . $querystring;
        }
        header('Location: ' . $destino);
        exit;
    }
}
