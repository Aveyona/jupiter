<?php
/**
 * Poznámky ako súbory .md (synchronizácia so súbormi):
 *  - vstup: uploads/j-notes/inbox/*.md – pri otvorení poznámok sa načítajú a vytvoria / aktualizujú poznámku
 *    podľa kľúča (slug). Hlavička súboru (voliteľná, ako front matter):
 *        ---
 *        slug: plan-j-uctovnictvo
 *        title: Plán J-Účtovníctvo
 *        type: plan            (plan | doc | note)
 *        project: J-Účtovníctvo
 *        status: active        (active | done | archived)
 *        now: 1                (rozpracované – zobrazí sa hore)
 *        ---
 *    Bez hlavičky: slug = názov súboru, názov = prvý nadpis. Spracovaný súbor sa presunie do processed/.
 *  - výstup: uploads/j-notes/export-<náhodný kód>/<slug>.md – každá uložená poznámka sa zapíše aj ako súbor (záloha, čítanie mimo WP).
 * Priečinok je chránený pred prístupom z webu (.htaccess pre Apache); export a spracované súbory majú navyše
 * náhodný názov priečinka (na serveri bez .htaccess, napr. nginx, sa nedajú uhádnuť). Raz denne sa ochrana overí.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Náhodná prípona priečinkov export a processed (vytvorí sa raz). */
function jno_dir_token() {
	$t = (string) get_option( 'jno_dir_token', '' );
	if ( ! preg_match( '/^[a-z0-9]{12}$/', $t ) ) {
		$t = strtolower( wp_generate_password( 12, false, false ) );
		update_option( 'jno_dir_token', $t, false );
	}
	return $t;
}

function jno_files_dir( $sub = '' ) {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . JNO_SLUG;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	if ( $sub ) {
		$name = in_array( $sub, array( 'export', 'processed' ), true ) ? $sub . '-' . jno_dir_token() : $sub;
		$path = $dir . '/' . $name;
		// Prechod zo starého názvu bez náhodnej prípony.
		if ( $name !== $sub && ! is_dir( $path ) && is_dir( $dir . '/' . $sub ) ) {
			@rename( $dir . '/' . $sub, $path ); // phpcs:ignore
		}
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}
		if ( ! file_exists( $path . '/index.php' ) ) {
			@file_put_contents( $path . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
		}
		$dir = $path;
	}
	return $dir;
}

/** Súbory .md v priečinku (bez varovania, keď glob zlyhá). */
function jno_glob_md( $dir ) {
	$f = glob( $dir . '/*.md' );
	return is_array( $f ) ? $f : array();
}

/** Rozdelenie súboru na hlavičku a text. */
function jno_parse_file( $raw ) {
	$raw  = str_replace( "\r\n", "\n", (string) $raw );
	$raw  = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$meta = array();
	if ( preg_match( '/^---\n(.*?)\n---\n?(.*)$/s', $raw, $m ) ) {
		foreach ( explode( "\n", $m[1] ) as $l ) {
			if ( preg_match( '/^\s*([a-z_]+)\s*:\s*(.*?)\s*$/i', $l, $kv ) ) {
				$meta[ strtolower( $kv[1] ) ] = trim( $kv[2], "\"' " );
			}
		}
		$raw = $m[2];
	}
	return array( $meta, ltrim( $raw, "\n" ) );
}

/**
 * Načítanie súborov zo vstupného priečinka. Vráti počet spracovaných.
 * Súbor sa po načítaní presunie do processed/. Ak sa presunúť ani zmazať nedá (práva na súbory), zapamätá sa jeho
 * odtlačok a znova sa načíta až po zmene obsahu – inak by pri každom otvorení prepisoval úpravy urobené vo WordPresse.
 */
