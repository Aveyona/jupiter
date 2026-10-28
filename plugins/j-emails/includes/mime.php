<?php
/** Rozbor e-mailu (MIME): hlavičky, text, HTML, prílohy, kódovania a znakové sady. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Prevod textu do UTF-8 z ľubovoľnej znakovej sady (aj windows-1250, iso-8859-2). */
function jem_to_utf8( $s, $charset ) {
	$cs = strtolower( trim( (string) $charset, " \"'" ) );
	if ( '' === $cs || 'us-ascii' === $cs || 'utf-8' === $cs || 'utf8' === $cs ) {
		return mb_check_encoding( $s, 'UTF-8' ) ? $s : mb_convert_encoding( $s, 'UTF-8', 'Windows-1252' );
	}
	$aliases = array( 'cp1250' => 'windows-1250', 'cp1252' => 'windows-1252', 'latin2' => 'iso-8859-2', 'ks_c_5601-1987' => 'cp949' );
	$cs      = isset( $aliases[ $cs ] ) ? $aliases[ $cs ] : $cs;
	if ( function_exists( 'iconv' ) ) {
		$r = @iconv( $cs, 'UTF-8//IGNORE', $s );
		if ( false !== $r && '' !== $r ) {
			return $r;
		}
	}
	try {
		return mb_convert_encoding( $s, 'UTF-8', $cs );
	} catch ( \Throwable $e ) {
		return mb_convert_encoding( $s, 'UTF-8', 'Windows-1252' );
	}
}

/** Dekódovanie hlavičky (=?utf-8?B?...?=, =?iso-8859-2?Q?...?=). */
function jem_decode_header( $s ) {
	$s = preg_replace( '/(=\?[^?]+\?[bqBQ]\?[^?]*\?=)\s+(?==\?)/', '$1', (string) $s );
	$out = preg_replace_callback(
		'/=\?([^?*]+)(?:\*[^?]*)?\?([bqBQ])\?([^?]*)\?=/',
		function ( $m ) {
			$data = 'b' === strtolower( $m[2] ) ? base64_decode( $m[3] ) : quoted_printable_decode( str_replace( '_', ' ', $m[3] ) );
			return jem_to_utf8( (string) $data, $m[1] );
		},
		$s
	);
	return jem_to_utf8( (string) $out, 'utf-8' );
}

/**
 * Hlavičky na pole (malé písmená → hodnota; opakované hlavičky spojené).
 *
 * @return array<string,string>
 */
function jem_parse_headers( $raw ) {
	$raw = preg_replace( "/\r?\n[ \t]+/", ' ', str_replace( "\r\n", "\n", (string) $raw ) );
	$h   = array();
	foreach ( explode( "\n", $raw ) as $line ) {
		$p = strpos( $line, ':' );
		if ( false === $p ) {
			continue;
		}
		$k = strtolower( trim( substr( $line, 0, $p ) ) );
		$v = trim( substr( $line, $p + 1 ) );
		$h[ $k ] = isset( $h[ $k ] ) ? $h[ $k ] . ', ' . $v : $v;
	}
	return $h;
}

