# 🔒 Diseño — Bloqueo de Período Contable (inmutabilidad)

> Cierra los hallazgos **F-1 / F-2** de la auditoría: impedir que se creen, editen, reclasifiquen o eliminen hechos contables con fecha dentro de un período ya cerrado (post-F29).

## 1. Contexto: lo que ya existe

- Los asientos tienen `estado` con default **`CONTABILIZADO`**; el cierre F29 crea un asiento especial con `estado = 'MAYORIZADO'`, `origen_modulo = 'impuestos'`, glosa `Cierre F29 MM/AAAA` (`ImpuestosService::ejecutarF29`).
- Ya hay un check `$yaCerrado` que detecta si un mes tiene su F29 ejecutado (`ImpuestosService.php:95`).
- **Falta:** que ese cierre sea un **candado de escritura** efectivo. Hoy es solo informativo.
- Casi todos los módulos crean asientos a través de un único punto: **`AsientoContableService::registrarAsiento()`** → es el *choke point* ideal para enforcement.

## 2. Concepto

Un **Período Contable** = (`empresa_id`, `año`, `mes`). Estados: **`ABIERTO`** (default implícito) y **`CERRADO`**. Un período cerrado **rechaza toda escritura contable** cuya fecha caiga dentro de él. La reapertura es una acción privilegiada y **auditada**.

## 3. Modelo de datos

Nueva tabla `periodos_contables` (solo se persisten los **cerrados**; ausencia = abierto):

| Columna | Tipo | Nota |
|---|---|---|
| `id` | bigint | |
| `empresa_id` | FK empresas | scope multitenant (+ `HasEmpresaScope`) |
| `anio` | smallint | |
| `mes` | tinyint (1-12) | |
| `estado` | string | `CERRADO` (reservado para futuro `BLOQUEADO_DEFINITIVO`) |
| `cerrado_por` | FK usuarios | quién cerró |
| `cerrado_at` | datetime | |
| `reabierto_por` | FK usuarios nullable | última reapertura |
| `reabierto_at` | datetime nullable | |
| `motivo` | string nullable | motivo de cierre/reapertura (traza) |

`UNIQUE(empresa_id, anio, mes)`.

## 4. Servicio: `PeriodoContableService`

```
estaCerrado(int $empresaId, string|Carbon $fecha): bool
assertAbierto(int $empresaId, string|Carbon $fecha): void   // lanza PeriodoCerradoException (HTTP 422/409)
cerrar(int $empresaId, int $anio, int $mes, User $u, ?string $motivo): PeriodoContable
reabrir(int $empresaId, int $anio, int $mes, User $u, string $motivo): PeriodoContable  // privilegiado + auditado
```

## 5. Puntos de enforcement (la parte crítica)

Defensa en profundidad, igual criterio que la recomendación multitenant de la auditoría:

1. **`AsientoContableObserver`** (nuevo) sobre `creating` / `updating` / `deleting`:
   - `creating`/`updating` → `assertAbierto(empresa_id, fecha)`.
   - `deleting` (soft-delete) → bloquear si la fecha cae en período cerrado **o** `estado === 'MAYORIZADO'`.
   - Centraliza la garantía incluso para `AsientoContable::create()` directo. **Cierra F-2.**
2. **`FacturaService::reclasificarAsiento()`** → `assertAbierto` sobre la fecha **original del asiento** y sobre `$datos['fecha']` (no permite mover hacia/desde un mes cerrado). **Cierra F-1.**
3. **`FacturaService::cambiarEstado()` y flujos de pago/tesorería** → `assertAbierto` sobre la fecha del documento afectado.
4. **Auto-cierre:** al final de `ejecutarF29()`, tras crear el asiento de cierre y correr Corrección Monetaria del mes, llamar `PeriodoContableService::cerrar()`. (El asiento de cierre se crea **antes** de marcar cerrado, por lo que el observer no lo bloquea.)

> El observer es la pieza que convierte esto en una garantía real: ningún camino de código puede escribir en un mes cerrado, aunque olvide llamar al service.

## 6. API y permisos

- `GET /api/contabilidad/periodos` — lista de períodos con su estado (para la UI de "candados").
- `POST /api/contabilidad/periodos/cerrar` `{anio, mes, motivo?}` — `permiso:contabilidad.cerrar_periodo`.
- `POST /api/contabilidad/periodos/reabrir` `{anio, mes, motivo}` — solo Super Admin (jerarquía ≥ 100), `motivo` obligatorio, registra en `auditorias`.

## 7. Manejo de errores

- `PeriodoCerradoException` → respuesta JSON `409/422` con `code: 'PERIODO_CERRADO'` y mensaje claro ("El período 03/2026 está cerrado. Solicita su reapertura para modificar asientos de esa fecha.").

## 8. Migración de datos (retro-cierre)

Comando `contabilidad:cerrar-periodos-historicos`: por cada empresa, cierra automáticamente los meses que ya tengan asiento `Cierre F29 MAYORIZADO`, usando la señal `$yaCerrado` existente. Deja consistente el histórico sin intervención manual.

## 9. Tests

- Período cerrado rechaza: crear asiento en ese mes, reclasificar hacia/desde él, soft-delete de asiento del mes.
- Período abierto permite todo lo anterior.
- `ejecutarF29` cierra el mes automáticamente; reintento da `PERIODO_CERRADO` / idempotente.
- Reapertura: solo Super Admin, queda en auditoría; tras reabrir se puede volver a escribir.
- Aislamiento multitenant: cerrar el mes de la Empresa A no afecta a la Empresa B.

## 10. Decisiones de negocio (tomadas) y estado

| Decisión | Resolución |
|---|---|
| Disparador de cierre | **Solo manual** (acción explícita con `permiso:contabilidad.cerrar_periodo`). F29 **ya no** cierra automáticamente. |
| Reapertura | **Admin (jerarquía ≥ 80) + motivo obligatorio + auditado** (tabla `auditorias`). |
| Estrictez | **Bloqueo duro**: el período cerrado rechaza la escritura con `409 PERIODO_CERRADO`. |
| Alcance | **Completo**: asientos (observer) + reclasificación (guard) + tesorería/pagos (vía `registrarAsiento`, cubierto por el observer). |

### Estado: ✅ IMPLEMENTADO (en working tree, sin commitear)

- Migración `periodos_contables`, modelo `PeriodoContable` (+ `HasEmpresaScope`), `PeriodoCerradoException` (render 409).
- `PeriodoContableService` (`estaCerrado`/`assertAbierto`/`cerrar`/`reabrir`/`listarCerrados`).
- `AsientoContableObserver` (creating/updating/deleting) registrado en `AppServiceProvider` → garantía central.
- `AsientoContableService::validarMesAbierto` ahora delega al service (cierre manual; se eliminó la antigua detección por F29).
- Guard en `FacturaService::reclasificarAsiento` (fecha original + nueva).
- Permiso `contabilidad.cerrar_periodo` en `ModuloPermisos` + rutas `GET/POST /api/contabilidad/periodos[/cerrar|/reabrir]`.
- Tests: `tests/Feature/Contabilidad/BloqueoPeriodoContableTest.php` (8 casos) + actualizado `ContabilidadTest`. **Suite completa: 0 fallos.**

> **Nota:** se quitó el auto-cierre por F29 (decisión "solo manual"). El test que dependía de ese comportamiento se actualizó para cerrar el período con el nuevo servicio. F29 sigue funcionando e idempotente.
