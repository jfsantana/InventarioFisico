<?php

class AdminController extends Controller
{
    private const PROCESOS_CONTACTO = ['Entrada', 'PreDespacho', 'Salida', 'En_Camino'];

    public function usuarios(): void
    {
        $this->requiereAdmin();
        $model = $this->model('Usuario');
        $rolModel = $this->model('Rol');

        $this->view('admin/usuarios', [
            'title' => 'Gestion de usuarios',
            'usuarios' => $model->listar($_GET),
            'roles' => $rolModel->listarActivos(),
            'filters' => $_GET,
            'message' => $_GET['msg'] ?? null,
        ]);
    }

    public function guardarUsuario(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $model = $this->model('Usuario');
        $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT) ?: null;

        if ($idUsuario && $idUsuario === (int) ($_SESSION['id_usuario'] ?? 0)) {
            $this->redirect('/admin/usuarios?msg=No puede modificar su propio usuario.');
        }

        try {
            $data = $this->normalizarUsuario($_POST);
            if ($idUsuario) {
                $model->actualizar($idUsuario, $data);
                Auth::log((int) $_SESSION['id_usuario'], $_SESSION['username'], 'administracion', 'editar_usuario', 'exitoso', $data['username']);
            } else {
                $password = (string) ($_POST['password'] ?? '');
                $confirm = (string) ($_POST['password_confirm'] ?? '');
                if (!$this->passwordValida($password) || $password !== $confirm) {
                    $this->redirect('/admin/usuarios?msg=La contraseña debe tener al menos 6 caracteres y coincidir.');
                }
                $model->crear($data, $password);
                Auth::log((int) $_SESSION['id_usuario'], $_SESSION['username'], 'administracion', 'crear_usuario', 'exitoso', $data['username']);
            }
        } catch (Throwable $exception) {
            $this->redirect('/admin/usuarios?msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/usuarios?msg=Usuario guardado correctamente.');
    }

    public function cambiarClave(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (!$idUsuario || !$this->passwordValida($password) || $password !== $confirm) {
            $this->redirect('/admin/usuarios?msg=La contraseña debe tener al menos 6 caracteres y coincidir.');
        }

        if ($idUsuario === (int) ($_SESSION['id_usuario'] ?? 0)) {
            $this->redirect('/admin/usuarios?msg=No puede modificar su propio usuario.');
        }

        $model = $this->model('Usuario');
        $model->cambiarClave($idUsuario, $password);
        Auth::log((int) $_SESSION['id_usuario'], $_SESSION['username'], 'administracion', 'cambiar_clave', 'exitoso', 'Usuario #' . $idUsuario);
        $this->redirect('/admin/usuarios?msg=Contraseña actualizada correctamente.');
    }

    public function toggleUsuario(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);

        if (!$idUsuario || $idUsuario === (int) ($_SESSION['id_usuario'] ?? 0)) {
            $this->redirect('/admin/usuarios?msg=No puede desactivar o modificar su propio usuario.');
        }

        $model = $this->model('Usuario');
        $user = $model->obtener($idUsuario);
        if (!$user) {
            $this->redirect('/admin/usuarios?msg=Usuario no encontrado.');
        }

        if ((int) $user['activo'] === 1 && $model->esUnicoAdministradorActivo($idUsuario)) {
            $this->redirect('/admin/usuarios?msg=No puede desactivar el unico administrador activo.');
        }

