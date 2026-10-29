<?php
/**
 * Pohyby zásob.
 *
 * Každá zmena zásoby produktu (doklad J-Skladu, predaj, vrátenie, refundácia, úprava objednávky, ručná zmena,
 * import, iný doplnok) prejde hákmi WooCommerce:
 *   woocommerce_(product|variation)_before_set_stock → zmena v databáze → woocommerce_(product|variation)_set_stock
 * Pred zmenou J-Sklad zamkne produkt (GET_LOCK) a prečíta skutočný stav z databázy, po zmene ho prečíta znova –
 * rozdiel je presne táto zmena aj pri súbežných objednávkach. Zdroj zmeny (doklad, objednávka…) sa určí z kontextu,
 * ktorý sa nastaví ešte pred zmenou. Ak stav pred zmenou nesedí so stavom v knihe (zmena mimo WooCommerce, alebo
 * počas vypnutia J-Skladu), zapíše sa najprv opravný riadok.
 *
 * Zásoba variantu, ktorý preberá zásobu z hlavného produktu, sa vedie na hlavnom produkte (get_stock_managed_by_id).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Kontext zmeny robenej dokladom J-Skladu: [ id, source, args, row, after ]. */
$GLOBALS['jsk_ctx'] = null;
/** Objednávka, ktorej zásoby WooCommerce práve mení: [ order, kind, once ]. */
$GLOBALS['jsk_order_ctx'] = null;
/** Stav produktu pred zmenou (z databázy) – medzi before_set_stock a set_stock. */
$GLOBALS['jsk_before'] = array();

const JSK_EPS = 0.00001;

/** Priemerná nákupná cena produktu (alebo null). */
function jsk_cost( $product_id ) {
	$v = get_post_meta( $product_id, '_jsk_cost', true );
	return '' === $v || null === $v ? null : (float) $v;
}

/** Skutočná zásoba priamo z databázy (objekt produktu môže byť pri súbežných objednávkach zastaraný). */
function jsk_db_stock( $product_id ) {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock' LIMIT 1", $product_id ) ); // phpcs:ignore
	return null === $v || '' === $v ? null : (float) $v;
}

function jsk_lock( $name, $wait = 10 ) {
	global $wpdb;
	return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $wpdb->prefix . $name, $wait ) );
}

function jsk_unlock( $name ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $wpdb->prefix . $name ) );
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
		'item_id'     => (int) ( $args['item_id'] ?? 0 ),
		'order_id'    => (int) ( $args['order_id'] ?? 0 ),
		'cost'        => isset( $args['cost'] ) && '' !== $args['cost'] && null !== $args['cost'] ? (float) $args['cost'] : null,
		'note'        => mb_substr( (string) ( $args['note'] ?? '' ), 0, 250 ),
		'user_id'     => get_current_user_id(),
		'created_at'  => current_time( 'mysql' ),
	) );
	return (int) $wpdb->insert_id;
}

/** Kniha produktu začína vždy počiatočným stavom – ak ešte nemá žiadny pohyb, zapíše ho. */
function jsk_ensure_opening( $product_id, $stock ) {
	if ( jsk_last_move( $product_id ) || null === $stock ) {
		return;
	}
	jsk_log_move( $product_id, (float) $stock, (float) $stock, 'opening', array( 'cost' => jsk_cost( $product_id ), 'note' => 'Prvý záznam produktu v J-Sklade' ) );
}

/** ID produktu, ktorý nesie zásobu (variant s „zásobu spravuje hlavný produkt“ → hlavný produkt). */
function jsk_stock_id( $p ) {
	if ( ! $p instanceof WC_Product ) {
		$p = wc_get_product( $p );
	}
	if ( ! $p ) {
		return 0;
	}
	return method_exists( $p, 'get_stock_managed_by_id' ) ? (int) $p->get_stock_managed_by_id() : (int) $p->get_id();
}

