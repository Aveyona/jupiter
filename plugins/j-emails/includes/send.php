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
 * @param array $d to, cc, subject, body, in_reply_to, references, files (z $_FILES)
 * @return true|string
 */
function jem_send( array $d ) {
	$s  = jem_settings();
	$to = jem_recipients( $d['to'] ?? '' );
	if ( ! $to ) {
		return 'Zadajte aspoň jedného platného príjemcu.';
	}
	try {
		$m = jem_mailer();
		$m->setFrom( $s['email'], $s['name'] );
		foreach ( $to as $a ) {
			$m->addAddress( $a[1], $a[0] );
		}
		foreach ( jem_recipients( $d['cc'] ?? '' ) as $a ) {
			$m->addCC( $a[1], $a[0] );
		}
		$m->Subject = (string) ( $d['subject'] ?? '' );
		$m->isHTML( false );
		$m->Body = preg_replace( "/\r?\n/", "\r\n", (string) ( $d['body'] ?? '' ) );
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
			$imap->append( $f['sent'], $m->getSentMIMEMessage() );
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
 * Odosielateľ z cudzej domény sa nahradí adresou schránky (inak by správy padali do spamu).
 */
add_action( 'phpmailer_init', function ( $m ) {
	if ( ! jem_configured() || ! jem_opt( 'site_mail' ) ) {
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
	$domain         = strtolower( substr( strrchr( $s['email'], '@' ), 1 ) );
	$from_domain    = strtolower( substr( strrchr( (string) $m->From, '@' ), 1 ) );
	if ( $from_domain !== $domain ) {
		$name = $m->FromName && 'WordPress' !== $m->FromName ? $m->FromName : $s['name'];
		$m->setFrom( $s['email'], $name, false );
	}
	$m->Sender = $s['email'];
}, 20 );
