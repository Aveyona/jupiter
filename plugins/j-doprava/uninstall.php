<?php
/**
 * Odinštalovanie J-Dopravy: zmažú sa nastavenia so zašifrovaným API kľúčom Packety.
 * Údaje o zásielkach pri objednávkach ostávajú.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'jdp_settings' );
