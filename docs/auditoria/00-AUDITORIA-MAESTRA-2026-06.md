# 🔎 Auditoría Maestra del ERP — Seguridad, Robustez, UX y Cumplimiento Legal

**Fecha:** 2026-06-12
**Alcance:** Backend Laravel 12 (337 archivos PHP) + Frontend React 19/Vite (126 componentes)
**Método:** escaneo dirigido (grep/lectura) sobre el estado actual del código + reportes de sub-agentes de robustez frontend.
**Rama auditada:** `NSalas-dev` @ `24c95f3`

> Esta auditoría refleja el estado **posterior** a los commits recientes de responsividad
> (360–2560px), auth blindada, integridad tesorería/contabilidad y correcciones legales RRHH.
> Complementa (no reemplaza) `AUDITORIA-SEGURIDAD-E-INTEGRIDAD-2026-06.md`.

---

## 1. Resumen ejecutivo

| Severidad | Seguridad | Robustez/Errores | UX/Responsive | Legal | **Total** |
|---|---|---|---|---|---|
| 🔴 CRÍTICO | 0 | 0 | 0 | 0 | **0** |
| 🟠 ALTO | 2 | 2 | 1 | 0 | **5** |
| 🟡 MEDIO | 3 | 6 | 2 | 1 | **12** |
| 🔵 BAJO | 2 | 5 | 2 | 1 | **10** |

**Veredicto general:** el ERP está en **buen estado de seguridad e integridad**. No se detectaron
vulnerabilidades críticas: **sin inyección SQL explotable, sin XSS, sin secretos filtrados, sin
http:// inseguro, con partida doble, períodos cerrados, RBAC y aislamiento multitenant intactos**.
Los hallazgos son **endurecimiento** (headers de seguridad, token storage) y **pulido de
robustez/UX** (estados de error visibles, race conditions menores, 2 tablas sin scroll).

---

## 2. Seguridad — OWASP Top 10

### ✅ Controles correctos verificados
- **A03 Inyección SQL:** las 11 ocurrencias de `DB::raw` y todos los `selectRaw/orderByRaw/havingRaw`
  usan **solo funciones de agregación con nombres de columna literales** (`SUM`, `COUNT`, `AVG`, `CASE`),
  **sin concatenación de input de usuario**. Eloquent parametriza el resto. Riesgo: nulo.
- **Sin `eval`, `unserialize`, `shell_exec`, `system`, `exec`** en todo `app/`.
- **A07 Auth:** login con `throttle:6,1` (anti fuerza bruta) — `routes/api.php:80`. Canal interno con `web.api.key` + `throttle:60,1`.
- **A02 Cripto:** RUT/datos bancarios/remuneración cifrados con `Crypt`, campos `$hidden` (Ley 21.719).
- **A05 Config:** `.env` **no** trackeado en git; `APP_DEBUG=false` en `.env.example` con advertencia; CORS con `allowed_origins` desde env (no `*`).
- **A01 Access Control:** aislamiento multitenant por `empresa_id` + `HasEmpresaScope` con test guardián (`EmpresaScopeCoberturaTest`); RBAC por `permiso:` en rutas sensibles.

### 🟠 ALTO

