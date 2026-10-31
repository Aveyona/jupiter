<?php
/** Odosielanie cez SMTP schránky (PHPMailer z WordPressu) a uloženie kópie do priečinka Odoslané. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jem_mailer() {
	require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
	require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
	require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
	$s = jem_settings();
	$m = new PHPMailer\PHPMailer\PHPMailer( true );
	$m->isSMTP();
	$m->Host        = $s['smtp_host'];
	$m->Port        = (int) $s['smtp_port'];
	$m->SMTPAuth    = true;
	$m->Username    = '' !== $s['user'] ? $s['user'] : $s['email'];
	$m->Password    = jem_decrypt( $s['pass'] );
	$m->SMTPSecure  = 'ssl' === $s['smtp_secure'] ? 'ssl' : ( 'tls' === $s['smtp_secure'] ? 'tls' : '' );
	$m->SMTPAutoTLS = 'none' !== $s['smtp_secure'];
	$m->CharSet     = 'UTF-8';
	$m->Encoding    = 'quoted-printable';
	$m->Timeout     = 20;
	$m->XMailer     = ' ';
	return $m;
}

/** @return true|string */
function jem_smtp_test() {
	if ( ! jem_configured() ) {
		return 'Schránka nie je nastavená.';
	}
	try {
		$m = jem_mailer();
		if ( $m->smtpConnect() ) {
			$m->smtpClose();
			return true;
		}
		return 'Nepodarilo sa prihlásiť.';
	} catch ( \Throwable $e ) {
		return $e->getMessage();
	}
}

/** „a@b.sk, Meno <c@d.sk>“ → platné adresy. */
function jem_recipients( $value ) {
	$out = array();
	foreach ( jem_addresses( str_replace( ';', ',', (string) $value ) ) as $a ) {
		if ( is_email( $a[1] ) ) {
			$out[] = $a;
		}
	}
	return $out;
}

/**
 * Odoslanie správy.
 *
 * @param array $d to, cc, bcc, subject, body (text) alebo html, from (adresa schránky alebo aliasu),
 *                 in_reply_to, references, files (z $_FILES), att_data ([[obsah, názov, typ], …] – prílohy pri preposlaní)
 * @return true|string
 */
function jem_send( array $d ) {
	$s    = jem_settings();
	$to   = jem_recipients( $d['to'] ?? '' );
	$mine = jem_my_addresses();
	if ( ! $to ) {
		return 'Zadajte aspoň jedného platného príjemcu.';
	}
	$from = strtolower( trim( (string) ( $d['from'] ?? '' ) ) );
	if ( '' === $from || ! isset( $mine[ $from ] ) ) {
		$from = strtolower( $s['email'] );
	}
	try {
		$m = jem_mailer();
		$m->setFrom( $from, '' !== (string) ( $mine[ $from ] ?? '' ) ? $mine[ $from ] : $s['name'] );
		foreach ( $to as $a ) {
			$m->addAddress( $a[1], $a[0] );
		}
		foreach ( jem_recipients( $d['cc'] ?? '' ) as $a ) {
			$m->addCC( $a[1], $a[0] );
		}
		foreach ( jem_recipients( $d['bcc'] ?? '' ) as $a ) {
			$m->addBCC( $a[1], $a[0] );
		}
		$m->Subject = (string) ( $d['subject'] ?? '' );
		if ( '' !== trim( (string) ( $d['html'] ?? '' ) ) ) {
			$m->isHTML( true );
			$m->Body    = '<!doctype html><html><head><meta charset="utf-8"></head><body><div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:#222">' . $d['html'] . '</div></body></html>';
			$m->AltBody = preg_replace( "/\r?\n/", "\r\n", jem_html_to_text( $d['html'] ) );
		} else {
			$m->isHTML( false );
			$m->Body = preg_replace( "/\r?\n/", "\r\n", (string) ( $d['body'] ?? '' ) );
		}
		foreach ( (array) ( $d['att_data'] ?? array() ) as $a ) {
			$m->addStringAttachment( (string) $a[0], sanitize_file_name( (string) $a[1] ), 'base64', (string) ( $a[2] ?? 'application/octet-stream' ) );
		}
		if ( ! empty( $d['in_reply_to'] ) ) {
			$m->addCustomHeader( 'In-Reply-To', $d['in_reply_to'] );
			$m->addCustomHeader( 'References', trim( ( $d['references'] ?? '' ) . ' ' . $d['in_reply_to'] ) );
		}
		$files = $d['files'] ?? array();
		if ( ! empty( $files['name'] ) && is_array( $files['name'] ) ) {
			foreach ( $files['name'] as $i => $name ) {
				if ( UPLOAD_ERR_OK === (int) $files['error'][ $i ] && is_uploaded_file( $files['tmp_name'][ $i ] ) ) {
					$m->addAttachment( $files['tmp_name'][ $i ], sanitize_file_name( $name ) );
				}
			}
		}
		$m->send();
	} catch ( \Throwable $e ) {
		return 'Správu sa nepodarilo odoslať: ' . $e->getMessage();
	}
	// Kópia do priečinka Odoslané (ak sa nepodarí, správa je aj tak odoslaná).
	$imap = jem_connect();
	if ( ! is_wp_error( $imap ) ) {
		$f = jem_folder_map( $imap );
		if ( '' !== $f['sent'] ) {
			$mime = $m->getSentMIMEMessage();
			$bcc  = $m->getBccAddresses();
			if ( $bcc ) { // kópia v Odoslaných si pamätá aj skrytých príjemcov (ako Gmail)
				$mime = 'Bcc: ' . implode( ', ', array_map( fn( $a ) => $a[0], $bcc ) ) . "\r\n" . $mime;
			}
			$imap->append( $f['sent'], $mime );
		}
		if ( ! empty( $d['reply_uid'] ) ) {
			$imap->select( $d['reply_folder'] ?? 'INBOX' );
			$imap->flag( (int) $d['reply_uid'], '\\Answered', true );
		}
		$imap->close();
	}
	return true;
}

