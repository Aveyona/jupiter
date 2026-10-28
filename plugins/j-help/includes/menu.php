<?php
/**
 * Menu administrácie: všetky doplnky Jupiter (slug začína „j-“) sú spolu hneď pod položkou
 * WooCommerce „Marketing“ – J-Help prvý, ostatné podľa abecedy.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( 'J-Help', 'J-Help', 'manage_woocommerce', 'j-help', 'jhelp_render_admin', 'dashicons-sos', 58 );
	add_submenu_page( 'j-help', 'J-Help – Prehľad', 'Prehľad', 'manage_woocommerce', 'j-help', 'jhelp_render_admin' );
} );

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
	uasort( $items, function ( $a, $b ) {
		if ( 'j-help' === $a[2] ) {
			return -1;
		}
		if ( 'j-help' === $b[2] ) {
			return 1;
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
