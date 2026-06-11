# 🔌 Plan Fase 2 — Integraciones externas (SII · TGR · Previred · Cajas)

**Fecha:** 2026-06-11
**Contexto:** tras P1 (aislamiento multitenant completo), diseñar cómo conectar y monitorear
organismos externos desde el panel de administración, reutilizando el patrón que ya existe.

---

## 1. El ecosistema (confirmado en los 3 repos)

```
Tenri-Admin (SPA React)        Tenri-Web backend = EL HUB (Laravel)          ERP-Contable (Laravel)
   panel de control      ──►   api.tenri.cl                          ──►     multitenant
                               app/Domain/{Erp,Billing,Order,...}            app/Domains/{Sii,...}
                               ErpClient (HMAC) + AdminErp*Controllers       /api/internal/web/* (HMAC)
```

**Lo que YA existe y vamos a reutilizar:**
- **Hub → ERP:** `App\Domain\Erp\Services\ErpClient` firma con `HmacFirma` y llama a
  `/api/internal/web/*` del ERP (`provision-user`, `sync-plan`, `online-users`, `module-catalog`).
- **Admin → Hub:** `AdminErpEmpresasController` / `AdminErpPlansController` exponen al SPA admin
  acciones sobre el ERP (empresas: suspender/activar/cambiar plan; usuarios: bloquear).
- **ERP:** endpoints internos bajo `web.api.key` (HMAC) que ejecutan la acción con datos del tenant.

> **Conclusión:** las integraciones se enchufan con el **mismo patrón**. No inventamos arquitectura;
> la extendemos.

---

## 2. Principio rector: Plano de Control vs Plano de Ejecución

| | **CONTROL** (Admin + Hub) | **EJECUCIÓN** (ERP) |
|---|---|---|
| Responsabilidad | Activar/desactivar por empresa, **monitorear** (salud, uso, último OK/error), billing, ver estado | **Hacer** la operación con datos del tenant: firmar DTE (SII), consultar deuda (TGR), generar archivo Previred, etc. |
| Dónde | `tenri-admin` (UI) + `tenri-web` (orquestación/estado de alto nivel) | `ERP-Contable` (certificados cifrados, CAF, plan de cuentas, nómina) |
| Secretos | **No** guarda credenciales operativas del organismo | **Sí**: certificados/credenciales cifrados en reposo (ya lo hace el SII) |

**Regla de oro:** los **secretos operativos viven solo en el ERP** (cifrados). El hub guarda el
**estado de control** (activa/inactiva, plan, métricas) y orquesta; nunca duplica certificados.

---

## 3. Modelo de datos

### 3.1 En el ERP (fuente de verdad de ejecución)
Tabla genérica `integraciones_empresa` (una fila por empresa × organismo):
| campo | nota |
|---|---|
| `empresa_id` | tenant (con `HasEmpresaScope`) |
| `tipo` | `sii` \| `tgr` \| `previred` \| `caja_X` |
| `estado` | `inactiva` \| `activa` \| `error` \| `requiere_credenciales` |
| `ambiente` | `certificacion` \| `produccion` |
| `credenciales_cifradas` | `Crypt` (reutiliza el patrón SII). **Decisión: credenciales PROPIAS por organismo** (usuario/clave o token por integración), no se comparte el certificado SII |
| `ultimo_ok_at`, `ultimo_error_at`, `ultimo_error` | salud/monitoreo |
| `metadata` | json por organismo |

> SII ya tiene sus tablas (`sii_certificado_empresa`, `sii_caf`, …); esta tabla las **complementa**
> como índice de estado, no las reemplaza.

### 3.2 En el hub (estado de control)
Tabla `erp_integraciones` (espejo liviano para el admin, sin secretos):
`empresa_id_erp`, `tipo`, `habilitada` (bool de negocio/billing), `plan_incluye` (bool), timestamps.
El **estado operativo** (salud) se consulta on-demand al ERP vía `ErpClient` (no se persiste secreto).

---

## 4. Contrato ERP ↔ Hub (nuevos endpoints internos)

En el **ERP**, bajo `web.api.key` (HMAC), nuevo grupo `/api/internal/web/integraciones`:
- `GET  /{empresaId}` → lista de integraciones de la empresa con estado/salud.
- `POST /{empresaId}/{tipo}/activar` → habilita (valida prerequisitos, p. ej. certificado SII cargado).
- `POST /{empresaId}/{tipo}/desactivar`.
- `GET  /{empresaId}/{tipo}/salud` → ping/estado del organismo (último OK/error, folios disponibles, etc.).

En el **hub**, extender `ErpClient` con `integraciones(empresaId)`, `activarIntegracion(...)`,
`saludIntegracion(...)`, y un `AdminErpIntegracionesController` que el SPA admin consume.

