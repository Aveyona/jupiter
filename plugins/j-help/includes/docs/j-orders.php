<?php
/**
 * Príručka doplnku J-Objednávky (podstránka J-Pomocník → J-Objednávky).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Objednávky sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.3.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_orders() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: objednávky prehľadnejšie', 'dashicons-clipboard', <<<'HTML'
<p class="jhelp-lead"><strong>J-Objednávky je prehľadnejší zoznam objednávok WooCommerce a vlastné čísla objednávok.</strong> Na jednej obrazovke vidíte stav, zákazníka, platbu aj s dátumom zaplatenia, dopravu, sumu a doklady z J-Fakturácie – a jedným klikom vystavíte faktúru.</p>
<p>Doplnok nič nemení na tom, ako objednávky fungujú: detail objednávky, e-maily aj stavy ostávajú vo WooCommerce. Pôvodný zoznam WooCommerce je naďalej dostupný.</p>
<div class="jhelp-facts">
	<div><b>2</b><span>stránky: Objednávky a Číslovanie</span></div>
	<div><b>9</b><span>stĺpcov v zozname</span></div>
	<div><b>50</b><span>objednávok na stránku</span></div>
	<div><b>3</b><span>druhy dokladov v zozname (ZF, F, D)</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Zoznam objednávok:</strong> stavy s počtami, vyhľadávanie aj podľa vlastného čísla, filter platby a obdobia.</li>
<li><strong>Doklady:</strong> zálohová faktúra, faktúra a dobropis pri objednávke so stavom a PDF; Vystaviť faktúru a Pregenerovať priamo v riadku.</li>
<li><strong>Na fakturáciu:</strong> vybavené objednávky, ktoré ešte nemajú faktúru – nič sa neprehliadne.</li>
<li><strong>Hromadné akcie:</strong> zmena stavu, vybavenie a vystavenie faktúr naraz.</li>
<li><strong>Čísla objednávok:</strong> prefix + poradové číslo (napr. <code>220260001</code>), náhrada doplnku My Order Numbers.</li>
</ul>
<p class="jhelp-tip"><strong>Dobré vedieť:</strong> klik na číslo objednávky otvorí bežný detail WooCommerce. J-Objednávky je „okno“ na objednávky, nie ich nová evidencia.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>Jupiter → Objednávky</strong> (ak je zlúčenie doplnkov Jupiter vypnuté, ako samostatná položka <strong>J-Objednávky</strong>).</p>
<table class="jhelp-table">
<thead><tr><th>Stránka</th><th>Na čo slúži</th><th>Kto ju vidí</th></tr></thead>
<tbody>
<tr><td><strong>Objednávky</strong></td><td>Zoznam objednávok s filtrami, dokladmi a hromadnými akciami.</td><td>každý, kto môže upravovať objednávky (administrátor, správca obchodu)</td></tr>
<tr><td><strong>Číslovanie</strong></td><td>Vlastné čísla objednávok: stav, nastavenie, história zmien.</td><td>administrátor a správca obchodu</td></tr>
</tbody>
</table>
<p>Hore na stránke Objednávky sú tlačidlá <em>Pridať objednávku</em> (nová objednávka vo WooCommerce) a <em>Zoznam WooCommerce</em> (pôvodný zoznam).</p>
HTML
	);

	$s[] = array( 'start', 'Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Otvorte zoznam.</strong> <em>Jupiter → Objednávky</em>: najnovšie objednávky sú hore, nad nimi stavy s počtami.</li>
<li><strong>Nájdite objednávku.</strong> Do poľa hľadania napíšte číslo objednávky, meno, e-mail alebo telefón a kliknite <em>Filtrovať</em>.</li>
<li><strong>Vybavte objednávku.</strong> Klik na číslo otvorí detail WooCommerce; stav zmeníte tam alebo hromadne v zozname.</li>
<li><strong>Vystavte faktúru.</strong> Pri vybavenej objednávke bez faktúry kliknite v stĺpci Doklady <em>Vystaviť faktúru</em>. Odkaz <em>Na fakturáciu</em> ukáže všetky vybavené objednávky bez faktúry.</li>
<li><strong>Čísla objednávok (voliteľne).</strong> V <em>Objednávky → Číslovanie</em> zapnite vlastné čísla, zadajte prefix a počet číslic a uložte.</li>
</ol>
HTML
	);

	$s[] = array( 'zoznam', 'Zoznam objednávok', 'dashicons-list-view', <<<'HTML'
<h3>Stavy</h3>
<p>Nad zoznamom sú stavy s počtom objednávok (Čaká na platbu, Spracováva sa, Pozastavené, Vybavené, Zrušené…). Zobrazia sa len stavy, v ktorých nejaká objednávka je; klik ukáže len daný stav. <em>Všetky</em> ukáže všetky objednávky okrem konceptov z pokladne – to sú rozpísané nákupy, ktoré zákazník neodoslal (WooCommerce ich po čase sám zmaže).</p>
<h3>Stĺpce</h3>
<table class="jhelp-table">
<tbody>
<tr><td><strong>Objednávka</strong></td><td>Číslo (vlastné alebo WooCommerce); ikona bubliny = zákazník napísal poznámku – prečítate ju podržaním myši nad ikonou.</td></tr>
<tr><td><strong>Dátum</strong></td><td>Dátum a čas vytvorenia.</td></tr>
<tr><td><strong>Stav</strong></td><td>Stav WooCommerce vo farbe ako v pôvodnom zozname.</td></tr>
<tr><td><strong>Zákazník</strong></td><td>Meno, firma a e-mail z fakturačných údajov.</td></tr>
<tr><td><strong>Platba</strong></td><td>Spôsob platby; pri zaplatenej objednávke aj <em>zaplatené</em> s dátumom.</td></tr>
<tr><td><strong>Doprava</strong></td><td>Zvolený spôsob dopravy.</td></tr>
<tr><td><strong>Suma</strong></td><td>Celková suma objednávky.</td></tr>
<tr><td><strong>Doklady</strong></td><td>Doklady z J-Fakturácie a tlačidlá Vystaviť / Pregenerovať (kapitola Doklady a fakturácia).</td></tr>
</tbody>
</table>
<p>Na stránke je 50 objednávok; ďalšie sú na ďalších stránkach (číslovanie strán pod zoznamom). Vpravo nad zoznamom je počet objednávok pre aktuálny filter.</p>
HTML
	);

	$s[] = array( 'filtre', 'Vyhľadávanie a filtre', 'dashicons-search', <<<'HTML'
<ul>
<li><strong>Hľadanie:</strong> číslo objednávky (aj vlastné, napr. <code>220260244</code>, alebo ID pri objednávkach bez vlastného čísla), meno, e-mail, telefón, adresa.</li>
<li><strong>Platba:</strong> len objednávky s vybraným spôsobom platby. Ponúkajú sa zapnuté spôsoby platby.</li>
<li><strong>Obdobie od – do:</strong> podľa dátumu vytvorenia objednávky, vrátane oboch dní.</li>
<li>Filtre sa dajú kombinovať so stavom aj s <em>Na fakturáciu</em>. <em>Zrušiť filter</em> vráti celý zoznam.</li>
</ul>
<p class="jhelp-tip"><strong>Tip:</strong> adresa stránky obsahuje filter – zoznam „Čaká na platbu cez prevod“ si môžete uložiť ako záložku v prehliadači.</p>
HTML
	);

	$s[] = array( 'doklady', 'Doklady a fakturácia', 'dashicons-media-text', <<<'HTML'
<p>Ak je zapnutá J-Fakturácia, stĺpec <strong>Doklady</strong> ukazuje doklady objednávky:</p>
<table class="jhelp-table">
<tbody>
<tr><td><strong>ZF</strong></td><td>Zálohová faktúra (proforma), napr. pri platbe prevodom.</td></tr>
<tr><td><strong>F</strong></td><td>Faktúra.</td></tr>
<tr><td><strong>D</strong></td><td>Dobropis.</td></tr>
</tbody>
</table>
<p>Pri doklade je jeho číslo (odkaz do J-Fakturácie), stav – vystavená, uhradená, po splatnosti – a odkaz <em>PDF</em>. Stornované doklady sa nezobrazujú. Faktúra z doplnku Woo Faktura, ktorá ešte nie je prevzatá do J-Fakturácie, je sivá s označením <em>Woo Faktura</em>.</p>
<h3>Vystaviť a Pregenerovať</h3>
<ul>
<li><strong>Vystaviť faktúru</strong> – pri objednávke bez faktúry. Faktúra sa vystaví podľa nastavení J-Fakturácie.</li>
<li><strong>Pregenerovať</strong> – pri objednávke s faktúrou: faktúra sa vytvorí znova z aktuálnej objednávky (napr. po oprave adresy). Pred akciou sa zobrazí potvrdenie.</li>
<li>Tlačidlá nie sú pri zrušených, neúspešných, refundovaných a nezaplatených (Čaká na platbu) objednávkach a vidí ich len administrátor a správca obchodu.</li>
<li>Kým beží Woo Faktura (režim prechodu), tlačidlá sa nezobrazujú – faktúry vystavuje ona.</li>
</ul>
<h3>Na fakturáciu</h3>
<p>Odkaz <strong>Na fakturáciu</strong> pri stavoch ukáže vybavené objednávky za posledných 90 dní, ktoré nemajú platnú faktúru. Číslo v zátvorke je ich počet – keď nie je nula, odkaz je zvýraznený. So zvoleným obdobím od – do sa hľadá v tomto období.</p>
<p>Ktorý stav znamená „vybavená“, nastavíte v <em>J-Fakturácia → Nastavenia → Postup fakturácie</em> (zvyčajne <em>Vybavené</em>).</p>
HTML
	);

	$s[] = array( 'hromadne', 'Hromadné akcie', 'dashicons-forms', <<<'HTML'
<ol class="jhelp-steps">
<li>Zaškrtnite objednávky (zaškrtávacie pole v hlavičke označí všetky na stránke).</li>
<li>Vyberte akciu v zozname <em>Hromadná akcia…</em> a kliknite <em>Použiť</em>. Pred vykonaním sa zobrazí potvrdenie.</li>
</ol>
<table class="jhelp-table">
<thead><tr><th>Akcia</th><th>Čo urobí</th></tr></thead>
<tbody>
<tr><td><strong>Zmeniť stav na …</strong></td><td>Zmení stav, do objednávky zapíše poznámku. Zákazníkom odídu e-maily podľa nastavení WooCommerce (napr. pri Vybavené).</td></tr>
<tr><td><strong>Vybaviť a vystaviť faktúry</strong></td><td>Zmení stav na vybavená a hneď vystaví faktúry objednávkam, ktoré ich ešte nemajú.</td></tr>
<tr><td><strong>Len vystaviť faktúry</strong></td><td>Vystaví faktúry bez zmeny stavu.</td></tr>
</tbody>
</table>
<ul>
<li>Hromadne vystavené faktúry sa zákazníkom <strong>neposielajú e-mailom</strong>; po akcii sa ukáže počet vystavených faktúr.</li>
<li>Ak sa faktúra nevystaví, dôvod je v poznámke objednávky a v hlásení je počet takých objednávok.</li>
<li>Zrušené, neúspešné, refundované a nezaplatené objednávky sa pri faktúrach preskočia.</li>
</ul>
HTML
	);

	$s[] = array( 'cislovanie', 'Číslovanie objednávok', 'dashicons-editor-ol', <<<'HTML'
<p>WooCommerce čísluje objednávky podľa interného ID (1268, 1270, 1272…) – s medzerami, lebo rovnaké počítadlo používajú aj iné záznamy. J-Objednávky môže dávať objednávkam vlastné čísla bez medzier: <strong>prefix + poradové číslo s pevným počtom číslic + prípona</strong>.</p>
<p>Príklad: prefix <code>22026</code>, 4 číslice → <code>220260001</code>, <code>220260002</code>…</p>
<h3>Stránka Číslovanie</h3>
<table class="jhelp-table">
<tbody>
<tr><td><strong>Stav</strong></td><td>Či je číslovanie zapnuté, posledná objednávka, posledné použité poradové číslo a číslo, ktoré dostane ďalšia objednávka.</td></tr>
<tr><td><strong>Vlastné čísla objednávok</strong></td><td>Zapnuté / vypnuté. Po zapnutí dostávajú vlastné číslo <strong>len nové objednávky</strong>; staršie si ponechajú číslo WooCommerce. Po vypnutí ostávajú pridelené čísla.</td></tr>
<tr><td><strong>Prefix a prípona</strong></td><td>Písmená, číslice, „-“, „_“, „/“ (najviac 20 znakov). Prípona je zvyčajne prázdna.</td></tr>
<tr><td><strong>Počet číslic</strong></td><td>Poradové číslo sa doplní nulami zľava (1 až 10 číslic).</td></tr>
<tr><td><strong>Posledné použité poradové číslo</strong></td><td>Ďalšia objednávka dostane toto číslo + 1.</td></tr>
<tr><td><strong>História zmien</strong></td><td>Posledných 20 zmien nastavenia: kto, kedy a čo zmenil.</td></tr>
</tbody>
</table>
<h3>Kedy objednávka dostane číslo</h3>
<ul>
<li>Pri <strong>odoslaní objednávky</strong> v pokladni – klasickej aj blokovej. Koncept, ktorý bloková pokladňa vytvorí pri otvorení stránky, číslo nedostane, takže opustené nákupy nemíňajú čísla.</li>
<li>Pri objednávke založenej v administrácii – po jej uložení.</li>
<li>Pri objednávke z REST API alebo z iného doplnku – hneď po vytvorení.</li>
<li>Ak by vypočítané číslo už mala iná objednávka, automaticky sa preskočí. Dve objednávky v tej istej sekunde nedostanú rovnaké číslo (prideľovanie je pod zámkom).</li>
</ul>
<h3>Nový rok</h3>
<ol class="jhelp-steps">
<li>Na začiatku roka otvorte <em>Objednávky → Číslovanie</em>.</li>
<li>Zmeňte prefix (napr. <code>22026</code> → <code>22027</code>) a posledné poradové číslo na <code>0</code>.</li>
<li>Uložte – ďalšia objednávka dostane <code>220270001</code>.</li>
</ol>
<p class="jhelp-warn"><strong>Pozor:</strong> zmena roka sa zatiaľ robí ručne. Automatický nový rok je v pláne vývoja.</p>
<p>Číslo objednávky sa zobrazuje všade, kde WooCommerce ukazuje číslo: v e-mailoch, v Môj účet, vo faktúrach. Sledovanie objednávky a vyhľadávanie WooCommerce fungujú aj podľa vlastného čísla.</p>
HTML
	);

	$s[] = array( 'prechod', 'Prechod z My Order Numbers', 'dashicons-migrate', <<<'HTML'
<p>J-Objednávky nahrádza doplnok <strong>My Order Numbers</strong> (aj pôvodný „Woo Custom and Sequential Order Number“). Čísla ukladá do rovnakého údaju objednávky, takže existujúce objednávky si čísla ponechajú.</p>
<ol class="jhelp-steps">
<li><strong>Pri aktivácii</strong> J-Objednávky prevezme nastavenie (prefix, číslice, prípona), počítadlo aj históriu zmien. Počítadlo nikdy nezaostane za najvyšším už použitým číslom.</li>
<li><strong>Kým je My Order Numbers zapnutý</strong>, čísla prideľuje on a J-Objednávky ich len zobrazuje a vyhľadáva. Na stránke Číslovanie je o tom upozornenie. Dve počítadlá nikdy nebežia naraz.</li>
<li><strong>Po vypnutí My Order Numbers</strong> prevezme číslovanie J-Objednávky a nadviaže na jeho posledné číslo (aj keď medzitým ďalej číslovalo).</li>
<li>Potom môžete My Order Numbers vymazať.</li>
</ol>
HTML
	);

	$s[] = array( 'problemy', 'Keď niečo nejde', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Problém</th><th>Príčina a riešenie</th></tr></thead>
<tbody>
<tr><td><strong>Objednávky majú čísla 1268, 1270…</strong></td><td>Vlastné číslovanie je vypnuté – zapnite ho v <em>Objednávky → Číslovanie</em>. Staršie objednávky si ponechajú pôvodné číslo.</td></tr>
<tr><td><strong>Upozornenie „Čísla prideľuje My Order Numbers“</strong></td><td>Starý doplnok je ešte zapnutý. Vypnite ho v Doplnkoch – J-Objednávky nadviaže na jeho posledné číslo.</td></tr>
<tr><td><strong>Ďalšie číslo je vyššie, ako som nastavil</strong></td><td>Číslo už mala iná objednávka, preto sa preskočilo. Skontrolujte prefix – po zmene roka treba zmeniť aj prefix.</td></tr>
<tr><td><strong>Chýba tlačidlo Vystaviť faktúru</strong></td><td>Objednávka je nezaplatená, zrušená alebo refundovaná; alebo beží Woo Faktura (režim prechodu); alebo nemáte právo spravovať obchod.</td></tr>
<tr><td><strong>Objednávka nie je v Na fakturáciu</strong></td><td>Nie je v stave „vybavená“ podľa J-Fakturácie, je staršia ako 90 dní (zadajte obdobie od – do), alebo už má faktúru (aj z Woo Faktury).</td></tr>
<tr><td><strong>Faktúra sa hromadne nevystavila</strong></td><td>Dôvod je v poznámke objednávky (napr. chýbajúce údaje). Opravte objednávku a vystavte faktúru tlačidlom v riadku.</td></tr>
<tr><td><strong>Hľadanie nič nenašlo</strong></td><td>Skontrolujte, či nie je zvolený stav alebo filter platby a obdobia – hľadá sa v rámci filtra. <em>Zrušiť filter</em> ho vypne.</td></tr>
<tr><td><strong>Objednávka chýba v zozname Všetky</strong></td><td>Ide o koncept z pokladne (zákazník nákup nedokončil), alebo je v koši WooCommerce.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'technicke', 'Technické údaje (pre vývojára)', 'dashicons-editor-code', <<<'HTML'
<h3>Údaje</h3>
<ul>
<li>Číslo objednávky: meta <code>_wcson_order_number</code> (rovnaké ako My Order Numbers) – v <code>postmeta</code> alebo pri HPOS v <code>wc_orders_meta</code>.</li>
<li>Nastavenie: voľba <code>jord_numbers</code> (<code>enabled</code>, <code>prefix</code>, <code>suffix</code>, <code>digits</code>, <code>last</code>, <code>since</code> = čas zapnutia), história <code>jord_numbers_history</code> (posledných 50).</li>
<li>Prideľovanie pod zámkom MySQL <code>GET_LOCK</code>; keď zámok nie je dostupný, pokus sa zopakuje o minútu (WP-Cron <code>jord_assign_retry</code>).</li>
</ul>
<h3>Háky, pri ktorých sa prideľuje číslo</h3>
<p><code>woocommerce_checkout_update_order_meta</code>, <code>woocommerce_store_api_checkout_order_processed</code>, <code>woocommerce_new_order</code>, <code>woocommerce_rest_insert_shop_order_object</code>, <code>woocommerce_order_status_changed</code>, <code>woocommerce_process_shop_order_meta</code>, <code>woocommerce_before_resend_order_emails</code>. Koncepty (<code>checkout-draft</code>, <code>auto-draft</code>) a objednávky spred zapnutia číslovania sa preskočia.</p>
<h3>Funkcie a filtre</h3>
<ul>
<li><code>jord_find_order( $number )</code> – ID objednávky podľa čísla.</li>
<li><code>jord_assign( $order )</code> – pridelí číslo, ak ho objednávka ešte nemá.</li>
<li>Filter <code>jord_tobill_days</code> – počet dní pre Na fakturáciu (predvolene 90).</li>
<li>Filter <code>woocommerce_order_number</code> – zobrazenie vlastného čísla; vyhľadávanie WooCommerce rozšírené o meta čísla.</li>
</ul>
<p>Zoznam číta objednávky cez <code>wc_get_orders</code>, takže funguje s klasickým úložiskom aj s HPOS. Vyžaduje PHP 8.1 a WordPress 6.5; prepojenie s dokladmi vyžaduje J-Fakturáciu.</p>
HTML
	);

	return $s;
}
