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
 *  - výstup: uploads/j-notes/export/<slug>.md – každá uložená poznámka sa zapíše aj ako súbor (záloha, čítanie mimo WP).
 * Priečinok je chránený pred prístupom z webu (.htaccess).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jno_files_dir( $sub = '' ) {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . JNO_SLUG;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore
	}
	if ( $sub ) {
		$dir .= '/' . $sub;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}
	return $dir;
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

/** Načítanie súborov zo vstupného priečinka. Vráti počet spracovaných. */
function jno_import_inbox() {
	$in   = jno_files_dir( 'inbox' );
	$done = jno_files_dir( 'processed' );
	$n    = 0;
	foreach ( (array) glob( $in . '/*.md' ) as $f ) {
		list( $meta, $body ) = jno_parse_file( (string) file_get_contents( $f ) );
		$slug = sanitize_title( $meta['slug'] ?? basename( $f, '.md' ) );
		$data = array( 'slug' => $slug, 'body' => $body, 'source' => basename( $f ) );
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
		@rename( $f, $done . '/' . gmdate( 'Ymd-His' ) . '-' . basename( $f ) ); // phpcs:ignore
		$n++;
	}
	return $n;
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
add_action( 'jno_inbox', 'jno_import_inbox' );
