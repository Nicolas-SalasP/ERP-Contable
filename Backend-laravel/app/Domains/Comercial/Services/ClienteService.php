<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\AnticipoCliente;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;

class ClienteService
{
    protected $asientoService;

    public function __construct(AsientoContableService $asientoService)
    {
        $this->asientoService = $asientoService;
    }

    public function obtenerFichaCliente(int $empresaId, int $id)
    {
        $cliente = Cliente::where('empresa_id', $empresaId)->find($id);

        if (!$cliente) {
            throw ComercialException::noEncontrado("El cliente solicitado no existe.");
        }

        $facturas = Factura::where('empresa_id', $empresaId)
            ->where('cliente_id', $id)
            ->withCount('documentosAdjuntos')
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $anticipos = AnticipoCliente::where('empresa_id', $empresaId)
            ->where('cliente_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'cliente' => $cliente,
            'facturas' => $facturas,
            'anticipos' => $anticipos,
        ];
    }

    public function buscarClientesPorEmpresa(int $empresaId, ?string $search = null)
    {
        $query = Cliente::where('empresa_id', $empresaId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('rut', 'like', "%{$search}%")
                    ->orWhere('razon_social', 'like', "%{$search}%")
                    ->orWhere('codigo_cliente', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('razon_social')->get();
    }

    public function registrarCliente(array $datos): Cliente
    {
        $existe = Cliente::where('empresa_id', $datos['empresa_id'])
            ->where('rut', $datos['rut'])
            ->exists();

        if ($existe) {
            throw ComercialException::regla("El cliente con RUT {$datos['rut']} ya se encuentra registrado.");
        }

        return Cliente::create($datos);
    }

    public function inactivarCliente(int $empresaId, int $id)
    {
        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);
        $cliente->update(['estado' => 'INACTIVO']);
        return $cliente;
    }
    public function actualizarCliente(int $empresaId, $id, array $datos)
    {
        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);

        if (isset($datos['rut']) && $datos['rut'] !== $cliente->rut) {
            $existe = Cliente::where('empresa_id', $cliente->empresa_id)
                ->where('rut', $datos['rut'])
                ->exists();

            if ($existe) {
                throw ComercialException::regla("El RUT ingresado ya está registrado para otro cliente en esta empresa.");
            }
        }

        return DB::transaction(function () use ($cliente, $datos) {
            $cliente->update($datos);
            return $cliente;
        });
    }
    
