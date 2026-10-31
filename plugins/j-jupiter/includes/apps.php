<?php
/** Doplnky Jupiter v menu: zoznam, farby, popisy, počítadlá. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jju_settings() {
	return wp_parse_args( (array) get_option( 'jju_settings', array() ), array( 'merge' => 1 ) );
}

/** Zlúčiť doplnky do položky Jupiter? */
function jju_merged() {
	return ! empty( jju_settings()['merge'] );
}

/** Je položka menu doplnkom Jupiter (slug j-…), okrem samotného Jupitera? */
function jju_is_j_item( $item ) {
	$slug = isset( $item[2] ) ? (string) $item[2] : '';
	return JJU_SLUG !== $slug && ( 0 === strpos( $slug, 'j-' ) || 0 === strpos( $slug, 'j_' ) );
}

/** Čistý názov (bez počítadiel). */
function jju_clean_title( $t ) {
	$t = preg_replace( '#<span[^>]*class="[^"]*(update-plugins|awaiting-mod|count)[^"]*"[^>]*>.*?</span>#is', '', (string) $t );
	return trim( wp_strip_all_tags( $t ) );
}

/** Počítadlo z názvu položky menu (napr. „J-Účtovníctvo 8“). */
function jju_badge( $raw ) {
	return preg_match( '#<span[^>]*class="[^"]*(update-plugins|awaiting-mod|count)[^"]*"[^>]*>.*?(\d+).*?</span>#is', (string) $raw, $m ) ? (int) $m[2] : 0;
}

function jju_page_url( $slug ) {
	return ( false !== strpos( $slug, '.php' ) ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );
}

/** Ikona (dashicons alebo obrázok). */
function jju_icon( $icon ) {
	if ( 0 === strpos( (string) $icon, 'dashicons-' ) ) {
		return '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
	}
	if ( 0 === strpos( (string) $icon, 'data:image' ) || 0 === strpos( (string) $icon, 'http' ) ) {
		return '<span class="jju-img" style="background-image:url(\'' . esc_attr( $icon ) . '\')" aria-hidden="true"></span>';
	}
	return '<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>';
}

/** Údaje doplnkov z hlavičiek (verzia, popis) podľa priečinka = adresy stránky. */
function jju_headers() {
	static $h = null;
	if ( null !== $h ) {
		return $h;
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$h = array();
	foreach ( get_plugins() as $file => $d ) {
		$dir = dirname( $file );
		if ( 0 === strpos( $dir, 'j-' ) ) {
			$h[ $dir ] = array(
				'file'    => $file,
				'name'    => (string) $d['Name'],
				'version' => (string) $d['Version'],
				'desc'    => trim( wp_strip_all_tags( preg_replace( '/\s*Súčasť rodiny Jupiter\.?/u', '', (string) $d['Description'] ) ) ),
				'active'  => is_plugin_active( $file ),
			);
		}
	}
	return $h;
}

/** Doplnok pre adresu stránky (j-emails, j-invoices…; podstránky j-sklad-… patria k j-sklad). */
function jju_plugin_for( $slug ) {
	$h = jju_headers();
	if ( isset( $h[ $slug ] ) ) {
		return $slug;
	}
	foreach ( array_keys( $h ) as $dir ) {
		if ( 0 === strpos( $slug, $dir . '-' ) ) {
			return $dir;
		}
	}
	return '';
}

/** Popis doplnku: zápis v J-Pomocníkovi (jupiter_plugins), inak hlavička doplnku. */
function jju_desc( $slug ) {
	$cat = (array) apply_filters( 'jupiter_plugins', array() );
	$p   = jju_plugin_for( $slug );
	$d   = '';
	foreach ( array( $slug, $p ) as $k ) {
		if ( $k && ! empty( $cat[ $k ]['what'] ) ) {
			$d = $cat[ $k ]['what'];
			break;
		}
	}
	if ( '' === $d && $p ) {
		$d = jju_headers()[ $p ]['desc'];
	}
	return wp_html_excerpt( wp_strip_all_tags( $d ), 160, '…' );
}

/** Farby doplnkov – každý dostane vlastnú (zlatý uhol na farebnom kruhu, odtiene modrej a fialovej najskôr). */
function jju_colors( $slugs ) {
	$c       = (array) get_option( 'jju_colors', array() );
	$changed = false;
	foreach ( $slugs as $slug ) {
		if ( empty( $c[ $slug ] ) ) {
			$n            = count( array_filter( $c ) );
			$c[ $slug ]   = jju_hsl_hex( fmod( 220 + $n * 137.508, 360 ), 0.62, 0.46 );
			$changed      = true;
		}
	}
	if ( $changed ) {
		update_option( 'jju_colors', $c, false );
	}
	return $c;
}

function jju_hsl_hex( $h, $s, $l ) {
	$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
	$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
	$m = $l - $c / 2;
	$r = $g = $b = 0; // phpcs:ignore
	if ( $h < 60 ) { $r = $c; $g = $x; } elseif ( $h < 120 ) { $r = $x; $g = $c; } elseif ( $h < 180 ) { $g = $c; $b = $x; } elseif ( $h < 240 ) { $g = $x; $b = $c; } elseif ( $h < 300 ) { $r = $x; $b = $c; } else { $r = $c; $b = $x; } // phpcs:ignore
	return sprintf( '#%02x%02x%02x', round( ( $r + $m ) * 255 ), round( ( $g + $m ) * 255 ), round( ( $b + $m ) * 255 ) );
}

/** Pripnuté doplnky prihláseného používateľa. */
function jju_pins() {
	return array_values( array_filter( (array) get_user_meta( get_current_user_id(), 'jju_pins', true ) ) );
}
