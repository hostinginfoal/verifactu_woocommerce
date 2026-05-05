<?php
/**
 * Invoices List Table
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Verifactu_Invoices_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Factura', 'verifactu-infoal' ),
            'plural'   => __( 'Facturas', 'verifactu-infoal' ),
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'cb'               => '<input type="checkbox" />',
            'id_order'         => __( 'Pedido', 'verifactu-infoal' ),
            'numero_factura'   => __( 'Nº Factura', 'verifactu-infoal' ),
            'estado'           => __( 'Estado', 'verifactu-infoal' ),
            'verifactuEstadoRegistro' => __( 'Estado VeriFactu', 'verifactu-infoal' ),
            'fecha_envio'      => __( 'Fecha Envío', 'verifactu-infoal' ),
            'retry_count'      => __( 'Reintentos', 'verifactu-infoal' ),
        );
    }

    public function get_sortable_columns() {
        return array(
            'id_order'         => array( 'id_order', false ),
            'numero_factura'   => array( 'numero_factura', false ),
            'estado'           => array( 'estado', false ),
            'fecha_envio'      => array( 'fecha_envio', true ),
        );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id_order':
                $order = wc_get_order( $item['id_order'] );
                if ( $order ) {
                    return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $item['id_order'] ) . '</a>';
                }
                return esc_html( $item['id_order'] );
            case 'numero_factura':
            case 'verifactuEstadoRegistro':
            case 'retry_count':
                return esc_html( $item[ $column_name ] );
            case 'fecha_envio':
                return esc_html( $item['fecha_envio'] );
            case 'estado':
                $status = $item['estado'];
                $class = 'verifactu-badge--pending';
                if ( $status === 'sincronizado' ) $class = 'verifactu-badge--success';
                elseif ( $status === 'api_error' || $status === 'failed' ) $class = 'verifactu-badge--error';
                return sprintf( '<span class="verifactu-badge %s">%s</span>', esc_attr( $class ), esc_html( $status ) );
            default:
                return print_r( $item, true );
        }
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="bulk-actions[]" value="%s" />',
            esc_attr( $item['id_order_invoice'] )
        );
    }

    public function prepare_items() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'verifactu_order_invoice';
        $per_page   = 20;
        $columns    = $this->get_columns();
        $hidden     = array();
        $sortable   = $this->get_sortable_columns();

        $this->_column_headers = array( $columns, $hidden, $sortable );

        $orderby = ( ! empty( $_GET['orderby'] ) ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'date_add';
        $order   = isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ? 'ASC' : 'DESC';
        $paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $offset  = ( $paged - 1 ) * $per_page;

        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY %i $order LIMIT %d OFFSET %d",
            $orderby,
            $per_page,
            $offset
        ), ARRAY_A );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }
}
