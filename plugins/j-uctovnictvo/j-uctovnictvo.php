<?php
/**
 * Plugin Name:       J-Účtovníctvo
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Podvojné účtovníctvo pre slovenské s. r. o.: účtový rozvrh podľa rámcovej účtovej osnovy, účtovné zápisy s predkontáciami, denník, hlavná kniha, obratová predvaha, saldokonto a automatické účtovanie dokladov J-Fakturácie, banky a J-Skladu. Súčasť rodiny Jupiter.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-uctovnictvo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JUC_VERSION', '0.1.0' );
define( 'JUC_DB_VERSION', 1 );
define( 'JUC_FILE', __FILE__ );
define( 'JUC_DIR', plugin_dir_path( __FILE__ ) );
define( 'JUC_URL', plugin_dir_url( __FILE__ ) );
define( 'JUC_CAP', 'manage_woocommerce' );

require_once JUC_DIR . 'includes/db.php';
require_once JUC_DIR . 'includes/settings.php';
require_once JUC_DIR . 'includes/accounts.php';
require_once JUC_DIR . 'includes/ledger.php';
require_once JUC_DIR . 'includes/autopost.php';
require_once JUC_DIR . 'includes/reports.php';
require_once JUC_DIR . 'includes/admin.php';
require_once JUC_DIR . 'includes/wizard.php';
require_once JUC_DIR . 'includes/help.php';

register_activation_hook( __FILE__, 'juc_activate' );
add_action( 'plugins_loaded', function () {
	if ( (int) get_option( 'juc_db_version' ) < JUC_DB_VERSION ) {
		juc_activate();
	}
} );

/** Aktivácia: tabuľky, účtový rozvrh (ak je prázdny) a účtovné obdobie aktuálneho roka. */
function juc_activate() {
	juc_install();
	juc_seed_accounts();
	juc_ensure_year( (int) current_time( 'Y' ) );
}

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins['j-uctovnictvo'] = array(
		'name'   => 'J-Účtovníctvo',
		'icon'   => 'dashicons-book-alt',
		'what'   => 'Podvojné účtovníctvo pre s. r. o.: účtový rozvrh, účtovné zápisy s predkontáciami, denník, hlavná kniha, obratová predvaha, saldokonto a automatické účtovanie faktúr, banky a skladu.',
		'status' => 'dev',
	);
	return $plugins;
} );
