<?php

// Emite una Factura B a consumidor final por $121 (neto $100 + IVA 21%).
// Uso: php examples/wsfe_factura_b.php
//
// Para una Factura C (monotributo): CbteTipo 11, ImpNeto = ImpTotal,
// ImpIVA = 0 y sin el bloque 'Iva'.

$config = require __DIR__ . '/bootstrap.php';

$neto = 100.00;
$iva = round($neto * 0.21, 2);

$detalle = [
    'Concepto' => 1,             // 1 = Productos, 2 = Servicios, 3 = Productos y servicios
    'DocTipo' => 99,             // 99 = Consumidor final sin identificar, 80 = CUIT, 96 = DNI
    'DocNro' => 0,
    'CbteFch' => date('Ymd'),
    'ImpTotal' => $neto + $iva,
    'ImpTotConc' => 0,           // importe no gravado
    'ImpNeto' => $neto,
    'ImpOpEx' => 0,              // importe exento
    'ImpIVA' => $iva,
    'ImpTrib' => 0,              // otros tributos
    'MonId' => 'PES',
    'MonCotiz' => 1,
    'CondicionIVAReceptorId' => 5, // 5 = Consumidor final (obligatorio, RG 5616)
    'Iva' => [
        'AlicIva' => [
            ['Id' => 5, 'BaseImp' => $neto, 'Importe' => $iva], // 5 = 21%
        ],
    ],
    // Si Concepto es 2 o 3 agregar: FchServDesde, FchServHasta y FchVtoPago (formato Ymd)
];

try {
    $wsfe = new Arca\Wsfe(new Arca\Wsaa($config));
    $r = $wsfe->autorizarSiguiente($config['punto_venta'], 6, $detalle);

    if ($r['resultado'] === 'A') {
        echo "Comprobante {$r['numero']} autorizado. CAE {$r['cae']}, vence {$r['cae_vto']}\n";
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
