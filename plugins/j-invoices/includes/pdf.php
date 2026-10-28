<?php
/**
 * PDF dokladu (faktúra, zálohová faktúra, dobropis) – tFPDF s písmom DejaVu (slovenská diakritika)
 * a QR kód platby PAY by square (kreslený vektorovo). PDF sa vytvára vždy zo snímky údajov dokladu,
 * takže je rovnaké pri každom stiahnutí; mení sa len pečiatka (uhradené / stornované).
 * Prevzaté doklady z Woo Faktura sa neprekresľujú – vráti sa ich pôvodné PDF.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( function ( $class ) {
	$map = array(
		'chillerlan\\QRCode\\'   => JINV_DIR . 'lib/qrcode/',
		'chillerlan\\Settings\\' => JINV_DIR . 'lib/settings-container/',
	);
	foreach ( $map as $prefix => $dir ) {
		if ( 0 === strpos( $class, $prefix ) ) {
			$file = $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
			return;
		}
	}
} );

/** Matica QR kódu (pole riadkov s true/false) alebo null. */
function jinv_qr_matrix( $text ) {
	try {
		$opt = new \chillerlan\QRCode\QROptions( array(
			'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::M,
			'addQuietzone' => false,
		) );
		$qr = new \chillerlan\QRCode\QRCode( $opt );
		$qr->addAlphaNumSegment( $text );
		return $qr->getQRMatrix()->getMatrix( true );
	} catch ( \Throwable $e ) {
		return null;
	}
}

/** Reťazec PAY by square pre doklad (len neuhradený doklad na úhradu s IBAN), inak ''. */
function jinv_doc_paybysquare( array $inv ) {
	$sup = (array) ( $inv['data']['supplier'] ?? array() );
	if ( 'creditnote' === $inv['kind'] || in_array( $inv['status'], array( 'paid', 'cancelled' ), true ) || empty( $sup['iban'] ) || (float) $inv['total'] <= 0 || 'EUR' !== $inv['currency'] ) {
		return '';
	}
	return jinv_paybysquare( array(
		'iban'     => $sup['iban'],
		'bic'      => $sup['swift'] ?? '',
		'amount'   => $inv['total'],
		'currency' => $inv['currency'],
		'due'      => $inv['due_date'],
		'vs'       => $inv['vs'],
		'note'     => jinv_kind_label( $inv['kind'] ) . ' ' . $inv['number'],
		'name'     => $sup['company'] ?? '',
		'street'   => $sup['street'] ?? '',
		'city'     => trim( ( $sup['zip'] ?? '' ) . ' ' . ( $sup['city'] ?? '' ) ),
	) );
}

function jinv_pdf_lib() {
	if ( ! class_exists( 'tFPDF' ) ) {
		require_once JINV_DIR . 'lib/tfpdf/tfpdf.php';
	}
	if ( ! class_exists( 'TTFontFile' ) ) {
		require_once JINV_DIR . 'lib/tfpdf/font/unifont/ttfonts.php';
	}
	if ( ! class_exists( 'JINV_PDF' ) ) {
		require_once JINV_DIR . 'includes/pdf-class.php';
	}
	// Vyrovnávacia pamäť písiem tFPDF obsahuje absolútnu cestu k písmu – po presune doplnku (iný server/priečinok) sa zmaže.
	// Súbory sa načítavajú cez include – OPcache by mohol držať starú verziu, preto sa vždy zneplatnia.
	$cache = glob( JINV_DIR . 'lib/tfpdf/font/unifont/dejavu*.mtx.php' );
	foreach ( (array) glob( JINV_DIR . 'lib/tfpdf/font/unifont/dejavu*.php' ) as $php ) {
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $php, true ); // phpcs:ignore
		}
	}
	foreach ( (array) $cache as $mtx ) {
		if ( false === strpos( (string) file_get_contents( $mtx ), JINV_DIR ) ) { // phpcs:ignore
			foreach ( (array) glob( JINV_DIR . 'lib/tfpdf/font/unifont/dejavu*' ) as $f ) {
				@unlink( $f ); // phpcs:ignore
			}
			break;
		}
	}
}

/** Obsah PDF dokladu (reťazec). Prevzatý doklad z Woo Faktura → pôvodné PDF. */
function jinv_pdf( array $inv ) {
	if ( 'woo-faktura' === $inv['source'] && function_exists( 'jinv_wf_file_path' ) ) {
		$path = jinv_wf_file_path( $inv['file'] );
		if ( $path ) {
			return (string) file_get_contents( $path ); // phpcs:ignore
		}
	}
	jinv_pdf_lib();
	$pdf = new JINV_PDF( $inv );
	$pdf->render_doc();
	return $pdf->Output( 'S' );
}

