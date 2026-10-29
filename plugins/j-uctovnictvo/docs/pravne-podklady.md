# Poznámky pre plugin podvojného účtovníctva (SK, s.r.o., mikro ÚJ) – stav k 29. 10. 2026

Skratky: „Opatrenie“ = opatrenie MF SR č. 23054/2002-92 o postupoch účtovania a rámcovej účtovej osnove pre podnikateľov v podvojnom účtovníctve v znení neskorších predpisov (posledná novela MF/013819/2025-74 = 27/2025 R. o., účinnosť 31. 12. 2025 / 1. 1. 2026). Konsolidované znenie (informatívne): https://static.slov-lex.sk/pdf/prilohy/SK/OP/2025/27/vyhlasene_znenie_5812469-2.pdf ; text novely: https://static.slov-lex.sk/static/SK/OP/2025/27/vyhlasene_znenie.html
„ZoÚ“ = zákon č. 431/2002 Z. z. o účtovníctve, znenie účinné od 1. 6. 2026: https://static.slov-lex.sk/static/SK/ZZ/2002/431/20260601.html
„ZDPH“ = zákon č. 222/2004 Z. z. o DPH, znenie účinné od 1. 1. 2026: https://static.slov-lex.sk/static/SK/ZZ/2004/222/20260101.html
„ZDP“ = zákon č. 595/2003 Z. z. o dani z príjmov, znenie účinné od 1. 1. 2026: https://static.slov-lex.sk/static/SK/ZZ/2003/595/20260101.html
„ZDFT“ = zákon č. 279/2024 Z. z. o dani z finančných transakcií, znenie účinné od 1. 1. 2026: https://static.slov-lex.sk/static/SK/ZZ/2024/279/20260101.html

---

## 1. DPH – sadzby 2026 a účet 343

