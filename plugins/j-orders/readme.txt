=== J-Objednávky ===
Contributors: aveyona
Tags: woocommerce, orders, order number, jupiter
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later

Prehľadný zoznam objednávok WooCommerce a vlastné čísla objednávok. Súčasť rodiny Jupiter.

== Description ==

* Zoznam objednávok (J-Objednávky → Objednávky): stavy s počtami, vyhľadávanie aj podľa vlastného čísla objednávky, filter platby a obdobia, stĺpce platba (aj dátum zaplatenia), doprava, suma, faktúra z J-Fakturácie, ikona poznámky zákazníka, hromadná zmena stavu. Funguje s klasickým ukladaním objednávok aj s HPOS.
* Čísla objednávok (J-Objednávky → Číslovanie): prefix + poradové číslo s pevným počtom číslic + prípona, história zmien. Náhrada doplnku My Order Numbers – prevezme jeho nastavenie, počítadlo aj históriu; čísla sú v rovnakom údaji (_wcson_order_number), takže existujúce objednávky si ich ponechajú. Kým My Order Numbers beží, čísla prideľuje on.

== Changelog ==

= 0.2.0 =
* Prepojenie s J-Fakturáciou: stĺpec Doklady (zálohová faktúra aj faktúra so stavom a PDF), Vystaviť faktúru a Pregenerovať pri riadku, filter Na fakturáciu (vybavené bez faktúry), hromadne Vybaviť a vystaviť faktúry.

= 0.1.3 =
* Číslovanie objednávok bez duplicít (overenie pod zámkom, aj HPOS), vyhľadávanie na HPOS, bezpečnejšia hromadná zmena stavu.

= 0.1.2 =
* Doplnok premenovaný na J-Objednávky (priečinok a nastavenia ostávajú – j-orders).

= 0.1.1 =
* Oprava: vyhľadávanie v zozname objednávok podľa mena, e-mailu a telefónu (doteraz našlo len číslo objednávky).

= 0.1.0 =
* Prvá verzia: zoznam objednávok a číslovanie objednávok (náhrada My Order Numbers).
