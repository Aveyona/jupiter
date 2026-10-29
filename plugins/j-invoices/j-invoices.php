<?php
/**
 * Plugin Name:       J-Fakturácia
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Doklady pre WooCommerce: faktúry z objednávok s číselným radom, splatnosťou a variabilným symbolom, prehľad a tlačový náhľad; pripravujú sa zálohové faktúry, dobropisy, PDF s QR PAY by square, e-mail, Pohoda a prechod z Woo Faktura. Súčasť rodiny Jupiter.
 * Version:           0.10.0
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

define( 'JINV_VERSION', '0.10.0' );
define( 'JINV_DB_VERSION', 4 );
define( 'JINV_FILE', __FILE__ );
define( 'JINV_DIR', plugin_dir_path( __FILE__ ) );
define( 'JINV_URL', plugin_dir_url( __FILE__ ) );

// Kompatibilita s HPOS (tabuľky objednávok) a s blokovou pokladňou WooCommerce.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', JINV_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', JINV_FILE, true );
	}
} );

require_once JINV_DIR . 'includes/db.php';
require_once JINV_DIR . 'includes/settings.php';
require_once JINV_DIR . 'includes/create.php';
require_once JINV_DIR . 'includes/documents.php';
require_once JINV_DIR . 'includes/paybysquare.php';
require_once JINV_DIR . 'includes/pdf.php';
require_once JINV_DIR . 'includes/email.php';
require_once JINV_DIR . 'includes/account.php';
require_once JINV_DIR . 'includes/export.php';
require_once JINV_DIR . 'includes/view.php';
require_once JINV_DIR . 'includes/admin.php';
require_once JINV_DIR . 'includes/order.php';
require_once JINV_DIR . 'includes/checkout.php';
require_once JINV_DIR . 'includes/checkout-blocks.php';
require_once JINV_DIR . 'includes/import.php';
require_once JINV_DIR . 'includes/help.php';
require_once JINV_DIR . 'includes/received.php';
require_once JINV_DIR . 'includes/payments.php';
require_once JINV_DIR . 'includes/partners.php';
require_once JINV_DIR . 'includes/bank.php';

register_activation_hook( __FILE__, 'jinv_install' );
add_action( 'plugins_loaded', function () {
	if ( (int) get_option( 'jinv_db_version' ) < JINV_DB_VERSION ) {
		$was = (int) get_option( 'jinv_db_version' );
		jinv_install();
		if ( $was && $was < 3 ) {
			jinv_partners_from_received(); // dodávatelia z doterajších prijatých faktúr
		}
	}
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	if ( isset( $plugins['j-invoices'] ) ) {
		$plugins['j-invoices']['status'] = 'dev';
	}
	return $plugins;
} );