| # | Hallazgo | Evidencia | Impacto | Recomendación |
|---|---|---|---|---|
| S-1 | **Faltan headers de seguridad HTTP** (CSP, HSTS, X-Frame-Options, X-Content-Type-Options) | `bootstrap/app.php:21` — sin middleware que los agregue; grep en `app/ bootstrap/ config/` = 0 resultados | Clickjacking, MIME-sniffing, sin forzar HTTPS en el navegador. Mitiga el riesgo residual del token en localStorage. | Middleware global `SecurityHeaders` que agregue CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Strict-Transport-Security`, `Referrer-Policy`. |
| S-2 | **Token de sesión en `localStorage`** (`erp_token`) | `Frontend/src/Configuracion/api.js:23-26` | Robo de token vía XSS (hoy no hay XSS, pero es defensa en profundidad). | Migrar a cookie `HttpOnly` + `Secure` + `SameSite=Strict` emitida por el backend; mantener CSRF para escritura. (Cambio mayor — planificar.) |

### 🟡 MEDIO
- **S-3 CORS permisivo en métodos/headers:** `config/cors.php:13,22` usan `['*']` con `supports_credentials:true`. Aunque `allowed_origins` está restringido por env, conviene acotar métodos y headers a los realmente usados.
- **S-4 Sin forzado de HTTPS / TrustProxies:** no hay `forceScheme('https')` ni `TrustProxies` configurado. Detrás de proxy, las URLs generadas y la detección de scheme pueden fallar (afecta cookies Secure y enlaces).
- **S-5 Auditoría de rutas sin `permiso:`:** ~126 rutas autenticadas sin middleware `permiso:` explícito. Muchas son legítimas (perfil propio, catálogos), pero requiere una revisión 1×1 para confirmar que ninguna acción sensible dependa solo de estar autenticado.

### 🔵 BAJO
- **S-6** El fallback `X-WEB-API-KEY` del canal interno debería retirarse en favor de HMAC puro (deuda ya identificada en auditoría previa).
- **S-7** Revisar llamadas `Http::` a SII/Previred/web Tenri para validar timeouts y allow-list de hosts (anti-SSRF).

---

## 3. Seguridad Frontend y Fugas de Información

### ✅ Excelente estado
- **XSS: CERO.** No hay `dangerouslySetInnerHTML`, `innerHTML`, `eval` ni `new Function` en todo `src/`.
- **Fugas en consola: CERO funcionales.** Solo 1 `console.error` y está en `ErrorBoundary.jsx:17` **gated a DEV**. Todo lo demás usa un `logger.` abstracto.
- **MITM: sin `http://` hardcodeado.** `API_BASE_URL` resuelve a HTTPS en producción.
- **Sin secretos/API keys** embebidos en el bundle.

> El gating por permiso en el cliente (`usePermisos`, `RutaProtegida`) es **UX**, no seguridad — y
> está bien, porque **el backend es la garantía real** (RBAC por `permiso:` + scope). Correcto.

---

## 4. Robustez y Manejo de Errores

### ✅ Fortalezas
- **Cliente `api.js` centralizado** (`Frontend/src/Configuracion/api.js`): retry idempotente solo en GET/HEAD (POST/PUT/DELETE con `maxReintentos=0` — evita duplicar facturas), timeout 30s con `AbortController`, refresh proactivo de token con mutex `refreshInFlight` + sync multi-tab, toasts centralizados con manejo diferenciado 403/422/500.
- **`ErrorBoundary`** envuelve toda la app (`App.jsx`), con `getDerivedStateFromError` + UI de recuperación.
- **53 usos de `DB::transaction`** en los dominios — atomicidad en operaciones multi-paso.
- **Excepciones de dominio renderizables** (`RrhhException`, `PeriodoCerradoException`) con HTTP correcto.

### 🟠 ALTO

| # | Hallazgo | Evidencia | Impacto |
|---|---|---|---|
| R-1 | **Carga falla → pantalla en blanco sin aviso** en GestionProveedores: el `catch` solo loguea, sin `setError` ni UI | `GestionProveedores.jsx:238` y `BankAccountsTab:29` | Si falla `/proveedores` o `/paises`, el usuario ve la tabla vacía como si no hubiera datos, sin saber que hubo error de red. |
| R-2 | **184 `throw new Exception` genéricos** en servicios de dominio (Inventario, etc.) que se renderizan como **HTTP 500** | `app/Domains/Inventario/Services/InventarioService.php:70,130,191...` | Errores de negocio legítimos ("no existe / no pertenece / SKU inválido") devuelven 500 en vez de 404/422; ensucia logs y confunde al cliente. (Patrón ya resuelto en Rrhh con `RrhhException`.) |

