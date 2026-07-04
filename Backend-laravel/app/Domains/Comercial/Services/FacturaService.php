<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Services\AnticipoProveedorService;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Integracion\EmitirDteDesdeFacturaService;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use Illuminate\Support\Facades\DB;
use \Carbon\Carbon;

class FacturaService
{
    protected $asientoService;
    protected AnticipoProveedorService $anticipoService;

    public function __construct(AsientoContableService $asientoService, AnticipoProveedorService $anticipoService)
    {
        $this->asientoService = $asientoService;
        $this->anticipoService = $anticipoService;
    }

    public function obtenerFacturasPaginadas(int $empresaId, array $filtros)
    {
        $query = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria']);

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['proveedor_id'])) {
            // proveedor_id identifica tanto al proveedor (COMPRA) como al cliente (VENTA).
            $query->where('proveedor_id', $filtros['proveedor_id']);
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

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_emision', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_emision', '<=', $filtros['fecha_hasta']);
        }

        // Tope duro de 500 para evitar que un limit arbitrario en query string
        // vuelva a convertir esto en un dump completo de la tabla.
        $limit = min((int) ($filtros['limit'] ?? 10), 500);
        return $query->orderBy('fecha_emision', 'desc')->paginate($limit);
    }

    public function obtenerFacturaPorId(int $empresaId, int $facturaId)
    {
        $factura = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria'])
            ->find($facturaId);

        if (!$factura) {
            throw ComercialException::noEncontrado("La factura solicitada no existe o no pertenece a su empresa.");
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
        $esDocumentoExterior = (bool) ($datos['es_documento_exterior'] ?? false);

        if ($esDocumentoExterior) {
            if (empty($datos['tipo_cambio']) || (float) $datos['tipo_cambio'] <= 0) {
                throw ComercialException::regla("Para documentos del exterior, debe especificar el tipo de cambio (mayor a 0).");
            }
            if (empty($datos['moneda']) || $datos['moneda'] === 'CLP') {
                throw ComercialException::regla("Para documentos del exterior, debe especificar una moneda extranjera (USD, EUR, etc.).");
            }
            $tipoCambio = round((float) $datos['tipo_cambio'], 4);
            $montoOrigen = round((float) ($datos['monto_bruto_origen'] ?? $datos['monto_bruto']), 2);
            $bruto = round($montoOrigen * $tipoCambio);
            $neto = $bruto;
            $iva = 0;
        } else {
            if (!isset($datos['monto_neto']) || $datos['monto_neto'] <= 0) {
                throw ComercialException::regla("El monto neto debe ser mayor a 0.");
            }
            $neto = round((float) $datos['monto_neto'], 2);
            $iva = isset($datos['monto_iva']) ? round((float) $datos['monto_iva'], 2) : round($neto * config('fiscal.tasa_iva'), 2);
            $bruto = isset($datos['monto_bruto']) ? round((float) $datos['monto_bruto'], 2) : round($neto + $iva, 2);
            if (abs(($neto + $iva) - $bruto) > 0.01) {
                throw ComercialException::regla("Inconsistencia tributaria: El Neto + IVA no coincide con el Monto Bruto.");
            }
        }

        // Retención Art. 59 LIR: obligación de retener al pagar servicios al exterior.
        // El retenido se entera al SII vía Formulario 50.
        $tipoGastoArt59 = null;
        $retencionArt59 = 0.0;
        if ($esDocumentoExterior && !empty($datos['tipo_gasto_art59'])) {
            $tasasArt59 = [
                'intereses'          => 4.0,   // Art. 59 N°1
                'regalias'           => 30.0,  // Art. 59 N°2
                'servicios_tecnicos' => 15.0,  // Art. 59 N°2 (con convenio)
                'honorarios'         => 15.0,  // Art. 59 N°2
                'otros'              => 35.0,  // Art. 59 N°4
            ];
            $tipoGastoArt59 = $datos['tipo_gasto_art59'];
            if (!array_key_exists($tipoGastoArt59, $tasasArt59)) {
                throw ComercialException::regla("Tipo de gasto Art. 59 inválido. Use: intereses, regalias, servicios_tecnicos, honorarios u otros.");
            }
            $retencionArt59 = round($bruto * $tasasArt59[$tipoGastoArt59] / 100, 2);
        }

        // Validar pertenencia antes de abrir la transacción (fail-fast).
        if (!empty($datos['proveedor_id'])) {
            $proveedorValido = Proveedor::where('empresa_id', $datos['empresa_id'])
                ->where('id', $datos['proveedor_id'])
                ->exists();
            if (!$proveedorValido) {
                throw ComercialException::regla("El proveedor indicado no pertenece a esta empresa.");
            }
        }

        if (!empty($datos['cuenta_bancaria_id'])) {
            $cuentaValida = CuentaBancariaEmpresa::where('empresa_id', $datos['empresa_id'])
                ->where('id', $datos['cuenta_bancaria_id'])
                ->exists();
            if (!$cuentaValida) {
                throw ComercialException::regla("La cuenta bancaria indicada no pertenece a esta empresa.");
            }
        }

        return DB::transaction(function () use ($datos, $neto, $iva, $bruto, $esDocumentoExterior, $tipoGastoArt59, $retencionArt59) {
            $existe = $this->verificarDuplicado($datos['empresa_id'], $datos['proveedor_id'], $datos['numero_factura']);

            if ($existe) {
                throw ComercialException::regla("La factura {$datos['numero_factura']} ya se encuentra registrada para este proveedor.");
            }

            $codigoUnico = Factura::generarCodigoUnico();

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
                'moneda' => $datos['moneda'] ?? 'CLP',
                'tipo_cambio' => $datos['tipo_cambio'] ?? null,
                'monto_bruto_origen' => $datos['monto_bruto_origen'] ?? null,
                'es_documento_exterior' => $esDocumentoExterior,
                'tipo_gasto_art59' => $tipoGastoArt59,
                'retencion_art59' => $retencionArt59 > 0 ? $retencionArt59 : null,
                'factura_referencia_id' => $datos['factura_referencia_id'] ?? null,
                'razon_nota_credito' => $datos['razon_nota_credito'] ?? null,
            ]);

            $esNotaCredito = in_array($datos['tipo_documento'] ?? '', ['NOTA_CREDITO', 'NOTA_CREDITO_EXPORTACION']);
            if ($esNotaCredito) {
                $codigoDestino = $datos['cuentaDestino'] ?? null;
                $codigoIva = $datos['cuentaIva'] ?? '353350';
                $codigoProveedor = $datos['cuentaProveedor'] ?? '352105';
            } else {
                $codigoDestino = $datos['cuentaDestino'] ?? throw ComercialException::regla("Debe especificar la cuenta de destino/gasto.");
                $codigoIva = $datos['cuentaIva'] ?? '353350';
                $codigoProveedor = $datos['cuentaProveedor'] ?? '352105';
            }

            $cuentaIva = $codigoIva ? PlanCuenta::where('empresa_id', $datos['empresa_id'])->where('codigo', $codigoIva)->first() : null;
            $cuentaProveedor = $codigoProveedor ? PlanCuenta::where('empresa_id', $datos['empresa_id'])->where('codigo', $codigoProveedor)->first() : null;
            $cuentaGasto = $codigoDestino ? PlanCuenta::where('empresa_id', $datos['empresa_id'])->where('codigo', $codigoDestino)->first() : null;
            // Antes, una NOTA_CREDITO con cuentas faltantes se registraba SIN asiento
            // (return silencioso), dejando la contabilidad descuadrada. Ahora exige las
            // cuentas igual que una factura normal y genera su asiento (invertido).
            if (!$cuentaGasto || !$cuentaIva || !$cuentaProveedor) {
                throw ComercialException::regla("Configuración Contable Incompleta: Verifique que las cuentas de IVA ({$codigoIva}), Proveedor ({$codigoProveedor}) y Destino ({$codigoDestino}) existan en el plan de cuentas de esta empresa.");
            }

            // Validar cuenta de retención Art. 59 si corresponde
            $cuentaRetencionArt59 = null;
            if ($retencionArt59 > 0) {
                $codigoRetencion = $datos['cuentaRetencion'] ?? '252200';
                $cuentaRetencionArt59 = PlanCuenta::where('empresa_id', $datos['empresa_id'])->where('codigo', $codigoRetencion)->first();
                if (!$cuentaRetencionArt59) {
                    throw ComercialException::regla("Cuenta de Retención Art. 59 ({$codigoRetencion}) no existe en el plan de cuentas. Créela como pasivo circulante antes de registrar esta factura.");
                }
            }

            $fechaOperacion = $datos['fechaContable'] ?? $datos['fecha_emision'];

            $cabeceraAsiento = [
                'empresa_id' => $datos['empresa_id'],
                'fecha' => $fechaOperacion,
                'glosa' => ($esDocumentoExterior ? "Centralización Automática Factura Exterior N° " : ($esNotaCredito ? "Centralización Automática Nota de Crédito Compra N° " : "Centralización Automática Factura Compra N° ")) . $datos['numero_factura'],
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'compras',
                'origen_id' => $factura->id,
                'usuario_id' => auth()->id() ?? $datos['autorizador_id'] ?? null,
            ];

            // Una factura de compra debita gasto + IVA y acredita la CxP del proveedor.
            // Una NOTA DE CRÉDITO de compra invierte los signos: reduce el gasto y el
            // IVA crédito (HABER) y disminuye la CxP del proveedor (DEBE).
            $detallesAsiento = [];

            // Gasto / Destino
            $detallesAsiento[] = [
                'cuenta_contable' => $cuentaGasto->codigo,
                'debe' => $esNotaCredito ? 0 : $neto,
                'haber' => $esNotaCredito ? $neto : 0,
                'glosa_detalle' => ($esNotaCredito ? "Reversa Gasto NC N° " : "Gasto Factura N° ") . $datos['numero_factura'],
                'centro_costo_id' => $datos['centro_costo_id'] ?? null
            ];

            // IVA Crédito Fiscal
            if ($iva > 0) {
                $detallesAsiento[] = [
                    'cuenta_contable' => $cuentaIva->codigo,
                    'debe' => $esNotaCredito ? 0 : $iva,
                    'haber' => $esNotaCredito ? $iva : 0,
                    'glosa_detalle' => ($esNotaCredito ? "Reversa IVA CF NC N° " : "IVA CF Factura N° ") . $datos['numero_factura'],
                ];
            }

            // Cuenta por Pagar (neto de retención Art. 59 si aplica)
            $montoNoCxP = $retencionArt59 > 0 ? $bruto - $retencionArt59 : $bruto;
            $detallesAsiento[] = [
                'cuenta_contable' => $cuentaProveedor->codigo,
                'debe' => $esNotaCredito ? $montoNoCxP : 0,
                'haber' => $esNotaCredito ? 0 : $montoNoCxP,
                'glosa_detalle' => ($esNotaCredito ? "Reducción CxP NC N° " : "CxP Proveedor Factura N° ") . $datos['numero_factura'],
            ];

            // Retención Art. 59 LIR por enterar vía F50
            if ($cuentaRetencionArt59 && $retencionArt59 > 0) {
                $detallesAsiento[] = [
                    'cuenta_contable' => $cuentaRetencionArt59->codigo,
                    'debe' => $esNotaCredito ? $retencionArt59 : 0,
                    'haber' => $esNotaCredito ? 0 : $retencionArt59,
                    'glosa_detalle' => "Retención Art. 59 LIR Factura N° " . $datos['numero_factura'],
                ];
            }

            $asiento = $this->asientoService->registrarAsiento($cabeceraAsiento, $detallesAsiento);

            $factura->update([
                'codigo_interno' => 'FAC-' . str_pad((string) $factura->id, 5, '0', STR_PAD_LEFT),
                'comprobante_contable' => $asiento->numero_comprobante
            ]);

            return $factura;
        });
    }

    /**
     * Emite una Nota de Crédito sobre una factura de VENTA, revirtiendo
     * el asiento de ingreso original (IVA Débito + CxC + Ingresos).
     *
     * Si la factura original tiene DTE firmado y se solicita emisión SII,
     * despacha el DTE tipo 61 con referencia al folio original.
     *
     * @param array{
     *   numero_nc: string,
     *   monto_neto: float,
     *   monto_iva: float,
     *   monto_bruto: float,
     *   fecha_emision?: string,
     *   razon: string,
     *   emitir_dte?: bool,
     * } $datos
     *
     * @throws ComercialException
     */
    public function emitirNotaCreditoVenta(int $empresaId, int $facturaVentaId, array $datos): Factura
    {
        return DB::transaction(function () use ($empresaId, $facturaVentaId, $datos) {
            /** @var Factura|null $origen */
            $origen = Factura::where('empresa_id', $empresaId)
                ->with(['dteEmitido'])
                ->find($facturaVentaId);

            if (!$origen) {
                throw ComercialException::noEncontrado("La factura de origen no existe o no pertenece a esta empresa.");
            }

            if ($origen->estado === 'ANULADA') {
                throw ComercialException::regla("No se puede emitir una NC sobre una factura ya anulada.");
            }

            if ((float) $datos['monto_bruto'] > (float) $origen->monto_bruto) {
                throw ComercialException::regla(
                    "El monto de la NC ($datos[monto_bruto]) no puede superar el monto original ({$origen->monto_bruto})."
                );
            }

            $ncExistente = Factura::where('empresa_id', $empresaId)
                ->where('factura_referencia_id', $origen->id)
                ->where('tipo_documento', 'NOTA_CREDITO')
                ->where('estado', '!=', 'ANULADA')
                ->exists();

            if ($ncExistente) {
                throw ComercialException::regla("Esta factura ya tiene una Nota de Crédito activa. Anule la NC anterior antes de emitir una nueva.");
            }

            $neto  = round((float) $datos['monto_neto'], 2);
            $iva   = round((float) $datos['monto_iva'], 2);
            $bruto = round((float) $datos['monto_bruto'], 2);
            $fecha = $datos['fecha_emision'] ?? now()->toDateString();

            $nc = Factura::create([
                'empresa_id'          => $empresaId,
                'tipo'                => 'VENTA',
                'tipo_documento'      => 'NOTA_CREDITO',
                'tipo_dte'            => 61,
                'codigo_unico'        => Factura::generarCodigoUnico(),
                'cliente_id'          => $origen->cliente_id,
                'proveedor_id'        => $origen->proveedor_id,
                'numero_factura'      => $datos['numero_nc'],
                'fecha_emision'       => $fecha,
                'monto_neto'          => $neto,
                'monto_iva'           => $iva,
                'monto_bruto'         => $bruto,
                'estado'              => 'REGISTRADA',
                'factura_referencia_id' => $origen->id,
                'razon_nota_credito'  => $datos['razon'],
                'moneda'              => $origen->moneda ?? 'CLP',
            ]);

            $nc->update([
                'codigo_interno' => 'NC-' . str_pad((string) $nc->id, 5, '0', STR_PAD_LEFT),
            ]);

            // Cuentas contables usadas en el asiento original de la venta.
            $cuentaCxC      = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '152005')->first();
            $cuentaVentas   = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '501105')->first();
            $cuentaIvaDebito = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '353360')->first();

            if (!$cuentaCxC || !$cuentaVentas) {
                throw ComercialException::regla(
                    "Configuración Contable Incompleta: se requieren cuentas 152005 (CxC) y 501105 (Ingresos) para emitir la NC."
                );
            }

            $detalles = [
                [
                    'cuenta_contable' => $cuentaVentas->codigo,
                    'debe'           => $neto,
                    'haber'          => 0,
                    'glosa_detalle'  => "Reversa Ingreso NC {$datos['numero_nc']}",
                ],
                [
                    'cuenta_contable' => $cuentaCxC->codigo,
                    'debe'           => 0,
                    'haber'          => $bruto,
                    'glosa_detalle'  => "Reducción CxC Cliente NC {$datos['numero_nc']}",
                ],
            ];

            if ($iva > 0 && $cuentaIvaDebito) {
                $detalles[] = [
                    'cuenta_contable' => $cuentaIvaDebito->codigo,
                    'debe'           => $iva,
                    'haber'          => 0,
                    'glosa_detalle'  => "Reversa IVA Débito NC {$datos['numero_nc']}",
                ];
            }

            $asiento = $this->asientoService->registrarAsiento([
                'empresa_id'    => $empresaId,
                'fecha'         => $fecha,
                'glosa'         => "Nota de Crédito Venta N° {$datos['numero_nc']} — {$datos['razon']}",
                'tipo_asiento'  => 'traspaso',
                'origen_modulo' => 'ventas',
                'origen_id'     => $nc->id,
            ], $detalles);

            $nc->update(['comprobante_contable' => $asiento->numero_comprobante]);

            // Si el monto de la NC iguala al total de la factura, la anulamos.
            if (abs($bruto - (float) $origen->monto_bruto) < 0.01) {
                $origen->estado = 'ANULADA';
                $origen->save();

                // Actualizar estado del DTE original en el registro SII.
                if ($origen->sii_dte_emitido_id) {
                    SiiDteEmitido::where('id', $origen->sii_dte_emitido_id)
                        ->update(['estado' => SiiDteEmitido::ESTADO_ANULADO_CON_NC]);
                }
            }

            // Despachar emisión DTE tipo 61 si se solicita y la factura tiene DTE original.
            if (!empty($datos['emitir_dte']) && $origen->sii_dte_emitido_id) {
                /** @var SiiDteEmitido|null $dteOrigen */
                $dteOrigen = $origen->dteEmitido;
                if ($dteOrigen && $dteOrigen->folio > 0) {
                    $referencias = [[
                        'tipo_doc' => (int) ($origen->tipo_dte ?? 33),
                        'folio_ref' => (string) $dteOrigen->folio,
                        'fecha_ref' => $origen->fecha_emision->format('Y-m-d'),
                        'cod_ref'   => 1, // 1 = Anula documento de referencia (SII)
                        'razon_ref' => $datos['razon'],
                    ]];

                    app(EmitirDteDesdeFacturaService::class)->dispatch(
                        $nc->fresh(),
                        $referencias,
                        'manual',
                        auth()->id()
                    );
                }
            }

            return $nc->load(['facturaOrigen']);
        });
    }

    /**
     * Emite una Nota de Débito sobre una factura de VENTA, registrando
     * un cargo adicional al cliente (aumento de CxC, Ventas e IVA Débito).
     *
     * @param array{
     *   numero_nd: string,
     *   monto_neto: float,
     *   monto_iva: float,
     *   monto_bruto: float,
     *   fecha_emision?: string,
     *   razon: string,
     *   emitir_dte?: bool,
     * } $datos
     *
     * @throws ComercialException
     */
    public function emitirNotaDebitoVenta(int $empresaId, int $facturaVentaId, array $datos): Factura
    {
        return DB::transaction(function () use ($empresaId, $facturaVentaId, $datos) {
            /** @var Factura|null $origen */
            $origen = Factura::where('empresa_id', $empresaId)->find($facturaVentaId);

            if (!$origen) {
                throw ComercialException::noEncontrado("La factura de origen no existe o no pertenece a esta empresa.");
            }

            if ($origen->estado === 'ANULADA') {
                throw ComercialException::regla("No se puede emitir una ND sobre una factura anulada.");
            }

            $neto  = round((float) $datos['monto_neto'], 2);
            $iva   = round((float) $datos['monto_iva'], 2);
            $bruto = round((float) $datos['monto_bruto'], 2);
            $fecha = $datos['fecha_emision'] ?? now()->toDateString();

            $nd = Factura::create([
                'empresa_id'            => $empresaId,
                'tipo'                  => 'VENTA',
                'tipo_documento'        => 'NOTA_DEBITO',
                'tipo_dte'              => 56,
                'codigo_unico'          => Factura::generarCodigoUnico(),
                'cliente_id'            => $origen->cliente_id,
                'proveedor_id'          => $origen->proveedor_id,
                'numero_factura'        => $datos['numero_nd'],
                'fecha_emision'         => $fecha,
                'monto_neto'            => $neto,
                'monto_iva'             => $iva,
                'monto_bruto'           => $bruto,
                'estado'                => 'REGISTRADA',
                'factura_referencia_id' => $origen->id,
                'razon_nota_debito'     => $datos['razon'],
                'moneda'                => $origen->moneda ?? 'CLP',
            ]);

            $nd->update([
                'codigo_interno' => 'ND-' . str_pad((string) $nd->id, 5, '0', STR_PAD_LEFT),
            ]);

            // Cuentas contables para el asiento de la ND:
            // DEBE CxC (aumenta la deuda del cliente)
            // HABER Ventas + HABER IVA Débito (ingreso adicional)
            $cuentaCxC       = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '152005')->first();
            $cuentaVentas    = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '501105')->first();
            $cuentaIvaDebito = PlanCuenta::where('empresa_id', $empresaId)->where('codigo', '353360')->first();

            if (!$cuentaCxC || !$cuentaVentas) {
                throw ComercialException::regla(
                    "Configuración Contable Incompleta: se requieren cuentas 152005 (CxC) y 501105 (Ventas) para emitir la ND."
                );
            }

            $detalles = [
                [
                    'cuenta_contable' => $cuentaVentas->codigo,
                    'debe'            => 0,
                    'haber'           => $neto,
                    'glosa_detalle'   => "Ingreso adicional ND {$datos['numero_nd']} — {$datos['razon']}",
                ],
                [
                    'cuenta_contable' => $cuentaCxC->codigo,
                    'debe'            => $bruto,
                    'haber'           => 0,
                    'glosa_detalle'   => "Aumento CxC ND {$datos['numero_nd']} — cliente {$origen->nombre_proveedor}",
                ],
            ];

            if ($iva > 0 && $cuentaIvaDebito) {
                $detalles[] = [
                    'cuenta_contable' => $cuentaIvaDebito->codigo,
                    'debe'            => 0,
                    'haber'           => $iva,
                    'glosa_detalle'   => "IVA Débito ND {$datos['numero_nd']}",
                ];
            }

            $asiento = $this->asientoService->registrarAsiento([
                'empresa_id'    => $empresaId,
                'fecha'         => $fecha,
                'glosa'         => "Nota de Débito Venta N° {$datos['numero_nd']} — {$datos['razon']}",
                'tipo_asiento'  => 'traspaso',
                'origen_modulo' => 'ventas',
                'origen_id'     => $nd->id,
            ], $detalles);

            $nd->update(['comprobante_contable' => $asiento->numero_comprobante]);

            return $nd->load(['facturaOrigen']);
        });
    }

    public function obtenerAsientoDeFactura(int $empresaId, int $facturaId): array
    {
        $factura = Factura::where('empresa_id', $empresaId)->findOrFail($facturaId);

        if (!$factura->comprobante_contable) {
            throw ComercialException::regla('Esta factura aún no tiene un asiento contable vinculado.');
        }

        $asiento = AsientoContable::with(['detalles.cuenta', 'usuario'])
            ->where('empresa_id', $empresaId)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        if (!$asiento) {
            throw ComercialException::regla('El asiento asociado no fue encontrado en la base de datos.');
        }

        return [
            'cabecera' => $asiento,
            'detalles' => $asiento->detalles->map(function ($d) {
                /** @var \App\Domains\Contabilidad\Models\DetalleAsiento $d */
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

            // Guard de integridad: no se puede reclasificar el asiento de una factura
            // anulada (su efecto contable ya fue reversado).
            if (strtoupper((string) $factura->estado) === 'ANULADA') {
                throw ComercialException::regla('No se puede reclasificar el asiento de una factura anulada.');
            }

            if (!$factura->comprobante_contable) {
                throw ComercialException::regla('Esta factura aún no tiene un asiento contable vinculado.');
            }

            $asiento = AsientoContable::with('detalles')
                ->where('empresa_id', $empresaId)
                ->where('numero_comprobante', $factura->comprobante_contable)
                ->firstOrFail();

            // No reclasificar desde ni hacia un periodo contable cerrado.
            $periodos = app(\App\Domains\Contabilidad\Services\PeriodoContableService::class);
            $periodos->assertAbierto($empresaId, $asiento->fecha);
            $periodos->assertAbierto($empresaId, $datos['fecha']);

            $glosaCabeceraOriginal = $asiento->glosa;
            $asiento->update([
                'fecha' => $datos['fecha'],
                'usuario_id' => $usuarioId
            ]);

            foreach ($datos['cambios'] as $detalleId => $nuevoCodigoCuenta) {
                $lineaOriginal = $asiento->detalles->firstWhere('id', $detalleId);

                if ($lineaOriginal) {
                    /** @var \App\Domains\Contabilidad\Models\DetalleAsiento $lineaOriginal */
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
            ->where('estado', '!=', 'ANULADA')
            ->whereNull('proyecto_activo_id')
            ->with('proveedor')
            ->get()
            ->map(function ($f) {
                $nombreProv = $f->proveedor->razon_social ?? 'Proveedor sin nombre';
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
        $factura = Factura::where('empresa_id', $empresaId)->find($facturaId);
        if (!$factura) {
            throw ComercialException::noEncontrado("Factura no encontrada.");
        }
        $factura->update(['proyecto_activo_id' => $proyectoId]);
        return $factura;
    }

    public function obtenerPorProyecto(int $proyectoId): array
    {
        return Factura::where('proyecto_activo_id', $proyectoId)
            ->with('proveedor')
            ->get()
            ->map(function ($f) {
                /** @var \App\Domains\Comercial\Models\Proveedor $prov */
                $prov = $f->proveedor;
                return [
                    'id' => $f->id,
                    'numero' => $f->numero_factura,
                    'proveedor' => $prov->razon_social ?? $prov->rut,
                    'monto' => (float) $f->monto_neto
                ];
            })
            ->toArray();
    }

    public function obtenerAuditoriaCompleta(int $empresaId, int $id): array
    {
        $factura = Factura::where('empresa_id', $empresaId)->with('proveedor')->findOrFail($id);

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
                    'fecha' => $factura->created_at->format('Y-m-d H:i:s'),
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
        // El atajo de "marcar pagada" se deshabilito: marcaba la factura como
        // PAGADA sin generar el asiento de egreso, dejando la cuenta por pagar
        // del proveedor abierta en contabilidad (descuadre subdiario vs mayor).
        // Los pagos deben registrarse desde Tesoreria > Conciliacion Bancaria,
        // que genera el asiento contra la cuenta bancaria real.
        throw ComercialException::regla(
            "Los pagos de facturas se registran desde Tesoreria > Conciliacion Bancaria "
            . "para contabilizar el egreso. Esta accion directa fue deshabilitada."
        );
    }

    public function obtenerFacturasPorIds(int $empresaId, array $ids)
    {
        // Solo se usa para aplicar pagos en la conciliacion bancaria: excluye
        // facturas ya PAGADAS o ANULADAS para evitar pagarlas dos veces y que el
        // excedente se registre erroneamente como anticipo. lockForUpdate evita
        // que la misma factura se pague dos veces si aparece en dos movimientos
        // bancarios distintos conciliados en paralelo.
        return Factura::where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->lockForUpdate()
            ->get();
    }

    public function cambiarEstado(int $empresaId, int $id, string $estado)
    {
        $factura = $this->obtenerFacturaPorId($empresaId, $id);
        $factura->update(['estado' => $estado]);
        return $factura;
    }

    public function obtenerFacturasImpagasPorMontoExacto(int $empresaId, string $tipo, float $monto)
    {
        return Factura::with(['proveedor', 'cliente'])
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('estado', '!=', 'PAGADA')
            ->where('monto_bruto', $monto)
            ->get();
    }

    /**
     * Retorna facturas impagadas cuyo monto bruto esta dentro de la tolerancia indicada.
     * Usado por el motor de sugerencias de conciliacion bancaria.
     */
    public function obtenerSugerenciasBancarias(
        int $empresaId,
        string $tipo,
        float $monto,
        float $toleranciaPct
    ): \Illuminate\Support\Collection {
        // Mapea el tipo de operacion al tipo de factura almacenado en BD
        $tipoFactura = $tipo === 'cobrar' ? 'VENTA' : 'COMPRA';

        $montoMin = $monto * (1 - $toleranciaPct);
        $montoMax = $monto * (1 + $toleranciaPct);

        // JOIN directo para obtener razon_social sin pasar por relaciones Eloquent.
        // Se restringe empresa_id en el join para evitar exposicion de datos cross-tenant.
        return DB::table('facturas')
            ->leftJoin('proveedores', function ($join) use ($empresaId) {
                $join->on('facturas.proveedor_id', '=', 'proveedores.id')
                     ->where('proveedores.empresa_id', '=', $empresaId);
            })
            ->select(
                'facturas.id',
                'facturas.numero_factura',
                'facturas.monto_bruto',
                'facturas.fecha_emision',
                'proveedores.razon_social'
            )
            ->where('facturas.empresa_id', $empresaId)
            ->where('facturas.tipo', $tipoFactura)
            ->whereNotIn('facturas.estado', ['PAGADA', 'ANULADA'])
            ->whereNull('facturas.deleted_at')
            ->whereBetween('facturas.monto_bruto', [$montoMin, $montoMax])
            ->get();
    }

    public function anularFactura(int $empresaId, int $userId, int $facturaId, string $motivo, ?string $fechaAnulacion = null)
    {
        return DB::transaction(function () use ($empresaId, $userId, $facturaId, $motivo, $fechaAnulacion) {

            $factura = Factura::where('empresa_id', $empresaId)->findOrFail($facturaId);

            if ($factura->estado === 'ANULADA') {
                throw ComercialException::regla("Esta factura ya fue anulada anteriormente.");
            }

            if (in_array($factura->estado, ['PAGADA', 'ABONADA'], true)) {
                throw ComercialException::regla("No se puede anular una factura que ya tiene pagos aplicados en Tesorería. Debe reversar los pagos primero.");
            }

            if ($factura->comprobante_contable) {
                $this->asientoService->reversarAsiento(
                    $empresaId,
                    $userId,
                    $factura->comprobante_contable,
                    "Reversa automática por anulación de factura N° {$factura->numero_factura}. Motivo: {$motivo}",
                    $fechaAnulacion
                );
            }
            
            $factura->estado = 'ANULADA';

            if ($factura->proyecto_activo_id) {
                $factura->proyecto_activo_id = null;
            }

            $factura->save();

            // Libera cualquier anticipo que se hubiese aplicado a esta factura
            // (quedaban consumidos permanentemente aunque la deuda ya no existiera).
            $this->anticipoService->revertirAplicacionesDeFactura($empresaId, $facturaId);

            // Si la factura vino de convertir una cotización, esta quedaba
            // "Facturada" para siempre al anularla, sin poder refacturarse.
            if ($factura->cotizacion_id) {
                $estadoAceptada = EstadoCotizacion::where('nombre', 'Aceptada')->first();
                if ($estadoAceptada) {
                    Cotizacion::where('empresa_id', $empresaId)
                        ->where('id', $factura->cotizacion_id)
                        ->update(['estado_id' => $estadoAceptada->id]);
                }
            }

            return $factura;
        });
    }

    public function obtenerVencidas(int $empresaId)
    {
        return Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->with('proveedor')
            ->orderBy('fecha_vencimiento', 'asc')
            ->get();
    }

    public function generarCsvExportacion(int $empresaId): string
    {
        $facturas = Factura::where('empresa_id', $empresaId)
            ->with('proveedor')
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $csvData = "ID,Numero Factura,Proveedor,RUT,Fecha Emision,Fecha Vencimiento,Monto Neto,IVA,Monto Bruto,Estado\n";

        foreach ($facturas as $f) {
            $provNombre = $f->proveedor->razon_social ?? 'Sin Proveedor';
            $provRut = $f->proveedor->rut ?? 'N/A';
            $emision = $f->fecha_emision->format('Y-m-d');
            $vcto = $f->fecha_vencimiento ? $f->fecha_vencimiento->format('Y-m-d') : '';
            $csvData .= "{$f->id},"
                . $this->escaparCampoCsv((string) $f->numero_factura) . ","
                . $this->escaparCampoCsv($provNombre) . ","
                . $this->escaparCampoCsv($provRut) . ","
                . "{$emision},{$vcto},{$f->monto_neto},{$f->monto_iva},{$f->monto_bruto},{$f->estado}\n";
        }

        return $csvData;
    }

    private function escaparCampoCsv(string $valor): string
    {
        $valor = str_replace('"', '""', $valor);
        if (preg_match('/^[=+\-@\t\r]/', $valor)) {
            $valor = "'" . $valor;
        }
        return '"' . $valor . '"';
    }
}