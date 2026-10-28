<?php
/**
 * Plugin Name:       J-Orders
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Objednávky WooCommerce prehľadnejšie: vlastný zoznam objednávok (stavy s počtami, vyhľadávanie aj podľa čísla, filtre platby a dátumu, platba, doprava, faktúra, hromadná zmena stavu) a vlastné čísla objednávok (prefix + poradové číslo, napr. 220260001) – náhrada doplnku My Order Numbers. Súčasť rodiny Jupiter.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-orders
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JORD_VERSION', '0.1.1' );
define( 'JORD_FILE', __FILE__ );
define( 'JORD_DIR', plugin_dir_path( __FILE__ ) );
define( 'JORD_URL', plugin_dir_url( __FILE__ ) );

require_once JORD_DIR . 'includes/numbers.php';
require_once JORD_DIR . 'includes/list.php';
require_once JORD_DIR . 'includes/admin.php';
require_once JORD_DIR . 'includes/help.php';

register_activation_hook( __FILE__, 'jord_numbers_activate' );

// Kompatibilita s tabuľkami objednávok WooCommerce (HPOS).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins['j-orders'] = array(
		'name'   => 'J-Orders',
		'icon'   => 'dashicons-clipboard',
		'what'   => 'Objednávky: prehľadný zoznam objednávok nad WooCommerce (stavy s počtami, vyhľadávanie aj podľa čísla objednávky, filtre platby a dátumu, stĺpce platba, doprava a faktúra, hromadná zmena stavu) a vlastné čísla objednávok s prefixom (náhrada doplnku My Order Numbers).',
		'status' => 'dev',
	);
	return $plugins;
} );
