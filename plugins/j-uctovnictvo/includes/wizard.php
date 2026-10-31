<?php
/**
 * Sprievodca založením účtovníctva a firmy (9 krokov). Každý krok sa uloží a dá sa k nemu vrátiť;
 * posledný krok založí účtovníctvo (počiatočné stavy, napojenie) a zaúčtuje doklady od dátumu začiatku.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-uctovnictvo', 'Sprievodca založením – J-Účtovníctvo', get_option( 'juc_setup_done' ) ? 'Sprievodca založením' : '★ Založiť účtovníctvo', JUC_CAP, 'j-uctovnictvo-wizard', 'juc_page_wizard', 1 );
}, 20 );

function juc_wizard_steps() {
	return array(
		1 => 'Firma',
		2 => 'Veľkosť a DPH',
		3 => 'Obdobie a začiatok',
		4 => 'Účtový rozvrh',
		5 => 'Počiatočné stavy',
		6 => 'Napojenie dokladov',
		7 => 'Predkontácie',
		8 => 'Kontrola',
		9 => 'Spustenie',
	);
}

function juc_wiz() {
	return wp_parse_args( (array) get_option( 'juc_wizard', array() ), array( 'step' => 1, 'done' => array(), 'mode' => 'new', 'zi' => '', 'zi_date' => '', 'zi_paid' => '', 'zi_paid_date' => '', 'zi_paid_to' => '221001', 'ps' => array() ) );
}

function juc_wiz_save( array $vals ) {
	update_option( 'juc_wizard', array_merge( juc_wiz(), $vals ), false );
}

function juc_wiz_url( $step ) {
	return juc_url( 'j-uctovnictvo-wizard', array( 'step' => $step ) );
}

/** Navrhované analytické účty pre e-shop (s. r. o.). */
function juc_wizard_analytics() {
	$out = array();
	$i   = 1;
	foreach ( function_exists( 'jinv_bank_accounts' ) ? jinv_bank_accounts() : array() as $id => $a ) {
		$out[ '221' . sprintf( '%03d', $i++ ) ] = array( ( $a['name'] ?? 'Bankový účet' ) . ( ! empty( $a['iban'] ) ? ' ' . jinv_iban_format( $a['iban'] ) : '' ), true, 'bank:' . $id );
	}
	if ( ! $out ) {
		$out['221001'] = array( 'Bankový účet', true, '' );
	}
	return $out + array(
		'211001' => array( 'Pokladňa', false, '' ),
		'261001' => array( 'Peniaze na ceste – platobná brána / dobierky', false, '' ),
		'365001' => array( 'Záväzky voči spoločníkovi – výdavky a pôžičky', true, '' ),
		'501001' => array( 'Spotreba materiálu – kancelária', true, '' ),
		'518001' => array( 'Hosting, domény a softvér', true, '' ),
		'518002' => array( 'Reklama a marketing', true, '' ),
		'518003' => array( 'Telefón a internet', true, '' ),
		'518004' => array( 'Poštovné a doprava', true, '' ),
		'518009' => array( 'Ostatné služby', true, '' ),
		'602001' => array( 'Tržby za služby – IT a AI poradenstvo', true, '' ),
		'602002' => array( 'Tržby za služby – tvorba webov', true, '' ),
		'602009' => array( 'Tržby za služby – doprava a poplatky e-shopu', true, '' ),
		'604001' => array( 'Tržby za tovar – e-shop', true, '' ),
	);
}

function juc_page_wizard() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$w    = juc_wiz();
	$step = max( 1, min( 9, (int) ( $_GET['step'] ?? $w['step'] ) ) ); // phpcs:ignore
	$s    = juc_settings();
	juc_head( 'Založenie účtovníctva a firmy' );
	?>
	<ol class="juc-steps">
		<?php foreach ( juc_wizard_steps() as $n => $l ) : ?>
			<li class="<?php echo $n === $step ? 'is-cur' : ''; ?><?php echo in_array( $n, (array) $w['done'], true ) ? ' is-done' : ''; ?>"><a href="<?php echo esc_url( juc_wiz_url( $n ) ); ?>"><span><?php echo (int) $n; ?></span> <?php echo esc_html( $l ); ?></a></li>
		<?php endforeach; ?>
	</ol>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-panel juc-wiz">
		<?php wp_nonce_field( 'juc_wizard' ); ?><input type="hidden" name="action" value="juc_wizard"><input type="hidden" name="step" value="<?php echo (int) $step; ?>">
		<h2><?php echo (int) $step; ?>. <?php echo esc_html( juc_wizard_steps()[ $step ] ); ?></h2>
		<?php call_user_func( 'juc_wiz_step_' . $step, $w, $s ); ?>
		<p class="juc-wiz-nav">
			<?php if ( $step > 1 ) : ?><a class="button" href="<?php echo esc_url( juc_wiz_url( $step - 1 ) ); ?>">← Späť</a><?php endif; ?>
			<?php if ( $step < 9 ) : ?><button class="button button-primary">Uložiť a pokračovať →</button><?php endif; ?>
		</p>
	</form>
	</div>
	<?php
}

/* ---------------------------------------------------------------- Kroky */

