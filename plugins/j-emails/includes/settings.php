<?php
/** Nastavenia schránky (každý web má vlastné). Heslo je uložené zašifrované kľúčom webu. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jem_defaults() {
	return array(
		'email'        => '',
		'name'         => get_bloginfo( 'name' ),
		'user'         => '',
		'pass'         => '',
		'imap_host'    => 'imap.websupport.sk',
		'imap_port'    => 993,
		'imap_secure'  => 'ssl',
		'smtp_host'    => 'smtp.websupport.sk',
		'smtp_port'    => 465,
		'smtp_secure'  => 'ssl',
		'sent_folder'  => '',
		'trash_folder' => '',
		'per_page'     => 30,
		'signature'    => '',
		'site_mail'    => 1,
	);
}

function jem_settings() {
	$s = get_option( 'jem_settings', array() );
	return array_merge( jem_defaults(), is_array( $s ) ? $s : array() );
}

function jem_opt( $k ) {
	$s = jem_settings();
	return isset( $s[ $k ] ) ? $s[ $k ] : '';
}

/** Kľúč na šifrovanie hesla – odvodený z kľúčov v wp-config.php (každý web iný). */
function jem_key() {
	return hash( 'sha256', wp_salt( 'auth' ) . '|j-emails', true );
}

function jem_encrypt( $plain ) {
	if ( '' === (string) $plain ) {
		return '';
	}
	$iv  = random_bytes( 12 );
	$tag = '';
	$ct  = openssl_encrypt( (string) $plain, 'aes-256-gcm', jem_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return 'v1:' . base64_encode( $iv . $tag . $ct );
}

function jem_decrypt( $stored ) {
	if ( 0 !== strpos( (string) $stored, 'v1:' ) ) {
		return '';
	}
	$bin = base64_decode( substr( $stored, 3 ) );
	if ( strlen( $bin ) < 29 ) {
		return '';
	}
	$p = openssl_decrypt( substr( $bin, 28 ), 'aes-256-gcm', jem_key(), OPENSSL_RAW_DATA, substr( $bin, 0, 12 ), substr( $bin, 12, 16 ) );
	return false === $p ? '' : $p;
}

/** Je schránka nastavená? */
function jem_configured() {
	$s = jem_settings();
	return '' !== $s['email'] && '' !== $s['imap_host'] && '' !== $s['pass'];
}

/** Pripojenie k schránke s nastaveniami webu. */
function jem_connect() {
	$s    = jem_settings();
	$imap = new JEM_Imap();
	$user = '' !== $s['user'] ? $s['user'] : $s['email'];
	if ( ! $imap->open( $s['imap_host'], (int) $s['imap_port'], $s['imap_secure'], $user, jem_decrypt( $s['pass'] ) ) ) {
		return new WP_Error( 'jem_imap', $imap->error );
	}
	return $imap;
}

/**
 * Priečinky Odoslané a Kôš: podľa nastavenia, inak podľa príznakov servera (\Sent, \Trash), inak podľa názvu.
 *
 * @return array{inbox:string,sent:string,trash:string}
 */
function jem_folder_map( JEM_Imap $imap ) {
	$cached = get_transient( 'jem_folders' );
	if ( ! is_array( $cached ) ) {
		$cached = array( 'inbox' => 'INBOX', 'sent' => '', 'trash' => '' );
		$list   = $imap->folders();
		$names  = array(
			'sent'  => array( 'sent', 'inbox.sent', 'sent items', 'sent messages', 'odoslané', 'odoslane', 'inbox.odoslane' ),
			'trash' => array( 'trash', 'inbox.trash', 'deleted items', 'deleted messages', 'kôš', 'kos', 'inbox.kos' ),
		);
		foreach ( $list as $name => $flags ) {
			$fl = array_map( 'strtolower', $flags );
			if ( '' === $cached['sent'] && in_array( '\\sent', $fl, true ) ) {
				$cached['sent'] = $name;
			}
			if ( '' === $cached['trash'] && in_array( '\\trash', $fl, true ) ) {
				$cached['trash'] = $name;
			}
		}
		foreach ( array( 'sent', 'trash' ) as $k ) {
			if ( '' !== $cached[ $k ] ) {
				continue;
			}
			foreach ( $list as $name => $flags ) {
				if ( in_array( strtolower( $name ), $names[ $k ], true ) ) {
					$cached[ $k ] = $name;
					break;
				}
			}
		}
		// Nová schránka nemusí mať priečinky Odoslané a Kôš – vytvoria sa (najprv „Sent“, potom „INBOX.Sent“ pre servery s predponou).
		foreach ( array( 'sent' => 'Sent', 'trash' => 'Trash' ) as $k => $name ) {
			if ( '' !== $cached[ $k ] ) {
				continue;
			}
			foreach ( array( $name, 'INBOX.' . $name, 'INBOX/' . $name ) as $try ) {
				if ( $imap->create( $try ) ) {
					$cached[ $k ] = $try;
					break;
				}
			}
		}
		$cached['all'] = array_keys( $list );
		set_transient( 'jem_folders', $cached, DAY_IN_SECONDS );
	}
	$s = jem_settings();
	if ( '' !== $s['sent_folder'] ) {
		$cached['sent'] = $s['sent_folder'];
	}
	if ( '' !== $s['trash_folder'] ) {
		$cached['trash'] = $s['trash_folder'];
	}
	return $cached;
}

/** Uloženie nastavení. */
add_action( 'admin_post_jem_save_settings', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'jem_settings' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$old = jem_settings();
	$in  = wp_unslash( $_POST['jem'] ?? array() );
	$new = $old;
	foreach ( array( 'email', 'name', 'user', 'imap_host', 'smtp_host', 'sent_folder', 'trash_folder' ) as $k ) {
		$new[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
	}
	$new['email'] = sanitize_email( $new['email'] );
	foreach ( array( 'imap_port', 'smtp_port', 'per_page' ) as $k ) {
		$new[ $k ] = max( 1, (int) ( $in[ $k ] ?? 0 ) );
	}
	$new['per_page'] = min( 100, $new['per_page'] );
	foreach ( array( 'imap_secure', 'smtp_secure' ) as $k ) {
		$new[ $k ] = in_array( $in[ $k ] ?? '', array( 'ssl', 'tls', 'none' ), true ) ? $in[ $k ] : 'ssl';
	}
	$new['signature'] = sanitize_textarea_field( $in['signature'] ?? '' );
	$new['site_mail'] = empty( $in['site_mail'] ) ? 0 : 1;
	if ( isset( $in['pass'] ) && '' !== $in['pass'] ) {
		$new['pass'] = jem_encrypt( $in['pass'] );
	}
	if ( ! empty( $in['pass_clear'] ) ) {
		$new['pass'] = '';
	}
	update_option( 'jem_settings', $new, false );
	delete_transient( 'jem_folders' );
	delete_transient( 'jem_unseen' );
	wp_safe_redirect( admin_url( 'admin.php?page=j-emails-settings&saved=1' ) );
	exit;
} );

/** Skúšobný e-mail cez wp_mail (ako e-maily webu a e-shopu) do vlastnej schránky. */
add_action( 'admin_post_jem_test_mail', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'jem_test_mail' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$to      = (string) jem_opt( 'email' );
	$headers = array();
	$woo     = (string) get_option( 'woocommerce_email_from_address' );
	if ( is_email( $woo ) ) {
		$headers[] = 'From: ' . wp_specialchars_decode( (string) get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) ) ) . ' <' . $woo . '>';
	}
	$err = '';
	$cb  = function ( $e ) use ( &$err ) {
		$err = $e->get_error_message();
	};
	add_action( 'wp_mail_failed', $cb );
	$ok = wp_mail( $to, 'Skúšobný e-mail z webu ' . wp_parse_url( home_url(), PHP_URL_HOST ), "Tento e-mail poslal web cez J-Emails, rovnako ako e-maily objednávok.\n\nOdoslané: " . wp_date( 'j. n. Y H:i' ), $headers );
	remove_action( 'wp_mail_failed', $cb );
	set_transient( 'jem_test_' . get_current_user_id(), array( $ok ? array( 'ok', 'Skúšobný e-mail je odoslaný na ' . $to . ( $headers ? ' (odosielateľ ' . $woo . ')' : '' ) . '. Po chvíli ho uvidíte v Doručenej pošte.' ) : array( 'err', 'Skúšobný e-mail sa nepodarilo odoslať: ' . $err ) ), 300 );
	wp_safe_redirect( admin_url( 'admin.php?page=j-emails-settings&tested=1' ) );
	exit;
} );

