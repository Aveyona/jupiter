<?php
/**
 * Balíčky (zostavy): produkt, ku ktorému sa v košíku automaticky pridajú ďalšie produkty (súčasti).
 * Napr. „E-shop na kľúč“ = e-shop + Členstvo na 1 rok + 8 h IT poradenstva.
 *
 * - Súčasti sú bežné riadky košíka a objednávky (vlastná cena, sklad, faktúra, zapnutie členstva…).
 * - Cena súčasti v balíčku: bežná cena produktu, alebo vlastná cena zadaná v balíčku.
 * - Počet súčastí sa riadi počtom balíčkov; súčasti sa nedajú meniť ani odobrať samostatne
 *   (odobratie súčasti alebo balíčka odoberie celý balíček).
 * Meta produktu `_jsk_bundle` = [ { id, qty, price } ] (price '' = bežná cena).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Súčasti balíčka produktu (len existujúce produkty). */
function jsk_bundle_items( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
	if ( ! $product instanceof WC_Product ) {
		return array();
	}
	$rows = (array) $product->get_meta( '_jsk_bundle' );
	$out  = array();
	foreach ( $rows as $r ) {
		$id  = absint( $r['id'] ?? 0 );
		$qty = max( 0, (float) ( $r['qty'] ?? 0 ) );
		if ( ! $id || ! $qty || $id === $product->get_id() || ! wc_get_product( $id ) ) {
			continue;
		}
		$out[] = array( 'id' => $id, 'qty' => $qty, 'price' => isset( $r['price'] ) && '' !== $r['price'] && null !== $r['price'] ? round( (float) $r['price'], 2 ) : '' );
	}
	return $out;
}

/** Cena súčasti v balíčku (za 1 ks). */
function jsk_bundle_item_price( array $row ) {
	if ( '' !== $row['price'] ) {
		return (float) $row['price'];
	}
	$p = wc_get_product( $row['id'] );
	return $p ? (float) $p->get_price() : 0.0;
}

/** Cena celého balíčka (produkt + súčasti) za 1 kus. */
function jsk_bundle_total( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
	if ( ! $product ) {
		return 0.0;
	}
	$sum = (float) $product->get_price();
	foreach ( jsk_bundle_items( $product ) as $r ) {
		$sum += $r['qty'] * jsk_bundle_item_price( $r );
	}
	return round( $sum, 2 );
}

/** Text „8 h“ / „1 ks“ podľa produktu. */
function jsk_bundle_qty_label( $qty, $product ) {
	$unit = $product ? jsk_unit( $product ) : 'ks';
	if ( 'ks' === $unit && $product && '' === (string) $product->get_meta( '_jsk_unit' ) && preg_match( '/poraden|hodin|konzult/iu', $product->get_name() ) ) {
		$unit = 'h'; // staršie produkty bez nastavenej jednotky
	}
	if ( 'ks' === $unit && 1.0 === (float) $qty ) {
		return ''; // „1 ks“ je zbytočné – pri jednom kuse sa množstvo neuvádza.
	}
	return wc_format_localized_decimal( rtrim( rtrim( number_format( (float) $qty, 2, '.', '' ), '0' ), '.' ) ) . ' ' . $unit;
}

/** Názov súčasti balíčka s množstvom („IT poradenstvo – 8 h“, pri 1 ks len názov). */
function jsk_bundle_item_label( $qty, $product ) {
	$name = $product ? wp_strip_all_tags( $product->get_name() ) : '';
	$q    = jsk_bundle_qty_label( $qty, $product );
	return '' === $q ? $name : $name . ' – ' . $q;
}

/* ---------- Administrácia: záložka „Balíček“ v úprave produktu ---------- */

add_filter( 'woocommerce_product_data_tabs', function ( $tabs ) {
	$tabs['jsk_bundle'] = array( 'label' => 'Balíček', 'target' => 'jsk_bundle_data', 'class' => array(), 'priority' => 65 );
	return $tabs;
} );

