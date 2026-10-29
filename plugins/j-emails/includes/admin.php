<?php
/** Administrácia J-Emails: Doručená pošta, Odoslané, Kôš, Napísať, Nastavenia – každé ako samostatná stránka. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JEM_CAP', 'manage_options' );

/** Priečinky ako stránky v menu. */
function jem_boxes() {
	return array(
		'inbox' => array( 'page' => 'j-emails', 'title' => 'Doručená pošta' ),
		'sent'  => array( 'page' => 'j-emails-sent', 'title' => 'Odoslané' ),
		'trash' => array( 'page' => 'j-emails-trash', 'title' => 'Kôš' ),
	);
}

add_action( 'admin_menu', function () {
	$unseen = (int) get_transient( 'jem_unseen' );
	$bubble = $unseen > 0 ? ' <span class="awaiting-mod count-' . $unseen . '"><span class="pending-count">' . $unseen . '</span></span>' : '';
	add_menu_page( 'J-Emails', 'J-Emails' . $bubble, JEM_CAP, 'j-emails', 'jem_render_inbox', 'dashicons-email-alt', 58 );
	add_submenu_page( 'j-emails', 'Doručená pošta', 'Doručená pošta' . $bubble, JEM_CAP, 'j-emails', 'jem_render_inbox' );
	add_submenu_page( 'j-emails', 'Odoslané', 'Odoslané', JEM_CAP, 'j-emails-sent', function () {
		jem_render_admin( 'sent' );
	} );
	add_submenu_page( 'j-emails', 'Kôš', 'Kôš', JEM_CAP, 'j-emails-trash', function () {
		jem_render_admin( 'trash' );
	} );
	add_submenu_page( 'j-emails', 'Napísať', 'Napísať', JEM_CAP, 'j-emails-compose', function () {
		jem_render_admin( 'compose' );
	} );
	add_submenu_page( 'j-emails', 'Nastavenia', 'Nastavenia', JEM_CAP, 'j-emails-settings', function () {
		jem_render_admin( 'settings' );
	} );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, 'j-emails' ) ) {
		wp_enqueue_style( 'j-emails-admin', JEM_URL . 'assets/admin.css', array(), JEM_VERSION );
	}
} );

/** Počet neprečítaných správ pre menu – obnovuje sa každých 5 minút na pozadí. */
add_filter( 'cron_schedules', function ( $s ) {
	$s['jem_5min'] = array( 'interval' => 300, 'display' => 'Každých 5 minút (J-Emails)' );
	return $s;
} );
add_action( 'init', function () {
	if ( jem_configured() && ! wp_next_scheduled( 'jem_check_unseen' ) ) {
		wp_schedule_event( time() + 60, 'jem_5min', 'jem_check_unseen' );
	}
} );
add_action( 'jem_check_unseen', function () {
	if ( ! jem_configured() ) {
		return;
	}
	$imap = jem_connect();
	if ( ! is_wp_error( $imap ) ) {
		set_transient( 'jem_unseen', $imap->unseen( 'INBOX' ), 900 );
		$imap->close();
	}
} );

function jem_render_inbox() {
	jem_render_admin( 'inbox' );
}

