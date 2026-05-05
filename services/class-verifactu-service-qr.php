<?php
/**
 * QR Service — handles QR code generation for VeriFactu.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Service_Qr {

    /**
     * Generate HTML for the QR code to be embedded in PDFs or emails.
     * 
     * @param string $url The VeriFactu URL to encode.
     * @return string HTML output
     */
    public function get_qr_img_html( $url ) {
        if ( empty( $url ) ) {
            return '';
        }

        $width = Verifactu_Infoal::get_option( 'qr_width', 60 );
        $text  = Verifactu_Infoal::get_option( 'qr_text', 'Factura verificable en la sede electrónica de la AEAT' );

        // Generate via an external QR API (qrserver) since we want to keep the plugin light without 
        // requiring large PHP dependencies like endroid/qr-code, unless it's already installed.
        $qr_api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $width . 'x' . $width . '&data=' . urlencode( $url );
        
        $html = '<div class="verifactu-qr-container" style="text-align:center; margin-top: 15px;">';
        $html .= '<img src="' . esc_url( $qr_api_url ) . '" alt="VeriFactu QR" width="' . esc_attr( $width ) . '" height="' . esc_attr( $width ) . '">';
        if ( ! empty( $text ) ) {
            $html .= '<p class="verifactu-qr-text" style="font-size: 8px; color: #555;">' . esc_html( $text ) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }
}
