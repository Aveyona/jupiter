<?php
/** Faktúra ako HTML (tlačový náhľad; z tohto tvaru vznikne aj PDF). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jinv_money( $v, $cur = 'EUR' ) {
	return number_format( (float) $v, 2, ',', ' ' ) . ' ' . ( 'EUR' === $cur ? '€' : $cur );
}

function jinv_date( $ymd ) {
	return $ymd ? date_i18n( 'j. n. Y', strtotime( $ymd ) ) : '';
}

function jinv_render_invoice_html( array $inv ) {
	$d   = (array) $inv['data'];
	$sup = isset( $d['supplier'] ) ? (array) $d['supplier'] : array();
	$cus = isset( $d['customer'] ) ? (array) $d['customer'] : array();
	$cur = $inv['currency'];
	$e   = function ( $a, $k ) {
		return isset( $a[ $k ] ) ? esc_html( (string) $a[ $k ] ) : '';
	};
	$logo = '';
	$lid  = (int) get_theme_mod( 'custom_logo' );
	if ( $lid ) {
		$logo = wp_get_attachment_image_url( $lid, 'medium' );
	}
	ob_start();
	?>
	<div class="jinv-doc">
		<header class="jinv-head">
			<div><?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="" class="jinv-logo"><?php endif; ?></div>
			<div class="jinv-title"><h1><?php echo esc_html( 'proforma' === $inv['kind'] ? 'Zálohová faktúra' : ( 'creditnote' === $inv['kind'] ? 'Dobropis' : 'Faktúra' ) ); ?></h1><p>č. <strong><?php echo esc_html( $inv['number'] ); ?></strong></p></div>
		</header>
		<section class="jinv-parties">
			<div>
				<h2>Dodávateľ</h2>
				<p><strong><?php echo $e( $sup, 'company' ); ?></strong><br><?php echo $e( $sup, 'street' ); ?><br><?php echo $e( $sup, 'zip' ); ?> <?php echo $e( $sup, 'city' ); ?><br><?php echo $e( $sup, 'country' ); ?></p>
				<p>
					<?php if ( ! empty( $sup['ico'] ) ) : ?>IČO: <?php echo $e( $sup, 'ico' ); ?><br><?php endif; ?>
					<?php if ( ! empty( $sup['dic'] ) ) : ?>DIČ: <?php echo $e( $sup, 'dic' ); ?><br><?php endif; ?>
					<?php if ( ! empty( $sup['icdph'] ) ) : ?>IČ DPH: <?php echo $e( $sup, 'icdph' ); ?><br><?php endif; ?>
				</p>
				<?php if ( ! empty( $sup['register'] ) ) : ?><p class="jinv-small"><?php echo $e( $sup, 'register' ); ?></p><?php endif; ?>
			</div>
			<div>
				<h2>Odberateľ</h2>
				<p><strong><?php echo $cus['company'] ? $e( $cus, 'company' ) : $e( $cus, 'name' ); ?></strong><?php if ( $cus['company'] && $cus['name'] ) : ?><br><?php echo $e( $cus, 'name' ); ?><?php endif; ?><br><?php echo $e( $cus, 'street' ); ?><br><?php echo $e( $cus, 'zip' ); ?> <?php echo $e( $cus, 'city' ); ?><br><?php echo $e( $cus, 'country' ); ?></p>
				<p>
					<?php if ( ! empty( $cus['ico'] ) ) : ?>IČO: <?php echo $e( $cus, 'ico' ); ?><br><?php endif; ?>
					<?php if ( ! empty( $cus['dic'] ) ) : ?>DIČ: <?php echo $e( $cus, 'dic' ); ?><br><?php endif; ?>
					<?php if ( ! empty( $cus['icdph'] ) ) : ?>IČ DPH: <?php echo $e( $cus, 'icdph' ); ?><br><?php endif; ?>
					<?php echo $e( $cus, 'email' ); ?>
				</p>
			</div>
		</section>
		<section class="jinv-meta">
			<div><span>Dátum vystavenia</span><strong><?php echo esc_html( jinv_date( $inv['issue_date'] ) ); ?></strong></div>
			<div><span>Dátum dodania</span><strong><?php echo esc_html( jinv_date( $inv['supply_date'] ) ); ?></strong></div>
			<div><span>Dátum splatnosti</span><strong><?php echo esc_html( jinv_date( $inv['due_date'] ) ); ?></strong></div>
			<div><span>Spôsob úhrady</span><strong><?php echo $e( $d, 'payment' ); ?></strong></div>
			<div><span>Variabilný symbol</span><strong><?php echo esc_html( $inv['vs'] ); ?></strong></div>
			<?php if ( ! empty( $d['order_no'] ) ) : ?><div><span>Objednávka</span><strong>#<?php echo $e( $d, 'order_no' ); ?></strong></div><?php endif; ?>
			<?php if ( ! empty( $d['invoice'] ) ) : ?><div><span>K faktúre</span><strong><?php echo $e( $d, 'invoice' ); ?></strong></div><?php endif; ?>
			<?php if ( ! empty( $d['proforma'] ) ) : ?><div><span>Zálohová faktúra</span><strong><?php echo $e( $d, 'proforma' ); ?></strong></div><?php endif; ?>
			<?php if ( ! empty( $sup['iban'] ) ) : ?><div class="jinv-wide"><span>IBAN</span><strong><?php echo esc_html( trim( chunk_split( (string) $sup['iban'], 4, ' ' ) ) ); ?></strong><?php if ( ! empty( $sup['swift'] ) ) : ?> <em>SWIFT <?php echo $e( $sup, 'swift' ); ?></em><?php endif; ?><?php if ( ! empty( $sup['bank'] ) ) : ?> <em><?php echo $e( $sup, 'bank' ); ?></em><?php endif; ?></div><?php endif; ?>
		</section>
		<?php if ( 'creditnote' === $inv['kind'] ) : ?><p class="jinv-lead">Opravný doklad k faktúre č. <strong><?php echo $e( $d, 'invoice' ); ?></strong><?php echo ! empty( $d['reason'] ) ? ' – dôvod: ' . $e( $d, 'reason' ) : ''; ?>.</p><?php endif; ?>
		<?php if ( 'proforma' === $inv['kind'] ) : ?><p class="jinv-lead">Zálohová faktúra – výzva na úhradu, nie je daňovým dokladom. Faktúru vystavíme po prijatí platby.</p><?php endif; ?>
		<?php if ( ! empty( $d['note'] ) ) : ?><p class="jinv-lead"><?php echo nl2br( $e( $d, 'note' ) ); // phpcs:ignore ?></p><?php endif; ?>
		<table class="jinv-items">
			<thead><tr><th>Položka</th><th class="n">Množstvo</th><th class="n">Cena za MJ</th><th class="n">Spolu</th></tr></thead>
			<tbody>
				<?php foreach ( (array) $d['items'] as $it ) : ?>
					<?php if ( ! empty( $it['info'] ) ) : ?>
						<tr class="info"><td colspan="4"><?php echo esc_html( $it['name'] ); ?></td></tr>
					<?php else : ?>
						<tr><td><?php echo esc_html( $it['name'] ); ?><?php if ( ! empty( $it['sku'] ) ) : ?> <small>(<?php echo esc_html( $it['sku'] ); ?>)</small><?php endif; ?></td><td class="n"><?php echo esc_html( rtrim( rtrim( number_format( (float) $it['qty'], 2, ',', '' ), '0' ), ',' ) . ( $it['unit'] ? ' ' . $it['unit'] : '' ) ); ?></td><td class="n"><?php echo esc_html( jinv_money( $it['price'], $cur ) ); ?></td><td class="n"><?php echo esc_html( jinv_money( $it['total'], $cur ) ); ?></td></tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<section class="jinv-sum">
			<?php if ( empty( $sup['vat_payer'] ) ) : ?><p class="jinv-vat"><?php echo $e( $sup, 'vat_note' ); ?></p><?php endif; ?>
			<?php if ( ! empty( $d['proforma'] ) && 'issued' === $inv['kind'] ) : ?><p class="jinv-vat">Uhradené na základe zálohovej faktúry č. <?php echo $e( $d, 'proforma' ); ?>.</p><?php endif; ?>
			<p class="jinv-total"><span><?php echo 'creditnote' === $inv['kind'] ? 'Celkom na vrátenie' : 'Celkom na úhradu'; ?></span><strong><?php echo esc_html( jinv_money( $inv['total'], $cur ) ); ?></strong></p>
			<?php if ( 'paid' === $inv['status'] ) : ?><p class="jinv-paid"><?php echo 'creditnote' === $inv['kind'] ? 'Vrátené' : 'Uhradené'; ?><?php echo $inv['paid_at'] ? ' ' . esc_html( jinv_date( substr( $inv['paid_at'], 0, 10 ) ) ) : ''; ?><?php echo 'creditnote' === $inv['kind'] ? '' : ' – neuhrádzajte'; ?>.</p><?php endif; ?>
			<?php if ( 'cancelled' === $inv['status'] ) : ?><p class="jinv-paid jinv-cancel">Stornovaný doklad</p><?php endif; ?>
		</section>
		<footer class="jinv-foot">
			<?php if ( ! empty( $sup['issued_by'] ) ) : ?><p>Vystavil: <?php echo $e( $sup, 'issued_by' ); ?></p><?php endif; ?>
			<p><?php echo $e( $sup, 'footer' ); ?></p>
			<p class="jinv-small"><?php echo implode( ' · ', array_filter( array( $e( $sup, 'email' ), $e( $sup, 'phone' ), $e( $sup, 'web' ) ) ) ); // phpcs:ignore ?></p>
		</footer>
	</div>
	<?php
	return ob_get_clean();
}

/** CSS faktúry (spoločné pre náhľad v administrácii, tlač aj neskôr PDF). */
function jinv_invoice_css() {
	return '.jinv-doc{max-width:800px;margin:0 auto;padding:36px 40px;background:#fff;color:#1f2937;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;border:1px solid #dcdcde;border-radius:8px}'
		. '.jinv-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px}.jinv-logo{max-height:56px;max-width:220px}.jinv-title{text-align:right}.jinv-title h1{margin:0;font-size:26px;letter-spacing:.02em;color:#162D4F}.jinv-title p{margin:2px 0 0;font-size:15px}'
		. '.jinv-parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:18px}.jinv-parties h2{margin:0 0 6px;font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#6b7280}.jinv-parties p{margin:0 0 6px}'
		. '.jinv-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:8px 16px;padding:12px 14px;margin-bottom:18px;background:#f6f7f9;border-radius:6px}.jinv-meta span{display:block;font-size:11px;color:#6b7280}.jinv-meta .jinv-wide{grid-column:1/-1}.jinv-meta em{font-style:normal;color:#4b5563;margin-left:8px}'
		. '.jinv-items{width:100%;border-collapse:collapse;margin-bottom:14px}.jinv-items th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;border-bottom:2px solid #162D4F;padding:6px 4px}.jinv-items td{padding:7px 4px;border-bottom:1px solid #eef0f3;vertical-align:top}.jinv-items .n{text-align:right;white-space:nowrap}.jinv-items tr.info td{color:#6b7280;font-style:italic}'
		. '.jinv-sum{display:flex;flex-direction:column;align-items:flex-end;gap:4px;margin-bottom:24px}.jinv-total{display:flex;gap:18px;align-items:baseline;margin:0;padding:10px 14px;border-radius:6px;background:#162D4F;color:#fff}.jinv-total strong{font-size:20px}.jinv-vat{margin:0;color:#4b5563}.jinv-paid{margin:6px 0 0;padding:4px 10px;border:2px solid #145A2A;border-radius:4px;color:#145A2A;font-weight:700;text-transform:uppercase;letter-spacing:.05em}.jinv-cancel{border-color:#A3121B;color:#A3121B}'
		. '.jinv-lead{margin:0 0 12px;color:#374151}.jinv-foot{border-top:1px solid #eef0f3;padding-top:10px;color:#4b5563}.jinv-foot p{margin:0 0 4px}.jinv-small{font-size:11px;color:#6b7280}'
		. '@media print{body *{visibility:hidden}.jinv-doc,.jinv-doc *{visibility:visible}.jinv-doc{position:absolute;left:0;top:0;border:0;max-width:none;width:100%;padding:0}}';
}
