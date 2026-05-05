<?php
/**
 * Main plugin class — singleton orchestrator.
 *
 * Responsibilities:
 *  - Load all sub-components (admin, hooks, services, cron).
 *  - Register activation/deactivation/uninstall routines.
 *  - Expose a clean public API for the rest of the plugin.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Verifactu_Infoal {

    // ── Singleton ─────────────────────────────────────────────────────────────

    /** @var Verifactu_Infoal|null Single instance */
    private static $instance = null;

    /**
     * Get or create the singleton instance.
     *
     * @return Verifactu_Infoal
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Prevent cloning */
    private function __clone() {}

    /** Prevent unserialization */
    public function __wakeup() {
        throw new \Exception( 'Verifactu_Infoal cannot be unserialized.' );
    }

    // ── Constructor ───────────────────────────────────────────────────────────

    private function __construct() {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_hooks();
    }

    // ── Initialisation ────────────────────────────────────────────────────────

    /**
     * Define plugin-wide constants (only those not set in the main file).
     * Main constants (VERSION, DIR, URL…) are already defined in verifactu_infoal.php.
     */
    private function define_constants() {
        // Nothing extra for now — all core constants live in the main plugin file.
        // Additional feature-flags can be added here as the plugin grows.
    }

    /**
     * Require all class files that are NOT auto-loaded (e.g., helpers, traits).
     * Most classes are resolved by Verifactu_Autoloader — only list exceptions here.
     */
    private function load_dependencies() {
        // TODO-FASE1: Installer (DB schema, default options)
        require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'includes/class-verifactu-installer.php';

        // TODO-FASE2: API client
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'api/class-verifactu-api-client.php';

        // TODO-FASE3: Services
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'services/class-verifactu-service-verifactu.php';
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'services/class-verifactu-service-facturae.php';
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'services/class-verifactu-service-qr.php';

        // FASE4: Admin pages
        require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'admin/class-verifactu-admin.php';
        require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'admin/class-verifactu-admin-ajax.php';
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'admin/class-verifactu-admin-settings.php'; // future
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'admin/class-verifactu-admin-orders.php';   // future

        // TODO-FASE5: WooCommerce hooks / order integration
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'includes/class-verifactu-order-hooks.php';

        // TODO-FASE6: Cron / background sync
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'includes/class-verifactu-cron.php';

        // TODO-FASE7: Facturae electronic invoicing
        // require_once VERIFACTU_INFOAL_PLUGIN_DIR . 'services/class-verifactu-service-facturae.php';
    }

    /**
     * Register WordPress / WooCommerce hooks.
     */
    private function init_hooks() {
        // Localisation
        add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

        // Plugin action links (Settings link in the plugins list)
        add_filter( 'plugin_action_links_' . VERIFACTU_INFOAL_PLUGIN_BASE, array( $this, 'plugin_action_links' ) );

        // TODO-FASE1: Run DB upgrades on plugin version bump
        add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 10 );

        // FASE4: Admin UI — boot admin only in back-office context
        if ( is_admin() ) {
            Verifactu_Admin::instance();
            Verifactu_Admin_Ajax::register_hooks();
        }

        // FASE5: WooCommerce order / invoice hooks
        Verifactu_Order_Hooks::instance();

        // FASE6: Cron scheduler
        // Cron hook is registered in Order Hooks
        add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );

        // FASE9: Updater
        add_action( 'admin_notices', array( 'Verifactu_Updater', 'display_update_notice' ) );
    }

    // ── Public methods ────────────────────────────────────────────────────────

    /**
     * Load plugin translations.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            VERIFACTU_INFOAL_TEXT_DOMAIN,
            false,
            dirname( VERIFACTU_INFOAL_PLUGIN_BASE ) . '/languages/'
        );
    }

    /**
     * Add a "Settings" link in the plugins list table.
     *
     * @param  array $links Existing action links.
     * @return array
     */
    public function plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=verifactu-infoal' ) ),
            esc_html__( 'Configuración', 'verifactu-infoal' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Run DB upgrade routines when the stored version differs from the current one.
     * Equivalent to PrestaShop's upgrade-{version}.php mechanism.
     */
    public function maybe_upgrade() {
        $stored_version = get_option( 'verifactu_infoal_version', '0.0.0' );
        if ( version_compare( $stored_version, VERIFACTU_INFOAL_VERSION, '<' ) ) {
            Verifactu_Installer::upgrade( $stored_version, VERIFACTU_INFOAL_VERSION );
            update_option( 'verifactu_infoal_version', VERIFACTU_INFOAL_VERSION );
        }
    }

    /**
     * Add custom cron intervals.
     */
    public function add_cron_intervals( $schedules ) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __( 'Cada minuto', 'verifactu-infoal' )
        );
        return $schedules;
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Write a log entry to the WooCommerce logger.
     * Equivalent to Verifactu::writeLog() in the PrestaShop module.
     *
     * @param string $message  Log message.
     * @param int    $level    0=debug, 1=info, 2=warning, 3=error.
     * @param string $source   Log source/channel identifier.
     */
    public static function log( $message, $level = 1, $source = 'verifactu-infoal' ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $logger  = wc_get_logger();
        $context = array( 'source' => $source );

        switch ( $level ) {
            case 0:
                $logger->debug( $message, $context );
                break;
            case 2:
                $logger->warning( $message, $context );
                break;
            case 3:
                $logger->error( $message, $context );
                break;
            default:
                $logger->info( $message, $context );
                break;
        }
    }

    /**
     * Get a plugin option with an optional default.
     * Options are stored under the 'verifactu_infoal_settings' option key (array).
     *
     * @param  string $key     Option key.
     * @param  mixed  $default Default value.
     * @return mixed
     */
    public static function get_option( $key, $default = '' ) {
        $settings = get_option( 'verifactu_infoal_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    /**
     * Update a single plugin option inside the settings array.
     *
     * @param string $key   Option key.
     * @param mixed  $value New value.
     */
    public static function update_option( $key, $value ) {
        $settings         = get_option( 'verifactu_infoal_settings', array() );
        $settings[ $key ] = $value;
        update_option( 'verifactu_infoal_settings', $settings );
    }

    /**
     * Get the configured API token.
     *
     * @return string
     */
    public static function get_api_token() {
        return self::get_option( 'api_token', '' );
    }

    /**
     * Get the configured NIF Emisor.
     *
     * @return string
     */
    public static function get_nif_emisor() {
        return self::get_option( 'nif_emisor', '' );
    }

    /**
     * Whether debug mode is active.
     *
     * @return bool
     */
    public static function is_debug() {
        return (bool) self::get_option( 'debug_mode', false );
    }
}
