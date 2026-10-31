<?php
/**
 * Zásielky Packeta k objednávkam: podanie (createPacket), štítky PDF, zrušenie, sledovanie stavu.
 * Meta objednávky: _jdp_kind (pp|hd), _jdp_point (pole), _jdp_point_label, _jdp_packet_id, _jdp_barcode,
 * _jdp_status (kód), _jdp_status_text, _jdp_status_at, _jdp_weight, _jdp_cod, _jdp_log (posledné chyby / udalosti).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Je platba dobierkou? */
function jdp_is_cod( $order ) {
	$m = array_filter( array_map( 'trim', explode( ',', (string) jdp_opt( 'cod_methods' ) ) ) );
	return in_array( $order->get_payment_method(), $m, true ) && ! $order->is_paid();
}

/** Hmotnosť objednávky (kg). */
function jdp_order_weight( $order ) {
	$w = 0.0;
	foreach ( $order->get_items() as $item ) {
		$p = $item->get_product();
		if ( $p && ! $p->is_virtual() ) {
			$w += (float) $p->get_weight() * (float) $item->get_quantity();
		}
	}
	return jdp_weight_kg( $w );
}

function jdp_log( $order, $text ) {
	$order->add_order_note( 'Packeta: ' . $text );
}

/** Údaje zásielky pre createPacket. */
function jdp_packet_attributes( $order, array $over = array() ) {
	$kind  = jdp_order_kind( $order );
	$ship  = $order->has_shipping_address();
	$first = $ship ? $order->get_shipping_first_name() : $order->get_billing_first_name();
	$last  = $ship ? $order->get_shipping_last_name() : $order->get_billing_last_name();
	$comp  = $ship ? $order->get_shipping_company() : $order->get_billing_company();
	$phone = $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
	$total = (float) $order->get_total();
	$a     = array(
		'number'   => (string) $order->get_order_number(),
		'name'     => $first,
		'surname'  => $last,
		'company'  => $comp,
		'email'    => $order->get_billing_email(),
		'phone'    => preg_replace( '/[^\d+]/', '', (string) $phone ),
		'currency' => $order->get_currency(),
		'value'    => number_format( $over['value'] ?? $total, 2, '.', '' ),
		'weight'   => number_format( (float) ( $over['weight'] ?? jdp_order_weight( $order ) ), 3, '.', '' ),
		'eshop'    => (string) jdp_opt( 'sender' ),
	);
	$cod = $over['cod'] ?? ( jdp_is_cod( $order ) ? $total : 0 );
	if ( (float) $cod > 0 ) {
		$a['cod'] = number_format( (float) $cod, 2, '.', '' );
	}
	if ( 'pp' === $kind ) {
		$p = (array) $order->get_meta( '_jdp_point' );
		if ( ! empty( $p['carrierId'] ) ) {
			$a['addressId']          = (string) $p['carrierId'];
			$a['carrierPickupPoint'] = (string) ( $p['carrierPickupPointId'] ?? '' );
		} else {
			$a['addressId'] = (string) ( $p['id'] ?? '' );
		}
	} else {
		// Doručenie na adresu – Packeta Home HD (Slovensko id 131, Česko 106); ulica a číslo domu.
		$country = strtolower( $ship ? $order->get_shipping_country() : $order->get_billing_country() );
		$street  = trim( ( $ship ? $order->get_shipping_address_1() : $order->get_billing_address_1() ) . ' ' . ( $ship ? $order->get_shipping_address_2() : $order->get_billing_address_2() ) );
		$house   = '';
		if ( preg_match( '/^(.*?)[\s,]+(\d[\w\/\-]*)$/u', $street, $m ) ) {
			$street = $m[1];
			$house  = $m[2];
		}
		$a['addressId']   = (string) apply_filters( 'jdp_home_delivery_id', 'cz' === $country ? 106 : 131, $country );
		$a['street']      = $street;
		$a['houseNumber'] = $house;
		$a['city']        = $ship ? $order->get_shipping_city() : $order->get_billing_city();
		$a['zip']         = preg_replace( '/\s+/', '', (string) ( $ship ? $order->get_shipping_postcode() : $order->get_billing_postcode() ) );
	}
	return apply_filters( 'jdp_packet_attributes', $a, $order );
}

