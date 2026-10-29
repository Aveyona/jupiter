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
		'Postup fakturácie – manuálny režim',
		array(
			'Tak, ako to robí väčšina malých e-shopov: ostrú faktúru vystavíš sám pri vybavení objednávky – v deň, keď ju dáš do stavu vybavená (napr. „Vybavené a odoslané“), prípadne o deň skôr.',
			'Prevod na účet: objednávka má dve faktúry – zálohovú faktúru (proformu) z dňa objednávky, ktorá vznikne automaticky a odíde zákazníkovi s QR kódom, a ostrú faktúru z dňa vybavenia, ktorú vystavíš tlačidlom.',
			'Dobierka: kým objednávka čaká na spracovanie, faktúru nemá. Ostrá faktúra vznikne až pri vybavení a ostáva neuhradená, kým zákazník zaplatí pri prevzatí.',
			'Číslo faktúry: podľa nastavenia číslovania – rovnaké ako číslo objednávky, alebo vlastný rad (napr. 2026001). Pri prechode z Woo Faktury nastav „Pokračovať od čísla“ podľa jej počítadla. Variabilný symbol je vždy číslo objednávky.',
			'Dátum vystavenia je deň, keď klikneš na Vystaviť faktúru. Splatnosť sa počíta od neho podľa nastaveného počtu dní. Dátum dodania je deň vybavenia (alebo zaplatenia).',
			'Pregenerovať: keď sa objednávka po vystavení zmení (položky, adresa, doprava), v boxe Doklady alebo v detaile faktúry klikni Pregenerovať – faktúra sa vytvorí znova z aktuálnej objednávky. Podľa nastavenia dostane nové číslo (pôvodná sa stornuje a ostane v evidencii) alebo si ponechá pôvodné číslo; dátum vystavenia je dnešný alebo pôvodný. E-mail zákazníkovi sa pri pregenerovaní nepošle.',
			'Kde vystaviť: detail objednávky → box Doklady → Vystaviť faktúru; hromadne v zozname objednávok WooCommerce → Hromadné akcie → Vystaviť faktúry (J-Fakturácia).',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Nastavenie postupu fakturácie',
		array(
			'J-Fakturácia → Nastavenia → Doklady a číslovanie → Postup fakturácie.',
			'Režim fakturácie: Manuálny – ostrú faktúru vystavuješ tlačidlom; Automatický – faktúra vznikne sama.',
			'Stav „vybavená“: ktorý stav objednávky znamená vybavenie (napr. Vybavené a odoslané). Pri ňom vzniká faktúra v automatickom režime a faktúra sa označí ako uhradená (okrem dobierky).',
			'Ostrá faktúra pri prevode na účet (automatický režim): pri vybavení objednávky (ako manuálny postup) alebo hneď po zaplatení.',
			'Pregenerovanie – číslo: nové číslo (pôvodná faktúra sa stornuje) alebo ponechať pôvodné. Pregenerovanie – dátum vystavenia: dnešný alebo pôvodný.',
			'Zálohová faktúra pri platbe prevodom a Dobropis pri vrátení peňazí sa zapínajú samostatne (fungujú v oboch režimoch).',
			'Kým beží doplnok Woo Faktura, nič sa nevystavuje ani neposiela automaticky (režim prechodu).',
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
	$guides[] = array(
		'Dodávatelia (adresár)',
		array(
			'J-Fakturácia → Dodávatelia: adresár firiem, od ktorých nakupuješ – IČO, DIČ, IČ DPH, adresa, kontakt, IBAN, splatnosť ich faktúr a predvolená kategória.',
			'Nový dodávateľ: zadaj IČO a klikni Načítať z registra – názov a adresa sa doplnia z Registra právnických osôb. DIČ a IBAN doplň z faktúry.',
			'Detail dodávateľa: neuhradené a po splatnosti, nákupy za rok, všetky jeho prijaté faktúry a príjemky na sklad. Tlačidlá + Prijatá faktúra a + Príjemka na sklad ho rovno predvyplnia.',
			'Doterajší dodávatelia z prijatých faktúr sa do adresára preniesli automaticky. Nepoužívaného dodávateľa archivuj (Upraviť → Archivovaný) – doklady ostanú.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Prijaté faktúry a úhrady',
		array(
			'Prijaté faktúry → + Pridať: začni písať názov alebo IČO dodávateľa a vyber ho – doplní sa IČO, DIČ, IBAN, kategória a splatnosť. Nového dodávateľa faktúra uloží do adresára.',
			'Priloženie PDF alebo fotky faktúry, variabilný symbol sa navrhne z čísla faktúry.',
			'Úhrady: v detaile faktúry Pridať úhradu (dátum, suma, spôsob) – môže ich byť viac (čiastočné platby). Keď súčet dosiahne sumu faktúry, faktúra je Uhradená. Rýchlo: v zozname odkaz Uhradená zapíše úhradu zvyšnej sumy s dnešným dátumom.',
			'Filtre Neuhradené, Po splatnosti, Uhradené; karta Splatné do 7 dní ukáže, čo treba čoskoro zaplatiť.',
			'Tovar na sklad: pri novej faktúre zaškrtni Vytvoriť príjemku, alebo v detaile faktúry Vytvoriť príjemku na sklad – v J-Sklade sa otvorí príjemka s dodávateľom a číslom faktúry, doplníš tovar a nákupné ceny. Opačne: v príjemke tlačidlo + Prijatá faktúra k príjemke.',
			'Úhrady majú aj vydané faktúry (detail faktúry). Pripravuje sa import bankového výpisu s automatickým párovaním podľa variabilného symbolu a pokladňa.',
		),
		'J-Fakturácia',
	);
	$guides[] = array(
		'Banka – Fio a párovanie úhrad',
		array(
			'J-Fakturácia → Banka → Účty: pripojenie účtu vo Fio banke. V internetbankingu Fio vytvor token (Nastavenia → API → Pridať token, druh Sledovanie účtu – len čítanie, platí 180 dní), vlož ho do poľa Token a klikni Pripojiť a načítať pohyby.',
			'Pohyby sa načítavajú automaticky každých 30 minút (alebo tlačidlom Načítať pohyby teraz; Fio dovoľuje jedno načítanie za 30 sekúnd). Každý pohyb sa uloží len raz.',
			'Párovanie: príjem s variabilným symbolom vydanej alebo zálohovej faktúry sa k nej zapíše ako úhrada; výdaj s VS prijatej faktúry (alebo s IBAN dodávateľa a presnou sumou) uhradí prijatú faktúru. Platba s VS = číslo objednávky bez faktúry sa zapíše do objednávky ako poznámka.',
			'Voľba Objednávky: keď príde platba za objednávku presnou sumou, objednávka sa označí ako zaplatená (Spracováva sa) – v automatickom režime sa potom vystaví faktúra.',
			'Na spárovanie: pohyby, ktoré sa nepodarilo priradiť. Klikni na navrhnutý doklad, alebo zadaj číslo faktúry / objednávky a Spárovať. Poplatky a prevody medzi účtami označ Ignorovať. Spárovanie sa dá zrušiť (úhrada z dokladu zmizne).',
			'Fio vydá cez API pohyby za posledných 90 dní; staršie len keď v internetbankingu (Nastavenia → API) odblokuješ históriu – platí 10 minút. Nový token začne fungovať asi 5 minút po autorizácii.',
			'Bankové poplatky, transakčná daň a úroky sa automaticky označia ako ignorované. Tlačidlo Skúsiť znova spárovať automaticky prejde nespárované pohyby po pridaní faktúr.',
			'14 dní pred vypršaním tokenu sa pri účte zobrazí upozornenie – vytvor nový token a vlož ho do účtu.',
		),
		'J-Fakturácia',
	);
	return $guides;
} );
