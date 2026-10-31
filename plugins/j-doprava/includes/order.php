<?php
/**
 * Zásielky Packeta k objednávkam: podanie (createPacket), štítky PDF, zrušenie, sledovanie stavu.
 * Meta objednávky: _jdp_kind (pp|hd), _jdp_point (pole), _jdp_point_label, _jdp_packet_id, _jdp_barcode,
 * _jdp_status (kód), _jdp_status_text, _jdp_status_at, _jdp_weight, _jdp_cod, _jdp_log (posledné chyby / udalosti).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Je platba dobierkou? Rozhoduje spôsob platby – objednávka na dobierku je v stave Spracováva sa, ktorý WooCommerce
 * považuje za „zaplatený“, hoci peniaze vyberie až dopravca. Dobierka sa preto neodvodzuje od is_paid().
 */
function jdp_is_cod( $order ) {
	$m = array_filter( array_map( 'trim', explode( ',', (string) jdp_opt( 'cod_methods' ) ) ) );
	return in_array( $order->get_payment_method(), $m, true ) && ! $order->has_status( array( 'refunded', 'cancelled', 'failed' ) );
}

// J-Fakturácia: dobierka sú rovnaké platobné metódy ako v nastavení J-Dopravy (jedna definícia pre obe).
add_filter( 'jinv_cod_methods', function ( $methods ) {
	$m = array_filter( array_map( 'trim', explode( ',', (string) jdp_opt( 'cod_methods' ) ) ) );
	return array_values( array_unique( array_merge( (array) $methods, $m ) ) );
} );

/**
 * Je objednávka pripravená na odoslanie? Spracováva sa (zaplatená alebo dobierka), Pozastavené len pri dobierke –
 * nezaplatený prevod (Pozastavené / Čaká na platbu) sa neposiela.
 */
function jdp_ready_to_ship( $order ) {
	return $order->has_status( 'processing' ) || ( $order->has_status( 'on-hold' ) && jdp_is_cod( $order ) );
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
	// Poistka proti dvojkliku / súbežnému podaniu: jedna objednávka sa podáva len raz naraz.
	$lock = 'jdp_creating_' . $order->get_id();
	if ( get_transient( $lock ) ) {
		return new WP_Error( 'jdp', 'Zásielka sa práve podáva – počkajte chvíľu a obnovte stránku.' );
	}
	set_transient( $lock, 1, 60 );
	$fresh = wc_get_order( $order->get_id() );
	if ( $fresh && $fresh->get_meta( '_jdp_packet_id' ) && 11 !== (int) $fresh->get_meta( '_jdp_status' ) ) {
		delete_transient( $lock );
		return new WP_Error( 'jdp', 'Zásielka je už podaná (' . $fresh->get_meta( '_jdp_barcode' ) . ').' );
	}
	$attr = jdp_packet_attributes( $order, $over );
	$r    = jdp_api( 'createPacket', array( 'packetAttributes' => $attr ) );
	delete_transient( $lock );
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
	// Len zásielky, ktoré ešte nie sú v konečnom stave (doručená, vrátená, zrušená) – najstaršie kontrolované ako prvé.
	$orders = wc_get_orders( array(
		'limit'        => 200,
		'type'         => 'shop_order',
		'date_created' => '>' . ( time() - 60 * DAY_IN_SECONDS ),
		'meta_query'   => array( // phpcs:ignore
			array( 'key' => '_jdp_packet_id', 'compare' => 'EXISTS' ),
			array( 'key' => '_jdp_status', 'value' => array( 7, 10, 11 ), 'compare' => 'NOT IN' ),
		),
		'orderby'      => 'date',
		'order'        => 'ASC',
	) );
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
		// Poznačiť vytlačené štítky (filter Na tlač na stránke Zásielky).
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o && in_array( (string) $o->get_meta( '_jdp_packet_id' ), $pids, true ) ) {
				$o->update_meta_data( '_jdp_label_at', current_time( 'mysql' ) );
				$o->save_meta_data();
			}
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
		} elseif ( 'claim' === $act ) {
			$r = jdp_create_claim( $o );
		} else {
			$r = jdp_refresh_status( $o );
		}
		is_wp_error( $r ) ? $err[] = '#' . $o->get_order_number() . ': ' . $r->get_error_message() : $ok++;
	}
	$names = array( 'create' => 'Podané zásielky', 'cancel' => 'Zrušené zásielky', 'status' => 'Aktualizovaný stav', 'claim' => 'Spätné zásielky' );
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
		if ( $o->get_meta( '_jdp_label_at' ) ) {
			echo '<p class="description">Štítok vytlačený ' . esc_html( date_i18n( 'j. n. H:i', strtotime( (string) $o->get_meta( '_jdp_label_at' ) ) ) ) . '.</p>';
		}
		if ( (int) $o->get_meta( '_jdp_status' ) <= 1 ) {
			echo '<p><a href="' . esc_url( jdp_action_url( 'cancel', $o->get_id() ) ) . '" onclick="return confirm(\'Zrušiť zásielku v Packete?\')" style="color:#b32d2e">Zrušiť zásielku</a></p>';
		}
		jdp_claim_box( $o );
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

