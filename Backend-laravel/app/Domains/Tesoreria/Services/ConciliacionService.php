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

            $factura = Factura::lockForUpdate()
                ->where('empresa_id', $datos['empresa_id'])
                ->findOrFail($datos['factura_id']);

            if ($factura->estado === 'PAGADA') {
                throw new Exception("La factura {$factura->numero_factura} ya se encuentra pagada.");
            }
            $factura->update([
                'estado' => 'PAGADA'
            ]);

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
                [
                    'cuenta_contable' => $cuentaProveedores,
                    'debe' => $factura->monto_bruto,
                    'haber' => 0
                ],
                [
                    'cuenta_contable' => $cuentaBanco,
                    'debe' => 0,
                    'haber' => $factura->monto_bruto
                ]
            ]);

            return $factura;
        });
    }
}