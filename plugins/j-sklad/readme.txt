=== J-Sklad ===
Contributors: aveyona
Tags: woocommerce, stock, inventory, warehouse, jupiter
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.3.0
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

Pripravuje sa: PDF dokladov, import príjemky z CSV, dodávatelia, prepojenie s J-Fakturáciou, viac skladov.

== Changelog ==

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
