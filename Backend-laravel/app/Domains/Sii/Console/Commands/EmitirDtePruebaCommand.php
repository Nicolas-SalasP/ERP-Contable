<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Exceptions\CertificadoInvalidoException;
use App\Domains\Sii\Exceptions\DteEstadoInvalidoException;
use App\Domains\Sii\Exceptions\DteIncompletoException;
use App\Domains\Sii\Exceptions\DteXmlInvalidException;
use App\Domains\Sii\Exceptions\SinFoliosDisponiblesException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiDteEmitidoDetalle;
use App\Domains\Sii\Services\Emision\EmitirDteService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comando de validacion manual para el flujo completo de emision F4.4.
 *
 * Crea un SiiDteEmitido en BORRADOR con datos minimos fixture (receptor
 * generico 66666666-6, un detalle "Servicio de prueba"), invoca el
 * orquestador y reporta resultado. Es la contraparte CLI de los smoke
 * tests del DoD: util para validar manualmente que el flujo emite y
 * persiste en disco sin tener que escribir un tinker script.
 */
class EmitirDtePruebaCommand extends Command
{
    protected $signature = 'sii:emitir-dte-prueba
                            {empresa_id : ID de la empresa emisora}
                            {--tipo=33 : Tipo DTE (33, 34, 39, 41, 46, 52, 56, 61)}';

    protected $description = 'Crea un DTE fixture en BORRADOR para la empresa dada y lo emite via EmitirDteService.';

    public function handle(EmitirDteService $service): int
    {
        $empresaId = (int) $this->argument('empresa_id');
        $tipoDte   = (int) $this->option('tipo');

        try {
            /** @var Empresa $empresa */
            $empresa = Empresa::findOrFail($empresaId);
        } catch (ModelNotFoundException) {
            $this->error("Empresa con ID {$empresaId} no encontrada.");
            return self::FAILURE;
        }

        try {
            $dte = $this->crearDteFixture($empresa, $tipoDte);
        } catch (Throwable $e) {
            $this->error('No se pudo crear el DTE fixture: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $firmado = $service->emitir($dte->id);
        } catch (DteEstadoInvalidoException $e) {
            $this->error('Estado invalido: ' . $e->getMessage());
            return self::FAILURE;
        } catch (DteIncompletoException $e) {
            $this->error('DTE incompleto: ' . $e->getMessage());
            return self::FAILURE;
        } catch (CertificadoInvalidoException $e) {
            $this->error('Certificado: ' . $e->getMessage());
            return self::FAILURE;
        } catch (SinFoliosDisponiblesException $e) {
            $this->error('Sin folios CAF: ' . $e->getMessage());
            return self::FAILURE;
        } catch (CafInvalidoException $e) {
            $this->error('CAF invalido: ' . $e->getMessage());
            return self::FAILURE;
        } catch (DteXmlInvalidException $e) {
            $this->error('XML invalido contra XSD: ' . $e->getMessage());
            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Error inesperado: ' . $e::class . ' — ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('DTE emitido exitosamente en estado FIRMADO.');
        $this->line('  dte_id          : ' . $firmado->id);
        $this->line('  tipo_dte        : ' . $firmado->tipo_dte);
        $this->line('  folio asignado  : ' . $firmado->folio);
        $this->line('  caf_id          : ' . $firmado->caf_id);
        $this->line('  estado          : ' . $firmado->estado);
        $this->line('  fecha_firma     : ' . $firmado->fecha_firma?->toIso8601String());
        $this->line('  xml_path        : ' . $firmado->xml_path);
        $this->line('  xml_hash_sha256 : ' . $firmado->xml_hash_sha256);

        return self::SUCCESS;
    }

    /**
     * Crea un DTE en BORRADOR con datos minimos para que pase XSD: snapshot
     * del emisor desde la empresa, receptor generico 66666666-6 y un detalle
     * "Servicio de prueba" a CLP 1000.
     */
    private function crearDteFixture(Empresa $empresa, int $tipoDte): SiiDteEmitido
    {
        return DB::transaction(function () use ($empresa, $tipoDte) {
            $neto  = 1000;
            $iva   = (int) round($neto * 0.19);
            $total = $neto + $iva;

            // Folio placeholder alto para no colisionar con el unique
            // (empresa_id, tipo_dte, folio) cuando el CAF reasigne un folio
            // bajo del rango. EmitirDteService::emitir lo sobreescribe.
            $folioPlaceholder = random_int(900_000, 999_999);

            $dte = SiiDteEmitido::create([
                'empresa_id'           => $empresa->id,
                'tipo_dte'             => $tipoDte,
                'folio'                => $folioPlaceholder,
                'fecha_emision'        => now()->toDateString(),
                'emisor_rut'           => $empresa->rut,
                'emisor_razon_social'  => $empresa->razon_social,
                'emisor_giro'          => $empresa->giro_emisor ?? 'Servicios',
                'emisor_acteco'        => $empresa->codigo_actividad_sii ?? 471910,
                'emisor_direccion'     => $empresa->direccion ?? 'Sin direccion',
                'emisor_comuna'        => $empresa->comuna ?? 'Santiago',
                'emisor_ciudad'        => $empresa->ciudad ?? 'Santiago',
                'receptor_rut'         => '66666666-6',
                'receptor_razon_social' => 'CLIENTE DE PRUEBA',
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
                'nombre_item'     => 'Servicio de prueba',
                'cantidad'        => 1,
                'precio_unitario' => $neto,
                'monto_item'      => $neto,
            ]);

            return $dte;
        });
    }
}
