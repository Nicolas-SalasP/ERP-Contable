<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Models\SiiDteEmitido;

/** Genera el Libro de Compras y Ventas (LCV) electrónico (Res. Ex. SII N°45/2003): ventas desde sii_dte_emitido, compras desde facturas tipo=COMPRA. */
class LibroComprasVentasService
{
    /** @var array<int, string> */
    private const TIPO_DTE_GLOSA = [
        33  => 'Factura Afecta',
        34  => 'Factura Exenta',
        39  => 'Boleta Electrónica',
        41  => 'Boleta Exenta',
        46  => 'Factura de Compra',
        52  => 'Guía de Despacho',
        56  => 'Nota de Débito',
        61  => 'Nota de Crédito',
        110 => 'Factura de Exportación',
        111 => 'Nota de Débito Exportación',
        112 => 'Nota de Crédito Exportación',
    ];

    /**
     * Genera el Libro de Ventas del período indicado desde sii_dte_emitido.
     *
     * @return array{
     *   periodo: string,
     *   empresa: array{razon_social: string, rut: string},
     *   tipo: string,
     *   lineas: list<array{
     *     tipo_dte: int,
     *     tipo_dte_glosa: string,
     *     folio: string,
     *     rut_receptor: string,
     *     razon_social: string,
     *     fecha_emision: string,
     *     monto_neto: int,
     *     iva: int,
     *     monto_exento: int,
     *     monto_total: int,
     *     estado: string
     *   }>,
     *   totales: array{monto_neto: int, iva: int, monto_exento: int, monto_total: int, cantidad: int}
     * }
     */
    public function generarVentas(int $empresaId, int $mes, int $anio): array
    {
        /** @var Empresa $empresa */
        $empresa = Empresa::findOrFail($empresaId);

        $dtes = SiiDteEmitido::where('empresa_id', $empresaId)
            ->whereYear('fecha_emision', $anio)
            ->whereMonth('fecha_emision', $mes)
            ->whereNotIn('estado', [
                SiiDteEmitido::ESTADO_BORRADOR,
                SiiDteEmitido::ESTADO_RECHAZADO,
                SiiDteEmitido::ESTADO_ANULADO_FALLO_INTERNO,
            ])
            ->orderBy('fecha_emision')
            ->orderBy('folio')
            ->get();

        $lineas    = [];
        $totNeto   = 0;
        $totIva    = 0;
        $totExento = 0;
        $totTotal  = 0;

        foreach ($dtes as $dte) {
            $neto   = (int) round((float) ($dte->monto_neto ?? 0));
            $iva    = (int) round((float) ($dte->iva ?? 0));
            $exento = (int) round((float) ($dte->monto_exento ?? 0));
            $total  = (int) round((float) $dte->monto_total);
            $tipo   = (int) $dte->tipo_dte;

            $totNeto   += $neto;
            $totIva    += $iva;
            $totExento += $exento;
            $totTotal  += $total;

            $lineas[] = [
                'tipo_dte'       => $tipo,
                'tipo_dte_glosa' => self::TIPO_DTE_GLOSA[$tipo] ?? "Tipo {$tipo}",
                'folio'          => (string) $dte->folio,
                'rut_receptor'   => $dte->receptor_rut,
                'razon_social'   => $dte->receptor_razon_social,
                'fecha_emision'  => $dte->fecha_emision->format('Y-m-d'),
                'monto_neto'     => $neto,
                'iva'            => $iva,
                'monto_exento'   => $exento,
                'monto_total'    => $total,
                'estado'         => $dte->estado,
            ];
        }

        return [
            'periodo' => sprintf('%02d/%d', $mes, $anio),
            'empresa' => [
                'razon_social' => (string) $empresa->razon_social,
                'rut'          => (string) $empresa->rut,
            ],
            'tipo'    => 'VENTAS',
            'lineas'  => $lineas,
            'totales' => [
                'monto_neto'   => $totNeto,
                'iva'          => $totIva,
                'monto_exento' => $totExento,
                'monto_total'  => $totTotal,
                'cantidad'     => count($lineas),
            ],
        ];
    }

    /**
     * Genera el Libro de Compras del período indicado desde facturas tipo=COMPRA, excluyendo documentos del exterior.
     *
     * @return array{
     *   periodo: string,
     *   empresa: array{razon_social: string, rut: string},
     *   tipo: string,
     *   lineas: list<array{
     *     tipo_dte: int,
     *     tipo_dte_glosa: string,
     *     numero_factura: string,
     *     rut_emisor: string,
     *     razon_social: string,
     *     fecha_emision: string,
     *     monto_neto: int,
     *     iva_recuperable: int,
     *     monto_no_recuperable: int,
     *     monto_total: int,
     *     indicador_uso: string
     *   }>,
     *   totales: array{monto_neto: int, iva: int, monto_exento: int, monto_total: int, cantidad: int}
     * }
     */
    public function generarCompras(int $empresaId, int $mes, int $anio): array
    {
        /** @var Empresa $empresa */
        $empresa = Empresa::findOrFail($empresaId);

        $facturas = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->where('es_documento_exterior', false)
            ->whereYear('fecha_emision', $anio)
            ->whereMonth('fecha_emision', $mes)
            ->with('proveedor')
            ->orderBy('fecha_emision')
            ->get();

        $lineas    = [];
        $totNeto   = 0;
        $totIva    = 0;
        $totExento = 0;
        $totTotal  = 0;

        foreach ($facturas as $factura) {
            $neto  = (int) round((float) ($factura->monto_neto ?? 0));
            $iva   = (int) round((float) ($factura->monto_iva ?? 0));
            $total = (int) round((float) ($factura->monto_bruto ?? 0));

            $totNeto   += $neto;
            $totIva    += $iva;
            $totTotal  += $total;

            $proveedor = $factura->proveedor;

            $tipoDteRaw = $factura->tipo_documento;
            $tipoDte    = is_numeric($tipoDteRaw) ? (int) $tipoDteRaw : 33;

            $lineas[] = [
                'tipo_dte'             => $tipoDte,
                'tipo_dte_glosa'       => self::TIPO_DTE_GLOSA[$tipoDte] ?? "Tipo {$tipoDte}",
                'numero_factura'       => (string) ($factura->numero_factura ?? ''),
                'rut_emisor'           => $proveedor !== null ? ($proveedor->rut ?? '') : '',
                'razon_social'         => $proveedor !== null ? ($proveedor->razon_social ?? '') : '',
                'fecha_emision'        => $factura->fecha_emision->format('Y-m-d'),
                'monto_neto'           => $neto,
                'iva_recuperable'      => $iva,
                'monto_no_recuperable' => 0,
                'monto_total'          => $total,
                'indicador_uso'        => 'GIRO',
            ];
        }

        return [
            'periodo' => sprintf('%02d/%d', $mes, $anio),
            'empresa' => [
                'razon_social' => (string) $empresa->razon_social,
                'rut'          => (string) $empresa->rut,
            ],
            'tipo'    => 'COMPRAS',
            'lineas'  => $lineas,
            'totales' => [
                'monto_neto'   => $totNeto,
                'iva'          => $totIva,
                'monto_exento' => $totExento,
                'monto_total'  => $totTotal,
                'cantidad'     => count($lineas),
            ],
        ];
    }
}
