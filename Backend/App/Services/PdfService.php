<?php
namespace App\Services;

use FPDF;
use App\Helpers\FechaHelper;

class PdfService extends FPDF
{
    private $cotizacion;
    private $empresa;
    private $colores; 

    private function encode($txt)
    {
        return mb_convert_encoding($txt ?? '', 'ISO-8859-1', 'UTF-8');
    }

    private function hex2rgb($hex)
    {
        $hex = str_replace("#", "", $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }

    public function generarCotizacion($cotizacion, $empresa, $dest = 'S')
    {
        $this->cotizacion = $cotizacion;
        $this->empresa = $empresa;

        // CONFIGURAR COLOR DINÁMICO
        $hexColor = !empty($empresa['color_primario']) ? $empresa['color_primario'] : '#10b981';
        $this->colores = $this->hex2rgb($hexColor);
        [$r, $g, $b] = $this->colores;

        $this->AddPage();
        $this->AliasNbPages();
        $this->SetAutoPageBreak(true, 20);

        // --- ENCABEZADO ---
        if (!empty($this->empresa['logo_path']) && file_exists($this->empresa['logo_path'])) {
            $this->Image($this->empresa['logo_path'], 10, 5, 30);
        }

        $this->SetY(10);
        $this->SetX(45);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(30, 41, 59);
        $this->Cell(0, 6, $this->encode($this->empresa['razon_social']), 0, 1);

        $this->SetX(45);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, $this->encode("RUT: " . $this->empresa['rut']), 0, 1);
        $this->SetX(45);
        $this->Cell(0, 5, $this->encode("Dirección: " . $this->empresa['direccion']), 0, 1);
        $this->SetX(45);
        $this->Cell(0, 5, $this->encode("Email: " . ($this->empresa['email'] ?? 'S/I') . " | Tel: " . ($this->empresa['telefono'] ?? 'S/I')), 0, 1);

        // Bloque de Folio
        $this->SetY(10);
        $this->SetX(145);
        $this->SetFillColor(248, 250, 252);
        $this->SetDrawColor($r, $g, $b);
        $this->SetLineWidth(0.5);
        $this->Cell(55, 20, '', 1, 0, 'C', true);
        $this->SetY(12);
        $this->SetX(145);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(55, 5, $this->encode("COTIZACIÓN"), 0, 1, 'C');
        $this->SetX(145);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(55, 8, "N " . str_pad((string) $cotizacion['id'], 6, "0", STR_PAD_LEFT), 0, 1, 'C');

        $this->Ln(15);
        $this->SetLineWidth(0.2);
        $this->SetDrawColor(226, 232, 240);

        // --- DATOS DEL CLIENTE ---
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(241, 245, 249);
        $this->Cell(100, 7, $this->encode("  CLIENTE / RECEPTOR"), 0, 0, 'L', true);
        $this->Cell(90, 7, $this->encode("  FECHA Y VALIDEZ"), 0, 1, 'L', true);

        $this->Ln(2);
        $this->SetFont('Arial', '', 9);
        $yInicioInfo = $this->GetY();

        $this->Cell(100, 5, $this->encode($cotizacion['nombre_cliente']), 0, 1);
        $this->Cell(100, 5, $this->encode("RUT: " . ($cotizacion['cliente_rut'] ?? 'N/A')), 0, 1);
        $this->Cell(100, 5, $this->encode("Email: " . ($cotizacion['cliente_email'] ?? 'N/A')), 0, 1);
        $this->Cell(100, 5, $this->encode("Teléfono: " . ($cotizacion['cliente_telefono'] ?? 'N/A')), 0, 1);

        // --- LÓGICA DE FECHAS  ---
        $this->SetY($yInicioInfo);
        $this->SetX(110);
        
        $fechaEmision = $cotizacion['fecha_emision'];
        $emisionBonita = date('d-m-Y', strtotime($fechaEmision));
        
        $this->Cell(0, 5, $this->encode("Fecha de Emisión: " . $emisionBonita), 0, 1);

        $diasValidez = $cotizacion['validez'] ?? 15;
        $fechaVencimiento = FechaHelper::calcularVencimientoHabil($fechaEmision, $diasValidez);
        $vencimientoBonita = date('d-m-Y', strtotime($fechaVencimiento));

        $this->SetX(110);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor($r, $g, $b); 
        $this->Cell(0, 5, $this->encode("Fecha Vencimiento: " . $vencimientoBonita), 0, 1);
        
        $this->SetX(110);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, $this->encode("(Válido por " . $diasValidez . " días hábiles)"), 0, 1);

        $this->SetTextColor(0);

        $this->Ln(10);

