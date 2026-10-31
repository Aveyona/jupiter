<?php
/** Nástenka Jupiter: dlaždice doplnkov (pripnuté hore), neaktívne doplnky a odkazy. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_jju_pin', function () {
	if ( ! current_user_can( JJU_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jju_pin' );
	$k    = sanitize_key( wp_unslash( $_GET['app'] ?? '' ) ); // phpcs:ignore
	$pins = jju_pins();
	$pins = in_array( $k, $pins, true ) ? array_values( array_diff( $pins, array( $k ) ) ) : array_merge( $pins, array( $k ) );
	update_user_meta( get_current_user_id(), 'jju_pins', $pins );
	wp_safe_redirect( admin_url( 'admin.php?page=' . JJU_SLUG ) );
	exit;
} );

/** Jedna dlaždica doplnku. */
function jju_tile( $slug, $it, $color, $pinned ) {
	$badge = jju_badge( $it['raw'] );
	$p     = jju_plugin_for( $slug );
	$ver   = $p ? jju_headers()[ $p ]['version'] : '';
	$pin   = wp_nonce_url( admin_url( 'admin-post.php?action=jju_pin&app=' . rawurlencode( $slug ) ), 'jju_pin' );
	$h     = '<div class="jju-tile' . ( $pinned ? ' is-pinned' : '' ) . '" style="--app:' . esc_attr( $color ) . '">'
		. '<a class="jju-pin" href="' . esc_url( $pin ) . '" title="' . ( $pinned ? 'Odopnúť' : 'Pripnúť – zobrazí sa vždy hore' ) . '" aria-label="' . esc_attr( ( $pinned ? 'Odopnúť ' : 'Pripnúť ' ) . $it['title'] ) . '"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span></a>'
		. '<a class="jju-main" href="' . esc_url( jju_page_url( $slug ) ) . '">' . jju_icon( $it['icon'] )
		. '<strong>' . esc_html( jju_short( $it['title'] ) ) . '</strong>' . ( $badge ? '<i class="jju-badge" title="Čaká na vybavenie">' . (int) $badge . '</i>' : '' )
		. '<span class="jju-desc">' . esc_html( jju_desc( $slug ) ) . '</span>'
		. ( $ver ? '<span class="jju-ver">Verzia ' . esc_html( $ver ) . '</span>' : '' ) . '</a>';
	$subs = array_values( array_filter( $it['subs'], function ( $s ) use ( $slug ) {
		return $s['slug'] !== $slug;
	} ) );
	if ( $subs ) {
		$h .= '<div class="jju-subs">';
		foreach ( array_slice( $subs, 0, 8 ) as $s ) {
			$h .= '<a href="' . esc_url( jju_page_url( $s['slug'] ) ) . '">' . esc_html( $s['title'] ) . '</a>';
		}
		$h .= '</div>';
	}
	return $h . '</div>';
}

function jju_render_dashboard() {
	global $jju_items;
	if ( ! current_user_can( JJU_CAP ) ) {
		return;
	}
	$items  = (array) $jju_items;
	$colors = jju_colors( array_keys( $items ) );
	$pins   = jju_pins();
	echo '<div class="wrap jju"><div class="jju-head"><img src="' . esc_attr( jju_menu_icon() ) . '" alt="" width="40" height="40" class="jju-logo"><div><h1>Jupiter</h1><p>Doplnky WooCommerce pre slovenský e-shop – sklad, objednávky, doprava, platby, faktúry a účtovníctvo na jednom mieste.</p></div>'
		. '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=j-jupiter-settings' ) ) . '"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> Nastavenia</a></div><hr class="wp-header-end">';
	if ( ! $items ) {
		echo '<p>Žiadny doplnok Jupiter nie je aktívny.</p></div>';
		return;
	}
	$pinned = array();
	foreach ( $pins as $k ) {
		if ( isset( $items[ $k ] ) ) {
			$pinned[ $k ] = $items[ $k ];
		}
	}
	if ( $pinned ) {
		echo '<h2 class="jju-h"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span> Pripnuté</h2><div class="jju-grid">';
		foreach ( $pinned as $slug => $it ) {
			echo jju_tile( $slug, $it, $colors[ $slug ] ?? '#2271b1', true ); // phpcs:ignore
		}
		echo '</div><h2 class="jju-h">Všetky doplnky</h2>';
	}
	echo '<div class="jju-grid">';
	foreach ( $items as $slug => $it ) {
		if ( ! isset( $pinned[ $slug ] ) ) {
			echo jju_tile( $slug, $it, $colors[ $slug ] ?? '#2271b1', false ); // phpcs:ignore
		}
	}
	echo '</div>';
	// Nainštalované, ale vypnuté doplnky Jupiter.
	$off = array_filter( jju_headers(), function ( $h, $dir ) {
		return ! $h['active'] && JJU_SLUG !== $dir;
	}, ARRAY_FILTER_USE_BOTH );
	if ( $off && current_user_can( 'activate_plugins' ) ) {
		echo '<h2 class="jju-h">Vypnuté doplnky</h2><div class="jju-off">';
		foreach ( $off as $dir => $h ) {
			echo '<div><strong>' . esc_html( $h['name'] ) . '</strong> <span>' . esc_html( $h['version'] ) . '</span><p>' . esc_html( wp_html_excerpt( $h['desc'], 140, '…' ) ) . '</p></div>';
		}
		echo '</div><p><a class="button" href="' . esc_url( admin_url( 'plugins.php?s=' . rawurlencode( 'Jupiter' ) ) ) . '">Zapnúť v Pluginoch</a></p>';
	}
	echo '<p class="jju-foot"><a href="https://github.com/Aveyona/jupiter" target="_blank" rel="noopener">Jupiter na GitHube</a>' . ( isset( $items['j-help'] ) ? ' · <a href="' . esc_url( admin_url( 'admin.php?page=j-help' ) ) . '">Návody a aktualizácie (J-Pomocník)</a>' : '' ) . '</p></div>';
}
