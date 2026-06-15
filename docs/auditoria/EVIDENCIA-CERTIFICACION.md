# Paquete de evidencia para certificación (ISO 27001/27701 · Ley 21.719)

**Propósito:** consolidar la evidencia de los controles técnicos implementados,
mapeados a la Ley 21.719, la Ley 21.663 y los controles ISO/IEC 27001 + 27701,
para una auditoría de certificación o una fiscalización de la Agencia.
**Última actualización:** 2026-06-14. **Alcance:** capa técnica del ERP (Tenri).

> **Importante:** este paquete cubre los **controles técnicos y la evidencia
> documental**. La certificación ISO la emite un **organismo acreditado** (etapa 1
> documental + etapa 2 in situ) y la conformidad legal final requiere **visto bueno
> de un abogado**. Quedan fuera de la capa técnica: designación formal del DPO,
> contrato de encargo (DPA) con el operador, y la auditoría externa.

## 1. Controles implementados (con evidencia en el repositorio)

| Control | Implementación | Evidencia (código/test) |
|---|---|---|
| Cifrado en reposo de cuentas bancarias | Cast `encrypted` en cuentas de empresa y proveedor | `app/Domains/Tesoreria/Models/CuentaBancaria*.php`; `tests/Feature/Tesoreria/CifradoCuentasBancariasTest.php` |
| Cifrado de sesión + Sentry sin PII | `SESSION_ENCRYPT=true`, `SENTRY_SEND_DEFAULT_PII=false` | `.env.example` |
| Máscara de RUT en UI | `enmascararIdentificador` en listados | `Frontend/src/Utilidades/identificadores.js` (+ test) |
| Cifrado de PII del empleado (CipherSweet) | RUT (blind index), contacto, fecha nac., sueldo | `app/Domains/Rrhh/Models/{Empleado,Contrato,CargaFamiliar}.php`; `tests/Feature/Rrhh/Cifrado*Test.php` |
| Búsqueda sin descifrar (blind index) | `whereBlind` sobre `empleado_rut_index` | `app/Domains/Rrhh/Services/EmpleadoService.php`; `CifradoRutEmpleadoTest.php` |
| Log de auditoría de acceso/cambio a PII | Observer sobre modelos PII + endpoint DPO | `app/Domains/Core/Observers/AuditoriaPiiObserver.php`, `Controllers/AuditoriaController.php`; `tests/Feature/Core/AuditoriaPiiTest.php` |
| Consentimiento + política versionada | `politicas_privacidad`, `consentimientos`, banner no bloqueante | `app/Domains/Core/{Models,Controllers}/...Privacidad...`; `tests/Feature/Core/PrivacidadTest.php` |
| Derechos ARCO+ | Exportación, bloqueo, supresión con retención | `app/Domains/Rrhh/Services/ArcoService.php`; `tests/Feature/Rrhh/ArcoTest.php` |
| Registro de incidentes + runbook 3h/72h | `incidentes_seguridad` + `RUNBOOK-BRECHAS.md` | `app/Domains/Core/...IncidenteSeguridad...`; `docs/auditoria/RUNBOOK-BRECHAS.md` |
| Control de acceso (RBAC) + multi-tenant | Middleware `permiso:*` + `EmpresaScope` | `app/Http/Middleware/EnsureUserHasPermission.php`; `EmpresaScopeCoberturaTest.php` |
| Contraseñas hasheadas | bcrypt(12) | `config/hashing.php`, `.env.example` |

## 2. Documentación de gobernanza (artefactos)

| Artefacto | Ubicación |
|---|---|
| Plan de cumplimiento por fases | `docs/auditoria/CUMPLIMIENTO-LEY-21719-PLAN.md` |
| Registro de Actividades de Tratamiento (RAT) | `docs/auditoria/RAT.md` |
| Evaluación de Impacto (DPIA) RRHH | `docs/auditoria/DPIA-RRHH.md` |
| Mapeo de controles Ley ↔ ISO | `docs/auditoria/MAPEO-CONTROLES-ISO.md` |
| Runbook de brechas | `docs/auditoria/RUNBOOK-BRECHAS.md` |
| Fuentes normativas | `docs/auditoria/fuentes/FUENTES.md` |

## 3. Checklist de cumplimiento Ley 21.719 (estado técnico)

| Requisito | Estado | Nota |
|---|---|---|
| Medidas de seguridad según riesgo (cifrado, seudonimización) | ✅ | CipherSweet + cast encrypted |
| Control de acceso a datos personales | ✅ | RBAC + multi-tenant |
| Registro de actividades de tratamiento | ✅ | RAT |
| Evaluación de impacto | ✅ | DPIA RRHH |
| Derecho de acceso / portabilidad | ✅ | export ARCO |
| Derecho de rectificación | ✅ | edición de ficha |
| Derecho de supresión | ✅ (con retención) | parcial bajo plazo / total al vencer |
| Derecho de oposición / bloqueo | ✅ | bloqueo ARCO |
| Consentimiento (donde aplica) | ✅ | política versionada + registro |
| Notificación de brechas | ✅ (procedimiento + registro) | runbook + `incidentes_seguridad` |
| Auditoría / trazabilidad | ✅ | log de auditoría PII |
| Delegado de Protección de Datos (DPO) | ⏳ organizativo | acto formal de la empresa |
| Contrato de encargo (DPA) con el operador | ⏳ legal | redactar y firmar |
| Certificación ISO 27001/27701 | ⏳ externo | auditor acreditado |

## 4. Pendientes que NO son de código (para la organización)

1. **Designar el DPO** y el responsable de ciberseguridad (Ley 21.663) por acto formal.
2. **Firmar el DPA** entre Tenri (encargado) y cada empresa cliente (responsable).
3. **Definir y completar** los contactos del runbook (CSIRT, Agencia).
4. **Contratar la auditoría** de certificación ISO 27001 + 27701.
5. **Revisión legal** de los textos de política de privacidad y bases de licitud.
6. **Rotación y custodia de claves** (`CIPHERSWEET_KEY`, `APP_KEY`) en un KMS/secret manager en producción.
