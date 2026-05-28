<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Models\SiiEnvioDte;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use App\Domains\Sii\Services\Envio\EnvioSiiService;
use App\Domains\Sii\Services\Polling\PollearEstadoSiiService;
use App\Domains\Sii\Services\Ws\SiiTokenService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * F5.4 — Comando demo del flujo completo SII (BORRADOR → resolucion terminal).
 *
 * Encadena los servicios existentes en orden para producir output legible
 * paso-a-paso. NO implementa logica de negocio propia; es solo una fachada
 * pensada para demo, debug operativo y validacion manual.
 *
 * SEGURIDAD: por default usa Http::fake() para NO tocar el SII real. El
 * flag --no-fake habilita peticiones reales y requiere confirmacion
 * interactiva (no es default).
 */
class FlujoCompletoPruebaCommand extends Command
{
    protected $signature = 'sii:flujo-completo-prueba
                            {empresa_id : ID de la empresa emisora}
                            {--tipo=33 : Tipo DTE (33|34|46|52|56|61)}
                            {--escenario=aceptado : aceptado|con-reparos|rechazado|procesando}
                            {--no-fake : Usar el SII real en lugar de Http::fake (PELIGROSO)}';

    protected $description = 'Demo del flujo completo de emision SII paso-a-paso: BORRADOR → FIRMADO → ENVIADO_SII → estado terminal.';

    /** Mapeo escenario → codigo SII simulado. */
    private const CODIGOS_SIMULADOS = [
        'aceptado'    => 'EOK',
        'con-reparos' => 'LOC',
        'rechazado'   => 'RCH',
        'procesando'  => 'EPR',
    ];

