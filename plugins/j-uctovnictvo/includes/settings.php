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
		// Účty pre automatické účtovanie.
		'acc_customers'  => '311',
		'acc_suppliers'  => '321',
		'acc_bank'       => '221',
		'acc_cash'       => '211',
		'acc_card'       => '261',
		'acc_transit'    => '261',
		'acc_adv_recv'   => '324',
		'acc_sales_goods' => '604',
		'acc_sales_serv' => '602',
		'acc_goods_buy'  => '131',
		'acc_goods'      => '132',
		'acc_cogs'       => '504',
		'acc_inv_minus'  => '549',
		'acc_inv_plus'   => '504',
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
		),
		'cat_default'    => '518',
		'issue_map'      => array(),      // dôvod výdajky J-Skladu => účet (predvolene 549 / 501)
		'bank_map'       => array(),      // ID účtu v J-Fakturácii => analytický účet 221xxx
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
		'PD-MAT' => array( 'Výdavok z pokladne – drobný materiál', '501', '211' ),
		'PD-PHM' => array( 'Výdavok z pokladne – pohonné hmoty', '501', '211' ),
		'SP-POZ' => array( 'Pôžička od spoločníka na účet', '221', '365' ),
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
		'PD' => 'Pokladňa',
		'SK' => 'Sklad',
		'ID' => 'Interné doklady',
		'PS' => 'Počiatočné stavy',
		'UZ' => 'Uzávierka',
	);
}
