=== J-Kupóny ===
Contributors: aveyona
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later

Evidencia zľavových kódov pre WooCommerce. Súčasť rodiny Jupiter.

== Description ==

* Nový kupón s automaticky vygenerovaným kódom (bez zameniteľných 0/O a 1/I, voliteľná predpona), alebo vlastný kód.
* Séria – viac kupónov s rovnakými podmienkami naraz, s názvom, exportom CSV, tlačou kartičiek a vypnutím celej série.
* Zľava v % alebo v € z košíka, doprava zadarmo (aj samotná), platnosť do dňa vrátane, počet použití, limit na zákazníka, minimálny nákup, e-mail, kategórie, produkty, kombinovanie, poznámka.
* Doprava zadarmo z kupónu pri každom spôsobe dopravy (Packeta z J-Doprava, pevná cena…).
* Evidencia so stránkovaním: stav, použitie, séria, vyhľadávanie, vypnutie a zapnutie jedným kliknutím; štatistika zliav za mesiac.
* Detail kupónu: všetky objednávky s kódom, poskytnutá zľava, tržby, duplikovanie.
* Kupóny sú bežné kupóny WooCommerce – fungujú v blokovom aj klasickom košíku a pokladni.

== Changelog ==

= 0.2.0 =
* Doprava zadarmo z kupónu funguje pri každom spôsobe dopravy vrátane Packety (J-Doprava); kupón len na dopravu zadarmo (zľava 0).
* Neplatný e-mail v obmedzení sa už potichu nezahodí (kupón by platil pre všetkých) – zobrazí sa chyba. Dátum platnosti v minulosti sa neprijme.
* Platnosť „do dňa vrátane“ ostane zachovaná aj po úprave kupónu vo WooCommerce.
* Série: názov, dlhší kód (predvolene 6 znakov), export CSV, tlač kódov, vypnutie a zapnutie celej série; jedinečné označenie série.
* Detail kupónu so všetkými objednávkami (zrušené označené), poskytnutou zľavou a tržbami; duplikovanie kupónu.
* Štatistika na stránke Kupóny: objednávky s kupónom, poskytnutá zľava a tržby za tento a minulý mesiac, najpoužívanejšie kódy.
* Formulár: kategórie, produkty, kombinovanie s inými kupónmi, predpona kódu.
* Nastavenia: dĺžka kódu, dĺžka kódu v sérii, predpona, doprava zadarmo, upozornenie na koniec platnosti.
* Evidencia so stránkovaním a rýchlym načítaním aj pri tisícoch kupónov; dlaždica na nástenke Jupiter.

= 0.1.0 =
* Prvá verzia: generovanie kódov, hromadné vytvorenie, evidencia s použitím a objednávkami.
