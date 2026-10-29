<?php
/**
 * Jupiter Email – schránka: priečinky, vyrovnávacia pamäť hlavičiek, konverzácie (vlákna), odložené správy.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const JEM_WINDOW_MAX = 1500; // najviac správ na priečinok, z ktorých sa skladajú konverzácie
const JEM_IMPORTANT  = '$Important';

/** Systémové priečinky a ich názvy v menu (poradie ako v Gmaile). */
function jem_system_folders() {
	return array(
		'inbox'     => 'Doručené',
		'starred'   => 'S hviezdičkou',
		'snoozed'   => 'Odložené',
		'important' => 'Dôležité',
		'sent'      => 'Odoslané',
		'drafts'    => 'Koncepty',
		'archive'   => 'Archív',
		'spam'      => 'Spam',
		'trash'     => 'Kôš',
	);
}

/** Moje adresy: schránka + aliasy (malé písmená) → meno. */
function jem_my_addresses() {
	$s   = jem_settings();
	$out = array();
	if ( '' !== $s['email'] ) {
		$out[ strtolower( $s['email'] ) ] = (string) $s['name'];
	}
	foreach ( jem_addresses( str_replace( array( "\r", "\n", ';' ), ',', (string) ( $s['aliases'] ?? '' ) ) ) as $a ) {
		if ( is_email( $a[1] ) && ! isset( $out[ strtolower( $a[1] ) ] ) ) {
			$out[ strtolower( $a[1] ) ] = '' !== $a[0] ? $a[0] : (string) $s['name'];
		}
	}
	return $out;
}

/**
 * Názov priečinka v schránke pre kľúč (inbox, sent, trash, archive, spam, snoozed, alebo „f:Názov“).
 * $create = vytvoriť priečinok, ak chýba (Archív, Spam, Odložené).
 */
function jem_resolve_folder( JEM_Imap $imap, $key, $create = false ) {
	$map = jem_folder_map( $imap );
	if ( 0 === strpos( (string) $key, 'f:' ) ) {
		$name = substr( $key, 2 );
		return in_array( $name, (array) ( $map['all'] ?? array() ), true ) ? $name : '';
	}
	if ( isset( $map[ $key ] ) && '' !== $map[ $key ] ) {
		return $map[ $key ];
	}
	if ( ! $create || ! in_array( $key, array( 'archive', 'spam', 'snoozed' ), true ) ) {
		return '';
	}
	$names = array( 'archive' => 'Archive', 'spam' => 'Spam', 'snoozed' => 'Snoozed' );
	$all   = (array) ( $map['all'] ?? array() );
	$sep   = '.';
	foreach ( $all as $n ) {
		if ( 0 === stripos( $n, 'INBOX/' ) ) {
			$sep = '/';
		}
	}
	foreach ( array( $names[ $key ], 'INBOX' . $sep . $names[ $key ] ) as $try ) {
		if ( $imap->create( $try ) ) {
			delete_transient( 'jem_folders' );
			$map = jem_folder_map( $imap );
			return '' !== ( $map[ $key ] ?? '' ) ? $map[ $key ] : $try;
		}
	}
	return '';
}

/** Kľúč priečinka podľa jeho názvu (opak jem_resolve_folder). */
function jem_folder_key( JEM_Imap $imap, $name ) {
	foreach ( jem_folder_map( $imap ) as $k => $v ) {
		if ( 'all' !== $k && is_string( $v ) && $v === $name ) {
			return $k;
		}
	}
	return 'f:' . $name;
}