    public function handle(
        EmitirDteService $emitir,
        EnvioSiiService $enviar,
        PollearEstadoSiiService $pollear,
        SiiTokenService $tokenService
    ): int {
        $empresaId = (int) $this->argument('empresa_id');
        $tipoDte   = (int) $this->option('tipo');
        $escenario = (string) $this->option('escenario');
        $noFake    = (bool) $this->option('no-fake');

        if (! array_key_exists($escenario, self::CODIGOS_SIMULADOS)) {
            $this->error("Escenario invalido: {$escenario}. Use uno de: " . implode('|', array_keys(self::CODIGOS_SIMULADOS)));
            return self::FAILURE;
        }

        try {
            /** @var Empresa $empresa */
            $empresa = Empresa::findOrFail($empresaId);
        } catch (ModelNotFoundException) {
            $this->error("Empresa con ID {$empresaId} no encontrada.");
            return self::FAILURE;
        }

        if ($noFake) {
            $this->warn('*** --no-fake activo: las peticiones iran al SII REAL ***');
            if (! $this->confirm('Confirma que quieres pegar al SII real ahora?', false)) {
                $this->info('Cancelado por el usuario.');
                return self::FAILURE;
            }
        } else {
            $this->configurarHttpFake($escenario);
        }

        $inicio = microtime(true);

        $this->printHeader($empresa, $escenario, $noFake);

        // --- Paso 1: crear DTE en BORRADOR ---
        try {
            $dte = $this->crearDteFixture($empresa, $tipoDte);
        } catch (Throwable $e) {
            $this->error('No se pudo crear el DTE fixture: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->printPaso1($dte);

        // --- Paso 2: emitir (F4.4) ---
        try {
            $dte = $emitir->emitir($dte->id);
        } catch (Throwable $e) {
            $this->error('Paso 2 (emitir) fallo: ' . $e->getMessage());
            $this->printResumenParcial($dte->fresh(), null);
            return self::FAILURE;
        }
        $this->printPaso2($dte);

        // --- Paso 3: token (F5.1) ---
        try {
            $sesion = $tokenService->obtenerSesionActiva($empresa);
        } catch (Throwable $e) {
            $this->error('Paso 3 (token) fallo: ' . $e->getMessage());
            $this->printResumenParcial($dte->fresh(), null);
            return self::FAILURE;
        }
        $this->printPaso3($sesion);

        // --- Paso 4: enviar (F5.2) ---
        try {
            $envio = $enviar->enviar($dte->id);
        } catch (Throwable $e) {
            $this->error('Paso 4 (enviar) fallo: ' . $e->getMessage());
            $this->printResumenParcial($dte->fresh(), null);
            return self::FAILURE;
        }
        $this->printPaso4($envio, $dte->fresh());

        // --- Paso 5: pollear inmediato ignorando backoff (demo) ---
        try {
            $envio = $pollear->pollear($envio->fresh());
        } catch (Throwable $e) {
            $this->error('Paso 5 (polling) fallo: ' . $e->getMessage());
            $this->printResumenParcial($dte->fresh(), $envio->fresh());
            return self::FAILURE;
        }
        $this->printPaso5($envio, $dte->fresh());

        $segundos = round(microtime(true) - $inicio, 2);
        $this->printResumen($dte->fresh(['eventos']), $envio->fresh(['eventos']), $segundos);

        return self::SUCCESS;
    }

    private function configurarHttpFake(string $escenario): void
    {
        $codigoSii = self::CODIGOS_SIMULADOS[$escenario];
        $glosa     = match ($escenario) {
            'aceptado'    => 'Envio Aceptado',
            'con-reparos' => 'Aceptado con reparos menores',
            'rechazado'   => 'Envio Rechazado',
            'procesando'  => 'En Proceso',
        };

        Http::fake([
            '*/DTEWS/CrSeed*'           => Http::response($this->envSeed('123456789'), 200),
            '*/DTEWS/GetTokenFromSeed*' => Http::response($this->envToken('TOK_DEMO'), 200),
            '*/cgi_dte/UPL/DTEUpload*'  => Http::response("RECIBIDO\nTRACKID: 99887766\nERROR: 0\nGLOSA: OK", 200),
            '*/DTEWS/QueryEstUp*'       => Http::response($this->envEstUp($codigoSii, $glosa), 200),
        ]);
    }

    private function crearDteFixture(Empresa $empresa, int $tipoDte): SiiDteEmitido
    {
        return DB::transaction(function () use ($empresa, $tipoDte) {
            $neto  = 1000;
            $iva   = 190;
            $total = 1190;
            $dte = SiiDteEmitido::create([
                'empresa_id'           => $empresa->id,
                'tipo_dte'             => $tipoDte,
                'folio'                => random_int(900_000, 999_999),
                'fecha_emision'        => now()->toDateString(),
                'emisor_rut'           => $empresa->rut,
                'emisor_razon_social'  => $empresa->razon_social,
                'emisor_giro'          => $empresa->giro_emisor ?? 'Servicios',
                'emisor_acteco'        => $empresa->codigo_actividad_sii ?? 471910,
                'emisor_direccion'     => $empresa->direccion ?? 'Sin direccion',
                'emisor_comuna'        => $empresa->comuna ?? 'Santiago',
                'receptor_rut'         => '66666666-6',
                'receptor_razon_social' => 'CLIENTE DEMO',
                'moneda'               => 'CLP',
                'monto_neto'           => $neto,
                'monto_exento'         => 0,
                'tasa_iva'             => 19.00,
                'iva'                  => $iva,
                'monto_total'          => $total,
                'estado'               => SiiDteEmitido::ESTADO_BORRADOR,
                'es_cedible'           => true,
            ]);
            SiiDteEmitidoDetalle::create([
                'dte_emitido_id'  => $dte->id,
                'numero_linea'    => 1,
                'nombre_item'     => 'Servicio demo',
                'cantidad'        => 1,
                'precio_unitario' => $neto,
                'monto_item'      => $neto,
            ]);
            return $dte;
        });
    }

    private function printHeader(Empresa $empresa, string $escenario, bool $noFake): void
    {
        $this->info('=== Flujo completo de emision SII ===');
        $this->line('Empresa   : ' . $empresa->razon_social . ' (' . $empresa->rut . ')');
        $this->line('Ambiente  : ' . ($empresa->ambiente_sii ?? '[no configurado]'));
        $this->line('Escenario : ' . $escenario);
        $this->line('HTTP fake : ' . ($noFake ? 'NO (SII real)' : 'SI'));
        $this->line('');
    }

    private function printPaso1(SiiDteEmitido $dte): void
    {
        $this->line('--- Paso 1: Crear DTE en BORRADOR ---');
        $this->line('  DTE ID : ' . $dte->id);
        $this->line('  Tipo   : ' . $dte->tipo_dte . ' (' . SiiDteEmitido::nombreTipo((int) $dte->tipo_dte) . ')');
        $this->line('  Total  : $' . number_format((float) $dte->monto_total, 0, ',', '.'));
        $this->line('');
    }

    private function printPaso2(SiiDteEmitido $dte): void
    {
        $this->line('--- Paso 2: Firmar y persistir (F4.4) ---');
        $this->line('  Estado          : ' . $dte->estado);
        $this->line('  Folio asignado  : ' . $dte->folio);
        $this->line('  CAF usado       : ' . $dte->caf_id);
        $this->line('  XML path        : ' . $dte->xml_path);
        $this->line('  Hash SHA256     : ' . substr((string) $dte->xml_hash_sha256, 0, 16) . '...');
        $this->line('  Evento          : BORRADOR -> FIRMADO');
        $this->line('');
    }

    private function printPaso3($sesion): void
    {
        $this->line('--- Paso 3: Autenticar con SII (F5.1) ---');
        $this->line('  Token        : ' . substr((string) $sesion->token, 0, 12) . '...');
        $this->line('  Reuso        : ' . ($sesion->intentos_uso > 1 ? 'SI (cache activo)' : 'NUEVA SESION'));
        $this->line('  Expira       : ' . $sesion->fecha_expiracion->toIso8601String() . ' (' . (int) max(0, now()->diffInMinutes($sesion->fecha_expiracion, false)) . ' min)');
        $this->line('');
    }

    private function printPaso4(SiiEnvioDte $envio, SiiDteEmitido $dte): void
    {
        $this->line('--- Paso 4: Enviar al SII (F5.2) ---');
        $this->line('  Estado envio    : ' . $envio->estado_envio);
        $this->line('  Track ID        : ' . ($envio->track_id ?? '[N/A]'));
        $this->line('  HTTP status     : ' . ($envio->http_status_ultimo_envio ?? '[N/A]'));
        $this->line('  Estado DTE      : FIRMADO -> ' . $dte->estado);
        $this->line('  Evento creado.');
        $this->line('');
    }

    private function printPaso5(SiiEnvioDte $envio, SiiDteEmitido $dte): void
    {
        $this->line('--- Paso 5: Polling de estado (F5.3) ---');
        $this->line('  Codigo SII      : ' . ($envio->estado_sii_ultimo ?? '[N/A]'));
        $this->line('  Estado envio    : ENVIADO -> ' . $envio->estado_envio);
        $this->line('  Estado DTE      : ENVIADO_SII -> ' . $dte->estado);
        if ($envio->glosa_sii) {
            $this->line('  Glosa SII       : ' . $envio->glosa_sii);
        }
        $this->line('');
    }

    private function printResumen(SiiDteEmitido $dte, SiiEnvioDte $envio, float $segundos): void
    {
        $this->info('=== Resumen ===');
        $this->line('Estado final DTE       : ' . $dte->estado);
        $this->line('Estado final envio     : ' . $envio->estado_envio);
        $this->line('Eventos del DTE        : ' . $dte->eventos->count());
        foreach ($dte->eventos as $ev) {
            $this->line('  ' . ($ev->estado_anterior ?? 'NULL') . ' -> ' . $ev->estado_nuevo . ' @ ' . $ev->created_at->format('H:i:s'));
        }
        $this->line('Eventos del envio      : ' . $envio->eventos->count());
        foreach ($envio->eventos as $ev) {
            $code = $ev->codigo_sii_raw ? " [code={$ev->codigo_sii_raw}]" : '';
            $this->line('  ' . ($ev->estado_anterior ?? 'NULL') . ' -> ' . $ev->estado_nuevo . $code);
        }
        $this->line('Tiempo total           : ' . $segundos . 's');
    }

    private function printResumenParcial(?SiiDteEmitido $dte, ?SiiEnvioDte $envio): void
    {
        $this->warn('--- Resumen parcial (flujo aborto) ---');
        if ($dte) {
            $this->line('Estado DTE   : ' . $dte->estado);
        }
        if ($envio) {
            $this->line('Estado envio : ' . $envio->estado_envio);
        }
    }

    // ----- envelopes helpers -----

    private function envSeed(string $semilla): string
    {
        $cdata = "<SII:RESPUESTA xmlns:SII=\"http://www.sii.cl/XMLSchema\"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><SEMILLA>{$semilla}</SEMILLA></SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getSeedResponse>'
            . "<getSeedReturn><![CDATA[{$cdata}]]></getSeedReturn></getSeedResponse></soapenv:Body></soapenv:Envelope>";
    }

    private function envToken(string $token): string
    {
        $cdata = "<SII:RESPUESTA xmlns:SII=\"http://www.sii.cl/XMLSchema\"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><TOKEN>{$token}</TOKEN></SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getTokenResponse>'
            . "<getTokenReturn><![CDATA[{$cdata}]]></getTokenReturn></getTokenResponse></soapenv:Body></soapenv:Envelope>";
    }

    private function envEstUp(string $codigo, string $glosa): string
    {
        $cdata = "<SII:RESPUESTA xmlns:SII=\"http://www.sii.cl/XMLSchema\"><SII:RESP_HDR><ESTADO>00</ESTADO></SII:RESP_HDR><SII:RESP_BODY><ESTADO>{$codigo}</ESTADO><GLOSA>{$glosa}</GLOSA></SII:RESP_BODY></SII:RESPUESTA>";
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body><getEstUpResponse>'
            . "<getEstUpReturn><![CDATA[{$cdata}]]></getEstUpReturn></getEstUpResponse></soapenv:Body></soapenv:Envelope>";
    }
}
