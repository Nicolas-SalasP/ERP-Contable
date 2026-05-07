<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use Exception;

class ConciliacionService
{
    protected $asientoService;

    public function __construct(AsientoContableService $asientoService)
    {
        $this->asientoService = $asientoService;
    }

    public function conciliarPagoFacturaCompra(array $datos): Factura
    {
        return DB::transaction(function () use ($datos) {
            $factura = Factura::lockForUpdate()->where('empresa_id', $datos['empresa_id'])->findOrFail($datos['factura_id']);
            if ($factura->estado === 'PAGADA')
                throw new Exception("La factura {$factura->numero_factura} ya está pagada.");
            $factura->update(['estado' => 'PAGADA']);

            $cuentaProveedores = '352105';
            $cuentaBanco = '110101';
            $glosa = "Pago Factura N° {$factura->numero_factura} a Proveedor";

            $this->asientoService->registrarAsiento([
                'empresa_id' => $factura->empresa_id,
                'fecha' => $datos['fecha_pago'],
                'glosa' => $glosa,
                'tipo_asiento' => 'egreso',
                'origen_modulo' => 'tesoreria',
                'origen_id' => $factura->id,
            ], [
                ['cuenta_contable' => $cuentaProveedores, 'debe' => $factura->monto_bruto, 'haber' => 0],
                ['cuenta_contable' => $cuentaBanco, 'debe' => 0, 'haber' => $factura->monto_bruto]
            ]);

            return $factura;
        });
    }

    public function obtenerMovimientosPendientes(int $empresaId, int $cuentaBancariaId)
    {
        return DB::table('movimientos_bancarios')
            ->where('empresa_id', $empresaId)
            ->where('cuenta_bancaria_id', $cuentaBancariaId)
            ->where('estado', 'PENDIENTE')
            ->orderBy('fecha', 'asc')
            ->get();
    }

    public function obtenerAnticiposPendientes(int $empresaId)
    {
        return DB::table('anticipos_proveedores')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'PENDIENTE')
            ->get();
    }

    public function conciliarDirecto(int $empresaId, array $datos, int $usuarioId)
    {
        return DB::transaction(function () use ($empresaId, $datos, $usuarioId) {
            $movimiento = DB::table('movimientos_bancarios')->where('empresa_id', $empresaId)->where('id', $datos['movimiento_id'])->first();
            if (!$movimiento)
                throw new Exception("Movimiento bancario no encontrado.");

            $esIngreso = $movimiento->abono > 0;
            $monto = $esIngreso ? $movimiento->abono : $movimiento->cargo;

            $cuentaBanco = DB::table('cuentas_bancarias_empresa')->where('id', $movimiento->cuenta_bancaria_id)->value('cuenta_contable') ?? '110101';

            $detalles = [];
            if ($esIngreso) {
                $detalles[] = ['cuenta_contable' => $cuentaBanco, 'debe' => $monto, 'haber' => 0];
                $detalles[] = ['cuenta_contable' => $datos['cuenta_codigo'], 'debe' => 0, 'haber' => $monto];
            } else {
                $detalles[] = ['cuenta_contable' => $datos['cuenta_codigo'], 'debe' => $monto, 'haber' => 0];
                $detalles[] = ['cuenta_contable' => $cuentaBanco, 'debe' => 0, 'haber' => $monto];
            }

            $asiento = $this->asientoService->registrarAsiento([
                'empresa_id' => $empresaId,
                'fecha' => $movimiento->fecha,
                'glosa' => $datos['glosa'],
                'tipo_asiento' => $esIngreso ? 'ingreso' : 'egreso',
                'origen_modulo' => 'tesoreria',
                'origen_id' => $movimiento->id,
                'usuario_id' => $usuarioId
            ], $detalles);

            DB::table('movimientos_bancarios')->where('id', $movimiento->id)->update(['estado' => 'CONCILIADO', 'asiento_id' => $asiento->id]);

            return $asiento;
        });
    }

    public function conciliarAnticipo(int $empresaId, array $datos, int $usuarioId)
    {
        return DB::transaction(function () use ($empresaId, $datos) {
            $mov = DB::table('movimientos_bancarios')->where('empresa_id', $empresaId)->where('id', $datos['movimiento_id'])->first();
            if (!$mov)
                throw new Exception("Movimiento bancario no encontrado.");

            DB::table('movimientos_bancarios')->where('id', $mov->id)->update(['estado' => 'CONCILIADO_ANTICIPO']);
            DB::table('anticipos_proveedores')->where('id', $datos['anticipo_id'])->update(['estado' => 'PAGADO', 'movimiento_id' => $mov->id]);

            return true;
        });
    }
}