        // --- TABLA DE PRODUCTOS ---
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor($r, $g, $b);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(105, 10, $this->encode(" DESCRIPCIÓN"), 0, 0, 'L', true);
        $this->Cell(20, 10, "CANT.", 0, 0, 'C', true);
        $this->Cell(30, 10, "P. UNITARIO", 0, 0, 'R', true);
        $this->Cell(35, 10, "TOTAL", 0, 1, 'R', true);

        $this->SetTextColor(30, 41, 59);
        $this->SetFont('Arial', '', 9);

        foreach ($cotizacion['detalles'] as $item) {
            $this->Cell(105, 8, " " . $this->encode($item['producto_nombre']), 'B', 0, 'L');
            $this->Cell(20, 8, $item['cantidad'], 'B', 0, 'C');
            $this->Cell(30, 8, "$" . number_format((float) $item['precio_unitario'], 0, ',', '.'), 'B', 0, 'R');
            $this->Cell(35, 8, "$" . number_format((float) $item['subtotal'], 0, ',', '.'), 'B', 1, 'R');
        }

        // --- POSICIONAMIENTO INFERIOR ---
        $yFijo = 225;
        if ($this->GetY() < $yFijo) {
            $this->SetY($yFijo);
        } else {
            $this->Ln(10);
        }
        $yInicioBloqueBajo = $this->GetY();

        // 1. BANCOS
        if (!empty($this->empresa['bancos']) && is_array($this->empresa['bancos'])) {
            $this->SetX(10);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(100, 6, $this->encode("Datos para Transferencia:"), 0, 1, 'L');
            $this->SetFont('Arial', '', 8);

            foreach ($this->empresa['bancos'] as $banco) {
                $titular = !empty($banco['titular']) ? $banco['titular'] : $this->empresa['razon_social'];
                $rutTitular = !empty($banco['rut_titular']) ? $banco['rut_titular'] : $this->empresa['rut'];
                $emailNotif = !empty($banco['email_notificacion']) ? $banco['email_notificacion'] : $this->empresa['email'];

                $this->SetX(10);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell(25, 4, $this->encode("Razón Social:"), 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->Cell(75, 4, $this->encode($titular), 0, 1, 'L');

                $this->SetX(10);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell(25, 4, $this->encode("RUT:"), 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->Cell(75, 4, $this->encode($rutTitular), 0, 1, 'L');

                $this->SetX(10);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell(25, 4, $this->encode("Banco:"), 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->Cell(75, 4, $this->encode($banco['banco']), 0, 1, 'L');

                $this->SetX(10);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell(25, 4, $this->encode("Tipo Cta:"), 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->Cell(75, 4, $this->encode($banco['tipo_cuenta']), 0, 1, 'L');

                $this->SetX(10);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell(25, 4, $this->encode("N° Cuenta:"), 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->Cell(75, 4, $this->encode($banco['numero_cuenta']), 0, 1, 'L');

                $this->SetX(10);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell(25, 4, $this->encode("Correo:"), 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->Cell(75, 4, $this->encode($emailNotif), 0, 1, 'L');

                $this->Ln(2);
            }
        }

        // 2. TOTALES
        $this->SetY($yInicioBloqueBajo);
        $totalFinal = (float) $cotizacion['total'];
        $neto = 0;
        $iva = 0;

        if (isset($cotizacion['es_afecta']) && $cotizacion['es_afecta'] == 1) {
            $neto = round($totalFinal / 1.19);
            $iva = $totalFinal - $neto;

            $this->SetX(130);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(35, 6, "Subtotal Neto:", 0, 0, 'R');
            $this->SetFont('Arial', '', 10);
            $this->Cell(35, 6, "$" . number_format($neto, 0, ',', '.'), 0, 1, 'R');

            $this->SetX(130);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(35, 6, "IVA (19%):", 0, 0, 'R');
            $this->SetFont('Arial', '', 10);
            $this->Cell(35, 6, "$" . number_format($iva, 0, ',', '.'), 0, 1, 'R');
        } else {
            $this->SetX(130);
            $this->SetFont('Arial', 'I', 9);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(70, 6, $this->encode("* Valores Exentos de IVA"), 0, 1, 'R');
        }

        $this->SetX(130);
        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor($r, $g, $b);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(35, 10, "TOTAL", 0, 0, 'R', true);
        $this->Cell(35, 10, "$" . number_format($totalFinal, 0, ',', '.'), 0, 1, 'R', true);

        // FOOTER
        $this->SetY(270);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, $this->encode("Generado vía Sistema ERP Contable Centralizado por " . $this->empresa['razon_social']), 0, 1, 'C');


        $nombreClienteLimpio = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $cotizacion['nombre_cliente']);
        $nombreArchivo = 'Cotizacion_' . $cotizacion['id'] . ' - ' . trim($nombreClienteLimpio) . '.pdf';

        return $this->Output($dest, $nombreArchivo);
    }
}