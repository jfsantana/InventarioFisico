<?php

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../app/Core/Database.php';
    require_once __DIR__ . '/../../app/Core/Auth.php';
    require_once __DIR__ . '/Controller/IaController.php';

    Auth::boot();

    if (!Auth::check()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'respuesta' => '',
            'error' => 'No autorizado. Inicie sesión para consultar el asistente.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $controller = new IaController();
    $controller->procesarPregunta();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'respuesta' => '',
        'error' => 'Error interno del módulo IA: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