/**
 * Všetky e-maily webu (wp_mail – WooCommerce, faktúry, registrácia…) cez SMTP schránky.
 * Odosielateľ, ktorý nie je adresou schránky ani jej aliasom, sa nahradí adresou schránky: server schránky
 * (napr. WebSupport) cudziu adresu odmietne („Údaje neboli akceptované“) – aj adresu tej istej domény, ktorá
 * neexistuje, napr. predvolenú wordpress@domena.sk z e-mailov WordPressu. Pôvodná skutočná adresa ide do Odpovedať.
 */
add_action( 'phpmailer_init', function ( $m ) {
	if ( ! empty( $GLOBALS['jem_smtp_skip'] ) || ! jem_configured() || ! jem_opt( 'site_mail' ) ) {
		return;
	}
	$s = jem_settings();
	$m->isSMTP();
	$m->Host        = $s['smtp_host'];
	$m->Port        = (int) $s['smtp_port'];
	$m->SMTPAuth    = true;
	$m->Username    = '' !== $s['user'] ? $s['user'] : $s['email'];
	$m->Password    = jem_decrypt( $s['pass'] );
	$m->SMTPSecure  = 'ssl' === $s['smtp_secure'] ? 'ssl' : ( 'tls' === $s['smtp_secure'] ? 'tls' : '' );
	$m->SMTPAutoTLS = 'none' !== $s['smtp_secure'];
	$m->Timeout     = 20;
	$from = strtolower( trim( (string) $m->From ) );
	$mine = jem_my_addresses();
	if ( ! isset( $mine[ $from ] ) ) {
		$name = $m->FromName && 'WordPress' !== $m->FromName ? $m->FromName : $s['name'];
		// Odpoveď na pôvodnú adresu (napr. zákazník z kontaktného formulára), nie na predvolenú wordpress@….
		if ( is_email( $from ) && 0 !== strpos( $from, 'wordpress@' ) && ! $m->getReplyToAddresses() ) {
			$m->addReplyTo( $from, 'WordPress' !== $m->FromName ? (string) $m->FromName : '' );
		}
		$m->setFrom( $s['email'], $name, false );
	}
	$m->Sender = $s['email'];
}, 20 );

