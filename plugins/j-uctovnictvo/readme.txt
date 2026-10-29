=== J-Účtovníctvo ===
Contributors: aveyona
Tags: woocommerce, accounting, double-entry, slovakia, jupiter
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.5.2
License: GPLv2 or later

Podvojné účtovníctvo pre slovenské s. r. o. Súčasť rodiny Jupiter.

== Description ==

* Sprievodca založením účtovníctva a firmy: údaje firmy (načítanie z registra podľa IČO), veľkosť a DPH, začiatok účtovania, účtový rozvrh, počiatočné stavy (nová firma – základné imanie, pokračujúca – zostatky proti 701), napojenie dokladov, predkontácie, kontrola.
* Účtový rozvrh podľa rámcovej účtovej osnovy pre podnikateľov a vlastné analytické účty; daňový / nedaňový náklad.
* Účtové obdobia, účtovné doklady so zápismi MD / Dal a predkontáciami, storno (červené), uzávierka obdobia (zámok dátumu).
* Účtovné knihy: denník, hlavná kniha, karta účtu, obratová predvaha, saldokonto (311, 321, 324, 365…) – tlač a CSV.
* Automatické účtovanie: vydané faktúry a dobropisy, prijaté faktúry podľa kategórie, úhrady, pohyby v banke (J-Fakturácia → Banka), preddavky, doklady J-Skladu a predaný tovar. Zmena zdrojového dokladu = storno a nový zápis; nič sa nezdvojí.
* Na zaúčtovanie: pohyby v banke bez faktúry s výberom účtu.
* Import histórie z OBERON-u (Exalogic) po rokoch.

* Účtovná závierka mikro účtovnej jednotky: Súvaha Úč MÚJ 1-01 a Výkaz ziskov a strát Úč MÚJ 2-01 (MF/15464/2013-74), priebežne aj k 31. 12., kontroly a rozpis účtov.

* Pokladňa: pokladničné doklady, pokladničná kniha, tlač.
* Na riešenie: úlohy, rozhodnutia, otázky pre účtovníka, poznámky a automatické kontroly.

Pripravuje sa: dlhodobý majetok a odpisy, ročná uzávierka a archív, DPH.

Program nenahrádza odborné posúdenie – pred podaním účtovnej závierky a daňového priznania odporúčame kontrolu účtovníkom.

== Changelog ==

= 0.5.2 =
* Pravidlá pre pohyby v banke: protiúčet (IBAN) → účet, napr. súkromný účet konateľa → 355 pohľadávka; takéto pohyby sa zaúčtujú automaticky.
* Predkontácie: splátka pohľadávky od konateľa (221/355), výplata konateľovi (355/221).

= 0.5.1 =
* Na riešenie: stĺpec Priorita (vysoká / stredná / nízka) so zmenou priamo v zozname, zoradenie od najvyššej priority, chýbajúci doklad dostane prioritu podľa sumy; súhrn a tlač zoradené podľa priority.

= 0.5.0 =
* Na riešenie: prehľad úloh, rozhodnutí, otázok pre účtovníka a poznámok so stavom, termínom, prioritou, väzbou na doklad / pohyb v banke / účet / partnera / rok a históriou riešenia.
* Automatické kontroly: pohyb v banke bez dokladu, prijatá faktúra bez PDF, faktúra po splatnosti, nesúlad banky s výpisom, záporná pokladňa, termín závierky a DPPO; po vyriešení sa zatvoria samy.
* Karta na prehľade, poznámky pri doklade a v „Na zaúčtovanie“, tlač zoznamu pre účtovníka, denný súhrn do J-Notes.

= 0.4.0 =
* Pokladňa: príjmové a výdavkové pokladničné doklady (PD/VD) s výberom účelu, kontrola záporného zostatku, pokladničná kniha so zostatkom po každom doklade, tlač dokladu aj knihy.

= 0.3.0 =
* Import účtovníctva z OBERON-u (Exalogic): nástroj tools/oberon2juc.py prevedie databázu .mdb (účtovný denník, adresár, názvy účtov), import s náhľadom po rokoch, bez duplicít, s uzavretím importovaných rokov; import otvoreného roka sa dá zmazať a zopakovať.

= 0.2.0 =
* Účtovná závierka mikro účtovnej jednotky (súvaha a výkaz ziskov a strát Úč MÚJ) s kontrolami a rozpisom účtov.
* Overená rámcová účtová osnova (235 účtov, platná pre 2026), prebytok zásob na 648.
* Počiatočné stavy pokračujúcej firmy sa dajú zaúčtovať aj po založení (oprava = storno a nový doklad PS).

= 0.1.0 =
* Prvá verzia: sprievodca založením, účtový rozvrh, účtovné doklady so stornom, denník, hlavná kniha, obratová predvaha, saldokonto, automatické účtovanie faktúr, banky a skladu.
