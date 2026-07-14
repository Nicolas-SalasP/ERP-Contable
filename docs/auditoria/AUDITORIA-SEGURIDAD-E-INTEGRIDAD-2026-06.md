# 🔐 Auditoría de Seguridad e Integridad — Tenri ERP Cloud

**Fecha:** 2026-06-10
**Rama auditada:** `claude/focused-brahmagupta-ikriw1` (deriva de `dev`)
**Alcance:** Backend Laravel 12 (API), Frontend React 19 (SPA), Base de Datos, CI/CD.
**Metodología:** Auditoría tipo *council* — 5 revisiones especializadas en paralelo (Auth/RBAC, Multitenancy, Integridad contable, SII/cripto, Secretos/CI-CD) + verificación manual directa de los hallazgos de mayor impacto.
**Enfoque:** Seguridad e integridad primero (solicitado). Roadmap de extensibilidad (SII/TGR/Previred/Cajas) al final.

> **Leyenda de confianza:**
> `✔ CONFIRMADO` = verificado manualmente sobre el código.
> `● REPORTADO` = hallazgo de revisión especializada, consistente con el código pero pendiente de validación puntual.

> **Re-verificación 2026-07-14** (5 agentes en paralelo, uno por sección, contra el código actual de `NSalas-dev`): de 27 hallazgos con ID, **16 resueltos**, **2 ya no aplican**, **2 parciales**, **7 siguen abiertos**. Cada ítem abajo lleva su estado real actualizado entre corchetes al inicio de la fila; el texto original del hallazgo se conserva sin editar. Dos hallazgos nuevos salieron de esta re-verificación y ya se corrigieron hoy mismo: `Base de Datos/sistema_contable.sql` tenía datos con forma de reales (RUT/email/teléfono personal del propietario + 3 proveedores/cliente) — sanitizado; `_ide_helper*.php` seguían trackeados sin excepción en `.gitignore` — corregido. Detalle de qué sigue realmente abierto: **A-4** (hash de provisión sin validar formato), **M-2** (3 modelos de CorrecciónMonetaria sin `HasEmpresaScope`), **M-3/M-4** (jobs sin tenant y mass-assignment de `empresa_id` sin capa estructural), **F-3/F-4/F-6/F-7** (máquina de estados de factura, FK de movimiento bancario, auditoría de campo, doble depreciación en restore de activo), **S-1/S-2/S-5/S-6** (firma CAF/SOAP del SII, rotación de `APP_KEY`, OCSP/CRL de certificado), **C-2** (token en localStorage, tiene plan propio `S-2-PLAN-TOKEN-COOKIE-HTTPONLY.md`), **C-5** (recién sanitizado hoy, pendiente decidir si conviene purgar del historial git también).

---

## 1. Resumen ejecutivo

Tenri ERP **no es un prototipo**: es un sistema en producción temprana, con arquitectura DDD por dominios, 204 archivos de test, CI/CD con análisis estático (PHPStan) + PHPUnit, Sentry, backups (spatie), health checks y un hardening de seguridad ya iniciado (cifrado de certificados/CAF en reposo, HMAC para SSO, RBAC granular por endpoint). La base es **sólida y de buena ingeniería**.

Dicho eso, la auditoría identifica **riesgos que deben cerrarse antes de escalar a más contribuyentes y antes de conectar TGR/Previred/Cajas**, porque esas integraciones multiplican el valor (y la sensibilidad) de los datos almacenados.

### Veredicto por dominio

| Dominio | Estado | Riesgo residual |
|---|---|---|
| Autenticación / Sanctum | 🟡 Aceptable con brechas | **Sin rate-limiting en login** (crítico) |
| Autorización / RBAC | 🟢 Bien diseñado (2 capas) | Validar techos de plan en escalada |
| **Aislamiento multitenant** | 🟠 **Frágil por diseño** | Scope global no es *fail-safe* y cobertura parcial |
| **Integridad contable** | 🟠 **Controles incompletos** | Reclasificación/soft-delete sin candados de período |
| SII / criptografía | 🟢 Lo mejor logrado | Falta validar firma de respuestas/CAF |
| Secretos / CI-CD / config | 🟡 Higiene mejorable | Credenciales de prueba commiteadas + headers |