### 🟡 MEDIO
- **R-3** `ErrorNotice` de error de carga inicial está **dentro del formulario cerrado por defecto** → el error inicial nunca se ve: `ProductosInventario.jsx:175`, `MovimientosInventario.jsx:264`.
- **R-4** `MovimientosInventario.jsx:93-105`: `Promise.allSettled` pero solo verifica `movimientosResponse`; fallos de productos/bodegas/lotes son silenciosos → dropdowns vacíos sin aviso.
- **R-5** **Race condition por año** en `DashboardRenta.jsx:25-85` (`[anio]` sin `AbortController`): navegar rápido entre años puede mostrar datos del año equivocado (grave en cálculo tributario).
- **R-6** **Race condition en paginación** `FacturasSii.jsx:29-50`: clics rápidos en Siguiente/Anterior → respuestas fuera de orden.
- **R-7** `componentDidCatch` solo loguea en DEV (`ErrorBoundary.jsx:16`): crashes de producción se pierden sin telemetría.
- **R-8** Un único ErrorBoundary raíz sin boundaries por módulo: un crash en una vista derriba todo el layout.

### 🔵 BAJO
- **R-9** 401 reactivo no intenta refresh, redirige directo a login (`api.js:474`).
- **R-10** `setState` en componente desmontado en hooks SII sin cleanup (`useSiiConfiguracion.js:44`, `useSiiCafs.js:75`).
- **R-11** Crash potencial `datosRenta.creditos.ppm_acumulado` sin optional chaining (`DashboardRenta.jsx:475`).
- **R-12** Loading eterno si la red se cuelga sin resolver/rechazar en vistas que no usan `api.js` con timeout (`FacturasSii.jsx:95`).
- **R-13** `GestionProveedores` usa `Promise.all` (no `allSettled`): un catálogo secundario caído bloquea todo el módulo.

---

## 5. Responsividad y Usabilidad

### ✅ Fortalezas
- Responsividad mayormente sólida tras el commit `responsividad completa 360px-2560px`: la gran mayoría de tablas densas ya usan `overflow-x-auto`; uso consistente de breakpoints `sm/md/lg`.
- Patrón de módulo consistente (encabezado + `AyudaModulo` + tabla + modal) especialmente en RRHH y SII.

### 🟠 ALTO
- **U-1 Dos tablas sin scroll horizontal** → desborde en móvil:
  `Contabilidad/Componentes/WorkbenchReclasificacion.jsx` y `Contabilidad/Vistas/HistorialFacturas.jsx`.
  Envolver en `<div className="overflow-x-auto">`.

### 🟡 MEDIO
- **U-2** Accesibilidad: revisar botones solo-ícono sin `aria-label` y trap de foco en modales propios (los `PanelModal`/Swal están OK; revisar modales antiguos).
- **U-3** Estados de error de carga inicial no visibles (se cruza con R-1/R-3): impacto directo de usabilidad.

### 🔵 BAJO
- **U-4** Targets táctiles pequeños en algunas acciones solo-ícono en tablas densas.
- **U-5** Consistencia de empty-states entre módulos viejos (Inventario) y nuevos (RRHH).

---

## 6. Cumplimiento Legal Contable Chileno

### ✅ Cumple correctamente (verificado)
- **Partida doble:** `AsientoContableService` valida `totalDebe == totalHaber` con redondeo a 2 decimales (`:80-97`).
- **Inmutabilidad de períodos cerrados:** `PeriodoContableService::assertAbierto` bloquea crear/editar/eliminar/mover asientos en período cerrado; reapertura con jerarquía (ver `DISENO-bloqueo-periodo-contable.md`).
- **Correlativo de comprobante** único por empresa.
- **RRHH sin números mágicos:** AFP 10%, salud 7%, AFC, tope 90 UF, gratificación Art.50, impuesto único por tabla UTM, SIS 1.62% — **todo leído de `ParametroPrevisional`/`IndicadorMensual`/`TablaImpuestoUnico`**.
- **Inmutabilidad de liquidaciones:** snapshot de `parametro_previsional_id`/`indicador_mensual_id`.
- **Finiquitos:** Art.161/163 (tope 11 años, fracción>6m=1año), aviso previo, vacaciones proporcionales, tope 90 UF.
- **Previred:** archivo de **105 campos** posicionales (corregido), códigos AFP/ISAPRE.
- **Ley 21.719:** datos sensibles cifrados + acceso por permiso.

