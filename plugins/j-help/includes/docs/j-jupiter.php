<?php
/**
 * Príručka doplnku Jupiter (podstránka J-Pomocník → Jupiter).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku Jupiter sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.2.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_jupiter() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: nástenka doplnkov', 'dashicons-admin-site-alt3', <<<'HTML'
<p class="jhelp-lead"><strong>Jupiter je hlavný doplnok rodiny.</strong> V ľavom menu je jedna položka Jupiter a pod ňou všetky doplnky J-. Nástenka ukazuje každý doplnok ako dlaždicu s popisom, verziou, stavom a odkazmi a navrchu súhrn toho, čo čaká na vybavenie.</p>
<div class="jhelp-facts">
	<div><b>1</b><span>položka v menu namiesto desiatich</span></div>
	<div><b>🔔</b><span>súhrn Čaká na vás zo všetkých doplnkov</span></div>
	<div><b>📌</b><span>pripnutie obľúbených doplnkov</span></div>
	<div><b>🔎</b><span>hľadanie doplnku alebo stránky</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Nástenka</strong> – dlaždice doplnkov s počítadlami, stavom, verziou, podstránkami, príručkou a aktualizáciou.</li>
<li><strong>Zlúčené menu</strong> – doplnky J- sú pod položkou Jupiter ako rozbaľovací strom.</li>
<li><strong>Navigácia</strong> – na stránke doplnku je hore lišta Jupiter › doplnok a jeho podstránky.</li>
<li><strong>Nastavenia</strong> – zlúčenie menu a farba každého doplnku.</li>
</ul>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V ľavom menu je položka <strong>Jupiter</strong> s ikonou planéty, hneď pod WooCommerce Marketing. Číslo pri nej je súčet počítadiel doplnkov.</p>
<table class="jhelp-table">
<thead><tr><th>Miesto</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Jupiter → Prehľad</strong></td><td>Nástenka doplnkov.</td></tr>
<tr><td><strong>Jupiter → doplnok</strong> (Pošta, Sklad…)</td><td>Hlavná stránka doplnku; šípka vpravo rozbalí jeho podstránky.</td></tr>
<tr><td><strong>Jupiter → Nastavenia</strong></td><td>Zlúčenie menu a farby doplnkov.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'nastenka', 'Nástenka a dlaždice', 'dashicons-grid-view', <<<'HTML'
<h3>Čaká na vás</h3>
<p>Riadok navrchu zbiera počítadlá všetkých doplnkov a ich stránok – napr. <em>Fakturácia › Banka 54</em>, <em>Účtovníctvo › Na zaúčtovanie 8</em>, <em>Účtovníctvo › Na riešenie 20</em>. Klik otvorí príslušnú stránku. Keď nič nečaká, riadok sa neukáže.</p>
<h3>Hľadanie</h3>
<p>Pole pod súhrnom filtruje dlaždice podľa názvu, popisu a podstránok (bez ohľadu na diakritiku). Nájdené podstránky sa zvýraznia; <kbd>Enter</kbd> otvorí prvú nájdenú.</p>
<h3>Dlaždica</h3>
<table class="jhelp-table">
<thead><tr><th>Časť</th><th>Význam</th></tr></thead>
<tbody>
<tr><td>Ikona, názov, popis</td><td>Klik otvorí hlavnú stránku doplnku.</td></tr>
<tr><td>Červené číslo</td><td>Počet vecí na vybavenie.</td></tr>
<tr><td>Stav (bodka a text)</td><td>Napr. <em>Webhook funguje</em> (Platby), <em>Platných 1, 1 končí do 7 dní</em> (Kupóny). Zelená = v poriadku, oranžová = pozor, červená = chyba.</td></tr>
<tr><td>Verzia</td><td>Nainštalovaná verzia doplnku.</td></tr>
<tr><td>Dostupná verzia + Aktualizovať</td><td>Na GitHube je novšia verzia – tlačidlo ju nainštaluje.</td></tr>
<tr><td>Podstránky</td><td>Odkazy na stránky doplnku s počítadlami; ďalšie sú pod <em>+ ďalšie</em>.</td></tr>
<tr><td>Ikona knihy</td><td>Príručka doplnku v J-Pomocníkovi (ukáže sa po prejdení myšou).</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'pripnutie', 'Pripnutie', 'dashicons-admin-post', <<<'HTML'
<ul>
<li>Po prejdení myšou je na dlaždici špendlík. Klik doplnok pripne – zobrazí sa v časti <strong>Pripnuté</strong> navrchu.</li>
<li>Pripnutie je pre každého používateľa zvlášť.</li>
<li>Ďalší klik na špendlík doplnok odopne. Pripnutie odinštalovaného doplnku sa zabudne samo.</li>
</ul>
HTML
	);

	$s[] = array( 'zlucenie', 'Menu a zlúčenie doplnkov', 'dashicons-menu-alt3', <<<'HTML'
<p>Pri zapnutom zlúčení (predvolené) je v ľavom menu len Jupiter. Pod ním sú doplnky v poradí Pošta, Sklad, Objednávky, Doprava, Platby, Fakturácia, Účtovníctvo, Kupóny, Notes, Pomocník.</p>
<ul>
<li>Šípka pri doplnku rozbalí jeho podstránky; rozbalenie si menu pamätá. Otvorený doplnok je rozbalený vždy.</li>
<li>Počítadlá podstránok (napr. <em>Na zaúčtovanie 8</em>) ostávajú aj v zlúčenom menu.</li>
<li>Pod Jupiter sa presúvajú len doplnky z rodiny Jupiter (adresa Aveyona/jupiter) – cudzí doplnok s podobným názvom ostane na svojom mieste.</li>
<li>Poradie platí aj vtedy, keď J-Pomocník nie je zapnutý.</li>
</ul>
<p class="jhelp-tip">Vypnutím zlúčenia (Nastavenia) má každý doplnok J- vlastnú položku v menu ako predtým.</p>
HTML
	);

	$s[] = array( 'navigacia', 'Navigácia na stránke doplnku', 'dashicons-admin-links', <<<'HTML'
<p>Na každej stránke doplnku J- je hore lišta vo farbe doplnku: <strong>Jupiter › Účtovníctvo</strong> a vpravo jeho podstránky ako tlačidlá (aktuálna je vyplnená). Klik na Jupiter vráti na nástenku. Lišta sa zobrazuje pri zlúčenom menu.</p>
HTML
	);

	$s[] = array( 'nastavenia', 'Nastavenia a farby', 'dashicons-admin-generic', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Nastavenie</th><th>Význam</th></tr></thead>
<tbody>
<tr><td><strong>Zlúčiť doplnky do položky Jupiter</strong></td><td>Zapnuté: v menu je len Jupiter. Vypnuté: každý doplnok má vlastnú položku.</td></tr>
<tr><td><strong>Farby doplnkov</strong></td><td>Farba dlaždice a navigačnej lišty. Nový doplnok dostane farbu automaticky.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'aktualizacie', 'Aktualizácie a vypnuté doplnky', 'dashicons-update', <<<'HTML'
<ul>
<li><strong>Aktualizácie</strong> doplnkov Jupiter hľadá J-Pomocník na GitHube (Aveyona/jupiter). Keď je novšia verzia, ukáže sa na dlaždici <em>Dostupná verzia … Aktualizovať</em> aj v Nástenka → Aktualizácie.</li>
<li><strong>Vypnuté doplnky</strong> – nainštalované, ale vypnuté doplnky Jupiter sú dole na nástenke s tlačidlom <em>Zapnúť</em> (vidí ho len ten, kto môže zapínať pluginy).</li>
</ul>
HTML
	);

	$s[] = array( 'faq', 'Časté otázky', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Otázka</th><th>Odpoveď</th></tr></thead>
<tbody>
<tr><td>Nevidím doplnok pod Jupiterom.</td><td>Doplnok je vypnutý (dole na nástenke Vypnuté doplnky), alebo nemáte oprávnenie na jeho stránky (napr. J-Notes vidí len administrátor).</td></tr>
<tr><td>Chcem staré menu s každým doplnkom zvlášť.</td><td>Jupiter → Nastavenia → vypnúť Zlúčiť doplnky.</td></tr>
<tr><td>Číslo pri Jupiteri nesedí so súhrnom.</td><td>Pri položke Jupiter je súčet počítadiel doplnkov; súhrn Čaká na vás ukazuje aj počítadlá jednotlivých stránok.</td></tr>
<tr><td>Ikona knihy na dlaždici chýba.</td><td>Doplnok ešte nemá príručku alebo J-Pomocník nie je zapnutý.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'dev', 'Pre vývojárov', 'dashicons-editor-code', <<<'HTML'
<p>Doplnok sa zaradí do rodiny Jupiter, keď je v priečinku <code>j-…</code> a v hlavičke má <code>Plugin URI</code> s Aveyona/jupiter alebo popis „Súčasť rodiny Jupiter“. Jeho stránky v menu musia mať adresu začínajúcu názvom priečinka (<code>j-kupony</code>, <code>j-kupony-new</code>).</p>
<table class="jhelp-table">
<thead><tr><th>Filter</th><th>Na čo</th></tr></thead>
<tbody>
<tr><td><code>jupiter_menu_order</code></td><td>Poradie doplnku pod Jupiterom: <code>$o['j-kupony'] = 40;</code></td></tr>
<tr><td><code>jupiter_plugins</code></td><td>Názov, ikona, popis (<code>what</code>) a stav doplnku pre nástenku a J-Pomocník.</td></tr>
<tr><td><code>jupiter_tile_status</code></td><td>Stav na dlaždici: vráti <code>[ 'ok'|'warn'|'err', text ]</code> pre svoj slug.</td></tr>
<tr><td><code>jupiter_guides</code>, <code>jupiter_roadmap</code></td><td>Návody a plán vývoja v J-Pomocníkovi.</td></tr>
</tbody>
</table>
<p>Počítadlo doplnku alebo podstránky je bežná bublina WordPressu v názve položky menu (<code>&lt;span class="awaiting-mod"&gt;</code>) – Jupiter ju prevezme do zlúčeného menu, na dlaždicu aj do súhrnu.</p>
<p>Údaje: option <code>jju_settings</code> (merge), <code>jju_colors</code>; používateľská meta <code>jju_pins</code>.</p>
HTML
	);

	return $s;
}
