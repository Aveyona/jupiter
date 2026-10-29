<?php
/**
 * Šablóny e-mailov a odosielanie podľa šablóny (aj pre iné doplnky, napr. nástroj „Rýchly e-mail“ cez My J-Connector).
 *
 * Šablóna = vzhľad e-mailu (HTML s premennými), do ktorej sa vloží text správy: {obsah}. Ďalšie premenné: {predmet},
 * {meno}, {odosielatel}, {web}, {web_url}, {datum}, {podpis}, {farba}. Každému príjemcovi ide vlastný e-mail (vidí len
 * seba, {meno} je jeho meno). Odoslané z nástrojov sa zapisujú do záznamu (J-Pošta → Šablóny).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Predvolené šablóny (dajú sa upraviť a obnoviť). */
function jem_default_templates() {
	$foot = '<tr><td style="padding:18px 32px 26px;border-top:1px solid #eceef1;font:12px/1.5 Arial,Helvetica,sans-serif;color:#8a9099">{podpis}<br><a href="{web_url}" style="color:#8a9099">{web}</a></td></tr>';
	return array(
		'moderna'    => array(
			'name'  => 'Moderná',
			'color' => '#b3121f',
			'html'  => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f3f6;padding:28px 12px"><tr><td align="center">'
				. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.06)">'
				. '<tr><td style="background:{farba};padding:22px 32px;font:800 22px/1.2 Arial,Helvetica,sans-serif;color:#ffffff;letter-spacing:.02em">{web}</td></tr>'
				. '<tr><td style="padding:28px 32px 6px;font:700 20px/1.35 Arial,Helvetica,sans-serif;color:#16181d">{predmet}</td></tr>'
				. '<tr><td style="padding:8px 32px 24px;font:15px/1.65 Arial,Helvetica,sans-serif;color:#2b2f36">{obsah}</td></tr>'
				. $foot . '</table></td></tr></table>',
		),
		'jednoducha' => array(
			'name'  => 'Jednoduchá',
			'color' => '#162d4f',
			'html'  => '<div style="max-width:620px;font:15px/1.6 Arial,Helvetica,sans-serif;color:#222">{obsah}<p style="margin:24px 0 0;color:#666;font-size:13px">{podpis}</p></div>',
		),
		'oznamenie'  => array(
			'name'  => 'Oznámenie',
			'color' => '#162d4f',
			'html'  => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f1726;padding:28px 12px"><tr><td align="center">'
				. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden">'
				. '<tr><td style="padding:26px 32px 4px;font:700 12px/1 Arial,Helvetica,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:{farba}">Oznámenie · {datum}</td></tr>'
				. '<tr><td style="padding:10px 32px 4px;font:800 24px/1.3 Arial,Helvetica,sans-serif;color:#16181d">{predmet}</td></tr>'
				. '<tr><td style="padding:4px 32px 0"><div style="height:4px;width:56px;background:{farba};border-radius:2px"></div></td></tr>'
				. '<tr><td style="padding:18px 32px 24px;font:15px/1.65 Arial,Helvetica,sans-serif;color:#2b2f36">{obsah}</td></tr>'
				. $foot . '</table></td></tr></table>',
		),
	);
}

function jem_templates() {
	$t = get_option( 'jem_templates', null );
	return is_array( $t ) && $t ? $t : jem_default_templates();
}

function jem_template_vars_help() {
	return array(
		'{obsah}'       => 'text správy (povinné)',
		'{predmet}'     => 'predmet e-mailu',
		'{meno}'        => 'meno príjemcu',
		'{odosielatel}' => 'meno toho, kto e-mail posiela',
		'{web}'         => 'názov webu',
		'{web_url}'     => 'adresa webu',
		'{datum}'       => 'dnešný dátum',
		'{podpis}'      => 'podpis zo Nastavení',
		'{farba}'       => 'farba šablóny',
	);
}

/** Text správy → HTML odseky (odkazy klikateľné). */
function jem_text_to_html( $text ) {
	$text  = str_replace( "\r\n", "\n", trim( (string) $text ) );
	$paras = preg_split( "/\n{2,}/", $text );
	$out   = '';
	foreach ( $paras as $p ) {
		if ( '' === trim( $p ) ) {
			continue;
		}
		$h    = nl2br( esc_html( $p ), false );
		$h    = preg_replace( '~(https?://[^\s<]+)~i', '<a href="$1" style="color:inherit">$1</a>', $h );
		$out .= '<p style="margin:0 0 14px">' . $h . '</p>';
	}
	return $out;
}

