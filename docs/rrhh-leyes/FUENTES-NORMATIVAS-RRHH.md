# Fuentes Normativas del Módulo RRHH y Remuneraciones

Documento de referencia: normativa, organismos y parámetros oficiales utilizados para la construcción y mantención del módulo de RRHH/Remuneraciones de Tenri ERP. Complementa `MARCO-LEGAL-LABORAL-CHILE.md` (marco legal) y `../integraciones/FORMATO-PREVIRED.md` (formato de archivo).

> **Vigencia:** valores verificados a junio de 2026. Los indicadores marcados como *mensuales* deben actualizarse cada mes desde Previred/SII (tabla `parametros_previsionales`).

---

## 1. Organismos y fuentes oficiales

| Organismo | Qué provee al módulo | URL |
|---|---|---|
| **Previred** | Indicadores previsionales mensuales (UF/UTM/UTA, topes, tasas AFP, AFC, asignación familiar), formato de archivo de 105 campos | https://www.previred.com/indicadores-previsionales/ |
| **SII** | Tabla de impuesto único de segunda categoría (mensual), UTM/UTA | https://www.sii.cl/valores_y_fechas/impuesto_2da_categoria/impuesto2026.htm |
| **Superintendencia de Pensiones (SP)** | Comisiones AFP, normativa cotización empleador ley 21.735, topes imponibles | https://www.spensiones.cl |
| **SUSESO** | Normativa CCAF (ley 18.833), crédito social, licencias médicas, ley 16.744 (mutualidades) | https://www.suseso.cl |
| **AFC Chile** | Seguro de cesantía: tasas y topes | https://www.afc.cl |
| **Dirección del Trabajo (DT)** | Código del Trabajo: gratificación, horas extra, semana corrida, finiquitos | https://www.dt.gob.cl |
| **CCAF Los Andes** | Crédito social, prestaciones, convenios | https://www.cajalosandes.cl |
| **CCAF Los Héroes** | Crédito social, prestaciones, convenios | https://www.losheroes.cl |
| **Biblioteca del Congreso (BCN)** | Textos legales (ley 21.735, Código del Trabajo, ley 18.833, ley 16.744) | https://www.bcn.cl/leychile |

---

## 2. Indicadores previsionales (Previred, junio 2026)

Fuente: Previred — Indicadores Previsionales.

### Valores unitarios
| Indicador | Valor |
|---|---|
| UF | $40.610,69 |
| UTM | $70.588 |
| UTA | $847.056 |

### Topes imponibles (mensuales)
| Concepto | Tope | Pesos |
|---|---|---|
| AFP / Salud / IPS moderno | **90 UF** | $3.654.962 |
| IPS (ex INP, régimen antiguo) | 60 UF | $2.407.212 |
| Seguro de cesantía (AFC) | **135,2 UF** | $5.490.565 |

> El tope AFP se reajusta anualmente por variación del índice real de remuneraciones (era 89,9 UF a inicios de 2026; valor vigente 90 UF según Previred).

### Renta mínima imponible
| Categoría | Valor |
|---|---|
| Trabajadores dependientes e independientes (IMM) | $539.000 |

### Tasas AFP (dependientes, junio 2026)
| AFP | Tasa trabajador (10% + comisión) | Cargo empleador (ley 21.735, cta. individual) |
|---|---|---|
| Capital | 11,44% | 0,1% |
| Cuprum | 11,44% | 0,1% |
| Habitat | 11,27% | 0,1% |
| PlanVital | 11,16% | 0,1% |
| ProVida | 11,45% | 0,1% |
| Modelo | 10,58% | 0,1% |
| Uno | 10,46% | 0,1% |

- **SIS** (Seguro de Invalidez y Sobrevivencia, cargo empleador): **1,62%** (subió desde 1,49% → 1,54% en enero 2026; valor Previred junio 2026: 1,62%).
- Comisiones AFP cambian por licitación/ajuste: verificar mensualmente en Previred/SP.

### Reforma previsional — Ley 21.735 (cotización adicional del empleador)
Fuentes: SP, Hacienda, BCN.

