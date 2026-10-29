<?php
/** Návody J-Objednávky v J-Pomocník (záložka Návody). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Zoznam objednávok',
		array(
			'J-Objednávky → Objednávky: hore stavy s počtom objednávok (Spracováva sa, Pozastavené, Vybavené…), klik zobrazí len daný stav.',
			'Vyhľadávanie podľa čísla objednávky (aj vlastného, napr. 220260244), mena, e-mailu alebo telefónu; filter spôsobu platby a obdobia od – do.',
			'Stĺpce: číslo (ikona bubliny = poznámka zákazníka), dátum, stav, zákazník, platba (aj dátum zaplatenia), doprava, suma a Doklady.',
			'Doklady: ZF = zálohová faktúra (proforma), F = faktúra, D = dobropis – s číslom, stavom (vystavená, uhradená, po splatnosti) a odkazom na PDF. Faktúra z Woo Faktury, ktorá ešte nie je prevzatá, je sivá.',
			'Pri objednávke bez faktúry je tlačidlo Vystaviť faktúru; pri objednávke s faktúrou Pregenerovať (faktúra sa vytvorí znova z aktuálnej objednávky podľa nastavení J-Fakturácie). Kým beží Woo Faktura, tlačidlá sa nezobrazujú.',
			'Na fakturáciu (pri stavoch): vybavené objednávky za posledných 90 dní, ktoré ešte nemajú faktúru – nič sa tak neprehliadne. Ktorý stav je „vybavená“, sa nastavuje v J-Fakturácia → Nastavenia → Postup fakturácie.',
			'Hromadná akcia: zmena stavu, Vybaviť a vystaviť faktúry (zmení stav na vybavená a hneď vystaví faktúry) alebo Len vystaviť faktúry. Faktúry sa hromadne vystavia bez e-mailu zákazníkom; v hlásení je počet vystavených faktúr. Zmena stavu zapíše poznámku a zákazníkom odídu e-maily podľa nastavení WooCommerce.',
			'Klik na číslo otvorí bežný detail objednávky WooCommerce; pôvodný zoznam WooCommerce ostáva dostupný.',
		),
		'J-Objednávky',
	);
	$guides[] = array(
		'Čísla objednávok',
		array(
			'J-Objednávky → Číslovanie: prefix + poradové číslo s pevným počtom číslic (napr. 22026 + 0244 = 220260244), voliteľne prípona.',
			'Pri aktivácii sa prevezme nastavenie, počítadlo aj história z doplnku My Order Numbers (alebo z pôvodného „Woo Custom and Sequential Order Number“); existujúce objednávky si čísla ponechajú.',
			'Kým je My Order Numbers zapnutý, čísla prideľuje on a J-Objednávky ich len zobrazuje. Po jeho vypnutí prevezme číslovanie J-Objednávky a nadviaže na jeho posledné číslo – My Order Numbers potom môžeš zmazať.',
			'Nový rok: zmeň prefix (napr. 22027) a posledné číslo na 0 → ďalšia objednávka 220270001. Číslo, ktoré už existuje, sa preskočí; zmeny sa zapisujú do histórie.',
			'Sledovanie objednávky a vyhľadávanie vo WooCommerce fungujú aj podľa vlastného čísla.',
		),
		'J-Objednávky',
	);
	return $guides;
} );
