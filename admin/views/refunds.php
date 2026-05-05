<?php
/**
 * Admin view: Refunds List.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once dirname( __DIR__ ) . '/tables/class-verifactu-refunds-table.php';

$table = new Verifactu_Refunds_Table();
$table->prepare_items();
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Facturas por Abono', 'verifactu-infoal' ); ?></h1>
    <p><?php esc_html_e( 'Listado de abonos (notas de crédito) enviadas a VeriFactu.', 'verifactu-infoal' ); ?></p>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
        <?php $table->display(); ?>
    </form>
</div>
