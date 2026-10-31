<?php
/**
 * Príručka doplnku J-Sklad (podstránka J-Pomocník → J-Sklad).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Sklad sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.6.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_sklad() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: sklad nad produktmi WooCommerce', 'dashicons-archive', <<<'HTML'
<p class="jhelp-lead"><strong>J-Sklad dopĺňa WooCommerce o skutočnú skladovú evidenciu.</strong> Zásoba ostáva tam, kde ju poznáte – v produkte (<em>Zásoby → Množstvo na sklade</em>). J-Sklad k nej pridáva doklady (príjemky, výdajky, inventúry), úplnú históriu každej zmeny, nákupnú cenu a hodnotu skladu.</p>
<p>Nemusíte nič prenášať ani meniť spôsob práce: objednávky odpisujú tovar ako doteraz, ručná zmena v produkte funguje ako doteraz. J-Sklad pri každej z nich zapíše <strong>kto, kedy, prečo a o koľko</strong> zásobu zmenil.</p>
<div class="jhelp-facts">
	<div><b>4</b><span>druhy dokladov s vlastným číslovaním</span></div>
	<div><b>10</b><span>zdrojov pohybu v knihe pohybov</span></div>
	<div><b>8</b><span>stránok v menu</span></div>
	<div><b>20</b><span>zabudovaných jednotiek + vlastné</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Doklady:</strong> príjemka s nákupnou cenou (prepočíta priemernú nákupnú cenu), výdajka s dôvodom, otváracia príjemka a inventúra. Koncept, zaúčtovanie s číslom, storno.</li>
<li><strong>Kniha pohybov:</strong> každá zmena zásoby – predaj, vrátenie, doklady, ručná zmena, zmena iným doplnkom – so stavom po zmene a odkazom na doklad či objednávku.</li>
<li><strong>Prehľad skladu:</strong> zásoba, nákupná a predajná cena, hodnota skladu, filtre dochádza / vypredané / bez nákupnej ceny.</li>
<li><strong>Inventúra:</strong> súpis na tlač, počítanie aj čítačkou čiarových kódov, manko a prebytok v peniazoch, protokol.</li>
<li><strong>Akcie a novinky:</strong> hromadné zľavy s dátumom od – do, novinky, štítky v obchode.</li>
<li><strong>Balíčky:</strong> produkt, ku ktorému sa do košíka automaticky pridajú ďalšie produkty.</li>
<li><strong>Jednotky:</strong> kus, hodina, kg, mesiac, licencia… pri každom produkte.</li>
<li><strong>Prepojenia:</strong> dodávatelia a prijaté faktúry z J-Fakturácie, zaúčtovanie skladu v J-Účtovníctve.</li>
</ul>
<p class="jhelp-tip"><strong>Dobré vedieť:</strong> J-Sklad eviduje len produkty a varianty, ktoré majú zapnuté <em>Spravovať zásoby</em>. Služby a produkty bez sledovania zásob sa v Prehľade ukážu, ale do knihy pohybov sa nezapisujú.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>Jupiter → Sklad</strong> (ak je zlúčenie doplnkov Jupiter vypnuté, ako samostatná položka <strong>J-Sklad</strong>).</p>
<table class="jhelp-table">
<thead><tr><th>Stránka</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Prehľad</strong></td><td>Zoznam produktov so zásobou, nákupnou a predajnou cenou a hodnotou; súčty za celý sklad.</td></tr>
<tr><td><strong>Doklady</strong></td><td>Príjemky, výdajky, otváracia príjemka – zoznam, nový doklad, zaúčtovanie, storno.</td></tr>
<tr><td><strong>Pohyby</strong></td><td>Kniha pohybov: každá zmena zásoby s filtrom podľa produktu, druhu, dokladu a dátumu.</td></tr>
<tr><td><strong>Obrázky</strong></td><td>Hlavné obrázky a galérie produktov na jednom mieste. Zobrazí sa len po zapnutí v Nastaveniach.</td></tr>
<tr><td><strong>Akcie a novinky</strong></td><td>Hromadné zľavy a označenie noviniek; záložka Štítky a zobrazenie.</td></tr>
<tr><td><strong>Balíčky</strong></td><td>Prehľad všetkých produktov, ktoré majú balíček, a ich súčastí.</td></tr>
<tr><td><strong>Inventúra</strong></td><td>Rozpracované a zaúčtované inventúry, nová inventúra, súpis a protokol.</td></tr>
<tr><td><strong>Nastavenia</strong></td><td>Číslovanie dokladov, hranica „dochádza“, jednotky, obrázky, dôvody výdaja.</td></tr>
</tbody>
</table>
<p>Ďalej J-Sklad pridáva do <strong>úpravy produktu</strong> box <em>Sklad (J-Sklad)</em> vpravo, pole <em>Jednotka</em> v záložke Zásoby, záložku <em>Balíček</em> a polia akcie a novinky v záložke Všeobecné.</p>
<p><strong>Kto ich vidí:</strong> všetci používatelia s právom spravovať obchod (administrátor a správca obchodu).</p>
HTML
	);

	$s[] = array( 'start', 'Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Zapnite sledovanie zásob.</strong> Pri tovare, ktorý chcete evidovať, v úprave produktu → <em>Zásoby</em> zaškrtnite <em>Spravovať zásoby</em> a zadajte množstvo. Pri variantoch to isté pri každom variante (alebo pri hlavnom produkte, ak majú spoločnú zásobu).</li>
<li><strong>Otváracia príjemka vznikne sama.</strong> Pri zapnutí doplnku J-Sklad zapíše súčasné zásoby ako <em>otváraciu príjemku</em> (OP2026001). Zásoby sa pritom nemenia – je to len počiatočný stav knihy. Produkt, ktorému sledovanie zapnete neskôr, dostane počiatočný stav pri prvej zmene.</li>
<li><strong>Doplňte nákupné ceny.</strong> V <em>Sklad → Prehľad</em> zadajte do stĺpca <em>Nákupná cena</em> sumu za jednotku – uloží sa hneď. Bez nej J-Sklad nevie spočítať hodnotu skladu (filter <em>Bez nákupnej ceny</em> ukáže, čo chýba).</li>
<li><strong>Nastavte jednotky.</strong> Pri službách a tovare, ktorý sa nepredáva na kusy, vyberte v úprave produktu → <em>Zásoby → Jednotka</em> napr. hodinu alebo kilogram.</li>
<li><strong>Prvá príjemka.</strong> Keď príde tovar: <em>Sklad → Doklady → Nová príjemka</em>, dodávateľ, položky, množstvá a nákupné ceny, potom <em>Uložiť a zaúčtovať</em>.</li>
</ol>
<p class="jhelp-tip"><strong>Tip:</strong> Zásobu môžete naďalej meniť aj priamo v produkte – zmena sa zapíše do Pohybov ako <em>Ručná zmena v produkte</em>. Pre príjem tovaru je však lepšia príjemka: má číslo, dodávateľa a nákupnú cenu.</p>
HTML
	);

	$s[] = array( 'prehlad', 'Prehľad skladu', 'dashicons-chart-bar', <<<'HTML'
<p>Stránka <em>Sklad → Prehľad</em> ukazuje celý sklad na jednom mieste. Hore sú súčty:</p>
<table class="jhelp-table">
<tbody>
<tr><td><strong>Produkty so sledovaním zásob</strong></td><td>Počet produktov a variantov, ktoré J-Sklad eviduje.</td></tr>
<tr><td><strong>Kusov na sklade</strong></td><td>Súčet zásob všetkých evidovaných produktov.</td></tr>
<tr><td><strong>Hodnota skladu (nákupné ceny)</strong></td><td>Zásoba × priemerná nákupná cena. Toto je hodnota tovaru pre účtovníctvo.</td></tr>
<tr><td><strong>Hodnota skladu v predajných cenách</strong></td><td>Koľko by ste utŕžili, keby ste predali všetko za bežnú cenu.</td></tr>
</tbody>
</table>
<h3>Riadok produktu</h3>
<ul>
<li><strong>Zásoba</strong> s jednotkou (pri kusoch len číslo). Pri variante so spoločnou zásobou je poznámka <em>spoločná</em>; pri produktoch bez sledovania <em>služba</em> alebo <em>nesleduje sa</em>.</li>
<li><strong>Predajná cena</strong> a <strong>nákupná cena</strong> sa dajú prepísať priamo v tabuľke – uložia sa hneď po opustení poľa alebo klávesom <kbd>Enter</kbd> (kurzor skočí na ďalší produkt). Zmena nákupnej ceny sa zapíše do Pohybov.</li>
<li><strong>Hodnota</strong>, <strong>posledný pohyb</strong> a odkaz <strong>História</strong> (pohyby tohto produktu).</li>
<li>Farba čísla zásoby: oranžová = dochádza (na hranici z Nastavení alebo pod ňou), červená = vypredané.</li>
</ul>
<h3>Filtre</h3>
<p><em>Všetky</em>, <em>So sledovaním zásob</em>, <em>Dochádza</em>, <em>Vypredané</em>, <em>Bez nákupnej ceny</em>; k tomu hľadanie podľa názvu alebo čísla produktu a výber kategórie (vrátane variantov).</p>
<h3>Box v produkte</h3>
<p>V úprave produktu vpravo je box <strong>Sklad (J-Sklad)</strong>: aktuálna zásoba, priemerná nákupná cena, posledných 10 pohybov (aj variantov) a odkazy <em>Celá história</em> a <em>Nová príjemka</em>.</p>
HTML
	);

	$s[] = array( 'prijemky', 'Príjemky a nákupná cena', 'dashicons-download', <<<'HTML'
<p>Príjemka zapisuje tovar, ktorý prišiel na sklad. Otvoríte ju v <em>Sklad → Doklady → Nová príjemka</em> (alebo z boxu v produkte).</p>
<ol class="jhelp-steps">
<li><strong>Hlavička:</strong> dátum, <em>Dodávateľ</em> a číslo jeho dokladu (dodací list, faktúra). Pri dodávateľovi stačí začať písať – ponúknu sa dodávatelia z adresára J-Fakturácie.</li>
<li><strong>Položky:</strong> do vyhľadávania napíšte časť názvu alebo číslo produktu a vyberte ho. <strong>Čítačka čiarových kódov:</strong> naskenujte kód – čítačka sama pošle Enter a produkt sa pridá. Opakovaný sken toho istého produktu zvýši množstvo.</li>
<li><strong>Množstvo a nákupná cena</strong> za jednotku (bez DPH, tak ako ju vediete v účtovníctve). Cena je nepovinná, ale bez nej sa neprepočíta hodnota skladu.</li>
<li><strong>Uložiť koncept</strong> – doklad sa uloží bez čísla, zásoby sa nemenia, môžete ho doplniť neskôr alebo zmazať.</li>
<li><strong>Uložiť a zaúčtovať</strong> – doklad dostane číslo (napr. <code>PR2026001</code>), zásoby sa zvýšia a pohyby sa zapíšu do knihy.</li>
</ol>
<h3>Priemerná nákupná cena (vážený priemer)</h3>
<p>Pri každom príjme s cenou J-Sklad prepočíta priemernú nákupnú cenu produktu:</p>
<p><code>nová priemerná cena = (zásoba pred príjmom × doterajšia cena + prijaté množstvo × cena príjmu) ÷ (zásoba pred príjmom + prijaté množstvo)</code></p>
<p>Príklad: na sklade sú 4 kusy s priemernou cenou 10, prijmete 6 kusov po 15 → nová priemerná cena je (40 + 90) ÷ 10 = 13. Ak bola zásoba nulová alebo cena ešte nebola, priemerná cena = cena príjmu. Táto cena sa potom používa pri výdajkách, inventúre a v účtovníctve.</p>
<h3>Prijatá faktúra</h3>
<p>Ak je zapnutá J-Fakturácia, zaúčtovaná príjemka ukazuje prepojenú prijatú faktúru, alebo tlačidlo <em>+ Prijatá faktúra k príjemke</em>, ktoré otvorí novú prijatú faktúru s údajmi z príjemky.</p>
<p class="jhelp-warn"><strong>Pozor:</strong> zaúčtovaný doklad sa už nedá upravovať ani zmazať. Chybu opravíte stornom a novým dokladom (kapitola Výdajky a storno).</p>
HTML
	);

	$s[] = array( 'vydajky', 'Výdajky a storno', 'dashicons-upload', <<<'HTML'
<p>Výdajka odpisuje tovar, ktorý odišiel inak ako predajom v e-shope: poškodenie, vlastná spotreba, darček alebo reklama, strata… Otvoríte ju v <em>Sklad → Doklady → Nová výdajka</em>.</p>
<ul>
<li>Vyplníte dátum, <strong>dôvod</strong> (zoznam dôvodov upravíte v Nastaveniach), poznámku a položky – rovnako ako pri príjemke, aj čítačkou.</li>
<li>Nákupná cena sa nezadáva – výdaj sa ocení <strong>priemernou nákupnou cenou</strong> produktu.</li>
<li>Výdajka nedovolí ísť pod nulu. Výnimkou sú produkty s povoleným predajom do mínusu (WooCommerce: <em>Povoliť objednávky, ak nie je na sklade</em>).</li>
<li>Po zaúčtovaní dostane číslo, napr. <code>VY2026001</code>.</li>
</ul>
<h3>Storno dokladu</h3>
<p>Pri zaúčtovanom doklade je tlačidlo <strong>Stornovať</strong>. Storno urobí presný protipohyb: príjemka zásobu zníži, výdajka ju zvýši, inventúra vráti rozdiely. Doklad ostane v evidencii so stavom <em>Stornovaný</em> a v Pohyboch pribudnú riadky <em>Storno dokladu</em>.</p>
<ul>
<li>Storno príjemky vráti aj priemernú nákupnú cenu do stavu pred príjmom (ak na sklade ostáva tovar).</li>
<li>Ak sa tovar z príjemky medzičasom predal, storno by zásobu stiahlo pod nulu – vtedy ho J-Sklad nedovolí. Vystavte výdajku len na zostávajúce kusy.</li>
</ul>
<h3>Stavy dokladu</h3>
<table class="jhelp-table">
<tbody>
<tr><td><strong>Koncept</strong></td><td>Bez čísla, zásoby sa nemenia, dá sa upravovať a zmazať.</td></tr>
<tr><td><strong>Zaúčtovaný</strong></td><td>Má číslo, zásoby sú zmenené, zapísané v Pohyboch a v účtovníctve.</td></tr>
<tr><td><strong>Stornovaný</strong></td><td>Zásoby vrátené späť, doklad ostáva na prezeranie.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'pohyby', 'Kniha pohybov', 'dashicons-list-view', <<<'HTML'
<p><em>Sklad → Pohyby</em> je úplná história zásob. Každý riadok je jedna zmena: dátum, produkt, druh pohybu, zmena (+ zelená, − červená), <strong>stav po zmene</strong>, odkaz na doklad alebo objednávku, kto zmenu urobil a poznámka.</p>
<table class="jhelp-table">
<thead><tr><th>Druh pohybu</th><th>Kedy vznikne</th></tr></thead>
<tbody>
<tr><td><strong>Príjemka / Výdajka</strong></td><td>Zaúčtovaním dokladu.</td></tr>
<tr><td><strong>Počiatočný stav</strong></td><td>Otváracia príjemka alebo prvý záznam produktu.</td></tr>
<tr><td><strong>Inventúra – rozdiel</strong></td><td>Zaúčtovaním inventúry (manko alebo prebytok).</td></tr>
<tr><td><strong>Objednávka – predaj</strong></td><td>WooCommerce odpísal tovar z objednávky; aj pridanie položky v úprave objednávky.</td></tr>
<tr><td><strong>Objednávka – vrátenie na sklad</strong></td><td>Zrušená objednávka, refundácia s vrátením na sklad, odobratie položky z objednávky.</td></tr>
<tr><td><strong>Storno dokladu</strong></td><td>Stornovaním zaúčtovaného dokladu.</td></tr>
<tr><td><strong>Ručná zmena v produkte</strong></td><td>Niekto prepísal množstvo v úprave produktu alebo v rýchlej úprave.</td></tr>
<tr><td><strong>Zmena nákupnej ceny</strong></td><td>Ručná zmena nákupnej ceny v Prehľade.</td></tr>
<tr><td><strong>Iná zmena</strong></td><td>Iný doplnok, import, alebo vyrovnanie – zásoba sa zmenila mimo WooCommerce či počas vypnutia J-Skladu.</td></tr>
</tbody>
</table>
<p><strong>Filtre:</strong> produkt (odkaz História v Prehľade), druh pohybu, doklad a obdobie od – do.</p>
<p class="jhelp-tip"><strong>Dobré vedieť:</strong> pohyby sú presné aj pri viacerých objednávkach v tej istej sekunde – J-Sklad počas zmeny produkt zamkne a stav číta priamo z databázy. Ak sa stav nezhoduje s knihou (zmena mimo WooCommerce), zapíše najprv opravný riadok <em>vyrovnanie</em>, takže kniha vždy sedí so skutočnou zásobou.</p>
HTML
	);

	$s[] = array( 'inventura', 'Inventúra', 'dashicons-clipboard', <<<'HTML'
<p>Inventúra porovná, čo je naozaj na sklade, s tým, čo je v evidencii, a rozdiely zaúčtuje.</p>
<ol class="jhelp-steps">
<li><strong>Nová inventúra:</strong> <em>Sklad → Inventúra → Nová inventúra</em> – všetky produkty so sledovaním zásob, alebo len jedna kategória. J-Sklad si zapamätá stav v evidencii; zásoby sa zatiaľ nemenia.</li>
<li><strong>Tlačiť súpis:</strong> papierový zoznam s prázdnym stĺpcom <em>Spočítané</em> na ručné zapisovanie.</li>
<li><strong>Počítanie:</strong> spočítané množstvá zapisujte do stĺpca <em>Spočítané</em>. Ukladajú sa priebežne – inventúru môžete zavrieť a pokračovať neskôr. Rozdiel a jeho hodnota v nákupných cenách sa ukazujú hneď (červená manko, zelená prebytok).</li>
<li><strong>Čítačka:</strong> naskenujte kód do poľa hore. So zapnutou voľbou <em>sken pripočíta 1 ks</em> sa ku produktu pripočíta kus (stačí skenovať kus po kuse), bez nej kurzor skočí do jeho riadku. Napísaním časti názvu alebo čísla a Enter produkt nájdete.</li>
<li><strong>Kontrola:</strong> filtre <em>Nespočítané</em> a <em>Rozdiely</em>. Tlačidlo <em>Nespočítané potvrdiť podľa evidencie</em> doplní zvyšku spočítané = stav v evidencii.</li>
<li><strong>Zaúčtovať inventúru:</strong> doklad dostane číslo (napr. <code>IN2026001</code>), zásoba každej spočítanej položky sa nastaví na spočítané množstvo a rozdiel sa zapíše do Pohybov.</li>
</ol>
<ul>
<li><strong>Predaje počas inventúry</strong> sa nezapočítajú dvakrát: rozdiel sa počíta voči zásobe v okamihu zaúčtovania. Ak sa zásoba od založenia inventúry zmenila, pri riadku je upozornenie <em>teraz X</em>.</li>
<li><strong>Nespočítané položky</strong> ostanú bez zmeny; voľba <em>nespočítané nastaviť na 0 ks</em> ich vynuluje (pre prípad, že tovar naozaj nie je).</li>
<li><strong>Protokol:</strong> zaúčtovaná inventúra má protokol na tlač – manko, prebytok, ich hodnoty a miesto na podpis.</li>
<li><strong>Chyba:</strong> zaúčtovanú inventúru môžete stornovať (rozdiely sa vrátia); rozpracovanú môžete zmazať.</li>
</ul>
HTML
	);

	$s[] = array( 'akcie', 'Akcie a novinky', 'dashicons-tag', <<<'HTML'
<h3>Hromadná zľava</h3>
<ol class="jhelp-steps">
<li><em>Sklad → Akcie a novinky</em>, záložka <em>Produkty</em>: vyfiltrujte produkty (názov, kategória, v akcii, novinky, bez akcie) a označte ich.</li>
<li>Vyberte <em>Nastaviť zľavu</em> a zadajte <strong>percento</strong>, <strong>sumu</strong> z ceny, alebo <strong>novú cenu</strong>.</li>
<li>Voliteľne <strong>Od – Do</strong> (cena sa zmení a vráti sama), <strong>Názov akcie</strong> (napr. Black Friday – zobrazí sa na štítku) a <strong>Zaokrúhliť</strong> (centy, 10 centov, celé eurá, na ,90 alebo ,99).</li>
</ol>
<p><em>Zrušiť zľavu</em> vráti bežnú cenu. Zľava je akciová cena WooCommerce, takže platí v košíku, v objednávke aj na faktúre; pri variabilnom produkte pre všetky varianty.</p>
<h3>Novinky</h3>
<ul>
<li><strong>Automaticky:</strong> produkt je novinkou N dní od pridania (záložka <em>Štítky a zobrazenie</em>, 0 = vypnuté).</li>
<li><strong>Ručne:</strong> označte produkty a vyberte <em>Označiť ako novinku</em> s dátumom <em>Novinka do</em>. <em>Zrušiť novinku</em> produkt vyradí aj z automatických noviniek; <em>Novinka automaticky podľa dátumu pridania</em> ho do nich vráti.</li>
<li>To isté sa dá nastaviť aj v úprave produktu (Všeobecné → Novinka, Názov akcie).</li>
</ul>
<h3>Štítky a stránky v obchode</h3>
<ul>
<li>V obchode sa pri produkte zobrazí štítok <strong>Akcia −20 %</strong> (alebo názov akcie) a <strong>Novinka</strong> namiesto „Výpredaj!“. Text, farbu a zobrazovanie percent zmeníte v záložke <em>Štítky a zobrazenie</em>.</li>
<li>Stránka s akciami alebo novinkami: krátky kód <code>[jsk_akcie]</code> alebo <code>[jsk_novinky]</code> (voliteľne <code>limit</code> a <code>columns</code>, napr. <code>[jsk_akcie limit="12" columns="3"]</code>).</li>
<li>Odkaz do menu: obchod s parametrom <code>?jsk=akcie</code> alebo <code>?jsk=novinky</code>.</li>
</ul>
HTML
	);

	$s[] = array( 'balicky', 'Balíčky', 'dashicons-products', <<<'HTML'
<p>Balíček je bežný produkt, ku ktorému sa v košíku <strong>automaticky pridajú ďalšie produkty</strong> (súčasti). Príklad: <em>E-shop na kľúč</em> = e-shop + členstvo na rok + 8 h IT poradenstva.</p>
<h3>Nastavenie</h3>
<ol class="jhelp-steps">
<li>V úprave produktu otvorte záložku <strong>Balíček</strong>.</li>
<li>Vyberte súčasť, zadajte <strong>Počet</strong> (môže byť aj desatinný, napr. 1,5 h) a voliteľne <strong>Cenu v balíčku</strong> za jednotku. Prázdna cena = bežná cena súčasti.</li>
<li>Uložte produkt – pribudne ďalší prázdny riadok. Súčasť odstránite voľbou <em>— žiadna —</em>.</li>
</ol>
<h3>Ako sa správa v obchode</h3>
<ul>
<li>Na stránke produktu je rámček <strong>V balíčku dostanete</strong> so zoznamom súčastí a ich cenou; pod cenou produktu je <em>balíček spolu</em>.</li>
<li>V košíku sú súčasti samostatné riadky označené <em>Súčasť balíčka</em>. Ich počet sa riadi počtom balíčkov; súčasť sa nedá meniť ani odobrať samostatne – odobratie odoberie celý balíček.</li>
<li>Súčasti sú bežné riadky objednávky aj faktúry: sklad sa odpisuje, členstvo sa zapne ako pri samostatnom nákupe.</li>
<li><strong>Variabilná súčasť</strong> (napr. Členstvo Mesačne / Ročne): vyberte v balíčku konkrétny variant. Ak vyberiete hlavný produkt, použije sa jeho predvolený variant.</li>
<li>Názov súčasti sa píše s množstvom a jednotkou: <em>IT poradenstvo – 8 h</em>; pri jednom kuse len názov.</li>
</ul>
<p>Prehľad všetkých balíčkov a ich súčastí je v <em>Sklad → Balíčky</em>.</p>
<p class="jhelp-warn"><strong>Pozor:</strong> ak niektorá súčasť nie je dostupná (vypredaná, skrytá), balíček sa do košíka nepridá a zákazník uvidí oznam. Pred zmenou alebo skrytím produktu skontrolujte, či nie je súčasťou balíčka.</p>
HTML
	);

	$s[] = array( 'jednotky', 'Jednotky', 'dashicons-editor-ol', <<<'HTML'
<p>Každý produkt má <strong>jednotku</strong>, v ktorej sa predáva a skladuje. Predvolený je kus; pri službách a tovare na váhu či na čas vyberte inú v úprave produktu → <em>Zásoby</em> → <strong>Jednotka</strong>. Varianty preberajú jednotku hlavného produktu.</p>
<table class="jhelp-table">
<thead><tr><th>Skupina</th><th>Jednotky</th></tr></thead>
<tbody>
<tr><td><strong>Kusy a balenia</strong></td><td>ks (kus), bal. (balenie), sada, pár</td></tr>
<tr><td><strong>Čas</strong></td><td>h (hodina), min (minúta), deň, týž. (týždeň), mes. (mesiac), rok</td></tr>
<tr><td><strong>Hmotnosť a objem</strong></td><td>kg, g, l, ml, m³</td></tr>
<tr><td><strong>Dĺžka a plocha</strong></td><td>m, m²</td></tr>
<tr><td><strong>Služby a licencie</strong></td><td>lic. (licencia), os. (osoba), str. (strana)</td></tr>
</tbody>
</table>
<h3>Kde sa jednotka zobrazí</h3>
<ul>
<li>Pri zásobe v Prehľade a v boxe produktu, v dokladoch, v Pohyboch (napr. <em>+8 h</em>, <em>2,5 kg</em>). Pri kusoch len číslo.</li>
<li>V balíčkoch: <em>IT poradenstvo – 8 h</em>.</li>
<li>Voliteľne v obchode pri cene: <em>· za hodinu</em>, <em>· za kg</em> (Nastavenia → <em>Jednotka pri cene</em>).</li>
</ul>
<h3>Vlastné jednotky</h3>
<p>V <em>Sklad → Nastavenia → Vlastné jednotky</em> pridáte ďalšie, jednu na riadok v tvare <code>skratka | názov | za …</code>, napríklad <code>porc. | porcia | za porciu</code>. Tretia časť je text, ktorý sa zobrazí pri cene; ak ho vynecháte, pri cene sa nič nepridá.</p>
HTML
	);

	$s[] = array( 'obrazky', 'Obrázky produktov', 'dashicons-format-gallery', <<<'HTML'
<p>Voliteľná funkcia pre obchody s veľa produktmi: obrázky produktov sa spravujú v J-Sklade a <strong>Knižnica multimédií</strong> zostane prehľadná – len obrázky webu (články, logo…).</p>
<ol class="jhelp-steps">
<li>Zapnite <em>Sklad → Nastavenia → Obrázky produktov spravuje J-Sklad</em> (predvolene vypnuté).</li>
<li>Pribudne stránka <em>Sklad → Obrázky</em>: pri každom produkte hlavný obrázok a galéria, varianty pod ním.</li>
<li><em>Zmeniť / Pridať</em> vyberie alebo nahrá obrázok, <em>+ Galéria</em> pridá viac obrázkov naraz, <em>×</em> obrázok z produktu odoberie (súbor ostane na webe).</li>
</ol>
<p>Súbory sa nikam nepresúvajú – po vypnutí voľby je Knižnica opäť kompletná. Okno výberu obrázka v úprave produktu ukazuje všetky obrázky ako doteraz.</p>
HTML
	);

	$s[] = array( 'prepojenia', 'Prepojenia s ďalšími doplnkami', 'dashicons-networking', <<<'HTML'
<h3>WooCommerce</h3>
<p>J-Sklad nemení, ako WooCommerce narába so zásobou – len ju sleduje. Objednávka znižuje zásobu podľa nastavení WooCommerce (zvyčajne pri zaplatení alebo spracovaní), zrušenie a refundácia s vrátením na sklad ju zvyšujú. Každá z týchto zmien sa zapíše do Pohybov s číslom objednávky.</p>
<h3>J-Fakturácia</h3>
<ul>
<li><strong>Dodávatelia:</strong> pole Dodávateľ v príjemke ponúka partnerov z adresára J-Fakturácie.</li>
<li><strong>Prijaté faktúry:</strong> príjemka sa prepojí s prijatou faktúrou; z príjemky sa dá faktúra rovno vytvoriť.</li>
</ul>
<h3>J-Účtovníctvo</h3>
<p>Pri spôsobe účtovania zásob <strong>A</strong> (J-Účtovníctvo → Nastavenia → Zásoby) sa doklady skladu zaúčtujú automaticky v rade <strong>SK</strong>:</p>
<table class="jhelp-table">
<thead><tr><th>Doklad</th><th>Zápis</th></tr></thead>
<tbody>
<tr><td><strong>Príjemka</strong></td><td>132 / 131 – tovar na sklade / obstaranie tovaru, v nákupných cenách z príjemky.</td></tr>
<tr><td><strong>Výdajka</strong></td><td>podľa dôvodu (napr. 501 spotreba, 513 reprezentácia, 543 dar, inak 549) / 132, v priemerných nákupných cenách.</td></tr>
<tr><td><strong>Inventúra</strong></td><td>manko 549 / 132, prebytok 132 / 648.</td></tr>
<tr><td><strong>Predaj tovaru</strong></td><td>raz mesačne 504 / 132 – predané kusy z objednávok v priemerných nákupných cenách.</td></tr>
</tbody>
</table>
<p>Stornovaný doklad sa v účtovníctve tiež stornuje. Pri spôsobe <strong>B</strong> sa tovar účtuje priamo do nákladov a doklady skladu sa neúčtujú.</p>
HTML
	);

	$s[] = array( 'nastavenia', 'Všetky nastavenia', 'dashicons-admin-settings', <<<'HTML'
<p><em>Sklad → Nastavenia</em>:</p>
<table class="jhelp-table">
<thead><tr><th>Nastavenie</th><th>Význam</th><th>Predvolené</th></tr></thead>
<tbody>
<tr><td><strong>Formát čísla príjemky</strong></td><td><code>{RRRR}</code> rok, <code>{RR}</code> rok dvojmiestne, <code>{nnn}</code> / <code>{nnnn}</code> / <code>{nnnnn}</code> poradie v roku. Bez roka pokračuje poradie cez roky.</td><td><code>PR{RRRR}{nnn}</code></td></tr>
<tr><td><strong>Formát čísla výdajky</strong></td><td>Rovnako ako pri príjemke.</td><td><code>VY{RRRR}{nnn}</code></td></tr>
<tr><td><strong>Formát otváracej príjemky</strong></td><td>Počiatočný stav skladu.</td><td><code>OP{RRRR}{nnn}</code></td></tr>
<tr><td><strong>Formát čísla inventúry</strong></td><td>Zaúčtovaná inventúra.</td><td><code>IN{RRRR}{nnn}</code></td></tr>
<tr><td><strong>Hranica „dochádza“</strong></td><td>Pri tejto zásobe a menej je produkt v Prehľade žltý a vo filtri Dochádza.</td><td>podľa WooCommerce</td></tr>
<tr><td><strong>Vlastné jednotky</strong></td><td>Ďalšie jednotky: <code>skratka | názov | za …</code>.</td><td>—</td></tr>
<tr><td><strong>Jednotka pri cene</strong></td><td>V obchode za cenu pridá „· za hodinu“, „· za kg“…</td><td>vypnuté</td></tr>
<tr><td><strong>Obrázky produktov</strong></td><td>Obrázky produktov spravuje J-Sklad, Knižnica ich nezobrazuje.</td><td>vypnuté</td></tr>
<tr><td><strong>Dôvody výdaja</strong></td><td>Zoznam pre výdajku, oddelený čiarkou.</td><td>Poškodenie, Vlastná spotreba, Darček / reklama, Strata, Iné</td></tr>
</tbody>
</table>
<p>Nastavenia štítkov akcií a noviniek sú v <em>Akcie a novinky → Štítky a zobrazenie</em>: zobrazovať štítky, percento na štítku, text a farba štítku akcie a novinky, počet dní automatickej novinky.</p>
<p class="jhelp-tip"><strong>Tip:</strong> Formát čísla meňte radšej na začiatku roka. Už vystavené doklady si číslo ponechajú.</p>
HTML
	);

	$s[] = array( 'problemy', 'Keď niečo nejde', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Problém</th><th>Príčina a riešenie</th></tr></thead>
<tbody>
<tr><td><strong>Produkt chýba v Prehľade alebo v dokladoch</strong></td><td>Nemá zapnuté <em>Spravovať zásoby</em>. Zapnite ho v úprave produktu → Zásoby.</td></tr>
<tr><td><strong>Zásoba nesedí so skutočnosťou</strong></td><td>Pozrite <em>História</em> produktu v Pohyboch – ukáže, ktorá zmena ju posunula. Rozdiel opravte inventúrou (aj len pre jednu kategóriu), nie prepísaním čísla v produkte – inventúra zanechá doklad s hodnotou rozdielu.</td></tr>
<tr><td><strong>Hodnota skladu je nula alebo príliš nízka</strong></td><td>Produkty nemajú nákupnú cenu – filter <em>Bez nákupnej ceny</em> v Prehľade. Doplňte ju priamo v tabuľke.</td></tr>
<tr><td><strong>„Na sklade nie je dosť“ pri výdajke</strong></td><td>Výdajka nesmie ísť pod nulu. Skontrolujte zásobu; ak tovar fyzicky je, najprv urobte príjemku alebo inventúru.</td></tr>
<tr><td><strong>„Storno by znížilo zásobu pod nulu“</strong></td><td>Tovar z príjemky sa už predal. Namiesto storna vystavte výdajku na zostávajúce kusy.</td></tr>
<tr><td><strong>„Doklad sa práve zaúčtováva“</strong></td><td>Niekto (alebo dvojklik) doklad práve zaúčtováva. Počkajte pár sekúnd a stránku obnovte.</td></tr>
<tr><td><strong>Pohyb „Iná zmena – vyrovnanie“</strong></td><td>Zásoba sa zmenila mimo WooCommerce (import, iný doplnok, priamo v databáze) alebo počas vypnutia J-Skladu. Kniha sa tým dorovnala na skutočný stav.</td></tr>
<tr><td><strong>Balíček sa nepridá do košíka</strong></td><td>Niektorá súčasť nie je dostupná (vypredaná, skrytá, variant bez ceny). Skontrolujte súčasti v záložke Balíček.</td></tr>
<tr><td><strong>Pri cene sa neukazuje „za hodinu“</strong></td><td>Zapnite Nastavenia → <em>Jednotka pri cene</em> a skontrolujte jednotku v produkte → Zásoby.</td></tr>
<tr><td><strong>Akciová cena sa nevrátila</strong></td><td>Koniec akcie spúšťa WooCommerce pri návšteve webu po dátume Do. Ak sa cena nevrátila, zrušte zľavu ručne.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'technicke', 'Technické údaje (pre vývojára)', 'dashicons-editor-code', <<<'HTML'
<h3>Tabuľky v databáze</h3>
<table class="jhelp-table">
<tbody>
<tr><td><code>{prefix}jsk_docs</code></td><td>Doklady: druh (<code>receipt</code>, <code>issue</code>, <code>opening</code>, <code>inventory</code>), číslo, dátum, dodávateľ (<code>partner_id</code> z J-Fakturácie), <code>invoice_id</code>, dôvod, stav (<code>draft</code>, <code>posted</code>, <code>cancelled</code>).</td></tr>
<tr><td><code>{prefix}jsk_doc_items</code></td><td>Položky: produkt, číslo produktu, názov, množstvo, nákupná cena; pri inventúre <code>expected</code> a <code>counted</code>.</td></tr>
<tr><td><code>{prefix}jsk_moves</code></td><td>Kniha pohybov: produkt, zmena, stav po, zdroj, doklad / objednávka, nákupná cena, poznámka, používateľ.</td></tr>
</tbody>
</table>
<h3>Údaje pri produkte (post meta)</h3>
<ul>
<li><code>_jsk_cost</code> – priemerná nákupná cena (pri variante so spoločnou zásobou na hlavnom produkte).</li>
<li><code>_jsk_unit</code> – jednotka; prázdne = kus. Variant bez vlastnej preberá jednotku hlavného produktu.</li>
<li><code>_jsk_bundle</code> – súčasti balíčka: <code>[ { id, qty, price } ]</code>.</li>
</ul>
<h3>Ako sa zapisujú pohyby</h3>
<p>J-Sklad sa napája na háky <code>woocommerce_(product|variation)_before_set_stock</code> a <code>…_set_stock</code>. Pred zmenou produkt zamkne (<code>GET_LOCK</code>) a prečíta stav z databázy, po zmene ho prečíta znova; rozdiel je presne táto zmena. Zdroj (doklad, objednávka) sa určí z kontextu nastaveného pred zmenou. Variant so zásobou hlavného produktu sa vedie na hlavnom produkte.</p>
<h3>Funkcie a háky</h3>
<ul>
<li><code>jsk_apply_move( $product_id, $qty, $source, $args )</code> – zmena zásoby s pohybom (príjem s <code>cost</code> prepočíta priemer).</li>
<li><code>jsk_cost( $id )</code>, <code>jsk_unit( $product )</code>, <code>jsk_qty_unit( $qty, $product )</code>, <code>jsk_bundle_items( $product )</code>.</li>
<li>Filter <code>jsk_units</code> – úprava zoznamu jednotiek: <code>skratka => [ názov, text pri cene ]</code>.</li>
<li>Akcia <code>jsk_doc_posted</code> – po zaúčtovaní dokladu (používa J-Účtovníctvo).</li>
</ul>
<p>Doplnok je kompatibilný s tabuľkami objednávok WooCommerce (HPOS) a vyžaduje PHP 8.1 a WordPress 6.5.</p>
HTML
	);

	return $s;
}
