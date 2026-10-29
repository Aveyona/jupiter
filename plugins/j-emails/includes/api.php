<?php
/**
 * Jupiter Pošta – AJAX rozhranie pre aplikáciu v administrácii (admin-ajax.php, akcie jem_*).
 * Každá požiadavka: oprávnenie JEM_CAP + nonce „jem_app“.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Spoločná kontrola a pripojenie k schránke. */
function jem_api_begin( $connect = true ) {
	if ( ! current_user_can( JEM_CAP ) || ! check_ajax_referer( 'jem_app', '_n', false ) ) {
		wp_send_json_error( array( 'msg' => 'Nemáte oprávnenie alebo vypršala relácia – obnovte stránku.' ), 403 );
	}
	if ( ! $connect ) {
		return null;
	}
	if ( ! jem_configured() ) {
		wp_send_json_error( array( 'msg' => 'Schránka nie je nastavená.', 'setup' => true ) );
	}
	$imap = jem_connect();
	if ( is_wp_error( $imap ) ) {
		wp_send_json_error( array( 'msg' => $imap->get_error_message() ) );
	}
	return $imap;
}

/** Vstup z POST (bez lomítok). */
function jem_in( $k, $def = '' ) {
	return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : $def; // phpcs:ignore
}

/** Povolené kľúče priečinkov pre akcie (systémové + vlastné „f:…“). */
function jem_valid_key( $k ) {
	$k = (string) $k;
	return in_array( $k, array( 'inbox', 'sent', 'trash', 'archive', 'spam', 'snoozed' ), true ) || ( 0 === strpos( $k, 'f:' ) && strlen( $k ) > 2 );
}

/** Členovia konverzácie z JSON: [ {k, u:[…]}, … ] → [ k => [uid…] ]. */
function jem_members( $json ) {
	$out = array();
	foreach ( (array) json_decode( (string) $json, true ) as $m ) {
		$k = (string) ( $m['k'] ?? '' );
		if ( ! jem_valid_key( $k ) ) {
			continue;
		}
		foreach ( (array) ( $m['u'] ?? array() ) as $u ) {
			if ( (int) $u > 0 ) {
				$out[ $k ][] = (int) $u;
			}
		}
	}
	foreach ( $out as $k => $u ) {
		$out[ $k ] = array_values( array_unique( $u ) );
	}
	return $out;
}

/** Počty neprečítaných pre menu. */
function jem_counts( JEM_Imap $imap ) {
	$c = array( 'inbox' => $imap->unseen( 'INBOX' ) );
	set_transient( 'jem_unseen', $c['inbox'], 900 );
	$spam = jem_resolve_folder( $imap, 'spam' );
	if ( '' !== $spam ) {
		$c['spam'] = $imap->unseen( $spam );
	}
	$c['drafts'] = count( jem_drafts() );
	return $c;
}

/* ---------- Štart aplikácie: adresy, priečinky, počty ---------- */
add_action( 'wp_ajax_jem_boot', function () {
	$imap = jem_api_begin();
	jem_process_snoozed( $imap );
	$custom = array();
	foreach ( array_slice( jem_custom_folders( $imap ), 0, 40 ) as $name ) {
		$custom[] = array( 'key' => 'f:' . $name, 'label' => jem_folder_label( $name ), 'count' => $imap->unseen( $name ) );
	}
	$map = jem_folder_map( $imap );
	$me  = array();
	foreach ( jem_my_addresses() as $e => $n ) {
		$me[] = array( 'email' => $e, 'name' => $n );
	}
	$out = array(
		'me'       => $me,
		'counts'   => jem_counts( $imap ),
		'custom'   => $custom,
		'has'      => array(
			'archive' => true,
			'spam'    => true,
			'trash'   => '' !== $map['trash'],
			'sent'    => '' !== $map['sent'],
		),
		'folders'  => array_map( 'jem_folder_label', array_diff_key( $map, array( 'all' => 1 ) ) ),
		'all'      => array_map( 'jem_folder_label', (array) ( $map['all'] ?? array() ) ),
		'signature' => (string) jem_opt( 'signature' ),
		'per'       => max( 10, min( 100, (int) jem_opt( 'per_page' ) ) ),
	);
	$imap->close();
	wp_send_json_success( $out );
} );

