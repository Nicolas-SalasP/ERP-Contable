<?php
/**
 * CPT propio (`tenri_producto`) para no interferir con ningun catalogo existente del sitio
 * (WooCommerce u otro). El sku vive como meta unica para poder buscar por el (clave natural
 * del contrato v1, ver CONTRATO-V1.md) sin depender del post_title/slug.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tenri_Inventario_Sync_Post_Type
{
    const SLUG = 'tenri_producto';

    public static function registrar(): void
    {
        register_post_type(self::SLUG, [
            'label' => 'Productos Tenri',
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-archive',
            'supports' => ['title', 'editor'],
            'has_archive' => false,
        ]);
    }

    public static function buscarPorSku(string $sku): ?int
    {
        $query = new WP_Query([
            'post_type' => self::SLUG,
            'post_status' => 'any',
            'meta_key' => '_tenri_sku',
            'meta_value' => $sku,
            'fields' => 'ids',
            'posts_per_page' => 1,
        ]);

        return $query->posts[0] ?? null;
    }
}
