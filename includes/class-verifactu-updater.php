<?php
/**
 * Updater — Handles GitHub releases checks.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Updater {

    public static function check_for_updates() {
        $transient_key = 'verifactu_github_update';
        $update_data = get_transient( $transient_key );

        if ( false === $update_data ) {
            $url = 'https://api.github.com/repos/' . VERIFACTU_INFOAL_GITHUB_REPO . '/releases/latest';
            $response = wp_remote_get( $url, array(
                'headers' => array( 'User-Agent' => 'WordPress/VeriFactu-InFoAL' ),
                'timeout' => 10,
            ) );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = wp_remote_retrieve_body( $response );
                $data = json_decode( $body );

                if ( ! empty( $data->tag_name ) ) {
                    $latest_version = ltrim( $data->tag_name, 'v' );
                    if ( version_compare( VERIFACTU_INFOAL_VERSION, $latest_version, '<' ) ) {
                        $update_data = array(
                            'version' => $latest_version,
                            'url'     => $data->html_url,
                        );
                    } else {
                        $update_data = 'no_update';
                    }
                }
            } else {
                $update_data = 'error';
            }

            set_transient( $transient_key, $update_data, 12 * HOUR_IN_SECONDS );
        }

        return ( is_array( $update_data ) ) ? $update_data : false;
    }

    public static function display_update_notice() {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        $update = self::check_for_updates();

        if ( $update ) {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s <strong>%s</strong>. <a href="%s" target="_blank">%s</a></p></div>',
                esc_html__( 'Hay una nueva versión del plugin VeriFactu InFoAL disponible:', 'verifactu-infoal' ),
                esc_html( $update['version'] ),
                esc_url( $update['url'] ),
                esc_html__( 'Descargar actualización', 'verifactu-infoal' )
            );
        }
    }
}
