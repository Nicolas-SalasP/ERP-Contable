<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use \Carbon\Carbon;
use Exception;

class FacturaService
{
    protected $asientoService;

    public function __construct(AsientoContableService $asientoService)
    {
        $this->asientoService = $asientoService;
    }

    public function obtenerFacturasPorEmpresa(int $empresaId, ?string $estado = null)
    {
        $query = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria']);

        if ($estado) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('fecha_emision', 'desc')->get();
    }

    public function obtenerFacturasPaginadas(int $empresaId, array $filtros)
    {
        $query = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria']);

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['num'])) {
            $query->where('numero_factura', 'like', "%{$filtros['num']}%");
        }

        if (!empty($filtros['search'])) {
            $query->whereHas('proveedor', function ($q) use ($filtros) {
                $q->where('razon_social', 'like', "%{$filtros['search']}%")
                    ->orWhere('rut', 'like', "%{$filtros['search']}%");
            });
        }

        $limit = $filtros['limit'] ?? 10;
        return $query->orderBy('fecha_emision', 'desc')->paginate($limit);
    }

    public function obtenerFacturaPorId(int $empresaId, int $facturaId)
    {
        $factura = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria'])
            ->find($facturaId);

        if (!$factura) {
            throw new Exception("La factura solicitada no existe o no pertenece a su empresa.");
        }

        return $factura;
    }

    public function verificarDuplicado(int $empresaId, ?int $proveedorId, string $numero): bool
    {
        if (!$proveedorId)
            return false;

        return Factura::where('empresa_id', $empresaId)
            ->where('proveedor_id', $proveedorId)
            ->where('numero_factura', $numero)
            ->exists();
    }

    public function registrarFacturaCompra(array $datos): Factura
    {
        if (!isset($datos['monto_neto']) || $datos['monto_neto'] <= 0) {
            throw new Exception("El monto neto debe ser mayor a 0.");
        }

        $neto = round((float) $datos['monto_neto'], 2);
        $iva = isset($datos['monto_iva']) ? round((float) $datos['monto_iva'], 2) : round($neto * 0.19, 2);
        $bruto = isset($datos['monto_bruto']) ? round((float) $datos['monto_bruto'], 2) : round($neto + $iva, 2);

        if (abs(($neto + $iva) - $bruto) > 0.01) {
            throw new Exception("Inconsistencia tributaria: El Neto + IVA no coincide con el Monto Bruto.");
        }

        return DB::transaction(function () use ($datos, $neto, $iva, $bruto) {
            $existe = $this->verificarDuplicado($datos['empresa_id'], $datos['proveedor_id'], $datos['numero_factura']);

            if ($existe) {
                throw new Exception("La factura {$datos['numero_factura']} ya se encuentra registrada para este proveedor.");
            }

            $codigoUnico = (int) (time() . rand(100, 999));

            $factura = Factura::create([
                'empresa_id' => $datos['empresa_id'],
                'tipo' => 'COMPRA',
                'tipo_documento' => $datos['tipo_documento'] ?? 'FACTURA',
                'codigo_unico' => $codigoUnico,
                'proveedor_id' => $datos['proveedor_id'],
                'cuenta_bancaria_id' => $datos['cuenta_bancaria_id'] ?? null,
                'numero_factura' => $datos['numero_factura'],
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'monto_bruto' => $bruto,
                'monto_neto' => $neto,
                'monto_iva' => $iva,
                'estado' => 'REGISTRADA',
                'autorizador_id' => auth()->id() ?? $datos['autorizador_id'] ?? null,
            ]);

            $codigoDestino = $datos['cuentaDestino'] ?? throw new Exception("Debe especificar la cuenta de destino/gasto.");
            $codigoIva = $datos['cuentaIva'] ?? '353350';
            $codigoProveedor = $datos['cuentaProveedor'] ?? '352105';

            $cuentaIva = PlanCuenta::where('empresa_id', $datos['empresa_id'])->where('codigo', $codigoIva)->first();
            $cuentaProveedor = PlanCuenta::where('empresa_id', $datos['empresa_id'])->where('codigo', $codigoProveedor)->first();
            $cuentaGasto = PlanCuenta::where('empresa_id', $datos['empresa_id'])->where('codigo', $codigoDestino)->first();

            if (!$cuentaGasto || !$cuentaIva || !$cuentaProveedor) {
                throw new Exception("Configuración Contable Incompleta: Verifique que las cuentas de IVA ({$codigoIva}), Proveedor ({$codigoProveedor}) y Destino ({$codigoDestino}) existan en el plan de cuentas de esta empresa.");
            }

            $fechaOperacion = $datos['fechaContable'] ?? $datos['fecha_emision'];

            $cabeceraAsiento = [
                'empresa_id' => $datos['empresa_id'],
                'fecha' => $fechaOperacion,
                'glosa' => "Centralización Automática Factura Compra N° " . $datos['numero_factura'],
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'compras',
                'origen_id' => $factura->id,
                'usuario_id' => auth()->id() ?? $datos['autorizador_id'] ?? null,
            ];

            $detallesAsiento = [];

            // 1. DEBE: El Gasto
            $detallesAsiento[] = [
                'cuenta_contable' => $cuentaGasto->codigo,
                'debe' => $neto,
                'haber' => 0,
                'glosa_detalle' => "Gasto Factura N° {$datos['numero_factura']}",
                'centro_costo_id' => $datos['centro_costo_id'] ?? null
            ];

            // 2. DEBE: IVA
            if ($iva > 0) {
                $detallesAsiento[] = [
                    'cuenta_contable' => $cuentaIva->codigo,
                    'debe' => $iva,
                    'haber' => 0,
                    'glosa_detalle' => "IVA CF Factura N° {$datos['numero_factura']}"
                ];
            }

            // 3. HABER: Cuenta por Pagar (Bruto)
            $detallesAsiento[] = [
                'cuenta_contable' => $cuentaProveedor->codigo,
                'debe' => 0,
                'haber' => $bruto,
                'glosa_detalle' => "CxP Proveedor Factura N° {$datos['numero_factura']}"
            ];

            $asiento = $this->asientoService->registrarAsiento($cabeceraAsiento, $detallesAsiento);

            $factura->update([
                'codigo_interno' => 'FAC-' . str_pad($factura->id, 5, '0', STR_PAD_LEFT),
                'comprobante_contable' => $asiento->numero_comprobante
            ]);

            return $factura;
        });
    }

    public function obtenerAsientoDeFactura(int $empresaId, int $facturaId): array
    {
        $factura = Factura::where('empresa_id', $empresaId)->findOrFail($facturaId);

        if (!$factura->comprobante_contable) {
            throw new Exception('Esta factura aún no tiene un asiento contable vinculado.');
        }

        $asiento = AsientoContable::with(['detalles.cuenta', 'usuario'])
            ->where('empresa_id', $empresaId)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        if (!$asiento) {
            throw new Exception('El asiento asociado no fue encontrado en la base de datos.');
        }

        return [
            'cabecera' => $asiento,
            'detalles' => $asiento->detalles->map(function ($d) {
                return [
                    'id' => $d->id,
                    'cuenta_contable' => $d->cuenta_contable,
                    'cuenta_nombre' => $d->cuenta->nombre ?? 'Sin nombre',
                    'debe' => $d->debe,
                    'haber' => $d->haber,
                    'glosa_detalle' => $d->descripcion_extensa
                ];
            })
        ];
    }

    public function reclasificarAsiento(int $empresaId, int $usuarioId, int $facturaId, array $datos): void
    {
        DB::transaction(function () use ($empresaId, $usuarioId, $facturaId, $datos) {
            $factura = Factura::where('empresa_id', $empresaId)->findOrFail($facturaId);
            $asiento = AsientoContable::with('detalles')
                ->where('empresa_id', $empresaId)
                ->where('numero_comprobante', $factura->comprobante_contable)
                ->firstOrFail();

            $glosaCabeceraOriginal = $asiento->glosa;
            $asiento->update([
                'fecha' => $datos['fecha'],
                'usuario_id' => $usuarioId
            ]);

            foreach ($datos['cambios'] as $detalleId => $nuevoCodigoCuenta) {
                $lineaOriginal = $asiento->detalles->firstWhere('id', $detalleId);

                if ($lineaOriginal) {
                    $glosaLineaOriginal = $lineaOriginal->descripcion_extensa ?: $glosaCabeceraOriginal;

                    $asiento->detalles()->create([
                        'cuenta_contable' => $lineaOriginal->cuenta_contable,
                        'debe' => $lineaOriginal->haber,
                        'haber' => $lineaOriginal->debe,
                        'descripcion_extensa' => "Reverso: " . $glosaLineaOriginal,
                        'centro_costo_id' => $lineaOriginal->centro_costo_id,
                        'empleado_nombre' => $lineaOriginal->empleado_nombre
                    ]);

                    $asiento->detalles()->create([
                        'cuenta_contable' => $nuevoCodigoCuenta,
                        'debe' => $lineaOriginal->debe,
                        'haber' => $lineaOriginal->haber,
                        'descripcion_extensa' => $datos['glosa'],
                        'centro_costo_id' => $lineaOriginal->centro_costo_id,
                        'empleado_nombre' => $lineaOriginal->empleado_nombre
                    ]);
                }
            }
        });
    }

    public function obtenerFacturasDisponiblesParaProyectos(int $empresaId): array
    {
        return Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->whereNull('proyecto_activo_id')
            ->with('proveedor')
            ->get()
            ->map(function ($f) {
                $nombreProv = $f->proveedor->nombre_fantasia ?? $f->proveedor->razon_social ?? 'Proveedor sin nombre';
                return [
                    'factura_id' => $f->id,
                    'numero_factura' => $f->numero_factura,
                    'proveedor' => $nombreProv,
                    'monto' => (float) $f->monto_neto 
                ];
            })
            ->toArray();
    }

    public function vincularAProyecto(int $empresaId, int $facturaId, int $proyectoId): Factura
    {
        $factura = Factura::where('empresa_id', $empresaId)->findOrFail($facturaId);
        $factura->update(['proyecto_activo_id' => $proyectoId]);
        return $factura;
    }

    public function obtenerPorProyecto(int $proyectoId): array
    {
        return Factura::where('proyecto_activo_id', $proyectoId)
            ->with('proveedor')
            ->get()
            ->map(function ($f) {
                return [
                    'numero' => $f->numero_factura,
                    'proveedor' => $f->proveedor->nombre_fantasia ?? $f->proveedor->razon_social ?? $f->proveedor->rut,
                    'monto' => (float) $f->monto_neto
                ];
            })
            ->toArray();
    }

    public function obtenerAuditoriaCompleta(int $id): array
    {
        $factura = Factura::with('proveedor')->findOrFail($id);

        $historial = DB::table('auditorias')
            ->where('auditable_type', Factura::class)
            ->where('auditable_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'usuario' => $log->nombre_usuario,
                    'fecha' => Carbon::parse($log->created_at)->format('Y-m-d H:i:s'),
                    'operacion' => $log->operacion,
                    'estado_ant' => $log->estado_anterior ?? '-',
                    'estado_nue' => $log->estado_nuevo ?? '-',
                    'detalle' => $log->detalle,
                    'asiento' => $log->referencia_cruzada
                ];
            })->toArray();

        if (empty($historial)) {
            $historial = [
                [
                    'id' => 0,
                    'usuario' => 'Sistema',
                    'fecha' => $factura->created_at ? $factura->created_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    'operacion' => 'CREACIÓN',
                    'estado_ant' => '-',
                    'estado_nue' => $factura->estado,
                    'detalle' => 'Registro original migrado/creado en el sistema.',
                    'asiento' => $factura->codigo_asiento ?? null
                ]
            ];
        }

        return [
            'factura' => [
                'id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'proveedor' => $factura->proveedor->razon_social ?? 'Proveedor Desconocido',
                'estado' => $factura->estado
            ],
            'historial' => $historial
        ];
    }

    public function registrarPago(int $empresaId, int $facturaId, array $datos)
    {
        $factura = Factura::where('empresa_id', $empresaId)->findOrFail($facturaId);

        if ($factura->estado === 'PAGADA') {
            throw new Exception("Esta factura ya se encuentra pagada.");
        }

        $factura->estado = 'PAGADA';
        $factura->fecha_pago = $datos['fechaPago'] ?? now()->format('Y-m-d');
        $factura->medio_pago = $datos['medioPago'] ?? 'TRANSFERENCIA';
        $factura->save();

        return $factura;
    }
}