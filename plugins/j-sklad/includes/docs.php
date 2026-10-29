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
	if ( false === strpos( $fmt, '{RR' ) ) {
		// Formát bez roka – poradie pokračuje cez roky, aby sa čísla neopakovali.
		$seq = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jsk_t( 'docs' ) . ' WHERE type = %s', $type ) ); // phpcs:ignore
	} else {
		$seq = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(seq) FROM ' . jsk_t( 'docs' ) . ' WHERE type = %s AND year = %d', $type, $year ) ); // phpcs:ignore
	}
	return array( jsk_format_number( $fmt, $year, $seq ), $seq );
}

/** Uloží koncept dokladu (nový alebo existujúci). $items = [ [product_id, qty, price], … ]. Vráti ID alebo WP_Error. */
function jsk_save_draft( $id, $type, array $head, array $items ) {
	if ( $id ) {
		// Rovnaký zámok ako zaúčtovanie – koncept sa nedá prepísať, kým sa zaúčtováva.
		if ( ! jsk_lock( 'jsk_doc_' . (int) $id, 5 ) ) {
			return new WP_Error( 'jsk_busy', 'Doklad sa práve zaúčtováva – úprava sa neuložila.' );
		}
		try {
			return jsk_save_draft_locked( $id, $type, $head, $items );
		} finally {
			jsk_unlock( 'jsk_doc_' . (int) $id );
		}
	}
	return jsk_save_draft_locked( 0, $type, $head, $items );
}

function jsk_save_draft_locked( $id, $type, array $head, array $items ) {
	global $wpdb;
	if ( $id ) {
		$d = jsk_doc( $id );
		if ( ! $d || 'draft' !== $d['status'] ) {
			return new WP_Error( 'jsk_locked', 'Zaúčtovaný doklad sa nedá upravovať – použite storno.' );
		}
		$type = $d['type']; // druh konceptu sa nemení
	}
	if ( ! in_array( $type, array( 'receipt', 'issue' ), true ) ) {
		return new WP_Error( 'jsk_type', 'Neznámy druh dokladu.' );
	}
	$row = array(
		'type'        => $type,
		'doc_date'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $head['doc_date'] ?? '' ) ) ? $head['doc_date'] : current_time( 'Y-m-d' ),
		'partner'     => sanitize_text_field( $head['partner'] ?? '' ),
		'partner_doc' => sanitize_text_field( $head['partner_doc'] ?? '' ),
		'partner_id'  => (int) ( $head['partner_id'] ?? 0 ),
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
	$total   = 0;
	$skipped = array();
	foreach ( $items as $it ) {
		$p   = wc_get_product( (int) ( $it['product_id'] ?? 0 ) );
		$raw = (float) str_replace( ',', '.', (string) ( $it['qty'] ?? 0 ) );
		$qty = (float) wc_stock_amount( $raw ); // WooCommerce vedie zásoby v celých kusoch (ak to iný doplnok nezmení)
		if ( ! $p || $qty <= 0 ) {
			$skipped[] = $p ? wp_strip_all_tags( $p->get_name() ) : '#' . (int) ( $it['product_id'] ?? 0 );
			continue;
		}
		$price = isset( $it['price'] ) && '' !== trim( (string) $it['price'] ) ? max( 0, (float) str_replace( ',', '.', (string) $it['price'] ) ) : null;
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
	$GLOBALS['jsk_skipped'] = $skipped;
	return $id;
}

/** Položky dokladu, ktoré už majú pohyb daného zdroja (pre dokončenie prerušeného zaúčtovania / storna). */
function jsk_done_items( $doc_id, $source ) {
	global $wpdb;
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT item_id FROM ' . jsk_t( 'moves' ) . ' WHERE doc_id = %d AND source = %s AND item_id > 0', $doc_id, $source ) ) ); // phpcs:ignore
}

/** Potrebné množstvá podľa produktu, ktorý nesie zásobu. */
function jsk_need_by_stock( array $items ) {
	$need = array();
	foreach ( $items as $it ) {
		$sid = jsk_stock_id( (int) $it['product_id'] );
		$key = $sid ? $sid : (int) $it['product_id'];
		$need[ $key ] = ( $need[ $key ] ?? 0 ) + (float) $it['qty'];
	}
	return $need;
}

