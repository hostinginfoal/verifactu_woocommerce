<?php
/**
 * PSR-4-style autoloader for the Verifactu InFoAL plugin.
 *
 * Maps class name prefixes to directories:
 *   Verifactu_*  → includes/
 *   Verifactu_Api_*  → api/
 *   Verifactu_Service_*  → services/
 *   Verifactu_Admin_*  → admin/
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Autoloader {

    /**
     * Register the autoloader with SPL.
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'load' ) );
    }

    /**
     * Attempt to load a class file based on naming convention.
     *
     * Naming convention: Verifactu_{Segment}_{Name}
     * → file: {dir}/{segment}/class-verifactu-{name}.php
     *
     * Examples:
     *   Verifactu_Infoal       → includes/class-verifactu-infoal.php
     *   Verifactu_Installer    → includes/class-verifactu-installer.php
     *   Verifactu_Api_Client   → api/class-verifactu-api-client.php
     *   Verifactu_Service_Qr   → services/class-verifactu-service-qr.php
     *   Verifactu_Admin_Page   → admin/class-verifactu-admin-page.php
     *
     * @param string $class_name
     */
    public static function load( $class_name ) {
        // Only handle our classes
        if ( strpos( $class_name, 'Verifactu_' ) !== 0 ) {
            return;
        }

        $base_dir = VERIFACTU_INFOAL_PLUGIN_DIR;

        // Convert class name to filename: Verifactu_Foo_Bar → class-verifactu-foo-bar.php
        $file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

        // Build segment-based path
        $parts = explode( '_', $class_name );

        // Determine subdirectory from second segment
        $subdirectory = '';
        if ( isset( $parts[1] ) ) {
            switch ( strtolower( $parts[1] ) ) {
                case 'api':
                    $subdirectory = 'api/';
                    break;
                case 'service':
                    $subdirectory = 'services/';
                    break;
                case 'admin':
                    $subdirectory = 'admin/';
                    break;
                default:
                    $subdirectory = 'includes/';
                    break;
            }
        }

        $file_path = $base_dir . $subdirectory . $file_name;

        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}
