<?php
/** Nastavenia J-Invoices: údaje dodávateľa, číselný rad, splatnosť, kedy vznikne faktúra. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jinv_defaults() {
	return array(
		'company'      => get_bloginfo( 'name' ),
		'street'       => (string) get_option( 'woocommerce_store_address' ),
		'city'         => (string) get_option( 'woocommerce_store_city' ),
		'zip'          => (string) get_option( 'woocommerce_store_postcode' ),
		'country'      => 'Slovensko',
		'ico'          => '',
		'dic'          => '',
		'icdph'        => '',
		'register'     => '',            // napr. Zapísaná v OR Okresného súdu Žilina, oddiel Sro, vložka č. …
		'vat_payer'    => 0,
		'vat_note'     => 'Nie sme platiteľom DPH.',
		'iban'         => '',
		'swift'        => '',
		'bank'         => '',
		'email'        => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
		'phone'        => '',
		'web'          => home_url( '/' ),
		'format'       => '{RRRR}{nnn}',  // číselný rad: {RRRR} rok, {RR} rok dvojmiestne, {nnn}/{nnnn} poradie
		'due_days'     => 14,
		'auto'         => 1,             // vytvárať faktúry z objednávok automaticky
		'issued_by'    => '',
		'footer'       => 'Ďakujeme za nákup.',
	);
}

function jinv_settings() {
	return wp_parse_args( (array) get_option( 'jinv_settings', array() ), jinv_defaults() );
}

function jinv_opt( $k ) {
	$s = jinv_settings();
	return isset( $s[ $k ] ) ? $s[ $k ] : null;
}

/** Polia nastavení: kľúč => [popis, typ, nápoveda]. */
function jinv_setting_fields() {
	return array(
		'Dodávateľ (údaje na faktúre)' => array(
			'company'  => array( 'Obchodné meno', 'text', '' ),
			'street'   => array( 'Ulica a číslo', 'text', '' ),
			'zip'      => array( 'PSČ', 'text', '' ),
			'city'     => array( 'Mesto', 'text', '' ),
			'country'  => array( 'Štát', 'text', '' ),
			'ico'      => array( 'IČO', 'text', '' ),
			'dic'      => array( 'DIČ', 'text', '' ),
			'icdph'    => array( 'IČ DPH', 'text', 'Len ak ste platiteľ DPH.' ),
			'register' => array( 'Zápis v registri', 'text', 'Napr. „Zapísaná v Obchodnom registri Okresného súdu Žilina, oddiel Sro, vložka č. …“' ),
			'vat_payer' => array( 'Platiteľ DPH', 'checkbox', 'Zatiaľ sa podporujú faktúry neplatiteľa DPH.' ),
			'vat_note' => array( 'Text pre neplatiteľa DPH', 'text', '' ),
		),
		'Platba' => array(
			'iban'     => array( 'IBAN', 'text', 'Číslo účtu pre úhradu faktúr prevodom.' ),
			'swift'    => array( 'SWIFT / BIC', 'text', '' ),
			'bank'     => array( 'Banka', 'text', '' ),
			'due_days' => array( 'Splatnosť (dni)', 'number', '' ),
		),
		'Kontakt' => array(
			'email'    => array( 'E-mail', 'email', '' ),
			'phone'    => array( 'Telefón', 'text', '' ),
			'web'      => array( 'Web', 'url', '' ),
		),
		'Číslovanie a vystavovanie' => array(
			'format'    => array( 'Formát čísla faktúry', 'text', '{RRRR} = rok (2026), {RR} = rok dvojmiestne, {nnn} / {nnnn} = poradie v roku. Predvolené {RRRR}{nnn} → 2026001.' ),
			'auto'      => array( 'Vystaviť faktúru automaticky z objednávky', 'checkbox', 'Prevod na účet: keď je objednávka zaplatená (Spracováva sa). Dobierka a osobný odber: keď je objednávka vybavená.' ),
			'issued_by' => array( 'Vystavil', 'text', 'Meno na faktúre (nepovinné).' ),
			'footer'    => array( 'Text v päte faktúry', 'text', '' ),
		),
	);
}

add_action( 'admin_post_jinv_save_settings', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_settings' );
	$in  = wp_unslash( $_POST );
	$out = array();
	foreach ( jinv_setting_fields() as $group ) {
		foreach ( $group as $k => $f ) {
			if ( 'checkbox' === $f[1] ) {
				$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			} elseif ( 'number' === $f[1] ) {
				$out[ $k ] = max( 0, min( 365, (int) ( isset( $in[ $k ] ) ? $in[ $k ] : 14 ) ) );
			} elseif ( 'email' === $f[1] ) {
				$out[ $k ] = sanitize_email( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			} elseif ( 'url' === $f[1] ) {
				$out[ $k ] = esc_url_raw( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			} else {
				$out[ $k ] = sanitize_text_field( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			}
		}
	}
	$out['iban'] = strtoupper( preg_replace( '/\s+/', '', $out['iban'] ) );
	if ( false === strpos( $out['format'], '{n' ) ) {
		$out['format'] = '{RRRR}{nnn}';
	}
	update_option( 'jinv_settings', $out, false );
	wp_safe_redirect( admin_url( 'admin.php?page=j-invoices&tab=settings&saved=1' ) );
	exit;
} );
