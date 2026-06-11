# Formato Previred — Planilla de Cotizaciones Previsionales

**Fecha:** 2026-06-11
**Fuente oficial:** https://www.previred.com/web/previred/ayuda-planilla
**Marco legal:** DL 3500, Ley 18.469, Ley 19.728, DL 824 LIR

> ⚠️ **Verificar antes de producción.** El formato CSV de Previred puede cambiar con
> actualizaciones del sistema. Contrastar este documento con la especificación oficial
> vigente en previred.com antes de enviar el primer archivo.

---

## 1. Descripción general

El archivo previsional mensual ("Planilla de Cotizaciones") se genera desde el ERP
para cada período en que haya liquidaciones **EMITIDAS**. Se descarga como CSV y se
sube al portal Previred para declarar y pagar las cotizaciones del mes.

**Endpoint de descarga:**
```
GET /api/rrhh/previred/{anio}/{mes}/archivo
GET /api/rrhh/previred/{anio}/{mes}/preview   ← vista previa en JSON
```

Permiso requerido: `rrhh.remuneraciones.ver`

---

## 2. Formato del archivo

- **Separador:** punto y coma (`;`)
- **Codificación:** UTF-8
- **Fin de línea:** CRLF (`\r\n`)
- **Primera línea:** encabezado con nombres de columna
- **Líneas siguientes:** una por trabajador con liquidación EMITIDA en el período

---

## 3. Columnas (25 campos)

| # | Nombre columna | Descripción | Tipo | Ejemplo |
|---|---|---|---|---|
| 01 | `RUT` | RUT del trabajador sin DV ni puntos | Numérico | `12345678` |
| 02 | `DV` | Dígito verificador | Char (0–9, K) | `9` |
| 03 | `AP_PATERNO` | Apellido paterno (mayúsculas) | Texto | `GONZALEZ` |
| 04 | `AP_MATERNO` | Apellido materno (mayúsculas) | Texto | `PEREZ` |
| 05 | `NOMBRES` | Nombres (mayúsculas) | Texto | `JUAN CARLOS` |
| 06 | `TIPO_MOVIMIENTO` | `A` = activo / `S` = suspendido | Char | `A` |
| 07 | `PERIODO` | Año y mes del período | AAAAMM | `202606` |
| 08 | `DIAS_COTIZADOS` | Días cotizados del mes | Numérico | `30` |
| 09 | `AFP_CODIGO` | Código AFP en Previred (ver tabla) | 2 dígitos | `07` |
| 10 | `RIM_AFP` | Remuneración imponible AFP (pesos) | Entero | `1213354` |
| 11 | `COTIZACION_AFP` | Cotización obligatoria AFP 10% (pesos) | Entero | `121335` |
| 12 | `COMISION_AFP` | Comisión de la AFP (pesos) | Entero | `15409` |
| 13 | `SALUD_CODIGO` | Código institución de salud (ver tabla) | 2 dígitos | `07` |
| 14 | `RIM_SALUD` | Remuneración imponible salud (pesos) | Entero | `1213354` |
| 15 | `COTIZACION_SALUD` | Cotización salud: 7% Fonasa o plan ISAPRE (pesos) | Entero | `84935` |
| 16 | `COTIZACION_ADICIONAL_ISAPRE` | Cotización adicional ISAPRE (pesos; 0 si Fonasa) | Entero | `0` |
| 17 | `TIPO_AFC` | `1` = indefinido, `2` = plazo fijo | Entero | `1` |
| 18 | `RIM_AFC` | Remuneración imponible AFC (pesos) | Entero | `1213354` |
| 19 | `COTIZACION_AFC_TRABAJADOR` | Cotización AFC trabajador: 0,6% indefinido / 0% fijo (pesos) | Entero | `7280` |
| 20 | `COTIZACION_AFC_EMPLEADOR` | Cotización AFC empleador: 2,4% indef. / 3% fijo (pesos) | Entero | `29120` |
| 21 | `COTIZACION_SIS` | Aporte SIS empleador: 1,62% (pesos) | Entero | `19656` |
| 22 | `COTIZACION_MUTUAL` | Cotización mutual de seguridad: 0,9% (pesos) | Entero | `10920` |
| 23 | `BASE_TRIBUTABLE` | Base tributable impuesto único 2ª cat. (pesos) | Entero | `984804` |
| 24 | `IMPUESTO_UNICO` | Impuesto único retenido (pesos) | Entero | `38592` |
| 25 | `LIQUIDO_PAGAR` | Líquido a pagar al trabajador (pesos) | Entero | `946212` |

