<?php
/**
 * PAY by square – reťazec pre QR kód platby (slovenský bankový štandard, verzia 1.2.0).
 *
 * Postup podľa špecifikácie: údaje oddelené tabulátorom → CRC32 na začiatok → kompresia LZMA (lc=3, lp=0, pb=2,
 * slovník 128 kB, bez 13-bajtovej hlavičky) → hlavička by square (2 bajty) + dĺžka (2 bajty, little-endian)
 * → base32hex. Kompresor tu kóduje všetky bajty ako literály (bez opakovaní) – výsledok je platný prúd LZMA,
 * ktorý banky dekódujú bežným spôsobom; pri krátkych platobných údajoch je rozdiel vo veľkosti zanedbateľný.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array $p iban, bic, amount, currency, due (Y-m-d), vs, ks, ss, note, name, street, city
 * @return string reťazec na zakódovanie do QR (alfanumerický)
 */
function jinv_paybysquare( array $p ) {
	$clean = function ( $s, $max ) {
		$s = function_exists( 'remove_accents' ) ? remove_accents( (string) $s ) : (string) $s;
		$s = str_replace( array( "\t", "\r", "\n" ), ' ', $s );
		return mb_substr( trim( $s ), 0, $max );
	};
	$fields = array(
		'',                                                         // InvoiceID
		'1',                                                        // počet platieb
		'1',                                                        // PaymentOptions: platobný príkaz
		rtrim( rtrim( number_format( (float) $p['amount'], 2, '.', '' ), '0' ), '.' ),
		strtoupper( $p['currency'] ?? 'EUR' ),
		! empty( $p['due'] ) ? str_replace( '-', '', $p['due'] ) : '',
		substr( preg_replace( '/\D/', '', (string) ( $p['vs'] ?? '' ) ), 0, 10 ),
		substr( preg_replace( '/\D/', '', (string) ( $p['ks'] ?? '' ) ), 0, 4 ),
		substr( preg_replace( '/\D/', '', (string) ( $p['ss'] ?? '' ) ), 0, 10 ),
		'',                                                         // OriginatorsReferenceInformation
		$clean( $p['note'] ?? '', 140 ),
		'1',                                                        // počet účtov
		strtoupper( preg_replace( '/\s+/', '', (string) $p['iban'] ) ),
		strtoupper( preg_replace( '/\s+/', '', (string) ( $p['bic'] ?? '' ) ) ),
		'0',                                                        // trvalý príkaz – nie
		'0',                                                        // inkaso – nie
		$clean( $p['name'] ?? '', 70 ),                             // príjemca (povinný od verzie 1.2.0)
		$clean( $p['street'] ?? '', 70 ),
		$clean( $p['city'] ?? '', 70 ),
	);
	$payload = implode( "\t", $fields );
	$data    = pack( 'V', crc32( $payload ) ) . $payload;
	$body    = jinv_lzma_literals( $data );
	$bin     = chr( ( 0x00 << 4 ) | 0x02 ) . chr( 0x00 ) . pack( 'v', strlen( $data ) ) . $body;
	return jinv_base32hex( $bin );
}

function jinv_base32hex( $bin ) {
	$chars = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
	$out   = '';
	$buf   = 0;
	$bits  = 0;
	$len   = strlen( $bin );
	for ( $i = 0; $i < $len; $i++ ) {
		$buf   = ( ( $buf << 8 ) | ord( $bin[ $i ] ) ) & 0xFFFF;
		$bits += 8;
		while ( $bits >= 5 ) {
			$bits -= 5;
			$out  .= $chars[ ( $buf >> $bits ) & 31 ];
		}
	}
	if ( $bits > 0 ) {
		$out .= $chars[ ( $buf << ( 5 - $bits ) ) & 31 ];
	}
	return $out;
}

/** Prúd LZMA (lc=3, lp=0, pb=2) zložený len z literálov, bez hlavičky a bez koncovej značky. */
function jinv_lzma_literals( $data ) {
	$lc       = 3;
	$pb_mask  = 3;
	$is_match = array_fill( 0, 12 << 4, 1024 );
	$literal  = array_fill( 0, 0x300 << $lc, 1024 );
	$low      = 0;
	$range    = 0xFFFFFFFF;
	$cache    = 0;
	$cache_sz = 1;
	$out      = '';

	$shift_low = function () use ( &$low, &$cache, &$cache_sz, &$out ) {
		if ( $low < 0xFF000000 || $low > 0xFFFFFFFF ) {
			$carry = $low > 0xFFFFFFFF ? 1 : 0;
			$temp  = $cache;
			do {
				$out  .= chr( ( $temp + $carry ) & 0xFF );
				$temp  = 0xFF;
			} while ( --$cache_sz !== 0 );
			$cache = ( $low >> 24 ) & 0xFF;
		}
		$cache_sz++;
		$low = ( $low & 0x00FFFFFF ) << 8;
	};
	$encode_bit = function ( array &$probs, $i, $bit ) use ( &$low, &$range, $shift_low ) {
		$bound = ( $range >> 11 ) * $probs[ $i ];
		if ( 0 === $bit ) {
			$range        = $bound;
			$probs[ $i ] += ( 2048 - $probs[ $i ] ) >> 5;
		} else {
			$low         += $bound;
			$range       -= $bound;
			$probs[ $i ] -= $probs[ $i ] >> 5;
		}
		while ( $range < 0x01000000 ) {
			$range = ( $range << 8 ) & 0xFFFFFFFF;
			$shift_low();
		}
	};

	$prev = 0;
	$len  = strlen( $data );
	for ( $pos = 0; $pos < $len; $pos++ ) {
		$byte = ord( $data[ $pos ] );
		$encode_bit( $is_match, ( 0 << 4 ) + ( $pos & $pb_mask ), 0 ); // stav ostáva 0 – samé literály
		$base   = 0x300 * ( $prev >> ( 8 - $lc ) );
		$symbol = 1;
		for ( $i = 7; $i >= 0; $i-- ) {
			$bit = ( $byte >> $i ) & 1;
			$encode_bit( $literal, $base + $symbol, $bit );
			$symbol = ( $symbol << 1 ) | $bit;
		}
		$prev = $byte;
	}
	for ( $i = 0; $i < 5; $i++ ) {
		$shift_low();
	}
	return $out;
}
