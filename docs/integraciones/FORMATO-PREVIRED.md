# Formato Previred — Archivo de 105 Posiciones

**Fecha:** 2026-06-11
**Fuente oficial:** https://www.previred.com/web/previred/ayuda-planilla
**Marco legal:** DL 3500, Ley 18.469, Ley 19.728, DL 824 LIR

> **Verificar antes de producción.** El formato y las posiciones de columna pueden
> cambiar con actualizaciones del sistema Previred. Contrastar este documento con la
> especificación oficial vigente en previred.com antes de enviar el primer archivo.

---

## 1. Descripción general

El archivo previsional mensual ("Planilla de Cotizaciones") se genera desde el ERP
para cada período en que haya liquidaciones **EMITIDAS**. Se descarga como `.txt` y se
sube al portal Previred para declarar y pagar las cotizaciones del mes.

**Endpoints:**
```
GET /api/rrhh/previred/{anio}/{mes}/archivo   ← descarga el .txt
GET /api/rrhh/previred/{anio}/{mes}/preview   ← vista previa JSON (array posicional)
```

Permiso requerido: `rrhh.remuneraciones.ver`

---

## 2. Características del archivo

| Propiedad        | Valor                               |
|-----------------|-------------------------------------|
| Separador       | punto y coma (`;`)                  |
| Codificación    | UTF-8                               |
| Fin de línea    | CRLF (`\r\n`)                       |
| Encabezado      | **Ninguno** — la primera línea es ya un registro de datos |
| Filas           | Una por trabajador con liquidación EMITIDA en el período |
| Campos por fila | **105** (posicionales, separados por `;`)            |

---

## 3. Códigos de tipo de movimiento

El campo 14 (posición 0-indexada 13) indica el movimiento del trabajador en el período:

| Código | Significado                          |
|--------|--------------------------------------|
| `0`    | Sin movimiento (mes completo)        |
| `1`    | Contratación (inicio en el período)  |
| `2`    | Retiro (término en el período)       |
| `3`    | Subsidio licencia médica             |
| `4`    | Permiso sin goce de sueldo           |
| `5`    | Incorporación nuevo lugar de trabajo |
| `6`    | Accidente del trabajo                |
| `7`    | Reliquidación                        |
| `8`    | Subsidio maternal                    |
| `11`   | Otros                                |

El ERP calcula automáticamente `0`, `1` y `2` según las fechas del contrato.
Los códigos `3`–`11` requieren datos no disponibles en el ERP actual (ver sección 5).

**Regla de cálculo:**
- Si `contrato.fecha_inicio` cae dentro del mes → `1`
- Si `contrato.fecha_termino` cae dentro del mes → `2`
- En cualquier otro caso → `0`

---

## 4. Layout completo — 105 campos

Los campos numéricos sin valor disponible se emiten como `0`.
Los campos alfanuméricos sin valor disponible se emiten vacíos `""`.

### BLOQUE 1 — Identificación trabajador (campos 1–13)

| # | Nombre               | Fuente en el ERP                              | Default |
|---|----------------------|-----------------------------------------------|---------|
| 1 | RUT trabajador       | `empleados.rut` (solo dígitos, sin DV)        | —       |
| 2 | DV trabajador        | `empleados.rut` (dígito verificador)          | —       |
| 3 | Apellido paterno     | `empleados.apellido_paterno` (mayúsculas)     | —       |
| 4 | Apellido materno     | `empleados.apellido_materno` (mayúsculas)     | —       |
| 5 | Nombres              | `empleados.nombres` (mayúsculas)              | —       |
| 6 | Sexo                 | **Sin dato ERP** (`empleados.sexo` no existe) | `""`    |
| 7 | Nacionalidad         | **Sin dato ERP** (`empleados.nacionalidad` no existe) | `""` |
| 8 | Tipo de pago         | Valor fijo `3` (transferencia bancaria)       | `3`     |
| 9 | Período desde        | Período liquidado (AAAAMM)                    | —       |
|10 | Período hasta        | Período liquidado (AAAAMM)                    | —       |
|11 | Régimen previsional  | Valor fijo `1` (AFP)                          | `1`     |
|12 | Tipo de trabajador   | Valor fijo `1` (activo)                       | `1`     |
|13 | Días trabajados      | Calculado (ver regla días proporcionales)     | —       |

