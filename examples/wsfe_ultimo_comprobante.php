<?php

// Muestra el último comprobante autorizado.
// Uso: php examples/wsfe_ultimo_comprobante.php [tipo_comprobante]
//   tipos comunes: 1 = Factura A, 6 = Factura B, 11 = Factura C

$config = require __DIR__ . '/bootstrap.php';
$tipo = isset($argv[1]) ? (int) $argv[1] : 11;

try {
    $wsfe = new Arca\Wsfe(new Arca\Wsaa($config));
    $numero = $wsfe->ultimoComprobante($config['punto_venta'], $tipo);
    echo "Punto de venta {$config['punto_venta']}, tipo $tipo: último número $numero\n";
} catch (Arca\ArcaException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