---

## 4. Tablas de códigos institucionales

### 4.1 Códigos AFP

| AFP | Código Previred |
|---|---|
| Capital | 03 |
| Cuprum | 05 |
| Habitat | 07 |
| Modelo | 09 |
| PlanVital | 10 |
| ProVida | 11 |
| Uno | 13 |

> Fuente: Superintendencia de Pensiones (superpensiones.gob.cl)

### 4.2 Códigos institución de salud

| Institución | Código Previred |
|---|---|
| FONASA | 07 |
| Banmédica | 01 |
| Colmena Golden Cross | 02 |
| Consalud | 03 |
| MásVida | 04 |
| Nueva MásVida | 06 |
| Vida Tres | 08 |
| Esencial | 52 |

> Fuente: Superintendencia de Salud (supersalud.gob.cl)
> Si la ISAPRE del trabajador no está en esta tabla, usar código `00` e
> informar al administrador para actualizar el mapeo en `PreviredService::SALUD_CODIGOS`.

---

## 5. Reglas de validación Previred

Previred rechaza el archivo si:
1. **RUT duplicado** en el mismo período/empresa.
2. **RUT inválido** (dígito verificador incorrecto).
3. **Base imponible AFP** superior al tope (90 UF del período).
4. **Cotización AFP** ≠ 10% de la base imponible AFP.
5. **Código AFP o salud** no reconocido.
6. **Período** no coincide con el mes declarado en el portal.

---

## 6. Flujo de trabajo mensual

```
1. Calcular liquidaciones (POST /api/rrhh/liquidaciones/calcular)
2. Revisar y emitir (POST /api/rrhh/liquidaciones/{id}/emitir)
3. Centralizar en contabilidad (POST /api/rrhh/centralizacion/{anio}/{mes})
4. Descargar archivo Previred (GET /api/rrhh/previred/{anio}/{mes}/archivo)
5. Subir al portal Previred (manual en https://www.previred.com)
6. Declarar y pagar dentro del plazo (día 13 del mes siguiente hábil)
```

---

## 7. Consideraciones legales

- **Plazo de pago:** las cotizaciones del mes M deben pagarse antes del día 13 de M+1
  (Art. 19 DL 3500). El incumplimiento genera multas e intereses.
- **AFP:** cotización obligatoria 10% + comisión variable por administradora.
  Tope imponible: 90 UF (vigencia 2026, verificar actualización anual).
- **Salud (FONASA):** 7% sobre imponible con mismo tope 90 UF.
- **Salud (ISAPRE):** monto pactado en UF del plan contratado.
- **AFC (indefinido):** 0,6% trabajador + 2,4% empleador hasta tope 135,2 UF.
- **AFC (plazo fijo):** 0% trabajador + 3,0% empleador hasta tope 135,2 UF.
- **SIS:** 1,62% empleador sobre imponible AFP (2026) — seguro invalidez/sobrevivencia.
- **Mutual:** ~0,9% empleador — seguro accidentes del trabajo (Ley 16.744).

---

## 8. Actualización de parámetros

Los valores (topes, tasas AFP, tabla IUSC) cambian periódicamente. Actualizar en:
1. `POST /api/rrhh/parametros` — nuevo `ParametroPrevisional` con fecha de vigencia.
2. `POST /api/rrhh/indicadores` — valores UF/UTM del mes.
3. Verificar con `RrhhParametrosLegalesSeeder` como referencia de valores 2026.

> Los códigos AFP en `PreviredService::AFP_CODIGOS` solo cambian si se crea o fusiona
> una AFP (evento raro). Los códigos ISAPRE pueden cambiar si hay fusiones o nuevas
> instituciones — verificar con Superintendencia de Salud.
