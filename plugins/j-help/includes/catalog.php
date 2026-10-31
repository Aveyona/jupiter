<?php
/**
 * Katalóg doplnkov Jupiter. Ďalší J-doplnok sa doňho pridá filtrom `jupiter_plugins`:
 *
 *     add_filter( 'jupiter_plugins', function ( $p ) {
 *         $p['j-invoices'] = array( 'name' => 'J-Fakturácia', 'icon' => 'dashicons-media-spreadsheet', 'what' => '…', 'status' => 'stable' );
 *         return $p;
 *     } );
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stavy vývoja doplnku. */
function jhelp_statuses() {
	return array(
		'stable'  => 'hotový',
		'dev'     => 'vo vývoji',
		'planned' => 'plánovaný',
		'idea'    => 'nápad',
	);
}

/** Všetky doplnky rodiny Jupiter: slug => názov, ikona, popis, stav. */
function jhelp_plugins() {
	$plugins = array(
		'j-help'     => array(
			'name'   => 'J-Pomocník',
			'icon'   => 'dashicons-sos',
			'what'   => 'Táto dokumentácia: prehľad doplnkov Jupiter, návody a plán vývoja. V menu je vždy posledný z doplnkov Jupiter (skupina pod Marketingom).',
			'status' => 'stable',
		),
		'j-invoices' => array(
			'name'   => 'J-Fakturácia',
			'icon'   => 'dashicons-media-spreadsheet',
			'what'   => 'Doklady k objednávkam WooCommerce: faktúra, zálohová faktúra (automaticky pri platbe prevodom) a dobropis; PDF s QR kódom PAY by square, odoslanie e-mailom, odkaz na stiahnutie pre zákazníka, pole „Nakupujem ako firma“ (IČO, DIČ, IČ DPH) v pokladni, export do účtovníctva Pohoda, prechod z doplnku Woo Faktura. Prijaté faktúry s prílohami a splatnosťou.',
			'status' => 'planned',
		),
		'j-emails'   => array(
			'name'   => 'J-Pošta',
			'icon'   => 'dashicons-email-alt',
			'what'   => 'E-maily: správca e-mailovej schránky obchodu priamo v administrácii (prijaté a odoslané správy, odpovede zákazníkom) a prepojenie s Mailchimpom pre zákazníkov (zoznam odberateľov, súhlasy, kampane).',
			'status' => 'planned',
		),
		'j-sklad'    => array(
			'name'   => 'J-Sklad',
			'icon'   => 'dashicons-archive',
			'what'   => 'Sklad: príjemky, výdajky a inventúra napojené na produkty WooCommerce.',
			'status' => 'idea',
		),
	);
	return apply_filters( 'jupiter_plugins', $plugins );
}

/**
 * Plán vývoja Jupitera – etapy v poradí, v akom vznikajú.
 * Stav: done (hotovo), now (prebieha), next (plánované).
 */
