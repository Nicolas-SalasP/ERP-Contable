<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Tesoreria\Services\BancoService;
use Illuminate\Support\Facades\DB;
use Exception;

class ConciliacionService
{
    protected $asientoService;
    protected $facturaService;
    protected $bancoService;

    public function __construct(
        AsientoContableService $asientoService,
        FacturaService $facturaService,
        BancoService $bancoService
    ) {
        $this->asientoService = $asientoService;
        $this->facturaService = $facturaService;
        $this->bancoService = $bancoService;
    }

    public function conciliarPagoFacturaCompra(array $datos)
    {
        return DB::transaction(function () use ($datos) {
            $cuentaBanco = $this->bancoService->obtenerCuentaBancaria($datos['empresa_id'], $datos['cuenta_bancaria_id']);
            $factura = $this->facturaService->obtenerFacturaPorId($datos['empresa_id'], $datos['factura_id']);

            if ($factura->estado === 'PAGADA') {
                throw new Exception("La factura {$factura->numero_factura} ya está pagada.", 422);
            }

            $this->facturaService->cambiarEstado($datos['empresa_id'], $factura->id, 'PAGADA');

            $cuentaProveedores = $datos['cuenta_proveedor'] ?? '352105';
            $codigoCuentaBanco = $cuentaBanco->cuenta_contable_codigo ?? '110101';
            $glosa = "Pago Factura N° {$factura->numero_factura} a Proveedor";

            $this->asientoService->registrarAsiento([
                'empresa_id' => $datos['empresa_id'],
                'fecha' => $datos['fecha_pago'],
                'glosa' => $glosa,
                'tipo_asiento' => 'egreso',
                'origen_modulo' => 'tesoreria',
                'origen_id' => $factura->id,
            ], [
                ['cuenta_contable' => $cuentaProveedores, 'debe' => $factura->monto_bruto, 'haber' => 0],
                ['cuenta_contable' => $codigoCuentaBanco, 'debe' => 0, 'haber' => $factura->monto_bruto]
            ]);

            return $factura;
        });
    }

    public function obtenerMovimientosPendientes(int $empresaId, int $cuentaBancariaId)
    {
        return $this->bancoService->obtenerMovimientosPendientes($empresaId, $cuentaBancariaId);
    }

    public function obtenerAnticiposPendientes(int $empresaId)
    {
        return $this->bancoService->obtenerAnticiposPendientes($empresaId);
    }

    public function obtenerSugerenciasConciliacion(int $empresaId, int $movimientoId)
    {
        $movimiento = $this->bancoService->obtenerMovimiento($empresaId, $movimientoId);
        
        $esIngreso = $movimiento->abono > 0;
        $monto = $esIngreso ? (float) $movimiento->abono : (float) $movimiento->cargo;
        $tipoFactura = $esIngreso ? 'VENTA' : 'COMPRA';

        return $this->facturaService->obtenerFacturasImpagasPorMontoExacto($empresaId, $tipoFactura, $monto);
    }

