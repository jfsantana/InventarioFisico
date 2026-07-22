<?php

class AuthController extends Controller
{
    public function login(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }

        $this->view('auth/login', [
            'title' => 'Iniciar sesion',
            'error' => Auth::flashError(),
            'username' => '',
        ]);
    }

    public function autenticar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
        }

        $this->validarCsrf();

        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if (Auth::login($username, $password, $remember)) {
            $this->redirect('/');
        }

        $this->view('auth/login', [
            'title' => 'Iniciar sesion',
            'error' => 'Credenciales inválidas',
            'username' => $username,
        ]);
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
        }

        $this->validarCsrf();
        Auth::logout();
        header('Location: ' . APP_URL . '/login');
        exit;
    }
}