/** Vlastné priečinky schránky (bez systémových) – v menu ako štítky. */
function jem_custom_folders( JEM_Imap $imap ) {
	$map    = jem_folder_map( $imap );
	$system = array();
	foreach ( $map as $k => $v ) {
		if ( 'all' !== $k && is_string( $v ) && '' !== $v ) {
			$system[] = strtolower( $v );
		}
	}
	$out = array();
	foreach ( (array) ( $map['all'] ?? array() ) as $name ) {
		if ( in_array( strtolower( $name ), $system, true ) || 'inbox' === strtolower( $name ) || in_array( strtolower( $name ), array( 'drafts', 'inbox.drafts', 'inbox/drafts', 'koncepty' ), true ) ) {
			continue;
		}
		$out[] = $name;
	}
	natcasesort( $out );
	return array_values( $out );
}

/* ---------- Vyrovnávacia pamäť hlavičiek (hlavičky správy sa nemenia, príznaky sa čítajú vždy nanovo) ---------- */

function jem_cache_key( $folder, $validity ) {
	return 'jem_hc_' . md5( $folder . '|' . (int) $validity );
}

/** Kompaktné údaje správy pre zoznam. */
function jem_meta_from_fetch( array $it ) {
	$h     = jem_parse_headers( $it['headers'] );
	$from  = jem_addresses( $h['from'] ?? '' );
	$date  = isset( $h['date'] ) ? jem_date_ts( $h['date'] ) : 0;
	$refs  = jem_msgids( ( $h['references'] ?? '' ) . ' ' . ( $h['in-reply-to'] ?? '' ) );
	$ids   = jem_msgids( $h['message-id'] ?? '' );
	$files = array_slice( (array) $it['files'], 0, 8 );
	$mixed = 0 === stripos( $h['content-type'] ?? '', 'multipart/mixed' );
	return array(
		'f'  => $from ? $from[0] : array( '', '' ),
		't'  => array_slice( jem_addresses( $h['to'] ?? '' ), 0, 20 ),
		'c'  => array_slice( jem_addresses( $h['cc'] ?? '' ), 0, 20 ),
		's'  => jem_decode_header( $h['subject'] ?? '' ),
		'd'  => $date ? $date : (int) $it['date'],
		'id' => $ids ? $ids[0] : '',
		'r'  => array_slice( $refs, -20 ),
		'sn' => jem_snippet( $it['headers'], $it['text'] ),
		'fl' => $files,
		'a'  => $files || $mixed,
		'sz' => (int) $it['size'],
	);
}

/**
 * Údaje správ vo vybranom priečinku (z pamäte, chýbajúce sa načítajú).
 *
 * @return array<int,array> uid → meta
 */
function jem_folder_meta( JEM_Imap $imap, $folder, array $uids ) {
	$key   = jem_cache_key( $folder, $imap->uidvalidity );
	$cache = get_transient( $key );
	$cache = is_array( $cache ) ? $cache : array();
	$miss  = array_values( array_diff( $uids, array_keys( $cache ) ) );
	if ( $miss ) {
		foreach ( $imap->list_data( $miss ) as $uid => $it ) {
			$cache[ $uid ] = jem_meta_from_fetch( $it );
		}
		if ( count( $cache ) > JEM_WINDOW_MAX + 200 ) {
			krsort( $cache );
			$cache = array_slice( $cache, 0, JEM_WINDOW_MAX, true );
		}
		set_transient( $key, $cache, 30 * DAY_IN_SECONDS );
	}
	$out = array();
	foreach ( $uids as $u ) {
		if ( isset( $cache[ $u ] ) ) {
			$out[ $u ] = $cache[ $u ];
		}
	}
	return $out;
}

/** IMAP kritérium hľadania s literálom pre diakritiku. Vráti [kritérium, literály]. */
function jem_search_criteria( $q ) {
	$q = trim( (string) $q );
	if ( '' === $q ) {
		return array( 'ALL', array() );
	}
	if ( JEM_Imap::needs_literal( $q ) ) {
		return array( "CHARSET UTF-8 TEXT \x01", array( $q ) );
	}
	return array( 'TEXT ' . JEM_Imap::q( $q ), array() );
}

/**
 * Správy z jedného priečinka pre zoznam: [ [k, uid, meta, flags], … ] – najnovšie, najviac $limit.
 */
