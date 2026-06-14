# Plan de Cumplimiento — Protección de Datos y Ciberseguridad (Chile)

**Estado:** En ejecución por fases.
**Alcance legal:** Ley 21.719 (Protección de Datos Personales), Ley 19.628 (vigente, será sustituida), Ley 21.663 (Marco de Ciberseguridad), camino a ISO/IEC 27001 + 27701.
**Autor:** auditoría de cumplimiento 2026-06. **Última actualización:** 2026-06-14.
**Decisiones tomadas:** cifrado con **CipherSweet** (columnas cifradas + blind index); despliegue **por fases, crítico primero**.

### Estado de avance

| Fase | Alcance | Estado |
|---|---|---|
| 0 | Docs: RAT, DPIA, mapeo ISO, fuentes | ✅ hecho |
| 1 | Cuentas bancarias empresa/proveedor cifradas; `SESSION_ENCRYPT`; máscara de RUT en UI | ✅ hecho |
| 2a | CipherSweet + contacto del empleado (email, teléfono, dirección) | ✅ hecho |
| 2b | RUT del empleado y cargas familiares con blind index (búsqueda exacta + unicidad) | ✅ hecho |
| 2c | Fecha de nacimiento y sueldo base cifrados (con manejo de casts date/decimal) | ✅ hecho |
| — | RUT de clientes/proveedores/empresa | ⏸️ diferido (toca SII/facturación; se reevalúa con certificación) |
| — | Nombres/apellidos del empleado | ⏸️ no se cifran (se preserva búsqueda parcial; protegidos por permisos) |
| 3 | Log de auditoría de acceso/cambio a PII + endpoint DPO | ✅ hecho |
| 4 | Consentimiento + política de privacidad versionada | ⏳ siguiente |
| 5 | Derechos ARCO+ (exportar/portabilidad, supresión respetando retención, bloqueo) | ⏳ pendiente |
| 6 | Registro de brechas + runbook 3h/72h | ⏳ pendiente |
| 7 | Evidencia para certificación ISO 27701 | ⏳ pendiente |

---

## 0. Marco legal y plazos

| Norma | Qué exige (resumen) | Vigencia |
|---|---|---|
| **Ley 21.719** | "GDPR chileno": base de licitud y consentimiento, derechos ARCO+ (acceso, rectificación, supresión, oposición, **portabilidad**), registro de actividades de tratamiento, evaluación de impacto (DPIA), Delegado de Protección de Datos (DPO), notificación de brechas, medidas técnicas según riesgo (cifrado, seudonimización, control de acceso, auditoría). | **1-dic-2026** (plena). PYMEs: solo amonestaciones el primer año. |
| **Ley 19.628** | Protección de la vida privada (régimen actual). | Vigente hasta ser reemplazada por la 21.719. |
| **Ley 21.663** | Medidas técnicas mínimas de ciberseguridad, responsable de ciberseguridad, **reporte de incidentes** (alerta 3h / reporte 72h al CSIRT). | Parcial desde mar-2025. |
| **ISO/IEC 27001 + 27701** | SGSI + extensión de privacidad. 27001 cubre ~50-60 % de la 21.719; la 27701 cierra el resto. Vía de **certificación** por auditor acreditado. | Voluntaria; certificado a 3 años con auditoría anual. |

**Sanciones 21.719:** hasta 20.000 UTM por infracción gravísima; hasta 4 % de los ingresos anuales por reincidencia.

### Advertencias legales que condicionan el diseño (importante)