function jem_page_url( $box, array $args = array() ) {
	$boxes = jem_boxes();
	$page  = isset( $boxes[ $box ] ) ? $boxes[ $box ]['page'] : ( 'compose' === $box ? 'j-emails-compose' : 'j-emails-settings' );
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

function jem_size( $b ) {
	return $b >= 1048576 ? number_format_i18n( $b / 1048576, 1 ) . ' MB' : max( 1, (int) round( $b / 1024 ) ) . ' kB';
}

function jem_when( $ts ) {
	if ( ! $ts ) {
		return '';
	}
	$today = wp_date( 'Y-m-d' );
	return wp_date( 'Y-m-d', $ts ) === $today ? wp_date( 'H:i', $ts ) : ( wp_date( 'Y', $ts ) === wp_date( 'Y' ) ? wp_date( 'j. n. H:i', $ts ) : wp_date( 'j. n. Y', $ts ) );
}

function jem_render_admin( $box ) {
	if ( ! current_user_can( JEM_CAP ) ) {
		return;
	}
	$titles = array( 'compose' => 'Napísať', 'settings' => 'Nastavenia' );
	foreach ( jem_boxes() as $k => $b ) {
		$titles[ $k ] = $b['title'];
	}
	$msgs = array(
		'sent'    => array( 'success', 'Správa je odoslaná.' ),
		'trashed' => array( 'success', 'Správa je presunutá do koša.' ),
		'unread'  => array( 'success', 'Správa je označená ako neprečítaná.' ),
		'moved'   => array( 'success', 'Správa je presunutá do Doručenej pošty.' ),
		'err'     => array( 'error', 'Akcia sa nepodarila.' ),
	);
	$msg = isset( $_GET['jem_msg'] ) ? sanitize_key( $_GET['jem_msg'] ) : ''; // phpcs:ignore
	echo '<div class="wrap jem">';
	echo '<h1>' . esc_html( $titles[ $box ] ) . ' <span class="jem-ver">J-Emails v' . esc_html( JEM_VERSION ) . '</span>';
	if ( 'compose' !== $box && 'settings' !== $box ) {
		echo ' <a href="' . esc_url( jem_page_url( 'compose' ) ) . '" class="page-title-action">Napísať</a>';
	}
	echo '</h1>';
	if ( isset( $msgs[ $msg ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $msgs[ $msg ][0] ) . ' is-dismissible"><p>' . esc_html( $msgs[ $msg ][1] ) . '</p></div>';
	}
	if ( isset( $_GET['saved'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>Nastavenia sú uložené.</p></div>';
	}
	if ( 'settings' === $box ) {
		jem_render_settings();
	} elseif ( ! jem_configured() ) {
		echo '<div class="jem-panel jem-empty"><p><strong>Schránka ešte nie je nastavená.</strong></p><p>V časti <a href="' . esc_url( jem_page_url( 'settings' ) ) . '">Nastavenia</a> zadajte e-mailovú adresu a heslo schránky a otestujte pripojenie.</p></div>';
	} elseif ( 'compose' === $box ) {
		jem_render_compose();
	} elseif ( isset( $_GET['uid'] ) ) { // phpcs:ignore
		jem_render_message( $box, (int) $_GET['uid'] ); // phpcs:ignore
	} else {
		jem_render_list( $box );
	}
	echo '</div>';
}

/** Zoznam správ v priečinku. */
function jem_render_list( $box ) {
	$imap = jem_connect();
	if ( is_wp_error( $imap ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $imap->get_error_message() ) . '</p></div>';
		return;
	}
	$map    = jem_folder_map( $imap );
	$folder = $map[ $box ];
	if ( '' === $folder ) {
		echo '<div class="jem-panel jem-empty"><p>Priečinok sa v schránke nenašiel. Názov môžete zadať v Nastaveniach.</p></div>';
		return;
	}
	if ( false === $imap->select( $folder, true ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $imap->error ) . '</p></div>';
		return;
	}
	$q = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore
	if ( '' !== $q ) {
		if ( JEM_Imap::needs_literal( $q ) ) {
			// Diakritika: hľadaný text ako literál {n} (v úvodzovkách ho prísne servery odmietnu).
			$uids = $imap->uids( "CHARSET UTF-8 OR OR FROM \x01 SUBJECT \x01 TO \x01", array( $q, $q, $q ) );
		} else {
			$qq   = JEM_Imap::q( $q );
			$uids = $imap->uids( 'CHARSET UTF-8 OR OR FROM ' . $qq . ' SUBJECT ' . $qq . ' TO ' . $qq );
		}
	} else {
		$uids = $imap->uids();
	}
	if ( 'inbox' === $box ) {
		set_transient( 'jem_unseen', $imap->unseen( 'INBOX' ), 900 );
	}
	$per   = max( 5, (int) jem_opt( 'per_page' ) );
	$total = count( $uids );
	$pages = max( 1, (int) ceil( $total / $per ) );
	$cur   = min( $pages, max( 1, isset( $_GET['p'] ) ? (int) $_GET['p'] : 1 ) ); // phpcs:ignore
	$slice = array_slice( $uids, ( $cur - 1 ) * $per, $per );
	$heads = $imap->headers( $slice );
	$imap->close();
	?>
	<form method="get" class="jem-search">
		<input type="hidden" name="page" value="<?php echo esc_attr( jem_boxes()[ $box ]['page'] ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Hľadať odosielateľa, príjemcu alebo predmet">
		<button class="button">Hľadať</button>
		<span class="jem-count"><?php echo esc_html( sprintf( '%d správ', $total ) ); ?></span>
	</form>
	<table class="widefat striped jem-list">
		<thead><tr><th class="jem-c-who"><?php echo 'sent' === $box ? 'Komu' : 'Od'; ?></th><th>Predmet</th><th class="jem-c-date">Dátum</th><th class="jem-c-size">Veľkosť</th></tr></thead>
		<tbody>
		<?php
		if ( ! $slice ) {
			echo '<tr><td colspan="4" class="jem-none">' . ( '' !== $q ? 'Nič sa nenašlo.' : 'Priečinok je prázdny.' ) . '</td></tr>';
		}
		foreach ( $slice as $uid ) {
			if ( ! isset( $heads[ $uid ] ) ) {
				continue;
			}
			$it     = $heads[ $uid ];
			$h      = jem_parse_headers( $it['headers'] );
			$seen   = in_array( '\\Seen', $it['flags'], true );
			$who    = jem_addr_label( 'sent' === $box ? ( $h['to'] ?? '' ) : ( $h['from'] ?? '' ) );
			$subj   = jem_decode_header( $h['subject'] ?? '' );
			$attach = 0 === stripos( $h['content-type'] ?? '', 'multipart/mixed' );
			$url    = jem_page_url( $box, array( 'uid' => $uid ) );
			$date   = isset( $h['date'] ) && jem_date_ts( $h['date'] ) ? jem_date_ts( $h['date'] ) : $it['date'];
			printf(
				'<tr class="%1$s"><td class="jem-c-who"><a href="%2$s">%3$s</a></td><td><a href="%2$s">%4$s%5$s%6$s</a></td><td class="jem-c-date">%7$s</td><td class="jem-c-size">%8$s</td></tr>',
				$seen ? 'is-seen' : 'is-unseen',
				esc_url( $url ),
				esc_html( '' !== $who ? $who : '—' ),
				in_array( '\\Answered', $it['flags'], true ) ? '<span class="dashicons dashicons-undo" title="Odpovedané"></span> ' : '',
				esc_html( '' !== $subj ? $subj : '(bez predmetu)' ),
				$attach ? ' <span class="dashicons dashicons-paperclip" title="Príloha"></span>' : '',
				esc_html( jem_when( $date ) ),
				esc_html( jem_size( $it['size'] ) )
			);
		}
		?>
		</tbody>
	</table>
	<?php
	if ( $pages > 1 ) {
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( 'p', '%#%' ),
					'format'    => '',
					'current'   => $cur,
					'total'     => $pages,
					'prev_text' => '‹',
					'next_text' => '›',
				)
			)
		) . '</div></div>';
	}
}

/** Bezpečné zobrazenie HTML správy v izolovanom rámci (bez skriptov, vzdialené obrázky len na požiadanie). */
function jem_html_frame( $html, array $atts, $box, $uid, $show_images ) {
	$html = preg_replace( '#<(script|iframe|object|embed|form|noscript)\b[^>]*>.*?</\1>#is', '', $html );
	$html = preg_replace( '#</?(script|iframe|frame|frameset|object|embed|applet|meta|base|link|form|input|button|textarea|select)\b[^>]*>#i', '', $html );
	$html = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );
	$html = preg_replace( '/(href|src)\s*=\s*(["\']?)\s*javascript:/i', '$1=$2#', $html );
	foreach ( $atts as $i => $a ) {
		if ( '' !== $a['cid'] ) {
			$src  = esc_url( jem_part_url( $box, $uid, $i, true ) );
			$html = str_ireplace( array( 'cid:' . $a['cid'] ), $src, $html );
		}
	}
	// Vložené obrázky (cid:) len cez admin-post.php – e-mail nemôže volať iné adresy webu s prihlásením správcu.
	$part = strtok( admin_url( 'admin-post.php' ), '?' );
	$csp  = "default-src 'none'; style-src 'unsafe-inline' " . ( $show_images ? '*' : '' ) . '; img-src data: ' . $part . ( $show_images ? ' https: http:' : '' ) . '; font-src ' . ( $show_images ? '*' : "'none'" ) . "; form-action 'none'; base-uri 'none'";
	$doc  = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="' . esc_attr( $csp ) . '"><base target="_blank"><style>body{margin:0;padding:16px;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;word-wrap:break-word}img{max-width:100%;height:auto}</style></head><body>' . $html . '</body></html>';
	return '<iframe class="jem-frame" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" srcdoc="' . htmlspecialchars( $doc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true ) . '" onload="this.style.height=(this.contentDocument.documentElement.scrollHeight+20)+\'px\'"></iframe>';
}

