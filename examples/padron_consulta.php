<?php

// Consulta un CUIT en el padrón.
// Uso: php examples/padron_consulta.php 20111111112 [a13|a5]

$config = require __DIR__ . '/bootstrap.php';

if (empty($argv[1])) {
    fwrite(STDERR, "Uso: php examples/padron_consulta.php CUIT [a13|a5]\n");
    exit(1);
}
$alcance = isset($argv[2]) ? $argv[2] : 'a13';

try {
    $padron = new Arca\Padron(new Arca\Wsaa($config), $alcance);
    print_r($padron->getPersona($argv[1]));
} catch (Arca\ArcaException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