add_action( 'woocommerce_product_data_panels', function () {
	global $post;
	$product = wc_get_product( $post->ID );
	$rows    = $product ? jsk_bundle_items( $product ) : array();
	$all     = wc_get_products( array( 'limit' => 300, 'status' => array( 'publish', 'private', 'draft' ), 'orderby' => 'title', 'order' => 'ASC', 'exclude' => array( $post->ID ), 'type' => array( 'simple', 'variation', 'variable' ) ) );
	for ( $i = 0; $i < 3; $i++ ) {
		$rows[] = array( 'id' => 0, 'qty' => 1, 'price' => '' ); // 3 prázdne riadky na nové súčasti
	}
	wp_nonce_field( 'jsk_bundle_save', 'jsk_bundle_nonce' );
	?>
	<div id="jsk_bundle_data" class="panel woocommerce_options_panel" style="padding:12px 16px">
		<p style="margin:0 0 10px;padding:0">Produkty, ktoré sa pri kúpe tohto produktu <strong>pridajú do košíka</strong>. Cena balíčka = cena tohto produktu + súčasti. <em>Cena v balíčku</em> prázdna = bežná cena súčasti.</p>
		<table class="widefat striped" style="max-width:720px">
			<thead><tr><th>Súčasť</th><th style="width:90px">Počet</th><th style="width:130px">Cena v balíčku (€/ks)</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $i => $r ) : ?>
				<tr>
					<td><select name="jsk_bundle[<?php echo (int) $i; ?>][id]" style="width:100%"><option value="">— žiadna —</option><?php foreach ( $all as $p ) : ?><option value="<?php echo (int) $p->get_id(); ?>" <?php selected( $r['id'], $p->get_id() ); ?>><?php echo esc_html( wp_strip_all_tags( $p->get_name() ) . ' (' . wp_strip_all_tags( wc_price( $p->get_price() ) ) . ')' ); ?></option><?php endforeach; ?></select></td>
					<td><input type="number" min="0" step="0.5" name="jsk_bundle[<?php echo (int) $i; ?>][qty]" value="<?php echo esc_attr( $r['qty'] ); ?>" style="width:80px"></td>
					<td><input type="text" name="jsk_bundle[<?php echo (int) $i; ?>][price]" value="<?php echo esc_attr( '' === $r['price'] ? '' : wc_format_localized_price( $r['price'] ) ); ?>" placeholder="bežná" style="width:110px"></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $product && jsk_bundle_items( $product ) ) : ?>
			<p style="padding:0;margin:10px 0 0"><strong>Cena balíčka spolu: <?php echo wp_kses_post( wc_price( jsk_bundle_total( $product ) ) ); ?></strong> (po uložení sa prepočíta)</p>
		<?php endif; ?>
		<p class="description" style="padding:0;margin:8px 0 0">Ďalšie riadky pribudnú po uložení. Súčasť odstránite voľbou „— žiadna —“.</p>
	</div>
	<?php
} );

add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
	if ( ! isset( $_POST['jsk_bundle_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['jsk_bundle_nonce'] ), 'jsk_bundle_save' ) ) {
		return;
	}
	$rows = array();
	foreach ( (array) wp_unslash( $_POST['jsk_bundle'] ?? array() ) as $r ) { // phpcs:ignore
		$id  = absint( $r['id'] ?? 0 );
		$qty = (float) str_replace( ',', '.', (string) ( $r['qty'] ?? '' ) );
		if ( ! $id || $qty <= 0 || (int) $id === (int) $post_id ) {
			continue;
		}
		$price  = trim( (string) ( $r['price'] ?? '' ) );
		$rows[] = array( 'id' => $id, 'qty' => $qty, 'price' => '' === $price ? '' : round( (float) wc_format_decimal( $price ), 2 ) );
	}
	$p = wc_get_product( $post_id );
	if ( $rows ) {
		$p->update_meta_data( '_jsk_bundle', $rows );
	} else {
		$p->delete_meta_data( '_jsk_bundle' );
	}
	$p->save_meta_data();
} );

