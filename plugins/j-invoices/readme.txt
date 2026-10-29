=== J-Fakturácia ===
Contributors: aveyona
Tags: woocommerce, faktúry, faktúra, slovensko, jupiter
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.10.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Faktúry pre slovenské e-shopy na WooCommerce. Súčasť rodiny doplnkov Jupiter.

== Popis ==

* Vydané faktúry z objednávok WooCommerce – automaticky (prevod na účet po zaplatení, dobierka a osobný odber po vybavení) alebo tlačidlom v objednávke.
* Ročný číselný rad bez medzier (predvolene RRRRnnn, napr. 2026001), variabilný symbol = číslo faktúry.
* Dátum vystavenia, dodania a splatnosti (predvolene 14 dní), text pre neplatiteľa DPH.
* Údaje dodávateľa a odberateľa sa uložia ako snímka – neskoršia zmena nastavení vystavené faktúry nemení.
* Prehľad faktúr s filtrom, stav vystavená / uhradená / po splatnosti / stornovaná, tlačový náhľad.

Pripravuje sa: PDF s QR kódom PAY by square, odoslanie zákazníkovi e-mailom po potvrdení, ručné faktúry, dobropisy, prijaté faktúry, export pre účtovníka.

== Changelog ==

= 0.10.0 =
* Dodávatelia: adresár s IČO, DIČ, IČ DPH, adresou, IBAN, splatnosťou a kategóriou; načítanie firmy z Registra právnických osôb podľa IČO; detail s faktúrami, neuhradenými sumami a príjemkami J-Skladu; dodávatelia z doterajších prijatých faktúr sa preniesli automaticky.
* Prijaté faktúry: výber dodávateľa našepkávačom (predvyplní údaje a splatnosť), IBAN na úhradu, filtre Neuhradené / Po splatnosti / Uhradené, karta Splatné do 7 dní, prepojenie s príjemkou J-Skladu (vytvorenie príjemky z faktúry aj faktúry z príjemky).
* Úhrady dokladov: viac úhrad k faktúre (čiastočné platby), dátum a spôsob úhrady; stav Uhradená podľa súčtu úhrad – pre prijaté aj vydané faktúry. Základ pre banku a pokladňu.

= 0.9.5 =
* Postup fakturácie v Nastaveniach: manuálny/automatický režim, stav „vybavená“ (napr. Vybavené a odoslané), ostrá faktúra pri prevode po zaplatení alebo pri vybavení, Pregenerovať faktúru (nové alebo pôvodné číslo, dnešný alebo pôvodný dátum), pokračovanie číselného radu od zadaného čísla; návod v J-Pomocníkovi.

= 0.9.4 =
* Dobropisy najviac do výšky faktúry, doklad sa bez zámku nevystaví, kompatibilita s HPOS, sumy v CSV ako čísla, ochrana vydaných dokladov.

= 0.9.3 =
* Ochrana pred dvojitými dokladmi: doklad, ktorý vystavila Woo Faktura po poslednom importe, sa prevezme (nevystaví sa druhý); zámok na objednávku proti súbežnému vystaveniu; po neúspešnom zápise sa použije už vystavený doklad.
* Chyba pri tvorbe dokladu, PDF alebo e-mailu nikdy nezablokuje objednávku ani pokladňu – zapíše sa do denníka WooCommerce (zdroj j-invoices) a do poznámky objednávky. E-maily s dokladmi sa posielajú na pozadí (Action Scheduler).
* Režim prechodu: kým beží Woo Faktura, J-Fakturácia nemení stavy dokladov ani IČO v adresách a hromadné vystavenie je vypnuté; spoľahlivejšie rozpoznanie Woo Faktury.
* Hromadné vystavenie faktúr: preskočí zrušené, nezaplatené a vrátené objednávky a neposiela e-maily.
* Dobierka: faktúra po vybavení je neuhradená (platí sa pri prevzatí), bez QR kódu, s textom „Úhrada dobierkou pri prevzatí zásielky“; nezobrazuje sa ako po splatnosti.
* Variabilný symbol = číslo objednávky (aj pri vlastnom číselnom rade a pri dokladoch s príponou -2).
* Vlastný rad: bez preskočených čísel pri súbežnom vystavení.
* Zálohová faktúra: „Objednávka zo dňa“ je dátum objednávky.
* Import z Woo Faktura: dobropisy s položkami z vrátení (záporné), skutočný dátum úhrady, upozornenie, ak k objednávke už je doklad J-Fakturácie.
* Export: doklady z Woo Faktura predvolene nezahrnuté; CSV chránené pred vzorcami v Exceli.

= 0.9.2 =
* Nákup na firmu aj v blokovej pokladni (WooCommerce Checkout block): „Nakupujem na firmu“, názov firmy, IČO, DIČ, IČ DPH – polia sa zobrazia po zaškrtnutí a uložia sa do fakturačných údajov objednávky.

= 0.9.1 =
* IBAN, BIC a banka: ak nie sú vyplnené v nastaveniach, použije sa prvý účet z WooCommerce → Platby → Priamy bankový prevod.

