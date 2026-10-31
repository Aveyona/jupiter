<?php
/**
 * Príručka doplnku J-Kupóny (podstránka J-Pomocník → J-Kupóny).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Kupóny sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.2.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_kupony() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: zľavové kódy', 'dashicons-tickets-alt', <<<'HTML'
<p class="jhelp-lead"><strong>J-Kupóny vytvárajú a evidujú zľavové kódy pre obchod.</strong> Kód sa vygeneruje sám, zľavu a podmienky nastavíte v jednom formulári a v evidencii vidíte, kto kód použil a akú zľavu dostal.</p>
<div class="jhelp-facts">
	<div><b>1 / 500</b><span>kupónov naraz – jeden kód alebo celá séria</span></div>
	<div><b>4 / 6</b><span>znakov má predvolene kód / kód v sérii</span></div>
	<div><b>4</b><span>stavy: platný, vyčerpaný, po platnosti, vypnutý</span></div>
	<div><b>CSV</b><span>export a tlač kódov série</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Nový kupón</strong> s automaticky vygenerovaným kódom bez zameniteľných znakov (0/O, 1/I), voliteľne s predponou, alebo s vlastným kódom.</li>
<li><strong>Série</strong> – desiatky až stovky kódov s rovnakými podmienkami, napr. do balíkov alebo na podujatie.</li>
<li><strong>Zľava</strong> v percentách alebo v eurách, <strong>doprava zadarmo</strong> pri každom spôsobe dopravy.</li>
<li><strong>Podmienky:</strong> platnosť, počet použití, limit na zákazníka, minimálny nákup, e-mail, kategórie, produkty, kombinovanie.</li>
<li><strong>Evidencia, detail a štatistika:</strong> objednávky s kódom, poskytnutá zľava, tržby, najpoužívanejšie kódy.</li>
</ul>
<p class="jhelp-tip">Kupóny sú bežné kupóny WooCommerce. Fungujú v blokovom aj klasickom košíku a pokladni a vidno ich aj v <em>Marketing → Kupóny</em>.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>J-Kupóny</strong> (pri zlúčení doplnkov pod <strong>Jupiter → Kupóny</strong>).</p>
<table class="jhelp-table">
<thead><tr><th>Miesto</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Kupóny</strong></td><td>Štatistika za mesiac, evidencia všetkých kódov, filtre podľa stavu, vyhľadávanie, série.</td></tr>
<tr><td><strong>Detail kupónu</strong> (klik na kód)</td><td>Podmienky, objednávky s kódom, poskytnutá zľava, tržby, Duplikovať, Vypnúť.</td></tr>
<tr><td><strong>Nový kupón</strong></td><td>Jeden kupón alebo séria.</td></tr>
<tr><td><strong>Nastavenia</strong></td><td>Dĺžka kódu, predpona, doprava zadarmo, upozornenie na koniec platnosti.</td></tr>
<tr><td><strong>Nástenka Jupiter</strong></td><td>Na dlaždici Kupóny je počet platných kupónov a upozornenie na tie, ktorým čoskoro končí platnosť.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'start', 'Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Zapnite kupóny vo WooCommerce.</strong> WooCommerce → Nastavenia → Všeobecné → <em>Povoliť používanie kupónov</em>. Ak sú vypnuté, J-Kupóny to ukážu v hornej časti stránky.</li>
<li><strong>Nový kupón.</strong> J-Kupóny → Nový kupón: kód je už vygenerovaný, zadajte zľavu (napr. 10 %) a dátum Platí do.</li>
<li><strong>Vytvoriť kupón.</strong> Otvorí sa detail kupónu s kódom na skopírovanie.</li>
<li><strong>Vyskúšajte.</strong> V obchode vložte tovar do košíka a zadajte kód. Zľava sa má ukázať v súhrne košíka.</li>
</ol>
HTML
	);

	$s[] = array( 'novy', 'Nový kupón – všetky polia', 'dashicons-edit', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Pole</th><th>Význam</th></tr></thead>
<tbody>
<tr><td><strong>Počet kupónov</strong></td><td>1 = jeden kupón. Viac = séria, každý kupón má vlastný kód (pozri Série).</td></tr>
<tr><td><strong>Predpona kódu</strong></td><td>Pridá sa pred generovaný kód, napr. <code>LETO-</code> → <code>LETO-K7MX</code>.</td></tr>
<tr><td><strong>Kód</strong></td><td>Vygenerovaný automaticky; tlačidlo <em>Vygenerovať iný</em> alebo vlastný kód (písmená bez diakritiky, číslice, pomlčka, podčiarkovník, 3 – 30 znakov).</td></tr>
<tr><td><strong>Zľava</strong></td><td>% z košíka alebo € z košíka. <em>Aj doprava zadarmo</em> pridá dopravu zadarmo. Len doprava zadarmo: zľava 0.</td></tr>
<tr><td><strong>Platí do</strong></td><td>Vrátane tohto dňa (do 23:59). Dátum v minulosti sa neprijme. Prázdne = bez obmedzenia.</td></tr>
<tr><td><strong>Počet použití</strong></td><td>Koľkokrát sa dá kód použiť spolu. Predvolene 1 = jednorazový kód; 0 = neobmedzene.</td></tr>
<tr><td><strong>Na jedného zákazníka</strong></td><td>Koľkokrát môže kód použiť ten istý zákazník (podľa účtu alebo e-mailu).</td></tr>
<tr><td><strong>Minimálny nákup</strong></td><td>Kód platí od tejto sumy košíka.</td></tr>
<tr><td><strong>Len pre e-mail</strong></td><td>Kód platí len pre zadané e-maily. Neplatná adresa sa nezahodí – formulár ukáže chybu, aby kupón omylom neplatil pre všetkých.</td></tr>
<tr><td><strong>Len na kategórie / produkty</strong></td><td>Zľava sa počíta len z položiek z týchto kategórií alebo z vybraných produktov.</td></tr>
<tr><td><strong>Kombinovanie</strong></td><td>Predvolene sa kupón nedá použiť spolu s iným kupónom. Zaškrtnutím to povolíte.</td></tr>
<tr><td><strong>Poznámka</strong></td><td>Pre vás, napr. „Facebook súťaž“. Zákazník ju nevidí.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'serie', 'Série hromadných kupónov', 'dashicons-images-alt2', <<<'HTML'
<p>Séria sú kupóny s rovnakými podmienkami, ale každý s vlastným kódom – napr. 100 jednorazových kódov do balíkov alebo pre účastníkov podujatia.</p>
<ol class="jhelp-steps">
<li>Nový kupón → <strong>Počet kupónov</strong> napr. 100 a <strong>Názov série</strong> (napr. „Letáky do balíkov“).</li>
<li>Nastavte zľavu a podmienky a kliknite <em>Vytvoriť kupón</em>. Kódy sa ukážu v poli na skopírovanie.</li>
<li><strong>Stiahnuť CSV</strong> – kódy do tabuľky (napr. pre tlačiareň). <strong>Tlačiť kódy</strong> – kartičky s kódom, zľavou a platnosťou (tlačia sa len platné).</li>
</ol>
<p>Na stránke Kupóny je tabuľka <strong>Série hromadných kupónov</strong> s počtom platných a použitých kódov. V detaile série môžete <em>Vypnúť celú sériu</em> naraz alebo vypnuté kupóny znova zapnúť.</p>
<p class="jhelp-tip">Kódy v sérii majú predvolene 6 znakov (Nastavenia → Dĺžka kódu v sérii). Pri stovkách platných kódov by sa kratší kód dal uhádnuť skúšaním v pokladni.</p>
HTML
	);

	$s[] = array( 'evidencia', 'Evidencia a stavy', 'dashicons-list-view', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Stav</th><th>Kedy</th></tr></thead>
<tbody>
<tr><td><strong>Platný</strong></td><td>Zapnutý, v platnosti a nevyčerpaný – zákazník ho môže použiť.</td></tr>
<tr><td><strong>Vyčerpaný</strong></td><td>Použitý toľkokrát, koľko je Počet použití.</td></tr>
<tr><td><strong>Po platnosti</strong></td><td>Uplynul dátum Platí do.</td></tr>
<tr><td><strong>Vypnutý</strong></td><td>Vypnutý tlačidlom Vypnúť (koncept) – v pokladni sa nedá použiť.</td></tr>
</tbody>
</table>
<ul>
<li>Filtre podľa stavu, vyhľadávanie podľa kódu, poznámky alebo série, 50 kupónov na stránku.</li>
<li>Pri kóde vidno zľavu, podmienky, použitie (napr. 1 / 1), sériu a poznámku.</li>
<li><strong>Vypnúť / Zapnúť</strong> jedným kliknutím – napr. keď kód unikol na zľavový portál.</li>
</ul>
HTML
	);

	$s[] = array( 'detail', 'Detail kupónu a štatistika', 'dashicons-chart-bar', <<<'HTML'
<h3>Detail kupónu</h3>
<ul>
<li>Stav, zľava, podmienky a použitie.</li>
<li><strong>Objednávky s kódom</strong> – dátum, zákazník, stav, zľava a suma. Zrušené, neúspešné a vrátené objednávky sú vyblednuté a nerátajú sa.</li>
<li><strong>Poskytnutá zľava</strong> (s DPH) a <strong>tržby s kódom</strong> po vrátení peňazí.</li>
<li><strong>Duplikovať</strong> – otvorí formulár s rovnakými podmienkami a novým kódom.</li>
<li><strong>Upraviť vo WooCommerce</strong> – všetky ďalšie nastavenia (vylúčené produkty, maximálny nákup…).</li>
</ul>
<h3>Štatistika na stránke Kupóny</h3>
<p>Za tento a minulý mesiac: počet objednávok s kupónom, poskytnutá zľava, tržby z týchto objednávok a päť najpoužívanejších kódov. Zrušené objednávky sa nerátajú.</p>
HTML
	);

	$s[] = array( 'doprava', 'Doprava zadarmo', 'dashicons-car', <<<'HTML'
<p>WooCommerce sám uplatní dopravu zadarmo z kupónu len pri spôsobe dopravy <em>Doprava zadarmo</em> s podmienkou „platný kupón na dopravu zadarmo“. Packeta z J-Doprava, pevná cena a iné spôsoby by dopravu účtovali ďalej.</p>
<p>J-Kupóny preto pri kupóne s dopravou zadarmo <strong>vynulujú cenu každého spôsobu dopravy</strong>. Zákazník si vyberie, ako chce, a dopravu neplatí.</p>
<ul>
<li>Vypnúť sa to dá v <em>Nastaveniach → Doprava zadarmo</em> (potom platí len správanie WooCommerce).</li>
<li>Dobierka a iné poplatky za platbu ostávajú – kupón ruší len cenu dopravy.</li>
<li>Kupón len na dopravu: zľava 0 a zaškrtnutá doprava zadarmo.</li>
</ul>
HTML
	);

	$s[] = array( 'prepojenia', 'Faktúra a účtovníctvo', 'dashicons-admin-links', <<<'HTML'
<ul>
<li><strong>J-Fakturácia:</strong> zľava je už v cenách položiek faktúry; na faktúre je informačný riadok „Uplatnená zľava“.</li>
<li><strong>J-Účtovníctvo:</strong> tržby sa účtujú po zľave (z faktúry), zľava sa neúčtuje osobitne.</li>
<li><strong>Doprava zadarmo</strong> – na faktúre je doprava s cenou 0.</li>
<li><strong>J-Objednávky:</strong> kód je v detaile objednávky v časti Kupóny (WooCommerce).</li>
</ul>
HTML
	);

	$s[] = array( 'bezpecnost', 'Bezpečnosť kódov', 'dashicons-shield', <<<'HTML'
<ul>
<li><strong>Dĺžka kódu.</strong> WooCommerce neobmedzuje, koľkokrát môže niekto skúsiť kód v pokladni. Jeden kód so 4 znakmi je v poriadku, pri sériách používajte aspoň 6 znakov.</li>
<li><strong>Jednorazové kódy.</strong> Počet použití 1 (predvolené) – kód sa po použití vyčerpá.</li>
<li><strong>E-mail.</strong> Kód pre konkrétneho zákazníka obmedzte na jeho e-mail.</li>
<li><strong>Únik kódu.</strong> Kód, ktorý sa objavil na zľavovom portáli, vypnite jedným kliknutím; celú sériu tlačidlom Vypnúť celú sériu.</li>
</ul>
HTML
	);

	$s[] = array( 'faq', 'Časté otázky', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Otázka</th><th>Odpoveď</th></tr></thead>
<tbody>
<tr><td>Zákazník nevidí pole na kód.</td><td>Kupóny sú vo WooCommerce vypnuté – Nastavenia → Všeobecné → Povoliť používanie kupónov.</td></tr>
<tr><td>Kód „neplatí“.</td><td>Pozrite stav v evidencii (po platnosti, vyčerpaný, vypnutý) a podmienky – minimálny nákup, e-mail, kategórie, iný kupón v košíku.</td></tr>
<tr><td>Doprava zadarmo sa neuplatnila.</td><td>Skontrolujte Nastavenia → Doprava zadarmo. Kupón musí mať zaškrtnuté „aj doprava zadarmo“.</td></tr>
<tr><td>Použitie v evidencii nesedí s objednávkami.</td><td>WooCommerce započíta použitie aj pri objednávke, ktorá sa potom zruší. V detaile sú zrušené objednávky vyblednuté.</td></tr>
<tr><td>Upravil som kupón vo WooCommerce, platí o deň menej?</td><td>Nie – J-Kupóny po uložení vrátia koniec platnosti na 23:59 zvoleného dňa.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'dev', 'Pre vývojárov', 'dashicons-editor-code', <<<'HTML'
<h3>Údaje</h3>
<ul>
<li>Kupóny sú <code>shop_coupon</code>. Meta J-Kupónov: <code>_jkp</code> (vytvorené tu), <code>_jkp_batch</code> a <code>_jkp_batch_name</code> (séria), <code>_jkp_from</code> (duplikované z), <code>_jkp_by</code> (autor).</li>
<li>Nastavenia v option <code>jkp_settings</code>: <code>len</code>, <code>len_batch</code>, <code>prefix</code>, <code>free_ship</code>, <code>warn_days</code>.</li>
</ul>
<h3>Funkcie</h3>
<ul>
<li><code>jkp_generate_code( $exclude, $batch, $prefix )</code>, <code>jkp_create_coupon( $args )</code>, <code>jkp_validate( $args )</code>.</li>
<li><code>jkp_index()</code> – všetky kupóny jedným dopytom, <code>jkp_batches()</code>, <code>jkp_coupon_uses( $code )</code>, <code>jkp_stats( $from, $to )</code>.</li>
</ul>
<h3>Háky</h3>
<ul>
<li>Filtre: <code>jkp_code_length</code>, <code>jkp_code_alphabet</code>, <code>jkp_free_shipping_rate</code> (vynechať spôsob dopravy z dopravy zadarmo).</li>
<li>Väzby: <code>woocommerce_package_rates</code> (doprava zadarmo), <code>woocommerce_coupon_options_save</code> (platnosť do 23:59), <code>jupiter_tile_status</code>.</li>
</ul>
HTML
	);

	return $s;
}
