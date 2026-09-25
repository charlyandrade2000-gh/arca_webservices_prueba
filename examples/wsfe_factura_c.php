<?php

// Emite una Factura C (monotributo) a consumidor final por $1000.
// Uso: php examples/wsfe_factura_c.php
//
// En la Factura C no se discrimina IVA: ImpNeto = ImpTotal, ImpIVA = 0 y no se envía el bloque 'Iva'.

$config = require __DIR__ . '/bootstrap.php';

$total = 1000.00;

$detalle = [
    'Concepto' => 1,             // 1 = Productos, 2 = Servicios, 3 = Productos y servicios
    'DocTipo' => 99,             // 99 = Consumidor final sin identificar, 80 = CUIT, 96 = DNI
    'DocNro' => 0,
    'CbteFch' => date('Ymd'),
    'ImpTotal' => $total,
    'ImpTotConc' => 0,           // en Factura C siempre 0
    'ImpNeto' => $total,         // en Factura C es el subtotal del comprobante
    'ImpOpEx' => 0,              // en Factura C siempre 0
    'ImpIVA' => 0,               // en Factura C siempre 0
    'ImpTrib' => 0,              // otros tributos
    'MonId' => 'PES',
    'MonCotiz' => 1,
    'CondicionIVAReceptorId' => 5, // 5 = Consumidor final (obligatorio, RG 5616)
];

// Para servicios (Concepto 2 o 3) son obligatorias las fechas del período facturado:
// $detalle['Concepto'] = 2;
// $detalle['FchServDesde'] = date('Ym01');
// $detalle['FchServHasta'] = date('Ymt');
// $detalle['FchVtoPago'] = date('Ymd');

try {
    $wsfe = new Arca\Wsfe(new Arca\Wsaa($config));
    $r = $wsfe->autorizarSiguiente($config['punto_venta'], 11, $detalle); // 11 = Factura C

    if ($r['resultado'] === 'A') {
        echo "Factura C {$r['numero']} autorizada. CAE {$r['cae']}, vence {$r['cae_vto']}\n";
    } else {
        echo "Comprobante rechazado.\n";
    }
    foreach ($r['observaciones'] as $obs) {
        echo "  Observación [{$obs['code']}] {$obs['msg']}\n";
    }
} catch (Arca\ArcaException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
