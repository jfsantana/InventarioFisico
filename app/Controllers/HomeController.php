<?php

class HomeController extends Controller
{
    public function index(): void
    {
        $this->requiereLogin();

        if (Auth::isOperator()) {
            $this->redirect('/salida');
        }

        $connectionStatus = 'No verificada';
        $connectionError = null;

        try {
            $database = new Database();
            $database->getConnection();
            $connectionStatus = 'Conexion exitosa a MySQL';
        } catch (PDOException $exception) {
            $connectionStatus = 'No se pudo conectar a MySQL';
            $connectionError = $exception->getMessage();
        }

        $this->view('home/index', [
            'title' => 'Inicio',
            'connectionStatus' => $connectionStatus,
            'connectionError' => $connectionError,
        ]);
    }
}
