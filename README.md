# ARCA Web Services (PHP 7.4)

Clientes PHP sin framework para los web services de ARCA (ex AFIP):

- **WSAA**: autenticación con certificado (Token y Sign, con caché de 12 h).
- **WSFEv1**: factura electrónica (último comprobante, solicitar CAE, consultar, parámetros).
- **Padrón**: consulta de contribuyentes (`ws_sr_padron_a13` y `ws_sr_constancia_inscripcion`, alcance 5).

## Requisitos

- PHP 7.4 o superior con las extensiones `soap`, `openssl` y `simplexml`
  (en Debian/Ubuntu: `apt install php7.4-soap php7.4-xml`).
- Certificado de ARCA y su clave privada.
- Composer es opcional: sin Composer se usa `src/autoload.php`.

## Instalación

```bash
git clone https://github.com/charlyandrade2000-gh/arca_webservices_prueba.git
cd arca_webservices_prueba
cp config/config.example.php config/config.php   # completar CUIT, rutas, punto de venta
```

Copiar el certificado y la clave privada a `certs/`, en el equipo donde se ejecuta el proyecto:

```
certs/certificado.crt   # certificado descargado de ARCA
certs/clave.key         # clave privada con la que se generó el pedido (CSR)
```

> ⚠️ **Nunca** subir los certificados ni `config/config.php` a GitHub. Ya están en `.gitignore`.
> Dar permisos restringidos: `chmod 600 certs/*`

El directorio `var/` guarda los tickets de acceso y debe tener permiso de escritura.

## Autorizar el certificado para cada servicio

Un certificado solo sirve para los servicios a los que está asociado.

**Homologación (pruebas):** en ARCA, con clave fiscal, entrar a *WSASS - Autogestión Certificados Homologación*
→ *Crear autorización a servicio* y asociar el certificado a:
- `wsfe`
- `ws_sr_padron_a13` y/o `ws_sr_constancia_inscripcion`

**Producción:** en *Administrador de Relaciones de Clave Fiscal* → *Nueva Relación* → *ARCA* → *WebServices*,
elegir el servicio y asociarlo al certificado (el "computador fiscal"). Además, para facturar hay que dar de alta un
punto de venta de tipo *Factura Electrónica - Monotributo/RI - Web Services* en *Administración de puntos de venta y domicilios*.

## Ejemplos

```bash
php examples/wsfe_dummy.php                   # estado de los servidores
php examples/wsfe_ultimo_comprobante.php 11   # último número de Factura C
php examples/wsfe_factura_c.php               # emite una Factura C de prueba y obtiene el CAE
php examples/padron_consulta.php 20111111112 a13
php examples/padron_consulta.php 20111111112 a5
```

## Uso en código

```php
require 'src/autoload.php';
$config = require 'config/config.php';

$wsaa = new Arca\Wsaa($config);

$wsfe = new Arca\Wsfe($wsaa);
$ultimo = $wsfe->ultimoComprobante(1, 11);
$r = $wsfe->autorizarSiguiente(1, 11, $detalle);   // ['resultado', 'cae', 'cae_vto', 'numero', 'observaciones']
$tiposIva = $wsfe->parametros('FEParamGetTiposIva');

$padron = new Arca\Padron($wsaa, 'a5');
$persona = $padron->getPersona('20-11111111-2');
```

Los errores de ARCA se lanzan como `Arca\ArcaException` (`getErrors()` devuelve los códigos).

## Problemas frecuentes

| Error | Causa / solución |
|---|---|
| `dh key too small` | Dejar `'ssl_ciphers' => 'DEFAULT@SECLEVEL=1'` en la configuración. |
| `El CEE ya posee un TA valido para el acceso al WSN solicitado` | Se borró la caché de `var/` teniendo un ticket vigente. Esperar a que venza (máx. 12 h) o usar otro certificado. |
| `No se pudo firmar el TRA` | El certificado no corresponde a la clave privada, o la contraseña es incorrecta. |
| `Computador no autorizado a acceder al servicio` | Falta asociar el certificado al servicio (ver arriba). |
| Error sobre `CondicionIVAReceptorId` | Es obligatorio informar la condición frente al IVA del receptor (RG 5616). Ver `FEParamGetCondicionIvaReceptor`. |
| Hora rechazada por el WSAA | Sincronizar el reloj del servidor (NTP). |
