# 👥 Plan de Módulo — Recursos Humanos y Remuneraciones (Chile)

**Fecha:** 2026-06-11
**Objetivo:** construir en el ERP un módulo de RRHH + Remuneraciones que cubra personal,
sueldos, liquidaciones, provisión de vacaciones y finiquitos, preparado para integrar
**control de asistencia** a futuro y para alimentar **Previred** (Fase 2 de integraciones).

> ⚠️ Módulo legalmente sensible. La estrategia clave es **parametrizar** todo valor legal
> (tasas AFP, tope imponible, tabla de impuesto único, UF/UTM, etc.) en una tabla mantenible,
> nunca hardcodearlos. Así se actualizan mes a mes sin tocar código y sin riesgo de cálculos
> obsoletos.

---

## 1. Alcance

**Incluye:**
- **Personal (RRHH):** ficha del trabajador, contratos, cargos, centros de costo, jornada.
- **Estructura de remuneración:** haberes (imponibles/no imponibles) y descuentos por contrato.
- **Liquidación de sueldos (motor de cálculo):** mensual, con todos los componentes legales chilenos.
- **Provisiones:** vacaciones (devengo mensual), y base para gratificación/finiquito.
- **Finiquitos:** indemnización años de servicio, aviso previo, vacaciones proporcionales.
- **Parámetros previsionales:** AFP, salud/Isapre, AFC (cesantía), topes, asignación familiar,
  impuesto único, UF/UTM/UTA, ingreso mínimo.

**Integra (puntos de salida):**
- **Contabilidad:** centralización mensual de remuneraciones y provisiones (asientos automáticos,
  vía `AsientoContableService` ya existente).
- **Previred:** archivo previsional mensual (Fase 2 — el verdadero objetivo).
- **Futuro — Control de asistencia:** marca de entrada/salida → horas extra, atrasos, inasistencias
  que alimentan la liquidación. Se deja el **contrato/enganche** preparado, sin implementarlo aún.

**No incluye (por ahora):** reclutamiento/ATS, evaluación de desempeño, capacitación.

---

## 2. Ubicación en la arquitectura

Nuevo dominio DDD **`app/Domains/Rrhh`** (mismo estilo que `Sii`, `Inventario`):
```
Rrhh/
  Controllers/   Models/   Services/   (Calculo/, Provisiones/, Finiquito/)
  Jobs/          Events/   Support/
```
**Multitenant desde el día 1:** todos los modelos con `empresa_id` + `HasEmpresaScope` (P1 ya nos
dejó el patrón y el test guardián que lo exige).

---

## 3. Modelo de datos (entidades principales)

| Entidad | Descripción |
|---|---|
| `Empleado` | Ficha: RUT, nombre, fecha nac., dirección, AFP, salud (Fonasa/Isapre + plan UF), cargas familiares, datos bancarios. |
| `Contrato` | Tipo (indefinido/plazo fijo/obra), fechas, cargo, jornada, sueldo base, centro de costo, causal de término. |
| `HaberDescuentoContrato` | Haberes/descuentos fijos por contrato (colación, movilización, anticipos, etc.). |
| `ConceptoRemuneracion` | Catálogo de conceptos: tipo (haber imponible / haber no imponible / descuento legal / descuento voluntario), regla de cálculo. |
| `ParametroPrevisional` | **Parametrización legal** versionada por período: tasas AFP por administradora, 7% salud, AFC, tope imponible (UF), asignación familiar por tramo, ingreso mínimo, gratificación tope. |
| `IndicadorMensual` | UF, UTM, UTA por período (para topes e impuesto único). |
| `TablaImpuestoUnico` | Tramos del impuesto único de 2ª categoría por período. |
| `Liquidacion` | Cabecera: empleado, período, totales (imponible, tributable, líquido). |
| `LiquidacionDetalle` | Líneas: cada haber/descuento con su monto calculado. |
| `ProvisionVacaciones` | Saldo de días y monto provisionado por empleado (devengo mensual). |
| `Finiquito` | Cálculo de término: indemnizaciones, vacaciones proporcionales, totales. |

> **Privacidad (Ley 21.719 de protección de datos):** RUT, salud, remuneración y datos bancarios son
> datos personales sensibles. Aislamiento multitenant estricto + acceso por permiso + datos bancarios
> cifrados (`Crypt`, patrón SII).

---

## 4. Componentes de la liquidación chilena (parametrizados)

