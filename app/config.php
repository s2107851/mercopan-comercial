<?php
declare(strict_types=1);
date_default_timezone_set('America/Montevideo');
$local = __DIR__ . '/config.local.php';
if (!is_file($local)) {
    throw new RuntimeException('Falta app/config.local.php. Copiá app/config.local.example.php y configurá el sistema.');
}
$config = require $local;
if (!is_array($config)) throw new RuntimeException('Configuración incorrecta.');
