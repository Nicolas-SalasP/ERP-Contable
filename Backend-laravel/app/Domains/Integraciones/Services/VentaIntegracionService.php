<?php

namespace App\Domains\Integraciones\Services;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\FacturaDetalle;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use App\Domains\Core\Services\ContadorEmpresaService;
use App\Domains\Inventario\Exceptions\InventarioException;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Services\InventarioReservaService;
use App\Domains\Integraciones\Exceptions\VentaIntegracionException;
use App\Domains\Integraciones\Models\IntegracionVentaIdempotencia;
use App\Domains\Sii\Exceptions\FacturaNoEmisibleException;
use App\Domains\Sii\Services\Integracion\EmitirDteDesdeFacturaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Puente Fase 2 entre la API publica de Integraciones y el motor real de ventas del ERP: un
 * tercero (Tenri-Web-Page) reserva stock por SKU, y al confirmar el checkout se consume la
 * reserva (InventarioReservaService::consumirReserva -> InventarioMovimientoService hace la
 * salida real), se crea una Factura/FacturaDetalle con su asiento contable (mismas cuentas que
 * CotizacionService::convertirEnFactura, sin pasar por Cotizacion: esta venta no tiene flujo de
 * aprobacion, nace ya "Aceptada" desde el checkout externo) y se encola la emision del DTE
 * (boleta 39 sin RUT valido del cliente, factura 33 con RUT) via EmitirDteDesdeFacturaService,
 * el mismo puerto de entrada que usa el resto del ERP.
 *
 * RBAC: estos metodos corren sin sesion Sanctum (autenticados solo por API-key +
 * integracion.scope:ventas:escribir, ver AutenticarApiKey/ExigirScope), pero
 * InventarioReservaService exige un `User` real con permisos de inventario. `actorSistema()`
 * arma un User NO persistido con un Rol (tambien no persistido) de jerarquia 100: el mismo
 * atajo que ya usa InventarioPermisoService::esAdministradorInventario para Super Admin, sin
 * crear una fila de usuario "fantasma" en la empresa. La key ya fue autorizada por su scope;
 * este actor solo satisface la firma de los servicios internos, no es una autorizacion nueva.
 */
class VentaIntegracionService
{
    private const TTL_RESERVA_HORAS = 48;

    private const TIPO_DTE_FACTURA = 33;

    private const TIPO_DTE_BOLETA = 39;

    private const RUT_GENERICO_BOLETA = '66666666-6';

    public function __construct(
        private readonly InventarioReservaService $reservaService,
        private readonly ContadorEmpresaService $contadorService,
        private readonly EmitirDteDesdeFacturaService $emitirDte,
    ) {
    }

    /** @return array{reserva_id: int, expira_en: string} */
    public function reservar(int $empresaId, array $datos): array
    {
        $sku = trim((string) ($datos['sku'] ?? ''));
        $cantidad = $datos['cantidad'] ?? null;

        if ($sku === '') {
            throw ValidationException::withMessages(['sku' => 'El sku es obligatorio.']);
        }

        if (! is_numeric($cantidad) || (float) $cantidad <= 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser numérica y mayor a cero.']);
        }

        $producto = Producto::where('empresa_id', $empresaId)
            ->where('sku', $sku)
            ->first();

        if ($producto === null || ! $producto->activo) {
            throw InventarioException::noEncontrado('El sku informado no existe, esta inactivo o no pertenece a la empresa.');
        }

        $bodega = $this->bodegaVentaPorDefecto($empresaId);
        $actor = $this->actorSistema($empresaId);

        $reserva = $this->reservaService->crearReserva($actor, [
            'referencia' => $this->normalizarReferencia($datos['referencia_externa'] ?? null),
            'origen_modulo' => 'INTEGRACIONES',
            'fecha_expiracion' => now()->addHours(self::TTL_RESERVA_HORAS),
            'detalles' => [[
                'producto_id' => $producto->id,
                'bodega_id' => $bodega->id,
                'cantidad' => (float) $cantidad,
            ]],
        ]);

        return [
            'reserva_id' => $reserva->id,
            'expira_en' => $reserva->fecha_expiracion?->toIso8601String(),
        ];
    }

