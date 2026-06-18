# Marco Legal — Remuneraciones y RRHH Chile

> **Valores actualizados a junio 2026. Indicadores mensuales y fuentes oficiales: ver FUENTES-NORMATIVAS-RRHH.md**

**Fecha de referencia:** 2026-06-11  
**Aplicación:** Módulo de RRHH y Remuneraciones del ERP Tenri  
**Advertencia:** Los valores numéricos (tasas, topes, indicadores) **cambian periódicamente**.  
Verificar contra fuentes oficiales antes de actualizar el seeder de producción.

---

## 1. Código del Trabajo (DFL 1/2003)

**Fuente:** Biblioteca del Congreso Nacional — bcn.cl  
**URL:** https://www.bcn.cl/leychile/navegar?idNorma=207436

### 1.1 Contrato de Trabajo (Art. 7-17)

- El contrato de trabajo es consensual; debe escriturarse dentro de 15 días (5 si dura menos de 30 días).
- Menciones obligatorias: lugar/fecha, individualización de las partes, función, lugar de trabajo, monto y forma de remuneración, jornada, plazo del contrato.
- Tipos de contrato:
  - **Indefinido**: sin fecha de término establecida.
  - **Plazo fijo**: hasta 1 año (2 años para gerentes y personas con título profesional). Si el trabajador presta servicios discontinuos más de 2 veces en 12 meses por similar servicio, se convierte en indefinido.
  - **Por obra o faena**: termina al concluir la obra.

### 1.2 Remuneración (Art. 41-51)

**Definición:** Contraprestaciones en dinero y adicionales en especie que el empleador debe pagar por los servicios (Art. 41).

**No constituyen remuneración** (no imponibles): asignación de movilización, colación, viáticos, asignación por desgaste de herramientas, asignación familiar, indemnizaciones, devolución de gastos.

**Componentes habituales:**
| Concepto | Imponible | Tributable | Fundamento |
|---|---|---|---|
| Sueldo base | Sí | Sí | Art. 42 a) |
| Sobresueldo (horas extra) | Sí | Sí | Art. 42 b), Art. 32 |
| Comisión | Sí | Sí | Art. 42 c) |
| Participación | Sí | Sí | Art. 42 d) |
| Gratificación | Sí* | Sí | Art. 42 e), Art. 47-50 |
| Bono imponible | Sí | Sí | — |
| Colación | No | No | Art. 41 inciso 2° |
| Movilización | No | No | Art. 41 inciso 2° |
| Asignación familiar | No | No | Art. 41 inciso 2°, DFL 150/1982 |
| Viáticos | No | No | Art. 41 inciso 2° |

*La gratificación legal del Art. 50 (25% mensual con tope) es imponible.

**Tope remuneración imponible AFP:** 90 UF (valor jun-2026; era 87,8 UF en 2022 — ver FUENTES-NORMATIVAS-RRHH.md).

### 1.3 Gratificación Legal (Art. 47-50)

Empresas que obtienen utilidades deben gratificar a sus trabajadores. Dos modalidades:
- **Art. 47**: 30% de utilidades repartido entre trabajadores proporcionalmente.
- **Art. 50** (más usada): **25%** de la remuneración anual del trabajador, con tope de **4,75 salarios mínimos mensuales** (IMM) ÷ 12 por mes, si se pacta mensual.

**Cálculo mensual Art. 50:**
```
Gratificación_mes = min(Sueldo_base_mes × 25%, (IMM × 4.75 / 12))
```
La gratificación del Art. 50 pagada mensualmente es **imponible y tributable**.

### 1.4 Jornada y Horas Extra (Art. 22, 31-32)

- Jornada ordinaria: **42 horas semanales** desde 26-abr-2026 (ley 21.561: 44 hrs desde abr-2024, **42 hrs desde abr-2026 — vigente**, 40 hrs desde abr-2028); distribución en 5 o 6 días.
- Horas extra: con acuerdo previo escrito, máximo **2 horas diarias**, recargo mínimo **50%** sobre hora ordinaria.
- Solo son horas extra las que superan la jornada pactada (no las de la legal si la pactada es menor).