**Regla días trabajados:**
- Mes completo (sin inicio/fin en el período): días del mes (28/29/30/31).
- `contrato.fecha_inicio` dentro del mes: días desde inicio hasta fin del mes.
- `contrato.fecha_termino` dentro del mes: días desde inicio del mes hasta término.
- Ambos dentro del mes: días entre inicio y término.

### BLOQUE 2 — AFP (campos 14–24)

| # | Nombre                    | Fuente en el ERP                               | Default |
|---|---------------------------|------------------------------------------------|---------|
|14 | Tipo de movimiento        | Calculado (ver sección 3)                      | `0`     |
|15 | Código AFP                | `empleados.afp` → tabla `AFP_CODIGOS`          | `00`    |
|16 | CUSPP                     | **Sin dato ERP** (solo AFP extranjeras)        | `""`    |
|17 | Renta imponible AFP       | `liquidacion_detalles.base_calculo` (AFP_COTIZACION) | `0` |
|18 | Cotización obligatoria 10%| `liquidacion_detalles.monto` (AFP_COTIZACION)  | `0`     |
|19 | Cotización SIS            | `liquidaciones.aporte_empleador_sis`           | `0`     |
|20 | Comisión AFP              | `liquidacion_detalles.monto` (AFP_COMISION)    | `0`     |
|21 | Cotización voluntaria AFP | **Sin dato ERP** (APV no implementado)         | `0`     |
|22 | APV trabajador            | **Sin dato ERP**                               | `0`     |
|23 | APV colectivo trabajador  | **Sin dato ERP**                               | `0`     |
|24 | APV colectivo empleador   | **Sin dato ERP**                               | `0`     |

### BLOQUE 3 — Seguro de Cesantía AFC (campos 25–29)

| # | Nombre                  | Fuente en el ERP                               | Default |
|---|-------------------------|------------------------------------------------|---------|
|25 | Tipo AFC                | `contratos.tipo`: INDEFINIDO→`1`, otros→`2`    | —       |
|26 | Renta imponible AFC     | `liquidacion_detalles.base_calculo` (AFC_TRABAJADOR) | `0` |
|27 | Cotización AFC trabajador | `liquidacion_detalles.monto` (AFC_TRABAJADOR) | `0`    |
|28 | Cotización AFC empleador  | `liquidaciones.aporte_empleador_afc`          | `0`    |
|29 | Aporte retiro AFC empleador | **Sin dato ERP**                            | `0`    |

### BLOQUE 4 — IPS / ex-INP (campos 30–34)

| # | Nombre                    | Fuente en el ERP                     | Default |
|---|---------------------------|--------------------------------------|---------|
|30 | Código caja IPS           | **Sin dato ERP** (todos van AFP)     | `""`    |
|31 | Renta imponible IPS       | **Sin dato ERP**                     | `0`     |
|32 | Cotización IPS            | **Sin dato ERP**                     | `0`     |
|33 | Cotización adicional IPS  | **Sin dato ERP**                     | `0`     |
|34 | Cotización especial IPS   | **Sin dato ERP**                     | `0`     |

### BLOQUE 5 — Salud (campos 35–51)

