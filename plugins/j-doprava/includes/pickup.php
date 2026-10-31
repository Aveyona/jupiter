<?php
/**
 * Osobný odber: e-mail zákazníkovi „Objednávka je pripravená na odber“.
 * Odošle sa z detailu objednávky (Akcie objednávky) alebo hromadne zo zoznamu objednávok – len pri osobnom odbere.
 * Text (adresu a čas odberu) upravíte vo WooCommerce → Nastavenia → E-maily → Pripravené na osobný odber.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Je doprava objednávky osobný odber? */
function jdp_is_pickup( $order ) {
	foreach ( $order->get_shipping_methods() as $m ) {
		if ( in_array( $m->get_method_id(), array( 'local_pickup', 'pickup_location' ), true ) ) {
			return true;
		}
	}
	return false;
}

add_filter( 'woocommerce_email_classes', function ( $emails ) {
	if ( ! class_exists( 'JDP_Email_Pickup' ) ) {

		class JDP_Email_Pickup extends WC_Email {
			public function __construct() {
				$this->id             = 'jdp_pickup';
				$this->customer_email = true;
				$this->title          = 'Pripravené na osobný odber';
				$this->description    = 'Zákazník s osobným odberom dostane e-mail, že si objednávku môže prevziať (J-Doprava). Odosiela sa z detailu objednávky alebo hromadne zo zoznamu objednávok.';
				$this->template_html  = 'emails/jdp-pickup-ready.php';
				$this->template_plain = 'emails/plain/jdp-pickup-ready.php';
				$this->template_base  = JDP_DIR . 'templates/';
				$this->placeholders   = array( '{order_date}' => '', '{order_number}' => '' );
				parent::__construct();
			}

			public function get_default_subject() {
				return 'Vaša objednávka č. {order_number} je pripravená na odber';
			}

			public function get_default_heading() {
				return 'Objednávka je pripravená na odber';
			}

			public function get_default_additional_content() {
				return 'Tešíme sa na vás.';
			}

			public function trigger( $order_id, $order = false ) {
				$this->setup_locale();
				if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
					$order = wc_get_order( $order_id );
				}
				if ( is_a( $order, 'WC_Order' ) ) {
					$this->object                         = $order;
					$this->recipient                      = $order->get_billing_email();
					$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
					$this->placeholders['{order_number}'] = $order->get_order_number();
				}
				if ( $this->is_enabled() && $this->get_recipient() ) {
					$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
				}
				$this->restore_locale();
			}

			public function get_content_html() {
				return wc_get_template_html( $this->template_html, $this->args(), '', $this->template_base );
			}

			public function get_content_plain() {
				return wc_get_template_html( $this->template_plain, $this->args(), '', $this->template_base );
			}

			private function args() {
				return array( 'order' => $this->object, 'email_heading' => $this->get_heading(), 'additional_content' => $this->get_additional_content(), 'sent_to_admin' => false, 'plain_text' => false, 'email' => $this );
			}
		}
	}
	$emails['JDP_Email_Pickup'] = new JDP_Email_Pickup();
	return $emails;
}, 20 );

/** Odoslanie e-mailu „pripravené na odber“. Vráti true alebo WP_Error. */
function jdp_send_pickup( $order ) {
	if ( ! jdp_is_pickup( $order ) ) {
		return new WP_Error( 'jdp', 'Objednávka nemá osobný odber.' );
	}
	$mails = WC()->mailer()->get_emails();
	if ( empty( $mails['JDP_Email_Pickup'] ) || ! $mails['JDP_Email_Pickup']->is_enabled() ) {
		return new WP_Error( 'jdp', 'E-mail Pripravené na osobný odber je vypnutý (WooCommerce → Nastavenia → E-maily).' );
	}
	$mails['JDP_Email_Pickup']->trigger( $order->get_id(), $order );
	$order->update_meta_data( '_jdp_pickup_mailed', current_time( 'mysql' ) );
	$order->add_order_note( 'Zákazníkovi odoslaný e-mail „Objednávka je pripravená na odber“.' );
	$order->save();
	return true;
}

// Akcia v detaile objednávky.
add_filter( 'woocommerce_order_actions', function ( $actions, $order = null ) {
	if ( $order instanceof WC_Order && jdp_is_pickup( $order ) ) {
		$actions['jdp_pickup_ready'] = 'Poslať e-mail: pripravené na osobný odber';
	}
	return $actions;
}, 10, 2 );
add_action( 'woocommerce_order_action_jdp_pickup_ready', function ( $order ) {
	jdp_send_pickup( $order );
} );

// Hromadná akcia v zozname objednávok.
foreach ( array( 'bulk_actions-woocommerce_page_wc-orders', 'bulk_actions-edit-shop_order' ) as $jdp_f ) {
	add_filter( $jdp_f, function ( $a ) {
		$a['jdp_pickup'] = 'Osobný odber – poslať e-mail „pripravené na odber“';
		return $a;
	} );
}
foreach ( array( 'handle_bulk_actions-woocommerce_page_wc-orders', 'handle_bulk_actions-edit-shop_order' ) as $jdp_f ) {
	add_filter( $jdp_f, function ( $redirect, $action, $ids ) {
		if ( 'jdp_pickup' !== $action ) {
			return $redirect;
		}
		$ok  = 0;
		$err = array();
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			$o = wc_get_order( $id );
			if ( ! $o ) {
				continue;
			}
			$r = jdp_send_pickup( $o );
			is_wp_error( $r ) ? $err[] = '#' . $o->get_order_number() . ': ' . $r->get_error_message() : $ok++;
		}
		set_transient( 'jdp_msg_' . get_current_user_id(), 'E-mail „pripravené na odber“ odoslaný: ' . $ok . ( $err ? '. Chyby: ' . implode( ' · ', $err ) : '.' ), 120 );
		return add_query_arg( 'jdp_msg', 1, $redirect );
	}, 10, 3 );
}