// Stĺpec v zozname produktov: ikona balíčka.
add_filter( 'manage_edit-product_columns', function ( $c ) {
	$c['jsk_bundle'] = '<span class="dashicons dashicons-screenoptions" title="Balíček"></span>';
	return $c;
}, 30 );
add_action( 'manage_product_posts_custom_column', function ( $col, $id ) {
	if ( 'jsk_bundle' === $col && jsk_bundle_items( $id ) ) {
		echo '<span class="dashicons dashicons-screenoptions" title="Balíček: ' . esc_attr( count( jsk_bundle_items( $id ) ) ) . ' súčasti"></span>';
	}
}, 10, 2 );

/* ---------- Košík ---------- */

/**
 * Súčasť balíčka, ktorá sa dá vložiť do košíka: pri variabilnom produkte (napr. Členstvo Mesačne/Ročne)
 * sa použije predvolená variácia, inak prvá dostupná.
 */
function jsk_bundle_cart_product_id( $id ) {
	$p = wc_get_product( $id );
	if ( ! $p || ! $p->is_type( 'variable' ) ) {
		return (int) $id;
	}
	$def = array();
	foreach ( $p->get_default_attributes() as $att => $val ) {
		$def[ 'attribute_' . $att ] = $val;
	}
	if ( $def ) {
		$vid = WC_Data_Store::load( 'product' )->find_matching_product_variation( $p, $def );
		$v   = $vid ? wc_get_product( $vid ) : null;
		if ( $v && $v->is_purchasable() ) {
			return (int) $vid;
		}
	}
	foreach ( $p->get_children() as $vid ) {
		$v = wc_get_product( $vid );
		if ( $v && $v->is_purchasable() ) {
			return (int) $vid;
		}
	}
	$kids = $p->get_children();
	return $kids ? (int) $kids[0] : (int) $id;
}

/** Doplní do košíka chýbajúce súčasti balíčka (pri pridaní aj pri starom košíku z relácie). */
function jsk_bundle_sync_children( $key ) {
	static $busy = false;
	$cart = WC()->cart;
	if ( $busy || ! $cart || empty( $cart->cart_contents[ $key ] ) ) {
		return true;
	}
	$item  = $cart->cart_contents[ $key ];
	$items = jsk_bundle_items( ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'] );
	if ( ! $items || ! empty( $item['jsk_bundle_parent'] ) ) {
		return true;
	}
	$busy     = true;
	$ok       = true;
	$children = array();
	$have     = array();
	foreach ( (array) ( $item['jsk_bundle_children'] ?? array() ) as $ck ) {
		if ( isset( $cart->cart_contents[ $ck ] ) ) {
			$c = $cart->cart_contents[ $ck ];
			$have[ (int) ( $c['variation_id'] ? $c['variation_id'] : $c['product_id'] ) ] = $ck;
		}
	}
	foreach ( $items as $r ) {
		$pid = jsk_bundle_cart_product_id( $r['id'] );
		$qty = $item['quantity'] * $r['qty'];
		if ( isset( $have[ $pid ] ) ) {
			$children[] = $have[ $pid ];
			unset( $have[ $pid ] );
			continue;
		}
		$ck = $cart->add_to_cart( $pid, $qty, 0, array(), array( 'jsk_bundle_parent' => $key, 'jsk_bundle_qty' => $r['qty'], 'jsk_bundle_price' => $r['price'] ) );
		if ( $ck ) {
			$children[] = $ck;
		} else {
			$ok = false;
		}
	}
	// Súčasti, ktoré už v balíčku nie sú (zmenené nastavenie balíčka), sa odoberú.
	foreach ( $have as $ck ) {
		unset( $cart->cart_contents[ $ck ] );
	}
	if ( isset( $cart->cart_contents[ $key ] ) ) {
		$cart->cart_contents[ $key ]['jsk_bundle_children'] = array_values( array_unique( $children ) );
	}
	$busy = false;
	return $ok;
}

// Po pridaní balíčka pridá súčasti. Ak sa niektorá pridať nedá, balíček sa z košíka odoberie celý.
add_action( 'woocommerce_add_to_cart', function ( $key, $product_id, $qty, $variation_id, $variation, $data ) {
	if ( ! empty( $data['jsk_bundle_parent'] ) ) {
		return;
	}
	if ( ! jsk_bundle_sync_children( $key ) ) {
		WC()->cart->remove_cart_item( $key );
		wc_add_notice( 'Balíček sa teraz nedá pridať do košíka – niektorá jeho súčasť nie je dostupná. Skúste to prosím neskôr alebo nám napíšte.', 'error' );
	}
}, 20, 6 );

// Starý košík z relácie (napr. po zmene nastavenia balíčka) – doplní chýbajúce súčasti.
add_action( 'woocommerce_cart_loaded_from_session', function ( $cart ) {
	foreach ( array_keys( $cart->cart_contents ) as $k ) {
		if ( empty( $cart->cart_contents[ $k ]['jsk_bundle_parent'] ) ) {
			jsk_bundle_sync_children( $k );
		}
	}
}, 20 );

// Údaje balíčka prežijú uloženie košíka v relácii.
add_filter( 'woocommerce_get_cart_item_from_session', function ( $item, $values ) {
	foreach ( array( 'jsk_bundle_parent', 'jsk_bundle_qty', 'jsk_bundle_price', 'jsk_bundle_children' ) as $k ) {
		if ( isset( $values[ $k ] ) ) {
			$item[ $k ] = $values[ $k ];
		}
	}
	return $item;
}, 10, 2 );

// Cena súčasti v balíčku.
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	foreach ( $cart->get_cart() as $k => $item ) {
		if ( ! empty( $item['jsk_bundle_parent'] ) && isset( $item['jsk_bundle_price'] ) && '' !== $item['jsk_bundle_price'] && null !== $item['jsk_bundle_price'] ) {
			$item['data']->set_price( (float) $item['jsk_bundle_price'] );
		}
	}
}, 20 );

