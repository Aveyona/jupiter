=== J-Platby ===
Contributors: aveyona
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later

Platby kartou pre WooCommerce cez Stripe Checkout. Súčasť rodiny Jupiter.

== Description ==

* Platobná metóda „Platba kartou (Visa, Mastercard, Apple Pay, Google Pay)“ v blokovej aj klasickej pokladni.
* Zákazník platí na zabezpečenej stránke Stripe Checkout – údaje o karte e-shop nevidí ani neukladá.
* Potvrdenie platby webhookom s overením podpisu (a aj pri návrate zákazníka).
* Vrátenie peňazí priamo z objednávky; vrátenie urobené v Stripe sa zapíše do objednávky.
* Prehľad platieb s poplatkami a čistou sumou, testovací a ostrý režim, kľúče uložené zašifrované.
* Úhrada kartou sa zapíše k faktúre J-Fakturácie, poplatok Stripe do J-Účtovníctva (568 / 261).

== Changelog ==

= 0.1.0 =
* Prvá verzia: Stripe Checkout, webhook, vrátenie peňazí, prehľad platieb, prepojenie s J-Fakturáciou a J-Účtovníctvom.