* **§ 27 ZDPH (znenie od 1. 1. 2026):** základná sadzba **23 %**; znížená **19 %** (tovary v prílohe č. 7 bod 1, služby v prílohe č. 7a bod 1 – reštauračné a stravovacie služby s nápojmi okrem alkoholických nad 0,5 %); znížená **5 %** (príloha č. 7 body 2 a 3, príloha č. 7a bod 2, registrovaný sociálny podnik, štátom podporované nájomné bývanie). Zdroj: https://static.slov-lex.sk/static/SK/ZZ/2004/222/20260101.html
* **Od kedy:** sadzby 23 % / 19 % / 5 % platia od **1. 1. 2025** (predtým 20 % / 10 % / 5 %) – novela č. 278/2024 Z. z. (konsolidačný balík; v histórii znení ZDPH na slov-lex je pre verziu od 1. 1. 2025 uvedená novela 278/2024 Z. z. spolu s 102/2024 a 354/2024 – ktorý z nich presne zaviedol sadzby, som priamo neoveril; podnikajte.sk uvádza „zákon ... v súvislosti s ďalším zlepšovaním stavu verejných financií“, čo zodpovedá zákonu 278/2024). Zdroje: https://www.podnikajte.sk/dan-z-pridanej-hodnoty/sadzby-dph-2025 , https://static.slov-lex.sk/static/SK/ZZ/2004/222/20260101.html (história znení).
* **1. 7. 2025:** rozšírenie 5 % sadzby (napr. bezlepkové potraviny, tlačoviny, niektoré kultúrne služby) – https://www.podnikajte.sk/dan-z-prijmov/sadzby-dph-od-1-7-2025-na-slovensku , https://www.podnikajte.sk/dan-z-pridanej-hodnoty/sadzby-dph-od-1-1-2026
* **1. 1. 2026:** potraviny so zvýšeným obsahom cukru a soli (sladkosti, cukrovinky, koláče, zmrzlina, džemy, slané snacky, sladené nealko nápoje) presunuté z 19 % na **23 %**; výnimky (dietetické potraviny, výrobky bez/min. cukru, mliečne a rastlinné nápoje bez kávy/čaju…) – https://www.podnikajte.sk/dan-z-pridanej-hodnoty/sadzby-dph-od-1-1-2026 , https://www.podnikajte.sk/dane/konsolidacny-balicek-2026-schvalene-zmeny . Novely ZDPH s účinnosťou 1. 1. 2026 podľa histórie znení: 384/2025, 261/2025, 385/2025 Z. z. (ktorá z nich obsahuje presun potravín, UNVERIFIED).
* **Registrácia (pre plugin – prepínač platiteľ/neplatiteľ):** § 4 ZDPH v znení 2026 – platiteľom sa osoba stáva od 1. 1. nasledujúceho roka po roku, v ktorom obrat presiahol **50 000 €**, alebo okamihom dodania, ktorým obrat v bežnom roku presiahne **62 500 €**. Zdroj: https://static.slov-lex.sk/static/SK/ZZ/2004/222/20260101.html
* **Preddavky:** § 19 ods. 4 ZDPH – „Ak je platba prijatá pred dodaním tovaru alebo služby, vzniká daňová povinnosť z prijatej platby dňom prijatia platby.“; § 73 ods. 1 písm. b) – faktúru k prijatej platbe treba vyhotoviť do 15 dní od prijatia platby alebo do konca mesiaca, v ktorom bola platba prijatá. Zdroj: tamtiež.
* **Účet 343 – Daň z pridanej hodnoty** (Opatrenie § 52): účtuje sa daňová povinnosť, odpočítanie dane, opravy základu dane; pri samozdanení (prenesenie daňovej povinnosti) sa daň účtuje v prospech aj na ťarchu príslušných **analytických účtov 343** (§ 52 ods. 6); oprava pri nevymožiteľnej pohľadávke medzi analytickými účtami 343 (§ 52 ods. 11 v znení od 1. 1. 2026); DPH pri zmene účelu použitia majetku 343 ↔ 548/648 (§ 52 ods. 8 v znení od 1. 1. 2026). Zdroj: konsolidované znenie Opatrenia (link vyššie), novela 27/2025 R. o. body 7–9.
* **Analytiky 343:** Opatrenie ich konkrétne nečísluje (len „príslušný analytický účet“). Bežná prax (NIE predpis, UNVERIFIED ako štandard): 343.1xx DPH na výstupe podľa sadzby (23 / 19 / 5 %), 343.2xx DPH na vstupe podľa sadzby, 343.3xx samozdanenie (tuzemské prenesenie, nadobudnutie z EÚ, služby z EÚ – výstup aj vstup), 343.4xx DPH z prijatých preddavkov, 343.9xx zúčtovanie s daňovým úradom (vlastná daňová povinnosť / nadmerný odpočet). Odporúčanie: analytiky viazať na riadky daňového priznania a kontrolného výkazu.
* **E-shop do EÚ (len upozornenie):** zákon obsahuje osobitnú úpravu pre predaj tovaru na diaľku (§ 68 ods. 1 písm. c) ZDPH – OSS). Detaily (prah 10 000 € za EÚ) som v rámci tejto rešerše neoveroval – UNVERIFIED.

## 2. Daň z finančných transakcií („transakčná daň“)

* **Právny základ:** ZDFT č. 279/2024 Z. z., účinný od 1. 1. 2025, prvé zdaňovacie obdobie **apríl 2025**; novely 354/2024, 26/2025 (od 15. 3. 2025), 176/2025 (od 30. 6. 2025), **272/2025 Z. z. (od 1. 1. 2026)**. Zdroje: https://static.slov-lex.sk/static/SK/ZZ/2024/279/20260101.html (história znení), https://podpora.financnasprava.sk/725188-Z%C3%A1kladn%C3%A9-inform%C3%A1cie
* **Kto platí v 2026:** § 3 ods. 1 ZDFT (znenie 2026) – daňovníkom je **právnická osoba** (teda aj s.r.o.). Od 1. 1. 2026 sú fyzické osoby – podnikatelia vyňatí (novela 272/2025). Zdroje: https://static.slov-lex.sk/static/SK/ZZ/2024/279/20260101.html , https://www.podnikajte.sk/dane/transakcna-dan-zmeny-od-1-1-2026
* **Sadzby (§ 6 ZDFT, znenie 2026):** 0,4 % z odpísanej sumy, **najviac 40 € za transakciu** (neplatí pre výber hotovosti); **0,8 %** pri výbere hotovosti (bez stropu); **2 € ročne** za platobnú kartu; 0,4 % z preúčtovaných nákladov (§ 5 ods. 2 písm. a)). Minimálna daň 1 eurocent, zaokrúhľovanie na eurocent (§ 8). Zdaňovacie obdobie kalendárny mesiac, pri karte kalendárny rok (§ 9). Platiteľ (spravidla banka) vyberá a odvádza do konca nasledujúceho mesiaca (§ 10). Zdroj: https://static.slov-lex.sk/static/SK/ZZ/2024/279/20260101.html
* **Účtovanie:** Opatrenie **§ 67 ods. 2** (doplnené novelou MF/014948/2024-74, účinnosť 1. 1. 2025): „Na tomto účte [**538 – Ostatné dane a poplatky**] sa účtuje aj daň z finančných transakcií.“ Zdroj: https://static.slov-lex.sk/static/SK/OP/2024/22/vyhlasene_znenie.html (bod 16), konsolidované znenie 2026.
  * Predkontácia (banka strhne daň z účtu): **538 / 221**. (Ak by sa daň účtovala z vyúčtovania banky pred odpisom, 538 / 345 a potom 345 / 221 – ide o variant praxe, UNVERIFIED ako povinný.)
  * Vo výkaze MUJ patrí do r. 13 „Dane a poplatky“ (účtová skupina 53).