### Top 5 riesgos a cerrar primero (P0)

1. **🔴 Sin rate-limiting en `/auth/login` y `/auth/token-login`** → fuerza bruta / credential stuffing ilimitado. *(✔ CONFIRMADO)*
2. **🟠 `EmpresaScope` no es *fail-safe* y solo lo usan 6 de 51 modelos** con `empresa_id` → el aislamiento entre empresas depende de disciplina manual en cada controller/servicio, no de una garantía central. *(✔ CONFIRMADO)*
3. **🟠 Mutación de hechos contables sin candado de período cerrado** (reclasificación de asientos, soft-delete de asientos/facturas pagadas) → corrupción de períodos post-F29. *(● REPORTADO)*
4. **🟡 Credenciales de prueba reutilizadas y commiteadas** (`superadmin@tenri.cl` / `password123` en `Frontend/.env.e2e` y en `UserSeeder` para 4 roles). *(✔ CONFIRMADO)*
5. **🟡 Faltan cabeceras de seguridad HTTP** (CSP, HSTS, X-Frame-Options, X-Content-Type-Options) y el token se guarda en `localStorage` (expuesto a XSS). *(● REPORTADO)*

---

## 2. Inventario técnico (estado real)

| Componente | Realidad | Nota |
|---|---|---|
| Backend | **Laravel 12.57 + Sanctum 4.3.1**, PHP 8.2 | El README raíz describe "PHP nativo + PDO + JWT": **desactualizado**. |
| Frontend | **React 19 + Vite 7**, Tailwind, Echo/Pusher (websockets) | Moderno. |
| Arquitectura | DDD: `Domains/{Sii, Contabilidad, Tesoreria, Inventario, Activos, CorreccionMonetaria, Comercial, Core}` | 80 modelos, 426 líneas de rutas API. |
| Multitenancy | Por `empresa_id` | Scope global parcial (ver §4). |
| Tests | 204 archivos (Unit + Feature), fuerte en SII | Incluye `AislamientoMultiTenantTest`. |
| CI/CD | GitHub Actions → **deploy por SCP/SSH** (no FTP) en push a `main` | README dice "FTP": desactualizado. Deploy en sí es razonable. |
| Observabilidad | Sentry (PII off), backups spatie, health checks | Buen punto de partida. |
| Dependencias | Sin vulnerabilidades hardcodeadas detectadas; versiones al día | `xmlseclibs 3.1.5` usa SHA1 (requisito SII, aceptable). |

---

## 3. Autenticación y autorización

### 3.1 Hallazgos

| ID | Sev | Hallazgo | Evidencia | Confianza |
|---|---|---|---|---|
| A-1 | 🔴 CRÍTICO | [✅ RESUELTO] **Login sin rate-limiting.** El grupo `api` no aplica `throttle`; `/auth/login` y `/auth/token-login` son públicos y sin límite → fuerza bruta y enumeración. | `routes/api.php:55-56`, `bootstrap/app.php:21-28` | ✔ CONFIRMADO |
| A-2 | 🟠 ALTO | [✅ RESUELTO] **Fallback legacy `X-WEB-API-KEY`** (llave estática sin nonce/timestamp) coexiste con HMAC → ventana de *replay* indefinida hasta retirar "Fase 2". | `app/Http/Middleware/VerifyWebApiKey.php:24-27` | ✔ CONFIRMADO |
| A-3 | 🟠 ALTO | [✅ RESUELTO] **Endpoints internos sin rate-limiting** (`/internal/web/*`): aun con HMAC, permiten DoS/abuso por volumen (p. ej. provisión masiva). | `routes/api.php:405-426` | ✔ CONFIRMADO |
| A-4 | 🟡 MEDIO | [🔴 SIGUE ABIERTO] **`syncPassword`/`provisionUser` insertan el hash directo** desde la web sin validar que sea bcrypt/argon2 válido → riesgo si la web emisora se ve comprometida. | `Core/Controllers/Internal/WebProvisioningController.php:107-137`, `Core/Services/ProvisionUserService.php:65` | ● REPORTADO |
| A-5 | 🟡 MEDIO | [✅ RESUELTO] **Posible escalada de jerarquía en gestión de roles** (self-asignación / techo de plan no aplicado a Administradores). Requiere validar la lógica relativa del controller. | `Core/Controllers/UsuarioController.php:78-114, 202-231`; `Core/Support/ModuloPermisos.php:158-174` | ● REPORTADO (verificar) |
| A-6 | 🟢 BAJO | [⚪ ACEPTADO, sigue así] `/me` expone `subscription_ends_at`/`module_keys` al cliente (info de negocio). | `Core/Controllers/AuthController.php:270-290` | ● REPORTADO |

