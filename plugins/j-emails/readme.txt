=== J-Emails ===
Contributors: aveyona
Tags: email, imap, smtp, woocommerce, jupiter
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.2.1
License: GPLv2 or later

E-mailová schránka obchodu priamo v administrácii WordPressu. Súčasť rodiny Jupiter.

== Description ==

Doručená pošta, Odoslané a Kôš ako samostatné stránky, čítanie správ (text aj HTML v izolovanom rámci, obrázky z internetu na požiadanie), prílohy, nová správa, odpoveď, odpoveď všetkým a preposlanie s prílohami. Pripája sa k schránke cez IMAP a SMTP; nepotrebuje rozšírenie php-imap. Heslo je uložené zašifrované. Poštu vidí len administrátor.

Pripravuje sa: HTML odpovede, šablóny odpovedí, prepojenie s Mailchimpom pre zákazníkov e-shopu.

== Changelog ==

= 0.2.1 =
* Bezpečnejšie zobrazenie HTML e-mailov, náhradné odoslanie pri zlyhaní SMTP s upozornením, šetrný presun do koša, diakritika v hľadaní.

= 0.2.0 =
* E-maily webu (objednávky, faktúry, registrácia) sa posielajú cez SMTP schránky – nepadajú do spamu. Odosielateľ z cudzej domény sa nahradí adresou schránky.
* Tlačidlo Poslať skúšobný e-mail v Nastaveniach.

= 0.1.1 =
* Ak nová schránka nemá priečinky Odoslané a Kôš, vytvoria sa automaticky. Test pripojenia vypíše priečinky schránky.

= 0.1.0 =
* Prvá verzia: schránka cez IMAP/SMTP, Doručená pošta, Odoslané, Kôš, čítanie, prílohy, napísať, odpovedať, preposlať, nastavenia s testom pripojenia.
