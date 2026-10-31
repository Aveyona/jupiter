<?php
/** Nastavenia J-Účtovníctva: účtovná jednotka, DPH, uzávierka, účty pre automatické účtovanie, predkontácie. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function juc_defaults() {
	return array(
		'company'        => '',
		'ico'            => '',
		'dic'            => '',
		'icdph'          => '',
		'street'         => '',
		'zip'            => '',
		'city'           => '',
		'register'       => '',
		'founded'        => '',
		'statutory'      => '',
		'size'           => 'micro',      // micro = mikro účtovná jednotka (Úč MUJ), small = malá (Úč POD)
		'vat_payer'      => 0,            // platiteľ DPH
		'vat_period'     => 'quarter',    // month | quarter
		'vat_from'       => '',           // platiteľ DPH od dátumu
		'start_date'     => '',           // automaticky účtovať doklady od tohto dátumu (napr. začiatok obdobia)
		'lock_date'      => '',           // obdobie do tohto dňa vrátane je uzavreté (nemenné)
		'auto'           => 1,            // automatické účtovanie zapnuté
		'stock_method'   => 'A',          // A = zásoby cez 131/132 (s J-Skladom), B = tovar priamo do 504
		'format_ID'      => 'ID{RRRR}{nnnn}',
		'format_PD'      => 'PD{RRRR}{nnnn}',
		'format_VD'      => 'VD{RRRR}{nnnn}',
		// Účty pre automatické účtovanie.
		'acc_customers'  => '311',
		'acc_suppliers'  => '321',
		'acc_bank'       => '221',
		'acc_cash'       => '211',
		'acc_card'       => '261',
		'acc_cod'        => '',           // dobierky (Packeta) – prázdne = 261002 pri analytike karty, inak ako karta
		'acc_transit'    => '261',
		'acc_adv_recv'   => '324',
		'acc_sales_goods' => '604',
		'acc_sales_serv' => '602',
		'acc_sales_virtual' => '602',   // služby predávané ako virtuálne produkty
		'acc_goods_buy'  => '131',
		'acc_goods'      => '132',
		'acc_cogs'       => '504',
		'acc_inv_minus'  => '549',
		'acc_inv_plus'   => '648',
		'acc_bank_fees'  => '568',
		'acc_tx_tax'     => '538',
		'acc_interest'   => '662',
		'acc_rounding'   => '548',
		'cat_map'        => array(
			'Tovar'              => '131',
			'Materiál'           => '501',
			'Doprava a poštovné' => '518',
			'Služby'             => '518',
			'Hosting a softvér'  => '518',
			'Reklama'            => '518',
			'Kancelária'         => '501',
			'Nájom a energie'    => '518',
			'Telefón a internet' => '518',
			'Poplatky'           => '568',
			'Majetok'            => '042',
		),
		'cat_default'    => '518',
		'issue_map'      => array(),      // dôvod výdajky J-Skladu => účet (predvolene 549 / 501)
		'bank_map'       => array(),      // ID účtu v J-Fakturácii => analytický účet 221xxx
		'bank_rules'     => array(),      // protiúčet (IBAN) => účet, napr. súkromný účet konateľa => 355001
	);
}

function juc_settings() {
	$s = wp_parse_args( (array) get_option( 'juc_settings', array() ), juc_defaults() );
	// Údaje firmy z J-Fakturácie, ak nie sú vyplnené.
	if ( '' === $s['company'] && function_exists( 'jinv_settings' ) ) {
		$j            = jinv_settings();
		$s['company'] = (string) ( $j['company'] ?? '' );
		$s['ico']     = $s['ico'] ? $s['ico'] : (string) ( $j['ico'] ?? '' );
		$s['dic']     = $s['dic'] ? $s['dic'] : (string) ( $j['dic'] ?? '' );
	}
	return $s;
}

function juc_opt( $k ) {
	$s = juc_settings();
	return $s[ $k ] ?? null;
}

function juc_settings_update( array $vals ) {
	$out = wp_parse_args( (array) get_option( 'juc_settings', array() ), juc_defaults() );
	update_option( 'juc_settings', array_merge( $out, $vals ), false );
}

/** Predkontácie pre ručné zápisy (kód => [názov, MD, Dal]). Dajú sa rozšíriť filtrom alebo v Nastaveniach. */
function juc_templates() {
	$def = array(
		'PS-A'   => array( 'Počiatočný stav – aktívny účet', '', '701' ),
		'PS-P'   => array( 'Počiatočný stav – pasívny účet', '701', '' ),
		'ZI'     => array( 'Upísané základné imanie (pohľadávka voči spoločníkom)', '353', '411' ),
		'ZI-SPL' => array( 'Splatenie základného imania na účet', '221', '353' ),
		'BV-POP' => array( 'Bankový poplatok', '568', '221' ),
		'BV-TD'  => array( 'Transakčná daň', '538', '221' ),
		'BV-UR'  => array( 'Prijatý úrok', '221', '662' ),
		'BV-PK'  => array( 'Výber z banky do pokladne (peniaze na ceste)', '261', '221' ),
		'PD-PK'  => array( 'Príjem do pokladne z banky', '211', '261' ),
		'PD-VYD' => array( 'Výdavok z pokladne – služba', '518', '211' ),
		'PD-VKL' => array( 'Vklad hotovosti do pokladne – vrátenie peňazí od spoločníka / konateľa', '211', '355' ),
		'ID-HOT' => array( 'Hotovosť mimo pokladne – pohľadávka voči spoločníkovi / konateľovi', '355', '211' ),
		'PD-BNK' => array( 'Odvod hotovosti z pokladne na bankový účet (peniaze na ceste)', '261', '211' ),
		'BV-VKL' => array( 'Vklad hotovosti na bankový účet (pripísanie)', '221', '261' ),
		'PD-MAT' => array( 'Výdavok z pokladne – drobný materiál', '501', '211' ),
		'PD-PHM' => array( 'Výdavok z pokladne – pohonné hmoty', '501', '211' ),
		'SP-POZ' => array( 'Pôžička od spoločníka na účet', '221', '365' ),
		'KO-SPL' => array( 'Splátka pohľadávky od konateľa / spoločníka na účet', '221', '355' ),
		'KO-VYP' => array( 'Výplata konateľovi / spoločníkovi z účtu (pohľadávka)', '355', '221' ),
		'SP-VR'  => array( 'Vrátenie pôžičky spoločníkovi', '365', '221' ),
		'FP-SL'  => array( 'Prijatá faktúra – služby', '518', '321' ),
		'FP-TOV' => array( 'Prijatá faktúra – tovar (obstaranie)', '131', '321' ),
		'FV-TOV' => array( 'Vydaná faktúra – tovar', '311', '604' ),
		'FV-SL'  => array( 'Vydaná faktúra – služby', '311', '602' ),
		'ODP'    => array( 'Odpis dlhodobého hmotného majetku', '551', '082' ),
		'DP-PO'  => array( 'Daň z príjmov – splatná', '591', '341' ),
		'VH'     => array( 'Rozdelenie výsledku hospodárenia – nerozdelený zisk', '431', '428' ),
	);
	$own = (array) get_option( 'juc_templates', array() );
	return apply_filters( 'juc_templates', array_merge( $def, $own ) );
}