/**
 * Zaúčtovanie: kontrola, číslo, pohyby zásob. Vráti true alebo WP_Error.
 * Doklad je počas zaúčtovania zamknutý (dvojklik ho nezaúčtuje dvakrát). Ak sa zaúčtovanie preruší, doklad ostane
 * v stave „posting“ a ďalšie zaúčtovanie dokončí len chýbajúce položky.
 */
function jsk_post_doc( $id ) {
	global $wpdb;
	if ( ! jsk_lock( 'jsk_doc_' . (int) $id, 0 ) ) {
		return new WP_Error( 'jsk_busy', 'Doklad sa práve zaúčtováva.' );
	}
	try {
		$d = jsk_doc( $id );
		if ( ! $d || ! in_array( $d['status'], array( 'draft', 'posting' ), true ) ) {
			return new WP_Error( 'jsk_state', 'Doklad nie je koncept (možno už bol zaúčtovaný).' );
		}
		if ( ! $d['items'] ) {
			return new WP_Error( 'jsk_empty', 'Doklad nemá žiadne položky.' );
		}
		$sign = jsk_doc_types()[ $d['type'] ][1];
		$done = 'posting' === $d['status'] ? jsk_done_items( $id, $d['type'] ) : array();
		$todo = array_filter( $d['items'], fn( $it ) => ! in_array( (int) $it['id'], $done, true ) );
		// Kontrola pred akoukoľvek zmenou: sledovanie zásob a dostatok tovaru pri výdaji.
		foreach ( $todo as $it ) {
			$p = wc_get_product( (int) $it['product_id'] );
			if ( ! $p || ! $p->managing_stock() ) {
				return new WP_Error( 'jsk_stock', 'Produkt „' . ( $p ? $p->get_name() : $it['name'] ) . '“ neexistuje alebo nemá zapnuté sledovanie zásob.' );
			}
		}
		if ( $sign < 0 ) {
			foreach ( jsk_need_by_stock( $todo ) as $sid => $q ) {
				$p  = wc_get_product( $sid );
				$st = jsk_db_stock( $sid ) ?? (float) $p->get_stock_quantity();
				if ( $st - $q < 0 && ! $p->backorders_allowed() ) {
					return new WP_Error( 'jsk_short', sprintf( 'Na sklade nie je dosť: „%s“ – na sklade %s, výdaj %s.', $p->get_name(), jsk_qty( $st ), jsk_qty( $q ) ) );
				}
			}
		}
		if ( 'draft' === $d['status'] ) {
			$year = (int) substr( $d['doc_date'], 0, 4 );
			jsk_lock( 'jsk_number' );
			list( $num, $seq ) = jsk_next_number( $d['type'], $year );
			$wpdb->update( jsk_t( 'docs' ), array( 'number' => $num, 'year' => $year, 'seq' => $seq, 'status' => 'posting', 'posted_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
			jsk_unlock( 'jsk_number' );
			$d['number'] = $num;
		}
		$label = jsk_doc_types()[ $d['type'] ][0] . ' ' . $d['number'];
		foreach ( $todo as $it ) {
			$r = jsk_apply_move( (int) $it['product_id'], $sign * (float) $it['qty'], $d['type'], array(
				'doc_id'  => $id,
				'item_id' => (int) $it['id'],
				'cost'    => $sign > 0 ? $it['price'] : null,
				'no_negative' => $sign < 0,
				'note'    => $label . ( 'issue' === $d['type'] && $d['reason'] ? ' – ' . $d['reason'] : '' ),
			) );
			if ( is_wp_error( $r ) ) {
				return new WP_Error( 'jsk_partial', $r->get_error_message() . ' Doklad ' . $d['number'] . ' je zaúčtovaný len čiastočne – po odstránení príčiny ho dokončite tlačidlom „Dokončiť zaúčtovanie“.' );
			}
		}
		$wpdb->update( jsk_t( 'docs' ), array( 'status' => 'posted' ), array( 'id' => $id ) );
		do_action( 'jsk_doc_posted', $id, $d['type'] );
		return true;
	} finally {
		jsk_unlock( 'jsk_doc_' . (int) $id );
	}
}

/** Storno zaúčtovaného dokladu: protipohyby, doklad ostáva v evidencii. Prerušené storno sa dá dokončiť. */
function jsk_cancel_doc( $id ) {
	global $wpdb;
	if ( ! jsk_lock( 'jsk_doc_' . (int) $id, 0 ) ) {
		return new WP_Error( 'jsk_busy', 'Doklad sa práve spracúva.' );
	}
	try {
		$d = jsk_doc( $id );
		if ( ! $d || ! in_array( $d['status'], array( 'posted', 'cancelling' ), true ) ) {
			return new WP_Error( 'jsk_state', 'Stornovať sa dá len zaúčtovaný doklad.' );
		}
		if ( 'opening' === $d['type'] ) {
			return new WP_Error( 'jsk_state', 'Otváracia príjemka len zaznamenáva počiatočný stav – nestornuje sa.' );
		}
		if ( 'inventory' === $d['type'] ) {
			$d['items'] = array_values( array_filter( $d['items'], fn( $it ) => abs( (float) $it['qty'] ) > JSK_EPS ) ); // len položky s rozdielom
		}
		$sign = jsk_doc_types()[ $d['type'] ][1];
		$done = 'cancelling' === $d['status'] ? jsk_done_items( $id, 'storno' ) : array();
		$todo = array_filter( $d['items'], fn( $it ) => ! in_array( (int) $it['id'], $done, true ) );
		if ( $sign > 0 ) {
			// Storno príjemky ubera zásobu – tovar už mohol byť predaný.
			foreach ( jsk_need_by_stock( $todo ) as $sid => $q ) {
				$p  = wc_get_product( $sid );
				$st = $p ? ( jsk_db_stock( $sid ) ?? (float) $p->get_stock_quantity() ) : 0;
				if ( $p && $p->managing_stock() && $st - $q < 0 ) {
					return new WP_Error( 'jsk_short', sprintf( 'Storno by znížilo zásobu pod nulu: „%s“ – na sklade %s, storno %s. Tovar už bol zrejme predaný; vystavte výdajku len na zostávajúce kusy.', $p->get_name(), jsk_qty( $st ), jsk_qty( $q ) ) );
				}
			}
		}
		$wpdb->update( jsk_t( 'docs' ), array( 'status' => 'cancelling' ), array( 'id' => $id ) );
		foreach ( $todo as $it ) {
			$r = jsk_apply_move( (int) $it['product_id'], -1 * $sign * (float) $it['qty'], 'storno', array(
				'doc_id'       => $id,
				'item_id'      => (int) $it['id'],
				'cost_reverse' => $sign > 0 && 'inventory' !== $d['type'] ? $it['price'] : null,
				'note'         => 'Storno ' . $d['number'],
			) );
			if ( is_wp_error( $r ) ) {
				return new WP_Error( 'jsk_partial', $r->get_error_message() . ' Storno je vykonané len čiastočne – po odstránení príčiny ho dokončite znova tlačidlom „Stornovať“.' );
			}
		}
		$wpdb->update( jsk_t( 'docs' ), array( 'status' => 'cancelled' ), array( 'id' => $id ) );
		return true;
	} finally {
		jsk_unlock( 'jsk_doc_' . (int) $id );
	}
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
	// Opätovná aktivácia: zmeny zásob počas vypnutia J-Skladu sa zapíšu ako vyrovnanie.
	foreach ( array_intersect( $ids, $have ) as $pid ) {
		$last = jsk_last_move( $pid );
		$now  = jsk_db_stock( $pid );
		if ( $last && null !== $last['stock_after'] && null !== $now && abs( $now - (float) $last['stock_after'] ) > JSK_EPS ) {
			jsk_log_move( $pid, $now - (float) $last['stock_after'], $now, 'other', array( 'cost' => jsk_cost( $pid ), 'note' => 'Vyrovnanie – zmena zásoby počas vypnutia J-Skladu' ) );
		}
	}
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
		if ( ! $p || ! $p->managing_stock() || jsk_stock_id( $p ) !== (int) $pid ) {
			continue; // variant so zásobou hlavného produktu sa vedie na hlavnom produkte
		}
		$q = (float) $p->get_stock_quantity();
		$wpdb->insert( jsk_t( 'doc_items' ), array( 'doc_id' => $doc, 'product_id' => $pid, 'sku' => (string) $p->get_sku(), 'name' => $p->get_name(), 'qty' => $q, 'price' => jsk_cost( $pid ) ) );
		jsk_log_move( $pid, $q, $q, 'opening', array( 'doc_id' => $doc, 'cost' => jsk_cost( $pid ), 'note' => $num ) );
	}
}
