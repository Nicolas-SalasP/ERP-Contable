<?php

namespace App\Domains\Core\Services;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Services\ContadorEmpresaService;
use Illuminate\Support\Facades\DB;
use Exception;

class AnulacionService
{
    public function __construct(private ContadorEmpresaService $contadorService)
    {
    }

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
                // lockForUpdate(): evita que dos anulaciones concurrentes (doble clic) generen dos asientos de reverso duplicados.
                $asientoOriginal = AsientoContable::with('detalles')
                    ->where('empresa_id', $empresaId)
                    ->lockForUpdate()
                    ->find($id);

                if (!$asientoOriginal)
                    throw new Exception("Asiento no encontrado.");
                if (in_array($asientoOriginal->estado, ['ANULADO', 'RECLASIFICADO'])) {
                    throw new Exception("Este asiento ya se encontraba anulado o procesado internamente.");
                }

                $asientoOriginal->update(['estado' => 'ANULADO']);

                $tempNum = 'T' . time() . rand(10, 99);

                // El reverso queda MAYORIZADO (no ANULADO): antes quedaba ANULADO y ReporteContableService::aplicarFiltroEstado lo excluía, invisible en Libro Diario/Mayor.
                $asientoReverso = AsientoContable::create([
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => $fechaAnulacion,
                    'glosa' => "REVERSO N° {$asientoOriginal->numero_comprobante} | Motivo: {$motivo}",
                    'tipo_asiento' => $asientoOriginal->tipo_asiento,
                    'estado' => 'MAYORIZADO',
                    'numero_comprobante' => $tempNum,
                    'origen_modulo' => $asientoOriginal->origen_modulo,
                    'origen_id' => $asientoOriginal->origen_id,
                ]);

                // Antes usaba el id de la fila como secuencia -- desincronizado del contador
                // real (ContadorEmpresaService), lo que producía colisiones con números
                // generados después por registrarAsiento/generarNumeroComprobante.
                $anio = date('y', strtotime($asientoReverso->fecha));
                $tipoCode = '10';
                $correlativo = $this->contadorService->siguienteNumero($empresaId, 'asiento_comprobante');
                $secuencia = str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
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

                // Si era el asiento de pago de facturas, revierte su estado a REGISTRADA para que puedan volver a pagarse (si no, quedaban "PAGADA" para siempre).
                Factura::where('empresa_id', $empresaId)
                    ->where('asiento_pago_id', $asientoOriginal->id)
                    ->update(['estado' => 'REGISTRADA', 'asiento_pago_id' => null]);

                // Si venia de conciliar un movimiento bancario, lo libera a PENDIENTE (si no, quedaba CONCILIADO apuntando a un asiento ya ANULADO, sin forma de reprocesar).
                DB::table('movimientos_bancarios')
                    ->where('empresa_id', $empresaId)
                    ->where('asiento_id', $asientoOriginal->id)
                    ->update(['estado' => 'PENDIENTE', 'asiento_id' => null]);

                // Si el asiento generó un anticipo autogenerado (sobrepago en Mesa de
                // Conciliación), lo anula: sin esto quedaba PAGADO para siempre con el
                // movimiento ya liberado arriba, permitiendo re-conciliarlo por duplicado.
                foreach (['anticipos_proveedores', 'anticipos_clientes'] as $tablaAnticipo) {
                    DB::table($tablaAnticipo)
                        ->where('empresa_id', $empresaId)
                        ->where('asiento_id', $asientoOriginal->id)
                        ->update(['estado' => 'ANULADO', 'asiento_id' => null, 'movimiento_id' => null]);
                }

                // Si era el asiento de centralizacion de remuneraciones, limpia comprobante_contable
                // en las liquidaciones que lo referenciaban -- sin esto quedaban apuntando a un
                // comprobante ya ANULADO y CentralizacionRemuneracionesService::centralizar (ya
                // corregido para no bloquearse con el reverso) las re-centralizaba sin que la
                // liquidacion mostrara el nuevo comprobante.
                if ($asientoOriginal->origen_modulo === 'rrhh') {
                    DB::table('liquidaciones')
                        ->where('empresa_id', $empresaId)
                        ->where('comprobante_contable', $asientoOriginal->numero_comprobante)
                        ->update(['comprobante_contable' => null]);
                }

                // Si era el asiento de baja de un activo fijo, lo reactiva -- sin esto quedaba
                // DADO_DE_BAJA para siempre pese a que el asiento contable que lo dio de baja
                // ya no existe (fue reversado). REACTIVADO (no ACTIVO) para dejar rastro de que
                // el activo pasó por una baja corregida.
                if ($asientoOriginal->origen_modulo === 'activos' && $asientoOriginal->origen_id) {
                    DB::table('activos_fijos')
                        ->where('empresa_id', $empresaId)
                        ->where('id', $asientoOriginal->origen_id)
                        ->where('estado', 'DADO_DE_BAJA')
                        ->update(['estado' => 'REACTIVADO']);
                }

                return [
                    'nuevo_asiento_id' => $asientoReverso->numero_comprobante
                ];
            });
        }

        throw new Exception("Acción de anulación no soportada para este documento.");
    }
}