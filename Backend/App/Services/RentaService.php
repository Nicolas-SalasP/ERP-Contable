<?php
namespace App\Services;

use App\Repositories\RentaRepository;

class RentaService
{
    private $repo;

    public function __construct()
    {
        $this->repo = new RentaRepository();
    }

    public function obtenerMapeoCuentas(): array {
        return [
            'mapeadas' => $this->repo->getMapeoActual(),
            'disponibles' => $this->repo->getCuentasDisponiblesMapeo(),
            'conceptos' => [
                'INGRESOS_DEL_GIRO' => 'Ingresos del Giro (Ventas)',
                'OTROS_INGRESOS' => 'Otros Ingresos',
                'REMUNERACIONES_PAGADAS' => 'Remuneraciones Pagadas',
                'HONORARIOS_PAGADOS' => 'Honorarios Pagados',
                'ARRIENDOS_PAGADOS' => 'Arriendos Pagados',
                'GASTOS_GENERALES' => 'Gastos Generales y Otros'
            ]
        ];
    }

    public function guardarMapeoCuenta($codigoCuenta, $conceptoSII) {
        if(empty($codigoCuenta) || empty($conceptoSII)) throw new \Exception("Datos incompletos");
        return $this->repo->guardarMapeo($codigoCuenta, $conceptoSII);
    }

    public function eliminarMapeoCuenta($id) {
        return $this->repo->eliminarMapeo($id);
    }

    public function calcularBaseImponible(int $anio)
    {
        $config = $this->repo->getConfiguracionTributaria();
        $regimen = $config['regimen_tributario'] ?? '14_D3';
        
        $tasa = (float)($config['tasa_impuesto'] ?? 25.00);
        if ($regimen === '14_A') $tasa = 27.00;
        if ($regimen === '14_D8') $tasa = 0.00;

        $movimientosSII = $this->repo->getMovimientosPorConceptoSII($anio);

        $desglose = [
            'ingresos_giro' => 0,
            'otros_ingresos' => 0,
            'compras' => 0,
            'remuneraciones_pagadas' => 0,
            'honorarios_pagados' => 0,
            'arriendos_pagados' => 0,
            'gastos_generales' => 0,
            'depreciacion' => 0
        ];

        foreach ($movimientosSII as $mov) {
            $concepto = $mov['concepto_sii'];
            if ($concepto === 'INGRESOS_DEL_GIRO') $desglose['ingresos_giro'] = (float)$mov['total_ingresos'];
            if ($concepto === 'OTROS_INGRESOS') $desglose['otros_ingresos'] = (float)$mov['total_ingresos'];
            if ($concepto === 'REMUNERACIONES_PAGADAS') $desglose['remuneraciones_pagadas'] = (float)$mov['total_egresos'];
            if ($concepto === 'HONORARIOS_PAGADOS') $desglose['honorarios_pagados'] = (float)$mov['total_egresos'];
            if ($concepto === 'ARRIENDOS_PAGADOS') $desglose['arriendos_pagados'] = (float)$mov['total_egresos'];
            if ($concepto === 'GASTOS_GENERALES') $desglose['gastos_generales'] = (float)$mov['total_egresos'];
        }

        if ($regimen === '14_A') {
            $desglose['compras'] = $this->repo->getTotalComprasDevengadas($anio);
            $desglose['depreciacion'] = $this->repo->getDepreciacionContableNormal($anio);
        } else {
            $desglose['compras'] = $this->repo->getTotalComprasPagadas($anio);
            $desglose['depreciacion'] = $this->repo->getTotalActivoFijoComprado($anio);
        }

        $ingresos = $desglose['ingresos_giro'] + $desglose['otros_ingresos'];
        $egresos = $desglose['compras'] + $desglose['remuneraciones_pagadas'] + 
                   $desglose['honorarios_pagados'] + $desglose['arriendos_pagados'] + 
                   $desglose['gastos_generales'] + $desglose['depreciacion'];

        $baseImponible = $ingresos - $egresos;
        
        $impuestoDeterminado = $baseImponible > 0 ? ($baseImponible * ($tasa / 100)) : 0;

        return [
            'anio_comercial' => $anio,
            'anio_tributario' => $anio + 1,
            'regimen_tributario' => $regimen,
            'tasa_impuesto' => $tasa,
            'regla_calculo' => ($regimen === '14_A') ? 'DEVENGADO' : 'FLUJO_DE_CAJA',
            'resumen' => [
                'total_ingresos' => $ingresos,
                'total_egresos' => $egresos,
                'base_imponible' => $baseImponible,
                'impuesto_determinado' => $impuestoDeterminado
            ],
            'desglose' => $desglose
        ];
    }
}