<?php

// Copiar este archivo como config/config.php y completar los datos.
// config/config.php NO se sube al repositorio.

return [
    // CUIT del contribuyente que emite (sin guiones)
    'cuit' => '20111111112',

    // false = homologación (pruebas), true = producción
    'production' => false,

    // Certificado emitido por ARCA y clave privada con la que se generó el CSR
    'cert' => __DIR__ . '/../certs/certificado.crt',
    'key' => __DIR__ . '/../certs/clave.key',
    'passphrase' => '', // si la clave privada tiene contraseña

    // Directorio donde se guardan los tickets de acceso (TA) del WSAA
    'cache_dir' => __DIR__ . '/../var',

    // Evita el error "dh key too small" con los servidores de ARCA
    'ssl_ciphers' => 'DEFAULT@SECLEVEL=1',

    // Tiempo máximo de conexión en segundos
    'timeout' => 30,

    // Punto de venta habilitado para web services (usado en los ejemplos)
    'punto_venta' => 1,
];
