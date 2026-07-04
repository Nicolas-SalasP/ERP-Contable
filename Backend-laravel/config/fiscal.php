<?php

/*
|--------------------------------------------------------------------------
| Parametros tributarios generales (Chile)
|--------------------------------------------------------------------------
|
| Tasa de IVA vigente segun Ley sobre Impuesto a las Ventas y Servicios
| (D.L. 825). Un solo punto de cambio para toda la app: antes estaba
| hardcodeada como 0.19 en FacturaService, OrdenCompraService y
| EmitirDtePruebaCommand, sin ningun lugar unico donde actualizarla si
| el legislador cambia la tasa.
*/

return [
    'tasa_iva' => (float) env('FISCAL_TASA_IVA', 0.19),
];
