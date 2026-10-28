<?php
/** Nastavenia J-Fakturácia: údaje dodávateľa, číselný rad, splatnosť, kedy vznikne faktúra. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jinv_defaults() {
	return array(
		'company'      => get_bloginfo( 'name' ),
		'street'       => (string) get_option( 'woocommerce_store_address' ),
		'city'         => (string) get_option( 'woocommerce_store_city' ),
		'zip'          => (string) get_option( 'woocommerce_store_postcode' ),
		'country'      => 'Slovensko',
		'ico'          => '',
		'dic'          => '',
		'icdph'        => '',
		'register'     => '',            // napr. Zapísaná v OR Okresného súdu Žilina, oddiel Sro, vložka č. …
		'vat_payer'    => 0,
		'vat_note'     => 'Nie sme platiteľom DPH.',
		'iban'         => '',
		'swift'        => '',
		'bank'         => '',
		'email'        => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
		'phone'        => '',
		'web'          => home_url( '/' ),
		'numbering'    => 'series',       // series = vlastný ročný rad, order = číslo dokladu = číslo objednávky
		'format'       => '{RRRR}{nnn}',  // číselný rad: {RRRR} rok, {RR} rok dvojmiestne, {nnn}/{nnnn} poradie
		'format_proforma'   => 'ZF{RRRR}{nnn}',
		'format_creditnote' => 'D{RRRR}{nnn}',
		'auto_proforma'     => 1,         // zálohová faktúra pri platbe prevodom (objednávka Čaká na platbu)
		'auto_creditnote'   => 0,         // dobropis pri vrátení peňazí vo WooCommerce
		'transfer_methods'  => 'bacs',    // kódy platieb prevodom na účet (oddelené čiarkou)
		'send_proforma'     => 1,         // poslať zálohovú faktúru zákazníkovi hneď po vystavení
		'send_issued'       => 1,         // poslať faktúru zákazníkovi hneď po vystavení
		'send_creditnote'   => 0,         // poslať dobropis zákazníkovi hneď po vystavení
		'email_text'        => '',        // doplnkový text e-mailu s dokladom
		'account_docs'      => 1,         // doklady na stiahnutie v Môj účet
		'due_days'     => 14,
		'auto'         => 1,             // vytvárať faktúry z objednávok automaticky
		'cod_item'      => 1,            // pri dobierke vždy uviesť položku dobierky
		'cod_item_name' => 'Dobierka',
		'company_fields'   => 1,         // pokladňa: Nakupujem na firmu + IČO, DIČ, IČ DPH
		'company_required' => 0,         // pri nákupe na firmu sú IČO a názov firmy povinné
		'issued_by'    => '',
		'footer'       => 'Ďakujeme za nákup.',
	);
}

function jinv_settings() {
	return wp_parse_args( (array) get_option( 'jinv_settings', array() ), jinv_defaults() );
}

function jinv_opt( $k ) {
	$s = jinv_settings();
	return isset( $s[ $k ] ) ? $s[ $k ] : null;
}

/** Záložky stránky Nastavenia: kľúč => [názov, skupiny polí]. Záložka „import“ má vlastný obsah (import.php). */
function jinv_settings_sections() {
	return array(
		'dodavatel' => array( 'Dodávateľ', array( 'Dodávateľ (údaje na faktúre)', 'Platba', 'Kontakt' ) ),
		'doklady'   => array( 'Doklady a číslovanie', array( 'Číslovanie a vystavovanie' ) ),
		'emaily'    => array( 'E-maily a Môj účet', array( 'E-maily a Môj účet' ) ),
		'firma'     => array( 'Nákup na firmu', array( 'Nákup na firmu' ) ),
		'import'    => array( 'Importy údajov', array() ),
	);
}

