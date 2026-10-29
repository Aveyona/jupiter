<?php
/**
 * Externé účty Google (Gmail API): faktúry z e-mailu.
 *
 * - Prístup cez OAuth 2.0 (Google Cloud – vlastný klient webu), len na čítanie (gmail.readonly), až keď ho používateľ povolí.
 * - Pri účte sa vyberú štítky (napr. T-COM, FA - Prijaté), ku každému dodávateľ a kategória nákladov.
 * - Zoznam faktúr: správy so štítkom a PDF prílohou; zo správy sa vyčíta suma, VS, obdobie a splatnosť.
 * - Prevzatie do J-Fakturácie: prijatá faktúra s PDF, automatické spárovanie s platbou v banke (VS + suma).
 * Tokeny a tajný kľúč klienta sú uložené zašifrované kľúčom webu.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JEM_G_SCOPE', 'https://www.googleapis.com/auth/gmail.readonly' );

function jem_g_settings() {
	$s = get_option( 'jem_google', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array( 'client_id' => '', 'client_secret' => '', 'accounts' => array(), 'since' => '' ) );
}

function jem_g_update( array $vals ) {
	update_option( 'jem_google', array_merge( jem_g_settings(), $vals ), false );
}

function jem_g_redirect_uri() {
	return admin_url( 'admin-post.php?action=jem_google_callback' );
}

function jem_g_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=j-emails-invoices' ) );
}

function jem_g_back( $msg, $type = 'error', $args = array() ) {
	set_transient( 'jem_g_msg_' . get_current_user_id(), array( $type, $msg ), 300 );
	wp_safe_redirect( jem_g_url( $args ) );
	exit;
}

/* ---------------------------------------------------------------- OAuth */

function jem_g_auth_url() {
	$s     = jem_g_settings();
	$state = wp_create_nonce( 'jem_google_state' );
	return add_query_arg( array(
		'client_id'              => rawurlencode( $s['client_id'] ),
		'redirect_uri'           => rawurlencode( jem_g_redirect_uri() ),
		'response_type'          => 'code',
		'scope'                  => rawurlencode( JEM_G_SCOPE . ' email' ),
		'access_type'            => 'offline',
		'prompt'                 => 'consent',
		'include_granted_scopes' => 'true',
		'state'                  => $state,
	), 'https://accounts.google.com/o/oauth2/v2/auth' );
}

/** Výmena kódu alebo obnovenie tokenu. */
function jem_g_token_request( array $body ) {
	$s = jem_g_settings();
	$r = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
		'timeout' => 20,
		'body'    => $body + array( 'client_id' => $s['client_id'], 'client_secret' => jem_decrypt( $s['client_secret'] ) ),
	) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== wp_remote_retrieve_response_code( $r ) || empty( $j['access_token'] ) ) {
		$e = is_array( $j ) ? ( $j['error_description'] ?? $j['error'] ?? '' ) : '';
		return new WP_Error( 'jem_g', 'Google odmietol prístup' . ( $e ? ': ' . $e : '' ) . '.' );
	}
	return $j;
}

add_action( 'admin_post_jem_google_callback', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ), 'jem_google_state' ) ) { // phpcs:ignore
		jem_g_back( 'Prihlásenie do Google vypršalo – skúste to znova.' );
	}
	if ( ! empty( $_GET['error'] ) ) { // phpcs:ignore
		jem_g_back( 'Prístup k Gmailu nebol povolený (' . sanitize_text_field( wp_unslash( $_GET['error'] ) ) . ').' ); // phpcs:ignore
	}
	$t = jem_g_token_request( array( 'code' => sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) ), 'grant_type' => 'authorization_code', 'redirect_uri' => jem_g_redirect_uri() ) ); // phpcs:ignore
	if ( is_wp_error( $t ) ) {
		jem_g_back( $t->get_error_message() );
	}
	if ( false === strpos( (string) ( $t['scope'] ?? '' ), 'gmail.readonly' ) ) {
		jem_g_back( 'Google nepovolil čítanie pošty – pri prihlásení treba zaškrtnúť prístup ku Gmailu.' );
	}
	// Adresa účtu.
	$p = wp_remote_get( 'https://gmail.googleapis.com/gmail/v1/users/me/profile', array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $t['access_token'] ) ) );
	$j = is_wp_error( $p ) ? array() : json_decode( wp_remote_retrieve_body( $p ), true );
	$email = sanitize_email( $j['emailAddress'] ?? '' );
	if ( ! $email ) {
		jem_g_back( 'Nepodarilo sa zistiť adresu Gmail účtu.' );
	}
	$s   = jem_g_settings();
	$acc = $s['accounts'][ $email ] ?? array( 'labels' => array() );
	if ( ! empty( $t['refresh_token'] ) ) {
		$acc['refresh'] = jem_encrypt( $t['refresh_token'] );
	}
	if ( empty( $acc['refresh'] ) ) {
		jem_g_back( 'Google neposlal obnovovací token – odpojte aplikáciu v nastaveniach Google účtu (Zabezpečenie → Aplikácie tretích strán) a pripojte účet znova.' );
	}
	$acc['connected'] = current_time( 'mysql' );
	$s['accounts'][ $email ] = $acc;
	jem_g_update( array( 'accounts' => $s['accounts'] ) );
	set_transient( 'jem_g_at_' . md5( $email ), $t['access_token'], max( 60, (int) ( $t['expires_in'] ?? 3600 ) - 120 ) );
	jem_g_back( 'Účet ' . $email . ' je pripojený (len čítanie). Vyberte štítky s faktúrami.', 'success', array( 'acc' => $email ) );
} );

