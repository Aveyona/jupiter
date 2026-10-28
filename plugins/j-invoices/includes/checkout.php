<?php
/**
 * Nákup na firmu: „Nakupujem na firmu“ + IČO, DIČ, IČ DPH v pokladni, v Môj účet → Adresy a v detaile objednávky.
 * Hodnoty sa ukladajú ako fakturačné polia WooCommerce (_billing_ico, _billing_dic, _billing_icdph) – funguje aj s HPOS.
 * Ak je aktívna Woo Faktura, polia sa nepridávajú (má vlastné) – faktúra ich načíta aj z jej polí (create.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pridávať polia? (zapnuté v nastaveniach a nebeží Woo Faktura) */
function jinv_company_fields_on() {
	if ( ! jinv_opt( 'company_fields' ) ) {
		return false;
	}
	return ! jinv_woo_faktura_active();
}

/** Popisy polí. */
function jinv_company_field_defs() {
	return array(
		'ico'   => array( 'IČO', 8 ),
		'dic'   => array( 'DIČ', 10 ),
		'icdph' => array( 'IČ DPH', 12 ),
	);
}

/** Je v aktuálnom odoslaní formulára zaškrtnuté „Nakupujem na firmu“? */
function jinv_posted_as_company() {
	// phpcs:ignore WordPress.Security.NonceVerification
	return ! empty( $_POST['billing_jinv_company'] );
}

add_filter( 'woocommerce_billing_fields', function ( $fields ) {
	if ( ! jinv_company_fields_on() ) {
		return $fields;
	}
	$out      = array();
	$prio     = isset( $fields['billing_company']['priority'] ) ? (int) $fields['billing_company']['priority'] : 30;
	$required = jinv_opt( 'company_required' ) && jinv_posted_as_company();
	foreach ( $fields as $k => $f ) {
		if ( 'billing_company' === $k ) {
			$out['billing_jinv_company'] = array(
				'type'     => 'checkbox',
				'label'    => 'Nakupujem na firmu',
				'class'    => array( 'form-row-wide', 'jinv-company-toggle' ),
				'priority' => $prio - 1,
				'required' => false,
			);
			$f['class']    = array_merge( (array) ( isset( $f['class'] ) ? $f['class'] : array() ), array( 'jinv-company-field' ) );
			$f['required'] = $required; // názov firmy povinný len pri nákupe na firmu (ak je to zapnuté)
			$out[ $k ]     = $f;
			$i             = 1;
			foreach ( jinv_company_field_defs() as $key => $def ) {
				$out[ 'billing_' . $key ] = array(
					'type'      => 'text',
					'label'     => $def[0],
					'class'     => array( 'form-row-wide', 'jinv-company-field' ),
					'priority'  => $prio + $i,
					'required'  => ( 'ico' === $key ) && $required,
					'maxlength' => $def[1] + 4,
				);
				$i++;
			}
			continue;
		}
		$out[ $k ] = $f;
	}
	if ( ! isset( $out['billing_jinv_company'] ) ) { // bez poľa „Firma“ – pridať na koniec
		$out['billing_jinv_company'] = array( 'type' => 'checkbox', 'label' => 'Nakupujem na firmu', 'class' => array( 'form-row-wide', 'jinv-company-toggle' ), 'priority' => 200 );
		foreach ( jinv_company_field_defs() as $key => $def ) {
			$out[ 'billing_' . $key ] = array( 'type' => 'text', 'label' => $def[0], 'class' => array( 'form-row-wide', 'jinv-company-field' ), 'priority' => 201, 'required' => false );
		}
	}
	return $out;
}, 20 );

/** Úprava hodnôt pri uložení: veľké písmená, bez medzier; bez zaškrtnutia sa firemné údaje neukladajú. */
add_filter( 'woocommerce_checkout_posted_data', function ( $data ) {
	if ( ! jinv_company_fields_on() ) {
		return $data;
	}
	if ( empty( $data['billing_jinv_company'] ) ) {
		foreach ( array( 'billing_company', 'billing_ico', 'billing_dic', 'billing_icdph' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$data[ $k ] = '';
			}
		}
		return $data;
	}
	foreach ( array_keys( jinv_company_field_defs() ) as $key ) {
		if ( isset( $data[ 'billing_' . $key ] ) ) {
			$data[ 'billing_' . $key ] = strtoupper( preg_replace( '/\s+/', '', (string) $data[ 'billing_' . $key ] ) );
		}
	}
	return $data;
} );

/** Kontrola tvaru IČO (8 číslic) a IČ DPH (2 písmená + číslice), len ak sú vyplnené. */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( ! jinv_company_fields_on() || empty( $data['billing_jinv_company'] ) ) {
		return;
	}
	if ( ! empty( $data['billing_ico'] ) && ! preg_match( '/^\d{6,8}$/', $data['billing_ico'] ) ) {
		$errors->add( 'jinv_ico', '<strong>IČO</strong> má mať 6 až 8 číslic.' );
	}
	if ( ! empty( $data['billing_icdph'] ) && ! preg_match( '/^[A-Z]{2}[0-9A-Z]{6,13}$/', $data['billing_icdph'] ) ) {
		$errors->add( 'jinv_icdph', '<strong>IČ DPH</strong> má začínať kódom krajiny, napr. SK2120635451.' );
	}
}, 10, 2 );

