<?php
/**
 * Adaptador de destino: CPT propio (`tenri_producto`). Se usa cuando el sitio NO tiene
 * WooCommerce activo (ver Tenri_Inventario_Sync_Service::detectar_adapter()). Mismo contrato
 * (buscarPorSku/crear/actualizar) que Tenri_Inventario_Sync_Woocommerce_Adapter -> el sync
 * service no sabe ni le importa cual de los dos esta usando.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tenri_Inventario_Sync_Cpt_Adapter
{
    public function buscarPorSku(string $sku): ?int
    {
        return Tenri_Inventario_Sync_Post_Type::buscarPorSku($sku);
    }

    public function crear(array $producto): void
    {
        $postId = wp_insert_post([
            'post_type' => Tenri_Inventario_Sync_Post_Type::SLUG,
            'post_title' => $producto['nombre'] ?? $producto['sku'],
            'post_content' => $producto['descripcion'] ?? '',
            'post_status' => $this->estado_post($producto),
        ]);

        if (is_wp_error($postId) || $postId === 0) {
            return;
        }

        $this->guardar_meta($postId, $producto);
    }

    public function actualizar(int $postId, array $producto): void
    {
        wp_update_post([
            'ID' => $postId,
            'post_title' => $producto['nombre'] ?? get_the_title($postId),
            'post_content' => $producto['descripcion'] ?? '',
            'post_status' => $this->estado_post($producto),
        ]);

        $this->guardar_meta($postId, $producto);
    }

    /**
     * El endpoint del ERP ya filtra visible_web=1, pero `activo` es un concepto aparte (ver
     * CONTRATO-V1.md): un producto desactivado en el ERP NUNCA debe quedar publicado aca, aunque
     * siga llegando con visible_web=true. Default true si el campo faltara (defensivo).
     */
    private function estado_post(array $producto): string
    {
        return (!array_key_exists('activo', $producto) || $producto['activo']) ? 'publish' : 'draft';
    }

    private function guardar_meta(int $postId, array $producto): void
    {
        update_post_meta($postId, '_tenri_sku', $producto['sku']);
        update_post_meta($postId, '_tenri_precio_venta_neto', $producto['precio_venta_neto'] ?? null);
        update_post_meta($postId, '_tenri_stock_actual_total', $producto['stock_actual_total'] ?? null);
        update_post_meta($postId, '_tenri_afecto_iva', !empty($producto['afecto_iva']) ? 1 : 0);
        update_post_meta($postId, '_tenri_codigo_barra', $producto['codigo_barra'] ?? '');
        update_post_meta($postId, '_tenri_activo', !empty($producto['activo']) ? 1 : 0);
        update_post_meta($postId, '_tenri_synced_at', current_time('mysql'));
    }
}