function juc_wiz_step_1( $w, $s ) {
	$f = function ( $k, $label, $attr = '' ) use ( $s ) {
		echo '<tr><th><label for="wz-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input id="wz-' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ?? '' ) . '" class="regular-text" ' . $attr . '></td></tr>'; // phpcs:ignore
	};
	echo '<p>Údaje účtovnej jednotky – použijú sa na výkazoch, v účtovných knihách a v hlavičke tlače.</p><table class="form-table" role="presentation">';
	echo '<tr><th><label for="wz-ico">IČO</label></th><td><input id="wz-ico" name="ico" value="' . esc_attr( $s['ico'] ) . '" inputmode="numeric"> ' . ( function_exists( 'jinv_rpo_lookup' ) ? '<button type="button" class="button" id="wz-rpo">Načítať z registra</button> <span id="wz-rpo-msg" class="description"></span>' : '' ) . '</td></tr>';
	$f( 'company', 'Obchodné meno', 'required' );
	$f( 'dic', 'DIČ' );
	$f( 'icdph', 'IČ DPH (ak je)' );
	$f( 'street', 'Sídlo – ulica a číslo' );
	echo '<tr><th>PSČ a obec</th><td><input name="zip" id="wz-zip" value="' . esc_attr( $s['zip'] ?? '' ) . '" style="width:90px" aria-label="PSČ"> <input name="city" id="wz-city" value="' . esc_attr( $s['city'] ?? '' ) . '" aria-label="Obec"></td></tr>';
	$f( 'register', 'Zápis v obchodnom registri', 'placeholder="napr. Okresný súd Žilina, oddiel Sro, vložka č. 12345/L"' );
	echo '<tr><th><label for="wz-founded">Dátum vzniku (zápisu do OR)</label></th><td><input type="date" id="wz-founded" name="founded" value="' . esc_attr( $s['founded'] ?? '' ) . '"></td></tr>';
	$f( 'statutory', 'Štatutárny orgán (konateľ)' );
	echo '</table>';
	if ( function_exists( 'jinv_rpo_lookup' ) ) {
		?>
		<script>
		document.getElementById('wz-rpo').addEventListener('click', function () {
			var b = this, m = document.getElementById('wz-rpo-msg'), fd = new FormData();
			fd.append('action', 'jinv_rpo'); fd.append('_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'jinv_partner' ) ); ?>); fd.append('ico', document.getElementById('wz-ico').value);
			m.textContent = 'Hľadám…'; b.disabled = true;
			fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				b.disabled = false; if (!j.success) { m.textContent = j.data; return; }
				document.getElementById('wz-company').value = j.data.name; document.getElementById('wz-street').value = j.data.street; document.getElementById('wz-zip').value = j.data.zip; document.getElementById('wz-city').value = j.data.city;
				m.textContent = 'Načítané z Registra právnických osôb – doplňte DIČ, zápis v OR a dátum vzniku.';
			});
		});
		</script>
		<?php
	}
}

function juc_wiz_step_2( $w, $s ) {
	?>
	<table class="form-table" role="presentation">
		<tr><th>Veľkosť účtovnej jednotky</th><td>
			<label><input type="radio" name="size" value="micro" <?php checked( $s['size'], 'micro' ); ?>> <strong>Mikro účtovná jednotka</strong> – účtovná závierka vo forme pre mikro účtovné jednotky (súvaha a výkaz ziskov a strát Úč MUJ)</label><br>
			<label><input type="radio" name="size" value="small" <?php checked( $s['size'], 'small' ); ?>> Malá účtovná jednotka (Úč POD)</label>
			<p class="description">Mikro účtovná jednotka nesmie prekročiť aspoň dve z troch hraníc (§ 2 ods. 6 zákona o účtovníctve; od 1. 6. 2024: majetok 450 000 €, čistý obrat 900 000 €, 10 zamestnancov): celková suma majetku, čistý obrat, priemerný počet zamestnancov. Nová malá s. r. o. s e-shopom je spravidla mikro účtovná jednotka.</p>
		</td></tr>
		<tr><th>DPH</th><td>
			<label><input type="radio" name="vat_payer" value="0" <?php checked( (int) $s['vat_payer'], 0 ); ?>> <strong>Neplatiteľ DPH</strong></label><br>
			<label><input type="radio" name="vat_payer" value="1" <?php checked( (int) $s['vat_payer'], 1 ); ?>> Platiteľ DPH od <input type="date" name="vat_from" value="<?php echo esc_attr( $s['vat_from'] ); ?>">, zdaňovacie obdobie <select name="vat_period"><option value="month" <?php selected( $s['vat_period'], 'month' ); ?>>mesiac</option><option value="quarter" <?php selected( $s['vat_period'], 'quarter' ); ?>>štvrťrok</option></select></label>
			<p class="description">Neplatiteľ: faktúry bez DPH, náklady vrátane DPH. Pri registrácii za platiteľa sa prepne tu – účtovanie DPH (343) sa pripraví v ďalšej verzii.</p>
		</td></tr>
	</table>
	<?php
}

function juc_wiz_step_3( $w, $s ) {
	$def = $s['start_date'] ? $s['start_date'] : ( (int) current_time( 'n' ) >= 10 ? ( (int) current_time( 'Y' ) + 1 ) . '-01-01' : current_time( 'Y' ) . '-01-01' );
	?>
	<table class="form-table" role="presentation">
		<tr><th>Účtovné obdobie</th><td>Kalendárny rok (1. 1. – 31. 12.). Ak firma vznikla počas roka, prvé obdobie začína dňom vzniku.</td></tr>
		<tr><th><label for="wz-start">Začiatok účtovania v Jupiteri</label></th><td><input type="date" id="wz-start" name="start_date" required value="<?php echo esc_attr( $def ); ?>">
			<p class="description">Od tohto dňa sa doklady (faktúry, banka, sklad) účtujú v J-Účtovníctve. Pre nový rok: 1. 1. Staršie doklady sa dajú doplniť neskôr – stačí posunúť dátum dozadu a spustiť účtovanie znova (nič sa nezdvojí).</p></td></tr>
		<tr><th>Situácia firmy</th><td>
			<label><input type="radio" name="mode" value="new" <?php checked( $w['mode'], 'new' ); ?>> <strong>Nová firma</strong> – účtovníctvo sa otvára ku dňu vzniku (počiatočné stavy = základné imanie a jeho splatenie)</label><br>
			<label><input type="radio" name="mode" value="continue" <?php checked( $w['mode'], 'continue' ); ?>> <strong>Pokračujúca firma</strong> – počiatočné stavy = konečné zostatky súvahových účtov z minulého obdobia (súvaha / predvaha od účtovníka)</label>
		</td></tr>
	</table>
	<?php
}

