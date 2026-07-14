# Auditoría de seguridad ofensiva — 2026-07-14

Auditoría whitebox completa, 9 agentes en paralelo (modelo Fable 5) por dominio, solo lectura, sin límites de alcance. Objetivo: romper la aplicación, encontrar todo fallo de seguridad real explotable. No se modificó código durante la auditoría.

Dominios cubiertos: Core (auth/RBAC/multitenant/HMAC), Comercial, Tesorería, Rrhh, Inventario, Sii, Activos/CorrecciónMonetaria, Frontend, Infra/Config/CI.

## Resumen ejecutivo

Base de seguridad del sistema es sólida (EmpresaScope, HMAC, cifrado CipherSweet, rate limiting, headers) — la mayoría de vectores clásicos (SQLi, XSS, mass assignment, IDOR simple) están cerrados. Los hallazgos reales caen en dos categorías: **fraude de lógica de negocio** (montos/IVA no recalculados server-side) y **condiciones de carrera** (checks sin lock ni unique constraint). Ningún hallazgo es cross-tenant explotable de forma directa salvo enumeración de IDs (H3 Comercial).

## CRÍTICOS

### C1 — Doble ejecución de Corrección Monetaria por condición de carrera (TOCTOU)
**Dominio:** CorrecciónMonetaria. **Archivo:** `CorreccionMonetariaService.php:233-366`, migración `cm_ejecuciones` sin `unique`.
Guard es check-then-insert sin lock. Dos requests concurrentes generan 2 ejecuciones "ejecutada" + doble asiento + doble ajuste sobre cada activo. Fix: unique compuesto `(empresa_id, periodo_mes, periodo_anio, estado)` + lock pesimista antes del check.

### C2 — Manipulación de IVA en Notas de Crédito/Débito de venta
**Dominio:** Comercial. **Archivo:** `FacturaService.php:399-409, 554-564`.
`monto_neto/monto_iva/monto_bruto` de NC/ND vienen del cliente sin validar proporción real. Permite reversar IVA Débito Fiscal por un monto arbitrario (ej. NC con IVA=$1 sobre neto=$1) → subdeclaración de IVA al SII. Fix: recalcular IVA server-side desde neto × tasa, o exigir proporción del documento origen.

### C3 — IVA de venta manipulable desde la cotización
**Dominio:** Comercial. **Archivo:** `CotizacionController.php:85,90`, `CotizacionService.php:60-61`.
`porcentaje_iva`/`es_afecta` no se validan; cotización afecta con IVA=0 se convierte en factura real con IVA Débito $0. Fix: forzar `porcentaje_iva` server-side desde config fiscal en operaciones afectas.

## ALTOS

### A1 — Contraseña por defecto estática para usuarios invitados
**Dominio:** Core. **Archivo:** `UsuarioService.php:63`.
Invitación crea usuario `Activa` con password `'12345678'` fijo, sin flag de cambio obligatorio. Cualquiera que conozca el email invitado puede loguearse antes que el titular real. Además evade `CheckSubscription` (usuario sin `tenri_user_id` → `SubscriptionVerifierService::isActive` retorna true). Fix: password aleatoria + flujo de set-password obligatorio.

### A2 — Doble uso de movimiento bancario vía conciliación de anticipo
**Dominio:** Tesorería. **Archivo:** `BancoService.php:393,454`.
Guard solo rechaza estado `'CONCILIADO'`, no `'CONCILIADO_ANTICIPO'`. Permite aplicar el mismo movimiento a dos anticipos, o a un anticipo y luego a conciliación directa/factura → doble contabilización de un mismo flujo de caja. Fix: whitelist `estado !== 'PENDIENTE'` en vez de blacklist.

### A3 — `trustProxies(at: '*')` permite spoofing de IP y bypass de rate-limit de login
**Dominio:** Infra. **Archivo:** `bootstrap/app.php:29`.
Con `X-Forwarded-For` arbitrario, atacante evade `throttle:6,1` de `/auth/login` rotando IP declarada por request → credential stuffing sin freno. Fix: acotar `trustProxies` a los CIDR reales del load balancer.

### A4 — Doble depreciación mensual por misma condición de carrera que C1
**Dominio:** Activos. **Archivo:** `ActivoFijoService.php:142-282` (guard línea 160-169).
Mismo patrón TOCTOU: check `exists()` antes de lock. Dos requests concurrentes duplican el asiento de depreciación del período. Fix: unique constraint o lock antes del check.

