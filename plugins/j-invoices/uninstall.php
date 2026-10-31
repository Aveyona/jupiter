<?php
/**
 * Odinštalovanie J-Fakturácie: zmažú sa len tajné údaje (token Fio API) a dočasné údaje.
 * Doklady, úhrady a pohyby v banke ostávajú – účtovné doklady sa musia uchovávať 10 rokov.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$jinv_accs = get_option( 'jinv_bank_accounts', array() );
if ( is_array( $jinv_accs ) ) {
	foreach ( $jinv_accs as $k => $a ) {
		unset( $jinv_accs[ $k ]['token'], $jinv_accs[ $k ]['token_tail'] );
	}
	update_option( 'jinv_bank_accounts', $jinv_accs, false );
}
delete_transient( 'jinv_bank_unmatched' );
wp_clear_scheduled_hook( 'jinv_bank_cron' );
