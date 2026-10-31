<?php
/**
 * Odinštalovanie J-Pošty: zmažú sa tajné údaje (heslá IMAP/SMTP v nastaveniach, prístup ku Google) a dočasné údaje.
 * Šablóny odpovedí ostávajú, aby sa po novej inštalácii nestratili.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
foreach ( array( 'jem_settings', 'jem_google', 'jem_g_cache', 'jem_smtp_failed', 'jem_tool_log', 'jem_snoozed' ) as $jem_opt ) {
	delete_option( $jem_opt );
}
