<?php
/**
 * Doklady skladu: príjemka (receipt), výdajka (issue), otváracia príjemka (opening).
 * Koncept sa dá upravovať a zmazať; zaúčtovaním dostane číslo a zmení zásoby. Zaúčtovaný doklad sa nemaže –
 * storno vráti zásoby protipohybom.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jsk_format_number( $format, $year, $seq ) {
	return strtr( $format, array(
		'{RRRR}'  => sprintf( '%04d', $year ),
		'{RR}'    => substr( sprintf( '%04d', $year ), 2 ),
		'{nnnnn}' => sprintf( '%05d', $seq ),
		'{nnnn}'  => sprintf( '%04d', $seq ),
		'{nnn}'   => sprintf( '%03d', $seq ),
	) );
}

/** Ďalšie číslo dokladu druhu v roku: [číslo, poradie]. */
function jsk_next_number( $type, $year ) {
	global $wpdb;
	$t   = jsk_doc_types()[ $type ];
	$fmt = (string) jsk_opt( $t[2] );
	if ( false === strpos( $fmt, '{n' ) ) {
		$fmt = jsk_defaults()[ $t[2] ];
	}
	$seq = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jsk_t( 'docs' ) . ' WHERE type = %s AND year = %d', $type, $year ) ); // phpcs:ignore
	return array( jsk_format_number( $fmt, $year, $seq ), $seq );
}

/** Uloží koncept dokladu (nový alebo existujúci). $items = [ [product_id, qty, price], … ]. Vráti ID alebo WP_Error. */
function jsk_save_draft( $id, $type, array $head, array $items ) {
	global $wpdb;
	if ( ! isset( jsk_doc_types()[ $type ] ) ) {
		return new WP_Error( 'jsk_type', 'Neznámy druh dokladu.' );
	}
	if ( $id ) {
		$d = jsk_doc( $id );
		if ( ! $d || 'draft' !== $d['status'] ) {
			return new WP_Error( 'jsk_locked', 'Zaúčtovaný doklad sa nedá upravovať – použite storno.' );
		}
	}
	$row = array(
		'type'        => $type,
		'doc_date'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $head['doc_date'] ?? '' ) ) ? $head['doc_date'] : current_time( 'Y-m-d' ),
		'partner'     => sanitize_text_field( $head['partner'] ?? '' ),
		'partner_doc' => sanitize_text_field( $head['partner_doc'] ?? '' ),
		'reason'      => sanitize_text_field( $head['reason'] ?? '' ),
		'note'        => sanitize_textarea_field( $head['note'] ?? '' ),
	);
	if ( $id ) {
		$wpdb->update( jsk_t( 'docs' ), $row, array( 'id' => $id ) );
		$wpdb->delete( jsk_t( 'doc_items' ), array( 'doc_id' => $id ) );
	} else {
		$row += array( 'status' => 'draft', 'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) );
		$wpdb->insert( jsk_t( 'docs' ), $row );
		$id = (int) $wpdb->insert_id;
	}
	$total = 0;
	foreach ( $items as $it ) {
		$p   = wc_get_product( (int) ( $it['product_id'] ?? 0 ) );
		$qty = (float) str_replace( ',', '.', (string) ( $it['qty'] ?? 0 ) );
		if ( ! $p || $qty <= 0 ) {
			continue;
		}
		$price = isset( $it['price'] ) && '' !== trim( (string) $it['price'] ) ? (float) str_replace( ',', '.', (string) $it['price'] ) : null;
		$wpdb->insert( jsk_t( 'doc_items' ), array(
			'doc_id'     => $id,
			'product_id' => $p->get_id(),
			'sku'        => (string) $p->get_sku(),
			'name'       => wp_strip_all_tags( $p->get_name() ),
			'qty'        => $qty,
			'price'      => $price,
		) );
		$total += null === $price ? 0 : $qty * $price;
	}
	$wpdb->update( jsk_t( 'docs' ), array( 'total' => round( $total, 4 ) ), array( 'id' => $id ) );
	return $id;
}