/** Polia nastavení: kľúč => [popis, typ, nápoveda]. */
function jinv_setting_fields() {
	return array(
		'Dodávateľ (údaje na faktúre)' => array(
			'company'  => array( 'Obchodné meno', 'text', '' ),
			'street'   => array( 'Ulica a číslo', 'text', '' ),
			'zip'      => array( 'PSČ', 'text', '' ),
			'city'     => array( 'Mesto', 'text', '' ),
			'country'  => array( 'Štát', 'text', '' ),
			'ico'      => array( 'IČO', 'text', '' ),
			'dic'      => array( 'DIČ', 'text', '' ),
			'icdph'    => array( 'IČ DPH', 'text', 'Len ak ste platiteľ DPH.' ),
			'register' => array( 'Zápis v registri', 'text', 'Napr. „Zapísaná v Obchodnom registri Okresného súdu Žilina, oddiel Sro, vložka č. …“' ),
			'vat_payer' => array( 'Platiteľ DPH', 'checkbox', 'Zatiaľ sa podporujú faktúry neplatiteľa DPH.' ),
			'vat_note' => array( 'Text pre neplatiteľa DPH', 'text', '' ),
		),
		'Platba' => array(
			'iban'     => array( 'IBAN', 'text', 'Číslo účtu pre úhradu faktúr prevodom.' ),
			'swift'    => array( 'SWIFT / BIC', 'text', '' ),
			'bank'     => array( 'Banka', 'text', '' ),
			'due_days' => array( 'Splatnosť (dni)', 'number', '' ),
		),
		'Nákup na firmu' => array(
			'company_fields'   => array( 'Polia pre firmu v pokladni', 'checkbox', 'Zaškrtávacie pole „Nakupujem na firmu“ a polia IČO, DIČ, IČ DPH (aj v Môj účet → Adresy a v detaile objednávky). Ak je zapnutý doplnok Woo Faktura, polia sa nepridávajú (má vlastné).' ),
			'company_required' => array( 'Povinné pri nákupe na firmu', 'checkbox', 'Názov firmy a IČO budú povinné, keď zákazník zaškrtne „Nakupujem na firmu“.' ),
		),
		'E-maily a Môj účet' => array(
			'send_proforma'   => array( 'Poslať zálohovú faktúru', 'checkbox', 'Hneď po vystavení e-mailom s PDF (QR kód PAY by square, IBAN, VS) – zákazník môže hneď zaplatiť.' ),
			'send_issued'     => array( 'Poslať faktúru', 'checkbox', 'Hneď po vystavení e-mailom s PDF.' ),
			'send_creditnote' => array( 'Poslať dobropis', 'checkbox', 'Hneď po vystavení e-mailom s PDF.' ),
			'email_text'      => array( 'Doplnkový text e-mailu', 'text', 'Nepovinné, napr. kontakt alebo poďakovanie. Kým beží Woo Faktura, J-Fakturácia automaticky nič neposiela; ručne cez tlačidlo Poslať e-mailom áno.' ),
			'account_docs'    => array( 'Doklady v Môj účet', 'checkbox', 'Zákazník si v Môj účet → Objednávky stiahne faktúru, zálohovú faktúru a dobropis (aj prevzaté z Woo Faktura). Kým beží Woo Faktura, nezobrazuje sa.' ),
		),
		'Kontakt' => array(
			'email'    => array( 'E-mail', 'email', '' ),
			'phone'    => array( 'Telefón', 'text', '' ),
			'web'      => array( 'Web', 'url', '' ),
		),
		'Číslovanie a vystavovanie' => array(
			'numbering' => array( 'Číslovanie dokladov', 'select', 'Pri čísle objednávky má faktúra aj zálohová faktúra rovnaké číslo ako objednávka (napr. 220260244) – ako doplnok Woo Faktura; variabilný symbol je rovnaký.', array( 'order' => 'Rovnaké ako číslo objednávky', 'series' => 'Vlastný ročný rad (podľa formátov nižšie)' ) ),
			'format'    => array( 'Formát čísla faktúry', 'text', 'Pri vlastnom rade: {RRRR} = rok (2026), {RR} = rok dvojmiestne, {nnn} / {nnnn} = poradie v roku. Predvolené {RRRR}{nnn} → 2026001.' ),
			'format_proforma'   => array( 'Formát čísla zálohovej faktúry', 'text', 'Pri vlastnom rade, napr. ZF{RRRR}{nnn} → ZF2026001.' ),
			'format_creditnote' => array( 'Formát čísla dobropisu', 'text', 'Pri vlastnom rade, napr. D{RRRR}{nnn} → D2026001.' ),
			'auto'      => array( 'Vystaviť faktúru automaticky z objednávky', 'checkbox', 'Prevod na účet: keď je objednávka zaplatená. Dobierka, osobný odber a ostatné: keď je objednávka vybavená. Kým beží Woo Faktura, automatika nefunguje.' ),
			'auto_proforma'   => array( 'Zálohová faktúra pri platbe prevodom', 'checkbox', 'Vystaví sa hneď, keď objednávka s platbou prevodom prejde do stavu Čaká na platbu (Pozastavené).' ),
			'auto_creditnote' => array( 'Dobropis pri vrátení peňazí', 'checkbox', 'Pri vrátení peňazí (Refund) vo WooCommerce sa k faktúre vystaví dobropis na vrátenú sumu.' ),
			'transfer_methods' => array( 'Platby prevodom na účet', 'text', 'Kódy spôsobov platby oddelené čiarkou (predvolene bacs = Priamy bankový prevod).' ),
			'cod_item'      => array( 'Položka dobierky na faktúre', 'checkbox', 'Pri platbe na dobierku sa poplatok z objednávky uvedie ako položka s týmto názvom. Ak objednávka poplatok nemá, pridá sa položka s cenou 0 €.' ),
			'cod_item_name' => array( 'Názov položky dobierky', 'text', 'Napr. „Dobierka“ alebo „Poplatok za dobierku“.' ),
			'issued_by' => array( 'Vystavil', 'text', 'Meno na faktúre (nepovinné).' ),
			'footer'    => array( 'Text v päte faktúry', 'text', '' ),
		),
	);
}