/* ---------------------------------------------------------------- Automatické podanie zásielky */

// Objednávka prešla do stavu Spracováva sa (zaplatená kartou, prevod, dobierka) → podanie na pozadí (nezdržiava pokladňu).
add_action( 'woocommerce_order_status_processing', function ( $order_id ) {
	if ( ! jdp_opt( 'auto_create' ) || ! jdp_ready() ) {
		return;
	}
	$o = wc_get_order( $order_id );
	if ( ! $o || ! jdp_order_kind( $o ) || $o->get_meta( '_jdp_packet_id' ) ) {
		return;
	}
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'jdp_auto_create', array( 'order_id' => (int) $order_id ), 'j-doprava' );
	} else {
		wp_schedule_single_event( time(), 'jdp_auto_create', array( (int) $order_id ) );
	}
} );
add_action( 'jdp_auto_create', function ( $order_id ) {
	$o = wc_get_order( absint( $order_id ) );
	if ( ! $o || $o->get_meta( '_jdp_packet_id' ) || ! $o->has_status( 'processing' ) ) {
		return;
	}
	$r = jdp_create_packet( $o );
	if ( ! is_wp_error( $r ) ) {
		$o->add_order_note( 'Packeta: zásielka podaná automaticky (J-Doprava → Nastavenia → Automatické podanie).' );
	}
} );

/* ---------------------------------------------------------------- Spätná zásielka (vrátenie / reklamácia) */

/**
 * Spätná zásielka cez Packetu (reklamačný asistent): zákazník povie na ľubovoľnom výdajnom mieste kód
 * a zásielku pošle späť bez tlače štítku. Meta _jdp_claim = [ id, barcode, password, at ].
 */
function jdp_create_claim( $order ) {
	if ( ! jdp_order_kind( $order ) && ! $order->get_meta( '_jdp_packet_id' ) ) {
		return new WP_Error( 'jdp', 'Objednávka nemá dopravu Packeta.' );
	}
	$c = $order->get_meta( '_jdp_claim' );
	if ( is_array( $c ) && ! empty( $c['id'] ) ) {
		return new WP_Error( 'jdp', 'Spätná zásielka už existuje (' . $c['barcode'] . ').' );
	}
	$phone = preg_replace( '/[^\d+]/', '', (string) ( $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone() ) );
	$r     = jdp_api( 'createPacketClaimWithPassword', array(
		'claimWithPasswordAttributes' => array(
			'number'           => 'R' . $order->get_order_number(),
			'email'            => $order->get_billing_email(),
			'phone'            => $phone,
			'value'            => number_format( (float) $order->get_total(), 2, '.', '' ),
			'currency'         => $order->get_currency(),
			'eshop'            => (string) jdp_opt( 'sender' ),
			'sendLabelToEmail' => false,
		),
	) );
	if ( is_wp_error( $r ) ) {
		jdp_log( $order, 'spätnú zásielku sa nepodarilo vytvoriť – ' . $r->get_error_message() );
		$order->save();
		return $r;
	}
	$c = array( 'id' => (string) $r->id, 'barcode' => (string) ( $r->barcode ? $r->barcode : 'Z' . $r->id ), 'password' => (string) $r->password, 'at' => current_time( 'mysql' ) );
	$order->update_meta_data( '_jdp_claim', $c );
	jdp_log( $order, 'spätná zásielka ' . $c['barcode'] . ' vytvorená, kód pre zákazníka: ' . $c['password'] . '.' );
	$order->save();
	return true;
}

/** Spätná zásielka v boxe Packeta (doručená alebo podaná zásielka). */
function jdp_claim_box( $o ) {
	$c = $o->get_meta( '_jdp_claim' );
	if ( is_array( $c ) && ! empty( $c['id'] ) ) {
		echo '<p><strong>Spätná zásielka</strong> <a href="' . esc_url( jdp_tracking_url( $c['barcode'] ) ) . '" target="_blank">' . esc_html( $c['barcode'] ) . '</a><br>Kód pre zákazníka: <code>' . esc_html( $c['password'] ) . '</code><br><small>Zákazník kód povie na ľubovoľnom výdajnom mieste Packety – štítok netlačí.</small></p>';
		return;
	}
	if ( 7 === (int) $o->get_meta( '_jdp_status' ) ) {
		echo '<p><a href="' . esc_url( jdp_action_url( 'claim', $o->get_id() ) ) . '" onclick="return confirm(\'Vytvoriť spätnú zásielku (vrátenie / reklamácia)? Zákazník dostane kód, s ktorým tovar pošle späť z výdajného miesta Packety.\')">Spätná zásielka (vrátenie / reklamácia)</a></p>';
	}
}
