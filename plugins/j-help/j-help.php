<?php
/**
 * Plugin Name:       J-Pomocník
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Dokumentácia doplnkov Jupiter pre WooCommerce priamo v administrácii – prehľad doplnkov, návody, podrobné príručky a plán vývoja – a aktualizácie všetkých doplnkov Jupiter z GitHubu. Doplnky Jupiter sú v menu hneď pod položkou Marketing.
 * Version:           1.3.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-help
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JHELP_VERSION', '1.3.0' );
define( 'JHELP_DIR', plugin_dir_path( __FILE__ ) );
define( 'JHELP_URL', plugin_dir_url( __FILE__ ) );

require_once JHELP_DIR . 'includes/catalog.php';
require_once JHELP_DIR . 'includes/menu.php';
require_once JHELP_DIR . 'includes/admin.php';
require_once JHELP_DIR . 'includes/docs.php';
require_once JHELP_DIR . 'includes/updater.php';