* **Bankové poplatky:** Opatrenie **§ 70 ods. 7** – na ťarchu **568 – Ostatné finančné náklady** sa účtujú „náklady peňažného styku, napríklad bankové výdavky, poistné, náklady súvisiace so získaním bankových záruk a podobné bankové výdavky…“ → **568 / 221**. Zdroj: konsolidované znenie Opatrenia.
* **Daňová uznateľnosť:** ZDP **§ 19 ods. 3 písm. j)** (znenie 2026) – daňovými výdavkami sú aj „… daň z motorových vozidiel, miestne dane …, daň z finančných transakcií a poplatky vzťahujúce sa na činnosti, z ktorých príjmy podliehajú dani“ → **je daňovým výdavkom**. Zdroj: https://static.slov-lex.sk/static/SK/ZZ/2003/595/20260101.html ; zhodne https://www.aksamec.sk/transakcna-dan-2026/ (aj účet 538).
* **Zrušenie:** k dnešnému dňu nie je na slov-lex žiadna novšia (napr. 2027) verzia ZDFT, ktorá by daň pre PO rušila; podnikajte.sk uvádza, že úplné zrušenie od 2027 „nie je isté“ – https://www.podnikajte.sk/dane/transakcna-dan-zmeny-od-1-1-2026

## 3. Daň z príjmov právnických osôb 2025/2026

* **§ 15 písm. b) bod 1 ZDP (znenie 2026):** 1a. **10 %** pre daňovníka so zdaniteľnými príjmami (výnosmi) za zdaňovacie obdobie **≤ 100 000 €**; 1b. **21 %** ostatní; 1c. **24 %** pri zdaniteľných príjmoch (výnosoch) **> 5 000 000 €**. Sadzba sa uplatní na celý základ dane (nie pásmovo). Zdroj: https://static.slov-lex.sk/static/SK/ZZ/2003/595/20260101.html ; https://www.podnikajte.sk/dan-z-prijmov/sadzby-dane-z-prijmov-pravnickych-osob-danova-licencia-2026 (rovnaké sadzby 2025 a 2026); https://www.podnikajte.sk/dan-z-prijmov/sadzby-dane-z-prijmov-pravnickych-osob-2025
* Pásmo 24 % platí od zdaňovacieho obdobia 2025 (zavedené zákonom 278/2024 Z. z.; pre 2024 platilo 15 % / 21 %) – UNVERIFIED presné číslo novely, potvrdené len, že sadzby 2025 = 2026.
* **Účtovanie:** splatná daň **591 / 341** (Opatrenie § 10 ods. 3); preddavky na daň 341 / 221; odložená daň 592 ↔ 481 (mikro ÚJ bez povinnosti auditu nemusí – § 10 ods. 4 Opatrenia).

## 4. Minimálna daň právnickej osoby („daňová licencia“)

