<?php
/**
 * Plugin Name:       J-Doprava
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Doprava pre WooCommerce: Packeta – výdajné miesta a Z-BOXy (výber na mape v košíku), doručenie na adresu, podanie zásielok cez API, štítky PDF, sledovanie stavu a dobierky. Funguje v blokovom aj klasickom košíku. Súčasť rodiny Jupiter.
 * Version:           0.3.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-doprava
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JDP_VERSION', '0.3.0' );
define( 'JDP_FILE', __FILE__ );
define( 'JDP_DIR', plugin_dir_path( __FILE__ ) );
define( 'JDP_URL', plugin_dir_url( __FILE__ ) );
define( 'JDP_CAP', 'manage_woocommerce' );

require_once JDP_DIR . 'includes/settings.php';
require_once JDP_DIR . 'includes/api.php';
require_once JDP_DIR . 'includes/shipping.php';
require_once JDP_DIR . 'includes/checkout.php';
require_once JDP_DIR . 'includes/order.php';
require_once JDP_DIR . 'includes/email.php';
require_once JDP_DIR . 'includes/pickup.php';
require_once JDP_DIR . 'includes/post.php';
require_once JDP_DIR . 'includes/admin.php';
require_once JDP_DIR . 'includes/help.php';

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

// Poradie v menu Jupiter: za J-Objednávkami.
add_filter( 'jupiter_menu_order', function ( $o ) {
	$o['j-doprava'] = 25;
	return $o;
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins['j-doprava'] = array(
		'name'   => 'J-Doprava',
		'icon'   => 'dashicons-car',
		'what'   => 'Doprava a zásielky: Packeta – výdajné miesta a Z-BOXy s mapou v košíku, doručenie na adresu, podanie zásielok cez API (aj automaticky), štítky PDF, sledovanie stavu, dobierky a spätné zásielky. Slovenská pošta – export do ePodacieho hárku. Osobný odber – e-mail „pripravené na odber“.',
		'status' => 'dev',
	);
	return $plugins;
} );

register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'jdp_tracking' ) ) {
		wp_schedule_event( time() + 600, 'hourly', 'jdp_tracking' );
	}
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'jdp_tracking' );
} );