/** wp_mail s už pripravenými (prefiltrovanými) údajmi e-mailu. */
function jem_wp_mail_atts( array $a ) {
	$args = array( $a['to'] ?? '', $a['subject'] ?? '', $a['message'] ?? '', $a['headers'] ?? '', $a['attachments'] ?? array() );
	if ( array_key_exists( 'embeds', $a ) ) {
		$args[] = $a['embeds'];
	}
	return wp_mail( ...$args );
}

/**
 * Poistka: keď SMTP schránky zlyhá, e-mail webu (objednávka, obnova hesla…) sa hneď pošle ešte raz štandardne
 * cez server – s pôvodnými hlavičkami (Od, Odpovedať, Kópia…), prílohami a výsledkom pre volajúceho
 * (napr. J-Fakturácia tak nepovažuje doklad za neodoslaný a nepošle ho druhýkrát).
 */
add_filter( 'pre_wp_mail', function ( $return, $atts ) {
	if ( null !== $return || ! empty( $GLOBALS['jem_in_send'] ) || ! jem_configured() || ! jem_opt( 'site_mail' ) ) {
		return $return;
	}
	global $wp_filter;
	$GLOBALS['jem_in_send'] = true;
	// Údaje už prešli filtrom wp_mail – pri vnútornom odoslaní sa nesmú upraviť druhýkrát.
	$saved = $wp_filter['wp_mail'] ?? null;
	unset( $wp_filter['wp_mail'] );
	$err = '';
	$cb  = function ( $e ) use ( &$err ) {
		$err = $e->get_error_message();
	};
	add_action( 'wp_mail_failed', $cb );
	try {
		$ok = jem_wp_mail_atts( (array) $atts );
		if ( ! $ok && empty( $GLOBALS['jem_no_fallback'] ) ) {
			$smtp_err                 = $err;
			$GLOBALS['jem_smtp_skip'] = true;
			$ok                       = jem_wp_mail_atts( (array) $atts );
			update_option( 'jem_smtp_failed', array( 'time' => time(), 'msg' => mb_substr( $smtp_err, 0, 300 ), 'fallback' => $ok ? 'ok' : 'fail' ), false );
		}
	} finally {
		remove_action( 'wp_mail_failed', $cb );
		if ( null !== $saved ) {
			$wp_filter['wp_mail'] = $saved;
		}
		$GLOBALS['jem_in_send']   = false;
		$GLOBALS['jem_smtp_skip'] = false;
	}
	return (bool) $ok;
}, 10, 2 );

// Upozornenie v administrácii po zlyhaní SMTP (posledných 24 hodín).
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = jem_settings();
	if ( '' !== $s['pass'] && '' === jem_decrypt( $s['pass'] ) ) {
		echo '<div class="notice notice-error"><p><strong>J-Pošta:</strong> heslo schránky sa nedá prečítať (zmenili sa bezpečnostné kľúče WordPressu). E-maily webu idú zatiaľ cez server webu a schránka sa nenačíta. <a href="' . esc_url( admin_url( 'admin.php?page=j-emails-settings' ) ) . '">Zadajte heslo znova</a>.</p></div>';
	}
	$f = get_option( 'jem_smtp_failed' );
	if ( ! is_array( $f ) || empty( $f['time'] ) || time() - (int) $f['time'] > DAY_IN_SECONDS || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$fb = 'ok' === ( $f['fallback'] ?? '' ) ? 'E-mail sa poslal náhradne cez server webu.' : 'Nepodarilo sa ho poslať ani náhradne cez server webu.';
	echo '<div class="notice ' . ( 'ok' === ( $f['fallback'] ?? '' ) ? 'notice-warning' : 'notice-error' ) . '"><p><strong>J-Pošta:</strong> odoslanie e-mailu webu cez SMTP schránky zlyhalo (' . esc_html( wp_date( 'j. n. Y H:i', (int) $f['time'] ) ) . ': ' . esc_html( (string) $f['msg'] ) . '). ' . esc_html( $fb ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=j-emails-settings' ) ) . '">Skontrolujte nastavenia</a> – po úspešnom skúšobnom e-maile upozornenie zmizne.</p></div>';
} );