### A5 — Credenciales de superadmin hardcodeadas en specs E2E + historial git
**Dominio:** Frontend. **Archivos:** `e2e/*.spec.js` (5+ archivos), historial git `.env.e2e` (commits `20b65b9`, `892f7fb`).
Fallback `superadmin@tenri.cl` / `password123` versionado. Si esa combinación es válida en cualquier entorno alcanzable, es toma de cuenta directa de un rol ≥100. Fix: eliminar defaults literales (fallar si falta env var); tratar la credencial como comprometida y rotarla si existe en algún entorno.

## MEDIOS

- **M1 (Comercial)** — `proveedor_id` en OC/Honorarios sin scope de empresa (`exists:proveedores,id` global) → oráculo de enumeración cross-tenant de IDs de proveedor. `OrdenCompraController.php:28,63`, `HonorariosController.php:41`.
- **M2 (Comercial)** — Aplicación de anticipos no valida que `factura.cliente_id`/`proveedor_id` coincida con el del anticipo; lado proveedor no valida `factura.tipo`. `AnticipoClienteService.php:41-58`, `AnticipoProveedorService.php:35-61`.
- **M3 (Core)** — Bloqueo de cuenta de terceros: 5 intentos fallidos bastan para bloquear 15 min la cuenta de una víctima conocida (DoS dirigido desde una sola IP). `AuthController.php:301-317`.
- **M4 (Inventario)** — `consumirReserva()` no aplica el guard `validarSinCicloDeVidaPropio()` que sí tienen cancelar/liberar → puede consumir reservas de picking/packing/despacho por el endpoint genérico, doble decremento de stock. `InventarioReservaService.php:275-282`.
- **M5 (Activos/CM)** — `factor_override` por cuenta acepta ±50/100% pese a que el IPC global topea ±30% → revalorización desproporcionada sin exigir jerarquía elevada. `CorreccionMonetariaController.php:126` vs `:42`.
- **M6 (Activos/CM)** — Cuentas de configuración CM (`cuenta_activos_codigo`, etc.) no se validan contra el Plan de Cuentas ni empresa. `CorreccionMonetariaController.php:85-89`.
- **M7 (Infra)** — Deploy a prod dispara con cualquier push a `main`; verificar branch protection + required reviewers en GitHub (fuera del repo, no verificable por código).
- **M8 (Infra)** — Backups sin cifrar por defecto (`BACKUP_ARCHIVE_PASSWORD` vacío) incluyen certificados digitales SII + PII completa.

## BAJOS

- **B1 (Core)** — `SubscriptionVerifierService` fail-open ante error de red/config/4xx-5xx concluyente.
- **B2 (Core)** — `rol_id` en invitación no scopeado a empresa (requiere jerarquía ≥100, impacto acotado).
- **B3 (Comercial)** — `vincularAProyecto`/`reclasificarAsiento` no validan pertenencia de proyecto/código de cuenta.
- **B4 (Comercial)** — Permiso cruzado: `compras.crear` puede emitir NC/ND de venta.
- **B5 (Rrhh)** — Inyección de delimitador/fórmula en exportación Previred/Excel (nombres/apellidos sin sanear `;`/salto de línea/`=`).
- **B6 (Inventario)** — CSV formula injection en exportación de reportes (`fputcsv` sin neutralizar `=+-@`).
- **B7 (Sii)** — Token de sesión SII persistido en texto plano (TTL 50 min, sin endpoint de exposición).
- **B8 (Sii)** — Gate de suspensión en `SiiCertificadoController` usa empresa "principal" en vez de `empresa_activa_id`.
- **B9 (Activos)** — `estado` de ActivoFijo sin whitelist `in:` en creación.
- **B10 (Frontend)** — Font Awesome vía CDN sin SRI; ausencia total de CSP en el frontend (agrava A-6 token en localStorage, ya documentado).
- **B11 (Infra)** — CSP del backend permite `script-src` desde CDNs externos no usados por las vistas Blade servidas.

## Verificado como robusto (sin hallazgo)

HMAC/WebProvisioning (hash_equals, anti-replay con nonce), escalada de privilegios en roles (jerarquía + intersección de permisos), EmpresaScope global, XXE en XML/DTE (libxml ≥2.9 sin flags peligrosos), certificado .pfx cifrado + oculto, folios/CAF con lock y validación de rango, cifrado CipherSweet en RRHH (RUT/sueldo/cuenta bancaria), autorización RRHH (todas las rutas con `permiso:`), autorización Inventario (~55 rutas verificadas vía `InventarioPermisoService`), CORS sin combinación insegura `*`+credentials, sin secretos commiteados, `composer audit`/`pnpm audit` en CI.

## Priorización de remediación sugerida

1. C1, C2, C3 (fraude/integridad contable directa vía API)
2. A1, A2, A3 (toma de cuenta / bypass de controles)
3. A4, A5 (duplicación contable / credencial expuesta)
4. M1-M8 según exposición de cada empresa
5. B1-B11 como hardening de background
