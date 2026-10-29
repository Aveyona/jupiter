<?php
/** Administrácia poznámok: prehľad „teraz robím“, zoznam, zobrazenie s úlohami, úprava v Markdowne, nahratie .md. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	add_menu_page( JNO_NAME, JNO_NAME, JNO_CAP, JNO_SLUG, 'jno_page', 'dashicons-welcome-write-blog', JNO_MENU_POS );
	add_submenu_page( JNO_SLUG, JNO_NAME, 'Poznámky', JNO_CAP, JNO_SLUG, 'jno_page' );
	add_submenu_page( JNO_SLUG, 'Nová poznámka – ' . JNO_NAME, 'Nová poznámka', JNO_CAP, JNO_SLUG . '&edit=new', '__return_null' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( (string) $hook, JNO_SLUG ) ) {
		wp_enqueue_style( JNO_SLUG, JNO_URL . 'assets/admin.css', array(), JNO_VERSION . '.' . (int) @filemtime( JNO_DIR . 'assets/admin.css' ) ); // phpcs:ignore
	}
} );

function jno_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . JNO_SLUG ) );
}

function jno_progress( array $n ) {
	if ( ! (int) $n['tasks_total'] ) {
		return '';
	}
	$p = (int) round( 100 * $n['tasks_done'] / $n['tasks_total'] );
	return '<span class="jno-prog" title="' . esc_attr( $n['tasks_done'] . ' z ' . $n['tasks_total'] . ' hotovo' . ( $n['tasks_doing'] ? ', ' . $n['tasks_doing'] . ' rozpracované' : '' ) ) . '"><span style="width:' . $p . '%"></span></span> <small>' . $p . ' %</small>';
}

function jno_page() {
	if ( ! current_user_can( JNO_CAP ) ) {
		return;
	}
	$edit = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : ''; // phpcs:ignore
	$id   = (int) ( $_GET['note'] ?? 0 ); // phpcs:ignore
	echo '<div class="wrap jno">';
	$imp = get_transient( 'jno_imported_' . get_current_user_id() );
	if ( $imp ) {
		delete_transient( 'jno_imported_' . get_current_user_id() );
		echo '<div class="notice notice-info is-dismissible"><p>Zo súborov .md sa načítalo poznámok: ' . (int) $imp . '.</p></div>';
	}
	$msg = isset( $_GET['jno_msg'] ) ? sanitize_key( $_GET['jno_msg'] ) : ''; // phpcs:ignore
	$msgs = array( 'saved' => 'Poznámka je uložená.', 'deleted' => 'Poznámka je zmazaná.', 'logged' => 'Záznam je pridaný do denníka zmien.', 'uploaded' => 'Súbory sú načítané.' );
	if ( isset( $msgs[ $msg ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $msg ] ) . '</p></div>';
	}
	if ( '' !== $edit ) {
		jno_render_edit( 'new' === $edit ? 0 : (int) $edit );
	} elseif ( $id ) {
		jno_render_view( $id );
	} else {
		jno_render_list();
	}
	echo '</div>';
}

/* ---------------------------------------------------------------- Zoznam */

