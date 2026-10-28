<?php
/** Nastavenia J-Skladu. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jsk_defaults() {
	return array(
		'format_receipt' => 'PR{RRRR}{nnn}',   // príjemka
		'format_issue'   => 'VY{RRRR}{nnn}',   // výdajka
		'format_opening' => 'OP{RRRR}{nnn}',   // otváracia príjemka (počiatočný stav)
		'low_stock'      => (int) get_option( 'woocommerce_notify_low_stock_amount', 2 ),
		'reasons'        => 'Poškodenie, Vlastná spotreba, Darček / reklama, Strata, Iné',
		'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
	);
}

function jsk_settings() {
	return wp_parse_args( (array) get_option( 'jsk_settings', array() ), jsk_defaults() );
}

function jsk_opt( $k ) {
	$s = jsk_settings();
	return $s[ $k ] ?? null;
}

/** Druhy dokladov: kľúč => [názov, znamienko pohybu, kľúč formátu]. */
function jsk_doc_types() {
	return array(
		'receipt' => array( 'Príjemka', 1, 'format_receipt' ),
		'issue'   => array( 'Výdajka', -1, 'format_issue' ),
		'opening' => array( 'Otváracia príjemka', 1, 'format_opening' ),
	);
}

/** Zdroje pohybov v knihe: kľúč => popis. */
function jsk_move_sources() {
	return array(
		'receipt' => 'Príjemka',
		'issue'   => 'Výdajka',
		'opening' => 'Počiatočný stav',
		'order'   => 'Objednávka – predaj',
		'restore' => 'Objednávka – vrátenie na sklad',
		'storno'  => 'Storno dokladu',
		'manual'  => 'Ručná zmena v produkte',
		'cost'    => 'Zmena nákupnej ceny',
		'other'   => 'Iná zmena (doplnok, import)',
	);
}

/** Dôvody výdaja z nastavení. */
function jsk_reasons() {
	return array_values( array_filter( array_map( 'trim', explode( ',', (string) jsk_opt( 'reasons' ) ) ) ) );
}

function jsk_qty( $q ) {
	$q = (float) $q;
	return rtrim( rtrim( number_format( $q, 4, ',', ' ' ), '0' ), ',' );
}

function jsk_money( $v ) {
	return null === $v || '' === $v ? '—' : number_format( (float) $v, 2, ',', ' ' ) . ' ' . ( 'EUR' === jsk_opt( 'currency' ) ? '€' : jsk_opt( 'currency' ) );
}
