<?php
/** Nastavenia Jupitera: zlúčenie doplnkov v menu a farby doplnkov. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jju_render_settings() {
	global $jju_items;
	if ( ! current_user_can( JJU_CAP ) ) {
		return;
	}
	$s      = jju_settings();
	$items  = (array) $jju_items;
	$colors = jju_colors( array_keys( $items ) );
	echo '<div class="wrap jju"><h1>Jupiter – Nastavenia</h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>Nastavenia sú uložené.</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'jju_save' );
	echo '<input type="hidden" name="action" value="jju_save">'
		. '<label class="jju-switchrow"><input type="checkbox" name="merge" value="1" ' . checked( ! empty( $s['merge'] ), true, false ) . '><span class="jju-switch" aria-hidden="true"></span><span><strong>Zlúčiť doplnky do položky Jupiter</strong><small>Zapnuté: v ľavom menu je len Jupiter, doplnky sa ukážu po prejdení myšou a na nástenke Jupiter. Vypnuté: každý doplnok J- má v menu vlastnú položku.</small></span></label>';
	echo '<h2>Farby doplnkov</h2><p class="description">Farba dlaždice na nástenke a navigácie na stránke doplnku.</p><table class="widefat striped jju-colors"><thead><tr><th>Doplnok</th><th>Farba</th></tr></thead><tbody>';
	foreach ( $items as $slug => $it ) {
		echo '<tr><td><span class="jju-dot" style="background:' . esc_attr( $colors[ $slug ] ) . '"></span> ' . esc_html( $it['title'] ) . '</td><td><input type="color" name="colors[' . esc_attr( $slug ) . ']" value="' . esc_attr( $colors[ $slug ] ) . '" aria-label="' . esc_attr( 'Farba: ' . $it['title'] ) . '"></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( 'Uložiť' );
	echo '</form></div>';
}

add_action( 'admin_post_jju_save', function () {
	if ( ! current_user_can( JJU_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jju_save' );
	update_option( 'jju_settings', array( 'merge' => empty( $_POST['merge'] ) ? 0 : 1 ), false ); // phpcs:ignore
	$c = (array) get_option( 'jju_colors', array() );
	foreach ( (array) ( $_POST['colors'] ?? array() ) as $k => $v ) { // phpcs:ignore
		$hex = sanitize_hex_color( (string) $v );
		if ( $hex ) {
			$c[ sanitize_key( $k ) ] = $hex;
		}
	}
	update_option( 'jju_colors', $c, false );
	wp_safe_redirect( admin_url( 'admin.php?page=j-jupiter-settings&saved=1' ) );
	exit;
} );
