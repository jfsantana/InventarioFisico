<?php

class Controller
{
    protected function model(string $model): object
    {
        require_once __DIR__ . '/../Models/' . $model . '.php';

        return new $model();
    }

    protected function view(string $view, array $data = []): void
    {
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo 'Vista no encontrada: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
            return;
        }

        extract($data, EXTR_SKIP);
        require_once $viewPath;
    }
}
