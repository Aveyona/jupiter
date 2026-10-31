<?php
/**
 * Kódy kupónov: generovanie, vytvorenie kupónu WooCommerce, stav, objednávky a štatistiky.
 * Kupóny sú bežné kupóny WooCommerce (shop_coupon); J-Kupóny k nim pridávajú meta _jkp, sériu (_jkp_batch, _jkp_batch_name) a poznámku.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Nastavenia J-Kupónov. */
function jkp_settings() {
	return wp_parse_args( (array) get_option( 'jkp_settings', array() ), array(
		'len'        => 4,  // dĺžka jednotlivého kódu
		'len_batch'  => 6,  // dĺžka kódov pri hromadnom vytvorení
		'prefix'     => '', // predpona, napr. LETO-
		'free_ship'  => 1,  // kupón s dopravou zadarmo vynuluje cenu každého spôsobu dopravy
		'warn_days'  => 7,  // upozornenie na koniec platnosti (dni)
	) );
}

/** Dĺžka generovaného kódu (bez predpony). */
function jkp_code_length( $batch = false ) {
	$s = jkp_settings();
	return max( 3, min( 20, (int) apply_filters( 'jkp_code_length', (int) ( $batch ? $s['len_batch'] : $s['len'] ), $batch ) ) );
}

/** Znaky kódu – veľké písmená a číslice bez zameniteľných (0/O, 1/I/L). */
function jkp_code_alphabet() {
	return (string) apply_filters( 'jkp_code_alphabet', 'ABCDEFGHJKMNPQRSTUVWXYZ23456789' );
}

/** Predpona kódu (len A–Z, 0–9, pomlčka, podčiarkovník). */
function jkp_code_prefix( $prefix = null ) {
	$p = null === $prefix ? (string) jkp_settings()['prefix'] : (string) $prefix;
	return substr( preg_replace( '/[^A-Z0-9_-]/', '', strtoupper( $p ) ), 0, 15 );
}

/** Existuje už kupón s týmto kódom (aj v koši)? */
function jkp_code_exists( $code ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_title = %s LIMIT 1", wc_format_coupon_code( $code ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Vygeneruje nový jedinečný kód. Prázdny reťazec, ak sa voľný kód nenájde. */
function jkp_generate_code( $exclude = array(), $batch = false, $prefix = null ) {
	$abc = jkp_code_alphabet();
	$len = jkp_code_length( $batch );
	$pre = jkp_code_prefix( $prefix );
	$max = strlen( $abc ) - 1;
	for ( $try = 0; $try < 200; $try++ ) {
		$code = $pre;
		for ( $i = 0; $i < $len; $i++ ) {
			$code .= $abc[ random_int( 0, $max ) ];
		}
		if ( ! in_array( $code, $exclude, true ) && ! jkp_code_exists( $code ) ) {
			return $code;
		}
	}
	return '';
}

/** Desatinné číslo z formulára (čiarka aj bodka, medzery). */
function jkp_num( $v ) {
	return (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) $v );
}

/** Zoznam e-mailov z textu. Vráti pole adries alebo WP_Error s neplatnými. */
function jkp_parse_emails( $text ) {
	$out = array();
	$bad = array();
	foreach ( preg_split( '/[\s,;]+/', trim( (string) $text ) ) as $e ) {
		if ( '' === $e ) {
			continue;
		}
		if ( is_email( $e ) ) {
			$out[] = strtolower( $e );
		} else {
			$bad[] = $e;
		}
	}
	return $bad ? new WP_Error( 'jkp_email', 'Neplatný e-mail: ' . implode( ', ', $bad ) . '. Opravte ho – inak by kupón platil pre všetkých.' ) : array_values( array_unique( $out ) );
}

/**
 * Overí podmienky kupónu (spoločné pre jeden aj hromadné vytvorenie). Vráti WP_Error alebo upravené $a.
 */
