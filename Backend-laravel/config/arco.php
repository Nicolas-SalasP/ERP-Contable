<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plazo de retención legal (años) — Ley 21.719 / Código del Trabajo / SII
    |--------------------------------------------------------------------------
    | Los registros de remuneraciones y tributarios deben conservarse por un
    | período legal (aprox. 6 años). Mientras un titular esté "bajo retención",
    | el derecho de supresión se ejecuta de forma parcial: se bloquea el
    | tratamiento y se eliminan los datos NO esenciales, conservando la identidad
    | mínima exigida por la obligación legal. Vencido el plazo, procede la
    | anonimización total.
    */
    'retencion_anios' => env('ARCO_RETENCION_ANIOS', 6),
];
