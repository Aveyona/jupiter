<?php
/**
 * Odoslanie dokladu zákazníkovi e-mailom (PDF v prílohe) cez poštu WooCommerce – vzhľad podľa šablóny e-mailov obchodu.
 * Automaticky podľa nastavení (zálohová faktúra, faktúra, dobropis) alebo tlačidlom v detaile dokladu.
 * Kým beží Woo Faktura, automatické odosielanie je vypnuté (posiela Woo Faktura).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Text e-mailu (HTML) pre doklad. */
function jinv_email_body( array $inv ) {
	$d    = (array) $inv['data'];
	$sup  = (array) ( $d['supplier'] ?? array() );
	$name = trim( (string) ( $d['customer']['name'] ?? '' ) );
	$h    = '<p>' . esc_html( $name ? 'Dobrý deň, ' . $name . ',' : 'Dobrý deň,' ) . '</p>';
	$ord  = ! empty( $d['order_no'] ) ? ' k objednávke č. ' . esc_html( $d['order_no'] ) : '';
	if ( 'proforma' === $inv['kind'] ) {
		$h .= '<p>v prílohe posielame zálohovú faktúru č. <strong>' . esc_html( $inv['number'] ) . '</strong>' . $ord . '. Objednávku odošleme po prijatí platby.</p>';
		if ( ! empty( $sup['iban'] ) ) {
			$h .= '<table cellpadding="4" style="border-collapse:collapse;margin:0 0 16px"><tbody>'
				. '<tr><td>Suma</td><td><strong>' . esc_html( jinv_money( $inv['total'], $inv['currency'] ) ) . '</strong></td></tr>'
				. '<tr><td>IBAN</td><td><strong>' . esc_html( trim( chunk_split( (string) $sup['iban'], 4, ' ' ) ) ) . '</strong></td></tr>'
				. '<tr><td>Variabilný symbol</td><td><strong>' . esc_html( $inv['vs'] ) . '</strong></td></tr>'
				. '<tr><td>Splatnosť</td><td>' . esc_html( jinv_date( $inv['due_date'] ) ) . '</td></tr>'
				. '</tbody></table><p>Platbu môžete zadať aj naskenovaním QR kódu PAY by square v PDF.</p>';
		}
	} elseif ( 'creditnote' === $inv['kind'] ) {
		$h .= '<p>v prílohe posielame dobropis č. <strong>' . esc_html( $inv['number'] ) . '</strong> k faktúre č. ' . esc_html( $d['invoice'] ?? '' ) . $ord . ' na sumu ' . esc_html( jinv_money( abs( (float) $inv['total'] ), $inv['currency'] ) ) . '.</p>';
	} else {
		$h .= '<p>v prílohe posielame faktúru č. <strong>' . esc_html( $inv['number'] ) . '</strong>' . $ord . '.' . ( 'paid' === $inv['status'] ? ' Faktúra je uhradená, nič neplaťte.' : '' ) . '</p>';
	}
	$extra = trim( (string) jinv_opt( 'email_text' ) );
	if ( '' !== $extra ) {
		$h .= wpautop( esc_html( $extra ) );
	}
	$h .= '<p>S pozdravom<br>' . esc_html( $sup['company'] ?? get_bloginfo( 'name' ) ) . '</p>';
	return $h;
}

/** Odošle doklad zákazníkovi. Vráti true alebo WP_Error. */
function jinv_send_doc( $id, $to = '' ) {
	$inv = is_array( $id ) ? $id : jinv_get( (int) $id );
	if ( ! $inv ) {
		return new WP_Error( 'jinv_none', 'Doklad neexistuje.' );
	}
	$to = $to ? $to : $inv['customer_email'];
	if ( ! is_email( $to ) ) {
		return new WP_Error( 'jinv_mail', 'Doklad nemá platný e-mail zákazníka.' );
	}
	if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
		return new WP_Error( 'jinv_mail', 'Pošta WooCommerce nie je dostupná.' );
	}
	$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject = sprintf( '%s č. %s%s – %s', jinv_kind_label( $inv['kind'] ), $inv['number'], ! empty( $inv['data']['order_no'] ) ? ' k objednávke ' . $inv['data']['order_no'] : '', $site );
	$mailer  = WC()->mailer();
	$message = $mailer->wrap_message( jinv_kind_label( $inv['kind'] ) . ' ' . $inv['number'], jinv_email_body( $inv ) );
	$file    = jinv_pdf_temp_file( $inv );
	$headers = 'Content-Type: text/html; charset=UTF-8';
	$ok      = $mailer->send( $to, $subject, $message, $headers, array( $file ) );
	jinv_pdf_temp_cleanup( $file );
	if ( ! $ok ) {
		return new WP_Error( 'jinv_mail', 'Odoslanie e-mailu zlyhalo (skontrolujte nastavenie odosielania pošty webu).' );
	}
	jinv_update( $inv['id'], array( 'sent_at' => current_time( 'mysql' ) ) );
	if ( $inv['order_id'] && ( $o = wc_get_order( $inv['order_id'] ) ) ) {
		$o->add_order_note( sprintf( 'J-Fakturácia: doklad %s č. %s odoslaný zákazníkovi na %s.', mb_strtolower( jinv_kind_label( $inv['kind'] ) ), $inv['number'], $to ) );
	}
	return true;
}

// Automatické odoslanie nového dokladu podľa nastavení.
add_action( 'jinv_document_created', function ( $id, $kind, $order, $opts ) {
	if ( isset( $opts['send'] ) ) {
		$send = (bool) $opts['send'];
	} else {
		$send = (bool) jinv_opt( 'send_' . $kind ) && ! jinv_woo_faktura_active();
	}
	if ( $send ) {
		jinv_send_doc( $id );
	}
}, 10, 4 );
