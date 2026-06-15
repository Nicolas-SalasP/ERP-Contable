<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Registro de lecturas PII (Ley 21.719 — Fase 3)
    |--------------------------------------------------------------------------
    | Cuando esta opcion esta activa, cada consulta de detalle de liquidacion
    | genera una fila en la tabla auditorias con operacion='LECTURA'.
    | Se puede deshabilitar en entornos de test de carga o CI para reducir I/O,
    | pero debe mantenerse en true en produccion.
    */
    'lectura_pii' => env('AUDITORIA_LECTURA_PII', true),
];
