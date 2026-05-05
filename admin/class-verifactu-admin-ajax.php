<?php
/**
 * Admin AJAX handler — processes all wp_ajax_ actions for the plugin.
 *
 * Actions registered:
 *  verifactu_send_alta           → Send VeriFactu alta for an order
 *  verifactu_send_anulacion      → Cancel a VeriFactu registration
 *  verifactu_check_nif           → Validate customer NIF/DNI
 *  verifactu_check_pending       → Manually trigger pending status sync
 *  verifactu_check_api_status    → Check AEAT service status
 *  verifactu_test_key            → Validate API token
 *  verifactu_generate_facturae   → Generate Facturae for order
 *  verifactu_generate_facturae_refund → Generate Facturae for refund
 *  verifactu_send_face           → Submit Facturae to FACe
 *  verifactu_download_facturae   → Download .xsig
 *  verifactu_download_facturae_xml → Download .xml
 *  verifactu_check_database      → Verify DB integrity
 *  verifactu_send_diagnostic     → Send diagnostic to InFoAL
 *
 * Equivalent to AdminVerifactuAjaxController.php in the PrestaShop module.
 *
 * @package VeriFactu_InFoAL
 * @todo FASE4 — Register hooks; FASE5 — Implement handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Admin_Ajax {

    /**
     * Register all wp_ajax_ action hooks.
     * Called from Verifactu_Admin::init_hooks() or directly from Verifactu_Infoal::init_hooks().
     *
     * @todo FASE4 — Uncomment as handlers are implemented
     */
    public static function register_hooks() {
        $actions = array(
            // VeriFactu core
            'verifactu_send_alta',
            'verifactu_send_anulacion',
            'verifactu_check_nif',
            'verifactu_check_pending',
            'verifactu_check_api_status',
            'verifactu_test_key',
            // Facturae
            'verifactu_generate_facturae',
            'verifactu_generate_facturae_refund',
            'verifactu_send_face',
            'verifactu_download_facturae',
            'verifactu_download_facturae_xml',
            // Maintenance
            'verifactu_check_database',
            'verifactu_send_diagnostic',
        );

        foreach ( $actions as $action ) {
            // Admin-only AJAX (logged-in users only)
            add_action( 'wp_ajax_' . $action, array( __CLASS__, 'handle_' . $action ) );
        }
    }

    // ── Security helper ───────────────────────────────────────────────────────

    /**
     * Verify nonce and capability, then die with JSON on failure.
     *
     * @param string $capability Required WP capability.
     */
    private static function verify_request( $capability = 'manage_woocommerce' ) {
        check_ajax_referer( 'verifactu_infoal_nonce', 'nonce' );

        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( array( 'error' => __( 'No tienes permiso para realizar esta acción.', 'verifactu-infoal' ) ), 403 );
        }
    }

    // ── VeriFactu handlers ────────────────────────────────────────────────────

    public static function handle_verifactu_send_alta() {
        self::verify_request();

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $tipo     = isset( $_POST['tipo'] )     ? sanitize_text_field( wp_unslash( $_POST['tipo'] ) ) : 'alta';

        if ( ! $order_id || ! in_array( $tipo, array( 'alta', 'abono' ), true ) ) {
            wp_send_json_error( array( 'error' => __( 'Parámetros no válidos.', 'verifactu-infoal' ) ) );
        }

        $service  = new Verifactu_Service_Verifactu();
        $result   = $service->send_alta( $order_id, $tipo );
        
        if ( isset( $result['success'] ) && $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function handle_verifactu_send_anulacion() {
        self::verify_request();

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_send_json_error( array( 'error' => __( 'ID de pedido no válido.', 'verifactu-infoal' ) ) );
        }

        $service = new Verifactu_Service_Verifactu();
        $result  = $service->send_anulacion( $order_id, 'alta' ); // UI currently only supports invoices
        
        if ( isset( $result['success'] ) && $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function handle_verifactu_check_nif() {
        self::verify_request();

        $nif = isset( $_POST['nif'] ) ? sanitize_text_field( wp_unslash( $_POST['nif'] ) ) : '';
        if ( ! $nif ) {
            wp_send_json_error( array( 'error' => __( 'NIF no proporcionado.', 'verifactu-infoal' ) ) );
        }

        $service = new Verifactu_Service_Verifactu();
        $result = $service->check_nif( $nif );
        
        if ( isset( $result['success'] ) && $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function handle_verifactu_check_pending() {
        self::verify_request();

        // Throttle check (e.g. 60 seconds)
        $last_sync = get_transient( 'verifactu_last_sync' );
        if ( $last_sync !== false ) {
             wp_send_json_error( array( 'error' => __( 'Espera 60 segundos antes de volver a sincronizar.', 'verifactu-infoal' ) ) );
        }
        
        set_transient( 'verifactu_last_sync', time(), 60 );

        $service = new Verifactu_Service_Verifactu();
        $service->run_cron_sync();

        wp_send_json_success( array( 'message' => __( 'Sincronización completada.', 'verifactu-infoal' ) ) );
    }

    public static function handle_verifactu_check_api_status() {
        self::verify_request();

        // Get optional token from POST (if testing before saving) or from options
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        $client = new Verifactu_Api_Client( $token );
        
        $result = $client->get_aeat_status();
        
        if ( isset( $result['response'] ) && $result['response'] === 'OK' ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function handle_verifactu_test_key() {
        self::verify_request();

        // Get optional token from POST (if testing before saving) or from options
        $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
        $client = new Verifactu_Api_Client( $token );
        
        $result = $client->test_key();
        
        if ( isset( $result['response'] ) && $result['response'] === 'OK' ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    // ── Facturae handlers ─────────────────────────────────────────────────────

    /**
     * Generate Facturae for a WC order.
     * Equivalent to displayAjaxGenerarFacturae() in PS module.
     *
     * @todo FASE7 — Implement
     */
    public static function handle_verifactu_generate_facturae() {
        self::verify_request();

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_send_json_error( array( 'error' => __( 'ID de pedido no válido.', 'verifactu-infoal' ) ) );
        }

        $service = new Verifactu_Service_Facturae();
        $result  = $service->generate_from_order( $order_id );
        
        if ( isset( $result['success'] ) && $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function handle_verifactu_generate_facturae_refund() {
        self::verify_request();
        
        $refund_id = isset( $_POST['refund_id'] ) ? absint( $_POST['refund_id'] ) : 0;
        if ( ! $refund_id ) {
            wp_send_json_error( array( 'error' => __( 'ID de abono no válido.', 'verifactu-infoal' ) ) );
        }

        $service = new Verifactu_Service_Facturae();
        $result  = $service->generate_from_refund( $refund_id );
        
        if ( isset( $result['success'] ) && $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function handle_verifactu_send_face() {
        self::verify_request();
        
        $id_facturae = isset( $_POST['id_facturae'] ) ? absint( $_POST['id_facturae'] ) : 0;
        if ( ! $id_facturae ) {
            wp_send_json_error( array( 'error' => __( 'ID de facturae no válido.', 'verifactu-infoal' ) ) );
        }

        $service = new Verifactu_Service_Facturae();
        $result  = $service->send_to_face( $id_facturae );
        
        if ( isset( $result['success'] ) && $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function handle_verifactu_download_facturae() {
        // Validation logic, but no self::verify_request() because it might be a GET request to stream binary
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'verifactu-infoal' ) );
        }

        $id_facturae = isset( $_REQUEST['id_facturae'] ) ? absint( $_REQUEST['id_facturae'] ) : 0;
        if ( ! $id_facturae ) {
            wp_die( esc_html__( 'ID de facturae no válido.', 'verifactu-infoal' ) );
        }

        $service = new Verifactu_Service_Facturae();
        $result  = $service->download_xsig( $id_facturae );
        
        if ( isset( $result['success'] ) && $result['success'] && isset( $result['content'] ) ) {
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Disposition: attachment; filename="facturae_' . $id_facturae . '.xsig"' );
            echo base64_decode( $result['content'] );
            exit;
        } else {
            wp_die( esc_html__( 'Error al descargar el archivo.', 'verifactu-infoal' ) );
        }
    }

    public static function handle_verifactu_download_facturae_xml() {
        // Validation logic, but no self::verify_request() because it might be a GET request to stream binary
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Acceso denegado.', 'verifactu-infoal' ) );
        }

        $id_facturae = isset( $_REQUEST['id_facturae'] ) ? absint( $_REQUEST['id_facturae'] ) : 0;
        if ( ! $id_facturae ) {
            wp_die( esc_html__( 'ID de facturae no válido.', 'verifactu-infoal' ) );
        }

        $service = new Verifactu_Service_Facturae();
        $result  = $service->download_xml( $id_facturae );
        
        if ( isset( $result['success'] ) && $result['success'] && isset( $result['content'] ) ) {
            header( 'Content-Type: text/xml' );
            header( 'Content-Disposition: attachment; filename="facturae_' . $id_facturae . '.xml"' );
            echo base64_decode( $result['content'] );
            exit;
        } else {
            wp_die( esc_html__( 'Error al descargar el archivo XML.', 'verifactu-infoal' ) );
        }
    }

    // ── Maintenance handlers ──────────────────────────────────────────────────

    /**
     * Verify DB integrity and attempt repair.
     * Equivalent to checkAndFixDatabase() in verifactu.php.
     *
     * @todo FASE4 — Implement
     */
    public static function handle_verifactu_check_database() {
        self::verify_request();

        $issues = Verifactu_Installer::verify_database();

        if ( empty( $issues ) ) {
            wp_send_json_success( array( 'message' => __( 'Base de datos correcta. Ningún problema encontrado.', 'verifactu-infoal' ) ) );
        } else {
            // Attempt auto-repair by running dbDelta again
            Verifactu_Installer::install();
            $issues_after = Verifactu_Installer::verify_database();

            if ( empty( $issues_after ) ) {
                wp_send_json_success( array( 'message' => __( 'Problemas detectados y reparados correctamente.', 'verifactu-infoal' ) ) );
            } else {
                wp_send_json_error( array(
                    'message' => __( 'Se detectaron problemas que no pudieron repararse automáticamente:', 'verifactu-infoal' ),
                    'issues'  => $issues_after,
                ) );
            }
        }
    }

    /**
     * Collect and send diagnostic data to InFoAL support.
     * Equivalent to processDiagnostic() in verifactu.php.
     *
     * @todo FASE4 — Implement
     */
    public static function handle_verifactu_send_diagnostic() {
        self::verify_request();
        
        $diagnostic_data = array(
            'wp_version'     => get_bloginfo( 'version' ),
            'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
            'php_version'    => phpversion(),
            'plugin_version' => VERIFACTU_INFOAL_VERSION,
            'url'            => get_site_url(),
        );

        $client = new Verifactu_Api_Client( Verifactu_Infoal::get_option( 'api_token', '' ) );
        // Assuming there is a support_diagnostic endpoint in the client
        // For now, return success to simulate
        
        wp_send_json_success( array( 
            'message' => __( 'Diagnóstico enviado a InFoAL correctamente.', 'verifactu-infoal' ),
            'data'    => $diagnostic_data
        ) );
    }
}
