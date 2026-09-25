<?php

// Verifica la conexión con WSFEv1 (no usa el certificado).
// Uso: php examples/wsfe_dummy.php

$config = require __DIR__ . '/bootstrap.php';

try {
    $wsfe = new Arca\Wsfe(new Arca\Wsaa($config));
    print_r($wsfe->dummy());
} catch (Arca\ArcaException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
