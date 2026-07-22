# Envío electrónico del Libro de Compras y Ventas (LCV / IECV) al SII

Estado hoy: el LCV solo se **genera** localmente (`app/Domains/Contabilidad/Services/LibroComprasVentasService.php` +
`LibroComprasVentasController.php`, salida CSV/Excel para carga manual al portal SII). No existe cliente HTTP
hacia ningún webservice de envío. Este documento investiga si construir ese envío tiene sentido legal/técnico y,
si lo tiene, cómo hacerlo reutilizando el patrón ya usado en Boleta Electrónica.

> **ADVERTENCIA DE ALCANCE (leer antes de estimar):** la investigación encontró que **la obligación de enviar el
> IECV al SII fue eliminada en 2017** (Res. Ex. SII N°61/2017, RCV). Enviar el IECV a *producción* ya no es una
> obligación tributaria vigente para facturadores electrónicos. El envío del IECV solo sobrevive como requisito del
> **proceso de certificación** del SII. Esto cambia por completo la justificación del ítem de roadmap: ver
> "Replanteo del ítem de roadmap" en el Plan de acción.

---

## Investigación

### 1. ¿Existe un webservice de envío del IECV? ¿Endpoint, formato, sobre?

**Sí, existe, y es el mismo endpoint que ya usamos para DTE.** El IECV (Información Electrónica de Compras y Ventas)
se sube como un XML firmado al **mismo CGI legacy `DTEUpload`** que hoy usa `SiiUploadService` para las facturas:

- Certificación: `https://maullin.sii.cl/cgi_dte/UPL/DTEUpload`
- Producción: `https://palena.sii.cl/cgi_dte/UPL/DTEUpload`

