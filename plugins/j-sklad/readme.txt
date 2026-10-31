=== J-Sklad ===
Contributors: aveyona
Tags: woocommerce, stock, inventory, warehouse, jupiter
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.6.1
License: GPLv2 or later

Sklad pre WooCommerce: príjemky, výdajky, kniha pohybov zásob a hodnota skladu. Súčasť rodiny Jupiter.

== Description ==

Zásoba ostáva v produktoch WooCommerce (Spravovať zásoby, Množstvo na sklade) – J-Sklad k nej pridáva doklady a úplnú históriu. Vhodný pre ľubovoľný e-shop WooCommerce; funguje s variantmi produktov a s HPOS.

* Príjemka (s nákupnou cenou → priemerná nákupná cena, vážený priemer) a výdajka s dôvodom; koncept, zaúčtovanie s číslom (PR2026001, VY2026001), storno protipohybom.
* Kniha pohybov: každá zmena zásoby – predaj v objednávke, vrátenie na sklad, doklady, ručná zmena v produkte, iný doplnok.
* Otváracia príjemka pri zapnutí – súčasné zásoby ako počiatočný stav (zásoby sa nemenia).
* Prehľad skladu: zásoba, nákupná cena, hodnota, filtre dochádza / vypredané / bez nákupnej ceny; box v detaile produktu.
* Vyhľadanie produktov podľa názvu a čísla produktu, aj čítačkou čiarových kódov.
* Inventúra: súpis na tlač, zadávanie spočítaných množstiev (aj čítačkou), priebežné ukladanie, manko a prebytok v nákupných cenách, zaúčtovanie a protokol (IN2026001), storno.

* Merné jednotky (ks, h, kg, mesiac…) pri produkte, vlastné jednotky, voliteľne jednotka pri cene v obchode.

* Akcie a novinky: hromadné zľavy (%, € z ceny, nová cena) s dátumom od – do a názvom akcie, označenie novinky ručne alebo automaticky podľa dátumu pridania, štítky v obchode, krátke kódy [jsk_akcie] a [jsk_novinky], filter obchodu ?jsk=akcie / ?jsk=novinky.

Pripravuje sa: PDF dokladov, import príjemky z CSV, návrh objednávky tovaru, viac skladov.

== Changelog ==

= 0.6.1 =
* Storno dokladu oznámi J-Účtovníctvu (udalosť jsk_doc_cancelled).

= 0.6.0 =
* Merné jednotky: pole Jednotka v úprave produktu (Zásoby) – kus, hodina, minúta, deň, týždeň, mesiac, rok, kg, g, l, ml, m, m², m³, balenie, sada, pár, licencia, osoba, strana; vlastné jednotky v Nastaveniach. Jednotka sa zobrazuje pri zásobe, v dokladoch, v pohyboch a v balíčkoch („IT poradenstvo – 8 h“); voliteľne aj pri cene v obchode („· za hodinu“).

= 0.5.0 =
* Balíčky (zostavy): záložka Balíček v úprave produktu, súčasti sa pridajú do košíka automaticky (počet podľa balíčka, bežná alebo vlastná cena), nedajú sa odobrať samostatne; prehľad J-Sklad → Balíčky; cena balíčka na stránke produktu a v kartách.

= 0.4.0 =
* Akcie a novinky (J-Sklad → Akcie a novinky): zoznam produktov s filtrom (v akcii, novinky, bez akcie), hromadne nastaviť alebo zrušiť zľavu – percentom, sumou alebo novou cenou, s dátumom začiatku a konca akcie, názvom akcie a zaokrúhlením (centy, 10 centov, celé €, ,90, ,99). Zľava je akciová cena WooCommerce, pri variabilnom produkte pre všetky varianty.
* Novinka: ručne (s dátumom konca) alebo automaticky N dní od pridania produktu; dá sa aj vypnúť pri konkrétnom produkte.
* Polia Názov akcie, Novinka a Novinka do v úprave produktu, stĺpec Akcia / novinka v zozname produktov.
* Obchod: štítky „Akcia −20 %“ (alebo názov akcie) a „Novinka“ namiesto „Výpredaj!“ – text a farba v nastaveniach; krátke kódy [jsk_akcie], [jsk_novinky] a filter obchodu ?jsk=akcie / ?jsk=novinky.

= 0.3.1 =
* Príjemka: dodávateľ z adresára J-Fakturácie (našepkávač), prepojenie s prijatou faktúrou (odkaz na faktúru, alebo + Prijatá faktúra k príjemke).

= 0.3.0 =
* Inventúra: nová inventúra pre všetky produkty alebo kategóriu, tlač súpisu, zadávanie spočítaných množstiev s priebežným ukladaním, čítačka čiarových kódov (+1 ks na sken), filtre Nespočítané / Rozdiely, zaúčtovanie (zásoba = spočítané, rozdiel do Pohybov), protokol na tlač, storno.
* Názov stĺpca „Číslo produktu“ aj v inventúre.

= 0.2.4 =
* Namiesto „SKU“ sa všade píše „Číslo produktu“ (prehľad, doklady, obrázky, vyhľadávanie).

= 0.2.3 =
* Knižnica médií skrýva len používané obrázky produktov, ochrana dokladov pred dvojitým zaúčtovaním, výdajka nepôjde do mínusu, varianty so zásobou hlavného produktu.

= 0.1.2 =
* Prehľad skladu: fotka produktu, väčší názov, cena a stav zásoby; pri zľave aj pôvodná cena.
* Pohyby sa zapisujú presne aj pri súbežných objednávkach (zámok produktu, stav z databázy pred a po zmene).
* Predaj, vrátenie, refundácia a úprava objednávky sa zapíšu rovno s číslom objednávky.
* Varianty so zásobou hlavného produktu sa vedú na hlavnom produkte (bez zdvojenia).
* Zmena zásoby mimo WooCommerce alebo počas vypnutia J-Skladu sa zapíše ako vyrovnanie.
* Zaúčtovanie a storno chránené proti dvojkliku; prerušené zaúčtovanie sa dá dokončiť; storno príjemky vráti priemernú nákupnú cenu a nedovolí zásobu pod nulu.
* Množstvá v celých kusoch, súhrn hodnoty skladu bez zdvojenia variabilných produktov, filter kategórie vrátane variantov.

= 0.1.1 =
* Prehľad skladu: hodnota skladu v predajných cenách, stĺpec predajná cena.
* Nákupná cena sa dá zadať priamo v Prehľade skladu (uloží sa hneď, zmena sa zapíše do Pohybov).

= 0.1.0 =
* Prvá verzia: príjemky, výdajky, kniha pohybov, prehľad a hodnota skladu.