### 1.5 Vacaciones (Art. 67-76)

- **Feriado legal**: 15 días hábiles al año por cada 12 meses de trabajo continuo.
- Se acumulan si no se usan (con consentimiento del trabajador, máximo hasta 2 períodos).
- **Vacaciones progresivas (Art. 68)**: trabajadores con más de 10 años continuos en la misma empresa tienen 1 día adicional por año sobre los 10 (hasta máximo de 20 días hábiles por uso de este beneficio).
- **Proporcionales**: al término del contrato, si < 1 año, se pagan proporcionales: `días_trabajados / 365 × 15 días hábiles`.
- El feriado anual se paga sobre la remuneración íntegra (incluyendo promedio de remuneraciones variables de los últimos 3 meses).

### 1.6 Término de Contrato y Finiquito (Art. 159-177)

**Causales:**

| Art. | Causal | Indemnización años servicio | Aviso previo |
|---|---|---|---|
| 159 N°1 | Mutuo acuerdo | No (salvo pacto) | No |
| 159 N°2 | Renuncia voluntaria | No | Sí (30 días) |
| 159 N°3 | Muerte trabajador | No | — |
| 159 N°4 | Vencimiento plazo | No | No |
| 159 N°5 | Conclusión obra | No | No |
| 159 N°6 | Caso fortuito | No | — |
| 160 | Causas imputables al trabajador (conducta grave) | No | No |
| **161** | **Necesidades de la empresa (trabajador ≥1 año)** | **Sí** | **Sí (30 días o sustitutivo)** |
| **161 bis** | **Trabajador con fuero maternal o paternal** | — | — |

**Indemnización por años de servicio (Art. 163):**
- 1 mes de última remuneración (promedio de últimos 3 meses, tope 90 UF) por cada año completo de servicios, con fracción > 6 meses como año completo.
- Máximo: 11 meses.
- Tope por mes: 90 UF (por la remuneración mensual base del cálculo).

**Aviso previo (Art. 161):**
- 30 días de anticipación o, en su defecto, indemnización sustitutiva del aviso previo (1 mes de remuneración o fracción proporcional).

**Feriado proporcional en finiquito:**
```
dias_habiles = (dias_trabajados_en_el_año / 365) × 15
monto = (remuneración_diaria × dias_habiles) → remuneración incluye promedio variables
```

---

## 2. Sistema de AFP — DL 3500 (1980)

**Fuente:** Superintendencia de Pensiones — spensiones.cl  
**URL:** https://www.spensiones.cl

### 2.1 Cotización Obligatoria

- **Trabajador:** 10% de la remuneración imponible + comisión de la administradora.
- **Empleador (SIS):** Prima del Seguro de Invalidez y Sobrevivencia (SIS), definida mensualmente. **1,62%** (valor jun-2026; era 1,49% en 2022 — ver FUENTES-NORMATIVAS-RRHH.md).

### 2.2 Tope Imponible

- **90 UF** (valor jun-2026; era 87,8 UF en 2022 — ver FUENTES-NORMATIVAS-RRHH.md).
- La cotización se aplica sobre `min(remuneración_imponible, tope_UF × valor_UF_del_mes)`.

### 2.3 AFP Vigentes y Comisiones (referencia 2026)

| AFP | Comisión sobre remuneración (%) | Cotización total trabajador (%) |
|---|---|---|
| Capital | 1,44 | 11,44 |
| Cuprum | 1,44 | 11,44 |
| Habitat | 1,27 | 11,27 |
| Modelo | 0,58 | 10,58 |
| PlanVital | 1,16 | 11,16 |
| ProVida | 1,45 | 11,45 |
| Uno | 0,49 | 10,49 |

> **⚠️ Advertencia:** Las comisiones cambian por licitación (cada 2 años) y por actualización del mercado.  
> Verificar en spensiones.cl antes de actualizar el seeder de producción.

### 2.4 APV (Ahorro Previsional Voluntario)

