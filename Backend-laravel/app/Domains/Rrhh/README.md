# Dominio RRHH y Remuneraciones

Módulo de Recursos Humanos y Remuneraciones con cumplimiento de la legislación
laboral chilena. Construido sobre el mismo patrón DDD que `Sii`, `Inventario`, etc.

> Marco legal completo: `docs/rrhh-leyes/MARCO-LEGAL-LABORAL-CHILE.md`
> Plan por fases: `docs/integraciones/PLAN-MODULO-RRHH-REMUNERACIONES.md`

## Estructura

```
Rrhh/
  Controllers/   EmpleadoController, ContratoController, LiquidacionController,
                 FiniquitoController, ParametroPrevisionalController,
                 CentralizacionController, PreviredController
  Models/        Empleado, Contrato, CargaFamiliar, HaberDescuentoContrato,
                 ConceptoRemuneracion, ParametroPrevisional, IndicadorMensual,
                 TablaImpuestoUnico, Liquidacion, LiquidacionDetalle,
                 ProvisionVacaciones, Finiquito, RrhhMapeoContable
  Services/      EmpleadoService, ContratoService
    Calculo/     LiquidacionService (motor de liquidación)
    Provisiones/ VacacionesService (devengo Art. 67)
    Finiquito/   FiniquitoService (Art. 161, 163, 70)
    Contabilidad/ CentralizacionRemuneracionesService (R5)
    Previred/    PreviredService (R6)
  Exceptions/    RrhhException (render 404/422)
```

## Estado de fases

| Fase | Entregable | Estado |
|---|---|---|
| **R1 — Personal** | Empleado + Contrato (histórico) + CargaFamiliar + banca cifrada | ✅ |
| **R2 — Parámetros** | ParametroPrevisional, IndicadorMensual, TablaImpuestoUnico + seeder | ✅ |
| **R3 — Liquidación** | ConceptoRemuneracion + LiquidacionService + Liquidacion/Detalle | ✅ |
| **R4 — Provisiones + Finiquito** | VacacionesService + FiniquitoService | ✅ |
| **R5 — Centralización contable** | CentralizacionRemuneracionesService + RrhhMapeoContable | ✅ |
| **R6 — Previred** | PreviredService — CSV 25 columnas por trabajador | ✅ |
| Futuro — Asistencia | Marcaje → horas extra/atrasos | ⏳ enganche preparado |

## Principios de diseño

1. **Cero números mágicos.** Todas las tasas, topes e indicadores (AFP, salud,
   AFC, tope imponible, IMM, tabla impuesto único, UF/UTM) se leen de
   `ParametroPrevisional`, `IndicadorMensual` y `TablaImpuestoUnico`. El código no
   hardcodea valores legales; se actualizan como dato.

2. **Inmutabilidad de cálculo.** Cada `Liquidacion` guarda referencia al
   `parametro_previsional_id` e `indicador_mensual_id` usados, para que recalcular
   parámetros futuros no altere liquidaciones históricas.

3. **Privacidad (Ley 21.719).** RUT, remuneración y datos bancarios son datos
   sensibles. Datos bancarios cifrados (`Crypt`), campo `$hidden`, multitenant
   estricto (`HasEmpresaScope`) y acceso por permiso RBAC.

4. **Multitenant desde el día 1.** Todos los modelos con `empresa_id` usan
   `HasEmpresaScope` (excepto los catálogos legales globales: `ParametroPrevisional`,
   `IndicadorMensual`, `TablaImpuestoUnico`, `ConceptoRemuneracion` del sistema).

## Endpoints (bajo `/api/rrhh`, protegidos por `permiso:rrhh.*`)

- `empleados` (CRUD), `empleados/{id}/contratos`, `contratos/{id}/terminar`
- `liquidaciones/calcular`, `liquidaciones/{id}/emitir|anular`
- `finiquitos/calcular`, `finiquitos/{id}/firmar`
- `parametros`, `indicadores`, `tabla-impuesto` (parametrización legal R2)
- `mapeo-contable` (CRUD), `centralizacion/{anio}/{mes}` (R5)
- `previred/{anio}/{mes}/archivo` (descarga CSV), `previred/{anio}/{mes}/preview` (JSON) (R6)

## Permisos RBAC

```
rrhh.empleados.ver | .crear | .editar
rrhh.contratos.crear
rrhh.remuneraciones.ver | .procesar
rrhh.parametros.ver | .editar
```

## Carga de parámetros legales

`php artisan db:seed --class=RrhhParametrosLegalesSeeder`

Carga la referencia 2026 (verificar contra fuentes oficiales antes de producción).
En producción los indicadores UF/UTM se cargan mes a mes vía `POST /api/rrhh/indicadores`.

## R5 — Centralización contable

Antes de centralizar, configurar las 6 cuentas obligatorias del mapeo:

```bash
POST /api/rrhh/mapeo-contable
{ "tipo_cuenta": "GASTO_REMUNERACIONES", "cuenta_contable_codigo": "4101" }
# ... repetir para GASTO_LEYES_SOCIALES, PASIVO_LIQUIDO_PAGAR,
#     PASIVO_RETENCIONES_PREVISIONALES, PASIVO_IMPUESTO_UNICO, PASIVO_LEYES_SOCIALES
```

Luego de emitir todas las liquidaciones del período:
```bash
POST /api/rrhh/centralizacion/2026/6
```

Genera un asiento doble-entrada: DEBE (gastos) = HABER (pasivos). Idempotente.

## R6 — Archivo Previred

```bash
GET /api/rrhh/previred/2026/6/archivo   → descarga CSV
GET /api/rrhh/previred/2026/6/preview   → JSON para previsualización
```

Formato documentado en `docs/integraciones/FORMATO-PREVIRED.md`.
Flujo: calcular → emitir → centralizar → descargar Previred → subir en previred.com.

## Tests

`vendor/bin/phpunit tests/Feature/Rrhh/` — cubre cifrado de banca, histórico de
contratos, cálculo previsional (AFP/salud/AFC/impuesto único/topes), finiquitos
(Art. 161/163), centralización contable (partida doble, idempotencia),
archivo Previred (códigos AFP/ISAPRE, columnas, multitenant) y RBAC.