* **Stav: znovu zavedená od 2025** – ZDP **§ 46b „Minimálna daň právnickej osoby“** (znenie 2026). Pôvodné daňové licencie platili 2014–2017 (zrušené od 2018); nová úprava platí od zdaňovacieho obdobia 2025. Zdroje: https://static.slov-lex.sk/static/SK/ZZ/2003/595/20260101.html ; https://www.podnikajte.sk/dan-z-prijmov/sadzby-dane-z-prijmov-pravnickych-osob-danova-licencia-2026 ; https://sita.sk/vofinanciach/male-a-stredne-podniky-podavali-danove-priznania-posledny-raz-bez-danovych-licencii-platit-ich-budu-musiet-aj-firmy-ktore-su-v-strate/
* **Sumy (§ 46b ods. 2) podľa zdaniteľných príjmov (výnosov):** ≤ 50 000 € → **340 €**; 50 000–250 000 € → **960 €**; 250 000–500 000 € → **1 920 €**; 500 000–5 000 000 € → **3 840 €**; > 5 000 000 € → **11 520 €** (od 2026; za 2025 bolo aj nad 5 mil. € 3 840 € – podľa podnikajte.sk). Kratšie obdobie: 1/12 × počet mesiacov (ods. 6).
* Platí sa, ak vypočítaná daň < minimálna daň, alebo pri daňovej strate (ods. 1); splatná v lehote na podanie DP (ods. 4); kladný rozdiel možno započítať v **3** nasledujúcich obdobiach, len na časť dane prevyšujúcu minimálnu daň (ods. 5); polovica pri ≥ 20 % zamestnancov so zdravotným postihnutím (ods. 3).
* **Neplatí (ods. 7):** v **prvom** zdaňovacom období, v ktorom daňovník vznikol (okrem právneho nástupcu); daňovník podľa § 12 ods. 3 a 4 (neziskové); chránená dielňa/pracovisko; obdobia likvidácie/konkurzu (§ 41 ods. 4, 6, 8, 12–14); pozemkové spoločenstvo s príjmami ≤ 10 000 €; od obdobia doručenia oznámenia o začatí konania o zrušení; (ďalšie písmená – registrovaný sociálny podnik podľa podnikajte.sk, v texte zákona nad písm. f) som neoveroval – UNVERIFIED).
* Pozn. k rozporom: podnikajte.sk (článok k balíčku 2026) raz hovorí o „obrate“, raz o „zdaniteľných príjmoch“ – zákon používa „zdaniteľné príjmy (výnosy)“.
* **Účtovanie (prax, Opatrenie výslovne neupravuje):** minimálna daň je daň z príjmov → 591 / 341 v sume minimálnej dane. UNVERIFIED (žiadny výslovný predpis), ale vyplýva z § 10 ods. 1 Opatrenia (daň z príjmov sa účtuje v skupine 59).

## 5. Typické predkontácie pre e-shop (s.r.o., platiteľ DPH, sadzba 23 %)

Pravidlá z Opatrenia (konsolidované znenie 2026) a ilustrácie z podnikajte.sk. Kde je uvedené „prax“, ide o bežný postup, nie o výslovné znenie predpisu.

### 5.1 Zásoby – spôsob A vs. B (Opatrenie § 43)
* § 43 ods. 1: **spôsob A** môžu použiť všetky ÚJ; **spôsob B** len ÚJ, na ktoré sa nevzťahuje povinnosť auditu podľa § 19 ZoÚ (mikro s.r.o. spravidla môže). Ak pri B ku dňu závierky splní aspoň 2 podmienky § 19 ods. 1 písm. a), od ďalšieho obdobia musí prejsť na A.
* **Spôsob A** (§ 43 ods. 2, ods. 8; § 44 ods. 9–10): obstaranie tovaru **131 / 321** (+ 343 / 321 DPH na vstupe); príjemka na sklad **132 / 131**; alebo pri nákupe priamo **132 / 321** (§ 43 ods. 8 to výslovne dovoľuje); predaj (výdajka) **504 / 132**; 131 pri uzatvorení kníh nemá zostatok; tovar na ceste ku koncu roka **139 / 321**.
* **Spôsob B** (§ 43 ods. 4): nákup priamo do nákladov **504 / 321**; pri uzávierke: začiatočný stav zásob **504 / 132** a konečný stav podľa inventúry **132 / 504**; manká **549**, prebytky **648** (alebo 50x pri preukázanej chybe).
* Zdroj: konsolidované znenie Opatrenia; príklad 504/321 aj https://www.podnikajte.sk/uctovnictvo/uctovanie-faktur

