<?php

require_once __DIR__ . '/../config/config.php';

spl_autoload_register(function (string $className): void {
    $paths = [
        __DIR__ . '/../app/Core/' . $className . '.php',
        __DIR__ . '/../app/Controllers/' . $className . '.php',
        __DIR__ . '/../app/Models/' . $className . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$app = new App();
$app->run();
