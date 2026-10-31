<?php
/**
 * Príručka doplnku J-Platby (podstránka J-Pomocník → J-Platby).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Platby sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.2.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_platby() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: platby kartou cez Stripe', 'dashicons-money-alt', <<<'HTML'
<p class="jhelp-lead"><strong>J-Platby pridávajú do obchodu platbu kartou, Apple Pay a Google Pay cez Stripe.</strong> Zákazník zaplatí na zabezpečenej platobnej stránke Stripe a vráti sa do obchodu. Objednávka sa sama označí ako zaplatená, k faktúre sa zapíše úhrada a poplatok Stripe ide do účtovníctva.</p>
<div class="jhelp-facts">
	<div><b>3</b><span>spôsoby platby: karta, Apple Pay, Google Pay</span></div>
	<div><b>2</b><span>režimy: testovací a ostrý</span></div>
	<div><b>5</b><span>udalostí webhooku zo Stripe</span></div>
	<div><b>24 h</b><span>platí odkaz na platbu z objednávky</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Platba v pokladni:</strong> metóda „Platba kartou (Visa, Mastercard, Apple Pay, Google Pay)“ v blokovej aj klasickej pokladni.</li>
<li><strong>Potvrdenie platby:</strong> webhook zo Stripe (s overením podpisu) a pre istotu aj návrat zákazníka.</li>
<li><strong>Vrátenie peňazí</strong> priamo z objednávky; vrátenie urobené v Stripe sa zapíše do objednávky.</li>
<li><strong>Prehľad platieb</strong> s poplatkami, čistou sumou a exportom do CSV.</li>
<li><strong>Odkaz na platbu kartou</strong> pre zákazníka z detailu nezaplatenej objednávky.</li>
<li><strong>Prepojenia:</strong> úhrada k faktúre v J-Fakturácii, poplatok do J-Účtovníctva.</li>
</ul>
<p class="jhelp-tip"><strong>Bezpečnosť:</strong> údaje o karte zadáva zákazník len na stránke Stripe. Obchod ich nevidí ani neukladá, a preto nepotrebujete certifikáciu PCI. Kľúče Stripe sú v databáze uložené zašifrované.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>Jupiter → Platby</strong> (ak je zlúčenie doplnkov Jupiter vypnuté, ako samostatná položka <strong>J-Platby</strong>). Vidí ho administrátor a správca obchodu. Pri názve stránky je štítok <em>TESTOVACÍ REŽIM</em> alebo <em>OSTRÝ REŽIM</em>.</p>
<table class="jhelp-table">
<thead><tr><th>Miesto</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Platby</strong></td><td>Platby kartou za mesiac: suma, poplatok, čistá suma, karta, stav, export CSV.</td></tr>
<tr><td><strong>Nastavenia</strong></td><td>Režim, kľúče Stripe, tajomstvo webhooku, účtovanie poplatkov, stav webhooku, testovacie karty.</td></tr>
<tr><td><strong>Box Platba kartou v objednávke</strong></td><td>Karta a poplatok pri zaplatenej objednávke; odkaz na platbu pri nezaplatenej.</td></tr>
<tr><td><strong>WooCommerce → Nastavenia → Platby</strong></td><td>Zapnutie metódy, názov a popis v pokladni, text tlačidla.</td></tr>
<tr><td><strong>Nástenka Jupiter</strong></td><td>Na dlaždici Platby je stav webhooku (zelená / oranžová / červená).</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'start', 'Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Účet Stripe.</strong> Založte si účet na stripe.com (pri ostrých platbách treba dokončiť overenie firmy a bankový účet).</li>
<li><strong>Testovacie kľúče.</strong> Stripe → Developers (Vývojári) → API keys: skopírujte <code>pk_test_…</code> a <code>sk_test_…</code> do <em>Platby → Nastavenia → Testovacie kľúče</em>, nechajte <em>Testovací režim</em> a kliknite <em>Uložiť a overiť spojenie</em>.</li>
<li><strong>Webhook.</strong> Stripe → Developers → Webhooks → Add endpoint: adresu a udalosti skopírujte zo stránky Nastavenia. Po vytvorení kliknite <em>Reveal signing secret</em> a <code>whsec_…</code> vložte do poľa <em>Tajomstvo webhooku</em>.</li>
<li><strong>Skúšobný nákup.</strong> V obchode zvoľte Platba kartou a zaplaťte kartou <code>4242 4242 4242 4242</code>. Objednávka sa má sama nastaviť na <em>Spracováva sa</em> a v Nastaveniach sa ukáže <em>Webhook funguje</em>.</li>
<li><strong>Ostrý režim.</strong> Rovnako zadajte ostré kľúče (<code>pk_live_…</code>, <code>sk_live_…</code>) a vytvorte ostrý webhook (v Stripe bez prepínača Test mode). Prepnite <em>Ostrý režim</em> a uložte.</li>
</ol>
<p class="jhelp-warn"><strong>Pozor:</strong> testovací a ostrý režim majú v Stripe vlastné kľúče aj vlastný webhook. Po prepnutí do ostrého režimu skontrolujte, či je zadané aj ostré tajomstvo webhooku.</p>
HTML
	);

	$s[] = array( 'rezimy', 'Testovací a ostrý režim', 'dashicons-admin-tools', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Režim</th><th>Čo sa deje</th></tr></thead>
<tbody>
<tr><td><strong>Testovací</strong></td><td>Skúšobné platby testovacími kartami, žiadne skutočné peniaze. V pokladni je oranžové upozornenie s testovacou kartou. Poplatky sa neúčtujú do J-Účtovníctva, v prehľade platieb sú označené <em>test</em>.</td></tr>
<tr><td><strong>Ostrý</strong></td><td>Skutočné platby zákazníkov. Ide až po aktivácii účtu v Stripe – pri uložení nastavení J-Platby upozornia, ak účet ešte nemôže prijímať platby.</td></tr>
</tbody>
</table>
<h3>Testovacie karty</h3>
<table class="jhelp-table">
<tbody>
<tr><td><code>4242 4242 4242 4242</code></td><td>úspešná platba</td></tr>
<tr><td><code>4000 0000 0000 0002</code></td><td>zamietnutá karta</td></tr>
<tr><td><code>4000 0027 6000 3184</code></td><td>overenie 3D Secure (potvrdenie v okne banky)</td></tr>
</tbody>
</table>
<p>Platnosť: ľubovoľný budúci dátum, CVC: ľubovoľné tri číslice. Platby zaplatené v testovacom režime si pamätajú režim – vrátenie peňazí ide vždy cez kľúče toho režimu, v ktorom sa platilo.</p>
HTML
	);

	$s[] = array( 'zakaznik', 'Ako platí zákazník', 'dashicons-cart', <<<'HTML'
<ol class="jhelp-steps">
<li>V pokladni zvolí <em>Platba kartou</em> a klikne <em>Zaplatiť kartou</em> (text tlačidla sa dá zmeniť).</li>
<li>Presmeruje sa na platobnú stránku Stripe v slovenčine: zadá kartu alebo použije Apple Pay / Google Pay; banka môže chcieť potvrdenie (3D Secure).</li>
<li>Po zaplatení sa vráti na stránku <em>Ďakujeme</em>. Objednávka je <em>Spracováva sa</em> a zákazník dostane potvrdenie e-mailom.</li>
</ol>
<ul>
<li><strong>Nedokončená platba:</strong> objednávka čaká na platbu; zákazník môže zaplatiť znova cez odkaz v Môj účet alebo na stránke Ďakujeme.</li>
<li><strong>Platnosť platobnej stránky:</strong> rovnaká ako čas, kým WooCommerce zruší nezaplatenú objednávku (WooCommerce → Nastavenia → Produkty → Zásoby → <em>Držať zásoby</em>), najmenej 36 minút. Kým stránka platí, WooCommerce objednávku nezruší. Keď vyprší bez zaplatenia, objednávka sa zruší.</li>
<li>Najmenšia suma pre platbu kartou je 0,50 € – pri menšej sume sa karta v pokladni neponúkne.</li>
</ul>
HTML
	);

	$s[] = array( 'webhook', 'Potvrdenie platby (webhook)', 'dashicons-update', <<<'HTML'
<p>Webhook je správa, ktorú Stripe pošle obchodu hneď po platbe. Vďaka nemu sa objednávka označí ako zaplatená, aj keď zákazník po platbe zatvorí prehliadač. Každá správa je podpísaná a J-Platby podpis overia tajomstvom webhooku.</p>
<table class="jhelp-table">
<thead><tr><th>Udalosť</th><th>Čo urobí</th></tr></thead>
<tbody>
<tr><td><code>checkout.session.completed</code></td><td>Platba prijatá → objednávka zaplatená (karta, poplatok, úhrada faktúry).</td></tr>
<tr><td><code>checkout.session.async_payment_succeeded</code></td><td>Oneskorene potvrdená platba → zaplatená.</td></tr>
<tr><td><code>checkout.session.async_payment_failed</code></td><td>Platba zlyhala → objednávka Neúspešná.</td></tr>
<tr><td><code>checkout.session.expired</code></td><td>Platobná stránka vypršala → nezaplatená objednávka sa zruší (pri odkaze na platbu sa len zruší odkaz).</td></tr>
<tr><td><code>charge.refunded</code></td><td>Vrátenie urobené v Stripe → zapíše sa do objednávky.</td></tr>
</tbody>
</table>
<h3>Stav webhooku</h3>
<ul>
<li><span style="color:#1e7e34;font-weight:600">Webhook funguje</span> – posledná udalosť bola prijatá.</li>
<li><span style="color:#9a6700;font-weight:600">Zatiaľ žiadna udalosť</span> – webhook ešte nebol použitý (urobte skúšobnú platbu).</li>
<li><span style="color:#b32d2e;font-weight:600">Webhook odmietnutý / chýba tajomstvo</span> – v celej administrácii sa ukáže červené upozornenie. Najčastejšie nesedí tajomstvo webhooku – skopírujte ho zo Stripe znova.</li>
</ul>
<p>Stav je v <em>Platby → Nastavenia</em> a na dlaždici Platby na nástenke Jupiter.</p>
HTML
	);

	$s[] = array( 'prehlad', 'Prehľad platieb a export', 'dashicons-chart-bar', <<<'HTML'
<p><em>Platby → Platby</em> ukazuje objednávky s platbou kartou za zvolený mesiac:</p>
<table class="jhelp-table">
<tbody>
<tr><td><strong>Suma</strong></td><td>Suma objednávky; pod ňou červene vrátené peniaze.</td></tr>
<tr><td><strong>Poplatok</strong></td><td>Poplatok Stripe za platbu (doplní sa do hodiny, ak ho Stripe hneď neuviedol).</td></tr>
<tr><td><strong>Dostanete</strong></td><td>Suma − poplatok − vrátené peniaze. Poplatok Stripe sa pri vrátení nevracia.</td></tr>
<tr><td><strong>Karta</strong></td><td>Typ a posledné 4 číslice, prípadne Apple Pay / Google Pay.</td></tr>
<tr><td><strong>Stav</strong></td><td>zaplatená, čiastočne vrátená, vrátená, čaká na platbu, nezaplatená.</td></tr>
<tr><td><strong>Stripe</strong></td><td>Odkaz na detail platby v Stripe.</td></tr>
</tbody>
</table>
<ul>
<li>Dole je súčet za mesiac.</li>
<li><strong>Export CSV</strong> stiahne platby mesiaca pre účtovníka (otvorí sa v Exceli – oddeľovač bodkočiarka, slovenská desatinná čiarka).</li>
<li><strong>Výplaty na účet</strong> otvorí výplaty v Stripe – Stripe posiela peniaze na bankový účet automaticky podľa nastavenia vo Stripe.</li>
</ul>
HTML
	);

	$s[] = array( 'odkaz', 'Odkaz na platbu kartou', 'dashicons-admin-links', <<<'HTML'
<p>Zákazník môže zaplatiť kartou aj objednávku, ktorú ste zadali v administrácii, alebo objednávku s prevodom, ktorú ešte nezaplatil.</p>
<ol class="jhelp-steps">
<li>Otvorte detail nezaplatenej objednávky (Čaká na platbu, Pozastavené alebo Neúspešná, aspoň 0,50 €).</li>
<li>V boxe <strong>Platba kartou (J-Platby)</strong> kliknite <em>Vytvoriť odkaz na platbu</em>.</li>
<li>Kliknite <em>Kopírovať odkaz</em> a pošlite ho zákazníkovi (e-mailom, SMS, správou).</li>
</ol>
<ul>
<li>Odkaz platí 24 hodín; čas platnosti je v boxe aj v poznámke objednávky. Po vypršaní sa objednávka nemení – môžete vytvoriť nový odkaz.</li>
<li>Po zaplatení sa objednávka nastaví na <em>Spracováva sa</em> a platobná metóda sa zmení na Platba kartou (pôvodná je v poznámke).</li>
</ul>
HTML
	);

	$s[] = array( 'vratenie', 'Vrátenie peňazí', 'dashicons-undo', <<<'HTML'
<ol class="jhelp-steps">
<li>Detail objednávky → <em>Vrátiť peniaze</em>.</li>
<li>Zadajte počty položiek alebo sumu a dôvod.</li>
<li>Kliknite <em>Vrátiť … cez Platba kartou</em>. Peniaze sa vrátia na kartu zákazníka (zvyčajne do 5 – 10 dní).</li>
</ol>
<ul>
<li>Dá sa vrátiť aj časť sumy, aj viackrát.</li>
<li>Vrátenie urobené priamo v Stripe Dashboarde sa do objednávky zapíše samo (webhook <code>charge.refunded</code>).</li>
<li>Poplatok Stripe sa pri vrátení nevracia.</li>
<li>Ak objednávka nemá platbu Stripe (napr. zaplatila prevodom), peniaze treba vrátiť ručne.</li>
</ul>
HTML
	);

	$s[] = array( 'uctovnictvo', 'Fakturácia a účtovníctvo', 'dashicons-media-spreadsheet', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Udalosť</th><th>Kde sa zapíše</th><th>Účtovanie</th></tr></thead>
<tbody>
<tr><td><strong>Platba kartou</strong></td><td>Úhrada k faktúre objednávky v J-Fakturácii (faktúra je uhradená, spôsob karta).</td><td>261 / 311</td></tr>
<tr><td><strong>Poplatok Stripe</strong></td><td>Interný doklad v J-Účtovníctve s dátumom platby.</td><td>568 / 261</td></tr>
<tr><td><strong>Výplata zo Stripe na účet</strong></td><td>Pohyb v banke (J-Fakturácia / J-Účtovníctvo).</td><td>221 / 261</td></tr>
</tbody>
</table>
<ul>
<li>Úhrada sa zapíše k faktúre, aj keď faktúra vznikne až po platbe.</li>
<li>Účtovanie poplatkov sa dá vypnúť v <em>Nastavenia → Účtovníctvo</em>. Testovacie platby sa neúčtujú.</li>
<li>Pre príjem od Stripe v banke nastavte v J-Účtovníctve pravidlo na účet 261.</li>
</ul>
HTML
	);

	$s[] = array( 'nastavenia', 'Všetky nastavenia', 'dashicons-admin-settings', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Nastavenie</th><th>Význam</th></tr></thead>
<tbody>
<tr><td><strong>Režim</strong></td><td>Testovací alebo ostrý – ktoré kľúče sa používajú.</td></tr>
<tr><td><strong>Publikovateľný kľúč</strong></td><td><code>pk_test_…</code> / <code>pk_live_…</code>.</td></tr>
<tr><td><strong>Tajný kľúč</strong></td><td><code>sk_…</code> alebo obmedzený <code>rk_…</code>. Uloží sa zašifrovaný a už sa nezobrazí; prázdne pole = ostane pôvodný.</td></tr>
<tr><td><strong>Tajomstvo webhooku</strong></td><td><code>whsec_…</code> zo Stripe → Webhooks. Bez neho sa platby nepotvrdia automaticky.</td></tr>
<tr><td><strong>Poplatky Stripe</strong></td><td>Zaúčtovať poplatok do J-Účtovníctva (568 / 261).</td></tr>
</tbody>
</table>
<p>V <em>WooCommerce → Nastavenia → Platby → Platba kartou (Stripe)</em> je zapnutie metódy, <strong>názov</strong> a <strong>popis</strong> v pokladni a <strong>text tlačidla</strong>.</p>
<p>Tlačidlo <em>Uložiť a overiť spojenie</em> po uložení skontroluje kľúč v Stripe, ukáže názov účtu a upozorní, ak účet ešte nemôže prijímať ostré platby alebo chýba tajomstvo webhooku.</p>
HTML
	);

	$s[] = array( 'problemy', 'Keď niečo nejde', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Problém</th><th>Príčina a riešenie</th></tr></thead>
<tbody>
<tr><td><strong>Platba kartou sa v pokladni nezobrazuje</strong></td><td>Chýba tajný kľúč aktuálneho režimu, metóda je vypnutá vo WooCommerce → Platby, suma je pod 0,50 €, alebo sa kľúč nedá prečítať (červené upozornenie – zadajte ho znova).</td></tr>
<tr><td><strong>Zaplatená objednávka ostala „Čaká na platbu“</strong></td><td>Webhook nefunguje (pozrite stav v Nastaveniach) a zákazník sa po platbe nevrátil. Opravte tajomstvo webhooku; v Stripe môžete udalosť poslať znova (Webhooks → udalosť → Resend).</td></tr>
<tr><td><strong>„Webhook odmietnutý: Neplatný podpis“</strong></td><td>Tajomstvo webhooku nesedí s webhookom v Stripe (alebo s režimom). Skopírujte <code>whsec_…</code> znova.</td></tr>
<tr><td><strong>„Platbu kartou sa nepodarilo spustiť“</strong></td><td>Stripe odmietol vytvoriť platobnú stránku – dôvod je v poznámke objednávky (napr. neaktivovaný účet, zlý kľúč).</td></tr>
<tr><td><strong>Poplatok chýba</strong></td><td>Stripe ho ešte neuviedol – doplní sa automaticky do hodiny.</td></tr>
<tr><td><strong>„Zaplatená suma sa líši“ v poznámke</strong></td><td>Objednávka sa zmenila po vytvorení platobnej stránky. Skontrolujte sumu a prípadne vráťte rozdiel alebo vytvorte odkaz na doplatok.</td></tr>
<tr><td><strong>Vrátenie zlyhalo</strong></td><td>Dôvod ukáže WooCommerce (napr. vrátenie väčšej sumy, než bolo zaplatené, alebo nedostatok zostatku v Stripe).</td></tr>
<tr><td><strong>Objednávka sa zrušila, hoci zákazník platil</strong></td><td>Zákazník platil po vypršaní platobnej stránky. Peniaze sa v takom prípade nestrhnú; vytvorte mu odkaz na platbu.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'technicke', 'Technické údaje (pre vývojára)', 'dashicons-editor-code', <<<'HTML'
<h3>Údaje pri objednávke (meta)</h3>
<table class="jhelp-table">
<tbody>
<tr><td><code>_jpl_session</code>, <code>_jpl_session_exp</code></td><td>ID Checkout Session a jej platnosť.</td></tr>
<tr><td><code>_jpl_mode</code></td><td>Režim platby (<code>test</code> / <code>live</code>).</td></tr>
<tr><td><code>_jpl_pi</code>, <code>_jpl_charge</code></td><td>PaymentIntent (= ID transakcie) a Charge.</td></tr>
<tr><td><code>_jpl_card</code>, <code>_jpl_fee</code>, <code>_jpl_net</code>, <code>_jpl_available_on</code></td><td>Karta, poplatok, čistá suma, dátum dostupnosti peňazí.</td></tr>
<tr><td><code>_jpl_refunds</code></td><td>ID vrátení v Stripe.</td></tr>
<tr><td><code>_jpl_link</code></td><td>Odkaz na platbu z administrácie (kým platí).</td></tr>
</tbody>
</table>
<h3>Rozhrania</h3>
<ul>
<li>Stripe API <code>2024-06-20</code> bez knižnice: <code>checkout/sessions</code> (s idempotentným kľúčom), <code>payment_intents</code>, <code>refunds</code>, <code>account</code>.</li>
<li>Webhook: <code>POST /wp-json/j-platby/v1/stripe</code>, podpis <code>Stripe-Signature</code> (HMAC-SHA256, tolerancia 5 minút), tajomstvá oboch režimov.</li>
<li>Platobná metóda WooCommerce <code>jpl_stripe</code> (produkty, vrátenie), blokový košík <code>JPL_Blocks_Stripe</code>.</li>
<li>WP-Cron <code>jpl_hourly</code> – doplnenie poplatkov.</li>
</ul>
<h3>Háky</h3>
<ul>
<li>Filtre <code>jpl_session_params</code>, <code>jpl_payment_method_types</code>.</li>
<li>Akcia <code>jpl_paid</code> ( order_id, payment_intent ).</li>
<li>Filter <code>woocommerce_cancel_unpaid_order</code> – nezaplatená objednávka sa nezruší, kým platí platobná stránka.</li>
<li>Filter <code>jupiter_tile_status</code> – stav webhooku na nástenke Jupiter.</li>
</ul>
HTML
	);

	return $s;
}
