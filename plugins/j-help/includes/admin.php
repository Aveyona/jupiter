<?php
/**
 * Stránka J-Pomocník: Prehľad doplnkov, Návody, Plán vývoja.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-help' ) ) {
		wp_enqueue_style( 'j-help-admin', JHELP_URL . 'assets/admin.css', array(), JHELP_VERSION );
	}
} );

function jhelp_tabs() {
	return array(
		'overview' => 'Prehľad',
		'guides'   => 'Návody',
		'roadmap'  => 'Plán vývoja',
	);
}

/** Odkaz na administráciu doplnku (prvá položka menu, ktorej slug začína slugom doplnku). */
function jhelp_admin_link( $slug ) {
	global $menu;
	foreach ( (array) $menu as $item ) {
		if ( isset( $item[2] ) && 0 === strpos( (string) $item[2], $slug ) ) {
			return admin_url( 'admin.php?page=' . $item[2] );
		}
	}
	return '';
}

/** Každá podpoložka J-Pomocníka je samostatná stránka: overview (Prehľad), guides (Návody), roadmap (Plán vývoja). */
function jhelp_render_admin( $tab = 'overview' ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$tab = is_string( $tab ) && isset( jhelp_tabs()[ $tab ] ) ? $tab : 'overview';
	?>
	<div class="wrap jhelp">
		<h1><?php echo esc_html( jhelp_tabs()[ $tab ] ); ?> <span class="jhelp-ver">J-Pomocník</span></h1>
		<?php
		if ( 'guides' === $tab ) {
			jhelp_tab_guides();
		} elseif ( 'roadmap' === $tab ) {
			jhelp_tab_roadmap();
		} else {
			jhelp_tab_overview();
		}
		?>
	</div>
	<?php
}

function jhelp_tab_overview() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = array();
	foreach ( get_plugins() as $file => $data ) {
		$installed[ dirname( $file ) ] = array( 'version' => $data['Version'], 'active' => is_plugin_active( $file ) );
	}
	$plugins  = jhelp_plugins();
	$statuses = jhelp_statuses();
	$active   = count( array_filter( array_keys( $plugins ), function ( $s ) use ( $installed ) {
		return ! empty( $installed[ $s ]['active'] );
	} ) );
	?>
	<div class="jhelp-intro">
		<h2>Doplnky Jupiter</h2>
		<p><strong>Jupiter</strong> je rodina otvorených doplnkov pre <strong>WooCommerce</strong> pre malé slovenské e-shopy – faktúry, sklad a jednoduché účtovníctvo. Všetky doplnky začínajú písmenom <strong>J</strong> a v menu administrácie sú spolu hneď pod položkou <strong>Marketing</strong>.</p>
		<p>Zdrojový kód je verejný na GitHube: <a href="https://github.com/Aveyona/jupiter" target="_blank" rel="noopener">github.com/Aveyona/jupiter</a> (licencia GPL-2.0-or-later).</p>
		<p class="jhelp-count"><span><?php echo (int) count( $plugins ); ?></span> doplnkov · <span><?php echo (int) $active; ?></span> zapnutých</p>
	</div>
	<div class="jhelp-grid">
		<?php foreach ( $plugins as $slug => $p ) : $inst = isset( $installed[ $slug ] ) ? $installed[ $slug ] : null; $link = $inst && $inst['active'] && 'j-help' !== $slug ? jhelp_admin_link( $slug ) : ''; ?>
			<article class="jhelp-card<?php echo $inst && $inst['active'] ? '' : ' is-off'; ?>">
				<header>
					<span class="dashicons <?php echo esc_attr( $p['icon'] ); ?>" aria-hidden="true"></span>
					<h3><?php echo esc_html( $p['name'] ); ?></h3>
					<?php if ( $inst ) : ?>
						<span class="jhelp-ver">v<?php echo esc_html( $inst['version'] ); ?></span>
						<span class="jhelp-state <?php echo $inst['active'] ? 'is-on' : ''; ?>"><?php echo $inst['active'] ? 'zapnutý' : 'vypnutý'; ?></span>
					<?php else : ?>
						<span class="jhelp-state"><?php echo esc_html( isset( $statuses[ $p['status'] ] ) ? $statuses[ $p['status'] ] : 'nenainštalovaný' ); ?></span>
					<?php endif; ?>
				</header>
				<p><?php echo esc_html( $p['what'] ); ?></p>
				<?php if ( $link ) : ?><a class="jhelp-link" href="<?php echo esc_url( $link ); ?>">Otvoriť →</a><?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

