<?php
/**
 * Pomocné funkcie administrácie J-Platieb: upozornenia (nečitateľný kľúč, nefunkčný webhook), stav na nástenke Jupiter,
 * odkaz na platbu kartou z objednávky a export platieb do CSV.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stav webhooku: [ 'ok' | 'warn' | 'err', text ].
 * Chyba = posledná udalosť zo Stripe bola odmietnutá, alebo chýba tajomstvo webhooku pri zadanom kľúči.
 */
function jpl_webhook_state() {
	if ( ! jpl_ready() ) {
		return array( 'warn', 'Platba kartou nie je nastavená' );
	}
	if ( '' === jpl_webhook_secret() ) {
		return array( 'err', 'Chýba tajomstvo webhooku – platby sa nepotvrdia automaticky' );
	}
	$last = (array) get_option( 'jpl_webhook_last', array() );
	if ( ! $last ) {
		return array( 'warn', 'Zo Stripe zatiaľ neprišla žiadna udalosť' );
	}
	$when = mysql2date( 'j. n. H:i', (string) $last['at'] ); // uložené v miestnom čase (current_time)
	$fail = (int) get_option( 'jpl_webhook_fail_at', 0 );
	$ok   = (int) get_option( 'jpl_webhook_ok_at', 0 );
	// Chyba len ak posledná udalosť bola odmietnutá, je novšia ako posledná prijatá a nie staršia ako 7 dní.
	if ( empty( $last['ok'] ) && $fail > $ok && $fail > time() - 7 * DAY_IN_SECONDS ) {
		return array( 'err', 'Webhook odmietnutý ' . $when . ': ' . ( $last['msg'] ?? '' ) );
	}
	if ( empty( $last['ok'] ) ) {
		return array( 'warn', 'Posledná udalosť zo Stripe bola odmietnutá (' . $when . ')' );
	}
	return array( 'ok', 'Webhook funguje (' . $when . ')' . ( jpl_is_test() ? ' · testovací režim' : '' ) );
}

// Upozornenia v administrácii.
add_action( 'admin_notices', function () {
	if ( ! current_user_can( JPL_CAP ) ) {
		return;
	}
	$s   = jpl_settings();
	$url = admin_url( 'admin.php?page=j-platby-settings' );
	// Tajný kľúč je uložený, ale nedá sa prečítať (zmenili sa bezpečnostné kľúče WordPressu) → platba kartou zmizla z pokladne.
	if ( '' !== (string) $s[ 'sk_' . jpl_mode() ] && '' === jpl_secret() ) {
		echo '<div class="notice notice-error"><p><strong>J-Platby:</strong> tajný kľúč Stripe sa nedá prečítať (zmenili sa bezpečnostné kľúče WordPressu). Platba kartou sa preto zákazníkom v pokladni nezobrazuje. <a href="' . esc_url( $url ) . '">Zadajte kľúče znova</a>.</p></div>';
		return;
	}
	if ( '' !== (string) $s[ 'wh_' . jpl_mode() ] && '' === jpl_webhook_secret() ) {
		echo '<div class="notice notice-error"><p><strong>J-Platby:</strong> tajomstvo webhooku sa nedá prečítať (zmenili sa bezpečnostné kľúče WordPressu). Platby sa nepotvrdia automaticky. <a href="' . esc_url( $url ) . '">Zadajte ho znova</a>.</p></div>';
		return;
	}
	$st = jpl_webhook_state();
	if ( 'err' === $st[0] ) {
		echo '<div class="notice notice-error"><p><strong>J-Platby:</strong> ' . esc_html( $st[1] ) . '. Zaplatené objednávky zákazníkov, ktorí sa po platbe nevrátia na web, by ostali v stave Čaká na platbu. <a href="' . esc_url( $url ) . '">Skontrolujte webhook</a>.</p></div>';
	}
} );

// Stav na nástenke Jupiter.
add_filter( 'jupiter_tile_status', function ( $st, $slug ) {
	return 'j-platby' === $slug ? jpl_webhook_state() : $st;
}, 10, 2 );

/* ---------------------------------------------------------------- Odkaz na platbu kartou */

/** Dá sa k objednávke vytvoriť odkaz na platbu kartou? */
function jpl_can_link( WC_Order $o ) {
	return jpl_ready() && ! $o->is_paid() && $o->has_status( array( 'pending', 'on-hold', 'failed' ) ) && (float) $o->get_total() >= 0.5;
}

add_action( 'add_meta_boxes', function () {
	$screen = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	add_meta_box( 'jpl-link', 'Platba kartou (J-Platby)', 'jpl_link_box', $screen, 'side', 'default' );
} );

