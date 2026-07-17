# Consulta automática del RCV del SII — recepción de facturas de compra (add-on de pago)

Continuación de `docs/sii/LCV-IECV-INVESTIGACION-Y-PLAN.md`, que dejó cerrado que **enviar** el IECV al SII es una
obligación derogada (Res. Ex. SII N°61/2017) y que el valor real está en la **Opción A: consultar/conciliar el RCV**
(Registro de Compras y Ventas que el SII arma solo con los DTE). Aquel doc dejó el endpoint de consulta del RCV como
**NO CONFIRMADO** — este documento lo resuelve, y diseña la feature como el negocio la pidió: **recibir automáticamente
las facturas de compra desde el SII**, pero como **add-on de pago con permiso propio**, no como feature gratuita para
todas las empresas.

> **ADVERTENCIA DE ALCANCE (leer antes de estimar):** el SII **no publica un web service oficial** para descargar el
> RCV. Existe una **API JSON interna** (la que consume el propio portal `www4.sii.cl/consdcvinternetui`) que todos los
> proveedores chilenos (SimpleAPI, BaseAPI, OpenFactura, LibreDTE) consumen vía **scraping de sesión autenticada**, no
> vía un contrato estable documentado. Esto es técnicamente viable y es lo que hace toda la industria, pero es un
> endpoint **no soportado oficialmente** que el SII puede cambiar sin aviso. Es un riesgo de mantenimiento permanente,
> no un bug puntual. Ver "Riesgos".

---

## Investigación

### 1. ¿Existe un webservice/API real de consulta del RCV? SOAP legado, REST moderno, o solo portal web

**No hay API pública oficial.** El SII expone el RCV únicamente por el portal web
(`https://www4.sii.cl/consdcvinternetui/`). Ese portal, sin embargo, es una SPA que por debajo llama a una **API JSON
interna** — y esa API es la que todos los proveedores consumen. No es SOAP (como `GetTokenFromSeed`/`DTEUpload` del DTE)
ni una REST documentada tipo la de Boleta; es un **facade JSON interno** pensado para el front del propio SII.