function jkp_validate( array $a ) {
	$type   = in_array( $a['type'] ?? '', array( 'percent', 'fixed_cart' ), true ) ? $a['type'] : 'percent';
	$amount = jkp_num( $a['amount'] ?? 0 );
	$free   = ! empty( $a['free_shipping'] );
	if ( $amount < 0 || ( 'percent' === $type && $amount > 100 ) ) {
		return new WP_Error( 'jkp_amount', 'Zľava v percentách musí byť od 0 do 100.' );
	}
	if ( $amount <= 0 && ! $free ) {
		return new WP_Error( 'jkp_amount', 'Zadajte zľavu väčšiu ako 0, alebo zaškrtnite dopravu zadarmo (kupón len na dopravu).' );
	}
	$exp = (string) ( $a['expires'] ?? '' );
	if ( '' !== $exp ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $exp ) ) {
			return new WP_Error( 'jkp_exp', 'Neplatný dátum platnosti.' );
		}
		if ( $exp < current_time( 'Y-m-d' ) ) {
			return new WP_Error( 'jkp_exp', 'Dátum platnosti je v minulosti – kupón by bol hneď neplatný.' );
		}
	}
	$emails = jkp_parse_emails( $a['email'] ?? '' );
	if ( is_wp_error( $emails ) ) {
		return $emails;
	}
	$a['type']    = $type;
	$a['amount']  = $amount;
	$a['emails']  = $emails;
	$a['min']     = max( 0, jkp_num( $a['min'] ?? 0 ) );
	$a['cats']    = array_values( array_filter( array_map( 'absint', (array) ( $a['cats'] ?? array() ) ) ) );
	$a['products'] = array_values( array_filter( array_map( 'absint', (array) ( $a['products'] ?? array() ) ) ) );
	return $a;
}

/**
 * Vytvorí kupón WooCommerce.
 *
 * @param array $a code, type (percent|fixed_cart), amount, expires (Y-m-d), limit, per_user, min, free_shipping, email, note,
 *                 combine (dá sa kombinovať s inými kupónmi), cats, products, batch, batch_name, from (ID pôvodného kupónu).
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
	if ( ! isset( $a['emails'] ) ) {
		$a = jkp_validate( $a );
		if ( is_wp_error( $a ) ) {
			return $a;
		}
	}
	$c = new WC_Coupon();
	$c->set_code( $code );
	$c->set_discount_type( $a['type'] );
	$c->set_amount( $a['amount'] );
	$c->set_individual_use( empty( $a['combine'] ) );
	if ( ! empty( $a['expires'] ) ) {
		$c->set_date_expires( jkp_expiry_ts( $a['expires'] ) );
	}
	$c->set_usage_limit( max( 0, (int) ( $a['limit'] ?? 0 ) ) );
	$c->set_usage_limit_per_user( max( 0, (int) ( $a['per_user'] ?? 0 ) ) );
	if ( $a['min'] > 0 ) {
		$c->set_minimum_amount( $a['min'] );
	}
	$c->set_free_shipping( ! empty( $a['free_shipping'] ) );
	if ( $a['emails'] ) {
		$c->set_email_restrictions( $a['emails'] );
	}
	if ( $a['cats'] ) {
		$c->set_product_categories( $a['cats'] );
	}
	if ( $a['products'] ) {
		$c->set_product_ids( $a['products'] );
	}
	$c->set_description( sanitize_textarea_field( (string) ( $a['note'] ?? '' ) ) );
	$c->update_meta_data( '_jkp', 1 );
	if ( ! empty( $a['batch'] ) ) {
		$c->update_meta_data( '_jkp_batch', sanitize_key( $a['batch'] ) );
		$c->update_meta_data( '_jkp_batch_name', sanitize_text_field( (string) ( $a['batch_name'] ?? '' ) ) );
	}
	if ( ! empty( $a['from'] ) ) {
		$c->update_meta_data( '_jkp_from', (int) $a['from'] );
	}
	$c->update_meta_data( '_jkp_by', get_current_user_id() );
	$id = $c->save();
	return $id ? $id : new WP_Error( 'jkp_save', 'Kupón sa nepodarilo uložiť.' );
}

/** Koniec platnosti „do dňa vrátane“: 23:59:59 miestneho času. */
function jkp_expiry_ts( $ymd ) {
	return ( new DateTimeImmutable( $ymd . ' 23:59:59', wp_timezone() ) )->getTimestamp();
}

