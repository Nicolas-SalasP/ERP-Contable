# Investigación técnica: SII Fase 7 (PDF timbrado) y Previred (bloque Mutualidad)

**Fecha:** 2026-07-10
**Tipo:** Investigación y documentación únicamente. No se escribió código de producción.
**Alcance:** Preparar dos features recomendadas en paralelo por el council de evaluación, para que
otra sesión las implemente con contexto sólido y sin adivinar requisitos normativos.

> **Actualización 2026-07-10 (misma fecha, sesión posterior):** el hallazgo de la sección 2.3
> (dos esquemas de código de mutualidad incompatibles) se corrigió — no es solo un hallazgo de
> investigación, era un bug de datos real. Ver "Estado del fix" al inicio de la sección 2 para el
> detalle de qué se implementó y qué sigue pendiente (los códigos Previred oficiales de IST/CChC
> siguen sin verificar, ver 2.2).

---

## TAREA 1 — SII Fase 7: Representación impresa PDF con timbre PDF417

### 1.1 Resumen del hallazgo

El SII exige que todo DTE tenga una "representación impresa" en papel (o PDF) que reproduzca los
datos del documento y contenga el **Timbre Electrónico** codificado como código de barras
bidimensional **PDF417**. El contenido exacto que va dentro del PDF417 es el **TED completo**
(`<TED version="1.0">...</TED>`, la misma estructura XML que ya arma `TedBuilder`), no un resumen
ni un hash aparte — es el string XML del timbre, codificado en modo binario (Byte Compaction Mode)
para preservar los caracteres ISO-8859-1 sin pérdida.

**El proyecto ya tiene la mitad de la infraestructura para esto:**
- `App\Domains\Sii\Services\Xml\Ted\TedBuilder::buildFirmado()` (`Backend-laravel/app/Domains/Sii/Services/Xml/Ted/TedBuilder.php`)
  ya construye el `<TED>` firmado completo en bytes ISO-8859-1 — es exactamente el payload que hay
  que pasarle al generador de PDF417.
- El modelo `SiiDteEmitido` (`Backend-laravel/app/Domains/Sii/Models/SiiDteEmitido.php`, líneas
  69 y 141) **ya tiene las columnas `ted_xml` y `pdf_path`** en fillable — el esquema fue diseñado
  anticipando F7, solo falta el servicio que genere el PDF y persista la ruta.
- El proyecto ya usa `barryvdh/laravel-dompdf` (`^3.1`, ver `Backend-laravel/composer.json`) para
  PDFs existentes (Libro de Remuneraciones, Impuestos, Cotizaciones — ver
  `LibroRemuneracionesController.php`, `ImpuestosController.php`, `CotizacionController.php`). Ese
  es el patrón a seguir para el PDF del DTE (`Pdf::loadView(...)`), no hay que introducir un motor
  de PDF nuevo.
- **Ninguna librería instalada actualmente genera PDF417.** `barryvdh/laravel-dompdf` no genera
  códigos de barras 2D; hay que agregar una dependencia dedicada solo para el PDF417 y luego
  incrustar la imagen resultante (PNG/SVG) en la vista Blade que dompdf renderiza.

### 1.2 Qué exige el SII exactamente (fuente citada)

**a) Obligación de la representación impresa y contenido del timbre**

> Fuente: `docs/sii-normativa/instructivo_emision.pdf`, sección **2.6 "Adecuar procedimiento de
> impresión de documentos"** (Pág. 5-6 de 30):
>
> "El contribuyente debe adecuar sus procedimientos y formularios utilizados para la impresión, con
> el fin de generar la representación impresa según la norma del SII, **incluyendo el código de
> barras 2D, simbología PDF417, que contenga la información del código del timbre electrónico**."
>
> La información incluida en la impresión del Timbre Electrónico es (lista textual del PDF):
> 1. Versión del timbre electrónico · 2. RUT del Emisor · 3. Tipo de Documento · 4. Número de Folio
> · 5. Fecha de emisión · 6. RUT del Receptor · 7. Razón Social Receptor · 8. Monto total ·
> 9. Descripción del primer Item del Detalle · 10. Fecha y hora de generación del timbre ·
> 11. Código de Autorización de Folios (CAF) · 12. Algoritmo de firma · 13. Firma digital.

Esta lista coincide campo a campo con las etiquetas `<RE><TD><F><FE><RR><RSR><MNT><IT1><CAF><TSTED>`
que ya arma `TedBuilder::buildFirmado()` — confirma que **no hay que construir un payload nuevo**,
solo consumir el TED ya firmado.