### 5.2 Predaj
* **Vydaná faktúra za tovar:** 311 / 604 (základ), 311 / 343 (DPH na výstupe). Zdroj: https://www.podnikajte.sk/uctovnictvo/uctovanie-faktur ; Opatrenie § 75 (tržby 60x so súvzťažnosťou 31x alebo 211).
* **Úbytok tovaru zo skladu (spôsob A):** 504 / 132.
* **Úhrada od odberateľa na banku:** 221 / 311.
* **Platobná brána bez platobného účtu (podnikajte.sk, 2021):** úhrada zákazníka cez bránu 315 / 311; poplatok brány 568 / 325; výplata netto 221 / 315; zápočet poplatku 325 / 315. **Brána s platobným účtom:** vedie sa ako analytický bankový účet 221.xx (221.xx / 311), poplatky 568 / 325, 325 / 221.xx. Zdroj: https://www.podnikajte.sk/uctovnictvo/platobne-brany-uctovanie-dph
* Opatrenie § 49 ods. 5: príjem cez **elektronickú registračnú pokladnicu platobnou kartou** sa účtuje na ťarchu **315**, po pripísaní na účet 221 / 315.
* **Dobierka (kuriér/Packeta inkasuje za e-shop):** Opatrenie ani preverené portály nedávajú výslovnú predkontáciu – **UNVERIFIED**. Bežná prax: pri odovzdaní/doručení zásielky preúčtovanie pohľadávky 315 (alebo 378) / 311 s analytikou podľa prepravcu; prijatie hromadnej výplaty 221 / 315; poplatok za dobierku a prepravu od prepravcu je **služba** (518 / 321, + 343 / 321), nie bankový poplatok. Alternatíva v praxi: 261 – Peniaze na ceste (len pre presuny medzi vlastnými finančnými účtami, § 46 ods. 7 – pre dobierku menej vhodné).
* **Prijatý preddavok (platiteľ DPH):** 221 / 324 (Opatrenie § 50 ods. 4: na 324 „prijaté krátkodobé preddavky od odberateľov“); DPH z prijatej platby vzniká dňom prijatia (§ 19 ods. 4 ZDPH) → daňový doklad k prijatej platbe do 15 dní / konca mesiaca (§ 73 ZDPH); prax: **324 / 343** (DPH z preddavku na analytiku 343). Vyúčtovacia faktúra: 311 / 604, 311 / 343 (celá DPH); zúčtovanie preddavku **324 / 311**, odpočet DPH z preddavku **343 / 324** (prax – presná podoba sa v softvéroch líši, UNVERIFIED ako jediný správny postup). Príklad bez DPH (neplatiteľ): 221 / 324 a 324 / 311 – https://www.podnikajte.sk/uctovnictvo/uctovanie-faktur . Ak je preddavok dlhodobý → 475.

### 5.3 Nákup a platby
* **Prijatá faktúra za tovar:** A: 131 / 321 (resp. 132 / 321); B: 504 / 321; DPH na vstupe 343 / 321. Zdroj: Opatrenie § 43; https://www.podnikajte.sk/uctovnictvo/uctovanie-faktur
* **Prijatá faktúra za službu (doprava, hosting, marketing):** 518 / 321, 343 / 321.
* **Úhrada dodávateľovi:** 321 / 221.
* **Poskytnutý preddavok dodávateľovi:** 314 / 221; zúčtovanie 321 / 314 (podnikajte.sk, tamtiež).
* **Bankové poplatky:** 568 / 221 (Opatrenie § 70 ods. 7).
* **Transakčná daň:** 538 / 221 (Opatrenie § 67 ods. 2).

