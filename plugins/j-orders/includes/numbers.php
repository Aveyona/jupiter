<?php
/**
 * Čísla objednávok: prefix + poradové číslo s pevným počtom číslic + prípona (napr. 22026 + 0244 → 220260244).
 *
 * Číslo sa ukladá do meta `_wcson_order_number` – rovnako ako doplnky My Order Numbers a „Woo Custom and Sequential
 * Order Number“, takže existujúce objednávky si čísla ponechajú. Pri aktivácii sa prevezme nastavenie aj počítadlo
 * z My Order Numbers (alebo z pôvodného doplnku). Kým beží niektorý z nich, čísla prideľuje on a J-Objednávky ich len
 * zobrazuje – dve počítadlá nikdy nebežia naraz. Funguje s klasickým ukladaním objednávok aj s HPOS.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JORD_META    = '_wcson_order_number';
const JORD_OPTION  = 'jord_numbers';
const JORD_HISTORY = 'jord_numbers_history';
const JORD_LOCK    = 'jord_order_number_lock';

function jord_hpos() {
	return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

/** Tabuľka a stĺpec s meta údajmi objednávok. */
function jord_meta_table() {
	global $wpdb;
	return jord_hpos() ? array( $wpdb->prefix . 'wc_orders_meta', 'order_id' ) : array( $wpdb->postmeta, 'post_id' );
}

/** Beží iný doplnok na čísla objednávok? Vráti jeho názov alebo ''. */
function jord_other_numbering() {
	if ( class_exists( 'My_Order_Numbers' ) ) {
		return 'My Order Numbers';
	}
	if ( class_exists( 'WCSON_ORDER_NUMBER' ) ) {
		return 'Woo Custom and Sequential Order Number';
	}
	return '';
}

function jord_numbers_defaults() {
	return array(
		'enabled' => 0,
		'prefix'  => '',
		'suffix'  => '',
		'digits'  => 4,
		'last'    => 0,
	);
}

/** @param bool $fresh čítať priamo z databázy (pod zámkom, mimo object cache) */
function jord_numbers_settings( $fresh = false ) {
	if ( $fresh ) {
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", JORD_OPTION ) );
		$s   = null === $raw ? array() : maybe_unserialize( $raw );
	} else {
		$s = get_option( JORD_OPTION, array() );
	}
	return wp_parse_args( is_array( $s ) ? $s : array(), jord_numbers_defaults() );
}

/** Číslovanie J-Objednávky prideľuje čísla (zapnuté a nebeží iný doplnok). */
function jord_numbers_active() {
	$s = jord_numbers_settings();
	return ! empty( $s['enabled'] ) && '' === jord_other_numbering();
}

function jord_format( $seq, $s ) {
	return $s['prefix'] . str_pad( (string) absint( $seq ), (int) $s['digits'], '0', STR_PAD_LEFT ) . $s['suffix'];
}

/**
 * Prvá aktivácia: prevziať nastavenie z My Order Numbers (mon_settings + história), inak z pôvodného doplnku
 * (vzor „22026{number_start_from:0001}“). Počítadlo nikdy nezaostane za najvyšším uloženým číslom.
 */
