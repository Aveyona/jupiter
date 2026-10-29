<?php
/**
 * Export pre účtovníka (J-Fakturácia → Export): doklady za obdobie ako
 *  - CSV (Excel, oddeľovač bodkočiarka, UTF-8 s BOM),
 *  - XML na import do účtovníctva Pohoda (dataPack 2.0 – vydané faktúry, zálohové faktúry, dobropisy),
 *  - ZIP so všetkými PDF.
 * Prevzaté doklady z Woo Faktura sa dajú zahrnúť; ich položky v XML pochádzajú z objednávky (platné je pôvodné PDF).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-invoices', 'Export pre účtovníka', 'Export', 'manage_woocommerce', 'j-invoices-export', 'jinv_render_export' );
}, 20 );

/** Doklady na export. */
function jinv_export_rows( $from, $to, array $kinds, $with_wf, $with_cancelled ) {
	global $wpdb;
	$kinds = array_values( array_intersect( $kinds, array_keys( jinv_kinds() ) ) );
	if ( ! $kinds ) {
		return array();
	}
	$in  = implode( ',', array_fill( 0, count( $kinds ), '%s' ) );
	$sql = 'SELECT * FROM ' . jinv_table() . " WHERE issue_date BETWEEN %s AND %s AND kind IN ($in)";
	if ( ! $with_wf ) {
		$sql .= " AND source = ''";
	}
	if ( ! $with_cancelled ) {
		$sql .= " AND status <> 'cancelled'";
	}
	$sql .= ' ORDER BY kind, issue_date, id';
	$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $from, $to ), $kinds ) ), ARRAY_A ); // phpcs:ignore
	foreach ( $rows as &$r ) {
		$r['data'] = json_decode( (string) $r['data'], true );
	}
	return $rows;
}

function jinv_render_export() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$first = gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-01' ) . ' -1 month' ) );
	$last  = gmdate( 'Y-m-t', strtotime( $first ) );
	?>
	<div class="wrap jinv">
		<h1>Export pre účtovníka <span class="jinv-ver">J-Fakturácia v<?php echo esc_html( JINV_VERSION ); ?></span></h1>
		<?php if ( isset( $_GET['empty'] ) ) : // phpcs:ignore ?><div class="notice notice-warning"><p>V zvolenom období nie sú žiadne doklady.</p></div><?php endif; ?>
		<div class="jinv-panel">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'jinv_export' ); ?>
				<input type="hidden" name="action" value="jinv_export">
				<table class="form-table" role="presentation">
					<tr><th>Obdobie (dátum vystavenia)</th><td><input type="date" name="from" value="<?php echo esc_attr( $first ); ?>" required> – <input type="date" name="to" value="<?php echo esc_attr( $last ); ?>" required><p class="description">Predvolene minulý mesiac.</p></td></tr>
					<tr><th>Doklady</th><td>
						<?php foreach ( jinv_kinds() as $k => $def ) : ?>
							<label style="margin-right:14px"><input type="checkbox" name="kinds[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( 'proforma' !== $k ); ?>> <?php echo esc_html( $def[0] === 'Faktúra' ? 'Faktúry' : ( $def[0] === 'Dobropis' ? 'Dobropisy' : 'Zálohové faktúry' ) ); ?></label>
						<?php endforeach; ?>
						<p class="description">Zálohové faktúry nie sú daňové doklady – účtovník ich zvyčajne nepotrebuje.</p>
					</td></tr>
					<tr><th>Zahrnúť</th><td>
						<label><input type="checkbox" name="wf" value="1"> doklady prevzaté z Woo Faktura</label> <span class="description">(zvyčajne nie – účtovník ich už dostal z Woo Faktury)</span><br>
						<label><input type="checkbox" name="cancelled" value="1"> stornované doklady</label>
					</td></tr>
					<tr><th>Formát</th><td>
						<label><input type="radio" name="format" value="csv" checked> Tabuľka CSV (Excel)</label><br>
						<label><input type="radio" name="format" value="pohoda"> XML pre Pohodu (import dokladov)</label><br>
						<label><input type="radio" name="format" value="zip"> ZIP so všetkými PDF</label>
					</td></tr>
				</table>
				<?php submit_button( 'Stiahnuť export' ); ?>
			</form>
		</div>
	</div>
	<?php
}

