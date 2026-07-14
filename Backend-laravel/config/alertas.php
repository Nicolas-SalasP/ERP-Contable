<?php

use App\Domains\Comercial\Services\Alertas\CxcVencidaEvaluador;
use App\Domains\Comercial\Services\Alertas\CxpVencidaEvaluador;
use App\Domains\Contabilidad\Services\Alertas\F29SinDeclararEvaluador;
use App\Domains\Contabilidad\Services\Alertas\PeriodoSinCerrarEvaluador;
use App\Domains\Rrhh\Services\Alertas\ContratoPorVencerEvaluador;

/**
 * Catalogo de evaluadores del motor de alertas. Cada clase implementa
 * App\Domains\Alertas\Contracts\EvaluadorAlerta y vive en el dominio dueno del dato que vigila.
 */
return [
    'evaluadores' => [
        PeriodoSinCerrarEvaluador::class,
        F29SinDeclararEvaluador::class,
        CxcVencidaEvaluador::class,
        CxpVencidaEvaluador::class,
        ContratoPorVencerEvaluador::class,
    ],
];