/** Platný prístupový token účtu. */
function jem_g_access_token( $email ) {
	$c = get_transient( 'jem_g_at_' . md5( $email ) );
	if ( $c ) {
		return $c;
	}
	$s   = jem_g_settings();
	$ref = jem_decrypt( $s['accounts'][ $email ]['refresh'] ?? '' );
	if ( '' === $ref ) {
		return new WP_Error( 'jem_g', 'Účet ' . $email . ' nie je pripojený – pripojte ho znova.' );
	}
	$t = jem_g_token_request( array( 'refresh_token' => $ref, 'grant_type' => 'refresh_token' ) );
	if ( is_wp_error( $t ) ) {
		return new WP_Error( 'jem_g', $t->get_error_message() . ' Ak ste prístup zrušili alebo vypršal (aplikácia v režime Testovanie platí 7 dní), pripojte účet znova.' );
	}
	set_transient( 'jem_g_at_' . md5( $email ), $t['access_token'], max( 60, (int) ( $t['expires_in'] ?? 3600 ) - 120 ) );
	return $t['access_token'];
}

/** GET na Gmail API (users/me/…). */
function jem_g_api( $email, $path, $args = array() ) {
	$at = jem_g_access_token( $email );
	if ( is_wp_error( $at ) ) {
		return $at;
	}
	$url = 'https://gmail.googleapis.com/gmail/v1/users/me/' . $path . ( $args ? '?' . http_build_query( $args ) : '' );
	$r   = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $at ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
		if ( 401 === wp_remote_retrieve_response_code( $r ) ) {
			delete_transient( 'jem_g_at_' . md5( $email ) );
		}
		return new WP_Error( 'jem_g', 'Gmail API: ' . ( $j['error']['message'] ?? 'chyba ' . wp_remote_retrieve_response_code( $r ) ) );
	}
	return $j;
}

function jem_g_b64( $s ) {
	return base64_decode( strtr( (string) $s, '-_', '+/' ) );
}

/* ---------------------------------------------------------------- Správy a faktúry */

/** Štítky účtu: [ id => name ] (vlastné štítky). */
function jem_g_labels( $email ) {
	$j = jem_g_api( $email, 'labels' );
	if ( is_wp_error( $j ) ) {
		return $j;
	}
	$out = array();
	foreach ( (array) ( $j['labels'] ?? array() ) as $l ) {
		if ( 'user' === ( $l['type'] ?? '' ) ) {
			$out[ $l['id'] ] = $l['name'];
		}
	}
	asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
	return $out;
}

/** Rozbor správy (format=full): hlavičky, text, prílohy. */
function jem_g_parse_message( array $m ) {
	$h = array();
	foreach ( (array) ( $m['payload']['headers'] ?? array() ) as $x ) {
		$h[ strtolower( $x['name'] ) ] = $x['value'];
	}
	$text  = '';
	$html  = '';
	$atts  = array();
	$walk  = function ( $p ) use ( &$walk, &$text, &$html, &$atts ) {
		$mime = strtolower( (string) ( $p['mimeType'] ?? '' ) );
		if ( ! empty( $p['filename'] ) && ! empty( $p['body']['attachmentId'] ) ) {
			$atts[] = array( 'name' => $p['filename'], 'type' => $mime, 'id' => $p['body']['attachmentId'], 'size' => (int) ( $p['body']['size'] ?? 0 ) );
		} elseif ( 'text/plain' === $mime && ! empty( $p['body']['data'] ) ) {
			$text .= jem_g_b64( $p['body']['data'] ) . "\n";
		} elseif ( 'text/html' === $mime && ! empty( $p['body']['data'] ) ) {
			$html .= jem_g_b64( $p['body']['data'] ) . "\n";
		}
		foreach ( (array) ( $p['parts'] ?? array() ) as $c ) {
			$walk( $c );
		}
	};
	$walk( (array) ( $m['payload'] ?? array() ) );
	if ( '' === trim( $text ) && '' !== $html ) {
		$text = html_entity_decode( wp_strip_all_tags( preg_replace( '#<(br|/p|/div|/tr)[^>]*>#i', "\n", $html ) ), ENT_QUOTES, 'UTF-8' );
	}
	$date = isset( $m['internalDate'] ) ? (int) floor( (int) $m['internalDate'] / 1000 ) : strtotime( $h['date'] ?? 'now' );
	return array(
		'id'      => $m['id'],
		'thread'  => $m['threadId'] ?? '',
		'labels'  => (array) ( $m['labelIds'] ?? array() ),
		'from'    => $h['from'] ?? '',
		'subject' => $h['subject'] ?? '',
		'date'    => wp_date( 'Y-m-d', $date ),
		'text'    => trim( (string) $text ),
		'snippet' => html_entity_decode( (string) ( $m['snippet'] ?? '' ), ENT_QUOTES, 'UTF-8' ),
		'atts'    => $atts,
	);
}

/** Suma v texte („179,97 €“, „€22.14“, „1 539,24 EUR“) → float. */
function jem_g_num( $s ) {
	$s = preg_replace( '/[\s\x{00A0}]/u', '', (string) $s );
	if ( preg_match( '/^\d{1,3}(\.\d{3})+,\d{2}$/', $s ) ) {
		$s = str_replace( '.', '', $s );
	}
	if ( preg_match( '/^\d{1,3}(,\d{3})+\.\d{2}$/', $s ) ) {
		$s = str_replace( ',', '', $s );
	}
	return (float) str_replace( ',', '.', $s );
}

