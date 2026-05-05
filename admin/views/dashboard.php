<?php
/**
 * Admin view: Dashboard.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
global $wpdb;

$table_inv = $wpdb->prefix . 'verifactu_order_invoice';
$table_ref = $wpdb->prefix . 'verifactu_order_refund';

$total_invoices   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_inv" );
$total_refunds    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_ref" );
$total_pending    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_inv WHERE estado = 'pendiente'" );
$total_errors     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_inv WHERE estado = 'api_error'" );

$recent_errors    = $wpdb->get_results( "SELECT id_order_invoice, verifactuDescripcionErrorRegistro, date_add FROM $table_inv WHERE estado = 'api_error' ORDER BY date_add DESC LIMIT 5" );

?>

<div class="wrap verifactu-dashboard">
    <h1><?php esc_html_e( 'Dashboard VeriFactu', 'verifactu-infoal' ); ?></h1>
    
    <div class="verifactu-stats-grid" style="display:flex; gap:20px; margin-top:20px;">
        <div class="verifactu-stat-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; flex:1; text-align:center;">
            <h3><?php esc_html_e( 'Total Facturas', 'verifactu-infoal' ); ?></h3>
            <span style="font-size:2em; font-weight:bold; color:#0073aa;"><?php echo esc_html( $total_invoices ); ?></span>
        </div>
        <div class="verifactu-stat-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; flex:1; text-align:center;">
            <h3><?php esc_html_e( 'Total Abonos', 'verifactu-infoal' ); ?></h3>
            <span style="font-size:2em; font-weight:bold; color:#0073aa;"><?php echo esc_html( $total_refunds ); ?></span>
        </div>
        <div class="verifactu-stat-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; flex:1; text-align:center;">
            <h3><?php esc_html_e( 'Pendientes de Sincronizar', 'verifactu-infoal' ); ?></h3>
            <span style="font-size:2em; font-weight:bold; color:#ffb900;"><?php echo esc_html( $total_pending ); ?></span>
        </div>
        <div class="verifactu-stat-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; flex:1; text-align:center;">
            <h3><?php esc_html_e( 'Errores API', 'verifactu-infoal' ); ?></h3>
            <span style="font-size:2em; font-weight:bold; color:#d63638;"><?php echo esc_html( $total_errors ); ?></span>
        </div>
    </div>

    <div style="margin-top:20px; text-align:right;">
        <button type="button" class="button button-primary" id="verifactu-force-sync">
            <span class="dashicons dashicons-update" style="margin-top:3px;"></span> <?php esc_html_e( 'Forzar Sincronización', 'verifactu-infoal' ); ?>
        </button>
        <span id="verifactu-sync-status" style="margin-left:10px; font-weight:bold;"></span>
    </div>

    <?php if ( ! empty( $recent_errors ) ) : ?>
        <h2 style="margin-top:30px;"><?php esc_html_e( 'Últimos errores de registro', 'verifactu-infoal' ); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID Pedido', 'verifactu-infoal' ); ?></th>
                    <th><?php esc_html_e( 'Error', 'verifactu-infoal' ); ?></th>
                    <th><?php esc_html_e( 'Fecha', 'verifactu-infoal' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $recent_errors as $error ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $error->id_order_invoice . '&action=edit' ) ); ?>">#<?php echo esc_html( $error->id_order_invoice ); ?></a></td>
                        <td><span style="color:#d63638;"><?php echo esc_html( $error->verifactuDescripcionErrorRegistro ?: 'Error de conectividad API' ); ?></span></td>
                        <td><?php echo esc_html( $error->date_add ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#verifactu-force-sync').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $status = $('#verifactu-sync-status');
        
        $btn.prop('disabled', true);
        $status.text('Sincronizando...').css('color', '#555');

        $.post( verifactuInFoAL.ajaxUrl, {
            action: 'verifactu_check_pending',
            nonce: verifactuInFoAL.nonce
        }, function(response) {
            if ( response.success ) {
                $status.text(response.data && response.data.message ? response.data.message : 'Sincronizado correctamente.').css('color', 'green');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                var errorMsg = response.data && response.data.error ? response.data.error : 'Error desconocido.';
                $status.text('Error: ' + errorMsg).css('color', 'red');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            $status.text('Error de conexión AJAX.').css('color', 'red');
            $btn.prop('disabled', false);
        });
    });
});
</script>