| # | Nombre                      | Fuente en el ERP                                    | Default |
|---|-----------------------------|-----------------------------------------------------|---------|
|35 | Código institución salud    | `empleados.tipo_salud` / `isapre_nombre` → tabla `SALUD_CODIGOS` | `00` |
|36 | Número FUN ISAPRE           | **Sin dato ERP**                                    | `""`    |
|37 | Renta imponible salud       | `liquidacion_detalles.base_calculo` (SALUD)         | `0`     |
|38 | Cotización obligatoria 7%   | `liquidacion_detalles.monto` (SALUD)                | `0`     |
|39 | Cotización adicional ISAPRE | Calculado: `max(0, plan_pesos − 7% imponible)`      | `0`     |
|40 | Cotización ISAPRE GES       | **Sin dato ERP**                                    | `0`     |
|41 | Cotización catastrófico     | **Sin dato ERP**                                    | `0`     |
|42 | Subsidio incapacidad        | **Sin dato ERP**                                    | `0`     |
|43 | Otros descuentos salud      | **Sin dato ERP**                                    | `0`     |
|44 | Número credencial salud     | **Sin dato ERP**                                    | `""`    |
|45 | Tipo cobertura              | **Sin dato ERP**                                    | `""`    |
|46 | Meses cotizados salud       | **Sin dato ERP**                                    | `0`     |
|47 | Cargas simples              | **Sin dato ERP** (`carga_familiares` no tipifica)   | `0`     |
|48 | Cargas dobles               | **Sin dato ERP**                                    | `0`     |
|49 | Cargas maternales           | **Sin dato ERP**                                    | `0`     |
|50 | Cargas inválidas            | **Sin dato ERP**                                    | `0`     |
|51 | Cotización salud empleador  | **Sin dato ERP**                                    | `0`     |

**Regla cotización adicional ISAPRE (campo 39):**
- Solo aplica si `empleados.tipo_salud = 'ISAPRE'` y `empleados.isapre_plan_uf > 0`.
- `plan_pesos = isapre_plan_uf × uf_valor` (UF del período).
- `adicional = max(0, plan_pesos − base_imponible_salud × 0.07)`.
- El campo 38 siempre corresponde al 7% obligatorio.

### BLOQUE 6 — CCAF (campos 52–58)

| # | Nombre                  | Fuente en el ERP | Default |
|---|-------------------------|------------------|---------|
|52 | Código CCAF             | **Sin dato ERP** | `""`    |
|53 | Cargas CCAF             | **Sin dato ERP** | `0`     |
|54 | Asignación familiar CCAF| **Sin dato ERP** | `0`     |
|55 | Subsidio CCAF           | **Sin dato ERP** | `0`     |
|56 | Crédito social CCAF     | **Sin dato ERP** | `0`     |
|57 | Otros descuentos CCAF   | **Sin dato ERP** | `0`     |
|58 | Cotización CCAF         | **Sin dato ERP** | `0`     |

### BLOQUE 7 — Mutualidad / Seguro Accidentes del Trabajo (campos 59–69)

| # | Nombre                        | Fuente en el ERP                            | Default |
|---|-------------------------------|---------------------------------------------|---------|
|59 | Código mutualidad             | **Sin dato ERP** (ACHS=01, ISL=02, MutSeg=03) | `""` |
|60 | Cotización mutualidad básica  | `liquidaciones.aporte_empleador_mutual`     | `0`     |
|61 | Cotización adicional mutual   | **Sin dato ERP**                            | `0`     |
|62 | Cotización diferenciada       | **Sin dato ERP**                            | `0`     |
|63 | Cotización extraordinaria     | **Sin dato ERP**                            | `0`     |
|64 | Días subsidio accidente       | **Sin dato ERP**                            | `0`     |
|65 | Monto subsidio accidente      | **Sin dato ERP**                            | `0`     |
|66 | Días suspensión mutualidad    | **Sin dato ERP**                            | `0`     |
|67 | Tasa cotización diferenciada  | **Sin dato ERP**                            | `0`     |
|68 | Renta imponible mutualidad    | **Sin dato ERP**                            | `0`     |
|69 | Tipo accidente                | **Sin dato ERP**                            | `0`     |

### BLOQUE 8 — Datos empleador (campos 70–80)