### 3.2 Fortalezas
- **RBAC en dos capas**: gate grueso por permiso en la ruta (`EnsureUserHasPermission`) + lógica relativa por instancia en el controller. Bien razonado y documentado.
- **HMAC-SHA256 con timestamp + nonce** para SSO inter-servicio (`app/Support/HmacFirma.php`).
- **bcrypt rounds = 12**; revocación de tokens al cambiar contraseña; Sanctum con expiración (120 min).

---

## 4. Aislamiento multitenant *(área de mayor riesgo estructural)*

### 4.1 Hallazgo central — `✔ CONFIRMADO`

[🟡 PARCIAL — re-verificado 2026-07-14] El caso "usuario autenticado sin empresa" ya es fail-safe
(`whereRaw('1 = 0')`, ver §7-bis). El caso "sin autenticación" (jobs/consola/colas) **sigue sin
filtrar en absoluto** — el propio código lo admite explícitamente y delega en disciplina manual por
Job. Es el hueco más serio que persiste de todo este documento.

El scope global de empresa **no es *fail-safe***:

```php
// app/Domains/Core/Scopes/EmpresaScope.php
public function apply(Builder $builder, Model $model): void {
    // Sin usuario autenticado (jobs, consola, tests) NO se filtra.
    if (auth()->check() && auth()->user()->empresa_id !== null) {
        $builder->where($model->getTable().'.empresa_id', auth()->user()->empresa_id);
    }
}
```

Consecuencias:
1. **En jobs, comandos artisan y colas no hay filtro** → cualquier `Modelo::all()` sin `actingAs` ve **todas las empresas**. Crítico porque los Jobs del SII y de inventario corren en cola.
2. **Usuario sin `empresa_id` (onboarding) ve todo** (la condición desactiva el filtro).
3. **Cobertura parcial:** solo **6 de 51 modelos** con `empresa_id` aplican el trait `HasEmpresaScope`:
   - ✅ Con scope: `Cliente`, `Cotizacion`, `Factura`, `Proveedor`, `AsientoContable`, `ActivoFijo`.
   - ❌ Sin scope (dependen 100% del filtrado manual del controller): todo **SII** (`SiiDteEmitido`, `SiiEnvioDte`, `SiiCaf`, `SiiCertificadoEmpresa`, `SiiTokenSesion`), **Inventario** (~28 modelos: `Producto`, `Bodega`, `LoteInventario`, `MovimientoInventario`, etc.), **Contabilidad** (`PlanCuenta`, `CentroCosto`, `DetalleAsiento`, `MapeoCuentaSii`), **Tesorería** (`CuentaBancariaEmpresa`), **Corrección Monetaria** (`CmConfiguracionEmpresa`, `CmEjecucion`, …).

> **Riesgo:** el aislamiento real **no está garantizado por una capa central**, sino por la disciplina de cada controller/servicio. Hoy funciona porque los controllers filtran (`->where('empresa_id', $user->empresa_id)`), pero un solo `find($id)` olvidado = IDOR cross-tenant. Para un ERP contable multiempresa esto es el riesgo #1 a futuro.

### 4.2 Hallazgos de apoyo

