<?php
/**
 * E-mail zákazníkovi „Objednávka odoslaná“: keď Packeta prevezme zásielku (prvý stav na ceste),
 * zákazník dostane e-mail s odkazom na sledovanie, výdajným miestom / adresou a obsahom objednávky. Odošle sa raz.
 * Nastavenie: WooCommerce → Nastavenia → E-maily → Objednávka odoslaná (Packeta).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stavy Packety, ktoré znamenajú „zásielka je na ceste“ (odovzdaná Packete). */
function jdp_shipped_codes() {
	return apply_filters( 'jdp_shipped_codes', array( 2, 3, 4, 6, 12, 31 ) );
}

add_filter( 'woocommerce_email_classes', function ( $emails ) {
	if ( ! class_exists( 'JDP_Email_Shipped' ) ) {

		class JDP_Email_Shipped extends WC_Email {
			public function __construct() {
				$this->id             = 'jdp_shipped';
				$this->customer_email = true;
				$this->title          = 'Objednávka odoslaná (Packeta)';
				$this->description    = 'Zákazník dostane e-mail s odkazom na sledovanie, keď Packeta prevezme zásielku (J-Doprava).';
				$this->template_html  = 'emails/jdp-shipped-order.php';
				$this->template_plain = 'emails/plain/jdp-shipped-order.php';
				$this->template_base  = JDP_DIR . 'templates/';
				$this->placeholders   = array( '{order_date}' => '', '{order_number}' => '' );
				parent::__construct();
			}

			public function get_default_subject() {
				return 'Vaša objednávka č. {order_number} je na ceste';
			}

			public function get_default_heading() {
				return 'Vaša objednávka je na ceste';
			}

			public function get_default_additional_content() {
				return 'Ďakujeme za nákup.';
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
				return wc_get_template_html( $this->template_html, $this->template_args(), '', $this->template_base );
			}

			public function get_content_plain() {
				return wc_get_template_html( $this->template_plain, $this->template_args(), '', $this->template_base );
			}

			private function template_args() {
				return array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				);
			}
		}
	}
	$emails['JDP_Email_Shipped'] = new JDP_Email_Shipped();
	return $emails;
} );

// Odoslanie pri prvom stave „na ceste“.
add_action( 'jdp_status_changed', function ( $order, $code, $old ) {
	if ( ! in_array( (int) $code, jdp_shipped_codes(), true ) || $order->get_meta( '_jdp_shipped_mailed' ) ) {
		return;
	}
	$order->update_meta_data( '_jdp_shipped_mailed', current_time( 'mysql' ) );
	$order->save();
	$mails = WC()->mailer()->get_emails();
	if ( isset( $mails['JDP_Email_Shipped'] ) ) {
		$mails['JDP_Email_Shipped']->trigger( $order->get_id(), $order );
		$order->add_order_note( 'Packeta: zákazníkovi odoslaný e-mail „Objednávka odoslaná“.' );
	}
}, 10, 3 );

/** Údaje pre e-mail: sledovanie a miesto doručenia. */
function jdp_email_ship_info( WC_Order $order ) {
	$bar  = (string) $order->get_meta( '_jdp_barcode' );
	$kind = (string) $order->get_meta( '_jdp_kind' );
	if ( 'pp' === $kind ) {
		$where_label = 'Výdajné miesto';
		$where       = (string) $order->get_meta( '_jdp_point_label' );
	} else {
		$where_label = 'Doručenie na adresu';
		$where       = trim( implode( ', ', array_filter( array( $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1(), trim( ( $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode() ) . ' ' . ( $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city() ) ) ) ) ) );
	}
	return array(
		'barcode'     => $bar,
		'url'         => $bar ? jdp_tracking_url( $bar ) : '',
		'where_label' => $where_label,
		'where'       => $where,
		'kind'        => $kind,
	);
}
