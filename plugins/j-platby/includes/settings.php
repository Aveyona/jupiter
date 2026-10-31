<?php
/**
 * Nastavenia J-Platieb: režim (test / ostrý), kľúče Stripe a tajomstvo webhooku.
 * Tajné kľúče sa ukladajú zašifrované (AES-256-GCM, kľúč odvodený zo solí WordPressu).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jpl_defaults() {
	return array(
		'mode'      => 'test',
		'pk_test'   => '',
		'sk_test'   => '',
		'wh_test'   => '',
		'pk_live'   => '',
		'sk_live'   => '',
		'wh_live'   => '',
		'post_fees' => 1,   // poplatok Stripe zaúčtovať do J-Účtovníctva (568 / 261)
		'methods'   => 'card', // druhy platby na platobnej stránke: card = karta + Apple Pay / Google Pay
	);
}

function jpl_settings() {
	return wp_parse_args( (array) get_option( 'jpl_settings', array() ), jpl_defaults() );
}

function jpl_opt( $k ) {
	return jpl_settings()[ $k ] ?? null;
}

function jpl_key() {
	return hash( 'sha256', wp_salt( 'auth' ) . 'j-platby', true );
}

function jpl_encrypt( $plain ) {
	if ( '' === (string) $plain ) {
		return '';
	}
	$iv  = random_bytes( 12 );
	$tag = '';
	$ct  = openssl_encrypt( (string) $plain, 'aes-256-gcm', jpl_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return 'v1:' . base64_encode( $iv . $tag . $ct );
}

function jpl_decrypt( $stored ) {
	if ( 0 !== strpos( (string) $stored, 'v1:' ) ) {
		return '';
	}
	$raw = base64_decode( substr( $stored, 3 ) );
	$pt  = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', jpl_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
	return false === $pt ? '' : $pt;
}

function jpl_mode() {
	return 'live' === jpl_opt( 'mode' ) ? 'live' : 'test';
}

function jpl_is_test() {
	return 'test' === jpl_mode();
}

/** Tajný kľúč aktuálneho režimu (alebo zadaného). */
function jpl_secret( $mode = '' ) {
	$mode = $mode ? $mode : jpl_mode();
	return jpl_decrypt( (string) jpl_opt( 'sk_' . $mode ) );
}

function jpl_publishable( $mode = '' ) {
	$mode = $mode ? $mode : jpl_mode();
	return (string) jpl_opt( 'pk_' . $mode );
}

function jpl_webhook_secret( $mode = '' ) {
	$mode = $mode ? $mode : jpl_mode();
	return jpl_decrypt( (string) jpl_opt( 'wh_' . $mode ) );
}

/** Je platba pripravená (aspoň tajný kľúč aktuálneho režimu)? */
function jpl_ready() {
	return '' !== jpl_secret();
}

function jpl_webhook_url() {
	return rest_url( 'j-platby/v1/stripe' );
}

/** Odkaz do Stripe Dashboardu (test / ostrý). */
function jpl_dashboard_url( $path = '', $mode = '' ) {
	$mode = $mode ? $mode : jpl_mode();
	return 'https://dashboard.stripe.com/' . ( 'test' === $mode ? 'test/' : '' ) . ltrim( $path, '/' );
}

function jpl_msg( $m ) {
	set_transient( 'jpl_msg_' . get_current_user_id(), $m, 120 );
}

add_action( 'admin_post_jpl_settings', function () {
	if ( ! current_user_can( JPL_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jpl_settings' );
	$s    = jpl_settings();
	$errs = array();
	$s['mode']      = 'live' === ( $_POST['mode'] ?? '' ) ? 'live' : 'test';
	$s['post_fees'] = empty( $_POST['post_fees'] ) ? 0 : 1;
	foreach ( array( 'test', 'live' ) as $m ) {
		$pk = trim( sanitize_text_field( wp_unslash( $_POST[ 'pk_' . $m ] ?? '' ) ) );
		if ( '' === $pk || 0 === strpos( $pk, 'pk_' . $m . '_' ) ) {
			$s[ 'pk_' . $m ] = $pk;
		} else {
			$errs[] = 'Publikovateľný kľúč (' . $m . ') musí začínať pk_' . $m . '_';
		}
		$sk = trim( (string) wp_unslash( $_POST[ 'sk_' . $m ] ?? '' ) );
		if ( ! empty( $_POST[ 'sk_' . $m . '_clear' ] ) ) {
			$s[ 'sk_' . $m ] = '';
		} elseif ( '' !== $sk ) {
			if ( preg_match( '/^(sk|rk)_' . $m . '_[A-Za-z0-9]+$/', $sk ) ) {
				$s[ 'sk_' . $m ] = jpl_encrypt( $sk );
			} else {
				$errs[] = 'Tajný kľúč (' . $m . ') musí začínať sk_' . $m . '_ (alebo rk_' . $m . '_)';
			}
		}
		$wh = trim( (string) wp_unslash( $_POST[ 'wh_' . $m ] ?? '' ) );
		if ( ! empty( $_POST[ 'wh_' . $m . '_clear' ] ) ) {
			$s[ 'wh_' . $m ] = '';
		} elseif ( '' !== $wh ) {
			if ( preg_match( '/^whsec_[A-Za-z0-9]+$/', $wh ) ) {
				$s[ 'wh_' . $m ] = jpl_encrypt( $wh );
			} else {
				$errs[] = 'Tajomstvo webhooku (' . $m . ') musí začínať whsec_';
			}
		}
	}
	update_option( 'jpl_settings', $s, false );
	$msg = 'Nastavenia sú uložené.';
	if ( jpl_ready() ) {
		$acc = jpl_stripe( 'GET', 'account' );
		if ( is_wp_error( $acc ) ) {
			$msg .= ' Overenie spojenia so Stripe zlyhalo: ' . $acc->get_error_message();
		} else {
			$name = $acc['settings']['dashboard']['display_name'] ?? ( $acc['business_profile']['name'] ?? '' );
			$msg .= ' Spojenie so Stripe funguje (' . ( jpl_is_test() ? 'testovací režim' : 'ostrý režim' ) . ( $name ? ', účet ' . $name : '' ) . ').';
			if ( ! jpl_is_test() && empty( $acc['charges_enabled'] ) ) {
				$msg .= ' Pozor: účet ešte nemôže prijímať ostré platby – dokončite aktiváciu v Stripe.';
			}
		}
		if ( '' === jpl_webhook_secret() ) {
			$msg .= ' Chýba tajomstvo webhooku – bez neho sa platby nepotvrdia automaticky.';
		}
	}
	if ( $errs ) {
		$msg = implode( ' ', $errs ) . ' – ostatné sa uložilo. ' . $msg;
	}
	jpl_msg( $msg );
	wp_safe_redirect( admin_url( 'admin.php?page=j-platby-settings&jpl_msg=1' ) );
	exit;
} );