/** Zaúčtovanie: kontrola, číslo, pohyby zásob. Vráti true alebo WP_Error (pri chybe sa nič nezmení). */
function jsk_post_doc( $id ) {
	global $wpdb;
	$d = jsk_doc( $id );
	if ( ! $d || 'draft' !== $d['status'] ) {
		return new WP_Error( 'jsk_state', 'Doklad nie je koncept.' );
	}
	if ( ! $d['items'] ) {
		return new WP_Error( 'jsk_empty', 'Doklad nemá žiadne položky.' );
	}
	$sign = jsk_doc_types()[ $d['type'] ][1];
	// Kontrola pred akoukoľvek zmenou: sledovanie zásob a dostatok tovaru pri výdaji.
	$need = array();
	foreach ( $d['items'] as $it ) {
		$need[ $it['product_id'] ] = ( $need[ $it['product_id'] ] ?? 0 ) + (float) $it['qty'];
	}
	foreach ( $need as $pid => $q ) {
		$p = wc_get_product( $pid );
		if ( ! $p || ! $p->managing_stock() ) {
			return new WP_Error( 'jsk_stock', 'Produkt „' . ( $p ? $p->get_name() : '#' . $pid ) . '“ nemá zapnuté sledovanie zásob.' );
		}
		if ( $sign < 0 && (float) $p->get_stock_quantity() - $q < 0 && ! $p->backorders_allowed() ) {
			return new WP_Error( 'jsk_short', sprintf( 'Na sklade nie je dosť: „%s“ – na sklade %s, výdaj %s.', $p->get_name(), jsk_qty( $p->get_stock_quantity() ), jsk_qty( $q ) ) );
		}
	}
	$year            = (int) substr( $d['doc_date'], 0, 4 );
	list( $num, $seq ) = jsk_next_number( $d['type'], $year );
	$wpdb->update( jsk_t( 'docs' ), array( 'number' => $num, 'year' => $year, 'seq' => $seq, 'status' => 'posted', 'posted_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
	$label = jsk_doc_types()[ $d['type'] ][0] . ' ' . $num;
	foreach ( $d['items'] as $it ) {
		$r = jsk_apply_move( (int) $it['product_id'], $sign * (float) $it['qty'], $d['type'], array(
			'doc_id' => $id,
			'cost'   => $sign > 0 ? $it['price'] : null,
			'note'   => $label . ( 'issue' === $d['type'] && $d['reason'] ? ' – ' . $d['reason'] : '' ),
		) );
		if ( is_wp_error( $r ) ) {
			return $r; // výnimočný prípad (produkt zmazaný medzičasom) – ostatné položky sú zapísané
		}
	}
	do_action( 'jsk_doc_posted', $id, $d['type'] );
	return true;
}

/** Storno zaúčtovaného dokladu: protipohyby, doklad ostáva v evidencii. */
function jsk_cancel_doc( $id ) {
	global $wpdb;
	$d = jsk_doc( $id );
	if ( ! $d || 'posted' !== $d['status'] ) {
		return new WP_Error( 'jsk_state', 'Stornovať sa dá len zaúčtovaný doklad.' );
	}
	if ( 'opening' === $d['type'] ) {
		return new WP_Error( 'jsk_state', 'Otváracia príjemka len zaznamenáva počiatočný stav – nestornuje sa.' );
	}
	$sign = jsk_doc_types()[ $d['type'] ][1];
	foreach ( $d['items'] as $it ) {
		jsk_apply_move( (int) $it['product_id'], -1 * $sign * (float) $it['qty'], 'storno', array( 'doc_id' => $id, 'note' => 'Storno ' . $d['number'] ) );
	}
	$wpdb->update( jsk_t( 'docs' ), array( 'status' => 'cancelled' ), array( 'id' => $id ) );
	return true;
}

/**
 * Aktivácia: tabuľky a otváracia príjemka – súčasná zásoba všetkých produktov so sledovaním zásob sa zapíše ako
 * počiatočný stav (zásoby sa nemenia). Pri opätovnej aktivácii sa preskočia produkty, ktoré už majú pohyby.
 */
function jsk_activate() {
	global $wpdb;
	jsk_install();
	if ( ! function_exists( 'wc_get_products' ) ) {
		return;
	}
	$ids = array_merge(
		wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'manage_stock' => true, 'status' => array( 'publish', 'private', 'draft', 'pending' ) ) ),
		wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'type' => 'variation', 'manage_stock' => true ) )
	);
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( ! $ids ) {
		return;
	}
	$have = array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT product_id FROM ' . jsk_t( 'moves' ) ) ); // phpcs:ignore
	$ids  = array_diff( $ids, $have );
	if ( ! $ids ) {
		return;
	}
	$today = current_time( 'Y-m-d' );
	$year  = (int) current_time( 'Y' );
	list( $num, $seq ) = jsk_next_number( 'opening', $year );
	$wpdb->insert( jsk_t( 'docs' ), array(
		'type' => 'opening', 'number' => $num, 'year' => $year, 'seq' => $seq, 'doc_date' => $today, 'status' => 'posted',
		'note' => 'Počiatočný stav skladu pri zapnutí J-Skladu (zásoby sa nemenili).', 'user_id' => get_current_user_id(),
		'posted_at' => current_time( 'mysql' ), 'created_at' => current_time( 'mysql' ),
	) );
	$doc = (int) $wpdb->insert_id;
	foreach ( $ids as $pid ) {
		$p = wc_get_product( $pid );
		if ( ! $p || ! $p->managing_stock() ) {
			continue;
		}
		$q = (float) $p->get_stock_quantity();
		$wpdb->insert( jsk_t( 'doc_items' ), array( 'doc_id' => $doc, 'product_id' => $pid, 'sku' => (string) $p->get_sku(), 'name' => $p->get_name(), 'qty' => $q, 'price' => jsk_cost( $pid ) ) );
		jsk_log_move( $pid, $q, $q, 'opening', array( 'doc_id' => $doc, 'cost' => jsk_cost( $pid ), 'note' => $num ) );
	}
}