function jem_g_date( $d, $m, $y ) {
	return checkdate( (int) $m, (int) $d, (int) $y ) ? sprintf( '%04d-%02d-%02d', $y, $m, $d ) : '';
}

/** Údaje faktúry vyčítané zo správy: total, vs, period_from, period_to, due, number, pdf (index prílohy). */
function jem_g_invoice_fields( array $p ) {
	$t   = $p['subject'] . "\n" . $p['snippet'] . "\n" . $p['text'];
	$out = array( 'total' => 0.0, 'vs' => '', 'period_from' => '', 'period_to' => '', 'due' => '', 'number' => '', 'pdf' => -1 );
	$cur = '(?:€|EUR|eur)';
	$num = '(\d{1,3}(?:[ \x{00A0}.,]?\d{3})*(?:[.,]\d{2}))';
	// Od najistejšieho výrazu (celkom k úhrade) po najvšeobecnejší (total – nie „subtotal“).
	foreach ( array(
		'/celkom\s+k\s+úhrade\s*:?\s*' . $cur . '?\s*' . $num . '/iu',
		'/(?:suma\s+)?(?:k\s+úhrade|na\s+úhradu)\s*:?\s*' . $cur . '?\s*' . $num . '/iu',
		'/amount\s+due\s*:?\s*' . $cur . '?\s*' . $num . '/iu',
		'/' . $cur . '\s*' . $num . '\s+due\b/iu',
		'/celková\s+(?:fakturovaná\s+)?(?:suma|čiastka)[^0-9€]{0,30}' . $cur . '?\s*' . $num . '/iu',
		'/(?<![a-z])total\s*:?\s*' . $cur . '?\s*' . $num . '/iu',
		'/celkom\s*:?\s*' . $cur . '?\s*' . $num . '\s*' . $cur . '/iu',
	) as $re ) {
		if ( preg_match( $re, $t, $m ) ) {
			$out['total'] = jem_g_num( $m[1] );
			break;
		}
	}
	if ( preg_match( '/variabiln\S*\s+symbol\S*\s*:?\s*(\d{2,10})/iu', $t, $m ) ) {
		$out['vs'] = $m[1];
	}
	if ( preg_match( '/(\d{1,2})\.\s?(\d{1,2})\.\s?(\d{4})\s*[-–]\s*(\d{1,2})\.\s?(\d{1,2})\.\s?(\d{4})/u', $t, $m ) ) {
		$out['period_from'] = jem_g_date( $m[1], $m[2], $m[3] );
		$out['period_to']   = jem_g_date( $m[4], $m[5], $m[6] );
	}
	if ( preg_match( '/(?:splatnos\S*|dátum\s+splatnosti|due\s+date|date\s+due)\s*:?\s*(\d{1,2})\.\s?(\d{1,2})\.\s?(\d{4})/iu', $t, $m ) ) {
		$out['due'] = jem_g_date( $m[1], $m[2], $m[3] );
	}
	if ( preg_match( '/(?:číslo\s+faktúry|faktúra\s+č\.?|invoice\s+number|invoice\s+no\.?)\s*:?\s*([A-Z0-9][A-Z0-9\-\/]{3,30})/iu', $t, $m ) ) {
		$out['number'] = $m[1];
	}
	foreach ( $p['atts'] as $i => $a ) {
		if ( 'application/pdf' === $a['type'] || preg_match( '/\.pdf$/i', $a['name'] ) ) {
			$out['pdf'] = $i;
			break;
		}
	}
	if ( '' === $out['number'] && $out['pdf'] >= 0 && preg_match( '/^(\d{6,})/', $p['atts'][ $out['pdf'] ]['name'], $m ) ) {
		$out['number'] = $m[1];
	}
	if ( '' === $out['number'] && $out['vs'] ) {
		$out['number'] = $out['vs'];
	}
	return $out;
}

/** Obsah prílohy (binárne) alebo WP_Error. */
function jem_g_attachment( $email, $msg_id, $att_id ) {
	$j = jem_g_api( $email, 'messages/' . rawurlencode( $msg_id ) . '/attachments/' . rawurlencode( $att_id ) );
	return is_wp_error( $j ) ? $j : jem_g_b64( $j['data'] ?? '' );
}

/**
 * Text z PDF (bez externých knižníc): pdftotext, ak je na serveri, inak jednoduché čítanie textových operátorov
 * (Tj, TJ) z komprimovaných aj nekomprimovaných streamov. Na vyčítanie sumy a VS z faktúry to zvyčajne stačí.
 */
