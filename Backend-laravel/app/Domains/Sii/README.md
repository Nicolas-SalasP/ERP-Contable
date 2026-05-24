# Modulo SII — Facturacion Electronica DTE Chile

Dominio aislado para la integracion con el Servicio de Impuestos Internos (SII)
de Chile. Cubre la emision, firma, envio y consulta de Documentos Tributarios
Electronicos (DTE): facturas afectas y exentas, notas de credito y debito,
guias de despacho y boletas electronicas.

## Proposito

Encapsular toda la logica relacionada con la normativa SII en un unico dominio
para:

- **Cumplir la exigencia de trazabilidad auditable** de cada DTE emitido
  (`manual_certificacion.pdf`).
- **Aislar** la complejidad legal y tecnica del SII del resto del ERP, evitando
  acoplar el dominio Comercial.
- **Facilitar auditorias externas** y migraciones de version del esquema DTE.

## Convenciones

- **Prefijo `Sii`** en todos los modelos del dominio: `SiiDteEmitido`,
  `SiiCaf`, `SiiFolioReservado`, `SiiCertificadoEmpresa`, etc. Evita
  colisiones con entidades de otros dominios.
- **Aislamiento DDD** estricto: el modulo NO importa modelos de
  `App\Domains\Comercial` directamente. La integracion con el flujo comercial
  se hara por eventos (Fase 6).
- **Separacion de capas:**
  - `Controllers/` — adaptadores HTTP, validacion de entrada, formateo de salida.
  - `Services/` — logica de negocio (firma, generacion XML, transporte).
  - `Models/` — Eloquent, sin reglas de negocio.
  - `Jobs/` — operaciones asincronas (envio SII, polling de estado).
  - `Events/` y `Listeners/` — comunicacion intra-dominio e integracion futura.
  - `Database/Migrations/` — esquema propio del dominio (cargado por el provider).
  - `Routes/` — rutas con prefix `api/sii`, autenticacion Sanctum.

## Stack normativo (lectura obligatoria antes de implementar)

Los documentos en `docs/sii-normativa/` rigen sobre cualquier interpretacion
local:

| Archivo | Contenido |
|---|---|
| `formato_dte_202602.pdf` | Formato XML del DTE v2.5 (febrero 2026). Autoritativo. |
| `instructivo_emision.pdf` | Flujo de emision, firma y envio. |
| `manual_certificacion.pdf` | Proceso de certificacion empresa ante SII. |

## Algoritmo de firma (NO NEGOCIABLE)

Fijado por el XSD oficial `xmldsignature_v10.xsd`:

- **Signature method:** `http://www.w3.org/2000/09/xmldsig#rsa-sha1`
- **Digest method:** `http://www.w3.org/2000/09/xmldsig#sha1`
- **Canonicalization:** `http://www.w3.org/TR/2001/REC-xml-c14n-20010315`
  (C14N 1.0)
- **Encoding XML:** `ISO-8859-1` (no UTF-8). El digest se calcula SOBRE el
  XML ya convertido a ISO-8859-1, en este orden: conversion -> digest -> firma.

Cualquier cambio a estas constantes requiere actualizacion paralela en
`config/sii.php` y referencia documentada al cambio normativo del SII.

Para DTE estandar (facturas, notas, guias) hay **triple firma anidada**:

1. **TED** firmado con la llave privada del CAF (no del emisor).
2. **`<Documento ID="...">`** firmado con el certificado del EMISOR.
3. **`<SetDTE ID="SetDoc">`** firmado con el certificado del EMISOR.

Las tres firmas son requisito previo a la validacion de schema en el SII.

## Boletas electronicas (39/41)

Flujo PARALELO al de facturas:

- Sobre: `EnvioBOLETA_v11.xsd` (NO `EnvioDTE_v10`).
- Endpoint REST: `palena.sii.cl/recursos/v1/boleta.electronica.envio`.
- RCOF diario obligatorio (`ConsumoFolio_v10`).

Cuando aterrice (Fase 6-bis), `EnvioBoletaService` quedara separado de
`EnvioDteService`.

## Estados del DTE

```
BORRADOR -> FOLIO_RESERVADO -> XML_GENERADO -> FIRMADO -> ENVIADO_SII
  -> EN_PROCESO_SII -> ACEPTADO
                    -> ACEPTADO_CON_REPAROS
                    -> RECHAZADO -> REEMITIDO o ANULADO_CON_NC
```

Cada transicion se persiste append-only en `sii_dte_estado_log`. Tabla
no actualizable. Retencion 6 anios por exigencia tributaria.

## Folios huerfanos (politica)

- Si la falla ocurre con `estado >= FIRMADO`: emitir Nota de Credito interna.
- Si la falla ocurre con `estado < FIRMADO`: marcar folio
  `ANULADO_FALLO_INTERNO` y reportarlo en libros / RCOF. No emitir NC (no
  existe DTE original).

## Contingencia ante caida del SII

- Reservar folio + persistir DTE firmado en `sii_dte_pendiente_envio`.
- `MonitorearSiiHealthJob` hace ping a `getSeed` cada 5 minutos.
- `DespacharColaContingenciaJob` despacha FIFO al restablecerse el servicio.
- El SII NO usa webhooks; el modelo es pull (`QueryEstUp`, `QueryEstDte`).

## Tests del modulo

```bash
composer test -- --filter=Sii
```

Los tests usan `RefreshDatabase` + helper `Tests\Concerns\PreparaEntornoBase`.
SQLite en memoria por defecto; CI los corre tambien contra MySQL 8.0.

## Roadmap de fases

| Fase | Objetivo |
|---|---|
| **F0** (esta) | Esqueleto del modulo + wiring (provider, ruta ping, config, log). |
| F1 | Tablas maestras (comunas, acteco, formas de pago, impuestos, unidades) + extension de empresas/clientes/productos + `facturas_detalles`. |
| F2 | Configuracion SII por empresa + carga segura de certificado .pfx. |
| F3 | Gestion de CAF + reserva atomica de folios. |
| F4 | Generacion XML + triple firma XMLDSig + TED + validacion XSD. |
| F5 | Transporte: Semilla -> Token -> Envio -> Consulta. Polling con backoff. |
| F6 | Integracion al flujo Comercial via evento `FacturaCreada`. |
| F6-bis | Boletas electronicas (39/41) + RCOF. |
| F6-ter | Guias de despacho (52) con campos Res. Ex. SII 154/2025. |
| F7 | Representacion impresa PDF con timbre PDF417. |
| F8 | Notas de credito y libros LCE. Acuse de recibo (Ley 19.983). |
| F9 | Endurecimiento, certificacion oficial SII, runbooks. |

AEC (cesion / factoring) queda fuera de v1.0 -> backlog en
`app/Domains/SiiCesion/`.
