<?php
/**
 * Installer — handles DB table creation, option defaults, and upgrades.
 *
 * Equivalent to sql/install.php + upgrade/upgrade-x.y.z.php in the PrestaShop module.
 *
 * Tables created:
 *  {prefix}verifactu_reg_fact         — Master log of all VeriFactu submissions
 *  {prefix}verifactu_order_invoice    — Per-order VeriFactu status (sales invoices)
 *  {prefix}verifactu_order_refund     — Per-refund VeriFactu status (credit notes)
 *  {prefix}verifactu_facturae         — Facturae 3.2.2 electronic invoices
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Installer {

    // ── DB schema version (bump this on every schema change) ─────────────────
    const DB_VERSION = '1.0.0';

    // ── Option defaults ───────────────────────────────────────────────────────

    /**
     * Default plugin settings stored in 'verifactu_infoal_settings'.
     *
     * @var array<string, mixed>
     */
    private static $default_settings = array(
        // API connectivity
        'api_token'                 => '',
        'nif_emisor'                => '',
        'invoice_engine'            => 'wp_overnight',

        // Debug / maintenance
        'debug_mode'                => false,

        // Fiscal options
        'usa_oss'                   => false,   // Ventanilla Única (OSS)
        'territorio_especial'       => 0,       // 0=normal, 1=IGIC (Canarias), 2=IPSI (Ceuta/Melilla)
        'recargo_compat'            => false,   // Recargo de Equivalencia compatibility

        // QR code options
        'qr_width'                  => 60,      // px
        'qr_text'                   => 'Factura verificable en la sede electrónica de la AEAT',
        'qr_hide_default'           => false,   // Hide default QR (use shortcode/hook instead)
        'qr_position'               => 'auto',  // 'auto' | 'top' | 'bottom'

        // UI / UX
        'show_anulacion_button'     => true,
        'lock_order_if_correct'     => true,    // Lock WC order editing once AEAT confirms

        // Cron / sync
        'cron_interval'             => 'every_minute', // WP cron schedule
    );

    // ── Activation (install) ──────────────────────────────────────────────────

    /**
     * Run on plugin activation.
     * Creates tables and seeds default options.
     */
    public static function install() {
        self::create_tables();
        self::seed_options();
        self::schedule_cron();

        // Store DB schema version for future upgrade detection
        update_option( 'verifactu_infoal_db_version', self::DB_VERSION );
        // Store plugin version
        update_option( 'verifactu_infoal_version', VERIFACTU_INFOAL_VERSION );

        // Flush rewrite rules (in case we register CPTs or endpoints later)
        flush_rewrite_rules();
    }

    // ── DB schema ─────────────────────────────────────────────────────────────

    /**
     * Create all plugin tables using dbDelta() (idempotent — safe to re-run).
     *
     * Column naming follows the PrestaShop module's VeriFactu field names
     * (PascalCase for AEAT API fields) to simplify API payload mapping.
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── 1. Master log ─────────────────────────────────────────────────────
        // Stores every VeriFactu submission (alta / abono / anulación) with the
        // full AEAT response: hash, chain, QR URL, states, issuer/buyer data…
        $sql_reg_fact = "CREATE TABLE {$wpdb->prefix}verifactu_reg_fact (
            id_reg_fact          BIGINT(20)   NOT NULL,
            id_order_invoice     BIGINT(20)   NOT NULL,
            tipo                 VARCHAR(20)  DEFAULT NULL,
            EstadoEnvio          VARCHAR(100) DEFAULT NULL,
            EstadoRegistro       VARCHAR(100) DEFAULT NULL,
            CodigoErrorRegistro  VARCHAR(100) DEFAULT NULL,
            DescripcionErrorRegistro TEXT,
            urlQR                VARCHAR(255) DEFAULT NULL,
            estado_queue         VARCHAR(20)  DEFAULT NULL,
            InvoiceNumber        VARCHAR(50)  DEFAULT NULL,
            IssueDate            DATE         DEFAULT NULL,
            TipoOperacion        VARCHAR(45)  DEFAULT NULL,
            EmpresaNombreRazon   VARCHAR(45)  DEFAULT NULL,
            EmpresaNIF           VARCHAR(20)  DEFAULT NULL,
            hash                 VARCHAR(255) DEFAULT NULL,
            cadena               TEXT,
            AnteriorHash         VARCHAR(255) DEFAULT NULL,
            TipoFactura          VARCHAR(45)  DEFAULT NULL,
            FacturaSimplificadaArt7273             VARCHAR(45) DEFAULT NULL,
            FacturaSinIdentifDestinatarioArt61d    VARCHAR(45) DEFAULT NULL,
            CalificacionOperacion                  VARCHAR(45) DEFAULT NULL,
            Macrodato            VARCHAR(45)  DEFAULT NULL,
            Cupon                VARCHAR(45)  DEFAULT NULL,
            TotalTaxOutputs      DECIMAL(15,2) DEFAULT NULL,
            InvoiceTotal         DECIMAL(15,2) DEFAULT NULL,
            BuyerName            VARCHAR(255) DEFAULT NULL,
            BuyerCorporateName   VARCHAR(255) DEFAULT NULL,
            BuyerTaxIdentificationNumber VARCHAR(45) DEFAULT NULL,
            BuyerCountryCode     VARCHAR(10)  DEFAULT NULL,
            IDOtroIDType         VARCHAR(45)  DEFAULT NULL,
            IDOtroID             VARCHAR(45)  DEFAULT NULL,
            TipoRectificativa    VARCHAR(10)  DEFAULT NULL,
            CorrectiveInvoiceNumber     VARCHAR(50)  DEFAULT NULL,
            CorrectiveInvoiceSeriesCode VARCHAR(10)  DEFAULT NULL,
            CorrectiveIssueDate  DATE         DEFAULT NULL,
            CorrectiveBaseAmount DECIMAL(15,2) DEFAULT NULL,
            CorrectiveTaxAmount  DECIMAL(15,2) DEFAULT NULL,
            FechaHoraHusoGenRegistro VARCHAR(45) DEFAULT NULL,
            fechaHoraRegistro    DATETIME     DEFAULT NULL,
            SIFNombreRazon       VARCHAR(255) DEFAULT NULL,
            SIFNIF               VARCHAR(45)  DEFAULT NULL,
            SIFNombreSIF         VARCHAR(45)  DEFAULT NULL,
            SIFIdSIF             VARCHAR(45)  DEFAULT NULL,
            SIFVersion           VARCHAR(45)  DEFAULT NULL,
            SIFNumeroInstalacion VARCHAR(45)  DEFAULT NULL,
            SIFTipoUsoPosibleSoloVerifactu  VARCHAR(45) DEFAULT NULL,
            SIFTipoUsoPosibleMultiOT        VARCHAR(45) DEFAULT NULL,
            SIFIndicadorMultiplesOT         VARCHAR(45) DEFAULT NULL,
            apiMode              VARCHAR(20)  DEFAULT NULL,
            date_add             DATETIME     DEFAULT NULL,
            PRIMARY KEY  (id_reg_fact)
        ) $charset_collate;";

        // ── 2. Order invoices status ──────────────────────────────────────────
        // One row per WooCommerce order invoice, tracks VeriFactu submission state.
        // Equivalent to ps_verifactu_order_invoice in the PrestaShop module.
        // `id_order_invoice` maps to WooCommerce's order ID (or invoice post ID if using PDF plugin).
        $sql_order_invoice = "CREATE TABLE {$wpdb->prefix}verifactu_order_invoice (
            id                              BIGINT(20)   NOT NULL AUTO_INCREMENT,
            id_order                        BIGINT(20)   NOT NULL,
            id_order_invoice                BIGINT(20)   DEFAULT NULL,
            estado                          VARCHAR(40)  DEFAULT NULL,
            id_reg_fact                     BIGINT(20)   NOT NULL DEFAULT 0,
            verifactuEstadoEnvio            VARCHAR(100) DEFAULT NULL,
            verifactuEstadoRegistro         VARCHAR(100) DEFAULT NULL,
            verifactuCodigoErrorRegistro    VARCHAR(100) DEFAULT NULL,
            verifactuDescripcionErrorRegistro TEXT,
            urlQR                           VARCHAR(255) DEFAULT NULL,
            anulacion                       TINYINT(1)   NOT NULL DEFAULT 0,
            TipoFactura                     VARCHAR(100) DEFAULT NULL,
            avisos                          TEXT         DEFAULT NULL,
            apiMode                         VARCHAR(20)  DEFAULT NULL,
            invoice_number                  VARCHAR(100) DEFAULT NULL,
            retry_count                     INT(11)      NOT NULL DEFAULT 0,
            last_retry_at                   DATETIME     DEFAULT NULL,
            date_add                        DATETIME     NOT NULL,
            PRIMARY KEY  (id),
            KEY id_order (id_order),
            KEY id_order_invoice (id_order_invoice)
        ) $charset_collate;";

        // ── 3. Refunds (credit notes) status ──────────────────────────────────
        // Equivalent to ps_verifactu_order_slip in PrestaShop module.
        // Maps to WooCommerce refund IDs.
        $sql_order_refund = "CREATE TABLE {$wpdb->prefix}verifactu_order_refund (
            id                              BIGINT(20)   NOT NULL AUTO_INCREMENT,
            id_order                        BIGINT(20)   NOT NULL,
            id_refund                       BIGINT(20)   NOT NULL,
            estado                          VARCHAR(40)  DEFAULT NULL,
            id_reg_fact                     BIGINT(20)   NOT NULL DEFAULT 0,
            verifactuEstadoEnvio            VARCHAR(100) DEFAULT NULL,
            verifactuEstadoRegistro         VARCHAR(100) DEFAULT NULL,
            verifactuCodigoErrorRegistro    VARCHAR(100) DEFAULT NULL,
            verifactuDescripcionErrorRegistro TEXT,
            urlQR                           VARCHAR(255) DEFAULT NULL,
            anulacion                       TINYINT(1)   NOT NULL DEFAULT 0,
            TipoFactura                     VARCHAR(100) DEFAULT NULL,
            avisos                          TEXT         DEFAULT NULL,
            apiMode                         VARCHAR(20)  DEFAULT NULL,
            invoice_number                  VARCHAR(100) DEFAULT NULL,
            retry_count                     INT(11)      NOT NULL DEFAULT 0,
            last_retry_at                   DATETIME     DEFAULT NULL,
            date_add                        DATETIME     NOT NULL,
            PRIMARY KEY  (id),
            KEY id_order (id_order),
            KEY id_refund (id_refund)
        ) $charset_collate;";

        // ── 4. Facturae electronic invoices ───────────────────────────────────
        // Tracks Facturae 3.2.2 generation and FACe submission status.
        $sql_facturae = "CREATE TABLE {$wpdb->prefix}verifactu_facturae (
            id                BIGINT(20)   NOT NULL AUTO_INCREMENT,
            id_order          BIGINT(20)   DEFAULT NULL,
            id_refund         BIGINT(20)   DEFAULT NULL,
            id_facturae_api   BIGINT(20)   DEFAULT NULL,
            invoice_number    VARCHAR(64)  NOT NULL,
            buyer_nif         VARCHAR(32)  DEFAULT NULL,
            buyer_name        VARCHAR(128) DEFAULT NULL,
            total_amount      DECIMAL(10,2) DEFAULT NULL,
            issue_date        DATE         DEFAULT NULL,
            face_sent         TINYINT(1)   NOT NULL DEFAULT 0,
            face_estado       VARCHAR(32)  DEFAULT 'pendiente',
            face_registro     VARCHAR(64)  DEFAULT NULL,
            face_mensaje      VARCHAR(255) DEFAULT NULL,
            date_add          DATETIME     NOT NULL,
            PRIMARY KEY  (id),
            KEY id_order (id_order),
            KEY id_refund (id_refund)
        ) $charset_collate;";

        dbDelta( $sql_reg_fact );
        dbDelta( $sql_order_invoice );
        dbDelta( $sql_order_refund );
        dbDelta( $sql_facturae );
    }

    // ── Option seeding ────────────────────────────────────────────────────────

    /**
     * Write default settings only if the option does not already exist.
     */
    private static function seed_options() {
        $existing = get_option( 'verifactu_infoal_settings', null );
        if ( $existing === null ) {
            add_option( 'verifactu_infoal_settings', self::$default_settings, '', 'no' );
        } else {
            // Merge new defaults into existing settings (non-destructive upgrade)
            $merged = array_merge( self::$default_settings, (array) $existing );
            update_option( 'verifactu_infoal_settings', $merged );
        }
    }

    // ── Cron ─────────────────────────────────────────────────────────────────

    /**
     * Register WP-Cron event for background sync (check pending statuses).
     * Equivalent to _checkPendingStatuses() + runCron() in ApiVerifactu.php.
     */
    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'verifactu_infoal_cron_sync' ) ) {
            $interval = Verifactu_Infoal::get_option( 'cron_interval', 'every_minute' );
            wp_schedule_event( time(), $interval, 'verifactu_infoal_cron_sync' );
        }
    }

    // ── Deactivation ──────────────────────────────────────────────────────────

    /**
     * Run on plugin deactivation (NOT uninstall).
     * Clears scheduled cron events. Does NOT remove data.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'verifactu_infoal_cron_sync' );
    }

    // ── Uninstall ─────────────────────────────────────────────────────────────

    /**
     * Drop tables and remove all plugin options.
     * Called explicitly from an uninstall.php file (registered via register_uninstall_hook).
     *
     * NOTE: Tables are NOT dropped on deactivation — only on full uninstall.
     */
    public static function uninstall() {
        global $wpdb;

        // Remove options
        delete_option( 'verifactu_infoal_settings' );
        delete_option( 'verifactu_infoal_version' );
        delete_option( 'verifactu_infoal_db_version' );

        // Drop tables
        $tables = array(
            $wpdb->prefix . 'verifactu_reg_fact',
            $wpdb->prefix . 'verifactu_order_invoice',
            $wpdb->prefix . 'verifactu_order_refund',
            $wpdb->prefix . 'verifactu_facturae',
        );

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }

        wp_clear_scheduled_hook( 'verifactu_infoal_cron_sync' );
    }

    // ── Upgrade ───────────────────────────────────────────────────────────────

    /**
     * Run incremental upgrades between versions.
     * Equivalent to upgrade/upgrade-{version}.php files in PrestaShop module.
     *
     * @param string $from_version Previously installed version.
     * @param string $to_version   Current plugin version.
     */
    public static function upgrade( $from_version, $to_version ) {
        // Always re-run dbDelta to add any new columns/tables
        self::create_tables();
        self::seed_options();

        // Version-specific migrations can be added here:
        // if ( version_compare( $from_version, '1.1.0', '<' ) ) {
        //     self::upgrade_to_110();
        // }

        Verifactu_Infoal::log(
            sprintf( 'DB upgrade: %s → %s', $from_version, $to_version ),
            1,
            'verifactu-installer'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Verify that all expected tables and columns exist in the current DB.
     * Returns an array of issues found (empty = healthy).
     *
     * Equivalent to checkAndFixDatabase() in verifactu.php.
     *
     * @return array List of issue descriptions.
     */
    public static function verify_database() {
        global $wpdb;
        $issues = array();

        $expected_tables = array(
            $wpdb->prefix . 'verifactu_reg_fact',
            $wpdb->prefix . 'verifactu_order_invoice',
            $wpdb->prefix . 'verifactu_order_refund',
            $wpdb->prefix . 'verifactu_facturae',
        );

        foreach ( $expected_tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $exists !== $table ) {
                $issues[] = sprintf( __( 'Tabla faltante: %s', 'verifactu-infoal' ), $table );
            }
        }

        return $issues;
    }
}
