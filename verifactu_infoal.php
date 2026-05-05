<?php
/**
 * Plugin Name:       VeriFactu InFoAL para WooCommerce
 * Plugin URI:        https://verifactu.infoal.io
 * Description:       Automatiza el envío de registros de facturación al sistema Veri*Factu de la AEAT. Añade el código QR a tus facturas y te permite hacer seguimiento de cada envío. Compatible con WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            InFoAL S.L.
 * Author URI:        https://www.infoal.com
 * License:           Proprietary - All Rights Reserved
 * License URI:       https://verifactu.infoal.io/eula
 * Text Domain:       verifactu-infoal
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.x
 *
 * @author    InFoAL S.L. <hosting@infoal.com>
 * @copyright 2025 InFoAL S.L.
 * @license   Proprietary - All Rights Reserved
 *
 * NOTICE OF LICENSE
 * This source file is subject to a Commercial License (EULA).
 * It is strictly prohibited to redistribute, copy, modify, or resell
 * this code without the written permission of InFoAL S.L.
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Plugin constants ──────────────────────────────────────────────────────────
define( 'VERIFACTU_INFOAL_VERSION',     '1.0.0' );
define( 'VERIFACTU_INFOAL_PLUGIN_FILE', __FILE__ );
define( 'VERIFACTU_INFOAL_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'VERIFACTU_INFOAL_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'VERIFACTU_INFOAL_PLUGIN_BASE', plugin_basename( __FILE__ ) );
define( 'VERIFACTU_INFOAL_TEXT_DOMAIN', 'verifactu-infoal' );
define( 'VERIFACTU_INFOAL_API_BASE',    'https://verifactu.infoal.io/api_v2' );
define( 'VERIFACTU_INFOAL_GITHUB_REPO', 'hostinginfoal/verifactu_woocommerce' );

// ── Minimum requirements check ────────────────────────────────────────────────
register_activation_hook( __FILE__, 'verifactu_infoal_check_requirements' );
function verifactu_infoal_check_requirements() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( VERIFACTU_INFOAL_PLUGIN_BASE );
        wp_die(
            esc_html__( 'VeriFactu InFoAL requiere WooCommerce activo. Por favor, instala y activa WooCommerce antes de activar este plugin.', 'verifactu-infoal' ),
            esc_html__( 'Error de activación', 'verifactu-infoal' ),
            array( 'back_link' => true )
        );
    }

    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( VERIFACTU_INFOAL_PLUGIN_BASE );
        wp_die(
            esc_html__( 'VeriFactu InFoAL requiere PHP 7.4 o superior.', 'verifactu-infoal' ),
            esc_html__( 'Error de activación', 'verifactu-infoal' ),
            array( 'back_link' => true )
        );
    }

    // Require the autoloader before calling the class
    require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'includes/class-verifactu-autoloader.php';
    Verifactu_Autoloader::register();

    Verifactu_Infoal::activate();
}

register_deactivation_hook( __FILE__, 'verifactu_infoal_deactivate' );
function verifactu_infoal_deactivate() {
    require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'includes/class-verifactu-autoloader.php';
    Verifactu_Autoloader::register();

    Verifactu_Infoal::deactivate();
}

// ── Autoload core files ───────────────────────────────────────────────────────
require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'includes/class-verifactu-autoloader.php';
Verifactu_Autoloader::register();

// ── Bootstrap: load the plugin after all plugins are loaded ──────────────────
add_action( 'plugins_loaded', 'verifactu_infoal_init', 20 );
function verifactu_infoal_init() {
    // WooCommerce HPOS (High-Performance Order Storage) compatibility declaration
    add_action( 'before_woocommerce_init', function() {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    });

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'verifactu_infoal_missing_wc_notice' );
        return;
    }

    // Main plugin instance
    Verifactu_Infoal::instance();
}

// ── Admin notice: WooCommerce not active ─────────────────────────────────────
function verifactu_infoal_missing_wc_notice() {
    echo '<div class="notice notice-error"><p>';
    printf(
        /* translators: %s: WooCommerce plugin name */
        esc_html__( 'VeriFactu InFoAL requiere que %s esté instalado y activo.', 'verifactu-infoal' ),
        '<strong>WooCommerce</strong>'
    );
    echo '</p></div>';
}

// ── Activation / Deactivation hooks ─────────────────────────────────────────
register_activation_hook(   __FILE__, array( 'Verifactu_Installer', 'install' ) );
register_deactivation_hook( __FILE__, array( 'Verifactu_Installer', 'deactivate' ) );