function jno_render_list() {
	global $wpdb;
	$t    = jno_table();
	$q    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore
	$proj = sanitize_text_field( wp_unslash( $_GET['project'] ?? '' ) ); // phpcs:ignore
	$type = sanitize_key( $_GET['type'] ?? '' ); // phpcs:ignore
	$st   = sanitize_key( $_GET['status'] ?? 'active' ); // phpcs:ignore
	$w    = '1=1';
	if ( 'all' !== $st ) {
		$w .= $wpdb->prepare( ' AND status = %s', $st );
	}
	if ( $proj ) {
		$w .= $wpdb->prepare( ' AND project = %s', $proj );
	}
	if ( $type ) {
		$w .= $wpdb->prepare( ' AND type = %s', $type );
	}
	if ( '' !== $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$w   .= $wpdb->prepare( ' AND (title LIKE %s OR body LIKE %s OR project LIKE %s)', $like, $like, $like );
	}
	$rows  = (array) $wpdb->get_results( "SELECT id, slug, title, type, project, status, pinned, now, tasks_total, tasks_done, tasks_doing, updated_at FROM $t WHERE $w ORDER BY pinned DESC, now DESC, updated_at DESC LIMIT 500", ARRAY_A ); // phpcs:ignore
	$now   = (array) $wpdb->get_results( "SELECT * FROM $t WHERE now = 1 AND status = 'active' ORDER BY updated_at DESC", ARRAY_A ); // phpcs:ignore
	$projs = (array) $wpdb->get_col( "SELECT DISTINCT project FROM $t WHERE project <> '' ORDER BY project" ); // phpcs:ignore
	?>
	<h1 class="wp-heading-inline"><?php echo esc_html( JNO_NAME ); ?> <span class="jno-ver">v<?php echo esc_html( JNO_VERSION ); ?></span></h1>
	<a class="page-title-action" href="<?php echo esc_url( jno_url( array( 'edit' => 'new', 'type' => 'plan' ) ) ); ?>">Nový plán</a>
	<a class="page-title-action" href="<?php echo esc_url( jno_url( array( 'edit' => 'new', 'type' => 'doc' ) ) ); ?>">Nová dokumentácia</a>
	<a class="page-title-action" href="<?php echo esc_url( jno_url( array( 'edit' => 'new' ) ) ); ?>">Nová poznámka</a>
	<hr class="wp-header-end">
	<?php if ( $now ) : ?>
		<h2 class="jno-h">Teraz robím</h2>
		<div class="jno-now">
			<?php
			foreach ( $now as $n ) :
				$doing = array_filter( jno_tasks( $n['body'] ), fn( $x ) => 'doing' === $x[1] );
				$next  = array_filter( jno_tasks( $n['body'] ), fn( $x ) => 'todo' === $x[1] );
				$log   = preg_match( '/^##\s+(?:\d+\.\s*)?Denník zmien\s*$(.*?)(?=^#{1,2}\s|\z)/msu', $n['body'], $m ) && preg_match_all( '/^\s*[-*]\s+(.+)$/mu', $m[1], $ls ) ? end( $ls[1] ) : '';
				?>
				<a class="jno-card" href="<?php echo esc_url( jno_url( array( 'note' => $n['id'] ) ) ); ?>">
					<span class="jno-card-p"><?php echo esc_html( $n['project'] ? $n['project'] : jno_types()[ $n['type'] ] ); ?></span>
					<strong><?php echo esc_html( $n['title'] ); ?></strong>
					<?php echo jno_progress( $n ); // phpcs:ignore ?>
					<?php foreach ( array_slice( $doing, 0, 3 ) as $x ) : ?><span class="jno-card-t">◐ <?php echo esc_html( wp_strip_all_tags( $x[2] ) ); ?></span><?php endforeach; ?>
					<?php if ( ! $doing && $next ) : $x = reset( $next ); ?><span class="jno-card-t jno-muted">Ďalej: <?php echo esc_html( wp_strip_all_tags( $x[2] ) ); ?></span><?php endif; ?>
					<?php if ( $log ) : ?><span class="jno-card-l"><?php echo esc_html( mb_strimwidth( $log, 0, 140, '…' ) ); ?></span><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<form method="get" class="jno-filter">
		<input type="hidden" name="page" value="<?php echo esc_attr( JNO_SLUG ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Hľadať v poznámkach">
		<select name="project"><option value="">Všetky projekty</option><?php foreach ( $projs as $p ) : ?><option <?php selected( $proj, $p ); ?>><?php echo esc_html( $p ); ?></option><?php endforeach; ?></select>
		<select name="type"><option value="">Všetky druhy</option><?php foreach ( jno_types() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<select name="status"><?php foreach ( jno_statuses() + array( 'all' => 'Všetky' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<button class="button">Filtrovať</button>
	</form>
	<table class="widefat striped jno-list">
		<thead><tr><th>Poznámka</th><th>Projekt</th><th>Druh</th><th>Stav úloh</th><th>Zmenené</th></tr></thead>
		<tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="5">Žiadne poznámky. Vytvorte plán alebo nahrajte súbor .md nižšie.</td></tr><?php endif; ?>
		<?php foreach ( $rows as $n ) : ?>
			<tr>
				<td><?php echo (int) $n['pinned'] ? '📌 ' : ''; ?><?php echo (int) $n['now'] ? '<span class="jno-now-b">teraz</span> ' : ''; ?><a href="<?php echo esc_url( jno_url( array( 'note' => $n['id'] ) ) ); ?>"><strong><?php echo esc_html( $n['title'] ); ?></strong></a><?php echo 'active' !== $n['status'] ? ' <small class="jno-muted">(' . esc_html( jno_statuses()[ $n['status'] ] ?? '' ) . ')</small>' : ''; ?></td>
				<td><?php echo esc_html( $n['project'] ); ?></td>
				<td><?php echo esc_html( jno_types()[ $n['type'] ] ?? $n['type'] ); ?></td>
				<td><?php echo jno_progress( $n ); // phpcs:ignore ?></td>
				<td><?php echo esc_html( date_i18n( 'j. n. Y H:i', strtotime( $n['updated_at'] ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jno-upload">
		<?php wp_nonce_field( 'jno_upload' ); ?><input type="hidden" name="action" value="<?php echo esc_attr( 'jno_upload_' . JNO_SLUG ); ?>">
		<strong>Nahrať súbory .md</strong> <input type="file" name="files[]" accept=".md,text/markdown,text/plain" multiple> <button class="button">Nahrať</button>
		<span class="description">Poznámka s rovnakým kľúčom (slug v hlavičke alebo názov súboru) sa aktualizuje. Súbory sa dajú nahrať aj cez FTP do uploads/<?php echo esc_html( JNO_SLUG ); ?>/inbox.</span>
	</form>
	<?php
}

/* ---------------------------------------------------------------- Zobrazenie */

function jno_render_view( $id ) {
	$n = jno_get( $id );
	if ( ! $n ) {
		echo '<p>Poznámka neexistuje.</p>';
		return;
	}
	$nonce = wp_create_nonce( 'jno_note_' . $id );
	$act   = function ( $do, $label, $extra = '' ) use ( $id ) {
		return '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jno_act_' . JNO_SLUG . '&do=' . $do . '&id=' . $id ), 'jno_act_' . $id ) ) . '"' . $extra . '>' . esc_html( $label ) . '</a>';
	};
	?>
	<h1 class="wp-heading-inline"><?php echo esc_html( $n['title'] ); ?></h1>
	<a class="page-title-action" href="<?php echo esc_url( jno_url( array( 'edit' => $id ) ) ); ?>">Upraviť</a>
	<hr class="wp-header-end">
	<p class="jno-meta">
		<span><?php echo esc_html( jno_types()[ $n['type'] ] ?? '' ); ?></span>
		<?php if ( $n['project'] ) : ?><span>Projekt: <strong><?php echo esc_html( $n['project'] ); ?></strong></span><?php endif; ?>
		<span>Stav: <?php echo esc_html( jno_statuses()[ $n['status'] ] ?? '' ); ?></span>
		<span>Zmenené <?php echo esc_html( date_i18n( 'j. n. Y H:i', strtotime( $n['updated_at'] ) ) ); ?></span>
		<?php if ( (int) $n['tasks_total'] ) : ?><span id="jno-prog"><?php echo jno_progress( $n ); // phpcs:ignore ?></span><?php endif; ?>
		<?php if ( $n['source'] ) : ?><span class="jno-muted">súbor: <?php echo esc_html( $n['source'] ); ?></span><?php endif; ?>
	</p>
	<p class="jno-actions">
		<a class="button" href="<?php echo esc_url( jno_url() ); ?>">← Poznámky</a>
		<?php echo $act( 'now', (int) $n['now'] ? 'Už nerobím' : 'Teraz robím' ); // phpcs:ignore ?>
		<?php echo $act( 'pin', (int) $n['pinned'] ? 'Odopnúť' : 'Pripnúť' ); // phpcs:ignore ?>
		<?php echo 'active' === $n['status'] ? $act( 'done', 'Hotovo' ) : $act( 'active', 'Znova aktívna' ); // phpcs:ignore ?>
		<?php if ( 'archived' !== $n['status'] ) { echo $act( 'archive', 'Archivovať' ); } // phpcs:ignore ?>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jno_md_' . JNO_SLUG . '&id=' . $id ), 'jno_md_' . $id ) ); ?>">Stiahnuť .md</a>
		<a class="button" href="#" onclick="window.print();return false;">Tlačiť</a>
	</p>
	<div class="jno-doc" id="jno-doc" data-id="<?php echo (int) $id; ?>">
		<?php echo jno_markdown( $n['body'] ); // phpcs:ignore -- výstup je ošetrený v jno_md_inline ?>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jno-log">
		<?php wp_nonce_field( 'jno_log_' . $id ); ?><input type="hidden" name="action" value="<?php echo esc_attr( 'jno_log_' . JNO_SLUG ); ?>"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<input type="text" name="text" class="large-text" placeholder="Záznam do denníka zmien – čo sa urobilo / rozhodlo" required>
		<button class="button button-primary">Pridať záznam</button>
	</form>
	<script>
	(function () {
		var doc = document.getElementById('jno-doc'), ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce = <?php echo wp_json_encode( $nonce ); ?>;
		function toggle(el) {
			var fd = new FormData(); fd.append('action', <?php echo wp_json_encode( 'jno_task_' . JNO_SLUG ); ?>); fd.append('_ajax_nonce', nonce); fd.append('id', doc.dataset.id); fd.append('line', el.dataset.line);
			el.classList.add('is-busy');
			fetch(ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				el.classList.remove('is-busy'); if (!j.success) { alert(j.data || 'Nepodarilo sa uložiť.'); return; }
				['jno-todo', 'jno-doing', 'jno-done'].forEach(function (c) { el.classList.remove(c); }); el.classList.add('jno-' + j.data.state);
				var st = el.querySelector('.jno-st'); if (st) { st.className = 'jno-st jno-st-' + j.data.state; st.textContent = { todo: '☐', doing: '◐', done: '☑' }[j.data.state]; }
				var p = document.getElementById('jno-prog'); if (p) { p.innerHTML = j.data.progress; }
			});
		}
		doc.addEventListener('click', function (e) {
			var b = e.target.closest('.jno-box, .jno-trow .jno-st'); if (!b) { return; }
			toggle(b.closest('[data-line]'));
		});
		doc.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('jno-box')) { e.preventDefault(); toggle(e.target.closest('[data-line]')); } });
	})();
	</script>
	<?php
}