/* ---------- Zoznam konverzácií ---------- */
add_action( 'wp_ajax_jem_list', function () {
	$view = sanitize_text_field( (string) jem_in( 'view', 'inbox' ) );
	$q    = trim( wp_check_invalid_utf8( (string) jem_in( 'q', '' ) ) );
	$page = max( 1, (int) jem_in( 'page', 1 ) );
	$per  = max( 10, min( 100, (int) jem_opt( 'per_page' ) ) );
	if ( 'drafts' === $view ) {
		jem_api_begin( false );
		$rows = array();
		foreach ( jem_drafts() as $id => $d ) {
			$rows[] = array(
				'id'      => 'draft:' . $id,
				'draft'   => $id,
				'subject' => (string) ( $d['subject'] ?? '' ),
				'snippet' => mb_substr( jem_html_to_text( (string) ( $d['html'] ?? '' ) ), 0, 160 ),
				'names'   => array( array( 'n' => 'Koncept', 'me' => true, 'unread' => false ) ),
				'to'      => (string) ( $d['to'] ?? '' ),
				'count'   => 1,
				'date'    => (int) ( $d['updated'] ?? 0 ),
				'members' => array(),
			);
		}
		usort( $rows, fn( $a, $b ) => $b['date'] <=> $a['date'] );
		wp_send_json_success( array( 'threads' => array_slice( $rows, ( $page - 1 ) * $per, $per ), 'total' => count( $rows ), 'more' => false, 'page' => $page, 'per' => $per ) );
	}
	$ok = in_array( $view, array( 'inbox', 'starred', 'snoozed', 'important', 'sent', 'archive', 'spam', 'trash', 'search' ), true ) || ( 0 === strpos( $view, 'f:' ) );
	if ( ! $ok ) {
		$view = 'inbox';
	}
	$imap = jem_api_begin();
	if ( 'inbox' === $view && 1 === $page ) {
		jem_process_snoozed( $imap );
	}
	$r = jem_list_threads( $imap, $view, $q, $page, $per );
	$c = jem_counts( $imap );
	$imap->close();
	wp_send_json_success( array_merge( $r, array( 'page' => $page, 'per' => $per, 'counts' => $c ) ) );
} );

/* ---------- Konverzácia (vlákno) ---------- */