1. **Retención obligatoria vs. derecho de supresión.** Las liquidaciones de sueldo, contratos y documentos tributarios tienen **plazos legales de conservación** (Código del Trabajo, normas laborales y tributarias, típicamente ~5-6 años). El "derecho al olvido" **no permite borrarlos libremente**: la respuesta lícita es **bloqueo + anonimización parcial** una vez cumplido el plazo de retención, no la eliminación inmediata. El plan implementa supresión *respetando retención*, no borrado total.
2. **El RUT debe poder recuperarse en claro** para Previred, libro de remuneraciones y facturas (DTE). Por eso se usa **cifrado reversible + blind index**, no hashing irreversible, para datos operativos. El hashing irreversible se reserva para anonimización definitiva (post-retención o supresión).
3. **Un hash simple del RUT no es seudonimización segura.** El universo de RUTs válidos es pequeño (~30M, DV determinista) y se revierte con tabla precalculada. Todo hash de RUT usa **HMAC con clave secreta (pepper)** guardada fuera de la BD (CipherSweet maneja esto).
4. **Certificación y "100 %" requieren actores externos.** El código deja listos los **controles técnicos**. La certificación ISO la emite un **organismo acreditado**; la conformidad legal final requiere **visto bueno de abogado**. Este plan entrega la capa técnica y la evidencia documental; no sustituye al auditor ni al asesor legal.

---

## 1. Estado actual (resumen de auditoría)

**Ya conforme:** contraseñas bcrypt(12); `empleados.banco_numero_cuenta_cifrado` cifrado (AES) y oculto en JSON; certificados SII cifrados; control de acceso por permisos (`permiso:rrhh.*`); aislamiento multi-empresa (`empresa_id`); validación de RUT (`RutHelper`, `RutChileno`); `SENTRY_SEND_DEFAULT_PII=false`.

**Brechas (prioridad):**

| # | Brecha | Riesgo | Fase |
|---|---|---|---|
| C1 | `cuentas_bancarias_empresa.numero_cuenta` y `cuentas_bancarias_proveedores.numero_cuenta` en **texto plano** | 🔴 Crítico (fraude) | 1 |
| C2 | `SESSION_ENCRYPT=false`; verificar Sentry PII en prod | 🟠 Alto | 1 |
| C3 | RUT visible/buscable en claro en toda la UI | 🟡 Medio | 1 |
| P1 | RUT, nombres, fecha nac., email, teléfono, dirección, `sueldo_base` en texto plano (empleados, clientes, proveedores, cargas familiares, contratos) | 🔴 Alto | 2 |
| P2 | Sin log de auditoría de acceso/modificación de PII (tabla `auditorias` existe pero subutilizada en RRHH) | 🟠 Alto | 3 |
| P3 | Sin gestión de consentimiento ni política de privacidad versionada | 🟠 Medio-Alto | 4 |
| P4 | Sin ARCO+: no hay exportación (portabilidad) ni supresión/anonimización; soft-delete no anonimiza | 🔴 Alto | 5 |
| P5 | Sin registro de incidentes/brechas ni runbook 3h/72h | 🟠 Medio | 6 |
| P6 | Sin RAT, DPIA, ni mapeo de controles para certificación | 🟡 Medio | 0/7 |

---

## 2. Arquitectura técnica elegida

- **Cifrado en reposo:** `paragonie/ciphersweet` + `spatie/laravel-ciphersweet`. Columnas cifradas AES-256 y **blind indexes** (HMAC) para búsqueda exacta sin descifrar. Clave maestra en `CIPHERSWEET_KEY` (env/KMS, **separada** de `APP_KEY`).
- **Campos cifrados + blind index:** `rut` (todas las tablas), `nombres`/`apellidos`, `email`, `telefono`, `direccion`, `fecha_nacimiento`, `numero_cuenta` (empresa/proveedor), `contratos.sueldo_base`.
- **Tradeoff aceptado:** tras cifrar, el RUT/email solo admite **búsqueda exacta** (vía blind index), no `LIKE` parcial. Las vistas de listado se ajustan a búsqueda exacta o por otros campos.
- **Anonimización (supresión/post-retención):** sobrescritura irreversible: RUT → token HMAC sin reverso, nombres/contacto → `NULL`/placeholder, marca `anonimizado_at`.
- **Auditoría:** se extiende la tabla `auditorias` existente con observers sobre modelos PII + registro de lectura de datos sensibles (payroll). Panel de consulta para DPO/admin.
- **Máscara en UI:** utilidad frontend que muestra `12.345.###-#` salvo permiso explícito de ver completo.