| ID | Sev | Hallazgo | Evidencia | Confianza |
|---|---|---|---|---|
| M-1 | 🟠 ALTO | [✅ RESUELTO] Servicios que acceden por ID sin pasar `empresa_id` explícito (defensa en profundidad ausente). | `Comercial/Services/ClienteService.php:47`, `Comercial/Services/CotizacionService.php:29`, `Inventario/Services/InventarioUbicacionService.php:227` | ● REPORTADO |
| M-2 | 🟠 ALTO | [🟡 PARCIAL — 2026-07-14: 68/75 modelos con `HasEmpresaScope` (era 6/51). 3 gap real: `CorreccionMonetaria/Models/{CmConfiguracionCuenta,CmConfiguracionEmpresa,CmEjecucion}.php` sin el trait, dependen 100% de filtrado manual] 45 modelos con `empresa_id` sin `HasEmpresaScope` (defensa central ausente). | (lista §4.1) | ✔ CONFIRMADO |
| M-3 | 🟡 MEDIO | [🟡 PARCIAL, ver §4.1] `EmpresaScope` debería **fallar cerrado** cuando no hay tenant resoluble en contexto web. | `Core/Scopes/EmpresaScope.php:13-16` | ✔ CONFIRMADO |
| M-4 | 🟢 BAJO | [🔴 SIGUE ABIERTO] Form Requests no bloquean `empresa_id` por mass-assignment; se mitiga porque los controllers lo sobreescriben con `$user->empresa_id`. | `Sii/Http/Requests/SubirCafRequest.php` y otros | ● REPORTADO |

### 4.3 Fortaleza
- Existe `tests/Feature/Core/AislamientoMultiTenantTest.php` que valida no-fuga en los modelos con scope. **Buena base** para extender la cobertura a los 45 restantes.

---

## 5. Integridad contable y financiera

> El motor contable valida **partida doble** (debe = haber) antes de persistir (`Contabilidad/Services/AsientoContableService.php`), usa `DECIMAL(15,2)` con casts `decimal:2`, candado anti-duplicado con `Cache::add` + `UNIQUE(empresa_id, numero_comprobante)`, y `DB::transaction` en operaciones multi-tabla. La base es correcta. Las brechas están en la **mutabilidad de hechos ya consumados**.

| ID | Sev | Hallazgo | Evidencia | Confianza |
|---|---|---|---|---|
| F-1 | 🔴 CRÍTICO | [✅ RESUELTO, ver §7-ter] **Reclasificación de asiento sin validar período cerrado**: permite cambiar `fecha`/cuentas de una factura ya pagada hacia un mes cerrado (post-F29). | `Comercial/Services/FacturaService.php:238-283` | ● REPORTADO |
| F-2 | 🔴 CRÍTICO | [✅ RESUELTO vía `AsientoContableObserver` + bloqueo de período, ver §7-ter. Nota: un asiento MAYORIZADO en período aún abierto sí puede soft-deletarse — el observer no mira `estado`, solo período; fuera del alcance original de F-1/F-2] **Soft-delete sin candado de estado**: asientos `MAYORIZADO` y facturas `PAGADA` pueden borrarse lógicamente (y `restore()`) sin reversa → libro mayor inconsistente, inmutabilidad rota. | `Contabilidad/Models/AsientoContable.php` (SoftDeletes); migración `2026_06_03_130000_add_soft_deletes_to_critical_tables` | ● REPORTADO |
| F-3 | 🟠 ALTO | [🔴 SIGUE ABIERTO] **`cambiarEstado` de factura sin máquina de estados**: transiciones ilegales posibles (PAGADA→REGISTRADA, ANULADA→REGISTRADA) sin asiento de reversa. | `Comercial/Services/FacturaService.php:405-410` | ● REPORTADO |
| F-4 | 🟠 ALTO | [🔴 SIGUE ABIERTO, mismo patrón replicado en `anticipos_*.asiento_id`] **Sin FK `movimientos_bancarios.asiento_id → asientos_contables.id`**: integridad referencial débil entre tesorería y mayor. | migración `2026_04_28_120000_create_movimientos_bancarios_table.php:21` | ● REPORTADO |
| F-5 | 🟠 ALTO | [🟡 SIN CAMBIO DE CÓDIGO, riesgo depende de config del operador] **Race condition latente**: el candado `Cache::add` de asientos depende de `CACHE_STORE`; con `file` y múltiples workers el lock no es global → duplicados. El `.env.example` ya advierte usar `database`. | `Contabilidad/Controllers/AsientoContableController.php:32,95`; `.env.example:48-50` | ✔ CONFIRMADO (riesgo de config) |
| F-6 | 🟡 MEDIO | [🔴 SIGUE ABIERTO] **Auditoría registra solo cambios de estado**, no de montos/fechas → reclasificaciones quedan fuera de la traza. | `Core/Models/Auditoria.php`; `FacturaService::obtenerAuditoriaCompleta` | ● REPORTADO |
| F-7 | 🟡 MEDIO | [🔴 SIGUE ABIERTO, sin observer/guard de `restore()`] Soft-delete de activos podría permitir doble depreciación al restaurar. | `Activos/Services/ActivoFijoService.php:439-508` | ● REPORTADO |