function jem_collect( JEM_Imap $imap, $key, $criteria, array $lits, $limit ) {
	$folder = jem_resolve_folder( $imap, $key );
	if ( '' === $folder || false === $imap->select( $folder, true ) ) {
		return array( 'items' => array(), 'more' => false );
	}
	$uids  = $imap->uids( $criteria, $lits );
	$more  = count( $uids ) > $limit;
	$uids  = array_slice( $uids, 0, $limit );
	$flags = $imap->flags_of( $uids );
	$meta  = jem_folder_meta( $imap, $folder, $uids );
	$items = array();
	foreach ( $uids as $u ) {
		if ( isset( $meta[ $u ] ) ) {
			$items[] = array( 'k' => $key, 'u' => $u, 'm' => $meta[ $u ], 'fl' => $flags[ $u ] ?? array() );
		}
	}
	return array( 'items' => $items, 'more' => $more );
}

/**
 * Konverzácie: správy spojené podľa Message-ID / References a podľa predmetu (Re:, Fwd:).
 *
 * @param array $items  správy zobrazeného priečinka
 * @param array $extra  ďalšie správy (napr. Odoslané) – pridajú sa len do konverzácií, ktoré už v zozname sú
 * @return array zoznam konverzácií od najnovšej
 */
function jem_threads( array $items, array $extra = array(), $threaded = true ) {
	$all    = array_merge( $items, $extra );
	$n      = count( $all );
	$parent = range( 0, max( 0, $n - 1 ) );
	$find   = function ( $i ) use ( &$parent ) {
		while ( $parent[ $i ] !== $i ) {
			$parent[ $i ] = $parent[ $parent[ $i ] ];
			$i            = $parent[ $i ];
		}
		return $i;
	};
	$union = function ( $a, $b ) use ( &$parent, $find ) {
		$a = $find( $a );
		$b = $find( $b );
		if ( $a !== $b ) {
			$parent[ max( $a, $b ) ] = min( $a, $b );
		}
	};
	if ( $threaded ) {
		$by_id = array();
		foreach ( $all as $i => $it ) {
			foreach ( array_merge( array( $it['m']['id'] ), $it['m']['r'] ) as $id ) {
				if ( '' === $id ) {
					continue;
				}
				if ( isset( $by_id[ $id ] ) ) {
					$union( $i, $by_id[ $id ] );
				} else {
					$by_id[ $id ] = $i;
				}
			}
		}
		// Rovnaký predmet s Re:/Fwd: (niektoré programy nepošlú References).
		$by_subj = array();
		foreach ( $all as $i => $it ) {
			$ns = jem_norm_subject( $it['m']['s'] );
			if ( mb_strlen( $ns ) < 4 ) {
				continue;
			}
			$is_reply = $ns !== mb_strtolower( trim( $it['m']['s'] ) );
			if ( isset( $by_subj[ $ns ] ) ) {
				$j = $by_subj[ $ns ];
				if ( ( $is_reply || $all[ $j ]['reply'] ) && abs( $it['m']['d'] - $all[ $j ]['m']['d'] ) < 120 * DAY_IN_SECONDS ) {
					$union( $i, $j );
				}
			} else {
				$by_subj[ $ns ] = $i;
			}
			$all[ $i ]['reply'] = $is_reply;
		}
	}
	$groups = array();
	$count  = count( $items );
	foreach ( $all as $i => $it ) {
		$groups[ $find( $i ) ][] = $i;
	}
	$mine    = jem_my_addresses();
	$threads = array();
	foreach ( $groups as $members ) {
		$own = array_filter( $members, fn( $i ) => $i < $count ); // aspoň jedna správa zo zobrazeného priečinka
		if ( ! $own ) {
			continue;
		}
		usort( $members, fn( $a, $b ) => $all[ $a ]['m']['d'] <=> $all[ $b ]['m']['d'] );
		$by_folder = array();
		$people    = array();
		$unread    = false;
		$star      = false;
		$imp       = false;
		$files     = array();
		$att       = false;
		$answered  = false;
		$own_set   = array_flip( $own );
		foreach ( $members as $i ) {
			$it                       = $all[ $i ];
			$by_folder[ $it['k'] ][] = $it['u'];
			$fl                       = $it['fl'];
			$from                     = strtolower( $it['m']['f'][1] );
			$me                       = isset( $mine[ $from ] );
			$label                    = $me ? 'ja' : ( '' !== $it['m']['f'][0] ? $it['m']['f'][0] : $it['m']['f'][1] );
			if ( 'sent' === $it['k'] && ! $me ) {
				$label = 'ja';
				$me    = true;
			}
			$people[]  = array( 'n' => $label, 'me' => $me, 'unread' => isset( $own_set[ $i ] ) && ! in_array( '\\Seen', $fl, true ) );
			$unread    = $unread || ( isset( $own_set[ $i ] ) && ! in_array( '\\Seen', $fl, true ) );
			$star      = $star || in_array( '\\Flagged', $fl, true );
			$imp       = $imp || in_array( JEM_IMPORTANT, $fl, true );
			$answered  = $answered || in_array( '\\Answered', $fl, true );
			$att       = $att || $it['m']['a'];
			$files     = array_merge( $files, $it['m']['fl'] );
		}
		$last  = $all[ end( $members ) ];
		$first = $all[ $members[0] ];
		// Mená ako v Gmaile: odosielatelia v poradí, bez opakovania, najviac 3.
		$names = array();
		foreach ( $people as $p ) {
			$k = $p['n'];
			if ( ! isset( $names[ $k ] ) ) {
				$names[ $k ] = $p;
			} elseif ( $p['unread'] ) {
				$names[ $k ]['unread'] = true;
			}
		}
		$names = array_values( $names );
		if ( count( $names ) > 3 ) {
			$names = array_merge( array( $names[0] ), array_slice( $names, -2 ) );
		}
		// Kto je „na druhej strane“ (pre Odoslané: príjemca).
		$to = '';
		foreach ( array_merge( $last['m']['t'], $first['m']['t'] ) as $a ) {
			if ( ! isset( $mine[ strtolower( $a[1] ) ] ) ) {
				$to = '' !== $a[0] ? $a[0] : $a[1];
				break;
			}
		}
		$latest_own = $all[ max( $own ) ];
		foreach ( $own as $i ) {
			if ( $all[ $i ]['m']['d'] > $latest_own['m']['d'] ) {
				$latest_own = $all[ $i ];
			}
		}
		$members_out = array();
		foreach ( $by_folder as $k => $uids ) {
			$members_out[] = array( 'k' => $k, 'u' => array_values( array_unique( $uids ) ) );
		}
		$threads[] = array(
			'id'       => $latest_own['k'] . ':' . $latest_own['u'],
			'members'  => $members_out,
			'subject'  => '' !== $first['m']['s'] ? $first['m']['s'] : $last['m']['s'],
			'snippet'  => $last['m']['sn'],
			'names'    => $names,
			'to'       => $to,
			'count'    => count( $members ),
			'date'     => $last['m']['d'],
			'unread'   => $unread,
			'starred'  => $star,
			'important' => $imp,
			'answered' => $answered,
			'att'      => $att,
			'files'    => array_slice( array_values( array_unique( $files ) ), 0, 3 ),
			'nfiles'   => count( array_unique( $files ) ),
			'last'     => array( 'k' => $last['k'], 'u' => $last['u'] ),
			'ids'      => array_values( array_filter( array_map( fn( $i ) => $all[ $i ]['m']['id'], $members ) ) ),
		);
	}
	usort( $threads, fn( $a, $b ) => $b['date'] <=> $a['date'] );
	return $threads;
}

