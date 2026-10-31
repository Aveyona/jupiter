<?php
/** Platba kartou v blokovej pokladni WooCommerce. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class JPL_Blocks_Stripe extends AbstractPaymentMethodType {
	protected $name = 'jpl_stripe';

	public function initialize() {
		$this->settings = (array) get_option( 'woocommerce_jpl_stripe_settings', array() );
	}

	public function is_active() {
		return 'no' !== ( $this->settings['enabled'] ?? 'yes' ) && jpl_ready();
	}

	public function get_payment_method_script_handles() {
		wp_register_script( 'jpl-blocks', JPL_URL . 'assets/blocks.js', array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ), JPL_VERSION, true );
		return array( 'jpl-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => jpl_gw_opt( 'title', 'Platba kartou (Visa, Mastercard, Apple Pay, Google Pay)' ),
			'description' => jpl_gw_opt( 'description', 'Po potvrdení objednávky vás presmerujeme na zabezpečenú platobnú stránku Stripe. Údaje o karte nevidíme ani neukladáme.' ),
			'button'      => jpl_gw_opt( 'button', 'Zaplatiť kartou' ),
			'test'        => jpl_is_test(),
			'supports'    => array( 'products', 'refunds' ),
		);
	}
}