---

## 6. SII y material criptográfico *(dominio más maduro)*

> El manejo de secretos del SII es **el mejor logrado del proyecto**: certificados `.pfx`, contraseñas, clave RSA del CAF y XML del CAF se **cifran con `Crypt::encryptString` (APP_KEY)** y se marcan `$hidden`; los tokens se truncan en logs; hay monitoreo de vencimiento de certificados con alertas por email; validación XSD del DTE. *(✔ CONFIRMADO el cifrado en reposo y `$hidden`.)*

| ID | Sev | Hallazgo | Evidencia | Confianza |
|---|---|---|---|---|
| S-1 | 🟠 ALTO | [🔴 SIGUE ABIERTO, el propio docblock del código lo admite] **No se valida la firma del CAF contra la CA raíz del SII** (comentario explícito "validación contra cert raíz SII queda diferida al backlog") → un CAF manipulado podría aceptarse. | `Sii/Services/Caf/CafXmlParser.php:13,35-95` | ● REPORTADO |
| S-2 | 🟠 ALTO | [🔴 SIGUE ABIERTO] **Respuestas SOAP del SII se parsean sin validar firma** (solo estructura/regex) → un MITM podría inyectar un estado "ACEPTADO" falso. | `Sii/Services/Ws/SiiUploadService.php:106-131`, `SiiEstadoUpService.php:137-178` | ● REPORTADO |
| S-3 | 🟠 ALTO | [🟡 PARCIAL — `CafService::reservarSiguienteFolio` bloquea reactivamente al intentar usar un CAF vencido; sigue faltando el job proactivo/batch] **No hay bloqueo automático de CAF vencido** (6 meses): folios vencidos podrían usarse. | `Sii/Models/SiiCaf.php`; falta job de expiración | ● REPORTADO |
| S-4 | 🟡 MEDIO | [⚪ YA NO APLICA — PHP ^8.2 + libxml ≥2.9 deshabilita expansión de entidades externas por defecto, sin necesidad de `LIBXML_NOENT`; confirmado también por la auditoría ofensiva 2026-07-14] **XXE no mitigado explícitamente** en parseos libxml/simplexml (sin `LIBXML_NONET`/deshabilitar entidades). | `Sii/Services/Caf/CafXmlParser.php:105` y parsers XML | ● REPORTADO |
| S-5 | 🟡 MEDIO | [🔴 SIGUE ABIERTO] **Sin estrategia documentada de rotación de `APP_KEY`**: toda la cadena de certificados depende de una sola llave; su fuga compromete todo y no hay plan de re-cifrado. | arquitectura de cifrado | ✔ CONFIRMADO (ausencia) |
| S-6 | 🟢 BAJO | [🔴 SIGUE ABIERTO] Sin validación de cadena/expiración por OCSP/CRL del certificado del contribuyente (solo `validTo`). | `Sii/Services/Certificado/CertificadoService.php:26-35` | ● REPORTADO |

---

## 7. Secretos, configuración y CI/CD

