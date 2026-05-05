<?php
/**
 * Admin view: Order Meta Box.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$estado_registro = isset( $status['verifactuEstadoRegistro'] ) ? $status['verifactuEstadoRegistro'] : '';
$estado_envio    = isset( $status['verifactuEstadoEnvio'] ) ? $status['verifactuEstadoEnvio'] : '';
$url_qr          = isset( $status['urlQR'] ) ? $status['urlQR'] : '';
$id_reg_fact     = isset( $status['id_reg_fact'] ) ? $status['id_reg_fact'] : 0;
$api_mode        = isset( $status['apiMode'] ) ? $status['apiMode'] : '';
$error_msg       = isset( $status['verifactuDescripcionErrorRegistro'] ) ? $status['verifactuDescripcionErrorRegistro'] : '';
$invoice_number  = isset( $status['invoice_number'] ) ? $status['invoice_number'] : '';
$tipo_factura    = isset( $status['TipoFactura'] ) ? $status['TipoFactura'] : 'F1'; // Default
$date_add        = isset( $status['date_add'] ) ? date( 'd/m/Y H:i', strtotime( $status['date_add'] ) ) : '';
$order_date      = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd/m/Y' ) : '';

$is_correcto     = ( $estado_registro === 'Correcto' );
$is_pending      = ( $status && $estado_registro !== 'Correcto' && $estado_envio !== 'Error' && $estado_registro !== 'Incorrecto' );
$is_error        = ( $estado_envio === 'Error' || $estado_registro === 'Incorrecto' );

$has_status      = ! empty( $status );

$next_cron = wp_next_scheduled( 'verifactu_infoal_cron_sync' );
$seconds_left = $next_cron ? max( 0, $next_cron - time() ) : 60;

// Default texts based on state
if ( $is_correcto ) {
    $banner_class = 'verifactu-banner-success';
    $banner_title = 'Aceptado por la AEAT';
    $banner_desc  = 'Factura Completa (' . esc_html( $tipo_factura ) . ') · ' . esc_html( $api_mode );
} elseif ( $is_error ) {
    $banner_class = 'verifactu-banner-error';
    $banner_title = 'Rechazado por la AEAT';
    $banner_desc  = $error_msg ? esc_html( $error_msg ) : 'Ha ocurrido un error en el envío.';
} else {
    $banner_class = 'verifactu-banner-pending';
    $banner_title = 'Enviado — En espera de confirmación';
    $banner_desc  = 'El registro ha sido enviado correctamente a Veri*Factu y está pendiente de validación por la AEAT. La comprobación del estado se realiza de forma automática; puede continuar navegando con normalidad.<br><br><div style="display:flex; align-items:center; justify-content:space-between; margin-top:10px;"><small>Próxima comprobación automática en <strong id="verifactu-countdown" data-seconds="' . esc_attr( $seconds_left ) . '">' . esc_html( $seconds_left ) . 's</strong></small> <button type="button" class="verifactu-btn-action-primary verifactu-action-btn" data-action="sync_now" style="width:auto; padding:4px 8px; font-size:11px;">Sincronizar ahora</button></div>';
}
?>

<div class="verifactu-order-panel">
    
    <?php if ( $has_status && $is_correcto ) : ?>
    <div class="verifactu-legal-notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <div>
            <strong>AVISO LEGAL OBLIGATORIO - INALTERABILIDAD DE FACTURA</strong>
            <p>La normativa española prohíbe modificar una factura cuyo registro de facturación ha sido aceptado correctamente en Veri*Factu. Para corregirla debe emitirse una Factura por Abono (Reembolso).</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="verifactu-header">
        <strong>Veri*Factu</strong> <span class="verifactu-invoice-number"><?php echo esc_html( $invoice_number ?: 'Pendiente' ); ?></span>
    </div>

    <div class="verifactu-body">
        
        <?php if ( $has_status ) : ?>
            
            <div class="verifactu-banner <?php echo esc_attr( $banner_class ); ?>">
                <strong><?php echo esc_html( $banner_title ); ?></strong>
                <p><?php echo wp_kses_post( $banner_desc ); ?></p>
            </div>

            <div class="verifactu-section-title">DATOS DEL REGISTRO AEAT</div>
            <table class="verifactu-data-table">
                <tr>
                    <td>Fecha factura</td>
                    <td><?php echo esc_html( $order_date ?: '—' ); ?></td>
                </tr>
                <tr>
                    <td>Tipo factura</td>
                    <td><?php echo esc_html( $tipo_factura ); ?></td>
                </tr>
                <tr>
                    <td>Modo API</td>
                    <td><span class="verifactu-pill-dark"><?php echo esc_html( $api_mode ); ?></span></td>
                </tr>
            </table>

            <div class="verifactu-section-title">HISTORIAL DE ENVÍOS</div>
            <div class="verifactu-timeline">
                <div class="verifactu-timeline-item <?php echo $has_status ? 'done' : ''; ?>">
                    <div class="verifactu-timeline-dot"></div>
                    <div class="verifactu-timeline-content">
                        <strong>Factura creada en WooCommerce</strong>
                        <span><?php echo esc_html( $order_date ); ?></span>
                    </div>
                </div>
                <div class="verifactu-timeline-item <?php echo ( $has_status && ! $is_error ) ? ( $is_pending ? 'active' : 'done' ) : ( $is_error ? 'error' : '' ); ?>">
                    <div class="verifactu-timeline-dot"></div>
                    <div class="verifactu-timeline-content">
                        <strong style="color: <?php echo $is_pending ? '#00a396' : 'inherit'; ?>;">Registro Enviado</strong>
                        <span><?php echo esc_html( $date_add ); ?></span>
                    </div>
                </div>
                <div class="verifactu-timeline-item <?php echo $is_correcto ? 'active' : ''; ?>" style="border-left-color: transparent;">
                    <div class="verifactu-timeline-dot"></div>
                    <div class="verifactu-timeline-content">
                        <?php if ( $is_correcto ) : ?>
                            <strong style="color: #00a396;">Aceptado por AEAT (Correcto)</strong>
                            <span><?php echo esc_html( $date_add ); ?></span>
                        <?php else : ?>
                            <strong>Pendiente de respuesta</strong>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ( $url_qr ) : ?>
            <div class="verifactu-section-title">CÓDIGO QR DE VERIFICACIÓN</div>
            <div class="verifactu-qr-box">
                <a href="<?php echo esc_url( $url_qr ); ?>" target="_blank">
                    <?php 
                    $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode( $url_qr );
                    echo '<img src="' . esc_url( $qr_api_url ) . '" alt="QR VeriFactu">';
                    ?>
                </a>
                <p>Clic para verificar en sede AEAT</p>
            </div>
            <?php endif; ?>

            <div class="verifactu-section-title">ACCIONES</div>
            <div class="verifactu-actions-list">
                <?php if ( ! $is_correcto ) : ?>
                <button type="button" class="verifactu-btn-action-primary verifactu-action-btn" data-action="reenviar" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                    Reenviar a Veri*Factu
                </button>
                <?php endif; ?>
                
                <button type="button" class="verifactu-btn-action-success" onclick="window.open('https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=<?php echo esc_attr( Verifactu_Infoal::get_option('nif_emisor','') ); ?>', '_blank')">
                    Estado de la AEAT
                </button>
                
                <button type="button" class="verifactu-btn-action-secondary verifactu-action-btn" data-action="check_nif" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" data-nif="<?php echo esc_attr( $order->get_meta('_billing_nif') ?: $order->get_meta('billing_nif') ?: $order->get_meta('NIF') ); ?>">
                    Comprobar NIF/DNI
                </button>
                
                <?php if ( Verifactu_Infoal::get_option( 'show_anulacion_button', 0 ) && ( $is_correcto || $status['verifactuEstadoRegistro'] === 'AceptadoConErrores' ) ) : ?>
                <button type="button" class="verifactu-btn-action-danger verifactu-action-btn" data-action="anular" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                    Anular registro
                </button>
                <?php endif; ?>
            </div>

            <?php if ( false ) : // Ocultado temporalmente según petición del usuario ?>
            <div class="verifactu-section-title" style="margin-top:20px;">FACTURA ELECTRÓNICA (FACTURAE)</div>
            <div class="verifactu-actions-list">
                <button type="button" class="verifactu-btn-action-secondary verifactu-facturae-btn" data-action="generate_facturae" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                    Generar / Actualizar Facturae
                </button>
                <?php
                global $wpdb;
                $facturae = $wpdb->get_row( $wpdb->prepare( "SELECT id_facturae, estado_face FROM {$wpdb->prefix}facturae WHERE id_order = %d", $order->get_id() ) );
                if ( $facturae ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=verifactu_download_facturae_xml&id_facturae=' . $facturae->id_facturae ) ) ); ?>" class="verifactu-btn-action-secondary" target="_blank" style="text-align:center; text-decoration:none;">
                        Descargar XML
                    </a>
                    <button type="button" class="verifactu-btn-action-primary verifactu-facturae-btn" data-action="send_face" data-id-facturae="<?php echo esc_attr( $facturae->id_facturae ); ?>">
                        Enviar a FACe
                    </button>
                    <?php if ( $facturae->estado_face ) : ?>
                        <div style="text-align:center; margin-top:5px; font-weight:bold; color:#555; background:#eee; padding:5px; border-radius:4px;">
                            FACe: <?php echo esc_html( $facturae->estado_face ); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else : ?>
            <p style="padding:15px; color:#666;">Esta factura no ha sido enviada a Veri*Factu todavía.</p>
            <div class="verifactu-actions-list">
                <button type="button" class="verifactu-btn-action-primary verifactu-action-btn" data-action="enviar" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                    Enviar Alta Veri*Factu
                </button>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<style>
.verifactu-order-panel {
    background: #fff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}
.verifactu-legal-notice {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    padding: 12px 15px;
    margin-bottom: 15px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.verifactu-legal-notice svg {
    width: 24px;
    height: 24px;
    color: #28a745;
    flex-shrink: 0;
    margin-top: 2px;
}
.verifactu-legal-notice strong {
    color: #155724;
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
}
.verifactu-legal-notice p {
    color: #155724;
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
}

.verifactu-header {
    background: #ececf4;
    padding: 12px 15px;
    font-size: 14px;
    border: 1px solid #e2e4e7;
    border-radius: 4px 4px 0 0;
    color: #333;
}
.verifactu-invoice-number {
    color: #666;
    margin-left: 5px;
}
.verifactu-body {
    padding: 15px;
    border: 1px solid #e2e4e7;
    border-top: none;
    border-radius: 0 0 4px 4px;
}
.verifactu-banner {
    border-left: 4px solid;
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 0 4px 4px 0;
}
.verifactu-banner-pending {
    background: #f0f4f8;
    border-left-color: #5c6ac4;
    color: #202e78;
}
.verifactu-banner-success {
    background: #eef7f0;
    border-left-color: #28a745;
    color: #155724;
}
.verifactu-banner-error {
    background: #fff0f0;
    border-left-color: #d63638;
    color: #8a1f1f;
}
.verifactu-banner strong {
    display: block;
    font-size: 14px;
    margin-bottom: 6px;
}
.verifactu-banner p {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
    color: #555;
}
.verifactu-section-title {
    font-size: 11px;
    font-weight: 600;
    color: #888;
    text-transform: uppercase;
    margin-bottom: 10px;
    border-bottom: 1px solid #eee;
    padding-bottom: 5px;
}
.verifactu-data-table {
    width: 100%;
    margin-bottom: 20px;
    border-collapse: collapse;
}
.verifactu-data-table td {
    padding: 8px 0;
    font-size: 13px;
    color: #555;
}
.verifactu-data-table td:first-child {
    color: #888;
    width: 40%;
}
.verifactu-pill-dark {
    background: #2c3e50;
    color: #fff;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

/* Timeline */
.verifactu-timeline {
    margin: 15px 0 25px 5px;
}
.verifactu-timeline-item {
    position: relative;
    padding-left: 20px;
    padding-bottom: 15px;
    border-left: 2px solid #ddd;
}
.verifactu-timeline-dot {
    position: absolute;
    left: -6px;
    top: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ccc;
}
.verifactu-timeline-item.done .verifactu-timeline-dot {
    background: #888;
}
.verifactu-timeline-item.active .verifactu-timeline-dot {
    background: #00a396;
    box-shadow: 0 0 0 3px rgba(0, 163, 150, 0.2);
}
.verifactu-timeline-item.error .verifactu-timeline-dot {
    background: #d63638;
}
.verifactu-timeline-content {
    top: -4px;
    position: relative;
}
.verifactu-timeline-content strong {
    display: block;
    font-size: 13px;
    color: #555;
    margin-bottom: 2px;
}
.verifactu-timeline-content span {
    font-size: 11px;
    color: #999;
}

