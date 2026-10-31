<?php
/**
 * Plugin Name:       J-Kupóny
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Evidencia zľavových kódov pre WooCommerce: vytvorenie kupónu s automaticky vygenerovaným kódom alebo celej série, doprava zadarmo pri každom spôsobe dopravy, prehľad použitia, poskytnutej zľavy a stavu, rýchle vypnutie a zapnutie. Kódy sú bežné kupóny WooCommerce, takže fungujú v košíku aj pokladni. Súčasť rodiny Jupiter.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-kupony
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JKP_VERSION', '0.2.0' );
define( 'JKP_FILE', __FILE__ );
define( 'JKP_DIR', plugin_dir_path( __FILE__ ) );
define( 'JKP_CAP', 'manage_woocommerce' );

require_once JKP_DIR . 'includes/codes.php';
require_once JKP_DIR . 'includes/admin.php';
require_once JKP_DIR . 'includes/help.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

// Poradie v menu Jupiter: za J-Účtovníctvom, pred J-Notes.
add_filter( 'jupiter_menu_order', function ( $o ) {
	$o['j-kupony'] = 40;
	return $o;
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins['j-kupony'] = array(
		'name'   => 'J-Kupóny',
		'icon'   => 'dashicons-tickets-alt',
		'what'   => 'Zľavové kódy: nový kupón s automaticky vygenerovaným kódom alebo celá séria naraz (export a tlač kódov), zľava v % alebo €, doprava zadarmo pri každom spôsobe dopravy, platnosť, podmienky, detail s objednávkami a poskytnutou zľavou, štatistika, vypnutie a zapnutie jedným kliknutím.',
		'status' => 'dev',
	);
	return $plugins;
} );