| ID | Sev | Hallazgo | Evidencia | Confianza |
|---|---|---|---|---|
| C-1 | 🟡 ALTO* | [✅ RESUELTO — reapareció como fallback en 8 specs `Frontend/e2e/*.spec.js` en junio/julio, corregido de nuevo el 2026-07-14 (commit `82eb14e`)] **Credenciales de prueba commiteadas y reutilizadas**: `superadmin@tenri.cl` / `password123` en `Frontend/.env.e2e` y en `UserSeeder` (4 roles). Mismo par como *fallback* en `e2e.yml`. *Crítico si producción fue sembrada con seeders.* | `Frontend/.env.e2e:1-2`, `database/seeders/UserSeeder.php:14`, `.github/workflows/e2e.yml:182-183` | ✔ CONFIRMADO |
| C-2 | 🟠 ALTO | [🔴 SIGUE ABIERTO — plan de migración en `S-2-PLAN-TOKEN-COOKIE-HTTPONLY.md`] **Token en `localStorage`** (no HttpOnly) → robo por XSS. | `Frontend/src/Configuracion/api.js:20-34` | ● REPORTADO |
| C-3 | 🟠 ALTO | [✅ RESUELTO] **Faltan cabeceras de seguridad** (CSP, HSTS, X-Frame-Options, X-Content-Type-Options) en backend y `.htaccess` del frontend. | sin middleware de headers | ● REPORTADO |
| C-4 | 🟡 MEDIO | [✅ RESUELTO] **PDFs de anticipos en disco `public`** (`store(...,'public')`) → potencialmente enumerables/descargables sin auth. | `Comercial/Controllers/ProveedorController.php:157` | ● REPORTADO |
| C-5 | 🟡 MEDIO | [✅ SANITIZADO 2026-07-14 — sí tenía datos con forma de reales (RUT/email/teléfono personal del propietario, 3 proveedores/cliente con RUT y contacto); reemplazados por datos demo genéricos en el commit del mismo día. El dato viejo sigue en el historial git (no reescrito, requiere autorización aparte)] **Dump SQL commiteado** (`Base de Datos/sistema_contable.sql`). Revisar que nunca contenga datos reales. | repo raíz | ✔ CONFIRMADO |
| C-6 | 🟢 BAJO | [✅ RESUELTO 2026-07-14 — `_ide_helper*.php` desindexados + agregados a `.gitignore`; `an optimizeclear` ya no existía] `_ide_helper.php` / `_ide_helper_models.php` commiteados (deberían ignorarse). Archivo basura `an optimizeclear` (salida de `less`) en la raíz. | repo | ✔ CONFIRMADO |
| C-7 | 🟢 BAJO | [✅ RESUELTO] `CORS allowed_methods/headers = ['*']` con `supports_credentials=true` (orígenes sí explícitos). Restringir a métodos/headers concretos. | `config/cors.php` | ✔ CONFIRMADO |
| C-8 | 🟢 BAJO | [✅ RESUELTO — `Gate::allows('viewApiDocs')` sin definir → `false` por defecto en prod, más restrictivo que "requiere auth"] Doc API (Scramble `/docs/api`) protegida por `RestrictedDocsAccess`: verificar que exige auth en prod. | `config/scramble.php` | ● REPORTADO |

\* Severidad efectiva depende de si producción usó estos seeds/credenciales. **Acción inmediata:** confirmar y rotar.

### Lo bien hecho en infra
- **Deploy por SSH/SCP** con claves en *GitHub Secrets* (no FTP); el ZIP excluye `.env*`.
- **No hay `.env`, `.pfx`, `.p12`, `.pem` ni claves privadas en el árbol ni en el historial** (347 commits revisados).
- CORS con **orígenes explícitos** (sin wildcard de origen).

---

## 7-bis. Estado de remediación (P0 — esta entrega)

Se implementaron en esta rama los bloqueantes P0 de menor riesgo de regresión, con tests que los fijan (suite verde: Core 364 + Comercial 208 assertions, 0 fallos):

| Hallazgo | Acción aplicada | Archivo |
|---|---|---|
| A-1 | `throttle:6,1` en `/auth/login` y `/auth/token-login` | `routes/api.php` |
| A-3 | `throttle:60,1` en `/internal/web/*` | `routes/api.php` |
| §4.1 / M-3 | `EmpresaScope` ahora es *fail-safe*: usuario autenticado sin `empresa_id` ve **0 registros** (antes veía todo) | `Core/Scopes/EmpresaScope.php` |
| C-1 | `UserSeeder` ya no hardcodea `password123` (lee `DEMO_SEED_PASSWORD` o genera aleatorio); `e2e.yml` sin fallback hardcodeado; `Frontend/.env.e2e` desindexado | `database/seeders/UserSeeder.php`, `.github/workflows/e2e.yml`, repo |
| F-1 | Guard: no reclasificar el asiento de una factura **anulada** | `Comercial/Services/FacturaService.php` |
| **F-1/F-2** | **Feature completa de bloqueo de período contable** (ver §7-ter) | dominio `Contabilidad` |
| Tests | `HardeningSeguridadP0Test` + `BloqueoPeriodoContableTest` (8 casos) | `tests/Feature/` |

