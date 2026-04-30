@php
    $colorPrimario = !empty($empresa->color_primario) ? $empresa->color_primario : '#10b981';
    $diasValidez = $cotizacion->validez ?? 15;
    $fechaEmision = \Carbon\Carbon::parse($cotizacion->fecha_emision);
    $fechaVencimiento = \Carbon\Carbon::parse($cotizacion->fecha_validez ?? $fechaEmision->copy()->addDays($diasValidez));
@endphp

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cotización {{ $cotizacion->numero_cotizacion }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 13px; color: #1e293b; margin: 0; padding: 0; }
        
        /* --- ENCABEZADO --- */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
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
            width: 180px;
        }
        .folio-titulo { font-size: 14px; font-weight: bold; margin-bottom: 5px; letter-spacing: 1px; }
        .folio-numero { font-size: 18px; font-weight: bold; color: #000; }

        /* --- DATOS DEL CLIENTE --- */
        .seccion-titulos { width: 100%; border-collapse: collapse; background-color: #f1f5f9; margin-bottom: 10px; }
        .seccion-titulos td { padding: 8px 12px; font-weight: bold; font-size: 12px; color: #1e293b; }
        .titulo-izq { width: 55%; }
        .titulo-der { width: 45%; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 12px; }
        .info-table td { vertical-align: top; padding: 0 12px; line-height: 1.6; }
        .info-izq { width: 55%; }
        .info-der { width: 45%; }
        .color-dinamico { color: {{ $colorPrimario }}; font-weight: bold; }
        .texto-gris { color: #64748b; font-size: 11px; }

        /* --- TABLA DE PRODUCTOS --- */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { 
            background-color: {{ $colorPrimario }}; 
            color: #ffffff; 
            font-size: 12px; 
            padding: 10px; 
            text-align: left;
            font-weight: bold;
        }
        .items-table td { 
            padding: 10px; 
            border-bottom: 1px solid #e2e8f0; 
            font-size: 12px; 
            color: #1e293b;
            vertical-align: middle;
        }
        .w-desc { width: 55%; }
        .w-cant { width: 10%; text-align: center !important; }
        .w-precio { width: 15%; text-align: right !important; }
        .w-total { width: 20%; text-align: right !important; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }

        /* --- POSICIONAMIENTO INFERIOR FIJADO ABAJO --- */
        .bottom-container { 
            position: absolute;
            bottom: 150px; 
            width: 100%; 
            border-collapse: collapse; 
            page-break-inside: avoid; 
        }
        .bottom-container td { vertical-align: top; }
        .bancos-td { width: 60%; padding-right: 20px; }
        .totales-td { width: 40%; }

        /* Bancos */
        .bancos-titulo { font-weight: bold; font-size: 12px; margin-bottom: 8px; color: #1e293b; }
        .bancos-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 15px; }
        .bancos-table td { padding: 3px 0; }
        .b-lbl { width: 80px; font-weight: bold; color: #1e293b; }
        .b-val { color: #475569; }

        /* Totales */
        .totales-table { width: 100%; border-collapse: collapse; }
        .totales-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        .t-lbl { text-align: right; font-weight: bold; color: #1e293b; width: 50%; }
        .t-val { text-align: right; color: #1e293b; width: 50%; }
        .t-exento { text-align: right; font-style: italic; font-size: 11px; color: #1e293b; padding: 8px 10px; }
        
        .total-final-row { background-color: {{ $colorPrimario }}; color: #ffffff; font-weight: bold; font-size: 15px; }
        .total-final-row td { border-bottom: none; color: #ffffff; padding: 12px 10px;}

        /* --- FOOTER --- */
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
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="logo-td">
                @if(!empty($empresa->logo_path))
                    <img src="{{ public_path('storage/' . $empresa->logo_path) }}" class="logo-img" alt="Logo">
                @endif
            </td>
            <td class="empresa-td">
                <div class="empresa-nombre">{{ $empresa->razon_social ?? 'Empresa No Configurada' }}</div>
                <div class="empresa-info">
                    RUT: {{ $empresa->rut ?? 'N/A' }}<br>
                    Dirección: {{ $empresa->direccion ?? 'N/A' }}<br>
                    Email: {{ $empresa->email ?? 'S/I' }} | Tel: {{ $empresa->telefono ?? 'S/I' }}
                </div>
            </td>
            <td class="folio-td">
                <div class="folio-box">
                    <div class="folio-titulo">COTIZACIÓN</div>
                    <div class="folio-numero">N° {{ str_pad($cotizacion->id, 6, "0", STR_PAD_LEFT) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="seccion-titulos">
        <tr>
            <td class="titulo-izq">CLIENTE / RECEPTOR</td>
            <td class="titulo-der">FECHA Y VALIDEZ</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-izq">
                <strong>{{ $cotizacion->nombre_cliente }}</strong><br>
                Atención: {{ $cotizacion->cliente->contacto_nombre ?? 'S/I' }}<br>
                RUT: {{ $cotizacion->cliente->rut ?? 'N/A' }}<br>
                Email: {{ $cotizacion->cliente->contacto_email ?? $cotizacion->cliente->email ?? 'S/I' }}<br>
                Teléfono: {{ $cotizacion->cliente->contacto_telefono ?? $cotizacion->cliente->telefono ?? 'S/I' }}
            </td>
            <td class="info-der">
                Fecha de Emisión: {{ $fechaEmision->format('d-m-Y') }}<br>
                <span class="color-dinamico">Fecha Vencimiento: {{ $fechaVencimiento->format('d-m-Y') }}</span><br>
                <span class="texto-gris">(Válido por {{ $diasValidez }} días hábiles)</span>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="w-desc">DESCRIPCIÓN</th>
                <th class="w-cant text-center">CANT.</th>
                <th class="w-precio text-right">P. UNITARIO</th>
                <th class="w-total text-right">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->detalles as $item)
            <tr>
                <td class="w-desc">
                    <strong>{{ $item->producto_nombre }}</strong>
                    @if(!empty($item->descripcion))
                        <br>
                        <span style="font-size: 10px; color: #64748b; display: block; margin-top: 3px; line-height: 1.3; white-space: pre-wrap;">{{ $item->descripcion }}</span>
                    @endif
                </td>
                <td class="w-cant text-center" style="vertical-align: top; padding-top: 10px;">{{ $item->cantidad }}</td>
                <td class="w-precio text-right" style="vertical-align: top; padding-top: 10px;">${{ number_format($item->precio_unitario, 0, ',', '.') }}</td>
                <td class="w-total text-right" style="vertical-align: top; padding-top: 10px;">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table> <div style="height: 220px; width: 100%; clear: both;"></div>

    <table class="bottom-container">
        <tr>
            <td class="bancos-td">
                @if($cuentasBancarias->count() > 0)
                    <div class="bancos-titulo">Datos para Transferencia:</div>
                    @foreach($cuentasBancarias as $banco)
                        <table class="bancos-table">
                            <tr><td class="b-lbl">Razón Social:</td><td class="b-val">{{ $banco->titular ?? $empresa->razon_social }}</td></tr>
                            <tr><td class="b-lbl">RUT:</td><td class="b-val">{{ $banco->rut_titular ?? $empresa->rut }}</td></tr>
                            <tr><td class="b-lbl">Banco:</td><td class="b-val">{{ $banco->banco }}</td></tr>
                            <tr><td class="b-lbl">Tipo Cta:</td><td class="b-val">{{ $banco->tipo_cuenta }}</td></tr>
                            <tr><td class="b-lbl">N° Cuenta:</td><td class="b-val">{{ $banco->numero_cuenta }}</td></tr>
                            <tr><td class="b-lbl">Correo:</td><td class="b-val">{{ $banco->email_notificacion ?? $empresa->email }}</td></tr>
                        </table>
                    @endforeach
                @endif
            </td>
            
            <td class="totales-td">
                @if($cotizacion->monto_iva > 0)
                    <table class="totales-table">
                        <tr>
                            <td class="t-lbl">Subtotal Neto:</td>
                            <td class="t-val">${{ number_format($cotizacion->monto_neto, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="t-lbl">IVA ({{ number_format($cotizacion->porcentaje_iva, 0) }}%):</td>
                            <td class="t-val">${{ number_format($cotizacion->monto_iva, 0, ',', '.') }}</td>
                        </tr>
                        <tr class="total-final-row">
                            <td class="t-lbl" style="color: white;">TOTAL</td>
                            <td class="t-val" style="color: white;">${{ number_format($cotizacion->monto_total, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                @else
                    <div class="t-exento">* Valores Exentos de IVA</div>
                    <table class="totales-table">
                        <tr class="total-final-row">
                            <td class="t-lbl" style="color: white;">TOTAL</td>
                            <td class="t-val" style="color: white;">${{ number_format($cotizacion->monto_total, 0, ',', '.') }}</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <div class="footer">
        Generado vía Sistema ERP Contable Centralizado por {{ $empresa->razon_social ?? 'Empresa' }}
    </div>

</body>
</html>