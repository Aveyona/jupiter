<?php
/** Nastavenia J-Dopravy (Packeta): prístupy k API (heslo šifrované), odosielateľ, hmotnosť, štítky, sledovanie. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jdp_defaults() {
	return array(
		'api_password' => '',        // šifrované (jdp_encrypt)
		'api_key'      => '',        // kľúč pre mapu výdajných miest (16 znakov); prázdne = prvých 16 znakov hesla
		'sender'       => '',        // označenie odosielateľa (eshop) z klientskej zóny Packety
		'country'      => 'sk',      // krajiny výdajných miest (sk, cz…)
		'weight_min'   => 0.1,       // minimálna hmotnosť zásielky (kg)
		'weight_add'   => 0.05,      // hmotnosť obalu (kg)
		'label_format' => 'A6 on A4',
		'cod_methods'  => 'cod',     // platobné metódy = dobierka
		'auto_complete' => 1,        // po doručení nastaviť objednávku Vybavená
		'track_email'  => 1,         // odkaz na sledovanie v e-maile zákazníkovi
		'auto_create'  => 0,         // podať zásielku Packeta automaticky, keď objednávka prejde do stavu Spracováva sa
		// Slovenská pošta – ePodací hárok
		'post_match'   => 'pošta',   // text v názve spôsobu dopravy, podľa ktorého sa spozná doprava poštou
		'post_kind'    => '1',       // druh zásielky: 1 doporučený list, 4 balík, 14 zmluvný balík
		'post_class'   => '1',       // trieda (1 / 2) pri liste a balíku
		'post_name'    => '',
		'post_org'     => '',
		'post_street'  => '',
		'post_city'    => '',
		'post_zip'     => '',
		'post_phone'   => '',
		'post_email'   => '',
		'post_iban'    => '',        // účet pre dobierky poštou
	);
}

/** Formáty štítkov Packeta: kód API => popis. */
function jdp_label_formats() {
	return array( 'A6 on A4' => 'A6 na A4 (4 štítky na stranu)', 'A6 on A6' => 'A6 (tlačiareň štítkov)', 'A7 on A4' => 'A7 na A4 (8 na stranu)', 'A7 on A7' => 'A7', '105x35mm on A4' => '105 × 35 mm na A4' );
}

function jdp_settings() {
	return wp_parse_args( (array) get_option( 'jdp_settings', array() ), jdp_defaults() );
}

function jdp_opt( $k ) {
	$s = jdp_settings();
	return $s[ $k ] ?? null;
}

function jdp_key() {
	return hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' ) . 'j-doprava', true );
}

