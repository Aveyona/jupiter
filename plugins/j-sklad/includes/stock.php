<?php
/**
 * Pohyby zásob.
 *
 * - jsk_apply_move(): zmena zásoby dokladom J-Skladu (cez wc_update_product_stock) + zápis do knihy.
 * - Každú inú zmenu zásoby (predaj v objednávke, vrátenie, ručná úprava produktu, iný doplnok, import) zachytí
 *   hák woocommerce_product_set_stock / woocommerce_variation_set_stock a zapíše ju do knihy; predaj a vrátenie
 *   sa doplnia o číslo objednávky (háky woocommerce_reduce_order_item_stock / woocommerce_restore_order_item_stock).
 * - Rozdiel sa počíta oproti stavu po poslednom pohybe v knihe; prvý záznam produktu je počiatočný stav.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Produkty, ktorých zmenu práve robí J-Sklad (aby sa nezapísala druhýkrát ako „iná zmena“). */
$GLOBALS['jsk_internal'] = array();
/** Posledný zapísaný „všeobecný“ pohyb v tejto požiadavke podľa produktu – objednávka ho potom doplní. */
$GLOBALS['jsk_recent'] = array();

/** Priemerná nákupná cena produktu (alebo null). */
function jsk_cost( $product_id ) {
	$v = get_post_meta( $product_id, '_jsk_cost', true );
	return '' === $v || null === $v ? null : (float) $v;
}

/** Zapíše riadok do knihy pohybov, vráti ID. */
function jsk_log_move( $product_id, $qty, $stock_after, $source, $args = array() ) {
	global $wpdb;
	$wpdb->insert( jsk_t( 'moves' ), array(
		'product_id'  => (int) $product_id,
		'qty'         => (float) $qty,
		'stock_after' => null === $stock_after ? null : (float) $stock_after,
		'source'      => $source,
		'doc_id'      => (int) ( $args['doc_id'] ?? 0 ),
		'order_id'    => (int) ( $args['order_id'] ?? 0 ),
		'cost'        => isset( $args['cost'] ) && '' !== $args['cost'] && null !== $args['cost'] ? (float) $args['cost'] : null,
		'note'        => mb_substr( (string) ( $args['note'] ?? '' ), 0, 250 ),
		'user_id'     => get_current_user_id(),
		'created_at'  => current_time( 'mysql' ),
	) );
	return (int) $wpdb->insert_id;
}

/**
 * Zmena zásoby dokladom: $qty kladné = príjem, záporné = výdaj. Pri príjme s cenou prepočíta priemernú nákupnú cenu.
 * Vráti nový stav alebo WP_Error.
 */
function jsk_apply_move( $product_id, $qty, $source, $args = array() ) {
	$p = wc_get_product( $product_id );
	if ( ! $p ) {
		return new WP_Error( 'jsk_product', 'Produkt ' . (int) $product_id . ' neexistuje.' );
	}
	if ( ! $p->managing_stock() ) {
		return new WP_Error( 'jsk_stock', 'Produkt „' . $p->get_name() . '“ nemá zapnuté sledovanie zásob (Spravovať zásoby).' );
	}
	$qty = (float) $qty;
	$old = (float) $p->get_stock_quantity();
	// Priemerná nákupná cena: vážený priemer starej zásoby a príjmu.
	if ( $qty > 0 && isset( $args['cost'] ) && null !== $args['cost'] && '' !== $args['cost'] ) {
		$cur  = jsk_cost( $product_id );
		$base = max( 0, $old );
		$new  = null === $cur || $base <= 0 ? (float) $args['cost'] : ( $base * $cur + $qty * (float) $args['cost'] ) / ( $base + $qty );
		update_post_meta( $product_id, '_jsk_cost', round( $new, 4 ) );
	}
	$GLOBALS['jsk_internal'][ $product_id ] = true;
	$after = wc_update_product_stock( $p, abs( $qty ), $qty >= 0 ? 'increase' : 'decrease' );
	unset( $GLOBALS['jsk_internal'][ $product_id ] );
	if ( false === $after || null === $after ) {
		return new WP_Error( 'jsk_stock', 'Zásobu produktu „' . $p->get_name() . '“ sa nepodarilo zmeniť.' );
	}
	if ( ! isset( $args['cost'] ) || null === $args['cost'] || '' === $args['cost'] ) {
		$args['cost'] = jsk_cost( $product_id );
	}
	jsk_log_move( $product_id, $qty, $after, $source, $args );
	return (float) $after;
}

/** Zachytenie každej zmeny zásoby mimo J-Skladu. */
function jsk_on_set_stock( $product ) {
	if ( ! $product instanceof WC_Product || ! $product->managing_stock() ) {
		return;
	}
	$id = $product->get_id();
	if ( ! empty( $GLOBALS['jsk_internal'][ $id ] ) ) {
		return;
	}
	$now  = (float) $product->get_stock_quantity();
	$last = jsk_last_move( $id );
	if ( $last && null !== $last['stock_after'] && abs( (float) $last['stock_after'] - $now ) < 0.00001 ) {
		return; // bez zmeny
	}
	$delta  = $last && null !== $last['stock_after'] ? $now - (float) $last['stock_after'] : $now;
	$source = $last ? ( is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ? 'manual' : 'other' ) : 'opening';
	$GLOBALS['jsk_recent'][ $id ] = jsk_log_move( $id, $delta, $now, $source, array( 'cost' => jsk_cost( $id ), 'note' => $last ? '' : 'Prvý záznam produktu v J-Sklade' ) );
}
add_action( 'woocommerce_product_set_stock', 'jsk_on_set_stock', 20 );
add_action( 'woocommerce_variation_set_stock', 'jsk_on_set_stock', 20 );

/** Predaj v objednávke: posledný pohyb produktu sa označí ako predaj s číslom objednávky. */
add_action( 'woocommerce_reduce_order_item_stock', function ( $item, $change, $order ) {
	jsk_tag_order_move( $item, $order, 'order' );
}, 20, 3 );

/** Vrátenie na sklad (zrušená objednávka, refundácia). */
add_action( 'woocommerce_restore_order_item_stock', function ( $item, $new_stock, $old_stock, $order ) {
	jsk_tag_order_move( $item, $order, 'restore' );
}, 20, 4 );

function jsk_tag_order_move( $item, $order, $source ) {
	global $wpdb;
	if ( ! $item instanceof WC_Order_Item_Product || ! $order instanceof WC_Order ) {
		return;
	}
	$pid = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
	if ( empty( $GLOBALS['jsk_recent'][ $pid ] ) ) {
		return;
	}
	$wpdb->update( jsk_t( 'moves' ), array(
		'source'   => $source,
		'order_id' => $order->get_id(),
		'note'     => mb_substr( 'Objednávka #' . $order->get_order_number(), 0, 250 ),
	), array( 'id' => (int) $GLOBALS['jsk_recent'][ $pid ] ) );
	unset( $GLOBALS['jsk_recent'][ $pid ] );
}
