<?php
/** Návody J-Dopravy v J-Pomocník (záložka Návody). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Packeta – nastavenie',
		array(
			'Klientska zóna Packeta (client.packeta.com): skopírujte API heslo a označenie odosielateľa. J-Doprava → Nastavenia: vložte ich a kliknite Uložiť a overiť spojenie.',
			'WooCommerce → Nastavenia → Doprava → zóna Slovensko: pridajte Packeta – výdajné miesto / Z-BOX a Packeta – doručenie na adresu, nastavte cenu a dopravu zadarmo od sumy.',
			'Produkty potrebujú hmotnosť (Doprava → Hmotnosť); k nej sa pripočíta obal z nastavení.',
		),
		'J-Doprava',
	);
	$guides[] = array(
		'Packeta – objednávky a zásielky',
		array(
			'Zákazník v košíku pri doprave Packeta – výdajné miesto klikne Vybrať výdajné miesto a vyberie miesto alebo Z-BOX na mape. Bez vybraného miesta sa objednávka neodošle; pri doručení na adresu je povinný telefón.',
			'Podanie zásielky: detail objednávky → box Packeta → Podať zásielku (hmotnosť a dobierku môžete upraviť), alebo hromadne J-Doprava → Zásielky → Podať označené, alebo v zozname objednávok Hromadné akcie → Packeta – podať zásielky.',
			'Štítky: Štítok PDF v detaile objednávky, alebo hromadne Štítky PDF (A6 na A4 po 4 kusoch, A6 pre tlačiareň štítkov). Zásielku odneste na ľubovoľné výdajné miesto Packety.',
			'Stav zásielky sa kontroluje každú hodinu; zákazník dostane odkaz na sledovanie v e-maile a v Mojom účte. Doručená objednávka sa nastaví na Vybavená. Dobierka sa platí pri prevzatí a Packeta ju pošle na účet.',
			'Kým zásielka nie je odovzdaná Packete, dá sa zrušiť (box Packeta → Zrušiť zásielku) a podať znova.',
		),
		'J-Doprava',
	);
	return $guides;
} );
