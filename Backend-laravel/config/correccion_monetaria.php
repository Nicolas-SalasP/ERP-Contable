<?php

return [
    'ipc_provider' => env('CM_IPC_PROVIDER', 'manual'),

    'ine_api' => [
        'base_url' => env('INE_API_URL', 'https://servicios.ine.cl/IPCV2/api/v1'),
        'api_key' => env('INE_API_KEY', ''),
        'timeout' => env('INE_API_TIMEOUT', 10),
    ],

    'cuentas_default' => [
        'activos' => '811001',  // CM Ganancia - Activos No Monetarios
        'depreciacion' => '821001',  // CM Pérdida - Depreciación Acumulada
        'patrimonio' => '311406',  // Corrección Monetaria Patrimonio (existente)
        'existencias' => '811002',  // CM Ganancia - Existencias
        'pasivos' => '821002',  // CM Pérdida - Pasivos No Monetarios
    ],

    'tolerancia_cuadre_pesos' => 1.00,
    
    'roles_default_cuentas' => [
        // Activos No Monetarios (Propiedades, Planta y Equipo)
        '112005' => 'ACTIVO_NO_MONETARIO',  // Edificios
        '112105' => 'ACTIVO_NO_MONETARIO',  // Maquinarias y equipos
        '112205' => 'ACTIVO_NO_MONETARIO',  // Hardware
        '112210' => 'ACTIVO_NO_MONETARIO',  // Vehiculos
        '112215' => 'ACTIVO_NO_MONETARIO',  // Camiones
        '112220' => 'ACTIVO_NO_MONETARIO',  // Muebles e instalaciones
        '111205' => 'ACTIVO_NO_MONETARIO',  // Software (intangible amortizable)

        // Depreciación / Amortización Acumulada
        '112006' => 'DEPRECIACION_ACUMULADA',  // Dep Acum. Edificios
        '112106' => 'DEPRECIACION_ACUMULADA',  // Dep Acum. Maquinarias
        '112206' => 'DEPRECIACION_ACUMULADA',  // Dep Acum. Hardware
        '112211' => 'DEPRECIACION_ACUMULADA',  // Dep Acum. Vehiculos
        '112216' => 'DEPRECIACION_ACUMULADA',  // Dep Acum. Camiones
        '112221' => 'DEPRECIACION_ACUMULADA',  // Dep Acum. Muebles
        '111206' => 'DEPRECIACION_ACUMULADA',  // Dep Acum. Software

        // Inventarios / Existencias
        '151005' => 'INVENTARIO',  // Inventario Materiales
        '151010' => 'INVENTARIO',  // Inventario Insumos
        '151225' => 'INVENTARIO',  // Mercadería en consignación

        // Patrimonio
        '311005' => 'PATRIMONIO_CAPITAL',  // Resultado Acumulado
    ],
];