=== J-Notes ===
Contributors: aveyona
Tags: notes, markdown, plans, documentation, jupiter
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.1
License: GPLv2 or later

Poznámky k projektom v administrácii WordPressu. Súčasť rodiny Jupiter.

== Description ==

* Plány s úlohami: - [ ] / - [~] / - [x] alebo ☐ ◐ ☑ (aj v poslednom stĺpci tabuľky), percento hotovo, prepínanie stavu klikom.
* Dokumentácia a poznámky v Markdowne (nadpisy, zoznamy, tabuľky, kód, odkazy), náhľad, tlač.
* Prehľad „Teraz robím“: rozpracované úlohy a posledný záznam denníka zmien.
* Denník zmien poznámky – rýchly záznam s dátumom.
* Projekty, druhy (plán, dokumentácia, poznámka), stavy, pripnutie, hľadanie, stránkovanie.
* Kôš: zmazaná poznámka sa dá 30 dní obnoviť.
* Ochrana pred prepísaním: ak sa poznámka zmení, kým je otvorená na úpravu, uloženie neprepíše novšiu verziu.
* Súbory .md: nahratie v administrácii alebo cez FTP do uploads/j-notes/inbox (s hlavičkou slug, title, type, project, status, now); každá poznámka sa ukladá aj ako súbor do uploads/j-notes/export-<náhodný kód>. Priečinok je chránený pred prístupom z webu (.htaccess, náhodný názov priečinka, denná kontrola).

Rovnaký kód používa doplnok My Notes (rodina My) – generuje sa skriptom.

== Changelog ==

= 0.2.1 =
* Deaktivácia zruší plánovanú úlohu načítania súborov.

= 0.2.0 =
* Úprava neprepíše zmeny urobené medzitým (odškrtnutá úloha, záznam v denníku, načítanie zo súboru) – formulár ukáže vašu aj aktuálnu verziu.
* Súbor .md, ktorý sa nedá presunúť z inboxu, sa už nenačítava stále dokola (neprepisuje úpravy); upozornenie v administrácii.
* Klik na úlohu overí text riadku – po zmene poznámky sa neprepne iná úloha.
* Export a spracované súbory v priečinkoch s náhodným názvom, denná kontrola, či sa súbory nedajú stiahnuť z webu (server bez .htaccess).
* Percento úloh bez riadkov v bloku kódu, so symbolmi aj v číslovanom zozname.
* Kôš namiesto trvalého zmazania (obnovenie 30 dní), stránkovanie zoznamu, mazanie spracovaných súborov po 90 dňoch.
* Odkazy s podčiarkovníkmi v adrese, jedinečné kotvy nadpisov, žiadne varovanie PHP pri zlyhaní hľadania súborov.

= 0.1.0 =
* Prvá verzia.
