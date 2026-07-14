<?php
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Models\Empresa;

/** @var Factura $factura */ /** @var Empresa $empresa */ ?>
@php
    $colorPrimario = !empty($empresa->color_primario) ? $empresa->color_primario : '#10b981';
    $esAfecta = (float) $factura->monto_iva > 0;
    $esCopia = $esCopia ?? false;
    $emisorRazonSocial = $factura->emisor_razon_social ?? $empresa->razon_social;
    $emisorRut = $factura->emisor_rut ?? $empresa->rut;
    $emisorLogoPath = $factura->emisor_logo_path ?? $empresa->logo_path;
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Comprobante de Factura {{ $factura->numero_factura }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 13px; color: #1e293b; margin: 0; padding: 0; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .logo-td { width: 22%; vertical-align: top; }
        .logo-img { max-width: 140px; max-height: 80px; }
        .empresa-td { width: 45%; vertical-align: top; padding-top: 5px; }
        .empresa-nombre { font-size: 16px; font-weight: bold; color: #1e293b; margin-bottom: 4px; }
        .empresa-info { font-size: 12px; color: #475569; line-height: 1.4; }

        .folio-td { width: 33%; vertical-align: top; text-align: right; padding-top: 5px; }
        .folio-box {
            border: 2px solid {{ $colorPrimario }};
            background-color: #f8fafc;
            padding: 12px;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            width: 200px;
        }
        .folio-titulo { font-size: 13px; font-weight: bold; margin-bottom: 5px; letter-spacing: 1px; }
        .folio-numero { font-size: 18px; font-weight: bold; color: #000; }

        .aviso-interno {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            font-size: 10px;
            padding: 8px 12px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .seccion-titulos { width: 100%; border-collapse: collapse; background-color: #f1f5f9; margin-bottom: 10px; }
        .seccion-titulos td { padding: 8px 12px; font-weight: bold; font-size: 12px; color: #1e293b; }
        .titulo-izq { width: 55%; }
        .titulo-der { width: 45%; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
        .info-table td { vertical-align: top; padding: 0 12px; line-height: 1.6; }
        .info-izq { width: 55%; }
        .info-der { width: 45%; }
        .color-dinamico { color: {{ $colorPrimario }}; font-weight: bold; }

        .totales-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .totales-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        .t-lbl { text-align: right; font-weight: bold; color: #1e293b; width: 70%; }
        .t-val { text-align: right; color: #1e293b; width: 30%; }
        .total-final-row { background-color: {{ $colorPrimario }}; color: #ffffff; font-weight: bold; font-size: 15px; }
        .total-final-row td { border-bottom: none; color: #ffffff; padding: 12px 10px; }

        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            font-style: italic;
            color: #94a3b8;
        }

        .marca-copia {
            position: fixed;
            top: 260px;
            left: 90px;
            font-size: 90px;
            font-weight: bold;
            color: rgba(220, 38, 38, 0.18);
            transform: rotate(-30deg);
            letter-spacing: 8px;
            z-index: -1;
        }
    </style>
</head>
<body>
    @if($esCopia)
        <div class="marca-copia">COPIA</div>
    @endif

    <table class="header-table">
        <tr>
            <td class="logo-td">
                @if(!empty($emisorLogoPath))
                    <img src="{{ public_path('storage/' . $emisorLogoPath) }}" class="logo-img" alt="Logo">
                @endif
            </td>
            <td class="empresa-td">
                <div class="empresa-nombre">{{ $emisorRazonSocial ?? 'Empresa No Configurada' }}</div>
                <div class="empresa-info">
                    RUT: {{ $emisorRut ?? 'N/A' }}<br>
                    Dirección: {{ $empresa->direccion ?? 'N/A' }}<br>
                    Email: {{ $empresa->email ?? 'S/I' }} | Tel: {{ $empresa->telefono ?? 'S/I' }}
                </div>
            </td>
            <td class="folio-td">
                <div class="folio-box">
                    <div class="folio-titulo">COMPROBANTE FACTURA VENTA{{ $esCopia ? ' — COPIA' : '' }}</div>
                    <div class="folio-numero">N° {{ $factura->numero_factura }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="aviso-interno">
        Documento interno de gestión — no reemplaza la representación impresa timbrada del SII (Servicio de Impuestos Internos).
        @if($esCopia)
            <strong>Esta es una reimpresión (COPIA), no el documento original.</strong>
        @endif
    </div>

    <table class="seccion-titulos">
        <tr>
            <td class="titulo-izq">CLIENTE</td>
            <td class="titulo-der">FECHA Y ESTADO</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-izq">
                <strong>{{ $factura->cliente->razon_social ?? 'Cliente' }}</strong><br>
                RUT: {{ $factura->cliente->rut ?? 'N/A' }}<br>
                Email: {{ $factura->cliente->contacto_email ?? $factura->cliente->email ?? 'S/I' }}
            </td>
            <td class="info-der">
                Fecha de Emisión: {{ $factura->fecha_emision->format('d-m-Y') }}<br>
                <span class="color-dinamico">Estado: {{ $factura->estado }}</span>
            </td>
        </tr>
    </table>

    <table class="totales-table">
        <tr>
            <td class="t-lbl">Neto:</td>
            <td class="t-val">${{ number_format((float) $factura->monto_neto, 0, ',', '.') }}</td>
        </tr>
        @if($esAfecta)
        <tr>
            <td class="t-lbl">IVA:</td>
            <td class="t-val">${{ number_format((float) $factura->monto_iva, 0, ',', '.') }}</td>
        </tr>
        @else
        <tr>
            <td class="t-lbl">IVA:</td>
            <td class="t-val" style="font-style: italic; color: #64748b;">Exento</td>
        </tr>
        @endif
        <tr class="total-final-row">
            <td class="t-lbl" style="color: white;">TOTAL</td>
            <td class="t-val" style="color: white;">${{ number_format((float) $factura->monto_bruto, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Generado vía Tenri ERP Cloud por {{ $emisorRazonSocial ?? 'Empresa' }}
    </div>

</body>
</html>
