=== J-Doprava ===
Contributors: aveyona
Tags: woocommerce, shipping, packeta, zasilkovna, jupiter
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.3.1
License: GPLv2 or later

Doprava pre WooCommerce: Packeta – výdajné miesta a Z-BOXy, doručenie na adresu, podanie zásielok, štítky a sledovanie. Súčasť rodiny Jupiter.

== Description ==

* Spôsoby dopravy Packeta – výdajné miesto / Z-BOX a Packeta – doručenie na adresu: cena, doprava zadarmo od sumy, maximálna hmotnosť.
* Výber výdajného miesta na mape Packeta (widget v6) v blokovom aj klasickom košíku, kontrola miesta pred odoslaním objednávky, telefón pri doručení na adresu.
* Podanie zásielky cez Packeta API z detailu objednávky, hromadne zo zoznamu objednávok alebo zo stránky Zásielky; hmotnosť z produktov + obal, dobierka pri platbe dobierkou.
* Štítky PDF (A6 na A4, A6, A7), zrušenie zásielky pred odovzdaním.
* Sledovanie stavu každú hodinu, odkaz na sledovanie v e-maile a v Mojom účte, doručená objednávka → Vybavená.
* API heslo sa ukladá zašifrované.

* Automatické podanie zásielky pri stave Spracováva sa (voliteľné), spätná zásielka (vrátenie / reklamácia) s kódom pre zákazníka.
* Slovenská pošta: export objednávok do ePodacieho hárku (XML), odosielateľ a dobierka na účet.
* Osobný odber: e-mail „Objednávka je pripravená na odber“ z detailu alebo hromadne.

Pripravuje sa: výplaty dobierok a ich párovanie s bankou, ďalší prepravcovia.

== Changelog ==

= 0.3.1 =
* Dobierka pre J-Fakturáciu podľa nastavenia J-Dopravy (jedna definícia). Odinštalovanie zmaže nastavenia s kľúčom Packety.

= 0.3.0 =
* Oprava: dobierka sa posiela do Packety aj pri objednávke v stave Spracováva sa (WooCommerce ju považuje za zaplatenú) – predtým by zásielka odišla bez dobierky.
* Oprava: upozornenie, keď sa API heslo nedá prečítať (zmena bezpečnostných kľúčov WordPressu) – Packeta vtedy zmizne z košíka.
* Oprava: sledovanie kontroluje len zásielky na ceste (aj pri viac ako 100 zásielkach), poistka proti dvojitému podaniu, e-mail Objednávka odoslaná aj pri preskočení stavov, všetky formáty štítkov na stránke Zásielky.
* Nové: automatické podanie zásielky pri stave Spracováva sa (voliteľné, na pozadí).
* Nové: záložka Na tlač – podané zásielky s nevytlačeným štítkom; poznačenie vytlačených štítkov.
* Nové: spätná zásielka Packeta (vrátenie / reklamácia) s kódom pre zákazníka.
* Nové: Slovenská pošta – export do ePodacieho hárku (XML), záložka Slovenská pošta na stránke Zásielky.
* Nové: e-mail „Pripravené na osobný odber“ (akcia v objednávke, hromadná akcia).

= 0.2.0 =
* E-mail zákazníkovi „Objednávka odoslaná“ (WooCommerce → Nastavenia → E-maily) s odkazom na sledovanie, keď Packeta prevezme zásielku.

= 0.1.0 =
* Prvá verzia: Packeta – výdajné miesta a Z-BOXy, doručenie na adresu, podanie zásielok, štítky PDF, sledovanie stavu.
