<?php

class AjusteController extends Controller
{
    public function index(array $formData = [], ?string $error = null): void
    {
        $this->requierePermiso('corregir_entradas');
        $lotes = [];
        $historial = [];
        $silos = [];
        $porPagina = 10;
        $paginaActual = max(1, (int) ($_GET['pagina'] ?? $_POST['pagina'] ?? 1));
        $totalRegistros = 0;
        $totalPaginas = 1;
        $busqueda = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
        $idHistorial = max(0, (int) ($_GET['historial'] ?? $_POST['idInventarioEntrante'] ?? 0));
        try {
            $model = $this->model('AjusteLote');
            $lotes = $model->obtenerLotes($busqueda);
            if ($idHistorial > 0) {
                $historial = $model->obtenerHistorial($idHistorial);
            }
            $totalRegistros = count($lotes);
            $totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
            $paginaActual = min($paginaActual, $totalPaginas);
            $lotes = array_slice($lotes, ($paginaActual - 1) * $porPagina, $porPagina);
        } catch (PDOException $exception) {
            error_log('Error al cargar ajustes de lote: ' . $exception->getMessage());
            $lotes = [];
            $historial = [];
            $silos = [];
            $error = 'No se pudieron cargar los ajustes. Verifique los scripts database/add_ajustes_lote.sql y database/add_ajustes_lote_silos.sql.';
        }
        $successMessage = $_SESSION['ajuste_success'] ?? null;
        unset($_SESSION['ajuste_success']);
        if (empty($_SESSION['ajuste_token'])) {
            $_SESSION['ajuste_token'] = bin2hex(random_bytes(32));
        }
        $this->view('ajuste/index', [
            'title' => 'Ajustes de lote por sistema', 'lotes' => $lotes, 'historial' => $historial,
            'silos' => $silos, 'busqueda' => $busqueda, 'idHistorial' => $idHistorial,
            'formData' => $formData, 'error' => $error, 'successMessage' => $successMessage,
            'tokenRegistro' => $_SESSION['ajuste_token'],
            'paginaActual' => $paginaActual, 'totalPaginas' => $totalPaginas,
            'totalRegistros' => $totalRegistros, 'porPagina' => $porPagina,
        ]);
    }

    public function guardar(): void
    {
        $this->requierePermiso('corregir_entradas', 'editar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/ajuste');
        }
        $this->validarCsrf();
        $data = [
            'idInventarioEntrante' => (string) ($_POST['idInventarioEntrante'] ?? ''),
            'tipo' => (string) ($_POST['tipo'] ?? ''),
            'monto' => str_replace(',', '.', trim((string) ($_POST['monto'] ?? ''))),
            'observacion' => trim((string) ($_POST['observacion'] ?? '')),
            'asignacionesSilo' => [],
            'tokenRegistro' => (string) ($_POST['tokenRegistro'] ?? ''),
        ];
        $ids = is_array($_POST['idSilo'] ?? null) ? $_POST['idSilo'] : [];
        $cantidades = is_array($_POST['cantidadSilo'] ?? null) ? $_POST['cantidadSilo'] : [];
        foreach ($ids as $index => $idSilo) {
            $data['asignacionesSilo'][] = [
                'idSilo' => is_scalar($idSilo) ? (string) $idSilo : '',
                'cantidad' => is_scalar($cantidades[$index] ?? null)
                    ? str_replace(',', '.', trim((string) $cantidades[$index])) : '',
            ];
        }
        if (empty($_SESSION['ajuste_token']) || !hash_equals($_SESSION['ajuste_token'], $data['tokenRegistro'])) {
            http_response_code(409);
            $this->index([], 'El formulario ya fue utilizado o ha expirado. Revise el historial antes de volver a registrar el ajuste.');
            return;
        }
        try {
            $id = $this->model('AjusteLote')->registrar($data, (int) Auth::user()['id_usuario']);
        } catch (InvalidArgumentException | DomainException $exception) {
            http_response_code(422);
            $this->index($data, $exception->getMessage());
            return;
        } catch (PDOException $exception) {
            error_log('Error al registrar ajuste de lote: ' . $exception->getMessage());
            http_response_code(500);
            $this->index($data, 'No se pudo guardar el ajuste. Revise el historial antes de reintentar y verifique el script SQL de actualizacion.');
            return;
        }
        unset($_SESSION['ajuste_token']);
        $_SESSION['ajuste_success'] = 'Ajuste #' . $id . ' registrado correctamente. La entrada original no fue modificada.';
        $this->redirect('/ajuste?' . http_build_query([
            'historial' => (int) $data['idInventarioEntrante'],
            'q' => trim((string) ($_POST['q'] ?? '')),
            'pagina' => max(1, (int) ($_POST['pagina'] ?? 1)),
        ]));
    }

    public function silosDisponibles(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || !Auth::can('corregir_entradas', 'editar')) {
            http_response_code(403);
            echo json_encode(['error' => 'No tiene permiso para consultar silos.']);
            return;
        }
        try {
            $id = filter_var($_GET['idInventarioEntrante'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                throw new InvalidArgumentException('Seleccione un lote valido.');
            }
            echo json_encode($this->model('AjusteLote')->obtenerSilosParaAjuste($id, (string) ($_GET['tipo'] ?? '')));
        } catch (InvalidArgumentException | DomainException $exception) {
            http_response_code(422);
            echo json_encode(['error' => $exception->getMessage()]);
        } catch (PDOException $exception) {
            error_log('Error al consultar silos para ajuste: ' . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'No se pudieron consultar los silos. Verifique la actualizacion SQL.']);
        }
    }
}