/* ---------------------------------------------------------------- Úprava */

function jno_render_edit( $id ) {
	global $wpdb;
	$n = $id ? jno_get( $id ) : null;
	if ( $id && ! $n ) {
		echo '<p>Poznámka neexistuje.</p>';
		return;
	}
	$type  = $n ? $n['type'] : ( isset( jno_types()[ $_GET['type'] ?? '' ] ) ? sanitize_key( $_GET['type'] ) : 'note' ); // phpcs:ignore
	$tpl   = 'plan' === $type ? "## Cieľ\n\n\n## Kroky\n\n- [ ] \n- [ ] \n\n## Otvorené otázky\n\n\n## Denník zmien\n" : '';
	$projs = (array) $wpdb->get_col( 'SELECT DISTINCT project FROM ' . jno_table() . " WHERE project <> '' ORDER BY project" ); // phpcs:ignore
	?>
	<h1><?php echo $n ? 'Upraviť: ' . esc_html( $n['title'] ) : 'Nová ' . esc_html( mb_strtolower( jno_types()[ $type ] ) ); ?></h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="jno-edit" id="jno-edit">
		<?php wp_nonce_field( 'jno_save' ); ?><input type="hidden" name="action" value="<?php echo esc_attr( 'jno_save_' . JNO_SLUG ); ?>"><input type="hidden" name="id" value="<?php echo (int) $id; ?>">
		<div class="jno-edit-head">
			<input name="title" class="jno-title" placeholder="Názov" required value="<?php echo esc_attr( $n['title'] ?? '' ); ?>">
			<label>Druh <select name="type"><?php foreach ( jno_types() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<label>Projekt <input name="project" list="jno-projs" value="<?php echo esc_attr( $n['project'] ?? sanitize_text_field( wp_unslash( $_GET['project'] ?? '' ) ) ); // phpcs:ignore ?>"></label>
			<datalist id="jno-projs"><?php foreach ( $projs as $p ) : ?><option><?php echo esc_html( $p ); ?></option><?php endforeach; ?></datalist>
			<label>Stav <select name="status"><?php foreach ( jno_statuses() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $n['status'] ?? 'active', $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<label><input type="checkbox" name="now" value="1" <?php checked( (int) ( $n['now'] ?? ( 'plan' === $type ? 1 : 0 ) ) ); ?>> teraz robím</label>
			<label><input type="checkbox" name="pinned" value="1" <?php checked( (int) ( $n['pinned'] ?? 0 ) ); ?>> pripnúť</label>
		</div>
		<div class="jno-tabs"><button type="button" class="button is-on" data-v="edit">Upraviť</button><button type="button" class="button" data-v="prev">Náhľad</button>
			<span class="description">Markdown: # nadpis, **tučné**, - zoznam, - [ ] úloha, - [~] rozpracovaná, - [x] hotová, | tabuľka |. Ctrl+S uloží.</span></div>
		<textarea name="body" id="jno-body" rows="28" class="large-text code" spellcheck="true"><?php echo esc_textarea( $n['body'] ?? $tpl ); ?></textarea>
		<div id="jno-prev" class="jno-doc" hidden></div>
		<p><button class="button button-primary">Uložiť</button> <a class="button" href="<?php echo esc_url( $n ? jno_url( array( 'note' => $id ) ) : jno_url() ); ?>">Zrušiť</a>
		<?php if ( $n ) : ?><a class="button jno-danger" style="float:right" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=jno_act_' . JNO_SLUG . '&do=delete&id=' . $id ), 'jno_act_' . $id ) ); ?>" onclick="return confirm('Natrvalo zmazať poznámku?');">Zmazať</a><?php endif; ?></p>
	</form>
	<script>
	(function () {
		var f = document.getElementById('jno-edit'), ta = document.getElementById('jno-body'), pv = document.getElementById('jno-prev'), dirty = false;
		ta.addEventListener('input', function () { dirty = true; });
		document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); dirty = false; f.submit(); } });
		f.addEventListener('submit', function () { dirty = false; });
		window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
		ta.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter') { return; }
			var s = ta.selectionStart, line = ta.value.slice(ta.value.lastIndexOf('\n', s - 1) + 1, s), m = line.match(/^(\s*)([-*+]|\d+\.)\s+(\[[ x~]\]\s+)?/);
			if (!m) { return; }
			e.preventDefault();
			if (line.trim() === m[0].trim()) { ta.setRangeText('', s - line.length, s, 'end'); return; }
			var ins = '\n' + m[1] + (/\d+\./.test(m[2]) ? (parseInt(m[2], 10) + 1) + '.' : m[2]) + ' ' + (m[3] ? '[ ] ' : '');
			ta.setRangeText(ins, s, s, 'end'); dirty = true;
		});
		document.querySelectorAll('.jno-tabs button').forEach(function (b) {
			b.addEventListener('click', function () {
				document.querySelectorAll('.jno-tabs button').forEach(function (x) { x.classList.remove('is-on'); }); b.classList.add('is-on');
				if (b.dataset.v === 'edit') { ta.hidden = false; pv.hidden = true; return; }
				var fd = new FormData(); fd.append('action', <?php echo wp_json_encode( 'jno_preview_' . JNO_SLUG ); ?>); fd.append('_ajax_nonce', <?php echo wp_json_encode( wp_create_nonce( 'jno_preview' ) ); ?>); fd.append('body', ta.value);
				fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { pv.innerHTML = j.success ? j.data : ''; ta.hidden = true; pv.hidden = false; });
			});
		});
	})();
	</script>
	<?php
}

