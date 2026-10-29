<?php
/** Administrácia: aplikácia Jupiter Pošta (pošta v štýle Gmailu) a Nastavenia schránky. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JEM_CAP', 'manage_options' );

add_action( 'admin_menu', function () {
	$unseen = (int) get_transient( 'jem_unseen' );
	$bubble = $unseen > 0 ? ' <span class="awaiting-mod count-' . $unseen . '"><span class="pending-count">' . $unseen . '</span></span>' : '';
	add_menu_page( 'Jupiter Pošta', 'J-Pošta' . $bubble, JEM_CAP, 'j-emails', 'jem_render_app', 'dashicons-email-alt', 58 );
	add_submenu_page( 'j-emails', 'Jupiter Pošta', 'Pošta' . $bubble, JEM_CAP, 'j-emails', 'jem_render_app' );
	add_submenu_page( 'j-emails', 'Nastavenia – Jupiter Pošta', 'Nastavenia', JEM_CAP, 'j-emails-settings', function () {
		jem_render_admin( 'settings' );
	} );
} );

// Staré adresy (samostatné stránky Odoslané, Kôš, Napísať) → aplikácia.
add_action( 'admin_init', function () {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
	$map  = array( 'j-emails-sent' => '#sent', 'j-emails-trash' => '#trash', 'j-emails-compose' => '#inbox/compose' );
	if ( isset( $map[ $page ] ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=j-emails' ) . $map[ $page ] );
		exit;
	}
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false === strpos( (string) $hook, 'j-emails' ) ) {
		return;
	}
	wp_enqueue_style( 'j-emails-admin', JEM_URL . 'assets/admin.css', array(), JEM_VERSION );
	if ( 'toplevel_page_j-emails' !== $hook ) {
		return;
	}
	// Verzia so dátumom súboru – po aktualizácii prehliadač nepoužije starý skript z vyrovnávacej pamäte.
	$ver = function ( $f ) {
		return JEM_VERSION . '.' . (int) @filemtime( JEM_DIR . $f ); // phpcs:ignore
	};
	wp_enqueue_style( 'j-emails-app', JEM_URL . 'assets/app.css', array(), $ver( 'assets/app.css' ) );
	wp_enqueue_script( 'j-emails-app', JEM_URL . 'assets/app.js', array(), $ver( 'assets/app.js' ), true );
	wp_localize_script(
		'j-emails-app',
		'JEM',
		array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'jem_app' ),
			'settings'  => admin_url( 'admin.php?page=j-emails-settings' ),
			'version'   => JEM_VERSION,
			'configured' => jem_configured(),
			'site'      => wp_parse_url( home_url(), PHP_URL_HOST ),
		)
	);
} );

add_filter( 'admin_body_class', function ( $c ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
	return 'j-emails' === $page ? $c . ' jem-app-page' : $c;
} );

/** Počet neprečítaných správ pre menu a vrátenie odložených správ – každých 5 minút na pozadí. */
add_filter( 'cron_schedules', function ( $s ) {
	$s['jem_5min'] = array( 'interval' => 300, 'display' => 'Každých 5 minút (J-Pošta)' );
	return $s;
} );
add_action( 'init', function () {
	if ( jem_configured() && ! wp_next_scheduled( 'jem_check_unseen' ) ) {
		wp_schedule_event( time() + 60, 'jem_5min', 'jem_check_unseen' );
	}
} );
add_action( 'jem_check_unseen', function () {
	if ( ! jem_configured() ) {
		return;
	}
	$imap = jem_connect();
	if ( ! is_wp_error( $imap ) ) {
		jem_process_snoozed( $imap );
		set_transient( 'jem_unseen', $imap->unseen( 'INBOX' ), 900 );
		$imap->close();
	}
} );

function jem_size( $b ) {
	return $b >= 1048576 ? number_format_i18n( $b / 1048576, 1 ) . ' MB' : max( 1, (int) round( $b / 1024 ) ) . ' kB';
}