add_action( 'admin_post_jinv_export', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_export' );
	$in    = wp_unslash( $_POST );
	$from  = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['from'] ?? '' ) ? $in['from'] : current_time( 'Y-m-01' );
	$to    = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['to'] ?? '' ) ? $in['to'] : current_time( 'Y-m-d' );
	$kinds = array_map( 'sanitize_key', (array) ( $in['kinds'] ?? array() ) );
	$rows  = jinv_export_rows( $from, $to, $kinds, ! empty( $in['wf'] ), ! empty( $in['cancelled'] ) );
	if ( ! $rows ) {
		wp_safe_redirect( admin_url( 'admin.php?page=j-invoices-export&empty=1' ) );
		exit;
	}
	$name = 'doklady-' . $from . '-' . $to;
	$fmt  = $in['format'] ?? 'csv';
	nocache_headers();
	if ( 'pohoda' === $fmt ) {
		$xml = jinv_export_pohoda( $rows );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '-pohoda.xml"' );
		echo $xml; // phpcs:ignore
		exit;
	}
	if ( 'zip' === $fmt ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'Server nepodporuje ZIP (rozšírenie PHP zip).' );
		}
		@set_time_limit( 300 ); // phpcs:ignore
		$tmp = wp_tempnam( $name . '.zip' );
		$zip = new ZipArchive();
		$zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$dirs = array( 'issued' => 'faktury', 'proforma' => 'zalohove-faktury', 'creditnote' => 'dobropisy' );
		foreach ( $rows as $r ) {
			$pdf = jinv_pdf( $r );
			if ( '' !== $pdf ) {
				$zip->addFromString( $dirs[ $r['kind'] ] . '/' . jinv_pdf_filename( $r ), $pdf );
			}
		}
		$zip->close();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '-pdf.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore
		wp_delete_file( $tmp );
		exit;
	}
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $name . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore – BOM, aby Excel čítal diakritiku
	fputcsv( $out, array( 'Druh', 'Číslo', 'Stav', 'Dátum vystavenia', 'Dátum dodania', 'Splatnosť', 'VS', 'Objednávka', 'Odberateľ', 'IČO', 'DIČ', 'IČ DPH', 'E-mail', 'Spôsob úhrady', 'Suma', 'Mena', 'Uhradené', 'K faktúre', 'Zdroj' ), ';' );
	$st = jinv_statuses();
	$safe = function ( $v ) { // ochrana pred vzorcami v Exceli (=, +, -, @ na začiatku textu)
		$num = is_string( $v ) && preg_match( '/^-?\d+(?:[.,]\d+)?$/', $v ); // suma (aj záporná s desatinnou čiarkou) ostáva číslom
		return is_string( $v ) && '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) && ! $num ? "'" . $v : $v;
	};
	foreach ( $rows as $r ) {
		$c = (array) ( $r['data']['customer'] ?? array() );
		fputcsv( $out, array_map( $safe, array(
			jinv_kind_label( $r['kind'] ),
			$r['number'],
			$st[ $r['status'] ] ?? $r['status'],
			jinv_date( $r['issue_date'] ),
			jinv_date( $r['supply_date'] ),
			jinv_date( $r['due_date'] ),
			$r['vs'],
			$r['data']['order_no'] ?? '',
			$r['customer_name'],
			$c['ico'] ?? '',
			$c['dic'] ?? '',
			$c['icdph'] ?? '',
			$r['customer_email'],
			$r['data']['payment'] ?? '',
			number_format( (float) $r['total'], 2, ',', '' ),
			$r['currency'],
			$r['paid_at'] ? jinv_date( substr( $r['paid_at'], 0, 10 ) ) : '',
			$r['data']['invoice'] ?? '',
			'woo-faktura' === $r['source'] ? 'Woo Faktura' : 'J-Fakturácia',
		)), ';' );
	}
	fclose( $out ); // phpcs:ignore
	exit;
} );

/** Spôsob úhrady pre Pohodu. */
function jinv_pohoda_payment( $code ) {
	if ( function_exists( 'jinv_transfer_methods' ) && in_array( $code, jinv_transfer_methods(), true ) ) {
		return 'draft';
	}
	if ( in_array( $code, apply_filters( 'jinv_cod_methods', array( 'cod', 'jetpack_custom_gateway' ) ), true ) ) {
		return 'delivery';
	}
	return in_array( $code, array( 'cheque', 'cash' ), true ) ? 'cash' : 'creditcard';
}

