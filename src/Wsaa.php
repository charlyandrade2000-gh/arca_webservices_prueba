<?php

namespace Arca;

/**
 * Cliente del WSAA (Web Service de Autenticación y Autorización).
 *
 * Firma un ticket de requerimiento de acceso (TRA) con el certificado y
 * obtiene el Token y Sign necesarios para llamar a los demás servicios.
 * El ticket de acceso (TA) dura 12 horas y se guarda en cache_dir para
 * reutilizarlo: ARCA rechaza un nuevo login mientras haya un TA vigente.
 */
class Wsaa
{
    const WSDL_HOMO = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl';
    const WSDL_PROD = 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl';

    /** Margen (en segundos) antes del vencimiento para renovar el TA */
    const MARGEN_RENOVACION = 600;

    /** @var array */
    private $config;

    public function __construct(array $config)
    {
        foreach (['cuit', 'cert', 'key', 'cache_dir'] as $clave) {
            if (empty($config[$clave])) {
                throw new ArcaException("Falta la opción de configuración '$clave'");
            }
        }
        foreach (['cert', 'key'] as $clave) {
            if (!is_readable($config[$clave])) {
                throw new ArcaException("No se puede leer el archivo '{$config[$clave]}' ($clave)");
            }
        }
        if (!is_dir($config['cache_dir']) && !mkdir($config['cache_dir'], 0700, true)) {
            throw new ArcaException("No se pudo crear el directorio '{$config['cache_dir']}'");
        }
        $this->config = $config;
    }

    public function getCuit(): string
    {
        return (string) $this->config['cuit'];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isProduction(): bool
    {
        return !empty($this->config['production']);
    }

    /**
     * Devuelve ['token' => ..., 'sign' => ..., 'expiration' => timestamp] para el servicio.
     *
     * @param string $service Nombre del servicio en ARCA, p. ej. 'wsfe' o 'ws_sr_padron_a13'
     */
    public function getCredentials(string $service): array
    {
        $ta = $this->readCache($service);
        if ($ta === null) {
            $ta = $this->login($service);
            $this->writeCache($service, $ta);
        }
        return $ta;
    }

    private function login(string $service): array
    {
        $cms = $this->signTra($this->createTra($service));

        $wsdl = $this->isProduction() ? self::WSDL_PROD : self::WSDL_HOMO;
        $client = SoapClientFactory::create($wsdl, $this->config);

        try {
            $response = $client->loginCms(['in0' => $cms]);
        } catch (\SoapFault $e) {
            throw new ArcaException('WSAA: ' . $e->getMessage(), [['code' => $e->faultcode, 'msg' => $e->getMessage()]], $e);
        }

        $xml = simplexml_load_string($response->loginCmsReturn);
        if ($xml === false) {
            throw new ArcaException('WSAA: respuesta inválida');
        }

        return [
            'token' => (string) $xml->credentials->token,
            'sign' => (string) $xml->credentials->sign,
            'expiration' => strtotime((string) $xml->header->expirationTime),
        ];
    }

    /**
     * Arma el XML del ticket de requerimiento de acceso (TRA).
     */
    protected function createTra(string $service): string
    {
        $ahora = time();
        $tra = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"/>');
        $tra->addChild('header');
        $tra->header->addChild('uniqueId', (string) $ahora);
        $tra->header->addChild('generationTime', date('c', $ahora - 600));
        $tra->header->addChild('expirationTime', date('c', $ahora + 600));
        $tra->addChild('service', $service);
        return $tra->asXML();
    }

    /**
     * Firma el TRA con el certificado y devuelve el CMS en base64.
     */
    protected function signTra(string $tra): string
    {
        $dir = $this->config['cache_dir'];
        $traFile = tempnam($dir, 'tra');
        $cmsFile = tempnam($dir, 'cms');

        try {
            file_put_contents($traFile, $tra);
            $clave = ['file://' . realpath($this->config['key']), isset($this->config['passphrase']) ? $this->config['passphrase'] : ''];
            $ok = openssl_pkcs7_sign($traFile, $cmsFile, 'file://' . realpath($this->config['cert']), $clave, [], !PKCS7_DETACHED);
            if (!$ok) {
                throw new ArcaException('No se pudo firmar el TRA: ' . openssl_error_string()
                    . ' (verificá que el certificado y la clave privada se correspondan)');
            }
            $smime = file_get_contents($cmsFile);
        } finally {
            @unlink($traFile);
            @unlink($cmsFile);
        }

        // El resultado es un mensaje S/MIME: se descartan los encabezados y queda el base64.
        $partes = preg_split("/\r?\n\r?\n/", $smime, 2);
        return trim($partes[1]);
    }

    private function cacheFile(string $service): string
    {
        $ambiente = $this->isProduction() ? 'prod' : 'homo';
        return rtrim($this->config['cache_dir'], '/') . "/ta_{$service}_{$ambiente}_{$this->getCuit()}.json";
    }

    private function readCache(string $service): ?array
    {
        $file = $this->cacheFile($service);
        if (!is_file($file)) {
            return null;
        }
        $ta = json_decode((string) file_get_contents($file), true);
        if (!is_array($ta) || empty($ta['token']) || $ta['expiration'] - self::MARGEN_RENOVACION < time()) {
            return null;
        }
        return $ta;
    }

    private function writeCache(string $service, array $ta): void
    {
        $file = $this->cacheFile($service);
        file_put_contents($file, json_encode($ta), LOCK_EX);
        @chmod($file, 0600);
    }
}
