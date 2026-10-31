<?php
/**
 * Plugin Name:       J-Platby
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Platby kartou pre WooCommerce cez Stripe Checkout: Visa, Mastercard, Apple Pay a Google Pay na zabezpečenej platobnej stránke, potvrdenie platby webhookom, vrátenie peňazí z objednávky, poplatky a prepojenie s J-Fakturáciou a J-Účtovníctvom. Funguje v blokovom aj klasickom košíku. Súčasť rodiny Jupiter.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-platby
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JPL_VERSION', '0.2.0' );
define( 'JPL_FILE', __FILE__ );
define( 'JPL_DIR', plugin_dir_path( __FILE__ ) );
define( 'JPL_URL', plugin_dir_url( __FILE__ ) );
define( 'JPL_CAP', 'manage_woocommerce' );

require_once JPL_DIR . 'includes/settings.php';
require_once JPL_DIR . 'includes/stripe.php';
require_once JPL_DIR . 'includes/gateway.php';
require_once JPL_DIR . 'includes/webhook.php';
require_once JPL_DIR . 'includes/links.php';
require_once JPL_DIR . 'includes/admin.php';
require_once JPL_DIR . 'includes/tools.php';
require_once JPL_DIR . 'includes/help.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

// Poradie v menu Jupiter: za J-Dopravou.
add_filter( 'jupiter_menu_order', function ( $o ) {
	$o['j-platby'] = 27;
	return $o;
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins['j-platby'] = array(
		'name'   => 'J-Platby',
		'icon'   => 'dashicons-money-alt',
		'what'   => 'Platby kartou cez Stripe: Visa, Mastercard, Apple Pay a Google Pay na zabezpečenej platobnej stránke, automatické potvrdenie zaplatenej objednávky, vrátenie peňazí priamo z objednávky, prehľad platieb s poplatkami a exportom do CSV, odkaz na platbu kartou pre zákazníka, stav webhooku a zápis úhrady do J-Fakturácie a J-Účtovníctva.',
		'status' => 'dev',
	);
	return $plugins;
} );