// Zmena počtu balíčkov → prepočet súčastí.
add_action( 'woocommerce_after_cart_item_quantity_update', function ( $key, $qty ) {
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$cart = WC()->cart;
	$item = $cart->cart_contents[ $key ] ?? null;
	if ( ! $item ) {
		return;
	}
	$busy = true;
	if ( ! empty( $item['jsk_bundle_children'] ) ) {
		foreach ( (array) $item['jsk_bundle_children'] as $ck ) {
			if ( isset( $cart->cart_contents[ $ck ] ) ) {
				$cart->set_quantity( $ck, $qty * (float) $cart->cart_contents[ $ck ]['jsk_bundle_qty'], false );
			}
		}
	} elseif ( ! empty( $item['jsk_bundle_parent'] ) && isset( $cart->cart_contents[ $item['jsk_bundle_parent'] ] ) ) {
		// Súčasť sa nemení samostatne – vráti sa podľa balíčka.
		$cart->set_quantity( $key, $cart->cart_contents[ $item['jsk_bundle_parent'] ]['quantity'] * (float) $item['jsk_bundle_qty'], false );
	}
	$busy = false;
}, 20, 2 );

// Odobratie balíčka alebo jeho súčasti odoberie celý balíček.
add_action( 'woocommerce_remove_cart_item', function ( $key, $cart ) {
	static $busy = false;
	if ( $busy ) {
		return;
	}
	$item = $cart->cart_contents[ $key ] ?? null;
	if ( ! $item ) {
		return;
	}
	$parent = ! empty( $item['jsk_bundle_parent'] ) ? $item['jsk_bundle_parent'] : ( ! empty( $item['jsk_bundle_children'] ) ? $key : '' );
	if ( ! $parent ) {
		return;
	}
	$busy = true;
	$keys = array_merge( array( $parent ), (array) ( $cart->cart_contents[ $parent ]['jsk_bundle_children'] ?? array() ) );
	foreach ( $keys as $k ) {
		if ( $k !== $key && isset( $cart->cart_contents[ $k ] ) ) {
			$cart->remove_cart_item( $k );
		}
	}
	$busy = false;
}, 10, 2 );