/** Podmienky existujúceho kupónu v tvare formulára (duplikovanie). */
function jkp_coupon_args( WC_Coupon $c ) {
	$exp = $c->get_date_expires();
	return array(
		'type'          => $c->get_discount_type(),
		'amount'        => wc_format_localized_decimal( (string) (float) $c->get_amount() ),
		'free_shipping' => $c->get_free_shipping() ? '1' : '',
		'expires'       => $exp && $exp->getTimestamp() > time() ? $exp->date_i18n( 'Y-m-d' ) : '',
		'limit'         => (string) (int) $c->get_usage_limit(),
		'per_user'      => (string) (int) $c->get_usage_limit_per_user(),
		'min'           => (float) $c->get_minimum_amount() > 0 ? wc_format_localized_decimal( (string) (float) $c->get_minimum_amount() ) : '',
		'email'         => implode( ', ', $c->get_email_restrictions() ),
		'note'          => $c->get_description(),
		'combine'       => $c->get_individual_use() ? '' : '1',
		'cats'          => $c->get_product_categories(),
		'products'      => $c->get_product_ids(),
		'from'          => $c->get_id(),
	);
}

/** Stav podľa surových údajov: active | off | expired | used. */
function jkp_status_raw( $post_status, $exp_ts, $limit, $count ) {
	if ( 'publish' !== $post_status ) {
		return 'off';
	}
	if ( $exp_ts && (int) $exp_ts < time() ) {
		return 'expired';
	}
	if ( (int) $limit && (int) $count >= (int) $limit ) {
		return 'used';
	}
	return 'active';
}

/** Stav kupónu: active | off | expired | used. */
function jkp_status( WC_Coupon $c ) {
	$exp = $c->get_date_expires();
	return jkp_status_raw( get_post_status( $c->get_id() ), $exp ? $exp->getTimestamp() : 0, $c->get_usage_limit(), $c->get_usage_count() );
}

function jkp_status_label( $s ) {
	$l = array(
		'active'  => array( 'Platný', '#e6f4ea', '#1e6b34' ),
		'off'     => array( 'Vypnutý', '#f0f0f1', '#50575e' ),
		'expired' => array( 'Po platnosti', '#fcf0e3', '#8a4b00' ),
		'used'    => array( 'Vyčerpaný', '#e8eefc', '#1d4ed8' ),
	);
	$x = $l[ $s ] ?? $l['off'];
	return '<span class="jkp-st" style="background:' . $x[1] . ';color:' . $x[2] . '">' . esc_html( $x[0] ) . '</span>';
}

/** Text zľavy: „10 %“, „5,00 €“, „doprava zadarmo“ alebo kombinácia. */
function jkp_discount_text( WC_Coupon $c ) {
	$amt = (float) $c->get_amount();
	$t   = '';
	if ( $amt > 0 ) {
		$t = 'percent' === $c->get_discount_type()
			? wc_format_localized_decimal( rtrim( rtrim( number_format( $amt, 2, '.', '' ), '0' ), '.' ) ) . ' %'
			: wp_strip_all_tags( wc_price( $amt ) );
	}
	if ( $c->get_free_shipping() ) {
		$t .= ( $t ? ' + ' : '' ) . 'doprava zadarmo';
	}
	return $t ? $t : '–';
}

/**
 * Prehľad všetkých kupónov jedným dopytom (bez načítania objektov): [ id => [ id, code, status, exp, limit, count, batch, batch_name, note, jkp, date ] ].
 */
function jkp_index() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT p.ID, p.post_title, p.post_status, p.post_excerpt, p.post_date,
		MAX(CASE WHEN m.meta_key = 'date_expires' THEN m.meta_value END) AS exp,
		MAX(CASE WHEN m.meta_key = 'usage_limit' THEN m.meta_value END) AS lim,
		MAX(CASE WHEN m.meta_key = 'usage_count' THEN m.meta_value END) AS cnt,
		MAX(CASE WHEN m.meta_key = '_jkp_batch' THEN m.meta_value END) AS batch,
		MAX(CASE WHEN m.meta_key = '_jkp_batch_name' THEN m.meta_value END) AS batch_name,
		MAX(CASE WHEN m.meta_key = '_jkp' THEN m.meta_value END) AS jkp
		FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('date_expires','usage_limit','usage_count','_jkp_batch','_jkp_batch_name','_jkp')
		WHERE p.post_type = 'shop_coupon' AND p.post_status IN ('publish','draft','pending','private','future')
		GROUP BY p.ID ORDER BY p.post_date DESC, p.ID DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[ (int) $r['ID'] ] = array(
			'id'         => (int) $r['ID'],
			'code'       => strtoupper( $r['post_title'] ),
			'status'     => jkp_status_raw( $r['post_status'], (int) $r['exp'], (int) $r['lim'], (int) $r['cnt'] ),
			'exp'        => (int) $r['exp'],
			'limit'      => (int) $r['lim'],
			'count'      => (int) $r['cnt'],
			'batch'      => sanitize_key( (string) $r['batch'] ),
			'batch_name' => (string) ( $r['batch_name'] ? $r['batch_name'] : ( $r['batch'] ? 'Séria ' . $r['batch'] : '' ) ),
			'note'       => (string) $r['post_excerpt'],
			'jkp'        => (bool) $r['jkp'],
			'date'       => $r['post_date'],
		);
	}
	return $out;
}

