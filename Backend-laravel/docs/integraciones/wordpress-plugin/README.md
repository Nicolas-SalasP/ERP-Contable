# Tenri Inventario Sync — plugin WordPress genérico

Implementación de referencia (Fase 3 del dominio Integraciones, ver `../CONTRATO-V1.md`) para
que cualquier sitio WordPress futuro pueda reflejar el inventario visible del ERP sin escribir
código nuevo. No fue probado contra un WordPress real (no existía uno al momento de escribirlo) —
es un punto de partida completo, no una entrega verificada en producción.

## Qué hace

- Consume `GET /api/integraciones/v1/inventario/productos` con una API-key propia (scope
  `inventario:leer`), igual que lo haría cualquier otro consumidor de la API.
- Guarda los productos en un Custom Post Type propio (`tenri_producto`), **no** en WooCommerce —
  para no asumir que el sitio use esa plataforma ni pisar un catálogo existente.
- Sincroniza cada 15 minutos vía WP-Cron (o manualmente desde Ajustes → Tenri Inventario).

## Instalación

1. Copiar la carpeta `tenri-inventario-sync/` a `wp-content/plugins/` del sitio destino.
2. Activar el plugin desde el panel de WordPress.
3. En el ERP, emitir una API-key con scope `inventario:leer` (`POST /api/integraciones/admin/keys`,
   requiere sesión y permiso `integraciones.api.gestionar`).
4. Cargar la URL del ERP y esa API-key en **Ajustes → Tenri Inventario**.

## Si el sitio real usa WooCommerce

El punto de enganche es `Tenri_Inventario_Sync_Service::crear()`/`actualizar()`
(`includes/class-sync-service.php`): en vez de `wp_insert_post()` con el CPT propio, mapear a
`wc_get_product()`/`WC_Product::save()`. No se hizo de entrada porque no todo WordPress usa
WooCommerce y forzarlo como dependencia dura habría sido asumir de más sin un sitio real
para validarlo.

## Qué falta antes de un sitio real

- Probarlo contra un WordPress real (no se hizo — no había ninguno disponible).
- Decidir si conviene WooCommerce en vez del CPT propio.
- Manejo de imágenes de producto (el contrato v1 de la API no expone imágenes hoy).