/**
 * Zmena zásoby dokladom: $qty kladné = príjem, záporné = výdaj. Pri príjme s cenou prepočíta priemernú nákupnú cenu.
 * $args: doc_id, item_id, cost (nákupná cena príjmu), cost_reverse (storno príjmu s touto cenou), note.
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
	$sid = jsk_stock_id( $p );
	$qty = (float) $qty;
	if ( abs( $qty ) < JSK_EPS ) {
		return (float) $p->get_stock_quantity();
	}
	$has_cost = isset( $args['cost'] ) && null !== $args['cost'] && '' !== $args['cost'];
	// Výdaj dokladom: kontrola zásoby pod zámkom produktu – súbežná objednávka ju nemôže medzitým minúť.
	$guard = $qty < 0 && ! empty( $args['no_negative'] ) && ! $p->backorders_allowed();
	unset( $args['no_negative'] );
	if ( $guard ) {
		if ( ! jsk_lock( 'jsk_stock_' . $sid ) ) {
			return new WP_Error( 'jsk_busy', 'Zásoba produktu „' . $p->get_name() . '“ sa práve mení – skúste to znova.' );
		}
		$st = jsk_db_stock( $sid );
		if ( null !== $st && $st + $qty < -JSK_EPS ) {
			jsk_unlock( 'jsk_stock_' . $sid );
			return new WP_Error( 'jsk_short', sprintf( 'Na sklade nie je dosť: „%s“ – na sklade %s, výdaj %s.', $p->get_name(), jsk_qty( $st ), jsk_qty( abs( $qty ) ) ) );
		}
	}
	$GLOBALS['jsk_ctx'] = array( 'id' => $sid, 'source' => $source, 'args' => $args, 'row' => 0, 'before' => null, 'after' => null );
	try {
		$after = wc_update_product_stock( $p, abs( $qty ), $qty >= 0 ? 'increase' : 'decrease' );
		$ctx   = $GLOBALS['jsk_ctx'];
	} finally {
		$GLOBALS['jsk_ctx'] = null;
		if ( $guard ) {
			jsk_unlock( 'jsk_stock_' . $sid );
		}
	}
	if ( false === $after || null === $after || is_wp_error( $after ) ) {
		return new WP_Error( 'jsk_stock', 'Zásobu produktu „' . $p->get_name() . '“ sa nepodarilo zmeniť.' );
	}
	$old = null === $ctx['before'] ? (float) $after - $qty : (float) $ctx['before'];
	// Priemerná nákupná cena (až po úspešnej zmene zásoby).
	$cur = jsk_cost( $sid );
	if ( $qty > 0 && $has_cost ) {
		$base = max( 0, $old );
		$new  = null === $cur || $base <= 0 ? (float) $args['cost'] : ( $base * $cur + $qty * (float) $args['cost'] ) / ( $base + $qty );
		update_post_meta( $sid, '_jsk_cost', round( $new, 4 ) );
	} elseif ( $qty < 0 && isset( $args['cost_reverse'] ) && null !== $args['cost_reverse'] && null !== $cur ) {
		// Storno príjmu: vážený priemer sa vráti späť (ak ostáva zásoba).
		$rest = $old + $qty;
		if ( $rest > 0 && $old > 0 ) {
			update_post_meta( $sid, '_jsk_cost', round( max( 0, ( $old * $cur + $qty * (float) $args['cost_reverse'] ) / $rest ), 4 ) );
		}
	}
	if ( ! $ctx['row'] ) {
		// Hák WooCommerce sa nespustil (iný doplnok ho mohol vypnúť) – zápis priamo.
		$args['cost'] = $has_cost && $qty > 0 ? $args['cost'] : jsk_cost( $sid );
		jsk_ensure_opening( $sid, $old );
		jsk_log_move( $sid, $qty, $after, $source, $args );
	} elseif ( $qty > 0 && $has_cost ) {
		global $wpdb;
		$wpdb->update( jsk_t( 'moves' ), array( 'cost' => (float) $args['cost'] ), array( 'id' => (int) $ctx['row'] ) );
	}
	return (float) $after;
}

/** Pred zmenou zásoby: zámok produktu a skutočný stav z databázy. */
function jsk_before_set_stock( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$id = $product->get_id();
	// Ak WooCommerce ohlási „pred zmenou“, ale hodnota sa nezmení, „po zmene“ nepríde – zámok už držíme, stav sa len prečíta znova.
	$locked = ! empty( $GLOBALS['jsk_before'][ $id ][1] ) ? true : jsk_lock( 'jsk_stock_' . $id );
	$GLOBALS['jsk_before'][ $id ] = array( jsk_db_stock( $id ), $locked );
}
add_action( 'woocommerce_product_before_set_stock', 'jsk_before_set_stock', 1 );
add_action( 'woocommerce_variation_before_set_stock', 'jsk_before_set_stock', 1 );

