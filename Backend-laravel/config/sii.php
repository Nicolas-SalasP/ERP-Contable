<?php

/*
|--------------------------------------------------------------------------
| Configuracion del modulo SII (Facturacion Electronica DTE Chile)
|--------------------------------------------------------------------------
|
| Las URLs, algoritmos de firma y encoding son normativos y NO deben
| modificarse sin actualizar la referencia normativa correspondiente en
| docs/sii-normativa/ (manual_certificacion.pdf, formato_dte_202602.pdf).
|
| Fase 0: solo plumbing. Claves de CAF, certificado y catalogos llegan
| en Fase 1+.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    | 'certificacion' apunta a maullin.sii.cl (pruebas).
    | 'produccion'    apunta a palena.sii.cl (DTE reales con valor tributario).
    */
    'ambiente' => env('SII_AMBIENTE', 'certificacion'),

    /*
    |--------------------------------------------------------------------------
    | URLs de Web Services del SII
    |--------------------------------------------------------------------------
    | Endpoints oficiales agrupados por ambiente. La seleccion en runtime se
    | resuelve con config("sii.urls.{$ambiente}.{$accion}").
    */
    'urls' => [
        'certificacion' => [
            'semilla' => 'https://maullin.sii.cl/DTEWS/CrSeed.jws',
            'token' => 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws',
            'upload' => 'https://maullin.sii.cl/cgi_dte/UPL/DTEUpload',
            'estado_envio' => 'https://maullin.sii.cl/DTEWS/QueryEstUp.jws',
            'estado_dte' => 'https://maullin.sii.cl/DTEWS/QueryEstDte.jws',
        ],
        'produccion' => [
            'semilla' => 'https://palena.sii.cl/DTEWS/CrSeed.jws',
            'token' => 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws',
            'upload' => 'https://palena.sii.cl/cgi_dte/UPL/DTEUpload',
            'estado_envio' => 'https://palena.sii.cl/DTEWS/QueryEstUp.jws',
            'estado_dte' => 'https://palena.sii.cl/DTEWS/QueryEstDte.jws',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Boleta Electronica (39/41) — API REST separada, servidores propios
    |--------------------------------------------------------------------------
    | NO son los mismos servidores que Factura/NC/ND (maullin/palena arriba).
    | Confirmado contra el spec OpenAPI oficial del SII
    | (https://www4c.sii.cl/bolcoreinternetui/api/openapi.yaml,
    | Instructivo Tecnico Boleta Electronica v1.0.5): semilla/token van por
    | un host, el envio/consulta de estado por otro, y el token es
    | especifico de boleta (no reutiliza el de Factura). Respuesta JSON
    | (no texto/HTML como el WS legacy de Factura).
    */
    'urls_boleta' => [
        'certificacion' => [
            'semilla' => 'https://apicert.sii.cl/recursos/v1/boleta.electronica.semilla',
            'token' => 'https://apicert.sii.cl/recursos/v1/boleta.electronica.token',
            'envio' => 'https://pangal.sii.cl/recursos/v1/boleta.electronica.envio',
            'estado_envio' => 'https://pangal.sii.cl/recursos/v1/boleta.electronica.envio', // + "/{rut}-{dv}-{trackid}"
        ],
        'produccion' => [
            'semilla' => 'https://api.sii.cl/recursos/v1/boleta.electronica.semilla',
            'token' => 'https://api.sii.cl/recursos/v1/boleta.electronica.token',
            'envio' => 'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio',
            'estado_envio' => 'https://rahue.sii.cl/recursos/v1/boleta.electronica.envio', // + "/{rut}-{dv}-{trackid}"
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Firma Electronica
    |--------------------------------------------------------------------------
    | Algoritmos FIJOS por XSD oficial xmldsignature_v10.xsd del SII. NO
    | cambiar a SHA256: el SII rechaza el DTE antes del schema si el
    | DigestMethod o SignatureMethod no son los enumerados aqui.
    */
    'firma' => [
        'algoritmo_signature' => 'http://www.w3.org/2000/09/xmldsig#rsa-sha1',
        'algoritmo_digest' => 'http://www.w3.org/2000/09/xmldsig#sha1',
        'canonicalization' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encoding XML
    |--------------------------------------------------------------------------
    | ISO-8859-1 obligatorio. El digest debe calcularse SOBRE el XML ya
    | convertido a ISO-8859-1, no sobre UTF-8.
    */
    'xml' => [
        'encoding' => 'ISO-8859-1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento
    |--------------------------------------------------------------------------
    | Disco privado para XML firmados, acuses y PDFs. Default 'local' apunta
    | a storage/app/private (ver config/filesystems.php).
    */
    'storage' => [
        'disk' => env('SII_DISK', 'sii_xml'),
        'path_prefix' => 'sii',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts HTTP (segundos)
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'connect' => 10,
        'read' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cliente HTTP — timeouts y reintentos de transporte
    |--------------------------------------------------------------------------
    | timeout              : segundos esperando la respuesta completa.
    | connect_timeout      : segundos para establecer la conexion TCP.
    | max_reintentos_polling: tope de ciclos de polling antes de marcar
    |                         ERROR_TIMEOUT (fallback al limite temporal
    |                         TIMEOUT_HORAS_ACUMULADAS del servicio).
    */
    'http' => [
        'timeout' => env('SII_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('SII_HTTP_CONNECT_TIMEOUT', 10),
        'max_reintentos_polling' => env('SII_MAX_REINTENTOS_POLLING', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Politica de Reintentos
    |--------------------------------------------------------------------------
    | Backoff escalonado en minutos: 5m, 15m, 1h, 4h, 12h. Cada nivel se
    | considera un intento. Tras superar max_attempts, el DTE queda en
    | estado de revision manual.
    */
    'retry' => [
        'max_attempts' => 5,
        'backoff_minutes' => [5, 15, 60, 240, 720],
    ],

];