/** Test pripojenia: IMAP aj SMTP. */
add_action( 'admin_post_jem_test', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'jem_test' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$res  = array();
	$imap = jem_connect();
	if ( is_wp_error( $imap ) ) {
		$res[] = array( 'err', 'Prijímanie (IMAP): ' . $imap->get_error_message() );
	} else {
		delete_transient( 'jem_folders' );
		$f     = jem_folder_map( $imap );
		$n     = $imap->select( 'INBOX', true );
		$res[] = array( 'ok', 'Prijímanie (IMAP): pripojené, v Doručenej pošte je ' . (int) $n . ' správ. Odoslané: „' . ( $f['sent'] ? $f['sent'] : 'nenájdené' ) . '“, Kôš: „' . ( $f['trash'] ? $f['trash'] : 'nenájdený' ) . '“. Priečinky v schránke: ' . implode( ', ', isset( $f['all'] ) ? $f['all'] : array() ) . '.' );
		$imap->close();
	}
	$smtp  = jem_smtp_test();
	$res[] = true === $smtp ? array( 'ok', 'Odosielanie (SMTP): prihlásenie úspešné.' ) : array( 'err', 'Odosielanie (SMTP): ' . $smtp );
	set_transient( 'jem_test_' . get_current_user_id(), $res, 300 );
	wp_safe_redirect( admin_url( 'admin.php?page=j-emails-settings&tested=1' ) );
	exit;
} );