/** Názov súboru PDF, napr. faktura-220260244.pdf */
function jinv_pdf_filename( array $inv ) {
	$p = array( 'issued' => 'faktura', 'proforma' => 'zalohova-faktura', 'creditnote' => 'dobropis' );
	return ( $p[ $inv['kind'] ] ?? 'doklad' ) . '-' . sanitize_file_name( $inv['number'] ) . '.pdf';
}

/** Odošle PDF do prehliadača a skončí. */
function jinv_pdf_send( array $inv, $inline = true ) {
	$data = jinv_pdf( $inv );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . jinv_pdf_filename( $inv ) . '"' );
	header( 'Content-Length: ' . strlen( $data ) );
	echo $data; // phpcs:ignore
	exit;
}

// Stiahnutie PDF v administrácii.
add_action( 'admin_post_jinv_pdf', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
	check_admin_referer( 'jinv_pdf_' . $id );
	$inv = jinv_get( $id );
	if ( ! $inv ) {
		wp_die( 'Doklad neexistuje.' );
	}
	jinv_pdf_send( $inv, empty( $_GET['download'] ) ); // phpcs:ignore
} );

// Ukážka PDF z poslednej objednávky (nič sa neuloží) – kontrola údajov dodávateľa, vzhľadu a QR kódu.
add_action( 'admin_post_jinv_pdf_sample', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jinv_pdf_sample' );
	$kind   = isset( $_GET['kind'] ) && isset( jinv_kinds()[ $_GET['kind'] ] ) ? sanitize_key( $_GET['kind'] ) : 'issued'; // phpcs:ignore
	$orders = wc_get_orders( array( 'limit' => 1, 'type' => 'shop_order', 'orderby' => 'date', 'order' => 'DESC' ) );
	if ( ! $orders ) {
		wp_die( 'Na ukážku je potrebná aspoň jedna objednávka.' );
	}
	$o     = $orders[0];
	$today = current_time( 'Y-m-d' );
	$num   = 'order' === jinv_opt( 'numbering' ) ? (string) $o->get_order_number() : jinv_next_number( $kind, null, (int) current_time( 'Y' ) )[0];
	$items = jinv_items_from_order( $o );
	$inv   = array(
		'id' => 0, 'kind' => $kind, 'number' => $num, 'status' => 'issued', 'issue_date' => $today, 'supply_date' => $today,
		'due_date' => gmdate( 'Y-m-d', strtotime( $today . ' +' . (int) jinv_opt( 'due_days' ) . ' days' ) ), 'vs' => preg_replace( '/\D/', '', $num ),
		'currency' => $o->get_currency(), 'total' => round( (float) $o->get_total(), 2 ), 'paid_at' => null, 'source' => '', 'file' => '',
		'data' => array( 'supplier' => jinv_supplier_snapshot(), 'customer' => jinv_customer_from_order( $o ), 'items' => $items, 'payment' => $o->get_payment_method_title(), 'pay_code' => $o->get_payment_method(), 'order_no' => $o->get_order_number(), 'note' => 'UKÁŽKA – nie je to vystavený doklad.' ),
	);
	jinv_pdf_send( $inv, true );
} );

function jinv_pdf_sample_url( $kind = 'issued' ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=jinv_pdf_sample&kind=' . $kind ), 'jinv_pdf_sample' );
}

function jinv_pdf_url( $id, $download = false ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=jinv_pdf&id=' . (int) $id . ( $download ? '&download=1' : '' ) ), 'jinv_pdf_' . (int) $id );
}

/** Dočasný súbor s PDF (prílohy e-mailov) v chránenom priečinku; vráti cestu. */
function jinv_pdf_temp_file( array $inv ) {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'j-invoices-tmp';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore
	}
	$sub = $dir . '/' . wp_generate_password( 12, false );
	wp_mkdir_p( $sub );
	$path = $sub . '/' . jinv_pdf_filename( $inv );
	file_put_contents( $path, jinv_pdf( $inv ) ); // phpcs:ignore
	return $path;
}

function jinv_pdf_temp_cleanup( $path ) {
	if ( $path && is_file( $path ) ) {
		wp_delete_file( $path );
		@rmdir( dirname( $path ) ); // phpcs:ignore
	}
}