/** Zdroje zobrazenia: [ [kľúč priečinka, kritérium, literály], … ] a priečinky na doplnenie konverzácií. */
function jem_view_sources( $view, $q ) {
	list( $crit, $lits ) = jem_search_criteria( $q );
	$and                 = function ( $extra ) use ( $crit ) {
		return 'ALL' === $crit ? $extra : $crit . ' ' . $extra;
	};
	switch ( $view ) {
		case 'starred':
			return array( array( array( 'inbox', $and( 'FLAGGED' ), $lits ), array( 'archive', $and( 'FLAGGED' ), $lits ), array( 'sent', $and( 'FLAGGED' ), $lits ), array( 'snoozed', $and( 'FLAGGED' ), $lits ) ), array() );
		case 'important':
			$kw = 'KEYWORD ' . JEM_IMPORTANT;
			return array( array( array( 'inbox', $and( $kw ), $lits ), array( 'archive', $and( $kw ), $lits ), array( 'sent', $and( $kw ), $lits ), array( 'snoozed', $and( $kw ), $lits ) ), array() );
		case 'search':
			return array( array( array( 'inbox', $crit, $lits ), array( 'archive', $crit, $lits ), array( 'sent', $crit, $lits ), array( 'snoozed', $crit, $lits ) ), array() );
		case 'inbox':
		case 'archive':
			return array( array( array( $view, $crit, $lits ) ), array( 'sent' ) );
		default:
			return array( array( array( $view, $crit, $lits ) ), array() );
	}
}

