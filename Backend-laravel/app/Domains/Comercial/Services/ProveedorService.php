<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use Exception;

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
        if (!empty($datos['rut'])) {
            $rutExiste = Proveedor::where('empresa_id', $datos['empresa_id'])
                ->where('rut', $datos['rut'])
                ->exists();

            if ($rutExiste) {
                throw new Exception("El proveedor con identificador {$datos['rut']} ya se encuentra registrado.");
            }
        }

        $proveedor = Proveedor::create([
            'empresa_id' => $datos['empresa_id'],
            'codigo_interno' => 'TEMP',
            'rut' => $datos['rut'] ?? null,
            'razon_social' => $datos['razonSocial'] ?? $datos['razon_social'],
            'pais_iso' => $datos['paisIso'] ?? 'CL',
            'moneda_defecto' => $datos['moneda'] ?? 'CLP',
            'nombre_contacto' => $datos['nombreContacto'] ?? null,
            'email_contacto' => $datos['emailContacto'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);

        $proveedor->update([
            'codigo_interno' => 'PROV-' . str_pad($proveedor->id, 5, '0', STR_PAD_LEFT)
        ]);

        return $proveedor;
    }

    public function obtenerFichaProveedor(int $empresaId, int $id)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)
            ->with(['cuentasBancarias', 'pais'])
            ->find($id);

        if (!$proveedor) {
            throw new Exception("El proveedor solicitado no existe.");
        }

        $facturas = Factura::where('empresa_id', $empresaId)
            ->where('proveedor_id', $id)
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $anticipos = AnticipoProveedor::where('empresa_id', $empresaId)
            ->where('proveedor_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'proveedor' => $proveedor,
            'facturas'  => $facturas,
            'anticipos' => $anticipos
        ];
    }

    public function actualizarProveedor(int $empresaId, int $id, array $datos)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)->findOrFail($id);

        if (isset($datos['rut']) && !empty($datos['rut']) && $datos['rut'] !== $proveedor->rut) {
            $existe = Proveedor::where('empresa_id', $empresaId)
                ->where('rut', $datos['rut'])
                ->exists();

            if ($existe) {
                throw new Exception("El Identificador Fiscal ingresado ya pertenece a otro proveedor.");
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
            // Quitamos el campo 'fecha' en caso de que no exista en tu migración real
            // Laravel usará created_at automáticamente.
            'monto' => $datos['monto'],
            'saldo_disponible' => $datos['monto'],
            'referencia' => $datos['referencia'] ?? null,
            'estado' => 'PENDIENTE'
        ]);
    }

    public function adjuntarPdfAnticipo(int $empresaId, int $anticipoId, ?string $rutaArchivo)
    {
        if (!$rutaArchivo) {
            throw new Exception("No se pudo procesar el archivo adjunto.");
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
                throw new Exception("Debe seleccionar al menos una deuda y un saldo a favor para ejecutar la compensación.");
            }

            $totalDeuda = DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $proveedorId)
                ->whereIn('id', $facturasIds)
                ->sum('monto_bruto');

            $totalNC = DB::table('facturas')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $proveedorId)
                ->whereIn('id', $ncIds)
                ->sum('monto_bruto');

            $totalAnticipos = DB::table('anticipos_proveedores')
                ->where('empresa_id', $empresaId)
                ->where('proveedor_id', $proveedorId)
                ->whereIn('id', $anticiposIds)
                ->sum('monto');

            $totalAFavor = $totalNC + $totalAnticipos;

            if ($totalAFavor > $totalDeuda) {
                throw new Exception("El monto a favor seleccionado ($" . number_format($totalAFavor, 0, ',', '.') . ") excede la deuda a compensar ($" . number_format($totalDeuda, 0, ',', '.') . "). Por favor deseleccione algunos documentos a favor.");
            }

            $nuevoEstadoFactura = ($totalAFavor == $totalDeuda) ? 'PAGADA' : 'ABONADA';

            if (!empty($facturasIds)) {
                DB::table('facturas')
                    ->where('empresa_id', $empresaId)
                    ->where('proveedor_id', $proveedorId)
                    ->whereIn('id', $facturasIds)
                    ->update(['estado' => $nuevoEstadoFactura]);
            }

            if (!empty($ncIds)) {
                DB::table('facturas')
                    ->where('empresa_id', $empresaId)
                    ->where('proveedor_id', $proveedorId)
                    ->whereIn('id', $ncIds)
                    ->update(['estado' => 'APLICADA']);
            }

            if (!empty($anticiposIds)) {
                DB::table('anticipos_proveedores')
                    ->where('empresa_id', $empresaId)
                    ->where('proveedor_id', $proveedorId)
                    ->whereIn('id', $anticiposIds)
                    ->update(['estado' => 'APLICADO']);
            }

            $asiento = null;

            if ($totalAnticipos > 0) {
                $proveedor = DB::table('proveedores')->where('id', $proveedorId)->first();
                $glosa = "Compensación de Anticipos con Facturas - " . ($proveedor->razon_social ?? 'Proveedor');

                $detallesAsiento = [
                    [
                        'cuenta_contable' => '352105', // Cuenta genérica de Proveedores (Pasivo disminuye al Debe)
                        'debe' => $totalAnticipos,
                        'haber' => 0,
                        'glosa_detalle' => 'Aplicación de Anticipo'
                    ],
                    [
                        'cuenta_contable' => '110205', // Cuenta de Anticipos a Proveedores (Activo disminuye al Haber)
                        'debe' => 0,
                        'haber' => $totalAnticipos,
                        'glosa_detalle' => 'Rebaja de Anticipo'
                    ]
                ];

                $asiento = $this->asientoService->registrarAsiento([
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'fecha' => now()->toDateString(),
                    'glosa' => substr($glosa, 0, 250),
                    'tipo_asiento' => 'traspaso',
                    'origen_modulo' => 'compras',
                    'estado' => 'MAYORIZADO'
                ], $detallesAsiento);
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