**Pendiente (acción de operador, no código):**
- **Rotación de credenciales en servidores** y **reescritura de historial Git** para purgar `password123` de commits previos (destructivo).

---

## 7-ter. Feature implementada — Bloqueo de período contable (cierra F-1/F-2)

Decisiones de negocio confirmadas: cierre **solo manual** (`permiso:contabilidad.cerrar_periodo`), reapertura **Admin ≥80 + motivo + auditada**, **bloqueo duro** (`409 PERIODO_CERRADO`), alcance **completo**.

- Tabla `periodos_contables` + modelo `PeriodoContable` (multitenant) + `PeriodoCerradoException`.
- `PeriodoContableService` y **`AsientoContableObserver`** (creating/updating/deleting) → garantía central: ningún camino de código puede escribir/borrar en un mes cerrado.
- Guard en `reclasificarAsiento` (fecha original + destino). Tesorería/pagos quedan cubiertos por pasar por `registrarAsiento`.
- API `GET/POST /api/contabilidad/periodos[/cerrar|/reabrir]`.
- Detalle completo en `docs/auditoria/DISENO-bloqueo-periodo-contable.md`. **Suite completa: 4432 assertions, 0 fallos.**

> Cambio de comportamiento: F29 ya **no** cierra el período automáticamente (antes sí, vía detección del asiento de centralización). El cierre es ahora una acción administrativa explícita.

---

## 8. Plan de acción priorizado

> Esfuerzo: **S** = ≤0.5 día · **M** = 1-3 días · **L** = 1-2 semanas.

### 🔴 P0 — Bloqueantes de seguridad (esta semana)
| Acción | Ref | Esfuerzo |
|---|---|---|
| Aplicar `throttle:6,1` (o `throttle:login`) a `/auth/login` y `/auth/token-login`; `throttle:60,1` a `/internal/web/*`. | A-1, A-3 | S |
| Rotar credenciales `superadmin@tenri.cl`/`password123` en todos los entornos; quitar `Frontend/.env.e2e` del repo y del historial; mover el par a GitHub Secrets sin *fallback* hardcodeado; randomizar password en `UserSeeder` (env o `Str::random`). | C-1 | S/M |
| Convertir `EmpresaScope` en *fail-safe*: en contexto web sin tenant resoluble, **denegar** en vez de no filtrar. Auditar que ningún Job consulte modelos tenant sin fijar empresa. | M-3, §4.1 | M |
| Añadir candado de **período cerrado** y de **estado** a `reclasificarAsiento` y a los soft-delete de asientos/facturas (bloquear `MAYORIZADO`/`PAGADA`). | F-1, F-2 | M |

### 🟠 P1 — Endurecimiento (2-4 semanas)
| Acción | Ref | Esfuerzo |
|---|---|---|
| Aplicar `HasEmpresaScope` a los 45 modelos restantes (empezar por SII, Inventario, Tesorería) y extender `AislamientoMultiTenantTest` a todos. | M-2 | L |
| Refactor: todo acceso por ID en servicios pasa `empresa_id` explícito (defensa en profundidad). | M-1 | M |
| Middleware global de **cabeceras de seguridad** (CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy). | C-3 | S |
| Migrar token a **cookie HttpOnly+Secure+SameSite** (Sanctum SPA) o, como mínimo, endurecer CSP para mitigar robo de `localStorage`. | C-2 | M |
| Máquina de estados de factura + FK `movimientos_bancarios.asiento_id`. | F-3, F-4 | M |
| Retirar fallback legacy `X-WEB-API-KEY` (Fase 2 HMAC-only). | A-2 | S |
| Mover PDFs de anticipos a disco privado con descarga autenticada. | C-4 | S |

