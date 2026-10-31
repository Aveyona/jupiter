<?php
/**
 * Spôsoby dopravy WooCommerce: Packeta – výdajné miesto / Z-BOX (jdp_packeta_pp) a Packeta – doručenie na adresu (jdp_packeta_hd).
 * Nastavenie v zóne dopravy: názov, cena, doprava zadarmo od sumy, maximálna hmotnosť.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Hmotnosť košíka / objednávky v kg (+ obal, najmenej minimálna hmotnosť). */
function jdp_weight_kg( $raw ) {
	$kg = (float) wc_get_weight( (float) $raw, 'kg' ) + (float) jdp_opt( 'weight_add' );
	return round( max( (float) jdp_opt( 'weight_min' ), $kg ), 3 );
}

add_action( 'woocommerce_shipping_init', function () {

	abstract class JDP_Packeta_Method extends WC_Shipping_Method {
		/** Predvolená maximálna hmotnosť (kg). */
		protected $max_kg = 10;

		public function __construct( $instance_id = 0 ) {
			$this->instance_id = absint( $instance_id );
			$this->supports    = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->get_option( 'title', $this->method_title );
			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->instance_form_fields = array(
				'title'     => array( 'title' => 'Názov v košíku', 'type' => 'text', 'default' => $this->method_title ),
				'cost'      => array( 'title' => 'Cena (€)', 'type' => 'price', 'default' => $this->default_cost(), 'placeholder' => '0,00' ),
				'free_from' => array( 'title' => 'Zadarmo od sumy objednávky (€)', 'type' => 'price', 'default' => '', 'placeholder' => 'nikdy', 'description' => 'Suma tovaru v košíku (po zľavách) – prázdne = doprava sa vždy platí.', 'desc_tip' => true ),
				'max_kg'    => array( 'title' => 'Maximálna hmotnosť (kg)', 'type' => 'number', 'default' => $this->max_kg, 'custom_attributes' => array( 'step' => '0.1', 'min' => '0' ) ),
			);
		}

		protected function default_cost() {
			return '';
		}

		public function is_available( $package ) {
			if ( ! jdp_ready() ) {
				return false;
			}
			$max = (float) $this->get_option( 'max_kg', $this->max_kg );
			if ( $max > 0 && WC()->cart && jdp_weight_kg( WC()->cart->get_cart_contents_weight() ) > $max ) {
				return false;
			}
			return parent::is_available( $package );
		}

		public function calculate_shipping( $package = array() ) {
			$cost = (float) wc_format_decimal( $this->get_option( 'cost', 0 ) );
			$free = $this->get_option( 'free_from', '' );
			if ( '' !== $free && null !== $free ) {
				$sum = 0.0;
				foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
					$sum += (float) ( $item['line_total'] ?? 0 ) + (float) ( $item['line_tax'] ?? 0 );
				}
				if ( $sum >= (float) wc_format_decimal( $free ) ) {
					$cost = 0.0;
				}
			}
			$this->add_rate( array( 'id' => $this->get_rate_id(), 'label' => $this->title, 'cost' => $cost, 'package' => $package ) );
		}
	}

	class JDP_Packeta_PP extends JDP_Packeta_Method {
		protected $max_kg = 10;

		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'jdp_packeta_pp';
			$this->method_title       = 'Packeta – výdajné miesto / Z-BOX';
			$this->method_description = 'Zákazník si v košíku vyberie výdajné miesto alebo Z-BOX Packety na mape (J-Doprava).';
			parent::__construct( $instance_id );
		}

		protected function default_cost() {
			return '2.90';
		}
	}

	class JDP_Packeta_HD extends JDP_Packeta_Method {
		protected $max_kg = 5;

		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'jdp_packeta_hd';
			$this->method_title       = 'Packeta – doručenie na adresu';
			$this->method_description = 'Kuriér Packeta doručí zásielku na adresu doručenia zákazníka (J-Doprava). Vyžaduje telefón.';
			parent::__construct( $instance_id );
		}

		protected function default_cost() {
			return '4.50';
		}
	}
} );

add_filter( 'woocommerce_shipping_methods', function ( $m ) {
	$m['jdp_packeta_pp'] = 'JDP_Packeta_PP';
	$m['jdp_packeta_hd'] = 'JDP_Packeta_HD';
	return $m;
} );

/** Druh dopravy Packeta v objednávke: 'pp', 'hd' alebo ''. */
function jdp_order_kind( $order ) {
	foreach ( $order->get_shipping_methods() as $m ) {
		if ( 'jdp_packeta_pp' === $m->get_method_id() ) {
			return 'pp';
		}
		if ( 'jdp_packeta_hd' === $m->get_method_id() ) {
			return 'hd';
		}
	}
	return '';
}

/** Je zvolená doprava Packeta – výdajné miesto (podľa vybraných metód v košíku)? */
function jdp_cart_needs_point() {
	$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
	foreach ( $chosen as $c ) {
		if ( 0 === strpos( (string) $c, 'jdp_packeta_pp' ) ) {
			return true;
		}
	}
	return false;
}