/** Číslo dokladu podľa formátu série. */
function juc_format_number( $format, $year, $seq ) {
	return strtr( $format, array(
		'{RRRR}'  => sprintf( '%04d', $year ),
		'{RR}'    => substr( sprintf( '%04d', $year ), 2 ),
		'{nnnnn}' => sprintf( '%05d', $seq ),
		'{nnnn}'  => sprintf( '%04d', $seq ),
		'{nnn}'   => sprintf( '%03d', $seq ),
	) );
}

/** Série účtovných dokladov. */
function juc_series() {
	return array(
		'OF' => 'Vydané faktúry',
		'OD' => 'Dobropisy vydané',
		'DF' => 'Prijaté faktúry',
		'BV' => 'Banka',
		'PD' => 'Pokladňa – príjmy',
		'VD' => 'Pokladňa – výdavky',
		'MA' => 'Majetok – zaradenie, odpisy, vyradenie',
		'MZ' => 'Mzdy',
		'SK' => 'Sklad',
		'ID' => 'Interné doklady',
		'PS' => 'Počiatočné stavy',
		'UZ' => 'Uzávierka',
	);
}

/* ---------------------------------------------------------------- Stránka Nastavenia */

function juc_page_settings() {
	if ( ! current_user_can( JUC_CAP ) ) {
		return;
	}
	$s = juc_settings();
	juc_head( 'Nastavenia účtovníctva' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="juc-panel">
		<?php wp_nonce_field( 'juc_settings' ); ?><input type="hidden" name="action" value="juc_settings">
		<h3>Účtovná jednotka</h3>
		<p><?php echo esc_html( $s['company'] . ( $s['ico'] ? ', IČO ' . $s['ico'] : '' ) . ( $s['dic'] ? ', DIČ ' . $s['dic'] : '' ) ); ?> · <?php echo 'micro' === $s['size'] ? 'mikro účtovná jednotka' : 'malá účtovná jednotka'; ?> · <?php echo $s['vat_payer'] ? 'platiteľ DPH' : 'neplatiteľ DPH'; ?> – <a href="<?php echo esc_url( juc_url( 'j-uctovnictvo-wizard', array( 'step' => 1 ) ) ); ?>">zmeniť v sprievodcovi</a></p>
		<h3>Účtovanie</h3>
		<table class="form-table" role="presentation">
			<tr><th><label for="js-start">Automaticky účtovať doklady od</label></th><td><input type="date" id="js-start" name="start_date" value="<?php echo esc_attr( $s['start_date'] ); ?>"> <label><input type="checkbox" name="auto" value="1" <?php checked( (int) $s['auto'] ); ?>> automatické účtovanie zapnuté</label><p class="description">Posunutím dátumu dozadu sa doplnia staršie doklady (nič sa nezdvojí).</p></td></tr>
			<tr><th><label for="js-lock">Účtovníctvo uzavreté do</label></th><td><input type="date" id="js-lock" name="lock_date" value="<?php echo esc_attr( $s['lock_date'] ); ?>"><p class="description">Do tohto dňa (vrátane) sa už nedá účtovať ani stornovať – uzávierka mesiaca po kontrole.</p></td></tr>
			<tr><th>Zásoby</th><td><select name="stock_method"><option value="A" <?php selected( $s['stock_method'], 'A' ); ?>>Spôsob A – 131/132, predaj 504 podľa J-Skladu</option><option value="B" <?php selected( $s['stock_method'], 'B' ); ?>>Spôsob B – nákup tovaru priamo do 504</option></select></td></tr>
			<tr><th>Číslovanie</th><td>Interné doklady <input name="format_ID" value="<?php echo esc_attr( $s['format_ID'] ); ?>" style="width:150px"> Pokladňa <input name="format_PD" value="<?php echo esc_attr( $s['format_PD'] ); ?>" style="width:150px"><p class="description">{RRRR} rok, {nnnn} poradové číslo v roku.</p></td></tr>
		</table>
		<h3>Účty pre automatické účtovanie</h3>
		<table class="form-table" role="presentation">
			<?php
			$labels = array(
				'acc_customers' => 'Odberatelia', 'acc_suppliers' => 'Dodávatelia', 'acc_bank' => 'Banka (syntetický)', 'acc_cash' => 'Pokladňa', 'acc_card' => 'Karta / platobná brána', 'acc_cod' => 'Dobierky – peniaze na ceste (prázdne = automaticky)', 'acc_adv_recv' => 'Prijaté preddavky',
				'acc_sales_goods' => 'Tržby za tovar', 'acc_sales_virtual' => 'Tržby za služby (virtuálne produkty)', 'acc_sales_serv' => 'Tržby za dopravu a poplatky', 'acc_goods_buy' => 'Obstaranie tovaru', 'acc_goods' => 'Tovar na sklade', 'acc_cogs' => 'Predaný tovar',
				'acc_inv_minus' => 'Manko / škoda', 'acc_inv_plus' => 'Inventúrny prebytok', 'acc_bank_fees' => 'Bankové poplatky', 'acc_tx_tax' => 'Transakčná daň', 'acc_interest' => 'Úroky (výnos)', 'cat_default' => 'Prijaté faktúry bez kategórie',
			);
			foreach ( $labels as $k => $l ) :
				?>
				<tr><th><?php echo esc_html( $l ); ?></th><td><input name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $s[ $k ] ); ?>" list="juc-accs" style="width:100px"> <small class="juc-muted"><?php echo esc_html( juc_account_name( $s[ $k ] ) ); ?></small></td></tr>
			<?php endforeach; ?>
		</table>
		<?php juc_accounts_datalist(); ?>
		<h2>Pravidlá pre pohyby v banke</h2>
		<p class="description">Pohyby, ktoré zodpovedajú pravidlu, sa zaúčtujú automaticky na zadaný účet. Jeden riadok = <code>IBAN = účet</code> (napr. súkromný účet konateľa → 355001), <code>názov: Google = 518</code> (názov protistrany obsahuje text) alebo <code>text: nákup: google = 518</code> (správa alebo typ pohybu obsahuje text – napr. nákupy kartou bez IBAN); voliteľne <code># poznámka</code>. Pravidlo vytvorí aj voľba „zapamätať“ v Na zaúčtovanie.</p>
		<textarea name="bank_rules" rows="5" class="large-text code"><?php foreach ( (array) $s['bank_rules'] as $iban => $acc ) { $iban = (string) $iban; echo esc_textarea( ( 0 === strpos( $iban, 'n:' ) ? 'názov: ' . substr( $iban, 2 ) : ( 0 === strpos( $iban, 't:' ) ? 'text: ' . substr( $iban, 2 ) : $iban ) ) . ' = ' . $acc ) . "\n"; } ?></textarea>
		<p class="description">Kategórie prijatých faktúr a bankové účty sa nastavujú v sprievodcovi (kroky 6 a 7).</p>
		<p><button class="button button-primary">Uložiť</button></p>
	</form>
	</div>
	<?php
}

