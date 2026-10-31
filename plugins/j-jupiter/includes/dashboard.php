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

/**
 * Stav doplnku na dlaždici (napr. „Webhook Stripe funguje“). Doplnok ho vráti filtrom jupiter_tile_status:
 * [ 'ok' | 'warn' | 'err', text ] pre svoj slug, alebo nič.
 */
function jju_status_html( $slug ) {
	$st = apply_filters( 'jupiter_tile_status', null, $slug );
	if ( ! is_array( $st ) || empty( $st[1] ) ) {
		return '';
	}
	$k = in_array( $st[0], array( 'ok', 'warn', 'err' ), true ) ? $st[0] : 'warn';
	return '<span class="jju-status jju-st-' . esc_attr( $k ) . '"><i aria-hidden="true"></i>' . esc_html( $st[1] ) . '</span>';
}

/** Dostupná aktualizácia doplnku: [ verzia, odkaz na aktualizáciu ] alebo null. */
function jju_update_for( $plugin_dir ) {
	$h = jju_headers()[ $plugin_dir ] ?? null;
	if ( ! $h ) {
		return null;
	}
	$t = get_site_transient( 'update_plugins' );
	$r = is_object( $t ) && isset( $t->response[ $h['file'] ] ) ? $t->response[ $h['file'] ] : null;
	if ( ! $r || empty( $r->new_version ) || version_compare( $r->new_version, $h['version'], '<=' ) ) {
		return null;
	}
	$url = current_user_can( 'update_plugins' ) ? wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $h['file'] ) ), 'upgrade-plugin_' . $h['file'] ) : '';
	return array( (string) $r->new_version, $url );
}

/** Jedna dlaždica doplnku. */
function jju_tile( $slug, $it, $color, $pinned ) {
	$badge = jju_badge( $it['raw'] );
	$p     = jju_plugin_for( $slug );
	$ver   = $p ? jju_headers()[ $p ]['version'] : '';
	$upd   = $p ? jju_update_for( $p ) : null;
	$doc   = function_exists( 'jhelp_doc_url' ) && $p ? jhelp_doc_url( $p ) : '';
	$pin   = wp_nonce_url( admin_url( 'admin-post.php?action=jju_pin&app=' . rawurlencode( $slug ) ), 'jju_pin' );
	$subs  = array_values( array_filter( $it['subs'], function ( $s ) use ( $slug ) {
		return $s['slug'] !== $slug;
	} ) );
	$find  = jju_short( $it['title'] ) . ' ' . jju_desc( $slug ) . ' ' . implode( ' ', wp_list_pluck( $subs, 'title' ) );
	$h     = '<div class="jju-tile' . ( $pinned ? ' is-pinned' : '' ) . '" style="--app:' . esc_attr( $color ) . '" data-find="' . esc_attr( remove_accents( mb_strtolower( $find ) ) ) . '">'
		. '<a class="jju-pin" href="' . esc_url( $pin ) . '" title="' . ( $pinned ? 'Odopnúť' : 'Pripnúť – zobrazí sa vždy hore' ) . '" aria-label="' . esc_attr( ( $pinned ? 'Odopnúť ' : 'Pripnúť ' ) . $it['title'] ) . '"><span class="dashicons dashicons-admin-post" aria-hidden="true"></span></a>'
		. ( $doc ? '<a class="jju-doc" href="' . esc_url( $doc ) . '" title="Príručka" aria-label="' . esc_attr( 'Príručka ' . $it['title'] ) . '"><span class="dashicons dashicons-book" aria-hidden="true"></span></a>' : '' )
		. '<a class="jju-main" href="' . esc_url( jju_page_url( $slug ) ) . '">' . jju_icon( $it['icon'] )
		. '<strong>' . esc_html( jju_short( $it['title'] ) ) . '</strong>' . ( $badge ? '<i class="jju-badge" title="Čaká na vybavenie">' . (int) $badge . '</i>' : '' )
		. '<span class="jju-desc">' . esc_html( jju_desc( $slug ) ) . '</span>'
		. jju_status_html( $slug )
		. ( $ver ? '<span class="jju-ver">Verzia ' . esc_html( $ver ) . '</span>' : '' ) . '</a>';
	if ( $upd ) {
		$h .= '<div class="jju-upd"><span class="dashicons dashicons-update" aria-hidden="true"></span> Dostupná verzia ' . esc_html( $upd[0] ) . ( $upd[1] ? ' <a class="button button-small" href="' . esc_url( $upd[1] ) . '">Aktualizovať</a>' : '' ) . '</div>';
	}
	if ( $subs ) {
		$link = function ( $s ) {
			return '<a href="' . esc_url( jju_page_url( $s['slug'] ) ) . '" data-find="' . esc_attr( remove_accents( mb_strtolower( $s['title'] ) ) ) . '">' . esc_html( $s['title'] ) . ( ! empty( $s['badge'] ) ? ' <b class="jju-sb">' . (int) $s['badge'] . '</b>' : '' ) . '</a>';
		};
		$h .= '<div class="jju-subs">' . implode( '', array_map( $link, array_slice( $subs, 0, 8 ) ) );
		if ( count( $subs ) > 8 ) {
			$h .= '<details class="jju-more"><summary>+ ďalšie (' . ( count( $subs ) - 8 ) . ')</summary>' . implode( '', array_map( $link, array_slice( $subs, 8 ) ) ) . '</details>';
		}
		$h .= '</div>';
	}
	return $h . '</div>';
}

