<?php
/**
 * Plugin Name:       J-Invoices
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Faktúry pre WooCommerce: vydané faktúry z objednávok s ročným číselným radom, splatnosťou a variabilným symbolom, prehľad faktúr a tlačový náhľad. Súčasť rodiny doplnkov Jupiter.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-invoices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JINV_VERSION', '0.1.0' );
define( 'JINV_DB_VERSION', 1 );
define( 'JINV_FILE', __FILE__ );
define( 'JINV_DIR', plugin_dir_path( __FILE__ ) );
define( 'JINV_URL', plugin_dir_url( __FILE__ ) );

require_once JINV_DIR . 'includes/db.php';
require_once JINV_DIR . 'includes/settings.php';
require_once JINV_DIR . 'includes/create.php';
require_once JINV_DIR . 'includes/view.php';
require_once JINV_DIR . 'includes/admin.php';
require_once JINV_DIR . 'includes/order.php';

register_activation_hook( __FILE__, 'jinv_install' );
add_action( 'plugins_loaded', function () {
	if ( (int) get_option( 'jinv_db_version' ) < JINV_DB_VERSION ) {
		jinv_install();
	}
} );

// Zápis do J-Help (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	if ( isset( $plugins['j-invoices'] ) ) {
		$plugins['j-invoices']['status'] = 'dev';
	}
	return $plugins;
} );
