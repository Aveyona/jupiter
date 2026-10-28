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
			'Stĺpce: číslo (ikona bubliny = poznámka zákazníka), dátum, stav, zákazník, platba (aj dátum zaplatenia), doprava, suma a faktúra z J-Fakturácie.',
			'Hromadná zmena stavu: označ objednávky, vyber nový stav a Použiť. Do objednávky sa zapíše poznámka; zákazníkom odídu e-maily podľa nastavení WooCommerce (napr. Vybavená).',
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
