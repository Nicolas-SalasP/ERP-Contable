@php
    $empresa = $datos['empresa'] ?? [];
    $periodo = $datos['periodo'] ?? '';
    $filas   = $datos['filas'] ?? [];
    $totales = $datos['totales'] ?? [];
    $cant    = $datos['cantidad_trabajadores'] ?? 0;

    $fmt = fn(int|float $v) => number_format((float) $v, 0, ',', '.');
@endphp
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        table { border-collapse: collapse; width: 100%; }
        th {
            background-color: #0f172a;
            color: #ffffff;
            padding: 5px 6px;
            text-align: center;
            border: 1px solid #1e293b;
            font-size: 10px;
        }
        td {
            padding: 4px 6px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        .num  { text-align: right; mso-number-format: '\#\,\#\#0'; }
        .cen  { text-align: center; }
        .enc  { font-size: 13px; font-weight: bold; margin-bottom: 6px; }
        .sub  { font-size: 10px; color: #475569; margin-bottom: 3px; }
        .fila-par td { background-color: #f1f5f9; }
        .totales td {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-align: right;
        }
        .totales td:first-child { text-align: left; }
    </style>
</head>
<body>
    <p class="enc">{{ $empresa['razon_social'] ?? '' }}</p>
    <p class="sub">RUT: {{ $empresa['rut'] ?? '' }}</p>
    <p class="sub">Libro de Remuneraciones Digital — Período: {{ $periodo }} — Trabajadores: {{ $cant }}</p>
    <p class="sub">DFL-1 Art. 62 Código del Trabajo</p>
    <br>

    <table>
        <thead>
            <tr>
                <th>RUT</th>
                <th>Nombre</th>
                <th>Cargo</th>
                <th>Días Trab.</th>
                <th>Sueldo Base</th>
                <th>H. Extra</th>
                <th>Total Haberes</th>
                <th>Desc. Prev.</th>
                <th>Desc. Legal</th>
                <th>Otros Desc.</th>
                <th>Total Desc.</th>
                <th>Líquido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($filas as $i => $fila)
            <tr class="{{ $i % 2 === 1 ? 'fila-par' : '' }}">
                <td>{{ $fila['rut'] }}</td>
                <td>{{ $fila['nombre'] }}</td>
                <td>{{ $fila['cargo'] }}</td>
                <td class="cen">{{ $fila['dias_trabajados'] }}</td>
                <td class="num">{{ $fila['sueldo_base'] }}</td>
                <td class="num">{{ $fila['horas_extras'] }}</td>
                <td class="num">{{ $fila['total_haberes'] }}</td>
                <td class="num">{{ $fila['descuento_previsional'] }}</td>
                <td class="num">{{ $fila['descuento_legal'] }}</td>
                <td class="num">{{ $fila['otros_descuentos'] }}</td>
                <td class="num">{{ $fila['total_descuentos'] }}</td>
                <td class="num">{{ $fila['liquido'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totales">
                <td colspan="4">TOTALES</td>
                <td>{{ $totales['sueldo_base'] ?? 0 }}</td>
                <td>{{ $totales['horas_extras'] ?? 0 }}</td>
                <td>{{ $totales['total_haberes'] ?? 0 }}</td>
                <td>{{ $totales['descuento_previsional'] ?? 0 }}</td>
                <td>{{ $totales['descuento_legal'] ?? 0 }}</td>
                <td>{{ $totales['otros_descuentos'] ?? 0 }}</td>
                <td>{{ $totales['total_descuentos'] ?? 0 }}</td>
                <td>{{ $totales['liquido'] ?? 0 }}</td>
            </tr>
        </tfoot>
    </table>
    <br>
    <p style="font-size:8px; color:#94a3b8;">
        Libro de Remuneraciones generado por ERP Tenri Cloud. DFL-1 Art. 62 C.T.
    </p>
</body>
</html>
