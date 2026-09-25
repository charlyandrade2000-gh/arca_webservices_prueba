<?php

namespace Arca;

/**
 * Consulta de contribuyentes en el padrón de ARCA.
 *
 *  - 'a13': ws_sr_padron_a13, datos generales (nombre, domicilio, estado de la clave).
 *  - 'a5':  ws_sr_constancia_inscripcion, datos de la constancia de inscripción
 *           (impuestos, condición frente al IVA, monotributo, actividades).
 */
class Padron
{
    const ALCANCES = [
        'a13' => [
            'service' => 'ws_sr_padron_a13',
            'homo' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA13?WSDL',
            'prod' => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA13?WSDL',
            'metodo' => 'getPersona',
        ],
        'a5' => [
            'service' => 'ws_sr_constancia_inscripcion',
            'homo' => 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL',
            'prod' => 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5?WSDL',
            'metodo' => 'getPersona_v2',
        ],
    ];

    /** @var Wsaa */
    private $wsaa;

    /** @var array */
    private $alcance;

    /** @var \SoapClient|null */
    private $client;

    public function __construct(Wsaa $wsaa, string $alcance = 'a13')
    {
        if (!isset(self::ALCANCES[$alcance])) {
            throw new ArcaException("Alcance de padrón desconocido: '$alcance' (use 'a13' o 'a5')");
        }
        $this->wsaa = $wsaa;
        $this->alcance = self::ALCANCES[$alcance];
    }

    /**
     * Verifica que el servicio esté funcionando (no requiere autenticación).
     */
    public function dummy(): \stdClass
    {
        return $this->client()->dummy()->return;
    }

    /**
     * Devuelve los datos del contribuyente (el contenido de personaReturn).
     *
     * @param string|int $cuit CUIT/CUIL a consultar (con o sin guiones)
     */
    public function getPersona($cuit): \stdClass
    {
        $credenciales = $this->wsaa->getCredentials($this->alcance['service']);
        $metodo = $this->alcance['metodo'];

        try {
            $response = $this->client()->$metodo([
                'token' => $credenciales['token'],
                'sign' => $credenciales['sign'],
                'cuitRepresentada' => $this->wsaa->getCuit(),
                'idPersona' => preg_replace('/\D/', '', (string) $cuit),
            ]);
        } catch (\SoapFault $e) {
            throw new ArcaException('Padrón: ' . $e->getMessage(), [], $e);
        }

        return $response->personaReturn;
    }

    private function client(): \SoapClient
    {
        if ($this->client === null) {
            $wsdl = $this->wsaa->isProduction() ? $this->alcance['prod'] : $this->alcance['homo'];
            $this->client = SoapClientFactory::create($wsdl, $this->wsaa->getConfig());
        }
        return $this->client;
    }
}
