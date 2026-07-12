# QA manual con Playwright MCP — 2026-07-12

Ronda de continuación sobre Tenri ERP Cloud. La ronda anterior ya cubrió login, contabilidad (asientos + cierre de período) e inventario/picking parcialmente — no se repitieron acá salvo verificación indirecta (Libro Mayor tras conciliación bancaria).

Entorno: backend `php artisan serve` (puerto 8001, MySQL vía XAMPP), frontend `pnpm dev` (puerto 3000). Login como `superadmin@tenri.cl` / `password123` (empresa principal, usuario preexistente — **no se tocó su contraseña ni datos**).

## Resumen de resultados

| Módulo | Resultado |
|---|---|
| RRHH (empleado → contrato → liquidación → emisión) | OK, con 1 bug real de UI (fecha) y 1 bug real de manejo de errores |
| Multitenancy (empresa nueva vs. empresa existente) | OK — aislamiento correcto a nivel API |
| Tesorería (movimiento manual → conciliación) | OK |
| Facturación DTE | No probado — sin certificado digital SII configurado localmente (gap de datos de prueba, documentado abajo) |

---

## 1. RRHH — empleado, contrato, liquidación, emisión

La empresa de prueba (empresa_id=1, la principal) partió con 0 empleados, tal como se indicó. Se creó todo el flujo desde la UI normal:

1. **Nuevo empleado** (`/rrhh/empleados` → "Nuevo empleado"): RUT `11.111.111-1` (válido), nombre "Juan QA Playwright Prueba", AFP Habitat, FONASA, fecha ingreso `2026-01-05`.
   - **Bug real encontrado (bloqueante, ya corregido en el entorno local para continuar la prueba):** el primer intento de guardar devolvió `500` con el error crudo de PHP expuesto en el modal de error del frontend: `ParagonIE\CipherSweet\KeyProvider\StringProvider::__construct(): Argument #1 ($rawKey) must be of type string, null given...`. Causa: `CIPHERSWEET_KEY` no estaba seteado en `Backend-laravel/.env` local (usado para cifrar RUT/datos bancarios de empleados, Ley 21.719). Se corrigió localmente con `php artisan ciphersweet:generate-key` (comando oficial de `spatie/laravel-ciphersweet`) para poder continuar — esto es un gap de configuración de entorno de desarrollo, no un bug de producción per se, **pero sí es un bug real que el mensaje de error crudo (stack trace / nombre de clase interna) se le muestre al usuario final** en vez de un mensaje genérico como en otros 500 (ej. el dashboard sí muestra "Ocurrió un error al procesar la solicitud..."). Esto indica manejo de excepciones inconsistente entre distintos módulos/controladores — revisar el exception handler global vs. el que usa el módulo de empleados.
   - Tras el fix, el empleado se creó correctamente. El RUT se muestra enmascarado en la tabla (`11.***.**1-1`), confirmando que el cifrado/masking funciona.

2. **Bug real — desfase de fecha en 3 pantallas distintas (off-by-one, un día atrás):**
   - Se ingresó fecha de ingreso `2026-01-05` en el formulario de empleado. La tabla de empleados la mostró como **`04-01-2026`**.
   - Se verificó contra la base de datos directamente (`fecha_ingreso_empresa` en tabla `empleados`): el valor almacenado es correcto, `2026-01-05`. El bug es puramente de renderizado en el frontend (probablemente `new Date('2026-01-05')` interpretado como UTC medianoche y mostrado en timezone local, que queda un día atrás).
   - El mismo patrón se repitió en **Contratos** (`/rrhh/contratos`): fecha inicio ingresada `2026-01-05`, mostrada como `04-01-2026`.
   - Y otra vez en **Cartola/Movimientos bancarios** (`/banco/cartola`): fecha ingresada `12/07/2026`, mostrada en el historial como `11-07-2026`.
   - Conclusión: es un bug transversal en el helper/utilidad de formateo de fechas usado por al menos 3 módulos distintos (RRHH empleados, RRHH contratos, Tesorería). Vale la pena buscar el helper compartido (probablemente en `Frontend/src/Utilidades/formato.js`) y revisar el parseo de fechas tipo `YYYY-MM-DD` sin hora.

