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
			'Zálohová faktúra – výzva na platbu pri platbe prevodom; vzniká automaticky, keď objednávka prejde do stavu Čaká na platbu (Pozastavené), a hneď sa pošle zákazníkovi s QR kódom. Po zaplatení sa označí ako uhradená; pri zrušení objednávky sa stornuje.',
			'Dobropis – opravný doklad k faktúre: pri vrátení peňazí (Refund) vo WooCommerce na vrátenú sumu (ak je to zapnuté), alebo ručne v boxe Doklady – na vrátenie bez dobropisu alebo na celú faktúru.',
			'Každý doklad má číslo, dátum vystavenia, dodania a splatnosti, variabilný symbol a pri položkách katalógové číslo (SKU).',
			'Pri platbe na dobierku je na faktúre položka „Dobierka“ s poplatkom z objednávky (názov sa dá zmeniť v Nastaveniach); ak objednávka poplatok nemá, položka má cenu 0 €.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Kedy vznikne faktúra',
		array(
			'Prevod na účet: zálohová faktúra hneď pri objednávke, faktúra keď je objednávka zaplatená (stav Vybavuje sa / Spracováva sa).',
			'Dobierka, osobný odber a ostatné platby: faktúra, keď je objednávka vybavená.',
			'Kedykoľvek ručne: detail objednávky → box Doklady → Vystaviť faktúru / zálohovú faktúru / dobropis.',
			'Automatiku vypneš v J-Fakturácia → Nastavenia → Vystaviť faktúru automaticky.',
			'Hromadne: v zozname objednávok WooCommerce vyber objednávky → Hromadné akcie → Vystaviť faktúry (J-Fakturácia).', 'Kým beží doplnok Woo Faktura, nič sa nevystavuje ani neposiela automaticky (režim prechodu).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Box Doklady v objednávke',
		array(
			'Vystaviť – vytvorí doklad k objednávke.',
			'Klik na doklad – detail s tlačidlami Otvoriť PDF, Stiahnuť PDF, Poslať e-mailom, Označiť ako uhradený, Stornovať.',
			'Poslať e-mailom – pošle zákazníkovi e-mail s PDF v prílohe (aj znova); dátum odoslania je v detaile dokladu.',
			'Stornovať – stornovaný doklad ostáva v evidencii, jeho číslo sa znova nepoužije.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Číslovanie a splatnosť',
		array(
			'Nastavenia → Doklady a číslovanie: číslo dokladu = číslo objednávky (napr. 220260244, ako vo Woo Faktura; ďalší dobropis k tej istej objednávke 220260244-2), alebo vlastný ročný rad bez medzier.',
			'Vlastný rad: faktúra {RRRR}{nnn} → 2026001, zálohová ZF{RRRR}{nnn} → ZF2026001, dobropis D{RRRR}{nnn} → D2026001 (formáty sa dajú zmeniť).',
			'Splatnosť v dňoch nastavíš v Nastaveniach (napr. 7 alebo 14 dní).',
			'Variabilný symbol = číslo dokladu (len číslice).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Faktúra pre zákazníka',
		array(
			'PDF dokladu s QR kódom PAY by square (na neuhradenej faktúre a zálohovej faktúre) – zákazník zaplatí naskenovaním v bankovej aplikácii.',
			'E-mail s PDF v prílohe: automaticky podľa Nastavenia → E-maily a Môj účet (predvolene zálohová faktúra a faktúra áno, dobropis nie), alebo tlačidlom Poslať e-mailom.',
			'Zákazník si doklady stiahne sám v Môj účet → Objednávky a v detaile objednávky (aj prevzaté z Woo Faktura) – len k vlastnej objednávke.',
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
			'V pokladni zaškrtávacie pole „Nakupujem na firmu“ – po zaškrtnutí sa zobrazia polia Firma, IČO, DIČ a IČ DPH (zapína sa v Nastaveniach, povinnosť IČO tiež).',
			'Tie isté polia sú v Môj účet → Adresy, v detaile objednávky a v profile zákazníka; IČO/DIČ sa zobrazia aj v adrese v e-mailoch.',
			'Kým beží doplnok Woo Faktura, J-Fakturácia vlastné polia nepridáva – faktúra však načíta údaje aj z polí Woo Faktury.',
			'Údaje firmy sa prenesú na faktúru odberateľa.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Export pre účtovníctvo',
		array(
			'J-Fakturácia → Export: vyber obdobie a druhy dokladov a stiahni XML na import do účtovníctva Pohoda.',
			'Ďalej tabuľka CSV (otvorí sa v Exceli) a ZIP so všetkými PDF za obdobie.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Prechod z doplnku Woo Faktura',
		array(
			'J-Fakturácia → Nastavenia → záložka Importy údajov: sprievodca v 4 krokoch (zistenie, prevzatie IČO/DIČ/splatnosti, skúšobný beh bez zápisu, import).',
			'Prevezmú sa faktúry, zálohové faktúry a dobropisy s pôvodnými číslami a dátumami; pri každom je tlačidlo Pôvodné PDF (Woo Faktura). Vo Woo Faktura ani v objednávkach sa nič nemení.',
			'Import sa dá spustiť opakovane – už prevzaté doklady sa preskočia, doplnia sa len nové. Spusti ho ešte raz tesne pred vypnutím Woo Faktury.',
			'Nové faktúry J-Fakturácie majú vlastný číselný rad (prevzaté čísla ho neovplyvnia). Zálohové faktúry a dobropisy nájdeš vo Vydaných faktúrach vo výbere druhu dokladu.',
			'Až potom sa Woo Faktura vypne – nedostáva aktualizácie (robená pre WooCommerce 4.9) a odkaz na faktúru v Môj účet zákazníkom nefunguje.',
		),
		'J-Fakturácia',
	);
	return $guides;
} );