function jpl_link_box( $post_or_order ) {
	$o = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $o ) {
		return;
	}
	if ( $o->get_meta( '_jpl_pi' ) ) {
		$test = 'test' === $o->get_meta( '_jpl_mode' );
		echo '<p>Zaplatené kartou' . ( $o->get_meta( '_jpl_card' ) ? ': ' . esc_html( (string) $o->get_meta( '_jpl_card' ) ) : '' ) . ( $test ? ' <small style="color:#b26200">(test)</small>' : '' ) . '</p>';
		if ( '' !== (string) $o->get_meta( '_jpl_fee' ) ) {
			echo '<p>Poplatok Stripe: ' . wp_kses_post( wc_price( (float) $o->get_meta( '_jpl_fee' ) ) ) . '<br>Dostanete: ' . wp_kses_post( wc_price( (float) $o->get_meta( '_jpl_net' ) ) ) . '</p>';
		}
		echo '<p><a href="' . esc_url( jpl_dashboard_url( 'payments/' . $o->get_meta( '_jpl_pi' ), $test ? 'test' : 'live' ) ) . '" target="_blank" rel="noopener">Platba v Stripe</a></p>';
		return;
	}
	if ( ! jpl_can_link( $o ) ) {
		echo '<p class="description">Odkaz na platbu kartou sa dá vytvoriť pri nezaplatenej objednávke (Čaká na platbu, Pozastavené, Neúspešná) od 0,50 €' . ( jpl_ready() ? '' : ' – najprv nastavte kľúče Stripe' ) . '.</p>';
		return;
	}
	$link = (string) $o->get_meta( '_jpl_link' );
	$exp  = (int) $o->get_meta( '_jpl_session_exp' );
	if ( $link && $exp > time() ) {
		echo '<p>Odkaz na platbu kartou (platí do ' . esc_html( wp_date( 'j. n. H:i', $exp ) ) . '):</p>';
		echo '<p><input type="text" readonly value="' . esc_attr( $link ) . '" class="widefat" onclick="this.select()"></p>';
		echo '<p><button type="button" class="button" onclick="var i=this.parentNode.previousElementSibling.querySelector(\'input\');navigator.clipboard.writeText(i.value);this.textContent=\'Skopírované\';">Kopírovať odkaz</button></p>';
		echo '<p class="description">Pošlite ho zákazníkovi (e-mail, SMS). Po zaplatení sa objednávka sama nastaví na Spracováva sa.</p>';
		return;
	}
	echo '<p>Zákazník môže objednávku zaplatiť kartou, Apple Pay alebo Google Pay cez odkaz, ktorý mu pošlete.</p>';
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jpl_link&order=' . $o->get_id() ), 'jpl_link_' . $o->get_id() ) ) . '">Vytvoriť odkaz na platbu</a></p>';
}

add_action( 'admin_post_jpl_link', function () {
	$id = absint( $_GET['order'] ?? 0 ); // phpcs:ignore
	if ( ! current_user_can( JPL_CAP ) || ! check_admin_referer( 'jpl_link_' . $id ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$o = wc_get_order( $id );
	if ( ! $o || ! jpl_can_link( $o ) ) {
		wp_die( 'K tejto objednávke sa odkaz na platbu nedá vytvoriť.', 'J-Platby', array( 'back_link' => true ) );
	}
	$r = jpl_create_session( $o, true );
	if ( is_wp_error( $r ) ) {
		wp_die( esc_html( 'Odkaz sa nepodarilo vytvoriť: ' . $r->get_error_message() ), 'J-Platby', array( 'back_link' => true ) );
	}
	wp_safe_redirect( $o->get_edit_order_url() );
	exit;
} );

/* ---------------------------------------------------------------- Export platieb do CSV */

add_action( 'admin_post_jpl_csv', function () {
	if ( ! current_user_can( JPL_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jpl_csv' );
	$month  = preg_match( '/^\d{4}-\d{2}$/', (string) ( $_GET['m'] ?? '' ) ) ? $_GET['m'] : current_time( 'Y-m' ); // phpcs:ignore
	$from   = strtotime( $month . '-01 00:00:00' );
	$to     = strtotime( '+1 month', $from ) - 1;
	$orders = wc_get_orders( array( 'limit' => -1, 'payment_method' => 'jpl_stripe', 'date_created' => $from . '...' . $to, 'orderby' => 'date', 'order' => 'ASC', 'status' => array_keys( wc_get_order_statuses() ) ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="platby-kartou-' . $month . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM – Excel spozná UTF-8
	$num = fn( $v ) => '' === (string) $v ? '' : number_format( (float) $v, 2, ',', '' );
	fputcsv( $out, array( 'Objednávka', 'Dátum platby', 'Zákazník', 'Suma', 'Vrátené', 'Poplatok Stripe', 'Dostanete', 'Karta', 'Režim', 'Platba Stripe' ), ';' );
	foreach ( $orders as $o ) {
		$pi = (string) $o->get_meta( '_jpl_pi' );
		if ( ! $pi ) {
			continue;
		}
		$ref = (float) $o->get_total_refunded();
		$net = $o->get_meta( '_jpl_net' );
		fputcsv( $out, array(
			$o->get_order_number(),
			$o->get_date_paid() ? $o->get_date_paid()->date_i18n( 'j. n. Y' ) : '',
			$o->get_formatted_billing_full_name(),
			$num( $o->get_total() ),
			$ref > 0 ? $num( $ref ) : '',
			$num( $o->get_meta( '_jpl_fee' ) ),
			'' === (string) $net ? '' : $num( (float) $net - $ref ),
			(string) $o->get_meta( '_jpl_card' ),
			'test' === $o->get_meta( '_jpl_mode' ) ? 'test' : 'ostrý',
			$pi,
		), ';' );
	}
	fclose( $out );
	exit;
} );
