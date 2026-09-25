<?php

$raiz = dirname(__DIR__);

if (is_file($raiz . '/vendor/autoload.php')) {
    require $raiz . '/vendor/autoload.php';
} else {
    require $raiz . '/src/autoload.php';
}

if (!is_file($raiz . '/config/config.php')) {
    fwrite(STDERR, "Falta config/config.php: copiá config/config.example.php y completalo.\n");
    exit(1);
}

return require $raiz . '/config/config.php';