/** Série hromadných kupónov: [ batch => [ name, total, active, used, date ] ], najnovšie prvé. */
function jkp_batches( array $index = null ) {
	$index = null === $index ? jkp_index() : $index;
	$out   = array();
	foreach ( $index as $r ) {
		if ( '' === $r['batch'] ) {
			continue;
		}
		$b = &$out[ $r['batch'] ];
		if ( ! $b ) {
			$b = array( 'name' => $r['batch_name'], 'total' => 0, 'active' => 0, 'used' => 0, 'date' => $r['date'] );
		}
		++$b['total'];
		if ( 'active' === $r['status'] ) {
			++$b['active'];
		}
		if ( $r['count'] > 0 ) {
			++$b['used'];
		}
		unset( $b );
	}
	return $out;
}

/** Stavy objednávok, ktoré sa nerátajú do tržieb a zliav. */
function jkp_void_statuses() {
	return array( 'cancelled', 'failed', 'refunded', 'trash', 'checkout-draft' );
}

/**
 * Použitia kódu v objednávkach (najnovšie prvé): [ [ order, discount (s DPH), void (zrušená) ], … ].
 */
function jkp_coupon_uses( $code, $limit = 200 ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT i.order_id, i.order_item_id,
			(SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = i.order_item_id AND meta_key = 'discount_amount' LIMIT 1) AS d,
			(SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = i.order_item_id AND meta_key = 'discount_amount_tax' LIMIT 1) AS t
		FROM {$wpdb->prefix}woocommerce_order_items i WHERE i.order_item_type = 'coupon' AND i.order_item_name = %s ORDER BY i.order_id DESC LIMIT %d",
		wc_format_coupon_code( $code ),
		$limit
	), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$out = array();
	foreach ( (array) $rows as $r ) {
		$o = wc_get_order( (int) $r['order_id'] );
		if ( ! $o || 'shop_order_refund' === $o->get_type() ) {
			continue;
		}
		$out[] = array(
			'order'    => $o,
			'discount' => round( (float) $r['d'] + (float) $r['t'], 2 ),
			'void'     => in_array( $o->get_status(), jkp_void_statuses(), true ),
		);
	}
	return $out;
}

/** Objednávky, v ktorých bol kód použitý (najnovšie prvé). */
function jkp_coupon_orders( $code, $limit = 20 ) {
	return wp_list_pluck( jkp_coupon_uses( $code, $limit ), 'order' );
}

/**
 * Štatistika za obdobie od $from (Y-m-d): objednávky s kupónom, poskytnutá zľava, tržby a najpoužívanejšie kódy.
 */
