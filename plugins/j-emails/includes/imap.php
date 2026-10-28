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
		$r = $this->cmd( 'LOGIN ' . self::q( $user ) . ' ' . self::q( $pass ) );
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

	/** Reťazec v úvodzovkách podľa IMAP. */
	public static function q( $s ) {
		return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $s ) . '"';
	}

	/**
	 * Príkaz a odpoveď. Literály {n} sa načítajú celé a v riadku sú nahradené značkou \x00n\x00.
	 *
	 * @return array{ok:bool,text:string,untagged:array<int,array{line:string,lits:array}>}
	 */
	public function cmd( $command ) {
		$out = array( 'ok' => false, 'text' => '', 'untagged' => array() );
		if ( ! $this->fp ) {
			$out['text'] = 'Nie je pripojenie.';
			return $out;
		}
		$tag = 'J' . ( ++$this->n );
		fwrite( $this->fp, $tag . ' ' . $command . "\r\n" );
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
		$this->selected = $folder;
		foreach ( $r['untagged'] as $u ) {
			if ( preg_match( '/^\* (\d+) EXISTS/i', $u['line'], $m ) ) {
				return (int) $m[1];
			}
		}
		return 0;
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
	public function uids( $criteria = 'ALL' ) {
		$r    = $this->cmd( 'UID SEARCH ' . $criteria );
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
		$this->cmd( $this->has( 'UIDPLUS' ) ? 'UID EXPUNGE ' . $uid : 'EXPUNGE' );
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
}