### 5.4 Inventarizačné rozdiely (Opatrenie § 11 a § 43 ods. 2 písm. d))
* **Manko** na tovare: **549 / 132** (spôsob A) – § 43 ods. 2 písm. d), § 68 ods. 8. Úbytky do výšky noriem prirodzených úbytkov: 504 / 132. Pri manku nad normu môže vzniknúť povinnosť upraviť DPH (§ 52 Opatrenia: 549 / 343 pri manku – text Opatrenia uvádza „…účtu 549 – Manká a škody so súvzťažným zápisom v prospech účtu 343“).
* **Prebytok** nakupovaných zásob: **132 / 648** (§ 11 ods. 3, § 43 ods. 2 písm. d)); ak sa preukáže chybné účtovanie výdaja: 132 / 504 (§ 11 ods. 2).
* Schodok v pokladnici: pohľadávka voči hmotne zodpovednej osobe (335) – § 11 ods. 1 písm. b).
* Predpis k inventarizácii: § 29–30 ZoÚ (inventarizačný rozdiel podľa § 30 ods. 7) – neoveroval som znenie detailne.

### 5.5 Odpisy
* **551 / 07x** (DNM) a **551 / 08x** (DHM) – Opatrenie § 69 ods. 2 (na ťarchu 551 sa účtujú odpisy DNM a DHM a zostatková cena pri likvidácii majetku). Vyradenie predaním: 541 / 0xx (zostatková cena), oprávky 08x / 02x.

### 5.6 Daň z príjmov, uzávierka a otvorenie kníh
* Splatná daň: 591 / 341 (Opatrenie § 10 ods. 3); zaplatený preddavok 341 / 221.
* **Uzavretie kníh (Opatrenie § 8 ods. 2):** a) zistia sa obraty; b) konečné stavy/zostatky; c) výsledok pred zdanením, mimo účtovníctva základ dane, zaúčtuje sa daň; d) náklady: **710 / 5xx** (konečné stavy nákladov v prospech nákladových účtov a na ťarchu 710), výnosy: **6xx / 710**; e) aktíva: **702 / aktívne účty**, pasíva: **pasívne účty / 702**; f) **zisk: 710 / 702**, **strata: 702 / 710**. Potom sa knihy uzatvoria a zostaví sa závierka (§ 8 ods. 3).
* **Otvorenie kníh (Opatrenie § 7):** začiatočné stavy aktív **aktívne účty / 701**, pasív **701 / pasívne účty** (§ 7 ods. 2); výsledok minulého obdobia: **zisk 701 / 431**, **strata 431 / 701** (§ 7 ods. 3).
* **Rozdelenie výsledku (§ 7 ods. 4, § 59 ods. 15):** podľa rozhodnutia valného zhromaždenia napr. zisk: 431 / 421 (rezervný fond, ak sa tvorí), 431 / 428 (nerozdelený zisk), 431 / 364 (podiely na zisku); strata: 429 / 431 (neuhradená strata) alebo úhrada z fondov. 431 nemá ku dňu uzatvorenia kníh zostatok.
* ZoÚ § 16 ods. 12: konečné zostatky súvahových účtov k poslednému dňu obdobia sa musia rovnať začiatočným stavom k prvému dňu nasledujúceho obdobia (aj podsúvahové).

## 6. Zákonné požiadavky na doklady a knihy (zákon 431/2002, znenie od 1. 6. 2026) – dôležité pre softvér

Zdroj celej sekcie: https://static.slov-lex.sk/static/SK/ZZ/2002/431/20260601.html

Pozn.: v zadaní uvedené „účtovné knihy § 13–16“ nezodpovedá číslovaniu: **§ 12** = účtovné knihy v podvojnom účtovníctve, § 13 = rámcová účtová osnova a účtový rozvrh, § 14 = analytické účty a zoznamy, § 15 = knihy v jednoduchom účtovníctve, § 16 = otvorenie a uzavretie kníh.