function juc_wiz_step_4( $w, $s ) {
	echo '<p>Syntetické účty sú z rámcovej účtovej osnovy pre podnikateľov (' . (int) count( juc_osnova() ) . ' účtov). Navrhované analytické účty pre e-shop – vyberte, ktoré vytvoriť (dajú sa premenovať aj neskôr v Účtovom rozvrhu):</p><table class="widefat striped juc-table"><thead><tr><th></th><th>Účet</th><th>Názov</th><th>Syntetický účet</th></tr></thead><tbody>';
	foreach ( juc_wizard_analytics() as $num => $a ) {
		$exists = (bool) juc_account( $num );
		echo '<tr><td><input type="checkbox" name="an[' . esc_attr( $num ) . ']" value="1" ' . checked( $a[1] || $exists, true, false ) . ( $exists ? ' disabled' : '' ) . '></td><td><strong>' . esc_html( $num ) . '</strong></td><td><input name="an_name[' . esc_attr( $num ) . ']" value="' . esc_attr( $exists ? juc_account_name( $num ) : $a[0] ) . '" class="regular-text"' . ( $exists ? ' disabled' : '' ) . '>' . ( $exists ? ' <small>už existuje</small>' : '' ) . '</td><td>' . esc_html( substr( $num, 0, 3 ) . ' ' . juc_account_name( substr( $num, 0, 3 ) ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

function juc_wiz_step_5( $w, $s ) {
	if ( 'new' === $w['mode'] ) {
		$founded = $s['founded'] ?? '';
		?>
		<p>Nová firma: vznikom spoločnosti vzniká pohľadávka voči spoločníkom za upísané základné imanie (353/411), splatením vkladu sa vyrovná (221/353).</p>
		<table class="form-table" role="presentation">
			<tr><th><label for="wz-zi">Základné imanie (€)</label></th><td><input id="wz-zi" name="zi" inputmode="decimal" value="<?php echo esc_attr( $w['zi'] ); ?>" style="width:120px"> upísané ku dňu <input type="date" name="zi_date" value="<?php echo esc_attr( $w['zi_date'] ? $w['zi_date'] : $founded ); ?>"></td></tr>
			<tr><th>Splatenie vkladu</th><td><input name="zi_paid" inputmode="decimal" value="<?php echo esc_attr( $w['zi_paid'] ); ?>" style="width:120px" placeholder="suma"> € dňa <input type="date" name="zi_paid_date" value="<?php echo esc_attr( $w['zi_paid_date'] ); ?>"> na účet <input name="zi_paid_to" value="<?php echo esc_attr( $w['zi_paid_to'] ); ?>" style="width:90px" list="juc-accs"> <span class="description">(221xxx banka, 211 pokladňa)</span></td></tr>
		</table>
		<p class="description">Ak vznik firmy a splatenie vkladu patria do minulého obdobia, ktoré sa v Jupiteri neúčtuje, zvoľte v kroku 3 „Pokračujúca firma“ a zadajte konečné zostatky.</p>
		<?php
		juc_accounts_datalist();
		return;
	}
	if ( get_option( 'juc_setup_done' ) ) {
		$cur = juc_entry_by_source( 'wizard', 'ps', (int) substr( (string) $s['start_date'], 0, 4 ) );
		echo '<p class="' . ( $cur ? 'juc-ok' : 'juc-warn' ) . '">' . ( $cur ? '✓ Počiatočné stavy sú zaúčtované (doklad ' . esc_html( $cur['number'] ) . '). Po zmene a uložení sa pôvodný doklad stornuje a zaúčtuje znova.' : '! Počiatočné stavy ešte nie sú zaúčtované – vyplňte ich a kliknite Uložiť a zaúčtovať počiatočné stavy.' ) . '</p>';
		echo '<input type="hidden" name="post_ps" value="1">';
	}
	echo '<p class="description">Pokračujúca firma: konečné zostatky súvahových účtov k ' . esc_html( juc_date( gmdate( 'Y-m-d', strtotime( (string) $s['start_date'] . ' -1 day' ) ) ) ) . ' (súvaha a obratová predvaha od účtovníka, ktorý viedol účtovníctvo za minulé obdobie). Ak ich ešte nemáte (uzávierka minulého roka), krok preskočte – zadáte ich neskôr, aj po spustení účtovania.</p>';
	$ps   = (array) $w['ps'];
	$rows = $ps ? $ps : array_fill_keys( array( '221001', '211001', '311', '321', '132', '365001', '411', '428', '429' ), array( 'md' => '', 'd' => '' ) );
	?>
	<p>Konečné zostatky súvahových účtov k poslednému dňu minulého obdobia (zo súvahy alebo predvahy). Aktívne účty (majetok, pohľadávky) majú zostatok na strane <strong>MD</strong>, pasívne (vlastné imanie, záväzky) na strane <strong>Dal</strong>. Zaúčtujú sa proti účtu 701 – Začiatočný účet súvahový; súčty strán sa musia rovnať. Výsledok minulého roka (ak ešte nie je rozdelený) zadajte na 431.</p>
	<table class="widefat juc-table" id="wz-ps">
		<thead><tr><th>Účet</th><th>Názov</th><th class="n">Zostatok MD</th><th class="n">Zostatok Dal</th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $acc => $v ) : ?>
			<tr><td><input name="ps_acc[]" value="<?php echo esc_attr( $acc ); ?>" list="juc-accs" style="width:100px"></td><td class="juc-muted"><?php echo esc_html( juc_account_name( $acc ) ); ?></td><td class="n"><input name="ps_md[]" value="<?php echo esc_attr( $v['md'] ); ?>" inputmode="decimal" class="wz-md" style="width:120px"></td><td class="n"><input name="ps_d[]" value="<?php echo esc_attr( $v['d'] ); ?>" inputmode="decimal" class="wz-d" style="width:120px"></td></tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot><tr><td><button type="button" class="button" id="wz-ps-add">+ Účet</button></td><td>Spolu</td><td class="n"><strong id="wz-smd">0,00</strong></td><td class="n"><strong id="wz-sd">0,00</strong></td></tr></tfoot>
	</table>
	<p id="wz-ps-ok" class="description"></p>
	<h3>Rozpis neuhradených faktúr (nepovinné)</h3>
	<p class="description">Pohľadávky (311) a záväzky (321) môžete rozpísať po faktúrach – v saldokonte potom uvidíte každú faktúru zvlášť a úhrada s rovnakým variabilným symbolom ju uzavrie. Súčet rozpisu účtu sa musí rovnať jeho počiatočnému stavu vyššie. Bez rozpisu sa zaúčtuje jedna suma.</p>
	<?php $sal = (array) ( $w['ps_saldo'] ?? array() ); $sal = $sal ? $sal : array( array( 'acc' => '311', 'partner' => '', 'vs' => '', 'amount' => '' ) ); ?>
	<table class="widefat juc-table" id="wz-sal" style="max-width:760px">
		<thead><tr><th>Účet</th><th>Odberateľ / dodávateľ</th><th>Variabilný symbol (č. faktúry)</th><th class="n">Neuhradená suma €</th></tr></thead>
		<tbody>
		<?php foreach ( $sal as $r ) : ?>
			<tr><td><select name="sal_acc[]"><option value="311" <?php selected( $r['acc'], '311' ); ?>>311 pohľadávka</option><option value="321" <?php selected( $r['acc'], '321' ); ?>>321 záväzok</option></select></td><td><input name="sal_partner[]" value="<?php echo esc_attr( $r['partner'] ); ?>" class="regular-text"></td><td><input name="sal_vs[]" value="<?php echo esc_attr( $r['vs'] ); ?>" style="width:150px"></td><td class="n"><input name="sal_amount[]" value="<?php echo esc_attr( $r['amount'] ); ?>" inputmode="decimal" style="width:120px"></td></tr>
		<?php endforeach; ?>
		</tbody>
		<tfoot><tr><td colspan="4"><button type="button" class="button" id="wz-sal-add">+ Faktúra</button></td></tr></tfoot>
	</table>
	<script>document.getElementById('wz-sal-add').addEventListener('click',function(){var tb=document.querySelector('#wz-sal tbody');var tr=tb.rows[tb.rows.length-1].cloneNode(true);tr.querySelectorAll('input').forEach(function(i){i.value='';});tb.appendChild(tr);});</script>
	<p class="description">Hodnotu tovaru na sklade (132) navrhuje krok 6 podľa J-Skladu.</p>
	<?php juc_accounts_datalist(); ?>
	<script>
	(function () {
		var tb = document.querySelector('#wz-ps tbody');
		function n(v) { return parseFloat(String(v).replace(/\s/g, '').replace(',', '.')) || 0; }
		function sum() { var a = 0, b = 0; tb.querySelectorAll('.wz-md').forEach(function (i) { a += n(i.value); }); tb.querySelectorAll('.wz-d').forEach(function (i) { b += n(i.value); }); document.getElementById('wz-smd').textContent = a.toFixed(2).replace('.', ','); document.getElementById('wz-sd').textContent = b.toFixed(2).replace('.', ','); var ok = Math.abs(a - b) < 0.005; var p = document.getElementById('wz-ps-ok'); p.textContent = ok ? '✓ Strany sa rovnajú.' : '⚠ Rozdiel ' + (a - b).toFixed(2).replace('.', ',') + ' € – aktíva sa musia rovnať pasívam.'; p.className = ok ? 'juc-ok' : 'juc-warn'; }
		tb.addEventListener('input', sum); sum();
		document.getElementById('wz-ps-add').addEventListener('click', function () { var tr = tb.rows[tb.rows.length - 1].cloneNode(true); tr.querySelectorAll('input').forEach(function (i) { i.value = ''; }); tr.cells[1].textContent = ''; tb.appendChild(tr); });
	})();
	</script>
	<?php
}

function juc_wiz_step_6( $w, $s ) {
	global $wpdb;
	$start = $s['start_date'];
	echo '<p>Odkiaľ sa budú doklady účtovať automaticky (od ' . esc_html( juc_date( $start ) ) . '):</p><table class="widefat striped juc-table"><tbody>';
	if ( function_exists( 'jinv_table' ) ) {
		$n = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM(kind IN (\'issued\',\'creditnote\')) iss, SUM(kind = \'received\') rec FROM ' . jinv_table() . ' WHERE issue_date >= %s AND status <> \'cancelled\'', $start ), ARRAY_A ); // phpcs:ignore
		echo '<tr><th>J-Fakturácia</th><td>✓ vydané faktúry a dobropisy: ' . (int) $n['iss'] . ', prijaté faktúry: ' . (int) $n['rec'] . ' (od začiatku účtovania)</td></tr>';
		$i = 1;
		foreach ( function_exists( 'jinv_bank_accounts' ) ? jinv_bank_accounts() : array() as $id => $a ) {
			$map = (array) $s['bank_map'];
			$cur = $map[ $id ] ?? '221' . sprintf( '%03d', $i );
			echo '<tr><th>Banka – ' . esc_html( $a['name'] ?? '' ) . '</th><td>✓ pohyby sa účtujú na účet <input name="bank_map[' . esc_attr( $id ) . ']" value="' . esc_attr( $cur ) . '" style="width:90px"> ' . esc_html( ! empty( $a['iban'] ) ? jinv_iban_format( $a['iban'] ) : '' ) . ( isset( $a['balance'] ) ? ' · zostatok vo Fio ' . esc_html( juc_money( $a['balance'] ) ) . ' €' : '' ) . ( null !== ( $bat = jinv_bank_balance_at( $id, $start ) ) ? ' · <strong>zostatok k ' . esc_html( juc_date( $start ) ) . ': ' . esc_html( juc_money( $bat ) ) . ' €</strong> (počiatočný stav účtu ' . esc_html( $cur ) . ')' : '' ) . '<br><small>Fio vydá cez API pohyby za 90 dní – staršie načítate po odblokovaní histórie v internetbankingu.</small></td></tr>';
			$i++;
		}
	} else {
		echo '<tr><th>J-Fakturácia</th><td>nie je zapnutá – faktúry a banku budete účtovať ručne.</td></tr>';
	}
	if ( function_exists( 'jsk_t' ) ) {
		$val = (float) $wpdb->get_var( "SELECT COALESCE(SUM(CAST(s.meta_value AS DECIMAL(14,4)) * CAST(c.meta_value AS DECIMAL(14,4))),0) FROM {$wpdb->postmeta} s JOIN {$wpdb->postmeta} c ON c.post_id = s.post_id AND c.meta_key = '_jsk_cost' WHERE s.meta_key = '_stock' AND s.meta_value > 0" ); // phpcs:ignore
		echo '<tr><th>J-Sklad</th><td><label><input type="radio" name="stock_method" value="A" ' . checked( $s['stock_method'], 'A', false ) . '> <strong>Spôsob A</strong> – zásoby na účtoch 131/132, predaný tovar 504 mesačne podľa J-Skladu</label><br><label><input type="radio" name="stock_method" value="B" ' . checked( $s['stock_method'], 'B', false ) . '> Spôsob B – nákup tovaru priamo do nákladov 504, zásoby len pri závierke</label><br><small>Hodnota tovaru na sklade teraz (nákupné ceny): <strong>' . esc_html( juc_money( $val ) ) . ' €</strong> – pri pokračujúcej firme ju zadajte ako počiatočný stav účtu 132.</small></td></tr>';
	}
	echo '<tr><th>Pokladňa</th><td><label><input type="checkbox" name="cash" value="1" ' . checked( (bool) juc_account( '211001' ), true, false ) . '> firma používa hotovosť (účet 211)</label></td></tr>';
	echo '</tbody></table>';
}

function juc_wiz_step_7( $w, $s ) {
	$acc = function ( $k, $label ) use ( $s ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><input name="' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ) . '" list="juc-accs" style="width:100px"> <small class="juc-muted">' . esc_html( juc_account_name( $s[ $k ] ) ) . '</small></td></tr>';
	};
	echo '<p>Na ktoré účty sa účtujú doklady. Predvolené hodnoty zodpovedajú bežnej praxi e-shopu; zmeniť ich môžete aj neskôr v Nastaveniach. Analytický účet (napr. 604001) má prednosť – ak ste ho vytvorili v kroku 4, zadajte ho sem.</p><h3>Tržby a pohľadávky</h3><table class="form-table" role="presentation">';
	$acc( 'acc_sales_goods', 'Tržby za tovar' );
	$acc( 'acc_sales_virtual', 'Tržby za služby (virtuálne produkty e-shopu)' );
	$acc( 'acc_sales_serv', 'Tržby za dopravu, dobierku a poplatky' );
	$acc( 'acc_customers', 'Odberatelia' );
	$acc( 'acc_adv_recv', 'Prijaté preddavky (zálohové faktúry)' );
	echo '</table><h3>Náklady a záväzky</h3><table class="form-table" role="presentation">';
	$acc( 'acc_suppliers', 'Dodávatelia' );
	$acc( 'acc_bank_fees', 'Bankové poplatky' );
	$acc( 'acc_tx_tax', 'Transakčná daň' );
	$acc( 'acc_card', 'Platby kartou / platobná brána (peniaze na ceste)' );
	echo '</table><h3>Kategórie prijatých faktúr</h3><table class="widefat striped juc-table"><thead><tr><th>Kategória</th><th>Účet</th></tr></thead><tbody>';
	foreach ( (array) $s['cat_map'] as $cat => $a ) {
		echo '<tr><td>' . esc_html( $cat ) . '</td><td><input name="cat_map[' . esc_attr( $cat ) . ']" value="' . esc_attr( $a ) . '" list="juc-accs" style="width:100px"> <small class="juc-muted">' . esc_html( juc_account_name( $a ) ) . '</small></td></tr>';
	}
	echo '<tr><td><input name="cat_new_name" placeholder="nová kategória"></td><td><input name="cat_new_acc" list="juc-accs" style="width:100px" placeholder="účet"></td></tr>';
	echo '<tr><td><em>Ostatné / bez kategórie</em></td><td><input name="cat_default" value="' . esc_attr( $s['cat_default'] ) . '" list="juc-accs" style="width:100px"></td></tr></tbody></table>';
	juc_accounts_datalist();
}

/** Kontroly pred spustením: [ [ok|warn|err, text], … ]. */
function juc_wizard_checks() {
	global $wpdb;
	$s   = juc_settings();
	$w   = juc_wiz();
	$out = array();
	$out[] = $s['company'] && $s['ico'] ? array( 'ok', 'Firma: ' . $s['company'] . ', IČO ' . $s['ico'] ) : array( 'err', 'Chýba obchodné meno alebo IČO (krok 1).' );
	$out[] = $s['start_date'] ? array( 'ok', 'Začiatok účtovania: ' . juc_date( $s['start_date'] ) ) : array( 'err', 'Chýba dátum začiatku účtovania (krok 3).' );
	if ( 'new' === $w['mode'] ) {
		$out[] = (float) $w['zi'] > 0 ? array( 'ok', 'Základné imanie ' . juc_money( $w['zi'] ) . ' €' . ( (float) $w['zi_paid'] > 0 ? ', splatené ' . juc_money( $w['zi_paid'] ) . ' €' : '' ) ) : array( 'warn', 'Základné imanie nie je zadané (krok 5).' );
	} else {
		$md = 0.0;
		$d  = 0.0;
		foreach ( (array) $w['ps'] as $v ) {
			$md += (float) str_replace( ',', '.', (string) $v['md'] );
			$d  += (float) str_replace( ',', '.', (string) $v['d'] );
		}
		$out[] = ! $w['ps'] || ( $md < 0.005 && $d < 0.005 ) ? array( 'warn', 'Počiatočné stavy nie sú zadané – zadáte ich po uzávierke minulého obdobia (krok 5, aj po spustení).' ) : ( abs( $md - $d ) < 0.005 ? array( 'ok', 'Počiatočné stavy: aktíva = pasíva = ' . juc_money( $md ) . ' €' ) : array( 'err', 'Počiatočné stavy sa nerovnajú: MD ' . juc_money( $md ) . ' €, Dal ' . juc_money( $d ) . ' € (krok 5).' ) );
	}
	if ( function_exists( 'jinv_table' ) && $s['start_date'] ) {
		$st   = $s['start_date'];
		$open = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jinv_table() . " WHERE kind = 'received' AND status = 'issued' AND issue_date >= %s", $st ) ); // phpcs:ignore
		$out[] = array( 'ok', sprintf( 'Doklady od začiatku účtovania: vydané %d, prijaté %d (neuhradené %d)', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jinv_table() . " WHERE kind IN ('issued','creditnote') AND issue_date >= %s AND status <> 'cancelled'", $st ) ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jinv_table() . " WHERE kind = 'received' AND issue_date >= %s AND status <> 'cancelled'", $st ) ), $open ) ); // phpcs:ignore
		if ( function_exists( 'jinv_bank_tx' ) ) {
			$nb    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jinv_table( 'banktx' ) . ' WHERE tx_date >= %s', $st ) ); // phpcs:ignore
			$nn    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jinv_table( 'banktx' ) . " WHERE tx_date >= %s AND status = 'new'", $st ) ); // phpcs:ignore
			$out[] = array( $nn ? 'warn' : 'ok', sprintf( 'Pohyby v banke od začiatku: %d, z toho nespárovaných %d – po spustení ich zaúčtujete v „Na zaúčtovanie“ alebo spárujete v J-Fakturácia → Banka.', $nb, $nn ) );
		}
		$test = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . jinv_table() . " WHERE issue_date >= %s AND (number LIKE %s OR customer_name LIKE %s)", $st, '%test%', '%test%' ) ); // phpcs:ignore
		if ( $test ) {
			$out[] = array( 'warn', $test . ' dokladov vyzerá ako skúšobné (obsahujú „test“) – pred spustením ich zmažte alebo stornujte.' );
		}
	}
	$y = $s['start_date'] ? juc_year_of( $s['start_date'] ) : null;
	if ( $y ) {
		$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . juc_t( 'entries' ) . ' WHERE year_id = %d', $y['id'] ) ); // phpcs:ignore
		if ( $n ) {
			$out[] = array( 'warn', 'V období už je ' . $n . ' účtovných dokladov – spustenie doplní len chýbajúce.' );
		}
	}
	return $out;
}