### 🟡 P2 — Robustez SII e integridad (1-2 meses)
| Acción | Ref | Esfuerzo |
|---|---|---|
| Validar firma del CAF y de respuestas SOAP contra el certificado raíz del SII. | S-1, S-2 | L |
| Job de expiración automática de CAF (bloquear emisión con folios vencidos). | S-3 | M |
| Mitigar XXE (`LIBXML_NONET`, deshabilitar entidades) en todos los parsers XML. | S-4 | S |
| Auditoría a nivel de campo (montos/fechas) vía Observers para asientos/facturas. | F-6 | M |
| Documentar y automatizar **rotación de `APP_KEY`** con script de re-cifrado. | S-5 | M |

### 🟢 P3 — Higiene y consistencia (continuo)
| Acción | Ref | Esfuerzo |
|---|---|---|
| `.gitignore` para `_ide_helper*.php`; borrar `an optimizeclear`; revisar que el dump SQL no tenga datos reales. | C-5, C-6 | S |
| Restringir `CORS` a métodos/headers explícitos; confirmar auth en `/docs/api`. | C-7, C-8 | S |
| **Actualizar README raíz** (Laravel/Sanctum/SSH, no PHP-nativo/JWT/FTP). | §2 | S |
| Pre-commit con `gitleaks`/`detect-secrets` para evitar futuros secretos. | C-1 | S |
| Validar que el hash de provisión es bcrypt/argon2 antes de persistir. | A-4 | S |

---

## 9. Preparación para integraciones futuras (SII · TGR · Previred · Cajas de Compensación)

El proyecto ya tiene los cimientos correctos para esto: **dominios DDD aislados** y un **patrón de integración por eventos** (`InventarioEventoIntegracion` + `Contratos/Adaptadores`, *outbox pattern*). Recomendaciones para que esas integraciones lleguen al "100% del servicio" de forma segura:

1. **Resolver primero el aislamiento multitenant (P0/P1).** TGR/Previred/Cajas almacenarán RUTs, montos previsionales y deudas fiscales: una fuga cross-tenant ahí es incidente regulatorio. No conectar nuevos orígenes de datos sensibles sin la garantía central de tenant.
2. **Generalizar la capa de credenciales cifradas del SII** (`Crypt` + `$hidden` + APP_KEY con rotación) como **servicio común** reutilizable por TGR/Previred/Cajas (cada uno requiere certificados/claves/credenciales). Hoy esa lógica vive solo en `Domains/Sii`.
3. **Patrón de adaptador por organismo:** un `Contrato` (interface) por integración + adaptadores intercambiables (sandbox/producción), siguiendo lo ya hecho en Inventario y SII. Facilita certificación y *feature flags* por plan.
4. **Cola + outbox para todas las integraciones externas** (idempotencia, reintentos con backoff, *dead-letter*). Reutilizar el patrón de Jobs del SII; exige resolver F-5 (cache/locks en `database`/`redis`).
5. **Verificación criptográfica de respuestas** (S-1/S-2) como requisito transversal antes de confiar en cualquier organismo externo.
6. **Trazabilidad/auditoría a nivel de campo** (F-6): obligatoria para datos tributarios/previsionales.

---

## 10. Conclusión

Tenri ERP tiene una **arquitectura de calidad y prácticas de ingeniería por encima del promedio** para su etapa (tests, CI, hardening iniciado, cifrado SII). Los riesgos no son de diseño roto, sino de **garantías que aún dependen de disciplina manual** en lugar de capas centrales *fail-safe*: rate-limiting, aislamiento multitenant y candados de inmutabilidad contable. Cerrar P0 y P1 deja al sistema en posición sólida para escalar contribuyentes y para conectar TGR/Previred/Cajas con seguridad regulatoria.

**Recomendación:** abordar P0 inmediatamente (todos son cambios acotados y de bajo riesgo de regresión), y planificar P1 como el "hardening multitenant" previo a cualquier nueva integración externa.

---
*Hallazgos `● REPORTADO` provienen de revisión especializada; conviene validarlos puntualmente antes de remediar. Los `✔ CONFIRMADO` fueron verificados manualmente sobre el código de esta rama.*