---

## 3. Fases, entregables y asignación de subagentes

> Cada fase es un PR revisable. Modelo sugerido por complejidad. Toda fase incluye **tests** (PHPUnit/Vitest) y actualización de este documento. Commits siempre desde `Nicolas-SalasP`, rama `NSalas-dev`.

### Fase 0 — Fundaciones documentales (Opus, lo hago yo)
- `RAT` (Registro de Actividades de Tratamiento): inventario de datos, finalidad, base de licitud, destinatarios, plazos de conservación.
- `DPIA` (Evaluación de Impacto) del módulo RRHH/payroll.
- Mapeo de controles 21.719 ↔ ISO 27001/27701 (gap analysis).
- Definición de claves, rotación y custodia.
- **Entregables:** `docs/auditoria/RAT.md`, `docs/auditoria/DPIA-RRHH.md`, `docs/auditoria/MAPEO-CONTROLES-ISO.md`.

### Fase 1 — Quick wins críticos de seguridad (Sonnet)
- Cifrar `cuentas_bancarias_empresa.numero_cuenta` y `cuentas_bancarias_proveedores.numero_cuenta` (replicar patrón del empleado) + comando de backfill de datos existentes. **(C1)**
- `SESSION_ENCRYPT=true`; auditar `.env`/config para confirmar Sentry sin PII en prod. **(C2)**
- Utilidad de máscara de RUT en frontend + aplicarla en listados RRHH/clientes/proveedores. **(C3)**
- **Tests:** cifrado/descifrado de cuentas, regresión de pagos/bancos.

### Fase 2 — Cifrado de PII con CipherSweet (Sonnet)
- Instalar y configurar CipherSweet; `CIPHERSWEET_KEY`.
- Migraciones: columnas cifradas + `*_bidx` (blind index) para empleados, clientes, proveedores, cargas familiares, `contratos.sueldo_base`.
- Traits/casts en modelos; ocultar en `$hidden` lo que corresponda.
- Comando `php artisan privacidad:cifrar-existentes` (backfill idempotente, en lotes).
- Ajustar búsquedas a blind index (exacta); ajustar validaciones de unicidad.
- **Tests:** cifrado en reposo, búsqueda por blind index, Previred/facturas siguen leyendo RUT en claro, suite RRHH verde.

### Fase 3 — Log de auditoría de acceso a PII (Sonnet)
- Observers en `Empleado`, `Contrato`, `Liquidacion`, `CargaFamiliar`, cuentas bancarias → registran crear/editar/eliminar en `auditorias`.
- Registro de **lectura** de datos sensibles (liquidaciones, ficha de empleado).
- Endpoint + vista de auditoría para DPO/admin (quién accedió a qué y cuándo).
- **Tests:** cada operación deja traza; lectura de payroll auditada.

### Fase 4 — Consentimiento y política de privacidad (Sonnet)
- Tabla `consentimientos` (titular, finalidad, base de licitud, versión de política, timestamp, IP, otorgado/revocado).
- Política de privacidad **versionada**; flujo de aceptación en onboarding de usuario y al registrar empleado.
- Endpoints otorgar/revocar/consultar; UI de aceptación (sin casillas premarcadas).
- **Tests:** registro de consentimiento, revocación, versionado.

### Fase 5 — Derechos ARCO+ (Sonnet)
- **Acceso/Portabilidad:** endpoint que exporta todos los datos de un titular (JSON + PDF legible).
- **Rectificación:** ya existe (PUT); se documenta y audita.
- **Supresión/anonimización:** anonimiza **respetando retención legal** (bloquea liquidaciones/contratos dentro del plazo; anonimiza al vencer). Marca `anonimizado_at`.
- **Oposición/bloqueo:** marca de bloqueo de tratamiento.
- Registro de solicitudes ARCO + panel DPO para gestionarlas; UI del titular.
- **Tests:** export completo, anonimización irreversible, respeto de retención, bloqueo.

