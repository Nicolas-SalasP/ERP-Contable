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
            'semilla'       => 'https://maullin.sii.cl/DTEWS/CrSeed.jws',
            'token'         => 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws',
            'upload'        => 'https://maullin.sii.cl/cgi_dte/UPL/DTEUpload',
            'estado_envio'  => 'https://maullin.sii.cl/DTEWS/QueryEstUp.jws',
            'estado_dte'    => 'https://maullin.sii.cl/DTEWS/QueryEstDte.jws',
            'boleta_envio'  => 'https://maullin.sii.cl/recursos/v1/boleta.electronica.envio',
        ],
        'produccion' => [
            'semilla'       => 'https://palena.sii.cl/DTEWS/CrSeed.jws',
            'token'         => 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws',
            'upload'        => 'https://palena.sii.cl/cgi_dte/UPL/DTEUpload',
            'estado_envio'  => 'https://palena.sii.cl/DTEWS/QueryEstUp.jws',
            'estado_dte'    => 'https://palena.sii.cl/DTEWS/QueryEstDte.jws',
            'boleta_envio'  => 'https://palena.sii.cl/recursos/v1/boleta.electronica.envio',
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
        'algoritmo_digest'    => 'http://www.w3.org/2000/09/xmldsig#sha1',
        'canonicalization'    => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
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
        'disk'        => env('SII_DISK', 'local'),
        'path_prefix' => 'sii',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts HTTP (segundos)
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'connect' => 10,
        'read'    => 30,
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
        'max_attempts'    => 5,
        'backoff_minutes' => [5, 15, 60, 240, 720],
    ],

];