- Desde remuneraciones de **agosto 2025**: aporte empleador **1%** (0,1% a cuenta de capitalización individual vía AFP + 0,9% al FAPP/Seguro Social).
- Gradualidad: aumenta cada año durante ~9 años hasta **7%**; régimen final empleador total **8,5%** (6% a cuentas individuales —4,5% directo + 1,5% cotización con rentabilidad protegida— y 2,5% al Seguro Social Previsional).
- El calendario anual de tasas debe mantenerse en `parametros_previsionales` (campo aporte empleador).

### Seguro de cesantía (AFC)
| Tipo de contrato | Empleador | Trabajador |
|---|---|---|
| Indefinido | 2,4% | 0,6% |
| Indefinido 11+ años | 0,8% | — |
| Plazo fijo / obra o faena | 3,0% | — |
| Trabajador de casa particular | 3,0% | — |

### Asignación familiar (tramos vigentes)
| Tramo | Monto por carga | Renta tope |
|---|---|---|
| A | $22.007 | ≤ $631.976 |
| B | $13.505 | ≤ $923.067 |
| C | $4.267 | ≤ $1.439.668 |
| D | $0 | > $1.439.668 |

### APV y depósito convenido (topes)
| Concepto | Tope |
|---|---|
| APV mensual | 50 UF ($2.030.535) |
| APV anual | 600 UF ($24.366.414) |
| Depósito convenido anual | 900 UF ($36.549.621) |

---

## 3. Salud

- **Fonasa:** 7% de la renta imponible (con tope 90 UF). Recaudación vía Previred; si la empresa está afiliada a CCAF, el 0,6% de la cotización se entera en la caja (ley 18.833 art. 26) y 6,4% a Fonasa.
- **Isapre:** cotización pactada en UF según plan (mínimo legal 7%). El excedente sobre 7% no es descuento legal adicional, es parte del plan pactado.

---

## 4. Impuesto único de segunda categoría (SII)

Tabla **mensual** publicada por el SII; se aplica sobre la base tributable = renta imponible − cotizaciones previsionales obligatorias del trabajador. Tabla julio 2026 (referencia; actualizar cada mes):

| Renta líquida imponible desde | Hasta | Factor | Rebaja |
|---|---|---|---|
| $0 | $967.261,50 | Exento | — |
| $967.261,51 | $2.149.470,00 | 0,04 | $38.690,46 |
| $2.149.470,01 | $3.582.450,00 | 0,08 | $124.669,26 |
| $3.582.450,01 | $5.015.430,00 | 0,135 | $321.704,01 |
| $5.015.430,01 | $6.448.410,00 | 0,23 | $798.169,86 |
| $6.448.410,01 | $8.597.880,00 | 0,304 | $1.275.352,20 |
| $8.597.880,01 | $22.211.190,00 | 0,35 | $1.670.854,68 |
| $22.211.190,01 | y más | 0,40 | $2.781.414,18 |

Impuesto = (base tributable × factor) − rebaja.

---

## 5. Cajas de Compensación (CCAF) — Ley 18.833

Rol de la CCAF cuando la empresa está afiliada (Los Andes, Los Héroes, La Araucana, 18 de Septiembre):

1. **Asignación familiar:** la CCAF paga las cargas por cuenta del Estado; el empleador la descuenta/compensa por planilla.
2. **Crédito social:** descuento por planilla del dividendo del crédito del trabajador. El empleador está **obligado** a retenerlo y enterarlo en la caja dentro de los primeros 10 días del mes siguiente (art. 22 ley 18.833). El descuento mensual tiene tope porcentual de la remuneración líquida para evitar sobreendeudamiento (norma SUSESO).
3. **Subsidios de incapacidad laboral (SIL):** para afiliados Fonasa, la CCAF tramita y paga licencias por enfermedad común y maternales.
4. **Cotización 0,6%:** del 7% de salud Fonasa, 0,6% se entera en la CCAF.
5. **Prestaciones adicionales:** bonos, convenios — no afectan la liquidación salvo descuentos pactados.

En el ERP: el concepto "crédito CCAF" es descuento voluntario/convenido posterior a los descuentos legales, informado en el archivo Previred.

