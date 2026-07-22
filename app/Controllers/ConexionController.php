<?php

class ConexionController extends Controller
{
    public function index(): void
    {
        $status = 'No se pudo conectar';
        $error = null;
        $serverInfo = null;

        try {
            $database = new Database();
            $connection = $database->getConnection();
            $serverInfo = $connection->getAttribute(PDO::ATTR_SERVER_VERSION);
            $status = 'Conexion exitosa';
        } catch (PDOException $exception) {
            $error = $exception->getMessage();
        }

        $this->view('conexion/index', [
            'title' => 'Test de conexion',
            'status' => $status,
            'error' => $error,
            'serverInfo' => $serverInfo,
            'connectionData' => [
                'Servidor' => DB_HOST,
                'Base de datos' => DB_NAME,
                'Usuario' => DB_USER,
                'Contrasena' => DB_PASS === '' ? 'Sin contrasena' : 'Configurada',
            ],
        ]);
    }
}