function juc_wiz_step_8( $w, $s ) {
	echo '<p>Kontrola pred založením. Chyby (✕) treba opraviť, upozornenia (!) skontrolujte.</p><ul class="juc-checks">';
	foreach ( juc_wizard_checks() as $c ) {
		echo '<li class="juc-c-' . esc_attr( $c[0] ) . '">' . ( 'ok' === $c[0] ? '✓' : ( 'warn' === $c[0] ? '!' : '✕' ) ) . ' ' . esc_html( $c[1] ) . '</li>';
	}
	echo '</ul><p class="description">Doklady za obdobie pred začiatkom účtovania sa nezaúčtujú. Doplniť ich môžete kedykoľvek neskôr.</p>';
}

function juc_wiz_step_9( $w, $s ) {
	$errs = array_filter( juc_wizard_checks(), fn( $c ) => 'err' === $c[0] );
	if ( get_option( 'juc_setup_done' ) ) {
		$res = (array) get_option( 'juc_autopost_last', array() );
		echo '<p class="juc-ok">✓ Účtovníctvo je založené ' . esc_html( date_i18n( 'j. n. Y H:i', strtotime( (string) get_option( 'juc_setup_done' ) ) ) ) . '. Automatické účtovanie beží.</p>';
		if ( $res ) {
			echo '<p>Posledné účtovanie: nové ' . (int) ( $res['posted'] ?? 0 ) . ', aktualizované ' . (int) ( $res['updated'] ?? 0 ) . ', chyby ' . count( (array) ( $res['errors'] ?? array() ) ) . '.</p>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( juc_url( 'j-uctovnictvo' ) ) . '">Prehľad účtovníctva</a> <a class="button" href="' . esc_url( juc_url( 'j-uctovnictvo-trial' ) ) . '">Obratová predvaha</a> <a class="button" href="' . esc_url( juc_url( 'j-uctovnictvo-queue' ) ) . '">Na zaúčtovanie</a></p>';
		return;
	}
	echo '<p>Spustenie:</p><ol><li>otvorí účtové obdobie,</li><li>vytvorí vybrané analytické účty,</li><li>zaúčtuje počiatočné stavy (doklady PS),</li><li>zapne automatické účtovanie a zaúčtuje doklady od ' . esc_html( juc_date( $s['start_date'] ) ) . ' (ak už sú).</li></ol>';
	if ( $errs ) {
		echo '<p class="juc-warn">Najprv opravte chyby z kroku 8.</p>';
		return;
	}
	echo '<p><button class="button button-primary button-hero" name="launch" value="1">Založiť účtovníctvo</button></p>';
}

/* ---------------------------------------------------------------- Uloženie krokov */

add_action( 'admin_post_juc_wizard', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_wizard' );
	$in   = wp_unslash( $_POST );
	$step = (int) ( $in['step'] ?? 1 );
	$w    = juc_wiz();
	$set  = array();
	$t    = fn( $k ) => sanitize_text_field( (string) ( $in[ $k ] ?? '' ) );
	$date = fn( $k ) => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in[ $k ] ?? '' ) ) ? $in[ $k ] : '';
	$acc  = fn( $v ) => preg_replace( '/\D/', '', (string) $v );
	switch ( $step ) {
		case 1:
			foreach ( array( 'company', 'ico', 'dic', 'icdph', 'street', 'zip', 'city', 'register', 'statutory' ) as $k ) {
				$set[ $k ] = $t( $k );
			}
			$set['founded'] = $date( 'founded' );
			break;
		case 2:
			$set = array( 'size' => 'small' === ( $in['size'] ?? '' ) ? 'small' : 'micro', 'vat_payer' => ! empty( $in['vat_payer'] ) ? 1 : 0, 'vat_from' => $date( 'vat_from' ), 'vat_period' => 'month' === ( $in['vat_period'] ?? '' ) ? 'month' : 'quarter' );
			break;
		case 3:
			$set = array( 'start_date' => $date( 'start_date' ) );
			$w['mode'] = 'continue' === ( $in['mode'] ?? '' ) ? 'continue' : 'new';
			if ( $set['start_date'] ) {
				juc_ensure_year( (int) substr( $set['start_date'], 0, 4 ) );
			}
			break;
		case 4:
			$names = (array) ( $in['an_name'] ?? array() );
			foreach ( array_keys( (array) ( $in['an'] ?? array() ) ) as $num ) {
				$num = $acc( $num );
				if ( $num && ! juc_account( $num ) ) {
					juc_account_ensure( $num, sanitize_text_field( $names[ $num ] ?? '' ) );
				}
			}
			break;
		case 5:
			if ( 'new' === $w['mode'] ) {
				foreach ( array( 'zi', 'zi_paid' ) as $k ) {
					$w[ $k ] = str_replace( array( ' ', ',' ), array( '', '.' ), $t( $k ) );
				}
				$w['zi_date']      = $date( 'zi_date' );
				$w['zi_paid_date'] = $date( 'zi_paid_date' );
				$w['zi_paid_to']   = $acc( $in['zi_paid_to'] ?? '221001' );
			} else {
				$ps = array();
				foreach ( (array) ( $in['ps_acc'] ?? array() ) as $i => $a ) {
					$a = $acc( $a );
					if ( $a ) {
						$ps[ $a ] = array( 'md' => str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['ps_md'][ $i ] ?? '' ) ), 'd' => str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['ps_d'][ $i ] ?? '' ) ) );
					}
				}
				$w['ps'] = $ps;
				$sal     = array();
				foreach ( (array) ( $in['sal_acc'] ?? array() ) as $i => $a ) {
					$amt = round( (float) str_replace( array( ' ', ',' ), array( '', '.' ), (string) ( $in['sal_amount'][ $i ] ?? '' ) ), 2 );
					if ( abs( $amt ) >= 0.005 && in_array( $a, array( '311', '321' ), true ) ) {
						$sal[] = array( 'acc' => $a, 'partner' => sanitize_text_field( $in['sal_partner'][ $i ] ?? '' ), 'vs' => preg_replace( '/\D/', '', (string) ( $in['sal_vs'][ $i ] ?? '' ) ), 'amount' => $amt );
					}
				}
				$w['ps_saldo'] = $sal;
				if ( ! empty( $in['post_ps'] ) ) {
					update_option( 'juc_wizard', $w, false );
					$r = juc_wizard_post_ps();
					if ( is_wp_error( $r ) ) {
						juc_back_err( juc_wiz_url( 5 ), $r->get_error_message() );
					}
					wp_safe_redirect( add_query_arg( 'juc_msg', 'posted', juc_wiz_url( 5 ) ) );
					exit;
				}
			}
			break;
		case 6:
			$map = array();
			foreach ( (array) ( $in['bank_map'] ?? array() ) as $id => $a ) {
				if ( $acc( $a ) ) {
					$map[ sanitize_key( $id ) ] = $acc( $a );
					juc_account_ensure( $acc( $a ) );
				}
			}
			$set = array( 'bank_map' => $map, 'stock_method' => 'B' === ( $in['stock_method'] ?? 'A' ) ? 'B' : 'A' );
			if ( ! empty( $in['cash'] ) ) {
				juc_account_ensure( '211001', 'Pokladňa' );
				$set['acc_cash'] = '211001';
			}
			break;
		case 7:
			foreach ( array( 'acc_sales_goods', 'acc_sales_virtual', 'acc_sales_serv', 'acc_customers', 'acc_adv_recv', 'acc_suppliers', 'acc_bank_fees', 'acc_tx_tax', 'acc_card', 'cat_default' ) as $k ) {
				if ( $acc( $in[ $k ] ?? '' ) ) {
					$set[ $k ] = $acc( $in[ $k ] );
				}
			}
			$cm = array();
			foreach ( (array) ( $in['cat_map'] ?? array() ) as $c => $a ) {
				if ( $acc( $a ) ) {
					$cm[ sanitize_text_field( $c ) ] = $acc( $a );
				}
			}
			if ( $t( 'cat_new_name' ) && $acc( $in['cat_new_acc'] ?? '' ) ) {
				$cm[ $t( 'cat_new_name' ) ] = $acc( $in['cat_new_acc'] );
			}
			$set['cat_map'] = $cm;
			break;
		case 9:
			if ( ! empty( $in['launch'] ) ) {
				$r = juc_wizard_launch();
				if ( is_wp_error( $r ) ) {
					juc_back_err( juc_wiz_url( 9 ), $r->get_error_message() );
				}
				wp_safe_redirect( juc_wiz_url( 9 ) );
				exit;
			}
			break;
	}
	if ( $set ) {
		juc_settings_update( $set );
	}
	$w['done'] = array_values( array_unique( array_merge( (array) $w['done'], array( $step ) ) ) );
	$w['step'] = min( 9, $step + 1 );
	update_option( 'juc_wizard', $w, false );
	wp_safe_redirect( juc_wiz_url( $w['step'] ) );
	exit;
} );

