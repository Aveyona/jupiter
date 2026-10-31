<?php
/**
 * Príručka doplnku J-Fakturácia (podstránka J-Pomocník → J-Fakturácia).
 * Časť A – vydané doklady, časť B – prijaté faktúry, úhrady, banka, export.
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Fakturácia sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.11.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_invoices() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: faktúry pre slovenský e-shop', 'dashicons-media-spreadsheet', <<<'HTML'
<p class="jhelp-lead"><strong>J-Fakturácia vystavuje doklady z objednávok WooCommerce a eviduje aj faktúry od dodávateľov.</strong> Zálohová faktúra pri prevode, faktúra po zaplatení alebo vybavení, dobropis pri vrátení peňazí – automaticky alebo jedným klikom. K tomu prijaté faktúry, úhrady, pripojenie banky Fio s párovaním platieb a export pre účtovníka.</p>
<div class="jhelp-facts">
	<div><b>3</b><span>druhy vydaných dokladov: faktúra, zálohová, dobropis</span></div>
	<div><b>30 min</b><span>načítanie pohybov z banky Fio</span></div>
	<div><b>3</b><span>formáty exportu: Excel, Pohoda, PDF v ZIPe</span></div>
	<div><b>0</b><span>medzier v číselnom rade</span></div>
</div>
<h3>Príručka má dve časti</h3>
<ul>
<li><strong>Časť A – vydané doklady</strong> (kapitoly 3 až 12): ako a kedy doklady vznikajú, číslovanie, PDF, e-maily, upomienky, nákup na firmu.</li>
<li><strong>Časť B – prijaté faktúry, úhrady, banka, export</strong> (kapitoly 13 až 18).</li>
</ul>
<p class="jhelp-tip"><strong>Dobré vedieť:</strong> údaje vašej firmy aj zákazníka sa do dokladu uložia v čase vystavenia. Neskoršia zmena nastavení alebo adresy zákazníka už vystavené doklady nezmení.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>Jupiter → Fakturácia</strong> (ak je zlúčenie doplnkov Jupiter vypnuté, ako samostatná položka <strong>J-Fakturácia</strong>). Vidí ho administrátor a správca obchodu.</p>
<table class="jhelp-table">
<thead><tr><th>Stránka</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Vydané faktúry</strong></td><td>Faktúry, zálohové faktúry a dobropisy – zoznam, filter, detail, PDF, e-mail, upomienka.</td></tr>
<tr><td><strong>Prijaté faktúry</strong></td><td>Faktúry od dodávateľov s prílohou, splatnosťou a úhradou.</td></tr>
<tr><td><strong>Dodávatelia</strong></td><td>Adresár dodávateľov (IČO, DIČ, IBAN, splatnosť, kategória).</td></tr>
<tr><td><strong>Banka</strong></td><td>Pohyby na účte, Na spárovanie, pripojenie účtu Fio. Číslo pri položke = pohyby čakajúce na spárovanie.</td></tr>
<tr><td><strong>Export</strong></td><td>Doklady za obdobie pre účtovníka.</td></tr>
<tr><td><strong>Nastavenia</strong></td><td>Dodávateľ, doklady a číslovanie, e-maily a Môj účet, nákup na firmu, importy.</td></tr>
</tbody>
</table>
<p>V detaile objednávky je box <strong>Doklady (J-Fakturácia)</strong>, v zozname objednávok stĺpec <em>Faktúra</em> a hromadná akcia <em>Vystaviť faktúry</em>. Doklady vidno aj v J-Objednávkach.</p>
HTML
	);

	$s[] = array( 'start', 'A1 · Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Údaje firmy.</strong> <em>Nastavenia → Dodávateľ</em>: obchodné meno, adresa, IČO, DIČ, zápis v registri, text pre neplatiteľa DPH, IBAN a splatnosť (dni). Bez obchodného mena, IČO a IBAN zoznam faktúr upozorní, čo chýba.</li>
<li><strong>Postup fakturácie.</strong> <em>Nastavenia → Doklady a číslovanie</em>: zvoľte odporúčané nastavenie (tlačidlo hore) alebo režim ručne – <em>Manuálny</em> (faktúru vystavujete vy) alebo <em>Automatický</em> (vznikne sama).</li>
<li><strong>Číslovanie.</strong> Vlastný ročný rad (2026001, ZF2026001, D2026001) alebo rovnaké ako číslo objednávky. Pri prechode z iného programu zadajte <em>Pokračovať od čísla</em>.</li>
<li><strong>E-maily.</strong> <em>Nastavenia → E-maily a Môj účet</em>: ktoré doklady sa zákazníkovi pošlú hneď po vystavení a či si ich stiahne v Môj účet.</li>
<li><strong>Skúška.</strong> V detaile objednávky v boxe Doklady kliknite <em>Vystaviť faktúru</em> a otvorte PDF.</li>
</ol>
HTML
	);

	$s[] = array( 'druhy', 'A2 · Druhy dokladov', 'dashicons-media-document', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Doklad</th><th>Kedy</th><th>Čo obsahuje</th></tr></thead>
<tbody>
<tr><td><strong>Zálohová faktúra (ZF)</strong></td><td>Pri platbe prevodom, kým zákazník nezaplatí.</td><td>Výzva na platbu: IBAN, variabilný symbol, splatnosť, QR kód PAY by square. Nie je daňovým dokladom.</td></tr>
<tr><td><strong>Faktúra (F)</strong></td><td>Po zaplatení alebo vybavení objednávky.</td><td>Daňový doklad. Pri zaplatenej objednávke pečiatka UHRADENÉ, pri neuhradenej QR kód.</td></tr>
<tr><td><strong>Dobropis (D)</strong></td><td>Pri vrátení peňazí, zrušenej objednávke alebo ručne.</td><td>Záporné položky, odkaz na faktúru, najviac do výšky faktúry.</td></tr>
</tbody>
</table>
<p>K objednávke je vždy najviac jedna platná faktúra a jedna zálohová faktúra; dobropisov môže byť viac. Stornovaný doklad ostáva v evidencii a jeho číslo sa už nepoužije.</p>
<p><strong>Dobierka:</strong> na faktúre je poplatok za dobierku ako samostatná položka (názov v nastaveniach), text „Úhrada dobierkou pri prevzatí zásielky“ a žiadny QR kód.</p>
HTML
	);

	$s[] = array( 'kedy', 'A3 · Kedy doklad vznikne', 'dashicons-backup', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Platba</th><th>Zálohová faktúra</th><th>Faktúra (automatický režim)</th></tr></thead>
<tbody>
<tr><td><strong>Prevod na účet</strong></td><td>Hneď, keď objednávka prejde do stavu Čaká na platbu (ak je zapnutá).</td><td>Po zaplatení alebo pri vybavení – podľa voľby <em>Ostrá faktúra pri prevode</em>.</td></tr>
<tr><td><strong>Karta (J-Platby)</strong></td><td>—</td><td>Pri vybavení; úhrada kartou sa k faktúre zapíše sama.</td></tr>
<tr><td><strong>Dobierka</strong></td><td>—</td><td>Pri vybavení; faktúra je neuhradená, uhradí sa po doručení.</td></tr>
<tr><td><strong>Osobný odber, iné</strong></td><td>—</td><td>Pri vybavení.</td></tr>
</tbody>
</table>
<ul>
<li><strong>Vybavená</strong> = stav z nastavenia <em>Stav „vybavená“</em> (napr. Vybavené).</li>
<li><strong>Manuálny režim:</strong> faktúru vystavíte tlačidlom (box Doklady, J-Objednávky, hromadná akcia). Zálohová faktúra pri prevode môže vznikať automaticky.</li>
<li><strong>Zrušená objednávka</strong> stornuje neuhradenú zálohovú faktúru.</li>
<li><strong>Zálohová faktúra nevznikne</strong>, ak objednávka už má faktúru (napr. pri zmene stavu späť na Čaká na platbu).</li>
<li><strong>Prevod odoslaný pred zaplatením</strong> (Čaká na platbu → Vybavená): faktúra je neuhradená a do objednávky sa zapíše poznámka; uhradí ju platba z banky alebo tlačidlo <em>Označiť ako uhradený</em>.</li>
<li>Kým beží doplnok Woo Faktura (režim prechodu), J-Fakturácia automaticky nič nevystavuje.</li>
</ul>
<p class="jhelp-tip"><strong>Bezpečnosť:</strong> chyba pri vystavení dokladu, PDF alebo e-mailu nikdy nezablokuje objednávku ani pokladňu – zapíše sa do poznámky objednávky.</p>
HTML
	);

	$s[] = array( 'cislovanie', 'A4 · Číslovanie', 'dashicons-editor-ol', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Spôsob</th><th>Príklad</th><th>Poznámka</th></tr></thead>
<tbody>
<tr><td><strong>Vlastný ročný rad</strong></td><td>2026001, ZF2026001, D2026001</td><td>Každý druh má vlastný rad, v novom roku začína od 1. Formát: <code>{RRRR}</code> rok, <code>{RR}</code> rok dvojmiestne, <code>{nnn}</code>/<code>{nnnn}</code> poradie.</td></tr>
<tr><td><strong>Rovnaké ako číslo objednávky</strong></td><td>20260001, druhý doklad 20260001-2</td><td>Ako doplnok Woo Faktura.</td></tr>
</tbody>
</table>
<ul>
<li><strong>Variabilný symbol</strong> = číslo objednávky (len číslice, najviac 10). Podľa neho sa párujú platby v banke.</li>
<li><strong>Pokračovať od čísla</strong> – poradové číslo ďalšieho dokladu v tomto roku (napr. pri prechode z iného programu). Platí len v roku, keď ho zadáte; v novom roku rad začne od 1.</li>
<li>Rad je bez medzier aj pri dvoch objednávkach v tej istej sekunde.</li>
</ul>
<h3>Pregenerovanie</h3>
<p>Tlačidlo <em>Pregenerovať</em> vytvorí faktúru alebo zálohovú faktúru znova z aktuálnej objednávky (oprava adresy, položiek, dopravy). Podľa nastavení:</p>
<ul>
<li><strong>Nové číslo</strong> – pôvodný doklad sa stornuje, vznikne nový; úhrady (banka, karta) sa presunú na nový doklad.</li>
<li><strong>Pôvodné číslo</strong> – údaje dokladu sa prepíšu.</li>
<li><strong>Dátum</strong> dnešný alebo pôvodný (pri pôvodnom dátume sa nové číslo berie z radu pôvodného roka).</li>
</ul>
<p>Faktúra s dobropisom sa nepregeneruje – vystavte ďalší dobropis.</p>
HTML
	);

	$s[] = array( 'box', 'A5 · Box Doklady v objednávke', 'dashicons-feedback', <<<'HTML'
<p>V detaile objednávky vpravo hore sú všetky doklady objednávky so stavom, dátumom a sumou a tlačidlá:</p>
<ul>
<li><strong>Vystaviť faktúru</strong>, <strong>Vystaviť zálohovú faktúru</strong> (ak ešte nie sú), <strong>Vystaviť dobropis</strong> (ak má objednávka faktúru a nie je celá dobropisovaná). Dobropis je na vrátenú sumu, ak má objednávka vrátenie bez dobropisu, inak na celú faktúru.</li>
<li><strong>Pregenerovať</strong> pri každej platnej faktúre a zálohovej faktúre.</li>
<li>Pod tlačidlami je krátky popis nastaveného postupu fakturácie.</li>
<li>Červené <strong>„Faktúra ostáva platná“</strong> – objednávka je zrušená, vrátená alebo má vrátenie peňazí bez dobropisu.</li>
</ul>
<p>Klik na číslo dokladu otvorí jeho detail: <em>Otvoriť PDF</em>, <em>Stiahnuť PDF</em>, <em>Poslať e-mailom</em>, <em>Poslať upomienku</em> (po splatnosti), <em>Označiť ako uhradený</em> / <em>Zrušiť úhradu</em>, <em>Stornovať</em> a úhrady dokladu.</p>
HTML
	);

	$s[] = array( 'pdf', 'A6 · PDF a QR kód', 'dashicons-pdf', <<<'HTML'
<ul>
<li>PDF obsahuje logo (nastavenie <em>Logo na doklade</em> alebo logo webu), dodávateľa a odberateľa, položky s jednotkou (z J-Skladu napr. h, kg), sumy, text pre neplatiteľa DPH a pätu.</li>
<li><strong>QR kód PAY by square</strong> pri neuhradenej faktúre a zálohovej faktúre s IBAN v eurách: zákazník ho naskenuje v bankovej aplikácii a platba sa vyplní sama (suma, IBAN, variabilný symbol, príjemca).</li>
<li>Po <strong>čiastočnej úhrade</strong> obsahuje QR kód aj PDF len zostávajúcu sumu („Uhradené … · zostáva uhradiť …“). Pri doklade po splatnosti je v QR kóde dnešný dátum úhrady.</li>
<li><strong>Pečiatka</strong> UHRADENÉ (s dátumom), VRÁTENÉ pri dobropise, STORNOVANÝ DOKLAD.</li>
<li>„Uhradené na základe zálohovej faktúry“ sa uvedie, len ak je faktúra aj zálohová faktúra uhradená.</li>
</ul>
HTML
	);

	$s[] = array( 'emaily', 'A7 · E-maily, upomienky a Môj účet', 'dashicons-email-alt', <<<'HTML'
<ul>
<li><strong>Automaticky:</strong> podľa <em>Nastavenia → E-maily a Môj účet</em> sa zálohová faktúra, faktúra a dobropis pošlú hneď po vystavení e-mailom s PDF (na pozadí, cez poštu WooCommerce).</li>
<li><strong>Ručne:</strong> v detaile dokladu <em>Poslať e-mailom</em> (dátum odoslania sa uloží).</li>
<li>E-mail k zálohovej faktúre a k neuhradenej faktúre obsahuje platobné údaje – sumu (zostávajúcu po čiastočnej úhrade), IBAN, variabilný symbol a splatnosť.</li>
<li>Hromadné vystavenie a pregenerovanie e-maily neposiela.</li>
</ul>
<h3>Upomienky</h3>
<ol class="jhelp-steps">
<li><em>Vydané faktúry</em> → stav <strong>Po splatnosti</strong> – neuhradené doklady po dátume splatnosti (dobierky sa sem nepočítajú).</li>
<li>Otvorte doklad a kliknite <strong>Poslať upomienku</strong>. Zákazník dostane e-mail „Upomienka: faktúra č. …“ s PDF a platobnými údajmi.</li>
<li>Odoslanie sa zapíše do poznámky objednávky; tlačidlo sa potom volá <em>Poslať upomienku znova</em>.</li>
</ol>
<h3>Môj účet</h3>
<p>Ak je zapnuté <em>Doklady v Môj účet</em>, zákazník si v Môj účet → Objednávky stiahne svoje doklady (aj prevzaté z Woo Faktury, ak majú pôvodné PDF). Odkaz funguje len pre majiteľa objednávky alebo s kľúčom objednávky.</p>
HTML
	);

	$s[] = array( 'firma', 'A8 · Nákup na firmu', 'dashicons-building', <<<'HTML'
<ul>
<li>Po zapnutí <em>Nastavenia → Nákup na firmu → Polia pre firmu v pokladni</em> sa v pokladni (blokovej aj klasickej) zobrazí <strong>Nakupujem na firmu</strong>; po zaškrtnutí názov firmy, IČO, DIČ a IČ DPH.</li>
<li>Voľba <em>Povinné pri nákupe na firmu</em> vyžaduje názov a IČO. Tvar IČO a IČ DPH sa kontroluje.</li>
<li>Údaje sú aj v Môj účet → Adresy, v detaile objednávky, v e-mailoch a prenesú sa na doklady.</li>
<li>Kým beží Woo Faktura, polia sa nepridávajú (pridáva ich ona).</li>
</ul>
HTML
	);

	$s[] = array( 'zoznam', 'A9 · Prehľad vydaných dokladov', 'dashicons-list-view', <<<'HTML'
<p><em>Vydané faktúry</em>: výber druhu (faktúry, zálohové faktúry, dobropisy), hľadanie podľa čísla, odberateľa a e-mailu, rok a stav – <strong>Vystavená, Po splatnosti, Uhradená, Stornovaná</strong>. Stĺpce: číslo, odberateľ, objednávka, dátum vystavenia, splatnosť, suma, stav.</p>
<p>Stav <em>po splatnosti</em> je oranžový; dobierka sa ako po splatnosti nezobrazuje (platí sa pri prevzatí).</p>
HTML
	);

	$s[] = array( 'zrusene', 'A10 · Vrátenie peňazí, zrušené objednávky, dobierka', 'dashicons-undo', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Situácia</th><th>Čo urobí J-Fakturácia</th></tr></thead>
<tbody>
<tr><td><strong>Vrátenie peňazí (Refund) vo WooCommerce</strong></td><td>Pri zapnutom <em>Dobropis pri vrátení peňazí</em> vystaví dobropis na vrátenú sumu.</td></tr>
<tr><td><strong>Vrátenie pred vystavením faktúry</strong></td><td>Po vystavení faktúry doplní dobropis (alebo upozornenie v boxe Doklady).</td></tr>
<tr><td><strong>Zrušená objednávka s faktúrou</strong></td><td>Pri zapnutom automatickom dobropise vystaví dobropis na zostatok faktúry, inak zapíše upozornenie „Faktúra ostáva platná“.</td></tr>
<tr><td><strong>Zrušená objednávka so zálohovou faktúrou</strong></td><td>Neuhradenú zálohovú faktúru stornuje.</td></tr>
<tr><td><strong>Dobierka doručená (Packeta)</strong></td><td>Pri zapnutom <em>Dobierka uhradená po doručení</em> označí faktúru ako uhradenú (keď J-Doprava ohlási doručenie). Pri inom prepravcovi označte ručne.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'dodavatelia', 'B1 · Dodávatelia', 'dashicons-groups', <<<'HTML'
<ul>
<li><em>Dodávatelia</em> – adresár s IČO, DIČ, IČ DPH, adresou, IBAN, splatnosťou a kategóriou.</li>
<li><strong>Načítanie podľa IČO:</strong> pri novom dodávateľovi zadajte IČO – údaje firmy sa načítajú z Registra právnických osôb (Štatistický úrad SR). Ak register neodpovedá, vyplňte ich ručne.</li>
<li>IČO kratšie ako 8 číslic sa doplní nulami zľava (123456 = 00123456), aby nevznikali dvojití dodávatelia.</li>
<li><strong>Detail dodávateľa:</strong> jeho faktúry, neuhradené sumy a príjemky z J-Skladu.</li>
<li>Dodávateľa používa aj príjemka v J-Sklade (našepkávač).</li>
</ul>
HTML
	);

	$s[] = array( 'prijate', 'B2 · Prijaté faktúry', 'dashicons-download', <<<'HTML'
<ol class="jhelp-steps">
<li><em>Prijaté faktúry → + Pridať</em>: začnite písať názov alebo IČO dodávateľa a vyberte ho – doplní sa IČO, DIČ, IBAN, kategória a splatnosť. Nový dodávateľ sa uloží do adresára.</li>
<li>Číslo faktúry dodávateľa, variabilný symbol, dátumy, suma (aj „1 234,50“), kategória (Tovar, Služby, Hosting a softvér, Majetok…), poznámka.</li>
<li><strong>Príloha:</strong> PDF alebo fotka (JPG, PNG, WEBP) do 15 MB. Iné súbory sa neprijmú – kontroluje sa obsah, nie len prípona.</li>
</ol>
<ul>
<li>Filtre <em>Neuhradené</em>, <em>Po splatnosti</em>, <em>Uhradené</em> a karta <em>Splatné do 7 dní</em>.</li>
<li><strong>J-Sklad:</strong> z prijatej faktúry vytvoríte príjemku, z príjemky prijatú faktúru.</li>
<li>Faktúry prevzaté z e-mailu (J-Pošta) sa uložia rovnako a hneď sa spárujú s platbou v banke.</li>
<li>Prílohy sú v chránenom priečinku a otvárajú sa len cez administráciu.</li>
<li>Zmazanie prijatej faktúry uvoľní jej platbu v banke na spárovanie.</li>
</ul>
HTML
	);

	$s[] = array( 'uhrady', 'B3 · Úhrady', 'dashicons-money-alt', <<<'HTML'
<ul>
<li>Každý doklad môže mať <strong>viac úhrad</strong> (čiastočné platby) s dátumom, spôsobom (prevodom, v hotovosti, kartou, zápočtom, iné) a poznámkou.</li>
<li>Stav <strong>Uhradená</strong> nastane, keď súčet úhrad dosiahne sumu dokladu; inak doklad ukazuje zostávajúcu sumu.</li>
<li>Úhrady vznikajú ručne (box Úhrady v detaile, tlačidlo <em>Označiť ako uhradený</em>), z banky (párovanie), z karty (J-Platby) a z dobierky (Packeta doručila).</li>
<li><em>Zrušiť úhradu</em> zmaže ručné úhrady; úhrady z banky sa rušia zrušením spárovania. Zmazaná úhrada z banky vráti pohyb do Na spárovanie.</li>
<li>Stornovanie dokladu uvoľní jeho úhrady z banky na spárovanie.</li>
</ul>
HTML
	);

	$s[] = array( 'banka', 'B4 · Banka Fio a párovanie platieb', 'dashicons-bank', <<<'HTML'
<h3>Pripojenie</h3>
<ol class="jhelp-steps">
<li>V internetbankingu Fio: Nastavenia → API → Pridať token, druh <em>Sledovanie účtu</em> (len čítanie, platí 180 dní).</li>
<li><em>Banka → Účty</em>: vložte token, zvoľte od kedy načítať pohyby a kliknite <em>Pripojiť a načítať pohyby</em>.</li>
<li>Voľby: <em>Automaticky</em> (načítanie a párovanie každých 30 minút), <em>Upozornenia</em> (denný e-mail), <em>Objednávky</em> (platba za objednávku ju označí ako zaplatenú).</li>
</ol>
<h3>Párovanie</h3>
<ul>
<li><strong>Príjem</strong> s variabilným symbolom vydanej alebo zálohovej faktúry sa k nej zapíše ako úhrada. Platba za objednávku bez faktúry sa zapíše do objednávky (a pri voľbe <em>Objednávky</em> ju označí ako zaplatenú – len pri presnej sume).</li>
<li><strong>Výdaj</strong> s variabilným symbolom prijatej faktúry (alebo s IBAN dodávateľa a presnou sumou) ju uhradí.</li>
<li>Porovnáva sa aj mena (CZK sa nespáruje s eurovou faktúrou).</li>
<li><strong>Dvojitá platba</strong> – platba k objednávke, ktorá je už zaplatená, zrušená alebo vrátená, sa nespáruje: ostane v <em>Na spárovanie</em> s poznámkou, aby ste mohli peniaze vrátiť.</li>
<li>Bankové poplatky, transakčná daň a úroky sa označia ako ignorované.</li>
</ul>
<h3>Na spárovanie</h3>
<p>Pohyby bez istej zhody. Kliknite na navrhnutý doklad, alebo zadajte číslo faktúry či objednávky a <em>Spárovať</em> (text bez čísla sa nehľadá podľa VS). Poplatky a prevody medzi účtami označte <em>Ignorovať</em>. Spárovanie sa dá zrušiť. Jeden pohyb sa nedá spárovať dvakrát. <em>Skúsiť znova spárovať automaticky</em> prejde nespárované pohyby po pridaní faktúr.</p>
<h3>Upozornenia</h3>
<p>V administrácii sa zobrazí upozornenie a (pri zapnutej voľbe) raz denne príde e-mail, keď:</p>
<ul>
<li>načítanie zlyhá (napr. neplatný token),</li>
<li>pohyby sa nenačítali viac ako 6 hodín (WP-Cron nebeží),</li>
<li>token vyprší do 14 dní alebo už vypršal,</li>
<li>Fio nevydal staršie pohyby (cez API len 90 dní) – treba odblokovať históriu v internetbankingu,</li>
<li>čakajú platby na spárovanie (len v e-maile).</li>
</ul>
HTML
	);

	$s[] = array( 'export', 'B5 · Export pre účtovníka', 'dashicons-database-export', <<<'HTML'
<p><em>Export</em> – obdobie podľa dátumu vystavenia (predvolene minulý mesiac), druhy dokladov a formát:</p>
<table class="jhelp-table">
<tbody>
<tr><td><strong>CSV (Excel)</strong></td><td>Tabuľka dokladov; sumy ako čísla, ochrana pred vzorcami.</td></tr>
<tr><td><strong>XML pre Pohodu</strong></td><td>Import vydaných faktúr, zálohových faktúr a dobropisov do účtovníctva Pohoda. Riadky so zľavou, ktoré sa nedajú presne rozdeliť na jednotkovú cenu, sa pošlú ako 1 × celá suma – bez centových rozdielov.</td></tr>
<tr><td><strong>ZIP s PDF</strong></td><td>Všetky PDF dokladov za obdobie.</td></tr>
</tbody>
</table>
<p>Doklady prevzaté z Woo Faktury sa predvolene nezahŕňajú. J-Účtovníctvo zaúčtuje doklady samo – export potrebujete pre externého účtovníka.</p>
HTML
	);

	$s[] = array( 'import', 'B6 · Prechod z Woo Faktury', 'dashicons-migrate', <<<'HTML'
<ol class="jhelp-steps">
<li><em>Nastavenia → Importy údajov</em>: sprievodca zistí doklady Woo Faktury, prevezme IČO/DIČ a splatnosť.</li>
<li>Skúšobný beh bez zápisu ukáže, čo sa prevezme.</li>
<li>Import po dávkach: faktúry, zálohové faktúry a dobropisy s pôvodným číslom, dátumami a PDF.</li>
<li>Pri číslovaní nastavte <em>Pokračovať od čísla</em> podľa posledného čísla Woo Faktury.</li>
<li>Kým Woo Faktura beží (režim prechodu), J-Fakturácia nevystavuje automaticky a nepridáva firemné polia. Po jej vypnutí prevezme vystavovanie.</li>
</ol>
<p>Opakované spustenie doplní len nové doklady. Doklad, ktorý Woo Faktura vystavila po poslednom importe, sa pri vystavení prevezme – druhý sa nevystaví.</p>
HTML
	);

	$s[] = array( 'nastavenia', 'Všetky nastavenia', 'dashicons-admin-settings', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Záložka</th><th>Obsah</th></tr></thead>
<tbody>
<tr><td><strong>Dodávateľ</strong></td><td>Obchodné meno, adresa, IČO, DIČ, IČ DPH, zápis v registri, neplatiteľ DPH a text, IBAN (inak z WooCommerce → Priamy bankový prevod), SWIFT, banka, splatnosť, e-mail, telefón, web.</td></tr>
<tr><td><strong>Doklady a číslovanie</strong></td><td>Režim fakturácie, stav „vybavená“, ostrá faktúra pri prevode, pregenerovanie (číslo, dátum), číslovanie a formáty, pokračovať od čísla, zálohová faktúra pri prevode, dobropis pri vrátení peňazí, dobierka uhradená po doručení, kódy platieb prevodom, položka dobierky, vystavil, logo, päta.</td></tr>
<tr><td><strong>E-maily a Môj účet</strong></td><td>Poslať zálohovú faktúru / faktúru / dobropis po vystavení, doplnkový text e-mailu, doklady v Môj účet.</td></tr>
<tr><td><strong>Nákup na firmu</strong></td><td>Polia pre firmu v pokladni, povinné pri nákupe na firmu.</td></tr>
<tr><td><strong>Importy údajov</strong></td><td>Sprievodca prevzatím dokladov z Woo Faktury.</td></tr>
</tbody>
</table>
<p>Nastavenia banky (token, automatické načítanie, upozornenia, objednávky) sú v <em>Banka → Účty</em>. Uloženie mení len údaje otvorenej záložky.</p>
HTML
	);

	$s[] = array( 'problemy', 'Keď niečo nejde', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Problém</th><th>Príčina a riešenie</th></tr></thead>
<tbody>
<tr><td><strong>Faktúra nevznikla</strong></td><td>Manuálny režim, objednávka nie je v stave „vybavená“, beží Woo Faktura, alebo chyba – dôvod je v poznámke objednávky.</td></tr>
<tr><td><strong>„Práve sa vystavuje iný doklad“</strong></td><td>Dve požiadavky naraz (napr. platobná brána a klik). Skúste o chvíľu znova.</td></tr>
<tr><td><strong>Chýba QR kód</strong></td><td>Doklad je uhradený, je to dobierka alebo dobropis, chýba IBAN v nastaveniach, alebo mena nie je euro.</td></tr>
<tr><td><strong>Faktúra je neuhradená, hoci zákazník zaplatil</strong></td><td>Platba z banky čaká v Na spárovanie (iný VS, iná suma) – spárujte ju, alebo kliknite Označiť ako uhradený.</td></tr>
<tr><td><strong>Platba sa nespárovala – „dvojitá platba?“</strong></td><td>Objednávka už bola zaplatená, zrušená alebo vrátená. Skontrolujte a prípadne vráťte peniaze; pohyb potom ignorujte.</td></tr>
<tr><td><strong>Upozornenie „token Fio vypršal“</strong></td><td>Vytvorte nový token v internetbankingu a vložte ho do Banka → Účty.</td></tr>
<tr><td><strong>„Pohyby sa nenačítali od …“</strong></td><td>WP-Cron nebeží (web bez návštev) – nastavte na hostingu spúšťanie wp-cron.php alebo kliknite Načítať pohyby teraz.</td></tr>
<tr><td><strong>Číslo faktúry preskočilo</strong></td><td>Skontrolujte Pokračovať od čísla (platí len v roku zadania) a či nebol doklad stornovaný.</td></tr>
<tr><td><strong>Zákazník nedostal doklad</strong></td><td>E-mail je vypnutý v nastaveniach, alebo zlyhalo odosielanie pošty webu (J-Pošta). Pošlite ho z detailu dokladu.</td></tr>
<tr><td><strong>Príloha prijatej faktúry sa neuložila</strong></td><td>Súbor nie je PDF ani obrázok, alebo má viac ako 15 MB.</td></tr>
<tr><td><strong>V Pohode iná suma</strong></td><td>Exportujte znova (od verzie 0.11 sa riadky so zľavou posielajú bez zaokrúhlenia).</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'technicke', 'Technické údaje (pre vývojára)', 'dashicons-editor-code', <<<'HTML'
<h3>Tabuľky</h3>
<ul>
<li><code>{prefix}j_invoices</code> – doklady (<code>kind</code>: issued, proforma, creditnote, received), číslo, rok, poradie (unikátny kľúč kind+year+seq), stav, dátumy, VS, mena, suma, snímka údajov v <code>data</code> (JSON), zdroj (napr. woo-faktura).</li>
<li><code>{prefix}j_invoices_payments</code> – úhrady (doklad, dátum, suma, spôsob, zdroj, ref).</li>
<li><code>{prefix}j_invoices_banktx</code> – pohyby z banky (unikátne podľa účtu a ID pohybu), stav new / pairing / matched / ignored / old.</li>
<li><code>{prefix}j_invoices_partners</code> – dodávatelia.</li>
</ul>
<h3>Funkcie</h3>
<ul>
<li><code>jinv_create_doc( $order, $kind, $opts )</code>, <code>jinv_regenerate_doc( $id )</code>, <code>jinv_by_order( $order_id, $kind )</code>, <code>jinv_docs_for_order( $order_id )</code>.</li>
<li><code>jinv_add_payment( $doc_id, $amount, $args )</code>, <code>jinv_delete_payment( $id )</code>, <code>jinv_remaining( $doc )</code>, <code>jinv_received_create( $args )</code>.</li>
<li><code>jinv_send_doc( $id, $to, $reminder )</code>, <code>jinv_pdf_url( $id )</code>.</li>
</ul>
<h3>Háky</h3>
<ul>
<li>Akcie: <code>jinv_document_created</code>, <code>jinv_document_regenerated</code>, <code>jinv_payment_added</code>, <code>jinv_payment_deleted</code>, <code>jinv_bank_paired</code>, <code>jinv_bank_paired_order</code>.</li>
<li>Filtre: <code>jinv_presets</code>, <code>jinv_transfer_methods</code>, <code>jinv_cod_methods</code>, <code>jinv_pay_methods</code>, <code>jinv_expense_categories</code>, <code>jinv_bank_order_by_number</code>.</li>
<li>Väzby: <code>jdp_status_changed</code> (dobierka po doručení), <code>jsk_unit</code> (jednotka položky).</li>
</ul>
<p>PDF: tFPDF s písmom DejaVu, QR kód chillerlan/php-qrcode, PAY by square 1.2.0. Banka: Fio API (jeden dopyt za 30 s), WP-Cron <code>jinv_bank_cron</code> každých 30 minút. E-maily cez Action Scheduler.</p>
HTML
	);

	return $s;
}
