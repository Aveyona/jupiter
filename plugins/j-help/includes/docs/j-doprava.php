<?php
/**
 * Príručka doplnku J-Doprava (podstránka J-Pomocník → J-Doprava).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Doprava sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.3.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_doprava() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: doprava a zásielky', 'dashicons-car', <<<'HTML'
<p class="jhelp-lead"><strong>J-Doprava prepája obchod s Packetou a uľahčuje odosielanie poštou a osobný odber.</strong> Zákazník si v košíku vyberie výdajné miesto alebo Z-BOX na mape, vy zásielku podáte jedným klikom, vytlačíte štítok a o zvyšok – sledovanie, e-mail zákazníkovi, vybavenie objednávky – sa postará doplnok.</p>
<div class="jhelp-facts">
	<div><b>2</b><span>spôsoby dopravy Packeta (výdajné miesto, adresa)</span></div>
	<div><b>5</b><span>formátov štítkov</span></div>
	<div><b>1×</b><span>za hodinu kontrola stavu zásielok</span></div>
	<div><b>2</b><span>vlastné e-maily zákazníkovi (odoslaná, pripravené na odber)</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Packeta v košíku:</strong> výber výdajného miesta alebo Z-BOXu na mape, doručenie na adresu, kontrola pred odoslaním objednávky.</li>
<li><strong>Zásielky:</strong> podanie cez API Packety – z objednávky, hromadne alebo automaticky; štítky PDF; dobierka; zrušenie.</li>
<li><strong>Sledovanie:</strong> stav každú hodinu, e-mail „Objednávka odoslaná“, odkaz v Môj účet, doručená objednávka → Vybavená.</li>
<li><strong>Spätná zásielka:</strong> vrátenie alebo reklamácia cez Packetu s kódom pre zákazníka.</li>
<li><strong>Slovenská pošta:</strong> export objednávok do ePodacieho hárku.</li>
<li><strong>Osobný odber:</strong> e-mail „Objednávka je pripravená na odber“.</li>
</ul>
<p class="jhelp-tip"><strong>Dobré vedieť:</strong> doprava poštou a osobný odber sú bežné spôsoby dopravy WooCommerce (napr. Pevná sadzba, Osobný odber). J-Doprava k nim pridáva export pre poštu a e-mail pre osobný odber; Packetu pridáva ako vlastné spôsoby dopravy.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>Jupiter → Doprava</strong> (ak je zlúčenie doplnkov Jupiter vypnuté, ako samostatná položka <strong>J-Doprava</strong>). Vidí ho administrátor a správca obchodu.</p>
<table class="jhelp-table">
<thead><tr><th>Miesto</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Zásielky</strong></td><td>Objednávky s Packetou v záložkách Na podanie, Na tlač, Na ceste, Ukončené, Všetky a záložka Slovenská pošta.</td></tr>
<tr><td><strong>Nastavenia</strong></td><td>Prístup k Packete, hmotnosť, štítky, dobierka, sledovanie, automatické podanie, Slovenská pošta.</td></tr>
<tr><td><strong>Box Packeta v objednávke</strong></td><td>Výdajné miesto, podanie zásielky, štítok, stav, zrušenie, spätná zásielka.</td></tr>
<tr><td><strong>Zoznam objednávok WooCommerce</strong></td><td>Hromadné akcie: Packeta – podať zásielky, Packeta – štítky PDF, Osobný odber – poslať e-mail.</td></tr>
<tr><td><strong>J-Objednávky</strong></td><td>V stĺpci Doprava výdajné miesto, číslo a stav zásielky, export na poštu a e-mail osobného odberu.</td></tr>
<tr><td><strong>WooCommerce → Nastavenia → Doprava</strong></td><td>Spôsoby dopravy Packeta v zóne: cena, zadarmo od sumy, maximálna hmotnosť.</td></tr>
<tr><td><strong>WooCommerce → Nastavenia → E-maily</strong></td><td>E-maily Objednávka odoslaná (Packeta) a Pripravené na osobný odber: zapnutie, predmet, text.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'start', 'Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Údaje z Packety.</strong> V klientskej zóne Packeta (client.packeta.com) skopírujte <em>API heslo</em> (Nastavenia klienta) a <em>označenie odosielateľa</em> (Odosielatelia).</li>
<li><strong>Nastavenia J-Dopravy.</strong> Vložte ich do <em>Doprava → Nastavenia</em> a kliknite <em>Uložiť a overiť spojenie</em>. Hore sa ukáže, či spojenie s Packetou funguje.</li>
<li><strong>Spôsoby dopravy.</strong> <em>WooCommerce → Nastavenia → Doprava</em>, zóna Slovensko → Pridať spôsob dopravy: <em>Packeta – výdajné miesto / Z-BOX</em> a <em>Packeta – doručenie na adresu</em>. Nastavte cenu a prípadne dopravu zadarmo od sumy.</li>
<li><strong>Hmotnosť produktov.</strong> Pri tovare vyplňte <em>Doprava → Hmotnosť</em> – z nej sa počíta hmotnosť zásielky.</li>
<li><strong>Prvá zásielka.</strong> Pri objednávke s Packetou otvorte detail, v boxe <em>Packeta</em> kliknite <em>Podať zásielku</em> a potom <em>Štítok PDF</em>. Zásielku odneste na ľubovoľné výdajné miesto Packety.</li>
</ol>
HTML
	);

	$s[] = array( 'sposoby', 'Spôsoby dopravy Packeta', 'dashicons-location', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Spôsob</th><th>Pre zákazníka</th><th>Predvolená max. hmotnosť</th></tr></thead>
<tbody>
<tr><td><strong>Packeta – výdajné miesto / Z-BOX</strong></td><td>Vyberie si miesto na mape, zásielku vyzdvihne s kódom zo SMS.</td><td>10 kg</td></tr>
<tr><td><strong>Packeta – doručenie na adresu</strong></td><td>Kuriér doručí na adresu doručenia; telefón je povinný.</td><td>5 kg</td></tr>
</tbody>
</table>
<p>Nastavenie každého spôsobu v zóne dopravy:</p>
<ul>
<li><strong>Názov v košíku</strong> – text, ktorý vidí zákazník.</li>
<li><strong>Cena</strong> – cena dopravy.</li>
<li><strong>Zadarmo od sumy objednávky</strong> – suma tovaru v košíku po zľavách vrátane DPH; prázdne = doprava sa vždy platí.</li>
<li><strong>Maximálna hmotnosť</strong> – pri ťažšom košíku sa spôsob nezobrazí (0 = bez obmedzenia).</li>
</ul>
<p class="jhelp-warn"><strong>Pozor:</strong> spôsoby Packeta sa v košíku zobrazia až po zadaní API hesla a odosielateľa. Ak sa heslo nedá prečítať (napr. po zmene bezpečnostných kľúčov WordPressu), Packeta z košíka zmizne – J-Doprava vtedy v administrácii ukáže červené upozornenie.</p>
HTML
	);

	$s[] = array( 'kosik', 'Košík a pokladňa', 'dashicons-cart', <<<'HTML'
<ul>
<li>Pri doprave <em>Packeta – výdajné miesto / Z-BOX</em> sa pod ňou zobrazí tlačidlo <strong>Vybrať výdajné miesto</strong>. Otvorí mapu Packety s výdajnými miestami a Z-BOXmi krajín z nastavení; mapa ponúka len miesta, kam sa zmestí hmotnosť košíka.</li>
<li>Vybrané miesto sa ukáže zeleným rámčekom (<em>Vybrané: …</em>), tlačidlo sa zmení na <em>Zmeniť výdajné miesto</em>. Miesto sa pamätá počas nákupu, aj keď zákazník z pokladne odíde a vráti sa.</li>
<li><strong>Bez vybraného miesta sa objednávka neodošle.</strong> Pred odoslaním J-Doprava ešte overí v Packete, či je miesto dostupné; ak nie, zákazník vyberie iné.</li>
<li>Pri <em>doručení na adresu</em> musí zákazník zadať telefón (aspoň 9 číslic).</li>
<li>Funguje v blokovej aj klasickej pokladni.</li>
<li>Výdajné miesto je v detaile objednávky, v e-mailoch a v Môj účet.</li>
</ul>
HTML
	);

	$s[] = array( 'podanie', 'Podanie zásielky', 'dashicons-upload', <<<'HTML'
<p>Podanie = odoslanie údajov zásielky do Packety. Packeta pridelí číslo zásielky a dá sa vytlačiť štítok. Zásielku podáte:</p>
<ol class="jhelp-steps">
<li><strong>Z detailu objednávky:</strong> box <em>Packeta</em> → skontrolujte hmotnosť a dobierku → <em>Podať zásielku</em>.</li>
<li><strong>Zo stránky Zásielky:</strong> záložka <em>Na podanie</em> (zaplatené objednávky a dobierky bez zásielky – nezaplatený prevod sa nepodáva) → označte → <em>Podať označené</em>.</li>
<li><strong>Zo zoznamu objednávok WooCommerce:</strong> Hromadné akcie → <em>Packeta – podať zásielky</em>.</li>
<li><strong>Automaticky:</strong> po zapnutí <em>Nastavenia → Automatické podanie</em> sa zásielka podá sama, keď objednávka prejde do stavu Spracováva sa (na pozadí, pokladňu to nezdržiava).</li>
</ol>
<ul>
<li><strong>Hmotnosť</strong> = súčet hmotností produktov + obal z nastavení, najmenej minimálna hmotnosť.</li>
<li><strong>Hodnota zásielky</strong> = suma objednávky (poistenie Packety).</li>
<li>Jedna objednávka sa nedá podať dvakrát – ani dvojklikom.</li>
<li>Ak podanie zlyhá, dôvod je v poznámke objednávky a v hlásení hore (napr. chýbajúce výdajné miesto, zlá adresa).</li>
<li><strong>Zrušenie:</strong> kým Packeta zásielku neprevzala (stav Prijaté údaje), box ponúka <em>Zrušiť zásielku</em>. Zrušenú zásielku môžete podať znova.</li>
</ul>
HTML
	);

	$s[] = array( 'stitky', 'Štítky', 'dashicons-tag', <<<'HTML'
<ul>
<li><strong>Jeden štítok:</strong> box Packeta → <em>Štítok PDF</em>.</li>
<li><strong>Viac naraz:</strong> stránka Zásielky → označte → <em>Štítky PDF</em> (formát sa dá zmeniť vedľa tlačidla), alebo Hromadné akcie → <em>Packeta – štítky PDF</em>.</li>
<li><strong>Na tlač:</strong> záložka ukáže podané zásielky, ktorým ste ešte nevytlačili štítok. Po stiahnutí PDF sa štítok poznačí ako vytlačený (v boxe objednávky aj v stĺpci Zásielka).</li>
</ul>
<table class="jhelp-table">
<thead><tr><th>Formát</th><th>Na čo</th></tr></thead>
<tbody>
<tr><td>A6 na A4</td><td>4 štítky na stranu A4 – bežná tlačiareň.</td></tr>
<tr><td>A6</td><td>Tlačiareň štítkov.</td></tr>
<tr><td>A7 na A4 / A7</td><td>Menšie štítky, 8 na stranu.</td></tr>
<tr><td>105 × 35 mm na A4</td><td>Samolepiace hárky.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'sledovanie', 'Sledovanie a e-maily', 'dashicons-visibility', <<<'HTML'
<ul>
<li>J-Doprava sa <strong>každú hodinu</strong> pýta Packety na stav zásielok, ktoré ešte nie sú doručené, vrátené alebo zrušené (posledných 60 dní). Stav hneď: <em>Obnoviť stav</em> v boxe alebo na stránke Zásielky.</li>
<li>Každá zmena stavu sa zapíše do poznámok objednávky.</li>
<li><strong>E-mail „Objednávka odoslaná“:</strong> keď Packeta zásielku prevezme, zákazník dostane e-mail s tlačidlom <em>Sledovať zásielku</em> a výdajným miestom alebo adresou. Odíde raz. Zapína sa a upravuje vo <em>WooCommerce → Nastavenia → E-maily → Objednávka odoslaná (Packeta)</em>.</li>
<li><strong>Odkaz na sledovanie</strong> je aj v ostatných e-mailoch objednávky a v Môj účet (dá sa vypnúť v Nastaveniach).</li>
<li><strong>Doručená zásielka</strong> → objednávka sa nastaví na <em>Vybavená</em> (voľba v Nastaveniach). Podľa nastavení J-Fakturácie sa môže hneď vystaviť faktúra.</li>
</ul>
<h3>Spätná zásielka (vrátenie, reklamácia)</h3>
<p>Pri doručenej zásielke box Packeta ponúka <strong>Spätná zásielka</strong>. Packeta vytvorí zásielku späť k vám a vráti <strong>kód pre zákazníka</strong>. Kód pošlite zákazníkovi – na ľubovoľnom výdajnom mieste Packety ho povie a balík odovzdá bez tlače štítku. Kód a číslo spätnej zásielky ostanú v boxe aj v poznámke objednávky.</p>
HTML
	);

	$s[] = array( 'dobierka', 'Dobierka', 'dashicons-money-alt', <<<'HTML'
<ul>
<li>Objednávka je na dobierku, keď má spôsob platby uvedený v <em>Nastavenia → Dobierka</em> (predvolene <code>cod</code> = platba pri doručení WooCommerce).</li>
<li>Pri podaní sa do Packety pošle dobierka vo výške celej objednávky (vrátane poplatku za dobierku a dopravy). V boxe objednávky ju pred podaním môžete zmeniť.</li>
<li>Na stránke Zásielky a v boxe je pri takej objednávke označenie <strong>dobierka</strong>.</li>
<li>Packeta vybranú dobierku pošle na váš účet podľa zmluvy.</li>
</ul>
<p class="jhelp-tip"><strong>Opravené vo verzii 0.3.0:</strong> objednávka na dobierku je v stave Spracováva sa, ktorý WooCommerce považuje za zaplatený. Staršie verzie preto dobierku do Packety neposlali. Teraz rozhoduje len spôsob platby.</p>
HTML
	);

	$s[] = array( 'posta', 'Slovenská pošta – ePodací hárok', 'dashicons-email', <<<'HTML'
<p>Pre objednávky s dopravou poštou pripraví J-Doprava súbor pre aplikáciu <strong>ePodací hárok</strong> Slovenskej pošty – netreba prepisovať adresy.</p>
<ol class="jhelp-steps">
<li><strong>Nastavenie:</strong> <em>Doprava → Nastavenia → Slovenská pošta</em> – text v názve spôsobu dopravy (napr. <code>pošta</code> pre „Slovenská pošta – doporučený list“), druh zásielky (doporučený list, poistený list, balík, zmluvný balík), trieda a odosielateľ.</li>
<li><strong>Export:</strong> <em>Doprava → Zásielky → Slovenská pošta</em> – zaplatené objednávky a dobierky za posledných 60 dní (nezaplatený prevod sa neponúka). Označte ich a kliknite <em>Stiahnuť ePodací hárok (XML)</em>.</li>
<li><strong>Import:</strong> v aplikácii ePodací hárok (eph.posta.sk) zvoľte Import a nahrajte súbor. Podací hárok vytlačte alebo ho elektronicky pošlite pošte.</li>
</ol>
<ul>
<li>Exportované objednávky sa presunú do <em>Exportované</em> (dajú sa stiahnuť znova) a v poznámke objednávky je záznam.</li>
<li>Hmotnosť = produkty + obal. Pri dobierke sa použije účet z nastavení a variabilný symbol = číslo objednávky.</li>
<li>Súbor je vo formáte ePodacieho hárku verzia 3.0.</li>
</ul>
HTML
	);

	$s[] = array( 'odber', 'Osobný odber', 'dashicons-store', <<<'HTML'
<p>Keď je objednávka s osobným odberom pripravená, pošlite zákazníkovi e-mail <strong>„Objednávka je pripravená na odber“</strong>:</p>
<ul>
<li><strong>Jedna objednávka:</strong> detail objednávky → <em>Akcie objednávky</em> → <em>Poslať e-mail: pripravené na osobný odber</em> → Aktualizovať.</li>
<li><strong>Viac naraz:</strong> zoznam objednávok WooCommerce → Hromadné akcie → <em>Osobný odber – poslať e-mail „pripravené na odber“</em>.</li>
</ul>
<p>E-mail obsahuje číslo objednávky, názov spôsobu dopravy (napr. miesto odberu) a obsah objednávky. Predmet, nadpis a doplnkový text (adresu, otváracie hodiny, čo si priniesť) upravíte vo <em>WooCommerce → Nastavenia → E-maily → Pripravené na osobný odber</em>. Odoslanie sa zapíše do poznámky objednávky a ukáže v J-Objednávkach.</p>
HTML
	);

	$s[] = array( 'nastavenia', 'Všetky nastavenia', 'dashicons-admin-settings', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Nastavenie</th><th>Význam</th></tr></thead>
<tbody>
<tr><td><strong>API heslo</strong></td><td>Z klientskej zóny Packety. Uloží sa zašifrované; prázdne pole = heslo ostane.</td></tr>
<tr><td><strong>API kľúč (mapa)</strong></td><td>Verejný kľúč pre mapu; prázdne = prvých 16 znakov API hesla (tak ho tvorí Packeta).</td></tr>
<tr><td><strong>Odosielateľ</strong></td><td>Označenie e-shopu v Packete – musí presne sedieť.</td></tr>
<tr><td><strong>Krajiny výdajných miest</strong></td><td><code>sk</code>, prípadne <code>sk,cz</code>.</td></tr>
<tr><td><strong>Hmotnosť</strong></td><td>Obal (pripočíta sa) a minimálna hmotnosť zásielky v kg.</td></tr>
<tr><td><strong>Formát štítkov</strong></td><td>Predvolený formát PDF.</td></tr>
<tr><td><strong>Dobierka</strong></td><td>ID spôsobov platby, ktoré sú dobierkou (oddelené čiarkou).</td></tr>
<tr><td><strong>Sledovanie</strong></td><td>Doručenú objednávku nastaviť na Vybavená; odkaz na sledovanie v e-mailoch a Môj účet.</td></tr>
<tr><td><strong>Automatické podanie</strong></td><td>Podať zásielku Packeta pri stave Spracováva sa.</td></tr>
<tr><td><strong>Slovenská pošta</strong></td><td>Text v názve dopravy, druh zásielky a trieda, odosielateľ, účet pre dobierky.</td></tr>
</tbody>
</table>
<p>Tlačidlo <em>Uložiť a overiť spojenie</em> po uložení overí v Packete API heslo aj odosielateľa.</p>
HTML
	);

	$s[] = array( 'problemy', 'Keď niečo nejde', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Problém</th><th>Príčina a riešenie</th></tr></thead>
<tbody>
<tr><td><strong>Packeta sa v košíku nezobrazuje</strong></td><td>Chýba API heslo alebo odosielateľ, heslo sa nedá prečítať (červené upozornenie – zadajte ho znova), košík je ťažší ako maximálna hmotnosť, alebo spôsob nie je v zóne dopravy zákazníka.</td></tr>
<tr><td><strong>„Overenie v Packete zlyhalo“</strong></td><td>Zlé API heslo alebo označenie odosielateľa – skopírujte ich znova z klientskej zóny.</td></tr>
<tr><td><strong>Mapa sa neotvorí</strong></td><td>Zlý API kľúč (nechajte pole prázdne) alebo blokovanie skriptov v prehliadači zákazníka.</td></tr>
<tr><td><strong>„Vybrané výdajné miesto nie je dostupné“</strong></td><td>Miesto je dočasne zatvorené alebo plné – zákazník vyberie iné.</td></tr>
<tr><td><strong>Podanie zlyhalo</strong></td><td>Dôvod je v poznámke objednávky (chýba miesto, telefón, adresa bez čísla domu…). Opravte objednávku a podajte znova.</td></tr>
<tr><td><strong>Stav zásielky sa nemení</strong></td><td>Kontrola beží s návštevami webu (WP-Cron) raz za hodinu. Kliknite <em>Obnoviť stav</em>.</td></tr>
<tr><td><strong>Zákazník nedostal „Objednávka odoslaná“</strong></td><td>E-mail je vypnutý vo WooCommerce → E-maily, alebo už raz odišiel (posiela sa len raz).</td></tr>
<tr><td><strong>Spätná zásielka sa nevytvorila</strong></td><td>Dôvod je v poznámke objednávky; skontrolujte, či máte v Packete povolený reklamačný asistent.</td></tr>
<tr><td><strong>Objednávka chýba v záložke Slovenská pošta</strong></td><td>Názov dopravy neobsahuje text z nastavení, objednávka ešte nie je zaplatená (prevod), je staršia ako 60 dní, alebo už bola exportovaná.</td></tr>
<tr><td><strong>ePodací hárok súbor neprijme</strong></td><td>Skontrolujte odosielateľa (meno alebo firma, ulica, PSČ, obec) a pri dobierke IBAN. Chybové hlásenie aplikácie pošty ukáže konkrétne pole.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'technicke', 'Technické údaje (pre vývojára)', 'dashicons-editor-code', <<<'HTML'
<h3>Údaje pri objednávke (meta)</h3>
<table class="jhelp-table">
<tbody>
<tr><td><code>_jdp_kind</code></td><td><code>pp</code> výdajné miesto, <code>hd</code> doručenie na adresu.</td></tr>
<tr><td><code>_jdp_point</code>, <code>_jdp_point_label</code></td><td>Vybrané miesto z mapy a jeho text.</td></tr>
<tr><td><code>_jdp_packet_id</code>, <code>_jdp_barcode</code></td><td>ID a číslo zásielky (ID ako reťazec – môže presiahnuť 32 bitov).</td></tr>
<tr><td><code>_jdp_status</code>, <code>_jdp_status_text</code>, <code>_jdp_status_at</code></td><td>Stav podľa Packety (7 doručená, 10 vrátená, 11 zrušená = konečné).</td></tr>
<tr><td><code>_jdp_weight</code>, <code>_jdp_cod</code></td><td>Hmotnosť a dobierka pri podaní.</td></tr>
<tr><td><code>_jdp_label_at</code>, <code>_jdp_shipped_mailed</code></td><td>Vytlačený štítok, odoslaný e-mail Objednávka odoslaná.</td></tr>
<tr><td><code>_jdp_claim</code></td><td>Spätná zásielka: id, barcode, password, at.</td></tr>
<tr><td><code>_jdp_eph_at</code>, <code>_jdp_pickup_mailed</code></td><td>Export do ePodacieho hárku, e-mail osobného odberu.</td></tr>
</tbody>
</table>
<h3>Rozhrania</h3>
<ul>
<li>Packeta API REST/XML <code>https://www.zasilkovna.cz/api/rest</code>: <code>createPacket</code>, <code>cancelPacket</code>, <code>packetStatus</code>, <code>packetsLabelsPdf</code>, <code>createPacketClaimWithPassword</code>, <code>senderGetReturnRouting</code> (overenie).</li>
<li>Mapa: widget Packeta v6; overenie miesta <code>widget.packeta.com/v6/pps/api/widget/v1/validate</code>.</li>
<li>ePodací hárok: XML verzia 3.0, menný priestor <code>http://ekp.posta.sk/LOGIS/Formulare/Podaj_v03</code>.</li>
<li>Sledovanie: WP-Cron <code>jdp_tracking</code> (hodinovo); automatické podanie cez Action Scheduler <code>jdp_auto_create</code>.</li>
</ul>
<h3>Háky</h3>
<ul>
<li>Filtre <code>jdp_packet_attributes</code>, <code>jdp_home_delivery_id</code>, <code>jdp_shipped_codes</code>.</li>
<li>Akcie <code>jdp_packet_created</code>, <code>jdp_status_changed</code>.</li>
</ul>
HTML
	);

	return $s;
}
