<?php
/**
 * Adaptador de destino: producto WooCommerce (`WC_Product_Simple`). Se usa solo si
 * class_exists('WooCommerce') al momento de sincronizar (ver
 * Tenri_Inventario_Sync_Service::detectar_adapter()) -> este archivo referencia clases de
 * WooCommerce (WC_Product_Simple, wc_get_product*) que NO existen si el plugin no esta
 * activo; por eso nunca se instancia esta clase salvo que WooCommerce este realmente cargado.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tenri_Inventario_Sync_Woocommerce_Adapter
{
    public function buscarPorSku(string $sku): ?int
    {
        $id = wc_get_product_id_by_sku($sku);

        return $id > 0 ? $id : null;
    }

    public function crear(array $producto): void
    {
        $wc = new WC_Product_Simple();
        $wc->set_sku($producto['sku']);
        $this->aplicar_datos($wc, $producto);
        $wc->save();
    }

    public function actualizar(int $productId, array $producto): void
    {
        $wc = wc_get_product($productId);

        if (!$wc) {
            return;
        }

        $this->aplicar_datos($wc, $producto);
        $wc->save();
    }

    private function aplicar_datos(WC_Product $wc, array $producto): void
    {
        $wc->set_name($producto['nombre'] ?? $producto['sku']);
        $wc->set_description($producto['descripcion'] ?? '');
        $wc->set_regular_price((string) ($producto['precio_venta_neto'] ?? 0));
        $wc->set_manage_stock(true);
        $wc->set_stock_quantity((int) round((float) ($producto['stock_actual_total'] ?? 0)));
        $wc->set_status('publish');
        $wc->update_meta_data('_tenri_synced_at', current_time('mysql'));
    }
}
