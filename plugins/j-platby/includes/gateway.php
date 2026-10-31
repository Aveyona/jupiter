<?php
/**
 * Platobná metóda WooCommerce „Platba kartou“ (jpl_stripe): Stripe Checkout – zákazník platí na zabezpečenej stránke Stripe,
 * potom sa vráti na stránku Ďakujeme. Zaplatenie potvrdí webhook (a pre istotu aj návrat zákazníka).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class JPL_Gateway_Stripe extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'jpl_stripe';
			$this->method_title       = 'Platba kartou (Stripe) – J-Platby';
			$this->method_description = 'Zákazník zaplatí kartou, Apple Pay alebo Google Pay na zabezpečenej platobnej stránke Stripe. Kľúče Stripe sa zadávajú v J-Platby → Nastavenia.';
			$this->has_fields         = false;
			$this->supports           = array( 'products', 'refunds' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title             = $this->get_option( 'title' );
			$this->description       = $this->get_option( 'description' );
			$this->order_button_text = $this->get_option( 'button', 'Zaplatiť kartou' );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'     => array( 'title' => 'Zapnúť', 'type' => 'checkbox', 'label' => 'Platba kartou cez Stripe', 'default' => 'yes' ),
				'title'       => array( 'title' => 'Názov v pokladni', 'type' => 'text', 'default' => 'Platba kartou (Visa, Mastercard, Apple Pay, Google Pay)' ),
				'description' => array( 'title' => 'Popis v pokladni', 'type' => 'textarea', 'default' => 'Po potvrdení objednávky vás presmerujeme na zabezpečenú platobnú stránku Stripe. Údaje o karte nevidíme ani neukladáme.' ),
				'button'      => array( 'title' => 'Text tlačidla', 'type' => 'text', 'default' => 'Zaplatiť kartou' ),
			);
		}

		public function admin_options() {
			echo '<h2>' . esc_html( $this->method_title ) . '</h2>';
			echo '<p>Kľúče Stripe, režim (test / ostrý) a webhook nastavíte v <a href="' . esc_url( admin_url( 'admin.php?page=j-platby-settings' ) ) . '">J-Platby → Nastavenia</a>.' . ( jpl_ready() ? ' Stav: <strong>' . ( jpl_is_test() ? 'testovací režim' : 'ostrý režim' ) . '</strong>.' : ' <strong>Kľúče zatiaľ nie sú zadané – platba sa v pokladni nezobrazí.</strong>' ) . '</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}

		public function is_available() {
			if ( ! jpl_ready() || ! parent::is_available() ) {
				return false;
			}
			// Stripe prijme najmenej 0,50 €.
			if ( WC()->cart && ! is_admin() && (float) $this->get_order_total() > 0 && (float) $this->get_order_total() < 0.5 ) {
				return false;
			}
			return true;
		}

		public function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wp_kses_post( $this->description ) );
			}
			if ( jpl_is_test() ) {
				echo '<p style="color:#b26200"><strong>Testovací režim</strong> – použite testovaciu kartu 4242 4242 4242 4242, ľubovoľný budúci dátum a CVC.</p>';
			}
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			$url   = jpl_create_session( $order );
			if ( is_wp_error( $url ) ) {
				wc_add_notice( 'Platbu kartou sa nepodarilo spustiť: ' . $url->get_error_message() . ' Skúste to prosím znova alebo zvoľte inú platbu.', 'error' );
				$order->add_order_note( 'Stripe: platobnú stránku sa nepodarilo vytvoriť – ' . $url->get_error_message() );
				return array( 'result' => 'failure' );
			}
			return array( 'result' => 'success', 'redirect' => $url );
		}

		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order = wc_get_order( $order_id );
			$pi    = $order ? (string) $order->get_meta( '_jpl_pi' ) : '';
			if ( ! $pi ) {
				return new WP_Error( 'jpl_refund', 'Objednávka nemá platbu Stripe – peniaze vráťte ručne.' );
			}
			$amount = null === $amount ? (float) $order->get_remaining_refund_amount() : (float) $amount;
			if ( $amount <= 0 ) {
				return new WP_Error( 'jpl_refund', 'Suma na vrátenie musí byť kladná.' );
			}
			$r = jpl_stripe( 'POST', 'refunds', array(
				'payment_intent' => $pi,
				'amount'         => jpl_cents( $amount ),
				'reason'         => 'requested_by_customer',
				'metadata'       => array( 'order_id' => $order->get_id(), 'reason' => mb_substr( (string) $reason, 0, 400 ) ),
			), 'jpl-refund-' . $order->get_id() . '-' . jpl_cents( $amount ) . '-' . count( $order->get_refunds() ), (string) $order->get_meta( '_jpl_mode' ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$ids   = (array) $order->get_meta( '_jpl_refunds' );
			$ids[] = $r['id'];
			$order->update_meta_data( '_jpl_refunds', $ids );
			$order->add_order_note( sprintf( 'Stripe: vrátených %s na kartu zákazníka (%s, stav %s). Peniaze prídu zákazníkovi zvyčajne do 5–10 dní.', wc_price( $amount, array( 'currency' => $order->get_currency() ) ), $r['id'], $r['status'] ?? '' ) );
			$order->save();
			return true;
		}
	}

	add_filter( 'woocommerce_payment_gateways', function ( $g ) {
		$g[] = 'JPL_Gateway_Stripe';
		return $g;
	} );
}, 20 );

// Blokový košík / pokladňa.
add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
	if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}
	require_once JPL_DIR . 'includes/blocks.php';
	$registry->register( new JPL_Blocks_Stripe() );
} );

/** Nastavenie platobnej metódy WooCommerce (názov, popis …). */
function jpl_gw_opt( $k, $def = '' ) {
	$s = (array) get_option( 'woocommerce_jpl_stripe_settings', array() );
	return isset( $s[ $k ] ) && '' !== $s[ $k ] ? $s[ $k ] : $def;
}

