<?php
/**
 * Menu administrácie: všetky doplnky Jupiter (slug začína „j-“) sú spolu hneď pod položkou
 * WooCommerce „Marketing“ – v pevnom poradí (sklad → objednávky → faktúry → e-maily), ďalšie doplnky podľa abecedy,
 * J-Pomocník vždy posledný.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Pomocník', 'J-Pomocník', 'manage_woocommerce', 'j-help', 'jhelp_render_admin', 'dashicons-sos', 58 );
	add_submenu_page( 'j-help', 'Prehľad', 'Prehľad', 'manage_woocommerce', 'j-help', 'jhelp_render_admin' );
	add_submenu_page( 'j-help', 'Návody', 'Návody', 'manage_woocommerce', 'j-help-guides', function () {
		jhelp_render_admin( 'guides' );
	} );
	add_submenu_page( 'j-help', 'Plán vývoja', 'Plán vývoja', 'manage_woocommerce', 'j-help-roadmap', function () {
		jhelp_render_admin( 'roadmap' );
	} );
} );

/** Poradie doplnkov Jupiter v menu (slug → poradie). Neuvedené doplnky idú za nimi podľa abecedy, J-Pomocník na koniec. */
function jhelp_menu_order() {
	return apply_filters( 'jupiter_menu_order', array(
		'j-sklad'    => 10,
		'j-orders'   => 20,
		'j-invoices' => 30,
		'j-emails'   => 40,
		'j-help'     => 1000,
	) );
}

/** Je položka menu doplnkom Jupiter? */
function jhelp_is_j_item( $item ) {
	$slug = isset( $item[2] ) ? (string) $item[2] : '';
	return 0 === strpos( $slug, 'j-' ) || 0 === strpos( $slug, 'j_' );
}

// Presun až po zostavení celého menu (keď už všetky doplnky pridali svoje položky).
add_action( 'admin_menu', function () {
	global $menu;
	if ( empty( $menu ) || ! is_array( $menu ) ) {
		return;
	}
	$anchor = null;
	$items  = array();
	foreach ( $menu as $k => $item ) {
		if ( isset( $item[2] ) && 'woocommerce-marketing' === $item[2] ) {
			$anchor = $k;
		} elseif ( jhelp_is_j_item( $item ) ) {
			$items[ $k ] = $item;
		}
	}
	if ( null === $anchor || ! $items ) {
		return;
	}
	$order = jhelp_menu_order();
	uasort( $items, function ( $a, $b ) use ( $order ) {
		$oa = $order[ $a[2] ] ?? 500; // neznámy doplnok: za známymi, pred J-Pomocníkom
		$ob = $order[ $b[2] ] ?? 500;
		if ( $oa !== $ob ) {
			return $oa <=> $ob;
		}
		return strcasecmp( wp_strip_all_tags( $a[0] ), wp_strip_all_tags( $b[0] ) );
	} );
	foreach ( array_keys( $items ) as $k ) {
		unset( $menu[ $k ] );
	}
	$pos = (float) $anchor;
	foreach ( $items as $item ) {
		$pos += 0.0001;
		while ( isset( $menu[ (string) $pos ] ) ) {
			$pos += 0.00001;
		}
		$menu[ (string) $pos ] = $item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}, PHP_INT_MAX );