    public function activarCliente(int $empresaId, int $id): Cliente
    {
        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);
        $cliente->update(['estado' => 'ACTIVO']);
        return $cliente;
    }

    public function reactivarCliente(int $empresaId, int $clienteId)
    {
        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($clienteId);

        if ($cliente->estado === 'ACTIVO') {
            throw ComercialException::regla("El cliente ya se encuentra activo.");
        }

        $cliente->estado = 'ACTIVO';
        $cliente->save();

        return $cliente;
    }

    /**
     * Espejo de ProveedorService::compensarPartidas para el lado venta: cruza facturas de
     * venta contra Notas de Crédito de venta y/o anticipos de cliente en una sola operación
     * transaccional. Débito/crédito del asiento de traspaso van invertidos respecto al lado
     * compra porque el anticipo de cliente es un PASIVO (Anticipos de Clientes 210205) y la
     * contrapartida es la Cuenta por Cobrar (152005), en vez de Proveedores/Anticipos a Proveedores.
     */
    public function compensarPartidas(int $empresaId, int $usuarioId, int $clienteId, array $datos)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $clienteId, $datos) {
            $facturasIds = $datos['facturas_ids'] ?? [];
            $ncIds = $datos['notas_credito_ids'] ?? [];
            $anticiposIds = $datos['anticipos_ids'] ?? [];

            if (empty($facturasIds) || (empty($ncIds) && empty($anticiposIds))) {
                throw ComercialException::regla("Debe seleccionar al menos una deuda y un saldo a favor para ejecutar la compensación.");
            }

            // Lock pesimista: evita que dos compensaciones concurrentes lean el mismo saldo disponible y lo apliquen dos veces.
            $totalDeuda = DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $clienteId)
                ->where('tipo', 'VENTA')
                ->whereIn('id', $facturasIds)
                ->lockForUpdate()
                ->sum('monto_bruto');

            $totalNC = DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $clienteId)
                ->where('tipo', 'VENTA')
                ->whereIn('id', $ncIds)
                ->where('estado', '!=', 'APLICADA')
                ->lockForUpdate()
                ->sum('monto_bruto');

            $totalAnticipos = DB::table('anticipos_clientes')
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $clienteId)
                ->whereIn('id', $anticiposIds)
                ->where('estado', '!=', 'APLICADO')
                ->lockForUpdate()
                ->sum('monto');

            $totalAFavor = $totalNC + $totalAnticipos;

            if ($totalAFavor > $totalDeuda) {
                throw ComercialException::regla("El monto a favor seleccionado ($" . number_format($totalAFavor, 0, ',', '.') . ") excede la deuda a compensar ($" . number_format($totalDeuda, 0, ',', '.') . "). Por favor deseleccione algunos documentos a favor.");
            }

            $nuevoEstadoFactura = ($totalAFavor == $totalDeuda) ? 'PAGADA' : 'ABONADA';

            DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $clienteId)
                ->where('tipo', 'VENTA')
                ->whereIn('id', $facturasIds)
                ->update(['estado' => $nuevoEstadoFactura]);

            if (!empty($ncIds)) {
                DB::table('facturas')
                    ->where('empresa_id', $empresaId)
                    ->where('cliente_id', $clienteId)
                    ->where('tipo', 'VENTA')
                    ->whereIn('id', $ncIds)
                    ->update(['estado' => 'APLICADA']);
            }

            if (!empty($anticiposIds)) {
                DB::table('anticipos_clientes')
                    ->where('empresa_id', $empresaId)
                    ->where('cliente_id', $clienteId)
                    ->whereIn('id', $anticiposIds)
                    ->update(['estado' => 'APLICADO']);
            }

            $asiento = null;

            if ($totalAnticipos > 0) {
                $cliente = DB::table('clientes')->where('id', $clienteId)->first();
                $glosa = "Compensación de Anticipos con Facturas de Venta - " . ($cliente->razon_social ?? 'Cliente');

                $detallesAsiento = [
                    [
                        'cuenta_contable' => '210205', // Anticipos de Clientes (Pasivo disminuye al Debe)
                        'debe' => $totalAnticipos,
                        'haber' => 0,
                        'glosa_detalle' => 'Aplicación de Anticipo'
                    ],
                    [
                        'cuenta_contable' => '152005', // Cuentas por Cobrar Clientes (Activo disminuye al Haber)
                        'debe' => 0,
                        'haber' => $totalAnticipos,
                        'glosa_detalle' => 'Rebaja de Cuenta por Cobrar'
                    ]
                ];

                $asiento = $this->asientoService->registrarAsiento([
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => now()->toDateString(),
                    'glosa' => substr($glosa, 0, 250),
                    'tipo_asiento' => 'traspaso',
                    'origen_modulo' => 'ventas',
                    'estado' => 'MAYORIZADO'
                ], $detallesAsiento);

                // Vincula el asiento de traspaso a las facturas compensadas: sin esto, al
                // reversar este asiento (AnulacionService/AsientoContableService) no había
                // forma de encontrar la Factura para revertir su estado, quedaba huérfana.
                DB::table('facturas')
                    ->where('empresa_id', $empresaId)
                    ->where('cliente_id', $clienteId)
                    ->where('tipo', 'VENTA')
                    ->whereIn('id', $facturasIds)
                    ->update(['asiento_pago_id' => $asiento->id]);
            }

            return [
                'facturas_afectadas' => count($facturasIds),
                'anticipos_consumidos' => count($anticiposIds),
                'notas_credito_aplicadas' => count($ncIds),
                'comprobante_traspaso' => $asiento ? $asiento->numero_comprobante : null
            ];
        });
    }
}