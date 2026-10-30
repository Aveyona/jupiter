<?php
/**
 * Košík a pokladňa: výber výdajného miesta Packeta na mape (widget v6) v blokovom aj klasickom košíku,
 * uloženie miesta do relácie (AJAX), kontrola pred odoslaním objednávky a uloženie do objednávky.
 * Miesto sa zobrazí v detaile objednávky, v e-mailoch a v Mojom účte.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Vybrané miesto v relácii. */
function jdp_session_point() {
	$p = WC()->session ? WC()->session->get( 'jdp_point' ) : null;
	return is_array( $p ) && ! empty( $p['id'] ) ? $p : null;
}

/** Text miesta (názov, ulica, mesto). */
function jdp_point_label( $p ) {
	if ( ! is_array( $p ) || empty( $p['id'] ) ) {
		return '';
	}
	$addr = trim( implode( ', ', array_filter( array( $p['street'] ?? '', trim( ( $p['zip'] ?? '' ) . ' ' . ( $p['city'] ?? '' ) ) ) ) ) );
	$name = trim( (string) ( $p['place'] ?? '' ) ) ? $p['place'] : ( $p['name'] ?? '' );
	return trim( ( 'zbox' === ( $p['group'] ?? '' ) ? 'Z-BOX ' : '' ) . $name . ( $addr && false === strpos( (string) $name, (string) ( $p['street'] ?? '###' ) ) ? ' – ' . $addr : '' ) );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || ! jdp_ready() ) {
		return;
	}
	wp_enqueue_script( 'packeta-widget', 'https://widget.packeta.com/v6/www/js/library.js', array(), null, true ); // phpcs:ignore
	wp_register_script( 'jdp-checkout', '', array( 'packeta-widget' ), JDP_VERSION, true );
	wp_enqueue_script( 'jdp-checkout' );
	$cfg = array(
		'apiKey'  => jdp_api_key(),
		'country' => (string) jdp_opt( 'country' ),
		'ajax'    => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'jdp_point' ),
		'weight'  => WC()->cart ? jdp_weight_kg( WC()->cart->get_cart_contents_weight() ) : 0,
		'point'   => jdp_session_point(),
		'label'   => jdp_point_label( jdp_session_point() ),
		'web'     => home_url(),
		'app'     => 'jupiter-j-doprava-' . JDP_VERSION,
	);
	wp_add_inline_script( 'jdp-checkout', 'window.jdpCfg=' . wp_json_encode( $cfg ) . ';' . jdp_checkout_js() );
	wp_register_style( 'jdp-checkout', false, array(), JDP_VERSION );
	wp_enqueue_style( 'jdp-checkout' );
	wp_add_inline_style( 'jdp-checkout', '.jdp-pick{margin:8px 0 4px;padding:10px 12px;border:1px dashed #ba1c24;border-radius:6px;background:#fff8f8;font-size:14px;line-height:1.4}.jdp-pick button{margin-right:8px;padding:6px 12px;border:0;border-radius:4px;background:#ba1c24;color:#fff;cursor:pointer;font-weight:600}.jdp-pick .jdp-sel{display:inline-block;margin-top:4px}.jdp-pick.jdp-ok{border-style:solid;border-color:#1e8e3e;background:#f3faf4}' );
} );