// Súčasti nemajú v košíku výber počtu ani odstránenie.
add_filter( 'woocommerce_store_api_product_quantity_editable', function ( $editable, $product, $item ) {
	return ! empty( $item['jsk_bundle_parent'] ) ? false : $editable;
}, 10, 3 );
add_filter( 'woocommerce_cart_item_quantity', function ( $html, $key, $item ) {
	return ! empty( $item['jsk_bundle_parent'] ) ? '<span class="jsk-bundle-qty">' . esc_html( wc_stock_amount( $item['quantity'] ) ) . '</span>' : $html;
}, 10, 3 );
add_filter( 'woocommerce_cart_item_remove_link', function ( $link, $key ) {
	$item = WC()->cart ? WC()->cart->get_cart_item( $key ) : array();
	return ! empty( $item['jsk_bundle_parent'] ) ? '' : $link;
}, 10, 2 );
add_filter( 'woocommerce_cart_item_class', function ( $class, $item ) {
	return ! empty( $item['jsk_bundle_parent'] ) ? $class . ' jsk-bundle-child' : ( ! empty( $item['jsk_bundle_children'] ) ? $class . ' jsk-bundle-parent' : $class );
}, 10, 2 );

// Popis v košíku (klasický aj blokový).
add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	if ( ! empty( $item['jsk_bundle_parent'] ) ) {
		$parent = WC()->cart ? WC()->cart->get_cart_item( $item['jsk_bundle_parent'] ) : array();
		$data[] = array( 'key' => 'Súčasť balíčka', 'value' => $parent && ! empty( $parent['data'] ) ? wp_strip_all_tags( $parent['data']->get_name() ) : 'áno' );
	} elseif ( ! empty( $item['jsk_bundle_children'] ) ) {
		$names = array();
		foreach ( jsk_bundle_items( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] ) as $r ) {
			$p       = wc_get_product( $r['id'] );
			$names[] = jsk_bundle_item_label( $r['qty'], $p );
		}
		$data[] = array( 'key' => 'Balíček obsahuje', 'value' => implode( ', ', $names ) );
	}
	return $data;
}, 10, 2 );

/* ---------- Objednávka ---------- */

add_action( 'woocommerce_checkout_create_order_line_item', function ( $line, $key, $values ) {
	if ( ! empty( $values['jsk_bundle_parent'] ) ) {
		$parent = WC()->cart ? WC()->cart->get_cart_item( $values['jsk_bundle_parent'] ) : array();
		if ( $parent && ! empty( $parent['data'] ) ) {
			$line->add_meta_data( 'Súčasť balíčka', wp_strip_all_tags( $parent['data']->get_name() ), true );
			$line->add_meta_data( '_jsk_bundle_parent', (int) $parent['data']->get_id(), true );
		}
	} elseif ( ! empty( $values['jsk_bundle_children'] ) ) {
		$line->add_meta_data( '_jsk_bundle', 1, true );
	}
}, 10, 3 );

/* ---------- Obchod: stránka produktu a karty ---------- */

// Stránka produktu: čo je v balíčku a cena spolu.
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	$items = $product ? jsk_bundle_items( $product ) : array();
	if ( ! $items ) {
		return;
	}
	echo '<div class="jsk-bundle-box"><p class="jsk-bundle-title">V balíčku dostanete</p><ul>';
	echo '<li><span class="jsk-ok" aria-hidden="true">✓</span> ' . esc_html( wp_strip_all_tags( $product->get_name() ) ) . ' <span class="jsk-bp">' . wp_kses_post( wc_price( $product->get_price() ) ) . '</span></li>';
	foreach ( $items as $r ) {
		$p = wc_get_product( $r['id'] );
		echo '<li><span class="jsk-ok" aria-hidden="true">✓</span> ' . esc_html( jsk_bundle_item_label( $r['qty'], $p ) ) . ' <span class="jsk-bp">' . wp_kses_post( wc_price( $r['qty'] * jsk_bundle_item_price( $r ) ) ) . '</span></li>';
	}
	echo '</ul><p class="jsk-bundle-sum">Cena balíčka spolu <strong>' . wp_kses_post( wc_price( jsk_bundle_total( $product ) ) ) . '</strong></p></div>';
}, 25 );

