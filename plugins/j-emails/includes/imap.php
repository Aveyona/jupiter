<?php
/**
 * Jednoduchý klient IMAP cez šifrované spojenie (bez rozšírenia php-imap, ktoré novšie PHP nemá).
 * Vie: prihlásenie, priečinky, zoznam správ, celá správa, príznaky, presun, uloženie odoslanej správy.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JEM_Imap {

	/** @var resource|null */
	private $fp = null;
	private $n = 0;
	private $caps = array();
	private $selected = null;
	public $error = '';
	/** UIDVALIDITY a trvalé príznaky vybraného priečinka. */
	public $uidvalidity = 0;
	public $permflags = array();

	public function __destruct() {
		$this->close();
	}

	/** Pripojenie a prihlásenie. */
	public function open( $host, $port, $secure, $user, $pass, $timeout = 20 ) {
		$ctx    = stream_context_create( array( 'ssl' => array( 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host ) ) );
		$scheme = 'ssl' === $secure ? 'ssl://' : 'tcp://';
		$errno  = 0;
		$errstr = '';
		$fp     = @stream_socket_client( $scheme . $host . ':' . (int) $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx );
		if ( ! $fp ) {
			$this->error = 'Nepodarilo sa pripojiť k ' . $host . ':' . $port . ( $errstr ? ' (' . $errstr . ')' : '' );
			return false;
		}
		stream_set_timeout( $fp, $timeout );
		$this->fp = $fp;
		$greet    = (string) fgets( $fp );
		if ( 0 !== strpos( $greet, '* OK' ) && 0 !== strpos( $greet, '* PREAUTH' ) ) {
			$this->error = 'Server neodpovedá ako IMAP: ' . trim( $greet );
			$this->close();
			return false;
		}
		if ( 'tls' === $secure ) {
			$r = $this->cmd( 'STARTTLS' );
			if ( ! $r['ok'] || ! stream_socket_enable_crypto( $this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) ) {
				$this->error = 'Šifrovanie STARTTLS sa nepodarilo zapnúť.';
				$this->close();
				return false;
			}
		}
		$lits = array();
		$arg  = function ( $v ) use ( &$lits ) {
			if ( self::needs_literal( $v ) ) {
				$lits[] = (string) $v;
				return "\x01";
			}
			return self::q( $v );
		};
		$r = $this->cmd( 'LOGIN ' . $arg( $user ) . ' ' . $arg( $pass ), $lits );
		if ( ! $r['ok'] ) {
			$this->error = 'Prihlásenie do schránky zlyhalo: ' . $r['text'];
			$this->close();
			return false;
		}
		$this->capabilities();
		return true;
	}

	public function close() {
		if ( $this->fp ) {
			@fwrite( $this->fp, 'Z' . ( ++$this->n ) . " LOGOUT\r\n" );
			@fclose( $this->fp );
		}
		$this->fp = null;
	}

	/** Treba reťazec poslať ako literál {n}? (diakritika a iné znaky mimo ASCII, koniec riadka) */
	public static function needs_literal( $s ) {
		return (bool) preg_match( '/[\x00\r\n\x80-\xff]/', (string) $s );
	}

	/** Reťazec v úvodzovkách podľa IMAP. */
	public static function q( $s ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $s ) . '"';
	}

	/**
	 * Príkaz a odpoveď. Literály {n} sa načítajú celé a v riadku sú nahradené značkou \x00n\x00.
	 *
	 * @return array{ok:bool,text:string,untagged:array<int,array{line:string,lits:array}>}
	 */
	public function cmd( $command, array $lits = array() ) {
		$out = array( 'ok' => false, 'text' => '', 'untagged' => array() );
		if ( ! $this->fp ) {
			$out['text'] = 'Nie je pripojenie.';
			return $out;
		}
		$tag = 'J' . ( ++$this->n );
		// Literály: v príkaze značka \x01, server po {n} potvrdí „+“ a až potom príjme údaje.
		$parts = explode( "\x01", $tag . ' ' . $command );
		foreach ( $parts as $i => $part ) {
			if ( $i >= count( $parts ) - 1 ) {
				fwrite( $this->fp, $part . "\r\n" );
				break;
			}
			$lit = (string) ( $lits[ $i ] ?? '' );
			fwrite( $this->fp, $part . '{' . strlen( $lit ) . "}\r\n" );
			// Čakanie na „+“; nevyžiadané riadky „* …“ sa uložia, záverečná odpoveď príkazu sa prečíta celá.
			while ( true ) {
				$resp = $this->read_response();
				if ( null === $resp ) {
					$out['text'] = 'Spojenie so serverom sa prerušilo.';
					return $out;
				}
				if ( 0 === strpos( $resp['line'], '+' ) ) {
					break;
				}
				if ( 0 === strpos( $resp['line'], $tag . ' ' ) ) {
					$out['text'] = trim( preg_replace( '/^(OK|NO|BAD)\s*(\[[^\]]*\]\s*)?/i', '', substr( $resp['line'], strlen( $tag ) + 1 ) ) );
					return $out;
				}
				$out['untagged'][] = $resp;
			}
			fwrite( $this->fp, $lit );
		}
		while ( true ) {
			$resp = $this->read_response();
			if ( null === $resp ) {
				$out['text'] = 'Spojenie so serverom sa prerušilo.';
				return $out;
			}
			$line = $resp['line'];
			if ( 0 === strpos( $line, $tag . ' ' ) ) {
				$rest        = substr( $line, strlen( $tag ) + 1 );
				$out['ok']   = 0 === stripos( $rest, 'OK' );
				$out['text'] = trim( preg_replace( '/^(OK|NO|BAD)\s*(\[[^\]]*\]\s*)?/i', '', $rest ) );
				return $out;
			}
			$out['untagged'][] = $resp;
		}
	}

	/** Jedna odpoveď servera aj s literálmi. */
	private function read_response() {
		$line = '';
		$lits = array();
		while ( true ) {
			$l = fgets( $this->fp );
			if ( false === $l ) {
				return null;
			}
			if ( preg_match( '/\{(\d+)\}\r\n$/', $l, $m ) ) {
				$len  = (int) $m[1];
				$data = '';
				while ( strlen( $data ) < $len ) {
					$chunk = fread( $this->fp, min( 65536, $len - strlen( $data ) ) );
					if ( false === $chunk || '' === $chunk ) {
						$meta = stream_get_meta_data( $this->fp );
						if ( ! empty( $meta['timed_out'] ) || feof( $this->fp ) ) {
							return null;
						}
						continue;
					}
					$data .= $chunk;
				}
				$line  .= substr( $l, 0, -strlen( $m[0] ) ) . "\x00" . count( $lits ) . "\x00";
				$lits[] = $data;
				continue;
			}
			$line .= rtrim( $l, "\r\n" );
			return array( 'line' => $line, 'lits' => $lits );
		}
	}

	private function capabilities() {
		$r = $this->cmd( 'CAPABILITY' );
		foreach ( $r['untagged'] as $u ) {
			if ( 0 === stripos( $u['line'], '* CAPABILITY ' ) ) {
				$this->caps = array_map( 'strtoupper', preg_split( '/\s+/', trim( substr( $u['line'], 13 ) ) ) );
			}
		}
	}

	public function has( $cap ) {
		return in_array( strtoupper( $cap ), $this->caps, true );
	}

	/**
	 * Priečinky s príznakmi (\Sent, \Trash …).
	 *
	 * @return array<string,array> názov → príznaky
	 */
	public function folders() {
		$r   = $this->cmd( 'LIST "" "*"' );
		$out = array();
		foreach ( $r['untagged'] as $u ) {
			if ( ! preg_match( '/^\* LIST \(([^)]*)\) (NIL|"(?:[^"\\\\]|\\\\.)*") (.+)$/i', $u['line'], $m ) ) {
				continue;
			}
			$name = trim( $m[3] );
			if ( preg_match( '/^\x00(\d+)\x00$/', $name, $lm ) ) {
				$name = $u['lits'][ (int) $lm[1] ];
			} elseif ( '"' === substr( $name, 0, 1 ) ) {
				$name = stripcslashes( substr( $name, 1, -1 ) );
			}
			$out[ $name ] = preg_split( '/\s+/', trim( $m[1] ) );
		}
		return $out;
	}

	/** Vytvorenie priečinka (a prihlásenie na odber, aby ho videli aj iné programy). */
	public function create( $folder ) {
		$r = $this->cmd( 'CREATE ' . self::q( $folder ) );
		if ( $r['ok'] || false !== stripos( $r['text'], 'exists' ) ) {
			$this->cmd( 'SUBSCRIBE ' . self::q( $folder ) );
			return true;
		}
		$this->error = $r['text'];
		return false;
	}

	/** Výber priečinka; vráti počet správ alebo false. */
	public function select( $folder, $readonly = false ) {
		$r = $this->cmd( ( $readonly ? 'EXAMINE ' : 'SELECT ' ) . self::q( $folder ) );
		if ( ! $r['ok'] ) {
			$this->error = 'Priečinok „' . $folder . '“ sa nedá otvoriť: ' . $r['text'];
			return false;
		}
		$this->selected    = $folder;
		$this->uidvalidity = 0;
		$this->permflags   = array();
		$exists            = 0;
		foreach ( $r['untagged'] as $u ) {
			if ( preg_match( '/^\* (\d+) EXISTS/i', $u['line'], $m ) ) {
				$exists = (int) $m[1];
			} elseif ( preg_match( '/\[UIDVALIDITY (\d+)\]/i', $u['line'], $m ) ) {
				$this->uidvalidity = (int) $m[1];
			} elseif ( preg_match( '/\[PERMANENTFLAGS \(([^)]*)\)\]/i', $u['line'], $m ) ) {
				$this->permflags = preg_split( '/\s+/', trim( $m[1] ) );
			}
		}
		return $exists;
	}

	/** Počet neprečítaných správ v priečinku. */
	public function unseen( $folder ) {
		$r = $this->cmd( 'STATUS ' . self::q( $folder ) . ' (UNSEEN)' );
		foreach ( $r['untagged'] as $u ) {
			if ( preg_match( '/UNSEEN (\d+)/i', $u['line'], $m ) ) {
				return (int) $m[1];
			}
		}
		return 0;
	}

	/** UID všetkých správ (alebo podľa kritéria) od najnovšej. */
	public function uids( $criteria = 'ALL', array $lits = array() ) {
		$r    = $this->cmd( 'UID SEARCH ' . $criteria, $lits );
		$uids = array();
		foreach ( $r['untagged'] as $u ) {
			if ( 0 === stripos( $u['line'], '* SEARCH' ) ) {
				foreach ( preg_split( '/\s+/', trim( substr( $u['line'], 8 ) ) ) as $x ) {
					if ( ctype_digit( $x ) ) {
						$uids[] = (int) $x;
					}
				}
			}
		}
		rsort( $uids );
		return $uids;
	}

	/**
	 * Hlavičky správ pre zoznam.
	 *
	 * @param int[] $uids
	 * @return array<int,array> uid → [flags, date, size, headers(raw)]
	 */
	public function headers( array $uids ) {
		if ( ! $uids ) {
			return array();
		}
		$r   = $this->cmd( 'UID FETCH ' . implode( ',', array_map( 'intval', $uids ) ) . ' (UID FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE CONTENT-TYPE REPLY-TO MESSAGE-ID)])' );
		$out = array();
		foreach ( $r['untagged'] as $u ) {
			$l = $u['line'];
			if ( ! preg_match( '/^\* \d+ FETCH /i', $l ) || ! preg_match( '/UID (\d+)/i', $l, $m ) ) {
				continue;
			}
			$uid = (int) $m[1];
			preg_match( '/FLAGS \(([^)]*)\)/i', $l, $f );
			preg_match( '/INTERNALDATE "([^"]+)"/i', $l, $d );
			preg_match( '/RFC822\.SIZE (\d+)/i', $l, $s );
			preg_match( '/BODY\[HEADER[^\]]*\] \x00(\d+)\x00/i', $l, $h );
			$out[ $uid ] = array(
				'flags'   => isset( $f[1] ) ? preg_split( '/\s+/', trim( $f[1] ) ) : array(),
				'date'    => isset( $d[1] ) ? strtotime( $d[1] ) : 0,
				'size'    => isset( $s[1] ) ? (int) $s[1] : 0,
				'headers' => isset( $h[1] ) ? $u['lits'][ (int) $h[1] ] : '',
			);
		}
		return $out;
	}

	/** Celá správa (bez označenia ako prečítaná). */
	public function message( $uid ) {
		$r = $this->cmd( 'UID FETCH ' . (int) $uid . ' (UID FLAGS BODY.PEEK[])' );
		foreach ( $r['untagged'] as $u ) {
			if ( preg_match( '/BODY\[\] \x00(\d+)\x00/i', $u['line'], $m ) ) {
				preg_match( '/FLAGS \(([^)]*)\)/i', $u['line'], $f );
				return array(
					'raw'   => $u['lits'][ (int) $m[1] ],
					'flags' => isset( $f[1] ) ? preg_split( '/\s+/', trim( $f[1] ) ) : array(),
				);
			}
		}
		$this->error = 'Správa sa nenašla.';
		return false;
	}

	/** Pridať / odobrať príznak (\Seen, \Answered, \Flagged). */
	public function flag( $uid, $flag, $on = true ) {
		return $this->cmd( 'UID STORE ' . (int) $uid . ' ' . ( $on ? '+' : '-' ) . 'FLAGS.SILENT (' . $flag . ')' )['ok'];
	}

	/** Presun správy do iného priečinka (napr. do Koša). */
	public function move( $uid, $to ) {
		$uid = (int) $uid;
		if ( $this->has( 'MOVE' ) ) {
			$r = $this->cmd( 'UID MOVE ' . $uid . ' ' . self::q( $to ) );
			if ( $r['ok'] ) {
				return true;
			}
		}
		$r = $this->cmd( 'UID COPY ' . $uid . ' ' . self::q( $to ) );
		if ( ! $r['ok'] ) {
			$this->error = $r['text'];
			return false;
		}
		$this->flag( $uid, '\\Deleted', true );
		if ( $this->has( 'UIDPLUS' ) ) {
			$this->cmd( 'UID EXPUNGE ' . $uid );
		} elseif ( array( $uid ) === $this->uids( 'DELETED' ) ) {
			// Bez UIDPLUS len vtedy, keď je na zmazanie označená iba táto správa – inak by EXPUNGE
			// natrvalo vymazal aj správy, ktoré iný poštový program len označil.
			$this->cmd( 'EXPUNGE' );
		}
		return true;
	}

	/** Uloženie správy do priečinka (kópia odoslanej pošty). */
	public function append( $folder, $mime, $flags = '(\\Seen)' ) {
		if ( ! $this->fp ) {
			return false;
		}
		$mime = preg_replace( "/\r?\n/", "\r\n", $mime );
		$tag  = 'J' . ( ++$this->n );
		fwrite( $this->fp, $tag . ' APPEND ' . self::q( $folder ) . ' ' . $flags . ' {' . strlen( $mime ) . "}\r\n" );
		$l = (string) fgets( $this->fp );
		if ( 0 !== strpos( $l, '+' ) ) {
			$this->error = trim( $l );
			return false;
		}
		fwrite( $this->fp, $mime . "\r\n" );
		while ( true ) {
			$resp = $this->read_response();
			if ( null === $resp ) {
				return false;
			}
			if ( 0 === strpos( $resp['line'], $tag . ' ' ) ) {
				return 0 === stripos( substr( $resp['line'], strlen( $tag ) + 1 ), 'OK' );
			}
		}
	}

	/** Je možné ukladať vlastné kľúčové slová (napr. $Important)? */
	public function allows_keywords() {
		return in_array( '\\*', $this->permflags, true );
	}

	/** Zoznam UID ako množina pre IMAP: [1,2,3,7] → „1:3,7“. */
	public static function uid_set( array $uids ) {
		$uids = array_values( array_unique( array_filter( array_map( 'intval', $uids ) ) ) );
		sort( $uids );
		$out   = array();
		$count = count( $uids );
		for ( $i = 0; $i < $count; $i++ ) {
			$start = $uids[ $i ];
			while ( $i + 1 < $count && $uids[ $i + 1 ] === $uids[ $i ] + 1 ) {
				$i++;
			}
			$out[] = $start === $uids[ $i ] ? (string) $start : $start . ':' . $uids[ $i ];
		}
		return implode( ',', $out );
	}

	/** Hodnota z FETCH riadka, ktorá je literálom (\x00n\x00) alebo reťazcom v úvodzovkách. */
	private static function fetch_value( array $u, $label_re ) {
		if ( preg_match( '/' . $label_re . ' \x00(\d+)\x00/i', $u['line'], $m ) ) {
			return (string) $u['lits'][ (int) $m[1] ];
		}
		if ( preg_match( '/' . $label_re . ' "((?:[^"\\\\]|\\\\.)*)"/i', $u['line'], $m ) ) {
			return stripcslashes( $m[1] );
		}
		return '';
	}

	/**
	 * Údaje pre zoznam konverzácií: príznaky, veľkosť, hlavičky, štruktúra (názvy príloh) a začiatok tela (náhľad).
	 *
	 * @param int[] $uids
	 * @return array<int,array{flags:array,date:int,size:int,headers:string,text:string,files:array}>
	 */
	public function list_data( array $uids ) {
		$out = array();
		foreach ( array_chunk( $uids, 150 ) as $chunk ) {
			$r = $this->cmd( 'UID FETCH ' . self::uid_set( $chunk ) . ' (UID FLAGS INTERNALDATE RFC822.SIZE BODYSTRUCTURE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE CONTENT-TYPE CONTENT-TRANSFER-ENCODING MESSAGE-ID IN-REPLY-TO REFERENCES REPLY-TO)] BODY.PEEK[TEXT]<0.4000>)' );
			foreach ( $r['untagged'] as $u ) {
				$l = $u['line'];
				if ( ! preg_match( '/^\* \d+ FETCH /i', $l ) || ! preg_match( '/UID (\d+)/i', $l, $m ) ) {
					continue;
				}
				preg_match( '/FLAGS \(([^)]*)\)/i', $l, $f );
				preg_match( '/INTERNALDATE "([^"]+)"/i', $l, $d );
				preg_match( '/RFC822\.SIZE (\d+)/i', $l, $s );
				$files = array();
				if ( preg_match( '/BODYSTRUCTURE (\(.*)$/is', $l, $bs ) ) {
					// Názvy príloh: ("filename" "x.pdf") alebo ("name" "x.pdf"), aj ako literál.
					if ( preg_match_all( '/"(?:file)?name(\*)?" (?:"((?:[^"\\\\]|\\\\.)*)"|\x00(\d+)\x00)/i', $bs[1], $nm, PREG_SET_ORDER ) ) {
						foreach ( $nm as $x ) {
							$name = isset( $x[3] ) && '' !== $x[3] ? (string) $u['lits'][ (int) $x[3] ] : stripcslashes( $x[2] );
							if ( '*' === $x[1] && preg_match( "/^([^']*)'[^']*'(.*)$/", $name, $rc ) ) {
								$name = jem_to_utf8( rawurldecode( $rc[2] ), '' !== $rc[1] ? $rc[1] : 'utf-8' ); // RFC 2231: filename*=UTF-8''Fakt%C3%BAra.pdf
							}
							$name = jem_decode_header( $name );
							if ( '' !== $name && ! in_array( $name, $files, true ) ) {
								$files[] = $name;
							}
						}
					}
				}
				$out[ (int) $m[1] ] = array(
					'flags'   => isset( $f[1] ) && '' !== trim( $f[1] ) ? preg_split( '/\s+/', trim( $f[1] ) ) : array(),
					'date'    => isset( $d[1] ) ? (int) strtotime( $d[1] ) : 0,
					'size'    => isset( $s[1] ) ? (int) $s[1] : 0,
					'headers' => self::fetch_value( $u, 'BODY\[HEADER\.FIELDS[^\]]*\]' ),
					'text'    => self::fetch_value( $u, 'BODY\[TEXT\](?:<\d+>)?' ),
					'files'   => $files,
				);
			}
		}
		return $out;
	}

	/** Príznaky správ: uid → [príznaky]. */
	public function flags_of( array $uids ) {
		$out = array();
		if ( ! $uids ) {
			return $out;
		}
		foreach ( array_chunk( $uids, 500 ) as $chunk ) {
			$r = $this->cmd( 'UID FETCH ' . self::uid_set( $chunk ) . ' (UID FLAGS)' );
			foreach ( $r['untagged'] as $u ) {
				if ( preg_match( '/UID (\d+)/i', $u['line'], $m ) && preg_match( '/FLAGS \(([^)]*)\)/i', $u['line'], $f ) ) {
					$out[ (int) $m[1] ] = '' !== trim( $f[1] ) ? preg_split( '/\s+/', trim( $f[1] ) ) : array();
				}
			}
		}
		return $out;
	}

	/** Príznak pre viac správ naraz (\Seen, \Flagged, $Important…). */
	public function store( array $uids, $flag, $on = true ) {
		if ( ! $uids ) {
			return true;
		}
		$r = $this->cmd( 'UID STORE ' . self::uid_set( $uids ) . ' ' . ( $on ? '+' : '-' ) . 'FLAGS.SILENT (' . $flag . ')' );
		if ( ! $r['ok'] ) {
			$this->error = $r['text'];
		}
		return $r['ok'];
	}

	/** Presun viacerých správ do priečinka. */
	public function move_many( array $uids, $to ) {
		$uids = array_values( array_filter( array_map( 'intval', $uids ) ) );
		if ( ! $uids ) {
			return true;
		}
		$set = self::uid_set( $uids );
		if ( $this->has( 'MOVE' ) ) {
			$r = $this->cmd( 'UID MOVE ' . $set . ' ' . self::q( $to ) );
			if ( $r['ok'] ) {
				return true;
			}
		}
		$r = $this->cmd( 'UID COPY ' . $set . ' ' . self::q( $to ) );
		if ( ! $r['ok'] ) {
			$this->error = $r['text'];
			return false;
		}
		$this->expunge_uids( $uids );
		return true;
	}

	/** Natrvalo zmazať správy (len vybrané – bez UIDPLUS iba ak iné nie sú označené na zmazanie). */
	public function expunge_uids( array $uids ) {
		$this->store( $uids, '\\Deleted', true );
		if ( $this->has( 'UIDPLUS' ) ) {
			return $this->cmd( 'UID EXPUNGE ' . self::uid_set( $uids ) )['ok'];
		}
		$marked = $this->uids( 'DELETED' );
		sort( $marked );
		$mine = array_map( 'intval', $uids );
		sort( $mine );
		if ( $marked === $mine ) {
			return $this->cmd( 'EXPUNGE' )['ok'];
		}
		return false;
	}
}
