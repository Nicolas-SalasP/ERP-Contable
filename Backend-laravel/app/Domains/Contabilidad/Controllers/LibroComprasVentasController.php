<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\LibroComprasVentasService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** LCV: Libro de Compras y Ventas electrónico (Res. Ex. SII N°45/2003). */
class LibroComprasVentasController extends Controller
{
    public function __construct(
        private readonly LibroComprasVentasService $service,
    ) {}

    public function ventas(Request $request, int $mes, int $anio): JsonResponse
    {
        $this->validarPeriodo($mes, $anio);
        $empresaId = (int) $request->user()->empresa_activa_id;
        $datos = $this->service->generarVentas($empresaId, $mes, $anio);

        return response()->json($datos);
    }

    public function compras(Request $request, int $mes, int $anio): JsonResponse
    {
        $this->validarPeriodo($mes, $anio);
        $empresaId = (int) $request->user()->empresa_activa_id;
        $datos = $this->service->generarCompras($empresaId, $mes, $anio);

        return response()->json($datos);
    }

    public function descargarVentas(Request $request, int $mes, int $anio): Response|StreamedResponse
    {
        $this->validarPeriodo($mes, $anio);
        $empresaId = (int) $request->user()->empresa_activa_id;
        $datos     = $this->service->generarVentas($empresaId, $mes, $anio);
        $formato   = (string) $request->query('formato', 'csv');

        if ($formato === 'excel') {
            return $this->exportarExcelVentas($datos, $mes, $anio);
        }

        return $this->exportarCsvVentas($datos, $mes, $anio);
    }

    public function descargarCompras(Request $request, int $mes, int $anio): Response|StreamedResponse
    {
        $this->validarPeriodo($mes, $anio);
        $empresaId = (int) $request->user()->empresa_activa_id;
        $datos     = $this->service->generarCompras($empresaId, $mes, $anio);
        $formato   = (string) $request->query('formato', 'csv');

        if ($formato === 'excel') {
            return $this->exportarExcelCompras($datos, $mes, $anio);
        }

        return $this->exportarCsvCompras($datos, $mes, $anio);
    }

    private function validarPeriodo(int $mes, int $anio): void
    {
        if ($mes < 1 || $mes > 12) {
            abort(422, 'El mes debe estar entre 1 y 12.');
        }
        if ($anio < 2020 || $anio > 2035) {
            abort(422, 'El año debe estar entre 2020 y 2035.');
        }
    }

