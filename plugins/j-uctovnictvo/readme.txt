=== J-Účtovníctvo ===
Contributors: aveyona
Tags: woocommerce, accounting, double-entry, slovakia, jupiter
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later

Podvojné účtovníctvo pre slovenské s. r. o. Súčasť rodiny Jupiter.

== Description ==

* Sprievodca založením účtovníctva a firmy: údaje firmy (načítanie z registra podľa IČO), veľkosť a DPH, začiatok účtovania, účtový rozvrh, počiatočné stavy (nová firma – základné imanie, pokračujúca – zostatky proti 701), napojenie dokladov, predkontácie, kontrola.
* Účtový rozvrh podľa rámcovej účtovej osnovy pre podnikateľov a vlastné analytické účty; daňový / nedaňový náklad.
* Účtové obdobia, účtovné doklady so zápismi MD / Dal a predkontáciami, storno (červené), uzávierka obdobia (zámok dátumu).
* Účtovné knihy: denník, hlavná kniha, karta účtu, obratová predvaha, saldokonto (311, 321, 324, 365…) – tlač a CSV.
* Automatické účtovanie: vydané faktúry a dobropisy, prijaté faktúry podľa kategórie, úhrady, pohyby v banke (J-Fakturácia → Banka), preddavky, doklady J-Skladu a predaný tovar. Zmena zdrojového dokladu = storno a nový zápis; nič sa nezdvojí.
* Na zaúčtovanie: pohyby v banke bez faktúry s výberom účtu.

* Účtovná závierka mikro účtovnej jednotky: Súvaha Úč MÚJ 1-01 a Výkaz ziskov a strát Úč MÚJ 2-01 (MF/15464/2013-74), priebežne aj k 31. 12., kontroly a rozpis účtov.

Pripravuje sa: dlhodobý majetok a odpisy, pokladňa, ročná uzávierka a archív, DPH.

Program nenahrádza odborné posúdenie – pred podaním účtovnej závierky a daňového priznania odporúčame kontrolu účtovníkom.

== Changelog ==

= 0.2.0 =
* Účtovná závierka mikro účtovnej jednotky (súvaha a výkaz ziskov a strát Úč MÚJ) s kontrolami a rozpisom účtov.
* Overená rámcová účtová osnova (235 účtov, platná pre 2026), prebytok zásob na 648.
* Počiatočné stavy pokračujúcej firmy sa dajú zaúčtovať aj po založení (oprava = storno a nový doklad PS).

= 0.1.0 =
* Prvá verzia: sprievodca založením, účtový rozvrh, účtovné doklady so stornom, denník, hlavná kniha, obratová predvaha, saldokonto, automatické účtovanie faktúr, banky a skladu.
