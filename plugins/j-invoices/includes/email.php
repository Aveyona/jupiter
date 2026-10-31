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
/** Platobné údaje do e-mailu (zostávajúca suma po čiastočnej úhrade). */
function jinv_email_pay_table( array $inv, array $sup ) {
	if ( empty( $sup['iban'] ) ) {
		return '';
	}
	$rest = ! empty( $inv['id'] ) ? jinv_remaining( $inv ) : (float) $inv['total'];
	$paid = round( (float) $inv['total'] - $rest, 2 );
	return '<table cellpadding="4" style="border-collapse:collapse;margin:0 0 16px"><tbody>'
		. ( $paid >= 0.01 ? '<tr><td>Uhradené</td><td>' . esc_html( jinv_money( $paid, $inv['currency'] ) ) . '</td></tr>' : '' )
		. '<tr><td>' . ( $paid >= 0.01 ? 'Zostáva uhradiť' : 'Suma' ) . '</td><td><strong>' . esc_html( jinv_money( $rest, $inv['currency'] ) ) . '</strong></td></tr>'
		. '<tr><td>IBAN</td><td><strong>' . esc_html( trim( chunk_split( (string) $sup['iban'], 4, ' ' ) ) ) . '</strong></td></tr>'
		. '<tr><td>Variabilný symbol</td><td><strong>' . esc_html( $inv['vs'] ) . '</strong></td></tr>'
		. '<tr><td>Splatnosť</td><td>' . esc_html( jinv_date( $inv['due_date'] ) ) . '</td></tr>'
		. '</tbody></table><p>Platbu môžete zadať aj naskenovaním QR kódu PAY by square v PDF.</p>';
}

function jinv_email_body( array $inv, $reminder = false ) {
	$d    = (array) $inv['data'];
	$sup  = (array) ( $d['supplier'] ?? array() );
	$name = trim( (string) ( $d['customer']['name'] ?? '' ) );
	$h    = '<p>' . esc_html( $name ? 'Dobrý deň, ' . $name . ',' : 'Dobrý deň,' ) . '</p>';
	$ord  = ! empty( $d['order_no'] ) ? ' k objednávke č. ' . esc_html( $d['order_no'] ) : '';
	if ( $reminder ) {
		$h .= '<p>dovoľujeme si pripomenúť, že ' . ( 'proforma' === $inv['kind'] ? 'zálohová faktúra' : 'faktúra' ) . ' č. <strong>' . esc_html( $inv['number'] ) . '</strong>' . $ord . ' bola splatná ' . esc_html( jinv_date( $inv['due_date'] ) ) . ' a zatiaľ sme platbu neprijali. Prosíme o úhradu. Ak ste už zaplatili, považujte túto správu za bezpredmetnú.</p>';
		$h .= jinv_email_pay_table( $inv, $sup );
	} elseif ( 'proforma' === $inv['kind'] ) {
		$h .= '<p>v prílohe posielame zálohovú faktúru č. <strong>' . esc_html( $inv['number'] ) . '</strong>' . $ord . '. Objednávku odošleme po prijatí platby.</p>';
		$h .= jinv_email_pay_table( $inv, $sup );
	} elseif ( 'creditnote' === $inv['kind'] ) {
		$h .= '<p>v prílohe posielame dobropis č. <strong>' . esc_html( $inv['number'] ) . '</strong> k faktúre č. ' . esc_html( $d['invoice'] ?? '' ) . $ord . ' na sumu ' . esc_html( jinv_money( abs( (float) $inv['total'] ), $inv['currency'] ) ) . '.</p>';
	} else {
		$h .= '<p>v prílohe posielame faktúru č. <strong>' . esc_html( $inv['number'] ) . '</strong>' . $ord . '.' . ( 'paid' === $inv['status'] ? ' Faktúra je uhradená, nič neplaťte.' : ( jinv_doc_is_cod( $inv ) ? ' Suma sa uhrádza dobierkou pri prevzatí zásielky – neplaťte prevodom.' : '' ) ) . '</p>';
		if ( 'issued' === $inv['status'] && ! jinv_doc_is_cod( $inv ) ) {
			$h .= jinv_email_pay_table( $inv, $sup ); // neuhradená faktúra (napr. prevod odoslaný pred zaplatením)
		}
	}
	$extra = trim( (string) jinv_opt( 'email_text' ) );
	if ( '' !== $extra ) {
		$h .= wpautop( esc_html( $extra ) );
	}
	$h .= '<p>S pozdravom<br>' . esc_html( $sup['company'] ?? get_bloginfo( 'name' ) ) . '</p>';
	return $h;
}

