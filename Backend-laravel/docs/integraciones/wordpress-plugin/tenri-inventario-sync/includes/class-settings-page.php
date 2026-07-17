<?php
/**
 * Ajustes > Tenri Inventario: URL base del ERP + API-key (emitida desde el ERP,
 * POST /api/integraciones/admin/keys con scope inventario:leer, ver CONTRATO-V1.md).
 * Boton de "Sincronizar ahora" para no esperar al cron.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tenri_Inventario_Sync_Settings_Page
{
    public static function registrar_menu(): void
    {
        add_options_page(
            'Tenri Inventario Sync',
            'Tenri Inventario',
            'manage_options',
            'tenri-inventario-sync',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['tenri_inv_sync_guardar']) && check_admin_referer('tenri_inv_sync_guardar_ajustes')) {
            update_option('tenri_inv_sync_base_url', esc_url_raw((string) ($_POST['base_url'] ?? '')));
            update_option('tenri_inv_sync_api_key', sanitize_text_field((string) ($_POST['api_key'] ?? '')));
            echo '<div class="notice notice-success"><p>Ajustes guardados.</p></div>';
        }

        if (isset($_POST['tenri_inv_sync_ahora']) && check_admin_referer('tenri_inv_sync_ejecutar_ahora')) {
            Tenri_Inventario_Sync_Cron::ejecutar_sync();
            echo '<div class="notice notice-info"><p>Sincronizacion ejecutada.</p></div>';
        }

        $baseUrl = get_option('tenri_inv_sync_base_url', '');
        $apiKey = get_option('tenri_inv_sync_api_key', '');
        $ultimoError = get_option('tenri_inv_sync_ultimo_error', '');
        $ultimaEjecucion = get_option('tenri_inv_sync_ultima_ejecucion', '');
        $ultimoResultado = get_option('tenri_inv_sync_ultimo_resultado', []);
        ?>
        <div class="wrap">
            <h1>Tenri Inventario Sync</h1>
            <p>Sincroniza el inventario visible del ERP hacia productos de este sitio, cada 15 minutos.</p>

            <form method="post">
                <?php wp_nonce_field('tenri_inv_sync_guardar_ajustes'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="base_url">URL del ERP</label></th>
                        <td><input type="url" id="base_url" name="base_url" class="regular-text"
                                   value="<?php echo esc_attr($baseUrl); ?>" placeholder="https://erp.tenri.cl"></td>
                    </tr>
                    <tr>
                        <th><label for="api_key">API-key (scope inventario:leer)</label></th>
                        <td><input type="password" id="api_key" name="api_key" class="regular-text"
                                   value="<?php echo esc_attr($apiKey); ?>" placeholder="tnri_..."></td>
                    </tr>
                </table>
                <?php submit_button('Guardar ajustes', 'primary', 'tenri_inv_sync_guardar'); ?>
            </form>

            <form method="post">
                <?php wp_nonce_field('tenri_inv_sync_ejecutar_ahora'); ?>
                <?php submit_button('Sincronizar ahora', 'secondary', 'tenri_inv_sync_ahora'); ?>
            </form>

            <h2>Estado</h2>
            <p><strong>Ultima ejecucion:</strong> <?php echo esc_html($ultimaEjecucion ?: 'nunca'); ?></p>
            <?php if ($ultimoError): ?>
                <p style="color:#b32d2e;"><strong>Ultimo error:</strong> <?php echo esc_html($ultimoError); ?></p>
            <?php elseif (!empty($ultimoResultado)): ?>
                <p>
                    <?php echo esc_html(sprintf(
                        '%d creados, %d actualizados (%d recibidos del ERP)',
                        $ultimoResultado['creados'] ?? 0,
                        $ultimoResultado['actualizados'] ?? 0,
                        $ultimoResultado['total'] ?? 0
                    )); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
