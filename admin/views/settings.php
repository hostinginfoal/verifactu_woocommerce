<?php
/**
 * Admin view: Settings page.
 *
 * Equivalent to the output of renderForm() in the PrestaShop module.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Retrieve current options
$api_token        = Verifactu_Infoal::get_option( 'api_token', '' );
$nif_emisor       = Verifactu_Infoal::get_option( 'nif_emisor', '' );
$debug_mode       = Verifactu_Infoal::get_option( 'debug_mode', 0 );
$usa_oss          = Verifactu_Infoal::get_option( 'usa_oss', 0 );
$territorio_esp   = Verifactu_Infoal::get_option( 'territorio_especial', 0 );
$qr_hide_default  = Verifactu_Infoal::get_option( 'qr_hide_default', 0 );
$qr_width         = Verifactu_Infoal::get_option( 'qr_width', 60 );
$qr_text          = Verifactu_Infoal::get_option( 'qr_text', 'Factura verificable en la sede electrónica de la AEAT' );
$show_anulacion   = Verifactu_Infoal::get_option( 'show_anulacion_button', 0 );
$lock_order       = Verifactu_Infoal::get_option( 'lock_order_if_correct', 0 );
$recargo_compat   = Verifactu_Infoal::get_option( 'recargo_compat', 0 );
$invoice_engine   = Verifactu_Infoal::get_option( 'invoice_engine', 'wp_overnight' );
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Configuración de VeriFactu', 'verifactu-infoal' ); ?></h1>

    <div class="notice notice-info">
        <p><?php esc_html_e( 'Introduce el token de la API de InFoAL para activar el servicio de comunicación con la AEAT.', 'verifactu-infoal' ); ?></p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'verifactu_save_settings' ); ?>
        <input type="hidden" name="verifactu_save_settings" value="1">

        <table class="form-table">
            <tbody>
                <!-- API Settings -->
                <tr>
                    <th scope="row">
                        <label for="verifactu_api_token"><?php esc_html_e( 'API Token', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <input name="verifactu_api_token" type="text" id="verifactu_api_token" value="<?php echo esc_attr( $api_token ); ?>" class="regular-text" required>
                        <button type="button" id="btn-test-api" class="button button-secondary"><?php esc_html_e( 'Validar token', 'verifactu-infoal' ); ?></button>
                        <button type="button" id="btn-check-status" class="button button-secondary"><?php esc_html_e( 'Comprobar Estado AEAT', 'verifactu-infoal' ); ?></button>
                        <span id="api-test-result" style="margin-left: 10px; font-weight: bold;"></span>
                        <p class="description"><?php esc_html_e( 'El token proporcionado por InFoAL.', 'verifactu-infoal' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_nif_emisor"><?php esc_html_e( 'NIF Emisor', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <input name="verifactu_nif_emisor" type="text" id="verifactu_nif_emisor" value="<?php echo esc_attr( $nif_emisor ); ?>" class="regular-text" required>
                        <p class="description"><?php esc_html_e( 'NIF de la empresa que emite las facturas.', 'verifactu-infoal' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_invoice_engine"><?php esc_html_e( 'Motor de facturación (PDF)', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <select name="verifactu_invoice_engine" id="verifactu_invoice_engine">
                            <option value="wp_overnight" <?php selected( $invoice_engine, 'wp_overnight' ); ?>><?php esc_html_e( 'PDF Invoices & Packing Slips for WooCommerce (WP Overnight)', 'verifactu-infoal' ); ?></option>
                            <option value="wc_order" <?php selected( $invoice_engine, 'wc_order' ); ?>><?php esc_html_e( 'Número de Pedido de WooCommerce (Sin PDF externo)', 'verifactu-infoal' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'El módulo generador de facturas del que tomaremos el número de factura y al que inyectaremos el Código QR.', 'verifactu-infoal' ); ?></p>
                    </td>
                </tr>

                <!-- Fiscal Flags -->
                <tr>
                    <th scope="row">
                        <label for="verifactu_usa_oss"><?php esc_html_e( 'Utiliza el esquema de IVA OSS', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="verifactu_usa_oss" id="verifactu_usa_oss" value="1" <?php checked( $usa_oss, 1 ); ?>>
                            <?php esc_html_e( 'Activar soporte para Ventanilla Única (OSS)', 'verifactu-infoal' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_territorio_especial"><?php esc_html_e( 'Territorio Especial (Canarias, Ceuta o Melilla)', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="verifactu_territorio_especial" id="verifactu_territorio_especial" value="1" <?php checked( $territorio_esp, 1 ); ?>>
                            <?php esc_html_e( 'Mis impuestos principales son IGIC o IPSI en lugar de IVA', 'verifactu-infoal' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_recargo_compat"><?php esc_html_e( 'Compatibilidad Recargo de Equivalencia', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="verifactu_recargo_compat" id="verifactu_recargo_compat" value="1" <?php checked( $recargo_compat, 1 ); ?>>
                            <?php esc_html_e( 'Habilitar cálculo inverso de RE a partir de las tasas totales (IVA+RE)', 'verifactu-infoal' ); ?>
                        </label>
                    </td>
                </tr>

                <!-- QR Settings -->
                <tr>
                    <th scope="row">
                        <label for="verifactu_qr_hide_default"><?php esc_html_e( 'Ocultar código QR de la factura', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="verifactu_qr_hide_default" id="verifactu_qr_hide_default" value="1" <?php checked( $qr_hide_default, 1 ); ?>>
                            <?php esc_html_e( 'No insertar el código QR automáticamente en los PDFs', 'verifactu-infoal' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_qr_width"><?php esc_html_e( 'Ancho del Código QR', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <input name="verifactu_qr_width" type="number" id="verifactu_qr_width" value="<?php echo esc_attr( $qr_width ); ?>" class="small-text" min="10" max="500"> px
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_qr_text"><?php esc_html_e( 'Texto descriptivo del QR', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <textarea name="verifactu_qr_text" id="verifactu_qr_text" rows="3" class="large-text"><?php echo esc_textarea( $qr_text ); ?></textarea>
                    </td>
                </tr>

                <!-- Advanced Settings -->
                <tr>
                    <th scope="row">
                        <label for="verifactu_show_anulacion_button"><?php esc_html_e( 'Mostrar botón Anulación Manual', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="verifactu_show_anulacion_button" id="verifactu_show_anulacion_button" value="1" <?php checked( $show_anulacion, 1 ); ?>>
                            <?php esc_html_e( 'Permitir forzar una anulación desde el registro de facturación', 'verifactu-infoal' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_lock_order_if_correct"><?php esc_html_e( 'Bloquear pedido', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="verifactu_lock_order_if_correct" id="verifactu_lock_order_if_correct" value="1" <?php checked( $lock_order, 1 ); ?>>
                            <?php esc_html_e( 'Bloquear edición del pedido si el registro VeriFactu es "Correcto"', 'verifactu-infoal' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="verifactu_debug_mode"><?php esc_html_e( 'Modo Debug', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="verifactu_debug_mode" id="verifactu_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?>>
                            <?php esc_html_e( 'Guardar logs detallados de peticiones API', 'verifactu-infoal' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Mantenimiento', 'verifactu-infoal' ); ?></label>
                    </th>
                    <td>
                        <button type="button" id="btn-check-database" class="button button-secondary"><?php esc_html_e( 'Verificar y Reparar Base de Datos', 'verifactu-infoal' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Comprueba que las tablas del plugin existen y tienen la estructura correcta.', 'verifactu-infoal' ); ?></p>
                    </td>
                </tr>

            </tbody>
        </table>

        <?php submit_button( __( 'Guardar configuración', 'verifactu-infoal' ) ); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var ajaxUrl = verifactuInFoAL.ajaxUrl;
    var nonce = verifactuInFoAL.nonce;

    $('#btn-test-api').on('click', function() {
        var token = $('#verifactu_api_token').val();
        $('#api-test-result').text('Probando...').css('color', 'black');
        
        $.post(ajaxUrl, {
            action: 'verifactu_test_key',
            nonce: nonce,
            token: token
        }, function(response) {
            if (response.success && response.data.response === 'OK') {
                $('#api-test-result').text('Token válido ✓').css('color', 'green');
            } else {
                var errorMsg = response.data && response.data.error ? response.data.error : 'Token inválido o error de API';
                $('#api-test-result').text('Error: ' + errorMsg).css('color', 'red');
            }
        }).fail(function() {
            $('#api-test-result').text('Error de conexión AJAX').css('color', 'red');
        });
    });

    $('#btn-check-status').on('click', function() {
        var token = $('#verifactu_api_token').val();
        $('#api-test-result').text('Comprobando AEAT...').css('color', 'black');
        
        $.post(ajaxUrl, {
            action: 'verifactu_check_api_status',
            nonce: nonce,
            token: token
        }, function(response) {
            if (response.success && response.data.response === 'OK') {
                var statusStr = response.data.status ? response.data.status : 'OK';
                $('#api-test-result').text('Estado AEAT: ' + statusStr + ' ✓').css('color', 'green');
            } else {
                var errorMsg = response.data && response.data.error ? response.data.error : 'Error de API';
                $('#api-test-result').text('Error: ' + errorMsg).css('color', 'red');
            }
        }).fail(function() {
            $('#api-test-result').text('Error de conexión AJAX').css('color', 'red');
        });
    });

    $('#btn-check-database').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Verificando...');
        
        $.post(ajaxUrl, {
            action: 'verifactu_check_database',
            nonce: nonce
        }, function(response) {
            if (response.success) {
                alert(response.data && response.data.message ? response.data.message : 'Base de datos correcta.');
            } else {
                var errorMsg = response.data && response.data.message ? response.data.message : 'Error al verificar la base de datos.';
                if (response.data && response.data.issues) {
                    errorMsg += "\n\nProblemas detectados:\n- " + response.data.issues.join("\n- ");
                }
                alert(errorMsg);
            }
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Verificar y Reparar Base de Datos', 'verifactu-infoal' ) ); ?>');
        }).fail(function() {
            alert('Error de conexión AJAX al verificar la base de datos.');
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Verificar y Reparar Base de Datos', 'verifactu-infoal' ) ); ?>');
        });
    });
});
</script>