function jord_numbers_activate() {
	if ( false !== get_option( JORD_OPTION, false ) ) {
		return;
	}
	$s    = jord_numbers_defaults();
	$from = '';
	$mon  = get_option( 'mon_settings', false );
	if ( is_array( $mon ) ) {
		foreach ( array( 'prefix', 'suffix', 'digits', 'last' ) as $k ) {
			if ( isset( $mon[ $k ] ) ) {
				$s[ $k ] = $mon[ $k ];
			}
		}
		$s['enabled'] = 1;
		$from         = 'My Order Numbers';
		$hist         = get_option( 'mon_history', array() );
		if ( is_array( $hist ) && $hist ) {
			add_option( JORD_HISTORY, array_slice( $hist, -50 ), '', false );
		}
	} else {
		$legacy = maybe_unserialize( get_option( 'wcson_order_number_settings', '' ) );
		if ( is_array( $legacy ) && ! empty( $legacy['wcson-order-number'] ) ) {
			$pattern = preg_replace( '/\s+/', '', $legacy['wcson-order-number'] );
			if ( preg_match( '/^(.*?)\{number_start_from:(\d+)\}(.*)$/i', $pattern, $m ) ) {
				$s['prefix'] = $m[1];
				$s['digits'] = max( 1, strlen( $m[2] ) );
				$s['suffix'] = $m[3];
			}
			$s['last']    = isset( $legacy['currentNumber'] ) ? absint( $legacy['currentNumber'] ) : 0;
			$s['enabled'] = 1;
			$from         = 'Woo Custom and Sequential Order Number';
		}
	}
	if ( $s['enabled'] ) {
		$s['last'] = max( absint( $s['last'] ), jord_highest_used( $s ) );
	}
	add_option( JORD_OPTION, $s, '', false );
	if ( $from ) {
		jord_numbers_log( 'Prevzaté z doplnku ' . $from, null, $s );
	}
}

/** Najvyššie poradové číslo už uložené pri objednávke pre daný prefix a príponu. */
function jord_highest_used( $s ) {
	global $wpdb;
	list( $t, $col ) = jord_meta_table();
	$like   = $wpdb->esc_like( $s['prefix'] ) . '%' . $wpdb->esc_like( $s['suffix'] );
	$values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $t WHERE meta_key = %s AND meta_value LIKE %s", JORD_META, $like ) ); // phpcs:ignore
	$max    = 0;
	$re     = '/^' . preg_quote( $s['prefix'], '/' ) . '(\d{' . (int) $s['digits'] . ',})' . preg_quote( $s['suffix'], '/' ) . '$/';
	foreach ( $values as $v ) {
		if ( preg_match( $re, $v, $m ) ) {
			$max = max( $max, absint( $m[1] ) );
		}
	}
	return $max;
}

function jord_number_exists( $number ) {
	global $wpdb;
	list( $t, $col ) = jord_meta_table();
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT $col FROM $t WHERE meta_key = %s AND meta_value = %s LIMIT 1", JORD_META, $number ) ); // phpcs:ignore
}

/** Uložené číslo objednávky (aj pre objekt načítaný pred pridelením čísla). */
function jord_stored_number( WC_Order $order ) {
	$n = '';
	if ( ! jord_hpos() ) {
		$n = trim( (string) get_post_meta( $order->get_id(), JORD_META, true ) );
	}
	if ( '' === $n ) {
		$n = trim( (string) $order->get_meta( JORD_META, true ) );
	}
	return $n;
}

/**
 * Posledné poradové číslo. Pred prvým pridelením po prechode z My Order Numbers sa nadviaže na jeho počítadlo
 * (ak bežal po aktivácii J-Objednávky ďalej) – len pri rovnakom formáte čísla. $consume = poznačiť, že prechod prebehol.
 */
function jord_effective_last( $s, $consume = false ) {
	$last = absint( $s['last'] );
	if ( ! get_option( 'jord_mon_synced' ) ) {
		$mon = get_option( 'mon_settings', false );
		if ( is_array( $mon ) && isset( $mon['last'], $mon['prefix'], $mon['digits'] ) && (string) $mon['prefix'] === (string) $s['prefix'] && (int) $mon['digits'] === (int) $s['digits'] && (string) ( $mon['suffix'] ?? '' ) === (string) $s['suffix'] ) {
			$last = max( $last, absint( $mon['last'] ) );
		}
		if ( $consume ) {
			update_option( 'jord_mon_synced', 1, false );
		}
	}
	return $last;
}