/**
 * Zoznam konverzácií pre zobrazenie (priečinok, hviezdičky, dôležité, hľadanie).
 *
 * @return array{threads:array,total:int,more:bool}|WP_Error
 */
function jem_list_threads( JEM_Imap $imap, $view, $q, $page, $per ) {
	list( $sources, $context ) = jem_view_sources( $view, $q );
	$limit                     = min( JEM_WINDOW_MAX, max( 200, $page * $per * 3 ) );
	$items                     = array();
	$more                      = false;
	foreach ( $sources as $src ) {
		if ( 'search' === $view || in_array( $view, array( 'starred', 'important' ), true ) ) {
			if ( '' === jem_resolve_folder( $imap, $src[0] ) ) {
				continue;
			}
		}
		$r     = jem_collect( $imap, $src[0], $src[1], $src[2], $limit );
		$items = array_merge( $items, $r['items'] );
		$more  = $more || $r['more'];
	}
	$extra = array();
	foreach ( $context as $ck ) {
		$r     = jem_collect( $imap, $ck, 'ALL', array(), min( 500, $limit ) );
		$extra = array_merge( $extra, $r['items'] );
	}
	$threads = jem_threads( $items, $extra, ! empty( jem_settings()['threads'] ) );
	if ( 'snoozed' === $view ) {
		$sz = jem_snoozed();
		foreach ( $threads as &$t ) {
			foreach ( $t['ids'] as $id ) {
				if ( isset( $sz[ $id ] ) ) {
					$t['snoozed_until'] = (int) $sz[ $id ]['until'];
				}
			}
		}
		unset( $t );
	}
	$total = count( $threads );
	return array(
		'threads' => array_slice( $threads, ( $page - 1 ) * $per, $per ),
		'total'   => $total,
		'more'    => $more,
	);
}

/* ---------- Odložené správy ---------- */

/** Odložené: Message-ID → [until, from]. */
function jem_snoozed() {
	$s = get_option( 'jem_snoozed', array() );
	return is_array( $s ) ? $s : array();
}

