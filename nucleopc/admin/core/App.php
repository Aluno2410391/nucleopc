<?php
/**
 * App
 * Roteador simples no padrão MVC.
 * Lê a URL em index.php?url=controller/metodo/param1/param2
 * e chama o Controller/Método correspondente.
 */
class App
{
    protected string $controller = 'DashboardController';
    protected string $method = 'index';
    protected array $params = [];

    public function __construct()
    {
        $url = $this->parseUrl();

        // Define o Controller
        if (!empty($url[0])) {
            $controllerName = ucfirst(strtolower($url[0])) . 'Controller';
            if (file_exists(__DIR__ . '/../controllers/' . $controllerName . '.php')) {
                $this->controller = $controllerName;
                unset($url[0]);
            }
        }
        require_once __DIR__ . '/../controllers/' . $this->controller . '.php';
        $controllerObj = new $this->controller();

        // Define o Método
        if (!empty($url[1])) {
            // is_callable (e não method_exists): métodos privados/protegidos não podem ser acessados pela URL
            if (is_callable([$controllerObj, $url[1]])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        // Define os parâmetros restantes
        $this->params = $url ? array_values($url) : [];

        call_user_func_array([$controllerObj, $this->method], $this->params);
    }

    private function parseUrl(): array
    {
        if (isset($_GET['url'])) {
            $url = rtrim($_GET['url'], '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);
            return explode('/', $url);
        }
        return [];
    }
}
