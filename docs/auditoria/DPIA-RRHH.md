# Evaluación de Impacto en Protección de Datos (DPIA) — Módulo RRHH / Remuneraciones

**Obligación:** Ley 21.719 (evaluación de impacto para tratamientos de alto riesgo).
**Tratamiento evaluado:** A1 del RAT — gestión de personal y remuneraciones.
**Fecha:** 2026-06-13. **Estado:** inicial (se revisa tras cada fase implementada).

## 1. Por qué requiere DPIA

El tratamiento de RRHH califica como **alto riesgo** porque:
- Trata **datos sensibles de salud** (Fonasa/Isapre, plan de salud).
- Trata datos de **menores de edad** (cargas familiares: hijos).
- Procesa datos a **gran escala** y de forma sistemática (toda la nómina).
- Incluye datos **financieros** (sueldo, cuenta bancaria) cuyo compromiso causa daño directo.
- Hay **evaluación/decisión** sobre la persona (cálculo de liquidaciones, finiquitos).

## 2. Descripción del tratamiento

| Aspecto | Detalle |
|---|---|
| Datos | Ver RAT A1 (identificación, contacto, laboral, salud, previsión, bancario). |
| Finalidad | Ejecutar el contrato de trabajo y cumplir obligaciones legales (pago de sueldos, Previred, libro de remuneraciones, finiquitos). |
| Necesidad | Los datos son **necesarios y proporcionales**: el RUT, sueldo y datos previsionales son exigidos por la normativa laboral/previsional chilena. No hay exceso evidente. |
| Flujos | Ingreso manual → cálculo de liquidación → archivo Previred (CSV) → centralización contable. |

## 3. Riesgos identificados y mitigaciones

| # | Riesgo | Prob. | Impacto | Mitigación | Estado |
|---|---|---|---|---|---|
| R1 | Fuga de RUT/nombres/sueldos por acceso a BD o backup (texto plano) | Media | Alto | Cifrado en reposo CipherSweet (Fase 2); cifrado de backups | ⏳ Fase 2 |
| R2 | Fuga de número de cuenta bancaria (texto plano en cuentas empresa/proveedor) | Media | Crítico | Cifrado AES (Fase 1) | ⏳ Fase 1 |
| R3 | Acceso indebido de un usuario autorizado a nóminas que no le corresponden | Media | Alto | Permisos por módulo (ya); **auditoría de acceso** (Fase 3); evaluar row-level | 🟡 parcial |
| R4 | Datos de salud tratados sin base/medidas reforzadas | Baja | Alto | Consentimiento donde aplique + cifrado (Fases 2/4) | ⏳ Fase 4 |
| R5 | Imposibilidad de ejercer derechos ARCO+ del trabajador | Alta | Medio | Endpoints de acceso/portabilidad/supresión (Fase 5) | ⏳ Fase 5 |
| R6 | Conservación más allá de lo necesario | Media | Medio | Política de retención + anonimización al vencer (Fase 5) | ⏳ Fase 5 |
| R7 | Falta de trazabilidad ante una brecha | Media | Alto | Audit log (Fase 3) + runbook de brechas (Fase 6) | ⏳ Fase 3/6 |
| R8 | Sesión/Sentry filtrando PII | Baja | Medio | `SESSION_ENCRYPT=true`, `SENTRY_SEND_DEFAULT_PII=false` (Fase 1) | ⏳ Fase 1 |

## 4. Evaluación de proporcionalidad

- **Licitud:** ejecución de contrato + obligación legal → adecuada para el núcleo del tratamiento.
- **Minimización:** los campos almacenados se justifican por exigencia normativa (Previred 105 campos). No se detecta recolección excesiva.
- **Datos sensibles (salud):** limitados a lo previsional obligatorio; requieren cifrado y, donde no sea obligación legal pura, consentimiento.
- **Menores (cargas):** mínimos necesarios (parentesco, RUT, fecha nac. para asignación familiar). Justificado.

## 5. Conclusión

El tratamiento es **lícito y proporcional en su finalidad**, pero su **nivel de
seguridad técnica actual es insuficiente** para el riesgo (PII en texto plano,
sin auditoría de acceso, sin ARCO+). El plan de fases 1-6 reduce el riesgo
residual a **aceptable**. La DPIA se **re-evalúa al cierre de cada fase**; no se
considera el tratamiento conforme hasta completar al menos Fases 1-3 y 5.
