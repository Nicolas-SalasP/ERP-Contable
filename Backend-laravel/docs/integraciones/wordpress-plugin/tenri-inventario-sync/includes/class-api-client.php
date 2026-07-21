<?php
/**
 * Cliente HTTP de la API de Integraciones del ERP (ver docs/integraciones/CONTRATO-V1.md).
 * Auth por API-key (Bearer), NO por HMAC. Espejo funcional de ErpInventarioClient.php
 * (Tenri-Web-Page/backend), traducido a wp_remote_get para no depender de Guzzle/Composer.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tenri_Inventario_Sync_Api_Client
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Trae TODAS las paginas de productos visibles (visible_web=1). Devuelve el array `data`
     * crudo del contrato v1, o WP_Error si algo fallo (el llamador decide como loguearlo).
     *
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public function listarProductosVisibles()
    {
        if ($this->apiKey === '') {
            return new WP_Error('tenri_sin_api_key', 'No hay API-key configurada (Ajustes > Tenri Inventario).');
        }

        $productos = [];
        $page = 1;
        $totalPages = 1;

        do {
            $url = add_query_arg([
                'visible_web' => 1,
                'page' => $page,
                'limit' => 100,
            ], $this->baseUrl . '/api/integraciones/v1/inventario/productos');

            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            if ($status < 200 || $status >= 300) {
                return new WP_Error(
                    'tenri_http_' . $status,
                    sprintf('El ERP respondio %d al consultar productos.', $status)
                );
            }

            $json = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
                return new WP_Error('tenri_respuesta_invalida', 'Respuesta del ERP con formato inesperado.');
            }

            $productos = array_merge($productos, $json['data']);
            $totalPages = (int) ($json['pagination']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $productos;
    }
}