function jno_import_inbox() {
	$in    = jno_files_dir( 'inbox' );
	$done  = jno_files_dir( 'processed' );
	$stuck = (array) get_option( 'jno_inbox_stuck', array() );
	$keep  = array();
	$n     = 0;
	foreach ( jno_glob_md( $in ) as $f ) {
		$raw  = (string) file_get_contents( $f );
		$base = basename( $f );
		$hash = md5( $raw );
		if ( isset( $stuck[ $base ] ) && $stuck[ $base ] === $hash ) {
			$keep[ $base ] = $hash;
			continue;
		}
		list( $meta, $body ) = jno_parse_file( $raw );
		$slug = sanitize_title( $meta['slug'] ?? basename( $f, '.md' ) );
		$data = array( 'slug' => $slug, 'body' => $body, 'source' => $base );
		foreach ( array( 'title', 'type', 'project', 'status' ) as $k ) {
			if ( isset( $meta[ $k ] ) && '' !== $meta[ $k ] ) {
				$data[ $k ] = $meta[ $k ];
			}
		}
		foreach ( array( 'now', 'pinned' ) as $k ) {
			if ( isset( $meta[ $k ] ) ) {
				$data[ $k ] = in_array( strtolower( $meta[ $k ] ), array( '1', 'true', 'yes', 'áno', 'ano' ), true );
			}
		}
		$old = jno_get_by_slug( $slug );
		jno_save( $data, $old ? (int) $old['id'] : 0 );
		$moved = @rename( $f, $done . '/' . gmdate( 'Ymd-His' ) . '-' . $base ); // phpcs:ignore
		if ( ! $moved ) {
			$moved = @copy( $f, $done . '/' . gmdate( 'Ymd-His' ) . '-' . $base ) && @unlink( $f ); // phpcs:ignore
		}
		if ( ! $moved && file_exists( $f ) ) {
			$keep[ $base ] = $hash;
		}
		$n++;
	}
	if ( $keep !== $stuck ) {
		update_option( 'jno_inbox_stuck', $keep, false );
	}
	return $n;
}

/** Zmaže spracované súbory staršie ako 90 dní. */
function jno_clean_processed() {
	$lim = time() - 90 * DAY_IN_SECONDS;
	foreach ( jno_glob_md( jno_files_dir( 'processed' ) ) as $f ) {
		if ( @filemtime( $f ) < $lim ) { // phpcs:ignore
			@unlink( $f ); // phpcs:ignore
		}
	}
}

/**
 * Overí, či sa súbory poznámok nedajú stiahnuť z webu (server bez .htaccess, napr. nginx). Výsledok: 'ok' | 'exposed' | ''.
 */
function jno_check_protection() {
	$dir  = jno_files_dir();
	$file = $dir . '/jno-check.txt';
	$code = 'jno-' . wp_generate_password( 10, false, false );
	if ( false === @file_put_contents( $file, $code ) ) { // phpcs:ignore
		return '';
	}
	$up  = wp_upload_dir();
	$url = trailingslashit( $up['baseurl'] ) . JNO_SLUG . '/jno-check.txt';
	$r   = wp_remote_get( $url, array( 'timeout' => 8, 'redirection' => 0 ) );
	@unlink( $file ); // phpcs:ignore
	if ( is_wp_error( $r ) ) {
		return '';
	}
	$st = 200 === (int) wp_remote_retrieve_response_code( $r ) && false !== strpos( (string) wp_remote_retrieve_body( $r ), $code ) ? 'exposed' : 'ok';
	update_option( 'jno_protection', array( 'state' => $st, 'time' => time() ), false );
	return $st;
}

/** Zápis poznámky do export/<slug>.md s hlavičkou. */
function jno_export_note( $id ) {
	$n = jno_get( $id );
	if ( ! $n ) {
		return;
	}
	$head = "---\nslug: {$n['slug']}\ntitle: {$n['title']}\ntype: {$n['type']}\nproject: {$n['project']}\nstatus: {$n['status']}\nnow: {$n['now']}\nupdated: {$n['updated_at']}\n---\n";
	@file_put_contents( jno_files_dir( 'export' ) . '/' . $n['slug'] . '.md', $head . $n['body'] ); // phpcs:ignore
}
add_action( 'jno_saved', 'jno_export_note' );

// Vstupné súbory sa načítajú pri otvorení poznámok v administrácii a raz za hodinu.
add_action( 'current_screen', function ( $screen ) {
	if ( $screen && false !== strpos( (string) $screen->id, JNO_SLUG ) && current_user_can( JNO_CAP ) ) {
		$n = jno_import_inbox();
		if ( $n ) {
			set_transient( 'jno_imported_' . get_current_user_id(), $n, 60 );
		}
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'jno_inbox' ) ) {
		wp_schedule_event( time() + 120, 'hourly', 'jno_inbox' );
	}
} );
add_action( 'jno_inbox', function () {
	jno_import_inbox();
	if ( get_transient( 'jno_daily' ) ) {
		return;
	}
	set_transient( 'jno_daily', 1, DAY_IN_SECONDS );
	jno_purge_trash();
	jno_clean_processed();
	jno_check_protection();
} );