    public function procesarPagoFacturas(int $empresaId, int $usuarioId, int $movimientoId, array $facturasIds, ?int $entidadId = null)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $movimientoId, $facturasIds, $entidadId) {
            $movimiento = $this->bancoService->obtenerMovimiento($empresaId, $movimientoId);
            
            $facturas = count($facturasIds) > 0 
                ? $this->facturaService->obtenerFacturasPorIds($empresaId, $facturasIds) 
                : collect([]);

            $esIngreso = $movimiento->abono > 0;
            $montoMovimiento = $esIngreso ? (float) $movimiento->abono : (float) $movimiento->cargo;
            $totalFacturas = (float) $facturas->sum('monto_bruto');
            
            $diferencia = $montoMovimiento - $totalFacturas;
            
            $cuentaBanco = $this->bancoService->obtenerCuentaContableDeBanco($empresaId, $movimiento->cuenta_bancaria_id);
            $cuentaContraparte = $esIngreso ? '152005' : '352105';
            $cuentaAnticipo = $esIngreso ? '210205' : '110205';

            $detallesAsiento = [];
            $glosaAsiento = "";

            if ($facturas->count() > 0) {
                $saldoRestante = $montoMovimiento;
                $facturas = $facturas->sortBy('fecha_emision');
                
                foreach ($facturas as $fac) {
                    $montoFactura = (float) $fac->monto_bruto;
                    if ($saldoRestante >= $montoFactura) {
                        $this->facturaService->cambiarEstado($empresaId, $fac->id, 'PAGADA');
                        $saldoRestante -= $montoFactura;
                    } elseif ($saldoRestante > 0) {
                        $this->facturaService->cambiarEstado($empresaId, $fac->id, 'ABONADA');
                        $saldoRestante = 0;
                    } else {
                        break; 
                    }
                }
            }

            if ($esIngreso) {
                $detallesAsiento[] = ['cuenta_contable' => $cuentaBanco, 'debe' => $montoMovimiento, 'haber' => 0, 'glosa_detalle' => 'Ingreso a Banco'];
                
                if ($facturas->count() > 0) {
                    $montoCubre = min($montoMovimiento, $totalFacturas);
                    $detallesAsiento[] = ['cuenta_contable' => $cuentaContraparte, 'debe' => 0, 'haber' => $montoCubre, 'glosa_detalle' => 'Cobro Facturas'];
                    $glosaAsiento = "Cobro Fac. " . $facturas->pluck('numero_factura')->implode(', ');
                }
                
                if ($diferencia > 0) {
                    $detallesAsiento[] = ['cuenta_contable' => $cuentaAnticipo, 'debe' => 0, 'haber' => $diferencia, 'glosa_detalle' => 'Anticipo de Cliente'];
                    $glosaAsiento .= ($glosaAsiento ? " | " : "") . "Anticipo Registrado";
                }
            } else {
                if ($facturas->count() > 0) {
                    $montoCubre = min($montoMovimiento, $totalFacturas);
                    $detallesAsiento[] = ['cuenta_contable' => $cuentaContraparte, 'debe' => $montoCubre, 'haber' => 0, 'glosa_detalle' => 'Pago Facturas'];
                    $glosaAsiento = "Pago Fac. " . $facturas->pluck('numero_factura')->implode(', ');
                }

                if ($diferencia > 0) {
                    $detallesAsiento[] = ['cuenta_contable' => $cuentaAnticipo, 'debe' => $diferencia, 'haber' => 0, 'glosa_detalle' => 'Anticipo a Proveedor'];
                    $glosaAsiento .= ($glosaAsiento ? " | " : "") . "Anticipo Generado";
                    
                    if ($entidadId) {
                        DB::table('anticipos_proveedores')->insert([
                            'empresa_id' => $empresaId,
                            'proveedor_id' => $entidadId,
                            'monto' => $diferencia,
                            'estado' => 'PAGADO',
                            'movimiento_id' => $movimiento->id,
                            'referencia' => 'Autogenerado en Conciliación',
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }

                $detallesAsiento[] = ['cuenta_contable' => $cuentaBanco, 'debe' => 0, 'haber' => $montoMovimiento, 'glosa_detalle' => 'Salida de Banco'];
            }

            if (empty($glosaAsiento)) $glosaAsiento = "Anticipo Directo / Operación Bancaria";

            $asiento = $this->asientoService->registrarAsiento([
                'empresa_id' => $empresaId,
                'fecha' => $movimiento->fecha,
                'glosa' => substr($glosaAsiento, 0, 250),
                'tipo_asiento' => $esIngreso ? 'ingreso' : 'egreso',
                'origen_modulo' => 'tesoreria',
                'origen_id' => $movimiento->id,
                'usuario_id' => $usuarioId
            ], $detallesAsiento);

            $this->bancoService->vincularAsientoAMovimiento($empresaId, $movimiento->id, $asiento->id);
            return $asiento; 
        });
    }

    public function conciliarDirecto(int $empresaId, array $datos, int $usuarioId)
    {
        return DB::transaction(function () use ($empresaId, $datos, $usuarioId) {
            $movimiento = $this->bancoService->obtenerMovimiento($empresaId, $datos['movimiento_id']);
            
            $esIngreso = $movimiento->abono > 0;
            $monto = $esIngreso ? $movimiento->abono : $movimiento->cargo;
            $cuentaBanco = $this->bancoService->obtenerCuentaContableDeBanco($empresaId, $movimiento->cuenta_bancaria_id);

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

            $this->bancoService->vincularAsientoAMovimiento($empresaId, $movimiento->id, $asiento->id);

            return $asiento;
        });
    }

    public function conciliarAnticipo(int $empresaId, array $datos, int $usuarioId)
    {
        return DB::transaction(function () use ($empresaId, $datos) {
            $this->bancoService->vincularMovimientoAAnticipo($empresaId, $datos['movimiento_id'], $datos['anticipo_id']);
            return true;
        });
    }
}