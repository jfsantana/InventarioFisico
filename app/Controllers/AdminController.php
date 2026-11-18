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
        Auth::requireDirector();
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
        Auth::requireDirector();
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
        Auth::requireDirector();
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
        Auth::requireDirector();
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
        Auth::requireDirector();
        $model = $this->model('Silo');

        $this->view('admin/silos', [
            'title' => 'Control de tanques',
            'silos' => $model->listar($_GET),
            'entradasPendientes' => $model->entradasPendientes(),
            'filters' => $_GET,
            'message' => $_GET['msg'] ?? null,
            'messageType' => ($_GET['tipo'] ?? '') === 'error' ? 'error' : 'success',
        ]);
    }

    public function guardarSilo(): void
    {
        Auth::requireDirector();
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

        $this->redirect('/admin/silos?msg=Tanque guardado correctamente.');
    }

    public function eliminarSilo(): void
    {
        Auth::requireDirector();
        $this->validarCsrf();
        $idSilo = filter_input(INPUT_POST, 'idSilo', FILTER_VALIDATE_INT);

        try {
            if (!$idSilo || !$this->model('Silo')->eliminar($idSilo)) {
                throw new InvalidArgumentException('El tanque no existe.');
            }
            Auth::log(
                (int) ($_SESSION['id_usuario'] ?? 0),
                $_SESSION['username'] ?? null,
                'administracion',
                'eliminar_silo',
                'exitoso',
                'Tanque #' . $idSilo
            );
        } catch (Throwable $exception) {
            $this->redirect('/admin/silos?tipo=error&msg=' . urlencode($exception->getMessage()));
        }

        $this->redirect('/admin/silos?msg=Tanque eliminado correctamente.');
    }

    public function asignarEntradaSilos(): void
    {
        Auth::requireDirector();
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

        $this->redirect('/admin/silos?msg=Entrada distribuida correctamente entre los tanques.');
    }

    public function silosDisponibles(): void
    {
        Auth::requireDirector();
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

    public function actualizacionInventario(): void
    {
        $this->requiereAdmin();

        $staging = $_SESSION['reinicio_inventario_staging'] ?? null;
        if (is_array($staging) && ($staging['version_validacion_cantidad'] ?? null) !== ReinicioInventarioService::VERSION_VALIDACION_CANTIDAD) {
            unset($_SESSION['reinicio_inventario_staging']);
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=' . urlencode('La validación de cantidades cambió. Vuelva a subir el Excel antes de continuar.'));
        }
        $backupsDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
        $backupsRecientes = [];

        if (is_dir($backupsDir)) {
            $archivos = glob($backupsDir . DIRECTORY_SEPARATOR . '*.sql');
            if (is_array($archivos)) {
                usort($archivos, static fn($a, $b) => filemtime($b) <=> filemtime($a));
                foreach (array_slice($archivos, 0, 5) as $archivo) {
                    $backupsRecientes[] = [
                        'nombre' => basename($archivo),
                        'tamano' => number_format(filesize($archivo) / 1024, 2) . ' KB',
                        'fecha' => date('d/m/Y H:i:s', filemtime($archivo)),
                    ];
                }
            }
        }

        $this->view('admin/actualizacion-inventario', [
            'title' => 'Actualización de Inventario',
            'staging' => $staging,
            'backupsRecientes' => $backupsRecientes,
            'message' => $_GET['msg'] ?? null,
            'messageType' => ($_GET['tipo'] ?? '') === 'error' ? 'error' : 'success',
        ]);
    }

    public function procesarExcelInventario(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/actualizacionInventario');
        }

        $archivo = $_FILES['archivo_excel'] ?? null;
        if (!$archivo || empty($archivo['tmp_name']) || $archivo['error'] !== UPLOAD_ERR_OK) {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=Debe seleccionar un archivo Excel válido (.xlsx o .xls).');
        }

        $extension = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=El archivo debe tener extensión .xlsx o .xls.');
        }

        try {
            $service = new ReinicioInventarioService();
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $username = (string) ($_SESSION['username'] ?? 'admin');

            $resultado = $service->procesarExcel($archivo['tmp_name'], $idUsuario, $username);

            // Generar también respaldo de seguridad preventivo al subir el Excel
            $backupInfo = $service->generarBackup('backup_preventivo_lectura');
            $resultado['backup_preventivo'] = $backupInfo;

            $_SESSION['reinicio_inventario_staging'] = $resultado;

            if (!$resultado['exito']) {
                $totalErrores = count($resultado['resumen']['errores']);
                $this->redirect('/admin/actualizacionInventario?tipo=error&msg=' . urlencode("Se detectaron {$totalErrores} errores en el Excel que deben corregirse antes de continuar."));
            }

            $totalValidos = $resultado['resumen']['total_registros_validos'];
            $this->redirect('/admin/actualizacionInventario?tipo=success&msg=' . urlencode("Archivo Excel leído y validado con éxito ({$totalValidos} registros válidos listos para cargar). Revise el resumen a continuación."));
        } catch (Throwable $e) {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=' . urlencode('Error al procesar el archivo Excel: ' . $e->getMessage()));
        }
    }

    public function cancelarReinicioInventario(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();

        unset($_SESSION['reinicio_inventario_staging']);
        $this->redirect('/admin/actualizacionInventario?msg=' . urlencode('Operación cancelada. Se descartaron los datos temporales del archivo subido.'));
    }

    public function descargarBackup(): void
    {
        $this->requiereAdmin();
        $archivo = basename((string) ($_GET['archivo'] ?? ''));
        if ($archivo === '' || !str_ends_with($archivo, '.sql')) {
            http_response_code(400);
            echo 'Archivo inválido.';
            exit;
        }

        $ruta = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $archivo;
        if (!file_exists($ruta)) {
            http_response_code(404);
            echo 'Archivo no encontrado.';
            exit;
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    public function ejecutarReinicioInventario(): void
    {
        $this->requiereAdmin();
        $this->validarCsrf();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/actualizacionInventario');
        }

        $staging = $_SESSION['reinicio_inventario_staging'] ?? null;
        if (!$staging || empty($staging['registros'])) {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=No hay datos de inventario en espera de confirmación. Suba el Excel primero.');
        }

        if (!$staging['exito']) {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=No se puede ejecutar el reinicio porque el archivo presenta errores pendientes de corrección.');
        }

        // Validación de frase de seguridad
        $confirmacion = trim((string) ($_POST['confirmacion_texto'] ?? ''));
        if (strtoupper($confirmacion) !== 'CONFIRMAR VACIADO') {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=Debe escribir exactamente "CONFIRMAR VACIADO" para autorizar la operación.');
        }

        // Validación de contraseña del administrador actual
        $password = (string) ($_POST['admin_password'] ?? '');
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $username = (string) ($_SESSION['username'] ?? 'admin');

        $db = (new Database())->getConnection();
        $stmt = $db->prepare('SELECT password_hash FROM usuarios WHERE id_usuario = :id LIMIT 1');
        $stmt->execute(['id' => $idUsuario]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, $hash)) {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=Contraseña de administrador incorrecta. Operación no autorizada.');
        }

        try {
            $service = new ReinicioInventarioService($db);
            $resultado = $service->ejecutarVaciadoYCarga($staging, $idUsuario, $username);

            unset($_SESSION['reinicio_inventario_staging']);

            $this->redirect('/admin/actualizacionInventario?tipo=success&msg=' . urlencode($resultado['mensaje']));
        } catch (Throwable $e) {
            $this->redirect('/admin/actualizacionInventario?tipo=error&msg=' . urlencode('Fallo crítico al ejecutar el reinicio de inventario: ' . $e->getMessage()));
        }
    }
}