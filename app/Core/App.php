<?php

class App
{
    private $controller = 'HomeController';
    private string $method = 'index';
    private array $params = [];
    private array $aliases = [
        'login' => ['AuthController', 'login'],
        'logout' => ['AuthController', 'logout'],
        'corregir-entradas' => ['EntradaController', 'detalle'],
        'corregir-salidas' => ['SalidaController', 'detalle'],
        'reporte-lote' => ['ReporteController', 'index'],
        'inteligencia' => ['AnaliticaController', 'index'],
        'predespachos' => ['PredespachoController', 'index'],
        'predespacho-detalle' => ['PredespachoController', 'detalle'],
        'predespacho-salida' => ['PredespachoController', 'salida'],
    ];

    public function __construct()
    {
        $url = $this->parseUrl();

        if (isset($url[0], $this->aliases[$url[0]])) {
            [$this->controller, $this->method] = $this->aliases[$url[0]];
            unset($url[0]);
            $this->controller = new $this->controller();
            $this->params = array_values($url);
            return;
        }

        if (isset($url[0])) {
            $controllerName = ucfirst($url[0]) . 'Controller';
            $controllerPath = __DIR__ . '/../Controllers/' . $controllerName . '.php';

            if (file_exists($controllerPath)) {
                $this->controller = $controllerName;
                unset($url[0]);
            }
        }

        $this->controller = new $this->controller();

        if (isset($url[1]) && method_exists($this->controller, $url[1])) {
            $this->method = $url[1];
            unset($url[1]);
        }

        $this->params = $url ? array_values($url) : [];
    }

    public function run(): void
    {
        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    private function parseUrl(): array
    {
        if (!isset($_GET['url'])) {
            return [];
        }

        $url = rtrim($_GET['url'], '/');
        $url = filter_var($url, FILTER_SANITIZE_URL);

        return explode('/', $url);
    }
}
