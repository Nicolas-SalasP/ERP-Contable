<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Support\Facades\DB;

class ProveedorService
{
    protected $asientoService;

    public function __construct(AsientoContableService $asientoService)
    {
        $this->asientoService = $asientoService;
    }

    public function obtenerProveedoresPorEmpresa(int $empresaId)
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->with('cuentasBancarias')
            ->orderBy('razon_social')
            ->get();
    }

    public function obtenerCatalogoBasico(int $empresaId)
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->select('id', 'rut', 'razon_social', 'codigo_interno')
            ->orderBy('razon_social')
            ->get();
    }

    public function registrarProveedor(array $datos): Proveedor
    {
        // Defensa en profundidad: aunque el controller ya valide, el service no debe
        // reventar con "Undefined array key" si le llega un array incompleto.
        $razonSocial = $datos['razonSocial'] ?? $datos['razon_social'] ?? null;
        if (! $razonSocial) {
            throw ComercialException::regla('La razón social del proveedor es obligatoria.');
        }

        $paisIso = $datos['paisIso'] ?? 'CL';

        // Solo se valida formato/DV para proveedores nacionales: uno extranjero
        // trae un identificador tributario de otro pais, no un RUT chileno.
        if (! empty($datos['rut']) && $paisIso === 'CL' && ! RutHelper::validar($datos['rut'])) {
            throw ComercialException::regla("El RUT {$datos['rut']} no es un RUT chileno valido.");
        }

        if (! empty($datos['rut'])) {
            $rutExiste = Proveedor::where('empresa_id', $datos['empresa_id'])
                ->whereBlind('rut', 'proveedor_rut_index', $datos['rut'])
                ->exists();

            if ($rutExiste) {
                throw ComercialException::regla("El proveedor con identificador {$datos['rut']} ya se encuentra registrado.");
            }
        }

        $proveedor = Proveedor::create([
            'empresa_id' => $datos['empresa_id'],
            'codigo_interno' => 'TEMP',
            'rut' => $datos['rut'] ?? null,
            'razon_social' => $razonSocial,
            'pais_iso' => $datos['paisIso'] ?? 'CL',
            'moneda_defecto' => $datos['moneda'] ?? 'CLP',
            'nombre_contacto' => $datos['nombreContacto'] ?? null,
            'email_contacto' => $datos['emailContacto'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);

        $proveedor->update([
            'codigo_interno' => 'PROV-'.str_pad((string) $proveedor->id, 5, '0', STR_PAD_LEFT),
        ]);

        return $proveedor;
    }

    public function obtenerFichaProveedor(int $empresaId, int $id)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)
            ->with(['cuentasBancarias', 'pais'])
            ->find($id);

        if (! $proveedor) {
            throw ComercialException::noEncontrado('El proveedor solicitado no existe.');
        }

        // tipo=COMPRA es obligatorio: si esta empresa Proveedor tambien es Cliente (mismo RUT),
        // CotizacionService::facturar reusa el mismo proveedor_id "espejo" para sus facturas de
        // VENTA -> sin este filtro, la ficha mezclaria facturas de venta con el historial real
        // de compras del proveedor (mismo patron de bug que FacturaController::historial()).
        $facturas = Factura::where('empresa_id', $empresaId)
            ->where('proveedor_id', $id)
            ->where('tipo', 'COMPRA')
            ->withCount('documentosAdjuntos')
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $anticipos = AnticipoProveedor::where('empresa_id', $empresaId)
            ->where('proveedor_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'proveedor' => $proveedor,
            'facturas' => $facturas,
            'anticipos' => $anticipos,
        ];
    }

    public function actualizarProveedor(int $empresaId, int $id, array $datos)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)->findOrFail($id);

        if (isset($datos['rut']) && ! empty($datos['rut']) && $datos['rut'] !== $proveedor->rut) {
            $paisIso = $datos['pais_iso'] ?? $proveedor->pais_iso ?? 'CL';
            if ($paisIso === 'CL' && ! RutHelper::validar($datos['rut'])) {
                throw ComercialException::regla("El RUT {$datos['rut']} no es un RUT chileno valido.");
            }

            $existe = Proveedor::where('empresa_id', $empresaId)
                ->whereBlind('rut', 'proveedor_rut_index', $datos['rut'])
                ->exists();

            if ($existe) {
                throw ComercialException::regla('El Identificador Fiscal ingresado ya pertenece a otro proveedor.');
            }
        }

        $proveedor->update($datos);

        return $proveedor;
    }

    public function registrarAnticipo(int $empresaId, array $datos)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)
            ->findOrFail($datos['proveedor_id']);

        return AnticipoProveedor::create([
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedor->id,
            // No se envía 'fecha': Laravel usa created_at automáticamente.
            'monto' => $datos['monto'],
            'saldo_disponible' => $datos['monto'],
            'referencia' => $datos['referencia'] ?? null,
            'estado' => 'PENDIENTE',
        ]);
    }

    public function adjuntarPdfAnticipo(int $empresaId, int $anticipoId, ?string $rutaArchivo)
    {
        if (! $rutaArchivo) {
            throw ComercialException::regla('No se pudo procesar el archivo adjunto.');
        }

        $anticipo = AnticipoProveedor::where('empresa_id', $empresaId)->findOrFail($anticipoId);

        $anticipo->archivo_pdf = $rutaArchivo;
        $anticipo->save();

        return $anticipo;
    }

    public function compensarPartidas(int $empresaId, int $usuarioId, int $proveedorId, array $datos)
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $proveedorId, $datos) {
            $facturasIds = $datos['facturas_ids'] ?? [];
            $ncIds = $datos['notas_credito_ids'] ?? [];
            $anticiposIds = $datos['anticipos_ids'] ?? [];

            if (empty($facturasIds) || (empty($ncIds) && empty($anticiposIds))) {
                throw ComercialException::regla('Debe seleccionar al menos una deuda y un saldo a favor para ejecutar la compensación.');
            }

            // tipo=COMPRA obligatorio en las 4 queries de facturas de este metodo: si el proveedor
            // tambien es Cliente (mismo RUT), CotizacionService::facturar reusa el mismo
            // proveedor_id "espejo" en sus facturas de VENTA -> sin este filtro, se podria
            // compensar/marcar PAGADA una factura de venta a un cliente real como si fuera deuda
            // de compra (mismo patron de bug que FacturaController::historial()).
            // Lock pesimista: evita que dos compensaciones concurrentes lean el mismo saldo disponible y lo apliquen dos veces.
            $totalDeuda = DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $proveedorId)
                ->where('tipo', 'COMPRA')
                ->whereIn('id', $facturasIds)
                ->lockForUpdate()
                ->sum('monto_bruto');

            $totalNC = DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $proveedorId)
                ->where('tipo', 'COMPRA')
                ->whereIn('id', $ncIds)
                ->where('estado', '!=', 'APLICADA')
                ->lockForUpdate()
                ->sum('monto_bruto');

            $totalAnticipos = DB::table('anticipos_proveedores')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $proveedorId)
                ->whereIn('id', $anticiposIds)
                ->where('estado', '!=', 'APLICADO')
                ->lockForUpdate()
                ->sum('monto');

            $totalAFavor = $totalNC + $totalAnticipos;

            if ($totalAFavor > $totalDeuda) {
                throw ComercialException::regla('El monto a favor seleccionado ($'.number_format($totalAFavor, 0, ',', '.').') excede la deuda a compensar ($'.number_format($totalDeuda, 0, ',', '.').'). Por favor deseleccione algunos documentos a favor.');
            }

            $nuevoEstadoFactura = ($totalAFavor == $totalDeuda) ? 'PAGADA' : 'ABONADA';

            DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $proveedorId)
                ->where('tipo', 'COMPRA')
                ->whereIn('id', $facturasIds)
                ->update(['estado' => $nuevoEstadoFactura]);

            if (! empty($ncIds)) {
                DB::table('facturas')
                    ->where('empresa_id', $empresaId)
                    ->where('proveedor_id', $proveedorId)
                    ->where('tipo', 'COMPRA')
                    ->whereIn('id', $ncIds)
                    ->update(['estado' => 'APLICADA']);
            }

            if (! empty($anticiposIds)) {
                DB::table('anticipos_proveedores')
                    ->where('empresa_id', $empresaId)
                    ->where('proveedor_id', $proveedorId)
                    ->whereIn('id', $anticiposIds)
                    ->update(['estado' => 'APLICADO']);
            }

            $asiento = null;

            if ($totalAnticipos > 0) {
                $proveedor = DB::table('proveedores')->where('id', $proveedorId)->first();
                $glosa = 'Compensación de Anticipos con Facturas - '.($proveedor->razon_social ?? 'Proveedor');

                $detallesAsiento = [
                    [
                        'cuenta_contable' => '352105', // Cuenta genérica de Proveedores (Pasivo disminuye al Debe)
                        'debe' => $totalAnticipos,
                        'haber' => 0,
                        'glosa_detalle' => 'Aplicación de Anticipo',
                    ],
                    [
                        'cuenta_contable' => '110205', // Cuenta de Anticipos a Proveedores (Activo disminuye al Haber)
                        'debe' => 0,
                        'haber' => $totalAnticipos,
                        'glosa_detalle' => 'Rebaja de Anticipo',
                    ],
                ];

                $asiento = $this->asientoService->registrarAsiento([
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => now()->toDateString(),
                    'glosa' => substr($glosa, 0, 250),
                    'tipo_asiento' => 'traspaso',
                    'origen_modulo' => 'compras',
                    'estado' => 'MAYORIZADO',
                ], $detallesAsiento);

                // Vincula el asiento de traspaso a las facturas compensadas: sin esto, al
                // reversar este asiento (AnulacionService/AsientoContableService) no había
                // forma de encontrar la Factura para revertir su estado, quedaba huérfana.
                DB::table('facturas')
                    ->where('empresa_id', $empresaId)
                    ->where('proveedor_id', $proveedorId)
                    ->where('tipo', 'COMPRA')
                    ->whereIn('id', $facturasIds)
                    ->update(['asiento_pago_id' => $asiento->id]);
            }

            return [
                'facturas_afectadas' => count($facturasIds),
                'anticipos_consumidos' => count($anticiposIds),
                'notas_credito_aplicadas' => count($ncIds),
                'comprobante_traspaso' => $asiento ? $asiento->numero_comprobante : null,
            ];
        });
    }

    public function obtenerProveedoresPaginados(int $empresaId, int $limit)
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    /** Inactiva (bloquea) un proveedor: no elimina el registro, solo cambia su estado. */
    public function inactivarProveedor(int $empresaId, int $id): Proveedor
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)->findOrFail($id);
        $proveedor->update(['estado' => 'INACTIVO']);

        return $proveedor;
    }

    /** Marca un proveedor como activo (desbloquea). */
    public function activarProveedor(int $empresaId, int $id): Proveedor
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)->findOrFail($id);
        $proveedor->update(['estado' => 'ACTIVO']);

        return $proveedor;
    }
}
