<?php
/**
 * Poznámky: tabuľka {prefix}jnotes. Text je Markdown; úlohy v texte (- [ ] / - [~] / - [x] alebo ☐ ◐ ☑)
 * sa počítajú do stavu plánu. Poznámka má jedinečný kľúč (slug) – podľa neho sa aktualizuje aj zo súboru .md.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jno_table() {
	global $wpdb;
	return $wpdb->prefix . 'jnotes';
}

function jno_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( 'CREATE TABLE ' . jno_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  slug varchar(120) NOT NULL DEFAULT '',
  title varchar(255) NOT NULL DEFAULT '',
  type varchar(20) NOT NULL DEFAULT 'note',
  project varchar(100) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'active',
  pinned tinyint(1) NOT NULL DEFAULT 0,
  now tinyint(1) NOT NULL DEFAULT 0,
  body longtext NOT NULL,
  tasks_total int(10) unsigned NOT NULL DEFAULT 0,
  tasks_done int(10) unsigned NOT NULL DEFAULT 0,
  tasks_doing int(10) unsigned NOT NULL DEFAULT 0,
  source varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY project (project),
  KEY status (status)
) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'jno_db_version', JNO_DB_VERSION, false );
}

function jno_types() {
	return array( 'plan' => 'Plán', 'doc' => 'Dokumentácia', 'note' => 'Poznámka' );
}

function jno_statuses() {
	return array( 'active' => 'Aktívna', 'done' => 'Hotová', 'archived' => 'Archív' );
}

/** Úlohy v texte: [ [riadok, stav (todo|doing|done), text], … ]. */
function jno_tasks( $body ) {
	$out = array();
	foreach ( preg_split( '/\R/', (string) $body ) as $i => $line ) {
		if ( preg_match( '/^\s*(?:[-*+]|\d+\.)\s+\[( |x|X|~|-)\]\s+(.*)$/u', $line, $m ) ) {
			$out[] = array( $i, 'x' === strtolower( $m[1] ) ? 'done' : ( ' ' === $m[1] ? 'todo' : 'doing' ), $m[2] );
		} elseif ( preg_match( '/^\s*(?:[-*+]\s+)?(☐|◐|☑)\s+(.*)$/u', $line, $m ) ) {
			$out[] = array( $i, '☑' === $m[1] ? 'done' : ( '◐' === $m[1] ? 'doing' : 'todo' ), $m[2] );
		} elseif ( preg_match( '/^\|.*\|\s*(☐|◐|☑)[^|]*\|?\s*$/u', $line, $m ) ) {
			// Riadok tabuľky, ktorý končí stavom (napr. tabuľka etáp plánu).
			$cells = array_values( array_filter( array_map( 'trim', explode( '|', $line ) ), 'strlen' ) );
			$out[] = array( $i, '☑' === $m[1] ? 'done' : ( '◐' === $m[1] ? 'doing' : 'todo' ), implode( ' – ', array_slice( $cells, 0, 2 ) ) );
		}
	}
	return $out;
}

function jno_get( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jno_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore
}

function jno_get_by_slug( $slug ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jno_table() . ' WHERE slug = %s', $slug ), ARRAY_A ); // phpcs:ignore
}