/* ---------------------------------------------------------------- Akcie */

add_action( 'admin_post_jno_save_' . JNO_SLUG, function () {
	if ( ! current_user_can( JNO_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jno_save' );
	$in = wp_unslash( $_POST );
	$id = jno_save( array(
		'title'   => $in['title'] ?? '',
		'type'    => sanitize_key( $in['type'] ?? 'note' ),
		'project' => $in['project'] ?? '',
		'status'  => sanitize_key( $in['status'] ?? 'active' ),
		'now'     => ! empty( $in['now'] ),
		'pinned'  => ! empty( $in['pinned'] ),
		'body'    => str_replace( "\r\n", "\n", (string) ( $in['body'] ?? '' ) ),
	), (int) ( $in['id'] ?? 0 ) );
	wp_safe_redirect( jno_url( array( 'note' => $id, 'jno_msg' => 'saved' ) ) );
	exit;
} );

add_action( 'admin_post_jno_act_' . JNO_SLUG, function () {
	global $wpdb;
	if ( ! current_user_can( JNO_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore
	$do = sanitize_key( $_GET['do'] ?? '' ); // phpcs:ignore
	check_admin_referer( 'jno_act_' . $id );
	$n = jno_get( $id );
	if ( $n ) {
		if ( 'delete' === $do ) {
			$wpdb->delete( jno_table(), array( 'id' => $id ) );
			@unlink( jno_files_dir( 'export' ) . '/' . $n['slug'] . '.md' ); // phpcs:ignore
			wp_safe_redirect( jno_url( array( 'jno_msg' => 'deleted' ) ) );
			exit;
		}
		$map = array( 'now' => array( 'now' => ! (int) $n['now'] ), 'pin' => array( 'pinned' => ! (int) $n['pinned'] ), 'done' => array( 'status' => 'done', 'now' => false ), 'active' => array( 'status' => 'active' ), 'archive' => array( 'status' => 'archived', 'now' => false ) );
		if ( isset( $map[ $do ] ) ) {
			jno_save( $map[ $do ], $id );
		}
	}
	wp_safe_redirect( jno_url( array( 'note' => $id ) ) );
	exit;
} );

add_action( 'admin_post_jno_log_' . JNO_SLUG, function () {
	if ( ! current_user_can( JNO_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_POST['id'] ?? 0 );
	check_admin_referer( 'jno_log_' . $id );
	jno_add_log( $id, sanitize_text_field( wp_unslash( $_POST['text'] ?? '' ) ) ); // phpcs:ignore
	wp_safe_redirect( jno_url( array( 'note' => $id, 'jno_msg' => 'logged' ) ) );
	exit;
} );

add_action( 'admin_post_jno_md_' . JNO_SLUG, function () {
	if ( ! current_user_can( JNO_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	$id = (int) ( $_GET['id'] ?? 0 ); // phpcs:ignore
	check_admin_referer( 'jno_md_' . $id );
	$n = jno_get( $id );
	if ( ! $n ) {
		wp_die( 'Poznámka neexistuje.' );
	}
	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $n['slug'] . '.md"' );
	echo "---\nslug: {$n['slug']}\ntitle: {$n['title']}\ntype: {$n['type']}\nproject: {$n['project']}\nstatus: {$n['status']}\n---\n" . $n['body']; // phpcs:ignore
	exit;
} );

add_action( 'admin_post_jno_upload_' . JNO_SLUG, function () {
	if ( ! current_user_can( JNO_CAP ) ) {
		wp_die( 'Nemáte oprávnenie.' );
	}
	check_admin_referer( 'jno_upload' );
	$in = jno_files_dir( 'inbox' );
	foreach ( (array) ( $_FILES['files']['name'] ?? array() ) as $i => $name ) {
		if ( UPLOAD_ERR_OK === (int) $_FILES['files']['error'][ $i ] && preg_match( '/\.(md|markdown|txt)$/i', $name ) && (int) $_FILES['files']['size'][ $i ] < 5 * MB_IN_BYTES ) {
			move_uploaded_file( $_FILES['files']['tmp_name'][ $i ], $in . '/' . sanitize_file_name( preg_replace( '/\.(markdown|txt)$/i', '.md', $name ) ) );
		}
	}
	jno_import_inbox();
	wp_safe_redirect( jno_url( array( 'jno_msg' => 'uploaded' ) ) );
	exit;
} );

add_action( 'wp_ajax_jno_task_' . JNO_SLUG, function () {
	$id = (int) ( $_POST['id'] ?? 0 );
	if ( ! current_user_can( JNO_CAP ) || ! check_ajax_referer( 'jno_note_' . $id, '_ajax_nonce', false ) ) {
		wp_send_json_error( 'Nemáte oprávnenie – obnovte stránku.', 403 );
	}
	$st = jno_set_task( $id, (int) ( $_POST['line'] ?? -1 ) );
	if ( ! $st ) {
		wp_send_json_error( 'Úloha sa nenašla – poznámka sa medzitým zmenila, obnovte stránku.' );
	}
	wp_send_json_success( array( 'state' => $st, 'progress' => jno_progress( jno_get( $id ) ) ) );
} );

add_action( 'wp_ajax_jno_preview_' . JNO_SLUG, function () {
	if ( ! current_user_can( JNO_CAP ) || ! check_ajax_referer( 'jno_preview', '_ajax_nonce', false ) ) {
		wp_send_json_error( '', 403 );
	}
	wp_send_json_success( jno_markdown( wp_unslash( $_POST['body'] ?? '' ), false ) ); // phpcs:ignore
} );
