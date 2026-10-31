<?php
/**
 * Plugin Name:       J-Notes
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Poznámky k projektom: plány s úlohami a stavom, dokumentácia v Markdowne a prehľad „teraz robím“. Plány sa dajú nahrať aj ako súbory .md. Súčasť rodiny Jupiter.
 * Version:           0.2.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-notes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JNO_VERSION', '0.2.1' );
define( 'JNO_DB_VERSION', 2 );
define( 'JNO_FILE', __FILE__ );
define( 'JNO_DIR', plugin_dir_path( __FILE__ ) );
define( 'JNO_URL', plugin_dir_url( __FILE__ ) );
define( 'JNO_CAP', 'manage_options' );
define( 'JNO_SLUG', 'j-notes' );
define( 'JNO_NAME', 'J-Notes' );
define( 'JNO_FAMILY', 'Jupiter' );
define( 'JNO_MENU_POS', 58 );

require_once JNO_DIR . 'includes/db.php';
require_once JNO_DIR . 'includes/markdown.php';
require_once JNO_DIR . 'includes/sync.php';
require_once JNO_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, 'jno_install' );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'jno_inbox' );
} );
add_action( 'plugins_loaded', function () {
	if ( (int) get_option( 'jno_db_version' ) < JNO_DB_VERSION ) {
		jno_install();
	}
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins['j-notes'] = array(
		'name'   => 'J-Notes',
		'icon'   => 'dashicons-welcome-write-blog',
		'what'   => 'Poznámky k projektom: plány s úlohami a stavom, dokumentácia v Markdowne, prehľad „teraz robím“ a nahrávanie plánov ako súborov .md.',
		'status' => 'dev',
	);
	return $plugins;
} );
