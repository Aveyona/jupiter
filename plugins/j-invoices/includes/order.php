<?php
/** Box „Doklady“ v detaile objednávky (klasické objednávky aj HPOS) a stĺpec v zozname objednávok. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', function () {
	$screens = array( 'shop_order' );
	if ( function_exists( 'wc_get_page_screen_id' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' );
	}
	foreach ( array_unique( $screens ) as $scr ) {
		add_meta_box( 'jinv-order-box', 'Doklady (J-Fakturácia)', 'jinv_order_box', $scr, 'side', 'high' );
	}
} );

function jinv_order_box( $post_or_order ) {
	$o = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $o || ! $o->get_id() ) {
		return;
	}
	$docs = jinv_docs_for_order( $o->get_id() );
	$back = $o->get_edit_order_url();
	if ( $docs ) {
		echo '<ul class="jinv-box-docs">';
		foreach ( $docs as $d ) {
			echo '<li><a href="' . esc_url( jinv_admin_url( array( 'view' => $d['id'] ) ) ) . '"><strong>' . esc_html( jinv_kind_label( $d['kind'] ) ) . ' ' . esc_html( $d['number'] ) . '</strong></a> ' . jinv_status_badge( $d ) // phpcs:ignore
				. '<br><small>' . esc_html( jinv_date( $d['issue_date'] ) ) . ' · ' . esc_html( jinv_money( $d['total'], $d['currency'] ) ) . ( 'woo-faktura' === $d['source'] ? ' · Woo Faktura' : '' ) . '</small></li>';
		}
		echo '</ul>';
	} else {
		echo '<p>K objednávke zatiaľ nie je vystavený žiadny doklad.</p>';
	}
	$has = function ( $kind ) use ( $o ) {
		return (bool) jinv_by_order( $o->get_id(), $kind );
	};
	echo '<p class="jinv-box-actions">';
	if ( ! $has( 'issued' ) ) {
		echo '<a class="button button-primary" href="' . esc_url( jinv_action_url( 'create', $o->get_id(), $back ) ) . '">Vystaviť faktúru</a> ';
	}
	if ( ! $has( 'proforma' ) && ! $has( 'issued' ) ) {
		echo '<a class="button" href="' . esc_url( jinv_action_url( 'create_proforma', $o->get_id(), $back ) ) . '">Vystaviť zálohovú faktúru</a> ';
	}
	if ( $has( 'issued' ) ) {
		echo '<a class="button" href="' . esc_url( jinv_action_url( 'create_creditnote', $o->get_id(), $back ) ) . '" onclick="return confirm(\'Vystaviť dobropis? Ak má objednávka vrátenie peňazí (Refund) bez dobropisu, dobropis bude na vrátenú sumu, inak na celú faktúru.\');">Vystaviť dobropis</a>';
	}
	echo '</p>';
	if ( jinv_woo_faktura_active() ) {
		echo '<p class="description">Režim prechodu: kým beží Woo Faktura, J-Fakturácia nevystavuje doklady automaticky.</p>';
	} else {
		echo '<p class="description">Automaticky: prevod na účet – zálohová faktúra pri objednávke, faktúra po zaplatení; ostatné platby – faktúra po vybavení.</p>';
	}
}

// Stĺpec „Faktúra“ v zozname objednávok.
$jinv_col = function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'order_status' === $k ) {
			$new['jinv'] = 'Faktúra';
		}
	}
	return $new;
};
add_filter( 'manage_edit-shop_order_columns', $jinv_col, 20 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', $jinv_col, 20 );
$jinv_cell = function ( $col, $order ) {
	if ( 'jinv' !== $col ) {
		return;
	}
	$o   = $order instanceof WC_Order ? $order : wc_get_order( $order );
	$num = $o ? (string) $o->get_meta( '_jinv_invoice' ) : '';
	echo $num ? esc_html( $num ) : '–';
};
add_action( 'manage_shop_order_posts_custom_column', $jinv_cell, 20, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', $jinv_cell, 20, 2 );
