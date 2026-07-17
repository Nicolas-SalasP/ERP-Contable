# API de Integraciones — Contrato v1

API pública del ERP para que sistemas externos (Tenri-Web-Page, Tenri-Admin, WordPress u otros)
consuman datos del ERP sin requerir código nuevo por cada integración. Autenticación por
API-key con scopes — **no** es el mismo mecanismo HMAC que usa Tenri-Web-Page para provisioning
de usuarios/planes (ver `app/Http/Middleware/VerifyWebApiKey.php`), ese sigue siendo exclusivo
de ese flujo.

Este documento es el contrato que cualquier consumidor debe respetar. Cambios incompatibles se
publican como `v2` conviviendo con `v1` — nunca se rompe `v1` en el lugar.

## Autenticación

Header `Authorization: Bearer <token>` (o `X-Api-Key: <token>` como alternativa). El token tiene
formato `tnri_<prefijo>_<secreto>` y se obtiene desde el ERP (`POST /api/integraciones/admin/keys`,
requiere sesión y permiso `integraciones.api.gestionar`) — **se muestra una única vez**, no se
puede recuperar después. Si se pierde, hay que rotarlo (`POST .../keys/{id}/rotar`), lo que
invalida el token anterior.

La empresa dueña de la key debe tener el módulo `integraciones.api` habilitado en su plan
(`module_keys`); si el plan lo pierde, todas las requests devuelven 401 aunque el token siga
siendo válido en base de datos.

## Scopes

Cada key se emite con uno o más scopes; una request sin el scope requerido devuelve **403**, no 401.

| Scope | Habilita |
|-------|----------|
| `inventario:leer` | `GET` de productos (listado y detalle) |
| `inventario:escribir` | `PATCH` del flag `visible_web` |

## Endpoints v1

Base: `https://<dominio-erp>/api/integraciones/v1`

| Método | Ruta | Scope | Descripción |
|--------|------|-------|-------------|
| GET | `/ping` | `inventario:leer` | Verifica que la key/scope son válidos. Devuelve `{ data: { empresa_id } }`. |
| GET | `/inventario/productos` | `inventario:leer` | Lista paginada de productos de la empresa dueña de la key. |
| GET | `/inventario/productos/{sku}` | `inventario:leer` | Detalle de un producto por SKU. |
| PATCH | `/inventario/productos/{sku}/visible-web` | `inventario:escribir` | Cambia el flag `visible_web` de un producto. |

### Filtros de `GET /inventario/productos`

Todos opcionales, se combinan con AND:

| Parámetro | Tipo | Efecto |
|-----------|------|--------|
| `search` | string | Busca en `sku` y `nombre` (`LIKE %valor%`). |
| `sku` | string | Filtra por SKU exacto. |
| `activo` | boolean (`1`/`0`) | Filtra por producto activo/inactivo. |
| `visible_web` | boolean (`1`/`0`) | Filtra por el flag de publicación. |
| `updated_since` | fecha ISO 8601 | Solo productos modificados desde esa fecha (útil para sync incremental). |
| `limit` | int | Tamaño de página, default 50. |

### Formato de respuesta

Listado:

```json
{
  "success": true,
  "data": [ { /* ProductoIntegracionResource */ } ],
  "pagination": { "total": 120, "total_pages": 3, "page": 1 }
}
```

Detalle / toggle:

```json
{ "success": true, "data": { /* ProductoIntegracionResource */ } }
```

### Campos de `ProductoIntegracionResource` (contrato estable)

| Campo | Tipo | Notas |
|-------|------|-------|
| `sku` | string | Clave natural — úsala para el mapeo en el sistema consumidor, no el `id` interno del ERP. |
| `nombre` | string | |
| `descripcion` | string\|null | |
| `precio_venta_neto` | decimal | Neto, sin IVA. |
| `afecto_iva` | boolean | |
| `codigo_barra` | string\|null | |
| `stock_actual_total` | decimal | Suma de stock en todas las bodegas de la empresa. |
| `activo` | boolean | |
| `visible_web` | boolean | Flag de intención de publicación — el ERP es la fuente de verdad; el consumidor decide si además la respeta localmente. |
| `actualizado_at` | string\|null (ISO 8601) | Para sincronización incremental vía `updated_since`. |

**Campos que nunca aparecen y no se deben esperar**: `costo_promedio`, `metodo_valorizacion`,
`bodega_defecto_id`, ni ningún otro campo interno/contable. Si una integración necesita un campo
nuevo, se agrega explícitamente al Resource (con versión si es incompatible) — nunca se expone el
modelo completo.

## Errores

Sigue el mismo contrato HTTP general del proyecto (`docs/CONTRATO-HTTP.md`):

| Código | Causa en esta API |
|--------|--------------------|
| 401 | Falta el token, es inválido, está revocado/expirado, o el plan de la empresa no tiene `integraciones.api`. |
| 403 | Token válido pero sin el scope requerido para el endpoint. |
| 404 | El SKU no existe **o pertenece a otra empresa** (nunca se distingue — mismo criterio multitenant del resto del ERP). |
| 422 | Body inválido (ej. `visible_web` no boolean). |
| 429 | Rate limit `integraciones-empresa`: 60 req/min por key (no por IP). |

## Versionado

- El prefijo de ruta (`v1`) es parte del contrato. Un cambio incompatible (quitar/renombrar un
  campo, cambiar un tipo, cambiar la semántica de un filtro) se publica como `v2` — `v1` sigue
  funcionando mientras tenga consumidores activos.
- Agregar un campo nuevo al Resource, o un filtro/endpoint nuevo, **no** requiere versión nueva.

## Roadmap (fuera de alcance de v1)

- **Fase 2** (siguiente): Tenri-Web-Page/Tenri-Admin consumiendo este contrato para reemplazar su
  catálogo local `products`/`categories` (hoy sin ningún vínculo al ERP) por datos reales de
  Inventario, mapeando por `sku`. Evaluar si conviene reusar el canal HMAC bidireccional ya
  existente (`VerifyErpApiKey` en `Tenri-Web-Page/backend`) para que el ERP empuje cambios en vez
  de que Web haga polling.
- **Fase 3**: plugin WordPress u otros terceros — mismo mecanismo de API-key, sin código nuevo en
  el ERP salvo, eventualmente, nuevos scopes si se exponen otros dominios además de inventario.
  Implementación de referencia (no probada contra un WordPress real, no había ninguno disponible):
  `wordpress-plugin/` en esta misma carpeta.
- Webhooks push desde el ERP: no existen en v1 (solo lectura/escritura por request del
  consumidor). Si se necesitan a futuro, el punto de partida es el bus de eventos interno ya
  existente (`InventarioEventoIntegracionService`), hoy solo usado para integraciones internas
  (ej. Contabilidad).