= 0.9.0 =
* Druhy dokladov: faktúra, zálohová faktúra, dobropis (z vrátenia peňazí vo WooCommerce alebo ručne). Box Doklady v objednávke so všetkými dokladmi a tlačidlami.
* Číslovanie: číslo dokladu = číslo objednávky (ako Woo Faktura; ďalší doklad toho istého druhu s príponou -2) alebo vlastný ročný rad pre každý druh (2026001, ZF2026001, D2026001). Sprievodca importom môže nastaviť číslovanie ako Woo Faktura.
* Automatika: prevod na účet – zálohová faktúra pri objednávke (Čaká na platbu), faktúra po zaplatení; ostatné platby – faktúra po vybavení; zrušená objednávka stornuje zálohovú faktúru; voliteľne dobropis pri vrátení peňazí. Hromadné vystavenie faktúr v zozname objednávok. Kým beží Woo Faktura, automatika aj automatické e-maily sú vypnuté.
* PDF dokladu (tFPDF, písmo DejaVu) s QR kódom PAY by square 1.2.0 (s názvom príjemcu), pečiatka Uhradené / Vrátené / Stornovaný doklad. Tlačidlá Otvoriť PDF a Stiahnuť PDF.
* E-maily: doklad s PDF zákazníkovi cez poštu WooCommerce – automaticky podľa nastavení (zálohová faktúra s platobnými údajmi, faktúra, dobropis) alebo tlačidlom Poslať e-mailom; dátum odoslania v detaile.
* Môj účet: zákazník si stiahne doklady k vlastnej objednávke (aj prevzaté z Woo Faktura) – v zozname objednávok aj v detaile.
* Export pre účtovníka: CSV (Excel), XML pre Pohodu (vydané faktúry, zálohové faktúry, dobropisy) a ZIP so všetkými PDF za obdobie.
* Nastavenia: nová záložka E-maily a Môj účet; výberové polia.

= 0.4.2 =
* Prevzatý doklad z Woo Faktura sa v detaile zobrazí ako pôvodné PDF (platný doklad s dodávateľom, ktorý bol v čase vystavenia); náhľad z údajov objednávky len ak PDF chýba.
* Sprievodca importom: tlačidlo „Pokračovať“ sa ukáže až po dokončení behu.

= 0.4.1 =
* Režim prechodu: kým beží Woo Faktura, J-Fakturácia nevystavuje faktúry automaticky (žiadne dvojité doklady); upozornenie vo Vydaných faktúrach.
* Automatika rozpozná dobierku aj cez ďalšie brány (napr. jetpack_custom_gateway) – faktúra až po vybavení.

= 0.4.0 =
* Nastavenia rozdelené do záložiek: Dodávateľ, Doklady a číslovanie, Nákup na firmu, Importy údajov. Uloženie mení len údaje otvorenej záložky.
* Importy údajov: sprievodca prevzatím faktúr, zálohových faktúr a dobropisov z doplnku Woo Faktura (zistenie, prevzatie IČO/DIČ/splatnosti, skúšobný beh bez zápisu, import po dávkach s priebehom). Opakované spustenie doplní len nové doklady.
* Prevzaté doklady majú pôvodné číslo a dátumy a tlačidlo Pôvodné PDF (Woo Faktura); vlastný číselný rad J-Fakturácie nimi nie je ovplyvnený.
* Vydané faktúry: výber druhu dokladu (faktúry / zálohové faktúry / dobropisy), zoradenie podľa dátumu vystavenia.
* Databáza v2 (stĺpce source a file) – aktualizuje sa automaticky.

= 0.3.0 =
* Nákup na firmu: v pokladni „Nakupujem na firmu“ + IČO, DIČ, IČ DPH (zobrazia sa po zaškrtnutí, kontrola tvaru IČO a IČ DPH), aj v Môj účet → Adresy, v detaile objednávky a v profile zákazníka; firemné údaje v adrese v e-mailoch. Ak beží Woo Faktura, polia sa nepridávajú.
* Dobierka na faktúre: pri platbe na dobierku sa poplatok z objednávky uvedie ako položka „Dobierka“ (názov nastaviteľný); ak poplatok chýba, pridá sa položka s cenou 0 €.

= 0.2.2 =
* IČO, DIČ a IČ DPH sa načítajú aj z polí doplnku Woo Faktura (billing_company_wi_id, _wi_tax, _wi_vat).

= 0.2.1 =
* Vydané faktúry, Prijaté faktúry a Nastavenia sú samostatné stránky v menu (bez záložiek).

= 0.2.0 =
* Prijaté faktúry: evidencia s prílohou (PDF/fotka), splatnosť, úhrady, súčet neuhradených.
* Doplnok premenovaný na J-Fakturácia.

= 0.1.1 =
* Návody v J-Pomocník (druhy dokladov, kedy vzniká faktúra, box v objednávke, číslovanie, pre zákazníka, nákup na firmu, Pohoda, prechod z Woo Faktura).

= 0.1.0 =
* Prvá verzia: vydané faktúry z objednávok, číselný rad, prehľad, náhľad, nastavenia dodávateľa.