/** Podanie zásielky. Vráti ID zásielky alebo WP_Error. */
function jdp_create_packet( $order, array $over = array() ) {
	$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
	if ( ! $order || ! jdp_order_kind( $order ) ) {
		return new WP_Error( 'jdp', 'Objednávka nemá dopravu Packeta.' );
	}
	if ( $order->get_meta( '_jdp_packet_id' ) && 11 !== (int) $order->get_meta( '_jdp_status' ) ) {
		return new WP_Error( 'jdp', 'Zásielka je už podaná (' . $order->get_meta( '_jdp_barcode' ) . ').' );
	}
	if ( 'pp' === jdp_order_kind( $order ) && ! $order->get_meta( '_jdp_point' ) ) {
		return new WP_Error( 'jdp', 'Objednávka nemá vybrané výdajné miesto.' );
	}
	$attr = jdp_packet_attributes( $order, $over );
	$r    = jdp_api( 'createPacket', array( 'packetAttributes' => $attr ) );
	if ( is_wp_error( $r ) ) {
		jdp_log( $order, 'zásielku sa nepodarilo podať – ' . $r->get_error_message() );
		$order->save();
		return $r;
	}
	$id  = (string) $r->id;
	$bar = (string) $r->barcode;
	$order->update_meta_data( '_jdp_packet_id', $id );
	$order->update_meta_data( '_jdp_barcode', $bar ? $bar : 'Z' . $id );
	$order->update_meta_data( '_jdp_status', 1 );
	$order->update_meta_data( '_jdp_status_text', jdp_status_codes()[1] );
	$order->update_meta_data( '_jdp_status_at', current_time( 'mysql' ) );
	$order->update_meta_data( '_jdp_weight', $attr['weight'] );
	$order->update_meta_data( '_jdp_cod', $attr['cod'] ?? 0 );
	jdp_log( $order, 'zásielka podaná ' . ( $bar ? $bar : 'Z' . $id ) . ( ! empty( $attr['cod'] ) ? ', dobierka ' . wc_price( $attr['cod'] ) : '' ) . ', ' . str_replace( '.', ',', $attr['weight'] ) . ' kg.' );
	$order->save();
	do_action( 'jdp_packet_created', $order, $id );
	return $id;
}

