<?php
/**
 * Jednoduchý Markdown → HTML: nadpisy, odseky, zoznamy (aj vnorené), úlohy (- [ ] / [~] / [x], ☐ ◐ ☑),
 * tabuľky, citácie, bloky kódu, vodorovná čiara; v riadku **tučné**, *kurzíva*, `kód`, ~~preškrtnuté~~, odkazy.
 * Úlohy dostanú číslo riadku (data-line) – v náhľade sa dajú prepínať klikom.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jno_md_inline( $t ) {
	$t     = esc_html( $t );
	$codes = array();
	$t     = preg_replace_callback( '/`([^`]+)`/', function ( $m ) use ( &$codes ) {
		$codes[] = '<code>' . $m[1] . '</code>';
		return "\x01" . ( count( $codes ) - 1 ) . "\x01";
	}, $t );
	$t = preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/', function ( $m ) {
		$url = esc_url( html_entity_decode( $m[2] ) );
		return $url ? '<a href="' . $url . '" target="_blank" rel="noopener">' . $m[1] . '</a>' : $m[0];
	}, $t );
	$t = preg_replace( '#(?<!["\'=>])\b(https?://[^\s<]+[^\s<.,;:!?)])#', '<a href="$1" target="_blank" rel="noopener">$1</a>', $t );
	$t = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t );
	$t = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $t );
	$t = preg_replace( '/(?<![\w_])_(?!\s)(.+?)(?<!\s)_(?![\w_])/u', '<em>$1</em>', $t );
	$t = preg_replace( '/~~(.+?)~~/u', '<del>$1</del>', $t );
	$t = preg_replace_callback( '/☐|◐|☑/u', function ( $m ) {
		$c = array( '☐' => 'todo', '◐' => 'doing', '☑' => 'done' )[ $m[0] ];
		return '<span class="jno-st jno-st-' . $c . '">' . $m[0] . '</span>';
	}, $t );
	return preg_replace_callback( "/\x01(\d+)\x01/", fn( $m ) => $codes[ (int) $m[1] ], $t );
}

function jno_markdown( $md, $tasks = true ) {
	$lines = preg_split( '/\R/', (string) $md );
	$out   = '';
	$n     = count( $lines );
	$i     = 0;
	$para  = array();
	$flush = function () use ( &$para, &$out ) {
		if ( $para ) {
			$out .= '<p>' . implode( '<br>', array_map( 'jno_md_inline', $para ) ) . "</p>\n";
			$para = array();
		}
	};
	while ( $i < $n ) {
		$l = $lines[ $i ];
		if ( '' === trim( $l ) ) {
			$flush();
			$i++;
			continue;
		}
		// Blok kódu.
		if ( preg_match( '/^\s*```/', $l ) ) {
			$flush();
			$code = array();
			$i++;
			while ( $i < $n && ! preg_match( '/^\s*```/', $lines[ $i ] ) ) {
				$code[] = $lines[ $i++ ];
			}
			$i++;
			$out .= '<pre><code>' . esc_html( implode( "\n", $code ) ) . "</code></pre>\n";
			continue;
		}
		if ( preg_match( '/^(#{1,6})\s+(.+?)\s*#*$/', $l, $m ) ) {
			$flush();
			$lv   = strlen( $m[1] );
			$out .= '<h' . $lv . ' id="' . esc_attr( sanitize_title( $m[2] ) ) . '">' . jno_md_inline( $m[2] ) . '</h' . $lv . ">\n";
			$i++;
			continue;
		}
		if ( preg_match( '/^\s*(-{3,}|\*{3,}|_{3,})\s*$/', $l ) ) {
			$flush();
			$out .= "<hr>\n";
			$i++;
			continue;
		}
		// Tabuľka.
		if ( '|' === substr( ltrim( $l ), 0, 1 ) && isset( $lines[ $i + 1 ] ) && preg_match( '/^\s*\|?\s*:?-{2,}/', $lines[ $i + 1 ] ) ) {
			$flush();
			$cells = function ( $row ) {
				return array_map( 'trim', explode( '|', trim( trim( $row ), '|' ) ) );
			};
			$out  .= '<table class="jno-table"><thead><tr>';
			foreach ( $cells( $l ) as $c ) {
				$out .= '<th>' . jno_md_inline( $c ) . '</th>';
			}
			$out .= '</tr></thead><tbody>';
			$i   += 2;
			while ( $i < $n && '|' === substr( ltrim( $lines[ $i ] ), 0, 1 ) ) {
				$st   = $tasks && preg_match( '/\|\s*(☐|◐|☑)[^|]*\|?\s*$/u', $lines[ $i ] ) ? ' data-line="' . $i . '" class="jno-trow"' : '';
				$out .= '<tr' . $st . '>';
				foreach ( $cells( $lines[ $i ] ) as $c ) {
					$out .= '<td>' . jno_md_inline( $c ) . '</td>';
				}
				$out .= '</tr>';
				$i++;
			}
			$out .= "</tbody></table>\n";
			continue;
		}
		if ( preg_match( '/^\s*>\s?(.*)$/', $l ) ) {
			$flush();
			$q = array();
			while ( $i < $n && preg_match( '/^\s*>\s?(.*)$/', $lines[ $i ], $m ) ) {
				$q[] = $m[1];
				$i++;
			}
			$out .= '<blockquote>' . jno_markdown( implode( "\n", $q ), false ) . "</blockquote>\n";
			continue;
		}
		// Zoznam (aj vnorený podľa odsadenia).
		if ( preg_match( '/^(\s*)([-*+]|\d+\.)\s+/', $l ) ) {
			$flush();
			$stack = array();
			while ( $i < $n && preg_match( '/^(\s*)([-*+]|\d+\.)\s+(.*)$/', $lines[ $i ], $m ) ) {
				$ind  = strlen( str_replace( "\t", '    ', $m[1] ) );
				$tag  = ctype_digit( rtrim( $m[2], '.' ) ) ? 'ol' : 'ul';
				while ( $stack && $ind < end( $stack )[0] ) {
					$out .= '</li></' . array_pop( $stack )[1] . '>';
				}
				if ( ! $stack || $ind > end( $stack )[0] ) {
					$out    .= '<' . $tag . '>';
					$stack[] = array( $ind, $tag );
				} else {
					$out .= '</li>';
				}
				$txt = $m[3];
				if ( $tasks && preg_match( '/^\[( |x|X|~|-)\]\s+(.*)$/u', $txt, $t ) ) {
					$st   = 'x' === strtolower( $t[1] ) ? 'done' : ( ' ' === $t[1] ? 'todo' : 'doing' );
					$out .= '<li class="jno-task jno-' . $st . '" data-line="' . $i . '"><span class="jno-box" role="button" tabindex="0" aria-label="Zmeniť stav úlohy"></span> ' . jno_md_inline( $t[2] );
				} elseif ( $tasks && preg_match( '/^(☐|◐|☑)\s+(.*)$/u', $txt, $t ) ) {
					$st   = '☑' === $t[1] ? 'done' : ( '◐' === $t[1] ? 'doing' : 'todo' );
					$out .= '<li class="jno-task jno-' . $st . '" data-line="' . $i . '"><span class="jno-box" role="button" tabindex="0" aria-label="Zmeniť stav úlohy"></span> ' . jno_md_inline( $t[2] );
				} else {
					$out .= '<li>' . jno_md_inline( $txt );
				}
				$i++;
				// Pokračovanie textu položky (odsadený riadok bez odrážky).
				while ( $i < $n && '' !== trim( $lines[ $i ] ) && preg_match( '/^\s{2,}\S/', $lines[ $i ] ) && ! preg_match( '/^\s*([-*+]|\d+\.)\s+/', $lines[ $i ] ) ) {
					$out .= '<br>' . jno_md_inline( trim( $lines[ $i ] ) );
					$i++;
				}
			}
			while ( $stack ) {
				$out .= '</li></' . array_pop( $stack )[1] . '>';
			}
			$out .= "\n";
			continue;
		}
		// Samostatná úloha so symbolom (☐ text).
		if ( $tasks && preg_match( '/^\s*(☐|◐|☑)\s+(.*)$/u', $l, $t ) ) {
			$flush();
			$st   = '☑' === $t[1] ? 'done' : ( '◐' === $t[1] ? 'doing' : 'todo' );
			$out .= '<ul><li class="jno-task jno-' . $st . '" data-line="' . $i . '"><span class="jno-box" role="button" tabindex="0" aria-label="Zmeniť stav úlohy"></span> ' . jno_md_inline( $t[2] ) . "</li></ul>\n";
			$i++;
			continue;
		}
		$para[] = $l;
		$i++;
	}
	$flush();
	return $out;
}