| # | Nombre                       | Fuente en el ERP                   | Default |
|---|------------------------------|------------------------------------|---------|
|70 | RUT empleador                | `empresas.rut` (solo dígitos)      | `""`    |
|71 | DV empleador                 | `empresas.rut` (dígito verificador)| `""`    |
|72 | Código actividad económica   | **Sin dato ERP** (`empresas` no tiene campo CIIU) | `""` |
|73 | Dirección empleador          | **Sin dato ERP** (`empresas.direccion` no normalizada) | `""` |
|74 | Ciudad empleador             | **Sin dato ERP**                   | `""`    |
|75 | Teléfono empleador           | **Sin dato ERP** (`empresas.telefono` no requerido por Previred en este campo) | `""` |
|76 | Email empleador              | **Sin dato ERP**                   | `""`    |
|77 | Nombre representante legal   | **Sin dato ERP**                   | `""`    |
|78 | RUT representante legal      | **Sin dato ERP**                   | `""`    |
|79 | DV representante legal       | **Sin dato ERP**                   | `""`    |
|80 | Cargo representante legal    | **Sin dato ERP**                   | `""`    |

### BLOQUE 9 — Tributario y otros (campos 81–95)

| #  | Nombre                   | Fuente en el ERP                                   | Default |
|----|--------------------------|----------------------------------------------------|---------|
| 81 | Base tributable IUSC     | `liquidaciones.base_tributable`                    | `0`     |
| 82 | Impuesto único retenido  | `liquidacion_detalles.monto` (IMPUESTO_UNICO)      | `0`     |
| 83 | Líquido a pagar          | `liquidaciones.liquido_a_pagar`                    | `0`     |
| 84 | Asignación familiar directa | **Sin dato ERP**                                | `0`     |
| 85 | Número de cargas directas   | **Sin dato ERP**                                | `0`     |
| 86 | Descuentos voluntarios      | **Sin dato ERP** (no se desglosa en este campo) | `0`     |
| 87 | Préstamo institucional      | **Sin dato ERP**                                | `0`     |
| 88 | Cuota sindical              | **Sin dato ERP**                                | `0`     |
| 89 | Otros descuentos            | **Sin dato ERP**                                | `0`     |
| 90 | Anticipo sueldo             | **Sin dato ERP**                                | `0`     |
| 91 | Sueldo base                 | **No requerido por Previred en este campo**     | `0`     |
| 92 | Bono empresa                | **Sin dato ERP**                                | `0`     |
| 93 | Horas extras                | **Sin dato ERP** (no se desglosa aquí)          | `0`     |
| 94 | Total haberes               | **Sin dato ERP** (no requerido)                 | `0`     |
| 95 | Total descuentos            | **Sin dato ERP** (no requerido)                 | `0`     |

### BLOQUE 10 — Reservados / Complementarios (campos 96–105)

Campos reservados por Previred. El ERP los emite vacíos.

---

## 5. Campos sin datos en el ERP (lista consolidada)

Los siguientes campos se emiten con valor por defecto (`0` o `""`) porque el ERP
no dispone del dato o no está modelado en la base de datos actual:

| Campo # | Nombre                        | Razón                                         |
|---------|-------------------------------|-----------------------------------------------|
| 6       | Sexo                          | `empleados` no tiene columna `sexo`           |
| 7       | Nacionalidad                  | `empleados` no tiene columna `nacionalidad`   |
| 16      | CUSPP                         | Solo AFP extranjeras; no aplica en Chile      |
| 21–24   | APV / Cotización voluntaria   | APV no implementado                           |
| 29      | Aporte retiro AFC empleador   | No modelado en `liquidaciones`                |
| 30–34   | Bloque IPS                    | Todos los trabajadores van en AFP             |
| 36      | Número FUN ISAPRE             | No modelado en `empleados`                    |
| 40–51   | Campos salud adicionales      | No modelados (GES, catastrófico, tipo cobertura, etc.) |
| 52–58   | Bloque CCAF                   | CCAF no modelado                              |
| 59      | Código mutualidad             | No hay tabla de mutualidades                  |
| 61–69   | Mutualidad adicional / accidentes | No modelados                              |
| 72–80   | Datos empleador extendidos    | CIIU, representante legal, etc. no modelados  |
| 84–95   | Campos tributarios/descuentos adicionales | No requeridos por Previred o no modelados |