/** Obsah HTML správy ako samostatný dokument pre izolovaný rámec (bez skriptov, obrázky z internetu len na požiadanie). */
function jem_html_doc( $html, array $atts, $key, $uid, $show_images ) {
	$html = preg_replace( '#<(script|iframe|object|embed|form|noscript)\b[^>]*>.*?</\1>#is', '', (string) $html );
	$html = preg_replace( '#</?(script|iframe|frame|frameset|object|embed|applet|meta|base|link|form|input|button|textarea|select)\b[^>]*>#i', '', $html );
	$html = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
	$html = preg_replace( '/(href|src)\s*=\s*(["\']?)\s*javascript:/i', '$1=$2#', $html );
	foreach ( $atts as $i => $a ) {
		if ( '' !== $a['cid'] ) {
			$html = str_ireplace( 'cid:' . $a['cid'], esc_url( jem_part_url( $key, $uid, $i, true ) ), $html );
		}
	}
	$part = strtok( admin_url( 'admin-post.php' ), '?' );
	$csp  = "default-src 'none'; style-src 'unsafe-inline' " . ( $show_images ? '*' : '' ) . '; img-src data: ' . $part . ( $show_images ? ' https: http:' : '' ) . '; font-src ' . ( $show_images ? '*' : "'none'" ) . "; form-action 'none'; base-uri 'none'";
	return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="' . esc_attr( $csp ) . '"><base target="_blank"><style>html,body{margin:0;padding:0}body{font:14px/1.5 Arial,Helvetica,sans-serif;color:#222;word-wrap:break-word;overflow-wrap:anywhere}img{max-width:100%;height:auto}table{max-width:100%}.jem-hide-quote .gmail_quote,.jem-hide-quote blockquote[type=cite],.jem-hide-quote .yahoo_quoted,.jem-hide-quote #appendonsend~*,.jem-hide-quote #divRplyFwdMsg,.jem-hide-quote #divRplyFwdMsg~*{display:none!important}</style></head><body class="jem-hide-quote">' . $html . '</body></html>';
}

/** Rozdelenie čistého textu na vlastný text a citovanú predošlú správu. */
function jem_split_quote( $text ) {
	$lines = explode( "\n", str_replace( "\r", '', (string) $text ) );
	$cut   = null;
	foreach ( $lines as $i => $l ) {
		if ( preg_match( '/^\s*(On |Dňa |Dna |Am |Le |\d{1,2}\. ?\d{1,2}\. ?\d{4}).{0,200}(wrote|napísal|napisal|schrieb|a écrit)\(?a?\)?:\s*$/iu', $l ) || preg_match( '/^-{5,}\s*(Original Message|Pôvodná správa|Preposlaná správa|Forwarded message)/iu', $l ) ) {
			$cut = $i;
			break;
		}
		if ( preg_match( '/^>/', $l ) ) {
			$rest = array_slice( $lines, $i );
			if ( count( array_filter( $rest, fn( $x ) => '' !== trim( $x ) && ! preg_match( '/^>/', $x ) ) ) <= 1 ) {
				$cut = $i;
				break;
			}
		}
	}
	if ( null === $cut || $cut < 1 ) {
		return array( trim( $text ), '' );
	}
	return array( rtrim( implode( "\n", array_slice( $lines, 0, $cut ) ) ), implode( "\n", array_slice( $lines, $cut ) ) );
}

/** Dôveryhodní odosielatelia (obrázky sa zobrazujú automaticky). */
function jem_trusted( $email ) {
	$email = strtolower( trim( (string) $email ) );
	foreach ( preg_split( '/[\s,;]+/', strtolower( (string) jem_opt( 'trusted' ) ) ) as $t ) {
		if ( '' !== $t && ( $t === $email || ( '@' === $t[0] && str_ends_with( $email, $t ) ) ) ) {
			return true;
		}
	}
	return false;
}

/** Jedna správa pripravená na zobrazenie. */
function jem_message_view( array $p, $key, $uid, array $flags, $show_img ) {
	$h       = $p['headers'];
	$from    = jem_addresses( $h['from'] ?? '' );
	$from    = $from ? $from[0] : array( '', '' );
	$mine    = jem_my_addresses();
	$atts    = array();
	foreach ( $p['attachments'] as $i => $a ) {
		if ( $a['inline'] && '' !== $a['cid'] && '' !== trim( $p['html'] ) ) {
			continue; // obrázok vložený v texte
		}
		$img    = (bool) preg_match( '#^image/(png|jpe?g|gif|webp)$#', $a['type'] );
		$atts[] = array(
			'i'    => $i,
			'name' => $a['name'],
			'type' => $a['type'],
			'size' => $a['size'],
			'url'  => jem_part_url( $key, $uid, $i ),
			'view' => $img || 'application/pdf' === $a['type'] ? jem_part_url( $key, $uid, $i, true ) : '',
			'img'  => $img,
		);
	}
	$show    = $show_img || jem_trusted( $from[1] );
	$html    = trim( $p['html'] );
	$remote  = '' !== $html && (bool) preg_match( '/<img[^>]+src\s*=\s*["\']?https?:|url\(\s*["\']?https?:/i', $html );
	$out     = array(
		'k'       => $key,
		'u'       => $uid,
		'from'    => array( 'n' => $from[0], 'e' => $from[1], 'me' => isset( $mine[ strtolower( $from[1] ) ] ) ),
		'to'      => jem_addresses( $h['to'] ?? '' ),
		'cc'      => jem_addresses( $h['cc'] ?? '' ),
		'replyTo' => jem_addresses( $h['reply-to'] ?? '' ),
		'subject' => jem_decode_header( $h['subject'] ?? '' ),
		'date'    => isset( $h['date'] ) ? jem_date_ts( $h['date'] ) : 0,
		'id'      => trim( $h['message-id'] ?? '' ),
		'unread'  => ! in_array( '\\Seen', $flags, true ),
		'starred' => in_array( '\\Flagged', $flags, true ),
		'atts'    => $atts,
		'snippet' => jem_snippet( 'Content-Type: text/plain; charset=utf-8', '' !== trim( $p['text'] ) ? $p['text'] : jem_html_to_text( $html ) ),
	);
	if ( '' !== $html ) {
		$out['doc']     = jem_html_doc( $html, $p['attachments'], $key, $uid, $show );
		$out['blocked'] = $remote && ! $show;
	} else {
		list( $main, $quote ) = jem_split_quote( $p['text'] );
		$out['text']          = $main;
		$out['quote']         = $quote;
	}
	return $out;
}

add_action( 'wp_ajax_jem_thread', function () {
	$members = jem_members( jem_in( 'members', '[]' ) );
	$img     = array_map( 'strval', (array) json_decode( (string) jem_in( 'img', '[]' ), true ) ); // „k:uid“ so zobrazenými obrázkami
	$mark    = '1' === (string) jem_in( 'mark', '1' );
	$imap    = jem_api_begin();
	$msgs    = array();
	foreach ( $members as $k => $uids ) {
		$folder = jem_resolve_folder( $imap, $k );
		if ( '' === $folder || false === $imap->select( $folder, ! $mark ) ) {
			continue;
		}
		$unseen = array();
		foreach ( array_slice( $uids, -60 ) as $u ) {
			$m = $imap->message( $u );
			if ( ! $m ) {
				continue;
			}
			$v = jem_message_view( jem_parse_message( $m['raw'] ), $k, $u, $m['flags'], in_array( $k . ':' . $u, $img, true ) || in_array( 'all', $img, true ) );
			if ( $v['unread'] ) {
				$unseen[] = $u;
			}
			$msgs[] = $v;
		}
		if ( $mark && $unseen ) {
			$imap->store( $unseen, '\\Seen', true );
		}
	}
	usort( $msgs, fn( $a, $b ) => $a['date'] <=> $b['date'] );
	$c = jem_counts( $imap );
	$imap->close();
	if ( ! $msgs ) {
		wp_send_json_error( array( 'msg' => 'Konverzácia sa nenašla – možno bola presunutá. Obnovte zoznam.' ) );
	}
	wp_send_json_success( array( 'messages' => $msgs, 'counts' => $c ) );
} );

/* ---------- Akcie: prečítané, hviezdička, dôležité, archív, kôš, spam, presun, odloženie ---------- */
add_action( 'wp_ajax_jem_act', function () {
	$op      = sanitize_key( (string) jem_in( 'op' ) );
	$members = jem_members( jem_in( 'members', '[]' ) );
	$target  = sanitize_text_field( (string) jem_in( 'target', '' ) );
	$imap    = jem_api_begin();
	$errors  = array();
	$done    = 0;
	$moveto  = function ( $k ) use ( $imap ) {
		return jem_resolve_folder( $imap, $k, true );
	};
	if ( 'snooze' === $op ) {
		$until = jem_snooze_time( sanitize_key( (string) jem_in( 'when' ) ), sanitize_text_field( (string) jem_in( 'custom' ) ) );
		if ( ! $until ) {
			wp_send_json_error( array( 'msg' => 'Zvoľte čas v budúcnosti.' ) );
		}
	}
	foreach ( $members as $k => $uids ) {
		$folder = jem_resolve_folder( $imap, $k );
		if ( '' === $folder || false === $imap->select( $folder ) ) {
			continue;
		}
		$ok = true;
		switch ( $op ) {
			case 'read':
			case 'unread':
				$ok = $imap->store( $uids, '\\Seen', 'read' === $op );
				break;
			case 'star':
			case 'unstar':
				$ok = $imap->store( $uids, '\\Flagged', 'star' === $op );
				break;
			case 'important':
			case 'unimportant':
				if ( ! $imap->allows_keywords() ) {
					$errors[] = 'Schránka nepodporuje označenie Dôležité.';
					$ok       = false;
					break;
				}
				$ok = $imap->store( $uids, JEM_IMPORTANT, 'important' === $op );
				break;
			case 'archive':
				if ( in_array( $k, array( 'inbox', 'snoozed', 'spam' ), true ) || 0 === strpos( $k, 'f:' ) ) {
					$to = $moveto( 'archive' );
					$ok = '' !== $to && $imap->move_many( $uids, $to );
				}
				break;
			case 'inbox':
				if ( 'inbox' !== $k && 'sent' !== $k ) {
					$ok = $imap->move_many( $uids, 'INBOX' );
				}
				break;
			case 'trash':
				if ( 'trash' === $k ) {
					$ok = $imap->expunge_uids( $uids );
				} else {
					$to = jem_resolve_folder( $imap, 'trash' );
					$ok = '' !== $to && $imap->move_many( $uids, $to );
				}
				break;
			case 'forever':
				$ok = $imap->expunge_uids( $uids );
				break;
			case 'spam':
				if ( 'spam' !== $k && 'sent' !== $k ) {
					$to = $moveto( 'spam' );
					$ok = '' !== $to && $imap->move_many( $uids, $to );
				}
				break;
			case 'notspam':
				if ( 'spam' === $k ) {
					$ok = $imap->store( $uids, '\\Seen', true ) && $imap->move_many( $uids, 'INBOX' );
				}
				break;
			case 'move':
				$to = jem_valid_key( $target ) ? jem_resolve_folder( $imap, $target, true ) : '';
				if ( '' === $to ) {
					$errors[] = 'Cieľový priečinok neexistuje.';
					$ok       = false;
				} elseif ( $to !== $folder && 'sent' !== $k ) {
					$ok = $imap->move_many( $uids, $to );
				}
				break;
			case 'snooze':
				if ( in_array( $k, array( 'inbox', 'archive' ), true ) || 0 === strpos( $k, 'f:' ) ) {
					$meta = jem_folder_meta( $imap, $folder, $uids );
					$to   = $moveto( 'snoozed' );
					if ( '' === $to ) {
						$ok = false;
						break;
					}
					// Odložiť sa dá len správa s Message-ID (podľa neho sa nájde pri návrate).
					$all  = jem_snoozed();
					$with = array();
					foreach ( $meta as $mu => $m ) {
						if ( '' !== $m['id'] ) {
							$all[ $m['id'] ] = array( 'until' => $until, 'from' => $k );
							$with[]          = $mu;
						}
					}
					if ( ! $with ) {
						$errors[] = 'Túto správu nemožno odložiť (chýba jej identifikátor Message-ID).';
						$ok       = false;
						break;
					}
					update_option( 'jem_snoozed', $all, false );
					$ok = $imap->move_many( $with, $to );
				}
				break;
			case 'unsnooze':
				if ( 'snoozed' === $k ) {
					$ids = array_values( array_filter( wp_list_pluck( jem_folder_meta( $imap, $folder, $uids ), 'id' ) ) );
					$ok  = jem_process_snoozed( $imap, $ids ) > 0;
				}
				break;
			default:
				$errors[] = 'Neznáma akcia.';
				$ok       = false;
		}
		if ( $ok ) {
			$done++;
		} elseif ( '' !== $imap->error ) {
			$errors[] = $imap->error;
		}
	}
	if ( in_array( $op, array( 'move', 'archive', 'spam', 'snooze' ), true ) ) {
		delete_transient( 'jem_folders' );
	}
	$c = jem_counts( $imap );
	$imap->close();
	if ( $errors && ! $done ) {
		wp_send_json_error( array( 'msg' => implode( ' ', array_unique( $errors ) ), 'counts' => $c ) );
	}
	wp_send_json_success( array( 'counts' => $c, 'warn' => $errors ? implode( ' ', array_unique( $errors ) ) : '' ) );
} );

/** Dôverovať odosielateľovi (obrázky vždy zobraziť). */
add_action( 'wp_ajax_jem_trust', function () {
	jem_api_begin( false );
	$e = strtolower( sanitize_email( (string) jem_in( 'email' ) ) );
	if ( ! is_email( $e ) ) {
		wp_send_json_error( array( 'msg' => 'Neplatná adresa.' ) );
	}
	$s            = jem_settings();
	$list         = array_filter( preg_split( '/[\s,;]+/', (string) $s['trusted'] ) );
	$list[]       = $e;
	$s['trusted'] = implode( "\n", array_unique( $list ) );
	update_option( 'jem_settings', $s, false );
	wp_send_json_success();
} );

/* ---------- Písanie: údaje pre odpoveď / preposlanie, odoslanie, koncepty, kontakty ---------- */

/** Predvyplnenie odpovede alebo preposlania. */
add_action( 'wp_ajax_jem_compose_data', function () {
	$k    = (string) jem_in( 'k' );
	$uid  = (int) jem_in( 'u' );
	$mode = sanitize_key( (string) jem_in( 'mode', 'reply' ) );
	if ( ! jem_valid_key( $k ) || $uid < 1 ) {
		jem_api_begin( false );
		wp_send_json_error( array( 'msg' => 'Neplatná správa.' ) );
	}
	$imap   = jem_api_begin();
	$folder = jem_resolve_folder( $imap, $k );
	$m      = '' !== $folder && false !== $imap->select( $folder, true ) ? $imap->message( $uid ) : false;
	$imap->close();
	if ( ! $m ) {
		wp_send_json_error( array( 'msg' => 'Správa sa nenašla.' ) );
	}
	$p    = jem_parse_message( $m['raw'] );
	$h    = $p['headers'];
	$mine = jem_my_addresses();
	$subj = jem_decode_header( $h['subject'] ?? '' );
	$date = isset( $h['date'] ) && jem_date_ts( $h['date'] ) ? wp_date( 'j. n. Y H:i', jem_date_ts( $h['date'] ) ) : '';
	$from = jem_decode_header( $h['from'] ?? '' );
	// Citovaný obsah: HTML (bezpečne prečistené) alebo text.
	if ( '' !== trim( $p['html'] ) ) {
		$body = preg_replace( '#<(style|head|title|script)\b.*?</\1>#is', '', $p['html'] );
		$body = wp_kses_post( $body );
		// Obrázky z internetu a vložené (cid:) sa do citácie nedávajú – v administrácii by sa načítali (sledovanie).
		$body = preg_replace( '#<img\b[^>]*>#i', '', $body );
	} else {
		$body = nl2br( esc_html( $p['text'] ) );
	}
	// Od: adresa, na ktorú správa prišla (alias), inak hlavná.
	$use_from = '';
	foreach ( jem_addresses( ( $h['to'] ?? '' ) . ',' . ( $h['cc'] ?? '' ) . ',' . ( $h['delivered-to'] ?? '' ) ) as $a ) {
		if ( isset( $mine[ strtolower( $a[1] ) ] ) ) {
			$use_from = strtolower( $a[1] );
			break;
		}
	}
	$fromlist = jem_addresses( $h['from'] ?? '' );
	$is_mine  = $fromlist && isset( $mine[ strtolower( $fromlist[0][1] ) ] );
	$fmt      = fn( $a ) => '' !== $a[0] ? $a[0] . ' <' . $a[1] . '>' : $a[1];
	$out      = array( 'from' => $use_from, 'to' => '', 'cc' => '', 'subject' => '', 'quote' => '', 'atts' => array() );
	if ( 'forward' === $mode ) {
		$out['subject'] = preg_match( '/^(fwd?|pd|preposlané):/iu', $subj ) ? $subj : 'Fwd: ' . $subj;
		$out['quote']   = '<div class="jem-fwd"><br>---------- Preposlaná správa ----------<br>Od: ' . esc_html( $from ) . '<br>Dátum: ' . esc_html( $date ) . '<br>Predmet: ' . esc_html( $subj ) . '<br>Komu: ' . esc_html( jem_decode_header( $h['to'] ?? '' ) ) . '<br><br>' . $body . '</div>';
		foreach ( $p['attachments'] as $i => $a ) {
			if ( ! ( $a['inline'] && '' !== $a['cid'] ) ) {
				$out['atts'][] = array( 'i' => $i, 'name' => $a['name'], 'size' => $a['size'] );
			}
		}
	} else {
		if ( $is_mine ) {
			// Odpoveď na vlastnú správu ide pôvodným príjemcom.
			$to = array_map( $fmt, jem_addresses( $h['to'] ?? '' ) );
		} else {
			$rt = jem_addresses( ! empty( $h['reply-to'] ) ? $h['reply-to'] : ( $h['from'] ?? '' ) );
			$to = array_map( $fmt, $rt );
		}
		$out['to'] = implode( ', ', $to );
		if ( 'replyall' === $mode ) {
			$cc = array();
			foreach ( jem_addresses( ( $h['to'] ?? '' ) . ',' . ( $h['cc'] ?? '' ) ) as $a ) {
				if ( ! isset( $mine[ strtolower( $a[1] ) ] ) && false === stripos( $out['to'], $a[1] ) ) {
					$cc[] = $fmt( $a );
				}
			}
			$out['cc'] = implode( ', ', array_unique( $cc ) );
		}
		$out['subject'] = preg_match( '/^re:/i', $subj ) ? $subj : 'Re: ' . $subj;
		$out['quote']   = '<div class="jem-quote"><br>' . esc_html( $date . ', ' . $from ) . ' napísal(a):<br><blockquote style="margin:0 0 0 .8ex;border-left:1px solid #ccc;padding-left:1ex">' . $body . '</blockquote></div>';
	}
	wp_send_json_success( $out );
} );

add_action( 'wp_ajax_jem_send', function () {
	$imap = jem_api_begin();
	$line = function ( $v ) {
		return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_check_invalid_utf8( (string) $v ) ) );
	};
	$html = (string) jem_in( 'html' );
	$html = wp_kses_post( wp_check_invalid_utf8( $html ) );
	$mode = sanitize_key( (string) jem_in( 'mode', 'new' ) );
	$rk   = (string) jem_in( 'ref_k' );
	$ru   = (int) jem_in( 'ref_u' );
	$d    = array(
		'from'    => sanitize_email( (string) jem_in( 'from' ) ),
		'to'      => $line( jem_in( 'to' ) ),
		'cc'      => $line( jem_in( 'cc' ) ),
		'bcc'     => $line( jem_in( 'bcc' ) ),
		'subject' => $line( jem_in( 'subject' ) ),
		'html'    => '' !== trim( wp_strip_all_tags( $html ) ) || false !== stripos( $html, '<img' ) ? $html : '',
		'body'    => '',
		'files'   => $_FILES['files'] ?? array(), // phpcs:ignore
	);
	if ( '' === $d['html'] ) {
		$d['body'] = ' ';
	}
	// Odpoveď / preposlanie: hlavičky vlákna a prílohy z pôvodnej správy.
	if ( in_array( $mode, array( 'reply', 'replyall', 'forward' ), true ) && jem_valid_key( $rk ) && $ru > 0 ) {
		$folder = jem_resolve_folder( $imap, $rk );
		if ( '' !== $folder && false !== $imap->select( $folder, true ) ) {
			$m = $imap->message( $ru );
			if ( $m ) {
				$p = jem_parse_message( $m['raw'] );
				$h = $p['headers'];
				if ( 'forward' !== $mode ) {
					$d['in_reply_to']  = trim( $h['message-id'] ?? '' );
					$d['references']   = trim( $h['references'] ?? ( $h['in-reply-to'] ?? '' ) );
					$d['reply_uid']    = $ru;
					$d['reply_folder'] = $folder;
				} else {
					$want = array_map( 'intval', (array) json_decode( (string) jem_in( 'fwd_atts', '[]' ), true ) );
					foreach ( $p['attachments'] as $i => $a ) {
						if ( in_array( $i, $want, true ) ) {
							$d['att_data'][] = array( $a['data'], $a['name'], $a['type'] );
						}
					}
				}
			}
		}
	}
	$imap->close();
	$r = jem_send( $d );
	if ( true !== $r ) {
		wp_send_json_error( array( 'msg' => $r ) );
	}
	$draft = sanitize_key( (string) jem_in( 'draft' ) );
	if ( '' !== $draft ) {
		$all = jem_drafts();
		unset( $all[ $draft ] );
		jem_drafts_save( $all );
	}
	delete_transient( 'jem_contacts' );
	wp_send_json_success( array( 'drafts' => count( jem_drafts() ) ) );
} );

