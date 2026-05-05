<?php
/**
 * WooCommerce Order Hooks — listens to WC events and triggers VeriFactu submissions.
 *
 * Equivalent to the PrestaShop hooks:
 *   actionSetInvoice    → woocommerce_order_status_completed + PDF invoice generation hook
 *   actionOrderSlipAdd  → woocommerce_order_fully_refunded
 *   actionShutdown      → shutdown (WP equivalent)
 *
 * Also manages:
 *   - Order locking (prevent editing once VeriFactu status is 'Correcto')
 *   - Order column in WC orders list (VeriFactu status badge)
 *   - Inline QR on order emails (optional)
 *
 * @package VeriFactu_InFoAL
 * @todo FASE5 — Implement all hooks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Verifactu_Order_Hooks {

    // ── Singleton ─────────────────────────────────────────────────────────────

    /** @var Verifactu_Order_Hooks|null */
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

    // ── Hook registration ─────────────────────────────────────────────────────

    private function init_hooks() {
        // ── Invoice trigger ────────────────────────────────────────────────────
        // WooCommerce does not have native invoices. We hook into:
        //   1. Order status change (completed / processing) as a fallback trigger
        //   2. PDF invoice plugin hook for exact parity with PS actionSetInvoice
        //
        // TODO-FASE5: choose the right trigger per shop configuration

        add_action( 'woocommerce_order_status_completed',  array( $this, 'on_order_completed'  ), 10, 2 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_processing' ), 10, 2 );

        // WooCommerce PDF Invoices & Packing Slips (Ewout Fernhout) hook:
        add_action( 'wpo_wcpdf_invoice_created', array( $this, 'on_invoice_created' ), 10, 2 );

        // ── Refund / credit note trigger ───────────────────────────────────────
        // Equivalent to actionOrderSlipAdd
        add_action( 'woocommerce_refund_created',          array( $this, 'on_refund_created'   ), 10, 2 );

        // ── Orders list column ─────────────────────────────────────────────────
        add_filter( 'manage_woocommerce_page_wc-orders_columns',  array( $this, 'add_orders_column'    ) );
        add_filter( 'manage_shop_order_posts_columns',             array( $this, 'add_orders_column'    ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_orders_column' ), 10, 2 );
        add_action( 'manage_shop_order_posts_custom_column',           array( $this, 'render_orders_column' ), 10, 2 );

        // ── Shutdown / deferred processing ────────────────────────────────────
        // add_action( 'shutdown', array( $this, 'on_shutdown' ) );

        // ── Cron sync ─────────────────────────────────────────────────────────
        add_action( 'verifactu_infoal_cron_sync', array( $this, 'run_cron_sync' ) );

        // ── Order Locking ──────────────────────────────────────────────────────
        add_filter( 'wc_order_is_editable', array( $this, 'is_order_editable' ), 10, 2 );
        add_filter( 'woocommerce_order_is_deletable', array( $this, 'is_order_deletable' ), 10, 2 );

        // ── QR Code ────────────────────────────────────────────────────────────
        add_action( 'wpo_wcpdf_after_order_details', array( $this, 'add_qr_to_pdf' ), 10, 2 );
    }

    // ── Invoice callbacks ─────────────────────────────────────────────────────

    /**
     * Triggered when an order reaches 'completed' status.
     * Sends a VeriFactu ALTA for the order if not already sent.
     *
     * @param int      $order_id WooCommerce order ID.
     * @param WC_Order $order    Order object.
     *
     * @todo FASE5 — Implement
     */
    public function on_order_completed( $order_id, $order ) {
        if ( $this->already_sent( $order_id ) ) {
            return;
        }
        $service = new Verifactu_Service_Verifactu();
        $result  = $service->send_alta( $order_id );
        Verifactu_Infoal::log( "Alta enviada para pedido #{$order_id}: " . json_encode( $result ), 1 );
    }

    public function on_order_processing( $order_id, $order ) {
        // Many stores generate invoices on processing. 
        // We'll follow the same logic if not already sent.
        if ( $this->already_sent( $order_id ) ) {
            return;
        }
        $service = new Verifactu_Service_Verifactu();
        $result  = $service->send_alta( $order_id );
        Verifactu_Infoal::log( "Alta enviada (processing) para pedido #{$order_id}: " . json_encode( $result ), 1 );
    }

    public function on_invoice_created( $document, $data ) {
        // Prevent double sending if already sent via order status
        $order_id = $document->order->get_id();
        if ( $this->already_sent( $order_id ) ) {
            return;
        }
        $service = new Verifactu_Service_Verifactu();
        $result  = $service->send_alta( $order_id );
        Verifactu_Infoal::log( "Alta enviada (invoice created) para pedido #{$order_id}: " . json_encode( $result ), 1 );
    }

    // ── Refund callbacks ──────────────────────────────────────────────────────

    /**
     * Triggered when a refund is created.
     * Sends a VeriFactu ALTA as 'abono' (credit note) type.
     *
     * @param int $order_id  WooCommerce order ID.
     * @param int $refund_id WooCommerce refund ID.
     *
     * @todo FASE5 — Implement
     */
    public function on_refund_created( $refund_id, $args ) {
        $order_id = isset( $args['order_id'] ) ? $args['order_id'] : 0;
        if ( ! $order_id ) {
            // Get order ID from refund object if not in args
            $refund = wc_get_order( $refund_id );
            if ( $refund ) {
                $order_id = $refund->get_parent_id();
            }
        }

        if ( ! $order_id || $this->already_sent( $order_id, 'refund' ) ) {
            return;
        }

        $service = new Verifactu_Service_Verifactu();
        $result  = $service->send_abono( $order_id, $refund_id );
        Verifactu_Infoal::log( "Abono enviado para pedido #{$order_id}, abono #{$refund_id}: " . json_encode( $result ), 1 );
    }

    // ── Orders list column ────────────────────────────────────────────────────

    public function add_orders_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( $key === 'order_status' ) {
                $new_columns['verifactu_status'] = __( 'VeriFactu', 'verifactu-infoal' );
            }
        }
        return $new_columns;
    }

    public function render_orders_column( $column_name, $order_or_id ) {
        if ( $column_name !== 'verifactu_status' ) {
            return;
        }

        $order_id = is_object( $order_or_id ) ? $order_or_id->get_id() : absint( $order_or_id );
        
        $service = new Verifactu_Service_Verifactu();
        $status = $service->get_status_for_order( $order_id, 'invoice' );

        if ( empty( $status ) ) {
            echo '<span class="verifactu-badge verifactu-badge--pending" style="background:#e5e5e5; padding:2px 5px; border-radius:3px; font-size:11px;">' . esc_html__( 'Pendiente', 'verifactu-infoal' ) . '</span>';
            return;
        }

        $estado_registro = isset( $status['verifactuEstadoRegistro'] ) ? $status['verifactuEstadoRegistro'] : '';
        $estado_envio    = isset( $status['verifactuEstadoEnvio'] ) ? $status['verifactuEstadoEnvio'] : '';
        
        $text = $estado_registro ?: ( $estado_envio ?: 'Pendiente' );
        $color = '#555';
        $bg = '#e5e5e5';

        if ( $estado_registro === 'Correcto' ) {
            $color = '#5b841b'; $bg = '#c6e1c6';
        } elseif ( $estado_envio === 'Error' || $estado_registro === 'Incorrecto' ) {
            $color = '#a00'; $bg = '#f8cbcb';
        }

        echo '<span class="verifactu-badge" style="background:' . esc_attr( $bg ) . '; color:' . esc_attr( $color ) . '; padding:2px 5px; border-radius:3px; font-size:11px; font-weight:600;">' . esc_html( $text ) . '</span>';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Check if a VeriFactu submission has already been made for this order.
     *
     * @param  int    $order_id
     * @param  string $type     'invoice' | 'refund'
     * @return bool
     *
     * @todo FASE5 — Implement
     */
    private function already_sent( $order_id, $type = 'invoice' ) {
        global $wpdb;
        $table = $type === 'invoice' ? 'verifactu_order_invoice' : 'verifactu_order_refund';
        $id_col = $type === 'invoice' ? 'id_order_invoice' : 'id_order_refund';
        
        $table_name = $wpdb->prefix . $table;
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE $id_col = %d AND estado != 'api_error'",
            $order_id
        ) );

        return $count > 0;
    }

    public function run_cron_sync() {
        $service = new Verifactu_Service_Verifactu();
        $service->run_cron_sync();
    }

    /**
     * Shutdown hook — deferred async actions.
     * Equivalent to actionShutdown in PrestaShop module.
     *
     * @todo FASE6 — Implement (e.g. fire WP Background Processing job)
     */
    public function on_shutdown() {
        // TODO-FASE6
    }

    public function is_order_editable( $is_editable, $order ) {
        if ( ! Verifactu_Infoal::get_option( 'lock_order_if_correct', 0 ) ) {
            return $is_editable;
        }

        $service = new Verifactu_Service_Verifactu();
        $status = $service->get_status_for_order( $order->get_id(), 'invoice' );
        
        if ( $status && isset( $status['verifactuEstadoRegistro'] ) && $status['verifactuEstadoRegistro'] === 'Correcto' ) {
            return false;
        }
        
        return $is_editable;
    }

    public function is_order_deletable( $is_deletable, $order ) {
        if ( ! Verifactu_Infoal::get_option( 'lock_order_if_correct', 0 ) ) {
            return $is_deletable;
        }

        $service = new Verifactu_Service_Verifactu();
        $status = $service->get_status_for_order( $order->get_id(), 'invoice' );
        
        if ( $status && isset( $status['verifactuEstadoRegistro'] ) && $status['verifactuEstadoRegistro'] === 'Correcto' ) {
            return false; // Prevent moving to trash
        }
        
        return $is_deletable;
    }

    public function add_qr_to_pdf( $document_type, $order ) {
        if ( Verifactu_Infoal::get_option( 'qr_hide_default', 0 ) ) {
            return;
        }

        if ( Verifactu_Infoal::get_option( 'invoice_engine', 'wp_overnight' ) !== 'wp_overnight' ) {
            return;
        }

        $service = new Verifactu_Service_Verifactu();
        // Since refunds might be on the same order, check the document type.
        $type = $document_type === 'credit-note' ? 'refund' : 'invoice';
        
        $status = $service->get_status_for_order( $order->get_id(), $type );

        if ( $status && ! empty( $status['urlQR'] ) ) {
            $qr_service = new Verifactu_Service_Qr();
            echo $qr_service->get_qr_img_html( $status['urlQR'] );
        }
    }
}
