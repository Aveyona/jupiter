<?php
/**
 * Odinštalovanie J-Platieb: zmažú sa nastavenia so zašifrovanými kľúčmi Stripe a stav webhooku.
 * Údaje o platbách pri objednávkach ostávajú (sú súčasťou objednávok a účtovníctva).
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
foreach ( array( 'jpl_settings', 'jpl_webhook_last', 'jpl_webhook_ok_at', 'jpl_webhook_fail_at' ) as $jpl_opt ) {
	delete_option( $jpl_opt );
}
wp_clear_scheduled_hook( 'jpl_hourly' );