function jem_g_pdf_text( $bin ) {
	$bin = (string) $bin;
	if ( '' === $bin ) {
		return '';
	}
	if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ) ) {
		$bin_path = trim( (string) @shell_exec( 'command -v pdftotext 2>/dev/null' ) ); // phpcs:ignore
		if ( $bin_path ) {
			$tmp = wp_tempnam( 'jem-pdf' );
			file_put_contents( $tmp, $bin ); // phpcs:ignore
			$out = (string) @shell_exec( escapeshellarg( $bin_path ) . ' -layout ' . escapeshellarg( $tmp ) . ' - 2>/dev/null' ); // phpcs:ignore
			@unlink( $tmp ); // phpcs:ignore
			if ( '' !== trim( $out ) ) {
				return $out;
			}
		}
	}
	$text = '';
	if ( preg_match_all( '/stream\r?\n(.*?)\r?\nendstream/s', $bin, $m ) ) {
		foreach ( $m[1] as $raw ) {
			$data = @gzuncompress( $raw ); // phpcs:ignore
			if ( false === $data ) {
				$data = @gzinflate( substr( $raw, 2 ) ); // phpcs:ignore
			}
			if ( false === $data ) {
				$data = $raw;
			}
			if ( false === strpos( $data, 'Tj' ) && false === strpos( $data, 'TJ' ) ) {
				continue;
			}
			// Riadky textu: BT … ET; reťazce (…) v Tj a [ … ] TJ.
			if ( preg_match_all( '/\[(.*?)\]\s*TJ|\((.*?)(?<!\\\\)\)\s*Tj|(T\*|Td|TD|ET)/s', $data, $ops, PREG_SET_ORDER ) ) {
				foreach ( $ops as $op ) {
					if ( ! empty( $op[3] ) ) {
						$text .= "\n";
						continue;
					}
					$chunk = '' !== ( $op[1] ?? '' ) ? $op[1] : ( $op[2] ?? '' );
					if ( '' !== ( $op[1] ?? '' ) ) {
						preg_match_all( '/\((.*?)(?<!\\\\)\)|(-?\d+(?:\.\d+)?)/s', $op[1], $parts, PREG_SET_ORDER );
						$chunk = '';
						foreach ( $parts as $pt ) {
							if ( isset( $pt[2] ) && '' !== $pt[2] && (float) $pt[2] < -200 ) {
								$chunk .= ' ';
							} elseif ( '' !== ( $pt[1] ?? '' ) ) {
								$chunk .= $pt[1];
							}
						}
					}
					$text .= stripcslashes( $chunk );
				}
			}
		}
	}
	if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
		$text = mb_convert_encoding( $text, 'UTF-8', 'ISO-8859-2' );
	}
	$text = (string) preg_replace( '/[^\P{C}\n\t]/u', '', $text );
	return (string) $text;
}

/** Faktúry zo štítku (s PDF prílohou) od dátumu. Rozobraté správy sa pamätajú. */
function jem_g_fetch( $email, $label_id, $since, $max = 150 ) {
	$q     = 'has:attachment filename:pdf' . ( $since ? ' after:' . str_replace( '-', '/', $since ) : '' );
	$ids   = array();
	$token = '';
	do {
		$args = array( 'labelIds' => $label_id, 'q' => $q, 'maxResults' => 100 );
		if ( $token ) {
			$args['pageToken'] = $token;
		}
		$j = jem_g_api( $email, 'messages', $args );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		foreach ( (array) ( $j['messages'] ?? array() ) as $m ) {
			$ids[] = $m['id'];
		}
		$token = (string) ( $j['nextPageToken'] ?? '' );
	} while ( $token && count( $ids ) < $max );
	$cache = get_option( 'jem_g_cache', array() );
	$cache = is_array( $cache ) ? $cache : array();
	$out   = array();
	foreach ( array_slice( $ids, 0, $max ) as $id ) {
		if ( empty( $cache[ $id ] ) ) {
			$m = jem_g_api( $email, 'messages/' . rawurlencode( $id ), array( 'format' => 'full' ) );
			if ( is_wp_error( $m ) ) {
				continue;
			}
			$p = jem_g_parse_message( $m );
			$f = jem_g_invoice_fields( $p );
			unset( $p['text'] );
			$cache[ $id ] = $p + array( 'fields' => $f, 'label' => $label_id, 'account' => $email );
		}
		// Suma nie je v texte správy → skúsi sa vyčítať z PDF faktúry (raz).
		$c = $cache[ $id ];
		if ( (float) $c['fields']['total'] <= 0 && $c['fields']['pdf'] >= 0 && empty( $c['pdf_read'] ) ) {
			$bin = jem_g_attachment( $email, $id, $c['atts'][ $c['fields']['pdf'] ]['id'] );
			if ( ! is_wp_error( $bin ) ) {
				$pdf_text = jem_g_pdf_text( $bin );
				$f2       = jem_g_invoice_fields( array( 'subject' => $c['subject'], 'snippet' => $c['snippet'], 'text' => $pdf_text, 'atts' => $c['atts'] ) );
				foreach ( $f2 as $k => $v ) {
					if ( ( 'total' === $k && (float) $c['fields'][ $k ] <= 0 ) || ( 'total' !== $k && 'pdf' !== $k && '' === (string) $c['fields'][ $k ] ) ) {
						$c['fields'][ $k ] = $v;
					}
				}
				$c['pdf_read']    = 1;
				$c['pdf_snippet'] = mb_substr( trim( preg_replace( '/\s+/u', ' ', $pdf_text ) ), 0, 400 );
				$cache[ $id ]     = $c;
			}
		}
		$out[ $id ] = $cache[ $id ];
	}
	if ( count( $cache ) > 3000 ) {
		$cache = array_slice( $cache, -3000, null, true );
	}
	update_option( 'jem_g_cache', $cache, false );
	return $out;
}

/** Je správa už prevzatá do J-Fakturácie? Vráti ID dokladu alebo 0. */
function jem_g_imported( $msg_id ) {
	global $wpdb;
	if ( ! function_exists( 'jinv_table' ) ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . jinv_table() . " WHERE kind = 'received' AND data LIKE %s", '%' . $wpdb->esc_like( '"ref":"gmail:' . $msg_id . '"' ) . '%' ) ); // phpcs:ignore
}