/** Koncept: uložiť (vráti ID) alebo zmazať. */
add_action( 'wp_ajax_jem_draft', function () {
	jem_api_begin( false );
	$all = jem_drafts();
	$id  = sanitize_key( (string) jem_in( 'id' ) );
	if ( 'delete' === jem_in( 'op' ) ) {
		unset( $all[ $id ] );
		jem_drafts_save( $all );
		wp_send_json_success( array( 'drafts' => count( $all ) ) );
	}
	if ( '' === $id || ! isset( $all[ $id ] ) ) {
		$id = strtolower( wp_generate_password( 12, false ) );
	}
	// Adresy „Meno <adresa>“ – sanitize_text_field by časť <…> zmazal ako HTML značku.
	$addr       = function ( $v ) {
		return mb_substr( trim( preg_replace( '/[\r\n\t]+/', ' ', wp_check_invalid_utf8( (string) $v ) ) ), 0, 2000 );
	};
	$all[ $id ] = array(
		'from'     => sanitize_email( (string) jem_in( 'from' ) ),
		'to'       => $addr( jem_in( 'to' ) ),
		'cc'       => $addr( jem_in( 'cc' ) ),
		'bcc'      => $addr( jem_in( 'bcc' ) ),
		'subject'  => sanitize_text_field( (string) jem_in( 'subject' ) ),
		'html'     => wp_kses_post( (string) jem_in( 'html' ) ),
		'mode'     => sanitize_key( (string) jem_in( 'mode', 'new' ) ),
		'ref_k'    => jem_valid_key( jem_in( 'ref_k' ) ) ? (string) jem_in( 'ref_k' ) : '',
		'ref_u'    => (int) jem_in( 'ref_u' ),
		'fwd_atts' => array_map( 'intval', (array) json_decode( (string) jem_in( 'fwd_atts', '[]' ), true ) ),
		'updated'  => time(),
	);
	if ( count( $all ) > 100 ) {
		uasort( $all, fn( $a, $b ) => $b['updated'] <=> $a['updated'] );
		$all = array_slice( $all, 0, 100, true );
	}
	jem_drafts_save( $all );
	wp_send_json_success( array( 'id' => $id, 'drafts' => count( $all ) ) );
} );

add_action( 'wp_ajax_jem_draft_get', function () {
	jem_api_begin( false );
	$all = jem_drafts();
	$id  = sanitize_key( (string) jem_in( 'id' ) );
	if ( ! isset( $all[ $id ] ) ) {
		wp_send_json_error( array( 'msg' => 'Koncept sa nenašiel.' ) );
	}
	wp_send_json_success( array_merge( $all[ $id ], array( 'id' => $id ) ) );
} );

add_action( 'wp_ajax_jem_contacts', function () {
	$c = get_transient( 'jem_contacts' );
	if ( is_array( $c ) ) {
		jem_api_begin( false );
		wp_send_json_success( $c );
	}
	$imap = jem_api_begin();
	$c    = jem_contacts( $imap );
	$imap->close();
	wp_send_json_success( $c );
} );

/** Počty neprečítaných (pravidelná kontrola novej pošty). */
add_action( 'wp_ajax_jem_counts', function () {
	$imap = jem_api_begin();
	$c    = jem_counts( $imap );
	$imap->close();
	wp_send_json_success( array( 'counts' => $c ) );
} );