3. **Contrato** (`/rrhh/contratos`): tipo Indefinido, sueldo base $800.000, cargo "Analista QA", fecha inicio `2026-01-05`. Se creó correctamente, quedó en estado VIGENTE.

4. **Cálculo de liquidación** (`/rrhh/liquidaciones`, período Julio 2026):
   - Primer intento falló con `422` y mensaje claro y correcto: *"No hay indicadores mensuales (UF/UTM) cargados para 7/2026."* — esto es una validación esperada y bien hecha, no un bug. La empresa de prueba tenía indicadores UF/UTM cargados solo hasta Junio 2026.
   - Se registró el indicador de Julio 2026 (UF $39.900, UTM $71.800) vía la UI normal (`Parámetros Previsionales` → pestaña "Indicadores UF/UTM" → "Registrar indicador"), tal como permite el flujo estándar de la app.
   - Reintentado el cálculo: funcionó correctamente. Desglose para sueldo base $800.000:
     - Gratificación Legal Art. 50: $200.000
     - AFP Cotización Obligatoria (10%): -$100.000
     - AFP Comisión Habitat (1.27%): -$12.700
     - Salud FONASA (7%): -$70.000
     - Seguro Cesantía Trabajador (0.6%): -$6.000
     - Total haberes $1.000.000, total descuentos $188.700, líquido a pagar **$811.300**.
   - Los cálculos de AFP/salud/AFC coinciden con las tasas mostradas en "Parámetros Previsionales" (10% AFP, 7% FONASA, 0.6% AFC indefinido trabajador), y la gratificación legal Art. 50 (25% del sueldo, tope aplicable) se ve razonable.

5. **Emisión de liquidación**: botón "Emitir" → modal de confirmación ("Una vez emitida no se puede recalcular") → confirmado → estado cambió de `BORRADOR` a `EMITIDA`. Flujo end-to-end completo y funcional.

---

## 2. Multitenancy

Se creó una empresa y usuario nuevos **desde cero** (nunca se tocó `nicolas@tenri.cl` ni `superadmin@tenri.cl`), siguiendo el patrón de inserción que usa el propio `EmpresaController::store` de la app (Eloquent `Empresa::create()` para disparar el `EmpresaObserver` que provisiona plan de cuentas base, más `User::create()` y una fila en la tabla pivote `empresa_user` — todo `INSERT`, ningún `UPDATE` sobre filas preexistentes):

- Empresa nueva: "QA Test Empresa 2", RUT `76543210-K` (empresa_id=5). El observer provisionó automáticamente 109 cuentas del plan de cuentas base.
- Usuario nuevo: `qa-playwright-2@tenri-test.local`, rol Administrador (rol_id=2).

Pruebas realizadas, todas en una pestaña de navegador separada (sesión independiente):

- Login con el usuario nuevo: OK, aterriza en dashboard de "QA Test Empresa 2".
- `/rrhh/empleados` bajo esta empresa: lista vacía, correcto (0 empleados, aislado de la empresa 1 que ya tenía el empleado de prueba creado en la sección anterior).
- **Prueba IDOR directa contra la API** (con el token Bearer real de la sesión de la empresa 2, extraído de `localStorage.erp_token`):
  - `GET /api/rrhh/empleados/2` (empleado real de la empresa 1) → **`404` `"El empleado no existe o no pertenece a la empresa."`** — correcto.
  - `GET /api/rrhh/empleados/1` → mismo resultado, `404` correcto.
  - `GET /api/rrhh/contratos/1` (contrato real de la empresa 1) → **`404` `"El contrato no existe o no pertenece a la empresa."`** — correcto.
  - `GET /api/rrhh/empleados` (listado propio) → `200`, `total: 0` — correcto.

No se encontró ninguna fuga multitenant en los endpoints probados. El aislamiento por `empresa_id` vía `EmpresaScope` + validación explícita en los controllers (mensajes "no pertenece a la empresa") funciona como se espera.

---

## 3. Tesorería — conciliación bancaria

En la cuenta "Scotiabank Chile - 000991980431" de la empresa principal (que partía con "Banco 100% Cuadrado", sin movimientos pendientes):

