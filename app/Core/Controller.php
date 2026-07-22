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

    protected function requiereLogin(): void
    {
        Auth::requireLogin();
    }

    protected function requierePermiso(string $module, string $action = 'ver'): void
    {
        Auth::requirePermission($module, $action);
    }

    protected function requiereAdmin(): void
    {
        Auth::requireAdmin();
    }

    protected function validarCsrf(): void
    {
        if (Auth::validateCsrf()) {
            return;
        }

        http_response_code(419);
        echo 'Token CSRF inválido. Vuelva al formulario e intente de nuevo.';
        exit;
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . APP_URL . $path);
        exit;
    }
}