**Haberes imponibles:** sueldo base, gratificación legal (Art. 50: 25% con tope 4,75 IMM/12),
horas extra, comisiones, bonos imponibles.
**Haberes no imponibles:** colación, movilización, asignación familiar, viáticos, asignación de pérdida de caja.
**Descuentos legales (previsionales):**
- **AFP:** 10% + comisión de la administradora, sobre imponible con tope (UF).
- **Salud:** 7% (Fonasa) o plan pactado en UF (Isapre).
- **AFC (seguro cesantía):** 0,6% trabajador (indefinido) sobre imponible con tope.
- **Impuesto único 2ª categoría:** sobre base tributable, según `TablaImpuestoUnico` del período.
**Descuentos voluntarios:** anticipos, préstamos, APV, cuotas sindicales.

> Cada uno es un `ConceptoRemuneracion` con su regla; el motor de cálculo (`LiquidacionService`)
> los resuelve leyendo `ParametroPrevisional`/`IndicadorMensual`/`TablaImpuestoUnico` del período.
> **Cero números mágicos en el código.**

---

## 5. Integración con Contabilidad

Centralización mensual automática (asiento por período):
- Gasto: remuneraciones, leyes sociales (aporte empleador AFC, mutual, SIS).
- Pasivos: líquido por pagar, retenciones (AFP/salud/impuesto/AFC), provisión vacaciones.
- Vía `AsientoContableService::registrarAsiento` (ya valida partida doble y período cerrado — P0/feature).

---

## 6. Fases de implementación (incremental, validando cada una)

| Fase | Entregable | Depende de | Estado |
|---|---|---|---|
| **R1 — Personal** | `Empleado` + `Contrato` + CRUD + permisos RBAC + multitenant + tests. **Base navegable.** | — | ✅ construido |
| **R2 — Parámetros** | `ParametroPrevisional`, `IndicadorMensual`, `TablaImpuestoUnico` + carga/seed desde fuentes oficiales (Previred publica indicadores mensuales). | R1 | ✅ construido |
| **R3 — Motor de liquidación** | `ConceptoRemuneracion` + `LiquidacionService` (cálculo completo) + `Liquidacion`/Detalle (PDF pendiente). | R1, R2 | ✅ construido |
| **R4 — Provisiones + Finiquitos** | Devengo de vacaciones + cálculo de finiquito (causales, indemnizaciones). | R3 | ✅ construido |
| **R5 — Centralización contable** | Asiento automático de remuneraciones y provisiones. | R3, R4 | ⏳ pendiente |
| **R6 — Previred** | Archivo previsional mensual + envío (cierra el objetivo de Fase 2). | R3 | ⏳ pendiente |
| **Futuro — Asistencia** | Contrato de eventos de marcaje → horas extra/atrasos hacia la liquidación. | R3 | ⏳ enganche preparado |

> **Implementado (2026-06-11):** R1–R4 en `app/Domains/Rrhh` con tests verdes.
> Marco legal investigado y documentado en `docs/rrhh-leyes/MARCO-LEGAL-LABORAL-CHILE.md`.
> Valores legales 2026 en `RrhhParametrosLegalesSeeder` (tope 90 UF, AFC 135,2 UF,
> IMM $539.000, SIS 1,62%, tabla impuesto único 8 tramos). Falta R5 y R6.

---

## 7. Parámetros legales — fuente y mantenimiento

Los valores (UF, UTM, tope imponible 87,8 UF aprox., tasas AFP, tramos impuesto único, asignación
familiar, ingreso mínimo) **cambian periódicamente**. Diseño:
- Tabla `parametros_previsionales` versionada por período de vigencia.
- Seed inicial + comando `rrhh:cargar-indicadores` (a futuro, scrapeo/import del archivo mensual de Previred).
- **Antes de producción:** verificar cada valor contra fuente oficial (Previred / SII / Dirección del Trabajo).

> En el diseño NO fijo valores; se cargan como dato. Esto evita cálculos legalmente incorrectos por
> código desactualizado.

---

## 8. Decisiones

**Tomadas:**
1. ✅ **Nombre del dominio: `Rrhh`** (amplio: personal + remuneraciones + finiquitos + futuro asistencia).
2. ✅ **R1 = ficha completa:** `Empleado` + `Contrato`(s) con **histórico de múltiples contratos** +
   **cargas familiares** + **datos bancarios cifrados** (`Crypt`).

**Pendientes (más adelante):**
3. **PDF de liquidación (R3):** ¿layout propio o estándar? (hay `dompdf` en el ERP).
4. **Mantenimiento de parámetros previsionales (R2):** carga manual en admin al inicio, y automatizar
   el import del archivo mensual de Previred después.

> **Estado:** plan en revisión por el equipo antes de construir R1. No se ha escrito código del módulo.

---

*Plan; sin código aún. Construcción sugerida por fases R1→R6, validando con tests entre cada una,
igual que P0/P1.*