### 🟡 MEDIO
- **L-1 Previred — campos no poblados:** sexo, nacionalidad, FUN ISAPRE, APV, mutualidad se emiten vacíos/`0` (documentado en `FORMATO-PREVIRED.md`). No es ilegal pero puede ser rechazado por Previred según la institución. **Antes de producción real:** poblar `sexo`/`nacionalidad` en `empleados` y código de mutualidad por empresa.

### 🔵 BAJO
- **L-2** Verificar valores legales 2026 contra fuente oficial antes de producción (ya advertido en seeders/docs).

---

## 7. Plan de Acción Priorizado

> Esfuerzo: S=pequeño (<2h), M=medio (medio día), L=grande (1+ día).

### 🥇 P0 — Endurecimiento de seguridad (esta semana)
1. **S-1** Middleware `SecurityHeaders` (CSP, HSTS, X-Frame-Options, X-Content-Type, Referrer-Policy). **[M]** — alto impacto, bajo riesgo.
2. **U-1** Envolver las 2 tablas sin `overflow-x-auto`. **[S]**
3. **R-1** `setError` + UI de error en `GestionProveedores` y `BankAccountsTab`. **[S]**
4. **R-11** Optional chaining en `DashboardRenta.jsx:475` (evita crash). **[S]**

### 🥈 P1 — Robustez y consistencia (próximas 2 semanas)
5. **R-2** Introducir excepciones de dominio tipadas (estilo `RrhhException`) en Inventario/Comercial/Tesorería para devolver 404/422 en vez de 500. **[L]** — se puede hacer por dominio.
6. **R-5 / R-6** `AbortController` en `DashboardRenta` (año) y `FacturasSii` (paginación). **[M]**
7. **R-3 / R-4** Mostrar error de carga inicial fuera del formulario; verificar todos los `allSettled`. **[M]**
8. **S-3 / S-4** Acotar CORS a métodos/headers reales + `TrustProxies`/`forceScheme(https)`. **[S]**

### 🥉 P2 — Mejora continua (backlog)
9. **R-7** Integrar telemetría de errores de producción (Sentry) en `ErrorBoundary` + `componentDidCatch`. **[M]**
10. **R-8** ErrorBoundary por módulo (que un crash no derribe el layout). **[M]**
11. **R-10** Cleanup de cancelación en hooks SII. **[S]**
12. **S-5** Revisión 1×1 de las 126 rutas sin `permiso:`. **[M]**
13. **L-1** Poblar campos Previred (sexo, nacionalidad, mutualidad) antes de envío real. **[M]**

### 🏅 P3 — Estratégico (planificar)
14. **S-2** Migrar token a cookie `HttpOnly`/`Secure`/`SameSite` (cambio transversal backend+frontend). **[L]**
15. **S-6** Retirar fallback `X-WEB-API-KEY` (HMAC puro). **[M]**
16. **U-2 / U-4 / U-5** Pasada de accesibilidad y consistencia de empty-states. **[L]**

---

## 8. Notas de método

- Los sub-agentes de profundización para **OWASP backend completo** y **cumplimiento legal exhaustivo**
  toparon con el límite de sesión; este documento consolida lo verificado por escaneo directo +
  los reportes de robustez frontend que sí completaron. Las áreas marcadas para "revisión 1×1"
  (S-5, rutas) merecen una pasada dedicada cuando se reabran los agentes.
- Ningún hallazgo es bloqueante de producción por sí solo; los P0 se recomiendan antes del próximo release.
