<?php
/**
 * Plugin Name:       Jupiter
 * Plugin URI:        https://github.com/Aveyona/jupiter
 * Description:       Hlavný doplnok rodiny Jupiter: jedna položka Jupiter v menu administrácie s nástenkou všetkých doplnkov J- (farebné dlaždice s popisom, verziou, počítadlami a odkazmi na podstránky, pripnutie), zlúčenie doplnkov do jednej položky menu a navigácia na stránkach doplnkov. Súčasť rodiny Jupiter.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Aveyona, s. r. o.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       j-jupiter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JJU_VERSION', '0.2.0' );
define( 'JJU_FILE', __FILE__ );
define( 'JJU_DIR', plugin_dir_path( __FILE__ ) );
define( 'JJU_URL', plugin_dir_url( __FILE__ ) );
define( 'JJU_CAP', 'manage_woocommerce' );
define( 'JJU_SLUG', 'j-jupiter' );

require_once JJU_DIR . 'includes/apps.php';
require_once JJU_DIR . 'includes/menu.php';
require_once JJU_DIR . 'includes/dashboard.php';
require_once JJU_DIR . 'includes/settings.php';

// Poradie v menu Jupiter: úplne prvý.
add_filter( 'jupiter_menu_order', function ( $o ) {
	$o[ JJU_SLUG ] = 1;
	return $o;
} );

// Zápis do J-Pomocník (prehľad doplnkov Jupiter).
add_filter( 'jupiter_plugins', function ( $plugins ) {
	$plugins[ JJU_SLUG ] = array(
		'name'   => 'Jupiter',
		'icon'   => 'dashicons-admin-site-alt3',
		'what'   => 'Hlavný doplnok rodiny: jedna položka Jupiter v menu, nástenka všetkých doplnkov J- s popisom, verziou, počítadlami a odkazmi, pripnutie obľúbených a zlúčenie doplnkov do jednej položky menu.',
		'status' => 'dev',
	);
	return $plugins;
} );

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Jupiter – nástenka a menu doplnkov',
		array(
			'V ľavom menu je položka Jupiter. Po prejdení myšou sa ukážu všetky doplnky J-, kliknutím sa otvorí nástenka Jupiter s dlaždicami doplnkov.',
			'Dlaždica: farebná ikona, názov, popis, verzia, počítadlo (napr. úlohy v J-Účtovníctve) a odkazy na podstránky. Špendlík pripne doplnok hore (každý používateľ má vlastné pripnuté).',
			'Na stránke doplnku je hore navigácia: Jupiter › doplnok a jeho podstránky.',
			'Hore na nástenke je súhrn Čaká na vás (počítadlá doplnkov a ich stránok) a pole na hľadanie doplnku alebo stránky – Enter otvorí prvú nájdenú. Na dlaždici je ikona knihy (príručka) a pri novej verzii tlačidlo Aktualizovať.',
			'Jupiter → Nastavenia: Zlúčiť doplnky do položky Jupiter (zapnuté = v menu je len Jupiter; vypnuté = každý doplnok má vlastnú položku ako doteraz) a farba každého doplnku.',
		),
		'Jupiter',
	);
	return $guides;
} );