1. Se creó un movimiento manual de ingreso desde `/banco/cartola` → "Registro Manual": $150.000, descripción "QA Playwright - abono de prueba", fecha 12/07/2026 (mostrada con el bug de -1 día ya reportado arriba).
2. El movimiento apareció como "Pendiente" en Mesa de Conciliación (`/banco/conciliacion`).
3. Se concilió usando la pestaña "Imputación Directa" (no había factura real para calzar contra el monto arbitrario de prueba), imputando contra la cuenta `[501105] Ventas Nacionales (INGRESO)`. La vista previa contable mostró correctamente el asiento de partida doble: Debe `[110205] Scotiabank Chile $150.000` / Haber `[501105] Ventas Nacionales $150.000`.
4. Tras "Generar Asiento Contable": la Mesa de Conciliación volvió a mostrar "¡Banco 100% Cuadrado!" — el movimiento se conciliró y el asiento contable se generó correctamente.

No se encontraron bugs en este flujo. La UI de conciliación (con las dos modalidades "Pago/Cobro Facturas" e "Imputación Directa") es clara y el preview contable en tiempo real ayuda a verificar antes de confirmar.

---

## 4. Facturación DTE — GAP, no probado

`/sii/certificado` muestra "Sin certificado digital" — no hay certificado `.pfx`/`.p12` cargado en el entorno local, y no hay modo mock/sandbox visible para emitir DTE sin él. Siguiendo la instrucción explícita de la tarea de no forzar esto contra el SII real, **no se probó el flujo de emisión de factura electrónica end-to-end**. Para cubrir esto en una futura ronda haría falta:
- Un certificado de prueba (autofirmado o de un ambiente de certificación SII), o
- Confirmar si existe algún modo de "simular" el timbrado/envío en `app/Domains/Sii/` que no requiera el certificado real, para habilitar pruebas E2E sin depender de credenciales SII reales.

---

## Bugs reales encontrados (resumen para decidir si se arreglan)

1. **[MEDIO] Mensaje de error crudo expuesto al usuario** cuando falla la creación de un empleado por un error de configuración/backend no manejado (ej. `CIPHERSWEET_KEY` ausente). El modal de error mostró el mensaje de excepción PHP completo (`ParagonIE\CipherSweet\KeyProvider\StringProvider::__construct()...`) en vez de un mensaje genérico, a diferencia de otros módulos (ej. dashboard) que sí muestran "Ocurrió un error al procesar la solicitud...". Revisar el exception handling en el flujo de creación de empleados (`EmpleadoController`/`RrhhException` o el handler global) para uniformar el comportamiento.
2. **[MEDIO] Bug de fecha off-by-one (-1 día) en al menos 3 pantallas**: listado de Empleados (`fecha_ingreso_empresa`), listado de Contratos (`fecha_inicio`), e historial de Movimientos bancarios (`fecha_del_movimiento`). Confirmado que es solo de **renderizado** (la base de datos almacena la fecha correcta) — causado casi seguro por un parseo de fecha `YYYY-MM-DD` como UTC-medianoche que luego se muestra en timezone local (Chile es UTC-3/-4), restando un día. Buscar el helper de formateo de fechas compartido (`Frontend/src/Utilidades/formato.js` es candidato) y corregir el parseo para que trate esas fechas como fecha local sin hora, no como timestamp UTC.

## Fixes aplicados (sesión posterior, misma fecha)

Los 2 bugs reales de esta ronda se corrigieron, junto con 2 hallazgos adicionales de una auditoría
de seguimiento centrada en el fix de mutualidad de la sesión anterior. Todo commiteado en
`NSalas-dev`, sin push.