function jem_part_url( $box, $uid, $i, $inline = false ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=jem_part&box=' . rawurlencode( $box ) . '&uid=' . (int) $uid . '&i=' . (int) $i . ( $inline ? '&inline=1' : '' ) ), 'jem_part' );
}

function jem_action_url( $do, $box, $uid ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=jem_act&do=' . rawurlencode( $do ) . '&box=' . rawurlencode( $box ) . '&uid=' . (int) $uid ), 'jem_act' );
}

/** Načítanie a rozbor jednej správy. */
function jem_load( $box, $uid, $mark_seen = false ) {
	$imap = jem_connect();
	if ( is_wp_error( $imap ) ) {
		return $imap;
	}
	$map = jem_folder_map( $imap );
	if ( ! isset( $map[ $box ] ) || '' === $map[ $box ] || false === $imap->select( $map[ $box ], ! $mark_seen ) ) {
		return new WP_Error( 'jem', 'Priečinok sa nedá otvoriť.' );
	}
	$m = $imap->message( $uid );
	if ( ! $m ) {
		return new WP_Error( 'jem', $imap->error );
	}
	if ( $mark_seen && ! in_array( '\\Seen', $m['flags'], true ) ) {
		$imap->flag( $uid, '\\Seen', true );
		if ( 'inbox' === $box ) {
			set_transient( 'jem_unseen', $imap->unseen( 'INBOX' ), 900 );
		}
	}
	$imap->close();
	$p          = jem_parse_message( $m['raw'] );
	$p['flags'] = $m['flags'];
	return $p;
}