/** Vykreslí e-mail: šablóna + správa + premenné. */
function jem_template_render( $tid, $subject, $message, array $vars = array() ) {
	$all = jem_templates();
	$t   = isset( $all[ $tid ] ) ? $all[ $tid ] : reset( $all );
	$sig = (string) jem_opt( 'signature' );
	$rep = array_merge( array(
		'{predmet}'     => esc_html( $subject ),
		'{meno}'        => '',
		'{odosielatel}' => esc_html( wp_get_current_user()->display_name ),
		'{web}'         => esc_html( get_bloginfo( 'name' ) ),
		'{web_url}'     => esc_url( home_url( '/' ) ),
		'{datum}'       => esc_html( wp_date( 'j. n. Y' ) ),
		'{podpis}'      => '' !== trim( $sig ) ? nl2br( esc_html( $sig ), false ) : esc_html( get_bloginfo( 'name' ) ),
		'{farba}'       => esc_attr( preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $t['color'] ) ? $t['color'] : '#162d4f' ),
	), array_map( 'esc_html', $vars ) );
	// {meno} aj v texte správy (napr. „Dobrý deň, {meno},“).
	$message = str_replace( '{meno}', isset( $vars['{meno}'] ) ? $vars['{meno}'] : '', (string) $message );
	$html    = strtr( (string) $t['html'], $rep );
	return str_replace( '{obsah}', jem_text_to_html( $message ), $html );
}

/** Adresa, z ktorej sa odosiela: [ e-mail, meno ]. */
function jem_sender() {
	$s = jem_settings();
	return array( strtolower( (string) $s['email'] ), (string) $s['name'] );
}

/* ------------------------------------------------------------------------
 * Odosielanie podľa šablóny + záznam + limit
 * ---------------------------------------------------------------------- */

function jem_tool_limits() {
	return array(
		'per_hour'   => max( 1, (int) ( jem_opt( 'tool_per_hour' ) ? jem_opt( 'tool_per_hour' ) : 30 ) ),
		'recipients' => max( 1, (int) ( jem_opt( 'tool_recipients' ) ? jem_opt( 'tool_recipients' ) : 20 ) ),
	);
}

/** Koľko e-mailov poslal používateľ za poslednú hodinu (zo záznamu). */
function jem_tool_sent_last_hour( $user_id ) {
	$n = 0;
	foreach ( jem_tool_log() as $r ) {
		if ( (int) $r['user'] === (int) $user_id && 'ok' === $r['status'] && (int) $r['time'] > time() - HOUR_IN_SECONDS ) {
			$n++;
		}
	}
	return $n;
}

function jem_tool_log() {
	$l = get_option( 'jem_tool_log', array() );
	return is_array( $l ) ? $l : array();
}

function jem_tool_log_add( array $row ) {
	$l = jem_tool_log();
	array_unshift( $l, $row );
	update_option( 'jem_tool_log', array_slice( $l, 0, 500 ), false );
}

/**
 * Odošle e-mail podľa šablóny každému príjemcovi zvlášť.
 *
 * @param array $a to ([[meno, e-mail], …] alebo reťazec „Meno <a@b.sk>, c@d.sk“), subject, message, template, source (napr. 'nástroj').
 * @return array [ 'sent' => n, 'errors' => [ e-mail => chyba ] ] alebo WP_Error (nič neodoslané).
 */
