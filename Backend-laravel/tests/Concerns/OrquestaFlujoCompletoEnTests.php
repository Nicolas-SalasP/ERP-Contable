<?php

namespace Tests\Concerns;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * F5.4 — Setup completo para tests E2E del flujo SII (BORRADOR → ACEPTADO).
 *
 * Compone GeneraParRsaParaTests + GeneraCertificadoParaTests y agrega:
 *   - setupEmpresaConFlujoCompleto(): empresa + cert + CAF + DTE en BORRADOR.
 *   - fakeRespuestasSiiFlujoCompleto(): Http::fake para los 4 endpoints SII.
 *   - Helpers de envelopes SOAP+CDATA reutilizables.
 *
 * Los tests E2E y el comando demo lo usan.
 */
trait OrquestaFlujoCompletoEnTests
{
    use GeneraCertificadoParaTests;

    /**
     * @return array{
     *   empresa: Empresa,
     *   caf: SiiCaf,
     *   dte: SiiDteEmitido,
     *   cert_pem: string
     * }
     */
    protected function setupEmpresaConFlujoCompleto(array $overrides = []): array
    {
        $defaults = [
            'rut'                   => '76555444-3',
            'razon_social'          => 'EMPRESA E2E',
            'ambiente_sii'          => 'certificacion',
            'resolucion_sii_numero' => 80,
            'resolucion_sii_fecha'  => '2024-08-22',
            'tipo_dte'              => 33,
            'folio_desde'           => 1,
            'folio_hasta'           => 50,
        ];
        $cfg = array_merge($defaults, $overrides);

        $empresa = Empresa::create([
            'rut'                   => $cfg['rut'],
            'razon_social'          => $cfg['razon_social'],
            'giro_emisor'           => 'Servicios',
            'codigo_actividad_sii'  => 471910,
            'direccion'             => 'Calle 1',
            'comuna'                => 'Santiago',
            'ciudad'                => 'Santiago',
            'resolucion_sii_numero' => $cfg['resolucion_sii_numero'],
            'resolucion_sii_fecha'  => $cfg['resolucion_sii_fecha'],
            'ambiente_sii'          => $cfg['ambiente_sii'],
        ]);

        [, $certPem] = $this->crearCertActivoParaEmpresa($empresa, 'TEST E2E ' . $empresa->rut);
        [$caf]       = $this->crearCafActivoParaEmpresa($empresa, $cfg['tipo_dte'], $cfg['folio_desde'], $cfg['folio_hasta']);

        $dte = SiiDteEmitido::create([
            'empresa_id'           => $empresa->id,
            'tipo_dte'             => $cfg['tipo_dte'],
            'folio'                => random_int(900_000, 999_999),
            'fecha_emision'        => now()->toDateString(),
            'emisor_rut'           => $cfg['rut'],
            'emisor_razon_social'  => $cfg['razon_social'],
            'emisor_giro'          => 'Servicios',
            'emisor_acteco'        => 471910,
            'emisor_direccion'     => 'Calle 1',
            'emisor_comuna'        => 'Santiago',
            'receptor_rut'         => '66666666-6',
            'receptor_razon_social' => 'CLIENTE E2E',
            'moneda'               => 'CLP',
            'monto_neto'           => 1000,
            'monto_exento'         => 0,
            'tasa_iva'             => 19.00,
            'iva'                  => 190,
            'monto_total'          => 1190,
            'estado'               => SiiDteEmitido::ESTADO_BORRADOR,
            'es_cedible'           => true,
        ]);
        SiiDteEmitidoDetalle::create([
            'dte_emitido_id'  => $dte->id,
            'numero_linea'    => 1,
            'nombre_item'     => 'Servicio E2E',
            'cantidad'        => 1,
            'precio_unitario' => 1000,
            'monto_item'      => 1000,
        ]);

        return [
            'empresa'  => $empresa->fresh(),
            'caf'      => $caf->fresh(),
            'dte'      => $dte->fresh(),
            'cert_pem' => $certPem,
        ];
    }

    /**
     * Configura Http::fake con los 4 endpoints del flujo SII en una pasada.
     * Reusa Crypt/Storage del entorno actual sin alterarlos.
     *
     * Escenarios:
     *   'aceptado'    → polling devuelve EOK (estado terminal ACEPTADO).
     *   'con-reparos' → polling devuelve LOC (ACEPTADO_CON_REPAROS).
     *   'rechazado'   → polling devuelve RCH (RECHAZADO).
     *   'procesando'  → polling devuelve EPR (sigue en ENVIADO, no transiciona).
     */
    protected function fakeRespuestasSiiFlujoCompleto(string $escenario = 'aceptado', string $trackId = '99887766'): void
    {
        $codigoSii = match ($escenario) {
            'aceptado'    => 'EOK',
            'con-reparos' => 'LOC',
            'rechazado'   => 'RCH',
            'procesando'  => 'EPR',
            default       => throw new \InvalidArgumentException("Escenario invalido: {$escenario}"),
        };

        $glosaEscenario = match ($escenario) {
            'aceptado'    => 'Envio Aceptado',
            'con-reparos' => 'Aceptado con reparos menores',
            'rechazado'   => 'Envio Rechazado',
            'procesando'  => 'En Proceso',
        };

        // Las llamadas sucesivas a Http::fake() MERGEAN matchers. Para evitar
        // que un test reuse responses del fake anterior, primero limpiamos
        // los recorded/stubs con Http::preventStrayRequests() reseteado.
        Http::clearResolvedInstance('http');

        Http::fake([
            '*/DTEWS/CrSeed*'           => Http::response($this->envelopeSemilla('123456789'), 200),
            '*/DTEWS/GetTokenFromSeed*' => Http::response($this->envelopeToken('TOK_E2E_DEMO'), 200),
            '*/cgi_dte/UPL/DTEUpload*'  => Http::response("RECIBIDO\nTRACKID: {$trackId}\nERROR: 0\nGLOSA: OK", 200),
            '*/DTEWS/QueryEstUp*'       => Http::response($this->envelopeEstadoUp($codigoSii, $glosaEscenario), 200),
        ]);
    }

    private function envelopeSemilla(string $semilla): string
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
              . "<SII:RESP_BODY><SEMILLA>{$semilla}</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>";

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Body><getSeedResponse>'
             . "<getSeedReturn><![CDATA[{$cdata}]]></getSeedReturn>"
             . '</getSeedResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function envelopeToken(string $token): string
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
              . "<SII:RESP_BODY><TOKEN>{$token}</TOKEN></SII:RESP_BODY></SII:RESPUESTA>";

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Body><getTokenResponse>'
             . "<getTokenReturn><![CDATA[{$cdata}]]></getTokenReturn>"
             . '</getTokenResponse></soapenv:Body></soapenv:Envelope>';
    }

    private function envelopeEstadoUp(string $codigo, string $glosa): string
    {
        $cdata = '<SII:RESPUESTA xmlns:SII="http://www.sii.cl/XMLSchema">'
              . "<SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR>"
              . "<SII:RESP_BODY><ESTADO>{$codigo}</ESTADO><GLOSA>{$glosa}</GLOSA></SII:RESP_BODY>"
              . '</SII:RESPUESTA>';

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
             . '<soapenv:Body><getEstUpResponse>'
             . "<getEstUpReturn><![CDATA[{$cdata}]]></getEstUpReturn>"
             . '</getEstUpResponse></soapenv:Body></soapenv:Envelope>';
    }
}
