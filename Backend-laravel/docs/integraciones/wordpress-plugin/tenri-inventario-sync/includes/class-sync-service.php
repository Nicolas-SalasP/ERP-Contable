<?php
/**
 * Upsert de productos del ERP hacia el CPT local (por sku). Espejo funcional de
 * ProductErpSyncService.php (Tenri-Web-Page/backend): el ERP es la fuente de verdad de
 * existencia/precio/stock/visibilidad mientras el producto siga viniendo en la respuesta
 * (que ya filtra visible_web=1) — si deja de venir, esta sync NO lo borra ni lo oculta de
 * oficio, igual que su equivalente en Tenri-Web-Page.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tenri_Inventario_Sync_Service
{
    private Tenri_Inventario_Sync_Api_Client $cliente;

    public function __construct(Tenri_Inventario_Sync_Api_Client $cliente)
    {
        $this->cliente = $cliente;
    }

    /** @return array{creados: int, actualizados: int, total: int}|WP_Error */
    public function sincronizar()
    {
        $productos = $this->cliente->listarProductosVisibles();

        if (is_wp_error($productos)) {
            return $productos;
        }

        $creados = 0;
        $actualizados = 0;

        foreach ($productos as $producto) {
            $sku = $producto['sku'] ?? null;
            if (!is_string($sku) || $sku === '') {
                continue;
            }

            $postId = Tenri_Inventario_Sync_Post_Type::buscarPorSku($sku);

            if ($postId === null) {
                $this->crear($producto);
                $creados++;
            } else {
                $this->actualizar($postId, $producto);
                $actualizados++;
            }
        }

        return ['creados' => $creados, 'actualizados' => $actualizados, 'total' => count($productos)];
    }

    private function crear(array $producto): void
    {
        $postId = wp_insert_post([
            'post_type' => Tenri_Inventario_Sync_Post_Type::SLUG,
            'post_title' => $producto['nombre'] ?? $producto['sku'],
            'post_content' => $producto['descripcion'] ?? '',
            'post_status' => 'publish',
        ]);

        if (is_wp_error($postId) || $postId === 0) {
            return;
        }

        $this->guardarMeta($postId, $producto);
    }

    private function actualizar(int $postId, array $producto): void
    {
        wp_update_post([
            'ID' => $postId,
            'post_title' => $producto['nombre'] ?? get_the_title($postId),
            'post_content' => $producto['descripcion'] ?? '',
        ]);

        $this->guardarMeta($postId, $producto);
    }

    private function guardarMeta(int $postId, array $producto): void
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
