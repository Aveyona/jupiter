<?php
/** Volania Stripe API (bez knižnice – wp_remote_request, formulárové parametre). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JPL_API', 'https://api.stripe.com/v1/' );
define( 'JPL_API_VERSION', '2024-06-20' );

/**
 * Volanie Stripe API. Vráti dekódovanú odpoveď (pole) alebo WP_Error s textom chyby od Stripe.
 * $params sú vnorené polia (metadata[order_id] …), $idem = idempotentný kľúč pre POST.
 */
function jpl_stripe( $method, $path, array $params = array(), $idem = '', $mode = '' ) {
	$key = jpl_secret( $mode );
	if ( '' === $key ) {
		return new WP_Error( 'jpl_nokey', 'Chýba tajný kľúč Stripe (J-Platby → Nastavenia).' );
	}
	$url  = JPL_API . ltrim( $path, '/' );
	$args = array(
		'method'  => $method,
		'timeout' => 30,
		'headers' => array(
			'Authorization'  => 'Bearer ' . $key,
			'Stripe-Version' => JPL_API_VERSION,
			'User-Agent'     => 'J-Platby/' . JPL_VERSION . ' (WordPress)',
		),
	);
	$query = jpl_build_query( $params );
	if ( 'GET' === $method || 'DELETE' === $method ) {
		if ( '' !== $query ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
		}
	} else {
		$args['body']                    = $query;
		$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
		if ( $idem ) {
			$args['headers']['Idempotency-Key'] = $idem;
		}
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'jpl_http', 'Stripe nie je dostupný: ' . $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( $code >= 400 || ! is_array( $data ) ) {
		$err = is_array( $data ) ? ( $data['error']['message'] ?? '' ) : '';
		return new WP_Error( 'jpl_api', ( $err ? $err : 'Neočakávaná odpoveď Stripe' ) . ' (HTTP ' . $code . ')', is_array( $data ) ? $data : null );
	}
	return $data;
}

/** Vnorené pole → a[b][c]=x&… (formát, ktorý očakáva Stripe). */
function jpl_build_query( array $params, $prefix = '' ) {
	$out = array();
	foreach ( $params as $k => $v ) {
		if ( null === $v ) {
			continue;
		}
		$key = '' === $prefix ? (string) $k : $prefix . '[' . $k . ']';
		if ( is_array( $v ) ) {
			$sub = jpl_build_query( $v, $key );
			if ( '' !== $sub ) {
				$out[] = $sub;
			}
		} else {
			if ( is_bool( $v ) ) {
				$v = $v ? 'true' : 'false';
			}
			$out[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $v );
		}
	}
	return implode( '&', $out );
}

/** Suma v centoch. */
function jpl_cents( $amount ) {
	return (int) round( (float) $amount * 100 );
}