function jdp_checkout_js() {
	return <<<'JS'
(function () {
	var cfg = window.jdpCfg || {}, busy = false;
	function selected() {
		var r = document.querySelector('.wc-block-components-shipping-rates-control input[type=radio]:checked, .wp-block-woocommerce-checkout-shipping-methods-block input[type=radio]:checked, input.shipping_method:checked, input.shipping_method[type=hidden]');
		return r;
	}
	function anchorFor(r) {
		return r.closest('.wc-block-components-radio-control__option, .wc-block-components-radio-control-accordion-option, li') || r.parentNode;
	}
	function text() {
		return cfg.label ? 'Vybrané: <strong>' + cfg.label.replace(/</g, '&lt;') + '</strong>' : 'Vyberte výdajné miesto alebo Z-BOX.';
	}
	function render() {
		var r = selected(), box = document.getElementById('jdp-pick');
		if (!r || r.value.indexOf('jdp_packeta_pp') !== 0) { if (box) { box.remove(); } return; }
		var a = anchorFor(r);
		if (!box) {
			box = document.createElement('div');
			box.id = 'jdp-pick';
			box.className = 'jdp-pick';
			box.innerHTML = '<button type="button">Vybrať výdajné miesto</button><span class="jdp-sel"></span>';
			box.querySelector('button').addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); pick(); });
		}
		if (box.previousElementSibling !== a && a.nextElementSibling !== box) { a.insertAdjacentElement('afterend', box); }
		box.querySelector('.jdp-sel').innerHTML = text();
		box.classList.toggle('jdp-ok', !!cfg.label);
		box.querySelector('button').textContent = cfg.label ? 'Zmeniť výdajné miesto' : 'Vybrať výdajné miesto';
	}
	function pick() {
		if (!window.Packeta || !Packeta.Widget) { alert('Mapa výdajných miest sa nenačítala, skúste obnoviť stránku.'); return; }
		var opts = { language: 'sk', webUrl: cfg.web, appIdentity: cfg.app, vendors: [] };
		(cfg.country || 'sk').split(',').forEach(function (c) { if (c) { opts.vendors.push({ country: c }); opts.vendors.push({ country: c, group: 'zbox' }); } });
		if (cfg.weight) { opts.weight = cfg.weight; }
		Packeta.Widget.pick(cfg.apiKey, function (p) {
			if (!p) { return; }
			var d = new FormData();
			d.append('action', 'jdp_point'); d.append('nonce', cfg.nonce);
			['id', 'name', 'place', 'street', 'city', 'zip', 'country', 'group', 'carrierId', 'carrierPickupPointId'].forEach(function (k) { if (p[k] !== undefined && p[k] !== null) { d.append(k, p[k]); } });
			busy = true;
			fetch(cfg.ajax, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (res) { return res.json(); }).then(function (j) {
				busy = false;
				if (j && j.success) { cfg.point = j.data.point; cfg.label = j.data.label; render(); } else { alert((j && j.data) || 'Výdajné miesto sa nepodarilo uložiť.'); }
			}).catch(function () { busy = false; alert('Výdajné miesto sa nepodarilo uložiť.'); });
		}, opts);
	}
	var t;
	new MutationObserver(function () { clearTimeout(t); t = setTimeout(render, 150); }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['checked'] });
	document.addEventListener('change', function (e) { if (e.target && e.target.type === 'radio') { setTimeout(render, 50); } });
	document.addEventListener('DOMContentLoaded', render);
	setTimeout(render, 500);
})();
JS;
}

// Uloženie vybraného miesta do relácie zákazníka.
foreach ( array( 'wp_ajax_jdp_point', 'wp_ajax_nopriv_jdp_point' ) as $jdp_hook ) {
	add_action( $jdp_hook, function () {
		if ( ! check_ajax_referer( 'jdp_point', 'nonce', false ) || ! WC()->session ) {
			wp_send_json_error( 'Platnosť stránky vypršala – obnovte ju, prosím.' );
		}
		$in = wp_unslash( $_POST );
		$p  = array();
		foreach ( array( 'id', 'name', 'place', 'street', 'city', 'zip', 'country', 'group', 'carrierId', 'carrierPickupPointId' ) as $k ) {
			$p[ $k ] = mb_substr( sanitize_text_field( (string) ( $in[ $k ] ?? '' ) ), 0, 200 );
		}
		if ( '' === $p['id'] && '' === $p['carrierPickupPointId'] ) {
			wp_send_json_error( 'Neplatné výdajné miesto.' );
		}
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		WC()->session->set( 'jdp_point', $p );
		wp_send_json_success( array( 'point' => $p, 'label' => jdp_point_label( $p ) ) );
	} );
}