/** Po zmene zásoby: zápis do knihy. */
function jsk_on_set_stock( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$id     = $product->get_id();
	$b      = $GLOBALS['jsk_before'][ $id ] ?? null;
	unset( $GLOBALS['jsk_before'][ $id ] );
	try {
		if ( $product->managing_stock() ) {
			jsk_record_change( $product, $id, $b ? $b[0] : null );
		}
	} catch ( \Throwable $e ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( 'J-Sklad: ' . $e->getMessage(), array( 'source' => 'j-sklad' ) );
		}
	} finally {
		if ( $b && $b[1] ) {
			jsk_unlock( 'jsk_stock_' . $id );
		}
	}
}
add_action( 'woocommerce_product_set_stock', 'jsk_on_set_stock', 20 );
add_action( 'woocommerce_variation_set_stock', 'jsk_on_set_stock', 20 );

function jsk_record_change( $product, $id, $before ) {
	$now = jsk_db_stock( $id );
	if ( null === $now ) {
		$now = (float) $product->get_stock_quantity();
	}
	$last = jsk_last_move( $id );
	$book = $last && null !== $last['stock_after'] ? (float) $last['stock_after'] : null;
	$ctx  = $GLOBALS['jsk_ctx'];
	$mine = $ctx && (int) $ctx['id'] === (int) $id;
	if ( $mine ) {
		$GLOBALS['jsk_ctx']['before'] = $before;
	}
	if ( null === $before ) {
		$before = $book; // bez háku „pred zmenou“ – porovnanie s knihou
	}
	// Prvý záznam produktu: počiatočný stav.
	if ( ! $last ) {
		$start = null === $before ? $now : $before;
		jsk_log_move( $id, $start, $start, 'opening', array( 'cost' => jsk_cost( $id ), 'note' => 'Prvý záznam produktu v J-Sklade' ) );
		$book = $start;
		if ( null === $before ) {
			$before = $start;
		}
	} elseif ( null !== $book && abs( $before - $book ) > JSK_EPS ) {
		// Kniha nesedí so skutočnosťou – opravný riadok pred touto zmenou.
		jsk_log_move( $id, $before - $book, $before, 'other', array( 'cost' => jsk_cost( $id ), 'note' => 'Vyrovnanie s knihou – zmena mimo WooCommerce alebo počas vypnutia J-Skladu' ) );
	}
	$delta = $now - $before;
	if ( abs( $delta ) < JSK_EPS && ! $mine ) {
		return;
	}
	if ( $mine ) {
		$a = $ctx['args'];
		$a['cost'] = jsk_cost( $id );
		$GLOBALS['jsk_ctx']['row']   = jsk_log_move( $id, $delta, $now, $ctx['source'], $a );
		$GLOBALS['jsk_ctx']['after'] = $now;
		return;
	}
	$args = array( 'cost' => jsk_cost( $id ) );
	$oc   = $GLOBALS['jsk_order_ctx'];
	if ( $oc && ! empty( $oc['product'] ) && (int) $oc['product'] !== (int) $id ) {
		$oc = null; // kontext úpravy objednávky patrí inému produktu
	}
	if ( $oc && $oc['order'] instanceof WC_Order ) {
		$num              = $oc['order']->get_order_number();
		$args['order_id'] = $oc['order']->get_id();
		$source           = $delta < 0 ? 'order' : 'restore';
		$args['note']     = array(
			'sale'    => 'Objednávka #' . $num,
			'restore' => 'Vrátenie – objednávka #' . $num,
			'refund'  => 'Refundácia – objednávka #' . $num,
			'edit'    => 'Úprava objednávky #' . $num,
		)[ $oc['kind'] ] ?? 'Objednávka #' . $num;
		if ( ! empty( $oc['once'] ) ) {
			$GLOBALS['jsk_order_ctx'] = null;
		}
	} elseif ( doing_action( 'wp_ajax_woocommerce_do_ajax_product_import' ) || ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) ) {
		$source       = 'other';
		$args['note'] = 'Import produktov';
	} elseif ( is_admin() && ! wp_doing_cron() && current_user_can( 'edit_products' ) ) {
		$source = 'manual'; // úprava produktu, rýchla úprava, varianty
	} else {
		$source = 'other'; // REST API, iný doplnok, cron
	}
	jsk_log_move( $id, $delta, $now, $source, $args );
}

