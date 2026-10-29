<?php
/**
 * Plugin Name:       J-Emails
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Jupiter Email – e-mailová schránka obchodu v administrácii WordPressu v štýle Gmailu: konverzácie, hviezdičky a Dôležité, archív, odloženie, rýchle a hromadné akcie, okno Napísať, aliasy odosielateľa, prílohy, šablóny e-mailov (aj pre nástroje iných doplnkov). Pripája sa cez IMAP a SMTP (napr. WebSupport). Súčasť rodiny Jupiter.
 * Version:           0.4.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JEM_VERSION', '0.4.0' );
define( 'JEM_FILE', __FILE__ );
define( 'JEM_DIR', plugin_dir_path( __FILE__ ) );
define( 'JEM_URL', plugin_dir_url( __FILE__ ) );

require_once JEM_DIR . 'includes/imap.php';
require_once JEM_DIR . 'includes/mime.php';
require_once JEM_DIR . 'includes/settings.php';
require_once JEM_DIR . 'includes/mailbox.php';
require_once JEM_DIR . 'includes/send.php';
require_once JEM_DIR . 'includes/templates.php';
require_once JEM_DIR . 'includes/admin.php';
require_once JEM_DIR . 'includes/api.php';
require_once JEM_DIR . 'includes/help.php';

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'jem_check_unseen' );
	delete_transient( 'jem_unseen' );
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	if ( isset( $plugins['j-emails'] ) ) {
		$plugins['j-emails']['status'] = 'dev';
	}
	return $plugins;
} );
