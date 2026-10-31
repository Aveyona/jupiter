<?php
/**
 * Príručka doplnku J-Pošta / Jupiter Pošta (podstránka J-Pomocník → Príručka J-Pošta).
 * Každá kapitola: id (kotva), názov, ikona (dashicons), HTML obsah. {ASSETS} = priečinok assets/ J-Pomocníka.
 * Pri zmene doplnku J-Pošta sem doplň, čo sa zmenilo (aktuálne podľa verzie 0.5.1).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jhelp_doc_j_emails() {
	$s = array();

	$s[] = array( 'uvod', 'Úvod: pošta obchodu priamo vo WordPresse', 'dashicons-email-alt', <<<'HTML'
<p class="jhelp-lead"><strong>J-Pošta (Jupiter Pošta) je e-mailová schránka obchodu priamo v administrácii WordPressu.</strong> Vyzerá a ovláda sa ako Gmail, no číta a posiela poštu z vašej vlastnej firemnej schránky (napr. u WebSupportu). Netreba otvárať iný program ani webmail: objednávky, faktúry aj pošta zákazníkov sú na jednom mieste.</p>
<p>Doplnok zároveň zabezpečí, aby <strong>všetky e-maily webu</strong> (potvrdenia objednávok, faktúry, registrácia, obnova hesla) odchádzali cez vašu schránku. Vďaka tomu nekončia v spame. Z externého účtu Google vie <strong>prevziať faktúry dodávateľov</strong> rovno do J-Fakturácie.</p>
<div class="jhelp-facts">
	<div><b>9</b><span>stálych priečinkov + vlastné zo schránky</span></div>
	<div><b>4</b><span>stránky v menu</span></div>
	<div><b>17</b><span>klávesových skratiek</span></div>
	<div><b>3</b><span>predvolené šablóny e-mailov</span></div>
</div>
<h3>Čo doplnok robí</h3>
<ul>
<li><strong>Pošta:</strong> Doručené, S hviezdičkou, Odložené, Dôležité, Odoslané, Koncepty, Archív, Spam a Kôš, plus vlastné priečinky vašej schránky. Správy sú zoskupené do konverzácií.</li>
<li><strong>Písanie:</strong> okno Napísať vpravo dole, formátovanie textu, prílohy, koncepty, odpoveď priamo v konverzácii a výber adresy odosielateľa (aliasy).</li>
<li><strong>E-maily webu cez schránku:</strong> všetko, čo posiela WordPress a WooCommerce, ide cez SMTP vašej schránky. Keď SMTP zlyhá, e-mail sa pošle náhradne cez server webu a dostanete upozornenie.</li>
<li><strong>Šablóny e-mailov:</strong> vzhľad e-mailov pre nástroje iných doplnkov (napr. Rýchly e-mail), s náhľadom a záznamom odoslaných.</li>
<li><strong>Faktúry z e-mailu:</strong> faktúry s PDF z vybraných štítkov Gmailu sa prevezmú do J-Fakturácie a platba z banky sa k nim spáruje.</li>
</ul>
<p class="jhelp-tip"><strong>Dobré vedieť:</strong> pošta zostáva na serveri vašej schránky. J-Pošta ju len zobrazuje a upravuje cez IMAP, rovnako ako Outlook alebo mobil. Čo urobíte tu (prečítané, presun, zmazanie), uvidíte aj v ostatných programoch.</p>
HTML
	);

	$s[] = array( 'menu', 'Kde ho nájdete', 'dashicons-menu', <<<'HTML'
<p>V administrácii je doplnok pod položkou <strong>Jupiter → Pošta</strong> (ak je zlúčenie doplnkov Jupiter vypnuté, ako samostatná položka <strong>J-Pošta</strong>). Číslo pri položke je počet neprečítaných správ v Doručenej pošte – obnovuje sa každých 5 minút.</p>
<table class="jhelp-table">
<thead><tr><th>Stránka</th><th>Na čo slúži</th></tr></thead>
<tbody>
<tr><td><strong>Pošta</strong></td><td>Aplikácia Jupiter Pošta: priečinky, zoznam konverzácií, čítanie, písanie a odpovede.</td></tr>
<tr><td><strong>Nastavenia</strong></td><td>Schránka (adresa, heslo, aliasy), servery IMAP a SMTP, e-maily webu, zobrazenie, podpis, test pripojenia a skúšobný e-mail.</td></tr>
<tr><td><strong>Šablóny</strong></td><td>Vzhľad e-mailov pre nástroje, limity odosielania a záznam odoslaných.</td></tr>
<tr><td><strong>Faktúry z e-mailu</strong></td><td>Pripojenie účtu Google, výber štítkov s faktúrami a prevzatie faktúr do J-Fakturácie.</td></tr>
</tbody>
</table>
<p><strong>Kto ich vidí:</strong> všetky stránky J-Pošty vidí len <strong>administrátor webu</strong>. Redaktor ani správca obchodu bez práv administrátora poštu neuvidí.</p>
HTML
	);

	$s[] = array( 'start', 'Rýchly štart', 'dashicons-controls-play', <<<'HTML'
<ol class="jhelp-steps">
<li><strong>Zadajte schránku.</strong> V <em>Pošta → Nastavenia</em> vyplňte e-mailovú adresu (napr. <code>info@vasa-firma.sk</code>), meno odosielateľa a heslo schránky. Servery WebSupportu sú predvyplnené.</li>
<li><strong>Uložte a otestujte.</strong> Kliknite <em>Uložiť nastavenia</em> a potom <em>Otestovať pripojenie</em>. Hore sa ukáže, či funguje prijímanie (IMAP) aj odosielanie (SMTP) a ktoré priečinky sa našli.</li>
<li><strong>Skúšobný e-mail webu.</strong> Kliknite <em>Poslať skúšobný e-mail</em>. Web pošle e-mail do vašej schránky rovnakou cestou ako potvrdenia objednávok – o chvíľu ho uvidíte v Doručenej pošte.</li>
<li><strong>Otvorte poštu.</strong> <em>Jupiter → Pošta</em>: vľavo priečinky, v strede konverzácie. Kliknutím konverzáciu otvoríte.</li>
<li><strong>Napíšte prvú správu.</strong> Tlačidlo <em>Napísať</em> (alebo kláves <kbd>c</kbd>) otvorí okno vpravo dole. Odoslanie: tlačidlo <em>Odoslať</em> alebo <kbd>Ctrl</kbd>+<kbd>Enter</kbd>.</li>
</ol>
<p class="jhelp-tip"><strong>Tip:</strong> Ak prichádza pošta aj na ďalšie adresy (napr. <code>eshop@</code>, <code>objednavky@</code>) do tej istej schránky, zapíšte ich do poľa <em>Aliasy</em>. Odpovede potom odídu z adresy, na ktorú zákazník písal.</p>
HTML
	);

	$s[] = array( 'schranka', 'Nastavenie schránky', 'dashicons-admin-network', <<<'HTML'
<h3>Schránka</h3>
<table class="jhelp-table">
<tbody>
<tr><td><strong>E-mailová adresa</strong></td><td>Adresa schránky. Z nej sa odosielajú odpovede a e-maily webu.</td></tr>
<tr><td><strong>Meno odosielateľa</strong></td><td>Zobrazí sa príjemcovi pri adrese (predvolene názov webu).</td></tr>
<tr><td><strong>Prihlasovacie meno</strong></td><td>Ak ho poskytovateľ vyžaduje inak ako adresu. Prázdne = použije sa e-mailová adresa.</td></tr>
<tr><td><strong>Aliasy (odosielať aj z)</strong></td><td>Ďalšie adresy, ktoré chodia do tejto schránky, jedna na riadok, voliteľne s menom: <code>E-shop &lt;eshop@vasa-firma.sk&gt;</code>. Pri písaní si vyberiete odosielateľa, odpoveď ide automaticky z adresy, na ktorú správa prišla.</td></tr>
<tr><td><strong>Heslo schránky</strong></td><td>Uloží sa <strong>zašifrované</strong> (AES-256, kľúčom z bezpečnostných kľúčov WordPressu). Prázdne pole = heslo ostane pôvodné. Zaškrtnutím <em>Zmazať uložené heslo</em> sa odstráni.</td></tr>
</tbody>
</table>
<h3>Prijímanie (IMAP) a odosielanie (SMTP)</h3>
<p>Pre WebSupport sú hodnoty predvyplnené: <strong>imap.websupport.sk</strong>, port <strong>993</strong>, SSL/TLS a <strong>smtp.websupport.sk</strong>, port <strong>465</strong>, SSL/TLS. Pri inom poskytovateľovi zadajte jeho servery; šifrovanie môže byť <em>SSL/TLS</em>, <em>STARTTLS</em> alebo <em>bez šifrovania</em> (neodporúča sa).</p>
<h3>Priečinky</h3>
<p>Polia <em>Odoslané</em>, <em>Kôš</em>, <em>Archív</em> a <em>Spam</em> nechajte prázdne – doplnok ich nájde sám: najprv podľa značiek servera, potom podľa názvu (napr. Sent, Odoslané, Junk, Nevyžiadaná pošta). Ak schránka nemá Odoslané alebo Kôš, vytvorí ich. Archív a Odložené sa vytvoria pri prvom použití. Názov zadajte ručne len vtedy, keď sa nájde nesprávny priečinok.</p>
<h3>Test pripojenia a skúšobný e-mail</h3>
<ul>
<li><strong>Otestovať pripojenie</strong> sa prihlási na IMAP aj SMTP s uloženými údajmi a vypíše počet správ v Doručenej pošte, nájdené priečinky Odoslané a Kôš a zoznam všetkých priečinkov schránky.</li>
<li><strong>Poslať skúšobný e-mail</strong> overí e-maily webu: web pošle správu do vašej schránky tak ako objednávky (aj s odosielateľom nastaveným vo WooCommerce). Pri tomto teste sa nepoužije náhradné odoslanie, takže uvidíte skutočný výsledok SMTP.</li>
</ul>
<p class="jhelp-warn"><strong>Pozor:</strong> ak sa zmenia bezpečnostné kľúče WordPressu (salts v súbore wp-config.php), uložené heslo sa už nedá prečítať. Administrácia to ohlási a heslo treba zadať znova.</p>
HTML
	);

	$s[] = array( 'posta', 'Práca s poštou', 'dashicons-email-alt2', <<<'HTML'
<p>Stránka <strong>Pošta</strong> má hore vyhľadávanie, vľavo priečinky a v strede zoznam konverzácií. Tlačidlom ☰ ľavý panel zbalíte, ikonou ozubeného kolieska otvoríte Nastavenia.</p>
<h3>Priečinky</h3>
<table class="jhelp-table">
<tbody>
<tr><td><strong>Doručené</strong></td><td>Prichádzajúca pošta. Číslo = neprečítané.</td></tr>
<tr><td><strong>S hviezdičkou</strong></td><td>Konverzácie označené hviezdičkou.</td></tr>
<tr><td><strong>Odložené</strong></td><td>Odložené konverzácie s časom návratu.</td></tr>
<tr><td><strong>Dôležité</strong></td><td>Konverzácie so značkou Dôležité.</td></tr>
<tr><td><strong>Odoslané</strong></td><td>Kópie odoslaných správ (aj so skrytými príjemcami).</td></tr>
<tr><td><strong>Koncepty</strong></td><td>Rozpísané správy (ukladajú sa na webe pre každého používateľa).</td></tr>
<tr><td><strong>Archív</strong></td><td>Vybavené konverzácie – nezmažú sa, len zmiznú z Doručených.</td></tr>
<tr><td><strong>Spam a Kôš</strong></td><td>Nevyžiadaná a odstránená pošta. Odstránenie v Koši je natrvalo.</td></tr>
</tbody>
</table>
<p>Pod nimi sú <strong>vlastné priečinky</strong> vašej schránky (do 40) s počtom neprečítaných.</p>
<h3>Konverzácie</h3>
<p>Odpovede aj vaše odoslané správy sú spojené do jednej konverzácie podľa hlavičiek správ, prípadne podľa predmetu (Re:, Fwd:) medzi tými istými ľuďmi. Staršie správy sú zbalené, kliknutím ich rozbalíte. Zoskupovanie sa dá vypnúť v Nastaveniach (<em>Zoskupovať odpovede do konverzácií</em>).</p>
<h3>Rýchle a hromadné akcie</h3>
<ul>
<li><strong>Pri prejdení myšou</strong> nad konverzáciou: Archivovať, Odstrániť, Označiť ako prečítané / neprečítané, Odložiť.</li>
<li><strong>Výber:</strong> zaškrtnite konverzácie (alebo v ponuke výberu zvoľte Všetky, Žiadne, Prečítané, Neprečítané, S hviezdičkou, Bez hviezdičky) a použite akcie hore: archivovať, spam, odstrániť, presunúť do priečinka, odložiť, prečítané, dôležité, hviezdička.</li>
<li><strong>Presun ťahaním:</strong> konverzáciu potiahnite myšou na priečinok vľavo (do Odložených, Odoslaných a Konceptov sa presúvať nedá).</li>
<li><strong>Hviezdička a Dôležité</strong> sa prepínajú kliknutím priamo v zozname.</li>
</ul>
<h3>Odloženie</h3>
<p>Konverzácia zmizne z Doručených a vráti sa ako neprečítaná vo zvolený čas: <em>Neskôr dnes</em> (o 3 hodiny), <em>Zajtra</em> (8:00), <em>Tento víkend</em> (sobota 8:00), <em>Budúci týždeň</em> (pondelok 8:00) alebo vlastný dátum a čas. Návrat kontroluje web každých 5 minút a pri otvorení pošty.</p>
<h3>Hľadanie</h3>
<p>Do poľa hore napíšte slovo, meno alebo adresu a stlačte <kbd>Enter</kbd>. Hľadá sa v <strong>Doručených, Archíve, Odoslaných a Odložených</strong>, aj bez diakritiky. Spam a Kôš sa neprehľadávajú.</p>
<h3>Čítanie správ a prílohy</h3>
<p>HTML správy sa zobrazujú v oddelenom bezpečnom rámci. Prílohy majú ikonu podľa typu; obrázky a PDF sa dajú otvoriť priamo, ostatné sa stiahnu.</p>
HTML
	);

	$s[] = array( 'pisanie', 'Písanie správ', 'dashicons-edit', <<<'HTML'
<h3>Okno Napísať</h3>
<p>Tlačidlo <em>Napísať</em> (kláves <kbd>c</kbd>) otvorí okno vpravo dole. Dá sa <strong>minimalizovať</strong>, zväčšiť na <strong>celú obrazovku</strong> a otvoriť viac okien naraz.</p>
<ul>
<li><strong>Od:</strong> ak máte aliasy, vyberiete adresu odosielateľa.</li>
<li><strong>Komu, Kópia, Skrytá kópia:</strong> pri písaní sa ponúkajú adresy, s ktorými ste si písali.</li>
<li><strong>Formátovanie:</strong> tučné (<kbd>Ctrl</kbd>+<kbd>B</kbd>), kurzíva (<kbd>Ctrl</kbd>+<kbd>I</kbd>), podčiarknuté (<kbd>Ctrl</kbd>+<kbd>U</kbd>), číslovaný zoznam, odrážky, citácia a odkaz.</li>
<li><strong>Prílohy:</strong> kancelárskou sponkou alebo presunutím súboru do okna. Jeden súbor môže mať najviac 25 MB (a zároveň limit servera vašej schránky).</li>
<li><strong>Podpis</strong> z Nastavení sa pridá na koniec každej novej správy a odpovede.</li>
</ul>
<h3>Odpovedať, Odpovedať všetkým, Preposlať</h3>
<p>Píšu sa priamo pod poslednou správou konverzácie (klávesy <kbd>r</kbd>, <kbd>a</kbd>, <kbd>f</kbd>). Odpoveď sa pošle z adresy, na ktorú správa prišla, a pôvodná správa sa v schránke označí ako zodpovedaná. Pri preposlaní sa pôvodné prílohy pridajú automaticky.</p>
<h3>Koncepty</h3>
<p>Keď okno zavriete bez odoslania, správa sa uloží do <strong>Konceptov</strong>. Koncepty sa ukladajú na webe pre prihláseného používateľa (nie do schránky), takže ich v iných poštových programoch neuvidíte.</p>
<h3>Odoslanie</h3>
<p>Správa odchádza cez SMTP vašej schránky a jej kópia sa uloží do priečinka <strong>Odoslané</strong> (aj so skrytými príjemcami, ako v Gmaile). Keď sa kópiu nepodarí uložiť, správa je aj tak odoslaná.</p>
HTML
	);

	$s[] = array( 'bezpecnost', 'Bezpečnosť a súkromie', 'dashicons-shield', <<<'HTML'
<ul>
<li><strong>Prístup:</strong> poštu, nastavenia aj prílohy vidí len administrátor webu. Každé stiahnutie prílohy je chránené jednorazovým kľúčom.</li>
<li><strong>Heslo</strong> schránky a tajný kľúč Google sa ukladajú zašifrované kľúčom webu. Každý web má vlastné nastavenia a vlastnú schránku.</li>
<li><strong>HTML správy</strong> sa zobrazujú v izolovanom rámci, takže skripty zo správy nemôžu ovplyvniť administráciu.</li>
<li><strong>Obrázky z internetu</strong> sú v správach skryté (odosielateľ by inak videl, že ste správu otvorili). Kliknite <em>Zobraziť obrázky</em> pre jednu správu alebo <em>Vždy zobrazovať od…</em> pre odosielateľa. Zoznam dôveryhodných adries a domén (napr. <code>@dodavatel.sk</code>) je v Nastaveniach v poli <em>Obrázky vždy zobraziť od</em>.</li>
<li><strong>Natrvalo</strong> sa správy mažú len v Koši a vždy s potvrdením.</li>
</ul>
HTML
	);

	$s[] = array( 'web', 'E-maily webu cez schránku', 'dashicons-cart', <<<'HTML'
<p>Keď je v Nastaveniach zapnuté <strong>Odosielanie e-mailov webu</strong>, všetky e-maily, ktoré posiela WordPress a doplnky (potvrdenia objednávok WooCommerce, faktúry J-Fakturácie, „Objednávka odoslaná“ z J-Dopravy, registrácia, obnova hesla), odchádzajú cez SMTP vašej schránky.</p>
<h3>Prečo nepadajú do spamu</h3>
<p>E-mail odoslaný zo schránky vašej domény prejde kontrolami SPF, DKIM a DMARC, ktoré má poskytovateľ schránky nastavené. E-mail poslaný priamo zo servera webu ich často nespĺňa a končí v spame.</p>
<ul>
<li>Odosielateľ z <strong>inej domény</strong> (napr. gmail.com) sa nahradí adresou schránky, meno odosielateľa ostane.</li>
<li>Adresy <strong>tej istej domény</strong> (napr. <code>eshop@vasa-firma.sk</code> nastavená vo WooCommerce) ostanú – ak ich schránka smie používať (typicky aliasy).</li>
</ul>
<h3>Náhradné odoslanie pri chybe</h3>
<p>Ak sa e-mail webu cez SMTP nepodarí odoslať (napr. zlé heslo, výpadok servera), J-Pošta ho <strong>hneď pošle ešte raz štandardne cez server webu</strong> s pôvodnými údajmi a prílohami. Zákazník tak e-mail dostane a doplnky (napr. J-Fakturácia) ho nepovažujú za neodoslaný.</p>
<p>V administrácii sa potom 24 hodín zobrazuje upozornenie <em>„J-Pošta: odoslanie e-mailu webu cez SMTP schránky zlyhalo…“</em> s časom a chybou. Upozornenie zmizne po úspešnom <em>skúšobnom e-maile</em> alebo po uložení nastavení.</p>
HTML
	);

	$s[] = array( 'sablony', 'Šablóny e-mailov', 'dashicons-layout', <<<'HTML'
<p>Šablóna je <strong>vzhľad e-mailu</strong> v HTML, do ktorého sa na miesto <code>{obsah}</code> vloží text správy. Používajú ich nástroje iných doplnkov – napríklad Rýchly e-mail na webe (cez My J-Connector) – a funkcia <code>jem_send_template()</code>.</p>
<h3>Predvolené šablóny</h3>
<p><strong>Moderná</strong> (farebná hlavička s názvom webu), <strong>Jednoduchá</strong> (len text a podpis) a <strong>Oznámenie</strong> (tmavé pozadie, dátum a farebný pruh). Každú môžete upraviť, vymazať (aspoň jedna musí ostať) alebo pridať novú. <em>Obnoviť predvolené šablóny</em> vráti pôvodný stav – vlastné úpravy sa stratia.</p>
<h3>Premenné</h3>
<table class="jhelp-table">
<tbody>
<tr><td><code>{obsah}</code></td><td>text správy (povinné – bez neho sa šablóna neuloží)</td></tr>
<tr><td><code>{predmet}</code></td><td>predmet e-mailu</td></tr>
<tr><td><code>{meno}</code></td><td>meno príjemcu (dá sa použiť aj v texte správy, napr. „Dobrý deň, {meno},“)</td></tr>
<tr><td><code>{odosielatel}</code></td><td>meno toho, kto e-mail posiela</td></tr>
<tr><td><code>{web}</code>, <code>{web_url}</code></td><td>názov a adresa webu</td></tr>
<tr><td><code>{datum}</code></td><td>dnešný dátum</td></tr>
<tr><td><code>{podpis}</code></td><td>podpis z Nastavení (inak názov webu)</td></tr>
<tr><td><code>{farba}</code></td><td>farba šablóny (vyberá sa pri úprave)</td></tr>
</tbody>
</table>
<p>Pri úprave vidíte vpravo <strong>náhľad</strong> s ukážkovým textom (obnoví sa po uložení).</p>
<h3>Odosielanie podľa šablóny</h3>
<ul>
<li><strong>Každý príjemca dostane vlastný e-mail</strong> – vidí len seba a <code>{meno}</code> je jeho meno.</li>
<li><strong>Limity pre nástroje:</strong> jeden používateľ najviac N e-mailov za hodinu (predvolene 30) a najviac M príjemcov naraz (predvolene 20). Nastavujú sa na stránke Šablóny.</li>
<li><strong>Odoslané z nástrojov:</strong> tabuľka posledných 100 odoslaných (kedy, kto, komu, predmet, šablóna, stav; pri chybe jej popis po prejdení myšou). Záznam si pamätá posledných 500.</li>
</ul>
HTML
	);

	$s[] = array( 'faktury', 'Faktúry z e-mailu (Gmail)', 'dashicons-media-spreadsheet', <<<'HTML'
<p>Niektorí dodávatelia (telefón, internet, predplatné…) posielajú faktúry e-mailom na účet Google. J-Pošta sa k tomuto účtu pripojí <strong>len na čítanie</strong>, nájde faktúry s PDF vo vybraných štítkoch a prevezme ich do <strong>J-Fakturácie</strong> ako prijaté faktúry. Potrebná je J-Fakturácia 0.10.1 alebo novšia.</p>
<h3>1. Prepojenie s Google (jednorazovo)</h3>
<ol class="jhelp-steps">
<li>V <strong>Google Cloud Console</strong> vytvorte projekt (napr. „Faktúry e-shop“).</li>
<li>Zapnite <strong>Gmail API</strong>.</li>
<li><strong>Google Auth Platform → Začať:</strong> názov aplikácie, váš e-mail, publikum <strong>Externé</strong>. V časti <em>Publikum</em> pridajte svoj Gmail ako testovacieho používateľa, alebo aplikáciu zverejnite.</li>
<li><strong>Klienti → Vytvoriť klienta</strong>, typ <strong>Webová aplikácia</strong>, do <em>Autorizované URI presmerovania</em> vložte adresu zo stránky Faktúry z e-mailu.</li>
<li><strong>ID klienta</strong> a <strong>tajný kľúč klienta</strong> skopírujte do J-Pošty a uložte (tajný kľúč sa uloží zašifrovaný).</li>
</ol>
<p class="jhelp-warn"><strong>Pozor:</strong> kým je aplikácia v Google v režime <em>Testovanie</em>, prístup platí len 7 dní – potom treba účet pripojiť znova. Po zverejnení aplikácie toto obmedzenie odpadne.</p>
<h3>2. Účty Google a štítky</h3>
<ul>
<li><strong>+ Pripojiť účet Google:</strong> Google sa opýta na povolenie „Zobrazovať e-mailové správy a nastavenia“ (len čítanie). Pripojiť môžete aj viac účtov.</li>
<li><strong>Štítky:</strong> zaškrtnite štítky, v ktorých sú faktúry (napr. „Telekom“). Ku každému vyberte <strong>dodávateľa</strong> z J-Fakturácie (alebo „podľa odosielateľa“) a <strong>kategóriu nákladu</strong> (napr. Telefón a internet).</li>
<li><strong>Faktúry od dátumu:</strong> odkedy sa majú faktúry hľadať (predvolene od začiatku roka).</li>
<li><strong>Odpojiť účet</strong> zruší aj prístup v Google.</li>
</ul>
<h3>3. Načítanie a prevzatie faktúr</h3>
<ol class="jhelp-steps">
<li>Kliknite <strong>Načítať faktúry zo sledovaných štítkov</strong>. Zobrazia sa správy s PDF prílohou.</li>
<li>Pri každej sa vyčíta <strong>suma, variabilný symbol, číslo faktúry, obdobie a splatnosť</strong> – zo správy, a ak tam suma nie je, aj z PDF.</li>
<li>Ak sa suma nenašla, zadajte ju (a VS) ručne – PDF otvoríte priamo z tabuľky.</li>
<li>Zaškrtnite faktúry a kliknite <strong>Prevziať vybrané do J-Fakturácie</strong>.</li>
</ol>
<p>Vznikne <strong>prijatá faktúra s PDF</strong>, dodávateľom a kategóriou podľa štítka. Dátum vystavenia je dátum e-mailu, dodanie koniec obdobia, splatnosť podľa faktúry (inak +14 dní). Ak je v banke platba s rovnakým VS a sumou, <strong>spáruje sa a zaúčtuje</strong>. Prevzatá faktúra je označená <em>✓ prevzatá</em> a druhýkrát sa neprevezme.</p>
HTML
	);

	$s[] = array( 'skratky', 'Klávesové skratky', 'dashicons-keyboard-hide', <<<'HTML'
<p>Skratky fungujú na stránke Pošta, keď nepíšete do poľa. Zoznam zobrazíte klávesom <kbd>?</kbd>.</p>
<table class="jhelp-table">
<thead><tr><th>Kláves</th><th>Akcia</th></tr></thead>
<tbody>
<tr><td><kbd>c</kbd></td><td>Napísať</td></tr>
<tr><td><kbd>/</kbd></td><td>Hľadať</td></tr>
<tr><td><kbd>j</kbd> / <kbd>k</kbd></td><td>Ďalšia / predošlá konverzácia</td></tr>
<tr><td><kbd>o</kbd> alebo <kbd>Enter</kbd></td><td>Otvoriť konverzáciu</td></tr>
<tr><td><kbd>u</kbd></td><td>Späť na zoznam</td></tr>
<tr><td><kbd>x</kbd></td><td>Vybrať konverzáciu</td></tr>
<tr><td><kbd>e</kbd></td><td>Archivovať</td></tr>
<tr><td><kbd>#</kbd></td><td>Odstrániť</td></tr>
<tr><td><kbd>!</kbd></td><td>Nahlásiť spam</td></tr>
<tr><td><kbd>s</kbd></td><td>Hviezdička</td></tr>
<tr><td><kbd>Shift</kbd>+<kbd>i</kbd> / <kbd>Shift</kbd>+<kbd>u</kbd></td><td>Označiť ako prečítané / neprečítané</td></tr>
<tr><td><kbd>r</kbd></td><td>Odpovedať</td></tr>
<tr><td><kbd>a</kbd></td><td>Odpovedať všetkým</td></tr>
<tr><td><kbd>f</kbd></td><td>Preposlať</td></tr>
<tr><td><kbd>Ctrl</kbd>+<kbd>Enter</kbd></td><td>Odoslať správu</td></tr>
<tr><td><kbd>Esc</kbd></td><td>Zavrieť okno alebo ponuku</td></tr>
<tr><td><kbd>?</kbd></td><td>Zoznam skratiek</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'nastavenia', 'Všetky nastavenia', 'dashicons-admin-settings', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Nastavenie</th><th>Význam</th><th>Predvolene</th></tr></thead>
<tbody>
<tr><td>E-mailová adresa, meno, prihlasovacie meno, heslo</td><td>Prístup k schránke (kapitola Nastavenie schránky).</td><td>meno = názov webu</td></tr>
<tr><td>Aliasy (odosielať aj z)</td><td>Ďalšie adresy schránky na odosielanie.</td><td>žiadne</td></tr>
<tr><td>Prijímanie (IMAP)</td><td>Server, port, šifrovanie.</td><td>imap.websupport.sk, 993, SSL/TLS</td></tr>
<tr><td>Priečinky Odoslané, Kôš, Archív, Spam</td><td>Ručné určenie priečinka, ak sa nenájde správny.</td><td>automaticky</td></tr>
<tr><td>Odosielanie (SMTP)</td><td>Server, port, šifrovanie.</td><td>smtp.websupport.sk, 465, SSL/TLS</td></tr>
<tr><td>Odosielanie e-mailov webu</td><td>Všetky e-maily webu cez schránku.</td><td>zapnuté</td></tr>
<tr><td>Konverzácií na stránku</td><td>10 až 100.</td><td>50</td></tr>
<tr><td>Zobrazenie konverzácií</td><td>Zoskupovať odpovede do konverzácií.</td><td>zapnuté</td></tr>
<tr><td>Obrázky vždy zobraziť od</td><td>Dôveryhodné adresy a domény (doména s @ na začiatku).</td><td>žiadne</td></tr>
<tr><td>Podpis</td><td>Text na konci každej novej správy a odpovede.</td><td>prázdny</td></tr>
<tr><td>Limity pre nástroje (Šablóny)</td><td>E-mailov za hodinu na používateľa / príjemcov naraz.</td><td>30 / 20</td></tr>
</tbody>
</table>
<p>Po uložení nastavení sa obnoví zoznam priečinkov, počet neprečítaných a našepkávané kontakty a zmizne upozornenie na chybu SMTP.</p>
HTML
	);

	$s[] = array( 'problemy', 'Keď niečo nejde', 'dashicons-sos', <<<'HTML'
<table class="jhelp-table">
<thead><tr><th>Problém</th><th>Čo urobiť</th></tr></thead>
<tbody>
<tr><td><strong>Upozornenie „odoslanie e-mailu webu cez SMTP schránky zlyhalo… Údaje neboli akceptované“</strong></td><td>Server schránky odmietol prihlásenie. Skontrolujte heslo (a prihlasovacie meno) v Nastaveniach, uložte, kliknite <em>Otestovať pripojenie</em> a potom <em>Poslať skúšobný e-mail</em>. Kým je chyba, e-maily webu idú náhradne cez server webu.</td></tr>
<tr><td><strong>Pošta sa nenačíta / chyba IMAP</strong></td><td>Overte server, port a šifrovanie (WebSupport: imap.websupport.sk, 993, SSL/TLS) a heslo. <em>Otestovať pripojenie</em> vypíše presnú chybu.</td></tr>
<tr><td><strong>„Heslo schránky sa nedá prečítať“</strong></td><td>Zmenili sa bezpečnostné kľúče WordPressu. Zadajte heslo v Nastaveniach znova.</td></tr>
<tr><td><strong>Odoslané správy nie sú v Odoslaných</strong></td><td>V Nastaveniach zadajte presný názov priečinka Odoslané (zoznam priečinkov vypíše test pripojenia).</td></tr>
<tr><td><strong>E-maily webu končia v spame</strong></td><td>Zapnite <em>Odosielanie e-mailov webu</em>. Odosielateľ vo WooCommerce má byť adresa vašej domény (schránka alebo alias). U poskytovateľa domény overte záznamy SPF, DKIM a DMARC.</td></tr>
<tr><td><strong>Správa sa neodoslala – príloha</strong></td><td>Súbor nad 25 MB sa nepridá; menšie súbory môže odmietnuť server schránky podľa svojho limitu.</td></tr>
<tr><td><strong>Odložená konverzácia sa nevrátila</strong></td><td>Návrat beží každých 5 minút cez plánovač WordPressu (WP-Cron) a pri otvorení pošty. Na webe bez návštev otvorte Poštu alebo nastavte skutočný cron.</td></tr>
<tr><td><strong>Faktúry z e-mailu: „Účet nie je pripojený“ alebo Google odmietol prístup</strong></td><td>Prístup vypršal (režim Testovanie = 7 dní) alebo bol zrušený. Pripojte účet znova, prípadne aplikáciu v Google zverejnite.</td></tr>
<tr><td><strong>Faktúry z e-mailu: „suma nenájdená“</strong></td><td>Otvorte PDF z tabuľky a zadajte sumu (a VS) ručne, potom faktúru prevezmite.</td></tr>
<tr><td><strong>Faktúry z e-mailu: chýba J-Fakturácia</strong></td><td>Prevzatie potrebuje J-Fakturáciu 0.10.1 alebo novšiu.</td></tr>
</tbody>
</table>
HTML
	);

	$s[] = array( 'technicke', 'Technické údaje (pre vývojára)', 'dashicons-editor-code', <<<'HTML'
<h3>Súbory doplnku</h3>
<table class="jhelp-table">
<tbody>
<tr><td><code>includes/imap.php</code></td><td>Vlastný IMAP klient (netreba rozšírenie php-imap).</td></tr>
<tr><td><code>includes/mime.php</code></td><td>Rozbor správ (MIME, kódovania, prílohy).</td></tr>
<tr><td><code>includes/mailbox.php</code></td><td>Priečinky, konverzácie, odloženie, koncepty, kontakty.</td></tr>
<tr><td><code>includes/api.php</code></td><td>AJAX pre aplikáciu Pošta (<code>jem_boot</code>, <code>jem_list</code>, <code>jem_thread</code>, <code>jem_act</code>, <code>jem_send</code>, <code>jem_draft</code>, <code>jem_contacts</code>…).</td></tr>
<tr><td><code>includes/send.php</code></td><td>Odosielanie cez SMTP (PHPMailer z WordPressu), e-maily webu a náhradné odoslanie.</td></tr>
<tr><td><code>includes/settings.php</code></td><td>Nastavenia, šifrovanie hesla, test pripojenia.</td></tr>
<tr><td><code>includes/templates.php</code></td><td>Šablóny e-mailov, odosielanie podľa šablóny, limity a záznam.</td></tr>
<tr><td><code>includes/google.php</code></td><td>Faktúry z e-mailu (Gmail API, OAuth, prevzatie do J-Fakturácie).</td></tr>
<tr><td><code>assets/app.js</code>, <code>app.css</code></td><td>Aplikácia Pošta v prehliadači.</td></tr>
</tbody>
</table>
<h3>Uložené údaje</h3>
<ul>
<li>Voľby: <code>jem_settings</code> (heslo zašifrované AES-256-GCM, predpona <code>v1:</code>), <code>jem_templates</code>, <code>jem_tool_log</code>, <code>jem_snoozed</code>, <code>jem_smtp_failed</code>, nastavenia Google.</li>
<li>Používateľské údaje: <code>jem_drafts</code> (koncepty).</li>
<li>Dočasné: <code>jem_folders</code> (mapa priečinkov, 1 deň), <code>jem_unseen</code>, <code>jem_contacts</code>.</li>
<li>Plánovač: <code>jem_check_unseen</code> každých 5 minút (neprečítané + návrat odložených).</li>
</ul>
<h3>Funkcie pre iné doplnky</h3>
<ul>
<li><code>jem_send( array $d )</code> – odoslanie správy (to, cc, bcc, subject, body alebo html, from, prílohy). Vráti <code>true</code> alebo text chyby.</li>
<li><code>jem_send_template( array $a )</code> – odoslanie podľa šablóny každému príjemcovi zvlášť (to, subject, message, template, source), s limitmi a záznamom. Vráti <code>['sent' =&gt; n, 'errors' =&gt; [...]]</code> alebo <code>WP_Error</code>.</li>
<li><code>jem_template_render( $id, $predmet, $text, $premenne )</code> – HTML e-mailu podľa šablóny.</li>
<li><code>jem_configured()</code> – je schránka nastavená.</li>
</ul>
<h3>Prepojenia</h3>
<p>J-Fakturácia: prijaté faktúry cez <code>jinv_received_create()</code> (zdroj <code>email</code>, odkaz <code>gmail:&lt;id správy&gt;</code>). Kategórie nákladov z filtra <code>jinv_expense_categories</code>. Návody pre J-Pomocník cez filter <code>jupiter_guides</code>.</p>
HTML
	);

	return $s;
}