- Trabajador puede cotizar adicionalmente (no obligatorio).
- Reducible de la base tributable del impuesto único (Art. 42 bis LIR) hasta ciertos topes.
- El ERP lo modela como `ConceptoRemuneracion` tipo `descuento_voluntario`.

---

## 3. Cotizaciones Previsionales — DL 3501 (1980)

**Fuente:** bcn.cl  
**URL:** https://www.bcn.cl/leychile/navegar?idNorma=6521

- Establece las bases imponibles para cotizaciones previsionales.
- Exime de cotización: movilización, colación, viáticos, asignación familiar.
- Ratifica: tope de 90 UF para AFP/salud (valor 2022: 87,8 UF — ver FUENTES-NORMATIVAS-RRHH.md) y 135,2 UF para AFC.

---

## 4. Salud Obligatoria — Ley 18.469 (1985) y DFL 1/2005

**Fuente:** Ministerio de Salud — minsal.cl; FONASA — fonasa.cl  
**URL:** https://www.bcn.cl/leychile/navegar?idNorma=29731

### 4.1 FONASA (salud pública)

- El trabajador cotiza el **7%** de su remuneración imponible al sistema de salud.
- Base imponible idéntica a AFP (mismo tope de 90 UF — valor jun-2026; era 87,8 UF en 2022 — ver FUENTES-NORMATIVAS-RRHH.md).
- El empleador no cotiza salud (el costo es del trabajador, descontado de su sueldo).

### 4.2 ISAPRE (salud privada)

- El trabajador elige una ISAPRE y paga un plan en UF según cotización pactada.
- Debe cubrir al menos el equivalente a 7% del imponible (mínimo legal).
- Si el plan cuesta más de 7% del imponible, el exceso lo paga el trabajador (cotización adicional).
- El ERP almacena: tipo (`FONASA`/`ISAPRE`), plan en UF, cotización adicional pactada.

---

## 5. Seguro de Cesantía AFC — Ley 19.728 (2001)

**Fuente:** SUSESO — suseso.cl; AFC Chile — afcchile.cl  
**URL:** https://www.bcn.cl/leychile/navegar?idNorma=186202

### 5.1 Tasas por Tipo de Contrato

| Tipo contrato | Trabajador | Empleador | Total |
|---|---|---|---|
| **Indefinido** | 0,6% | 2,4% | 3,0% |
| **Plazo fijo** | 0,0% | 3,0% | 3,0% |

### 5.2 Tope Imponible AFC

- **135,2 UF** (valor jun-2026; era 122,6 UF en 2022 — ver FUENTES-NORMATIVAS-RRHH.md).

### 5.3 Cuenta Individual vs. Fondo Solidario

- Los primeros años van a la cuenta individual del trabajador.
- El fondo solidario cubre a quienes agotan la cuenta individual.

---

## 6. Reforma Previsional — Ley 20.255 (2008) / SIS

**Fuente:** bcn.cl  
**URL:** https://www.bcn.cl/leychile/navegar?idNorma=269892

- Crea el **SIS (Seguro de Invalidez y Sobrevivencia)** como cotización patronal.
- La prima SIS varía según la licitación vigente y se aplica sobre la remuneración imponible.
- En jun-2026 la tasa SIS es **1,62%** (era 1,49% en 2022 — ver FUENTES-NORMATIVAS-RRHH.md; verificar en spensiones.cl mensualmente).
- La reforma también introdujo el aporte al Pilar Solidario (cargo fiscal, no de empleadores).

---

## 7. Impuesto Único de Segunda Categoría — Art. 42-43 LIR (DL 824/1974)

**Fuente:** SII — sii.cl  
**URL:** https://www.sii.cl/catastro/ley_renta.htm

### 7.1 Base Tributable

```
Base_tributable = Imponible − Descuentos_previsionales_legales + Haberes_no_imponibles_que_tributan
```

En la práctica:
```
Base_tributable = Total_haberes − (AFP_trabajador + Salud + AFC_trabajador)
```

