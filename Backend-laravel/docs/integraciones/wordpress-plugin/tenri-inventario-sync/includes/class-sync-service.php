<?php
/**
 * Upsert de productos del ERP hacia el destino local (por sku), delegado al adaptador que
 * corresponda: WooCommerce si esta activo, CPT propio si no. Espejo funcional de
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
    /** @var Tenri_Inventario_Sync_Cpt_Adapter|Tenri_Inventario_Sync_Woocommerce_Adapter */
    private $adaptador;

    public function __construct(Tenri_Inventario_Sync_Api_Client $cliente, $adaptador = null)
    {
        $this->cliente = $cliente;
        $this->adaptador = $adaptador ?? self::detectar_adaptador();
    }

    /** WooCommerce si esta activo al momento de correr la sync, CPT propio si no. Se detecta en cada
     *  ejecucion (no en la activacion del plugin) -> si WooCommerce se instala/desinstala despues,
     *  la proxima sync usa el destino correcto sin que haya que reconfigurar nada. */
    public static function detectar_adaptador()
    {
        return class_exists('WooCommerce')
            ? new Tenri_Inventario_Sync_Woocommerce_Adapter()
            : new Tenri_Inventario_Sync_Cpt_Adapter();
    }

    public static function nombre_adaptador_activo(): string
    {
        return class_exists('WooCommerce') ? 'WooCommerce' : 'CPT propio (tenri_producto)';
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

            $id = $this->adaptador->buscarPorSku($sku);

            if ($id === null) {
                $this->adaptador->crear($producto);
                $creados++;
            } else {
                $this->adaptador->actualizar($id, $producto);
                $actualizados++;
            }
        }

        return ['creados' => $creados, 'actualizados' => $actualizados, 'total' => count($productos)];
    }
}
