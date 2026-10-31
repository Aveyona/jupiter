<?php
/** Merné jednotky produktov (kus, hodina, kg, mesiac…): pole v úprave produktu, zobrazenie v sklade, v balíčkoch a voliteľne pri cene v obchode. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zoznam jednotiek: skratka => [názov, text pri cene].
 * Vlastné jednotky sa pridávajú v Nastaveniach (riadok „skratka | názov | za …“).
 */
function jsk_units() {
	$u = array(
		'ks'   => array( 'kus', '' ),
		'h'    => array( 'hodina', 'za hodinu' ),
		'min'  => array( 'minúta', 'za minútu' ),
		'deň'  => array( 'deň', 'za deň' ),
		'týž.' => array( 'týždeň', 'za týždeň' ),
		'mes.' => array( 'mesiac', 'za mesiac' ),
		'rok'  => array( 'rok', 'za rok' ),
		'kg'   => array( 'kilogram', 'za kg' ),
		'g'    => array( 'gram', 'za g' ),
		'l'    => array( 'liter', 'za liter' ),
		'ml'   => array( 'mililiter', 'za ml' ),
		'm'    => array( 'meter', 'za meter' ),
		'm²'   => array( 'štvorcový meter', 'za m²' ),
		'm³'   => array( 'kubický meter', 'za m³' ),
		'bal.' => array( 'balenie', 'za balenie' ),
		'sada' => array( 'sada', 'za sadu' ),
		'pár'  => array( 'pár', 'za pár' ),
		'lic.' => array( 'licencia', 'za licenciu' ),
		'os.'  => array( 'osoba', 'za osobu' ),
		'str.' => array( 'strana', 'za stranu' ),
	);
	foreach ( preg_split( '/\r?\n/', (string) jsk_opt( 'units_custom' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( '' !== $p[0] && mb_strlen( $p[0] ) <= 10 ) {
			$u[ $p[0] ] = array( '' !== ( $p[1] ?? '' ) ? $p[1] : $p[0], $p[2] ?? '' );
		}
	}
	return apply_filters( 'jsk_units', $u );
}

/** Jednotka produktu (variant preberá jednotku hlavného produktu); predvolene „ks“. */
function jsk_unit( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( (int) $product ) : $product;
	if ( ! $product instanceof WC_Product ) {
		return 'ks';
	}
	$unit = (string) $product->get_meta( '_jsk_unit' );
	if ( '' === $unit && $product->is_type( 'variation' ) ) {
		$unit = (string) get_post_meta( $product->get_parent_id(), '_jsk_unit', true );
	}
	return '' === $unit ? 'ks' : $unit;
}

/** Množstvo s jednotkou („8 h“, „2,5 kg“); pri kusoch len číslo (ak $ks = false). */
function jsk_qty_unit( $qty, $product, $ks = false ) {
	$u = jsk_unit( $product );
	return jsk_qty( $qty ) . ( 'ks' !== $u || $ks ? ' ' . $u : '' );
}

/* ---------- Pole „Jednotka“ v úprave produktu (záložka Zásoby) ---------- */

add_action( 'woocommerce_product_options_inventory_product_data', function () {
	global $post;
	$cur  = (string) get_post_meta( $post->ID, '_jsk_unit', true );
	$opts = array();
	foreach ( jsk_units() as $k => $u ) {
		$opts[ $k ] = $k . ' – ' . $u[0];
	}
	if ( '' !== $cur && ! isset( $opts[ $cur ] ) ) {
		$opts[ $cur ] = $cur;
	}
	echo '<div class="options_group">';
	woocommerce_wp_select(
		array(
			'id'          => '_jsk_unit',
			'label'       => 'Jednotka',
			'value'       => '' === $cur ? 'ks' : $cur,
			'options'     => $opts,
			'desc_tip'    => true,
			'description' => 'V čom sa produkt predáva a skladuje (J-Sklad): kus, hodina, kg, mesiac… Zobrazí sa pri zásobe, v dokladoch, v balíčkoch a podľa nastavenia aj pri cene v obchode. Varianty preberajú jednotku hlavného produktu.',
		)
	);
	echo '</div>';
} );

add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
	if ( ! isset( $_POST['_jsk_unit'] ) ) { // phpcs:ignore
		return;
	}
	$u = sanitize_text_field( wp_unslash( $_POST['_jsk_unit'] ) ); // phpcs:ignore
	if ( 'ks' === $u || '' === $u ) {
		$product->delete_meta_data( '_jsk_unit' );
	} elseif ( isset( jsk_units()[ $u ] ) || $u === (string) $product->get_meta( '_jsk_unit' ) ) {
		$product->update_meta_data( '_jsk_unit', $u );
	}
} );

/* ---------- Obchod: „· za hodinu“ pri cene (voľba v Nastaveniach) ---------- */

add_filter( 'woocommerce_get_price_html', function ( $html, $product ) {
	if ( '' === $html || is_admin() || ! jsk_opt( 'unit_price' ) || ! $product instanceof WC_Product ) {
		return $html;
	}
	$per = jsk_units()[ jsk_unit( $product ) ][1] ?? '';
	return '' === $per ? $html : $html . '<small class="jsk-unit-price"> · ' . esc_html( $per ) . '</small>';
}, 20, 2 );