    public function liberar(int $empresaId, int $reservaId): void
    {
        $actor = $this->actorSistema($empresaId);

        $this->reservaService->cancelarReserva($actor, $reservaId);
    }

    /** @return array{factura_id: int, numero_factura: string, estado: string, dte_estado: string, monto_neto: float, monto_iva: float, monto_bruto: float} */
    public function confirmar(int $empresaId, array $datos, ?string $idempotencyKey): array
    {
        $clave = $this->resolverClaveIdempotencia($idempotencyKey, $datos, (int) ($datos['reserva_id'] ?? 0));

        $existente = IntegracionVentaIdempotencia::where('empresa_id', $empresaId)
            ->where('clave', $clave)
            ->first();

        if ($existente !== null) {
            return array_merge($existente->respuesta_json, ['idempotente' => true]);
        }

        return DB::transaction(function () use ($empresaId, $datos, $clave) {
            $reservaId = (int) ($datos['reserva_id'] ?? 0);
            $actor = $this->actorSistema($empresaId);

            $reserva = $this->reservaService->obtenerReserva($actor, $reservaId);

            if (! $reserva->comprometeDisponibilidad()) {
                throw ValidationException::withMessages([
                    'reserva_id' => 'La reserva no esta disponible para confirmar (estado actual: '.$reserva->estado.').',
                ]);
            }

            $this->validarItemsContraReserva($reserva, $datos['items'] ?? []);

            $reservaConsumida = $this->reservaService->consumirReserva($actor, $reservaId, [
                'referencia' => $reserva->referencia ?? $reserva->codigo_reserva,
                'motivo' => 'venta_integracion',
                'observacion' => 'Venta confirmada via API de integraciones.',
            ]);

            [$cliente, $tipoDte] = $this->resolverCliente($empresaId, $datos['cliente'] ?? []);

            $factura = $this->crearFacturaConAsiento($empresaId, $reservaConsumida, $cliente, $tipoDte);

            $dteEstado = $this->intentarEmitirDte($factura);

            $respuesta = [
                'factura_id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'estado' => $factura->estado,
                'dte_estado' => $dteEstado,
                'monto_neto' => (float) $factura->monto_neto,
                'monto_iva' => (float) $factura->monto_iva,
                'monto_bruto' => (float) $factura->monto_bruto,
            ];

            try {
                IntegracionVentaIdempotencia::create([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'factura_id' => $factura->id,
                    'respuesta_status' => 201,
                    'respuesta_json' => $respuesta,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Carrera: otra request con la misma clave gano el insert primero -> esta
                // transaccion completa (factura, asiento, movimiento, consumo de reserva) se
                // revierte al relanzar, y el caller debe reintentar para recibir la respuesta
                // ya persistida por el ganador.
                throw VentaIntegracionException::idempotenciaEnCarrera($e);
            }

            return $respuesta;
        });
    }

    private function validarItemsContraReserva(ReservaInventario $reserva, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $detalle = $reserva->detalles->first();

        if ($detalle === null) {
            throw ValidationException::withMessages(['items' => 'La reserva no tiene detalles.']);
        }

        if (count($items) !== 1) {
            throw ValidationException::withMessages([
                'items' => 'Esta reserva cubre un unico sku; envie exactamente un item.',
            ]);
        }

        $item = $items[0];
        $sku = trim((string) ($item['sku'] ?? ''));
        $cantidad = (float) ($item['cantidad'] ?? 0);

        if ($sku !== '' && $sku !== $detalle->producto?->sku) {
            throw ValidationException::withMessages(['items.0.sku' => 'El sku no coincide con el de la reserva.']);
        }

        if ($cantidad > 0 && round($cantidad, 4) !== round((float) $detalle->cantidadPendiente(), 4)) {
            throw ValidationException::withMessages(['items.0.cantidad' => 'La cantidad no coincide con la cantidad pendiente de la reserva.']);
        }
    }

    private function crearFacturaConAsiento(int $empresaId, ReservaInventario $reserva, Cliente $cliente, int $tipoDte): Factura
    {
        $detalle = $reserva->detalles->first();

        if ($detalle === null || $detalle->producto === null) {
            throw ValidationException::withMessages(['reserva_id' => 'La reserva no tiene un detalle de producto valido.']);
        }

        // ReservaInventario::detalles.producto viene con select() acotado (cargarRelacionesReserva
        // en InventarioReservaService solo trae id/sku/nombre/activo/...), sin precio_venta_neto
        // ni afecto_iva -> hay que recargar el Producto completo, no reusar esa relacion.
        $producto = Producto::findOrFail($detalle->producto_id);
        $cantidad = (float) $detalle->cantidad_reservada;
        $precioNeto = (float) $producto->precio_venta_neto;
        $montoNeto = round($precioNeto * $cantidad, 2);
        $afecta = (bool) $producto->afecto_iva;
        $montoIva = $afecta ? round($montoNeto * (float) config('fiscal.tasa_iva'), 2) : 0.0;
        $montoBruto = $montoNeto + $montoIva;

        $numeroFactura = sprintf('FV-INT-%06d', $this->contadorService->siguienteNumero($empresaId, 'venta_integracion'));

        $factura = Factura::create([
            'empresa_id' => $empresaId,
            'codigo_unico' => Factura::generarCodigoUnico(),
            'cliente_id' => $cliente->id,
            'numero_factura' => $numeroFactura,
            'tipo' => 'VENTA',
            'tipo_documento' => $tipoDte === self::TIPO_DTE_BOLETA ? 'BOLETA' : 'FACTURA',
            'tipo_dte' => $tipoDte,
            'fecha_emision' => now()->toDateString(),
            'monto_neto' => $montoNeto,
            'monto_iva' => $montoIva,
            'monto_bruto' => $montoBruto,
            'estado' => 'REGISTRADA',
        ]);

        FacturaDetalle::create([
            'factura_id' => $factura->id,
            'numero_linea' => 1,
            'producto_id' => $producto->id,
            'nombre_item' => $producto->nombre,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioNeto,
            'monto_item' => $montoNeto,
            'exento' => ! $afecta,
        ]);

        $costoVenta = (float) $reserva->consumos->sum(
            fn (\App\Domains\Inventario\Models\ReservaConsumoInventario $consumo) => (float) ($consumo->movimiento->costo_total ?? 0)
        );

        $this->registrarAsientoVenta($empresaId, $factura, $montoNeto, $montoIva, $montoBruto, $costoVenta);

        return $factura->fresh();
    }

    private function registrarAsientoVenta(int $empresaId, Factura $factura, float $montoNeto, float $montoIva, float $montoBruto, float $costoVenta): void
    {
        $cuentaCxC = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '152005')->first();
        $cuentaVentas = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '501105')->first();
        $cuentaIvaDebito = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '353360')->first();

