<?php
/** Vykreslenie dokladu do PDF (A4, mm). Načíta sa až pri tvorbe PDF (jinv_pdf_lib). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JINV_PDF extends tFPDF {

	/** @var array doklad */
	protected $inv;
	protected $navy = array( 22, 45, 79 );
	protected $grey = array( 107, 114, 128 );

	public function __construct( array $inv ) {
		parent::__construct( 'P', 'mm', 'A4' );
		$this->inv = $inv;
		$this->SetMargins( 16, 14, 16 );
		$this->SetAutoPageBreak( true, 22 );
		$this->AddFont( 'DejaVu', '', 'DejaVuSansCondensed.ttf', true );
		$this->AddFont( 'DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true );
		$this->AliasNbPages();
		$sup = (array) ( $inv['data']['supplier'] ?? array() );
		$this->SetTitle( jinv_kind_label( $inv['kind'] ) . ' ' . $inv['number'], true );
		$this->SetAuthor( (string) ( $sup['company'] ?? '' ), true );
		$this->SetCreator( 'J-Fakturácia', true );
	}

	protected function t( $s ) {
		return trim( (string) $s );
	}

	protected function font( $size, $bold = false, $color = null ) {
		$this->SetFont( 'DejaVu', $bold ? 'B' : '', $size );
		$c = $color ? $color : array( 31, 41, 55 );
		$this->SetTextColor( $c[0], $c[1], $c[2] );
	}

	public function Footer() {
		$sup = (array) ( $this->inv['data']['supplier'] ?? array() );
		$this->SetY( -17 );
		$this->SetDrawColor( 229, 231, 235 );
		$this->Line( 16, $this->GetY(), 194, $this->GetY() );
		$this->Ln( 1.5 );
		$this->font( 7.5, false, $this->grey );
		$line = implode( '  ·  ', array_filter( array( $this->t( $sup['company'] ?? '' ), $this->t( $sup['register'] ?? '' ) ) ) );
		$this->Cell( 150, 4, $line, 0, 0, 'L' );
		$this->Cell( 28, 4, 'Strana ' . $this->PageNo() . ' / {nb}', 0, 1, 'R' );
		$this->Cell( 178, 4, implode( '  ·  ', array_filter( array( $this->t( $sup['email'] ?? '' ), $this->t( $sup['phone'] ?? '' ), $this->t( $sup['web'] ?? '' ) ) ) ), 0, 1, 'L' );
	}

	/** Počet riadkov, ktoré zaberie text v bunke danej šírky. */
	protected function nb_lines( $w, $txt ) {
		$words = preg_split( '/\s+/u', (string) $txt );
		$lines = 1;
		$cur   = 0;
		$max   = $w - 2 * $this->cMargin;
		$space = $this->GetStringWidth( ' ' );
		foreach ( $words as $word ) {
			$ww = $this->GetStringWidth( $word );
			if ( $cur > 0 && $cur + $space + $ww > $max ) {
				$lines++;
				$cur = $ww;
			} else {
				$cur += ( $cur > 0 ? $space : 0 ) + $ww;
			}
			while ( $cur > $max ) { // veľmi dlhé slovo
				$lines++;
				$cur -= $max;
			}
		}
		return $lines;
	}

	protected function logo() {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $id ) {
			return 0;
		}
		$path = get_attached_file( $id );
		if ( ! $path || ! is_file( $path ) || ! preg_match( '/\.(png|jpe?g)$/i', $path ) ) {
			return 0;
		}
		$size = @getimagesize( $path ); // phpcs:ignore
		if ( ! $size || ! $size[1] ) {
			return 0;
		}
		$h = 16;
		$w = $h * $size[0] / $size[1];
		if ( $w > 60 ) {
			$w = 60;
			$h = $w * $size[1] / $size[0];
		}
		try {
			$this->Image( $path, 16, 14, $w, $h );
		} catch ( \Throwable $e ) {
			return 0;
		}
		return $h;
	}

	protected function party( $x, $y, $title, array $p, $is_customer ) {
		$this->SetXY( $x, $y );
		$this->font( 7.5, true, $this->grey );
		$this->Cell( 85, 4, mb_strtoupper( $title ), 0, 2 );
		$name = ! empty( $p['company'] ) ? $p['company'] : ( $p['name'] ?? '' );
		$this->font( 10.5, true );
		$this->MultiCell( 85, 5, $this->t( $name ), 0, 'L' );
		$this->SetX( $x );
		$this->font( 9 );
		$lines = array();
		if ( $is_customer && ! empty( $p['company'] ) && ! empty( $p['name'] ) ) {
			$lines[] = $p['name'];
		}
		$lines[] = $p['street'] ?? '';
		$lines[] = trim( ( $p['zip'] ?? '' ) . ' ' . ( $p['city'] ?? '' ) );
		$lines[] = $p['country'] ?? '';
		foreach ( array( 'ico' => 'IČO', 'dic' => 'DIČ', 'icdph' => 'IČ DPH' ) as $k => $l ) {
			if ( ! empty( $p[ $k ] ) ) {
				$lines[] = $l . ': ' . $p[ $k ];
			}
		}
		if ( $is_customer && ! empty( $p['email'] ) ) {
			$lines[] = $p['email'];
		}
		foreach ( array_filter( array_map( array( $this, 't' ), $lines ) ) as $l ) {
			$this->SetX( $x );
			$this->MultiCell( 85, 4.6, $l, 0, 'L' );
		}
		return $this->GetY();
	}

	protected function items_header() {
		$this->SetDrawColor( $this->navy[0], $this->navy[1], $this->navy[2] );
		$this->SetLineWidth( 0.5 );
		$this->font( 7.5, true, $this->grey );
		$this->Cell( 98, 6, 'POLOŽKA', 'B', 0, 'L' );
		$this->Cell( 22, 6, 'MNOŽSTVO', 'B', 0, 'R' );
		$this->Cell( 29, 6, 'CENA ZA MJ', 'B', 0, 'R' );
		$this->Cell( 29, 6, 'SPOLU', 'B', 1, 'R' );
		$this->SetLineWidth( 0.2 );
	}

	public function render_doc() {
		$inv  = $this->inv;
		$d    = (array) $inv['data'];
		$sup  = (array) ( $d['supplier'] ?? array() );
		$cus  = (array) ( $d['customer'] ?? array() );
		$cur  = $inv['currency'];
		$kind = $inv['kind'];
		$this->AddPage();

		// Hlavička: logo + názov dokladu.
		$this->logo();
		$this->SetXY( 110, 14 );
		$this->font( 20, true, $this->navy );
		$this->Cell( 84, 9, jinv_kind_label( $kind ), 0, 2, 'R' );
		$this->font( 11 );
		$this->Cell( 84, 6, 'č. ' . $inv['number'], 0, 1, 'R' );

		// Dodávateľ a odberateľ.
		$y  = 38;
		$y1 = $this->party( 16, $y, 'Dodávateľ', $sup, false );
		$y2 = $this->party( 109, $y, 'Odberateľ', $cus, true );
		$y  = max( $y1, $y2 ) + 5;

		// Údaje dokladu v sivom poli.
		$meta = array(
			array( 'Dátum vystavenia', jinv_date( $inv['issue_date'] ) ),
			array( 'creditnote' === $kind ? 'Dátum vrátenia' : ( 'proforma' === $kind ? 'Objednávka zo dňa' : 'Dátum dodania' ), jinv_date( $inv['supply_date'] ) ),
			array( 'Dátum splatnosti', jinv_date( $inv['due_date'] ) ),
			array( 'Spôsob úhrady', $this->t( $d['payment'] ?? '' ) ),
			array( 'Variabilný symbol', $inv['vs'] ),
			array( 'Objednávka', ! empty( $d['order_no'] ) ? '#' . $d['order_no'] : '' ),
		);
		if ( ! empty( $d['invoice'] ) ) {
			$meta[] = array( 'K faktúre', $d['invoice'] );
		}
		if ( ! empty( $d['proforma'] ) && 'issued' === $kind ) {
			$meta[] = array( 'Zálohová faktúra', $d['proforma'] );
		}
		$rows  = (int) ceil( count( $meta ) / 3 );
		$has_b = ! empty( $sup['iban'] );
		$box_h = 4 + $rows * 10 + ( $has_b ? 10 : 0 );
		$this->SetFillColor( 246, 247, 249 );
		$this->Rect( 16, $y, 178, $box_h, 'F' );
		foreach ( $meta as $i => $m ) {
			$cx = 20 + ( $i % 3 ) * 58;
			$cy = $y + 3 + (int) floor( $i / 3 ) * 10;
			$this->SetXY( $cx, $cy );
			$this->font( 7.5, false, $this->grey );
			$this->Cell( 56, 4, $m[0], 0, 2 );
			$this->font( 9.5, true );
			$this->Cell( 56, 5, '' !== (string) $m[1] ? (string) $m[1] : '—', 0, 0 );
		}
		if ( $has_b ) {
			$cy = $y + 3 + $rows * 10;
			$this->SetXY( 20, $cy );
			$this->font( 7.5, false, $this->grey );
			$this->Cell( 170, 4, 'Bankové spojenie', 0, 2 );
			$this->font( 9.5, true );
			$iban = trim( chunk_split( (string) $sup['iban'], 4, ' ' ) );
			$this->Cell( 70, 5, 'IBAN ' . $iban, 0, 0 );
			$this->font( 9 );
			$this->Cell( 100, 5, implode( '   ', array_filter( array( ! empty( $sup['swift'] ) ? 'SWIFT ' . $sup['swift'] : '', $this->t( $sup['bank'] ?? '' ) ) ) ), 0, 0 );
		}
		$this->SetY( $y + $box_h + 5 );

		// Úvodný text.
		$lead = array();
		if ( 'creditnote' === $kind ) {
			$lead[] = 'Opravný doklad k faktúre č. ' . ( $d['invoice'] ?? '' ) . ( ! empty( $d['reason'] ) ? ' – dôvod: ' . $d['reason'] : '' ) . '.';
		}
		if ( 'proforma' === $kind ) {
			$lead[] = 'Zálohová faktúra – výzva na úhradu, nie je daňovým dokladom. Faktúru vystavíme po prijatí platby.';
		}
		if ( ! empty( $d['note'] ) ) {
			$lead[] = $d['note'];
		}
		if ( $lead ) {
			$this->font( 9 );
			foreach ( $lead as $l ) {
				$this->MultiCell( 178, 4.8, $this->t( $l ), 0, 'L' );
			}
			$this->Ln( 3 );
		}

		// Položky.
		$this->items_header();
		$this->SetDrawColor( 238, 240, 243 );
		foreach ( (array) ( $d['items'] ?? array() ) as $it ) {
			if ( ! empty( $it['info'] ) ) {
				$this->font( 8.5, false, $this->grey );
				$this->MultiCell( 178, 6, $this->t( $it['name'] ), 'B', 'L' );
				continue;
			}
			$name = $this->t( $it['name'] ) . ( ! empty( $it['sku'] ) ? ' (' . $it['sku'] . ')' : '' );
			$this->font( 9 );
			$h = max( 1, $this->nb_lines( 98, $name ) ) * 4.8 + 2.4;
			if ( $this->GetY() + $h > $this->PageBreakTrigger ) {
				$this->AddPage();
				$this->items_header();
				$this->SetDrawColor( 238, 240, 243 );
				$this->font( 9 );
			}
			$x0 = $this->GetX();
			$y0 = $this->GetY();
			$this->SetXY( $x0, $y0 + 1.2 );
			$this->MultiCell( 98, 4.8, $name, 0, 'L' );
			$qty = rtrim( rtrim( number_format( (float) $it['qty'], 2, ',', '' ), '0' ), ',' ) . ( ! empty( $it['unit'] ) ? ' ' . $it['unit'] : '' );
			$this->SetXY( $x0 + 98, $y0 + 1.2 );
			$this->Cell( 22, 4.8, $qty, 0, 0, 'R' );
			$this->Cell( 29, 4.8, jinv_money( $it['price'], $cur ), 0, 0, 'R' );
			$this->Cell( 29, 4.8, jinv_money( $it['total'], $cur ), 0, 0, 'R' );
			$this->Line( 16, $y0 + $h, 194, $y0 + $h );
			$this->SetXY( $x0, $y0 + $h );
		}

		// Súčet, QR a poznámky.
		$this->Ln( 5 );
		$qr     = jinv_doc_paybysquare( $inv );
		$matrix = $qr ? jinv_qr_matrix( $qr ) : null;
		$need   = $matrix ? 48 : 26;
		if ( $this->GetY() + $need > $this->PageBreakTrigger ) {
			$this->AddPage();
		}
		$y = $this->GetY();
		// Celková suma vpravo.
		$this->SetFillColor( $this->navy[0], $this->navy[1], $this->navy[2] );
		$this->Rect( 120, $y, 74, 13, 'F' );
		$this->SetXY( 123, $y + 1.5 );
		$this->font( 8, false, array( 255, 255, 255 ) );
		$this->Cell( 68, 4, 'creditnote' === $kind ? 'Celkom na vrátenie' : 'Celkom na úhradu', 0, 2, 'R' );
		$this->font( 14, true, array( 255, 255, 255 ) );
		$this->Cell( 68, 6.5, jinv_money( $inv['total'], $cur ), 0, 0, 'R' );
		$ry = $y + 16;
		$this->SetXY( 120, $ry );
		$this->font( 8.5, false, array( 75, 85, 99 ) );
		if ( empty( $sup['vat_payer'] ) && ! empty( $sup['vat_note'] ) ) {
			$this->MultiCell( 74, 4.4, $this->t( $sup['vat_note'] ), 0, 'R' );
			$this->SetX( 120 );
		}
		if ( ! empty( $d['proforma'] ) && 'issued' === $kind ) {
			$this->MultiCell( 74, 4.4, 'Uhradené na základe zálohovej faktúry č. ' . $d['proforma'] . '.', 0, 'R' );
			$this->SetX( 120 );
		}
		// Pečiatka stavu.
		$stamp = '';
		if ( 'cancelled' === $inv['status'] ) {
			$stamp = 'STORNOVANÝ DOKLAD';
			$col   = array( 163, 18, 27 );
		} elseif ( 'paid' === $inv['status'] ) {
			$stamp = ( 'creditnote' === $kind ? 'VRÁTENÉ' : 'UHRADENÉ' ) . ( $inv['paid_at'] ? ' ' . jinv_date( substr( $inv['paid_at'], 0, 10 ) ) : '' );
			$col   = array( 20, 90, 42 );
		}
		if ( $stamp ) {
			$this->Ln( 1 );
			$this->SetX( 194 - 60 );
			$this->SetDrawColor( $col[0], $col[1], $col[2] );
			$this->SetLineWidth( 0.6 );
			$this->font( 10, true, $col );
			$this->Cell( 60, 8, $stamp, 1, 1, 'C' );
			$this->SetLineWidth( 0.2 );
		}
		$after = $this->GetY();
		// QR kód vľavo.
		if ( $matrix ) {
			$n    = count( $matrix );
			$size = 34;
			$m    = $size / $n;
			$this->SetFillColor( 0, 0, 0 );
			foreach ( $matrix as $r => $row ) {
				foreach ( $row as $c => $on ) {
					if ( $on ) {
						$this->Rect( 16 + $c * $m, $y + $r * $m, $m + 0.02, $m + 0.02, 'F' );
					}
				}
			}
			$this->SetXY( 16, $y + $size + 1 );
			$this->font( 7.5, true, $this->navy );
			$this->Cell( 34, 4, 'PAY by square', 0, 2, 'C' );
			$this->SetXY( 54, $y );
			$this->font( 8.5, false, array( 75, 85, 99 ) );
			$this->MultiCell( 60, 4.4, 'Naskenujte QR kód v aplikácii banky – suma, účet aj variabilný symbol sa vyplnia automaticky.', 0, 'L' );
			$after = max( $after, $y + $size + 6 );
		}
		$this->SetY( $after + 6 );
		// Vystavil, text v päte dokladu.
		$this->font( 9, false, array( 75, 85, 99 ) );
		if ( ! empty( $sup['issued_by'] ) ) {
			$this->MultiCell( 178, 4.8, 'Vystavil: ' . $this->t( $sup['issued_by'] ), 0, 'L' );
		}
		if ( ! empty( $sup['footer'] ) ) {
			$this->MultiCell( 178, 4.8, $this->t( $sup['footer'] ), 0, 'L' );
		}
	}
}
