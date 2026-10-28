<?php
/**
 * Nákup na firmu v blokovej pokladni (WooCommerce Checkout block): „Nakupujem na firmu“ + názov firmy, IČO, DIČ, IČ DPH.
 * Polia sa zobrazia až po zaškrtnutí (pravidlá polí WooCommerce) a po objednávke sa skopírujú do fakturačných polí
 * objednávky (_billing_company, _billing_ico, _billing_dic, _billing_icdph) – rovnako ako v klasickej pokladni.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pravidlo „pole je viditeľné/povinné len pri zaškrtnutom Nakupujem na firmu“ (JSON schéma polí WooCommerce). */
function jinv_blocks_company_rule( $when_checked ) {
	return array(
		'type'       => 'object',
		'properties' => array(
			'customer' => array(
				'properties' => array(
					'additional_fields' => array(
						'properties' => array(
							'jupiter/company' => array( 'const' => $when_checked ),
						),
					),
				),
			),
		),
	);
}

add_action( 'woocommerce_init', function () {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! jinv_company_fields_on() ) {
		return;
	}
	$required = (bool) jinv_opt( 'company_required' );
	$fields   = array(
		array(
			'id'       => 'jupiter/company',
			'label'    => 'Nakupujem na firmu',
			'location' => 'contact',
			'type'     => 'checkbox',
		),
		array(
			'id'       => 'jupiter/company_name',
			'label'    => 'Názov firmy',
			'location' => 'contact',
			'type'     => 'text',
			'hidden'   => jinv_blocks_company_rule( false ),
			'required' => $required ? jinv_blocks_company_rule( true ) : false,
		),
		array(
			'id'                => 'jupiter/ico',
			'label'             => 'IČO',
			'location'          => 'contact',
			'type'              => 'text',
			'hidden'            => jinv_blocks_company_rule( false ),
			'required'          => $required ? jinv_blocks_company_rule( true ) : false,
			'attributes'        => array( 'maxLength' => 12, 'inputmode' => 'numeric' ),
			'sanitize_callback' => function ( $v ) {
				return preg_replace( '/\s+/', '', (string) $v );
			},
			'validate_callback' => function ( $v ) {
				if ( '' !== $v && ! preg_match( '/^\d{6,8}$/', $v ) ) {
					return new WP_Error( 'jinv_ico', 'IČO má mať 6 až 8 číslic.' );
				}
			},
		),
		array(
			'id'                => 'jupiter/dic',
			'label'             => 'DIČ (nepovinné)',
			'location'          => 'contact',
			'type'              => 'text',
			'hidden'            => jinv_blocks_company_rule( false ),
			'attributes'        => array( 'maxLength' => 14 ),
			'sanitize_callback' => function ( $v ) {
				return preg_replace( '/\s+/', '', (string) $v );
			},
		),
		array(
			'id'                => 'jupiter/icdph',
			'label'             => 'IČ DPH (nepovinné)',
			'location'          => 'contact',
			'type'              => 'text',
			'hidden'            => jinv_blocks_company_rule( false ),
			'attributes'        => array( 'maxLength' => 16 ),
			'sanitize_callback' => function ( $v ) {
				return strtoupper( preg_replace( '/\s+/', '', (string) $v ) );
			},
			'validate_callback' => function ( $v ) {
				if ( '' !== $v && ! preg_match( '/^[A-Z]{2}[0-9A-Z]{6,13}$/', $v ) ) {
					return new WP_Error( 'jinv_icdph', 'IČ DPH má začínať kódom krajiny, napr. SK2120635451.' );
				}
			},
		),
	);
	foreach ( $fields as $f ) {
		try {
			woocommerce_register_additional_checkout_field( $f );
		} catch ( \Throwable $e ) {
			// Staršia verzia WooCommerce bez podpory pravidiel – zaregistrovať bez nich.
			unset( $f['hidden'] );
			if ( is_array( $f['required'] ?? null ) ) {
				$f['required'] = false;
			}
			try {
				woocommerce_register_additional_checkout_field( $f );
			} catch ( \Throwable $e2 ) {
				continue;
			}
		}
	}
} );

/** Hodnota poľa pokladne z objednávky (kontaktné polia sa ukladajú ako _wc_other/…). */
function jinv_blocks_order_field( WC_Order $order, $id ) {
	foreach ( array( '_wc_other/' . $id, '_wc_billing/' . $id ) as $k ) {
		$v = $order->get_meta( $k );
		if ( '' !== (string) $v ) {
			return $v;
		}
	}
	return '';
}

/** Po objednávke z blokovej pokladne: firemné údaje do fakturačných polí objednávky (faktúra, e-maily, administrácia). */
add_action( 'woocommerce_store_api_checkout_order_processed', function ( $order ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	$is_company = jinv_blocks_order_field( $order, 'jupiter/company' );
	if ( ! $is_company || 'false' === $is_company || '0' === (string) $is_company ) {
		return;
	}
	$name = trim( (string) jinv_blocks_order_field( $order, 'jupiter/company_name' ) );
	if ( '' !== $name ) {
		$order->set_billing_company( $name );
	}
	foreach ( array( 'ico', 'dic', 'icdph' ) as $k ) {
		$v = strtoupper( preg_replace( '/\s+/', '', (string) jinv_blocks_order_field( $order, 'jupiter/' . $k ) ) );
		if ( '' !== $v ) {
			$order->update_meta_data( '_billing_' . $k, $v );
		}
	}
	$order->save();
} );
