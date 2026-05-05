<?php
/**
 * RegFacts List Table
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Verifactu_RegFacts_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Registro', 'verifactu-infoal' ),
            'plural'   => __( 'Registros', 'verifactu-infoal' ),
            'ajax'     => false,
        ) );
    }

    public function get_columns() {
        return array(
            'cb'               => '<input type="checkbox" />',
            'id_reg_fact'      => __( 'ID API', 'verifactu-infoal' ),
            'id_order'         => __( 'Pedido', 'verifactu-infoal' ),
            'tipo'             => __( 'Tipo', 'verifactu-infoal' ),
            'verifactuEstadoRegistro' => __( 'Estado AEAT', 'verifactu-infoal' ),
            'date_add'         => __( 'Fecha Alta', 'verifactu-infoal' ),
            'estado'           => __( 'Estado', 'verifactu-infoal' ),
            'apiMode'          => __( 'Entorno', 'verifactu-infoal' ),
        );
    }

    public function get_sortable_columns() {
        return array(
            'id_reg_fact'      => array( 'id_reg_fact', false ),
            'id_order'         => array( 'id_order', false ),
            'date_add'         => array( 'date_add', true ),
        );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id_reg_fact':
            case 'tipo':
            case 'verifactuEstadoRegistro':
            case 'apiMode':
                return esc_html( $item[ $column_name ] );
            case 'id_order':
                $order = wc_get_order( $item['id_order'] );
                if ( $order ) {
                    return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $item['id_order'] ) . '</a>';
                }
                return esc_html( $item['id_order'] );
            case 'date_add':
            case 'estado':
                return esc_html( $item[ $column_name ] );
            default:
                return print_r( $item, true );
        }
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="bulk-actions[]" value="%s" />',
            esc_attr( $item['id_reg_fact'] )
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

        $orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_GET['orderby'] ) ) : 'date_add';
        $order   = isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'asc' ? 'ASC' : 'DESC';
        $paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $offset  = ( $paged - 1 ) * $per_page;

        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE id_reg_fact > 0" );

        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT id_reg_fact, id_order, 'alta' as tipo, verifactuEstadoRegistro, date_add, estado, apiMode FROM $table_name WHERE id_reg_fact > 0 ORDER BY %i $order LIMIT %d OFFSET %d",
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
