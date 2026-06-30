@php
    $empresa = $datos['empresa'] ?? [];
    $periodo = $datos['periodo'] ?? '';
    $filas   = $datos['filas'] ?? [];
    $totales = $datos['totales'] ?? [];
    $cant    = $datos['cantidad_trabajadores'] ?? 0;

    $fmt = fn(int|float $v) => number_format((float) $v, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libro de Remuneraciones — {{ $periodo }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9px;
            color: #1e293b;
        }
        .encabezado {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .encabezado td { vertical-align: top; }
        .titulo-empresa {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
        }
        .subtitulo {
            font-size: 10px;
            color: #475569;
            margin-top: 2px;
        }
        .badge-periodo {
            border: 1px solid #10b981;
            padding: 4px 10px;
            border-radius: 4px;
            text-align: center;
            color: #065f46;
            font-weight: bold;
            font-size: 11px;
        }
        .legal-nota {
            font-size: 8px;
            color: #64748b;
            margin-bottom: 8px;
        }
        table.datos {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }
        table.datos thead tr {
            background-color: #0f172a;
            color: #ffffff;
        }
        table.datos thead th {
            padding: 4px 3px;
            text-align: center;
            font-weight: bold;
            border: 1px solid #1e293b;
            white-space: nowrap;
        }
        table.datos tbody tr:nth-child(even) {
            background-color: #f1f5f9;
        }
        table.datos tbody td {
            padding: 3px 3px;
            border: 1px solid #cbd5e1;
        }
        .col-texto { text-align: left; }
        .col-num   { text-align: right; }
        .col-cen   { text-align: center; }
        tr.totales {
            background-color: #0f172a !important;
            color: #ffffff;
            font-weight: bold;
        }
        tr.totales td {
            padding: 4px 3px;
            border: 1px solid #1e293b;
        }
        .footer {
            margin-top: 10px;
            font-size: 7.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    {{-- Encabezado --}}
    <table class="encabezado">
        <tr>
            <td style="width:70%">
                <div class="titulo-empresa">{{ $empresa['razon_social'] ?? '' }}</div>
                <div class="subtitulo">RUT: {{ $empresa['rut'] ?? '' }}</div>
                <div class="subtitulo">Libro de Remuneraciones Digital — DFL-1 Art. 62 C.T.</div>
            </td>
            <td style="width:30%; text-align:right;">
                <div class="badge-periodo">Período: {{ $periodo }}</div>
                <div class="subtitulo" style="text-align:right; margin-top:4px;">
                    Trabajadores: <strong>{{ $cant }}</strong>
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabla de datos --}}
    @if(count($filas) > 0)
    <table class="datos">
        <thead>
            <tr>
                <th class="col-texto">RUT</th>
                <th class="col-texto">Nombre</th>
                <th class="col-texto">Cargo</th>
                <th class="col-cen">Días</th>
                <th class="col-num">Sueldo Base</th>
                <th class="col-num">H. Extra</th>
                <th class="col-num">Total Haberes</th>
                <th class="col-num">Desc. Prev.</th>
                <th class="col-num">Desc. Legal</th>
                <th class="col-num">Otros Desc.</th>
                <th class="col-num">Total Desc.</th>
                <th class="col-num">Líquido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $fila)
            <tr>
                <td class="col-texto">{{ $fila['rut'] }}</td>
                <td class="col-texto">{{ $fila['nombre'] }}</td>
                <td class="col-texto">{{ $fila['cargo'] }}</td>
                <td class="col-cen">{{ $fila['dias_trabajados'] }}</td>
                <td class="col-num">{{ $fmt($fila['sueldo_base']) }}</td>
                <td class="col-num">{{ $fmt($fila['horas_extras']) }}</td>
                <td class="col-num">{{ $fmt($fila['total_haberes']) }}</td>
                <td class="col-num">{{ $fmt($fila['descuento_previsional']) }}</td>
                <td class="col-num">{{ $fmt($fila['descuento_legal']) }}</td>
                <td class="col-num">{{ $fmt($fila['otros_descuentos']) }}</td>
                <td class="col-num">{{ $fmt($fila['total_descuentos']) }}</td>
                <td class="col-num">{{ $fmt($fila['liquido']) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totales">
                <td class="col-texto" colspan="4">TOTALES</td>
                <td class="col-num">{{ $fmt($totales['sueldo_base'] ?? 0) }}</td>
                <td class="col-num">{{ $fmt($totales['horas_extras'] ?? 0) }}</td>
                <td class="col-num">{{ $fmt($totales['total_haberes'] ?? 0) }}</td>
                <td class="col-num">{{ $fmt($totales['descuento_previsional'] ?? 0) }}</td>
                <td class="col-num">{{ $fmt($totales['descuento_legal'] ?? 0) }}</td>
                <td class="col-num">{{ $fmt($totales['otros_descuentos'] ?? 0) }}</td>
                <td class="col-num">{{ $fmt($totales['total_descuentos'] ?? 0) }}</td>
                <td class="col-num">{{ $fmt($totales['liquido'] ?? 0) }}</td>
            </tr>
        </tfoot>
    </table>
    @else
    <p style="color:#94a3b8; text-align:center; padding:20px;">
        Sin liquidaciones emitidas para el período {{ $periodo }}.
    </p>
    @endif

    {{-- Pie de página --}}
    <div class="footer">
        Libro de Remuneraciones generado por ERP Tenri Cloud. DFL-1 Art. 62 C.T.
    </div>
</body>
</html>