---

## 6. Tablas de códigos institucionales

### 6.1 Códigos AFP

| AFP       | Código Previred |
|-----------|-----------------|
| Capital   | 03              |
| Cuprum    | 05              |
| Habitat   | 07              |
| Modelo    | 09              |
| PlanVital | 10              |
| ProVida   | 11              |
| Uno       | 13              |

> Fuente: Superintendencia de Pensiones (superpensiones.gob.cl)

### 6.2 Códigos institución de salud

| Institución       | Código Previred |
|-------------------|-----------------|
| FONASA            | 07              |
| Banmédica         | 01              |
| Colmena Golden Cross | 02           |
| Consalud          | 03              |
| MásVida           | 04              |
| Nueva MásVida     | 06              |
| Vida Tres         | 08              |
| Esencial          | 52              |

> Fuente: Superintendencia de Salud (supersalud.gob.cl)
> Si la ISAPRE del trabajador no está en esta tabla, usar código `00` e
> informar al administrador para actualizar el mapeo en `PreviredService::SALUD_CODIGOS`.

---

## 7. Reglas de validación Previred

Previred rechaza el archivo si:
1. **RUT duplicado** en el mismo período/empresa.
2. **RUT inválido** (dígito verificador incorrecto).
3. **Base imponible AFP** superior al tope (90 UF del período).
4. **Cotización AFP** ≠ 10% de la base imponible AFP.
5. **Código AFP o salud** no reconocido.
6. **Período** no coincide con el mes declarado en el portal.
7. **Número de campos** ≠ 105 por fila.

---

## 8. Flujo de trabajo mensual

```
1. Calcular liquidaciones (POST /api/rrhh/liquidaciones/calcular)
2. Revisar y emitir (POST /api/rrhh/liquidaciones/{id}/emitir)
3. Centralizar en contabilidad (POST /api/rrhh/centralizacion/{anio}/{mes})
4. Descargar archivo Previred (GET /api/rrhh/previred/{anio}/{mes}/archivo)
5. Subir al portal Previred (manual en https://www.previred.com)
6. Declarar y pagar dentro del plazo (día 13 del mes siguiente hábil)
```

---

## 9. Consideraciones legales

- **Plazo de pago:** las cotizaciones del mes M deben pagarse antes del día 13 de M+1
  (Art. 19 DL 3500). El incumplimiento genera multas e intereses.
- **AFP:** cotización obligatoria 10% + comisión variable por administradora.
  Tope imponible: 90 UF (vigencia 2026, verificar actualización anual).
- **Salud (FONASA):** 7% sobre imponible con mismo tope 90 UF.
- **Salud (ISAPRE):** el 7% va en campo 38; el exceso del plan en UF va en campo 39.
- **AFC (indefinido):** 0,6% trabajador + 2,4% empleador hasta tope 135,2 UF.
- **AFC (plazo fijo):** 0% trabajador + 3,0% empleador hasta tope 135,2 UF.
- **SIS:** 1,62% empleador sobre imponible AFP (2026) — seguro invalidez/sobrevivencia.
- **Mutual:** ~0,9% empleador — seguro accidentes del trabajo (Ley 16.744).

---

## 10. Actualización de parámetros

Los valores (topes, tasas AFP, tabla IUSC) cambian periódicamente. Actualizar en:
1. `POST /api/rrhh/parametros` — nuevo `ParametroPrevisional` con fecha de vigencia.
2. `POST /api/rrhh/indicadores` — valores UF/UTM del mes.
3. Verificar con `RrhhParametrosLegalesSeeder` como referencia de valores 2026.

> Los códigos AFP en `PreviredService::AFP_CODIGOS` solo cambian si se crea o fusiona
> una AFP (evento raro). Los códigos ISAPRE pueden cambiar si hay fusiones o nuevas
> instituciones — verificar con Superintendencia de Salud.