/** Zobrazenie/skrytie firemných polí podľa zaškrtnutia (pokladňa aj Môj účet). */
add_action( 'wp_footer', function () {
	if ( ! jinv_company_fields_on() || ! ( is_checkout() || is_account_page() ) ) {
		return;
	}
	?>
	<script id="jinv-company-toggle">
	(function(){
		function sync(){
			var cb=document.getElementById('billing_jinv_company');
			if(!cb){return;}
			var on=cb.checked;
			document.querySelectorAll('.jinv-company-field').forEach(function(el){el.style.display=on?'':'none';});
		}
		document.addEventListener('change',function(e){if(e.target&&e.target.id==='billing_jinv_company'){sync();}});
		document.addEventListener('DOMContentLoaded',function(){
			var cb=document.getElementById('billing_jinv_company'),has=false;
			['billing_company','billing_ico','billing_dic','billing_icdph'].forEach(function(id){var i=document.getElementById(id);if(i&&i.value){has=true;}});
			if(cb&&has){cb.checked=true;}
			sync();
		});
		if(window.jQuery){jQuery(document.body).on('updated_checkout',sync);}
	})();
	</script>
	<?php
}, 30 );

/** Detail objednávky v administrácii – polia v časti Fakturácia (ukladajú sa ako _billing_ico atď.). */
add_filter( 'woocommerce_admin_billing_fields', function ( $fields ) {
	$out = array();
	foreach ( $fields as $k => $f ) {
		$out[ $k ] = $f;
		if ( 'company' === $k ) {
			foreach ( jinv_company_field_defs() as $key => $def ) {
				$out[ $key ] = array( 'label' => $def[0], 'show' => true );
			}
		}
	}
	foreach ( jinv_company_field_defs() as $key => $def ) {
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array( 'label' => $def[0], 'show' => true );
		}
	}
	return $out;
} );

/** Profil zákazníka v administrácii (Používatelia → Upraviť). */
add_filter( 'woocommerce_customer_meta_fields', function ( $fields ) {
	if ( isset( $fields['billing']['fields'] ) ) {
		foreach ( jinv_company_field_defs() as $key => $def ) {
			$fields['billing']['fields'][ 'billing_' . $key ] = array( 'label' => $def[0], 'description' => '' );
		}
	}
	return $fields;
} );

/** Firemné údaje v adrese (e-maily, Môj účet, objednávka). */
add_filter( 'woocommerce_order_formatted_billing_address', function ( $address, $order ) {
	if ( ! $order instanceof WC_Order || jinv_woo_faktura_active() ) {
		return $address; // kým beží Woo Faktura, IČO/DIČ v adrese dopĺňa ona
	}
	$ico = jinv_order_meta_any( $order, array( '_billing_ico', 'billing_ico', '_billing_company_wi_id', 'billing_company_wi_id' ) );
	$dic = jinv_order_meta_any( $order, array( '_billing_dic', 'billing_dic', '_billing_company_wi_tax', 'billing_company_wi_tax' ) );
	$vat = jinv_order_meta_any( $order, array( '_billing_icdph', 'billing_icdph', '_billing_company_wi_vat', 'billing_company_wi_vat' ) );
	$address['jinv_ids'] = trim( implode( ', ', array_filter( array( $ico ? 'IČO: ' . $ico : '', $dic ? 'DIČ: ' . $dic : '', $vat ? 'IČ DPH: ' . $vat : '' ) ) ) );
	return $address;
}, 10, 2 );

add_filter( 'woocommerce_formatted_address_replacements', function ( $repl, $args ) {
	$repl['{jinv_ids}'] = isset( $args['jinv_ids'] ) ? $args['jinv_ids'] : '';
	return $repl;
}, 10, 2 );

add_filter( 'woocommerce_localisation_address_formats', function ( $formats ) {
	foreach ( $formats as $c => $f ) {
		if ( false === strpos( $f, '{jinv_ids}' ) ) {
			$formats[ $c ] = $f . "\n{jinv_ids}";
		}
	}
	return $formats;
} );

/** Predvyplnenie IČO / DIČ / IČ DPH v pokladni zo starších polí Woo Faktury (zákazník ich vyplnil ešte pred prechodom). */
add_filter( 'woocommerce_checkout_get_value', function ( $value, $input ) {
	$map = array( 'billing_ico' => 'billing_company_wi_id', 'billing_dic' => 'billing_company_wi_tax', 'billing_icdph' => 'billing_company_wi_vat' );
	if ( ( null !== $value && '' !== $value ) || ! isset( $map[ $input ] ) || ! is_user_logged_in() || ! jinv_company_fields_on() ) {
		return $value;
	}
	$old = trim( (string) get_user_meta( get_current_user_id(), $map[ $input ], true ) );
	return '' !== $old ? $old : $value;
}, 10, 2 );
