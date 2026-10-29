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
			'Automaticky: vydaná faktúra 311/604 (tovar), 311/602 (služby – virtuálne produkty e-shopu, doprava, poplatky – každé môže mať vlastný analytický účet), dobropis so zápornou sumou, prijatá faktúra podľa kategórie (napr. Hosting → 518) / 321, úhrady z banky 221/311 a 321/221, platba zálohy alebo objednávky 221/324 a pri faktúre 324/311, bankový poplatok 568/221, transakčná daň 538/221.',
			'J-Sklad (spôsob A): príjemka 132/131, predaný tovar za ukončený mesiac 504/132 v nákupných cenách, manko 549/132, prebytok 132/648.',
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
	$guides[] = array(
		'Pokladňa',
		array(
			'J-Účtovníctvo → Pokladňa: Príjem (PD) alebo Výdaj (VD) – vyberiete účel (napr. výber z banky, nákup materiálu, vrátenie peňazí od konateľa), protiúčet sa doplní sám, zadáte sumu a text.',
			'Výdaj nad zostatok pokladne sa nezaúčtuje – pokladňa nesmie mať záporný zostatok.',
			'Pokladničná kniha: príjmy, výdaje a zostatok po každom doklade, za rok alebo zvolené obdobie; tlač knihy aj jednotlivého dokladu s podpismi.',
			'Import z OBERON-u: J-Účtovníctvo → Import (OBERON) – zálohu databázy (.mdb) prevedie nástroj tools/oberon2juc.py, import po rokoch s náhľadom a kontrolou zostatkov.',
		),
		'J-Účtovníctvo',
	);
	$guides[] = array(
		'Účtovná závierka mikro účtovnej jednotky',
		array(
			'J-Účtovníctvo → Účtovná závierka: Súvaha (Úč MÚJ 1-01) a Výkaz ziskov a strát (Úč MÚJ 2-01) podľa opatrenia MF SR č. MF/15464/2013-74 – riadky a väzby na účty podľa vzoru tlačiva, sumy v celých eurách.',
			'Kedykoľvek počas roka ukáže priebežný stav k zvolenému dátumu; k 31. 12. riadnu závierku. Pri každom riadku sa dajú rozbaliť účty, z ktorých je zostavený.',
			'Stĺpec predchádzajúceho obdobia: zostatky minulého roka v J-Účtovníctve, v prvom roku otváracia súvaha (počiatočné stavy).',
			'Kontroly: aktíva = pasíva, výsledok hospodárenia vo výkaze = súvaha r. 33; upozornenia na účty, ktoré vzor výkazu nezaraďuje, a na zostatok 131/111.',
			'Mikro účtovná jednotka (§ 2 ods. 6 zákona o účtovníctve): neprekročí aspoň 2 z 3 hraníc – majetok 450 000 €, čistý obrat 900 000 €, 10 zamestnancov.',
		),
		'J-Účtovníctvo',
	);
	$guides[] = array(
		'Počiatočné stavy pokračujúcej firmy',
		array(
			'Ak firma existovala pred začiatkom účtovania v Jupiteri, počiatočné stavy sú konečné zostatky súvahových účtov z minulého obdobia – zo súvahy a obratovej predvahy od účtovníka.',
			'Sprievodca → krok 5: zadajte zostatky (aktíva na strane MD, pasíva na strane Dal), súčty sa musia rovnať. Účtuje sa proti účtu 701 – Začiatočný účet súvahový, doklad série PS.',
			'Zostatky môžete zadať aj po spustení účtovania (keď je minulý rok uzavretý). Oprava: zmeňte hodnoty a uložte – pôvodný doklad PS sa stornuje a zaúčtuje znova.',
		),
		'J-Účtovníctvo',
	);
	return $guides;
} );
