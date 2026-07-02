<?php

namespace App\Domains\Core\Services;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Facades\DB;
use Exception;

class AnulacionService
{
    public function buscarDocumento(int $empresaId, string $tipo, string $numero)
    {
        $tipoStr = strtoupper($tipo);

        if ($tipoStr === 'ASIENTO' || $tipoStr === 'COMPROBANTE') {
            $asiento = AsientoContable::where('empresa_id', $empresaId)
                ->where('numero_comprobante', $numero)
                ->with('detalles.cuenta')
                ->first();

            if (!$asiento) {
                throw new Exception("No se encontró el asiento contable N° {$numero}");
            }

            $total = 0;
            foreach ($asiento->detalles as $det) {
                /** @var \App\Domains\Contabilidad\Models\DetalleAsiento $det */
                if ($det->tipo_operacion === 'DEBE') {
                    $total += $det->debe;
                }
            }

            return [
                'id' => $asiento->id,
                'tipo' => 'ASIENTO',
                'numero' => $asiento->numero_comprobante,
                'fecha' => $asiento->fecha,
                'estado' => $asiento->estado,
                'descripcion' => $asiento->glosa,
                'total' => $total,
                'detalles' => $asiento->detalles
            ];
        }

        throw new Exception("El tipo de documento '{$tipo}' no está soportado en este módulo aún.");
    }

    public function anularDocumento(int $empresaId, string $tipo, int $id, string $motivo, int $usuarioId, string $fechaAnulacion)
    {
        $tipoStr = strtoupper($tipo);

        if ($tipoStr === 'ASIENTO' || $tipoStr === 'COMPROBANTE') {
            return DB::transaction(function () use ($empresaId, $id, $motivo, $usuarioId, $fechaAnulacion) {
                $asientoOriginal = AsientoContable::with('detalles')->where('empresa_id', $empresaId)->find($id);

                if (!$asientoOriginal)
                    throw new Exception("Asiento no encontrado.");
                if (in_array($asientoOriginal->estado, ['ANULADO', 'RECLASIFICADO'])) {
                    throw new Exception("Este asiento ya se encontraba anulado o procesado internamente.");
                }

                $asientoOriginal->update(['estado' => 'ANULADO']);

                $tempNum = 'T' . time() . rand(10, 99);

                $asientoReverso = AsientoContable::create([
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => $fechaAnulacion,
                    'glosa' => "REVERSO N° {$asientoOriginal->numero_comprobante} | Motivo: {$motivo}",
                    'tipo_asiento' => $asientoOriginal->tipo_asiento,
                    'estado' => 'ANULADO',
                    'numero_comprobante' => $tempNum,
                    'origen_modulo' => $asientoOriginal->origen_modulo,
                    'origen_id' => $asientoOriginal->origen_id,
                ]);

                $anio = date('y', strtotime($asientoReverso->fecha));
                $tipoCode = '10';
                $secuencia = str_pad((string) $asientoReverso->id, 6, '0', STR_PAD_LEFT);
                $asientoReverso->update(['numero_comprobante' => $anio . $tipoCode . $secuencia]);

                foreach ($asientoOriginal->detalles as $det) {
                    /** @var \App\Domains\Contabilidad\Models\DetalleAsiento $det */
                    $asientoReverso->detalles()->create([
                        'cuenta_contable' => $det->cuenta_contable,
                        'debe' => $det->haber,
                        'haber' => $det->debe,
                        'fecha' => $fechaAnulacion,
                        'tipo_operacion' => $det->tipo_operacion === 'DEBE' ? 'HABER' : 'DEBE'
                    ]);
                }

                // Si este era el asiento de pago de una o más facturas (Nómina de Pagos,
                // Mesa de Conciliación), revierte su estado a REGISTRADA para que puedan
                // volver a pagarse. Sin esto la factura quedaba "PAGADA" para siempre
                // aunque el asiento de pago ya estuviera anulado.
                Factura::where('empresa_id', $empresaId)
                    ->where('asiento_pago_id', $asientoOriginal->id)
                    ->update(['estado' => 'REGISTRADA', 'asiento_pago_id' => null]);

                return [
                    'nuevo_asiento_id' => $asientoReverso->numero_comprobante
                ];
            });
        }

        throw new Exception("Acción de anulación no soportada para este documento.");
    }
}