function jem_send_template( array $a ) {
	if ( ! jem_configured() ) {
		return new WP_Error( 'jem_config', 'E-mailová schránka (J-Pošta) nie je nastavená.' );
	}
	$to = is_array( $a['to'] ?? null ) ? $a['to'] : jem_recipients( (string) ( $a['to'] ?? '' ) );
	$to = array_values( array_filter( $to, function ( $r ) {
		return is_array( $r ) && is_email( $r[1] ?? '' );
	} ) );
	$subject = trim( sanitize_text_field( (string) ( $a['subject'] ?? '' ) ) );
	$message = trim( (string) ( $a['message'] ?? '' ) );
	$lim     = jem_tool_limits();
	$uid     = get_current_user_id();
	if ( ! $to ) {
		return new WP_Error( 'jem_to', 'Zadaj aspoň jedného platného príjemcu.' );
	}
	if ( count( $to ) > $lim['recipients'] ) {
		return new WP_Error( 'jem_many', 'Naraz sa dá poslať najviac ' . $lim['recipients'] . ' príjemcom.' );
	}
	if ( '' === $subject || '' === $message ) {
		return new WP_Error( 'jem_empty', 'Vyplň predmet aj text správy.' );
	}
	if ( jem_tool_sent_last_hour( $uid ) + count( $to ) > $lim['per_hour'] ) {
		return new WP_Error( 'jem_limit', 'Limit je ' . $lim['per_hour'] . ' e-mailov za hodinu – skús to neskôr.' );
	}
	$tid    = sanitize_key( (string) ( $a['template'] ?? '' ) );
	$sent   = 0;
	$errors = array();
	foreach ( $to as $r ) {
		$name = trim( (string) ( $r[0] ?? '' ) );
		$html = jem_template_render( $tid, $subject, $message, array( '{meno}' => $name ) );
		$res  = jem_send( array(
			'to'      => ( '' !== $name ? '"' . str_replace( '"', '', $name ) . '" ' : '' ) . '<' . $r[1] . '>',
			'subject' => $subject,
			'html'    => $html,
		) );
		jem_tool_log_add( array(
			'time'     => time(),
			'user'     => $uid,
			'to'       => $r[1],
			'name'     => $name,
			'subject'  => $subject,
			'template' => $tid,
			'message'  => mb_substr( $message, 0, 2000 ),
			'source'   => sanitize_text_field( (string) ( $a['source'] ?? '' ) ),
			'status'   => true === $res ? 'ok' : 'error',
			'error'    => true === $res ? '' : (string) $res,
		) );
		if ( true === $res ) {
			$sent++;
		} else {
			$errors[ $r[1] ] = (string) $res;
		}
	}
	return array( 'sent' => $sent, 'errors' => $errors );
}

