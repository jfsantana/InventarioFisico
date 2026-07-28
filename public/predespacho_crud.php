<?php

require_once __DIR__ . '/../config/config.php';

spl_autoload_register(function (string $className): void {
    $paths = [
        __DIR__ . '/../app/Core/' . $className . '.php',
        __DIR__ . '/../app/Models/' . $className . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

function predespachoClienteModel(): Cliente
{
    return new Cliente();
}

function predespachoModel(): Predespacho
{
    return new Predespacho();
}

function obtenerTodosLosClientes(): array
{
    return predespachoClienteModel()->obtenerTodosLosClientes();
}

function obtenerClientePorId(int $idCliente): ?array
{
    return predespachoClienteModel()->obtenerClientePorId($idCliente);
}

function crearCliente(string $rif, string $nombre, string $direccion, string $tipo): int|false
{
    return predespachoClienteModel()->crearCliente($rif, $nombre, $direccion, $tipo);
}

function actualizarCliente(int $idCliente, string $rif, string $nombre, string $direccion, string $tipo): bool
{
    return predespachoClienteModel()->actualizarCliente($idCliente, $rif, $nombre, $direccion, $tipo);
}

function desactivarCliente(int $idCliente): bool
{
    return predespachoClienteModel()->desactivarCliente($idCliente);
}

function crearCabeceraPredespacho(
    int $idCliente,
    string $fechaRetiro,
    int|string $userCreador,
    ?string $codigoNotaEntregaSAP = null,
    ?string $observaciones = null
): int|false {
    return predespachoModel()->crearCabeceraPredespacho($idCliente, $fechaRetiro, $userCreador, $codigoNotaEntregaSAP, $observaciones);
}

function obtenerTodosLosPredespachos(): array
{
    return predespachoModel()->obtenerTodosLosPredespachos();
}

function obtenerPredespachoPorId(int $idCabeceraPredespacho): ?array
{
    return predespachoModel()->obtenerPredespachoPorId($idCabeceraPredespacho);
}

function actualizarCodigoSAP(int $idCabeceraPredespacho, ?string $codigoNotaEntregaSAP): bool
{
    return predespachoModel()->actualizarCodigoSAP($idCabeceraPredespacho, $codigoNotaEntregaSAP);
}

function actualizarStatusCabecera(int $idCabeceraPredespacho, string $nuevoStatus): bool
{
    return predespachoModel()->actualizarStatusCabecera($idCabeceraPredespacho, $nuevoStatus);
}

function verificarYCerrarPredespacho(int $idCabeceraPredespacho): bool
{
    return predespachoModel()->verificarYCerrarPredespacho($idCabeceraPredespacho);
}

function agregarItemPredespacho(int $idCabeceraPredespacho, int $idInventarioEntrante, float $cantidadSolicitada, ?string $tipo = null): array
{
    return predespachoModel()->agregarItemPredespacho($idCabeceraPredespacho, $idInventarioEntrante, $cantidadSolicitada, $tipo);
}

function obtenerItemsPorPredespacho(int $idCabeceraPredespacho): array
{
    return predespachoModel()->obtenerItemsPorPredespacho($idCabeceraPredespacho);
}

function eliminarItemPredespacho(int $idItem): array
{
    return predespachoModel()->eliminarItemPredespacho($idItem);
}

function actualizarCantidadDespachada(int $idItem, float $cantidadDespachada): bool
{
    return predespachoModel()->actualizarCantidadDespachada($idItem, $cantidadDespachada);
}

function cerrarItem(int $idItem): bool
{
    return predespachoModel()->cerrarItem($idItem);
}

function obtenerDisponibilidadPorLote(int $idInventarioEntrante): ?array
{
    return predespachoModel()->obtenerDisponibilidadPorLote($idInventarioEntrante);
}

function buscarProductosDisponibles(string $terminoBusqueda): array
{
    return predespachoModel()->buscarProductosDisponibles($terminoBusqueda);
}

function obtenerLotesPorProducto(int $idProducto): array
{
    return predespachoModel()->obtenerLotesPorProducto($idProducto);
}

function obtenerSectoresPendientesPredespacho(): array
{
    return predespachoModel()->obtenerSectoresPendientesPredespacho();
}

function obtenerPredespachosPorSector(string $sector): array
{
    return predespachoModel()->obtenerPredespachosPorSector($sector);
}

function obtenerPredespachoPorCodigo(string $codigoInterno): ?array
{
    return predespachoModel()->obtenerPredespachoPorCodigo($codigoInterno);
}

function obtenerItemsPorPredespachoYSector(int $idCabeceraPredespacho, string $sector): array
{
    return predespachoModel()->obtenerItemsPorPredespachoYSector($idCabeceraPredespacho, $sector);
}

function registrarSalida(int $idItem, float $cantidadDespachada, int $idCabeceraPredespacho): array
{
    return predespachoModel()->registrarSalida($idItem, $cantidadDespachada, $idCabeceraPredespacho);
}