<?php

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow', true);

echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'SAPI: ' . PHP_SAPI . PHP_EOL;
echo 'php.ini: ' . (php_ini_loaded_file() ?: 'ninguno') . PHP_EOL;
echo 'Fileinfo: ' . (class_exists('finfo') ? 'HABILITADO' : 'NO HABILITADO') . PHP_EOL;