### Fase 6 — Notificación de brechas + runbook (Sonnet + docs)
- Tabla/registro de incidentes; hook de alerta ante eventos sospechosos.
- Runbook 3h/72h (Ley 21.663 al CSIRT) y notificación a la Agencia + afectados (21.719).
- **Entregable:** `docs/auditoria/RUNBOOK-BRECHAS.md` + registro técnico.

### Fase 7 — Preparación de certificación (Opus + docs)
- Consolidar evidencia de controles implementados (Fases 1-6) contra ISO 27001/27701.
- Checklist de cumplimiento 21.719 con estado por control.
- Paquete de evidencia para auditor externo acreditado.
- **Entregable:** `docs/auditoria/EVIDENCIA-CERTIFICACION.md`.

---

## 4. Lo que este plan NO resuelve por sí solo

- **Designación del DPO** y decisiones organizativas (acto formal de la empresa).
- **Auditoría y emisión del certificado ISO** (organismo acreditado externo).
- **Visto bueno legal final** sobre textos de política, bases de licitud y plazos de retención (abogado).
- **Coordinación con Tenri** (operador de plataforma) sobre responsabilidades de encargado/responsable de tratamiento.

---

## 5. Orden de ejecución propuesto

Fase 0 (docs) → **Fase 1 (crítico, ya)** → Fase 2 → Fase 3 → Fase 4 → Fase 5 → Fase 6 → Fase 7.
Cada fase se aprueba y mergea antes de lanzar la siguiente. Los subagentes (Sonnet/Haiku) ejecutan una fase a la vez bajo revisión.

---

## Fase 2 — Operación de despliegue (Fase 2a: cifrado contacto Empleado)

**Estado implementado:** `email`, `telefono`, `direccion` de `empleados` cifrados con
`spatie/laravel-ciphersweet` v1.7 + `paragonie/ciphersweet` v4. Clave en `CIPHERSWEET_KEY`
(64 hex chars / 32 bytes, backend NaCl, provider string).

### Pasos obligatorios al desplegar en producción

1. **Generar la clave de producción** (NUNCA reutilizar la de desarrollo/CI):
   ```
   php artisan ciphersweet:generate-key --show
   ```
   Guardar el resultado (64 hex chars) en el gestor de secretos (AWS Secrets Manager,
   HashiCorp Vault, etc.). Definir la variable de entorno `CIPHERSWEET_KEY` en el servidor
   antes de iniciar el proceso de migración.

2. **Ejecutar la migración de esquema** (amplía email/telefono/direccion a `text`):
   ```
   php artisan migrate
   ```

3. **Backfill de filas existentes** (cifra las filas actualmente en texto plano):
   El comando `ciphersweet:encrypt` de spatie re-guarda cada fila pasando por los
   observers del modelo, lo que activa el cifrado CipherSweet. Requiere la nueva clave
   como segundo argumento (idéntica al valor de `CIPHERSWEET_KEY` en producción):
   ```
   php artisan ciphersweet:encrypt "App\Domains\Rrhh\Models\Empleado" <NUEVA_CLAVE_HEX>
   ```
   El comando acepta un tercer argumento opcional `{sortDirection=asc}` y un cuarto
   `{tablename?}`. En instalaciones con muchos empleados, considerar ejecutarlo en una
   ventana de mantenimiento o en lotes usando una estrategia de migración sin downtime.

4. **Verificar que el backfill fue completo:**
   Tras el backfill no debe quedar ninguna fila con texto en claro. Verificar con:
   ```sql
   SELECT id, email FROM empleados WHERE email NOT LIKE 'nacl:%' LIMIT 10;
   ```
   (El ciphertext de CipherSweet con backend NaCl comienza con el prefijo `nacl:`.)

5. **Advertencia de rotación de clave:** si en el futuro se rota la clave, el mismo
   comando `ciphersweet:encrypt` actúa como re-encriptador: descifra con la clave
   actual y cifra con la nueva. Perder la clave hace irrecuperables los campos cifrados.
   La nueva clave debe quedar en `CIPHERSWEET_KEY` después de la rotación.
