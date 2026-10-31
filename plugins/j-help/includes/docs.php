<?php
/**
 * Príručky doplnkov Jupiter (podrobná dokumentácia): podstránka J-Pomocník → Príručka … pre každý zdokumentovaný doplnok.
 * Každá príručka je súbor includes/docs/<doplnok>.php s funkciou, ktorá vráti kapitoly: [ id (kotva), názov, ikona, HTML ].
 * V HTML sa {ASSETS} nahradí adresou priečinka assets/ J-Pomocníka (obrázky a schémy).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Zdokumentované doplnky: slug doplnku => údaje príručky. */
function jhelp_docs() {
	$docs  = jhelp_docs_list();
	$order = function_exists( 'jhelp_menu_order' ) ? jhelp_menu_order() : array();
	// Rovnaké poradie ako doplnky v menu Jupiter (Pošta, Sklad, Objednávky, …).
	uksort( $docs, function ( $a, $b ) use ( $order, $docs ) {
		return array( $order[ $a ] ?? 500, $docs[ $a ]['name'] ) <=> array( $order[ $b ] ?? 500, $docs[ $b ]['name'] );
	} );
	return $docs;
}

function jhelp_docs_list() {
	return array(
		'j-emails' => array(
			'name'      => 'J-Pošta',
			'title'     => 'Jupiter Pošta',
			'fn'        => 'jhelp_doc_j_emails',
			'ver_const' => 'JEM_VERSION',
			'badge'     => 'Pošta obchodu v administrácii',
			'hero'      => 'img/j-emails-schema.svg',
			'hero_w'    => 1200,
			'hero_h'    => 620,
		),
		'j-orders' => array(
			'name'      => 'J-Objednávky',
			'title'     => 'J-Objednávky',
			'fn'        => 'jhelp_doc_j_orders',
			'ver_const' => 'JORD_VERSION',
			'badge'     => 'Objednávky a ich čísla',
			'hero'      => 'img/j-orders-schema.svg',
			'hero_w'    => 1200,
			'hero_h'    => 620,
		),
		'j-sklad'  => array(
			'name'      => 'J-Sklad',
			'title'     => 'J-Sklad',
			'fn'        => 'jhelp_doc_j_sklad',
			'ver_const' => 'JSK_VERSION',
			'badge'     => 'Sklad nad produktmi WooCommerce',
			'hero'      => 'img/j-sklad-schema.svg',
			'hero_w'    => 1200,
			'hero_h'    => 620,
		),
	);
}

function jhelp_doc_url( $slug ) {
	return isset( jhelp_docs()[ $slug ] ) ? admin_url( 'admin.php?page=j-help-doc-' . $slug ) : '';
}

add_action( 'admin_menu', function () {
	foreach ( jhelp_docs() as $slug => $d ) {
		// V menu Pomocníka len názov doplnku (J-Pošta), titulok stránky je Príručka J-Pošta.
		add_submenu_page( 'j-help', 'Príručka ' . $d['name'], $d['name'], 'manage_woocommerce', 'j-help-doc-' . $slug, 'jhelp_render_doc' );
	}
}, 11 );

function jhelp_render_doc() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
	$slug = substr( $page, strlen( 'j-help-doc-' ) );
	$docs = jhelp_docs();
	if ( ! isset( $docs[ $slug ] ) || ! preg_match( '/^j-[a-z0-9-]+$/', $slug ) ) {
		return;
	}
	$d = $docs[ $slug ];
	require_once JHELP_DIR . 'includes/docs/' . $slug . '.php';
	$chapters = function_exists( $d['fn'] ) ? call_user_func( $d['fn'] ) : array();
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$ver = ( ! empty( $d['ver_const'] ) && defined( $d['ver_const'] ) ) ? constant( $d['ver_const'] ) : '';
	foreach ( get_plugins() as $file => $data ) {
		if ( '' === $ver && dirname( $file ) === $slug ) {
			$ver = $data['Version'];
		}
	}
	$link   = jhelp_admin_link( $slug );
	$assets = JHELP_URL . 'assets/';
	?>
	<div class="wrap jhelp jhelp-doc">
		<h1>Príručka <span class="jhelp-ver">J-Pomocník</span></h1>
		<div class="jhelp-doc-hero">
			<span class="jhelp-doc-badge"><?php echo esc_html( $d['badge'] ); ?></span>
			<h2><?php echo esc_html( $d['title'] ); ?> <?php if ( $ver ) : ?><small>v<?php echo esc_html( $ver ); ?></small><?php endif; ?></h2>
			<p>Podrobná príručka doplnku <?php echo esc_html( $d['name'] ); ?>: čo robí, ako sa používa, nastavenia a riešenie problémov.</p>
			<?php if ( $link ) : ?><a class="button button-primary" href="<?php echo esc_url( $link ); ?>">Otvoriť <?php echo esc_html( $d['name'] ); ?> →</a><?php endif; ?>
		</div>
		<?php if ( ! empty( $d['hero'] ) ) : ?>
			<figure class="jhelp-doc-cover"><a href="<?php echo esc_url( $assets . $d['hero'] ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $assets . $d['hero'] ); ?>" alt="<?php echo esc_attr( $d['title'] . ': ako funguje, schéma' ); ?>" width="<?php echo (int) $d['hero_w']; ?>" height="<?php echo (int) $d['hero_h']; ?>"></a><figcaption><?php echo esc_html( $d['title'] ); ?> na jeden pohľad. Kliknite na obrázok pre plnú veľkosť.</figcaption></figure>
		<?php endif; ?>
		<div class="jhelp-doc-layout">
			<nav class="jhelp-toc" aria-label="Obsah príručky">
				<strong>Obsah</strong>
				<ol>
					<?php foreach ( $chapters as $c ) : ?><li><a href="#<?php echo esc_attr( $c[0] ); ?>"><?php echo esc_html( $c[1] ); ?></a></li><?php endforeach; ?>
				</ol>
			</nav>
			<div class="jhelp-doc-body">
				<?php foreach ( $chapters as $i => $c ) : ?>
					<section id="<?php echo esc_attr( $c[0] ); ?>" class="jhelp-chapter">
						<h2><span class="dashicons <?php echo esc_attr( $c[2] ); ?>" aria-hidden="true"></span><span class="jhelp-ch-n"><?php echo (int) $i + 1; ?></span><?php echo esc_html( $c[1] ); ?></h2>
						<?php echo wp_kses_post( str_replace( '{ASSETS}', $assets, $c[3] ) ); ?>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}