Fuentes: [SUSESO Compendio ley 18.833](https://www.suseso.cl/620/w3-propertyvalue-593660.html), [SP descuentos CCAF](https://www.spensiones.cl/portal/compendio/596/w3-propertyvalue-4423.html), [Cajas de Chile](https://cajasdechile.cl/).

---

## 6. Accidentes del trabajo — Ley 16.744 (Mutual / ISL)

- Cotización básica general: **0,93%** de la remuneración imponible (cargo empleador).
- Cotización adicional diferenciada por riesgo de la actividad: 0% a 3,4% (DS 110), ajustable por siniestralidad (DS 67).
- Se entera en la mutualidad (ACHS, Mutual de Seguridad, IST) o ISL si no hay adhesión.

---

## 7. Código del Trabajo — reglas de cálculo implementadas

| Materia | Regla | Referencia |
|---|---|---|
| **Gratificación legal art. 50** | 25% de lo devengado, con tope mensual de 4,75 IMM / 12 ($539.000 × 4,75 / 12 = $213.354 aprox.) | CdT art. 47-50 |
| **Horas extraordinarias** | Recargo 50% sobre sueldo convenido; valor hora = (sueldo mensual / 30) × 28 / 180 × 1,5 (jornada 44 hrs desde abr-2026, ley 21.561: transición 45→44→42 hrs) | CdT art. 31-32 |
| **Semana corrida** | Pago de séptimo día para remuneración variable diaria | CdT art. 45 |
| **Indemnización años de servicio** | 30 días por año (fracción >6 meses), tope **11 años** y base tope **90 UF** | CdT art. 163 |
| **Aviso previo** | 30 días o indemnización sustitutiva (tope 90 UF) | CdT art. 161-162 |
| **Feriado proporcional** | 1,25 días hábiles por mes trabajado, pagado en finiquito | CdT art. 73 |
| **Descuento AFC en despido art. 161** | Se imputa a la indemnización el aporte empleador (1,6% parte cuenta individual) acumulado en AFC | Ley 19.728 art. 13 |
| **Tope descuentos voluntarios** | Máx. 15% remuneración total (art. 58); descuentos convenidos (CCAF) hasta 30% en conjunto con legales | CdT art. 58 |
| **Jornada laboral** | Ley 21.561 (40 horas): 44 hrs desde 26-abr-2024, 42 hrs desde 26-abr-2026, 40 hrs desde 26-abr-2028 — afecta divisor de hora extra | BCN ley 21.561 |

---

## 8. Previred — archivo de remuneraciones

- Formato oficial **105 campos** por línea (detalle en `../integraciones/FORMATO-PREVIRED.md`).
- Códigos de movimiento de personal (contratación=1, retiro=2, licencias, etc.) según manual Previred.
- Plazo de pago electrónico: día 13 del mes siguiente (10 si es declaración sin pago presencial).

---

## 9. Procedimiento de actualización mensual de parámetros

1. Primer día hábil del mes: obtener indicadores desde https://www.previred.com/indicadores-previsionales/.
2. Actualizar en el ERP (`parametros_previsionales` vía pantalla Parámetros RRHH): UF/UTM, topes (AFP, AFC), IMM, tasas AFP/SIS, tramos asignación familiar.
3. Verificar tabla de impuesto único del mes en SII y actualizar tramos.
4. Revisar cambios de gradualidad ley 21.735 cada agosto.
5. Comisiones AFP: revisar en SP ante licitaciones (afecta AFP de nuevos afiliados).

---

## Fuentes consultadas (junio 2026)

- Previred — Indicadores Previsionales: https://www.previred.com/indicadores-previsionales/
- SII — Impuesto de segunda categoría 2026: https://www.sii.cl/valores_y_fechas/impuesto_2da_categoria/impuesto2026.htm
- SP — Cotización de cargo del empleador (ley 21.735): https://www.spensiones.cl/portal/institucional/594/w3-propertyvalue-10906.html
- Hacienda — Implementación reforma previsional: https://www.hacienda.cl/noticias-y-eventos/noticias/implementacion-de-la-reforma-previsional-nueva-cotizacion-del-empleador-regira
- BCN — Ley 21.735: https://www.bcn.cl/leychile/navegar?idNorma=1212060
- SUSESO — Compendio ley 18.833 (crédito social): https://www.suseso.cl/620/w3-propertyvalue-593660.html
- SUSESO — Reforma previsional e incapacidad laboral: https://www.suseso.gob.cl/612/w3-article-772931.html
- SP — Descuentos a pensionados afiliados a CCAF: https://www.spensiones.cl/portal/compendio/596/w3-propertyvalue-4423.html
- Cajas de Chile (gremial CCAF): https://cajasdechile.cl/
