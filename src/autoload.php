<?php

// Autoload simple para usar el proyecto sin Composer.
spl_autoload_register(function ($clase) {
    $prefijo = 'Arca\\';
    if (strpos($clase, $prefijo) !== 0) {
        return;
    }
    $archivo = __DIR__ . '/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