function jem_render_message( $box, $uid ) {
	$m = jem_load( $box, $uid, true );
	if ( is_wp_error( $m ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $m->get_error_message() ) . '</p></div>';
		return;
	}
	$h        = $m['headers'];
	$subject  = jem_decode_header( $h['subject'] ?? '' );
	$show_img = ! empty( $_GET['img'] ); // phpcs:ignore
	$back     = jem_page_url( $box );
	$date     = isset( $h['date'] ) ? jem_date_ts( $h['date'] ) : 0;
	?>
	<div class="jem-msg-actions">
		<a class="button" href="<?php echo esc_url( $back ); ?>">← <?php echo esc_html( jem_boxes()[ $box ]['title'] ); ?></a>
		<?php if ( 'sent' !== $box ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( jem_page_url( 'compose', array( 'reply' => $uid, 'box' => $box ) ) ); ?>">Odpovedať</a>
			<a class="button" href="<?php echo esc_url( jem_page_url( 'compose', array( 'reply' => $uid, 'box' => $box, 'all' => 1 ) ) ); ?>">Odpovedať všetkým</a>
		<?php endif; ?>
		<a class="button" href="<?php echo esc_url( jem_page_url( 'compose', array( 'fwd' => $uid, 'box' => $box ) ) ); ?>">Preposlať</a>
		<?php if ( 'inbox' === $box ) : ?>
			<a class="button" href="<?php echo esc_url( jem_action_url( 'unread', $box, $uid ) ); ?>">Označiť ako neprečítané</a>
		<?php endif; ?>
		<?php if ( 'trash' === $box ) : ?>
			<a class="button" href="<?php echo esc_url( jem_action_url( 'restore', $box, $uid ) ); ?>">Vrátiť do Doručenej pošty</a>
		<?php else : ?>
			<a class="button jem-danger" href="<?php echo esc_url( jem_action_url( 'trash', $box, $uid ) ); ?>">Do koša</a>
		<?php endif; ?>
	</div>
	<div class="jem-panel jem-msg">
		<h2 class="jem-subj"><?php echo esc_html( '' !== $subject ? $subject : '(bez predmetu)' ); ?></h2>
		<table class="jem-head">
			<tr><th>Od</th><td><?php echo esc_html( jem_decode_header( $h['from'] ?? '' ) ); ?></td></tr>
			<tr><th>Komu</th><td><?php echo esc_html( jem_decode_header( $h['to'] ?? '' ) ); ?></td></tr>
			<?php if ( ! empty( $h['cc'] ) ) : ?><tr><th>Kópia</th><td><?php echo esc_html( jem_decode_header( $h['cc'] ) ); ?></td></tr><?php endif; ?>
			<?php if ( ! empty( $h['reply-to'] ) ) : ?><tr><th>Odpoveď na</th><td><?php echo esc_html( jem_decode_header( $h['reply-to'] ) ); ?></td></tr><?php endif; ?>
			<tr><th>Dátum</th><td><?php echo esc_html( $date ? wp_date( 'j. n. Y H:i', $date ) : '' ); ?></td></tr>
		</table>
		<?php
		$files = array();
		foreach ( $m['attachments'] as $i => $a ) {
			if ( ! ( $a['inline'] && '' !== $a['cid'] && '' !== $m['html'] ) ) {
				$files[ $i ] = $a;
			}
		}
		if ( $files ) {
			echo '<ul class="jem-atts">';
			foreach ( $files as $i => $a ) {
				printf( '<li><a href="%s"><span class="dashicons dashicons-paperclip"></span> %s</a> <small>%s</small></li>', esc_url( jem_part_url( $box, $uid, $i ) ), esc_html( $a['name'] ), esc_html( jem_size( $a['size'] ) ) );
			}
			echo '</ul>';
		}
		if ( '' !== trim( $m['html'] ) ) {
			if ( ! $show_img && preg_match( '/<img[^>]+src\s*=\s*["\']?https?:/i', $m['html'] ) ) {
				echo '<p class="jem-img-note">Obrázky z internetu sú skryté (ochrana súkromia). <a href="' . esc_url( add_query_arg( 'img', 1 ) ) . '">Zobraziť obrázky</a></p>';
			}
			echo jem_html_frame( $m['html'], $m['attachments'], $box, $uid, $show_img ); // phpcs:ignore -- iframe so srcdoc, obsah je escapovaný.
		} else {
			echo '<div class="jem-text">' . make_clickable( nl2br( esc_html( $m['text'] ) ) ) . '</div>'; // phpcs:ignore
		}
		?>
	</div>
	<?php
}

/** Akcie so správou: neprečítané, do koša, späť z koša. */
add_action( 'admin_post_jem_act', function () {
	if ( ! current_user_can( JEM_CAP ) || ! check_admin_referer( 'jem_act' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$box  = sanitize_key( $_GET['box'] ?? 'inbox' );
	$uid  = (int) ( $_GET['uid'] ?? 0 );
	$do   = sanitize_key( $_GET['do'] ?? '' );
	$imap = jem_connect();
	$res  = 'err';
	if ( ! is_wp_error( $imap ) ) {
		$map = jem_folder_map( $imap );
		if ( ! empty( $map[ $box ] ) && false !== $imap->select( $map[ $box ] ) ) {
			if ( 'unread' === $do && $imap->flag( $uid, '\\Seen', false ) ) {
				$res = 'unread';
			} elseif ( 'trash' === $do && '' !== $map['trash'] && $imap->move( $uid, $map['trash'] ) ) {
				$res = 'trashed';
			} elseif ( 'restore' === $do && $imap->move( $uid, $map['inbox'] ) ) {
				$res = 'moved';
			}
		}
		set_transient( 'jem_unseen', $imap->unseen( 'INBOX' ), 900 );
		$imap->close();
	}
	wp_safe_redirect( jem_page_url( isset( jem_boxes()[ $box ] ) ? $box : 'inbox', array( 'jem_msg' => $res ) ) );
	exit;
} );

/** Stiahnutie prílohy (alebo vložený obrázok v HTML správe). */
add_action( 'admin_post_jem_part', function () {
	if ( ! current_user_can( JEM_CAP ) || ! check_admin_referer( 'jem_part' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$m = jem_load( sanitize_key( $_GET['box'] ?? 'inbox' ), (int) ( $_GET['uid'] ?? 0 ) );
	$i = (int) ( $_GET['i'] ?? -1 );
	if ( is_wp_error( $m ) || ! isset( $m['attachments'][ $i ] ) ) {
		wp_die( 'Príloha sa nenašla.' );
	}
	$a      = $m['attachments'][ $i ];
	$inline = ! empty( $_GET['inline'] ) && preg_match( '#^image/(png|jpe?g|gif|webp)$#', $a['type'] );
	nocache_headers();
	header( 'Content-Type: ' . ( $inline ? $a['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . "; filename*=UTF-8''" . rawurlencode( $a['name'] ) );
	header( 'Content-Length: ' . strlen( $a['data'] ) );
	header( 'X-Content-Type-Options: nosniff' );
	echo $a['data']; // phpcs:ignore
	exit;
} );

/** Nová správa, odpoveď alebo preposlanie. */
function jem_render_compose() {
	$draft = get_transient( 'jem_draft_' . get_current_user_id() );
	$d     = array( 'to' => '', 'cc' => '', 'subject' => '', 'body' => '', 'in_reply_to' => '', 'references' => '', 'reply_uid' => 0, 'reply_box' => '' );
	$sig   = trim( (string) jem_opt( 'signature' ) );
	$err   = '';
	if ( is_array( $draft ) && isset( $_GET['err'] ) ) { // phpcs:ignore
		$d   = array_merge( $d, $draft );
		$err = $draft['error'] ?? '';
	} else {
		$box = sanitize_key( $_GET['box'] ?? 'inbox' ); // phpcs:ignore
		$rid = (int) ( $_GET['reply'] ?? ( $_GET['fwd'] ?? 0 ) ); // phpcs:ignore
		if ( $rid ) {
			$m = jem_load( $box, $rid );
			if ( ! is_wp_error( $m ) ) {
				$h     = $m['headers'];
				$subj  = jem_decode_header( $h['subject'] ?? '' );
				$date  = isset( $h['date'] ) && jem_date_ts( $h['date'] ) ? wp_date( 'j. n. Y H:i', jem_date_ts( $h['date'] ) ) : '';
				$from  = jem_decode_header( $h['from'] ?? '' );
				$text  = '' !== trim( $m['text'] ) ? $m['text'] : wp_strip_all_tags( preg_replace( '#<(br|/p|/div|/tr|/h\d)[^>]*>#i', "\n", preg_replace( '#<(style|head)\b.*?</\1>#is', '', $m['html'] ) ) );
				$text  = trim( html_entity_decode( preg_replace( "/\n{3,}/", "\n\n", str_replace( "\r", '', $text ) ), ENT_QUOTES, 'UTF-8' ) );
				if ( isset( $_GET['fwd'] ) ) { // phpcs:ignore
					$d['subject'] = preg_match( '/^(fwd?|pd|preposlané):/i', $subj ) ? $subj : 'Fwd: ' . $subj;
					$d['body']    = "\n\n" . ( '' !== $sig ? "--\n" . $sig . "\n\n" : '' ) . "---------- Preposlaná správa ----------\nOd: " . $from . "\nDátum: " . $date . "\nPredmet: " . $subj . "\n\n" . $text;
				} else {
					$me       = strtolower( (string) jem_opt( 'email' ) );
					$to       = ! empty( $h['reply-to'] ) ? $h['reply-to'] : ( $h['from'] ?? '' );
					$d['to']  = jem_decode_header( $to );
					if ( ! empty( $_GET['all'] ) ) { // phpcs:ignore
						$cc = array();
						foreach ( jem_addresses( ( $h['to'] ?? '' ) . ',' . ( $h['cc'] ?? '' ) ) as $a ) {
							if ( strtolower( $a[1] ) !== $me && false === stripos( $d['to'], $a[1] ) ) {
								$cc[] = '' !== $a[0] ? $a[0] . ' <' . $a[1] . '>' : $a[1];
							}
						}
						$d['cc'] = implode( ', ', $cc );
					}
					$d['subject']     = preg_match( '/^re:/i', $subj ) ? $subj : 'Re: ' . $subj;
					$d['body']        = "\n\n" . ( '' !== $sig ? "--\n" . $sig . "\n\n" : '' ) . $date . ', ' . $from . " napísal(a):\n" . preg_replace( '/^/m', '> ', $text );
					$d['in_reply_to'] = trim( $h['message-id'] ?? '' );
					$d['references']  = trim( $h['references'] ?? '' );
					$d['reply_uid']   = $rid;
					$d['reply_box']   = $box;
				}
			}
		} elseif ( '' !== $sig ) {
			$d['body'] = "\n\n--\n" . $sig;
		}
		if ( isset( $_GET['to'] ) ) { // phpcs:ignore
			$d['to'] = trim( preg_replace( '/[\r\n]+/', ' ', wp_unslash( (string) $_GET['to'] ) ) ); // phpcs:ignore
		}
	}
	if ( '' !== $err ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
	}
	?>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jem-panel jem-compose">
		<input type="hidden" name="action" value="jem_send">
		<?php wp_nonce_field( 'jem_send' ); ?>
		<input type="hidden" name="jem[in_reply_to]" value="<?php echo esc_attr( $d['in_reply_to'] ); ?>">
		<input type="hidden" name="jem[references]" value="<?php echo esc_attr( $d['references'] ); ?>">
		<input type="hidden" name="jem[reply_uid]" value="<?php echo esc_attr( (string) $d['reply_uid'] ); ?>">
		<input type="hidden" name="jem[reply_box]" value="<?php echo esc_attr( $d['reply_box'] ); ?>">
		<p class="jem-from">Od: <strong><?php echo esc_html( jem_opt( 'name' ) . ' <' . jem_opt( 'email' ) . '>' ); ?></strong></p>
		<p><label>Komu<input type="text" name="jem[to]" value="<?php echo esc_attr( $d['to'] ); ?>" required placeholder="meno@domena.sk, ďalší príjemca"></label></p>
		<p><label>Kópia<input type="text" name="jem[cc]" value="<?php echo esc_attr( $d['cc'] ); ?>"></label></p>
		<p><label>Predmet<input type="text" name="jem[subject]" value="<?php echo esc_attr( $d['subject'] ); ?>"></label></p>
		<p><textarea name="jem[body]" rows="16"><?php echo esc_textarea( $d['body'] ); ?></textarea></p>
		<p><label class="jem-files">Prílohy <input type="file" name="jem_files[]" multiple></label></p>
		<p><button class="button button-primary button-large">Odoslať</button></p>
	</form>
	<?php
	delete_transient( 'jem_draft_' . get_current_user_id() );
}

add_action( 'admin_post_jem_send', function () {
	if ( ! current_user_can( JEM_CAP ) || ! check_admin_referer( 'jem_send' ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$in = wp_unslash( $_POST['jem'] ?? array() );
	$line = function ( $v ) {
		return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_check_invalid_utf8( (string) $v ) ) );
	};
	$d    = array(
		'to'          => $line( $in['to'] ?? '' ),
		'cc'          => $line( $in['cc'] ?? '' ),
		'subject'     => $line( $in['subject'] ?? '' ),
		'body'        => str_replace( "\r\n", "\n", wp_check_invalid_utf8( (string) ( $in['body'] ?? '' ) ) ),
		'in_reply_to' => $line( $in['in_reply_to'] ?? '' ),
		'references'  => $line( $in['references'] ?? '' ),
		'reply_uid'   => (int) ( $in['reply_uid'] ?? 0 ),
		'reply_box'   => sanitize_key( $in['reply_box'] ?? '' ),
		'files'       => $_FILES['jem_files'] ?? array(), // phpcs:ignore
	);
	if ( $d['reply_uid'] ) {
		$imap = jem_connect();
		if ( ! is_wp_error( $imap ) ) {
			$map               = jem_folder_map( $imap );
			$d['reply_folder'] = $map[ $d['reply_box'] ] ?? 'INBOX';
			$imap->close();
		}
	}
	$r = jem_send( $d );
	if ( true !== $r ) {
		unset( $d['files'] );
		$d['error'] = $r;
		set_transient( 'jem_draft_' . get_current_user_id(), $d, HOUR_IN_SECONDS );
		wp_safe_redirect( jem_page_url( 'compose', array( 'err' => 1 ) ) );
		exit;
	}
	wp_safe_redirect( jem_page_url( 'sent', array( 'jem_msg' => 'sent' ) ) );
	exit;
} );
