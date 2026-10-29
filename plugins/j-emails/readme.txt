=== J-Pošta ===
Contributors: aveyona
Tags: email, imap, smtp, woocommerce, jupiter
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.5.0
License: GPLv2 or later

E-mailová schránka obchodu priamo v administrácii WordPressu. Súčasť rodiny Jupiter.

== Description ==

Jupiter Email – pošta obchodu v administrácii WordPressu v štýle Gmailu:

* Konverzácie – odpovede (aj vaše odoslané) sú zoskupené do jedného vlákna, staršie správy zbalené.
* Ľavé menu: Doručené, S hviezdičkou, Odložené, Dôležité, Odoslané, Koncepty, Archív, Spam, Kôš a vlastné priečinky schránky.
* Rýchle akcie pri prejdení myšou (archivovať, odstrániť, prečítané, odložiť), hromadné akcie pre vybrané konverzácie, presun ťahaním do priečinka.
* Hviezdičky a Dôležité, odloženie konverzácie (vráti sa do Doručených ako neprečítaná), hľadanie v celej pošte.
* Okno Napísať vpravo dole (minimalizovať, celá obrazovka, viac okien), formátovanie textu, prílohy (aj presunutím súboru), našepkávanie adries, koncepty.
* Odpoveď priamo v konverzácii, odpovedať všetkým, preposlať s prílohami.
* Odosielanie zo schránky aj z aliasov (napr. eshop@, redakcia@) – odpoveď ide z adresy, na ktorú správa prišla.
* HTML správy v izolovanom rámci, obrázky z internetu skryté, kým ich nezobrazíte (alebo odosielateľovi nedôverujete natrvalo).
* Klávesové skratky ako v Gmaile (c, /, j, k, e, #, s, r, a, f…).

Faktúry z e-mailu: externý účet Google (Gmail) sa pripojí cez Google API len na čítanie; faktúry zo zvolených štítkov sa prevezmú do J-Fakturácie aj s PDF.

Pripája sa k schránke cez IMAP a SMTP, nepotrebuje rozšírenie php-imap. Heslo je uložené zašifrované. Poštu vidí len administrátor.

== Changelog ==

= 0.5.0 =
* Nový názov: J-Pošta (Jupiter Pošta), v menu prvá v skupine Jupiter.
* Faktúry z e-mailu: externý účet Google (Gmail API, OAuth, len čítanie) – výber štítkov s faktúrami, dodávateľ a kategória ku štítku, zoznam faktúr s PDF (suma, VS, obdobie a splatnosť sa vyčítajú zo správy), prevzatie do J-Fakturácie s PDF a automatickým spárovaním s platbou v banke.

= 0.4.0 =
* Šablóny e-mailov (J-Emails → Šablóny): vzhľad e-mailu s premennými ({obsah}, {meno}, {predmet}…), náhľad, úprava, obnovenie predvolených.
* Odosielanie podľa šablóny pre iné doplnky (jem_send_template): každému príjemcovi vlastný e-mail, limity za hodinu a počtu príjemcov, záznam odoslaných.

= 0.3.0 =
* Jupiter Email: nové rozhranie v štýle Gmailu – konverzácie, hviezdičky, Dôležité, Archív, Odložené, Spam, rýchle a hromadné akcie, presun ťahaním, okno Napísať s formátovaním a prílohami, koncepty, aliasy odosielateľa, našepkávanie adries, klávesové skratky.

= 0.2.1 =
* Bezpečnejšie zobrazenie HTML e-mailov, náhradné odoslanie pri zlyhaní SMTP s upozornením, šetrný presun do koša, diakritika v hľadaní.

= 0.2.0 =
* E-maily webu (objednávky, faktúry, registrácia) sa posielajú cez SMTP schránky – nepadajú do spamu. Odosielateľ z cudzej domény sa nahradí adresou schránky.
* Tlačidlo Poslať skúšobný e-mail v Nastaveniach.

= 0.1.1 =
* Ak nová schránka nemá priečinky Odoslané a Kôš, vytvoria sa automaticky. Test pripojenia vypíše priečinky schránky.

= 0.1.0 =
* Prvá verzia: schránka cez IMAP/SMTP, Doručená pošta, Odoslané, Kôš, čítanie, prílohy, napísať, odpovedať, preposlať, nastavenia s testom pripojenia.