    /** @param array<string, mixed> $datos */
    private function exportarCsvVentas(array $datos, int $mes, int $anio): StreamedResponse
    {
        $nombre = sprintf('LCV_VENTAS_%02d%d.csv', $mes, $anio);

        return response()->stream(function () use ($datos): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF"); // BOM UTF-8
            fputcsv($handle, [
                'Tipo DTE', 'Glosa Tipo', 'Folio', 'RUT Receptor', 'Razón Social',
                'Fecha Emisión', 'Monto Neto', 'IVA', 'Monto Exento', 'Monto Total', 'Estado',
            ], ';');
            /** @var list<array<string, mixed>> $lineas */
            $lineas = $datos['lineas'];
            foreach ($lineas as $linea) {
                fputcsv($handle, [
                    $linea['tipo_dte'],
                    $linea['tipo_dte_glosa'],
                    $linea['folio'],
                    $linea['rut_receptor'],
                    $linea['razon_social'],
                    $linea['fecha_emision'],
                    $linea['monto_neto'],
                    $linea['iva'],
                    $linea['monto_exento'],
                    $linea['monto_total'],
                    $linea['estado'],
                ], ';');
            }
            /** @var array<string, int> $totales */
            $totales = $datos['totales'];
            fputcsv($handle, [
                'TOTAL', '', '', '', '', '',
                $totales['monto_neto'],
                $totales['iva'],
                $totales['monto_exento'],
                $totales['monto_total'],
                '',
            ], ';');
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function exportarCsvCompras(array $datos, int $mes, int $anio): StreamedResponse
    {
        $nombre = sprintf('LCV_COMPRAS_%02d%d.csv', $mes, $anio);

        return response()->stream(function () use ($datos): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Tipo DTE', 'Glosa Tipo', 'N° Factura', 'RUT Emisor', 'Razón Social',
                'Fecha Emisión', 'Monto Neto', 'IVA Recuperable', 'Monto No Recuperable',
                'Monto Total', 'Indicador Uso',
            ], ';');
            /** @var list<array<string, mixed>> $lineas */
            $lineas = $datos['lineas'];
            foreach ($lineas as $linea) {
                fputcsv($handle, [
                    $linea['tipo_dte'],
                    $linea['tipo_dte_glosa'],
                    $linea['numero_factura'],
                    $linea['rut_emisor'],
                    $linea['razon_social'],
                    $linea['fecha_emision'],
                    $linea['monto_neto'],
                    $linea['iva_recuperable'],
                    $linea['monto_no_recuperable'],
                    $linea['monto_total'],
                    $linea['indicador_uso'],
                ], ';');
            }
            /** @var array<string, int> $totales */
            $totales = $datos['totales'];
            fputcsv($handle, [
                'TOTAL', '', '', '', '', '',
                $totales['monto_neto'],
                $totales['iva'],
                '',
                $totales['monto_total'],
                '',
            ], ';');
            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function exportarExcelVentas(array $datos, int $mes, int $anio): Response
    {
        $nombre = sprintf('LCV_VENTAS_%02d%d.xls', $mes, $anio);
        /** @var array<string, string> $empresa */
        $empresa = $datos['empresa'];
        /** @var list<array<string, mixed>> $lineas */
        $lineas  = $datos['lineas'];
        /** @var array<string, int> $totales */
        $totales = $datos['totales'];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="LCV Ventas"><Table>';

        $xml .= '<Row><Cell ss:MergeAcross="10"><Data ss:Type="String">';
        $xml .= 'LIBRO DE VENTAS — ' . htmlspecialchars($empresa['razon_social'], ENT_XML1) . ' — ' . $datos['periodo'];
        $xml .= '</Data></Cell></Row>';

        $cabecera = ['Tipo DTE', 'Glosa Tipo', 'Folio', 'RUT Receptor', 'Razón Social',
                     'Fecha Emisión', 'Monto Neto', 'IVA', 'Monto Exento', 'Monto Total', 'Estado'];
        $xml .= '<Row>';
        foreach ($cabecera as $col) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($col, ENT_XML1) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($lineas as $linea) {
            $xml .= '<Row>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['tipo_dte'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['tipo_dte_glosa'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['folio'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['rut_receptor'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['razon_social'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['fecha_emision'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['monto_neto'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['iva'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['monto_exento'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['monto_total'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['estado'], ENT_XML1) . '</Data></Cell>';
            $xml .= '</Row>';
        }

        $xml .= '<Row>';
        $xml .= '<Cell><Data ss:Type="String">TOTAL</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="Number">' . $totales['monto_neto'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="Number">' . $totales['iva'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="Number">' . $totales['monto_exento'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="Number">' . $totales['monto_total'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '</Row>';

        $xml .= '</Table></Worksheet></Workbook>';

        return response($xml, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    /** @param array<string, mixed> $datos */
    private function exportarExcelCompras(array $datos, int $mes, int $anio): Response
    {
        $nombre = sprintf('LCV_COMPRAS_%02d%d.xls', $mes, $anio);
        /** @var array<string, string> $empresa */
        $empresa = $datos['empresa'];
        /** @var list<array<string, mixed>> $lineas */
        $lineas  = $datos['lineas'];
        /** @var array<string, int> $totales */
        $totales = $datos['totales'];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="LCV Compras"><Table>';

        $xml .= '<Row><Cell ss:MergeAcross="10"><Data ss:Type="String">';
        $xml .= 'LIBRO DE COMPRAS — ' . htmlspecialchars($empresa['razon_social'], ENT_XML1) . ' — ' . $datos['periodo'];
        $xml .= '</Data></Cell></Row>';

        $cabecera = ['Tipo DTE', 'Glosa Tipo', 'N° Factura', 'RUT Emisor', 'Razón Social',
                     'Fecha Emisión', 'Monto Neto', 'IVA Recuperable', 'Monto No Recuperable',
                     'Monto Total', 'Indicador Uso'];
        $xml .= '<Row>';
        foreach ($cabecera as $col) {
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($col, ENT_XML1) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($lineas as $linea) {
            $xml .= '<Row>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['tipo_dte'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['tipo_dte_glosa'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['numero_factura'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['rut_emisor'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['razon_social'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['fecha_emision'], ENT_XML1) . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['monto_neto'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['iva_recuperable'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['monto_no_recuperable'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="Number">' . (int) $linea['monto_total'] . '</Data></Cell>';
            $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $linea['indicador_uso'], ENT_XML1) . '</Data></Cell>';
            $xml .= '</Row>';
        }

        $xml .= '<Row>';
        $xml .= '<Cell><Data ss:Type="String">TOTAL</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="Number">' . $totales['monto_neto'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="Number">' . $totales['iva'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '<Cell><Data ss:Type="Number">' . $totales['monto_total'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String"></Data></Cell>';
        $xml .= '</Row>';

        $xml .= '</Table></Worksheet></Workbook>';

        return response($xml, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }
}
