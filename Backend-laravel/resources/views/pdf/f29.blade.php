@php
    $fmt = fn(int|float $v): string => '$' . number_format((float) $v, 0, ',', '.');
    $fmtPct = fn(int|float $v): string => number_format((float) $v, 3, ',', '.') . '%';

    $seccionesA = ['L1', 'L2', 'L7', 'L11'];
    $seccionesB = ['L20', 'L24', 'L26', 'L27', 'L28', 'L36'];
    $seccionesC = ['L63', 'L64', 'L65'];
    $seccionesD = ['L49'];
    $seccionesE = ['L89', 'L91'];

    $totalesA = ['L11'];
    $totalesB = ['L27', 'L28', 'L36'];
    $totalesE = ['L89', 'L91'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Formulario 29 — {{ $periodo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            padding: 12px;
        }

        /* ── ENCABEZADO ── */
        .encabezado-principal {
            background-color: #00703c;
            color: #ffffff;
            padding: 10px 14px;
            margin-bottom: 6px;
        }
        .encabezado-principal .form-titulo {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .encabezado-principal .form-subtitulo {
            font-size: 9px;
            margin-top: 2px;
            opacity: 0.9;
        }
        .encabezado-empresa {
            border: 1px solid #00703c;
            padding: 7px 10px;
            margin-bottom: 8px;
            display: table;
            width: 100%;
        }
        .encabezado-empresa table {
            width: 100%;
            border-collapse: collapse;
        }
        .encabezado-empresa td {
            font-size: 9px;
            padding: 2px 4px;
            vertical-align: top;
        }
        .encabezado-empresa .lbl {
            font-weight: bold;
            color: #00703c;
            width: 90px;
        }
        .encabezado-empresa .val {
            color: #1a1a1a;
        }

        /* ── SECCIONES ── */
        .seccion-header {
            background-color: #00703c;
            color: #ffffff;
            font-weight: bold;
            font-size: 8.5px;
            padding: 4px 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-top: 6px;
            margin-bottom: 0;
        }

        /* ── TABLA DE LÍNEAS ── */
        table.lineas {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        table.lineas tr {
            border-bottom: 1px solid #d1d5db;
        }
        table.lineas td.num-linea {
            width: 32px;
            text-align: center;
            font-weight: bold;
            color: #ffffff;
            background-color: #4b5563;
            padding: 3px 4px;
            border-right: 1px solid #6b7280;
        }
        table.lineas td.descripcion {
            padding: 3px 8px;
            color: #374151;
        }
        table.lineas td.monto {
            width: 130px;
            text-align: right;
            padding: 3px 8px;
            font-weight: bold;
            color: #1a1a1a;
        }
        table.lineas tr.fila-total td.num-linea {
            background-color: #00703c;
        }
        table.lineas tr.fila-total td.descripcion {
            font-weight: bold;
            background-color: #f0fdf4;
        }
        table.lineas tr.fila-total td.monto {
            background-color: #f0fdf4;
            color: #00703c;
        }
        table.lineas tr.fila-remanente td.num-linea {
            background-color: #065f46;
        }
        table.lineas tr.fila-remanente td.descripcion {
            background-color: #ecfdf5;
        }
        table.lineas tr.fila-remanente td.monto {
            background-color: #ecfdf5;
            color: #065f46;
        }
        table.lineas tr.fila-determinado td.num-linea {
            background-color: #92400e;
        }
        table.lineas tr.fila-determinado td {
            background-color: #fffbeb;
        }
        table.lineas tr.fila-determinado td.monto {
            color: #92400e;
        }

        /* ── DOS COLUMNAS ── */
        .dos-columnas {
            display: table;
            width: 100%;
            margin-bottom: 0;
        }
        .columna-izq {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 4px;
        }
        .columna-der {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-left: 4px;
        }

        /* ── RESUMEN FINAL ── */
        .resumen-box {
            border: 2px solid #00703c;
            padding: 8px 12px;
            margin-top: 8px;
            background-color: #f0fdf4;
        }
        .resumen-box .resumen-titulo {
            font-size: 9px;
            font-weight: bold;
            color: #00703c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .resumen-box .resumen-monto {
            font-size: 16px;
            font-weight: bold;
            color: #00703c;
        }
        .resumen-box .resumen-sub {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ── PIE ── */
        .pie {
            margin-top: 14px;
            border-top: 1px solid #d1d5db;
            padding-top: 6px;
            font-size: 7.5px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ENCABEZADO PRINCIPAL --}}
    <div class="encabezado-principal">
        <div class="form-titulo">FORMULARIO 29</div>
        <div class="form-subtitulo">Declaración Mensual y Pago Simultáneo de Impuestos — Servicio de Impuestos Internos</div>
    </div>

    {{-- DATOS DE LA EMPRESA --}}
    <div class="encabezado-empresa">
        <table>
            <tr>
                <td class="lbl">Razón Social:</td>
                <td class="val">{{ $empresa['razon_social'] }}</td>
                <td class="lbl">RUT:</td>
                <td class="val">{{ $empresa['rut'] }}</td>
                <td class="lbl">Período:</td>
                <td class="val">{{ $periodo }}</td>
            </tr>
        </table>
    </div>

    {{-- DOS COLUMNAS: A (Ventas) + B (Compras) --}}
    <div class="dos-columnas">
        <div class="columna-izq">
            <div class="seccion-header">A — Ventas y Débitos</div>
            <table class="lineas">
                @foreach ($seccionesA as $clave)
                    @php $l = $lineas[$clave] ?? null; @endphp
                    @if ($l)
                        @php
                            $esTotal = in_array($clave, $totalesA);
                            $clase = $esTotal ? 'fila-total' : '';
                        @endphp
                        <tr class="{{ $clase }}">
                            <td class="num-linea">{{ substr($clave, 1) }}</td>
                            <td class="descripcion">{{ $l['desc'] }}</td>
                            <td class="monto">{{ $fmt($l['valor']) }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </div>

        <div class="columna-der">
            <div class="seccion-header">B — Compras y Créditos</div>
            <table class="lineas">
                @foreach ($seccionesB as $clave)
                    @php $l = $lineas[$clave] ?? null; @endphp
                    @if ($l)
                        @php
                            $esTotal = $clave === 'L27' || $clave === 'L28';
                            $esRemanente = $clave === 'L36';
                            $clase = $esTotal ? 'fila-total' : ($esRemanente ? 'fila-remanente' : '');
                        @endphp
                        <tr class="{{ $clase }}">
                            <td class="num-linea">{{ substr($clave, 1) }}</td>
                            <td class="descripcion">{{ $l['desc'] }}</td>
                            <td class="monto">{{ $fmt($l['valor']) }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        </div>
    </div>

    {{-- SECCIÓN C — PPM --}}
    <div class="seccion-header">C — Pagos Provisionales Mensuales (PPM)</div>
    <table class="lineas">
        @foreach ($seccionesC as $clave)
            @php $l = $lineas[$clave] ?? null; @endphp
            @if ($l)
                <tr>
                    <td class="num-linea">{{ substr($clave, 1) }}</td>
                    <td class="descripcion">{{ $l['desc'] }}</td>
                    <td class="monto">
                        @if ($clave === 'L64')
                            {{ $fmtPct($l['valor']) }}
                        @else
                            {{ $fmt($l['valor']) }}
                        @endif
                    </td>
                </tr>
            @endif
        @endforeach
    </table>

    {{-- SECCIÓN D — RETENCIONES --}}
    <div class="seccion-header">D — Retenciones</div>
    <table class="lineas">
        @foreach ($seccionesD as $clave)
            @php $l = $lineas[$clave] ?? null; @endphp
            @if ($l)
                <tr>
                    <td class="num-linea">{{ substr($clave, 1) }}</td>
                    <td class="descripcion">{{ $l['desc'] }}</td>
                    <td class="monto">{{ $fmt($l['valor']) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    {{-- SECCIÓN E — DETERMINACIÓN IMPUESTO --}}
    <div class="seccion-header">E — Determinación del Impuesto</div>
    <table class="lineas">
        @foreach ($seccionesE as $clave)
            @php $l = $lineas[$clave] ?? null; @endphp
            @if ($l)
                <tr class="fila-determinado">
                    <td class="num-linea">{{ substr($clave, 1) }}</td>
                    <td class="descripcion">{{ $l['desc'] }}</td>
                    <td class="monto">{{ $fmt($l['valor']) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    {{-- RESUMEN --}}
    <div class="resumen-box">
        <div class="resumen-titulo">
            @if ($simulacion['resumen']['total_a_pagar'] > 0)
                Total a Pagar (F29)
            @else
                Remanente Crédito Fiscal para el Período Siguiente
            @endif
        </div>
        <div class="resumen-monto">
            @if ($simulacion['resumen']['total_a_pagar'] > 0)
                {{ $fmt($simulacion['resumen']['total_a_pagar']) }}
            @else
                {{ $fmt($simulacion['resumen']['remanente']) }}
            @endif
        </div>
        <div class="resumen-sub">
            Período: {{ $periodo }} &nbsp;|&nbsp;
            IVA Débito: {{ $fmt($simulacion['ventas']['iva_debito']) }} &nbsp;|&nbsp;
            IVA Crédito: {{ $fmt($simulacion['compras']['iva_credito']) }} &nbsp;|&nbsp;
            PPM: {{ $fmt($simulacion['ppm']['monto']) }}
        </div>
    </div>

    {{-- PIE --}}
    <div class="pie">
        Generado por ERP Tenri Cloud — Solo para referencia interna, no reemplaza la declaración oficial en portal.sii.cl
    </div>

</body>
</html>