function jdp_cancel_packet( $order ) {
	$id = (string) $order->get_meta( '_jdp_packet_id' );
	if ( ! $id ) {
		return new WP_Error( 'jdp', 'Objednávka nemá zásielku.' );
	}
	$r = jdp_api( 'cancelPacket', array( 'packetId' => $id ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$order->update_meta_data( '_jdp_status', 11 );
	$order->update_meta_data( '_jdp_status_text', jdp_status_codes()[11] );
	$order->update_meta_data( '_jdp_status_at', current_time( 'mysql' ) );
	jdp_log( $order, 'zásielka ' . $order->get_meta( '_jdp_barcode' ) . ' zrušená.' );
	$order->save();
	return true;
}

/** Aktualizácia stavu zásielky. Vráti kód stavu alebo WP_Error. */
function jdp_refresh_status( $order ) {
	$id = (string) $order->get_meta( '_jdp_packet_id' );
	if ( ! $id ) {
		return new WP_Error( 'jdp', 'Objednávka nemá zásielku.' );
	}
	$r = jdp_api( 'packetStatus', array( 'packetId' => $id ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = (int) $r->statusCode;
	$old  = (int) $order->get_meta( '_jdp_status' );
	if ( $code !== $old ) {
		$text = jdp_status_codes()[ $code ] ?? (string) $r->codeText;
		$order->update_meta_data( '_jdp_status', $code );
		$order->update_meta_data( '_jdp_status_text', $text );
		$order->update_meta_data( '_jdp_status_at', current_time( 'mysql' ) );
		jdp_log( $order, 'stav zásielky: ' . $text . '.' );
		if ( 7 === $code && jdp_opt( 'auto_complete' ) && $order->has_status( array( 'processing', 'on-hold' ) ) ) {
			$order->update_status( 'completed', 'Packeta: zásielka doručená. ' );
		}
		$order->save();
		do_action( 'jdp_status_changed', $order, $code, $old );
	}
	return $code;
}

// Sledovanie: každú hodinu zásielky, ktoré nie sú v konečnom stave (najviac 60 dní).
add_action( 'jdp_tracking', function () {
	if ( ! jdp_ready() ) {
		return;
	}
	$orders = wc_get_orders( array( 'limit' => 100, 'meta_key' => '_jdp_packet_id', 'meta_compare' => 'EXISTS', 'date_created' => '>' . ( time() - 60 * DAY_IN_SECONDS ) ) );
	foreach ( $orders as $o ) {
		if ( ! jdp_status_final( (int) $o->get_meta( '_jdp_status' ) ) ) {
			jdp_refresh_status( $o );
		}
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'jdp_tracking' ) ) {
		wp_schedule_event( time() + 600, 'hourly', 'jdp_tracking' );
	}
} );

/** PDF štítkov pre zásielky (ID zásielok). Vráti binárne PDF alebo WP_Error. */
function jdp_labels_pdf( array $packet_ids, $format = '' ) {
	$packet_ids = array_values( array_filter( array_map( 'strval', $packet_ids ) ) );
	if ( ! $packet_ids ) {
		return new WP_Error( 'jdp', 'Žiadne zásielky na tlač.' );
	}
	$r = jdp_api( 'packetsLabelsPdf', array( 'packetIds' => array( '@item' => 'id', '@list' => $packet_ids ), 'format' => $format ? $format : (string) jdp_opt( 'label_format' ), 'offset' => 0 ), 30 + 5 * count( $packet_ids ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$pdf = base64_decode( (string) $r, true );
	return false !== $pdf && 0 === strpos( $pdf, '%PDF' ) ? $pdf : ( 0 === strpos( (string) $r, '%PDF' ) ? (string) $r : new WP_Error( 'jdp', 'Packeta nevrátila PDF štítkov.' ) );
}

/* ---------------------------------------------------------------- Akcie v administrácii */

add_action( 'admin_post_jdp_action', function () {
	if ( ! current_user_can( JDP_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$ids  = array_filter( array_map( 'intval', (array) ( $_REQUEST['ids'] ?? array( $_REQUEST['order'] ?? 0 ) ) ) ); // phpcs:ignore
	$act  = sanitize_key( $_REQUEST['act'] ?? '' ); // phpcs:ignore
	check_admin_referer( 'jdp_action' );
	$back = wp_get_referer() ? remove_query_arg( array( 'jdp_msg' ), wp_get_referer() ) : admin_url( 'admin.php?page=j-doprava' );
	$ok   = 0;
	$err  = array();
	if ( 'labels' === $act ) {
		$pids = array();
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o && $o->get_meta( '_jdp_packet_id' ) && 11 !== (int) $o->get_meta( '_jdp_status' ) ) {
				$pids[] = (string) $o->get_meta( '_jdp_packet_id' );
			}
		}
		$pdf = jdp_labels_pdf( $pids, sanitize_text_field( wp_unslash( $_REQUEST['format'] ?? '' ) ) ); // phpcs:ignore
		if ( is_wp_error( $pdf ) ) {
			wp_die( esc_html( $pdf->get_error_message() ), 'Štítky Packeta', array( 'back_link' => true ) );
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="packeta-stitky-' . gmdate( 'Ymd-His' ) . '.pdf"' );
		echo $pdf; // phpcs:ignore
		exit;
	}
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		if ( ! $o ) {
			continue;
		}
		if ( 'create' === $act ) {
			$over = array();
			if ( isset( $_POST['weight'] ) && '' !== $_POST['weight'] ) { // phpcs:ignore
				$over['weight'] = (float) str_replace( ',', '.', (string) wp_unslash( $_POST['weight'] ) ); // phpcs:ignore
			}
			if ( isset( $_POST['cod'] ) && '' !== $_POST['cod'] ) { // phpcs:ignore
				$over['cod'] = (float) str_replace( ',', '.', (string) wp_unslash( $_POST['cod'] ) ); // phpcs:ignore
			}
			$r = jdp_create_packet( $o, $over );
		} elseif ( 'cancel' === $act ) {
			$r = jdp_cancel_packet( $o );
		} else {
			$r = jdp_refresh_status( $o );
		}
		is_wp_error( $r ) ? $err[] = '#' . $o->get_order_number() . ': ' . $r->get_error_message() : $ok++;
	}
	$names = array( 'create' => 'Podané zásielky', 'cancel' => 'Zrušené zásielky', 'status' => 'Aktualizovaný stav' );
	set_transient( 'jdp_msg_' . get_current_user_id(), ( $names[ $act ] ?? 'Hotovo' ) . ': ' . $ok . ( $err ? '. Chyby: ' . implode( ' · ', $err ) : '.' ), 120 );
	wp_safe_redirect( add_query_arg( 'jdp_msg', 1, $back ) );
	exit;
} );

function jdp_action_url( $act, $ids, $extra = array() ) {
	return add_query_arg( array_merge( array( 'action' => 'jdp_action', 'act' => $act, 'ids' => array_values( (array) $ids ), '_wpnonce' => wp_create_nonce( 'jdp_action' ) ), $extra ), admin_url( 'admin-post.php' ) );
}

/* ---------------------------------------------------------------- Box v detaile objednávky */

add_action( 'add_meta_boxes', function () {
	$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	add_meta_box( 'jdp-box', 'Packeta (J-Doprava)', 'jdp_order_box', $screen, 'side', 'high' );
} );

function jdp_order_box( $post_or_order ) {
	$o = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $o ) {
		return;
	}
	$kind = jdp_order_kind( $o );
	if ( ! $kind ) {
		echo '<p class="description">Objednávka nemá dopravu Packeta.</p>';
		return;
	}
	echo '<p><strong>' . ( 'pp' === $kind ? 'Výdajné miesto' : 'Doručenie na adresu' ) . '</strong>';
	if ( 'pp' === $kind ) {
		$l = (string) $o->get_meta( '_jdp_point_label' );
		echo '<br>' . ( $l ? esc_html( $l ) : '<span style="color:#b32d2e">nie je vybrané</span>' );
	}
	echo '</p>';
	$pid = (string) $o->get_meta( '_jdp_packet_id' );
	if ( ! jdp_ready() ) {
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=j-doprava-settings' ) ) . '">Nastavte API heslo Packety</a></p>';
		return;
	}
	if ( $pid && 11 !== (int) $o->get_meta( '_jdp_status' ) ) {
		$bar = (string) $o->get_meta( '_jdp_barcode' );
		echo '<p>Zásielka <a href="' . esc_url( jdp_tracking_url( $bar ) ) . '" target="_blank">' . esc_html( $bar ) . '</a><br>Stav: <strong>' . esc_html( (string) $o->get_meta( '_jdp_status_text' ) ) . '</strong>' . ( (float) $o->get_meta( '_jdp_cod' ) > 0 ? '<br>Dobierka: ' . wp_kses_post( wc_price( $o->get_meta( '_jdp_cod' ) ) ) : '' ) . '</p>';
		echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( jdp_action_url( 'labels', $o->get_id() ) ) . '">Štítok PDF</a> <a class="button" href="' . esc_url( jdp_action_url( 'status', $o->get_id() ) ) . '">Obnoviť stav</a></p>';
		if ( (int) $o->get_meta( '_jdp_status' ) <= 1 ) {
			echo '<p><a href="' . esc_url( jdp_action_url( 'cancel', $o->get_id() ) ) . '" onclick="return confirm(\'Zrušiť zásielku v Packete?\')" style="color:#b32d2e">Zrušiť zásielku</a></p>';
		}
		return;
	}
	$cod = jdp_is_cod( $o ) ? (float) $o->get_total() : 0;
	echo '<div class="jdp-create"><p><label>Hmotnosť (kg)<br><input type="text" id="jdp-w" value="' . esc_attr( str_replace( '.', ',', (string) jdp_order_weight( $o ) ) ) . '" style="width:90px"></label> <label>Dobierka (€)<br><input type="text" id="jdp-c" value="' . esc_attr( $cod ? str_replace( '.', ',', (string) $cod ) : '' ) . '" style="width:90px" placeholder="bez"></label></p>';
	echo '<p><button type="button" class="button button-primary" id="jdp-go">Podať zásielku</button></p></div>';
	$url = jdp_action_url( 'create', $o->get_id() );
	echo '<script>document.getElementById("jdp-go").addEventListener("click",function(){var f=document.createElement("form");f.method="post";f.action=' . wp_json_encode( $url ) . ';[["weight","jdp-w"],["cod","jdp-c"]].forEach(function(x){var i=document.createElement("input");i.type="hidden";i.name=x[0];i.value=document.getElementById(x[1]).value;f.appendChild(i);});document.body.appendChild(f);f.submit();});</script>';
}

// Hromadné akcie v zozname objednávok (HPOS aj klasický zoznam).
foreach ( array( 'bulk_actions-woocommerce_page_wc-orders', 'bulk_actions-edit-shop_order' ) as $jdp_f ) {
	add_filter( $jdp_f, function ( $a ) {
		$a['jdp_create'] = 'Packeta – podať zásielky';
		$a['jdp_labels'] = 'Packeta – štítky PDF';
		return $a;
	} );
}
foreach ( array( 'handle_bulk_actions-woocommerce_page_wc-orders', 'handle_bulk_actions-edit-shop_order' ) as $jdp_f ) {
	add_filter( $jdp_f, function ( $redirect, $action, $ids ) {
		if ( in_array( $action, array( 'jdp_create', 'jdp_labels' ), true ) ) {
			return jdp_action_url( 'jdp_create' === $action ? 'create' : 'labels', array_map( 'intval', (array) $ids ) );
		}
		return $redirect;
	}, 10, 3 );
}

// Oznam po akcii (zoznam objednávok, detail).
add_action( 'admin_notices', function () {
	if ( empty( $_GET['jdp_msg'] ) ) { // phpcs:ignore
		return;
	}
	$m = get_transient( 'jdp_msg_' . get_current_user_id() );
	delete_transient( 'jdp_msg_' . get_current_user_id() );
	if ( $m ) {
		echo '<div class="notice notice-' . ( false !== strpos( $m, 'Chyby' ) || false !== strpos( $m, 'zlyhalo' ) ? 'warning' : 'success' ) . ' is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
	}
} );
