<?php
/**
 * Facturae Service — handles generation and submission of Facturae 3.2.2 via InFoAL API.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Service_Facturae {

    /** @var Verifactu_Api_Client */
    private $api;

    public function __construct() {
        $this->api = new Verifactu_Api_Client( Verifactu_Infoal::get_option( 'api_token', '' ) );
    }

    public function generate_from_order( $order_id, $tipo = 'alta' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return array( 'success' => false, 'error' => 'Pedido no encontrado.' );
        }

        // Simplification: We rely on the core verifactu service to build the payload 
        // since the Facturae API endpoint accepts the exact same payload format 
        // as the VeriFactu ALTA endpoint.
        $verifactu_service = new Verifactu_Service_Verifactu();
        $payload = $verifactu_service->build_alta_payload( $order, $tipo );

        if ( is_wp_error( $payload ) ) {
            return array( 'success' => false, 'error' => $payload->get_error_message() );
        }

        $result = $this->api->facturae_alta( $payload );

        if ( isset( $result['response'] ) && $result['response'] === 'OK' ) {
            // Save Facturae state
            global $wpdb;
            $table = $wpdb->prefix . 'facturae';
            $wpdb->replace( $table, array(
                'id_order'    => $order_id,
                'id_facturae' => $result['id_facturae'],
                'estado'      => 'Generada'
            ) );

            return array( 'success' => true, 'id_facturae' => $result['id_facturae'] );
        }

        return array( 'success' => false, 'error' => isset( $result['error'] ) ? $result['error'] : 'Error al generar Facturae' );
    }

    public function generate_from_refund( $refund_id ) {
        return $this->generate_from_order( $refund_id, 'abono' );
    }

    public function download_xsig( $id_facturae ) {
        $result = $this->api->facturae_download_xsig( array( 'id_facturae' => $id_facturae ) );
        if ( is_wp_error( $result ) ) {
            return false;
        }
        return $result;
    }

    public function download_xml( $id_facturae ) {
        $result = $this->api->facturae_download_xml( array( 'id_facturae' => $id_facturae ) );
        if ( is_wp_error( $result ) ) {
            return false;
        }
        return $result;
    }

    public function send_to_face( $id_facturae ) {
        $result = $this->api->facturae_send_face( array( 'id_facturae' => $id_facturae ) );
        if ( isset( $result['response'] ) && $result['response'] === 'OK' ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'facturae',
                array( 'estado_face' => 'Enviada' ),
                array( 'id_facturae' => $id_facturae )
            );
            return array( 'success' => true );
        }
        return array( 'success' => false, 'error' => isset( $result['error'] ) ? $result['error'] : 'Error' );
    }

    public function get_face_status( $id_facturae ) {
        $result = $this->api->facturae_face_status( array( 'id_facturae' => $id_facturae ) );
        if ( isset( $result['response'] ) && $result['response'] === 'OK' ) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'facturae',
                array( 'estado_face' => isset( $result['estado'] ) ? $result['estado'] : 'Actualizado' ),
                array( 'id_facturae' => $id_facturae )
            );
            return array( 'success' => true, 'estado' => $result['estado'] );
        }
        return array( 'success' => false, 'error' => isset( $result['error'] ) ? $result['error'] : 'Error' );
    }
}
