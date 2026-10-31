<?php
/**
 * Príručka doplnku J-Účtovníctvo (podstránka J-Pomocník → J-Účtovníctvo).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Účtovníctvo sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.7.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_uctovnictvo() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: podvojné účtovníctvo pre s. r. o.', 'dashicons-book-alt', <<<'HTML'
<p class="jhelp-lead"><strong>J-Účtovníctvo je podvojné účtovníctvo pre slovenskú s. r. o. priamo vo WordPresse.</strong> Faktúry, pohyby v banke, sklad a platby z ostatných doplnkov Jupiter sa zaúčtujú samy. Vy riešite len to, čo sa nedá určiť automaticky, a raz do roka uzávierku.</p>
<div class="jhelp-facts">
	<div><b>4</b><span>zdroje automatického účtovania: faktúry, banka, sklad, platby</span></div>
	<div><b>1×</b><span>za hodinu beží automatické účtovanie</span></div>
	<div><b>Úč MÚJ</b><span>účtovná závierka mikro účtovnej jednotky</span></div>
	<div><b>DPPO</b><span>pracovný list dane z príjmov a termíny</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Účtovné knihy:</strong> účtový rozvrh podľa rámcovej osnovy, účtovné doklady so stornom, denník, hlavná kniha, obratová predvaha, saldokonto a karta účtu.</li>
<li><strong>Automatické účtovanie</strong> vydaných a prijatých faktúr, dobropisov, pohybov v banke, skladových dokladov a platieb kartou.</li>
<li><strong>Pokladňa a majetok:</strong> pokladničné doklady a kniha, karty majetku s odpisovým plánom a mesačnými odpismi.</li>
<li><strong>Kontroly:</strong> kontroly mesiaca, odsúhlasenie banky s výpisom, zoznam Na riešenie s úlohami a otázkami pre účtovníka.</li>
<li><strong>Uzávierka:</strong> daň z príjmov s minimálnou daňou, uzavretie kníh, otvorenie nového roka, rozdelenie výsledku hospodárenia.</li>
<li><strong>Výkazy:</strong> súvaha a výkaz ziskov a strát Úč MÚJ, poznámky Úč MÚJ 3-01, export CSV.</li>
</ul>
<p class="jhelp-tip"><strong>Účtovník ostáva potrebný.</strong> Doplnok pripraví knihy, výpočty a podklady. Daňové priznanie a závierku podáva firma alebo jej účtovník cez eDane / Finančnú správu; doplnok ich zatiaľ neodosiela.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>J-Účtovníctvo</strong> (pri zlúčení doplnkov pod <strong>Jupiter → Účtovníctvo</strong>). Číslo pri názve ukazuje, koľko položiek čaká na zaúčtovanie.</p>
<table class="jhelp-table">
<thead><tr><th>Stránka</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Prehľad</strong></td><td>Najbližšie termíny, výsledok roka, faktúry po splatnosti, stav automatického účtovania.</td></tr>
<tr><td><strong>Účtovné doklady</strong> / <strong>Nový doklad</strong></td><td>Denník dokladov, detail so stornom, ručný doklad s predkontáciami.</td></tr>
<tr><td><strong>Na zaúčtovanie</strong></td><td>Pohyby v banke a úhrady, pri ktorých treba určiť účet.</td></tr>
<tr><td><strong>Hlavná kniha</strong>, <strong>Obratová predvaha</strong>, <strong>Saldokonto</strong></td><td>Účtovné knihy za zvolené obdobie, karta účtu, neuhradené faktúry po partneroch.</td></tr>
<tr><td><strong>Pokladňa</strong></td><td>Príjmové a výdavkové pokladničné doklady, pokladničná kniha.</td></tr>
<tr><td><strong>Majetok</strong></td><td>Karty majetku, odpisy, vyradenie.</td></tr>
<tr><td><strong>Uzávierka</strong></td><td>Kontroly a uzamknutie mesiaca, ročná uzávierka, poznámky.</td></tr>
<tr><td><strong>Termíny a daň</strong></td><td>Kalendár povinností, pracovný list DPPO, rozdelenie výsledku.</td></tr>
<tr><td><strong>Odsúhlasenie banky</strong></td><td>Zostatok účtu 221 oproti výpisu za každý mesiac.</td></tr>
<tr><td><strong>Účtovná závierka</strong></td><td>Súvaha a výkaz ziskov a strát Úč MÚJ.</td></tr>
<tr><td><strong>Na riešenie</strong></td><td>Úlohy, rozhodnutia, otázky pre účtovníka a automatické kontroly.</td></tr>
<tr><td><strong>Účtový rozvrh</strong>, <strong>Nastavenia</strong>, <strong>Import (OBERON)</strong>, <strong>Sprievodca založením</strong></td><td>Účty a analytiky, predvolené účty a pravidlá, prevod z OBERON-u, prvé nastavenie.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'start', 'Založenie účtovníctva (sprievodca)', 'dashicons-controls-play', <<<'HTML'
<p>Kým účtovníctvo nie je založené, v menu svieti <strong>★ Založiť účtovníctvo</strong>. Sprievodca má tieto kroky:</p>
<ol class="jhelp-steps">
<li><strong>Firma.</strong> Obchodné meno, IČO (dá sa načítať z registra), DIČ, sídlo, zápis v obchodnom registri, dátum vzniku, konateľ.</li>
<li><strong>Veľkosť a DPH.</strong> Veľkosť účtovnej jednotky (mikro alebo malá) a či je firma platiteľom DPH.</li>
<li><strong>Obdobie a začiatok.</strong> Dátum začiatku účtovania v Jupiteri a režim: <em>Nová firma</em> (základné imanie 353/411 a jeho splatenie 221/353) alebo <em>Pokračujúca firma</em> (prenášate konečné zostatky). Doklady pred začiatkom sa automaticky neúčtujú.</li>
<li><strong>Účtový rozvrh.</strong> Overený rozvrh pre obchodnú s. r. o. podľa rámcovej osnovy.</li>
<li><strong>Počiatočné stavy.</strong> Zostatky súvahových účtov zo súvahy a predvahy od účtovníka: aktíva na MD, pasíva na Dal, súčty sa musia rovnať. Zaúčtujú sa dokladom série <code>PS</code> proti 701.</li>
<li><strong>Napojenie dokladov.</strong> Bankové účty z J-Fakturácie → analytiky 221, pokladňa (211), spôsob účtovania zásob a hodnota tovaru na sklade (132) podľa J-Skladu.</li>
<li><strong>Predkontácie.</strong> Účty pre tržby, odberateľov, dodávateľov, zálohy, bankové poplatky a kategórie prijatých faktúr.</li>
<li><strong>Kontrola.</strong> Zhrnutie: čo je hotové, čo chýba a na čo si dať pozor.</li>
<li><strong>Spustenie.</strong> Prvé automatické zaúčtovanie všetkých dokladov od dátumu začiatku.</li>
</ol>
<h3>Rozpis neuhradených faktúr</h3>
<p>Pohľadávky (311) a záväzky (321) môžete v kroku 5 rozpísať po faktúrach: partner, variabilný symbol a neuhradená suma. Súčet rozpisu sa musí rovnať zostatku účtu. Saldokonto potom ukazuje každú faktúru zvlášť a úhrada s rovnakým VS ju uzavrie. Bez rozpisu sa zaúčtuje jedna suma.</p>
<p class="jhelp-tip"><strong>Oprava počiatočných stavov:</strong> zmeňte hodnoty a uložte. Pôvodný doklad PS sa stornuje a zaúčtuje znova; ak sa nový doklad nepodarí zaúčtovať, pôvodný ostane platný.</p>
HTML
	);

	$s[] = array( 'doklady', 'Účtovné doklady a storno', 'dashicons-media-text', <<<'HTML'
<p>Každý účtovný doklad má sériu, číslo, dátum účtovného prípadu, popis, partnera, variabilný symbol a riadky MD / Dal. Číslo sa prideľuje automaticky v rámci série a roka.</p>
<table class="jhelp-table">
<thead><tr><th>Séria</th><th>Doklad</th></tr></thead>
<tbody>
<tr><td><code>OF</code> / <code>OD</code></td><td>Vydaná faktúra / dobropis (z J-Fakturácie).</td></tr>
<tr><td><code>DF</code></td><td>Prijatá faktúra.</td></tr>
<tr><td><code>BV</code></td><td>Bankový výpis – pohyb v banke alebo úhrada.</td></tr>
<tr><td><code>PD</code> / <code>VD</code></td><td>Príjmový / výdavkový pokladničný doklad.</td></tr>
<tr><td><code>SK</code></td><td>Sklad – príjemky, výdajky, predaný tovar za mesiac.</td></tr>
<tr><td><code>MA</code></td><td>Majetok – zaradenie, odpisy, vyradenie.</td></tr>
<tr><td><code>ID</code></td><td>Interný doklad – ručné doklady, daň, rozdelenie výsledku.</td></tr>
<tr><td><code>PS</code> / <code>UZ</code></td><td>Počiatočné stavy / uzávierkové doklady.</td></tr>
</tbody>
</table>
<h3>Ručný doklad</h3>
<p><em>Nový doklad</em>: zvoľte predkontáciu (napr. výplata konateľovi 355/221) alebo zadajte riadky ručne. Súčet MD a Dal sa musí rovnať.</p>
<h3>Storno</h3>
<p>Doklad sa nemaže – v detaile kliknite <em>Stornovať</em> a vznikne doklad so zápornými sumami. Storno automaticky zaúčtovaného dokladu sa pamätá: ten istý zdroj sa už znova nezaúčtuje, kým sa zdroj nezmení.</p>
<p class="jhelp-warn"><strong>Uzamknuté obdobie:</strong> do uzamknutého mesiaca ani uzavretého roka sa nedá účtovať ani stornovať. Opravu zaúčtujte v otvorenom mesiaci alebo najprv obdobie odomknite.</p>
HTML
	);

	$s[] = array( 'auto', 'Automatické účtovanie', 'dashicons-update', <<<'HTML'
<p>Automatické účtovanie beží každú hodinu a hneď po zmene dokladu v inom doplnku. Spracuje len zmenené doklady a raz denne prejde všetko pre kontrolu.</p>
<table class="jhelp-table">
<thead><tr><th>Zdroj</th><th>Ako sa účtuje</th></tr></thead>
<tbody>
<tr><td>Vydaná faktúra</td><td>311 / 604 (tovar), 602 (služby, doprava), 343 (DPH); zálohy cez 324.</td></tr>
<tr><td>Prijatá faktúra</td><td>Podľa kategórie (501, 504, 518, 042 …) / 321.</td></tr>
<tr><td>Pohyb v banke</td><td>Párovaný s faktúrou 221 / 311 alebo 321 / 221, vrátenie peňazí k dobropisu 311 / 221; výplata od Packety a Stripe 221 / 261; ostatné podľa pravidiel.</td></tr>
<tr><td>Sklad</td><td>Príjemky 132 / 131 alebo 321, predaný tovar 504 / 132 za mesiac.</td></tr>
<tr><td>Platba kartou (J-Platby)</td><td>Úhrada cez 261 a poplatok Stripe 568.</td></tr>
<tr><td>Dobierka (Packeta)</td><td>Peniaze na ceste 261002 / 311 – nie do pokladne; výplata Packety do banky 221 / 261002 (zostatok 261002 = ešte nevyplatené dobierky alebo zrážky Packety).</td></tr>
</tbody>
</table>
<p>Keď sa zdroj zmení (napr. oprava faktúry), účtovný doklad sa stornuje a zaúčtuje znova. Keď sa zdroj zmaže, zaúčtovanie sa stornuje.</p>
<h3>Na zaúčtovanie</h3>
<p>Pohyby v banke, ktoré nie sú spárované s faktúrou ani nezodpovedajú pravidlu, a úhrady bez určeného účtu čakajú na stránke <em>Na zaúčtovanie</em>. Vyberte účet a kliknite <em>Zaúčtovať</em>.</p>
<ul>
<li><strong>Zapamätať:</strong> zaškrtnite a uloží sa pravidlo – podľa IBAN protiúčtu, inak podľa názvu, inak podľa textu platby. Podobné pohyby vo fronte sa zaúčtujú hneď a budúce automaticky.</li>
<li><strong>Pravidlá</strong> upravíte v <em>Nastaveniach</em>, jeden riadok = jedno pravidlo: <code>SK12 3456 … = 355</code>, <code>názov: Orange = 518</code>, <code>text: nájomné = 518</code>.</li>
</ul>
<h3>Vylúčenie z automatického účtovania</h3>
<p>Doklad, ktorý sa nemá účtovať automaticky (interný prevod, duplicita), otvorte a kliknite <em>Vylúčiť z automatického účtovania</em>. Zaúčtovanie sa stornuje a už sa nevytvorí. Vylúčenie sa dá kedykoľvek zrušiť.</p>
<p class="jhelp-tip">Chyby automatického účtovania (napr. chýbajúci účet) sa zobrazia na Prehľade. Po oprave príčiny kliknite <em>Spustiť teraz</em>.</p>
HTML
	);

	$s[] = array( 'banka', 'Banka a odsúhlasenie s výpisom', 'dashicons-bank', <<<'HTML'
<p>Každý bankový účet z J-Fakturácie má vlastnú analytiku účtu 221 (221001, 221002 …). Pohyby sa sťahujú v J-Fakturácii (napr. Fio API) a odtiaľ sa účtujú.</p>
<h3>Odsúhlasenie banky</h3>
<p>Stránka <em>Odsúhlasenie banky</em> porovná za každý mesiac zostatok účtu 221 v účtovníctve so zostatkom podľa výpisu (počiatočný stav účtu + pohyby v J-Fakturácii). Rozdiel znamená:</p>
<ul>
<li>pohyb, ktorý ešte čaká <em>Na zaúčtovanie</em>,</li>
<li>pohyb zaúčtovaný ručne a zároveň automaticky (dvakrát),</li>
<li>chýbajúci počiatočný stav bankového účtu v J-Fakturácii.</li>
</ul>
<p class="jhelp-warn"><strong>Pred uzamknutím mesiaca</strong> musí byť rozdiel nulový. Kontroly mesiaca to ukazujú aj na stránke Uzávierka.</p>
HTML
	);

	$s[] = array( 'pokladna', 'Pokladňa', 'dashicons-money', <<<'HTML'
<p><em>Pokladňa → Nový doklad</em>: príjem (séria <code>PD</code>) alebo výdavok (<code>VD</code>), dátum, suma, účel a protiúčet. Účel (napr. vklad z banky 261, nákup materiálu 501) predvyplní protiúčet.</p>
<ul>
<li>Pokladňa nesmie ísť do mínusu – výdavok, po ktorom by zostatok klesol pod nulu, sa nezaúčtuje.</li>
<li>Pokladničná kniha za mesiac s priebežným zostatkom sa dá vytlačiť.</li>
<li>Doklad z pokladne sa stornuje rovnako ako ostatné doklady.</li>
</ul>
HTML
	);

	$s[] = array( 'majetok', 'Majetok a odpisy', 'dashicons-building', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Faktúra.</strong> Faktúru za majetok zaúčtujte v J-Fakturácii s kategóriou <em>Majetok</em> (042 / 321).</li>
<li><strong>Karta.</strong> <em>Majetok → Nová karta</em>: názov, druh (hmotný, nehmotný, drobný), vstupná cena, dátum zaradenia, odpisová skupina 0 – 6 a spôsob (rovnomerný § 27, zrýchlený § 28 alebo vlastný plán).</li>
<li><strong>Zaradenie.</strong> Karta zaúčtuje 022 / 042 (013 / 042 pri nehmotnom) k dátumu zaradenia.</li>
<li><strong>Odpisy</strong> sa účtujú automaticky za každý ukončený mesiac jedným dokladom <code>ODP-RRRRMM</code> (551 / 082). V roku zaradenia sa odpisuje pomerná časť.</li>
<li><strong>Vyradenie:</strong> predaj (541), likvidácia (551), škoda (549) alebo darovanie (543) – zaúčtujú sa oprávky a zostatková cena.</li>
</ol>
<p>Zmena karty opraví už zaúčtované odpisy v otvorených mesiacoch. Drobný majetok sa len eviduje.</p>
HTML
	);

	$s[] = array( 'knihy', 'Hlavná kniha, predvaha a saldokonto', 'dashicons-list-view', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Kniha</th><th>Čo ukazuje</th></tr></thead>
<tbody>
<tr><td><strong>Účtovné doklady</strong> (denník)</td><td>Doklady v časovom poradí, filter podľa série, mesiaca, partnera a textu.</td></tr>
<tr><td><strong>Hlavná kniha</strong></td><td>Počiatočný stav, obraty MD a Dal a zostatok každého účtu. Klik na účet otvorí kartu účtu s jednotlivými zápismi.</td></tr>
<tr><td><strong>Obratová predvaha</strong></td><td>Všetky účty za obdobie, súčty MD = Dal; export CSV pre účtovníka.</td></tr>
<tr><td><strong>Saldokonto</strong></td><td>Neuhradené pohľadávky (311) a záväzky (321) po partneroch a variabilných symboloch, s dňami po splatnosti.</td></tr>
</tbody>
</table>
<p>Exporty CSV sú chránené proti vzorcom (bunky začínajúce <code>=</code>, <code>+</code>, <code>-</code>, <code>@</code> sa pri otvorení v Exceli nespustia).</p>
HTML
	);

	$s[] = array( 'mesiac', 'Mesačná kontrola a uzamknutie', 'dashicons-calendar-alt', <<<'HTML'
<p>Na stránke <em>Uzávierka</em> sú <strong>Kontroly mesiaca</strong> pre každý mesiac roka:</p>
<ul>
<li><strong>Banka</strong> – zostatok 221 sa rovná výpisu,</li>
<li><strong>Pokladňa</strong> – zostatok nie je záporný,</li>
<li><strong>Na zaúčtovanie</strong> – žiadne čakajúce pohyby a úhrady z mesiaca,</li>
<li><strong>Odpisy</strong> – zaúčtované za mesiac,</li>
<li><strong>Chyby automatického účtovania</strong> a <strong>úlohy Na riešenie</strong> po termíne.</li>
</ul>
<p>Keď je všetko v poriadku, kliknite <em>Všetko v poriadku – uzavrieť mesiac</em>. Do uzamknutého mesiaca sa už nič nezaúčtuje – ani automaticky. Zmena faktúry z uzamknutého mesiaca sa objaví v zozname Na riešenie.</p>
<p class="jhelp-tip">Prehľad ukazuje pre majiteľa to najdôležitejšie: najbližšie termíny, výsledok hospodárenia za rok, neuhradené faktúry po splatnosti a mesiace, ktoré ešte nie sú skontrolované.</p>
HTML
	);

	$s[] = array( 'rok', 'Ročná uzávierka a daň z príjmov', 'dashicons-lock', <<<'HTML'
<p><em>Uzávierka → Ročná uzávierka</em> vedie krokmi v pevnom poradí; ďalší krok sa sprístupní po dokončení predchádzajúceho.</p>
<ol class="jhelp-steps">
<li><strong>Kontroly</strong> – banka, pokladňa, doklady na zaúčtovanie, odpisy, otvorené položky Na riešenie; inventarizácia k 31. 12.</li>
<li><strong>Odpisy do decembra.</strong></li>
<li><strong>Zásoby</strong> podľa inventúry (spôsob B) – rozdiel sa zaúčtuje.</li>
<li><strong>Daň z príjmov</strong> – výsledok hospodárenia, nedaňové náklady, základ dane, odpočet straty, sadzba podľa zdaniteľných výnosov a minimálna daň (§ 46b). Zaúčtuje sa 591 / 341. Ak sa po výpočte zmenia doklady, výpočet treba zopakovať.</li>
<li><strong>Uzavrieť rok</strong> – uzávierkové doklady <code>UZ</code> (710, 702), uzamknutie roka a v novom roku počiatočné stavy cez 701 vrátane saldokonta po VS a partneroch. Výsledok hospodárenia ide na 431.</li>
</ol>
<p class="jhelp-warn"><strong>Zrušenie uzávierky</strong> stornuje doklady UZ a počiatočné stavy nového roka a vráti pôvodné uzamknutie mesiacov. Použite ho len na opravy pred podaním závierky.</p>
HTML
	);

	$s[] = array( 'terminy', 'Termíny, DPPO a rozdelenie výsledku', 'dashicons-clock', <<<'HTML'
<h3>Kalendár povinností</h3>
<p><em>Termíny a daň</em> ukazuje termíny roka: daňové priznanie k dani z príjmov PO a úhrada dane, uloženie účtovnej závierky do registra, preddavky na daň (mesačné alebo štvrťročné podľa výšky poslednej dane). Ak podáte oznámenie o predĺžení lehoty, zvoľte predĺženie o 3 alebo 6 mesiacov – termíny sa posunú.</p>
<h3>Pracovný list DPPO</h3>
<p>Podklad k riadkom daňového priznania: výsledok hospodárenia, pripočítateľné položky (nedaňové náklady na analytikách účtov), odpočítateľné položky, základ dane, odpočet daňovej straty, sadzba a minimálna daň. Pracovný list sa dá vytlačiť pre účtovníka.</p>
<h3>Rozdelenie výsledku hospodárenia</h3>
<p>Po schválení závierky valným zhromaždením zadajte rozdelenie:</p>
<ul>
<li><strong>Zisk</strong> z 431 – do nerozdeleného zisku (428), na výplatu podielov spoločníkom (364) alebo do fondov (421).</li>
<li><strong>Strata</strong> z 431 – na neuhradenú stratu (429) alebo úhrada z nerozdeleného zisku (428).</li>
</ul>
<p>Zaúčtuje sa interným dokladom <code>ID</code> v aktuálnom roku.</p>
HTML
	);

	$s[] = array( 'zavierka', 'Účtovná závierka Úč MÚJ', 'dashicons-media-document', <<<'HTML'
<p><em>Účtovná závierka</em> zostaví súvahu a výkaz ziskov a strát mikro účtovnej jednotky (Úč MÚJ) z obratovej predvahy. Každý riadok sa dá rozkliknúť na účty, z ktorých vznikol.</p>
<ul>
<li>Kontrola: aktíva = pasíva, výsledok vo výkaze = výsledok v súvahe.</li>
<li><strong>Poznámky Úč MÚJ 3-01</strong> (Uzávierka → Poznámky) sa zostavia z údajov firmy, účtovníctva a majetku; texty sa dajú upraviť a vytlačiť.</li>
<li>Export riadkov do CSV pre prepis do formulára Finančnej správy.</li>
</ul>
HTML
	);

	$s[] = array( 'riesenie', 'Na riešenie, import a nastavenia', 'dashicons-flag', <<<'HTML'
<h3>Na riešenie</h3>
<p>Zoznam úloh, rozhodnutí a otázok pre účtovníka. Automatické kontroly (napr. záporná pokladňa, faktúra zmenená v uzamknutom mesiaci, chýbajúci majetok k účtu 042) pridávajú položky samy raz denne. Položka sa uzavrie vyriešením alebo ručne s poznámkou.</p>
<h3>Import z OBERON-u</h3>
<p><em>Import (OBERON)</em> prenesie účtovníctvo z programu OBERON (Exalogic) po rokoch: účtový rozvrh, doklady a počiatočné stavy. Importované roky slúžia ako história; účtovanie v Jupiteri pokračuje od dátumu začiatku.</p>
<h3>Nastavenia</h3>
<ul>
<li>Predvolené účty (tržby, náklady, DPH, zálohy, poplatky).</li>
<li>Pravidlá pre banku (IBAN, názov, text → účet).</li>
<li>Dátum uzamknutia – všetko do tohto dátumu je uzamknuté.</li>
</ul>
HTML
	);

	$s[] = array( 'faq', 'Časté otázky a problémy', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Problém</th><th>Riešenie</th></tr></thead>
<tbody>
<tr><td>Faktúra sa nezaúčtovala.</td><td>Skontrolujte dátum (pred začiatkom účtovania sa neúčtuje), uzamknutý mesiac a chyby na Prehľade. Nie je doklad vylúčený z automatického účtovania?</td></tr>
<tr><td>Banka nesedí s výpisom.</td><td>Odsúhlasenie banky ukáže mesiac s rozdielom; pozrite Na zaúčtovanie a dvakrát zaúčtované pohyby.</td></tr>
<tr><td>Stornoval som doklad a vrátil sa.</td><td>Storno automatického dokladu sa pamätá. Ak sa vrátil, zdroj sa medzitým zmenil – vtedy doklad vylúčte z automatického účtovania.</td></tr>
<tr><td>Nedá sa zaúčtovať do minulého mesiaca.</td><td>Mesiac je uzamknutý. Odomknite ho na stránke Uzávierka, alebo opravu zaúčtujte v otvorenom mesiaci.</td></tr>
<tr><td>Daň sa nedá zaúčtovať.</td><td>Najprv dokončite predchádzajúce kroky ročnej uzávierky. Po zmene dokladov treba výpočet dane zopakovať.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'dev', 'Pre vývojárov', 'dashicons-editor-code', <<<'HTML'
<h3>Tabuľky</h3>
<ul>
<li><code>{prefix}juc_accounts</code>, <code>juc_years</code>, <code>juc_entries</code> (hlavička dokladu so zdrojom <code>source / source_type / source_id / source_hash</code>), <code>juc_lines</code> (riadky MD / Dal s partnerom a VS), <code>juc_issues</code>, <code>juc_issue_log</code>, <code>juc_assets</code>.</li>
</ul>
<h3>Funkcie</h3>
<ul>
<li><code>juc_post( $head, $lines )</code>, <code>juc_storno( $id, $reason )</code>, <code>juc_entry_by_source( $source, $type, $id )</code>, <code>juc_can_post( $date )</code>.</li>
<li><code>juc_sync_source( $source, $type, $id, $built )</code> – vráti posted / updated / same / skip / cancelled.</li>
<li><code>juc_account_balance( $acc, $to )</code>, <code>juc_turnovers( $year_id, $to, $from )</code>, <code>juc_close_tax( $year )</code>.</li>
</ul>
<h3>Háky</h3>
<ul>
<li>Akcie: <code>juc_posted</code>, <code>juc_year_closed</code>, <code>juc_issues_summary</code>.</li>
<li>Filtre: <code>juc_templates</code> (predkontácie), <code>juc_cash_purposes</code>, <code>juc_issues_detect</code>, <code>juc_nontax_accounts</code>, <code>juc_muj_balance_rules</code>.</li>
<li>WP-Cron: <code>juc_autopost</code> (každú hodinu), <code>juc_issues_daily</code> (denne ráno).</li>
</ul>
HTML
	);

	return $s;
}