/** Súhrn „Čaká na vás“: počítadlá doplnkov a ich podstránok. */
function jju_render_waiting( array $items ) {
	$rows = array();
	foreach ( $items as $slug => $it ) {
		$any = false;
		foreach ( $it['subs'] as $s ) {
			if ( ! empty( $s['badge'] ) ) {
				$rows[] = array( jju_short( $it['title'] ) . ( $s['slug'] !== $slug ? ' › ' . $s['title'] : '' ), (int) $s['badge'], jju_page_url( $s['slug'] ) );
				$any    = true;
			}
		}
		$b = jju_badge( $it['raw'] );
		if ( $b && ! $any ) {
			$rows[] = array( jju_short( $it['title'] ), $b, jju_page_url( $slug ) );
		}
	}
	if ( ! $rows ) {
		return;
	}
	echo '<div class="jju-wait"><strong><span class="dashicons dashicons-bell" aria-hidden="true"></span> Čaká na vás</strong>';
	foreach ( $rows as $r ) {
		echo '<a href="' . esc_url( $r[2] ) . '">' . esc_html( $r[0] ) . ' <b>' . (int) $r[1] . '</b></a>';
	}
	echo '</div>';
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
	// Pripnuté doplnky, ktoré už nie sú nainštalované, sa zabudnú.
	$valid = array_values( array_filter( $pins, fn( $k ) => isset( $items[ $k ] ) || '' !== jju_plugin_for( $k ) ) );
	if ( $valid !== $pins ) {
		update_user_meta( get_current_user_id(), 'jju_pins', $valid );
		$pins = $valid;
	}
	jju_render_waiting( $items );
	echo '<p class="jju-search"><input type="search" id="jju-q" placeholder="Hľadať doplnok alebo stránku (napr. saldokonto, banka, kupón)…" aria-label="Hľadať doplnok alebo stránku" autocomplete="off"></p>';
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
			$act = current_user_can( 'activate_plugin', $h['file'] ) ? wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $h['file'] ) ), 'activate-plugin_' . $h['file'] ) : '';
			echo '<div><strong>' . esc_html( $h['name'] ) . '</strong> <span>' . esc_html( $h['version'] ) . '</span><p>' . esc_html( wp_html_excerpt( $h['desc'], 140, '…' ) ) . '</p>'
				. ( $act ? '<p><a class="button button-small" href="' . esc_url( $act ) . '">Zapnúť</a></p>' : '' ) . '</div>';
		}
		echo '</div>';
	}
	?>
	<p class="jju-none" id="jju-none" hidden>Nič sa nenašlo.</p>
	<script>
	( function () {
		var q = document.getElementById( 'jju-q' );
		if ( ! q ) { return; }
		function norm( t ) { return t.toLowerCase().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).trim(); }
		q.addEventListener( 'input', function () {
			var v = norm( q.value ), shown = 0;
			document.querySelectorAll( '.jju .jju-tile' ).forEach( function ( t ) {
				var hit = ! v || t.dataset.find.indexOf( v ) > -1;
				t.hidden = ! hit;
				if ( hit ) { shown++; }
				t.querySelectorAll( '.jju-subs a' ).forEach( function ( a ) { a.classList.toggle( 'jju-hit', !! v && a.dataset.find.indexOf( v ) > -1 ); } );
				var more = t.querySelector( '.jju-more' );
				if ( more && v && more.querySelector( '.jju-hit' ) ) { more.open = true; }
			} );
			document.querySelectorAll( '.jju .jju-h' ).forEach( function ( h ) { h.hidden = !! v; } );
			document.getElementById( 'jju-none' ).hidden = shown > 0;
		} );
		q.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Enter' ) { return; }
			var a = document.querySelector( '.jju .jju-tile:not([hidden]) .jju-hit' ) || document.querySelector( '.jju .jju-tile:not([hidden]) .jju-main' );
			if ( a ) { e.preventDefault(); window.location = a.href; }
		} );
	} )();
	</script>
	<?php
	echo '<p class="jju-foot"><a href="https://github.com/Aveyona/jupiter" target="_blank" rel="noopener">Jupiter na GitHube</a>' . ( isset( $items['j-help'] ) ? ' · <a href="' . esc_url( admin_url( 'admin.php?page=j-help' ) ) . '">Návody a aktualizácie (J-Pomocník)</a>' : '' ) . '</p></div>';
}
