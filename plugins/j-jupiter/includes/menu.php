<?php
/**
 * Menu: položka Jupiter (nástenka + nastavenia). Pri zlúčení sú doplnky J- len pod položkou Jupiter
 * (ukážu sa po prejdení myšou), na ich stránkach je zvýraznený Jupiter a hore navigácia Jupiter › doplnok.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ikona planéty (biela, súbor assets/planet.svg). V menu ju kreslí CSS ako masku, takže má farbu menu (aj pri prejdení myšou). */
function jju_menu_icon() {
	return JJU_URL . 'assets/planet.svg';
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Jupiter', 'Jupiter', JJU_CAP, JJU_SLUG, 'jju_render_dashboard', 'none', 58 );
	add_submenu_page( JJU_SLUG, 'Jupiter – Prehľad', 'Prehľad', JJU_CAP, JJU_SLUG, 'jju_render_dashboard' );
	add_submenu_page( JJU_SLUG, 'Jupiter – Nastavenia', 'Nastavenia', JJU_CAP, 'j-jupiter-settings', 'jju_render_settings' );
}, 5 );

/** Zoznam doplnkov J- z menu (po tom, čo sa všetky zaregistrovali); pri zlúčení ich presunie pod Jupiter. */
add_action( 'admin_menu', function () {
	global $menu, $submenu, $jju_items, $jju_child;
	$jju_items = array();
	$jju_child = array();
	$order     = function_exists( 'jhelp_menu_order' ) ? jhelp_menu_order() : array();
	foreach ( (array) $menu as $k => $item ) {
		if ( ! jju_is_j_item( $item ) || ! current_user_can( $item[1] ) ) {
			continue;
		}
		$slug = (string) $item[2];
		$subs = array();
		foreach ( (array) ( $submenu[ $slug ] ?? array() ) as $s ) {
			if ( isset( $s[2] ) && current_user_can( $s[1] ) ) {
				$subs[]                       = array( 'title' => jju_clean_title( $s[0] ), 'slug' => (string) $s[2] );
				$jju_child[ (string) $s[2] ] = $slug;
			}
		}
		$jju_child[ $slug ] = $slug;
		$jju_items[ $slug ] = array(
			'title' => jju_clean_title( $item[0] ),
			'raw'   => (string) $item[0],
			'cap'   => (string) $item[1],
			'icon'  => isset( $item[6] ) ? (string) $item[6] : '',
			'pos'   => $order[ $slug ] ?? 500,
			'subs'  => $subs,
		);
	}
	uasort( $jju_items, function ( $a, $b ) {
		return array( $a['pos'], $a['title'] ) <=> array( $b['pos'], $b['title'] );
	} );
	if ( ! jju_merged() || ! $jju_items ) {
		return;
	}
	$badge = 0;
	$entries = array();
	foreach ( $jju_items as $slug => $it ) {
		remove_menu_page( $slug );
		$b      = jju_badge( $it['raw'] );
		$badge += $b;
		$subs = array_values( array_filter( $it['subs'], function ( $sb ) use ( $slug ) {
			return $sb['slug'] !== $slug;
		} ) );
		$entries[] = array( jju_short( $it['title'] ) . ( $b ? ' <span class="awaiting-mod count-' . $b . '"><span class="pending-count">' . $b . '</span></span>' : '' ), $it['cap'], 'admin.php?page=' . $slug, $it['title'], 'jju-app' . ( $subs ? ' jju-has-subs' : '' ) . ' jju-app-' . sanitize_html_class( $slug ) );
		// Podpoložky doplnku (rozbaľujú sa pod ním; pri otvorenom doplnku sú rozbalené).
		foreach ( $subs as $sb ) {
			$url       = false !== strpos( $sb['slug'], '.php' ) ? $sb['slug'] : 'admin.php?page=' . $sb['slug'];
			$entries[] = array( $sb['title'], $it['cap'], $url, $sb['title'], 'jju-sub jju-of-' . sanitize_html_class( $slug ) );
		}
	}
	// Pod Jupiterom: Prehľad, doplnky, Nastavenia.
	$own = (array) ( $submenu[ JJU_SLUG ] ?? array() );
	$first = array_shift( $own );
	$submenu[ JJU_SLUG ] = array_merge( $first ? array( $first ) : array(), $entries, $own ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	if ( $badge ) {
		foreach ( $menu as $k => $item ) {
			if ( JJU_SLUG === ( $item[2] ?? '' ) ) {
				$menu[ $k ][0] = 'Jupiter <span class="awaiting-mod count-' . $badge . '"><span class="pending-count">' . $badge . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}
}, PHP_INT_MAX - 10 );

/** Na stránke doplnku J- je pri zlúčení v menu zvýraznený Jupiter a ten doplnok. */
add_filter( 'parent_file', function ( $parent ) {
	global $plugin_page, $jju_child, $submenu_file;
	if ( jju_merged() && $plugin_page && isset( $jju_child[ $plugin_page ] ) ) {
		$submenu_file = 'admin.php?page=' . $plugin_page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return JJU_SLUG;
	}
	return $parent;
} );
add_filter( 'submenu_file', function ( $file ) {
	global $plugin_page, $jju_child;
	return ( jju_merged() && $plugin_page && isset( $jju_child[ $plugin_page ] ) ) ? 'admin.php?page=' . $plugin_page : $file;
} );

/** Navigácia hore na stránke doplnku: Jupiter › doplnok + jeho podstránky. */
add_action( 'in_admin_header', function () {
	global $plugin_page, $jju_child, $jju_items;
	if ( ! jju_merged() || ! $plugin_page || ! isset( $jju_child[ $plugin_page ] ) ) {
		return;
	}
	$top = $jju_items[ $jju_child[ $plugin_page ] ] ?? null;
	if ( ! $top ) {
		return;
	}
	$color = jju_colors( array_keys( (array) $jju_items ) )[ $jju_child[ $plugin_page ] ] ?? '#2271b1';
	echo '<div class="jju-crumb" style="--app:' . esc_attr( $color ) . '"><a href="' . esc_url( admin_url( 'admin.php?page=' . JJU_SLUG ) ) . '"><img src="' . esc_attr( jju_menu_icon() ) . '" alt="" width="16" height="16"> Jupiter</a> <span class="sep">›</span> <strong>' . esc_html( jju_short( $top['title'] ) ) . '</strong>';
	if ( count( $top['subs'] ) > 1 ) {
		echo '<nav aria-label="' . esc_attr( $top['title'] ) . '">';
		foreach ( $top['subs'] as $s ) {
			echo '<a class="' . ( $s['slug'] === $plugin_page ? 'is-on' : '' ) . '" href="' . esc_url( jju_page_url( $s['slug'] ) ) . '">' . esc_html( $s['title'] ) . '</a>';
		}
		echo '</nav>';
	}
	echo '</div>';
} );

/* Stránky doplnkov: WordPress im určí rodiča podľa doplnku (jeho stránky ostávajú registrované pod ním), položku Jupiter zvýrazníme skriptom. */
add_action( 'admin_footer', function () {
	global $plugin_page, $jju_child;
	if ( ! jju_merged() || ! $plugin_page || ! isset( $jju_child[ $plugin_page ] ) ) {
		return;
	}
	?>
	<script>
	( function () {
		var li = document.getElementById( 'toplevel_page_j-jupiter' );
		if ( ! li || li.classList.contains( 'wp-has-current-submenu' ) ) { return; }
		li.classList.remove( 'wp-not-current-submenu' );
		li.classList.add( 'wp-has-current-submenu', 'wp-menu-open', 'current' );
		var a = li.querySelector( 'a.menu-top' );
		if ( a ) { a.classList.remove( 'wp-not-current-submenu' ); a.classList.add( 'wp-has-current-submenu', 'wp-menu-open' ); a.setAttribute( 'aria-current', 'page' ); }
	} )();
	</script>
	<?php
} );

/* Podmenu Jupiter: podpoložky doplnkov ako rozbaľovací strom (šípka pri doplnku), otvorený doplnok je rozbalený. */
add_action( 'admin_footer', function () {
	global $plugin_page, $jju_child;
	if ( ! jju_merged() ) {
		return;
	}
	$open = ( $plugin_page && isset( $jju_child[ $plugin_page ] ) ) ? sanitize_html_class( $jju_child[ $plugin_page ] ) : '';
	?>
	<script>
	( function () {
		var root = document.querySelector( '#toplevel_page_j-jupiter .wp-submenu' );
		if ( ! root ) { return; }
		var key = 'jjuOpen', saved = [];
		try { saved = JSON.parse( localStorage.getItem( key ) || '[]' ); } catch ( e ) {}
		var open = <?php echo wp_json_encode( $open ); ?>;
		function set( app, on ) {
			root.querySelectorAll( 'li.jju-of-' + app ).forEach( function ( li ) { li.classList.toggle( 'jju-show', on ); } );
			var p = root.querySelector( 'li.jju-app-' + app );
			if ( p ) { p.classList.toggle( 'jju-open', on ); var b = p.querySelector( '.jju-tog' ); if ( b ) { b.setAttribute( 'aria-expanded', on ? 'true' : 'false' ); } }
		}
		root.querySelectorAll( 'li.jju-has-subs' ).forEach( function ( li ) {
			var app = ( li.className.match( /jju-app-([\w-]+)/ ) || [] )[1];
			if ( ! app ) { return; }
			var b = document.createElement( 'button' );
			b.type = 'button'; b.className = 'jju-tog'; b.setAttribute( 'aria-expanded', 'false' );
			b.setAttribute( 'aria-label', 'Podpoložky: ' + li.textContent.trim() );
			b.addEventListener( 'click', function ( e ) {
				e.preventDefault(); e.stopPropagation();
				var on = ! li.classList.contains( 'jju-open' );
				set( app, on );
				saved = saved.filter( function ( x ) { return x !== app; } );
				if ( on ) { saved.push( app ); }
				try { localStorage.setItem( key, JSON.stringify( saved ) ); } catch ( e2 ) {}
			} );
			li.appendChild( b );
			set( app, app === open || saved.indexOf( app ) > -1 );
		} );
	} )();
	</script>
	<?php
} );

add_action( 'admin_enqueue_scripts', function () {
	wp_enqueue_style( 'j-jupiter', JJU_URL . 'assets/jupiter.css', array(), JJU_VERSION . '.' . filemtime( JJU_DIR . 'assets/jupiter.css' ) );
} );
