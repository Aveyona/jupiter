<?php
/** Návody J-Účtovníctva v J-Pomocník (záložka Návody). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'jupiter_guides', function ( $guides ) {
	$guides[] = array(
		'Založenie účtovníctva (sprievodca)',
		array(
			'J-Účtovníctvo → Založiť účtovníctvo: 9 krokov – firma (načítanie z registra podľa IČO), veľkosť a DPH, začiatok účtovania, účtový rozvrh, počiatočné stavy, napojenie J-Fakturácie, banky a J-Skladu, predkontácie, kontrola a spustenie.',
			'Každý krok sa uloží – k sprievodcovi sa dá vrátiť a pokračovať neskôr.',
			'Nová firma: zadáte základné imanie (353/411) a jeho splatenie (221/353). Pokračujúca firma: konečné zostatky súvahových účtov z minulého obdobia proti účtu 701 – súčty strán sa musia rovnať.',
			'Po spustení sa doklady od dátumu začiatku účtujú automaticky. Staršie doklady doplníte posunutím dátumu v Nastaveniach a tlačidlom Zaúčtovať nové doklady teraz.',
		),
		'J-Účtovníctvo',
	);
	$guides[] = array(
		'Ako J-Účtovníctvo účtuje',
		array(
			'Každý zápis má účet MD a účet Dal – kniha je vždy vyrovnaná. Zaúčtovaný doklad sa nemení; oprava = Stornovať (vznikne doklad so zápornými sumami) a nový doklad.',
			'Automaticky: vydaná faktúra 311/604 (tovar) a 311/602 (doprava, poplatky), dobropis so zápornou sumou, prijatá faktúra podľa kategórie (napr. Hosting → 518) / 321, úhrady z banky 221/311 a 321/221, platba zálohy alebo objednávky 221/324 a pri faktúre 324/311, bankový poplatok 568/221, transakčná daň 538/221.',
			'J-Sklad (spôsob A): príjemka 132/131, predaný tovar za ukončený mesiac 504/132 v nákupných cenách, manko 549/132, prebytok 132/504.',
			'Keď sa zdrojový doklad zmení (pregenerovaná faktúra, spárovanie platby, storno), pôvodný zápis sa stornuje a zaúčtuje sa nový. Opakované účtovanie nič nezdvojí.',
			'Na zaúčtovanie: pohyby v banke bez faktúry (nákup kartou, vklad, výplata dobierok…) – vyberiete predkontáciu alebo účet a kliknete Zaúčtovať.',
		),
		'J-Účtovníctvo',
	);
	$guides[] = array(
		'Účtovné knihy a zostavy',
		array(
			'Účtovné doklady = účtovný denník (chronologicky, tlač). Hlavná kniha: obraty a zostatky syntetických účtov; klik na účet = karta účtu so zápismi a priebežným zostatkom (tlač, CSV).',
			'Obratová predvaha: počiatočné stavy, obraty a konečné zostatky po triedach; kontrola rovnosti strán MD a Dal. Voliteľne za časť roka a s analytickými účtami.',
			'Saldokonto: otvorené položky odberateľov (311), dodávateľov (321), preddavkov a spoločníkov podľa partnera a variabilného symbolu.',
			'Účtový rozvrh: syntetické účty z rámcovej účtovej osnovy a vlastné analytické účty (napr. 518001 Hosting). Daňový / nedaňový náklad sa nastavuje pri účte.',
			'Uzávierka mesiaca: Nastavenia → Účtovníctvo uzavreté do – do tohto dňa sa už nedá účtovať ani stornovať.',
		),
		'J-Účtovníctvo',
	);
	return $guides;
} );
