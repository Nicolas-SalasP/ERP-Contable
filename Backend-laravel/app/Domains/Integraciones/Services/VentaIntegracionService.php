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
use App\Domains\Integraciones\Exceptions\VentaIntegracionException;
use App\Domains\Integraciones\Models\IntegracionVentaIdempotencia;
use App\Domains\Inventario\Exceptions\InventarioException;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ProductoSerie;
use App\Domains\Inventario\Models\ReservaConsumoInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Services\InventarioReservaService;
use App\Domains\Sii\Exceptions\FacturaNoEmisibleException;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Integracion\EmitirDteDesdeFacturaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
 *
 * Serie por unidad (Fase 4, RMA): `items[].numero_serie` es opcional y best-effort. Decision
 * explicita de diseno: capturar la serie de forma obligatoria/validada en el momento de la venta
 * hubiera significado tocar el flujo de checkout ya cerrado y verificado (reserva -> consumo ->
 * factura) para algo que solo importa si el producto se devuelve -- demasiado invasivo para el
 * beneficio. Si el producto `requiere_serie` y no llega ninguna, se registra un warning (no se
 * bloquea la venta); si llega, se guarda como "vendido". La devolucion
 * (DevolucionIntegracionService) es quien realmente exige la serie y, si nunca se registro aca,
 * la crea recien ahi en el momento del RMA.
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
    ) {}

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

    /** @return array{factura_id: int, numero_factura: string, estado: string, tipo_dte: int, dte_estado: string, monto_neto: float, monto_iva: float, monto_bruto: float} */
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

            $items = $datos['items'] ?? [];
            $despacho = $datos['despacho'] ?? null;

            $factura = $this->crearFacturaConAsiento($empresaId, $reservaConsumida, $cliente, $tipoDte, $items[0] ?? null, $despacho);

            $this->registrarSeriesVendidas($empresaId, $reservaConsumida, $items, $factura->id);

            $dteEstado = $this->intentarEmitirDte($factura);

            $respuesta = [
                'factura_id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'estado' => $factura->estado,
                'tipo_dte' => (int) $factura->tipo_dte,
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
            } catch (QueryException $e) {
                // Carrera: otra request con la misma clave gano el insert primero -> esta
                // transaccion completa (factura, asiento, movimiento, consumo de reserva) se
                // revierte al relanzar, y el caller debe reintentar para recibir la respuesta
                // ya persistida por el ganador.
                throw VentaIntegracionException::idempotenciaEnCarrera($e);
            }

            return $respuesta;
        });
    }

    /**
     * Estado actual de la factura/DTE, para que el canal externo haga polling: al confirmar() el
     * DTE recien se encola (folio/pdf_url todavia no existen porque el SII no respondio), aca se
     * exponen ni bien SiiDteEmitido los tenga.
     *
     * @return array{factura_id: int, numero_factura: string, estado: string, tipo_dte: int|null, dte_estado: string, folio: int|null, pdf_url: string|null, monto_neto: float, monto_iva: float, monto_bruto: float}
     */
    public function obtenerEstado(int $empresaId, int $facturaId): array
    {
        $factura = Factura::where('empresa_id', $empresaId)
            ->where('id', $facturaId)
            ->firstOrFail();

        $dte = $factura->dteEmitido;

        return [
            'factura_id' => $factura->id,
            'numero_factura' => $factura->numero_factura,
            'estado' => $factura->estado,
            'tipo_dte' => $factura->tipo_dte !== null ? (int) $factura->tipo_dte : null,
            'dte_estado' => $dte !== null ? $dte->estado : 'pendiente',
            'folio' => $dte?->folio,
            'pdf_url' => $this->pdfUrl($dte),
            'monto_neto' => (float) $factura->monto_neto,
            'monto_iva' => (float) $factura->monto_iva,
            'monto_bruto' => (float) $factura->monto_bruto,
        ];
    }

    /**
     * pdf_path (F7, representacion impresa con timbre) todavia no lo genera ningun proceso del
     * ERP -- si algun dia se completa, esto ya queda cableado: URL firmada temporal (7 dias)
     * contra la ruta local.serve de Laravel (disco 'local' es privado, `serve => true` en
     * config/filesystems.php), sin exponer el storage completo.
     */
    private function pdfUrl(?SiiDteEmitido $dte): ?string
    {
        if ($dte === null || $dte->pdf_path === null || ! Storage::disk('local')->exists($dte->pdf_path)) {
            return null;
        }

        return URL::temporarySignedRoute('storage.local', now()->addDays(7), ['path' => $dte->pdf_path]);
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

    /**
     * @param array{sku?: string, cantidad?: float|string, numero_serie?: string, precio_unitario_neto?: float|string|null}|null $itemDatos
     * @param array{monto_neto?: float|string|null}|null $despachoDatos
     */
    private function crearFacturaConAsiento(int $empresaId, ReservaInventario $reserva, Cliente $cliente, int $tipoDte, ?array $itemDatos, ?array $despachoDatos): Factura
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
        $precioListaNeto = (float) $producto->precio_venta_neto;
        $precioNeto = $this->resolverPrecioUnitario($itemDatos, $precioListaNeto);
        $montoNeto = round($precioNeto * $cantidad, 2);
        $afecta = (bool) $producto->afecto_iva;
        $montoIva = $afecta ? round($montoNeto * (float) config('fiscal.tasa_iva'), 2) : 0.0;

        // Despacho: linea de detalle extra, opcional, con el mismo tratamiento de IVA que el
        // resto de la factura (sigue el afecto_iva del producto, no tiene uno propio).
        $montoNetoDespacho = 0.0;
        if (is_array($despachoDatos) && isset($despachoDatos['monto_neto']) && (float) $despachoDatos['monto_neto'] > 0) {
            $montoNetoDespacho = round((float) $despachoDatos['monto_neto'], 2);
        }
        $montoIvaDespacho = $afecta ? round($montoNetoDespacho * (float) config('fiscal.tasa_iva'), 2) : 0.0;

        $montoNetoTotal = $montoNeto + $montoNetoDespacho;
        $montoIvaTotal = $montoIva + $montoIvaDespacho;
        $montoBrutoTotal = $montoNetoTotal + $montoIvaTotal;

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
            'monto_neto' => $montoNetoTotal,
            'monto_iva' => $montoIvaTotal,
            'monto_bruto' => $montoBrutoTotal,
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

        if ($montoNetoDespacho > 0) {
            FacturaDetalle::create([
                'factura_id' => $factura->id,
                'numero_linea' => 2,
                'producto_id' => null,
                'nombre_item' => 'Despacho',
                'cantidad' => 1,
                'precio_unitario' => $montoNetoDespacho,
                'monto_item' => $montoNetoDespacho,
                'exento' => ! $afecta,
            ]);
        }

        $costoVenta = (float) $reserva->consumos->sum(
            fn (ReservaConsumoInventario $consumo) => (float) ($consumo->movimiento->costo_total ?? 0)
        );

        $this->registrarAsientoVenta($empresaId, $factura, $montoNetoTotal, $montoIvaTotal, $montoBrutoTotal, $costoVenta);

        return $factura->fresh();
    }

    /**
     * Precio realmente cobrado por el canal externo, con tope de seguridad: nunca puede superar
     * el precio de lista del producto (evita que un canal comprometido/con bug infle el monto
     * del DTE y habilite una nota de credito fraudulenta despues). Si no viene, se usa el precio
     * de lista (comportamiento historico, compatibilidad hacia atras).
     */
    private function resolverPrecioUnitario(?array $itemDatos, float $precioListaNeto): float
    {
        if ($itemDatos === null || ! array_key_exists('precio_unitario_neto', $itemDatos) || $itemDatos['precio_unitario_neto'] === null) {
            return $precioListaNeto;
        }

        $precioSolicitado = (float) $itemDatos['precio_unitario_neto'];

        if ($precioSolicitado > $precioListaNeto) {
            throw ValidationException::withMessages([
                'items.0.precio_unitario_neto' => "El precio unitario informado ({$precioSolicitado}) no puede superar el precio de lista del producto ({$precioListaNeto}).",
            ]);
        }

        return $precioSolicitado;
    }

    /**
     * Best-effort, ver docblock de la clase: no bloquea la venta si falta la serie de un
     * producto que la requiere, solo lo deja en el log para seguimiento operativo.
     */
    private function registrarSeriesVendidas(int $empresaId, ReservaInventario $reserva, array $items, int $facturaId): void
    {
        $detalle = $reserva->detalles->first();

        if ($detalle === null) {
            return;
        }

        $producto = Producto::find($detalle->producto_id);

        if ($producto === null) {
            return;
        }

        $numeroSerie = null;
        if (! empty($items) && isset($items[0]['numero_serie'])) {
            $numeroSerie = trim((string) $items[0]['numero_serie']);
            $numeroSerie = $numeroSerie !== '' ? $numeroSerie : null;
        }

        if ($numeroSerie === null) {
            if ($producto->requiereSerie()) {
                Log::warning('Venta de integración de un producto que requiere número de serie, pero no se informó ninguna. Queda pendiente de asociarse en la devolución si corresponde.', [
                    'empresa_id' => $empresaId,
                    'producto_id' => $producto->id,
                    'factura_id' => $facturaId,
                ]);
            }

            return;
        }

        ProductoSerie::updateOrCreate(
            ['empresa_id' => $empresaId, 'producto_id' => $producto->id, 'numero_serie' => $numeroSerie],
            ['estado' => ProductoSerie::ESTADO_VENDIDO, 'lote_id' => $detalle->lote_id, 'venta_referencia' => (string) $facturaId]
        );
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
        $usuario = new User;
        $usuario->forceFill([
            'empresa_id' => $empresaId,
            'empresa_activa_id' => $empresaId,
        ]);

        $rol = new Rol;
        $rol->forceFill([
            'nombre' => 'Integración API (sistema)',
            'jerarquia' => 100,
            'permisos' => [],
        ]);

        $usuario->setRelation('rol', $rol);

        return $usuario;
    }
}