/** Prevzatie jednej faktúry do J-Fakturácie. Vráti ID dokladu alebo WP_Error. */
function jem_g_import( $msg_id, array $override = array() ) {
	if ( ! function_exists( 'jinv_received_create' ) ) {
		return new WP_Error( 'jem_g', 'Na prevzatie faktúr treba J-Fakturáciu 0.10.1 alebo novšiu.' );
	}
	$cache = (array) get_option( 'jem_g_cache', array() );
	$p     = $cache[ $msg_id ] ?? null;
	if ( ! $p ) {
		return new WP_Error( 'jem_g', 'Správa nie je načítaná – načítajte faktúry znova.' );
	}
	$s   = jem_g_settings();
	$cfg = $s['accounts'][ $p['account'] ]['labels'][ $p['label'] ] ?? array();
	$f   = $p['fields'];
	if ( isset( $override['total'] ) && (float) $override['total'] > 0 ) {
		$f['total'] = round( (float) $override['total'], 2 );
	}
	if ( ! empty( $override['vs'] ) ) {
		$f['vs'] = preg_replace( '/\D/', '', (string) $override['vs'] );
		$f['number'] = $f['number'] ? $f['number'] : $f['vs'];
	}
	if ( (float) $f['total'] <= 0 ) {
		return new WP_Error( 'jem_g', 'V správe „' . $p['subject'] . '“ sa nenašla suma faktúry.' );
	}
	$pdf = '';
	$fn  = 'faktura.pdf';
	if ( $f['pdf'] >= 0 ) {
		$a = $p['atts'][ $f['pdf'] ];
		$j = jem_g_api( $p['account'], 'messages/' . rawurlencode( $msg_id ) . '/attachments/' . rawurlencode( $a['id'] ) );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$pdf = jem_g_b64( $j['data'] ?? '' );
		$fn  = $a['name'];
	}
	$sup = array();
	if ( empty( $cfg['partner_id'] ) && preg_match( '/^\s*"?([^"<]+?)"?\s*</', $p['from'], $m ) ) {
		$sup = array( 'name' => trim( $m[1] ) );
	}
	$issue = $p['date'];
	return jinv_received_create( array(
		'number'      => $f['number'],
		'issue_date'  => $issue,
		'supply_date' => $f['period_to'] ? $f['period_to'] : $issue,
		'due_date'    => $f['due'] ? $f['due'] : gmdate( 'Y-m-d', strtotime( $issue . ' +14 days' ) ),
		'vs'          => $f['vs'],
		'total'       => $f['total'],
		'partner_id'  => (int) ( $cfg['partner_id'] ?? 0 ),
		'supplier'    => $sup,
		'category'    => (string) ( $cfg['category'] ?? '' ),
		'note'        => trim( $p['subject'] . ( $f['period_from'] ? "\nObdobie " . wp_date( 'j. n. Y', strtotime( $f['period_from'] ) ) . ' – ' . wp_date( 'j. n. Y', strtotime( $f['period_to'] ) ) : '' ) . "\nPrevzaté z e-mailu " . $p['account'] ),
		'file_data'   => $pdf,
		'file_name'   => $fn,
		'ref'         => 'gmail:' . $msg_id,
		'source'      => 'email',
	) );
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_jem_g_pdf', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jem_g_pdf' );
	$id = sanitize_text_field( wp_unslash( $_GET['msg'] ?? '' ) ); // phpcs:ignore
	$c  = ( (array) get_option( 'jem_g_cache', array() ) )[ $id ] ?? null;
	if ( ! $c || $c['fields']['pdf'] < 0 ) {
		wp_die( 'PDF sa nenašlo.' );
	}
	$a   = $c['atts'][ $c['fields']['pdf'] ];
	$bin = jem_g_attachment( $c['account'], $id, $a['id'] );
	if ( is_wp_error( $bin ) ) {
		wp_die( esc_html( $bin->get_error_message() ) );
	}
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( "Content-Disposition: inline; filename*=UTF-8''" . rawurlencode( $a['name'] ) );
	header( 'X-Content-Type-Options: nosniff' );
	echo $bin; // phpcs:ignore
	exit;
} );

add_action( 'admin_post_jem_g_save_client', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jem_g_save_client' );
	$in  = wp_unslash( $_POST );
	$set = array( 'client_id' => sanitize_text_field( $in['client_id'] ?? '' ) );
	if ( '' !== trim( (string) ( $in['client_secret'] ?? '' ) ) ) {
		$set['client_secret'] = jem_encrypt( trim( (string) $in['client_secret'] ) );
	}
	jem_g_update( $set );
	jem_g_back( 'Údaje klienta Google sú uložené.', 'success' );
} );

add_action( 'admin_post_jem_g_connect', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jem_g_connect' );
	$s = jem_g_settings();
	if ( '' === $s['client_id'] || '' === jem_decrypt( $s['client_secret'] ) ) {
		jem_g_back( 'Najprv zadajte ID klienta a tajný kľúč z Google Cloud.' );
	}
	wp_redirect( jem_g_auth_url() ); // phpcs:ignore
	exit;
} );

add_action( 'admin_post_jem_g_disconnect', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jem_g_disconnect' );
	$email = sanitize_email( wp_unslash( $_POST['account'] ?? '' ) ); // phpcs:ignore
	$s     = jem_g_settings();
	if ( isset( $s['accounts'][ $email ] ) ) {
		$ref = jem_decrypt( $s['accounts'][ $email ]['refresh'] ?? '' );
		if ( $ref ) {
			wp_remote_post( 'https://oauth2.googleapis.com/revoke', array( 'timeout' => 10, 'body' => array( 'token' => $ref ) ) );
		}
		unset( $s['accounts'][ $email ] );
		jem_g_update( array( 'accounts' => $s['accounts'] ) );
		delete_transient( 'jem_g_at_' . md5( $email ) );
	}
	jem_g_back( 'Účet ' . $email . ' je odpojený a prístup v Google zrušený.', 'success' );
} );