/* ------------------------------------------------------------------------
 * Administrácia: J-Pošta → Šablóny
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'j-emails', 'Šablóny – Jupiter Pošta', 'Šablóny', JEM_CAP, 'j-emails-templates', 'jem_render_templates' );
}, 20 );

add_action( 'admin_post_jem_templates', function () {
	if ( ! current_user_can( JEM_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jem_templates' );
	$all = jem_templates();
	$do  = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
	$id  = isset( $_POST['id'] ) ? sanitize_key( $_POST['id'] ) : '';
	if ( 'reset' === $do ) {
		delete_option( 'jem_templates' );
		wp_safe_redirect( admin_url( 'admin.php?page=j-emails-templates&msg=reset' ) );
		exit;
	}
	if ( 'delete' === $do && isset( $all[ $id ] ) && count( $all ) > 1 ) {
		unset( $all[ $id ] );
		update_option( 'jem_templates', $all, false );
		wp_safe_redirect( admin_url( 'admin.php?page=j-emails-templates&msg=deleted' ) );
		exit;
	}
	if ( 'limits' === $do ) {
		$s                    = jem_settings();
		$s['tool_per_hour']   = max( 1, min( 1000, (int) ( $_POST['tool_per_hour'] ?? 30 ) ) );
		$s['tool_recipients'] = max( 1, min( 200, (int) ( $_POST['tool_recipients'] ?? 20 ) ) );
		update_option( 'jem_settings', $s );
		wp_safe_redirect( admin_url( 'admin.php?page=j-emails-templates&msg=saved' ) );
		exit;
	}
	// Uloženie (nová alebo existujúca).
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$html = (string) wp_unslash( $_POST['html'] ?? '' );
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$html = wp_kses_post( $html );
	}
	if ( '' === $name || false === strpos( $html, '{obsah}' ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=j-emails-templates&edit=' . rawurlencode( $id ) . '&msg=invalid' ) );
		exit;
	}
	if ( '' === $id || ! isset( $all[ $id ] ) ) {
		$id = sanitize_key( sanitize_title( $name ) );
		$id = '' === $id ? 'sablona' : $id;
		$base = $id;
		for ( $i = 2; isset( $all[ $id ] ); $i++ ) {
			$id = $base . '-' . $i;
		}
	}
	$color      = sanitize_hex_color( wp_unslash( $_POST['color'] ?? '' ) );
	$all[ $id ] = array( 'name' => $name, 'color' => $color ? $color : '#162d4f', 'html' => $html );
	update_option( 'jem_templates', $all, false );
	wp_safe_redirect( admin_url( 'admin.php?page=j-emails-templates&edit=' . rawurlencode( $id ) . '&msg=saved' ) );
	exit;
} );

function jem_render_templates() {
	if ( ! current_user_can( JEM_CAP ) ) {
		return;
	}
	$all  = jem_templates();
	$edit = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore
	$new  = isset( $_GET['new'] ); // phpcs:ignore
	$msg  = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore
	$msgs = array( 'saved' => array( 'success', 'Uložené.' ), 'deleted' => array( 'success', 'Šablóna je vymazaná.' ), 'reset' => array( 'success', 'Obnovené predvolené šablóny.' ), 'invalid' => array( 'error', 'Šablóna musí mať názov a v HTML premennú {obsah}.' ) );
	$cur  = $new ? array( 'name' => '', 'color' => '#162d4f', 'html' => jem_default_templates()['moderna']['html'] ) : ( isset( $all[ $edit ] ) ? $all[ $edit ] : null );
	$demo = "Dobrý deň, {meno},\n\nďakujeme za záujem. Toto je ukážka textu správy – takto bude vyzerať e-mail s touto šablónou.\n\nS pozdravom";
	$lim  = jem_tool_limits();
	?>
	<div class="wrap jem-tpl">
		<h1>Šablóny e-mailov <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=j-emails-templates&new=1' ) ); ?>">Pridať šablónu</a></h1>
		<?php if ( isset( $msgs[ $msg ] ) ) : ?><div class="notice notice-<?php echo esc_attr( $msgs[ $msg ][0] ); ?> is-dismissible"><p><?php echo esc_html( $msgs[ $msg ][1] ); ?></p></div><?php endif; ?>
		<p class="description">Šablóna je vzhľad e-mailu – text správy sa do nej vloží na miesto <code>{obsah}</code>. Používa ich nástroj „Rýchly e-mail“ na webe (My J-Connector) a môžu ich používať aj iné doplnky.</p>

		<?php if ( $cur ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jem-tpl-edit">
			<?php wp_nonce_field( 'jem_templates' ); ?>
			<input type="hidden" name="action" value="jem_templates"><input type="hidden" name="id" value="<?php echo esc_attr( $new ? '' : $edit ); ?>">
			<div class="jem-tpl-cols">
				<div>
					<p><label>Názov<br><input type="text" name="name" class="regular-text" value="<?php echo esc_attr( $cur['name'] ); ?>" required></label></p>
					<p><label>Farba <input type="color" name="color" value="<?php echo esc_attr( $cur['color'] ); ?>"></label></p>
					<p><label>HTML šablóny<br><textarea name="html" rows="18" class="large-text code" id="jem-tpl-html"><?php echo esc_textarea( $cur['html'] ); ?></textarea></label></p>
					<p class="description">Premenné: <?php foreach ( jem_template_vars_help() as $k => $l ) { echo '<code>' . esc_html( $k ) . '</code> ' . esc_html( $l ) . ' · '; } ?></p>
					<p><?php submit_button( 'Uložiť šablónu', 'primary', 'submit', false ); ?> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=j-emails-templates' ) ); ?>">Späť</a></p>
				</div>
				<div><strong>Náhľad</strong><iframe class="jem-tpl-frame" title="Náhľad" srcdoc="<?php echo esc_attr( jem_template_render( $new ? 'moderna' : $edit, 'Predmet e-mailu', $demo, array( '{meno}' => 'Ján Novák' ) ) ); ?>"></iframe><p class="description">Náhľad sa obnoví po uložení.</p></div>
			</div>
		</form>
		<?php endif; ?>

		<div class="jem-tpl-grid">
			<?php foreach ( $all as $id => $t ) : ?>
				<div class="jem-tpl-card">
					<iframe class="jem-tpl-mini" title="<?php echo esc_attr( $t['name'] ); ?>" srcdoc="<?php echo esc_attr( jem_template_render( $id, 'Predmet e-mailu', $demo, array( '{meno}' => 'Ján Novák' ) ) ); ?>" tabindex="-1"></iframe>
					<div class="jem-tpl-foot"><span class="jem-tpl-dot" style="background:<?php echo esc_attr( $t['color'] ); ?>"></span><strong><?php echo esc_html( $t['name'] ); ?></strong>
						<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=j-emails-templates&edit=' . rawurlencode( $id ) ) ); ?>">Upraviť</a>
						<?php if ( count( $all ) > 1 ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Vymazať šablónu?');"><?php wp_nonce_field( 'jem_templates' ); ?><input type="hidden" name="action" value="jem_templates"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>"><button class="button-link jem-tpl-del">Vymazať</button></form>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Obnoviť predvolené šablóny? Vlastné úpravy sa stratia.');" style="margin-top:10px"><?php wp_nonce_field( 'jem_templates' ); ?><input type="hidden" name="action" value="jem_templates"><input type="hidden" name="do" value="reset"><button class="button-link">Obnoviť predvolené šablóny</button></form>

		<h2>Limity pre nástroje</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jem_templates' ); ?><input type="hidden" name="action" value="jem_templates"><input type="hidden" name="do" value="limits">
			Jeden používateľ najviac <input type="number" min="1" max="1000" class="small-text" name="tool_per_hour" value="<?php echo (int) $lim['per_hour']; ?>"> e-mailov za hodinu, naraz najviac <input type="number" min="1" max="200" class="small-text" name="tool_recipients" value="<?php echo (int) $lim['recipients']; ?>"> príjemcov.
			<?php submit_button( 'Uložiť', 'secondary', 'submit', false ); ?>
		</form>

		<h2>Odoslané z nástrojov</h2>
		<?php $log = array_slice( jem_tool_log(), 0, 100 ); ?>
		<?php if ( ! $log ) : ?>
			<p class="description">Zatiaľ nič.</p>
		<?php else : ?>
		<table class="widefat striped"><thead><tr><th>Kedy</th><th>Kto</th><th>Komu</th><th>Predmet</th><th>Šablóna</th><th>Stav</th></tr></thead><tbody>
			<?php foreach ( $log as $r ) : $u = get_userdata( (int) $r['user'] ); ?>
				<tr><td><?php echo esc_html( wp_date( 'j. n. Y H:i', (int) $r['time'] ) ); ?></td><td><?php echo esc_html( $u ? $u->display_name : '#' . (int) $r['user'] ); ?></td><td><?php echo esc_html( trim( $r['name'] . ' <' . $r['to'] . '>' ) ); ?></td><td><?php echo esc_html( $r['subject'] ); ?></td><td><?php echo esc_html( isset( $all[ $r['template'] ] ) ? $all[ $r['template'] ]['name'] : $r['template'] ); ?></td><td><?php echo 'ok' === $r['status'] ? '<span style="color:#00a32a">odoslané</span>' : '<span style="color:#d63638" title="' . esc_attr( $r['error'] ) . '">chyba</span>'; ?></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<?php endif; ?>
		<style>
		.jem-tpl-cols{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:24px;margin:16px 0 26px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:10px}
		.jem-tpl-frame{display:block;width:100%;height:520px;margin-top:6px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
		.jem-tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-top:16px}
		.jem-tpl-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;overflow:hidden}
		.jem-tpl-mini{display:block;width:200%;height:560px;border:0;transform:scale(.5);transform-origin:0 0;margin-bottom:-280px;pointer-events:none}
		.jem-tpl-foot{display:flex;align-items:center;gap:8px;padding:10px 12px;border-top:1px solid #f0f0f1}.jem-tpl-foot strong{flex:1}.jem-tpl-foot form{margin:0}
		.jem-tpl-dot{width:12px;height:12px;border-radius:50%}.jem-tpl-del{color:#b32d2e!important}
		@media(max-width:960px){.jem-tpl-cols{grid-template-columns:1fr}}
		</style>
	</div>
	<?php
}