* **§ 8 ods. 1 a 6:** účtovníctvo sa vedie správne, úplne, preukázateľne, zrozumiteľne a **spôsobom zaručujúcim trvalosť účtovných záznamov** – po celú dobu spracovania a úschovy (§ 31–36).
* **§ 10 ods. 1 – náležitosti účtovného dokladu:** a) **slovné a číselné označenie** dokladu; b) obsah účtovného prípadu a označenie jeho účastníkov; c) peňažná suma alebo cena za mernú jednotku a množstvo; d) dátum vyhotovenia; e) dátum uskutočnenia účtovného prípadu, ak nie je zhodný s dátumom vyhotovenia; f) podpisový záznam osoby zodpovednej za účtovný prípad, ak overenie nie je zabezpečené podľa § 32 ods. 3 písm. b) alebo c) (elektronická výmena údajov / vnútorný kontrolný systém). **§ 10 ods. 2:** doklad sa vyhotoví bez zbytočného odkladu.
  * Pre softvér: zákon **nevyžaduje bezmedzerové (súvislé) číslovanie** účtovných dokladov – vyžaduje „slovné a číselné označenie“. (Pre **faktúry** platiteľa DPH vyžaduje § 74 ods. 1 písm. c) ZDPH „poradové číslo faktúry“ – overené v znení 2026; či musí byť rad bez medzier, zákon výslovne neuvádza.) Odporúčanie: číselné rady podľa typu dokladu (VF, PF, BV, PD, ID…) + rok.
  * Pre softvér: pri dokladoch vytvorených v plugine (interné doklady, príjemky…) zabezpečiť pole „zodpovedná osoba“ alebo popísať vnútorný kontrolný systém (§ 32 ods. 3 písm. c), ods. 6).
* **§ 11:** účtovné zápisy sa zaznamenávajú v účtovných knihách **priebežne**; zakázané je účtovať mimo kníh, o prípade, ktorý nevznikol, alebo zatajovať.
* **§ 12 – knihy v podvojnom účtovníctve:** a) **denník** (chronologicky, preukazuje zaúčtovanie všetkých prípadov), b) **hlavná kniha** (systematicky, syntetické a analytické účty podľa účtového rozvrhu); hlavná kniha obsahuje najmä stavy ku dňu otvorenia, **súhrnné obraty MD a D jednotlivých účtov minimálne za kalendárny mesiac** a zostatky/stavy ku dňu závierky (ods. 2); zápis na syntetickom účte sa rozvádza na analytických (ods. 3); všetko z denníka musí byť v hlavnej knihe (ods. 4); podsúvahové účty (ods. 5); **nesmie sa účtovať na účtoch, ktoré nie sú v účtovom rozvrhu** (ods. 6).
* **§ 13:** účtový rozvrh (syntetické, analytické, podsúvahové účty) v súlade s rámcovou osnovou; možno ho dopĺňať počas roka; ak sa nemení, platí aj na ďalšie obdobie.
* **§ 14:** súčty analytických účtov musia sedieť so syntetickými (začiatočné stavy, obraty MD/D, konečné zostatky) (ods. 1); analytická evidencia v peniazoch, prípadne v množstve (ods. 2); povinný **zoznam účtovných kníh** a **zoznam číselných znakov, symbolov a skratiek** s ich významom (ods. 3).
* **§ 16 – otvorenie/uzavretie:** PO otvára knihy ku dňu vzniku, k prvému dňu obdobia, k rozhodnému dňu… (ods. 1); uzatvára k poslednému dňu obdobia, ku dňu pred likvidáciou/konkurzom… (ods. 4). **Po zostavení účtovnej závierky nemožno otvárať uzavreté knihy** (ods. 9), **okrem** prípadu, že do schválenia závierky ich treba otvoriť na zabezpečenie verného obrazu (ods. 10); neskôr zistené neporovnateľné údaje sa opravujú v období zistenia (ods. 11); kontinuita zostatkov (ods. 12).
* **§ 31:** účtovné záznamy listinné / elektronické; povinnosť zabezpečiť **vierohodnosť pôvodu, neporušenosť obsahu a čitateľnosť** od vyhotovenia do konca úschovy (ods. 3–6). **§ 31 ods. 8:** ÚJ v podvojnom účtovníctve vedúca účtovníctvo softvérom musí daňovému úradu **umožniť prístup do softvéru** a preukázať označenie účtov, na ktorých sú prípady účtované – počas celej doby úschovy (→ plugin by mal mať read-only „audítorský“ prístup a export).
* **§ 32:** preukázateľný účtovný záznam; **podpisový záznam** = vlastnoručný, kvalifikovaný elektronický alebo „obdobný preukázateľný podpisový záznam“ umožňujúci jednoznačnú identifikáciu osoby (ods. 2); overenie podpisom, elektronickou výmenou údajov alebo vnútorným kontrolným systémom (ods. 3, 5, 6).
* **§ 33:** transformácia záznamu (sken / tlač) so zachovaním obsahu.
* **§ 34 – oprava účtovného záznamu (kľúčové pre nemennosť):** oprava sa vykoná bez zbytočného odkladu (ods. 1) tak, **aby bolo možné určiť zodpovednú osobu, deň vykonania opravy a obsah záznamu pred opravou aj po oprave** (ods. 2); **na opravu účtovného zápisu treba vždy vyhotoviť účtovný doklad** (ods. 3); dátum s presnosťou, aby neistota nespôsobila neistotu obsahu (ods. 4).
  * Pre softvér: zaúčtované zápisy nemeniť ani nemazať (append-only); opravy robiť **opravným dokladom** (storno / červené storno alebo doúčtovanie) s auditným záznamom (kto, kedy, pôvodná a nová hodnota). Uzamknutie obdobia po zostavení závierky (§ 16 ods. 9–10).
