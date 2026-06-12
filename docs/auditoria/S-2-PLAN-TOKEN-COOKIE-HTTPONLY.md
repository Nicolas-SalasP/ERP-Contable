# S-2 — Migración del token de sesión a cookie HttpOnly

**Estado:** Planificado (no implementado). Documento de diseño para ejecución dedicada.
**Severidad:** 🟠 ALTO (defensa en profundidad). **Tamaño:** L (transversal backend + frontend + coordinación SSO).
**Autor:** auditoría 2026-06. **Última actualización:** 2026-06-12.

---

## 1. Problema

Hoy el token de sesión (Sanctum *personal access token*) se entrega al frontend en
el cuerpo JSON del login/refresh y se guarda en `localStorage`:

- `Frontend/src/Configuracion/api.js` → `getToken()` lee `localStorage.erp_token`
  y lo envía como `Authorization: Bearer`.
- `AuthController::login/tokenLogin/refresh` → `createToken('react-spa-token')->plainTextToken`
  devuelto en JSON.

`localStorage` es legible por cualquier JavaScript que corra en la página. Hoy **no
hay XSS** (verificado: cero `dangerouslySetInnerHTML`/`innerHTML`/`eval`), pero un
único XSS futuro permitiría exfiltrar el token. Una cookie `HttpOnly` no es accesible
desde JS, eliminando ese vector aunque exista un XSS.

## 2. Objetivo

El token (o el identificador de sesión) reside en una cookie `HttpOnly` + `Secure` +
`SameSite=Strict|Lax`, emitida por el backend. El frontend deja de leer/escribir el
token. La protección CSRF se gestiona explícitamente para las mutaciones.

## 3. Alcance — qué se toca

### Backend
1. **Modo de autenticación.** Dos opciones:
   - **(A) Sanctum SPA stateful** (recomendado si front y API comparten dominio
     registrable, p.ej. `erp.tenri.cl` + `erp.tenri.cl/api`): sesión basada en
     cookie + `XSRF-TOKEN`. Requiere `EnsureFrontendRequestsAreStateful`,
     `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, y CORS con `supports_credentials`.
   - **(B) Token en cookie HttpOnly manual:** seguir emitiendo el PAT pero
     setearlo con `cookie()->make(..., httpOnly: true)` en vez de devolverlo en
     JSON; un middleware lo copia de la cookie al header `Authorization` antes de
     `auth:sanctum`. Menos idiomático pero menos disruptivo para el modelo de tokens.
2. **CSRF.** En (A) Laravel ya trae `VerifyCsrfToken` + cookie `XSRF-TOKEN`. En (B)
   hay que añadir verificación CSRF propia para POST/PUT/DELETE (doble submit).
3. **Login/refresh/logout** (`AuthController`): dejar de exponer `token` en el body;
   set/clear de la cookie. `refresh` rota la cookie. `logout` la expira.
4. **CORS** (`config/cors.php`): `supports_credentials: true` (ya está) y origin
   explícito (ya está). Confirmar que no se use `*` con credenciales.
5. **TrustProxies** ya configurado (S-4) → `Secure` se detecta bien tras el proxy.

### Frontend (`api.js` — el archivo más afectado)
1. Quitar `getToken`/`getAuthHeaders`/`localStorage` para el token. Las peticiones
   pasan a `credentials: 'include'` (la cookie viaja sola).
2. **CSRF:** antes de la primera mutación, `GET /sanctum/csrf-cookie` (modo A) y
   enviar el header `X-XSRF-TOKEN` leído de la cookie `XSRF-TOKEN` (esa cookie NO
   es HttpOnly, por diseño).
3. **Refresh proactivo + mutex `refreshInFlight`:** ya no se puede leer
   `erp_token_issued_at` del token. Alternativas: (a) endpoint `/auth/me` que
   indique expiración; (b) refresh reactivo en 401 (ya existe parcialmente). El
   mutex sigue siendo necesario para no disparar N refresh simultáneos.
4. **Sync multi-tab:** el `storage` event sobre `erp_token` desaparece. Con cookie,
   las tabs comparten sesión automáticamente; el logout multi-tab puede resolverse
   con un `BroadcastChannel` o el evento de `visibilitychange` + `/auth/me`.
5. **`clearAuth`:** ya no borra el token (no lo tiene); solo limpia `erp_user`
   (datos no sensibles de UI) y pide `POST /auth/logout`.

### SSO — el punto de coordinación externa (riesgo principal)
`tokenLogin` es el callback del SSO de la **web externa de Tenri**: recibe un token
de un solo uso por redirect y hoy responde con un PAT que el front guarda
(`SsoCallback.jsx`). Con cookies, el backend debe **setear la cookie** en la
respuesta del callback y redirigir, en vez de entregar el token al JS. Esto exige:
- Que el dominio de la cookie cubra el front (`erp.tenri.cl`).
- Revisar `SsoCallback.jsx`: ya no lee `token` de la respuesta; tras el redirect la
  sesión ya está activa vía cookie. Llamar a `/auth/me` para hidratar el usuario.
- **Coordinar con el equipo de la web** el dominio/flujo de redirect. Este paso no
  es verificable en el entorno de desarrollo del ERP de forma aislada.

## 4. Plan de ejecución sugerido (incremental, con rollback)

1. **Backend dual-mode (sin romper):** aceptar auth por cookie HttpOnly **y** por
   Bearer simultáneamente (el middleware copia la cookie al header si está presente;
   si no, usa el Bearer). Emitir AMBOS en login. Feature flag `AUTH_COOKIE_MODE`.
2. **Frontend tras flag:** cuando `AUTH_COOKIE_MODE=on`, `api.js` usa
   `credentials:'include'` + CSRF y deja de leer `localStorage`. Probar login,
   refresh, 401→refresh, multi-tab, logout, y el flujo SSO en staging.
3. **Cortar Bearer:** una vez validado end-to-end (incl. SSO con la web), dejar de
   emitir el token en JSON y eliminar el código de `localStorage`. 
4. **Rollback:** apagar `AUTH_COOKIE_MODE` revierte a Bearer sin redeploy de código.

## 5. Pruebas requeridas

- Backend: login set-cookie HttpOnly/Secure/SameSite; refresh rota cookie; logout la
  expira; CSRF rechaza mutación sin `X-XSRF-TOKEN`; `auth:sanctum` resuelve por cookie.
- Frontend: peticiones con `credentials:'include'`; mutación adjunta CSRF; 401
  dispara un solo refresh (mutex); logout limpia y redirige.
- SSO: callback setea cookie y la sesión queda activa sin token en el JS.
- Regresión: toda la suite actual de auth (`SsoLoginTest`, `SsoSyncTest`,
  `SyncPasswordRevocaTokensTest`) sigue verde.

## 6. Riesgos

| Riesgo | Mitigación |
|---|---|
| Romper login en producción | Dual-mode + feature flag con rollback sin redeploy |
| Romper SSO con la web externa | Coordinar dominio/flujo; validar en staging antes de cortar Bearer |
| CSRF mal configurado bloquea mutaciones | Suite de pruebas de CSRF antes del corte |
| Cookie no viaja (dominio/SameSite) | Verificar `SESSION_DOMAIN`/`SANCTUM_STATEFUL_DOMAINS` en cada entorno |

## 7. Decisión 2026-06-12

Se **difiere** la implementación: es un cambio transversal que toca el flujo SSO con
un sistema externo no verificable desde este entorno. Se ejecuta como tarea dedicada
siguiendo el plan dual-mode de §4 para garantizar rollback seguro.
