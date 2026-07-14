# Hallazgos colaterales

Hallazgos detectados durante otras tareas que quedan fuera del alcance del sprint en curso. No se modifican sin autorización explícita.

> Limpieza 2026-07-14: se retiraron los puntos ya cerrados (tasa IVA hardcodeada en comandos/seeder SII, entrada FIFO sin costo explícito, entrada a costo $0 sin validación, mensaje genérico en ajuste crítico FIFO, capa FIFO huérfana al anular ajuste crítico, ausencia de integración Inventario↔Contabilidad, `CompensarPartidasClienteTest` bajo MySQL) — todos verificados resueltos en el código actual o cerrados explícitamente en commits posteriores. Queda solo lo genuinamente abierto.

## Fallo pre-existente bajo MySQL real, no investigado

`Tests\Feature\Inventario\InventarioEmpresaActivaMultitenantTest::test_reporte_reservas_usa_empresa_activa_no_empresa_hogar` falla solo bajo MySQL real (pasa en SQLite): `GET /api/inventario/reportes/reservas` devuelve 422 en vez de 200. No es un `QueryException` sino una validación de negocio que rechaza el request — causa raíz no investigada. Verificado que sigue fallando (2026-07-14).

## PMP: costo derivado a $0 sin validación (silencioso)

`InventarioValorizacionService::calcularEntradaPmp()` valida costo $0 **explícito** (rechaza si no viene `costo_cero_confirmado`), pero si el costo se **deriva** a 0 por ausencia de referencia (producto/bodega sin `costo_promedio` previo, ej. tras agotar completamente el stock), la entrada queda valorizada en $0 sin ningún error ni advertencia. Puede subvaluar silenciosamente el inventario y, en cascada, el costo de venta. Verificado en `PmpValorizacionEdgeCasesTest::test_stock_que_llega_a_cero_reinicia_el_promedio_sin_arrastrar_resabio`. No se ha decidido si amerita fix (requeriría distinguir "costo 0 derivado por ausencia de dato" de "costo 0 real intencional").

## PMP: reversa de ajuste crítico no garantiza restaurar el promedio exacto

A diferencia de FIFO (ya corregido, ver commit `7888df4`), PMP no tiene capas discretas — solo un promedio ponderado por fila de stock. Una reversa recalcula el promedio sobre el pool agregado; si el promedio ya se diluyó con movimientos posteriores al ajuste original, la reversa no necesariamente restaura el promedio previo al ajuste. No investigado a fondo, sin test dedicado.
