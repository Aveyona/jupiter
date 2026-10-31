<?php
/**
 * Príručka doplnku J-Notes (podstránka J-Pomocník → J-Notes).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Notes sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.2.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_notes() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: poznámky k projektom', 'dashicons-welcome-write-blog', <<<'HTML'
<p class="jhelp-lead"><strong>J-Notes sú poznámky k projektom priamo v administrácii.</strong> Plány s úlohami a percentom hotovo, dokumentácia v Markdowne a prehľad toho, na čom sa práve pracuje. Poznámky sa dajú nahrať aj ako súbory .md.</p>
<div class="jhelp-facts">
	<div><b>3</b><span>druhy: plán, dokumentácia, poznámka</span></div>
	<div><b>☐ ◐ ☑</b><span>úlohy – otvorená, rozpracovaná, hotová</span></div>
	<div><b>.md</b><span>nahratie, FTP a export do súborov</span></div>
	<div><b>30 dní</b><span>ostáva zmazaná poznámka v koši</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Plány</strong> s úlohami, ktoré odškrtnete klikom; percento hotovo vidno v zozname aj na karte.</li>
<li><strong>Dokumentácia</strong> v Markdowne – nadpisy, zoznamy, tabuľky, kód, odkazy; náhľad a tlač.</li>
<li><strong>Teraz robím</strong> – karty rozpracovaných plánov s aktuálnymi úlohami a posledným záznamom.</li>
<li><strong>Denník zmien</strong> – rýchly záznam s dátumom do poznámky.</li>
<li><strong>Súbory .md</strong> – nahratie v administrácii alebo cez FTP, každá poznámka sa ukladá aj ako súbor.</li>
</ul>
<p class="jhelp-tip">Plány doplnkov Jupiter (plan-j-fakturacia, plan-j-kupony …) sú v J-Notes – s opravami, vylepšeniami a sekciou <em>Na vás</em>, čo treba skontrolovať.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>J-Notes</strong> (pri zlúčení doplnkov pod <strong>Jupiter → Notes</strong>). Vidí ho len administrátor.</p>
<table class="jhelp-table">
<thead><tr><th>Miesto</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Poznámky</strong></td><td>Karty Teraz robím, zoznam s filtrom (projekt, druh, stav, kôš), hľadanie, nahratie súborov .md.</td></tr>
<tr><td><strong>Zobrazenie poznámky</strong></td><td>Text s úlohami na odškrtnutie, akcie (Teraz robím, Pripnúť, Hotovo, Archivovať, Stiahnuť .md, Tlačiť), denník zmien.</td></tr>
<tr><td><strong>Nová poznámka / Upraviť</strong></td><td>Editor Markdownu s náhľadom; Ctrl+S uloží.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'start', 'Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Nový plán.</strong> J-Notes → <em>Nový plán</em>. Text má pripravenú osnovu: Cieľ, Kroky, Otvorené otázky, Denník zmien.</li>
<li><strong>Úlohy.</strong> Do časti Kroky píšte úlohy: <code>- [ ] Nová úloha</code>. Enter na konci riadku pridá ďalšiu úlohu.</li>
<li><strong>Projekt.</strong> Vyplňte projekt (napr. Jupiter) – podľa neho sa poznámky filtrujú.</li>
<li><strong>Uložiť.</strong> Plán je hneď označený <em>teraz robím</em> a zobrazí sa hore ako karta.</li>
<li><strong>Odškrtávanie.</strong> V zobrazení kliknite na štvorček úlohy: otvorená → rozpracovaná → hotová.</li>
</ol>
HTML
	);

	$s[] = array( 'druhy', 'Druhy a stavy poznámok', 'dashicons-category', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th></th><th>Hodnoty</th><th>Význam</th></tr></thead>
<tbody>
<tr><td><strong>Druh</strong></td><td>Plán, Dokumentácia, Poznámka</td><td>Plán má úlohy a percento; dokumentácia je návod alebo popis; poznámka je čokoľvek iné.</td></tr>
<tr><td><strong>Stav</strong></td><td>Aktívna, Hotová, Archív, Kôš</td><td>Zoznam predvolene ukazuje aktívne. <em>Hotovo</em> a <em>Archivovať</em> zrušia aj „teraz robím“.</td></tr>
<tr><td><strong>Teraz robím</strong></td><td>áno / nie</td><td>Poznámka sa zobrazí hore ako karta.</td></tr>
<tr><td><strong>Pripnutá</strong></td><td>📌</td><td>V zozname je vždy navrchu.</td></tr>
<tr><td><strong>Kľúč (slug)</strong></td><td>napr. plan-j-kupony</td><td>Jedinečné meno poznámky – podľa neho sa aktualizuje zo súboru .md.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'markdown', 'Markdown – ťahák', 'dashicons-editor-code', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Napíšete</th><th>Výsledok</th></tr></thead>
<tbody>
<tr><td><code># Nadpis</code>, <code>## Podnadpis</code></td><td>Nadpisy (každý dostane kotvu na odkaz).</td></tr>
<tr><td><code>**tučné**</code>, <code>*kurzíva*</code>, <code>~~preškrtnuté~~</code>, <code>`kód`</code></td><td>Formátovanie v riadku.</td></tr>
<tr><td><code>- položka</code>, <code>1. položka</code></td><td>Zoznam (vnorený odsadením o 2 medzery).</td></tr>
<tr><td><code>- [ ] úloha</code>, <code>- [~]</code>, <code>- [x]</code></td><td>Úloha otvorená, rozpracovaná, hotová.</td></tr>
<tr><td><code>☐ ◐ ☑</code></td><td>Úlohy so symbolom – aj v číslovanom zozname alebo v poslednom stĺpci tabuľky.</td></tr>
<tr><td><code>| a | b |</code> + riadok <code>|---|---|</code></td><td>Tabuľka.</td></tr>
<tr><td><code>[text](https://…)</code> alebo samotná adresa</td><td>Odkaz (otvorí sa v novom okne).</td></tr>
<tr><td><code>&gt; citát</code>, <code>```</code> … <code>```</code>, <code>---</code></td><td>Citácia, blok kódu, vodorovná čiara.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'ulohy', 'Úlohy a percento hotovo', 'dashicons-yes-alt', <<<'HTML'
<ul>
<li>Percento = hotové úlohy / všetky úlohy v poznámke. Rozpracované sa ukážu v popise pri prechode myšou.</li>
<li>Úlohy v bloku kódu (<code>```</code>) sa nepočítajú – sú to len ukážky.</li>
<li><strong>Odškrtnutie klikom</strong> (aj klávesom Enter alebo medzerníkom): otvorená → rozpracovaná → hotová → otvorená. Zmena sa uloží hneď.</li>
<li>Ak sa poznámka medzitým zmenila (iné okno, načítanie zo súboru), klik overí text úlohy. Keď sa úloha nenájde, nič sa nezmení a objaví sa výzva obnoviť stránku.</li>
<li>Tabuľka etáp: riadok, ktorý končí symbolom ☐ ◐ ☑, je tiež úloha – klik na symbol ho prepne.</li>
</ul>
HTML
	);

	$s[] = array( 'teraz', 'Teraz robím', 'dashicons-clock', <<<'HTML'
<p>Karty hore na stránke Poznámky ukazujú aktívne poznámky označené <em>teraz robím</em>:</p>
<ul>
<li>projekt, názov a percento hotovo,</li>
<li>až tri rozpracované úlohy (◐); ak žiadna nie je, prvú otvorenú ako „Ďalej“,</li>
<li>posledný záznam z denníka zmien.</li>
</ul>
<p>Označenie zapnete alebo vypnete tlačidlom <em>Teraz robím / Už nerobím</em> v zobrazení poznámky. Nový plán ho má zapnuté automaticky.</p>
HTML
	);

	$s[] = array( 'dennik', 'Denník zmien', 'dashicons-backup', <<<'HTML'
<p>Pod textom poznámky je pole <em>Záznam do denníka zmien</em>. Záznam sa pridá s dátumom a časom na koniec sekcie <code>## Denník zmien</code> (aj číslovanej, napr. <code>## 4. Denník zmien</code>); ak sekcia chýba, vytvorí sa na konci poznámky.</p>
<p class="jhelp-tip">Denník je vhodný na rozhodnutia a urobené kroky („Nasadená verzia 0.2.0“, „Dohodnuté s účtovníkom…“). Posledný záznam vidno aj na karte Teraz robím.</p>
HTML
	);

	$s[] = array( 'bezpecie', 'Ochrana pred prepísaním a kôš', 'dashicons-shield', <<<'HTML'
<h3>Súbežná úprava</h3>
<p>Ak máte poznámku otvorenú na úpravu a medzitým sa zmení (odškrtnutá úloha, záznam v denníku, načítanie zo súboru .md), uloženie ju neprepíše. Formulár ostane vyplnený vašou verziou a pod ním je aktuálna verzia. Prevezmite z nej, čo chcete zachovať, a uložte znova.</p>
<h3>Kôš</h3>
<ul>
<li>Tlačidlo <em>Do koša</em> v úprave poznámku nezmaže – presunie ju do koša.</li>
<li>Kôš nájdete vo filtri stavu (<em>Kôš</em>). V poznámke sú tlačidlá <em>Obnoviť</em> a <em>Zmazať natrvalo</em>.</li>
<li>Po 30 dňoch sa poznámka v koši zmaže natrvalo (aj jej súbor v exporte).</li>
</ul>
HTML
	);

	$s[] = array( 'subory', 'Súbory .md', 'dashicons-media-text', <<<'HTML'
<h3>Nahratie</h3>
<ul>
<li>Na stránke Poznámky dole: <em>Nahrať súbory .md</em> (aj viac naraz, .md alebo .txt do 5 MB).</li>
<li>Cez FTP: súbory do <code>wp-content/uploads/j-notes/inbox/</code>. Načítajú sa pri otvorení J-Notes a raz za hodinu, potom sa presunú medzi spracované (tie sa mažú po 90 dňoch).</li>
<li>Poznámka s rovnakým kľúčom sa <strong>aktualizuje</strong> – text zo súboru nahradí text v poznámke.</li>
</ul>
<h3>Hlavička súboru</h3>
<pre><code>---
slug: plan-j-kupony
title: Plán: J-Kupóny
type: plan
project: Jupiter
status: active
now: 1
---
# Text poznámky…</code></pre>
<p>Bez hlavičky je kľúč názov súboru a názov poznámky prvý nadpis.</p>
<h3>Export a ochrana</h3>
<ul>
<li>Každá uložená poznámka sa zapíše aj ako súbor do <code>uploads/j-notes/export-…</code> (priečinok má náhodný názov). Jednu poznámku stiahnete tlačidlom <em>Stiahnuť .md</em>.</li>
<li>Priečinok je zakázaný pre web (.htaccess). Raz denne J-Notes overí, či sa súbory nedajú stiahnuť; ak áno (server bez .htaccess, napr. nginx), ukáže upozornenie s pravidlom pre server.</li>
<li>Ak sa súbor z inboxu nedá presunúť (práva na súbory), načíta sa len raz a J-Notes upozorní, aby ste ho zmazali cez FTP.</li>
</ul>
HTML
	);

	$s[] = array( 'faq', 'Časté otázky', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Otázka</th><th>Odpoveď</th></tr></thead>
<tbody>
<tr><td>Percento nesedí s počtom úloh.</td><td>Úlohy v bloku kódu sa nepočítajú. Riadok musí začínať <code>- [ ]</code>, <code>1. ☐</code> a pod. – medzera za zátvorkou je povinná.</td></tr>
<tr><td>Po nahratí súboru zmizli moje úpravy.</td><td>Súbor s rovnakým kľúčom nahradí text poznámky. Pred nahratím si poznámku stiahnite (.md), upravte a nahrajte späť.</td></tr>
<tr><td>Klik na úlohu hlási „Úloha sa nenašla“.</td><td>Poznámka sa medzitým zmenila – obnovte stránku.</td></tr>
<tr><td>Zmazal som poznámku omylom.</td><td>Filter stavu → Kôš → otvorte poznámku → Obnoviť (do 30 dní).</td></tr>
<tr><td>Čo je My Notes?</td><td>Rovnaký doplnok pre weby rodiny My – má vlastné poznámky, vlastnú tabuľku a vlastný priečinok súborov.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'dev', 'Pre vývojárov', 'dashicons-editor-code', <<<'HTML'
<h3>Údaje</h3>
<ul>
<li>Tabuľka <code>{prefix}jnotes</code>: slug (jedinečný), title, type, project, status (active, done, archived, trash), pinned, now, body (Markdown), počty úloh, source, prev_status, trashed_at.</li>
<li>Option <code>jno_dir_token</code> (prípona priečinkov), <code>jno_inbox_stuck</code>, <code>jno_protection</code>.</li>
</ul>
<h3>Funkcie</h3>
<ul>
<li><code>jno_save( $data, $id = 0 )</code> – nová alebo existujúca poznámka (podľa ID alebo <code>slug</code>); vráti ID. Takto zapisujú plány doplnkov skripty pri nasadení.</li>
<li><code>jno_get( $id )</code>, <code>jno_get_by_slug( $slug )</code>, <code>jno_tasks( $body )</code>, <code>jno_set_task( $id, $line, $state, $hash )</code>, <code>jno_add_log( $id, $text )</code>.</li>
<li><code>jno_trash()</code>, <code>jno_restore()</code>, <code>jno_delete()</code>, <code>jno_import_inbox()</code>, <code>jno_markdown( $md )</code>.</li>
</ul>
<h3>Háky</h3>
<ul>
<li>Akcia <code>jno_saved</code> (po uložení – zápis do exportu), filter <code>jno_trash_days</code>.</li>
<li>WP-Cron <code>jno_inbox</code> každú hodinu (inbox), raz denne kôš, spracované súbory a kontrola ochrany.</li>
</ul>
<p>My Notes sa generuje z J-Notes skriptom <code>tools/build-my-notes.sh</code> (predpona mno_, tabuľka mynotes).</p>
HTML
	);

	return $s;
}