### 7.2 Tabla de Tramos (referencia 2026)

La tabla se expresa en **UTM mensual**. Los tramos cambian cuando varía la UTM.

| Tramo (UTM/mes) | Tasa | Factor deducción |
|---|---|---|
| Hasta 13,5 UTM | Exento (0%) | 0 |
| 13,5 a 30 UTM | 4,0% | 0,54 UTM |
| 30 a 50 UTM | 8,0% | 1,74 UTM |
| 50 a 70 UTM | 13,5% | 4,49 UTM |
| 70 a 90 UTM | 23,0% | 11,14 UTM |
| 90 a 120 UTM | 30,4% | 17,80 UTM |
| 120 a 310 UTM | 35,0% | 23,32 UTM |
| Más de 310 UTM | 40,0% | 38,82 UTM |

**Cálculo:**
```
Impuesto = (Base_tributable × Tasa_del_tramo) − Factor_deducción_en_pesos
```
Donde el factor deducción se convierte: `Factor_UTM × valor_UTM_del_mes`.

> **⚠️ Los tramos en pesos cambian mensualmente** según el valor de la UTM.  
> Verificar tabla actualizada en sii.cl antes de cada cierre de nómina.

---

## 8. Asignación Familiar — DFL 150 (1982) y Ley 18.987

**Fuente:** IPS (ex-INP) — ips.gob.cl; SUSESO — suseso.cl

- Beneficio por cargas familiares reconocidas (hijos, cónyuge sin renta, etc.).
- Se paga al trabajador por cada carga reconocida según tramo de remuneración.
- **No es imponible ni tributable**.

### Tramos Asignación Familiar (referencia 2026)

| Tramo (remuneración mensual del trabajador) | Monto por carga |
|---|---|
| Hasta ~$420.000 (Tramo A) | ~$16.000/carga (varía con IMM) |
| $420.001 a ~$610.000 (Tramo B) | ~$9.000/carga |
| $610.001 a ~$840.000 (Tramo C) | ~$3.000/carga |
| Sobre $840.000 (Tramo D) | Sin asignación |

> Los montos exactos son fijados por el Ministerio del Trabajo cada año con el reajuste del IMM.

---

## 9. Ingreso Mínimo Mensual (IMM)

**Fuente:** Ministerio del Trabajo — mintrab.gob.cl  
**URL:** https://www.mintrab.gob.cl/ingreso-minimo/

- Fijado por ley anualmente (normalmente en julio).
- **2026 vigente:** $539.000 CLP.
- Usado para: tope de gratificación Art. 50 (4,75 × IMM / 12), asignación familiar, mínimo contractual.

---

## 10. Ley 21.719 — Protección de Datos Personales (2024)

**Fuente:** bcn.cl  
**URL:** https://www.bcn.cl/leychile/navegar?idNorma=1207468

### 10.1 Datos Sensibles en RRHH

Son **datos personales sensibles** (requieren protección reforzada y base de legitimación explícita):
- RUT
- Remuneración y condiciones contractuales
- Datos bancarios (cuenta, banco, tipo)
- Estado de salud / previsión (AFP, ISAPRE)
- Cargas familiares

### 10.2 Implementación en el ERP

- **Datos bancarios**: cifrados en reposo con `Crypt::encryptString()` (patrón SII ya implementado).
- **Campo `$hidden`** en el modelo Eloquent para evitar serialización accidental.
- **Acceso por permiso**: endpoints protegidos por `permiso:rrhh.ver` / `permiso:rrhh.remuneraciones.ver`.
- **Multitenant estricto**: `HasEmpresaScope` obligatorio en todos los modelos del dominio Rrhh.
- **Auditoría**: toda consulta/modificación de remuneraciones queda en tabla `auditorias`.

---

## 11. Previred — Especificaciones del Archivo Previsional

**Fuente:** Previred — previred.com  
**URL:** https://www.previred.com/web/previred/

### 11.1 Propósito

Previred es el portal de pago de cotizaciones previsionales y laborales. El empleador sube un archivo mensual con el detalle de cotizaciones de cada trabajador.