* **§ 35 – archivácia:** ochrana proti strate, zničeniu, neoprávnenému prístupu aj pre programové vybavenie (ods. 1); lehoty (ods. 3): účtovná závierka a výročná správa **10 rokov** po roku, ktorého sa týkajú; **účtovné doklady, účtovné knihy, zoznamy kníh, zoznamy symbolov a skratiek, odpisový plán, inventúrne súpisy, inventarizačné zápisy, účtový rozvrh – 10 rokov**; záznamy o spôsobe vedenia účtovníctva a systéme uchovávania – 10 rokov po roku posledného použitia (ods. 3 písm. d) – týka sa aj dokumentácie softvéru/verzie); dodržiavať zvolený spôsob uchovávania, elektronická úschova = uloženie na dátovom nosiči (ods. 5).

## 7. Prehľad zdrojov

* Opatrenie 23054/2002-92 – konsolidované znenie 2026: https://static.slov-lex.sk/pdf/prilohy/SK/OP/2025/27/vyhlasene_znenie_5812469-2.pdf
* Novela 27/2025 R. o. (MF/013819/2025-74): https://static.slov-lex.sk/static/SK/OP/2025/27/vyhlasene_znenie.html
* Novela 22/2024 R. o. (MF/014948/2024-74; DFT na 538, kryptoaktíva): https://static.slov-lex.sk/static/SK/OP/2024/22/vyhlasene_znenie.html ; konsolidované znenie k 1. 1. 2025: https://static.skolenie.slov-lex.sk/pdf/prilohy/SK/OP/2024/22/20250101_5693948-2.pdf
* Opatrenie MF/15464/2013-74 (mikro) – konsolidované znenie: https://static.slov-lex.sk/pdf/prilohy/SK/OP/2017/37/37_2017_konsolidovane_znenie.pdf
* Zákon 431/2002: https://static.slov-lex.sk/static/SK/ZZ/2002/431/20260601.html
* Zákon 222/2004: https://static.slov-lex.sk/static/SK/ZZ/2004/222/20260101.html
* Zákon 595/2003: https://static.slov-lex.sk/static/SK/ZZ/2003/595/20260101.html
* Zákon 279/2024: https://static.slov-lex.sk/static/SK/ZZ/2024/279/20260101.html
* Finančná správa – DFT základné info: https://podpora.financnasprava.sk/725188-Z%C3%A1kladn%C3%A9-inform%C3%A1cie
* podnikajte.sk: https://www.podnikajte.sk/dan-z-pridanej-hodnoty/sadzby-dph-od-1-1-2026 , https://www.podnikajte.sk/dane/transakcna-dan-zmeny-od-1-1-2026 , https://www.podnikajte.sk/dan-z-prijmov/sadzby-dane-z-prijmov-pravnickych-osob-danova-licencia-2026 , https://www.podnikajte.sk/uctovnictvo/uctovanie-faktur , https://www.podnikajte.sk/uctovnictvo/platobne-brany-uctovanie-dph , https://www.podnikajte.sk/dane/konsolidacny-balicek-2026-schvalene-zmeny
* aksamec.sk (DFT 2026, daňový výdavok, 538): https://www.aksamec.sk/transakcna-dan-2026/