En **tenri-admin**, nueva sección **"Integraciones"** (separada de "Servicios"/planes):
switch por empresa + tablero de salud (recharts ya está) + historial de errores.

---

## 5. Capa de ejecución en el ERP (la parte técnica pesada)

1. **Generalizar el patrón SII** (hoy en `Domains/Sii`) a un contrato común:
   - `IntegracionContrato` (interface): `activar()`, `salud()`, `ejecutar(...)`.
   - **Adaptadores por organismo** intercambiables sandbox/producción (igual que SII ya hace con
     certificación/producción).
   - **Servicio común de credenciales cifradas** (extraer de `Domains/Sii` → `Domains/Core/Crypto`):
     `Crypt` + `$hidden` + rotación de `APP_KEY` (pendiente del reporte de seguridad).
2. **Nuevos dominios de ejecución** (uno por organismo, siguiendo a `Sii`):
   - `Domains/Tgr` — consulta de deuda fiscal / convenios (RUT + certificado o credenciales TGR).
   - `Domains/Previred` — generación y envío del archivo previsional mensual.
   - `Domains/Caja` — integración con caja(s) de compensación.
3. **Cola + outbox** para toda operación externa: idempotencia, reintentos con backoff,
   dead-letter. Reutiliza el patrón de Jobs del SII. **Requiere** `QUEUE/CACHE` en `database`/`redis`
   (ya advertido en el reporte: con `file` se rompen los locks).
4. **Verificación criptográfica de respuestas** (hallazgos S-1/S-2 del reporte) como requisito
   transversal antes de confiar en cualquier organismo.

---

## 6. Seguridad (no negociable antes de producción)

- **P1 multitenant: ✅ hecho** — prerequisito cumplido (datos previsionales/fiscales aislados).
- Retirar el **fallback legacy `X-WEB-API-KEY`** (A-2): el canal interno de integraciones debe ser
  **HMAC-only**.
- Credenciales de cada organismo **cifradas en reposo** en el ERP (`Crypt`), nunca en el hub ni en logs.
- **Auditoría a nivel de campo** de cada operación (quién activó, cada envío/consulta) — tabla
  `auditorias` ya existe; extender.
- Rate-limiting en los nuevos endpoints internos (ya pusimos `throttle:60,1` en `/internal/web/*`).

---

## 7. Roadmap incremental (sugerido)

| Hito | Entregable |
|---|---|
| **F2.0** | Tabla `integraciones_empresa` (ERP) + `IntegracionContrato` + servicio común de credenciales (extraído de SII). **Sin organismo nuevo todavía.** |
| **F2.1** | Endpoints internos `/internal/web/integraciones/*` + `ErpClient` extendido + `AdminErpIntegracionesController`. |
| **F2.2** | UI "Integraciones" en tenri-admin (switch + tablero de salud) usando SII (ya existe) como **primera integración visible** end-to-end. |
| **F2.3** | **Piloto = Previred** (decidido): adaptador sandbox/producción, generación + envío del archivo previsional mensual, con cola/outbox y credenciales propias cifradas. |
| **F2.4** | Repetir para los organismos restantes con el andamiaje ya probado. |

---

## 8. Decisiones

**Tomadas (1 con alerta):**
1. ⚠️ **Piloto = Previred — BLOQUEADO POR DEPENDENCIA.** Previred se genera desde datos de
   **nómina/remuneraciones**, y se verificó que el ERP **NO tiene módulo de remuneraciones**
   (no hay dominio ni migraciones de nómina). Opciones:
   - (a) **Construir primero un módulo de Remuneraciones** en el ERP (proyecto grande) y luego Previred.
   - (b) **Cambiar el piloto a TGR** (consulta de deuda fiscal por RUT + credenciales: autocontenido,
     no depende de nómina) → recomendado para validar el andamiaje rápido.
   - (c) Importar la nómina desde una fuente externa (define el origen de datos).
2. ✅ **Credenciales propias por organismo** (usuario/clave o token por integración, cifradas en el ERP).
   El esquema `credenciales_cifradas` guarda un blob por integración; no se reutiliza el certificado SII.

**Pendientes (antes de F2.3):**
3. **Billing:** ¿cada integración es un add-on facturable por plan (controlado desde el hub), o incluida
   en ciertos planes?
4. **¿Microservicio aparte?** Recomendación: **no** por ahora — ejecución dentro del ERP (monolito
   modular por dominios). Extraer solo si una integración lo justifica por carga/escala.
5. **Detalle Previred:** confirmar formato de archivo y canal de envío (portal/SFTP/API) y datos de nómina
   que el ERP debe tener (hoy el ERP no tiene módulo de remuneraciones completo — verificar alcance).

---

*Este documento es un plan; no se implementó código. La arquitectura propuesta reutiliza
exactamente el patrón Admin→Hub(ErpClient/HMAC)→ERP que ya está en producción.*