/** Vráti odložené správy, ktorým uplynul čas, do Doručenej pošty (volá sa z cronu každých 5 minút). */
function jem_process_snoozed( JEM_Imap $imap, $force_ids = array() ) {
	$all = jem_snoozed();
	$due = array();
	foreach ( $all as $id => $r ) {
		if ( (int) $r['until'] <= time() || in_array( $id, $force_ids, true ) ) {
			$due[] = $id;
		}
	}
	if ( ! $due ) {
		return 0;
	}
	$folder = jem_resolve_folder( $imap, 'snoozed' );
	$moved  = 0;
	if ( '' !== $folder && false !== $imap->select( $folder ) ) {
		$uids = array();
		foreach ( $due as $id ) {
			$found = $imap->uids( 'HEADER Message-ID ' . JEM_Imap::q( $id ) );
			$uids  = array_merge( $uids, $found );
		}
		if ( $uids ) {
			$imap->store( $uids, '\\Seen', false ); // vrátená správa je opäť neprečítaná, ako v Gmaile
			if ( $imap->move_many( $uids, 'INBOX' ) ) {
				$moved = count( $uids );
			}
		}
	}
	foreach ( $due as $id ) {
		unset( $all[ $id ] );
	}
	update_option( 'jem_snoozed', $all, false );
	return $moved;
}

/** Čas odloženia podľa voľby (ako v Gmaile). */
function jem_snooze_time( $choice, $custom = '' ) {
	$tz  = wp_timezone();
	$now = new DateTimeImmutable( 'now', $tz );
	switch ( $choice ) {
		case 'later':
			$t = $now->modify( '+3 hours' );
			return (int) $t->setTime( (int) $t->format( 'H' ), 0 )->getTimestamp();
		case 'tomorrow':
			return (int) $now->modify( '+1 day' )->setTime( 8, 0 )->getTimestamp();
		case 'weekend':
			$sat = 6 === (int) $now->format( 'N' ) || 7 === (int) $now->format( 'N' ) ? $now->modify( 'next saturday' ) : $now->modify( 'saturday this week' );
			return (int) $sat->setTime( 8, 0 )->getTimestamp();
		case 'nextweek':
			return (int) $now->modify( 'next monday' )->setTime( 8, 0 )->getTimestamp();
		case 'custom':
			try {
				$t = new DateTimeImmutable( (string) $custom, $tz );
				return $t->getTimestamp() > time() ? (int) $t->getTimestamp() : 0;
			} catch ( \Throwable $e ) {
				return 0;
			}
	}
	return 0;
}

/* ---------- Koncepty (uložené vo WordPresse pre používateľa) ---------- */

function jem_drafts() {
	$d = get_user_meta( get_current_user_id(), 'jem_drafts', true );
	return is_array( $d ) ? $d : array();
}

function jem_drafts_save( array $d ) {
	update_user_meta( get_current_user_id(), 'jem_drafts', $d );
}

/* ---------- Kontakty pre našepkávanie adries ---------- */

function jem_contacts( JEM_Imap $imap ) {
	$c = get_transient( 'jem_contacts' );
	if ( is_array( $c ) ) {
		return $c;
	}
	$mine  = jem_my_addresses();
	$score = array();
	$names = array();
	foreach ( array( 'sent' => 3, 'inbox' => 1 ) as $k => $w ) {
		$r = jem_collect( $imap, $k, 'ALL', array(), 400 );
		foreach ( $r['items'] as $it ) {
			$list = 'sent' === $k ? array_merge( $it['m']['t'], $it['m']['c'] ) : array( $it['m']['f'] );
			foreach ( $list as $a ) {
				$e = strtolower( trim( $a[1] ) );
				if ( ! is_email( $e ) || isset( $mine[ $e ] ) || preg_match( '/^(no-?reply|mailer-daemon|postmaster)@/i', $e ) ) {
					continue;
				}
				$score[ $e ] = ( $score[ $e ] ?? 0 ) + $w;
				if ( '' !== $a[0] && empty( $names[ $e ] ) ) {
					$names[ $e ] = $a[0];
				}
			}
		}
	}
	arsort( $score );
	$c = array();
	foreach ( array_slice( $score, 0, 500, true ) as $e => $s ) {
		$c[] = array( 'n' => $names[ $e ] ?? '', 'e' => $e );
	}
	set_transient( 'jem_contacts', $c, 6 * HOUR_IN_SECONDS );
	return $c;
}