### 11.2 Formato del Archivo (referencia)

El archivo es CSV con los campos:
```
RUT_trabajador | DV | AFP | Remuneración_imponible_AFP | Cotización_AFP | 
Cotización_adicional | ISAPRE/FONASA | Remuneración_imponible_salud | 
Cotización_salud | AFC | Remuneración_imponible_AFC | Cotización_AFC_trabajador |
Cotización_AFC_empleador | ...
```

> El formato exacto está documentado en previred.com (sección "Archivos de pago").  
> El módulo Previred del ERP (R6) generará este archivo desde los datos de `Liquidacion`.

---

## 12. UF, UTM, UTA — Indicadores Mensuales

**Fuente:** CMF (ex-SBIF) — cmfchile.cl; SII — sii.cl

| Indicador | Publicado por | Frecuencia | Uso |
|---|---|---|---|
| **UF** | CMF | Diario | Tope imponible AFP/salud/AFC, plan Isapre, indemnizaciones, alquileres |
| **UTM** | SII | Mensual | Tramos impuesto único, multas, etc. |
| **UTA** | SII | Anual (= 12 UTM) | Referencias tributarias anuales |

- La UF del **primer día hábil del mes** se usa para calcular el tope imponible del mes.
- Los indicadores se guardan en la tabla `indicadores_mensuales` (ver modelo `IndicadorMensual`).

---

## 13. Resumen de Tasas y Topes (Referencia Rápida — Verificar Antes de Producción)

| Concepto | Tasa trabajador | Tasa empleador | Tope imponible |
|---|---|---|---|
| AFP (cotización) | 10,00% | — | 90 UF (valor 2022: 87,8 UF — ver FUENTES-NORMATIVAS-RRHH.md) |
| AFP (comisión) | variable (0,46%–1,45%) | — | 90 UF |
| SIS (Seguro Invalidez/Sobrevivencia) | — | 1,62% (valor 2022: 1,49% — ver FUENTES-NORMATIVAS-RRHH.md) | 90 UF |
| Salud (Fonasa) | 7,00% | — | 90 UF |
| Salud (Isapre) | plan en UF (mín. 7%) | — | 90 UF |
| AFC – contrato indefinido | 0,60% | 2,40% | 135,2 UF (valor 2022: 122,6 UF — ver FUENTES-NORMATIVAS-RRHH.md) |
| AFC – contrato plazo fijo | 0,00% | 3,00% | 135,2 UF |
| Impuesto Único | tabla SII por tramos | — | — |

---

## Fuentes Primarias

1. **Código del Trabajo (DFL 1/2003):** https://www.bcn.cl/leychile/navegar?idNorma=207436
2. **DL 3500 (AFP):** https://www.bcn.cl/leychile/navegar?idNorma=7147
3. **DL 3501 (Cotizaciones):** https://www.bcn.cl/leychile/navegar?idNorma=6521
4. **Ley 18.469 (Salud):** https://www.bcn.cl/leychile/navegar?idNorma=29731
5. **Ley 19.728 (AFC):** https://www.bcn.cl/leychile/navegar?idNorma=186202
6. **Ley 20.255 (Reforma Previsional):** https://www.bcn.cl/leychile/navegar?idNorma=269892
7. **DL 824 / LIR (Impuesto Único):** https://www.bcn.cl/leychile/navegar?idNorma=3626
8. **DFL 150 (Asignación Familiar):** https://www.bcn.cl/leychile/navegar?idNorma=38280
9. **Ley 21.719 (Protección Datos):** https://www.bcn.cl/leychile/navegar?idNorma=1207468
10. **Superintendencia de Pensiones:** https://www.spensiones.cl
11. **Previred:** https://www.previred.com
12. **SII (tablas impuesto único):** https://www.sii.cl
13. **Dirección del Trabajo:** https://www.dt.gob.cl
14. **SUSESO:** https://www.suseso.cl
15. **AFC Chile:** https://www.afcchile.cl
