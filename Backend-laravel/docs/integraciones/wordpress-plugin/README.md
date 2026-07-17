# Tenri Inventario Sync — plugin WordPress genérico

Implementación de referencia (Fase 3 del dominio Integraciones, ver `../CONTRATO-V1.md`) para
que cualquier sitio WordPress futuro pueda reflejar el inventario visible del ERP sin escribir
código nuevo. No fue probado contra un WordPress real (no existía uno al momento de escribirlo) —
es un punto de partida completo, no una entrega verificada en producción.

## Qué hace

- Consume `GET /api/integraciones/v1/inventario/productos` con una API-key propia (scope
  `inventario:leer`), igual que lo haría cualquier otro consumidor de la API.
- **Detecta automáticamente si WooCommerce está activo** (`class_exists('WooCommerce')`, evaluado
  en cada sync) y sincroniza hacia el destino que corresponda, sin configuración manual:
  - **Con WooCommerce**: crea/actualiza `WC_Product_Simple` (nombre, precio, stock, descripción)
    vía `Tenri_Inventario_Sync_Woocommerce_Adapter`.
  - **Sin WooCommerce**: usa un Custom Post Type propio (`tenri_producto`) vía
    `Tenri_Inventario_Sync_Cpt_Adapter`, para no depender de ningún plugin de tienda.
  - Si WooCommerce se instala/desinstala después, la próxima sync cambia de destino sola — no
    hay que tocar nada.
- Sincroniza cada 15 minutos vía WP-Cron (o manualmente desde Ajustes → Tenri Inventario, que
  también muestra qué destino está usando ahora mismo).

## Instalación

1. Copiar la carpeta `tenri-inventario-sync/` a `wp-content/plugins/` del sitio destino.
2. Activar el plugin desde el panel de WordPress.
3. En el ERP, emitir una API-key con scope `inventario:leer` (`POST /api/integraciones/admin/keys`,
   requiere sesión y permiso `integraciones.api.gestionar`).
4. Cargar la URL del ERP y esa API-key en **Ajustes → Tenri Inventario**.

## Estructura de los adaptadores

Ambos adaptadores (`class-cpt-adapter.php` / `class-woocommerce-adapter.php`) implementan el
mismo contrato informal (`buscarPorSku(string): ?int`, `crear(array): void`,
`actualizar(int, array): void`); `Tenri_Inventario_Sync_Service` no sabe ni le importa cuál de
los dos está usando — solo llama a esos tres métodos. Agregar un tercer destino (ej. otro plugin
de catálogo) es agregar un adaptador nuevo con ese mismo contrato, sin tocar el resto.

## Qué falta antes de un sitio real

- Probarlo contra un WordPress real (no se hizo — no había ninguno disponible), con y sin
  WooCommerce activo.
- Manejo de imágenes de producto (el contrato v1 de la API no expone imágenes hoy).
- Mapeo de categorías WooCommerce (hoy el adaptador WooCommerce no asigna ninguna).