- **Confirmado explícitamente por SimpleAPI:** *"El SII no dispone de web services para estos fines, por lo que todas
  las operaciones se realizan utilizando técnicas de scraping"*
  ([SimpleAPI — SimpleRCV](https://www.simpleapi.cl/Productos/SimpleRCV)). BaseAPI, OpenFactura (endpoint
  `registry/sync-rcv`, incorporado 28/05/2026), LibreDTE/API Gateway ("sincronización con el RCV del SII") y APIs menores
  (apisii.digital, apipyme.cl) ofrecen exactamente lo mismo: un wrapper de pago sobre ese scraping. Ninguno expone el
  RCV "gratis" porque el costo real es mantener el scraper vivo.

- **Endpoint interno real** (el que hay que replicar si NO se terceriza):
  - Base: `https://www4.sii.cl/consdcvinternetui/services/data/facadeService/`
  - Métodos (POST): `getResumen` (totales por tipo de doc), `getDetalleCompra`, `getDetalleVenta`,
    `getDetalleCompraExport`.
  - Namespace de la petición: `cl.sii.sdi.lob.diii.consdcv.data.api.interfaces.FacadeService/<metodo>`.
  - Fuentes: [lenguajedemaquinas — Recuperación Información RCV
    (2018)](https://lenguajedemaquinas.blogspot.com/2018/03/recuperacion-informacion-registro.html),
    [foro con namespace `getDetalleCompra`](https://www.simpleapi.cl/Productos/SimpleRCV),
    [sergioocode/Sii.RegistroCompraVenta (wrapper por scraping + certificado)](https://github.com/sergioocode/Sii.RegistroCompraVenta),
    [FacTronica/DescargarRCV](https://github.com/FacTronica/DescargarRCV).

> **NO CONFIRMADO — contrato exacto del facade:** la estructura precisa del `body` (`metaData.namespace`,
> `conversationId`, `transactionId`, `data.ptributario` en formato `AAAAMM`, `operacion`, `estadoContab`, `codTipoDoc`,
> y campos anti-bot `accionRecaptcha`/`tokenRecaptcha`) se reconstruyó de un blog de 2018 y wrappers de terceros, **no de
> documentación oficial del SII** (no existe). Puede haber cambiado — sobre todo la parte de reCAPTCHA, que es señal de
> que el SII activamente dificulta el scraping. Antes de implementar hay que capturar el tráfico real del portal actual
> (DevTools → Network) con una cuenta de prueba y validar campo por campo. **Esto es un spike obligatorio.**

### 2. ¿Qué requiere para autenticar? (clave tributaria, certificado, token de sesión)

El facade interno exige una **sesión autenticada del portal SII**, materializada en cookies
(`TOKEN`, `RUT_NS`, `DV_NS`, `CSESSIONID`). Para obtener esa sesión el SII acepta tres vías
([SII — Autenticación consdcvinternetui](https://www4.sii.cl/consdcvinternetui/),
[SII — Clave Tributaria](https://www.sii.cl/destacados/clave_tributaria/)):

1. **Clave Tributaria** (RUT + clave del SII) — la vía directa que usan la mayoría de los scrapers.
2. **ClaveÚnica** (identidad estatal).
3. **Certificado digital** en trámites avanzados.

**Diferencia crítica con el flujo DTE que ya tenemos:** el ERP hoy **no** guarda la clave tributaria de la empresa; para
DTE usa el **certificado digital** (`.pfx`) para firmar la semilla y pedir el token SOAP (`SiiSeedService` →
`SiiTokenService`). El token del DTE **no sirve** para el facade del RCV — es otro dominio de autenticación (portal web
vs WS de firma). Grep confirma que en `app/Domains/Sii` no existe hoy ningún `clave_tributaria`/`clave_sii` almacenado.

> **NO CONFIRMADO — cómo obtienen la sesión los terceros:** SimpleAPI/BaseAPI piden clave tributaria; sergioocode
> reporta autenticar "usando un certificado digital". No pudimos confirmar si el login al portal `consdcvinternetui` con
> **certificado** basta para levantar la sesión del facade sin clave tributaria. Si basta, **reutilizamos el certificado
> que la empresa ya subió** y evitamos capturar una credencial nueva. Si no basta, hay que **capturar y cifrar la clave
> tributaria** de la empresa — credencial sensible nueva, con todas las implicancias de seguridad. **Resolver esto en el
> spike es lo que más mueve el diseño y el esfuerzo.**

### 3. ¿Qué trae el RCV de compras y se puede distinguir lo no registrado localmente?

El detalle de compras (`getDetalleCompra`) trae, por documento: **tipo de DTE** (33 factura, 34 exenta, 61 NC, 56 ND,
etc.), **folio**, **RUT emisor** (+ razón social), **fecha de emisión/recepción**, **montos** (neto, exento, IVA,
total), y el **estado contable / de aceptación**. El SII segmenta el RCV de compras en estados —
`REGISTRO` (aceptado, con derecho a crédito fiscal), `PENDIENTE` (dentro de los 8 días para aceptar/reclamar),
`NO_INCLUIR`, y reclamados — que en el facade viajan como el parámetro/columna `estadoContab`
([SII — Registro de Compras y Ventas](https://www.sii.cl/servicios_online/1039-3256.html)).

**Diferenciar "lo que el cliente aún no tiene cargado localmente" es exactamente el caso de uso** y es directo: el RCV es
la **fuente de verdad del SII**; se cruza cada línea contra las `facturas` tipo COMPRA locales por la llave natural
**(RUT emisor + tipo DTE + folio)**. Lo que está en el RCV y no en `facturas` = documento recibido que el cliente no ha
ingresado (el dolor que el negocio quiere resolver). Lo que está local y no en el RCV = posible error de captura o DTE
aún no propagado.

> **NO CONFIRMADO — nombres exactos de campos JSON:** la lista de campos de arriba está descrita en prosa por los
> proveedores ("la misma información que el portal"), no leída de un JSON de respuesta real. Los nombres exactos de las
> claves (p.ej. `dhdrTipoDoc`, `dhdrFolio`, `dhdrMntTotal`…) se confirman en el spike capturando una respuesta real.

### 4. Legal: ¿se puede automatizar la lectura del RCV propio de la empresa cliente?

Sí, y es el **mismo patrón legal que ya usamos para DTE**: la empresa cliente **autoriza a Tenri a operar en su nombre
ante el SII** con sus propias credenciales/certificado, sobre **su propio RCV**. No es acceso a datos de un tercero: es
la empresa consultando lo suyo, delegando la operación técnica en su software contable (uso normal y esperado de un ERP).

Matices a dejar por escrito:

- **Es la propia empresa consultando su RCV**, no Tenri consultando el de un ajeno. El aislamiento multitenant garantiza
  que la empresa X solo ve su RCV (ver Riesgos).
- **Términos de uso del SII vs scraping:** el SII no ofrece API oficial y su portal está para uso humano; automatizar su
  consumo es una zona gris que toda la industria (Nubox, Bsale, LibreDTE, SimpleAPI) habita sin sanción conocida, pero
  **no hay una autorización explícita del SII**. Riesgo reputacional/operativo, no una ilegalidad clara.
  **NO CONFIRMADO** que exista norma que lo prohíba o lo permita expresamente.
- **Custodia de credenciales:** si el diseño termina requiriendo la clave tributaria (ver §2), Tenri pasa a custodiar la
  credencial maestra del SII de la empresa — cifrado en reposo obligatorio y una decisión de negocio/legal explícita
  sobre asumir esa responsabilidad. Con certificado el riesgo es equivalente al que ya asumimos para DTE.

### 5. "GetDTE" (portal BCN) como proveedor a tercerizar — ¿existe y sirve para el RCV?

El dueño mencionó un servicio "GetDTE" del "portal BCN". **Existe, pero no es lo que se necesita, y el nombre está compartido por dos proveedores distintos** (posible fuente de confusión):

1. **GetDTE de BCN Consultores** (`getdte.cl` — este es el "portal BCN" al que se refiere el dueño; BCN Consultores es una empresa chilena fundada en 2001, **no** la Biblioteca del Congreso Nacional). Es un **sistema de facturación electrónica licenciado** (software de emisión/recepción de DTE, facturación masiva "batch" y uno a uno, portal web, exportación a Excel, licencia indefinida). Los subdominios operativos son de tenants (`bcn.getdte.cl`, `aggreko.getdte.cl`, etc.), lo que confirma que es una plataforma multi-empresa de emisión, no una API pública.
   ([comparasoftware — BCN Consultores](https://www.comparasoftware.cl/bcn-consultores), [getdte.cl](https://bcn.getdte.cl/)).
2. **GetDTE de Treming** (`getdte.com`) — otra plataforma de facturación electrónica chilena (Treming, desde 2010), portal de emisión + una API para integrarse desde el sistema del cliente. También **orientada a emisión**, no a consulta del RCV.
   ([getdte.com](https://getdte.com/)).

**Veredicto:** ninguno de los dos "GetDTE" es una **API documentada de consulta del RCV** comparable a SimpleRCV / BaseAPI / ApiPyme / OpenFactura. Son plataformas de **emisión/recepción de DTE** (generar y timbrar documentos propios), no wrappers del facade `consdcvinternetui` que descarguen el Registro de Compras y Ventas consolidado del SII. La "recepción" que publicitan es recibir los DTE que un proveedor te emite (buzón DTE), **no** consultar el RCV oficial del SII con su segmentación `REGISTRO`/`PENDIENTE`. BCN Consultores no publica documentación técnica ni precios (todo "bajo cotización" / contacto comercial), así que ni siquiera sirve como backend REST tercerizable a la manera de SimpleAPI.

> **Corrección del malentendido (evidencia, no forzar un match):** "GetDTE" **no** es un proveedor de consulta del RCV a tercerizar; es una plataforma de emisión de DTE (de BCN Consultores o de Treming). No cambia la lista de proveedores reales para *esta* feature — los candidatos de consulta del RCV siguen siendo **SimpleAPI (SimpleRCV), BaseAPI, OpenFactura y ApiPyme/LibreDTE**, que sí envuelven el facade por scraping. Descartar GetDTE como opción para el add-on RCV.

### 6. Legalidad de custodiar clave tributaria vs certificado (Ley 21.719 / Ley 19.799 / advertencias SII)

El dueño decidió **usar el certificado digital que Tenri ya custodia para DTE**, no pedir la clave tributaria, por ser menos invasivo. La investigación **confirma que esa decisión también es la más defendible legalmente**, no solo la menos invasiva:

- **Ley 21.719 (protección de datos, vigencia plena 1-dic-2026):** su catálogo de "datos sensibles" (salud, biometría, origen racial/étnico, afiliación sindical/política, convicciones, vida/orientación sexual, y en Chile además la **situación socioeconómica**) **NO incluye la clave tributaria como tal** — una credencial no es un "dato sensible" en el sentido del art. 2. **PERO** la clave tributaria es la **llave de acceso** a información que sí es sensible o de alto riesgo (situación socioeconómica del contribuyente, y datos en TGR/Dirección del Trabajo vía interoperabilidad). Tratar/almacenar la clave arrastra el deber de seguridad del art. sobre medidas técnicas y el consentimiento libre e informado; las sanciones llegan a **20.000 UTM (o 4% de ingresos anuales), 40.000 UTM en reincidencia**. **NO CONFIRMADO** que exista una prohibición expresa de que un tercero la custodie, pero el marco impone cifrado en reposo + consentimiento explícito como piso.
  ([Ley Chile — Ley 21.719 (BCN)](https://www.bcn.cl/leychile/navegar?idNorma=1209272), [Datos sensibles bajo la Ley 21.719 — Alayia](https://alayiatrust.com/blog/datos-sensibles-ley-21719)).
- **El propio SII desaconseja explícitamente compartir la clave tributaria con terceros.** En su llamado de 2022 ("SII llama a los contribuyentes a resguardar la privacidad de su Clave Tributaria") advierte que entregar la autorización de uso "otorga acceso a **toda** su información personal y tributaria" (SII + organismos con convenio: TGR, Dirtrab), y en comunicación posterior es aún más directo: *"El uso de tu Clave Tributaria es tu responsabilidad. Compartirla con terceros tiene **riesgos legales y tributarios**"*. No hay API oficial ni endoso del SII para que un software de gestión la custodie: la responsabilidad recae en el contribuyente.
  ([SII — resguardar la Clave Tributaria (2022)](https://www.sii.cl/noticias/2022/300822noti01rp.htm), [SII en X — riesgos de compartir la clave](https://x.com/SII_Chile/status/1971653554827088201), [SII — Clave Tributaria](https://www.sii.cl/destacados/clave_tributaria/)).
- **El certificado digital SÍ tiene un marco legal claro y purpose-built (Ley 19.799):** los actos suscritos con firma electrónica avanzada "son válidos de la misma manera y producen los mismos efectos que los celebrados por escrito y en soporte de papel", y la FEA es **legalmente equivalente a la firma manuscrita**. El certificado está diseñado precisamente para **actuar/representar con validez jurídica**, que es exactamente el acto de operar ante el SII en nombre de la empresa. Además Tenri **ya lo custodia hoy para DTE** con consentimiento del cliente: usarlo para el RCV **no incorpora una credencial nueva ni un riesgo nuevo** — reutiliza uno ya aceptado.
  ([Ley Chile — Ley 19.799 (BCN)](https://www.bcn.cl/leychile/navegar?idNorma=196640), [digital.gob.cl — Ley 19.799](https://digital.gob.cl/biblioteca/regulacion/ley-19799-sobre-documentos-electronicos-firma-electronica-y-servicios-de-certificacion-de-dicha-firma/)).

**Veredicto legal (confirmado):** el **certificado es preferible al clave tributaria por dos razones, no una** — (a) es menos invasivo (lo que dijo el dueño), y (b) tiene un marco legal más limpio: la Ley 19.799 le da validez jurídica expresa para actuar en representación, mientras que la clave tributaria carga la advertencia expresa del SII de "riesgos legales y tributarios" al compartirla y el deber reforzado de la Ley 21.719 por ser llave a datos socioeconómicos. **Decisión del dueño ratificada por la evidencia.**

> **El punto técnico crítico SIGUE sin resolverse por búsqueda web (§2):** que el login por **certificado** al portal `consdcvinternetui` **baste para levantar la sesión del facade JSON sin clave tributaria** no se pudo confirmar con ninguna fuente pública en esta ronda (sergioocode reporta autenticar "con certificado digital", pero no documenta si eso arma la sesión del facade RCV sin clave). **Esto NO se resuelve con más búsqueda: se resuelve únicamente con el spike técnico** (probar el login por certificado contra el ambiente real de certificación del SII y capturar las cookies de sesión). Si el certificado **no** basta, la única alternativa vuelve a ser custodiar la clave tributaria — con todo el peso legal de arriba — o tercerizar en un proveedor. Ese es el hecho que más mueve el diseño y debe cerrarse en E0.

### Resumen de la investigación

| Ítem | Veredicto | Confianza |
|------|-----------|-----------|
| API oficial del SII para RCV | **No existe** | Confirmado (SimpleAPI + ausencia en sii.cl) |
| Endpoint real | Facade JSON interno `consdcvinternetui/services/data/facadeService/` vía scraping | Alta (endpoint), media (contrato exacto) |
| SOAP/REST/portal | Ni SOAP ni REST oficial; JSON interno de la SPA del portal | Confirmado |
| Autenticación | Sesión del portal (cookies) por clave tributaria / ClaveÚnica / certificado | Confirmado la vía; **NO CONFIRMADO** si el certificado basta |
| Datos de compras | tipo DTE, folio, RUT emisor, montos, `estadoContab` | Alta (semántica), **NO CONFIRMADO** (nombres JSON) |
| Distinguir no-registrado local | Cruce por (RUT emisor + tipo DTE + folio) | Confirmado (diseño) |
| Legalidad | Empresa consulta su propio RCV, mismo patrón que DTE; scraping en zona gris | Media |
| reCAPTCHA en el facade | Presente en fuentes; el SII dificulta activamente el scraping | Media — validar en spike |
| "GetDTE" (portal BCN) | Plataforma de **emisión** de DTE (BCN Consultores / Treming), **no** API de consulta del RCV | Confirmado — descartado para esta feature |
| Certificado vs clave tributaria (legal) | Certificado preferible: Ley 19.799 le da validez jurídica; SII desaconseja compartir la clave y Ley 21.719 la refuerza | Confirmado (marco legal); **NO CONFIRMADO** si el certificado basta técnicamente para el facade |

**Recomendación técnica sobre "propio vs tercerizado":** dado que (a) no hay API oficial, (b) el facade lleva
reCAPTCHA y el SII endurece el scraping, y (c) mantener un scraper propio es costo permanente, **evaluar seriamente
apoyarse en un proveedor (SimpleAPI/BaseAPI/OpenFactura) como backend de la consulta** en la v1, en vez de scrapear
directo. El add-on de pago de Tenri puede envolver un proveedor y trasladar su costo por request al precio del add-on.
Scraping propio solo si el volumen justifica internalizarlo. **Esta es una decisión de negocio/costos, no técnica pura —
señalada en el Plan de acción.**

---

## Diseño del add-on de pago

### Patrones existentes que se reutilizan (no inventar nada nuevo)

Investigación del repo (ERP `Backend-laravel/app/Domains/Core` + web `Tenri-Web-Page/backend`, middleware, `ModuloPermisos`):

- **El mecanismo de entitlement company-wide YA EXISTE y hay que reusarlo — no inventar una tabla `empresa_addons` nueva.**
  Revisado el código real de ambos repos:
  - El ERP ya expone `PUT /api/internal/web/empresas/{id}/plan`
    (`AdminEmpresasController::cambiarPlan`, `app/Domains/Core/Controllers/Internal/AdminEmpresasController.php` ~línea 245)
    que actualiza `module_keys` de **TODOS los usuarios de esa empresa** (los que la tienen como "hogar" y los que la
    tienen como `empresa_activa_id`) en **una sola transacción atómica**. Eso ya ES el "qué módulos tiene contratados
    esta empresa" a nivel de empresa.
  - En la web, `ErpClient::cambiarPlanEmpresa($id, $planSlug, $moduleKeys)`
    (`Tenri-Web-Page/backend/app/Domain/Erp/Services/ErpClient.php`) es el método que ya llama a ese endpoint, y
    `AdminErpPlansController::ALL_MODULES` (~línea 14) es el **catálogo maestro** de módulos asignables, que **ya** tiene
    una categoría "Enterprise" con módulos premium/opcionales (`integraciones.api`, `white_label`, `modulos.custom`).
    O sea: **el patrón de "módulo premium opcional dentro del mismo catálogo ya existe** — un add-on es un módulo más, no
    una entidad de otro tipo.
  - `ModuloPermisos::permisosUsuario()` ya usa `module_keys` como **techo** de los permisos efectivos: cualquier módulo
    fuera de `MODULOS_SIEMPRE_DISPONIBLES` queda gateado por estar o no en `module_keys` — que ya es el entitlement de pago.
  - **Conclusión:** el gating de pago del add-on se reduce a **un solo eje** (permiso gateado por `module_keys`), no a dos.
    Se elimina del diseño toda la maquinaria `empresa_addons` / `EmpresaAddon` / `sync-addons` / `EnsureEmpresaHasAddon`
    (ver diseño simplificado abajo).
- **El modelo de plan/suscripción real es:** tenri.cl es la fuente de verdad (SSO order-based). El provisioning
  (`WebProvisioningController::provisionUser`/`syncPlan` → `ProvisionUserService`) entrega por usuario un `plan_slug` +
  un array `module_keys[]`. Esos `module_keys` se guardan en `usuarios` y actúan como **techo del plan**:
  `ModuloPermisos::permisosUsuario()` limita los permisos efectivos a los que conceden los módulos del plan
  (`array_intersect($base, $permisosModulos)`), con una lista chica `MODULOS_SIEMPRE_DISPONIBLES` que escapa al techo.
- **RBAC:** `permiso:<clave>` (`EnsureUserHasPermission`) pasa si el usuario tiene AL MENOS UNO de los permisos; los
  permisos se derivan de `ModuloPermisos::MAP` (módulo → lista de permisos). Cache 5 min por usuario+empresa.
- **Gating de suscripción:** `CheckSubscription` (activa/inactiva contra la web) y `EnsureSubscriptionWritable`
  (bloquea escrituras si `subscription_status ∈ {read_only, expired}`). Son middlewares **separados** del RBAC — el
  proyecto ya mantiene "estado de suscripción" y "permiso de rol" como dos ejes distintos. **Seguimos esa separación.**

### Cómo se modela "la Empresa X tiene contratado el add-on RCV" — como un módulo más (un solo eje)

**Decisión de diseño (revisada tras leer el código real de la web y el ERP): el add-on RCV NO es una entidad nueva; es
un módulo más del catálogo existente, gateado por `module_keys` — el mismo mecanismo company-wide que ya usa todo módulo
premium.** No hay tabla `empresa_addons`, ni modelo `EmpresaAddon`, ni endpoint `sync-addons`, ni middleware
`EnsureEmpresaHasAddon`. Todo eso se elimina del diseño: era reinventar un mecanismo que ya existe.

Racional (evidencia en el código, ver "Patrones existentes"):

- El entitlement **por empresa** ya se materializa en `module_keys` de todos los usuarios de la empresa, actualizado
  atómicamente por `AdminEmpresasController::cambiarPlan` (ERP) vía `ErpClient::cambiarPlanEmpresa` (web). Es exactamente
  "qué contrató esta empresa", a nivel de empresa, ya resuelto.
- `ModuloPermisos::permisosUsuario()` ya usa `module_keys` como **techo**: un permiso cuyo módulo no está en
  `module_keys` (y no está en `MODULOS_SIEMPRE_DISPONIBLES`) simplemente no se concede. **El "no lo contrataste → no
  puedes" ya está implementado**; solo hay que colgar los permisos del RCV de un módulo que quede fuera del set siempre-disponible.
- El catálogo web (`AdminErpPlansController::ALL_MODULES`) ya tiene módulos premium/opcionales (categoría "Enterprise");
  agregar el RCV ahí es el patrón establecido, no una excepción.

**Cambios concretos (mínimos):**

1. **Web (`Tenri-Web-Page/backend`):** agregar el/los módulo(s) RCV a `AdminErpPlansController::ALL_MODULES`, en una
   categoría nueva (p.ej. **"Automatización SII"**), como opcional/premium — al estilo de la categoría "Enterprise" ya
   existente. Con eso el add-on se puede asignar a una empresa desde el panel de planes y viaja en `module_keys` por el
   flujo `cambiarPlanEmpresa` que ya existe.
2. **ERP (`Backend-laravel`):** agregar el módulo `sii.rcv` a `ModuloPermisos::MAP` con sus permisos, **fuera** de
   `MODULOS_SIEMPRE_DISPONIBLES`:
   ```
   // ModuloPermisos::MAP
   'sii.rcv' => ['sii.rcv.ver', 'sii.rcv.sincronizar', 'sii.rcv.conciliar'],
   // ModuloPermisos::META
   'sii.rcv' => ['Recepción de compras (RCV)', 'SII'],
   ```
   Al no estar en `MODULOS_SIEMPRE_DISPONIBLES`, estos permisos solo se conceden si `sii.rcv` está en los `module_keys`
   de la empresa → **el gating de pago queda hecho por el mismo eje que el RBAC**, sin middleware extra.

**El gating pasa a ser un solo eje, no dos.** No hace falta un `addon:` separado del `permiso:`: el permiso `sii.rcv.*`
YA está gateado por `module_keys`, que YA es el entitlement de pago. Las rutas llevan solo `permiso:sii.rcv.*`. Un
usuario en una empresa que no contrató el add-on no tiene el permiso (porque el módulo no está en su `module_keys`), y
recibe el 403 estándar de permiso. `CheckSubscription`/`EnsureSubscriptionWritable` siguen aplicando encima
(suscripción vencida → solo lectura) sin cambios.

> **NO CONFIRMADO — unir `module_keys` de varios Services simultáneos (trabajo real en `Tenri-Web-Page`):** hoy un
> `Service` (plan) tiene su `module_keys` **fijo** y `ProvisionErpUserData` provisiona con
> `moduleKeys: $service->module_keys` de **un** service. **No existe lógica que UNA los `module_keys` de varios Services
> activos del mismo cliente** (p.ej. plan base + un Service "add-on RCV" comprado como orden aparte). Si el negocio quiere
> vender el add-on como **orden/Service separada** del plan base (y no como upgrade del plan a un `plan_slug` que ya lo
> incluya), hay que **agregar esa lógica de unión de `module_keys` en el lado web** antes de que el `cambiarPlanEmpresa`
> combinado funcione. Esto es trabajo pendiente **en `Tenri-Web-Page`, no en el ERP** — requiere revisión del flujo de
> checkout/Order de la web (ver estimación E1 y "qué queda bloqueado"). Si en cambio el add-on se vende como un
> `plan_slug` premium que ya trae `sii.rcv` en su `module_keys`, este hueco no aplica y el add-on funciona sin tocar la
> lógica de unión.

### Flujo: ¿crear automático o bandeja de revisión?

**Recomendación firme: bandeja de revisión, NO creación automática de `facturas` reales.** Justificación (esto toca
contabilidad e IVA crédito fiscal reales):

- Una `factura` COMPRA creada dispara asiento contable, afecta el F29 y el crédito fiscal. Crear eso sin ojo humano, a
  partir de un scraping no oficial que puede traer datos parciales o duplicados, es exactamente la clase de error que en
  contabilidad es caro de revertir.
- El propio SII da 8 días para aceptar/reclamar: hay un paso de decisión humana inherente al proceso; la UI debe
  acompañarlo, no saltárselo.
- El estado `PENDIENTE` vs `REGISTRO` del RCV importa para el crédito fiscal — no todo lo que aparece debe contabilizarse
  ya.

**Flujo propuesto:**

1. **Sincronización** (`sii.rcv.sincronizar`): job por cola (`onQueue('sii')`, ver `docs/COLAS.md`) que consulta el RCV
   de compras del período y **upsert** en una tabla staging `sii_rcv_compras` (no en `facturas`), por
   `(empresa_id, rut_emisor, tipo_dte, folio)` — idempotente, correr dos veces no duplica.
2. **Conciliación** (`sii.rcv.conciliar`): cada línea staging se marca `conciliado` (existe en `facturas`),
   `faltante` (en RCV, no local) o `solo_local`. La bandeja muestra los `faltante`.
3. **Confirmación humana:** desde la bandeja, el usuario con permiso crea la `Factura` COMPRA real (pre-llenada con los
   datos del RCV) o la descarta. La creación reusa el flujo de facturas de compra existente del dominio Comercial — el
   RCV solo **propone**, no contabiliza solo.

Un modo "auto-crear como **borrador**" (nunca como factura contabilizada) puede ofrecerse como opción avanzada más
adelante, pero la v1 no lo incluye.

### Servicios / modelos / tablas nuevas (estilo dominio Sii)

Siguiendo el patrón de nombres de `app/Domains/Sii/Services/` (`Ws/`, `Xml/`, `Envio/`, `Integracion/`,
`Polling/`, `Mapping/`):

- `App\Domains\Sii\Services\Rcv\SiiRcvSessionService` — obtiene la sesión autenticada del portal (cookies). Aísla el
  punto sucio (login por certificado o clave tributaria, o llamada al proveedor tercerizado). **Único lugar que toca
  credenciales.**
- `App\Domains\Sii\Services\Rcv\SiiRcvConsultaService` — cliente del facade (`getResumen`/`getDetalleCompra`), parseo
  JSON → DTOs. Espejo funcional de `SiiUploadService` pero de lectura.
- `App\Domains\Sii\Services\Rcv\ConciliarRcvService` — cruza RCV vs `facturas` COMPRA por (RUT+tipo+folio), produce el
  estado de cada línea. Respeta `EmpresaScope`.
- `App\Domains\Sii\Jobs\SincronizarRcvJob` — orquesta sesión → consulta → upsert staging → conciliar, con auditoría
  (`request/response` redactados, sin credenciales en logs) y lock para no solaparse.
- **Modelos/tablas:** `SiiRcvCompra` (staging, tabla `sii_rcv_compras`, `EmpresaScope`). Migración sin seed de catálogo.
  **No hay tabla/modelo de add-on** — el entitlement vive en `module_keys` (ver diseño de gating de un solo eje).
- **Config:** agregar a `config/sii.php` la base del facade por ambiente y, si se terceriza, credenciales del proveedor
  vía `.env` (nunca hardcode).

### Rutas nuevas (en `app/Domains/Sii/Routes/api.php`, prefix `api/sii`)

Todas con **solo el permiso** (`permiso:sii.rcv.*`) — el entitlement de pago ya está gateado dentro del permiso vía
`module_keys` (un solo eje, sin middleware `addon:`):

- `POST api/sii/rcv/{tipo}/{mes}/{anio}/sincronizar` — encola `SincronizarRcvJob` (`tipo` ∈ compras|ventas; v1 solo
  compras). `['permiso:sii.rcv.sincronizar', 'throttle:sii-uploads-pesados']`.
- `GET api/sii/rcv/{tipo}/{mes}/{anio}` — bandeja conciliada del período. `['permiso:sii.rcv.ver']`.
- `POST api/sii/rcv/compras/{id}/crear-factura` — crea la `Factura` COMPRA desde una línea faltante.
  `['permiso:sii.rcv.conciliar']`.
- `POST api/sii/rcv/compras/{id}/descartar` — descarta una línea. `['permiso:sii.rcv.conciliar']`.

Frontend: vista nueva bajo `Frontend/src/Modulos/Sii/` (o Comercial), oculta salvo que `module_keys` expongan `sii.rcv`
(el contexto `Permisos` ya sabe ocultar UI en función de los permisos derivados de `module_keys`; recordar que es
cosmético, la autorización real es backend).

### Riesgos

- **[CRÍTICO — multitenant]** `sii_rcv_compras` **debe** llevar `EmpresaScope` y `empresa_id`; el
  job debe fijar la empresa activa correcta antes de consultar y persistir. Una fuga acá mezclaría facturas de compra de
  una empresa en el RCV de otra — la peor clase de bug del proyecto. El cruce por (RUT+folio) **nunca** debe hacerse
  cross-empresa.
- **[ALTO — duplicados]** El upsert por `(empresa_id, rut_emisor, tipo_dte, folio)` es la defensa contra el job corriendo
  dos veces; sin ese índice único, dos sincronizaciones concurrentes duplican la bandeja. Lock/`WithoutOverlapping` en
  el job.
- **[ALTO — sin certificado/credencial vigente]** Si la empresa no tiene certificado vigente (o no cargó clave
  tributaria, según se resuelva §2), la sesión del portal falla. El job debe degradar con error claro ("no se pudo
  autenticar ante el SII, revisa tu certificado/credenciales"), no romper, y no dejar la bandeja en estado
  inconsistente. Reusar la validación de vigencia que ya tiene `CertificadoService`.
- **[ALTO — endpoint no oficial]** El facade puede cambiar o el reCAPTCHA endurecerse sin aviso; la feature puede
  romperse del lado del SII. Mitiga: aislar todo en `SiiRcvSessionService`/proveedor tercerizado y monitorear fallas.
- **[MEDIO — credencial sensible]** Si se termina guardando la clave tributaria, cifrado en reposo obligatorio y decisión
  legal explícita de custodiarla.
- **[MEDIO — entitlement desincronizado]** Como el gating depende de `module_keys`, si tenri.cl cambia el plan/add-on pero
  el `cambiarPlan` (o el provisioning) no propaga los nuevos `module_keys`, la empresa podría quedar con el permiso
  desactualizado. No es un riesgo nuevo: es el mismo comportamiento que **cualquier** módulo del ERP ya tiene, y se
  mitiga con lo existente (el flujo `AdminEmpresasController::cambiarPlan` es atómico y `CheckSubscription` revalida
  contra la web). No requiere maquinaria propia del add-on.
- **[MEDIO — módulo del plan sin unión de Services]** Si el add-on se vende como orden/Service separada del plan base,
  hoy `ProvisionErpUserData` provisiona con el `module_keys` de **un** Service y no une los de varios Services activos
  (ver "NO CONFIRMADO" en el diseño y estimación E1). Sin esa unión, comprar el add-on aparte podría **sobrescribir** los
  `module_keys` del plan base en vez de sumarse. Mitiga: venderlo como `plan_slug` premium que ya incluya `sii.rcv`, o
  implementar la unión en la web antes de habilitar la venta como orden separada.

### Estimación de esfuerzo por etapa

Referencia: día = jornada de desarrollo enfocada.

- **E0 — Spike obligatorio:** capturar el tráfico real del portal RCV (contrato del facade + reCAPTCHA), y **decidir
  vía de autenticación** (certificado propio vs clave tributaria vs proveedor tercerizado). **1.5–2.5 días.** Sin esto
  el resto no es estimable con confianza.
- **E1 — Entitlement/módulo (reuso, mucho más chico que el diseño anterior):**
  - **ERP:** módulo `sii.rcv` en `ModuloPermisos::MAP`/`META`, fuera de `MODULOS_SIEMPRE_DISPONIBLES`, + tests de que el
    permiso se concede solo con el `module_key`. **~0.5 día.**
  - **Web:** agregar `sii.rcv` a `AdminErpPlansController::ALL_MODULES` (categoría "Automatización SII") para poder
    asignarlo desde el panel de planes. **~0.5 día.**
  - **Web (condicional, solo si el negocio lo vende como orden/Service separada del plan base):** implementar la
    **unión de `module_keys` de Services simultáneos** en el flujo de provisioning
    (`ErpProvisioningService`/`ProvisionErpUserData::fromUserAndService` provisiona hoy por Service individual, sin unir).
    **Requiere revisión del flujo de checkout/Order de `Tenri-Web-Page`**; estimación fundada tras leer
    `OrderService` + `ErpProvisioningService`: **~1.5–2.5 días** (agregar Services ERP a la orden, unir sus `module_keys`
    antes de provisionar, provisionar una sola vez, tests). **Se evita por completo si el add-on se vende como `plan_slug`
    premium que ya incluye `sii.rcv`** (entonces E1 web = solo el catálogo). **Decisión de negocio, ver abajo.**
- **E2 — (absorbido en E1):** ya no hay una etapa RBAC separada; el permiso vive en el mismo cambio de `ModuloPermisos`.
- **E3 — Consulta RCV:** `SiiRcvSessionService` + `SiiRcvConsultaService` + DTOs + tabla staging `sii_rcv_compras` con
  `EmpresaScope` + tests (mock del facade/proveedor). **3–4 días** (más si es scraping propio con reCAPTCHA).
- **E4 — Conciliación + bandeja:** `ConciliarRcvService` + `SincronizarRcvJob` (cola, lock, idempotencia) + rutas +
  controller. **2.5 días.**
- **E5 — Creación de factura desde bandeja + reuso flujo Comercial + UI:** **2.5 días.**
- **E6 — E2E contra SII real (ambiente real, empresa de prueba con RCV):** **1–1.5 días.**
- **Total estimado:** **~12–15 días** si el add-on se vende como `plan_slug` premium (E1 se reduce a ~1 día de catálogo +
  `ModuloPermisos`, sin la unión de Services); **~13.5–17.5 días** si se vende como orden/Service separada (suma la unión
  de `module_keys` en la web). Sigue dominado por E0/E3 (la incógnita del facade). Si se terceriza la consulta en un
  proveedor, E3 baja fuerte (integrar una REST documentada) y el total ronda **~7–10 días**, a cambio de costo por request.
  (El diseño anterior estimaba ~14–17 días **con** la maquinaria `empresa_addons`/`sync-addons` ahora eliminada — el
  ahorro real está en no construir ese modelo de add-on.)

### Orden de implementación sugerido

1. **E0 primero, y parar para decisiones de negocio.** No escribir servicios hasta resolver: (a) contrato del
   facade + auth (¿el certificado basta?, §2/§6), (b) scraping propio vs proveedor tercerizado, (c) cómo se vende el
   add-on en tenri.cl (`plan_slug` premium vs orden/Service separada, que decide si hace falta la unión de `module_keys`).
   Actualizar el artifact de bitácora de auditoría con el hallazgo (no hay API oficial; scraping de facade).
2. **E1** (agregar el módulo `sii.rcv` al catálogo web + `ModuloPermisos` del ERP) es independiente del transporte y
   desbloquea el gating; la unión de `module_keys` en la web solo si se vende como orden separada.
3. **E3 → E4 → E5** en orden (consulta → conciliación → creación).
4. **E6 al final**, contra empresa de prueba con RCV real.

### Qué queda bloqueado por decisión de negocio (NO decidir acá)

- **Nombre comercial y precio del add-on** — no lo define ingeniería. El slug técnico propuesto es `sii.rcv` (módulo del
  catálogo); el nombre de cara al cliente y el pricing los define producto/negocio.
- **Scraping propio vs proveedor tercerizado (SimpleAPI/BaseAPI/OpenFactura/ApiPyme)** — trade-off costo por request vs
  costo de mantener scraper. Recomendación técnica: **tercerizar en la v1**; decisión final es de negocio/costos.
  (**"GetDTE" queda descartado** como proveedor de esta consulta: es una plataforma de emisión de DTE, no una API de
  consulta del RCV — ver Investigación §5.)
- **Custodiar o no la clave tributaria del cliente** — decisión de riesgo/legal. La investigación §6 **respalda la
  decisión del dueño de usar el certificado** (marco Ley 19.799 + advertencia SII contra compartir la clave); solo si el
  spike concluye que el certificado **no** basta técnicamente para el facade, volver a poner la clave tributaria sobre la
  mesa, con cifrado en reposo y consentimiento explícito por la Ley 21.719.
- **Cómo se vende el add-on en tenri.cl: `plan_slug` premium vs orden/Service separada** — decide si hace falta construir
  la **unión de `module_keys` de Services simultáneos** en `Tenri-Web-Page` (hoy no existe: `ProvisionErpUserData`
  provisiona por Service individual, sin unir). Si se vende como un `plan_slug` que ya incluye `sii.rcv`, no hay trabajo
  extra en la web más allá del catálogo; si se vende como orden aparte del plan base, requiere la lógica de unión
  (E1 condicional, ~1.5–2.5 días en la web). **Requiere revisión del flujo de checkout/Order de `Tenri-Web-Page`.**

---

### Fuentes

- SimpleAPI — SimpleRCV (confirma "el SII no dispone de web services... scraping"): https://www.simpleapi.cl/Productos/SimpleRCV
- SimpleAPI — Documentación: https://documentacion.simpleapi.cl/
- BaseAPI — API RCV del SII (JSON): https://www.baseapi.cl/servicios/rcv
- OpenFactura (Haulmer) — API (endpoint `registry/sync-rcv`): https://docsapi-openfactura.haulmer.com/
- LibreDTE — Nueva API para consultas al SII: https://www.libredte.cl/blog/libredte-3/nueva-api-de-libredte-para-consultas-al-sii-48
- API Gateway — Consultas al SII (Registro de compras y ventas): https://www.apigateway.cl/academy/capacitacion-inicial/consultas-usando-postman/consultas-al-sii
- lenguajedemaquinas — Recuperación Información RCV (endpoint facadeService, 2018): https://lenguajedemaquinas.blogspot.com/2018/03/recuperacion-informacion-registro.html
- sergioocode/Sii.RegistroCompraVenta (wrapper por scraping + certificado): https://github.com/sergioocode/Sii.RegistroCompraVenta
- FacTronica/DescargarRCV: https://github.com/FacTronica/DescargarRCV
- SII — Registro de Compras y Ventas: https://www.sii.cl/servicios_online/1039-3256.html
- SII — Autenticación consdcvinternetui: https://www4.sii.cl/consdcvinternetui/
- SII — Clave Tributaria: https://www.sii.cl/destacados/clave_tributaria/
- apisii.digital — Registro Compra y Venta: https://www.apisii.digital/hub/api/registro-compra-venta-1/
- ApiPyme — API SII Chile (RCV y F29): https://apipyme.cl/
- comparasoftware — BCN Consultores / GetDTE (plataforma de emisión de DTE): https://www.comparasoftware.cl/bcn-consultores
- GetDTE de BCN Consultores (portal, subdominios por tenant): https://bcn.getdte.cl/
- GetDTE de Treming (otra plataforma de emisión homónima): https://getdte.com/
- Ley Chile — Ley 21.719 sobre protección de datos personales (BCN): https://www.bcn.cl/leychile/navegar?idNorma=1209272
- Alayia — Datos sensibles bajo la Ley 21.719: https://alayiatrust.com/blog/datos-sensibles-ley-21719
- Ley Chile — Ley 19.799 sobre documentos electrónicos y firma electrónica (BCN): https://www.bcn.cl/leychile/navegar?idNorma=196640
- digital.gob.cl — Ley 19.799 (regulación): https://digital.gob.cl/biblioteca/regulacion/ley-19799-sobre-documentos-electronicos-firma-electronica-y-servicios-de-certificacion-de-dicha-firma/
- SII — llamado a resguardar la Clave Tributaria (2022): https://www.sii.cl/noticias/2022/300822noti01rp.htm
- SII (X/Twitter) — riesgos legales y tributarios de compartir la Clave Tributaria: https://x.com/SII_Chile/status/1971653554827088201