**b) Estructura exacta del TED**

> Fuente: `docs/sii-normativa/instructivo_emision.pdf`, **ANEXO 2, A.2.3 "Estructura"** (Pág. 16-18
> de 30). Reproduce la estructura `<TED version="1.0"><DD>...</DD><FRMT algoritmo="SHA1withRSA">
> ...</FRMT></TED>` con ejemplo completo (Figura A.6). Coincide 1:1 con lo que produce
> `TedBuilder.php` líneas 40-63.

También en `docs/sii-normativa/formato_dte_202602.pdf`, sección **G.- TIMBRE ELECTRÓNICO SII DEL
DOCUMENTO** (Pág. 48-49 de 51): confirma que el timbre "Corresponde a la información del Código de
Barras Bidimensional PDF417" — mismo dato, formulación más breve, sin agregar campos nuevos.

**c) Qué va exactamente codificado en el PDF417 (confirmación explícita)**

> Fuente: `docs/sii-normativa/instructivo_emision.pdf`, línea previa a A.2.5: "Debido a que el
> Timbre Electrónico **se debe imprimir en un código de barras 2D (PDF417)**... las firmas que el
> Timbre Electrónico incluye deben regirse estrictamente por lo especificado en el presente
> documento y no por el estándar XMLDSIG". Es decir: **se codifica el string completo `<TED>...
> </TED>` tal cual**, no un base64 del TED completo, no un hash. (Los valores internos como
> `<FRMT>` y `<RSAPK>` sí van en base64 — eso ya lo implementa `TedSignerService` — pero el
> contenedor `<TED>` en sí se imprime como texto plano dentro del PDF417.)

**d) Reglas técnicas exactas de generación/impresión del PDF417**

> Fuente: `docs/sii-normativa/instructivo_emision.pdf`, **ANEXO 2, A.2.5 "Reglas Para La Generación
> e Impresión Del Timbre PDF417"** (Pág. 21-22 de 30). Cita textual de las reglas obligatorias:
>
> - Impresora láser o inyección tinta, resolución mínima **300 DPI**.
> - Color de impresión: **negro**.
> - **Quiet Zone** (espacio en blanco) mínimo de **0,25 pulgadas** alrededor de los 4 lados.
> - **"Truncated" NO debe usarse** (aumenta sensibilidad al daño).
> - Codificación en **modo binario (Byte Compaction Mode)** — para preservar caracteres especiales
>   sin reinterpretación.
> - **Error Correction Level (ECL): nivel 5** obligatorio (dado el volumen de datos del TED).
> - **X Dim (ancho de barra más angosta): mínimo 6,7 mils.**
> - **Y Dim (alto de fila): relación 3:1 respecto a X Dim.**
> - Tamaño recomendado del código impreso: **máximo 3 cm alto × 9 cm ancho.**

Estos cinco parámetros (Byte Compaction, ECL 5, X-Dim ≥6.7 mils, ratio 3:1, sin truncado) son
**verificables y exigibles al elegir librería** — cualquier librería PDF417 debe permitir configurar
ECL y modo de codificación explícitamente; si no lo permite, no sirve para este caso de uso.

**e) Layout/diagramación de la representación impresa (formato de la hoja completa)**

> `docs/sii-normativa/formato_dte_202602.pdf`, capítulo **"3.- FORMATO DE IMPRESIÓN DE DOCUMENTOS"**
> (justo después de la sección G, Pág. 50 de 51) dice textualmente:
>
> **"Se elimina este capítulo por estar contenida en MANUAL DE MUESTRAS IMPRESAS en www.sii.cl
> SISTEMA DE FACTURACIÓN DE MERCADO, opción Ayudas."**

**Hallazgo importante — gap de información:** el layout detallado (posiciones de campos, tamaño
mínimo de letra, si el logo/RUT/folio van en el encabezado, márgenes de la hoja, diferencia exacta
entre formato carta y formato térmico 80mm) **NO está en ninguno de los tres PDFs presentes en
`docs/sii-normativa/`**. Ese contenido vive en el documento separado "MANUAL DE MUESTRAS IMPRESAS"
del sitio sii.cl, que **no está descargado en el repositorio**. No se debe inventar el layout — hay
que descargar ese manual antes de fijar el diseño de la vista Blade/PDF, o basarse en la práctica de
mercado (que sigue siendo una interpretación, no la norma oficial) y dejarlo explícito en el código.

**f) Diferencias factura afecta/exenta vs. boleta (39/41)**

No se encontró ninguna mención a boletas electrónicas (tipo 39/41) en `instructivo_emision.pdf` (el
documento es de **versión 15.10.09**, anterior a la ley de boleta electrónica obligatoria) ni en
`formato_dte_202602.pdf` en el capítulo de timbre/impresión. El propio `Sii/README.md` ya advierte
que las boletas son un **"flujo PARALELO"** (`EnvioBOLETA_v11.xsd`, endpoint distinto, RCOF diario) —
es razonable asumir que también tendrán reglas de representación impresa propias (probablemente
recibo corto/térmico dado su volumen de emisión), pero **eso no está verificado en la normativa
local**; hay que confirmarlo con la resolución específica de boleta electrónica antes de
implementar F6-bis/F7 para boletas. No inventar aquí un layout de boleta.

**g) Certificación: el SII pide muestras impresas**

> Fuente: `docs/sii-normativa/manual_certificacion.pdf`, sección **"4. Pruebas de Impresión de
> DTEs"** (Pág. 27 de 29): "Esta etapa considera la entrega al SII de la imagen de un conjunto de
> documentos impresos de acuerdo a la normativa y que incluyan el timbre electrónico en
> representación PDF417 ... enviado a sii_dte_impresos@sii.cl."

Esto confirma que F7 es un requisito duro para pasar la certificación oficial (Fase F9 del roadmap
del módulo), no solo una comodidad para el cliente.

### 1.3 Librería PHP para PDF417 (investigación verificada)

Se verificó vía búsqueda web (no había opción confiable de inferirlo del repo, porque no hay
ninguna dependencia PDF417 instalada hoy):

| Librería | ¿Soporta PDF417? | Estado / notas |
|---|---|---|
| `picqer/php-barcode-generator` | **No confirmado / probablemente NO** | Es un generador de códigos 1D (Code128, Code39, EAN, UPC); no aparece PDF417 en su lista de tipos soportados. **No usar** sin verificar antes en el código fuente (`src/Types/`) porque la documentación pública no lo confirma ni lo descarta con certeza. |
| `tecnickcom/tc-lib-barcode` | **Sí** | Librería del ecosistema TCPDF, activamente mantenida, soporta PDF417 (ISO/IEC 15438:2006) entre muchos otros formatos. Requiere PHP 8.2+ (compatible con el proyecto, que ya pide `"php": "^8.2"`). Output como PNG/SVG/string binario — se puede incrustar directo en la vista Blade de dompdf como imagen. **Candidato recomendado.** |
| `leongrdic/php-pdf417` (fork de `ihabunek/pdf417-php`) | Sí | Librería específica de PDF417, liviana, PHP 8.2 compatible. El original (`ihabunek/pdf417-php`, también conocido como `bigfish/pdf417`) está **abandonado/archivado**; este fork lo mantiene vivo. Alternativa más minimalista a tc-lib-barcode si no se quiere traer todo el ecosistema Tecnick. |
| `barcode-bakery/*` (Barcode Bakery) | Sí | Soporta PDF417 con control fino de ECL/X-Dim, pero es **software comercial de pago** para uso en producción (solo la versión gratuita es para uso no-comercial). Descartar salvo que se justifique el costo de licencia. |

**Recomendación para el desglose de subtareas:** evaluar `tecnickcom/tc-lib-barcode` primero (mejor
mantenida, respaldada por el proyecto TCPDF con trayectoria larga en facturación electrónica
latinoamericana) y confirmar en un spike que su configuración permite fijar explícitamente ECL nivel
5 y Byte Compaction Mode antes de comprometerse — **esto no se pudo verificar en detalle de API en
esta investigación** (solo se confirmó soporte de PDF417 y requisitos de PHP), así que es la primera
subtarea técnica antes de escribir código de producción.

### 1.4 Subtareas técnicas concretas (orden sugerido de implementación)

1. **Spike de librería (medio día).** Instalar `tecnickcom/tc-lib-barcode` en un entorno de prueba
   (no en el repo aún) y confirmar que su API permite fijar ECL=5, Byte Compaction Mode, y que el
   PNG/SVG resultante decodifica correctamente con un lector de PDF417 real (app de celular o
   librería de verificación). Si no cumple, evaluar `leongrdic/php-pdf417` como alternativa.
2. **Descargar el "MANUAL DE MUESTRAS IMPRESAS"** desde sii.cl (no está en el repo) y agregarlo a
   `docs/sii-normativa/` antes de diseñar la vista — es la única fuente oficial del layout exacto
   de campos en la hoja (más allá del propio timbre).
3. **Agregar dependencia** `composer require tecnickcom/tc-lib-barcode` (o la alternativa elegida en
   el paso 1) a `Backend-laravel/composer.json`.
4. **Nuevo servicio** `App\Domains\Sii\Services\Impresion\RepresentacionImpresaService` (o nombre
   equivalente, dentro de `Backend-laravel/app/Domains/Sii/Services/`, siguiendo la convención de
   subcarpetas existente tipo `Emision/`, `Xml/`): recibe un `SiiDteEmitido` con `ted_xml` ya
   poblado, genera la imagen PDF417 (Byte Compaction, ECL5, X-Dim ≥6.7 mils, ratio 3:1) y renderiza
   el PDF vía `Pdf::loadView()` (patrón ya usado en `LibroRemuneracionesController.php`), guarda el
   archivo y actualiza `pdf_path` en el modelo.
5. **Vista Blade** nueva (p. ej. `resources/views/sii/dte-pdf.blade.php`) con el layout carta,
   siguiendo el manual de muestras impresas del paso 2. Contemplar una segunda vista para formato
   térmico 80mm si el negocio lo requiere (no confirmado como obligatorio en la normativa leída).
6. **Endpoint** nuevo en `Backend-laravel/app/Domains/Sii/Routes/` (prefix `api/sii`, Sanctum, según
   convención del README del dominio) — algo como
   `GET /api/sii/dte/{id}/pdf` que dispara la generación (si no existe `pdf_path`) o sirve el
   archivo cacheado.
7. **Regla de negocio:** el PDF solo debe poder generarse cuando `SiiDteEmitido::estado` está en
   `FIRMADO` o posterior (el TED ya existe y está firmado) — reusar el enum de estados ya definido
   en el modelo, no inventar uno nuevo.
8. **Tests:** cobertura de que el servicio falla limpiamente si `ted_xml` es null (DTE no firmado
   aún), y un test de "smoke" que verifique que el PDF generado contiene una imagen (no valida el
   contenido decodificado del PDF417 en CI, salvo que se agregue también una librería de
   *decodificación* PDF417 solo para tests — evaluarlo aparte, no es prioritario).
9. **Actualizar `Backend-laravel/app/Domains/Sii/README.md`**: mover F7 del roadmap a "en curso" o
   "hecho" con referencia al servicio nuevo, y documentar la decisión de librería tomada en el paso 1.
10. Diferido explícitamente fuera de esta tarea (no confirmado en la normativa local): layout de
    boleta 39/41 impresa — requiere la resolución SII específica de boleta electrónica, no presente
    en `docs/sii-normativa/`.

---

## TAREA 2 — Previred: completar bloque Mutualidad + sexo/nacionalidad

### Estado del fix (2026-07-10, sesión posterior a esta investigación)

El hallazgo de la sección 2.3 (dos esquemas de código de mutualidad incompatibles entre Previred y
LRE) se implementó, no se dejó solo documentado, porque es un bug de datos real (afecta el archivo
previsional legal que se declara mensualmente), no una feature nueva.

**Qué se hizo:**
- Tabla `mutualidades` nueva (migración `2026_07_10_000001_create_mutualidades_table.php`): catálogo
  único con `codigo_previred` y `codigo_lre` por organismo (ACHS, ISL, IST, Mutual de Seguridad
  CChC).
- `empresas.mutualidad_id` (FK nullable) + `empresas.mutual_tasa_adicional_pct` — la afiliación y la
  tasa diferenciada ahora viven en la empresa, no en un parámetro global ni por trabajador (ver 2.3).
- Migración de datos: para empresas donde todos los empleados ya compartían el mismo
  `organismo_mutual_codigo` legacy, se infirió y asignó la mutualidad correspondiente automáticamente.
  Donde había valores heterogéneos entre trabajadores de una misma empresa, se dejó sin asignar
  (`null`) a propósito — es una señal de datos inconsistentes que requiere revisión funcional, la
  migración no decide por sí sola.
- `PreviredService` y `GenerarLreService` ahora leen `empresa->mutualidad` como fuente única
  (con fallback a los campos legacy solo para empresas aún no migradas — no se eliminó el dato viejo).
- `LiquidacionService` ahora suma la tasa básica (parámetro legal nacional) + la tasa adicional de
  la empresa al calcular `aporte_empleador_mutual` (ver 2.4 — antes esto estaba completamente sin
  modelar, subestimando el costo empresa real).
- Tests de regresión en `PreviredTest`, `GenerarLreTest` y `LiquidacionCalculoTest`. Suite completa
  validada contra SQLite y MySQL real (XAMPP local) — 2274 tests, 0 fallos en ambos motores.

**Qué sigue pendiente (no se resolvió, sigue igual que en la investigación original):**
- Los códigos `codigo_previred` de IST y Mutual de Seguridad CChC se dejaron en `null` en el seed —
  el esquema Previred original (01/02/03) nunca distinguió esos dos organismos con códigos separados,
  y no se encontró la especificación oficial vigente de previred.com para confirmarlos (ver 2.2).
  **No usar esos dos organismos en producción hasta verificar y actualizar la migración con el
  código correcto** — mientras tanto, `PreviredService` cae al valor por defecto `'01'` si
  `codigo_previred` es null, lo cual es una aproximación, no el valor correcto.
- CCAF (bloque 52-58) y datos extendidos de empleador (72-80) siguen sin modelar — fuera del alcance
  de este fix, tal como recomendó el council (Fase 2 de Previred, no ahora).
- Formato de `empleados.nacionalidad` (¿ISO 3166-1 alpha-3 real?) sigue sin verificar contra la
  tabla de países de Previred (ver 2.1).

### 2.1 Resumen del hallazgo — corrección importante sobre el estado real

**El estado real del código es más avanzado de lo que dice `docs/integraciones/FORMATO-PREVIRED.md`
en su sección 5.** El documento fue escrito el 2026-06-11 y quedó desactualizado por trabajo
posterior (probablemente durante la implementación de LRE, 2026-06-16 según los commits). Puntos
concretos:

- **Sexo y nacionalidad SÍ existen en el ERP.** La migración
  `Backend-laravel/database/migrations/2026_06_11_000001_create_empleados_table.php` (líneas 20-21)
  ya define `empleados.sexo` (`enum('M','F','O')`, nullable) y `empleados.nacionalidad` (`string(3)`,
  default `'CHL'`). Y `PreviredService::construirFila105()`
  (`Backend-laravel/app/Domains/Rrhh/Services/Previred/PreviredService.php`, líneas 119-122 y
  191-192) **ya los lee y los emite** en los campos 6 y 7. `FORMATO-PREVIRED.md` sección 5 dice
  "Sin dato ERP" para ambos — **eso ya no es correcto y hay que corregir el documento**, no el
  código.
- **El código de mutualidad NO va en `empresas` como hipotetizaba la tarea — ya existe una
  implementación parcial, pero repartida en DOS tablas con DOS esquemas de códigos distintos que no
  coinciden entre sí.** Ver detalle en 2.3.
- El campo 60 (cotización mutualidad básica) **ya se calcula y se informa** —
  `Liquidacion::aporte_empleador_mutual`, calculado en `LiquidacionService.php` línea 281 como
  `baseImponible × ParametroPrevisional.mutual_cotizacion_basica_pct` (0.9% por defecto,
  `RrhhParametrosLegalesSeeder.php` línea 94). Lo que falta es solo el **código** de la mutualidad
  (campo 59) resuelto de forma consistente, y los campos 61-69 (adicional, diferenciada,
  extraordinaria, subsidios, tasa diferenciada) que siguen sin dato ERP.

### 2.2 Organismos administradores Ley 16.744 y sus códigos (fuente)

`docs/integraciones/FORMATO-PREVIRED.md` sección 6 **no tiene** tabla de códigos de mutualidad (solo
tiene 6.1 AFP y 6.2 Salud) — el comentario del código (`PreviredService.php` línea 49) es la única
fuente actual en el repo:

> `// El valor correcto viene de ParametroPrevisional::mutual_codigo (01=ACHS, 02=ISL, 03=Mutual).`

Los organismos administradores de la Ley 16.744 en Chile son:
- **ACHS** (Asociación Chilena de Seguridad) — mutualidad privada.
- **Mutual de Seguridad CChC** (Cámara Chilena de la Construcción) — mutualidad privada.
- **IST** (Instituto de Seguridad del Trabajo) — mutualidad privada (antes "IST", a veces referido
  como "Instituto de Seguridad del Trabajo").
- **ISL** (Instituto de Seguridad Laboral) — organismo **estatal**, sucesor legal del antiguo INP
  para el seguro de accidentes del trabajo de los trabajadores no afiliados a una mutualidad
  privada.

**No se pudo verificar contra la tabla oficial de Previred** (no está en `docs/sii-normativa/` ni en
`docs/integraciones/`) cuáles son los códigos numéricos oficiales exactos que exige el *archivo
Previred* en el campo 59 — solo lo que quedó documentado en el comentario del código
(`01`/`02`/`03`). Antes de dar por buenos esos tres códigos hay que contrastarlos con la
especificación vigente en previred.com (la misma advertencia que ya hace
`FORMATO-PREVIRED.md` en su encabezado: "Verificar antes de producción").

### 2.3 Hallazgo crítico: dos esquemas de código de mutualidad que NO coinciden

Esto es un hallazgo real de inconsistencia arquitectónica, no hipotético:

| Tabla / campo | Formato de código | Valores documentados en el código | Usado por |
|---|---|---|---|
| `parametros_previsionales.mutual_codigo` (migración `2026_06_12_000001_add_mutual_codigo_to_parametros_previsionales.php`, string(2), default `'01'`) | String de 2 dígitos con cero a la izquierda | `01=ACHS, 02=ISL, 03=Mutual de Seguridad` | `PreviredService.php` línea 177 → campo 59 del archivo Previred |
| `empleados.organismo_mutual_codigo` (migración `2026_06_16_000001_add_lre_fields_to_empleados_liquidaciones_conceptos.php` línea 13, `unsignedSmallInteger`, nullable) | Entero sin ceros | `1=ACHS, 2=IST, 3=MUTUAL, 4=CChC` | `GenerarLreService.php` línea 243 → código LRE 1152 |

Problemas concretos:
1. **`ISL` (código 2 en Previred) no aparece en absoluto en el esquema del LRE**, que en su lugar
   tiene `IST` en la posición 2 — son organismos distintos (ISL es estatal, IST es una mutualidad
   privada), no un simple typo, pero el resultado es que **el código 2 significa cosas distintas en
   cada tabla**.
2. **`CChC` (Mutual de Seguridad de la Cámara Chilena de la Construcción) solo existe en el esquema
   LRE**, no en el esquema Previred documentado (que solo tiene 3 valores, ACHS/ISL/"Mutual" — sin
   distinguir si "Mutual" ahí es Mutual de Seguridad o un genérico).
3. **Ninguna de las dos tablas es `empresas`.** El campo `mutual_codigo` vive en
   `parametros_previsionales`, que la propia migración documenta como **"parámetros globales (no
   por empresa); empresa_id = null significa global"** (`2026_06_11_000006_create_parametros_
   previsionales_table.php` línea 15-16). Es decir: **hoy todas las empresas del sistema comparten
   el mismo código de mutualidad**, lo cual es legalmente incorrecto — la afiliación a una
   mutualidad es una decisión de cada empresa (Art. 8 y siguientes Ley 16.744 vía D.S. N°285/1969 y
   normativa SUSESO), no un parámetro legal nacional único. `empleados.organismo_mutual_codigo`, en
   cambio, sí está a nivel de trabajador — más cerca de lo correcto operacionalmente (la mutualidad
   afilia a la empresa, y por transitividad a todos sus trabajadores, salvo excepción), pero
   almacenarlo por trabajador individualmente abre la puerta a inconsistencias entre trabajadores de
   la misma empresa que no deberían poder ocurrir.

**Conclusión de diseño:** la afiliación a mutualidad **debe vivir en `empresas`** (una empresa = una
mutualidad, salvo que se demuestre lo contrario con una fuente legal — no se encontró ninguna en el
repo que diga que puede variar por trabajador dentro de la misma empresa). El estado actual
(parámetro global + campo por empleado) es una solución de paso, no el diseño final.

### 2.4 Tasa de cotización adicional diferenciada — impacto en cálculo, no solo en reporte

> Fuente: `docs/integraciones/FORMATO-PREVIRED.md` sección 9: *"Mutual: ~0,9% empleador — seguro
> accidentes del trabajo (Ley 16.744)."* Y el comentario de la migración
> `2026_06_11_000006_create_parametros_previsionales_table.php` línea 54: *"El porcentaje varía por
> actividad económica; se configura por empresa si difiere"* — **pero hoy no se configura por
> empresa realmente**, porque `mutual_cotizacion_basica_pct` es un campo único en un registro
> global de `parametros_previsionales` (confirmado en 2.3).

La Ley 16.744 (D.S. N°110/1968 y D.S. N°67/1999, administrados por SUSESO) contempla, además de la
**tasa básica** (0,90% actual, uniforme para todas las mutualidades por ley), una **tasa adicional
diferenciada** que cada empresa paga según:
- su actividad económica (rubro/CIIU) — riesgo presunto, y
- su siniestralidad efectiva (accidentabilidad histórica), reevaluada periódicamente por la
  mutualidad.

**Esto SÍ afecta el cálculo de la liquidación, no solo el reporte Previred.**
`LiquidacionService.php` línea 281 calcula `aporte_empleador_mutual = baseImponible ×
mutual_cotizacion_basica_pct` usando **únicamente la tasa básica**, nunca una tasa adicional. Esto
significa que **el costo empresa real está subestimado para cualquier empresa con tasa adicional
distinta de cero** (que es la gran mayoría de las empresas fuera de rubros de oficina/muy bajo
riesgo) — es un gap de cálculo, no solo un gap de reporte, aunque el aporte del empleador no impacta
el líquido a pagar del trabajador (es 100% carga patronal, no se descuenta del sueldo), así que no
hay riesgo de error hacia el trabajador, pero sí hacia la centralización contable
(`CentralizacionRemuneracionesService.php` línea 109, que sí sumariza `aporte_empleador_mutual` al
asiento contable) y hacia cualquier reporte de costo real de nómina.

### 2.5 Diseño de migración concreta propuesto

1. **Nueva tabla `mutualidades`** (catálogo, sin `empresa_id` — es un catálogo global de
   organismos, no de datos por empresa):
   ```
   id, nombre, codigo_previred (string, 2, nullable hasta confirmar con fuente oficial),
   codigo_lre (unsignedSmallInteger, nullable), activo (boolean default true), timestamps
   ```
   Sembrar con al menos ACHS, IST, Mutual de Seguridad CChC, ISL — usando las DOS columnas de código
   (`codigo_previred` y `codigo_lre`) precisamente porque **la sección 2.3 demostró que no son el
   mismo número** para el mismo organismo. No colapsar en una sola columna de código genérico.
2. **Columna `mutualidad_id`** (foreignId nullable, constrained a `mutualidades`) en `empresas` —
   reemplaza conceptualmente a `parametros_previsionales.mutual_codigo`, que pasa a ser solo la tasa
   (queda bien donde está, es un parámetro legal que sí es nacional).
3. **Columna `mutual_tasa_adicional_pct`** (decimal, nullable, default 0) en `empresas` — la tasa
   diferenciada específica de cada empresa según su resolución de afiliación con la mutualidad
   (dato que la empresa recibe de su mutualidad, no un valor legal fijo — por eso va en `empresas`,
   no en `parametros_previsionales`).
4. **Migrar `empleados.organismo_mutual_codigo` → deprecar en favor de `empresas.mutualidad_id`**,
   dado el hallazgo de 2.3 de que la afiliación es a nivel empresa. Antes de eliminar la columna,
   auditar si hay datos de producción con valores heterogéneos entre trabajadores de una misma
   empresa (indicaría datos corruptos que hay que limpiar antes de la migración, no después).
5. `empleados.sexo` y `empleados.nacionalidad` **no requieren migración — ya existen** (ver 2.1).
   Verificar únicamente que el valor de `nacionalidad` (código ISO 3166-1 alpha-3, según el
   comentario de `PreviredService.php` línea 121) es el formato que Previred realmente espera — el
   comentario del código lo asume pero **no se encontró una fuente normativa local que lo confirme
   contra la tabla de países de Previred**; el `FORMATO-PREVIRED.md` tampoco documenta el formato
   esperado de este campo. Marcar como pendiente de verificación, no dar por bueno el ISO-3166.
6. Actualizar `PreviredService.php` línea 177 para leer `empresa->mutualidad->codigo_previred` en
   vez de `liq->parametro->mutual_codigo`, y `GenerarLreService.php` línea 243 para leer
   `empresa->mutualidad->codigo_lre` en vez de `empleado->organismo_mutual_codigo` — mismo catálogo,
   dos columnas de salida.
7. Actualizar `LiquidacionService.php` línea 281 para sumar tasa básica + tasa adicional de la
   empresa: `mutualMonto = round(baseImponible × (mutual_cotizacion_basica_pct +
   empresa.mutual_tasa_adicional_pct) / 100)`.

### 2.6 Impacto en `RrhhParametrosLegalesSeeder`

El seeder actual (`Backend-laravel/database/seeders/RrhhParametrosLegalesSeeder.php` línea 94) solo
siembra `mutual_cotizacion_basica_pct = 0.9000` en el registro global de
`parametros_previsionales` — eso sigue siendo correcto y no cambia (la tasa básica sí es un
parámetro legal nacional único). Lo que hay que agregar:
- Un seeder nuevo (o extender uno existente de datos semilla de empresa) para poblar la tabla
  `mutualidades` con el catálogo de 4 organismos y sus códigos duales — **una vez confirmados los
  códigos oficiales de Previred**, que no se pudieron verificar en esta investigación (ver 2.2).
- **No** agregar `mutual_tasa_adicional_pct` al seeder de parámetros legales — ese valor es
  específico de cada empresa (depende de su rubro/siniestralidad), no un valor legal por defecto
  razonable para sembrar en un fixture nacional. Debe quedar en `0` por defecto y configurarse por
  empresa en onboarding/certificación.

### 2.7 Subtareas técnicas concretas (orden sugerido de implementación)

1. **Verificar contra fuente oficial** los códigos de mutualidad exigidos por el layout real de
   Previred (portal previred.com) y por el layout LRE del SII — no dar por buenos los valores que
   hoy solo están en comentarios de código (`01/02/03` y `1/2/3/4`). Esta es la subtarea bloqueante:
   sin esto, cualquier migración de catálogo arrastra el mismo riesgo que ya existe hoy.
2. **Corregir `docs/integraciones/FORMATO-PREVIRED.md` sección 5**: quitar sexo y nacionalidad de la
   lista de "campos sin datos en el ERP" (ya están implementados), y agregar una nota explicando el
   estado real del código de mutualidad (parcialmente implementado, con la inconsistencia de 2.3).
3. Migración `create_mutualidades_table` (catálogo, ver 2.5.1).
4. Migración `add_mutualidad_id_and_tasa_adicional_to_empresas` (ver 2.5.2-2.5.3).
5. Seeder de catálogo de mutualidades (solo tras completar el paso 1).
6. Actualizar `PreviredService.php`, `GenerarLreService.php` y `LiquidacionService.php` según 2.5.6
   y 2.5.7 — este orden importa porque cambia una fuente de verdad compartida por dos generadores de
   archivo (Previred y LRE) que hoy están desincronizados.
7. Migración de datos: mapear `empleados.organismo_mutual_codigo` existente a
   `empresas.mutualidad_id` (agregando validación de que todos los empleados de una empresa tengan
   el mismo código antes de colapsar — si no, es una señal de datos sucios que hay que resolver con
   el usuario funcional antes de escribir código).
8. Deprecar (no eliminar de inmediato) `empleados.organismo_mutual_codigo` — dejarlo un ciclo de
   release como columna leída solo para fallback/auditoría, luego eliminarla en una migración
   separada.
9. Tests: actualizar `PreviredTest.php` (que ya tiene un test explícito,
   `test_campo_mutualidad_usa_codigo_del_parametro_previsional`, línea 683, que quedará obsoleto tal
   como está escrito porque asume que el código vive en `ParametroPrevisional`) y
   `GenerarLreTest.php` para reflejar el nuevo origen del dato desde `empresas`.
10. UI: agregar selector de mutualidad + tasa adicional en la pantalla de configuración de empresa
    (`Tenri-Admin` o el módulo de configuración de empresa en el ERP, no auditado en detalle en esta
    investigación por estar fuera del alcance pedido — solo backend/datos).

---

## Notas finales sobre limitaciones de esta investigación

- No se pudo descargar ni consultar el "MANUAL DE MUESTRAS IMPRESAS" del sii.cl (Tarea 1) ni la
  especificación viva del portal previred.com (Tarea 2) — ambos son la fuente oficial final y este
  documento señala explícitamente dónde falta esa verificación en vez de rellenar con supuestos.
- La comparación de librerías PDF417 se basó en búsqueda web puntual (julio 2026), no en pruebas de
  integración reales contra el código del proyecto — el paso 1 de la sección 1.4 (spike) es
  obligatorio antes de comprometerse a una dependencia.
- El hallazgo de la sección 2.3 (dos esquemas de código de mutualidad incompatibles) es el punto más
  importante de esta investigación para Tarea 2: es un bug latente de datos, no solo un campo
  faltante, y debería tratarse con esa prioridad independientemente de cuándo se implemente el resto
  del catálogo `mutualidades`.