/** Kontrola pred objednávkou: vybrané miesto (výdajné miesto) a telefón (doručenie na adresu). Vráti chybu alebo ''. */
function jdp_checkout_error( $method_ids, $phone ) {
	$kind = '';
	foreach ( (array) $method_ids as $m ) {
		$kind = 0 === strpos( (string) $m, 'jdp_packeta_pp' ) ? 'pp' : ( 0 === strpos( (string) $m, 'jdp_packeta_hd' ) ? 'hd' : $kind );
	}
	if ( 'pp' === $kind ) {
		$p = jdp_session_point();
		if ( ! $p ) {
			return 'Vyberte, prosím, výdajné miesto Packeta (tlačidlo Vybrať výdajné miesto pri doprave).';
		}
		$w = WC()->cart ? jdp_weight_kg( WC()->cart->get_cart_contents_weight() ) : 0;
		$v = jdp_validate_point( $p['id'], $p['carrierId'] ?? '', $p['carrierPickupPointId'] ?? '', $w );
		if ( is_wp_error( $v ) ) {
			return $v->get_error_message();
		}
	}
	if ( 'hd' === $kind && strlen( preg_replace( '/\D/', '', (string) $phone ) ) < 9 ) {
		return 'Pri doručení kuriérom Packeta na adresu zadajte, prosím, telefónne číslo.';
	}
	return '';
}

function jdp_order_save_point( $order ) {
	$kind = jdp_order_kind( $order );
	if ( 'pp' === $kind && ( $p = jdp_session_point() ) ) {
		$order->update_meta_data( '_jdp_point', $p );
		$order->update_meta_data( '_jdp_point_label', jdp_point_label( $p ) );
	}
	if ( $kind ) {
		$order->update_meta_data( '_jdp_kind', $kind );
	}
}

// Klasická pokladňa.
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	$err = jdp_checkout_error( (array) ( $data['shipping_method'] ?? array() ), $data['billing_phone'] ?? ( $data['shipping_phone'] ?? '' ) );
	if ( $err ) {
		$errors->add( 'jdp', $err );
	}
}, 10, 2 );
add_action( 'woocommerce_checkout_create_order', function ( $order ) {
	jdp_order_save_point( $order );
} );

// Bloková pokladňa (Store API).
add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $order, $request ) {
	$ids = array();
	foreach ( $order->get_shipping_methods() as $m ) {
		$ids[] = $m->get_method_id();
	}
	$err = jdp_checkout_error( $ids, $order->get_billing_phone() ? $order->get_billing_phone() : $order->get_shipping_phone() );
	if ( $err ) {
		throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'jdp_packeta', $err, 400 ); // phpcs:ignore
	}
	jdp_order_save_point( $order );
}, 10, 2 );

/* ---------------------------------------------------------------- Zobrazenie miesta a sledovania zákazníkovi */

function jdp_customer_box( $order, $plain = false ) {
	$label = (string) $order->get_meta( '_jdp_point_label' );
	$bar   = (string) $order->get_meta( '_jdp_barcode' );
	if ( '' === $label && '' === $bar ) {
		return;
	}
	if ( $plain ) {
		echo "\n" . ( $label ? 'Výdajné miesto Packeta: ' . $label . "\n" : '' ) . ( $bar && jdp_opt( 'track_email' ) ? 'Sledovanie zásielky: ' . jdp_tracking_url( $bar ) . "\n" : '' );
		return;
	}
	echo '<section class="jdp-order-box" style="margin:16px 0">';
	if ( $label ) {
		echo '<p><strong>Výdajné miesto Packeta:</strong><br>' . esc_html( $label ) . '</p>';
	}
	if ( $bar && jdp_opt( 'track_email' ) ) {
		echo '<p><strong>Sledovanie zásielky:</strong> <a href="' . esc_url( jdp_tracking_url( $bar ) ) . '">' . esc_html( $bar ) . '</a></p>';
	}
	echo '</section>';
}

add_action( 'woocommerce_email_after_order_table', function ( $order, $sent_to_admin, $plain ) {
	jdp_customer_box( $order, $plain );
}, 15, 3 );
add_action( 'woocommerce_order_details_after_order_table', function ( $order ) {
	jdp_customer_box( $order );
} );
