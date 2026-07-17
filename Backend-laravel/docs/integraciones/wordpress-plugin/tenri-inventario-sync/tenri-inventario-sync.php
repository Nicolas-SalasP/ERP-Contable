<?php
/**
 * Plugin Name: Tenri Inventario Sync
 * Description: Sincroniza (pull) el inventario visible del ERP Tenri hacia un Custom Post Type
 *              local, consumiendo la API de Integraciones del ERP (ver CONTRATO-V1.md, misma
 *              carpeta un nivel arriba). No asume WooCommerce: guarda los productos en su propio
 *              CPT (`tenri_producto`) para no interferir con ningun catalogo existente. Si el
 *              sitio usa WooCommerce, extender `Tenri_Inventario_Sync_Service::upsert()` para
 *              mapear a `wc_product` es el punto de enganche natural.
 * Version:     1.0.0
 * Text Domain: tenri-inventario-sync
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TENRI_INV_SYNC_VERSION', '1.0.0');
define('TENRI_INV_SYNC_DIR', plugin_dir_path(__FILE__));
define('TENRI_INV_SYNC_CRON_HOOK', 'tenri_inventario_sync_cron');

require_once TENRI_INV_SYNC_DIR . 'includes/class-settings-page.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-api-client.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-sync-service.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-cron.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-post-type.php';

register_activation_hook(__FILE__, ['Tenri_Inventario_Sync_Cron', 'activar']);
register_deactivation_hook(__FILE__, ['Tenri_Inventario_Sync_Cron', 'desactivar']);

add_action('init', ['Tenri_Inventario_Sync_Post_Type', 'registrar']);
add_action('admin_menu', ['Tenri_Inventario_Sync_Settings_Page', 'registrar_menu']);
add_action(TENRI_INV_SYNC_CRON_HOOK, ['Tenri_Inventario_Sync_Cron', 'ejecutar_sync']);
add_filter('cron_schedules', ['Tenri_Inventario_Sync_Cron', 'agregar_intervalo']);