/** Vytvorí Stripe Checkout Session pre objednávku a vráti URL platobnej stránky. */
function jpl_create_session( WC_Order $order ) {
	if ( ! jpl_ready() ) {
		return new WP_Error( 'jpl_nokey', 'Platba kartou nie je nastavená.' );
	}
	$total = (float) $order->get_total();
	if ( $total < 0.5 ) {
		return new WP_Error( 'jpl_min', 'Najmenšia suma pre platbu kartou je 0,50 €.' );
	}
	$names = array();
	foreach ( $order->get_items() as $it ) {
		$names[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
	}
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$success = $order->get_checkout_order_received_url();
	$success .= ( false === strpos( $success, '?' ) ? '?' : '&' ) . 'jpl_session={CHECKOUT_SESSION_ID}';
	$params  = array(
		'mode'                 => 'payment',
		'payment_method_types' => apply_filters( 'jpl_payment_method_types', array( 'card' ) ),
		'line_items'           => array(
			array(
				'quantity'   => 1,
				'price_data' => array(
					'currency'     => strtolower( $order->get_currency() ),
					'unit_amount'  => jpl_cents( $total ),
					'product_data' => array(
						'name'        => 'Objednávka č. ' . $order->get_order_number(),
						'description' => mb_substr( implode( ', ', $names ), 0, 480 ),
					),
				),
			),
		),
		'customer_email'       => $order->get_billing_email() ? $order->get_billing_email() : null,
		'client_reference_id'  => (string) $order->get_id(),
		'locale'               => 'sk',
		'metadata'             => array( 'order_id' => $order->get_id(), 'order_number' => $order->get_order_number(), 'site' => home_url() ),
		'payment_intent_data'  => array(
			'description' => mb_substr( 'Objednávka č. ' . $order->get_order_number() . ' – ' . $site, 0, 300 ),
			'metadata'    => array( 'order_id' => $order->get_id(), 'order_number' => $order->get_order_number() ),
		),
		'success_url'          => $success,
		'cancel_url'           => $order->get_checkout_payment_url(),
	);
	$params = apply_filters( 'jpl_session_params', $params, $order );
	$s      = jpl_stripe( 'POST', 'checkout/sessions', $params, 'jpl-' . $order->get_id() . '-' . md5( jpl_build_query( $params ) . wp_date( 'YmdH' ) ) );
	if ( is_wp_error( $s ) ) {
		return $s;
	}
	$order->update_meta_data( '_jpl_session', $s['id'] );
	$order->update_meta_data( '_jpl_mode', jpl_mode() );
	if ( ! $order->has_status( 'pending' ) ) {
		$order->set_status( 'pending' );
	}
	$order->add_order_note( 'Stripe: zákazník presmerovaný na platobnú stránku' . ( jpl_is_test() ? ' (TESTOVACÍ REŽIM)' : '' ) . '.' );
	$order->save();
	return $s['url'];
}

/**
 * Overí Checkout Session (návrat zákazníka / webhook) a ak je zaplatená, označí objednávku ako zaplatenú.
 * $session môže byť ID alebo objekt zo Stripe.
 */
function jpl_check_session( WC_Order $order, $session ) {
	$mode = (string) $order->get_meta( '_jpl_mode' );
	if ( is_string( $session ) ) {
		$session = jpl_stripe( 'GET', 'checkout/sessions/' . rawurlencode( $session ), array(), '', $mode );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
	}
	if ( (string) ( $session['metadata']['order_id'] ?? $session['client_reference_id'] ?? '' ) !== (string) $order->get_id() ) {
		return new WP_Error( 'jpl_mismatch', 'Platba nepatrí k tejto objednávke.' );
	}
	if ( 'paid' === ( $session['payment_status'] ?? '' ) ) {
		return jpl_mark_paid( $order, $session );
	}
	return false;
}

/** Objednávku označí ako zaplatenú (raz), uloží platbu, kartu a poplatok Stripe. */
function jpl_mark_paid( WC_Order $order, array $session ) {
	$pi = is_array( $session['payment_intent'] ?? null ) ? $session['payment_intent']['id'] : (string) ( $session['payment_intent'] ?? '' );
	if ( ! $pi ) {
		return new WP_Error( 'jpl_nopi', 'Platba Stripe nemá ID.' );
	}
	$lock = 'jpl_lock_' . $order->get_id();
	if ( get_transient( $lock ) ) {
		return true; // práve spracúva iná požiadavka (webhook a návrat zákazníka naraz)
	}
	set_transient( $lock, 1, 30 );
	$order = wc_get_order( $order->get_id() ); // čerstvé údaje
	if ( $order->get_meta( '_jpl_pi' ) && $order->is_paid() ) {
		delete_transient( $lock );
		return true;
	}
	$order->update_meta_data( '_jpl_pi', $pi );
	$order->update_meta_data( '_jpl_session', $session['id'] );
	$amount = isset( $session['amount_total'] ) ? (int) $session['amount_total'] / 100 : (float) $order->get_total();
	if ( abs( $amount - (float) $order->get_total() ) >= 0.01 ) {
		$order->add_order_note( sprintf( 'Stripe: POZOR – zaplatená suma %s sa líši od sumy objednávky %s. Skontrolujte objednávku.', wc_price( $amount ), wc_price( $order->get_total() ) ) );
	}
	jpl_fill_charge( $order );
	$order->set_transaction_id( $pi );
	$order->save();
	$card = (string) $order->get_meta( '_jpl_card' );
	$order->add_order_note( 'Stripe: platba prijatá ' . wc_price( $amount, array( 'currency' => $order->get_currency() ) ) . ( $card ? ' – ' . $card : '' ) . ( '' !== (string) $order->get_meta( '_jpl_fee' ) ? ', poplatok ' . wc_price( (float) $order->get_meta( '_jpl_fee' ) ) : '' ) . ' (' . $pi . ').' );
	$order->payment_complete( $pi );
	delete_transient( $lock );
	do_action( 'jpl_paid', $order->get_id(), $pi );
	return true;
}

/** Doplní údaje o platbe: karta, poplatok Stripe a čistá suma (z balance transaction). */
function jpl_fill_charge( WC_Order $order ) {
	$pi = (string) $order->get_meta( '_jpl_pi' );
	if ( ! $pi ) {
		return false;
	}
	$p = jpl_stripe( 'GET', 'payment_intents/' . rawurlencode( $pi ), array( 'expand' => array( 'latest_charge.balance_transaction' ) ), '', (string) $order->get_meta( '_jpl_mode' ) );
	if ( is_wp_error( $p ) || ! is_array( $p['latest_charge'] ?? null ) ) {
		return false;
	}
	$ch = $p['latest_charge'];
	$order->update_meta_data( '_jpl_charge', $ch['id'] );
	$c = $ch['payment_method_details']['card'] ?? null;
	if ( $c ) {
		$wallet = $c['wallet']['type'] ?? '';
		$order->update_meta_data( '_jpl_card', trim( ucfirst( (string) $c['brand'] ) . ' •••• ' . $c['last4'] . ( $wallet ? ' (' . ucwords( str_replace( '_', ' ', $wallet ) ) . ')' : '' ) ) );
	}
	$bt = $ch['balance_transaction'] ?? null;
	if ( is_array( $bt ) ) {
		$order->update_meta_data( '_jpl_fee', round( (int) $bt['fee'] / 100, 2 ) );
		$order->update_meta_data( '_jpl_net', round( (int) $bt['net'] / 100, 2 ) );
		$order->update_meta_data( '_jpl_available_on', gmdate( 'Y-m-d', (int) $bt['available_on'] ) );
		return true;
	}
	return false;
}

// Návrat zákazníka zo Stripe: overí platbu hneď (ak webhook ešte neprišiel).
add_action( 'template_redirect', function () {
	if ( empty( $_GET['jpl_session'] ) || ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) { // phpcs:ignore
		return;
	}
	global $wp;
	$order = wc_get_order( absint( $wp->query_vars['order-received'] ?? 0 ) );
	$key   = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ); // phpcs:ignore
	$sid   = sanitize_text_field( wp_unslash( $_GET['jpl_session'] ) ); // phpcs:ignore
	if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || 'jpl_stripe' !== $order->get_payment_method() || $order->is_paid() ) {
		return;
	}
	if ( $sid !== (string) $order->get_meta( '_jpl_session' ) ) {
		return;
	}
	jpl_check_session( $order, $sid );
} );

// Ďakujeme: ak platba ešte nie je potvrdená (napr. pomalší spôsob), informuj zákazníka.
add_action( 'woocommerce_thankyou_jpl_stripe', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( $order && ! $order->is_paid() && $order->has_status( 'pending' ) ) {
		echo '<p class="woocommerce-info">Platbu kartou ešte overujeme. Potvrdenie vám príde e-mailom. Ak ste platbu nedokončili, môžete <a href="' . esc_url( $order->get_checkout_payment_url() ) . '">zaplatiť znova</a>.</p>';
	}
} );