/** Založenie: počiatočné stavy, zapnutie automatického účtovania a prvé zaúčtovanie. */
function juc_wizard_launch() {
	$s     = juc_settings();
	$w     = juc_wiz();
	$start = $s['start_date'];
	juc_ensure_year( (int) substr( $start, 0, 4 ) );
	if ( 'new' === $w['mode'] ) {
		if ( (float) $w['zi'] > 0 && ! juc_entry_by_source( 'wizard', 'zi', 1 ) ) {
			$r = juc_post( array( 'series' => 'ID', 'entry_date' => $w['zi_date'] ? $w['zi_date'] : $start, 'descr' => 'Upísané základné imanie – vznik spoločnosti', 'source' => 'wizard', 'source_type' => 'zi', 'source_id' => 1 ), array( array( 'md' => '353', 'd' => '411', 'amount' => (float) $w['zi'], 'text' => 'Pohľadávka voči spoločníkom za upísané základné imanie' ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( (float) $w['zi_paid'] > 0 && ! juc_entry_by_source( 'wizard', 'zi_paid', 1 ) ) {
			$r = juc_post( array( 'series' => 'ID', 'entry_date' => $w['zi_paid_date'] ? $w['zi_paid_date'] : $start, 'descr' => 'Splatenie základného imania', 'source' => 'wizard', 'source_type' => 'zi_paid', 'source_id' => 1 ), array( array( 'md' => $w['zi_paid_to'] ? $w['zi_paid_to'] : '221', 'd' => '353', 'amount' => (float) $w['zi_paid'], 'text' => 'Splatenie vkladu spoločníka' ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
	} else {
		$r = juc_wizard_post_ps();
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	juc_settings_update( array( 'auto' => 1 ) );
	update_option( 'juc_setup_done', current_time( 'mysql' ), false );
	if ( ! defined( 'JUC_FORCE_RUN' ) ) {
		define( 'JUC_FORCE_RUN', true );
	}
	juc_autopost_run( 5000 ); // chyby si beh ukladá sám (zlúčené s predchádzajúcimi)
	return true;
}

/**
 * Počiatočné stavy pokračujúcej firmy (otvorenie kníh proti 701). Dá sa zaúčtovať aj po založení – keď sú známe
 * konečné zostatky minulého obdobia. Ak už boli zaúčtované, starý doklad PS sa stornuje a zaúčtuje znova.
 * Vráti ID dokladu, 0 (nič na zaúčtovanie) alebo WP_Error.
 */
function juc_wizard_post_ps() {
	$s     = juc_settings();
	$w     = juc_wiz();
	$start = $s['start_date'];
	if ( ! $start || 'continue' !== $w['mode'] || ! $w['ps'] ) {
		return 0;
	}
	$lines = array();
	$md    = 0.0;
	$d     = 0.0;
	foreach ( $w['ps'] as $a => $v ) {
		$vm = round( (float) $v['md'], 2 );
		$vd = round( (float) $v['d'], 2 );
		if ( $vm ) {
			$lines[] = array( 'md' => (string) $a, 'd' => '701', 'amount' => $vm, 'text' => 'Počiatočný stav ' . $a );
			$md     += $vm;
		}
		if ( $vd ) {
			$lines[] = array( 'md' => '701', 'd' => (string) $a, 'amount' => $vd, 'text' => 'Počiatočný stav ' . $a );
			$d      += $vd;
		}
	}
	if ( ! $lines ) {
		return 0;
	}
	if ( abs( $md - $d ) >= 0.005 ) {
		return new WP_Error( 'juc_ps', sprintf( 'Počiatočné stavy sa nerovnajú: MD %s €, Dal %s € – aktíva sa musia rovnať pasívam.', juc_money( $md ), juc_money( $d ) ) );
	}
	// Rozpis 311 / 321 po faktúrach (saldokonto): nahradí jednu sumu účtu riadkami s partnerom a VS.
	$sal = (array) ( $w['ps_saldo'] ?? array() );
	foreach ( array( '311' => 'md', '321' => 'd' ) as $sa => $side ) {
		$rows = array_values( array_filter( $sal, fn( $r ) => $r['acc'] === $sa ) );
		if ( ! $rows ) {
			continue;
		}
		$want = 0.0;
		foreach ( $lines as $i => $l ) {
			if ( ( 'md' === $side ? $l['md'] : $l['d'] ) === $sa ) {
				$want += (float) $l['amount'];
			}
		}
		$sum = round( array_sum( wp_list_pluck( $rows, 'amount' ) ), 2 );
		if ( abs( $sum - round( $want, 2 ) ) >= 0.005 ) {
			return new WP_Error( 'juc_ps', sprintf( 'Rozpis faktúr na účte %s (%s €) sa nerovná jeho počiatočnému stavu (%s €).', $sa, juc_money( $sum ), juc_money( $want ) ) );
		}
		$lines = array_values( array_filter( $lines, fn( $l ) => ( 'md' === $side ? $l['md'] : $l['d'] ) !== $sa ) );
		foreach ( $rows as $r ) {
			$pid     = function_exists( 'jinv_partner_match' ) && '' !== $r['partner'] ? (int) jinv_partner_match( $r['partner'] ) : 0;
			$text    = 'Počiatočný stav ' . $sa . ' – ' . trim( $r['partner'] . ( $r['vs'] ? ' (VS ' . $r['vs'] . ')' : '' ) );
			$lines[] = 'md' === $side
				? array( 'md' => $sa, 'd' => '701', 'amount' => $r['amount'], 'text' => $text, 'partner_id' => $pid, 'vs' => $r['vs'] )
				: array( 'md' => '701', 'd' => $sa, 'amount' => $r['amount'], 'text' => $text, 'partner_id' => $pid, 'vs' => $r['vs'] );
		}
	}
	$yr = (int) substr( $start, 0, 4 );
	// Najprv overiť, že sa k dátumu začiatku dá účtovať – inak by storno pristálo v inom dni a nové stavy by sa nezaúčtovali.
	$ok = juc_can_post( $start );
	if ( is_wp_error( $ok ) ) {
		return new WP_Error( 'juc_ps', 'Počiatočné stavy k ' . juc_date( $start ) . ' sa nedajú zaúčtovať ani opraviť – obdobie je uzavreté (zrušte uzávierku mesiaca alebo roka a skúste znova). ' . $ok->get_error_message() );
	}
	$old  = juc_entry_by_source( 'wizard', 'ps', $yr );
	$prev =$old ? juc_entry( (int) $old['id'] ) : null;
	if ( $old ) {
		$r = juc_storno( (int) $old['id'], 'oprava počiatočných stavov', $start );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	$head = array( 'series' => 'PS', 'number' => 'PS' . $yr . ( $old ? '-' . substr( md5( microtime() ), 0, 4 ) : '' ), 'entry_date' => $start, 'descr' => 'Otvorenie účtovných kníh – počiatočné stavy', 'source' => 'wizard', 'source_type' => 'ps', 'source_id' => $yr, 'opening' => 1 );
	$r    = juc_post( $head, $lines );
	if ( is_wp_error( $r ) && $prev ) {
		// Obnovenie pôvodných počiatočných stavov, aby sa po neúspešnej oprave nestratili.
		$back = array();
		foreach ( (array) $prev['lines'] as $l ) {
			$back[] = array( 'md' => $l['md'], 'd' => $l['d'], 'amount' => (float) $l['amount'], 'text' => $l['text'], 'partner_id' => (int) $l['partner_id'], 'vs' => $l['vs'] );
		}
		$rr = juc_post( array( 'number' => 'PS' . $yr . '-' . substr( md5( microtime() . 'r' ), 0, 4 ), 'descr' => 'Obnovenie počiatočných stavov (neúspešná oprava)' ) + $head, $back );
		return new WP_Error( 'juc_ps', 'Nové počiatočné stavy sa nepodarilo zaúčtovať: ' . $r->get_error_message() . ( is_wp_error( $rr ) ? ' Pôvodné stavy sa nepodarilo obnoviť: ' . $rr->get_error_message() . ' – zadajte ich ručne.' : ' Pôvodné počiatočné stavy boli obnovené.' ) );
	}
	return $r;
}
