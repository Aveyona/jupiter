=== J-Platby ===
Contributors: aveyona
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later

Platby kartou pre WooCommerce cez Stripe Checkout. Súčasť rodiny Jupiter.

== Description ==

* Platobná metóda „Platba kartou (Visa, Mastercard, Apple Pay, Google Pay)“ v blokovej aj klasickej pokladni.
* Zákazník platí na zabezpečenej stránke Stripe Checkout – údaje o karte e-shop nevidí ani neukladá.
* Potvrdenie platby webhookom s overením podpisu (a aj pri návrate zákazníka).
* Vrátenie peňazí priamo z objednávky; vrátenie urobené v Stripe sa zapíše do objednávky.
* Prehľad platieb s poplatkami a čistou sumou, testovací a ostrý režim, kľúče uložené zašifrované.
* Odkaz na platbu kartou z objednávky (aj pri prevode alebo objednávke z administrácie), export platieb do CSV, stav webhooku na nástenke Jupiter.
* Úhrada kartou sa zapíše k faktúre J-Fakturácie, poplatok Stripe do J-Účtovníctva (568 / 261).

== Changelog ==

= 0.2.0 =
* Oprava: upozornenie, keď sa tajný kľúč alebo tajomstvo webhooku nedá prečítať (zmena bezpečnostných kľúčov WordPressu) – platba kartou vtedy zmizne z pokladne.
* Oprava: platobná stránka Stripe platí len kým WooCommerce nezruší nezaplatenú objednávku (Držať zásoby) a objednávka sa nezruší, kým zákazník ešte môže platiť.
* Oprava: upozornenie na odmietnutý webhook; „Dostanete“ v prehľade platieb odpočíta vrátené peniaze; bloková pokladňa neponúkne kartu pod 0,50 €.
* Nové: odkaz na platbu kartou v detaile objednávky (Čaká na platbu, Pozastavené, Neúspešná) – platí 24 hodín.
* Nové: export platieb mesiaca do CSV, stav webhooku v nastaveniach a na nástenke Jupiter.

= 0.1.0 =
* Prvá verzia: Stripe Checkout, webhook, vrátenie peňazí, prehľad platieb, prepojenie s J-Fakturáciou a J-Účtovníctvom.