/* Kontext objednávky – nastaví sa pred zmenou zásob, aby sa pohyb zapísal rovno s číslom objednávky. */

function jsk_set_order_ctx( $order, $kind, $once = false, $product_id = 0 ) {
	$GLOBALS['jsk_order_ctx'] = $order instanceof WC_Order ? array( 'order' => $order, 'kind' => $kind, 'once' => $once, 'product' => (int) $product_id ) : null;
}

add_filter( 'woocommerce_can_reduce_order_stock', function ( $can, $order = null ) {
	if ( $can ) {
		jsk_set_order_ctx( $order, 'sale' );
	}
	return $can;
}, PHP_INT_MAX, 2 );
add_action( 'woocommerce_reduce_order_stock', function () {
	$GLOBALS['jsk_order_ctx'] = null;
}, 1 );

add_filter( 'woocommerce_can_restore_order_stock', function ( $can, $order = null ) {
	if ( $can ) {
		jsk_set_order_ctx( $order, 'restore' );
	}
	return $can;
}, PHP_INT_MAX, 2 );
add_action( 'woocommerce_restore_order_stock', function () {
	$GLOBALS['jsk_order_ctx'] = null;
}, 1 );

// Refundácia s vrátením na sklad (wc_restock_refunded_items).
add_filter( 'woocommerce_can_restock_refunded_items', function ( $can, $order = null ) {
	if ( $can ) {
		jsk_set_order_ctx( $order, 'refund' );
	}
	return $can;
}, PHP_INT_MAX, 2 );
add_action( 'woocommerce_order_refunded', function () {
	$GLOBALS['jsk_order_ctx'] = null;
}, 1 );

// Pridanie / odobratie / zmena množstva položky v úprave objednávky (wc_maybe_adjust_line_item_product_stock).
add_filter( 'woocommerce_prevent_adjust_line_item_product_stock', function ( $prevent, $item = null ) {
	if ( ! $prevent && $item instanceof WC_Order_Item_Product && $item->get_order_id() ) {
		$prod = $item->get_product();
		jsk_set_order_ctx( wc_get_order( $item->get_order_id() ), 'edit', true, $prod ? jsk_stock_id( $prod ) : 0 );
	}
	return $prevent;
}, PHP_INT_MAX, 2 );

// Poistka: kontext objednávky neprežije koniec požiadavky ani výnimku pri vrátení peňazí.
add_action( 'shutdown', function () {
	$GLOBALS['jsk_order_ctx'] = null;
}, 0 );

// Poistka: kontext objednávky po dokončení jej uloženia už neplatí.
add_action( 'woocommerce_after_order_object_save', function () {
	if ( $GLOBALS['jsk_order_ctx'] && 'edit' !== $GLOBALS['jsk_order_ctx']['kind'] ) {
		return;
	}
	$GLOBALS['jsk_order_ctx'] = null;
}, 1 );
