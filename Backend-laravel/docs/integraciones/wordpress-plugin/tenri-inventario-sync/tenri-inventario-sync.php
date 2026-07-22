<?php
/**
 * Plugin Name: Tenri Inventario Sync
 * Description: Sincroniza (pull) el inventario visible del ERP Tenri hacia productos locales,
 *              consumiendo la API de Integraciones del ERP (ver CONTRATO-V1.md, misma carpeta
 *              un nivel arriba). Detecta automaticamente si WooCommerce esta activo: si lo
 *              esta, sincroniza como WC_Product; si no, usa un Custom Post Type propio
 *              (`tenri_producto`) para no interferir con ningun catalogo existente. Funciona
 *              igual en ambos casos, sin configuracion manual del modo.
 * Version:     1.1.0
 * Text Domain: tenri-inventario-sync
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TENRI_INV_SYNC_VERSION', '1.1.0');
define('TENRI_INV_SYNC_DIR', plugin_dir_path(__FILE__));
define('TENRI_INV_SYNC_CRON_HOOK', 'tenri_inventario_sync_cron');

require_once TENRI_INV_SYNC_DIR . 'includes/class-settings-page.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-api-client.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-post-type.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-cpt-adapter.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-woocommerce-adapter.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-sync-service.php';
require_once TENRI_INV_SYNC_DIR . 'includes/class-cron.php';

register_activation_hook(__FILE__, ['Tenri_Inventario_Sync_Cron', 'activar']);
register_deactivation_hook(__FILE__, ['Tenri_Inventario_Sync_Cron', 'desactivar']);

// El CPT propio solo hace falta si WooCommerce no esta activo (si lo esta, el destino es
// WC_Product y este CPT no se usa para nada). Se evalua en 'init' porque el estado de
// WooCommerce (otro plugin) recien esta disponible ahi, no antes.
add_action('init', function () {
    if (!class_exists('WooCommerce')) {
        Tenri_Inventario_Sync_Post_Type::registrar();
    }
});

add_action('admin_menu', ['Tenri_Inventario_Sync_Settings_Page', 'registrar_menu']);
add_action(TENRI_INV_SYNC_CRON_HOOK, ['Tenri_Inventario_Sync_Cron', 'ejecutar_sync']);
add_filter('cron_schedules', ['Tenri_Inventario_Sync_Cron', 'agregar_intervalo']);