        if (! $cuentaCxC || ! $cuentaVentas || ! $cuentaIvaDebito) {
            throw ValidationException::withMessages([
                'empresa' => 'Configuración contable incompleta: para facturar una venta se requieren las cuentas de Clientes (152005), Ventas (501105) e IVA Débito (353360) en el plan de cuentas.',
            ]);
        }

        $detallesAsiento = [
            ['cuenta_contable' => '152005', 'debe' => $montoBruto, 'haber' => 0, 'glosa_detalle' => "CxC Cliente Venta {$factura->numero_factura}"],
            ['cuenta_contable' => '501105', 'debe' => 0, 'haber' => $montoNeto, 'glosa_detalle' => "Ingreso por Venta {$factura->numero_factura}"],
        ];

        if ($montoIva > 0) {
            $detallesAsiento[] = ['cuenta_contable' => '353360', 'debe' => 0, 'haber' => $montoIva, 'glosa_detalle' => "IVA Débito Venta {$factura->numero_factura}"];
        }

        if ($costoVenta > 0) {
            $cuentaCostoVenta = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '601205')->first();
            $cuentaInventario = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '151005')->first();

            if (! $cuentaCostoVenta || ! $cuentaInventario) {
                throw ValidationException::withMessages([
                    'empresa' => 'Configuración contable incompleta: para facturar una venta con productos de Inventario se requieren las cuentas de Costo de Venta (601205) e Inventario (151005) en el plan de cuentas.',
                ]);
            }

            $detallesAsiento[] = ['cuenta_contable' => '601205', 'debe' => $costoVenta, 'haber' => 0, 'glosa_detalle' => "Costo de Venta {$factura->numero_factura}"];
            $detallesAsiento[] = ['cuenta_contable' => '151005', 'debe' => 0, 'haber' => $costoVenta, 'glosa_detalle' => "Salida de Inventario Venta {$factura->numero_factura}"];
        }

        $asiento = app(AsientoContableService::class)->registrarAsiento([
            'empresa_id' => $empresaId,
            'fecha' => now()->toDateString(),
            'glosa' => "Centralización Venta Integración - {$factura->numero_factura}",
            'tipo_asiento' => 'ingreso',
            'origen_modulo' => 'integraciones_ventas',
            'origen_id' => $factura->id,
        ], $detallesAsiento);

        $factura->update(['comprobante_contable' => $asiento->numero_comprobante]);
    }

    /** @return array{0: Cliente, 1: int} */
    private function resolverCliente(int $empresaId, array $datosCliente): array
    {
        $rut = trim((string) ($datosCliente['rut'] ?? ''));
        $esBoleta = $rut === '';
        $rutBusqueda = $esBoleta ? self::RUT_GENERICO_BOLETA : $rut;
        $nombre = trim((string) ($datosCliente['nombre'] ?? '')) ?: ($esBoleta ? 'Consumidor Final' : $rut);
        $email = $datosCliente['email'] ?? null;

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->whereBlind('rut', 'cliente_rut_index', $rutBusqueda)
            ->first();

        if ($cliente === null) {
            $cliente = Cliente::create([
                'empresa_id' => $empresaId,
                'rut' => $rutBusqueda,
                'razon_social' => $nombre,
                'contacto_email' => $email,
                'email' => $email,
                'estado' => 'ACTIVO',
            ]);
        }

        return [$cliente, $esBoleta ? self::TIPO_DTE_BOLETA : self::TIPO_DTE_FACTURA];
    }

    /** No lanza si la emision falla: la venta ya esta creada y consistente; queda "pendiente" y se reintenta con el mecanismo existente del dominio Sii (POST /api/sii/facturas/{id}/reintentar -> ReintentarEmisionFacturaService), sin plomeria nueva porque esta Factura es indistinguible de una manual. */
    private function intentarEmitirDte(Factura $factura): string
    {
        try {
            $this->emitirDte->dispatch($factura, [], 'automatico', null);

            return 'pendiente';
        } catch (FacturaNoEmisibleException $e) {
            Log::warning('Venta de integración creada pero el DTE no pudo encolarse; queda pendiente de reintento manual.', [
                'factura_id' => $factura->id,
                'empresa_id' => $factura->empresa_id,
                'razon' => $e->razon,
            ]);

            return 'error_encolando';
        }
    }

    private function bodegaVentaPorDefecto(int $empresaId): Bodega
    {
        $bodega = Bodega::where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVA')
            ->orderBy('id')
            ->first();

        if ($bodega === null) {
            throw InventarioException::regla('No hay ninguna bodega activa configurada en Inventario para reservar stock.');
        }

        return $bodega;
    }

    private function resolverClaveIdempotencia(?string $idempotencyKey, array $datos, int $reservaId): string
    {
        $clave = $idempotencyKey ?? ($datos['referencia_externa'] ?? null);
        $clave = is_string($clave) ? trim($clave) : null;

        return $clave !== null && $clave !== '' ? $clave : "reserva-{$reservaId}";
    }

    private function normalizarReferencia(mixed $referencia): ?string
    {
        if ($referencia === null) {
            return null;
        }

        $referencia = trim((string) $referencia);

        return $referencia === '' ? null : mb_substr($referencia, 0, 120);
    }

    /**
     * Actor no persistido (User::exists = false) usado solo para satisfacer las firmas de
     * InventarioReservaService/InventarioPermisoService; ver docblock de la clase.
     */
    private function actorSistema(int $empresaId): User
    {
        $usuario = new User();
        $usuario->forceFill([
            'empresa_id' => $empresaId,
            'empresa_activa_id' => $empresaId,
        ]);

        $rol = new Rol();
        $rol->forceFill([
            'nombre' => 'Integración API (sistema)',
            'jerarquia' => 100,
            'permisos' => [],
        ]);

        $usuario->setRelation('rol', $rol);

        return $usuario;
    }
}