add_action( 'admin_post_jem_g_labels', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jem_g_labels' );
	$in    = wp_unslash( $_POST );
	$email = sanitize_email( $in['account'] ?? '' );
	$s     = jem_g_settings();
	if ( ! isset( $s['accounts'][ $email ] ) ) {
		jem_g_back( 'Účet neexistuje.' );
	}
	$labels = array();
	foreach ( (array) ( $in['watch'] ?? array() ) as $lid => $on ) {
		$lid            = sanitize_text_field( $lid );
		$labels[ $lid ] = array(
			'name'       => sanitize_text_field( $in['lname'][ $lid ] ?? $lid ),
			'partner_id' => (int) ( $in['partner'][ $lid ] ?? 0 ),
			'category'   => sanitize_text_field( $in['category'][ $lid ] ?? '' ),
		);
	}
	$s['accounts'][ $email ]['labels'] = $labels;
	$since = sanitize_text_field( $in['since'] ?? '' );
	jem_g_update( array( 'accounts' => $s['accounts'], 'since' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) ? $since : '' ) );
	jem_g_back( 'Štítky sú uložené (' . count( $labels ) . '). Teraz načítajte faktúry.', 'success', array( 'acc' => $email ) );
} );

add_action( 'admin_post_jem_g_import', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jem_g_import' );
	$ids = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['msg'] ?? array() ) ); // phpcs:ignore
	if ( ! $ids ) {
		jem_g_back( 'Vyberte faktúry na prevzatie.' );
	}
	@set_time_limit( 300 ); // phpcs:ignore
	$ok   = 0;
	$errs = array();
	$amounts = (array) wp_unslash( $_POST['amount'] ?? array() ); // phpcs:ignore
	$vss     = (array) wp_unslash( $_POST['vs'] ?? array() ); // phpcs:ignore
	foreach ( $ids as $id ) {
		$r = jem_g_import( $id, array(
			'total' => isset( $amounts[ $id ] ) && '' !== trim( (string) $amounts[ $id ] ) ? jem_g_num( $amounts[ $id ] ) : null,
			'vs'    => isset( $vss[ $id ] ) ? sanitize_text_field( $vss[ $id ] ) : '',
		) );
		if ( is_wp_error( $r ) ) {
			$errs[] = $r->get_error_message();
		} else {
			++$ok;
		}
	}
	if ( function_exists( 'juc_autopost_run' ) && $ok ) {
		juc_autopost_run();
	}
	jem_g_back( 'Prevzatých faktúr: ' . $ok . '.' . ( $errs ? ' Chyby: ' . implode( ' · ', array_slice( array_unique( $errs ), 0, 5 ) ) : ' Úhrady z banky sa spárovali podľa VS a sumy.' ), $errs ? 'warning' : 'success', array( 'show' => 1 ) );
} );

/* ---------------------------------------------------------------- Stránka */

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-emails', 'Faktúry z e-mailu – Jupiter Pošta', 'Faktúry z e-mailu', JEM_CAP, 'j-emails-invoices', 'jem_g_page' );
}, 20 );

function jem_g_partners() {
	global $wpdb;
	if ( ! function_exists( 'jinv_table' ) ) {
		return array();
	}
	return (array) $wpdb->get_results( 'SELECT id, name, category FROM ' . jinv_table( 'partners' ) . ' WHERE active = 1 ORDER BY name', ARRAY_A ); // phpcs:ignore
}

