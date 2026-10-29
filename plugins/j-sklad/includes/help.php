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
			'Každá zmena zásoby sa zapíše do Pohybov: predaj v objednávke, vrátenie na sklad (zrušená objednávka, refundácia), pridanie či odobratie položky v úprave objednávky – vždy s číslom objednávky –, príjemka, výdajka, ručná zmena v produkte aj zmena iným doplnkom.',
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
		'Obrázky produktov',
		array(
			'J-Sklad → Nastavenia → Obrázky produktov spravuje J-Sklad (predvolene vypnuté).',
			'Po zapnutí pribudne J-Sklad → Obrázky: hlavný obrázok a galéria každého produktu, varianty pod ním. Zmeniť / Pridať vyberie alebo nahrá obrázok, + Galéria pridá viac obrázkov naraz, × obrázok z produktu odoberie (súbor ostane).',
			'Knižnica multimédií potom obrázky produktov nezobrazuje – ostanú v nej len obrázky webu (články, logo…). Okno výberu obrázka v úprave produktu ukazuje všetky obrázky ako doteraz.',
			'Súbory sa nikam nepresúvajú; po vypnutí voľby je Knižnica opäť kompletná.',
		),
		'J-Sklad',
	);
	$guides[] = array(
		'Prehľad skladu',
		array(
			'J-Sklad → Prehľad: zásoba, priemerná nákupná cena a hodnota každého produktu; súčet hodnoty skladu.',
			'Filtre: dochádza (pod hranicou z Nastavení), vypredané, bez nákupnej ceny; vyhľadávanie a kategória.',
			'V detaile produktu je box Sklad (J-Sklad) s posledným pohybmi a odkazom na celú históriu.',
			'Pripravuje sa: PDF dokladov, import príjemky z CSV, dodávatelia, prepojenie s J-Fakturáciou (prijaté faktúry), návrh objednávky tovaru.',
		),
		'J-Sklad',
	);
	$guides[] = array(
		'Inventúra',
		array(
			'J-Sklad → Inventúra → Nová inventúra: všetky produkty so sledovaním zásob alebo jedna kategória. Zapamätá sa stav v evidencii, zásoby sa zatiaľ nemenia.',
			'Tlačiť súpis – papierový zoznam na počítanie (stĺpec Spočítané na zapísanie).',
			'Spočítané množstvá zadávaš do stĺpca Spočítané; ukladajú sa priebežne, inventúru môžeš zavrieť a dokončiť neskôr. Rozdiel a jeho hodnota v nákupných cenách sa ukazujú hneď (červená manko, zelená prebytok).',
			'Čítačka čiarových kódov: naskenuj kód do poľa hore – pri zapnutej voľbe „sken pripočíta 1 ks“ sa produkt pripočíta, inak sa nastaví kurzor do jeho riadku. Napísaním časti názvu alebo SKU a Enter produkt nájdeš.',
			'Filtre Nespočítané a Rozdiely ukážu, čo ešte chýba a čo nesedí. Nespočítané potvrdiť podľa evidencie – zvyšok dostane spočítané = stav v evidencii.',
			'Zaúčtovať inventúru – doklad dostane číslo (IN2026001) a zásoba každej spočítanej položky sa nastaví na spočítané množstvo; rozdiel sa zapíše do Pohybov. Predaje počas inventúry sa nezapočítajú dvakrát – rozdiel sa počíta voči zásobe v okamihu zaúčtovania („teraz X“ pri riadku upozorní na zmenu).',
			'Nespočítané položky ostanú bez zmeny; voľba „nespočítané nastaviť na 0 ks“ ich vynuluje.',
			'Zaúčtovaná inventúra má protokol na tlač (manko, prebytok, hodnoty, podpis). Chyba: Stornovať – rozdiely sa vrátia späť.',
		),
		'J-Sklad',
	);
	return $guides;
} );