        $nextState = (int) $user['activo'] === 1 ? 0 : 1;
        $model->cambiarEstado($idUsuario, $nextState);
        Auth::log((int) $_SESSION['id_usuario'], $_SESSION['username'], 'administracion', 'toggle_usuario', 'exitoso', $user['username']);
        $this->redirect('/admin/usuarios?msg=Estado de usuario actualizado.');
    }

    public function eliminarUsuario(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);

        if (!$idUsuario || $idUsuario === (int) ($_SESSION['id_usuario'] ?? 0)) {
            $this->redirect('/admin/usuarios?msg=No puede eliminar su propio usuario.');
        }

        $model = $this->model('Usuario');
        if ($model->esUnicoAdministradorActivo($idUsuario)) {
            $this->redirect('/admin/usuarios?msg=No puede eliminar el unico administrador activo.');
        }

        $model->eliminar($idUsuario);
        Auth::log((int) $_SESSION['id_usuario'], $_SESSION['username'], 'administracion', 'eliminar_usuario', 'exitoso', 'Usuario #' . $idUsuario);
        $this->redirect('/admin/usuarios?msg=Usuario eliminado correctamente.');
    }

    public function roles(): void
    {
        $this->requiereAdmin();
        $rolModel = $this->model('Rol');
        $this->view('admin/roles', [
            'title' => 'Roles y permisos',
            'roles' => $rolModel->listarConPermisos(),
        ]);
    }

    public function log(): void
    {
        $this->requiereAdmin();
        $model = $this->model('LogAcceso');

        if (($_GET['export'] ?? '') === 'csv') {
            $model->exportarCsv($_GET);
            return;
        }

        $this->view('admin/log', [
            'title' => 'Log de accesos',
            'logs' => $model->listar($_GET, 50),
            'usuarios' => $model->usuariosDisponibles(),
            'filters' => $_GET,
        ]);
    }

    public function contactosEmail(): void
    {
        $this->requiereAdmin();
        $model = $this->model('ContactoInternoEmail');

        $this->view('admin/contactos-email', [
            'title' => 'Contactos de notificacion',
            'contactos' => $model->listar($_GET),
            'procesos' => self::PROCESOS_CONTACTO,
            'filters' => $_GET,
            'message' => $_GET['msg'] ?? null,
            'messageType' => ($_GET['tipo'] ?? '') === 'error' ? 'error' : 'success',
        ]);
    }

    public function guardarContactoEmail(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $model = $this->model('ContactoInternoEmail');
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;

        try {
            $data = $this->normalizarContactoEmail($_POST);
            if ($id) {
                if (!$model->obtener($id)) {
                    throw new InvalidArgumentException('El contacto no existe.');
                }
                $model->actualizar($id, $data);
                $accion = 'editar_contacto_email';
            } else {
                $model->crear($data);
                $accion = 'crear_contacto_email';
            }

            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                $accion,
                'exitoso',
                $data['email'] . ' - ' . $data['proceso']
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/contactosEmail?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/contactosEmail?msg=Contacto guardado correctamente.');
    }

    public function eliminarContactoEmail(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            $this->redirect('/admin/contactosEmail?tipo=error&msg=No se pudo identificar el contacto.');
        }

        $model = $this->model('ContactoInternoEmail');
        $contacto = $model->obtener($id);
        if (!$contacto || !$model->eliminar($id)) {
            $this->redirect('/admin/contactosEmail?tipo=error&msg=El contacto no existe.');
        }

        Auth::log(
            (int) ($_SESSION['id_usuario'] ?? 0),
            $_SESSION['username'] ?? null,
            'administracion',
            'eliminar_contacto_email',
            'exitoso',
            $contacto['email'] . ' - ' . $contacto['proceso']
        );
        $this->redirect('/admin/contactosEmail?msg=Contacto eliminado correctamente.');
    }

    private function normalizarUsuario(array $data): array
    {
        $username = trim($data['username'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{3,60}$/', $username)) {
            throw new InvalidArgumentException('El usuario solo puede tener letras, numeros y guion bajo.');
        }

        $nombre = trim($data['nombre_completo'] ?? '');
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre completo es requerido.');
        }

        $idRol = filter_var($data['id_rol'] ?? null, FILTER_VALIDATE_INT);
        if (!$idRol) {
            throw new InvalidArgumentException('Seleccione un rol valido.');
        }

        return [
            'nombre_completo' => $nombre,
            'username' => $username,
            'id_rol' => (int) $idRol,
            'activo' => !empty($data['activo']) ? 1 : 0,
        ];
    }

    private function normalizarContactoEmail(array $data): array
    {
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '' || strlen($nombre) > 100) {
            throw new InvalidArgumentException('Escriba un nombre valido de hasta 100 caracteres.');
        }

        $email = strtolower(trim($data['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 150) {
            throw new InvalidArgumentException('Escriba un correo electronico valido.');
        }

        $cargo = trim($data['cargo'] ?? '');
        if (strlen($cargo) > 50) {
            throw new InvalidArgumentException('El cargo no puede superar 50 caracteres.');
        }

        $proceso = trim($data['proceso'] ?? '');
        if (!in_array($proceso, self::PROCESOS_CONTACTO, true)) {
            throw new InvalidArgumentException('Seleccione un proceso valido.');
        }

        return [
            'nombre' => $nombre,
            'email' => $email,
            'cargo' => $cargo === '' ? null : $cargo,
            'proceso' => $proceso,
        ];
    }

    private function passwordValida(string $password): bool
    {
        return strlen($password) > 5;
    }
}