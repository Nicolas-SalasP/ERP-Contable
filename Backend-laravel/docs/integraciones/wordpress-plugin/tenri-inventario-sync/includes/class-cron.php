<?php
/**
 * Programa la sync via WP-Cron cada 15 minutos (mismo intervalo que el comando artisan
 * equivalente en Tenri-Web-Page, `erp:sincronizar-productos`).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tenri_Inventario_Sync_Cron
{
    const INTERVALO = 'tenri_quince_minutos';

    public static function activar(): void
    {
        if (!wp_next_scheduled(TENRI_INV_SYNC_CRON_HOOK)) {
            wp_schedule_event(time(), self::INTERVALO, TENRI_INV_SYNC_CRON_HOOK);
        }
    }

    public static function desactivar(): void
    {
        wp_clear_scheduled_hook(TENRI_INV_SYNC_CRON_HOOK);
    }

    public static function agregar_intervalo(array $schedules): array
    {
        $schedules[self::INTERVALO] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'Cada 15 minutos (Tenri Inventario Sync)',
        ];

        return $schedules;
    }

    public static function ejecutar_sync(): void
    {
        $baseUrl = get_option('tenri_inv_sync_base_url', '');
        $apiKey = get_option('tenri_inv_sync_api_key', '');

        if ($baseUrl === '' || $apiKey === '') {
            return;
        }

        $cliente = new Tenri_Inventario_Sync_Api_Client($baseUrl, $apiKey);
        $servicio = new Tenri_Inventario_Sync_Service($cliente);
        $resultado = $servicio->sincronizar();

        if (is_wp_error($resultado)) {
            update_option('tenri_inv_sync_ultimo_error', $resultado->get_error_message());
            return;
        }

        update_option('tenri_inv_sync_ultimo_error', '');
        update_option('tenri_inv_sync_ultimo_resultado', $resultado);
        update_option('tenri_inv_sync_ultima_ejecucion', current_time('mysql'));
    }
}
