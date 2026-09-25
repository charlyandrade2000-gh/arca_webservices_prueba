<?php

namespace Arca;

/**
 * Crea los SoapClient con las opciones comunes a todos los servicios de ARCA.
 */
class SoapClientFactory
{
    public static function create(string $wsdl, array $config, int $soapVersion = SOAP_1_1): \SoapClient
    {
        $ssl = [];
        // Los servidores de ARCA usan claves DH que OpenSSL moderno rechaza
        // ("dh key too small"); bajar el nivel de seguridad lo soluciona.
        if (!empty($config['ssl_ciphers'])) {
            $ssl['ciphers'] = $config['ssl_ciphers'];
        }

        $options = [
            'soap_version' => $soapVersion,
            'trace' => true,
            'exceptions' => true,
            'encoding' => 'UTF-8',
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'connection_timeout' => isset($config['timeout']) ? (int) $config['timeout'] : 30,
            'stream_context' => stream_context_create(['ssl' => $ssl]),
        ];

        try {
            return new \SoapClient($wsdl, $options);
        } catch (\SoapFault $e) {
            throw new ArcaException('No se pudo cargar el WSDL ' . $wsdl . ': ' . $e->getMessage(), [], $e);
        }
    }
}