function jem_g_page() {
	if ( ! current_user_can( JEM_CAP ) ) {
		return;
	}
	$s   = jem_g_settings();
	$msg = get_transient( 'jem_g_msg_' . get_current_user_id() );
	delete_transient( 'jem_g_msg_' . get_current_user_id() );
	echo '<div class="wrap jem"><h1>Faktúry z e-mailu <span class="jem-ver">Jupiter Pošta v' . esc_html( JEM_VERSION ) . '</span></h1>';
	if ( $msg ) {
		echo '<div class="notice notice-' . esc_attr( $msg[0] ) . ' is-dismissible"><p>' . esc_html( $msg[1] ) . '</p></div>';
	}
	echo '<p class="description">Externý e-mailový účet Google (Gmail), na ktorý chodia faktúry. Program má prístup len na čítanie a len keď ho povolíte. Vyberiete štítky s faktúrami (napr. T-COM) a faktúry s PDF prevezmete do J-Fakturácie – úhrada z banky sa spáruje sama podľa variabilného symbolu.</p>';

	$ready = '' !== $s['client_id'] && '' !== jem_decrypt( $s['client_secret'] );
	// 1) Klient Google.
	echo '<details class="jem-g-box" ' . ( $ready ? '' : 'open' ) . '><summary><strong>1. Prepojenie s Google</strong> ' . ( $ready ? '<span class="jem-ok">✓ nastavené</span>' : '<span class="jem-warn">nenastavené</span>' ) . '</summary>';
	?>
	<ol>
		<li>Otvorte <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud Console</a> a vytvorte projekt (napr. „Faktúry e-shop“).</li>
		<li><a href="https://console.cloud.google.com/apis/library/gmail.googleapis.com" target="_blank" rel="noopener">Zapnite Gmail API</a> (Povoliť).</li>
		<li><a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener">Google Auth Platform</a> → Začať: názov aplikácie, váš e-mail, publikum <strong>Externé</strong>. V časti <em>Publikum</em> pridajte svoj Gmail ako testovacieho používateľa (alebo aplikáciu zverejnite – v režime Testovanie platí prístup len 7 dní).</li>
		<li><a href="https://console.cloud.google.com/auth/clients" target="_blank" rel="noopener">Klienti</a> → Vytvoriť klienta → typ <strong>Webová aplikácia</strong> → Autorizované URI presmerovania:<br><code style="user-select:all"><?php echo esc_html( jem_g_redirect_uri() ); ?></code></li>
		<li>Skopírujte <strong>ID klienta</strong> a <strong>tajný kľúč klienta</strong> sem:</li>
	</ol>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'jem_g_save_client' ); ?><input type="hidden" name="action" value="jem_g_save_client">
		<table class="form-table" role="presentation">
			<tr><th><label for="g-id">ID klienta</label></th><td><input id="g-id" name="client_id" class="large-text" value="<?php echo esc_attr( $s['client_id'] ); ?>" placeholder="…apps.googleusercontent.com" autocomplete="off"></td></tr>
			<tr><th><label for="g-sec">Tajný kľúč klienta</label></th><td><input id="g-sec" name="client_secret" type="password" class="regular-text" placeholder="<?php echo $ready ? 'uložený – zadajte len pri zmene' : 'GOCSPX-…'; ?>" autocomplete="new-password"><p class="description">Uloží sa zašifrovaný kľúčom webu.</p></td></tr>
		</table>
		<p><button class="button">Uložiť</button></p>
	</form>
	</details>
	<?php
	// 2) Účty.
	echo '<h2>2. Účty Google</h2>';
	if ( ! $s['accounts'] ) {
		echo '<p class="jem-muted">Zatiaľ nie je pripojený žiadny účet.</p>';
	}
	$partners = jem_g_partners();
	$cats     = apply_filters( 'jinv_expense_categories', array( 'Tovar', 'Materiál', 'Doprava a poštovné', 'Služby', 'Hosting a softvér', 'Reklama', 'Kancelária', 'Nájom a energie', 'Telefón a internet', 'Poplatky' ) );
	$open     = sanitize_email( wp_unslash( $_GET['acc'] ?? '' ) ); // phpcs:ignore
	foreach ( $s['accounts'] as $email => $acc ) {
		echo '<div class="jem-g-acc"><h3>' . esc_html( $email ) . ' <small class="jem-muted">pripojený ' . esc_html( mysql2date( 'j. n. Y H:i', $acc['connected'] ?? '' ) ) . ', len čítanie</small></h3>';
		$labels = ( $open === $email || ! $acc['labels'] ) ? jem_g_labels( $email ) : null;
		if ( is_wp_error( $labels ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $labels->get_error_message() ) . '</p></div>';
		} elseif ( null === $labels ) {
			$names = wp_list_pluck( $acc['labels'], 'name' );
			echo '<p>Sledované štítky: <strong>' . esc_html( implode( ', ', $names ) ) . '</strong> · <a href="' . esc_url( jem_g_url( array( 'acc' => $email ) ) ) . '">Zmeniť štítky</a></p>';
		} else {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'jem_g_labels' ); ?><input type="hidden" name="action" value="jem_g_labels"><input type="hidden" name="account" value="<?php echo esc_attr( $email ); ?>">
				<table class="widefat striped jem-g-labels"><thead><tr><th>Sledovať</th><th>Štítok</th><th>Dodávateľ (J-Fakturácia)</th><th>Kategória nákladu</th></tr></thead><tbody>
				<?php foreach ( $labels as $lid => $lname ) : $c = $acc['labels'][ $lid ] ?? null; ?>
					<tr>
						<td><input type="checkbox" name="watch[<?php echo esc_attr( $lid ); ?>]" value="1" <?php checked( (bool) $c ); ?>><input type="hidden" name="lname[<?php echo esc_attr( $lid ); ?>]" value="<?php echo esc_attr( $lname ); ?>"></td>
						<td><?php echo esc_html( $lname ); ?></td>
						<td><select name="partner[<?php echo esc_attr( $lid ); ?>]"><option value="0">— podľa odosielateľa —</option><?php foreach ( $partners as $p ) : ?><option value="<?php echo (int) $p['id']; ?>" <?php selected( (int) ( $c['partner_id'] ?? 0 ), (int) $p['id'] ); ?>><?php echo esc_html( $p['name'] ); ?></option><?php endforeach; ?></select></td>
						<td><input name="category[<?php echo esc_attr( $lid ); ?>]" list="jem-cats" value="<?php echo esc_attr( $c['category'] ?? '' ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<datalist id="jem-cats"><?php foreach ( $cats as $cat ) { echo '<option value="' . esc_attr( $cat ) . '">'; } ?></datalist>
				<p><label>Faktúry od dátumu <input type="date" name="since" value="<?php echo esc_attr( $s['since'] ? $s['since'] : current_time( 'Y' ) . '-01-01' ); ?>"></label> <button class="button button-primary">Uložiť štítky</button></p>
			</form>
			<?php
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Odpojiť účet a zrušiť prístup v Google?\');">';
		wp_nonce_field( 'jem_g_disconnect' );
		echo '<input type="hidden" name="action" value="jem_g_disconnect"><input type="hidden" name="account" value="' . esc_attr( $email ) . '"><button class="button-link jem-danger">Odpojiť účet</button></form></div>';
	}
	if ( $ready ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'jem_g_connect' );
		echo '<input type="hidden" name="action" value="jem_g_connect"><button class="button button-primary">+ Pripojiť účet Google</button> <span class="description">Google sa opýta na povolenie „Zobrazovať e-mailové správy a nastavenia“ (len čítanie).</span></form>';
	}

	// 3) Faktúry.
	$watched = 0;
	foreach ( $s['accounts'] as $acc ) {
		$watched += count( $acc['labels'] );
	}
	if ( ! $watched ) {
		echo '</div>';
		return;
	}
	echo '<h2>3. Faktúry</h2>';
	if ( empty( $_GET['show'] ) ) { // phpcs:ignore
		echo '<p><a class="button button-primary" href="' . esc_url( jem_g_url( array( 'show' => 1 ) ) ) . '">Načítať faktúry zo sledovaných štítkov</a></p></div>';
		return;
	}
	@set_time_limit( 300 ); // phpcs:ignore
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'jem_g_import' ); ?><input type="hidden" name="action" value="jem_g_import">
	<?php
	foreach ( $s['accounts'] as $email => $acc ) {
		foreach ( $acc['labels'] as $lid => $cfg ) {
			$rows = jem_g_fetch( $email, $lid, $s['since'] );
			$pn   = '';
			foreach ( $partners as $p ) {
				$pn = (int) $p['id'] === (int) $cfg['partner_id'] ? $p['name'] : $pn;
			}
			echo '<h3>' . esc_html( $cfg['name'] ) . ' <small class="jem-muted">' . esc_html( $email . ( $pn ? ' · ' . $pn : '' ) . ( $cfg['category'] ? ' · ' . $cfg['category'] : '' ) ) . '</small></h3>';
			if ( is_wp_error( $rows ) ) {
				echo '<div class="notice notice-error inline"><p>' . esc_html( $rows->get_error_message() ) . '</p></div>';
				continue;
			}
			if ( ! $rows ) {
				echo '<p class="jem-muted">Žiadne správy s PDF od ' . esc_html( $s['since'] ) . '.</p>';
				continue;
			}
			echo '<table class="widefat striped jem-g-inv"><thead><tr><th class="check-column"><input type="checkbox" onclick="var t=this;this.closest(\'table\').querySelectorAll(\'tbody input[type=checkbox]:not(:disabled)\').forEach(function(c){c.checked=t.checked;});"></th><th>Dátum</th><th>Predmet</th><th>Obdobie</th><th>VS / číslo</th><th class="num">Suma</th><th>PDF</th><th>Stav</th></tr></thead><tbody>';
			foreach ( $rows as $id => $r ) {
				$f   = $r['fields'];
				$doc = jem_g_imported( $id );
				$st  = $doc ? '<a href="' . esc_url( admin_url( 'admin.php?page=j-invoices-received&edit=' . $doc ) ) . '">✓ prevzatá</a>' : ( $f['total'] > 0 ? 'nová' : '<span class="jem-warn">suma nenájdená – doplňte ju (otvorte PDF)</span>' );
				echo '<tr><td><input type="checkbox" name="msg[]" value="' . esc_attr( $id ) . '" ' . disabled( (bool) $doc, true, false ) . ' ' . checked( ! $doc && $f['total'] > 0, true, false ) . '></td>';
				echo '<td>' . esc_html( wp_date( 'j. n. Y', strtotime( $r['date'] ) ) ) . '</td><td>' . esc_html( $r['subject'] ) . '</td>';
				echo '<td>' . esc_html( $f['period_from'] ? wp_date( 'j. n.', strtotime( $f['period_from'] ) ) . ' – ' . wp_date( 'j. n. Y', strtotime( $f['period_to'] ) ) : '' ) . '</td>';
				if ( ! $doc && $f['total'] <= 0 ) {
					// Suma sa nenašla ani v správe, ani v PDF – zadá sa ručne (otvorte PDF).
					echo '<td><input name="vs[' . esc_attr( $id ) . ']" value="' . esc_attr( $f['vs'] ) . '" placeholder="VS" style="width:110px"></td>';
					echo '<td class="num"><input name="amount[' . esc_attr( $id ) . ']" placeholder="suma €" inputmode="decimal" style="width:90px;text-align:right" oninput="var c=this.closest(\'tr\').querySelector(\'input[type=checkbox]\');c.checked=this.value!==\'\';"></td>';
				} else {
					echo '<td>' . esc_html( $f['vs'] . ( $f['number'] && $f['number'] !== $f['vs'] ? ' / ' . $f['number'] : '' ) ) . '</td>';
					echo '<td class="num"><strong>' . esc_html( $f['total'] > 0 ? number_format_i18n( $f['total'], 2 ) . ' €' : '—' ) . '</strong></td>';
				}
				$pdf_url = $f['pdf'] >= 0 ? wp_nonce_url( admin_url( 'admin-post.php?action=jem_g_pdf&msg=' . rawurlencode( $id ) ), 'jem_g_pdf' ) : '';
				echo '<td>' . ( $pdf_url ? '<a href="' . esc_url( $pdf_url ) . '" target="_blank">' . esc_html( $r['atts'][ $f['pdf'] ]['name'] ) . '</a>' : '—' ) . '</td><td>' . $st . '</td></tr>'; // phpcs:ignore
			}
			echo '</tbody></table>';
		}
	}
	?>
		<p><button class="button button-primary button-large">Prevziať vybrané do J-Fakturácie</button> <span class="description">Vznikne prijatá faktúra s PDF, dodávateľom a kategóriou podľa štítka; ak je v banke platba s rovnakým VS a sumou, spáruje sa a zaúčtuje.</span></p>
	</form>
	</div>
	<?php
}
