<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Tesoreria\Models\CatalogoBanco;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use Exception;

class BancoService
{
    protected $asientoService;

    public function __construct(AsientoContableService $asientoService)
    {
        $this->asientoService = $asientoService;
    }

    public function obtenerCatalogo()
    {
        return CatalogoBanco::orderBy('nombre')->get();
    }

    public function obtenerCuentasPorEmpresa(int $empresaId)
    {
        return CuentaBancariaEmpresa::where('empresa_id', $empresaId)->get();
    }

    public function registrarCuentaPropia(array $datos): CuentaBancariaEmpresa
    {
        $existe = CuentaBancariaEmpresa::where('empresa_id', $datos['empresa_id'])
            ->where('banco', $datos['banco'])
            ->where('numero_cuenta', $datos['numero_cuenta'])
            ->exists();

        if ($existe) {
            throw new Exception("Esta cuenta bancaria ya se encuentra registrada para su empresa.");
        }

        return CuentaBancariaEmpresa::create($datos);
    }

    public function pagarNominaMasiva($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $facturasIds, $cuentaBancariaId) {
            
            $facturas = Factura::where('empresa_id', $empresaId)
                ->whereIn('id', $facturasIds)
                ->get();

            if ($facturas->isEmpty()) {
                throw new Exception("No se encontraron facturas pendientes.");
            }

            $totalNomina = 0;
            $numerosFacturas = [];
            $fechaHoy = now()->format('Y-m-d');

            foreach ($facturas as $factura) {
                /** @var Factura $factura */
                $factura->estado = 'PAGADA'; 
                // Se eliminó la línea de fecha_pago para evitar el error SQL
                $factura->save();

                $totalNomina += $factura->monto_bruto;
                $numerosFacturas[] = $factura->numero_factura;
            }

            $cuentaEmpresa = CuentaBancariaEmpresa::find($cuentaBancariaId);
            // Si la cuenta empresa no tiene una cuenta contable asociada, usamos la 110201 (Banco)
            $cuentaContableBanco = $cuentaEmpresa->cuenta_contable_id ?? 110201; 

            $datosAsiento = [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'fecha'      => $fechaHoy,
                'glosa'      => "Pago masivo nómina facturas: " . implode(', ', $numerosFacturas),
                'tipo'       => 'EGRESO',
                'detalles'   => [
                    [
                        'cuenta_contable' => 352130, // DEBE: Rebaja la cuenta puente de pasivo
                        'debe'            => $totalNomina,
                        'haber'           => 0,
                        'fecha'           => $fechaHoy,
                        'tipo_operacion'  => 'DEBE'
                    ],
                    [
                        'cuenta_contable' => $cuentaContableBanco, // HABER: Salida de dinero del Banco
                        'debe'            => 0,
                        'haber'           => $totalNomina,
                        'fecha'           => $fechaHoy,
                        'tipo_operacion'  => 'HABER'
                    ]
                ]
            ];

            $asiento = $this->asientoService->crearAsientoManual($datosAsiento);

            return [
                'success'    => true,
                'mensaje'    => 'Nómina pagada y facturas actualizadas correctamente.',
                'asiento_id' => $asiento->numero_comprobante,
                'total'      => $totalNomina
            ];
        });
    }
}