<?php
/**
 * Admin view: Facturae List.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once dirname( __DIR__ ) . '/tables/class-verifactu-facturae-table.php';

$table = new Verifactu_Facturae_Table();
$table->prepare_items();
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Facturae Electrónica', 'verifactu-infoal' ); ?></h1>
    
    <div class="notice notice-info" style="border-left-color: #0073aa; padding: 10px;">
        <p><strong><?php esc_html_e( 'Información Facturae (SPFE)', 'verifactu-infoal' ); ?></strong></p>
        <p><?php esc_html_e( 'Este módulo utiliza la pasarela SPFE (Sistemas de Proveedores de Facturación Electrónica) de InFoAL para la firma delegada en formato Facturae v3.2.2 y su envío directo a FACe.', 'verifactu-infoal' ); ?></p>
    </div>

    <p><?php esc_html_e( 'Listado de facturas en formato Facturae.', 'verifactu-infoal' ); ?></p>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
        <?php $table->display(); ?>
    </form>
</div>
