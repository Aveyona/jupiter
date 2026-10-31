<?php
/**
 * Menu: položka Jupiter (nástenka + nastavenia). Pri zlúčení sú doplnky J- len pod položkou Jupiter
 * (ukážu sa po prejdení myšou), na ich stránkach je zvýraznený Jupiter a hore navigácia Jupiter › doplnok.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ikona planéty (biela, WordPress ju prefarbí podľa farebnej schémy). */
function jju_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="5.6" fill="black"/><ellipse cx="10" cy="10" rx="9.2" ry="3.1" fill="none" stroke="black" stroke-width="1.5" transform="rotate(-22 10 10)"/></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
}

add_action( 'admin_menu', function () {
	add_menu_page( 'Jupiter', 'Jupiter', JJU_CAP, JJU_SLUG, 'jju_render_dashboard', jju_menu_icon(), 58 );
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
		$entries[] = array( $it['title'] . ( $b ? ' <span class="awaiting-mod count-' . $b . '"><span class="pending-count">' . $b . '</span></span>' : '' ), $it['cap'], 'admin.php?page=' . $slug, $it['title'] );
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
		$submenu_file = 'admin.php?page=' . $jju_child[ $plugin_page ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return JJU_SLUG;
	}
	return $parent;
} );
add_filter( 'submenu_file', function ( $file ) {
	global $plugin_page, $jju_child;
	return ( jju_merged() && $plugin_page && isset( $jju_child[ $plugin_page ] ) ) ? 'admin.php?page=' . $jju_child[ $plugin_page ] : $file;
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
	echo '<div class="jju-crumb" style="--app:' . esc_attr( $color ) . '"><a href="' . esc_url( admin_url( 'admin.php?page=' . JJU_SLUG ) ) . '"><img src="' . esc_attr( jju_menu_icon() ) . '" alt="" width="16" height="16"> Jupiter</a> <span class="sep">›</span> <strong>' . esc_html( $top['title'] ) . '</strong>';
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
		var cur = li.querySelector( '.wp-submenu a[href$="page=<?php echo esc_js( $jju_child[ $plugin_page ] ); ?>"]' );
		if ( cur ) { cur.classList.add( 'current' ); cur.parentElement.classList.add( 'current' ); }
	} )();
	</script>
	<?php
} );

add_action( 'admin_enqueue_scripts', function () {
	wp_enqueue_style( 'j-jupiter', JJU_URL . 'assets/jupiter.css', array(), JJU_VERSION );
} );