function jhelp_tab_guides() {
	$guides = array(
		array(
			'Inštalácia doplnku Jupiter',
			array(
				'Stiahni ZIP doplnku z GitHubu (Releases) – napr. j-invoices-v1.0.0.zip.',
				'Vo WordPresse otvor Pluginy → Pridať plugin → Nahrať plugin a vyber ZIP.',
				'Doplnok zapni. Doplnky Jupiter potrebujú zapnutý WooCommerce.',
				'Doplnok sa objaví v menu pod položkou Marketing a v prehľade J-Pomocník.',
			),
		),
		array(
			'Aktualizácia na novú verziu',
			array(
				'Pred aktualizáciou si urob zálohu webu (súbory aj databázu).',
				'Nahraj nový ZIP rovnako ako pri inštalácii – WordPress ponúkne nahradenie staršej verzie.',
				'Údaje doplnku (napr. faktúry) ostávajú v databáze, aktualizácia ich nemení.',
			),
		),
		array(
			'Kde sú doplnky v menu',
			array(
				'Všetky doplnky Jupiter sú spolu hneď pod položkou WooCommerce Marketing.',
				'Doplnky sú zoradené podľa abecedy, J-Pomocník je vždy posledný.',
				'Každá podpoložka doplnku (napr. Vydané faktúry, Prijaté faktúry, Nastavenia) je samostatná stránka v menu.',
				'Doplnky vidí používateľ s oprávnením spravovať WooCommerce (Správca obchodu alebo Administrátor).',
			),
		),
		array(
			'Nahlásenie chyby alebo návrh',
			array(
				'Chybu alebo nápad zapíš na GitHube ako Issue: github.com/Aveyona/jupiter/issues.',
				'Uveď verziu doplnku, WordPressu a WooCommerce a postup, ako chybu zopakovať.',
			),
		),
	);
	/** Návody doplnkov Jupiter: array( názov, kroky[], doplnok ). Každý J-doplnok pridáva svoje cez filter jupiter_guides. */
	$guides = apply_filters( 'jupiter_guides', $guides );
	?>
	<div class="jhelp-intro">
		<h2>Návody</h2>
		<p>Stručné postupy pre prácu s doplnkami Jupiter. Návody jednotlivých doplnkov (napr. J-Fakturácia) sa zobrazia, keď je doplnok zapnutý. Funkcie označené „(pripravuje sa)“ ešte nie sú hotové.</p>
	</div>
	<div class="jhelp-grid">
		<?php foreach ( $guides as $g ) : ?>
			<article class="jhelp-card">
				<header><span class="dashicons dashicons-book" aria-hidden="true"></span><h3><?php echo esc_html( $g[0] ); ?></h3><?php if ( ! empty( $g[2] ) ) : ?><span class="jhelp-state"><?php echo esc_html( $g[2] ); ?></span><?php endif; ?></header>
				<ol class="jhelp-steps">
					<?php foreach ( $g[1] as $step ) : $soon = false !== strpos( $step, '(pripravuje sa)' ); ?><li<?php echo $soon ? ' class="is-soon"' : ''; ?>><?php echo esc_html( $step ); ?></li><?php endforeach; ?>
				</ol>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

function jhelp_tab_roadmap() {
	$labels = array( 'done' => 'hotovo', 'now' => 'prebieha', 'next' => 'plánované' );
	$steps  = jhelp_roadmap();
	?>
	<div class="jhelp-intro">
		<h2>Plán vývoja</h2>
		<p>Etapy, v ktorých Jupiter vzniká – od základu cez faktúry až po sklad. Každá etapa sa najprv otestuje na skúšobnom webe a až potom vydá.</p>
	</div>
	<ol class="jhelp-ms">
		<?php foreach ( $steps as $i => $s ) : ?>
			<li class="is-<?php echo esc_attr( $s['state'] ); ?>">
				<span class="jhelp-ms-dot" aria-hidden="true"><?php echo (int) $i + 1; ?></span>
				<div class="jhelp-ms-card">
					<header><span class="jhelp-ms-n"><?php echo (int) $i + 1; ?>. etapa</span><h3><?php echo esc_html( $s['title'] ); ?></h3><span class="jhelp-ms-when"><?php echo esc_html( $labels[ $s['state'] ] ); ?></span></header>
					<ul>
						<?php foreach ( $s['points'] as $pt ) : ?><li><?php echo esc_html( $pt ); ?></li><?php endforeach; ?>
					</ul>
				</div>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
}