// Karty produktov: „balíček spolu …“ pod cenou.
add_filter( 'woocommerce_get_price_html', function ( $html, $product ) {
	if ( is_admin() && ! wp_doing_ajax() || ! $product || ! jsk_bundle_items( $product ) || ( function_exists( 'is_product' ) && is_product() && get_queried_object_id() === $product->get_id() ) ) {
		return $html;
	}
	return $html . '<span class="jsk-bundle-price"> · balíček spolu ' . wp_kses_post( wc_price( jsk_bundle_total( $product ) ) ) . '</span>';
}, 30, 2 );

add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}
	echo '<style id="jsk-bundle-css">.jsk-bundle-box{margin:16px 0 20px;padding:16px 18px;border:1px solid #dbe3ef;border-radius:14px;background:rgba(255,255,255,.55)}.jsk-bundle-title{margin:0 0 8px;font-weight:700}.jsk-bundle-box ul{list-style:none;margin:0;padding:0}.jsk-bundle-box li{display:flex;gap:8px;align-items:baseline;padding:4px 0}.jsk-bundle-box .jsk-ok{color:#16a34a;font-weight:700}.jsk-bundle-box .jsk-bp{margin-left:auto;color:#475569;white-space:nowrap}.jsk-bundle-sum{margin:10px 0 0;padding-top:10px;border-top:1px solid #dbe3ef;display:flex;justify-content:space-between}.jsk-bundle-price{display:block;font-size:.85em;color:#475569}.jsk-bundle-child td.product-name{padding-left:2em}</style>';
} );

/* ---------- J-Sklad → Balíčky (prehľad) ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-sklad', 'Balíčky', 'Balíčky', JSK_CAP, 'j-sklad-bundles', 'jsk_render_bundles', 3 );
}, 20 );

function jsk_render_bundles() {
	if ( ! current_user_can( JSK_CAP ) ) {
		return;
	}
	$ids = wc_get_products( array( 'limit' => 200, 'status' => array( 'publish', 'private', 'draft' ), 'meta_key' => '_jsk_bundle', 'meta_compare' => 'EXISTS', 'return' => 'ids' ) );
	echo '<div class="wrap"><h1>Balíčky</h1><p>Produkty, ku ktorým sa v košíku automaticky pridajú ďalšie produkty. Balíček nastavíte v úprave produktu → záložka <strong>Balíček</strong>.</p>';
	echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>Balíček</th><th>Súčasti</th><th class="num">Cena produktu</th><th class="num">Spolu</th></tr></thead><tbody>';
	if ( ! $ids ) {
		echo '<tr><td colspan="4">Zatiaľ žiadne balíčky.</td></tr>';
	}
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		$c = array();
		foreach ( jsk_bundle_items( $p ) as $r ) {
			$cp  = wc_get_product( $r['id'] );
			$c[] = esc_html( jsk_bundle_item_label( $r['qty'], $cp ) ) . ( '' !== $r['price'] ? ' <small>(v balíčku ' . wp_kses_post( wc_price( $r['price'] ) ) . '/ks)</small>' : '' );
		}
		echo '<tr><td><a href="' . esc_url( get_edit_post_link( $id ) ) . '"><strong>' . esc_html( wp_strip_all_tags( $p->get_name() ) ) . '</strong></a></td><td>' . implode( '<br>', $c ) . '</td><td class="num">' . wp_kses_post( wc_price( $p->get_price() ) ) . '</td><td class="num"><strong>' . wp_kses_post( wc_price( jsk_bundle_total( $p ) ) ) . '</strong></td></tr>'; // phpcs:ignore
	}
	echo '</tbody></table></div>';
}
