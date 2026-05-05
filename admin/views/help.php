<?php
/**
 * Admin view: Help / FAQ.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$diagnostic_data = array(
    'WP Version'     => get_bloginfo( 'version' ),
    'WC Version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
    'PHP Version'    => phpversion(),
    'Plugin Version' => VERIFACTU_INFOAL_VERSION,
    'Site URL'       => get_site_url(),
    'API Token'      => Verifactu_Infoal::get_option( 'api_token', '' ) ? 'Configurado' : 'No configurado',
    'API Mode'       => Verifactu_Infoal::get_option( 'api_mode', 'test' ),
);
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Ayuda y Soporte VeriFactu InFoAL', 'verifactu-infoal' ); ?></h1>

    <div style="display:flex; gap:20px; margin-top:20px;">
        <div style="flex:2;">
            <div class="postbox">
                <h2 class="hndle" style="padding:10px;"><span><?php esc_html_e( 'Preguntas Frecuentes (FAQ)', 'verifactu-infoal' ); ?></span></h2>
                <div class="inside">
                    <h3>¿Cuándo se envían las facturas a la AEAT?</h3>
                    <p>Las facturas se envían automáticamente a la AEAT cuando un pedido cambia a estado "Completado" o cuando se genera su PDF de factura a través del plugin soportado.</p>
                    
                    <h3>¿Qué pasa si falla la comunicación?</h3>
                    <p>Si la conexión falla en el momento de generar el alta, la factura quedará en estado "Pendiente de Sincronizar" (Error API). Un proceso en segundo plano (WP-Cron) se encargará de intentar reenviarlo automáticamente cada 15 minutos.</p>

                    <h3>¿Cómo funcionan los abonos?</h3>
                    <p>Cuando procesas un "Reembolso" (Refund) desde WooCommerce, el plugin envía automáticamente un registro de tipo "Abono" a la AEAT, cancelando los importes de la factura original.</p>

                    <h3>El código QR no aparece en las facturas</h3>
                    <p>Asegúrate de que en la Configuración tienes activada la opción "Mostrar Código QR". También, revisa que el plugin de facturación en PDF sea compatible (WooCommerce PDF Invoices & Packing Slips).</p>
                </div>
            </div>
        </div>

        <div style="flex:1;">
            <div class="postbox">
                <h2 class="hndle" style="padding:10px;"><span><?php esc_html_e( 'Información del Sistema', 'verifactu-infoal' ); ?></span></h2>
                <div class="inside">
                    <table class="widefat striped">
                        <tbody>
                            <?php foreach ( $diagnostic_data as $key => $value ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $key ); ?></strong></td>
                                    <td><?php echo esc_html( $value ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p style="margin-top:15px;">
                        <button type="button" class="button button-primary" id="verifactu-send-diagnostic">
                            <?php esc_html_e( 'Enviar diagnóstico a InFoAL', 'verifactu-infoal' ); ?>
                        </button>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#verifactu-send-diagnostic').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        
        $btn.prop('disabled', true).text( verifactuInFoAL.i18n.sending || 'Enviando...' );

        $.post( verifactuInFoAL.ajaxUrl, {
            action: 'verifactu_send_diagnostic',
            nonce: verifactuInFoAL.nonce
        }, function(response) {
            if ( response.success ) {
                alert( response.data && response.data.message ? response.data.message : 'Enviado.' );
                $btn.prop('disabled', false).text( '<?php echo esc_js( __( 'Enviar diagnóstico a InFoAL', 'verifactu-infoal' ) ); ?>' );
            } else {
                alert( response.data && response.data.error ? response.data.error : verifactuInFoAL.i18n.error_generic );
                $btn.prop('disabled', false).text( '<?php echo esc_js( __( 'Enviar diagnóstico a InFoAL', 'verifactu-infoal' ) ); ?>' );
            }
        }).fail(function() {
            alert( verifactuInFoAL.i18n.error_generic );
            $btn.prop('disabled', false).text( '<?php echo esc_js( __( 'Enviar diagnóstico a InFoAL', 'verifactu-infoal' ) ); ?>' );
        });
    });
});
</script>
