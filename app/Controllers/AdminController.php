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

    public function proveedores(): void
    {
        $this->requiereAdmin();
        $model = $this->model('Proveedor');

        $this->view('admin/proveedores', [
            'title' => 'Proveedores y fabricantes',
            'proveedores' => $model->listar($_GET),
            'filters' => $_GET,
            'message' => $_GET['msg'] ?? null,
            'messageType' => ($_GET['tipo'] ?? '') === 'error' ? 'error' : 'success',
        ]);
    }

    public function guardarProveedor(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $model = $this->model('Proveedor');
        $originalCardCode = trim((string) ($_POST['originalCardCode'] ?? ''));

        try {
            if ($originalCardCode !== '') {
                $model->actualizar($originalCardCode, $_POST);
                $accion = 'editar_proveedor';
                $cardCode = $originalCardCode;
            } else {
                $proveedor = $model->crear($_POST);
                $accion = 'crear_proveedor';
                $cardCode = $proveedor['CardCode'];
            }

            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                $accion,
                'exitoso',
                $cardCode
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/proveedores?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/proveedores?msg=Proveedor o fabricante guardado correctamente.');
    }

    public function eliminarProveedor(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $cardCode = trim((string) ($_POST['CardCode'] ?? ''));

        try {
            if ($cardCode === '' || !$this->model('Proveedor')->eliminar($cardCode)) {
                throw new InvalidArgumentException('El proveedor o fabricante no existe.');
            }

            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                'eliminar_proveedor',
                'exitoso',
                $cardCode
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/proveedores?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/proveedores?msg=Proveedor o fabricante eliminado correctamente.');
    }

    public function clientesCotizaciones(): void
    {
        $this->requiereAdmin();
        $model = $this->model('ClienteCotizacion');
        $filters = $_GET;
        $filters['status'] = $filters['status'] ?? 'all';

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $listing = $model->listarPaginado($filters, $page, 25);

        $this->view('admin/clientes-cotizaciones', [
            'title' => 'Clientes de Cotizaciones',
            'clientes' => $listing['clientes'],
            'pagination' => $listing,
            'filters' => $filters,
            'message' => $_GET['msg'] ?? null,
            'messageType' => ($_GET['tipo'] ?? '') === 'error' ? 'error' : 'success',
        ]);
    }

    public function guardarClienteCotizacion(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $model = $this->model('ClienteCotizacion');
        $idCliente = trim((string) ($_POST['idCliente'] ?? '')) ?: null;

        try {
            if ($idCliente) {
                $model->actualizar($idCliente, $_POST);
                $accion = 'editar_cliente_cotizacion';
            } else {
                $idCliente = $model->crear($_POST);
                $accion = 'crear_cliente_cotizacion';
            }

            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                $accion,
                'exitoso',
                trim((string) ($_POST['rif'] ?? ''))
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/clientesCotizaciones?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/clientesCotizaciones?msg=Cliente de Cotizaciones guardado correctamente.');
    }

    public function eliminarClienteCotizacion(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idCliente = trim((string) ($_POST['idCliente'] ?? ''));

        try {
            if ($idCliente === '' || !$this->model('ClienteCotizacion')->desactivar($idCliente)) {
                throw new InvalidArgumentException('El cliente de Cotizaciones no existe o ya fue desactivado.');
            }

            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                'desactivar_cliente_cotizacion',
                'exitoso',
                'Cliente #' . $idCliente
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/clientesCotizaciones?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/clientesCotizaciones?msg=Cliente de Cotizaciones desactivado correctamente.');
    }

    public function activarClienteCotizacion(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idCliente = trim((string) ($_POST['idCliente'] ?? ''));

        try {
            if ($idCliente === '' || !$this->model('ClienteCotizacion')->activar($idCliente)) {
                throw new InvalidArgumentException('El cliente de Cotizaciones no existe o ya está disponible.');
            }
        } catch (Throwable $exception) {
            $this->redirect('/admin/clientesCotizaciones?status=inactive&tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/clientesCotizaciones?status=inactive&msg=Cliente de Cotizaciones activado correctamente.');
    }

    public function silos(): void
    {
        $this->requiereAdmin();
        $model = $this->model('Silo');

        $this->view('admin/silos', [
            'title' => 'Control de silos',
            'silos' => $model->listar($_GET),
            'entradasPendientes' => $model->entradasPendientes(),
            'filters' => $_GET,
            'message' => $_GET['msg'] ?? null,
            'messageType' => ($_GET['tipo'] ?? '') === 'error' ? 'error' : 'success',
        ]);
    }

    public function guardarSilo(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idSilo = filter_input(INPUT_POST, 'idSilo', FILTER_VALIDATE_INT) ?: null;

        try {
            $model = $this->model('Silo');
            if ($idSilo) {
                $model->actualizar($idSilo, $_POST);
                $accion = 'editar_silo';
            } else {
                $idSilo = $model->crear($_POST);
                $accion = 'crear_silo';
            }
            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                $accion,
                'exitoso',
                trim((string) ($_POST['codigo'] ?? ''))
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/silos?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/silos?msg=Silo guardado correctamente.');
    }

    public function eliminarSilo(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idSilo = filter_input(INPUT_POST, 'idSilo', FILTER_VALIDATE_INT);

        try {
            if (!$idSilo || !$this->model('Silo')->eliminar($idSilo)) {
                throw new InvalidArgumentException('El silo no existe.');
            }
            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                'eliminar_silo',
                'exitoso',
                'Silo #' . $idSilo
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/silos?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/silos?msg=Silo eliminado correctamente.');
    }

    public function asignarEntradaSilos(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();
        $idEntrada = filter_input(INPUT_POST, 'idInventarioEntrante', FILTER_VALIDATE_INT);

        try {
            if (!$idEntrada) {
                throw new InvalidArgumentException('Seleccione una entrada válida.');
            }
            $this->model('Silo')->asignarEntradaPendiente($idEntrada, $this->normalizarAsignacionesSilo($_POST));
            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                'asignar_entrada_silos',
                'exitoso',
                'Entrada #' . $idEntrada
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/silos?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/silos?msg=Entrada distribuida correctamente entre los silos.');
    }

    public function silosDisponibles(): void
    {
        $this->requiereAdmin();
        header('Content-Type: application/json; charset=utf-8');
        $idProducto = filter_input(INPUT_GET, 'idProducto', FILTER_VALIDATE_INT);
        if (!$idProducto) {
            http_response_code(400);
            echo json_encode(['error' => 'Producto inválido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($this->model('Silo')->disponiblesParaProducto($idProducto), JSON_UNESCAPED_UNICODE);
    }

    private function normalizarAsignacionesSilo(array $data): array
    {
        $ids = is_array($data['idSilo'] ?? null) ? $data['idSilo'] : [];
        $cantidades = is_array($data['cantidadSilo'] ?? null) ? $data['cantidadSilo'] : [];
        $asignaciones = [];
        foreach ($ids as $index => $idSilo) {
            $asignaciones[] = [
                'idSilo' => $idSilo,
                'cantidad' => $cantidades[$index] ?? '',
            ];
        }

        return $asignaciones;
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