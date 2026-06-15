# Fuentes normativas y técnicas — Protección de datos y ciberseguridad

Índice de las fuentes usadas para el plan de cumplimiento. Donde fue posible se
adjunta el documento original (PDF/Word) en esta carpeta; cuando el entorno de
ejecución bloqueó la descarga binaria (política de egress de red), se referencia
por URL.

> **Nota de entorno:** la descarga directa de varios PDF oficiales falló con
> `Host not in allowlist` (egress restringido del entorno de Claude Code en la
> web). Para adjuntar los binarios, agregar estos hosts al allowlist de la red
> del entorno (ver https://code.claude.com/docs/en/claude-code-on-the-web) y
> re-ejecutar la descarga: `www.aepd.es`, `www.edps.europa.eu`,
> `wikiguias.digital.gob.cl`, `www.bcn.cl`.

## Legislación chilena

| Documento | Referencia | URL |
|---|---|---|
| Ley 21.719 — Protección de Datos Personales | Publicada 13-dic-2024, vigencia plena 1-dic-2026 | https://www.bcn.cl/leychile (buscar 21.719) |
| Ley 19.628 — Protección de la Vida Privada | Régimen vigente, será sustituido | https://www.bcn.cl/leychile/navegar?idNorma=141599 |
| Ley 21.663 — Ley Marco de Ciberseguridad | Publicada 8-abr-2024; arts. 5/8/9 vigentes desde mar-2025 | https://www.bcn.cl/leychile (buscar 21.663) |

## Guías técnicas (seudonimización / hash / anonimización)

| Documento | Autor | URL |
|---|---|---|
| Introducción al hash como técnica de seudonimización de datos personales | AEPD (España) | https://www.aepd.es/guias/estudio-hash-anonimidad.pdf |
| Hash como técnica de seudonimización (versión conjunta) | AEPD + EDPS (UE) | https://www.edps.europa.eu/sites/default/files/publication/19-10-30_aepd-edps_paper_hash_es.pdf |
| Guía introductoria a la anonimización de datos | Gob. Digital Chile | https://wikiguias.digital.gob.cl/documentos/guía_anonimizacion_de_datos.pdf |

## Estándares de certificación

| Documento | Referencia |
|---|---|
| ISO/IEC 27001 — Sistema de Gestión de Seguridad de la Información (SGSI) | Norma certificable por organismo acreditado |
| ISO/IEC 27701 — Extensión de gestión de privacidad (PIMS) sobre 27001 | Cubre el resto de los requisitos de la Ley 21.719 |

## Conclusiones técnicas clave extraídas

1. **Hash simple de RUT = inseguro.** El universo de RUTs válidos es pequeño
   (~30M con dígito verificador determinista); un `SHA-256(rut)` se revierte con
   tabla precalculada. Se requiere **HMAC con clave secreta (pepper)** separada
   de la base de datos, o cifrado reversible. (AEPD/EDPS).
2. **Seudonimización ≠ anonimización.** Mientras exista la posibilidad de
   revertir (clave, tabla de correspondencia), el dato sigue siendo personal y
   protegido por la ley. (Gob. Digital Chile / AEPD).
3. **ISO 27001 cubre ~50-60 % de la Ley 21.719**; la 27701 cierra la brecha de
   privacidad.
