@php
    $fmt    = fn(int|float $v): string => number_format((float) $v, 0, ',', '.');
    $fmtPct = fn(int|float $v): string => number_format((float) $v, 3, ',', '.') . '%';

    $secciones = [
        'A — Ventas y Débitos'              => ['L1', 'L2', 'L7', 'L11'],
        'B — Compras y Créditos'            => ['L20', 'L24', 'L26', 'L27', 'L28', 'L36'],
        'C — Pagos Provisionales Mensuales' => ['L63', 'L64', 'L65'],
        'D — Retenciones'                   => ['L49'],
        'E — Determinación del Impuesto'    => ['L89', 'L91'],
    ];

    $clavesTotales = ['L11', 'L27', 'L28', 'L89', 'L91'];
@endphp
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <style>
        body  { font-family: Arial, sans-serif; font-size: 11px; }
        .enc  { font-size: 14px; font-weight: bold; margin-bottom: 3px; }
        .sub  { font-size: 10px; color: #555555; margin-bottom: 2px; }
        table { border-collapse: collapse; width: 60%; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 7px; font-size: 10px; }
        th { background-color: #1e293b; color: #ffffff; text-align: left; }
        th.monto { text-align: right; }
        td.monto { text-align: right; mso-number-format: '\#\,\#\#0'; }
        td.num   { text-align: center; font-weight: bold; color: #374151; background-color: #f1f5f9; width: 40px; }
        .seccion-header td {
            background-color: #00703c;
            color: #ffffff;
            font-weight: bold;
            font-size: 10px;
            padding: 4px 7px;
            border-color: #00703c;
        }
        .fila-total td { background-color: #f0fdf4; font-weight: bold; color: #00703c; }
        .fila-total td.num { background-color: #d1fae5; color: #065f46; }
        .fila-total td.monto { color: #00703c; }
        .fila-determinado td { background-color: #fffbeb; font-weight: bold; color: #92400e; }
        .fila-determinado td.num { background-color: #fde68a; }
    </style>
</head>
<body>
    <p class="enc">{{ $empresa['razon_social'] }}</p>
    <p class="sub">RUT: {{ $empresa['rut'] }}</p>
    <p class="sub">Formulario 29 — Declaración Mensual y Pago Simultáneo — Período: {{ $periodo }}</p>
    <p class="sub">Generado por ERP Tenri Cloud — Solo referencia interna</p>

    <table>
        <thead>
            <tr>
                <th style="width:40px">Línea</th>
                <th>Descripción</th>
                <th class="monto" style="width:140px">Monto ($)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($secciones as $titulo => $claves)
                <tr class="seccion-header">
                    <td colspan="3">{{ $titulo }}</td>
                </tr>
                @foreach ($claves as $clave)
                    @php $l = $lineas[$clave] ?? null; @endphp
                    @if ($l)
                        @php
                            $esTotal = in_array($clave, $clavesTotales);
                            $esDeterminado = $clave === 'L89' || $clave === 'L91';
                            $clase = $esDeterminado ? 'fila-determinado' : ($esTotal ? 'fila-total' : '');
                        @endphp
                        <tr class="{{ $clase }}">
                            <td class="num">{{ substr($clave, 1) }}</td>
                            <td>{{ $l['desc'] }}</td>
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
            @endforeach
        </tbody>
    </table>
</body>
</html>