function jem_render_settings() {
	$s     = jem_settings();
	$field = function ( $k, $label, $type = 'text', $help = '' ) use ( $s ) {
		printf(
			'<tr><th><label for="jem-%1$s">%2$s</label></th><td><input type="%3$s" id="jem-%1$s" name="jem[%1$s]" value="%4$s" class="regular-text" autocomplete="off">%5$s</td></tr>',
			esc_attr( $k ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( (string) $s[ $k ] ),
			$help ? '<p class="description">' . esc_html( $help ) . '</p>' : ''
		);
	};
	$secure = function ( $k ) use ( $s ) {
		echo '<select name="jem[' . esc_attr( $k ) . ']">';
		foreach ( array( 'ssl' => 'SSL/TLS', 'tls' => 'STARTTLS', 'none' => 'bez šifrovania' ) as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $s[ $k ], $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	};
	$tested = get_transient( 'jem_test_' . get_current_user_id() );
	if ( isset( $_GET['tested'] ) && is_array( $tested ) ) { // phpcs:ignore
		foreach ( $tested as $t ) {
			echo '<div class="notice notice-' . ( 'ok' === $t[0] ? 'success' : 'error' ) . '"><p>' . esc_html( $t[1] ) . '</p></div>';
		}
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jem-panel">
		<input type="hidden" name="action" value="jem_save_settings">
		<?php wp_nonce_field( 'jem_settings' ); ?>
		<h2>Schránka</h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'email', 'E-mailová adresa', 'email', 'Adresa schránky, napr. info@hedlajner.sk. Z nej sa odosielajú odpovede.' );
			$field( 'name', 'Meno odosielateľa', 'text', 'Zobrazí sa príjemcovi pri adrese.' );
			$field( 'user', 'Prihlasovacie meno', 'text', 'Ak je prázdne, použije sa e-mailová adresa.' );
			?>
			<tr><th><label for="jem-pass">Heslo schránky</label></th><td>
				<input type="password" id="jem-pass" name="jem[pass]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo '' !== $s['pass'] ? esc_attr( '•••••••• (uložené)' ) : ''; ?>">
				<p class="description">Heslo je uložené zašifrované. Ak pole necháte prázdne, ostane pôvodné.</p>
				<?php if ( '' !== $s['pass'] ) : ?><label><input type="checkbox" name="jem[pass_clear]" value="1"> Zmazať uložené heslo</label><?php endif; ?>
			</td></tr>
		</table>
		<h2>Prijímanie (IMAP)</h2>
		<table class="form-table" role="presentation">
			<?php $field( 'imap_host', 'Server' ); ?>
			<tr><th>Port a šifrovanie</th><td><input type="number" name="jem[imap_port]" value="<?php echo esc_attr( (string) $s['imap_port'] ); ?>" class="small-text"> <?php $secure( 'imap_secure' ); ?></td></tr>
			<?php
			$field( 'sent_folder', 'Priečinok Odoslané', 'text', 'Nechajte prázdne – nájde sa automaticky.' );
			$field( 'trash_folder', 'Priečinok Kôš', 'text', 'Nechajte prázdne – nájde sa automaticky.' );
			?>
		</table>
		<h2>Odosielanie (SMTP)</h2>
		<table class="form-table" role="presentation">
			<?php $field( 'smtp_host', 'Server' ); ?>
			<tr><th>Port a šifrovanie</th><td><input type="number" name="jem[smtp_port]" value="<?php echo esc_attr( (string) $s['smtp_port'] ); ?>" class="small-text"> <?php $secure( 'smtp_secure' ); ?></td></tr>
		</table>
		<h2>E-maily webu</h2>
		<table class="form-table" role="presentation">
			<tr><th>Odosielanie e-mailov webu</th><td><label><input type="checkbox" name="jem[site_mail]" value="1" <?php checked( ! empty( $s['site_mail'] ) ); ?>> Všetky e-maily webu (objednávky, faktúry, registrácia, obnova hesla) posielať cez túto schránku</label><p class="description">E-maily sa tak nedostanú do spamu. Odosielateľ z inej domény (napr. gmail) sa nahradí adresou schránky; adresy tej istej domény (napr. eshop@) ostanú.</p></td></tr>
		</table>
		<h2>Zobrazenie a podpis</h2>
		<table class="form-table" role="presentation">
			<tr><th>Správ na stránku</th><td><input type="number" name="jem[per_page]" min="5" max="100" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>" class="small-text"></td></tr>
			<tr><th><label for="jem-signature">Podpis</label></th><td><textarea id="jem-signature" name="jem[signature]" rows="4" class="large-text"><?php echo esc_textarea( $s['signature'] ); ?></textarea><p class="description">Pridá sa na koniec každej novej správy a odpovede.</p></td></tr>
		</table>
		<?php submit_button( 'Uložiť nastavenia' ); ?>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jem-panel jem-test">
		<input type="hidden" name="action" value="jem_test">
		<?php wp_nonce_field( 'jem_test' ); ?>
		<p><strong>Test pripojenia</strong> – overí prihlásenie na prijímanie aj odosielanie pošty s uloženými nastaveniami.</p>
		<?php submit_button( 'Otestovať pripojenie', 'secondary', 'submit', false ); ?>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jem-panel jem-test">
		<input type="hidden" name="action" value="jem_test_mail">
		<?php wp_nonce_field( 'jem_test_mail' ); ?>
		<p><strong>Skúšobný e-mail</strong> – web pošle e-mail do tejto schránky rovnakou cestou ako e-maily objednávok (overí odosielanie e-mailov webu).</p>
		<?php submit_button( 'Poslať skúšobný e-mail', 'secondary', 'submit', false ); ?>
	</form>
	<?php
}