/** XML dataPack 2.0 pre Pohodu. */
function jinv_export_pohoda( array $rows ) {
	$s   = jinv_settings();
	$ico = preg_replace( '/\D/', '', (string) $s['ico'] );
	$x   = new XMLWriter();
	$x->openMemory();
	$x->setIndent( true );
	$x->startDocument( '1.0', 'UTF-8' );
	$x->startElement( 'dat:dataPack' );
	$x->writeAttribute( 'xmlns:dat', 'http://www.stormware.cz/schema/version_2/data.xsd' );
	$x->writeAttribute( 'xmlns:inv', 'http://www.stormware.cz/schema/version_2/invoice.xsd' );
	$x->writeAttribute( 'xmlns:typ', 'http://www.stormware.cz/schema/version_2/type.xsd' );
	$x->writeAttribute( 'id', 'JF' . gmdate( 'YmdHis' ) );
	$x->writeAttribute( 'ico', $ico );
	$x->writeAttribute( 'application', 'J-Fakturacia' );
	$x->writeAttribute( 'version', '2.0' );
	$x->writeAttribute( 'note', 'Export dokladov z J-Fakturácia' );
	$types = array( 'issued' => 'issuedInvoice', 'proforma' => 'issuedAdvanceInvoice', 'creditnote' => 'issuedCreditNotice' );
	foreach ( $rows as $r ) {
		$d = (array) $r['data'];
		$c = (array) ( $d['customer'] ?? array() );
		$x->startElement( 'dat:dataPackItem' );
		$x->writeAttribute( 'id', $r['kind'] . '-' . $r['number'] );
		$x->writeAttribute( 'version', '2.0' );
		$x->startElement( 'inv:invoice' );
		$x->writeAttribute( 'version', '2.0' );
		$x->startElement( 'inv:invoiceHeader' );
		$x->writeElement( 'inv:invoiceType', $types[ $r['kind'] ] );
		$x->startElement( 'inv:number' );
		$x->writeElement( 'typ:numberRequested', $r['number'] );
		$x->endElement();
		$x->writeElement( 'inv:symVar', $r['vs'] );
		$x->writeElement( 'inv:date', $r['issue_date'] );
		if ( 'proforma' !== $r['kind'] ) {
			$x->writeElement( 'inv:dateTax', $r['supply_date'] );
			$x->writeElement( 'inv:dateAccounting', $r['issue_date'] );
		}
		$x->writeElement( 'inv:dateDue', $r['due_date'] );
		$x->writeElement( 'inv:text', jinv_kind_label( $r['kind'] ) . ( ! empty( $d['order_no'] ) ? ' k objednávke ' . $d['order_no'] : '' ) );
		$x->startElement( 'inv:partnerIdentity' );
		$x->startElement( 'typ:address' );
		if ( ! empty( $c['company'] ) ) {
			$x->writeElement( 'typ:company', $c['company'] );
		}
		$x->writeElement( 'typ:name', (string) ( $c['name'] ?? '' ) );
		$x->writeElement( 'typ:city', (string) ( $c['city'] ?? '' ) );
		$x->writeElement( 'typ:street', (string) ( $c['street'] ?? '' ) );
		$x->writeElement( 'typ:zip', (string) ( $c['zip'] ?? '' ) );
		foreach ( array( 'ico' => 'typ:ico', 'dic' => 'typ:dic', 'icdph' => 'typ:icDph' ) as $k => $el ) {
			if ( ! empty( $c[ $k ] ) ) {
				$x->writeElement( $el, $c[ $k ] );
			}
		}
		if ( ! empty( $c['email'] ) ) {
			$x->writeElement( 'typ:email', $c['email'] );
		}
		$x->endElement(); // address
		$x->endElement(); // partnerIdentity
		$x->startElement( 'inv:paymentType' );
		$x->writeElement( 'typ:paymentType', jinv_pohoda_payment( (string) ( $d['pay_code'] ?? '' ) ) );
		$x->endElement();
		if ( 'creditnote' === $r['kind'] && ! empty( $d['invoice'] ) ) {
			$x->writeElement( 'inv:note', 'Dobropis k faktúre ' . $d['invoice'] . ( ! empty( $d['reason'] ) ? ' – ' . $d['reason'] : '' ) );
		}
		if ( 'cancelled' === $r['status'] ) {
			$x->writeElement( 'inv:intNote', 'Stornovaný doklad' );
		}
		$x->endElement(); // invoiceHeader
		$x->startElement( 'inv:invoiceDetail' );
		foreach ( (array) ( $d['items'] ?? array() ) as $it ) {
			if ( ! empty( $it['info'] ) ) {
				continue;
			}
			$x->startElement( 'inv:invoiceItem' );
			$x->writeElement( 'inv:text', mb_substr( (string) $it['name'], 0, 90 ) );
			$x->writeElement( 'inv:quantity', (string) (float) $it['qty'] );
			if ( ! empty( $it['unit'] ) ) {
				$x->writeElement( 'inv:unit', $it['unit'] );
			}
			$x->writeElement( 'inv:rateVAT', 'none' );
			$x->startElement( 'inv:homeCurrency' );
			$x->writeElement( 'typ:unitPrice', number_format( (float) $it['price'], 2, '.', '' ) );
			$x->endElement();
			if ( ! empty( $it['sku'] ) ) {
				$x->writeElement( 'inv:code', mb_substr( (string) $it['sku'], 0, 64 ) );
			}
			$x->endElement();
		}
		$x->endElement(); // invoiceDetail
		$x->startElement( 'inv:invoiceSummary' );
		$x->writeElement( 'inv:roundingDocument', 'none' );
		$x->startElement( 'inv:homeCurrency' );
		$x->writeElement( 'typ:priceNone', number_format( (float) $r['total'], 2, '.', '' ) );
		$x->endElement();
		$x->endElement(); // invoiceSummary
		$x->endElement(); // invoice
		$x->endElement(); // dataPackItem
	}
	$x->endElement(); // dataPack
	$x->endDocument();
	return $x->outputMemory();
}
