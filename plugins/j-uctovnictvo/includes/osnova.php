<?php
/**
 * Rámcová účtová osnova pre podnikateľov (podvojné účtovníctvo) – syntetické účty triedy 0 až 7.
 *
 * Zdroj (právny stav platný pre účtovné obdobia začínajúce 1. 1. 2026):
 *   Príloha č. 1 k opatreniu MF SR zo 16. decembra 2002 č. 23054/2002-92 (oznámenie č. 740/2002 Z. z.)
 *   v znení neskorších predpisov, naposledy opatrenia z 10. decembra 2025 č. MF/013819/2025-74
 *   (27/2025 R. o., účinnosť 31. 12. 2025 / 1. 1. 2026).
 *   Informatívne konsolidované znenie (slov-lex):
 *     https://static.slov-lex.sk/pdf/prilohy/SK/OP/2025/27/vyhlasene_znenie_5812469-2.pdf
 *     https://static.slov-lex.sk/static/SK/OP/2025/27/vyhlasene_znenie.html
 *   Znenie k 1. 1. 2025 (MF/014948/2024-74, 22/2024 R. o.) má identický zoznam účtov:
 *     https://static.skolenie.slov-lex.sk/pdf/prilohy/SK/OP/2024/22/20250101_5693948-2.pdf
 *   Kontrola: podnikajte.sk – Účtová osnova 2024 (PDF), identický zoznam:
 *     https://www.podnikajte.sk/assets/prilohy-v-clankoch/2022-11-01-uctova-osnova-2023-2024/2022-11-01-uctova-osnova-2024.pdf
 *
 * Text prílohy č. 1 v súčasnej podobe pochádza z opatrenia MF/23635/2014-74 (oznámenie č. 390/2014 Z. z.,
 * účinnosť 1. 1. 2015) – hlavička prílohy v konsolidovanom znení: "Príloha k opatreniu č. MF/23635/2014-74".
 * Novely MF/19930/2015-74, MF/14776/2017-74, MF/015328/2018-74, MF/017028/2018-74, MF/011805/2020-74,
 * MF/005391/2024-74, MF/014948/2024-74 a MF/013819/2025-74 zoznam účtov nemenili (overené porovnaním
 * konsolidovaných znení 2025 a 2026 a textov noviel 2017–2025 na slov-lex).
 *
 * Zmeny oproti zneniu účinnému od 1. 1. 2014 (static.slov-lex.sk/static/SK/ZZ/2002/740/20140101.html),
 * zavedené opatrením MF/23635/2014-74 s účinnosťou od 1. 1. 2015:
 *   ZRUŠENÉ: 582 Škody, 588 Ostatné mimoriadne náklady, 593 Splatná daň z príjmov z mimoriadnej činnosti,
 *            594 Odložená daň z príjmov z mimoriadnej činnosti, 597 Prevod nákladov na hospodársku činnosť,
 *            598 Prevod finančných nákladov, 682 Náhrady škôd, 688 Ostatné mimoriadne výnosy,
 *            697 Prevod výnosov z hospodárskej činnosti, 698 Prevod finančných výnosov
 *            (zrušené účtové skupiny 58, 68 a 69).
 *   PRIDANÝ:  547 Tvorba a zúčtovanie opravných položiek k pohľadávkam.
 *   PREMENOVANÉ: 062 (predtým "... v spoločnosti s podstatným vplyvom"), 066 ("Pôžičky účtovnej jednotke
 *            v konsolidovanom celku"), 351, 361, 471 (predtým "... v rámci konsolidovaného celku"),
 *            591 ("Splatná daň z príjmov z bežnej činnosti"), 592 ("Odložená daň z príjmov z bežnej činnosti").
 *
 * Účty, ktoré opatrenie uvádza iba ako PRÍKLAD účtu, ktorý si účtovná jednotka môže vytvoriť
 * (nie sú v rámcovej osnove, preto tu nie sú): 222 – Tokeny elektronických peňazí (§ 46 ods. 2),
 * 258 – Kryptoaktíva (§ 46 ods. 6), 399 – Zúčtovací účet (§ 30a ods. 7, od 31. 12. 2025).
 * Podsúvahové účty 75 až 79 osnova jednotlivo nevymenúva (len "75 až 79 – Podsúvahové účty");
 * účtovná jednotka si ich vytvára v účtovom rozvrhu. Triedy 8 a 9 = vnútroorganizačné účtovníctvo.
 *
 * Tretí prvok = typ účtu podľa obvyklej strany zostatku (nie podľa strany súvahy):
 *   'A'  = aktívny súvahový účet (zostatok Má dať),
 *   'P'  = pasívny súvahový účet (zostatok Dal) – vrátane korekčných účtov k aktívam
 *          (oprávky 07x/08x, opravné položky 09x/19x/291/391), ktoré sa v súvahe vykazujú na strane aktív
 *          ako odpočet,
 *   'AP' = účet, ktorý môže mať zostatok na ktorejkoľvek strane (vykazuje sa podľa charakteru zostatku;
 *          pre 316, 336, 341, 342, 343, 345, 346, 347 to výslovne uvádza § 2 ods. 4 opatrenia
 *          MF/15464/2013-74 pre mikro účtovné jednotky),
 *   'N'  = nákladový účet (trieda 5), 'V' = výnosový účet (trieda 6),
 *   'Z'  = uzávierkové účty (701, 702, 710, 711).
 * Klasifikácia A/P/AP je odvodená (autor rešerše) z obsahu opatrenia a súvahy MUJ, nie je súčasťou osnovy.
 */
