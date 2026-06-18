# Mapeo de controles — Ley 21.719 ↔ ISO 27001/27701 ↔ estado en el ERP

**Propósito:** gap analysis que cruza las obligaciones de la Ley 21.719 (y la Ley
21.663 de ciberseguridad) con los controles de ISO/IEC 27001 + 27701 y el estado
real del ERP, indicando la fase del plan que lo cierra.
**Fecha:** 2026-06-13. **Estado:** vivo.

Leyenda estado: ✅ implementado · 🟡 parcial · ⏳ planificado · 🔴 ausente.

## 1. Principios y bases de licitud (Ley 21.719)

| Requisito legal | Control ISO 27701 | Estado | Fase |
|---|---|---|---|
| Licitud, finalidad, proporcionalidad, calidad | A.7.2 (finalidad), 7.4 (minimización) | 🟡 (documentado en RAT/DPIA) | 0 |
| Base de licitud identificada por tratamiento | 7.2.2 | 🟡 (RAT) | 0 |
| Consentimiento libre, específico, informado | 7.2.3, 7.2.4 | 🔴 | 4 |
| Tratamiento de datos sensibles (salud) reforzado | 7.2.1 + cifrado | 🟡 | 2/4 |

## 2. Derechos de los titulares (ARCO+)

| Derecho | Control ISO 27701 | Estado | Fase |
|---|---|---|---|
| Acceso | 7.3.2 | 🔴 (sin endpoint) | 5 |
| Rectificación | 7.3.6 | ✅ (PUT empleados/clientes) | — |
| Cancelación / Supresión | 7.3.7 | 🔴 (soft-delete no anonimiza) | 5 |
| Oposición / Bloqueo | 7.3.3 | 🔴 | 5 |
| Portabilidad | 7.3.8 | 🔴 | 5 |

## 3. Medidas técnicas de seguridad (Ley 21.719 + Ley 21.663 + ISO 27001 Anexo A)

| Medida | Control ISO 27001 | Estado | Fase |
|---|---|---|---|
| Cifrado en reposo de PII | A.8.24 (criptografía) | 🟡 (solo cuenta empleado + SII) | 1/2 |
| Cifrado de cuentas bancarias empresa/proveedor | A.8.24 | 🔴 (texto plano) | 1 |
| Seudonimización / blind index | A.8.11 (enmascaramiento) | 🔴 | 2 |
| Control de acceso (RBAC) | A.5.15, A.8.2, A.8.3 | ✅ (permisos + `empresa_id`) | — |
| Cifrado en tránsito (TLS) | A.8.24 | ✅ (HTTPS) | — |
| Contraseñas hasheadas | A.8.5 | ✅ (bcrypt 12) | — |
| Cifrado de sesión | A.8.24 | 🔴 (`SESSION_ENCRYPT=false`) | 1 |
| Registro/auditoría de acceso a PII | A.8.15 (logging) | 🔴 (tabla existe, subutilizada) | 3 |
| Enmascaramiento de RUT en UI | A.8.11 | 🔴 | 1 |
| Cifrado de respaldos | A.8.13 | 🔴 (backup sin cifrar PII) | 2 |
| Gestión de claves (separación, rotación) | A.8.24 | ⏳ (`CIPHERSWEET_KEY` separada) | 0/2 |

## 4. Gobernanza y respuesta (Ley 21.719 + Ley 21.663)

| Requisito | Control ISO 27701/27001 | Estado | Fase |
|---|---|---|---|
| Registro de Actividades de Tratamiento (RAT) | 7.2.8 | ✅ (`docs/auditoria/RAT.md`) | 0 |
| Evaluación de Impacto (DPIA) | 7.2.5 | ✅ (`docs/auditoria/DPIA-RRHH.md`) | 0 |
| Delegado de Protección de Datos (DPO) | 6.x (organización) | 🔴 (acto organizativo) | externo |
| Notificación de brechas (Agencia + afectados) | A.5.24-A.5.26 (gestión incidentes) | 🔴 | 6 |
| Reporte de incidentes 3h/72h (CSIRT, Ley 21.663) | A.5.24-A.5.26 | 🔴 | 6 |
| Contrato de encargo (DPA) Tenri ↔ cliente | 7.2.6 | 🔴 (legal) | externo |

## 5. Resumen de cobertura

| Bloque | Cobertura actual | Tras Fases 1-6 |
|---|---|---|
| Principios y licitud | 🟡 ~40 % | ✅ ~90 % |
| Derechos ARCO+ | 🔴 ~20 % | ✅ ~95 % |
| Medidas técnicas | 🟡 ~45 % | ✅ ~90 % |
| Gobernanza / respuesta | 🟡 ~35 % | 🟡 ~75 % (resto requiere actos externos: DPO, DPA, auditor ISO) |

**Camino a certificación:** completar Fases 1-6 deja la **capa técnica y documental**
lista. La certificación ISO 27001/27701 requiere además: (a) designación formal de
DPO y responsable de ciberseguridad, (b) DPA con Tenri, (c) auditoría de un
**organismo acreditado** (etapa 1 documental + etapa 2 in situ; certificado a 3
años con vigilancia anual), y (d) visto bueno legal de los textos de privacidad.