function jhelp_roadmap() {
	return apply_filters( 'jupiter_roadmap', array(
		array(
			'title'  => 'Základ Jupitera a J-Pomocník',
			'state'  => 'done',
			'points' => array(
				'Verejný repozitár na GitHube (licencia GPL-2.0-or-later), automatická kontrola kódu a vydávanie ZIP doplnkov.',
				'J-Pomocník: prehľad doplnkov, návody a plán vývoja priamo v administrácii.',
				'Doplnky Jupiter majú v menu vlastné miesto hneď pod položkou Marketing.',
			),
		),
		array(
			'title'  => 'J-Fakturácia 0.1 – faktúry z objednávok',
			'state'  => 'done',
			'points' => array(
				'Faktúra z objednávky automaticky (prevod po zaplatení, dobierka a osobný odber po vybavení) alebo tlačidlom v objednávke.',
				'Ročný číselný rad, variabilný symbol, splatnosť, text pre neplatiteľa DPH, katalógové číslo (SKU) pri položkách.',
				'Prehľad faktúr s filtrom, stavy uhradená / po splatnosti / stornovaná, tlačový náhľad, box v objednávke a stĺpec v zozname objednávok.',
			),
		),
		array(
			'title'  => 'J-Fakturácia 0.2 – prijaté faktúry',
			'state'  => 'done',
			'points' => array(
				'Evidencia dodávateľských faktúr: dodávateľ, číslo, VS, dátumy, suma, kategória, poznámka.',
				'Príloha PDF alebo fotka v chránenom priečinku, stav uhradená / neuhradená / po splatnosti, súčet neuhradených.',
			),
		),
		array(
			'title'  => 'J-Fakturácia – PDF, e-mail a odkaz pre zákazníka',
			'state'  => 'now',
			'points' => array(
				'PDF dokladu s logom a QR kódom PAY by square (úhrada prevodom naskenovaním v bankovej aplikácii).',
				'Odoslanie e-mailom „Doklad k objednávke … z {obchod}“ s PDF v prílohe – podľa nastavenia automaticky alebo po potvrdení tlačidlom Poslať e-mailom.',
				'Zákazník si doklad stiahne sám v Môj účet → Objednávky a z e-mailu (zabezpečený odkaz, nie do administrácie).',
			),
		),
		array(
			'title'  => 'J-Fakturácia – zálohové faktúry, dobropisy, box v objednávke',
			'state'  => 'next',
			'points' => array(
				'Zálohová faktúra automaticky pri platbe prevodom, keď objednávka prejde do stavu Pozastavené – hneď sa pošle zákazníkovi.',
				'Box Faktúry v objednávke: Vystaviť, Zobraziť, Znova vystaviť, Poslať e-mailom, Zmazať – pre faktúru, zálohovú faktúru aj dobropis.',
				'Hromadné vystavenie faktúr pre vybrané objednávky v zozname objednávok.',
				'Číslovanie podľa voľby: vlastný ročný rad alebo rovnaké ako číslo objednávky; splatnosť nastaviteľná (napr. 7 dní).',
			),
		),
		array(
			'title'  => 'J-Fakturácia – pokladňa „Nakupujem ako firma“',
			'state'  => 'next',
			'points' => array(
				'Zaškrtávacie pole Nakupujem ako firma a nepovinné polia IČO, DIČ a IČ DPH v pokladni.',
				'Údaje firmy sa prenesú na faktúru a sú v detaile objednávky.',
			),
		),
		array(
			'title'  => 'J-Fakturácia – export do Pohody a pre účtovníka',
			'state'  => 'next',
			'points' => array(
				'Export faktúr za zvolené obdobie do súboru XML na import do účtovníctva Pohoda.',
				'Export pre účtovníka: tabuľka (XLSX/CSV) a ZIP so všetkými PDF.',
			),
		),
		array(
			'title'  => 'J-Fakturácia – prechod z doplnku Woo Faktura',
			'state'  => 'next',
			'points' => array(
				'Načítanie existujúcich faktúr, zálohových faktúr a dobropisov (aj PDF) z Woo Faktura, aby boli v prehľade a v účte zákazníka.',
				'Pokračovanie v rovnakom číslovaní, potom vypnutie starého doplnku.',
			),
		),
		array(
			'title'  => 'J-Emails 0.1 – schránka v administrácii',
			'state'  => 'done',
			'points' => array(
				'Doručená pošta, Odoslané a Kôš ako samostatné stránky; čítanie správ s prílohami, HTML v izolovanom rámci.',
				'Napísať, Odpovedať, Odpovedať všetkým, Preposlať; kópia do Odoslaných; zašifrované heslo; test pripojenia.',
			),
		),
		array(
			'title'  => 'J-Emails – Mailchimp a e-maily e-shopu',
			'state'  => 'next',
			'points' => array(
				'HTML odpovede a šablóny odpovedí.',
				'Mailchimp: synchronizácia zákazníkov so súhlasom, zoznamy a kampane.',
				'Odosielanie faktúr a e-mailov e-shopu cez J-Emails.',
			),
		),
		array(
			'title'  => 'J-Objednávky 0.3 – opravy číslovania a príručka',
			'state'  => 'done',
			'points' => array(
				'Bloková pokladňa nemíňa čísla objednávok (koncept pri otvorení pokladne číslo nedostane), číslo aj pre objednávky z REST API a iných doplnkov, staršie objednávky si ponechajú číslo WooCommerce.',
				'Zoznam bez konceptov z pokladne, rýchly filter Na fakturáciu aj pri tisícoch faktúr, filter len zapnutých platieb.',
				'Podrobná príručka J-Objednávky v J-Pomocníkovi.',
			),
		),
		array(
			'title'  => 'J-Objednávky – vylepšenia zoznamu a číslovania',
			'state'  => 'next',
			'points' => array(
				'Automatický nový rok v čísle objednávky: {RRRR} / {RR} v prefixe, poradie sa 1. januára samo vynuluje.',
				'Rozbalenie riadku: položky, adresa, telefón a poznámka bez otvárania detailu objednávky.',
				'Doprava podrobnejšie: výdajné miesto Packeta / Z-BOX a stav zásielky z J-Dopravy.',
				'Nezaplatené prevody: filter „čaká na platbu dlhšie ako X dní“ a voliteľné automatické zrušenie po N dňoch.',
				'Export zoznamu do CSV podľa filtra; filter dopravy, triedenie stĺpcov, počet objednávok na stránku.',
				'Rýchly e-mail zákazníkovi z riadku cez J-Poštu.',
			),
		),
		array(
			'title'  => 'J-Doprava 0.3 – opravy, pošta, osobný odber, príručka',
			'state'  => 'done',
			'points' => array(
				'Oprava dobierky (objednávka v stave Spracováva sa sa posielala bez dobierky), upozornenie na nečitateľné API heslo, spoľahlivejšie sledovanie, poistka proti dvojitému podaniu.',
				'Automatické podanie zásielky Packeta, záložka Na tlač, spätná zásielka (vrátenie / reklamácia) s kódom pre zákazníka.',
				'Slovenská pošta – export do ePodacieho hárku; osobný odber – e-mail „pripravené na odber“; výdajné miesto a stav zásielky v J-Objednávkach.',
				'Podrobná príručka J-Doprava v J-Pomocníkovi.',
			),
		),
		array(
			'title'  => 'J-Doprava – ďalej',
			'state'  => 'next',
			'points' => array(
				'Výplaty dobierok od Packety a ich párovanie s platbami v banke (J-Fakturácia).',
				'Slovenská pošta cez API (podanie bez súboru, sledovanie zásielok).',
				'Ďalší prepravcovia (napr. kuriérske spoločnosti).',
			),
		),
		array(
			'title'  => 'J-Sklad a ďalšie doplnky',
			'state'  => 'next',
			'points' => array(
				'Príjemky, výdajky a inventúra.',
				'Vyčítanie údajov z prijatých faktúr pomocou umelej inteligencie.',
			),
		),
	) );
}
