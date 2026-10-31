<?php
/** Box „Sklad (J-Sklad)“ v detaile produktu: zásoba, priemerná nákupná cena a posledné pohyby (aj variantov). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes_product', function () {
	add_meta_box( 'jsk-product-box', 'Sklad (J-Sklad)', 'jsk_product_box', 'product', 'side', 'default' );
} );

function jsk_product_box( $post ) {
	global $wpdb;
	$p = wc_get_product( $post->ID );
	if ( ! $p ) {
		return;
	}
	$ids = array( $p->get_id() );
	if ( $p->is_type( 'variable' ) ) {
		$ids = array_merge( $ids, array_map( 'intval', $p->get_children() ) );
	}
	if ( ! $p->managing_stock() && ! $p->is_type( 'variable' ) ) {
		echo '<p>Produkt nemá zapnuté sledovanie zásob (Zásoby → Spravovať zásoby). J-Sklad eviduje len produkty so sledovaním zásob.</p>';
		return;
	}
	if ( $p->managing_stock() ) {
		$cost = jsk_cost( $p->get_id() );
		echo '<p>Na sklade: <strong>' . esc_html( jsk_qty_unit( $p->get_stock_quantity(), $p, true ) ) . '</strong><br>Priemerná nákupná cena: <strong>' . esc_html( jsk_money( $cost ) ) . '</strong></p>';
	}
	$in   = implode( ',', array_map( 'intval', $ids ) );
	$rows = $wpdb->get_results( 'SELECT * FROM ' . jsk_t( 'moves' ) . " WHERE product_id IN ($in) ORDER BY id DESC LIMIT 10", ARRAY_A ); // phpcs:ignore
	if ( ! $rows ) {
		echo '<p class="description">Zatiaľ žiadne pohyby.</p>';
	} else {
		echo '<table class="jsk-mini"><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( date_i18n( 'j. n. y', strtotime( $r['created_at'] ) ) ) . '</td><td>' . esc_html( jsk_move_sources()[ $r['source'] ] ?? $r['source'] ) . ( (int) $r['product_id'] !== $p->get_id() ? '<br><small>' . esc_html( jsk_var_label( (int) $r['product_id'] ) ) . '</small>' : '' ) . '</td><td class="n ' . ( (float) $r['qty'] < 0 ? 'jsk-neg' : 'jsk-pos' ) . '">' . esc_html( ( (float) $r['qty'] > 0 ? '+' : '' ) . jsk_qty_unit( $r['qty'], (int) $r['product_id'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '<p><a href="' . esc_url( jsk_url( 'j-sklad-moves', array( 'product' => $p->get_id() ) ) ) . '">Celá história</a> · <a href="' . esc_url( jsk_url( 'j-sklad-docs', array( 'new' => 'receipt' ) ) ) . '">Nová príjemka</a></p>';
}

/** Krátky popis variantu (atribúty alebo SKU). */
function jsk_var_label( $id ) {
	$v = wc_get_product( $id );
	if ( ! $v ) {
		return '#' . $id;
	}
	$a = $v->is_type( 'variation' ) ? wc_get_formatted_variation( $v, true, false, false ) : '';
	return $a ? wp_strip_all_tags( $a ) : ( $v->get_sku() ? $v->get_sku() : '#' . $id );
}