/** Hodnota hlavičky s parametrami: „text/plain; charset=utf-8“ → [text/plain, [charset => utf-8]]. */
function jem_header_params( $value ) {
	$parts  = preg_split( '/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) $value );
	$main   = strtolower( trim( array_shift( $parts ) ) );
	$params = array();
	$cont   = array();
	foreach ( $parts as $p ) {
		$eq = strpos( $p, '=' );
		if ( false === $eq ) {
			continue;
		}
		$k = strtolower( trim( substr( $p, 0, $eq ) ) );
		$v = trim( substr( $p, $eq + 1 ) );
		if ( strlen( $v ) > 1 && '"' === $v[0] && '"' === substr( $v, -1 ) ) {
			$v = stripcslashes( substr( $v, 1, -1 ) );
		}
		// RFC 2231: filename*=utf-8''n%C3%A1zov.pdf, filename*0*=…
		if ( preg_match( '/^([^*]+)\*(\d+)?(\*)?$/', $k, $m ) ) {
			$cont[ $m[1] ][ isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0 ] = array( $v, ! empty( $m[3] ) || ( ! isset( $m[2] ) || '' === $m[2] ) );
			continue;
		}
		$params[ $k ] = $v;
	}
	foreach ( $cont as $k => $pieces ) {
		ksort( $pieces );
		$charset = '';
		$val     = '';
		foreach ( $pieces as $i => $pc ) {
			list( $v, $enc ) = $pc;
			if ( 0 === $i && $enc && preg_match( "/^([^']*)'[^']*'(.*)$/", $v, $m ) ) {
				$charset = $m[1];
				$v       = $m[2];
			}
			$val .= $enc ? rawurldecode( $v ) : $v;
		}
		$params[ $k ] = jem_to_utf8( $val, $charset ? $charset : 'utf-8' );
	}
	return array( $main, $params );
}

/** Dekódovanie tela časti podľa Content-Transfer-Encoding. */
function jem_decode_body( $body, $enc ) {
	switch ( strtolower( trim( (string) $enc ) ) ) {
		case 'base64':
			return (string) base64_decode( preg_replace( '/[^A-Za-z0-9+\/=]/', '', $body ) );
		case 'quoted-printable':
			return quoted_printable_decode( $body );
		default:
			return $body;
	}
}

/**
 * Rozbor celej správy.
 *
 * @return array{headers:array,text:string,html:string,attachments:array}
 */
function jem_parse_message( $raw ) {
	$msg = array( 'headers' => array(), 'text' => '', 'html' => '', 'attachments' => array() );
	list( $head, $body ) = jem_split_part( $raw );
	$msg['headers']      = jem_parse_headers( $head );
	jem_walk_part( $msg['headers'], $body, $msg );
	return $msg;
}

function jem_split_part( $raw ) {
	$raw = (string) $raw;
	$p   = strpos( $raw, "\r\n\r\n" );
	$q   = strpos( $raw, "\n\n" );
	if ( false !== $p && ( false === $q || $p <= $q ) ) {
		return array( substr( $raw, 0, $p ), substr( $raw, $p + 4 ) );
	}
	if ( false !== $q ) {
		return array( substr( $raw, 0, $q ), substr( $raw, $q + 2 ) );
	}
	return array( $raw, '' );
}

/** Rekurzívny prechod časťami; prílohy sú číslované v poradí, v akom sa v správe nachádzajú. */
function jem_walk_part( array $h, $body, array &$msg, $depth = 0 ) {
	if ( $depth > 20 ) {
		return;
	}
	list( $type, $tp ) = jem_header_params( isset( $h['content-type'] ) ? $h['content-type'] : 'text/plain; charset=us-ascii' );
	list( $disp, $dp ) = jem_header_params( isset( $h['content-disposition'] ) ? $h['content-disposition'] : '' );
	if ( 0 === strpos( $type, 'multipart/' ) && ! empty( $tp['boundary'] ) ) {
		$b      = '--' . $tp['boundary'];
		$chunks = preg_split( '/^' . preg_quote( $b, '/' ) . '(?:--)?[ \t]*\r?$/m', $body );
		array_shift( $chunks );
		foreach ( $chunks as $chunk ) {
			$chunk = preg_replace( '/^\r?\n/', '', $chunk );
			if ( '' === trim( $chunk ) ) {
				continue;
			}
			list( $ph, $pb ) = jem_split_part( $chunk );
			jem_walk_part( jem_parse_headers( $ph ), preg_replace( '/\r?\n$/', '', $pb ), $msg, $depth + 1 );
		}
		return;
	}
	if ( 'message/rfc822' === $type && 'attachment' !== $disp ) {
		list( $ph, $pb ) = jem_split_part( jem_decode_body( $body, isset( $h['content-transfer-encoding'] ) ? $h['content-transfer-encoding'] : '' ) );
		jem_walk_part( jem_parse_headers( $ph ), $pb, $msg, $depth + 1 );
		return;
	}
	$data     = jem_decode_body( $body, isset( $h['content-transfer-encoding'] ) ? $h['content-transfer-encoding'] : '' );
	$filename = isset( $dp['filename'] ) ? $dp['filename'] : ( isset( $tp['name'] ) ? $tp['name'] : '' );
	$filename = jem_decode_header( $filename );
	$cid      = isset( $h['content-id'] ) ? trim( $h['content-id'], '<> ' ) : '';
	$is_text  = in_array( $type, array( 'text/plain', 'text/html' ), true );
	if ( $is_text && 'attachment' !== $disp && '' === $filename ) {
		$text = jem_to_utf8( $data, isset( $tp['charset'] ) ? $tp['charset'] : '' );
		if ( 'text/html' === $type ) {
			$msg['html'] .= $text;
		} elseif ( '' === $msg['text'] ) {
			$msg['text'] = $text;
		} else {
			$msg['text'] .= "\n\n" . $text;
		}
		return;
	}
	if ( '' === $filename ) {
		$ext      = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'application/pdf' => 'pdf', 'text/calendar' => 'ics', 'message/rfc822' => 'eml' );
		$filename = 'priloha-' . ( count( $msg['attachments'] ) + 1 ) . '.' . ( isset( $ext[ $type ] ) ? $ext[ $type ] : 'bin' );
	}
	$msg['attachments'][] = array(
		'name'   => $filename,
		'type'   => $type,
		'size'   => strlen( $data ),
		'cid'    => $cid,
		'inline' => 'inline' === $disp || ( '' !== $cid && 0 === strpos( $type, 'image/' ) ),
		'data'   => $data,
	);
}

/** Adresy z hlavičky „Meno <a@b.sk>, c@d.sk“ → [[name, email], …]. */
function jem_addresses( $value ) {
	$out = array();
	foreach ( preg_split( '/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) $value ) as $a ) {
		$a = trim( $a );
		if ( '' === $a ) {
			continue;
		}
		if ( preg_match( '/^(.*)<([^>]+)>\s*$/', $a, $m ) ) {
			$name  = trim( jem_decode_header( trim( $m[1] ) ), " \"'" );
			$email = trim( $m[2] );
		} else {
			$name  = '';
			$email = trim( $a, '<> ' );
		}
		$out[] = array( $name, $email );
	}
	return $out;
}

/** Krátke zobrazenie adresy: meno, inak e-mail. */
function jem_addr_label( $value ) {
	$list = jem_addresses( $value );
	if ( ! $list ) {
		return '';
	}
	$names = array();
	foreach ( $list as $a ) {
		$names[] = '' !== $a[0] ? $a[0] : $a[1];
	}
	return implode( ', ', $names );
}

/** Dátum z hlavičky Date (deň v týždni sa ignoruje – PHP by ho bral ako posun). */
function jem_date_ts( $value ) {
	$v  = preg_replace( '/^\s*[A-Za-z]{2,}\s*,\s*/', '', (string) $value );
	$v  = preg_replace( '/\s*\([^)]*\)\s*$/', '', $v );
	$ts = strtotime( $v );
	return $ts ? $ts : 0;
}