function jkp_stats( $from, $to = '' ) {
	global $wpdb;
	$args = array(
		'limit'        => -1,
		'return'       => 'ids',
		'type'         => 'shop_order',
		'status'       => array_diff( array_keys( wc_get_order_statuses() ), array_map( fn( $s ) => 'wc-' . $s, jkp_void_statuses() ) ),
		'date_created' => $to ? strtotime( $from . ' 00:00:00' ) . '...' . strtotime( $to . ' 23:59:59' ) : '>=' . strtotime( $from . ' 00:00:00' ),
	);
	$ids  = wc_get_orders( $args );
	$res  = array( 'orders' => 0, 'discount' => 0.0, 'revenue' => 0.0, 'codes' => array() );
	if ( ! $ids ) {
		return $res;
	}
	$in   = implode( ',', array_map( 'intval', $ids ) );
	$rows = $wpdb->get_results(
		"SELECT i.order_id, i.order_item_name AS code,
			(SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = i.order_item_id AND meta_key = 'discount_amount' LIMIT 1) AS d,
			(SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id = i.order_item_id AND meta_key = 'discount_amount_tax' LIMIT 1) AS t
		FROM {$wpdb->prefix}woocommerce_order_items i WHERE i.order_item_type = 'coupon' AND i.order_id IN ($in)", // phpcs:ignore
		ARRAY_A
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$orders = array();
	foreach ( (array) $rows as $r ) {
		$code = strtoupper( $r['code'] );
		$d    = (float) $r['d'] + (float) $r['t'];
		$res['discount'] += $d;
		$orders[ (int) $r['order_id'] ] = true;
		if ( ! isset( $res['codes'][ $code ] ) ) {
			$res['codes'][ $code ] = array( 'uses' => 0, 'discount' => 0.0 );
		}
		++$res['codes'][ $code ]['uses'];
		$res['codes'][ $code ]['discount'] += $d;
	}
	foreach ( array_keys( $orders ) as $oid ) {
		$o = wc_get_order( $oid );
		if ( $o ) {
			$res['revenue'] += (float) $o->get_total() - (float) $o->get_total_refunded();
		}
	}
	$res['orders'] = count( $orders );
	uasort( $res['codes'], fn( $a, $b ) => $b['uses'] <=> $a['uses'] ?: $b['discount'] <=> $a['discount'] );
	return $res;
}

/* ---------------------------------------------------------------- Doprava zadarmo pre všetky spôsoby dopravy */

/** Má košík uplatnený kupón s dopravou zadarmo? */
function jkp_cart_free_shipping() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}
	foreach ( WC()->cart->get_coupons() as $c ) {
		if ( $c instanceof WC_Coupon && $c->get_free_shipping() ) {
			return true;
		}
	}
	return false;
}

/*
 * WooCommerce uplatní „doprava zadarmo“ z kupónu len pri spôsobe „Doprava zadarmo“ s podmienkou kupónu. Packeta (J-Doprava),
 * pevná cena a iné spôsoby by dopravu účtovali ďalej – preto ich cenu pri takom kupóne vynulujeme (vypnuteľné v nastaveniach).
 */
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
	if ( empty( jkp_settings()['free_ship'] ) || ! jkp_cart_free_shipping() ) {
		return $rates;
	}
	foreach ( $rates as $rate ) {
		if ( ! $rate instanceof WC_Shipping_Rate || (float) $rate->get_cost() <= 0 ) {
			continue;
		}
		if ( ! apply_filters( 'jkp_free_shipping_rate', true, $rate, $package ) ) {
			continue;
		}
		$rate->set_cost( 0 );
		$rate->set_taxes( array_map( fn() => 0, (array) $rate->get_taxes() ) );
	}
	return $rates;
}, 100, 2 );

/* ---------------------------------------------------------------- Platnosť „do dňa vrátane“ aj po úprave vo WooCommerce */

// WooCommerce pri uložení kupónu nastaví koniec platnosti na 0:00 zvoleného dňa (platil by o deň kratšie, ako ukazuje evidencia).
add_action( 'woocommerce_coupon_options_save', function ( $post_id, $coupon ) {
	if ( ! $coupon instanceof WC_Coupon || ! $coupon->get_meta( '_jkp' ) ) {
		return;
	}
	$exp = $coupon->get_date_expires();
	if ( $exp && '00:00:00' === $exp->date_i18n( 'H:i:s' ) ) {
		$coupon->set_date_expires( jkp_expiry_ts( $exp->date_i18n( 'Y-m-d' ) ) );
		$coupon->save();
	}
}, 20, 2 );

/* ---------------------------------------------------------------- Nástenka Jupiter */

add_filter( 'jupiter_tile_status', function ( $st, $slug ) {
	if ( 'j-kupony' !== $slug ) {
		return $st;
	}
	$idx    = jkp_index();
	$active = 0;
	$soon   = 0;
	$lim    = time() + DAY_IN_SECONDS * max( 1, (int) jkp_settings()['warn_days'] );
	foreach ( $idx as $r ) {
		if ( 'active' === $r['status'] ) {
			++$active;
			if ( $r['exp'] && $r['exp'] < $lim ) {
				++$soon;
			}
		}
	}
	if ( $soon ) {
		return array( 'warn', sprintf( 'Platných %d, %d končí do %d dní', $active, $soon, (int) jkp_settings()['warn_days'] ) );
	}
	return array( 'ok', $active ? 'Platných kupónov: ' . $active : 'Žiadny platný kupón' );
}, 10, 2 );
