<?php
/**
 * Kódy kupónov: generovanie a vytvorenie kupónu WooCommerce.
 * Kupóny sú bežné kupóny WooCommerce (shop_coupon); J-Kupóny k nim pridávajú meta _jkp (vytvorené tu) a poznámku.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dĺžka generovaného kódu (zatiaľ 4 znaky). */
function jkp_code_length() {
	return max( 3, min( 20, (int) apply_filters( 'jkp_code_length', 4 ) ) );
}

/** Znaky kódu – veľké písmená a číslice bez zameniteľných (0/O, 1/I/L). */
function jkp_code_alphabet() {
	return (string) apply_filters( 'jkp_code_alphabet', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789' );
}

/** Existuje už kupón s týmto kódom (aj v koši)? */
function jkp_code_exists( $code ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_title = %s LIMIT 1", wc_format_coupon_code( $code ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Vygeneruje nový jedinečný kód. Prázdny reťazec, ak sa voľný kód nenájde. */
function jkp_generate_code( $exclude = array() ) {
	$abc = jkp_code_alphabet();
	$len = jkp_code_length();
	$max = strlen( $abc ) - 1;
	for ( $try = 0; $try < 200; $try++ ) {
		$code = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$code .= $abc[ random_int( 0, $max ) ];
		}
		if ( ! in_array( $code, $exclude, true ) && ! jkp_code_exists( $code ) ) {
			return $code;
		}
	}
	return '';
}

/**
 * Vytvorí kupón WooCommerce.
 *
 * @param array $a code, type (percent|fixed_cart), amount, expires (Y-m-d), limit, per_user, min, free_shipping, email, note.
 * @return int|WP_Error ID kupónu.
 */
function jkp_create_coupon( array $a ) {
	$code = strtoupper( trim( (string) ( $a['code'] ?? '' ) ) );
	if ( '' === $code || ! preg_match( '/^[A-Z0-9_-]{3,30}$/', $code ) ) {
		return new WP_Error( 'jkp_code', 'Kód môže obsahovať len písmená bez diakritiky, číslice, pomlčku a podčiarkovník (3 – 30 znakov).' );
	}
	if ( jkp_code_exists( $code ) ) {
		return new WP_Error( 'jkp_dup', 'Kupón s kódom ' . $code . ' už existuje.' );
	}
	$type   = in_array( $a['type'] ?? '', array( 'percent', 'fixed_cart' ), true ) ? $a['type'] : 'percent';
	$amount = (float) str_replace( ',', '.', (string) ( $a['amount'] ?? 0 ) );
	if ( $amount <= 0 || ( 'percent' === $type && $amount > 100 ) ) {
		return new WP_Error( 'jkp_amount', 'percent' === $type ? 'Zľava v percentách musí byť od 0,01 do 100.' : 'Zľava v eurách musí byť väčšia ako 0.' );
	}
	$c = new WC_Coupon();
	$c->set_code( $code );
	$c->set_discount_type( $type );
	$c->set_amount( $amount );
	$c->set_individual_use( true );
	if ( ! empty( $a['expires'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $a['expires'] ) ) {
		$c->set_date_expires( strtotime( $a['expires'] . ' 23:59:59 ' . wp_timezone_string() ) );
	}
	$c->set_usage_limit( max( 0, (int) ( $a['limit'] ?? 0 ) ) );
	$c->set_usage_limit_per_user( max( 0, (int) ( $a['per_user'] ?? 0 ) ) );
	$min = (float) str_replace( ',', '.', (string) ( $a['min'] ?? 0 ) );
	if ( $min > 0 ) {
		$c->set_minimum_amount( $min );
	}
	$c->set_free_shipping( ! empty( $a['free_shipping'] ) );
	$emails = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) ( $a['email'] ?? '' ) ) ) );
	if ( $emails ) {
		$c->set_email_restrictions( array_values( $emails ) );
	}
	$note = sanitize_textarea_field( (string) ( $a['note'] ?? '' ) );
	$c->set_description( $note );
	$c->update_meta_data( '_jkp', 1 );
	if ( ! empty( $a['batch'] ) ) {
		$c->update_meta_data( '_jkp_batch', sanitize_text_field( $a['batch'] ) );
	}
	$c->update_meta_data( '_jkp_by', get_current_user_id() );
	$id = $c->save();
	return $id ? $id : new WP_Error( 'jkp_save', 'Kupón sa nepodarilo uložiť.' );
}

/** Stav kupónu: active | off | expired | used. */
function jkp_status( WC_Coupon $c ) {
	if ( 'publish' !== get_post_status( $c->get_id() ) ) {
		return 'off';
	}
	$exp = $c->get_date_expires();
	if ( $exp && $exp->getTimestamp() < time() ) {
		return 'expired';
	}
	if ( $c->get_usage_limit() && $c->get_usage_count() >= $c->get_usage_limit() ) {
		return 'used';
	}
	return 'active';
}

function jkp_status_label( $s ) {
	$l = array(
		'active'  => array( 'Platný', '#e6f4ea', '#1e6b34' ),
		'off'     => array( 'Vypnutý', '#f0f0f1', '#50575e' ),
		'expired' => array( 'Po platnosti', '#fcf0e3', '#8a4b00' ),
		'used'    => array( 'Vyčerpaný', '#e8eefc', '#1d4ed8' ),
	);
	$x = $l[ $s ] ?? $l['off'];
	return '<span style="display:inline-block;padding:2px 9px;border-radius:10px;font-size:12px;background:' . $x[1] . ';color:' . $x[2] . '">' . esc_html( $x[0] ) . '</span>';
}

/** Text zľavy: „10 %“ alebo „5,00 €“ (+ doprava zadarmo). */
function jkp_discount_text( WC_Coupon $c ) {
	$t = 'percent' === $c->get_discount_type()
		? wc_format_localized_decimal( rtrim( rtrim( number_format( (float) $c->get_amount(), 2, '.', '' ), '0' ), '.' ) ) . ' %'
		: wp_strip_all_tags( wc_price( $c->get_amount() ) );
	if ( $c->get_free_shipping() ) {
		$t .= ' + doprava zadarmo';
	}
	return $t;
}

/** Objednávky, v ktorých bol kód použitý (najnovšie prvé). */
function jkp_coupon_orders( $code, $limit = 20 ) {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_type = 'coupon' AND order_item_name = %s ORDER BY order_id DESC LIMIT %d", wc_format_coupon_code( $code ), $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return array_filter( array_map( 'wc_get_order', $ids ) );
}