add_action( 'admin_post_juc_settings', function () {
	if ( ! current_user_can( JUC_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'juc_settings' );
	$in  = wp_unslash( $_POST );
	$set = array(
		'start_date'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['start_date'] ?? '' ) ) ? $in['start_date'] : '',
		'lock_date'    => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $in['lock_date'] ?? '' ) ) ? $in['lock_date'] : '',
		'auto'         => ! empty( $in['auto'] ) ? 1 : 0,
		'stock_method' => 'B' === ( $in['stock_method'] ?? '' ) ? 'B' : 'A',
		'format_ID'    => false !== strpos( (string) ( $in['format_ID'] ?? '' ), '{n' ) ? sanitize_text_field( $in['format_ID'] ) : 'ID{RRRR}{nnnn}',
		'format_PD'    => false !== strpos( (string) ( $in['format_PD'] ?? '' ), '{n' ) ? sanitize_text_field( $in['format_PD'] ) : 'PD{RRRR}{nnnn}',
	);
	$rules = array();
	foreach ( preg_split( '/\R/', (string) ( $in['bank_rules'] ?? '' ) ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( preg_match( '/^(názov|nazov|text)\s*:\s*(.+?)\s*=\s*(\d{3,9})$/iu', $line, $m ) ) {
			$rules[ ( 'text' === mb_strtolower( $m[1] ) ? 't:' : 'n:' ) . mb_strtolower( trim( $m[2] ) ) ] = $m[3];
		} elseif ( preg_match( '/^([A-Za-z]{2}[0-9A-Za-z ]{10,40})\s*=\s*(\d{3,9})$/', $line, $m ) ) {
			$rules[ strtoupper( str_replace( ' ', '', $m[1] ) ) ] = $m[2];
		}
	}
	$set['bank_rules'] = $rules;
	foreach ( array_keys( juc_defaults() ) as $k ) {
		if ( ( 0 === strpos( $k, 'acc_' ) || 'cat_default' === $k ) && isset( $in[ $k ] ) && preg_replace( '/\D/', '', $in[ $k ] ) ) {
			$set[ $k ] = preg_replace( '/\D/', '', $in[ $k ] );
		}
	}
	juc_settings_update( $set );
	wp_safe_redirect( juc_url( 'j-uctovnictvo-settings', array( 'juc_msg' => 'saved' ) ) );
	exit;
} );
