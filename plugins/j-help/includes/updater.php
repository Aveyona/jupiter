<?php
/**
 * Aktualizácie doplnkov Jupiter z GitHubu (verejný repozitár Aveyona/jupiter).
 *
 * Každé vydanie má značku „<doplnok>-v<verzia>“ (napr. j-invoices-v0.2.1) a ZIP doplnku v prílohe.
 * WordPress potom pri doplnkoch Jupiter ukáže „Dostupná aktualizácia“ ako pri bežných doplnkoch
 * a dá sa zapnúť aj automatická aktualizácia. Funguje na každom webe, kde je J-Pomocník zapnutý.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JHELP_REPO', 'Aveyona/jupiter' );

/** Najnovšie vydania na GitHube: slug => [version, package, url, notes, published]. Uloženie na 6 hodín. */
function jhelp_remote_versions( $force = false ) {
	$cached = get_site_transient( 'jhelp_releases' );
	if ( ! $force && is_array( $cached ) ) {
		return $cached;
	}
	$res = wp_remote_get( 'https://api.github.com/repos/' . JHELP_REPO . '/releases?per_page=50', array(
		'timeout' => 15,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Jupiter-WordPress/' . JHELP_VERSION ),
	) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		set_site_transient( 'jhelp_releases', is_array( $cached ) ? $cached : array(), HOUR_IN_SECONDS ); // pri chybe skúsiť o hodinu
		update_site_option( 'jhelp_releases_error', is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
		return is_array( $cached ) ? $cached : array();
	}
	delete_site_option( 'jhelp_releases_error' );
	$out = array();
	foreach ( (array) json_decode( wp_remote_retrieve_body( $res ), true ) as $rel ) {
		if ( ! empty( $rel['draft'] ) || ! empty( $rel['prerelease'] ) || empty( $rel['tag_name'] ) ) {
			continue;
		}
		if ( ! preg_match( '/^(j-[a-z0-9-]+)-v(\d+(?:\.\d+)*)$/', $rel['tag_name'], $m ) ) {
			continue;
		}
		$slug = $m[1];
		$ver  = $m[2];
		$zip  = '';
		foreach ( (array) $rel['assets'] as $a ) {
			if ( isset( $a['name'] ) && $rel['tag_name'] . '.zip' === $a['name'] ) {
				$zip = $a['browser_download_url'];
			}
		}
		if ( ! $zip ) {
			continue;
		}
		if ( ! isset( $out[ $slug ] ) || version_compare( $ver, $out[ $slug ]['version'], '>' ) ) {
			$out[ $slug ] = array(
				'version'   => $ver,
				'package'   => $zip,
				'url'       => $rel['html_url'],
				'notes'     => (string) $rel['body'],
				'published' => (string) $rel['published_at'],
			);
		}
	}
	set_site_transient( 'jhelp_releases', $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

/** Nainštalované doplnky Jupiter: slug => [file, version, name]. */
function jhelp_installed_j_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( get_plugins() as $file => $data ) {
		$slug = dirname( $file );
		if ( 0 === strpos( $slug, 'j-' ) ) {
			$out[ $slug ] = array( 'file' => $file, 'version' => $data['Version'], 'name' => $data['Name'] );
		}
	}
	return $out;
}

// Ponuka aktualizácií v zozname doplnkov.
add_filter( 'pre_set_site_transient_update_plugins', function ( $transient ) {
	if ( ! is_object( $transient ) ) {
		$transient = new stdClass();
	}
	$remote = jhelp_remote_versions();
	foreach ( jhelp_installed_j_plugins() as $slug => $p ) {
		if ( empty( $remote[ $slug ] ) ) {
			continue;
		}
		$r    = $remote[ $slug ];
		$item = (object) array(
			'id'          => 'github.com/' . JHELP_REPO . '/' . $slug,
			'slug'        => $slug,
			'plugin'      => $p['file'],
			'new_version' => $r['version'],
			'url'         => 'https://github.com/' . JHELP_REPO,
			'package'     => $r['package'],
			'icons'       => array(),
			'tested'      => get_bloginfo( 'version' ),
		);
		if ( version_compare( $r['version'], $p['version'], '>' ) ) {
			$transient->response[ $p['file'] ] = $item;
		} else {
			$transient->no_update[ $p['file'] ] = $item; // umožní zapnúť automatické aktualizácie
		}
	}
	return $transient;
} );

// Okno „Zobraziť podrobnosti“ pri aktualizácii.
add_filter( 'plugins_api', function ( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 0 !== strpos( $args->slug, 'j-' ) ) {
		return $res;
	}
	$remote = jhelp_remote_versions();
	$inst   = jhelp_installed_j_plugins();
	if ( empty( $remote[ $args->slug ] ) ) {
		return $res;
	}
	$r = $remote[ $args->slug ];
	return (object) array(
		'name'          => isset( $inst[ $args->slug ] ) ? $inst[ $args->slug ]['name'] : $args->slug,
		'slug'          => $args->slug,
		'version'       => $r['version'],
		'author'        => 'Aveyona, s. r. o.',
		'homepage'      => 'https://github.com/' . JHELP_REPO,
		'download_link' => $r['package'],
		'last_updated'  => $r['published'],
		'sections'      => array(
			'description' => 'Doplnok rodiny Jupiter pre WooCommerce. Zdrojový kód: https://github.com/' . JHELP_REPO,
			'changelog'   => nl2br( esc_html( $r['notes'] ? $r['notes'] : 'Pozri vydanie na GitHube: ' . $r['url'] ) ),
		),
	);
}, 10, 3 );

// Po aktualizácii obnoviť zoznam vydaní.
add_action( 'upgrader_process_complete', function () {
	delete_site_transient( 'jhelp_releases' );
} );

// „Skontrolovať aktualizácie teraz“ z J-Pomocníka.
add_action( 'admin_post_jhelp_check_updates', function () {
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jhelp_check_updates' );
	jhelp_remote_versions( true );
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	wp_safe_redirect( admin_url( 'admin.php?page=j-help&checked=1' ) );
	exit;
} );