Método `POST`, `multipart/form-data`, mismos campos (`rutSender`, `dvSender`, `rutCompany`, `dvCompany`, `archivo`)
y misma autenticación por cookie `TOKEN`. No hay un endpoint dedicado distinto para libros: lo único que cambia es el
XML adjunto. Fuente: implementación de referencia de desarrollador
([lenguajedemaquinas — "Cómo enviar un DTE o Libro Compra Venta"](http://lenguajedemaquinas.blogspot.com/2013/05/como-enviar-un-dte-o-libro-compra-venta.html))
y el instructivo oficial de upload de XML del SII
([Instrucciones Upload xml de Libros de Compra y Venta, sii.cl](https://www.sii.cl/declaraciones_juradas/ddjj_3327_3328/intruct_nav_UPLOAD_xml.pdf)).
Coincide con lo que ya está en `config/sii.php` (`urls.{ambiente}.upload`) y con `SiiUploadService::subir()` — el WS
de token/semilla (`CrSeed.jws` / `GetTokenFromSeed.jws`) también se reutiliza tal cual (`SiiSeedService` / `SiiTokenService`).

**Formato del "sobre" (XML):** el libro se arma con el schema oficial **`LibroCV_v10.xsd`**. La raíz es
`<LibroCompraVenta>` conteniendo un `<EnvioLibro>` con:

- `<Caratula>`: identificación del envío — `RutEmisorLibro`, `RutEnvia`, `PeriodoTributario` (AAAA-MM),
  `FchResol` y `NroResol` (fecha y número de la resolución que autorizó al emisor como facturador electrónico),
  `TipoOperacion` (`COMPRA` / `VENTA`), `TipoLibro` (`MENSUAL` / `ESPECIAL` / `RECTIFICA`), `TipoEnvio`,
  y `FolioNotificacion`.
- `<ResumenPeriodo>`: totales agregados por tipo de documento.
- `<Detalle>`: una línea por documento (tipo DTE, folio, RUT contraparte, montos neto/exento/IVA/total, etc.).
- Firma XMLDSig (`<Signature>`) sobre el `EnvioLibro`, con los mismos algoritmos SHA1/RSA-SHA1 y canonicalización
  C14N que ya usa el DTE (`config('sii.firma')`), y encoding **ISO-8859-1** (igual que el DTE).

Fuentes del schema y estructura:
[SII — Formato XML de documentos electrónicos](https://www.sii.cl/servicios_online/3532-formato_xml-3811.html),
[LibroCV_v10.xsd (niclabs/DTE, espejo del XSD oficial)](https://github.com/niclabs/DTE/blob/master/schemas/LibroCV_v10.xsd),
[LibroCV_v10.xsd (zetta-biz, espejo)](https://github.com/zetta-biz/sii-xsd-schemas/blob/main/schemas/LibroCV_v10.xsd),
[SimpleAPI — Generación del IECV desde cero](https://www.simpleapi.cl/Tutoriales/GenerarIECV) (fetch directo devolvió HTTP 500 al momento de investigar; se cita por el título indexado, no se pudo leer el cuerpo — ver huecos).

- **Es SOAP o REST:** ni uno ni otro para el *envío del libro*. El upload es un **POST multipart a un CGI** (texto/HTML
  de respuesta parseado por regex `TRACKID/ERROR/GLOSA`), idéntico al de DTE. La semilla/token sí son WS SOAP
  (`.jws`). No es la API REST/JSON moderna de Boleta (`apicert`/`pangal`) — esa es exclusiva de boletas 39/41.

> **NO CONFIRMADO:** la lista exacta y el orden de campos del `<Detalle>` y `<ResumenPeriodo>` de `LibroCV_v10.xsd`
> no se validaron contra el XSD oficial descargado (se citan espejos de GitHub y descripciones de terceros, no el
> XSD del SII leído campo por campo). Antes de implementar hay que descargar `LibroCV_v10.xsd` +
> `SiiTypes_v10.xsd` + `xmldsignature_v10.xsd` desde sii.cl y validar contra ellos, tal como se hizo con los XSD
> de DTE (que ya viven en el repo, ver `DteXsdValidator` / `EnvioDteXsdValidator`).

### 2. Normativa legal, plazos, y qué pasa si no se envía

- **El IECV fue reemplazado por el RCV (Registro de Compras y Ventas).** La **Res. Ex. SII N°61 del 12/07/2017**
  creó el RCV y **eximió a los facturadores electrónicos de la obligación de llevar los Libros de Compras y Ventas
  y de enviar el IECV**. Vigente desde el **1 de agosto de 2017** (propuesta de RCV disponible desde el 01/09/2017).
  Fuentes:
  [SII — ¿El RCV reemplaza los Libros de Compras y Ventas?](https://www.sii.cl/preguntas_frecuentes/factura_electronica/001_003_6979.htm),
  [SII — Preguntas Frecuentes RCV (PDF)](https://www.sii.cl/ayudas/ayudas_por_servicios/rcv_faqs.pdf),
  [Análisis Res. 61/2017 — SienaCloud](https://www.sienacloud.cl/normativa-65-sii-la-emision-libros-diarios/).
- **Cómo funciona el RCV hoy:** el SII **construye automáticamente** el registro a partir de los DTE emitidos y
  recibidos. El contribuyente ya no "envía" un libro mensual; a lo sumo **complementa** el RCV (agrega documentos no
  electrónicos, marca tipo de transacción de compras, acepta/reclama facturas). Para facturas de crédito, el receptor
  tiene **8 días corridos** para aceptar o reclamar; sin acción opera la "aceptación tácita" que da derecho al crédito
  fiscal. Fuentes:
  [Laudus — Registro de Compras y Ventas del SII](https://laudus.cl/factura-electronica/software-de-facturacion-electronica-nuevo-registro-de-compras-y-ventas-del-sii/),
  [EasyTax — Cómo funciona el RCV](https://www.easytax.cl/blog/registro-de-compras-y-ventas-como-funciona-en-chile).
- **Plazo del IECV (histórico, hoy sin vigencia para producción):** cuando el IECV era obligatorio se enviaba
  **mensualmente**. **NO CONFIRMADO** el día exacto del mes con fuente oficial vigente (era del orden del día 12 del
  mes siguiente, atado al F29, pero no se validó porque la obligación ya no existe y las fuentes actuales no lo
  detallan).
- **Efecto de no enviar / enviar tarde:** para el IECV en producción **ya no aplica** (no hay obligación → no hay
  multa por no enviarlo). El riesgo tributario real hoy vive en el **RCV**: no complementar/gestionar el RCV o no
  aceptar/reclamar a tiempo puede afectar la **determinación del crédito fiscal** y la propuesta de F29. **NO
  CONFIRMADO** el detalle de sanciones específicas del RCV con cita a artículo del Código Tributario.

### 3. ¿El envío es obligatorio para todos, o solo bajo condiciones?

- **Enviar el IECV a producción: NO es obligatorio para nadie que sea facturador electrónico** desde 08/2017 (Res.
  61/2017). El IECV sobrevive **solo como parte del proceso de certificación** del SII: el postulante debe generar y
  subir el IECV del set de pruebas para certificarse, aunque después, ya en operación, no lo envíe nunca más. Fuente
  explícita:
  [SII — Certificación (menú)](https://www.sii.cl/servicios_online/1039-menu_certificacion-1184.html) y la aclaración
  de que *"desde agosto de 2017 el IECV ya no forma parte del proceso electrónico, por lo tanto no debe enviarse al
  SII, aunque se solicita para el proceso de certificación"*
  ([SII — Formato IECV (PDF)](https://www.sii.cl/factura_electronica/factura_mercado/formato_iecv.pdf),
  [SII — Instrucciones construcción set de pruebas](https://www.sii.cl/servicios_online/docs/inst_set_pruebas.pdf)).
- Contribuyentes **no** acogidos a factura electrónica, o que emiten documentos que no requieren envío, quedan fuera
  del RCV automático y reportan resúmenes en el sistema RCV
  ([SII — 001_003_6979](https://www.sii.cl/preguntas_frecuentes/factura_electronica/001_003_6979.htm)).

### 4. RCOF (Reporte de Consumo de Folios) — es OTRA cosa, y también quedó obsoleto

El **RCOF** es un reporte **diario** del **consumo de folios de boletas** (cantidad, tipo y montos de boletas
emitidas por día). **No tiene relación con el IECV** (uno es de boletas/folios diarios, el otro es un libro
mensual de compras y ventas). Dato clave para el roadmap:

- **La obligación de enviar el RCOF terminó el 1 de agosto de 2022.** Con la entrada en vigencia de la boleta
  electrónica que se informa **documento por documento** al SII, el reporte diario de consumo dejó de exigirse
  (cumplimiento requerido solo hasta julio 2022). La deuda de RCOF de períodos anteriores no prescribe. Fuentes:
  [SII — RCOF (FAQ catastro)](https://www.sii.cl/preguntas_frecuentes/catastro/001_012_6270.htm),
  [Bsale — RCOF pendiente en el SII](https://ayuda.bsale.app/support/solutions/articles/151000005903--c%C3%B3mo-revisar-los-reportes-de-consumo-de-folios-rcof-pendiente-en-el-sii-),
  [TUU — ¿Qué es el RCOF?](https://help.tuu.cl/temas-de-ayuda/5pKp9Zk7c41cBeKgJEzQRB/%C2%BFqu%C3%A9-es-el-rcof-reporte-de-consumo-de-folios/rhYweXoghVZSapx2td59iS).
- Implicancia: como Tenri ya envía cada boleta 39/41 individualmente vía `EnvioBoletaService`, **construir el RCOF
  sería implementar una obligación derogada**. Solo tendría valor si aparece un contribuyente con deuda histórica
  (< 08/2022) que cubrir, lo cual es un caso de borde y probablemente no justifica código nuevo.

### Resumen de la investigación (qué es real y qué no)

| Ítem | Veredicto | Vigencia |
|------|-----------|----------|
| Endpoint envío IECV | `DTEUpload` (maullin/palena), mismo que DTE | Técnico OK, pero obligación derogada |
| Formato sobre | XML `LibroCV_v10.xsd`, `EnvioLibro`+`Caratula`, firma XMLDSig ISO-8859-1 | Vigente para certificación |
| Obligación de enviar IECV a producción | **Eliminada** (Res. 61/2017, RCV) | Sin vigencia desde 08/2017 |
| IECV en certificación | Requerido | Vigente |
| RCV automático | Lo arma el SII con los DTE | Vigente |
| RCOF | **Obligación terminada** 08/2022 | Sin vigencia |

---

## Plan de acción

### Replanteo del ítem de roadmap (recomendación)

El ítem "envío real de LCV al SII, mismo patrón que boleta" fue especificado asumiendo que existe una obligación de
envío como la de los DTE. **Esa obligación no existe desde 2017.** Antes de escribir código conviene decidir, con el
negocio, cuál de estos tres alcances se persigue — están ordenados de mayor a menor valor real:

- **Opción A — Consulta/conciliación del RCV (RECOMENDADA).** En vez de *enviar* un libro que el SII ya no pide,
  **consumir el RCV que el SII construye** y conciliarlo contra los registros locales (`sii_dte_emitido` y
  `facturas` tipo COMPRA). Esto sí resuelve un dolor vigente: detectar DTE recibidos que el cliente no tiene
  cargados, diferencias de crédito fiscal, y facturas pendientes de aceptar/reclamar dentro de los 8 días. Requiere
  investigar el WS/endpoint de consulta del RCV (**NO CONFIRMADO** en esta investigación, ver riesgos).
- **Opción B — Envío IECV solo para certificación.** Implementar el `EnvioLibro` firmado + upload contra `maullin`
  **exclusivamente** para poder completar el set de certificación del SII de forma automatizada. Valor acotado
  (se hace una vez por empresa), pero reutiliza casi todo lo de DTE.
- **Opción C — Descartar / documentar.** Dejar el LCV como está (generación local para carga manual, que ya cubre el
  caso raro de quien aún quiera subirlo) y **no** construir envío. Actualizar el roadmap marcando el ítem como
  "obsoleto por Res. 61/2017".

El resto del plan detalla la **Opción B** (es la que calza literalmente con "mismo patrón que boleta" y es
autocontenida), y esboza la **Opción A** como epic aparte porque su incógnita técnica (endpoint del RCV) es mayor.

### Opción B — Servicios nuevos (patrón Seed/Token/Upload de boleta)

Reutiliza el máximo posible. La semilla y el token de `maullin`/`palena` **ya existen** (`SiiSeedService`,
`SiiTokenService`) — a diferencia de boleta, **no** hace falta un Seed/Token nuevos, porque el libro va por el mismo
host que el DTE, no por `apicert`. Servicios a crear:

- **`App\Domains\Sii\Services\Xml\Libro\LibroCvXmlBuilder`** — arma el XML `<LibroCompraVenta><EnvioLibro>` (Caratula
  + ResumenPeriodo + Detalle) a partir de la salida de `LibroComprasVentasService::generarVentas()/generarCompras()`.
  Responsabilidad única: estructura + encoding ISO-8859-1. Espejo de `DteXmlBuilder` / `SetDteBuilder`.
- **`App\Domains\Sii\Services\Xml\Libro\LibroCvSigner`** — firma XMLDSig del `EnvioLibro`. Reutiliza `XmlDsigSigner`
  (mismos algoritmos que DTE); probablemente sea un wrapper delgado, no lógica de firma nueva.
- **`App\Domains\Sii\Services\Xml\Libro\LibroCvXsdValidator`** — valida contra `LibroCV_v10.xsd` antes de enviar
  (espejo de `EnvioDteXsdValidator`). Requiere agregar los XSD al repo.
- **`App\Domains\Sii\Services\Ws\Libro\SiiLibroUploadService`** — cliente HTTP del `DTEUpload` para el libro.
  **Casi idéntico a `SiiUploadService`**; evaluar si conviene *reutilizar* `SiiUploadService` tal cual (el endpoint,
  los form fields y el parseo `TRACKID/ERROR/GLOSA` son los mismos) en lugar de duplicar. Recomendación: extraer un
  método/servicio común y no clonar el regex.
- **`App\Domains\Sii\Services\Envio\EnvioLibroService`** — orquestador (espejo de `EnvioBoletaService` /
  `EnvioSiiService`): toma período+tipo, arma → firma → valida XSD → obtiene token → sube → persiste track_id/estado,
  con la misma política de reintentos y auditoría (`request_body`/`response_body` redactados).

### Modelos / tablas nuevas

Lo local **ya existe** y no se toca (`LibroComprasVentasService` genera los datos). Falta persistir el *envío*:

- **Tabla nueva `sii_envio_libros`** (espejo reducido de `sii_envio_dte`): `id`, `empresa_id` (con `EmpresaScope`,
  crítico multitenant), `periodo` (AAAA-MM), `tipo_operacion` (COMPRA/VENTA), `tipo_libro` (MENSUAL/ESPECIAL/RECTIFICA),
  `track_id`, `estado`, `xml_path` (o cifrado como el DTE), `respuesta_sii`, timestamps. Modelo
  `App\Domains\Sii\Models\SiiEnvioLibro` con el global scope de empresa.
- **NO** se necesitan campos nuevos en `facturas` ni en `sii_dte_emitido`: el libro se *deriva* de ellos.
- Migración siguiendo las convenciones del repo (no sembrar catálogos en la migración — ver CLAUDE.md).

### Endpoints / rutas nuevas (en `app/Domains/Sii/Routes/api.php`, prefix `api/sii`)

- `POST api/sii/libros/{tipo}/{mes}/{anio}/enviar` — encola el envío (`tipo` ∈ compras|ventas). Middleware
  `permiso:sii.libro.enviar` (permiso nuevo) + `throttle:sii-uploads-pesados`.
- `GET  api/sii/libros/{tipo}/{mes}/{anio}/estado` — estado del último envío. `permiso:sii.libro.ver`.
- `GET  api/sii/libros` — historial de envíos de la empresa. `permiso:sii.libro.ver`.

El envío debe ir por cola (`onQueue('sii')`, ver `docs/COLAS.md`) con un `EnviarLibroSiiJob`, no síncrono en el
request (el `DTEUpload` puede tardar). Frontend: extender `Frontend/src/Modulos/Tributario/Vistas/LibroComprasVentas.jsx`
con un botón "Enviar al SII" + estado del track_id, junto a los botones de descarga actuales.

### Riesgos y huecos de información

- **[BLOQUEANTE de negocio]** La obligación de envío del IECV **no existe** (Res. 61/2017). Construir Opción B
  entrega valor solo para certificación; el valor operativo real está en Opción A (consultar el RCV). Decidir alcance
  **antes** de codificar.
- **[NO CONFIRMADO — endpoint RCV]** El WS/endpoint de *consulta* del RCV (Opción A) no se identificó con fuente
  pública confiable en esta investigación. Hay que revisar la documentación del SII de "Registro de Compras y Ventas"
  y APIs de terceros (BaseAPI, SimpleAPI, LibreDTE) o pedir el spec al SII. Sin esto, la Opción A no es estimable.
- **[NO CONFIRMADO — XSD exacto]** `LibroCV_v10.xsd` se citó desde espejos de GitHub, no desde el XSD oficial leído
  campo por campo (el tutorial de SimpleAPI devolvió HTTP 500). Descargar y validar los 3 XSD oficiales antes de armar
  el builder.
- **[NO CONFIRMADO — FchResol/NroResol]** La Caratula exige fecha y número de la resolución que autorizó al emisor
  como facturador electrónico. Verificar si ese dato ya está en `configuracion_sii` / `SiiConfiguracion` de la empresa;
  si no, es un campo nuevo a capturar.
- **[NO CONFIRMADO — plazo/sanción]** Día exacto del mes y sanciones del régimen IECV histórico no se validaron con
  fuente oficial vigente (irrelevante si se descarta el envío a producción).
- **Certificación cruzada:** si se hace Opción B, coordinar con el proceso de certificación real del SII (ambiente
  `maullin`), que no se puede probar sin una empresa postulante activa.

### Estimación de esfuerzo por etapa

Referencia: día = jornada de desarrollo enfocada.

- **E0 — Decisión de alcance + descarga/validación de XSD oficiales:** 0.5–1 día (incluye conseguir `LibroCV_v10.xsd`
  y confirmar FchResol/NroResol en la config de empresa).
- **E1 — `LibroCvXmlBuilder` + `LibroCvXsdValidator` + tests contra XSD:** 2–3 días.
- **E2 — Firma (`LibroCvSigner` reutilizando `XmlDsigSigner`) + tests de integridad:** 1 día.
- **E3 — `SiiLibroUploadService`/reuso de `SiiUploadService` + `EnvioLibroService` + job + tabla/modelo
  `sii_envio_libros` con `EmpresaScope`:** 2 días.
- **E4 — Rutas + permisos + controller + UI (botón "Enviar al SII" + estado):** 1.5 días.
- **E5 — Prueba end-to-end contra `maullin` (certificación):** 1 día (depende de tener empresa postulante).
- **Total Opción B:** ~8–9.5 días.
- **Opción A (RCV) — spike previo obligatorio:** 1–2 días solo para confirmar endpoint/spec del RCV antes de estimar
  el resto; sin ese spike no es estimable.

### Orden de implementación sugerido

1. **E0 primero, y parar ahí para decisión de negocio.** No escribir builder/servicios hasta resolver
   Opción A vs B vs C. Actualizar el artifact de bitácora de auditoría con este hallazgo (Res. 61/2017 deroga el
   envío IECV) para que el roadmap no lo arrastre como "pendiente P3" sin contexto.
2. Si se elige **B**: E1 → E2 → E3 → E4 → E5, en ese orden (el XML y su validación XSD son el cimiento; el transporte
   reutiliza casi todo lo de DTE y va al final).
3. Si se elige **A**: primero el spike del endpoint RCV; luego se rearma este plan sobre consulta+conciliación, no
   sobre envío.
4. **RCOF: no implementar** (obligación terminada 08/2022). Documentarlo como obsoleto en el roadmap.

---

### Fuentes

- SII — Certificación (menú): https://www.sii.cl/servicios_online/1039-menu_certificacion-1184.html
- SII — Formato XML de documentos electrónicos: https://www.sii.cl/servicios_online/3532-formato_xml-3811.html
- SII — Instrucciones Upload XML de Libros de Compra y Venta (PDF): https://www.sii.cl/declaraciones_juradas/ddjj_3327_3328/intruct_nav_UPLOAD_xml.pdf
- SII — Formato IECV (PDF): https://www.sii.cl/factura_electronica/factura_mercado/formato_iecv.pdf
- SII — Instrucciones construcción set de pruebas (PDF): https://www.sii.cl/servicios_online/docs/inst_set_pruebas.pdf
- SII — ¿El RCV reemplaza los Libros de Compras y Ventas?: https://www.sii.cl/preguntas_frecuentes/factura_electronica/001_003_6979.htm
- SII — Preguntas Frecuentes RCV (PDF): https://www.sii.cl/ayudas/ayudas_por_servicios/rcv_faqs.pdf
- SII — RCOF (FAQ catastro): https://www.sii.cl/preguntas_frecuentes/catastro/001_012_6270.htm
- lenguajedemaquinas — Cómo enviar un DTE o Libro Compra Venta: http://lenguajedemaquinas.blogspot.com/2013/05/como-enviar-un-dte-o-libro-compra-venta.html
- LibroCV_v10.xsd (niclabs/DTE): https://github.com/niclabs/DTE/blob/master/schemas/LibroCV_v10.xsd
- LibroCV_v10.xsd (zetta-biz): https://github.com/zetta-biz/sii-xsd-schemas/blob/main/schemas/LibroCV_v10.xsd
- SimpleAPI — Generación del IECV desde cero (no legible, HTTP 500 al fetch): https://www.simpleapi.cl/Tutoriales/GenerarIECV
- Análisis Res. 61/2017 — SienaCloud: https://www.sienacloud.cl/normativa-65-sii-la-emision-libros-diarios/
- Laudus — Registro de Compras y Ventas del SII: https://laudus.cl/factura-electronica/software-de-facturacion-electronica-nuevo-registro-de-compras-y-ventas-del-sii/
- EasyTax — Cómo funciona el RCV: https://www.easytax.cl/blog/registro-de-compras-y-ventas-como-funciona-en-chile
- Bsale — RCOF pendiente en el SII: https://ayuda.bsale.app/support/solutions/articles/151000005903--c%C3%B3mo-revisar-los-reportes-de-consumo-de-folios-rcof-pendiente-en-el-sii-
- TUU — ¿Qué es el RCOF?: https://help.tuu.cl/temas-de-ayuda/5pKp9Zk7c41cBeKgJEzQRB/%C2%BFqu%C3%A9-es-el-rcof-reporte-de-consumo-de-folios/rhYweXoghVZSapx2td59iS