/* QR Box */
.verifactu-qr-box {
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
    margin-bottom: 20px;
}
.verifactu-qr-box img {
    max-width: 100px;
    height: auto;
    background: #fff;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.verifactu-qr-box p {
    margin: 10px 0 0 0;
    font-size: 12px;
    color: #888;
}

/* Actions */
.verifactu-actions-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.verifactu-btn-action-primary,
.verifactu-btn-action-secondary,
.verifactu-btn-action-success,
.verifactu-btn-action-danger {
    width: 100%;
    padding: 8px 12px;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.verifactu-btn-action-primary {
    background: #9ecbed;
    color: #fff;
}
.verifactu-btn-action-primary:hover { background: #85bce4; }

.verifactu-btn-action-success {
    background: #1bbc9b;
    color: #fff;
}
.verifactu-btn-action-success:hover { background: #16a085; }

.verifactu-btn-action-secondary {
    background: #f0f0f1;
    color: #2271b1;
    border: 1px solid #2271b1;
}
.verifactu-btn-action-secondary:hover { background: #f6f7f7; }

.verifactu-btn-action-danger {
    background: #fff;
    color: #d63638;
    border: 1px solid #d63638;
}
.verifactu-btn-action-danger:hover { background: #d63638; color: #fff; }

</style>

<script>
jQuery(document).ready(function($) {
    $('.verifactu-action-btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var actionType = $btn.data('action');
        var orderId = $btn.data('order-id');
        
        var confirmMsg = actionType === 'anular' 
            ? verifactuInFoAL.i18n.confirm_cancel 
            : verifactuInFoAL.i18n.confirm_send;

        if ( actionType !== 'check_nif' && actionType !== 'enviar' && actionType !== 'reenviar' && actionType !== 'sync_now' && ! confirm( confirmMsg ) ) {
            return;
        }

        $btn.prop('disabled', true).css('opacity', '0.7');
        if (actionType === 'sync_now') {
            $btn.text('Sincronizando...');
        }

        var ajaxAction = actionType === 'anular' ? 'verifactu_send_anulacion' : (actionType === 'check_nif' ? 'verifactu_check_nif' : (actionType === 'sync_now' ? 'verifactu_check_pending' : 'verifactu_send_alta'));
        var requestData = {
            action: ajaxAction,
            nonce: verifactuInFoAL.nonce,
            order_id: orderId,
            tipo: 'alta'
        };

        if (actionType === 'check_nif') {
            requestData.nif = $btn.data('nif');
        }

        $.post( verifactuInFoAL.ajaxUrl, requestData, function(response) {
            if ( response.success ) {
                if (actionType === 'check_nif') {
                    alert( response.data.message || verifactuInFoAL.i18n.success_generic );
                    $btn.prop('disabled', false).css('opacity', '1');
                } else {
                    window.location.reload();
                }
            } else {
                alert( response.data && response.data.error ? response.data.error : verifactuInFoAL.i18n.error_generic );
                $btn.prop('disabled', false).css('opacity', '1');
            }
        }).fail(function() {
            alert( verifactuInFoAL.i18n.error_generic );
            $btn.prop('disabled', false).css('opacity', '1');
        });
    });

    $('.verifactu-facturae-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var actionType = $btn.data('action');
        var orderId = $btn.data('order-id');
        var idFacturae = $btn.data('id-facturae');

        var ajaxAction = actionType === 'generate_facturae' ? 'verifactu_generate_facturae' : 'verifactu_send_face';

        $btn.prop('disabled', true).css('opacity', '0.7');

        $.post( verifactuInFoAL.ajaxUrl, {
            action: ajaxAction,
            nonce: verifactuInFoAL.nonce,
            order_id: orderId,
            id_facturae: idFacturae
        }, function(response) {
            if ( response.success ) {
                window.location.reload();
            } else {
                alert( response.data && response.data.error ? response.data.error : verifactuInFoAL.i18n.error_generic );
                $btn.prop('disabled', false).css('opacity', '1');
            }
        }).fail(function() {
            alert( verifactuInFoAL.i18n.error_generic );
            $btn.prop('disabled', false).css('opacity', '1');
        });
    });

    // Countdown logic
    var $countdown = $('#verifactu-countdown');
    if ($countdown.length) {
        var secondsLeft = parseInt($countdown.data('seconds'), 10);
        var timer = setInterval(function() {
            secondsLeft--;
            if (secondsLeft <= 0) {
                $countdown.text('0s (Sincronizando...)');
                clearInterval(timer);
                // Optionally auto-trigger the sync or just reload
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                $countdown.text(secondsLeft + 's');
            }
        }, 1000);
    }
});
</script>