/** Pridelí objednávke ďalšie číslo (ak ešte žiadne nemá). */
function jord_assign( $order_or_id ) {
	if ( ! jord_numbers_active() ) {
		return;
	}
	$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( absint( $order_or_id ) );
	if ( ! $order || 'shop_order' !== $order->get_type() || '' !== jord_stored_number( $order ) ) {
		return;
	}
	global $wpdb;
	$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', JORD_LOCK ) );

	$s = jord_numbers_settings( true );
	$s['last'] = jord_effective_last( $s, true );
	$next = absint( $s['last'] ) + 1;
	while ( jord_number_exists( jord_format( $next, $s ) ) ) {
		$next++;
	}
	$number    = jord_format( $next, $s );
	$s['last'] = $next;
	update_option( JORD_OPTION, $s, false );

	if ( jord_hpos() ) {
		$order->update_meta_data( JORD_META, $number );
		$order->save_meta_data();
	} else {
		// Priamo do post meta – objekt objednávky z pokladne o tomto údaji nevie a nemôže ho prepísať.
		update_post_meta( $order->get_id(), JORD_META, $number );
	}
	if ( $locked ) {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', JORD_LOCK ) );
	}
}

add_action( 'init', function () {
	if ( ! jord_numbers_active() ) {
		return;
	}
	foreach ( array( 'woocommerce_checkout_update_order_meta', 'woocommerce_store_api_checkout_update_order_meta', 'woocommerce_before_resend_order_emails', 'woocommerce_api_create_order', 'woocommerce_deposits_create_order' ) as $hook ) {
		add_action( $hook, 'jord_assign', 10, 1 );
	}
	add_action( 'woocommerce_process_shop_order_meta', function ( $id, $post = null ) {
		if ( $post instanceof WP_Post && 'auto-draft' === $post->post_status ) {
			return;
		}
		jord_assign( $id );
	}, 35, 2 );
} );

/** Zobrazenie čísla (len ak to nerobí iný doplnok). */
add_filter( 'woocommerce_order_number', function ( $number, $order ) {
	if ( '' !== jord_other_numbering() || ! $order instanceof WC_Order ) {
		return $number;
	}
	$custom = jord_stored_number( $order );
	return '' !== $custom ? $custom : $number;
}, 10, 2 );

/** ID objednávky podľa čísla (aj čisté ID pre objednávky bez vlastného čísla). */
function jord_find_order( $number ) {
	global $wpdb;
	list( $t, $col ) = jord_meta_table();
	$number = wc_clean( (string) $number );
	$id     = $wpdb->get_var( $wpdb->prepare( "SELECT $col FROM $t WHERE meta_key = %s AND meta_value = %s LIMIT 1", JORD_META, $number ) ); // phpcs:ignore
	if ( $id ) {
		return absint( $id );
	}
	if ( ctype_digit( $number ) ) {
		$o = wc_get_order( absint( $number ) );
		if ( $o && 'shop_order' === $o->get_type() && '' === jord_stored_number( $o ) ) {
			return $o->get_id();
		}
	}
	return 0;
}

add_filter( 'woocommerce_shortcode_order_tracking_order_id', function ( $id ) {
	return '' === jord_other_numbering() ? jord_find_order( $id ) : $id;
} );

add_filter( 'woocommerce_shop_order_search_fields', 'jord_search_fields' );
add_filter( 'woocommerce_order_table_search_query_meta_keys', 'jord_search_fields' );
function jord_search_fields( $fields ) {
	if ( ! in_array( JORD_META, (array) $fields, true ) ) {
		$fields[] = JORD_META;
	}
	return $fields;
}

function jord_numbers_log( $label, $old, $new ) {
	$u    = wp_get_current_user();
	$desc = function ( $s ) {
		$t = 'prefix „' . $s['prefix'] . '“, číslic ' . $s['digits'] . ', posledné ' . $s['last'];
		if ( '' !== (string) $s['suffix'] ) {
			$t .= ', prípona „' . $s['suffix'] . '“';
		}
		return $t . ( empty( $s['enabled'] ) ? ' (vypnuté)' : '' );
	};
	$hist   = (array) get_option( JORD_HISTORY, array() );
	$hist[] = array(
		'time' => time(),
		'user' => $u && $u->exists() ? $u->user_login : 'systém',
		'text' => $label . ': ' . ( $old ? $desc( $old ) . ' → ' : '' ) . $desc( $new ),
	);
	update_option( JORD_HISTORY, array_slice( $hist, -50 ), false );
}
