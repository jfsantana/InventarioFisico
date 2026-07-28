<?php

$validacion = [
    'trigger' => [
        'status' => 'CREADO',
        'detalle' => 'No existia trg_codigo_interno_predespacho en los archivos SQL/PHP revisados. Se creo database/trigger_predespacho.sql con trigger BEFORE INSERT y formato PRE-AÑO-NNNNN usando LPAD.',
    ],
    'rutas' => [
        'status' => 'OK',
        'detalle' => 'public/index.php delega el routing a App. En app/Core/App.php existen aliases predespachos, predespacho-detalle y predespacho-salida; ademas el routing automatico cubre /predespacho, /predespacho/detalle y /predespacho/salida.',
    ],
    'conexion' => [
        'status' => 'OK',
        'detalle' => 'public/predespacho_crud.php no requiere conexion.php. Usa require_once __DIR__ . \'/../config/config.php\' y autoload para app/Core/Database.php, que es la conexion real del proyecto.',
    ],
];

foreach ($validacion as $key => $val) {
    echo "[{$val['status']}] {$key}: {$val['detalle']}" . PHP_EOL;
}