return array(

	// ===== Účtová trieda 0 – Dlhodobý majetok =====
	// 01 – Dlhodobý nehmotný majetok
	array( '012', 'Aktivované náklady na vývoj', 'A' ),
	array( '013', 'Softvér', 'A' ),
	array( '014', 'Oceniteľné práva', 'A' ),
	array( '015', 'Goodwill', 'A' ),
	array( '019', 'Ostatný dlhodobý nehmotný majetok', 'A' ),
	// 02 – Dlhodobý hmotný majetok – odpisovaný
	array( '021', 'Stavby', 'A' ),
	array( '022', 'Samostatné hnuteľné veci a súbory hnuteľných vecí', 'A' ),
	array( '025', 'Pestovateľské celky trvalých porastov', 'A' ),
	array( '026', 'Základné stádo a ťažné zvieratá', 'A' ),
	array( '029', 'Ostatný dlhodobý hmotný majetok', 'A' ),
	// 03 – Dlhodobý hmotný majetok – neodpisovaný
	array( '031', 'Pozemky', 'A' ),
	array( '032', 'Umelecké diela a zbierky', 'A' ),
	// 04 – Obstaranie dlhodobého majetku
	array( '041', 'Obstaranie dlhodobého nehmotného majetku', 'A' ),
	array( '042', 'Obstaranie dlhodobého hmotného majetku', 'A' ),
	array( '043', 'Obstaranie dlhodobého finančného majetku', 'A' ),
	// 05 – Poskytnuté preddavky na dlhodobý majetok
	array( '051', 'Poskytnuté preddavky na dlhodobý nehmotný majetok', 'A' ),
	array( '052', 'Poskytnuté preddavky na dlhodobý hmotný majetok', 'A' ),
	array( '053', 'Poskytnuté preddavky na dlhodobý finančný majetok', 'A' ),
	// 06 – Dlhodobý finančný majetok
	array( '061', 'Podielové cenné papiere a podiely v dcérskej účtovnej jednotke', 'A' ),
	array( '062', 'Podielové cenné papiere a podiely v spoločnosti alebo družstve s podielovou účasťou', 'A' ),
	array( '063', 'Realizovateľné cenné papiere a podiely', 'A' ),
	array( '065', 'Dlhové cenné papiere držané do splatnosti', 'A' ),
	array( '066', 'Pôžičky prepojeným účtovným jednotkám a účtovným jednotkám v rámci podielovej účasti', 'A' ),
	array( '067', 'Ostatné pôžičky', 'A' ),
	array( '069', 'Ostatný dlhodobý finančný majetok', 'A' ),
	// 07 – Oprávky k dlhodobému nehmotnému majetku
	array( '072', 'Oprávky k aktivovaným nákladom na vývoj', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '073', 'Oprávky k softvéru', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '074', 'Oprávky k oceniteľným právam', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '075', 'Oprávky ku goodwillu', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '079', 'Oprávky k ostatnému dlhodobému nehmotnému majetku', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	// 08 – Oprávky k dlhodobému hmotnému majetku
	array( '081', 'Oprávky k stavbám', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '082', 'Oprávky k samostatným hnuteľným veciam a k súboru hnuteľných vecí', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '085', 'Oprávky k pestovateľským celkom trvalých porastov', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '086', 'Oprávky k základnému stádu a ťažným zvieratám', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '089', 'Oprávky k ostatnému dlhodobému hmotnému majetku', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	// 09 – Opravné položky k dlhodobému majetku
	array( '091', 'Opravné položky k dlhodobému nehmotnému majetku', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '092', 'Opravné položky k dlhodobému hmotnému majetku', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '093', 'Opravné položky k nedokončenému dlhodobému nehmotnému majetku', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '094', 'Opravné položky k nedokončenému dlhodobému hmotnému majetku', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '095', 'Opravné položky k poskytnutým preddavkom na dlhodobý majetok', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '096', 'Opravné položky k dlhodobému finančnému majetku', 'P' ), // korekčné účty k aktívam (zostatok Dal); v súvahe znižujú aktíva (medzi lomkami /.../ vo výkaze)
	array( '097', 'Opravné položky k nadobudnutému majetku', 'AP' ), // môže byť kladná aj záporná; súvaha MUJ r. 08: (+/- 097)
	array( '098', 'Oprávky k opravnej položke k nadobudnutému majetku', 'AP' ), // súvaha MUJ r. 08: - /+/- 098/

	// ===== Účtová trieda 1 – Zásoby =====
	// 11 – Materiál
	array( '111', 'Obstaranie materiálu', 'A' ),
	array( '112', 'Materiál na sklade', 'A' ),
	array( '119', 'Materiál na ceste', 'A' ),
	// 12 – Zásoby vlastnej výroby
	array( '121', 'Nedokončená výroba', 'A' ),
	array( '122', 'Polotovary vlastnej výroby', 'A' ),
	array( '123', 'Výrobky', 'A' ),
	array( '124', 'Zvieratá', 'A' ),
	// 13 – Tovar
	array( '131', 'Obstaranie tovaru', 'A' ),
	array( '132', 'Tovar na sklade a v predajniach', 'A' ),
	array( '133', 'Nehnuteľnosť na predaj', 'A' ),
	array( '139', 'Tovar na ceste', 'A' ),
	// 19 – Opravné položky k zásobám
	array( '191', 'Opravné položky k materiálu', 'P' ), // korekčný účet k aktívam (zostatok Dal)
	array( '192', 'Opravné položky k nedokončenej výrobe', 'P' ), // korekčný účet k aktívam (zostatok Dal)
	array( '193', 'Opravné položky k polotovarom vlastnej výroby', 'P' ), // korekčný účet k aktívam (zostatok Dal)
	array( '194', 'Opravné položky k výrobkom', 'P' ), // korekčný účet k aktívam (zostatok Dal)
	array( '195', 'Opravné položky k zvieratám', 'P' ), // korekčný účet k aktívam (zostatok Dal)
	array( '196', 'Opravné položky k tovaru', 'P' ), // korekčný účet k aktívam (zostatok Dal)

	// ===== Účtová trieda 2 – Finančné účty =====
	// 21 – Peniaze
	array( '211', 'Pokladnica', 'A' ),
	array( '213', 'Ceniny', 'A' ),
	// 22 – Účty v bankách
	array( '221', 'Bankové účty', 'A' ), // spravidla zostatok MD; pasívny zostatok (kontokorent) sa vykazuje ako krátkodobý bankový úver (§ 47 ods. 3 opatrenia)
	// 23 – Bežné bankové úvery
	array( '231', 'Krátkodobé bankové úvery', 'P' ),
	array( '232', 'Eskontné úvery', 'P' ),
	// 24 – Iné krátkodobé finančné výpomoci
	array( '241', 'Vydané krátkodobé dlhopisy', 'P' ),
	array( '249', 'Ostatné krátkodobé finančné výpomoci', 'P' ),
	// 25 – Krátkodobý finančný majetok
	array( '251', 'Majetkové cenné papiere na obchodovanie', 'A' ),
	array( '252', 'Vlastné akcie a vlastné obchodné podiely', 'A' ), // zostatok MD
	array( '253', 'Dlhové cenné papiere na obchodovanie', 'A' ),
	array( '255', 'Vlastné dlhopisy', 'A' ), // zostatok MD; vykazuje sa v pasívach so záporným znamienkom (/-/255A)
	array( '256', 'Dlhové cenné papiere so splatnosťou do jedného roka držané do splatnosti', 'A' ),
	array( '257', 'Ostatné realizovateľné cenné papiere', 'A' ),
	array( '259', 'Obstaranie krátkodobého finančného majetku', 'A' ),
	// 26 – Prevody medzi finančnými účtami
	array( '261', 'Peniaze na ceste', 'A' ), // +/- 261 v súvahe MUJ (r. 22)
	// 29 – Opravné položky ku krátkodobému finančnému majetku
	array( '291', 'Opravné položky ku krátkodobému finančnému majetku', 'P' ), // korekčný účet k aktívam (zostatok Dal)

	// ===== Účtová trieda 3 – Zúčtovacie vzťahy =====
	// 31 – Pohľadávky
	array( '311', 'Odberatelia', 'A' ),
	array( '312', 'Zmenky na inkaso', 'A' ),
	array( '313', 'Pohľadávky za eskontované cenné papiere', 'A' ),
	array( '314', 'Poskytnuté preddavky', 'A' ),
	array( '315', 'Ostatné pohľadávky', 'A' ),
	array( '316', 'Čistá hodnota zákazky', 'AP' ), // pohľadávka aj záväzok – vykazuje sa podľa zostatku (§ 2 ods. 4 opatrenia MF/15464/2013-74)
	// 32 – Záväzky
	array( '321', 'Dodávatelia', 'P' ),
	array( '322', 'Zmenky na úhradu', 'P' ),
	array( '323', 'Krátkodobé rezervy', 'P' ),
	array( '324', 'Prijaté preddavky', 'P' ),
	array( '325', 'Ostatné záväzky', 'P' ),
	array( '326', 'Nevyfakturované dodávky', 'P' ),
	// 33 – Zúčtovanie so zamestnancami a orgánmi sociálneho poistenia a zdravotného poistenia
	array( '331', 'Zamestnanci', 'P' ),
	array( '333', 'Ostatné záväzky voči zamestnancom', 'P' ),
	array( '335', 'Pohľadávky voči zamestnancom', 'A' ),
	array( '336', 'Zúčtovanie s orgánmi sociálneho poistenia a zdravotného poistenia', 'AP' ), // pohľadávka aj záväzok – vykazuje sa podľa zostatku
	// 34 – Zúčtovanie daní a dotácií
	array( '341', 'Daň z príjmov', 'AP' ), // pohľadávka aj záväzok – vykazuje sa podľa zostatku
	array( '342', 'Ostatné priame dane', 'AP' ), // pohľadávka aj záväzok – vykazuje sa podľa zostatku
	array( '343', 'Daň z pridanej hodnoty', 'AP' ), // pohľadávka aj záväzok – analytiky podľa sadzieb/typu (DPH na výstupe, na vstupe, zúčtovanie s DÚ)
	array( '345', 'Ostatné dane a poplatky', 'AP' ), // pohľadávka aj záväzok
	array( '346', 'Dotácie zo štátneho rozpočtu', 'AP' ), // pohľadávka aj záväzok
	array( '347', 'Ostatné dotácie', 'AP' ), // pohľadávka aj záväzok
	// 35 – Pohľadávky voči spoločníkom a združeniu
	array( '351', 'Pohľadávky voči prepojeným účtovným jednotkám a účtovným jednotkám v rámci podielovej účasti', 'A' ),
	array( '353', 'Pohľadávky za upísané vlastné imanie', 'A' ), // zostatok MD; v súvahe MUJ sa vykazuje v pasívach so záporným znamienkom (/-/353, r. 28)
	array( '354', 'Pohľadávky voči spoločníkom a členom pri úhrade straty', 'A' ),
	array( '355', 'Ostatné pohľadávky voči spoločníkom a členom', 'A' ),
	array( '358', 'Pohľadávky voči účastníkom združenia', 'A' ),
	// 36 – Záväzky voči spoločníkom a združeniu
	array( '361', 'Záväzky voči prepojeným účtovným jednotkám a účtovným jednotkám v rámci podielovej účasti', 'P' ),
	array( '364', 'Záväzky voči spoločníkom a členom pri rozdeľovaní zisku', 'P' ),
	array( '365', 'Ostatné záväzky voči spoločníkom a členom', 'P' ),
	array( '366', 'Záväzky voči spoločníkom a členom zo závislej činnosti', 'P' ),
	array( '367', 'Záväzky z upísaných nesplatených cenných papierov a vkladov', 'P' ),
	array( '368', 'Záväzky voči účastníkom združenia', 'P' ),
	// 37 – Iné pohľadávky a iné záväzky
	array( '371', 'Pohľadávky z predaja podniku', 'A' ),
	array( '372', 'Záväzky z kúpy podniku', 'P' ),
	array( '373', 'Pohľadávky a záväzky z pevných termínových operácií', 'AP' ), // pohľadávky aj záväzky z derivátov
	array( '374', 'Pohľadávky z nájmu', 'A' ),
	array( '375', 'Pohľadávky z vydaných dlhopisov', 'A' ),
	array( '376', 'Nakúpené opcie', 'A' ),
	array( '377', 'Predané opcie', 'P' ),
	array( '378', 'Iné pohľadávky', 'A' ),
	array( '379', 'Iné záväzky', 'P' ),
	// 38 – Časové rozlíšenie nákladov a výnosov
	array( '381', 'Náklady budúcich období', 'A' ),
	array( '382', 'Komplexné náklady budúcich období', 'A' ),
	array( '383', 'Výdavky budúcich období', 'P' ),
	array( '384', 'Výnosy budúcich období', 'P' ),
	array( '385', 'Príjmy budúcich období', 'A' ),
	// 39 – Opravná položka k zúčtovacím vzťahom a vnútorné zúčtovanie
	array( '391', 'Opravné položky k pohľadávkam', 'P' ), // korekčný účet k aktívam (zostatok Dal)
	array( '395', 'Vnútorné zúčtovanie', 'AP' ), // pri uzavieraní kníh nemá zostatok
	array( '398', 'Spojovací účet pri združení', 'AP' ), // +/-

	// ===== Účtová trieda 4 – Kapitálové účty a dlhodobé záväzky =====
	// 41 – Základné imanie a kapitálové fondy
	array( '411', 'Základné imanie', 'P' ),
	array( '412', 'Emisné ážio', 'P' ),
	array( '413', 'Ostatné kapitálové fondy', 'P' ),
	array( '414', 'Oceňovacie rozdiely z precenenia majetku a záväzkov', 'AP' ), // +/-
	array( '415', 'Oceňovacie rozdiely z kapitálových účastín', 'AP' ), // +/-
	array( '416', 'Oceňovacie rozdiely z precenenia pri zlúčení, splynutí a rozdelení', 'AP' ), // +/-
	array( '417', 'Zákonný rezervný fond z kapitálových vkladov', 'P' ),
	array( '418', 'Nedeliteľný fond z kapitálových vkladov', 'P' ),
	array( '419', 'Zmeny základného imania', 'AP' ), // +/-
	// 42 – Fondy tvorené zo zisku a prevedené výsledky hospodárenia
	array( '421', 'Zákonný rezervný fond', 'P' ),
	array( '422', 'Nedeliteľný fond', 'P' ),
	array( '423', 'Štatutárne fondy', 'P' ),
	array( '427', 'Ostatné fondy', 'P' ),
	array( '428', 'Nerozdelený zisk minulých rokov', 'P' ),
	array( '429', 'Neuhradená strata minulých rokov', 'A' ), // zostatok MD; v súvahe sa vykazuje v pasívach so záporným znamienkom (/-/429, r. 32)
	// 43 – Výsledok hospodárenia
	array( '431', 'Výsledok hospodárenia v schvaľovaní', 'AP' ), // zisk (Dal) alebo strata (MD) v schvaľovaní; ku dňu uzatvorenia kníh nemá zostatok (§ 59 ods. 15 opatrenia)
	// 45 – Rezervy
	array( '451', 'Rezervy zákonné', 'P' ),
	array( '459', 'Ostatné rezervy', 'P' ),
	// 46 – Bankové úvery
	array( '461', 'Bankové úvery', 'P' ),
	// 47 – Dlhodobé záväzky
	array( '471', 'Dlhodobé záväzky voči prepojeným účtovným jednotkám a účtovným jednotkám v rámci podielovej účasti', 'P' ),
	array( '472', 'Záväzky zo sociálneho fondu', 'P' ),
	array( '473', 'Vydané dlhopisy', 'P' ),
	array( '474', 'Záväzky z nájmu', 'P' ),
	array( '475', 'Dlhodobé prijaté preddavky', 'P' ),
	array( '476', 'Dlhodobé nevyfakturované dodávky', 'P' ),
	array( '478', 'Dlhodobé zmenky na úhradu', 'P' ),
	array( '479', 'Ostatné dlhodobé záväzky', 'P' ),
	// 48 – Odložený daňový záväzok a odložená daňová pohľadávka
	array( '481', 'Odložený daňový záväzok a odložená daňová pohľadávka', 'AP' ), // odložený daňový záväzok (Dal) alebo pohľadávka (MD)
	// 49 – Fyzická osoba – podnikateľ
	array( '491', 'Vlastné imanie fyzickej osoby – podnikateľa', 'AP' ), // +/-

	// ===== Účtová trieda 5 – Náklady =====
	// 50 – Spotrebované nákupy
	array( '501', 'Spotreba materiálu', 'N' ),
	array( '502', 'Spotreba energie', 'N' ),
	array( '503', 'Spotreba ostatných neskladovateľných dodávok', 'N' ),
	array( '504', 'Predaný tovar', 'N' ),
	array( '505', 'Tvorba a zúčtovanie opravných položiek k zásobám', 'N' ), // tvorba (MD) a zúčtovanie (Dal) OP – môže mať záporný stav
	array( '507', 'Predaná nehnuteľnosť', 'N' ),
	// 51 – Služby
	array( '511', 'Opravy a udržiavanie', 'N' ),
	array( '512', 'Cestovné', 'N' ),
	array( '513', 'Náklady na reprezentáciu', 'N' ),
	array( '518', 'Ostatné služby', 'N' ),
	// 52 – Osobné náklady
	array( '521', 'Mzdové náklady', 'N' ),
	array( '522', 'Príjmy spoločníkov a členov zo závislej činnosti', 'N' ),
	array( '523', 'Odmeny členom orgánov spoločnosti a družstva', 'N' ),
	array( '524', 'Zákonné sociálne poistenie', 'N' ),
	array( '525', 'Ostatné sociálne poistenie', 'N' ),
	array( '526', 'Sociálne náklady fyzickej osoby – podnikateľa', 'N' ),
	array( '527', 'Zákonné sociálne náklady', 'N' ),
	array( '528', 'Ostatné sociálne náklady', 'N' ),
	// 53 – Dane a poplatky
	array( '531', 'Daň z motorových vozidiel', 'N' ),
	array( '532', 'Daň z nehnuteľnosti', 'N' ),
	array( '538', 'Ostatné dane a poplatky', 'N' ), // od 1.1.2025 sa tu účtuje aj daň z finančných transakcií (§ 67 ods. 2 opatrenia)
	// 54 – Iné náklady na hospodársku činnosť
	array( '541', 'Zostatková cena predaného dlhodobého nehmotného majetku a dlhodobého hmotného majetku', 'N' ),
	array( '542', 'Predaný materiál', 'N' ),
	array( '543', 'Dary', 'N' ),
	array( '544', 'Zmluvné pokuty, penále a úroky z omeškania', 'N' ),
	array( '545', 'Ostatné pokuty, penále a úroky z omeškania', 'N' ),
	array( '546', 'Odpis pohľadávky', 'N' ),
	array( '547', 'Tvorba a zúčtovanie opravných položiek k pohľadávkam', 'N' ), // tvorba (MD) a zúčtovanie (Dal) OP – môže mať záporný stav
	array( '548', 'Ostatné náklady na hospodársku činnosť', 'N' ),
	array( '549', 'Manká a škody', 'N' ),
	// 55 – Odpisy a opravné položky k dlhodobému majetku
	array( '551', 'Odpisy dlhodobého nehmotného majetku a dlhodobého hmotného majetku', 'N' ),
	array( '553', 'Tvorba a zúčtovanie opravných položiek k dlhodobému majetku', 'N' ), // môže mať záporný stav
	array( '555', 'Zúčtovanie komplexných nákladov budúcich období', 'N' ),
	array( '557', 'Zúčtovanie oprávky k opravnej položke k nadobudnutému majetku', 'N' ),
	// 56 – Finančné náklady
	array( '561', 'Predané cenné papiere a podiely', 'N' ),
	array( '562', 'Úroky', 'N' ),
	array( '563', 'Kurzové straty', 'N' ),
	array( '564', 'Náklady na precenenie cenných papierov', 'N' ),
	array( '565', 'Tvorba a zúčtovanie opravných položiek k finančnému majetku', 'N' ), // môže mať záporný stav
	array( '566', 'Náklady na krátkodobý finančný majetok', 'N' ),
	array( '567', 'Náklady na derivátové operácie', 'N' ),
	array( '568', 'Ostatné finančné náklady', 'N' ), // bankové poplatky, poplatky platobných brán; aj úbytok/precenenie kryptoaktív (§ 46 ods. 6)
	array( '569', 'Manká a škody na finančnom majetku', 'N' ),
	// 59 – Dane z príjmov a prevodové účty
	array( '591', 'Splatná daň z príjmov', 'N' ), // od 31.12.2024 aj dorovnávacia daň (§ 52 ods. 1)
	array( '592', 'Odložená daň z príjmov', 'N' ),
	array( '595', 'Dodatočné odvody dane z príjmov', 'N' ),
	array( '596', 'Prevod podielov na výsledku hospodárenia spoločníkom', 'N' ), // +/- (v.o.s., k.s.)

	// ===== Účtová trieda 6 – Výnosy =====
	// 60 – Tržby za vlastné výkony a tovar
	array( '601', 'Tržby za vlastné výrobky', 'V' ),
	array( '602', 'Tržby z predaja služieb', 'V' ),
	array( '604', 'Tržby za tovar', 'V' ),
	array( '606', 'Výnosy zo zákazky', 'V' ),
	array( '607', 'Výnosy z nehnuteľnosti na predaj', 'V' ),
	// 61 – Zmeny stavu vnútroorganizačných zásob
	array( '611', 'Zmena stavu nedokončenej výroby', 'V' ), // +/-
	array( '612', 'Zmena stavu polotovarov', 'V' ), // +/-
	array( '613', 'Zmena stavu výrobkov', 'V' ), // +/-
	array( '614', 'Zmena stavu zvierat', 'V' ), // +/-
	// 62 – Aktivácia
	array( '621', 'Aktivácia materiálu a tovaru', 'V' ),
	array( '622', 'Aktivácia vnútroorganizačných služieb', 'V' ),
	array( '623', 'Aktivácia dlhodobého nehmotného majetku', 'V' ),
	array( '624', 'Aktivácia dlhodobého hmotného majetku', 'V' ),
	// 64 – Iné výnosy z hospodárskej činnosti
	array( '641', 'Tržby z predaja dlhodobého nehmotného majetku a dlhodobého hmotného majetku', 'V' ),
	array( '642', 'Tržby z predaja materiálu', 'V' ),
	array( '644', 'Zmluvné pokuty, penále a úroky z omeškania', 'V' ),
	array( '645', 'Ostatné pokuty, penále a úroky z omeškania', 'V' ),
	array( '646', 'Výnosy z odpísaných pohľadávok', 'V' ),
	array( '648', 'Ostatné výnosy z hospodárskej činnosti', 'V' ),
	// 65 – Zúčtovanie niektorých položiek z hospodárskej činnosti
	array( '655', 'Zúčtovanie komplexných nákladov budúcich období', 'V' ),
	array( '657', 'Zúčtovanie oprávky k opravnej položke k nadobudnutému majetku', 'V' ),
	// 66 – Finančné výnosy
	array( '661', 'Tržby z predaja cenných papierov a podielov', 'V' ),
	array( '662', 'Úroky', 'V' ),
	array( '663', 'Kurzové zisky', 'V' ),
	array( '664', 'Výnosy z precenenia cenných papierov', 'V' ),
	array( '665', 'Výnosy z dlhodobého finančného majetku', 'V' ),
	array( '666', 'Výnosy z krátkodobého finančného majetku', 'V' ),
	array( '667', 'Výnosy z derivátových operácií', 'V' ),
	array( '668', 'Ostatné finančné výnosy', 'V' ),

	// ===== Účtová trieda 7 – Uzávierkové účty a podsúvahové účty =====
	// 70 – Súvahové uzávierkové účty
	array( '701', 'Začiatočný účet súvahový', 'Z' ), // otvorenie súvahových účtov (§ 7 opatrenia)
	array( '702', 'Konečný účet súvahový', 'Z' ), // uzavretie súvahových účtov (§ 8 ods. 2 písm. e), f))
	// 71 – Výsledkový uzávierkový účet
	array( '710', 'Účet ziskov a strát', 'Z' ), // uzavretie výsledkových účtov (§ 8 ods. 2 písm. d), f))
	array( '711', 'Začiatočný účet nákladov a výnosov', 'Z' ), // v osnove; pri bežnom ročnom uzavieraní sa výsledkové účty neotvárajú
	// 75 až 79 – Podsúvahové účty

	// ===== Účtové triedy 8 a 9 – Vnútroorganizačné účtovníctvo =====
	// 75 až 79 – Podsúvahové účty (osnova ich jednotlivo neuvádza)
);
