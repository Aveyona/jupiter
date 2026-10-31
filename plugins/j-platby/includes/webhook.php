<?php
/**
 * Webhook Stripe: POST /wp-json/j-platby/v1/stripe
 * Overenie podpisu (Stripe-Signature, HMAC-SHA256 s tajomstvom webhooku), potom spracovanie udalostí:
 * checkout.session.completed / async_payment_succeeded → zaplatené; async_payment_failed → neúspešná;
 * checkout.session.expired → zrušená nezaplatená objednávka; charge.refunded → vrátenie urobené priamo v Stripe.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'j-platby/v1', '/stripe', array(
		'methods'             => 'POST',
		'callback'            => 'jpl_webhook',
		'permission_callback' => '__return_true',
	) );
} );

/** Overí podpis Stripe. Vráti režim ('test' / 'live') alebo WP_Error. */
function jpl_verify_signature( $payload, $header, $tolerance = 300 ) {
	$t  = 0;
	$v1 = array();
	foreach ( explode( ',', (string) $header ) as $part ) {
		$kv = explode( '=', trim( $part ), 2 );
		if ( 2 !== count( $kv ) ) {
			continue;
		}
		if ( 't' === $kv[0] ) {
			$t = (int) $kv[1];
		} elseif ( 'v1' === $kv[0] ) {
			$v1[] = $kv[1];
		}
	}
	if ( ! $t || ! $v1 ) {
		return new WP_Error( 'jpl_sig', 'Chýba podpis.' );
	}
	if ( abs( time() - $t ) > $tolerance ) {
		return new WP_Error( 'jpl_sig', 'Podpis je príliš starý.' );
	}
	foreach ( array( 'test', 'live' ) as $mode ) {
		$secret = jpl_webhook_secret( $mode );
		if ( '' === $secret ) {
			continue;
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
		foreach ( $v1 as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return $mode;
			}
		}
	}
	return new WP_Error( 'jpl_sig', 'Neplatný podpis.' );
}

function jpl_webhook( WP_REST_Request $req ) {
	$payload = $req->get_body();
	$mode    = jpl_verify_signature( $payload, $req->get_header( 'stripe-signature' ) );
	if ( is_wp_error( $mode ) ) {
		update_option( 'jpl_webhook_last', array( 'at' => current_time( 'mysql' ), 'ok' => 0, 'msg' => $mode->get_error_message() ), false );
		update_option( 'jpl_webhook_fail_at', time(), false );
		return new WP_REST_Response( array( 'error' => $mode->get_error_message() ), 400 );
	}
	$ev   = json_decode( $payload, true );
	$type = (string) ( $ev['type'] ?? '' );
	$obj  = (array) ( $ev['data']['object'] ?? array() );
	$msg  = jpl_handle_event( $type, $obj );
	update_option( 'jpl_webhook_last', array( 'at' => current_time( 'mysql' ), 'ok' => 1, 'type' => $type, 'mode' => $mode, 'msg' => $msg ), false );
	update_option( 'jpl_webhook_ok_at', time(), false );
	return new WP_REST_Response( array( 'received' => true, 'result' => $msg ), 200 );
}

/** Objednávka podľa Checkout Session (metadata order_id + zhoda uloženého ID session). */
function jpl_order_from_session( array $s ) {
	$id    = absint( $s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0 );
	$order = $id ? wc_get_order( $id ) : null;
	if ( ! $order ) {
		return null;
	}
	// Platba kartou z pokladne, alebo odkaz na platbu z administrácie (uložené ID session sa zhoduje).
	if ( 'jpl_stripe' !== $order->get_payment_method() && (string) $order->get_meta( '_jpl_session' ) !== (string) ( $s['id'] ?? '' ) ) {
		return null;
	}
	return $order;
}

function jpl_order_from_pi( $pi ) {
	if ( ! $pi ) {
		return null;
	}
	$found = wc_get_orders( array( 'limit' => 1, 'meta_key' => '_jpl_pi', 'meta_value' => $pi, 'status' => array_keys( wc_get_order_statuses() ) ) );
	return $found ? $found[0] : null;
}

function jpl_handle_event( $type, array $obj ) {
	switch ( $type ) {
		case 'checkout.session.completed':
		case 'checkout.session.async_payment_succeeded':
			$order = jpl_order_from_session( $obj );
			if ( ! $order ) {
				return 'objednávka nenájdená';
			}
			if ( 'paid' !== ( $obj['payment_status'] ?? '' ) ) {
				$order->add_order_note( 'Stripe: platba čaká na potvrdenie banky.' );
				return 'čaká';
			}
			$r = jpl_check_session( $order, $obj );
			return is_wp_error( $r ) ? $r->get_error_message() : 'zaplatené #' . $order->get_order_number();

		case 'checkout.session.async_payment_failed':
			$order = jpl_order_from_session( $obj );
			if ( $order && ! $order->is_paid() ) {
				$order->update_status( 'failed', 'Stripe: platba zlyhala.' );
			}
			return 'neúspešná';

		case 'checkout.session.expired':
			$order = jpl_order_from_session( $obj );
			if ( $order && $order->get_meta( '_jpl_link' ) && (string) $order->get_meta( '_jpl_session' ) === (string) ( $obj['id'] ?? '' ) ) {
				// Odkaz na platbu vypršal – objednávka sa nemení (zákazník môže platiť inak, odkaz sa dá vytvoriť znova).
				$order->delete_meta_data( '_jpl_link' );
				$order->add_order_note( 'Stripe: odkaz na platbu kartou vypršal bez zaplatenia.' );
				$order->save();
				return 'odkaz vypršal #' . $order->get_order_number();
			}
			if ( $order && 'jpl_stripe' === $order->get_payment_method() && ! $order->is_paid() && $order->has_status( 'pending' ) && (string) $order->get_meta( '_jpl_session' ) === (string) ( $obj['id'] ?? '' ) ) {
				$order->update_status( 'cancelled', 'Stripe: zákazník platbu nedokončil, platobná stránka vypršala.' );
				return 'zrušená #' . $order->get_order_number();
			}
			return 'bez zmeny';

		case 'charge.refunded':
			$order = jpl_order_from_pi( (string) ( $obj['payment_intent'] ?? '' ) );
			if ( ! $order ) {
				return 'objednávka nenájdená';
			}
			$stripe   = round( (int) ( $obj['amount_refunded'] ?? 0 ) / 100, 2 );
			$woo      = round( (float) $order->get_total_refunded(), 2 );
			$diff     = round( $stripe - $woo, 2 );
			if ( $diff >= 0.01 ) {
				// Vrátenie urobené priamo v Stripe Dashboarde – zapíše sa aj do objednávky (bez ďalšieho vrátenia peňazí).
				$r = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => $diff, 'reason' => 'Vrátené v Stripe', 'refund_payment' => false, 'restock_items' => false ) );
				if ( is_wp_error( $r ) ) {
					$order->add_order_note( 'Stripe: v Stripe bolo vrátených ' . wc_price( $diff ) . ', ale do objednávky sa to nepodarilo zapísať: ' . $r->get_error_message() );
				} else {
					$order->add_order_note( 'Stripe: vrátenie ' . wc_price( $diff ) . ' urobené v Stripe Dashboarde – zapísané do objednávky.' );
				}
			}
			return 'vrátenie';
	}
	return 'ignorované';
}
