<?php
/**
 * Admin view: Registration Log.
 *
 * @package VeriFactu_InFoAL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
require_once dirname( __DIR__ ) . '/tables/class-verifactu-regfacts-table.php';

$table = new Verifactu_RegFacts_Table();
$table->prepare_items();
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Registros de Facturación', 'verifactu-infoal' ); ?></h1>
    <p><?php esc_html_e( 'Log histórico de los registros generados en el sistema de la AEAT.', 'verifactu-infoal' ); ?></p>
    
    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
        <?php $table->display(); ?>
    </form>
</div>
