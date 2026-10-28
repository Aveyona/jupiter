<?php
/** Návody J-Skladu v J-Pomocník (záložka Návody). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Ako funguje J-Sklad',
		array(
			'Zásoba ostáva v produktoch WooCommerce (Zásoby → Spravovať zásoby, Množstvo na sklade). J-Sklad k nej pridáva doklady a úplnú históriu pohybov.',
			'Pri zapnutí sa súčasné zásoby zapíšu ako otváracia príjemka (počiatočný stav) – zásoby sa nemenia.',
			'Každá zmena zásoby sa zapíše do Pohybov: predaj v objednávke, vrátenie na sklad (zrušená objednávka), príjemka, výdajka, ručná zmena v produkte aj zmena iným doplnkom.',
			'Eviduje len produkty a varianty so zapnutým sledovaním zásob.',
		),
		'J-Sklad',
	);
	$guides[] = array(
		'Príjemka a výdajka',
		array(
			'J-Sklad → Doklady → Nová príjemka: dátum, dodávateľ, číslo jeho dokladu; produkty pridáš vyhľadaním podľa názvu alebo SKU (čítačka čiarových kódov: naskenuj a Enter).',
			'Nákupná cena je nepovinná; ak ju zadáš, prepočíta sa priemerná nákupná cena produktu (vážený priemer) a hodnota skladu.',
			'Uložiť koncept – zásoby sa nemenia, doklad môžeš doplniť. Uložiť a zaúčtovať – doklad dostane číslo (PR2026001) a zásoby sa zmenia.',
			'Výdajka (VY2026001) – poškodenie, vlastná spotreba, darček…; nedovolí ísť pod nulu (okrem produktov s predajom do mínusu).',
			'Chyba v zaúčtovanom doklade: Stornovať – zásoby sa vrátia, doklad ostane v evidencii.',
		),
		'J-Sklad',
	);
	$guides[] = array(
		'Prehľad skladu',
		array(
			'J-Sklad → Prehľad: zásoba, priemerná nákupná cena a hodnota každého produktu; súčet hodnoty skladu.',
			'Filtre: dochádza (pod hranicou z Nastavení), vypredané, bez nákupnej ceny; vyhľadávanie a kategória.',
			'V detaile produktu je box Sklad (J-Sklad) s posledným pohybmi a odkazom na celú históriu.',
			'Pripravuje sa: inventúra, PDF dokladov, import príjemky z CSV, dodávatelia, prepojenie s J-Fakturáciou (prijaté faktúry).',
		),
		'J-Sklad',
	);
	return $guides;
} );