/** Aplikácia Jupiter Pošta – obsah vykreslí assets/app.js. */
function jem_render_app() {
	if ( ! current_user_can( JEM_CAP ) ) {
		return;
	}
	echo '<div id="jem-app" class="jem-app"><div class="jem-boot"><div class="jem-boot-logo"></div><p>Načítavam Jupiter Pošta…</p></div></div>';
	echo '<noscript><p>Jupiter Pošta potrebuje zapnutý JavaScript.</p></noscript>';
}

/** Stránka Nastavenia. */
function jem_render_admin( $box ) {
	if ( ! current_user_can( JEM_CAP ) ) {
		return;
	}
	echo '<div class="wrap jem">';
	echo '<h1>Nastavenia <span class="jem-ver">Jupiter Pošta v' . esc_html( JEM_VERSION ) . '</span> <a href="' . esc_url( admin_url( 'admin.php?page=j-emails' ) ) . '" class="page-title-action">← Pošta</a></h1>';
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>Nastavenia sú uložené.</p></div>';
	}
	jem_render_settings();
	echo '</div>';
}

function jem_part_url( $box, $uid, $i, $inline = false ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=jem_part&box=' . rawurlencode( $box ) . '&uid=' . (int) $uid . '&i=' . (int) $i . ( $inline ? '&inline=1' : '' ) ), 'jem_part' );
}

/** Načítanie a rozbor jednej správy (priečinok podľa kľúča: inbox, sent, archive, …, f:Názov). */
function jem_load( $box, $uid, $mark_seen = false ) {
	$imap = jem_connect();
	if ( is_wp_error( $imap ) ) {
		return $imap;
	}
	$folder = jem_resolve_folder( $imap, $box );
	if ( '' === $folder || false === $imap->select( $folder, ! $mark_seen ) ) {
		return new WP_Error( 'jem', 'Priečinok sa nedá otvoriť.' );
	}
	$m = $imap->message( $uid );
	if ( ! $m ) {
		return new WP_Error( 'jem', $imap->error );
	}
	if ( $mark_seen && ! in_array( '\\Seen', $m['flags'], true ) ) {
		$imap->flag( $uid, '\\Seen', true );
	}
	$imap->close();
	$p          = jem_parse_message( $m['raw'] );
	$p['flags'] = $m['flags'];
	return $p;
}

/** Stiahnutie prílohy (alebo obrázok / PDF na zobrazenie). */
add_action( 'admin_post_jem_part', function () {
	if ( ! current_user_can( JEM_CAP ) || ! check_admin_referer( 'jem_part' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$box = sanitize_text_field( wp_unslash( $_GET['box'] ?? 'inbox' ) ); // phpcs:ignore
	if ( ! jem_valid_key( $box ) ) {
		wp_die( 'Neplatný priečinok.' );
	}
	$m = jem_load( $box, (int) ( $_GET['uid'] ?? 0 ) );
	$i = (int) ( $_GET['i'] ?? -1 );
	if ( is_wp_error( $m ) || ! isset( $m['attachments'][ $i ] ) ) {
		wp_die( 'Príloha sa nenašla.' );
	}
	$a      = $m['attachments'][ $i ];
	$inline = ! empty( $_GET['inline'] ) && preg_match( '#^(image/(png|jpe?g|gif|webp)|application/pdf)$#', $a['type'] );
	nocache_headers();
	header( 'Content-Type: ' . ( $inline ? $a['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . "; filename*=UTF-8''" . rawurlencode( $a['name'] ) );
	header( 'Content-Length: ' . strlen( $a['data'] ) );
	header( 'X-Content-Type-Options: nosniff' );
	if ( $inline && 0 === strpos( $a['type'], 'image/' ) ) {
		header( "Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox" );
	}
	echo $a['data']; // phpcs:ignore
	exit;
} );
