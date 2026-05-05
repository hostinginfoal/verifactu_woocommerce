<?php
/**
 * Admin view: Invoices List.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once dirname( __DIR__ ) . '/tables/class-verifactu-invoices-table.php';

$table = new Verifactu_Invoices_Table();
$table->prepare_items();
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Facturas de Venta', 'verifactu-infoal' ); ?></h1>
    <p><?php esc_html_e( 'Listado de facturas enviadas a VeriFactu.', 'verifactu-infoal' ); ?></p>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
        <?php $table->display(); ?>
    </form>
</div>