add_action( 'admin_post_jinv_save_settings', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_settings' );
	$in       = wp_unslash( $_POST );
	$sections = jinv_settings_sections();
	$section  = isset( $in['section'] ) && isset( $sections[ $in['section'] ] ) ? $in['section'] : 'dodavatel';
	$out      = jinv_settings(); // ukladajú sa len polia odoslanej záložky, ostatné ostávajú
	foreach ( jinv_setting_fields() as $gname => $group ) {
		if ( ! in_array( $gname, $sections[ $section ][1], true ) ) {
			continue;
		}
		foreach ( $group as $k => $f ) {
			if ( 'checkbox' === $f[1] ) {
				$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			} elseif ( 'select' === $f[1] ) {
				$out[ $k ] = isset( $in[ $k ], $f[3][ $in[ $k ] ] ) ? $in[ $k ] : $out[ $k ];
			} elseif ( 'number' === $f[1] ) {
				$out[ $k ] = max( 0, min( 365, (int) ( isset( $in[ $k ] ) ? $in[ $k ] : 14 ) ) );
			} elseif ( 'email' === $f[1] ) {
				$out[ $k ] = sanitize_email( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			} elseif ( 'url' === $f[1] ) {
				$out[ $k ] = esc_url_raw( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			} else {
				$out[ $k ] = sanitize_text_field( isset( $in[ $k ] ) ? $in[ $k ] : '' );
			}
		}
	}
	if ( '' === trim( (string) $out['cod_item_name'] ) ) {
		$out['cod_item_name'] = 'Dobierka';
	}
	$out['iban'] = strtoupper( preg_replace( '/\s+/', '', $out['iban'] ) );
	foreach ( array( 'format' => '{RRRR}{nnn}', 'format_proforma' => 'ZF{RRRR}{nnn}', 'format_creditnote' => 'D{RRRR}{nnn}' ) as $fk => $fd ) {
		if ( false === strpos( (string) $out[ $fk ], '{n' ) ) {
			$out[ $fk ] = $fd;
		}
	}
	$out['transfer_methods'] = implode( ',', array_filter( array_map( 'sanitize_key', explode( ',', (string) $out['transfer_methods'] ) ) ) );
	update_option( 'jinv_settings', $out, false );
	wp_safe_redirect( admin_url( 'admin.php?page=j-invoices-settings&section=' . $section . '&saved=1' ) );
	exit;
} );