/** Uloženie poznámky (nová alebo existujúca podľa id / slug). Vráti ID. */
function jno_save( array $n, $id = 0 ) {
	global $wpdb;
	$old  = $id ? jno_get( $id ) : ( ! empty( $n['slug'] ) ? jno_get_by_slug( sanitize_title( $n['slug'] ) ) : null );
	$body = (string) ( $n['body'] ?? ( $old['body'] ?? '' ) );
	$t    = jno_tasks( $body );
	$cnt  = array_count_values( wp_list_pluck( $t, 1 ) );
	$row  = array(
		'title'       => mb_substr( sanitize_text_field( $n['title'] ?? ( $old['title'] ?? '' ) ), 0, 255 ),
		'type'        => isset( jno_types()[ $n['type'] ?? '' ] ) ? $n['type'] : ( $old['type'] ?? 'note' ),
		'project'     => mb_substr( sanitize_text_field( $n['project'] ?? ( $old['project'] ?? '' ) ), 0, 100 ),
		'status'      => isset( jno_statuses()[ $n['status'] ?? '' ] ) ? $n['status'] : ( $old['status'] ?? 'active' ),
		'pinned'      => isset( $n['pinned'] ) ? ( $n['pinned'] ? 1 : 0 ) : (int) ( $old['pinned'] ?? 0 ),
		'now'         => isset( $n['now'] ) ? ( $n['now'] ? 1 : 0 ) : (int) ( $old['now'] ?? 0 ),
		'body'        => $body,
		'tasks_total' => count( $t ),
		'tasks_done'  => (int) ( $cnt['done'] ?? 0 ),
		'tasks_doing' => (int) ( $cnt['doing'] ?? 0 ),
		'updated_at'  => current_time( 'mysql' ),
	);
	if ( isset( $n['source'] ) ) {
		$row['source'] = mb_substr( (string) $n['source'], 0, 255 );
	}
	if ( '' === $row['title'] ) {
		$row['title'] = preg_match( '/^#\s+(.+)$/m', $body, $m ) ? mb_substr( trim( $m[1] ), 0, 255 ) : 'Bez názvu';
	}
	if ( $old ) {
		$wpdb->update( jno_table(), $row, array( 'id' => (int) $old['id'] ) );
		$id = (int) $old['id'];
	} else {
		$slug = sanitize_title( ! empty( $n['slug'] ) ? $n['slug'] : $row['title'] );
		$base = $slug ? $slug : 'poznamka';
		$i    = 1;
		while ( jno_get_by_slug( $slug ) ) {
			$slug = $base . '-' . ( ++$i );
		}
		$wpdb->insert( jno_table(), $row + array( 'slug' => $slug, 'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) ) );
		$id = (int) $wpdb->insert_id;
	}
	do_action( 'jno_saved', $id );
	return $id;
}

/** Zmena stavu úlohy na riadku textu: todo → doing → done → todo (alebo na zadaný stav). */
function jno_set_task( $id, $line, $state = '' ) {
	$n = jno_get( $id );
	if ( ! $n ) {
		return false;
	}
	$lines = preg_split( '/\R/', (string) $n['body'] );
	if ( ! isset( $lines[ $line ] ) ) {
		return false;
	}
	$l    = $lines[ $line ];
	$next = array( 'todo' => 'doing', 'doing' => 'done', 'done' => 'todo' );
	if ( preg_match( '/^(\s*(?:[-*+]|\d+\.)\s+)\[( |x|X|~|-)\](.*)$/u', $l, $m ) ) {
		$cur   = 'x' === strtolower( $m[2] ) ? 'done' : ( ' ' === $m[2] ? 'todo' : 'doing' );
		$st    = $state ? $state : $next[ $cur ];
		$l     = $m[1] . '[' . array( 'todo' => ' ', 'doing' => '~', 'done' => 'x' )[ $st ] . ']' . $m[3];
	} elseif ( preg_match( '/(☐|◐|☑)/u', $l, $m ) ) {
		$cur = '☑' === $m[1] ? 'done' : ( '◐' === $m[1] ? 'doing' : 'todo' );
		$st  = $state ? $state : $next[ $cur ];
		$l   = preg_replace( '/☐|◐|☑/u', array( 'todo' => '☐', 'doing' => '◐', 'done' => '☑' )[ $st ], $l, 1 );
	} else {
		return false;
	}
	$lines[ $line ] = $l;
	jno_save( array( 'body' => implode( "\n", $lines ) ), $id );
	return $st;
}

/** Záznam do denníka zmien poznámky (sekcia „## Denník zmien“ – vytvorí sa, ak chýba). */
function jno_add_log( $id, $text ) {
	$n = jno_get( $id );
	if ( ! $n || '' === trim( $text ) ) {
		return false;
	}
	$entry = '- ' . date_i18n( 'j. n. Y H:i' ) . ' – ' . trim( preg_replace( '/\s+/', ' ', $text ) );
	$body  = rtrim( (string) $n['body'] );
	// Sekcia „## Denník zmien“ (aj číslovaná, napr. „## 4. Denník zmien“) – záznam sa pridá na jej koniec.
	if ( preg_match( '/^##\s+(?:\d+\.\s*)?Denník zmien\s*$/mu', $body, $m, PREG_OFFSET_CAPTURE ) ) {
		$start = $m[0][1] + strlen( $m[0][0] );
		$next  = preg_match( '/^#{1,2}\s/m', $body, $m2, PREG_OFFSET_CAPTURE, $start ) ? $m2[0][1] : strlen( $body );
		$sect  = rtrim( substr( $body, $start, $next - $start ) );
		$body  = substr( $body, 0, $start ) . $sect . "\n" . $entry . "\n" . ( $next < strlen( $body ) ? "\n" . substr( $body, $next ) : '' );
	} else {
		$body .= "\n\n## Denník zmien\n" . $entry;
	}
	return jno_save( array( 'body' => $body ), $id );
}
