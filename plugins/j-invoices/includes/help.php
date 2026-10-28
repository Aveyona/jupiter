<?php
/** Návody J-Fakturácia v J-Pomocník (záložka Návody). Funkcie, ktoré ešte nie sú hotové, sú označené „(pripravuje sa)“. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Druhy dokladov',
		array(
			'Faktúra – daňový doklad k objednávke; vystavuje sa automaticky alebo tlačidlom v objednávke.',
			'Zálohová faktúra – výzva na platbu pri platbe prevodom; vzniká automaticky, keď objednávka prejde do stavu Pozastavené, a hneď sa pošle zákazníkovi (pripravuje sa).',
			'Dobropis – opravný doklad k faktúre pri vrátení tovaru alebo zľave (pripravuje sa).',
			'Každý doklad má číslo, dátum vystavenia, dodania a splatnosti, variabilný symbol a pri položkách katalógové číslo (SKU).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Kedy vznikne faktúra',
		array(
			'Prevod na účet: keď je objednávka zaplatená (stav Spracováva sa).',
			'Dobierka a osobný odber: keď je objednávka vybavená.',
			'Kedykoľvek ručne: detail objednávky → box Faktúra → Vystaviť faktúru teraz.',
			'Automatiku vypneš v J-Fakturácia → Nastavenia → Vystaviť faktúru automaticky.',
			'Hromadne: v zozname objednávok vyber objednávky → Hromadné akcie → Vystaviť faktúry (pripravuje sa).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Box Faktúry v objednávke',
		array(
			'Vystaviť – vytvorí doklad k objednávke.',
			'Zobraziť – otvorí doklad (náhľad, tlač / PDF).',
			'Znova vystaviť – prepočíta doklad podľa aktuálnej objednávky s rovnakým číslom (pripravuje sa).',
			'Poslať e-mailom – pošle zákazníkovi e-mail s PDF v prílohe (pripravuje sa).',
			'Zmazať / Stornovať – stornovaná faktúra ostáva v evidencii, jej číslo sa znova nepoužije.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Číslovanie a splatnosť',
		array(
			'Predvolene vlastný ročný rad bez medzier: {RRRR}{nnn} → 2026001, 2026002…',
			'Možnosť: číslo dokladu = číslo objednávky (ako vo Woo Faktura) (pripravuje sa).',
			'Splatnosť v dňoch nastavíš v Nastaveniach (napr. 7 alebo 14 dní).',
			'Variabilný symbol = číslo dokladu (len číslice).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Faktúra pre zákazníka',
		array(
			'PDF dokladu s QR kódom PAY by square – zákazník zaplatí naskenovaním v bankovej aplikácii (pripravuje sa).',
			'E-mail „Doklad k objednávke … z {obchod}“ s PDF v prílohe; faktúra po potvrdení, zálohová automaticky (pripravuje sa).',
			'Zákazník si doklad stiahne sám v Môj účet → Objednávky – zabezpečeným odkazom, nie cez administráciu (pripravuje sa).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Prijaté faktúry',
		array(
			'J-Fakturácia → Prijaté faktúry → Pridať prijatú faktúru.',
			'Vyplň dodávateľa, číslo faktúry a VS, dátum vystavenia, dodania a splatnosti, sumu a kategóriu (Tovar, Doprava, Služby…).',
			'Prilož PDF alebo fotku faktúry – uloží sa do chráneného priečinka, otvorí ju len správca obchodu.',
			'Po zaplatení klikni Uhradená. Hore vidíš počet a súčet neuhradených faktúr a koľko je po splatnosti.',
			'Vyčítanie údajov z PDF pomocou umelej inteligencie (pripravuje sa).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Nákup na firmu',
		array(
			'V pokladni pole Nakupujem ako firma a nepovinné polia IČO, DIČ a IČ DPH (pripravuje sa).',
			'Údaje firmy sa prenesú na faktúru odberateľa.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Export pre účtovníctvo',
		array(
			'J-Fakturácia → Export: vyber obdobie a stiahni XML na import do účtovníctva Pohoda (pripravuje sa).',
			'Export pre účtovníka: tabuľka (XLSX/CSV) a ZIP so všetkými PDF za obdobie (pripravuje sa).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Prechod z doplnku Woo Faktura',
		array(
			'J-Fakturácia načíta existujúce faktúry, zálohové faktúry a dobropisy z Woo Faktura aj s PDF (pripravuje sa).',
			'Číslovanie pokračuje bez medzier, staré doklady ostanú dostupné zákazníkom aj v exporte.',
			'Až potom sa Woo Faktura vypne – nedostáva aktualizácie (robená pre WooCommerce 4.9) a odkaz na faktúru v Môj účet zákazníkom nefunguje.',
		),
		'J-Fakturácia',
	);
	return $guides;
} );
