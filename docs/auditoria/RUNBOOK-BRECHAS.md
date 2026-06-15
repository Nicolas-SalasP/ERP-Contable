# Runbook de brechas e incidentes de seguridad

**Obligación:** Ley 21.663 (notificación al CSIRT Nacional) + Ley 21.719
(notificación a la Agencia de Protección de Datos y a los afectados).
**Audiencia:** DPO / responsable de ciberseguridad / administrador.
**Última actualización:** 2026-06-14.

> Este runbook define el procedimiento ante un incidente de seguridad o brecha de
> datos personales. El **registro técnico** de incidentes vive en la tabla
> `incidentes_seguridad` (endpoints `/api/incidentes`, solo admin/DPO), que captura
> la línea de tiempo legal exigida.

## 1. Plazos legales (memorizar)

| Hito | Plazo | Norma | Campo en el registro |
|---|---|---|---|
| Detección del incidente | T0 | — | `detectado_at` |
| **Alerta temprana al CSIRT Nacional** | **≤ 3 horas** desde que se tiene conocimiento, si tiene efectos significativos | Ley 21.663 art. 9 | `alerta_temprana_at` |
| **Reporte completo al CSIRT** | **≤ 72 horas** | Ley 21.663 art. 9 | `reporte_csirt_at` |
| Notificación a la Agencia de Protección de Datos | sin dilación indebida | Ley 21.719 | `notificacion_agencia_at` |
| Notificación a los titulares afectados | sin dilación indebida cuando hay alto riesgo para sus derechos | Ley 21.719 | `notificacion_afectados_at` |
| Reporte final post-contención | tras contener | Ley 21.663 | `estado = CERRADO` |

## 2. Procedimiento (paso a paso)

### Fase A — Detección y registro (inmediato)
1. Quien detecta el incidente avisa al DPO/responsable de ciberseguridad.
2. El DPO crea el incidente en el registro (`POST /api/incidentes`) con
   `detectado_at`, severidad estimada y descripción. **No** se vuelcan datos
   personales en el registro: solo **categorías** de datos afectados
   (`categorias_datos_afectados`) y un número estimado de afectados.

### Fase B — Triage (primeras horas)
3. Determinar si el incidente tiene **efectos significativos** (compromiso de PII,
   indisponibilidad de servicios críticos, exfiltración). Si sí → activar el reloj
   de 3h.
4. Contener: revocar tokens/credenciales comprometidas, aislar el componente,
   rotar claves (incluida `CIPHERSWEET_KEY`/`APP_KEY` si se sospecha exposición de
   material criptográfico).

### Fase C — Notificación (3h / 72h)
5. **≤ 3h:** alerta temprana al CSIRT Nacional → registrar `alerta_temprana_at`.
6. **≤ 72h:** reporte completo al CSIRT → registrar `reporte_csirt_at`.
7. Si hay datos personales comprometidos: notificar a la **Agencia** (registrar
   `notificacion_agencia_at`) y, si hay alto riesgo para los titulares, a los
   **afectados** (registrar `notificacion_afectados_at`).

### Fase D — Contención y cierre
8. Pasar `estado` a `CONTENIDO` cuando el vector está cerrado.
9. Análisis de causa raíz y lecciones aprendidas (adjuntar a `docs/auditoria/`).
10. Pasar `estado` a `CERRADO`. Conservar el registro como evidencia.

## 3. Contactos y canales (completar por la organización)

| Rol | Responsable | Contacto |
|---|---|---|
| DPO / Delegado de Protección de Datos | *(por designar)* | — |
| Responsable de ciberseguridad | *(por designar)* | — |
| CSIRT Nacional | ANCI | https://www.csirt.gob.cl/ |
| Agencia de Protección de Datos Personales | *(según entre en operación)* | — |

## 4. Qué NO hacer

- **No** registrar valores de PII en el incidente (RUT, nombres, etc.): solo
  categorías. El registro de incidentes no debe convertirse en una segunda fuga.
- **No** esperar a tener el análisis completo para la alerta temprana de 3h: la
  alerta es preliminar; el detalle va en el reporte de 72h.
- **No** borrar logs/auditoría durante un incidente: son evidencia.

## 5. Relación con el resto del cumplimiento

- El **log de auditoría de PII** (Fase 3, tabla `auditorias`) es la fuente para
  reconstruir qué datos se accedieron/modificaron durante un incidente.
- El **cifrado en reposo** (Fases 1-2, CipherSweet) reduce el impacto de una
  exfiltración de base de datos: sin `CIPHERSWEET_KEY`/`APP_KEY`, el PII es
  ciphertext.
- Los **derechos ARCO+** (Fase 5) permiten responder solicitudes de titulares
  afectados tras una brecha.
