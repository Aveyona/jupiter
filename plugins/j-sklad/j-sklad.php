<?php
/**
 * Plugin Name:       J-Sklad
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Sklad pre WooCommerce: príjemky a výdajky, kniha všetkých pohybov zásob (predaj, vrátenie, ručné zmeny), priemerná nákupná cena a hodnota skladu. Zásoba ostáva v produktoch WooCommerce. Súčasť rodiny Jupiter.
 * Version:           0.2.2
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-sklad
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JSK_VERSION', '0.2.2' );
define( 'JSK_DB_VERSION', 2 );
define( 'JSK_FILE', __FILE__ );
define( 'JSK_DIR', plugin_dir_path( __FILE__ ) );
define( 'JSK_URL', plugin_dir_url( __FILE__ ) );

require_once JSK_DIR . 'includes/db.php';
require_once JSK_DIR . 'includes/settings.php';
require_once JSK_DIR . 'includes/stock.php';
require_once JSK_DIR . 'includes/docs.php';
require_once JSK_DIR . 'includes/admin.php';
require_once JSK_DIR . 'includes/product.php';
require_once JSK_DIR . 'includes/media.php';
require_once JSK_DIR . 'includes/help.php';

register_activation_hook( __FILE__, 'jsk_activate' );
add_action( 'plugins_loaded', function () {
	if ( (int) get_option( 'jsk_db_version' ) < JSK_DB_VERSION ) {
		jsk_install();
	}
} );

// Kompatibilita s tabuľkami objednávok WooCommerce (HPOS).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins['j-sklad'] = array(
		'name'   => 'J-Sklad',
		'icon'   => 'dashicons-archive',
		'what'   => 'Sklad nad produktmi WooCommerce: príjemky a výdajky, kniha všetkých pohybov zásob (predaj, vrátenie, ručné zmeny), priemerná nákupná cena a hodnota skladu, voliteľne aj evidencia obrázkov produktov. Pripravuje sa inventúra, PDF dokladov, dodávatelia a prepojenie s J-Fakturáciou.',
		'status' => 'dev',
	);
	return $plugins;
} );
