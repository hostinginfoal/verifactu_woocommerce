<?php
/**
 * Admin — main admin class. Boots all back-office sub-components.
 *
 * Registers:
 *  - Admin menu pages (Settings, Invoices, Refunds, Reg. Facts, Facturae, Help)
 *  - Admin assets (CSS + JS)
 *  - WooCommerce order panel widget (order meta box)
 *  - AJAX handlers
 *
 * Equivalent to getContent() / install() menu registration in verifactu.php (PS module).
 *
 * @package VeriFactu_InFoAL
 * @todo FASE4 — Implement all admin pages and hooks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Admin {

    // ── Singleton ─────────────────────────────────────────────────────────────

    /** @var Verifactu_Admin|null */
    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}

    // ── Constructor ───────────────────────────────────────────────────────────

    private function __construct() {
        $this->init_hooks();
    }

    // ── Hooks ─────────────────────────────────────────────────────────────────

    private function init_hooks() {
        // Register admin menu
        add_action( 'admin_menu',              array( $this, 'register_menu' ), 55 );

        // Enqueue admin styles and scripts
        add_action( 'admin_enqueue_scripts',   array( $this, 'enqueue_assets' ) );

        // Add plugin settings link
        add_filter( 'plugin_action_links_' . VERIFACTU_INFOAL_PLUGIN_BASE,
                    array( $this, 'plugin_action_links' ) );

        // FASE4: Register meta box in WC order screen
        add_action( 'add_meta_boxes',          array( $this, 'register_order_meta_box' ) );

        // FASE4: HPOS compatibility
        add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'register_order_meta_box' ) );
    }

    // ── Menu registration ─────────────────────────────────────────────────────

    /**
     * Register all plugin admin menu pages.
     *
     * Menu structure (mirrors PrestaShop module tabs):
     *   VeriFactu InFoAL (top-level)
     *    ├── Inicio / Dashboard
     *    ├── Configuración
     *    ├── Facturas de venta
     *    ├── Facturas por abono
     *    ├── Registros de facturación
     *    ├── Facturae electrónica
     *    └── Ayuda
     *
     * @todo FASE4 — Implement page callbacks
     */
    public function register_menu() {
        // Top-level menu (equivalent to AdminVerifactuParent tab in PS)
        add_menu_page(
            __( 'VeriFactu InFoAL', 'verifactu-infoal' ),
            __( 'VeriFactu', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-infoal',
            array( $this, 'page_dashboard' ),
            'dashicons-shield-alt',
            56
        );

        // Dashboard (first child = duplicate of parent, standard WP pattern)
        add_submenu_page(
            'verifactu-infoal',
            __( 'Dashboard VeriFactu', 'verifactu-infoal' ),
            __( 'Dashboard', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-infoal',
            array( $this, 'page_dashboard' )
        );

        // Settings (Configuración)
        add_submenu_page(
            'verifactu-infoal',
            __( 'Configuración — VeriFactu InFoAL', 'verifactu-infoal' ),
            __( 'Configuración', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-settings',
            array( $this, 'page_settings' )
        );

        // Sales invoices (Facturas de venta)
        add_submenu_page(
            'verifactu-infoal',
            __( 'Facturas de Venta — VeriFactu', 'verifactu-infoal' ),
            __( 'Facturas de venta', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-invoices',
            array( $this, 'page_invoices' )
        );

        // Credit notes (Facturas por abono)
        add_submenu_page(
            'verifactu-infoal',
            __( 'Facturas por Abono — VeriFactu', 'verifactu-infoal' ),
            __( 'Facturas por abono', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-refunds',
            array( $this, 'page_refunds' )
        );

        // Registration log (Registros de facturación)
        add_submenu_page(
            'verifactu-infoal',
            __( 'Registros de Facturación — VeriFactu', 'verifactu-infoal' ),
            __( 'Registros', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-reg-facts',
            array( $this, 'page_reg_facts' )
        );

        // Facturae 3.2.2
        add_submenu_page(
            'verifactu-infoal',
            __( 'Facturae Electrónica — VeriFactu InFoAL', 'verifactu-infoal' ),
            __( 'Facturae', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-facturae',
            array( $this, 'page_facturae' )
        );

        // Help / FAQ
        add_submenu_page(
            'verifactu-infoal',
            __( 'Ayuda — VeriFactu InFoAL', 'verifactu-infoal' ),
            __( 'Ayuda', 'verifactu-infoal' ),
            'manage_woocommerce',
            'verifactu-help',
            array( $this, 'page_help' )
        );
    }

    // ── Page callbacks ────────────────────────────────────────────────────────

    /**
     * Dashboard — statistics, recent errors, API status.
     * Equivalent to renderDashboard() in verifactu.php.
     *
     * @todo FASE4 — Implement
     */
    public function page_dashboard() {
        $this->render_view( 'dashboard' );
    }

    public function page_settings() {
        if ( isset( $_POST['verifactu_save_settings'] ) && check_admin_referer( 'verifactu_save_settings' ) ) {
            $this->save_settings();
        }
        settings_errors( 'verifactu_messages' );
        $this->render_view( 'settings' );
    }

    /**
     * Sales invoices list.
     * Equivalent to renderSalesInvoicesList() in verifactu.php.
     *
     * @todo FASE4 — Implement with WP_List_Table
     */
    public function page_invoices() {
        $this->render_view( 'invoices' );
    }

    /**
     * Credit notes list.
     * Equivalent to renderCreditSlipsList() in verifactu.php.
     *
     * @todo FASE4 — Implement with WP_List_Table
     */
    public function page_refunds() {
        $this->render_view( 'refunds' );
    }

    /**
     * Registration log.
     * Equivalent to renderList() in verifactu.php.
     *
     * @todo FASE4 — Implement with WP_List_Table
     */
    public function page_reg_facts() {
        $this->render_view( 'reg-facts' );
    }

    /**
     * Facturae 3.2.2 electronic invoices.
     * Equivalent to renderFacturaeList() in verifactu.php.
     *
     * @todo FASE7 — Implement
     */
    public function page_facturae() {
        $this->render_view( 'facturae' );
    }

    /**
     * Help / FAQ / Diagnostic.
     * Equivalent to renderHelp() in verifactu.php.
     *
     * @todo FASE4 — Implement
     */
    public function page_help() {
        $this->render_view( 'help' );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    /**
     * Enqueue CSS and JS for admin pages.
     * Equivalent to hookActionAdminControllerSetMedia + displayBackOfficeHeader in PS.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     *
     * @todo FASE4 — Scope to plugin pages only
     */
    public function enqueue_assets( $hook_suffix ) {
        // Only enqueue on our plugin pages
        // TODO-FASE4: add hook suffix check
        // if ( strpos( $hook_suffix, 'verifactu' ) === false ) {
        //     return;
        // }

        wp_enqueue_style(
            'verifactu-infoal-admin',
            VERIFACTU_INFOAL_PLUGIN_URL . 'admin/css/verifactu-admin.css',
            array(),
            VERIFACTU_INFOAL_VERSION
        );

        wp_enqueue_script(
            'verifactu-infoal-admin',
            VERIFACTU_INFOAL_PLUGIN_URL . 'admin/js/verifactu-admin.js',
            array( 'jquery' ),
            VERIFACTU_INFOAL_VERSION,
            true
        );

        // Pass data to JS (equivalent to JS variables in back.js)
        wp_localize_script( 'verifactu-infoal-admin', 'verifactuInFoAL', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'verifactu_infoal_nonce' ),
            'i18n'      => array(
                'confirm_send'    => __( '¿Enviar esta factura a VeriFactu?', 'verifactu-infoal' ),
                'confirm_cancel'  => __( '¿Anular este registro en VeriFactu?', 'verifactu-infoal' ),
                'sending'         => __( 'Enviando…', 'verifactu-infoal' ),
                'error_generic'   => __( 'Error inesperado. Comprueba la consola.', 'verifactu-infoal' ),
            ),
        ) );

        // SweetAlert2 (CDN — same as PS module)
        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
            array(),
            '11',
            true
        );
    }

    // ── Settings handling ─────────────────────────────────────────────────────

    private function save_settings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $fields_text = array(
            'api_token'      => 'verifactu_api_token',
            'nif_emisor'     => 'verifactu_nif_emisor',
            'qr_text'        => 'verifactu_qr_text',
            'invoice_engine' => 'verifactu_invoice_engine',
        );

        foreach ( $fields_text as $option_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                Verifactu_Infoal::update_option( $option_key, sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) );
            }
        }

        $fields_int = array(
            'qr_width' => 'verifactu_qr_width',
        );

        foreach ( $fields_int as $option_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                Verifactu_Infoal::update_option( $option_key, absint( wp_unslash( $_POST[ $post_key ] ) ) );
            }
        }

        $fields_bool = array(
            'usa_oss'                   => 'verifactu_usa_oss',
            'territorio_especial'       => 'verifactu_territorio_especial',
            'qr_hide_default'           => 'verifactu_qr_hide_default',
            'show_anulacion_button'     => 'verifactu_show_anulacion_button',
            'lock_order_if_correct'     => 'verifactu_lock_order_if_correct',
            'debug_mode'                => 'verifactu_debug_mode',
            'recargo_compat'            => 'verifactu_recargo_compat',
        );

        foreach ( $fields_bool as $option_key => $post_key ) {
            $val = isset( $_POST[ $post_key ] ) ? 1 : 0;
            Verifactu_Infoal::update_option( $option_key, $val );
        }

        add_settings_error( 'verifactu_messages', 'verifactu_message', __( 'Configuración guardada correctamente.', 'verifactu-infoal' ), 'updated' );
    }

    // ── Order meta box ────────────────────────────────────────────────────────

    public function register_order_meta_box() {
        $screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'verifactu-infoal-order-panel',
            __( 'VeriFactu InFoAL', 'verifactu-infoal' ),
            array( $this, 'render_order_meta_box' ),
            $screen,
            'side',
            'high'
        );
    }

    public function render_order_meta_box( $post_or_order ) {
        $order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
        if ( ! $order ) {
            return;
        }

        $service = new Verifactu_Service_Verifactu();
        $status = $service->get_status_for_order( $order->get_id(), 'invoice' );

        $this->render_view( 'meta-box-order', array(
            'order'  => $order,
            'status' => $status
        ) );
    }

    // ── Plugin action links ───────────────────────────────────────────────────

    /**
     * Add "Configuración" link in Plugins list.
     *
     * @param  array $links
     * @return array
     */
    public function plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=verifactu-settings' ) ),
            esc_html__( 'Configuración', 'verifactu-infoal' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    // ── View rendering ────────────────────────────────────────────────────────

    /**
     * Load and render an admin view template.
     *
     * @param string $view  View name (maps to admin/views/{view}.php).
     * @param array  $data  Data to extract into the view scope.
     */
    private function render_view( $view, array $data = array() ) {
        $file = VERIFACTU_INFOAL_PLUGIN_DIR . 'admin/views/' . $view . '.php';

        if ( ! file_exists( $file ) ) {
            printf(
                '<div class="notice notice-error"><p>%s <code>%s</code></p></div>',
                esc_html__( 'Vista no encontrada:', 'verifactu-infoal' ),
                esc_html( $view )
            );
            return;
        }

        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract( $data );
        include $file;
    }
}
