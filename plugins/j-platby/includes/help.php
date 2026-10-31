<?php
/** Návody J-Platieb v J-Pomocník (záložka Návody). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Platby kartou – nastavenie Stripe',
		array(
			'Stripe → Developers → API keys: skopírujte Publishable key (pk_…) a Secret key (sk_…). J-Platby → Nastavenia: vložte ich do testovacích alebo ostrých kľúčov a kliknite Uložiť a overiť spojenie.',
			'Stripe → Developers → Webhooks → Add endpoint: adresa a udalosti sú uvedené v J-Platby → Nastavenia. Po vytvorení skopírujte Signing secret (whsec_…) do poľa Tajomstvo webhooku.',
			'WooCommerce → Nastavenia → Platby: Platba kartou (Stripe) musí byť zapnutá; tam sa dá zmeniť aj názov a popis v pokladni.',
			'Najprv skúšajte v testovacom režime kartou 4242 4242 4242 4242. Ostrý režim zapnite až po aktivácii účtu v Stripe a zadaní ostrých kľúčov a ostrého webhooku.',
		),
		'J-Platby',
	);
	$guides[] = array(
		'Platby kartou – objednávky, vrátenie, účtovníctvo',
		array(
			'Zákazník zvolí Platba kartou, potvrdí objednávku a zaplatí na zabezpečenej stránke Stripe (karta, Apple Pay, Google Pay). Po zaplatení sa objednávka sama nastaví na Spracováva sa a príde potvrdenie e-mailom.',
			'Nedokončená platba: objednávka čaká na platbu; zákazník môže zaplatiť znova cez odkaz v Mojom účte. Keď platobná stránka vyprší (24 h), objednávka sa zruší.',
			'Vrátenie peňazí: detail objednávky → Vrátiť peniaze → suma → Vrátiť cez Platba kartou. Peniaze sa vrátia na kartu zákazníka. Vrátenie urobené priamo v Stripe sa do objednávky zapíše samo.',
			'J-Platby → Platby: prehľad platieb za mesiac so sumou, poplatkom Stripe a čistou sumou (po vrátení), Export CSV pre účtovníka. Stripe posiela peniaze na bankový účet automaticky (výplaty).',
			'Odkaz na platbu kartou: detail nezaplatenej objednávky (aj prevod alebo objednávka z administrácie) → box Platba kartou → Vytvoriť odkaz na platbu → skopírujte a pošlite zákazníkovi. Platí 24 hodín; po zaplatení sa objednávka nastaví na Spracováva sa.',
			'Stav webhooku je v J-Platby → Nastavenia a na nástenke Jupiter; pri odmietnutej udalosti sa v administrácii ukáže upozornenie.',
			'Účtovanie: úhrada faktúry kartou 261 / 311 (zapíše sa k faktúre v J-Fakturácii), poplatok Stripe 568 / 261, výplata zo Stripe na účet 221 / 261 – pre pohyb v banke od Stripe nastavte v J-Účtovníctve pravidlo na účet 261.',
		),
		'J-Platby',
	);
	return $guides;
} );