/** Odošle doklad zákazníkovi ($reminder = upomienka k dokladu po splatnosti). Vráti true alebo WP_Error. */
function jinv_send_doc( $id, $to = '', $reminder = false ) {
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
	$subject = sprintf( '%s%s č. %s%s – %s', $reminder ? 'Upomienka: ' : '', $reminder ? mb_strtolower( jinv_kind_label( $inv['kind'] ) ) : jinv_kind_label( $inv['kind'] ), $inv['number'], ! empty( $inv['data']['order_no'] ) ? ' k objednávke ' . $inv['data']['order_no'] : '', $site );
	$mailer  = WC()->mailer();
	$message = $mailer->wrap_message( ( $reminder ? 'Upomienka – ' : '' ) . jinv_kind_label( $inv['kind'] ) . ' ' . $inv['number'], jinv_email_body( $inv, $reminder ) );
	$file    = jinv_pdf_temp_file( $inv );
	$headers = 'Content-Type: text/html; charset=UTF-8';
	$ok      = $mailer->send( $to, $subject, $message, $headers, array( $file ) );
	jinv_pdf_temp_cleanup( $file );
	if ( ! $ok ) {
		return new WP_Error( 'jinv_mail', 'Odoslanie e-mailu zlyhalo (skontrolujte nastavenie odosielania pošty webu).' );
	}
	if ( $reminder ) {
		$data             = (array) $inv['data'];
		$data['reminded'] = array_merge( (array) ( $data['reminded'] ?? array() ), array( current_time( 'mysql' ) ) );
		jinv_update( $inv['id'], array( 'data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) );
	} else {
		jinv_update( $inv['id'], array( 'sent_at' => current_time( 'mysql' ) ) );
	}
	if ( $inv['order_id'] && ( $o = wc_get_order( $inv['order_id'] ) ) ) {
		$o->add_order_note( $reminder
			? sprintf( 'J-Fakturácia: upomienka k dokladu %s č. %s odoslaná zákazníkovi na %s.', mb_strtolower( jinv_kind_label( $inv['kind'] ) ), $inv['number'], $to )
			: sprintf( 'J-Fakturácia: doklad %s č. %s odoslaný zákazníkovi na %s.', mb_strtolower( jinv_kind_label( $inv['kind'] ) ), $inv['number'], $to ) );
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
	if ( ! $send ) {
		return;
	}
	// Odoslanie mimo spracovania objednávky (Action Scheduler) – PDF a SMTP nespomalia ani nezablokujú pokladňu.
	if ( function_exists( 'as_enqueue_async_action' ) ) {
		as_enqueue_async_action( 'jinv_send_doc_async', array( (int) $id ), 'j-invoices' );
		return;
	}
	jinv_send_doc_safe( $id );
}, 10, 4 );

add_action( 'jinv_send_doc_async', 'jinv_send_doc_safe' );

/** Odoslanie dokladu bez rizika pádu (chyba sa zapíše do denníka a poznámky objednávky). */
function jinv_send_doc_safe( $id ) {
	try {
		$r = jinv_send_doc( (int) $id );
		if ( is_wp_error( $r ) ) {
			throw new \RuntimeException( $r->get_error_message() );
		}
	} catch ( \Throwable $e ) {
		$inv = jinv_get( (int) $id );
		jinv_log_error( $inv && $inv['order_id'] ? wc_get_order( $inv['order_id'] ) : null, $e, 'odoslanie dokladu e-mailom' );
	}
}
