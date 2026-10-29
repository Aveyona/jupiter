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
	if ( ! empty( $GLOBALS['jem_smtp_skip'] ) || ! jem_configured() || ! jem_opt( 'site_mail' ) ) {
		return;
	}
	$GLOBALS['jem_smtp_used'] = true;
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

/**
 * Poistka: keď SMTP schránky zlyhá, e-mail webu (objednávka, obnova hesla…) sa pošle ešte raz štandardne
 * cez server, aby sa nestratil. Zlyhanie sa zapíše a v administrácii sa zobrazí upozornenie.
 */
add_action( 'wp_mail_failed', function ( $error ) {
	if ( empty( $GLOBALS['jem_smtp_used'] ) || ! empty( $GLOBALS['jem_smtp_skip'] ) ) {
		return;
	}
	$GLOBALS['jem_smtp_used'] = false;
	update_option( 'jem_smtp_failed', array( 'time' => time(), 'msg' => mb_substr( $error->get_error_message(), 0, 300 ) ), false );
	if ( ! empty( $GLOBALS['jem_no_fallback'] ) ) {
		return;
	}
	$d = (array) $error->get_error_data();
	if ( empty( $d['to'] ) ) {
		return;
	}
	$GLOBALS['jem_smtp_skip'] = true;
	try {
		wp_mail( $d['to'], $d['subject'] ?? '', $d['message'] ?? '', $d['headers'] ?? array(), $d['attachments'] ?? array() );
	} finally {
		$GLOBALS['jem_smtp_skip'] = false;
	}
} );

// Úspešné odoslanie cez SMTP – príznak pre ďalší e-mail v tej istej požiadavke sa vynuluje.
add_action( 'wp_mail_succeeded', function () {
	$GLOBALS['jem_smtp_used'] = false;
} );

// Upozornenie v administrácii po zlyhaní SMTP (posledných 24 hodín).
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = jem_settings();
	if ( '' !== $s['pass'] && '' === jem_decrypt( $s['pass'] ) ) {
		echo '<div class="notice notice-error"><p><strong>J-Emails:</strong> heslo schránky sa nedá prečítať (zmenili sa bezpečnostné kľúče WordPressu). E-maily webu idú zatiaľ cez server webu a schránka sa nenačíta. <a href="' . esc_url( admin_url( 'admin.php?page=j-emails-settings' ) ) . '">Zadajte heslo znova</a>.</p></div>';
	}
	$f = get_option( 'jem_smtp_failed' );
	if ( ! is_array( $f ) || empty( $f['time'] ) || time() - (int) $f['time'] > DAY_IN_SECONDS || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>J-Emails:</strong> odoslanie e-mailu webu cez SMTP schránky zlyhalo (' . esc_html( wp_date( 'j. n. Y H:i', (int) $f['time'] ) ) . ': ' . esc_html( (string) $f['msg'] ) . '). E-mail sa poslal náhradne cez server webu. <a href="' . esc_url( admin_url( 'admin.php?page=j-emails-settings' ) ) . '">Skontrolujte nastavenia</a> – po úspešnom skúšobnom e-maile upozornenie zmizne.</p></div>';
} );
