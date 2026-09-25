<?php

namespace Arca;

/**
 * Cliente de WSFEv1 (Factura Electrónica, comprobantes A, B, C y M sin detalle de ítems).
 *
 * Manual del desarrollador: https://www.afip.gob.ar/fe/documentos/manual-desarrollador-ARCA-COMPG-v4-0.pdf
 */
class Wsfe
{
    const SERVICE = 'wsfe';
    const WSDL_HOMO = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';
    const WSDL_PROD = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';

    /** @var Wsaa */
    private $wsaa;

    /** @var \SoapClient|null */
    private $client;

    public function __construct(Wsaa $wsaa)
    {
        $this->wsaa = $wsaa;
    }

    /**
     * Verifica que los servidores de ARCA estén funcionando (no requiere autenticación).
     */
    public function dummy(): \stdClass
    {
        return $this->client()->FEDummy()->FEDummyResult;
    }

    /**
     * Número del último comprobante autorizado para el punto de venta y tipo indicados.
     */
    public function ultimoComprobante(int $ptoVta, int $cbteTipo): int
    {
        $result = $this->call('FECompUltimoAutorizado', [
            'PtoVta' => $ptoVta,
            'CbteTipo' => $cbteTipo,
        ]);
        return (int) $result->CbteNro;
    }

    /**
     * Solicita el CAE para un comprobante.
     *
     * @param array $detalle Campos de FECAEDetRequest (Concepto, DocTipo, DocNro, CbteDesde,
     *                       CbteHasta, CbteFch, ImpTotal, ImpNeto, ImpIVA, MonId, Iva, ...)
     * @return array ['resultado' => 'A'|'R', 'cae', 'cae_vto', 'numero', 'observaciones', 'respuesta']
     */
    public function solicitarCae(int $ptoVta, int $cbteTipo, array $detalle): array
    {
        $result = $this->call('FECAESolicitar', [
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $ptoVta,
                    'CbteTipo' => $cbteTipo,
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => $detalle,
                ],
            ],
        ]);

        $det = self::toList($result->FeDetResp->FECAEDetResponse)[0];
        $observaciones = [];
        if (isset($det->Observaciones->Obs)) {
            foreach (self::toList($det->Observaciones->Obs) as $obs) {
                $observaciones[] = ['code' => $obs->Code, 'msg' => $obs->Msg];
            }
        }

        return [
            'resultado' => $det->Resultado,
            'cae' => isset($det->CAE) ? $det->CAE : null,
            'cae_vto' => isset($det->CAEFchVto) ? $det->CAEFchVto : null,
            'numero' => (int) $det->CbteDesde,
            'observaciones' => $observaciones,
            'respuesta' => $result,
        ];
    }

    /**
     * Toma el próximo número disponible y solicita el CAE (completa CbteDesde y CbteHasta).
     */
    public function autorizarSiguiente(int $ptoVta, int $cbteTipo, array $detalle): array
    {
        $numero = $this->ultimoComprobante($ptoVta, $cbteTipo) + 1;
        $detalle['CbteDesde'] = $numero;
        $detalle['CbteHasta'] = $numero;
        return $this->solicitarCae($ptoVta, $cbteTipo, $detalle);
    }

    /**
     * Consulta un comprobante ya emitido.
     */
    public function consultarComprobante(int $ptoVta, int $cbteTipo, int $numero): \stdClass
    {
        $result = $this->call('FECompConsultar', [
            'FeCompConsReq' => [
                'CbteTipo' => $cbteTipo,
                'CbteNro' => $numero,
                'PtoVta' => $ptoVta,
            ],
        ]);
        return $result->ResultGet;
    }

    /**
     * Llama a un método de parámetros, p. ej. 'FEParamGetTiposCbte', 'FEParamGetTiposIva',
     * 'FEParamGetTiposDoc', 'FEParamGetPtosVenta', 'FEParamGetCondicionIvaReceptor'.
     */
    public function parametros(string $metodo, array $params = []): \stdClass
    {
        return $this->call($metodo, $params)->ResultGet;
    }

    /**
     * Llama a cualquier método de WSFEv1 agregando la autenticación.
     * Lanza ArcaException si ARCA devuelve errores.
     */
    public function call(string $metodo, array $params = []): \stdClass
    {
        $credenciales = $this->wsaa->getCredentials(self::SERVICE);
        $params = ['Auth' => [
            'Token' => $credenciales['token'],
            'Sign' => $credenciales['sign'],
            'Cuit' => $this->wsaa->getCuit(),
        ]] + $params;

        try {
            $response = $this->client()->$metodo($params);
        } catch (\SoapFault $e) {
            throw new ArcaException("WSFE $metodo: " . $e->getMessage(), [], $e);
        }

        $result = $response->{$metodo . 'Result'};
        if (isset($result->Errors->Err)) {
            $errores = [];
            foreach (self::toList($result->Errors->Err) as $err) {
                $errores[] = ['code' => $err->Code, 'msg' => $err->Msg];
            }
            $texto = implode('; ', array_map(function ($e) {
                return "[{$e['code']}] {$e['msg']}";
            }, $errores));
            throw new ArcaException("WSFE $metodo: $texto", $errores);
        }
        return $result;
    }

    public function getSoapClient(): \SoapClient
    {
        return $this->client();
    }

    private function client(): \SoapClient
    {
        if ($this->client === null) {
            $wsdl = $this->wsaa->isProduction() ? self::WSDL_PROD : self::WSDL_HOMO;
            $this->client = SoapClientFactory::create($wsdl, $this->wsaa->getConfig(), SOAP_1_2);
        }
        return $this->client;
    }

    /**
     * SOAP devuelve un objeto cuando hay un solo elemento y un array cuando hay varios.
     */
    public static function toList($valor): array
    {
        if ($valor === null) {
            return [];
        }
        return is_array($valor) ? $valor : [$valor];
    }
}
