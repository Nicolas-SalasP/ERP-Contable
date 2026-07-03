<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Tesoreria\Exceptions\TesoreriaException;

use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Tesoreria\Services\BancoService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

            // Lock pesimista: sin esto, dos conciliaciones casi simultaneas sobre la
            // misma factura (doble clic) podian leer ambas "no pagada" antes de que
            // cualquiera comiteara y generar dos asientos de egreso duplicados.
            $factura = Factura::where('empresa_id', $datos['empresa_id'])
                ->where('id', $datos['factura_id'])
                ->lockForUpdate()
                ->first();

            if (!$factura) {
                throw ComercialException::noEncontrado("La factura solicitada no existe o no pertenece a su empresa.");
            }

            if ($factura->estado === 'PAGADA') {
                throw TesoreriaException::regla("La factura {$factura->numero_factura} ya está pagada.");
            }

            $this->facturaService->cambiarEstado($datos['empresa_id'], $factura->id, 'PAGADA');

            $cuentaProveedores = $datos['cuenta_proveedor'] ?? '352105';
            // Usa la cuenta contable real del banco; lanza excepción si no está configurada.
            $codigoCuentaBanco = $this->bancoService->obtenerCuentaContableDeBanco(
                $datos['empresa_id'],
                $datos['cuenta_bancaria_id']
            );
            $glosa = "Pago Factura N° {$factura->numero_factura} a Proveedor";

            $asientoPago = $this->asientoService->registrarAsiento([
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

            $factura->update(['asiento_pago_id' => $asientoPago->id]);

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

    public function obtenerSugerenciasConciliacion(int $empresaId, int $movimientoId): \Illuminate\Support\Collection
    {
        $movimiento = $this->bancoService->obtenerMovimiento($empresaId, $movimientoId);

        $esIngreso = (float) $movimiento->abono > 0;
        $monto     = $esIngreso ? (float) $movimiento->abono : (float) $movimiento->cargo;
        $tipoOperacion = $esIngreso ? 'cobrar' : 'pagar';

        // Obtiene facturas candidatas con tolerancia mas/menos 10% en el monto
        $candidatas = $this->facturaService->obtenerSugerenciasBancarias(
            $empresaId,
            $tipoOperacion,
            $monto,
            0.10
        );

        $fechaMovimiento = Carbon::parse((string) $movimiento->fecha);

        // Prioridad para ordenamiento final: alta=0, media=1, baja=2
        $ordenScore = ['alta' => 0, 'media' => 1, 'baja' => 2];

        /** @var array<int, array<string, mixed>> $sugerencias */
        $sugerencias = [];

        // Clasifica cada candidata y descarta las de baja confianza
        foreach ($candidatas as $factura) {
            $montoBruto    = (float) $factura->monto_bruto;
            $esMontoExacto = abs($montoBruto - $monto) <= 0.01;

            $fechaEmision    = Carbon::parse((string) $factura->fecha_emision);
            $diferenciaDias  = (int) abs($fechaMovimiento->diffInDays($fechaEmision));
            $esFechaProxima  = $diferenciaDias <= 7;

            // Determina el score segun las reglas de negocio
            if ($esMontoExacto && $esFechaProxima) {
                $score = 'alta';
            } elseif ($esMontoExacto) {
                $score = 'media';
            } elseif ($esFechaProxima) {
                $score = 'baja';
            } else {
                // Monto aproximado + fecha lejana: descarta la sugerencia
                continue;
            }

            $sugerencias[] = [
                'id'              => $factura->id,
                'numero_factura'  => $factura->numero_factura,
                'monto_bruto'     => $montoBruto,
                'fecha_emision'   => $fechaEmision->format('Y-m-d'),
                'razon_social'    => (string) ($factura->razon_social ?? ''),
                'score'           => $score,
                'diferencia_dias' => $diferenciaDias,
            ];
        }

        // Ordena: alta -> media -> baja
        usort($sugerencias, function (array $a, array $b) use ($ordenScore): int {
            $scoreA = isset($a['score']) ? (int) ($ordenScore[(string) $a['score']] ?? 99) : 99;
            $scoreB = isset($b['score']) ? (int) ($ordenScore[(string) $b['score']] ?? 99) : 99;
            return $scoreA - $scoreB;
        });

        return collect($sugerencias);
    }

    public function procesarPagoFacturas(int $empresaId, int $usuarioId, int $movimientoId, array $facturasIds, ?int $entidadId = null)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $movimientoId, $facturasIds, $entidadId) {
            // Bloquea el movimiento y rechaza si ya esta conciliado (evita doble cargo a banco).
            $movimiento = $this->bancoService->obtenerMovimientoParaConciliar($empresaId, $movimientoId);
            
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

            /** @var array<int, int> $idsPagadas */
            $idsPagadas = [];
            $idAbonada  = null;

            if ($facturas->count() > 0) {
                $saldoRestante = $montoMovimiento;
                $facturas = $facturas->sortBy('fecha_emision');

                foreach ($facturas as $fac) {
                    $montoFactura = (float) $fac->monto_bruto;
                    if ($saldoRestante >= $montoFactura) {
                        $idsPagadas[] = (int) $fac->id;
                        $saldoRestante -= $montoFactura;
                    } elseif ($saldoRestante > 0) {
                        $idAbonada    = (int) $fac->id;
                        $saldoRestante = 0;
                    } else {
                        break;
                    }
                }

                // Batch UPDATE: máximo 2 queries en lugar de N, sin alterar la lógica de saldo.
                if (!empty($idsPagadas)) {
                    Factura::whereIn('id', $idsPagadas)->where('empresa_id', $empresaId)->update(['estado' => 'PAGADA']);
                }
                if ($idAbonada !== null) {
                    Factura::where('id', $idAbonada)->where('empresa_id', $empresaId)->update(['estado' => 'ABONADA']);
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
                        // Valida que el proveedor pertenezca a la empresa antes de
                        // crear el anticipo (evita referenciar un proveedor de otra
                        // empresa: corrupcion de datos cross-tenant).
                        $proveedorValido = DB::table('proveedores')
                            ->where('id', $entidadId)
                            ->where('empresa_id', $empresaId)
                            ->exists();

                        if (!$proveedorValido) {
                            throw TesoreriaException::regla("El proveedor indicado no pertenece a la empresa.");
                        }

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

            // Vincula el asiento de pago a las facturas afectadas para que una
            // anulación posterior (Anulación General) pueda revertir su estado.
            $idsAfectadas = array_filter(array_merge($idsPagadas, [$idAbonada]));
            if (!empty($idsAfectadas)) {
                Factura::whereIn('id', $idsAfectadas)->where('empresa_id', $empresaId)->update(['asiento_pago_id' => $asiento->id]);
            }

            return $asiento;
        });
    }

    public function conciliarDirecto(int $empresaId, array $datos, int $usuarioId)
    {
        return DB::transaction(function () use ($empresaId, $datos, $usuarioId) {
            // Bloquea el movimiento y rechaza si ya esta conciliado (evita doble cargo a banco).
            $movimiento = $this->bancoService->obtenerMovimientoParaConciliar($empresaId, $datos['movimiento_id']);

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