function jdp_encrypt( $plain ) {
	if ( '' === (string) $plain ) {
		return '';
	}
	$iv  = random_bytes( 12 );
	$tag = '';
	$ct  = openssl_encrypt( (string) $plain, 'aes-256-gcm', jdp_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return 'v1:' . base64_encode( $iv . $tag . $ct );
}

function jdp_decrypt( $stored ) {
	if ( 0 !== strpos( (string) $stored, 'v1:' ) ) {
		return '';
	}
	$raw = base64_decode( substr( $stored, 3 ) );
	$pt  = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', jdp_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
	return false === $pt ? '' : $pt;
}

function jdp_api_password() {
	return jdp_decrypt( (string) jdp_opt( 'api_password' ) );
}

/** API kľúč pre mapu výdajných miest (verejný, 16 znakov). */
function jdp_api_key() {
	$k = trim( (string) jdp_opt( 'api_key' ) );
	return $k ? $k : substr( jdp_api_password(), 0, 16 );
}

function jdp_ready() {
	return '' !== jdp_api_password() && '' !== trim( (string) jdp_opt( 'sender' ) );
}

add_action( 'admin_post_jdp_settings', function () {
	if ( ! current_user_can( JDP_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jdp_settings' );
	$in  = wp_unslash( $_POST );
	$old = jdp_settings();
	$pw  = trim( (string) ( $in['api_password'] ?? '' ) );
	$set = array(
		'api_password'  => '' !== $pw ? jdp_encrypt( $pw ) : ( ! empty( $in['api_password_clear'] ) ? '' : $old['api_password'] ),
		'api_key'       => preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['api_key'] ?? '' ) ),
		'sender'        => sanitize_text_field( $in['sender'] ?? '' ),
		'country'       => preg_replace( '/[^a-z,]/', '', strtolower( (string) ( $in['country'] ?? 'sk' ) ) ),
		'weight_min'    => max( 0, (float) str_replace( ',', '.', (string) ( $in['weight_min'] ?? 0.1 ) ) ),
		'weight_add'    => max( 0, (float) str_replace( ',', '.', (string) ( $in['weight_add'] ?? 0 ) ) ),
		'label_format'  => isset( jdp_label_formats()[ $in['label_format'] ?? '' ] ) ? $in['label_format'] : 'A6 on A4',
		'cod_methods'   => sanitize_text_field( $in['cod_methods'] ?? 'cod' ),
		'auto_complete' => ! empty( $in['auto_complete'] ) ? 1 : 0,
		'track_email'   => ! empty( $in['track_email'] ) ? 1 : 0,
		'auto_create'   => ! empty( $in['auto_create'] ) ? 1 : 0,
		'post_match'    => sanitize_text_field( $in['post_match'] ?? 'pošta' ),
		'post_kind'     => in_array( (string) ( $in['post_kind'] ?? '1' ), array( '1', '2', '4', '14' ), true ) ? (string) $in['post_kind'] : '1',
		'post_class'    => '2' === (string) ( $in['post_class'] ?? '1' ) ? '2' : '1',
		'post_name'     => sanitize_text_field( $in['post_name'] ?? '' ),
		'post_org'      => sanitize_text_field( $in['post_org'] ?? '' ),
		'post_street'   => sanitize_text_field( $in['post_street'] ?? '' ),
		'post_city'     => sanitize_text_field( $in['post_city'] ?? '' ),
		'post_zip'      => preg_replace( '/\s+/', '', sanitize_text_field( $in['post_zip'] ?? '' ) ),
		'post_phone'    => preg_replace( '/[^\d+]/', '', (string) ( $in['post_phone'] ?? '' ) ),
		'post_email'    => sanitize_email( $in['post_email'] ?? '' ),
		'post_iban'     => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $in['post_iban'] ?? '' ) ) ),
	);
	update_option( 'jdp_settings', $set, false );
	$msg = 'Nastavenia sú uložené.';
	if ( jdp_ready() ) {
		$t   = jdp_api( 'senderGetReturnRouting', array( 'senderLabel' => $set['sender'] ) );
		$msg = is_wp_error( $t ) ? 'Nastavenia sú uložené, ale overenie v Packete zlyhalo: ' . $t->get_error_message() : 'Nastavenia sú uložené – spojenie s Packetou funguje (odosielateľ „' . $set['sender'] . '“ existuje).';
	}
	set_transient( 'jdp_msg_' . get_current_user_id(), $msg, 120 );
	wp_safe_redirect( admin_url( 'admin.php?page=j-doprava-settings&jdp_msg=1' ) );
	exit;
} );

// Upozornenie: uložené API heslo sa nedá prečítať (zmenili sa bezpečnostné kľúče WordPressu) – Packeta zmizla z košíka.
add_action( 'admin_notices', function () {
	if ( ! current_user_can( JDP_CAP ) ) {
		return;
	}
	$s = jdp_settings();
	if ( '' !== (string) $s['api_password'] && '' === jdp_api_password() ) {
		echo '<div class="notice notice-error"><p><strong>J-Doprava:</strong> API heslo Packety sa nedá prečítať (zmenili sa bezpečnostné kľúče WordPressu). Spôsoby dopravy Packeta sa preto zákazníkom v košíku nezobrazujú. <a href="' . esc_url( admin_url( 'admin.php?page=j-doprava-settings' ) ) . '">Zadajte API heslo znova</a>.</p></div>';
	}
} );