1. **Mensaje de error crudo (bug #1 de esta ronda) → corregido con una red de seguridad global,
   no un parche puntual.** `bootstrap/app.php` ya tenía un `render()` específico para
   `QueryException` (evita filtrar SQL crudo) pero nada para el resto de excepciones inesperadas —
   cualquier error de config/librería de terceros (como el de `CIPHERSWEET_KEY` que gatilló este
   bug) se filtraba tal cual al cliente. Se agregó un catch-all de `\Throwable` que devuelve un
   mensaje genérico cuando `APP_DEBUG=false` (el valor real en producción). **Riesgo real durante
   la implementación:** por tipar el catch-all como `\Throwable`, en un primer intento también
   capturaba `ValidationException` (422) y cualquier `HttpExceptionInterface` (404/403/405/etc.),
   lo que habría convertido esas respuestas en 500 genérico en **toda la API** — se corrigió
   excluyendo esos dos tipos explícitamente antes de commitear. Test de regresión dedicado
   (`ManejoGlobalExcepcionesTest`) verifica las 3 cosas: excepción inesperada → 500 genérico sin
   filtrar el mensaje interno; `ValidationException` → sigue en 422; ruta inexistente → sigue en 404.

2. **Fecha off-by-one (bug #2 de esta ronda) → corregido en la raíz, no solo en los 3 lugares
   reportados.** La causa real no era la ausencia total de un fix — RRHH ya tenía un intento previo
   de corrección (`Modulos/Rrhh/Utilidades/formato.js`, sesión 2026-07-09) que agregaba `T00:00:00`
   solo cuando el string NO traía hora. El problema: Eloquent serializa columnas `date`/`datetime`
   como `"AAAA-MM-DDT00:00:00.000000Z"`, que **sí** trae `T` pero sigue siendo UTC — ese caso se
   saltaba el fix y seguía mostrando un día atrás. Se reescribió `formatFecha` en
   `Frontend/src/Utilidades/formato.js` (nuevo, a nivel de app) tomando solo los primeros 10
   caracteres (la fecha calendario, sin importar el sufijo) y construyendo la fecha con
   `new Date(anio, mes, dia)` (constructor local, no UTC) — elimina la dependencia de parsear
   sufijos de timezone por completo. RRHH ahora reexporta desde ahí (una sola fuente), y las 2
   pantallas de Tesorería (`CartolaBancaria.jsx`, `MesaConciliacion.jsx`) que no tenían ningún fix
   se migraron al mismo helper. Tests de regresión nuevos, incluyendo el caso específico
   `"2026-01-05T00:00:00.000000Z"` que reproduce exactamente el bug encontrado en QA.

3. **[Hallazgo adicional, auditoría de seguimiento] Fallback incorrecto de código Previred para
   IST/Mutual CChC.** El fix de mutualidad de la sesión anterior (commit `7485f54`) dejaba
   `codigo_previred = null` para IST y Mutual CChC (códigos oficiales no confirmados, a propósito),
   pero el fallback `?? $liq->parametro->mutual_codigo` no distinguía "empresa sin mutualidad
   asignada" de "empresa con mutualidad asignada pero código Previred desconocido" — en el segundo
   caso caía silenciosamente al parámetro legacy **global**, que representa la elección de otra
   empresa, no la de la que se está declarando. Corregido: ahora loguea una advertencia y usa el
   default documentado en vez de un valor de otra empresa. Test de regresión agregado.

4. **[Hallazgo adicional] Enum `tipo_asiento` sin validación en el endpoint público + fixtures de
   test con valores inválidos.** `AsientoContableController::store()` validaba `tipo_asiento` como
   `nullable|string` sin restricción — un valor fuera del enum real de la columna
   (`ingreso`/`egreso`/`traspaso`/`''`) pasaba la validación y explotaba como `QueryException`/500
   en MySQL (SQLite no lo detecta, por eso nunca apareció en la suite normal). Se agregó `in:` a la
   validación. De paso, se encontraron y corrigieron 2 archivos de test con fixtures usando
   `'MANUAL'`/`'manual'` como `tipo_asiento` (valores que nunca calzaron con el enum real) — pasaban
   silenciosamente en SQLite pero fallaban corriendo contra MySQL real.

Verificación: backend 2278/2278 (SQLite) + Contabilidad/Core/Rrhh validados contra MySQL real,
frontend 1533/1533, Pint limpio.

## Gaps de datos de prueba (no bugs, documentados para transparencia)

- Sin certificado digital SII configurado → no se pudo probar emisión de DTE end-to-end.
- La empresa de prueba no tenía indicadores UF/UTM del mes actual (Julio 2026) cargados — se completó vía la UI normal, no es un bug (la validación funcionó correctamente y con mensaje claro).
- `CIPHERSWEET_KEY` no estaba configurado en `.env` local — se generó con el comando oficial `php artisan ciphersweet:generate-key` para poder continuar la prueba de RRHH. Esto es una configuración de entorno de desarrollo, no algo a "arreglar" en código, pero vale la pena documentarlo en el README/setup de onboarding de desarrolladores nuevos si no está ya.
