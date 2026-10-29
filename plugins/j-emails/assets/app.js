/**
 * Jupiter Email – pošta v štýle Gmailu v administrácii WordPressu.
 * Bez knižníc a bez zostavovania; komunikuje s admin-ajax.php (akcie jem_*).
 * Texty zo servera sa vkladajú cez textContent; HTML správ sa zobrazuje v izolovanom rámci (srcdoc + sandbox).
 */
(function () {
	'use strict';

	var C = window.JEM || {};
	var root = document.getElementById('jem-app');
	if (!root) { return; }

	/* ================= Pomocné funkcie ================= */

	function h(tag, attrs) {
		var el = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) { return; }
				if (k === 'class') { el.className = v; }
				else if (k === 'text') { el.textContent = v; }
				else if (k === 'html') { el.innerHTML = v; }
				else if (k.slice(0, 2) === 'on') { el.addEventListener(k.slice(2), v); }
				else if (k === 'style' && typeof v === 'object') { Object.assign(el.style, v); }
				else { el.setAttribute(k, v === true ? '' : v); }
			});
		}
		for (var i = 2; i < arguments.length; i++) { add(el, arguments[i]); }
		return el;
	}
	function add(el, c) {
		if (c === null || c === undefined || c === false) { return; }
		if (Array.isArray(c)) { c.forEach(function (x) { add(el, x); }); return; }
		el.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
	}
	function clear(el) { while (el.firstChild) { el.removeChild(el.firstChild); } return el; }

	var P = { // ikony 24×24, ťah
		menu: 'M3 6h18M3 12h18M3 18h18',
		search: 'M10.5 4a6.5 6.5 0 1 0 0 13a6.5 6.5 0 1 0 0-13zM20 20l-4.6-4.6',
		x: 'M6 6l12 12M18 6L6 18',
		// ozubené koleso podľa Feather Icons (MIT)
		gear: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z',
		inbox: 'M3 13h5l1.5 3h5L16 13h5M5.5 5h13l2.5 8v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5z',
		star: 'M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z',
		clock: 'M12 3a9 9 0 1 0 0 18a9 9 0 1 0 0-18zM12 7.5V12l3 2',
		imp: 'M3.5 6h11l5 6-5 6h-11l4.5-6z',
		send: 'M21 3L10 14M21 3l-6.5 18-4-8-8-4z',
		draft: 'M14 3H6.5A1.5 1.5 0 0 0 5 4.5v15A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V8zM14 3v5h5M8.5 13h7M8.5 17h5',
		archive: 'M3.5 4h17v4h-17zM5 8v10.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V8M10 12h4',
		spam: 'M8 2.5h8L21.5 8v8L16 21.5H8L2.5 16V8zM12 7.5v5.5M12 16.2v.3',
		trash: 'M4 6.5h16M9 6.5V4h6v2.5M6 6.5l1 13.5h10l1-13.5M10 10.5v6M14 10.5v6',
		folder: 'M3 6.5A1.5 1.5 0 0 1 4.5 5H9l2 2h8.5A1.5 1.5 0 0 1 21 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5z',
		pencil: 'M4 20h4L19.5 8.5l-4-4L4 16zM13.5 6.5l4 4',
		refresh: 'M20 12a8 8 0 1 1-2.4-5.7M20 4.5v5h-5',
		more: 'M12 5h.01M12 12h.01M12 19h.01',
		left: 'M15 5.5L8.5 12l6.5 6.5',
		right: 'M9 5.5l6.5 6.5L9 18.5',
		back: 'M20 12H5M11 5.5L4.5 12l6.5 6.5',
		mail: 'M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM3.5 6.5l8.5 6 8.5-6',
		mailopen: 'M3 10l9-6.5 9 6.5v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zM3.5 10.5l8.5 6 8.5-6',
		move: 'M3 6.5A1.5 1.5 0 0 1 4.5 5H9l2 2h8.5A1.5 1.5 0 0 1 21 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5zM9 13h7M13.5 10.5L16 13l-2.5 2.5',
		reply: 'M9.5 7L4 12l5.5 5M4 12h10a6 6 0 0 1 6 6v1',
		replyall: 'M7.5 7L2 12l5.5 5M12.5 7L7 12l5.5 5M7 12h8a6 6 0 0 1 6 6v1',
		forward: 'M14.5 7l5.5 5-5.5 5M20 12H10a6 6 0 0 0-6 6v1',
		clip: 'M20.5 11.5l-8.3 8.3a5 5 0 0 1-7.1-7.1l8.5-8.5a3.4 3.4 0 0 1 4.8 4.8l-8.5 8.5a1.7 1.7 0 0 1-2.4-2.4L15.4 7.6',
		minimize: 'M5 18h14',
		expand: 'M14 4h6v6M10 20H4v-6M20 4l-6.5 6.5M4 20l6.5-6.5',
		shrink: 'M10 4v6H4M14 20v-6h6M4 4l6 6M20 20l-6-6',
		list: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01',
		olist: 'M10 6h10M10 12h10M10 18h10M4 5l1.5-1v5M4 14.5c0-1 2.5-1 2.5 0S4 16.5 4 18h2.5',
		link: 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
		textfmt: 'M5 19L11 5h2l6 14M7.5 14h9',
		quote: 'M5 17h3l2-4V7H4v6h3zM14 17h3l2-4V7h-6v6h3z',
		clearfmt: 'M6 5h12M12 5l-4 14M4 20l16-16',
		download: 'M12 4v11M6.5 10l5.5 5.5 5.5-5.5M5 20h14',
		check: 'M5 12.5l4.5 4.5L19 7.5',
		down: 'M6 9.5l6 6 6-6',
		image: 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M15.5 8.5h.01',
		print: 'M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M7 14h10v6H7z',
		undo: 'M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 0 1 0 11H11',
		redo: 'M15 14l5-5-5-5M20 9H9.5a5.5 5.5 0 0 0 0 11H13'
	};
	function icon(name, cls) {
		var ns = 'http://www.w3.org/2000/svg';
		var s = document.createElementNS(ns, 'svg');
		s.setAttribute('viewBox', '0 0 24 24');
		s.setAttribute('aria-hidden', 'true');
		s.setAttribute('class', 'jem-i' + (name === 'more' ? ' jem-i-dots' : '') + (cls ? ' ' + cls : ''));
		var p = document.createElementNS(ns, 'path');
		p.setAttribute('d', P[name] || '');
		s.appendChild(p);
		return s;
	}
	function logo() {
		var ns = 'http://www.w3.org/2000/svg';
		var s = document.createElementNS(ns, 'svg');
		s.setAttribute('viewBox', '0 0 40 40');
		s.setAttribute('class', 'jem-logo-mark');
		s.innerHTML = '<defs><clipPath id="jemPl"><circle cx="20" cy="20" r="12"/></clipPath></defs>' +
			'<ellipse cx="20" cy="20" rx="18.5" ry="6" fill="none" stroke="#7aa7f7" stroke-width="2.2" transform="rotate(-18 20 20)"/>' +
			'<g clip-path="url(#jemPl)"><rect x="6" y="6" width="28" height="28" fill="#f2b56b"/><rect x="6" y="12" width="28" height="3.5" fill="#d9773a"/><rect x="6" y="18.5" width="28" height="2.5" fill="#fbe2bf"/><rect x="6" y="23" width="28" height="3.5" fill="#c9622b"/><ellipse cx="24.5" cy="24.8" rx="3" ry="1.8" fill="#a8441d"/></g>' +
			'<path d="M2.6 25.3a18.5 6 -18 0 0 34.8-11.6" fill="none" stroke="#1a73e8" stroke-width="2.2" transform="rotate(0 20 20)"/>';
		return s;
	}

	function api(action, data, files) {
		var fd = new FormData();
		fd.append('action', 'jem_' + action);
		fd.append('_n', C.nonce);
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			fd.append(k, typeof v === 'object' && v !== null ? JSON.stringify(v) : (v === undefined || v === null ? '' : v));
		});
		(files || []).forEach(function (f) { fd.append('files[]', f, f.name); });
		return fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.text().then(function (t) { return { ok: r.ok, status: r.status, text: t }; }); })
			.then(function (r) {
				var j;
				try { j = JSON.parse(r.text); } catch (e) { throw new Error(r.status === 403 ? 'Vypršala relácia – obnovte stránku.' : 'Server vrátil neplatnú odpoveď (' + r.status + ').'); }
				if (!j || !j.success) {
					var err = new Error((j && j.data && j.data.msg) || 'Akcia sa nepodarila.');
					err.data = j && j.data;
					throw err;
				}
				return j.data;
			});
	}

	var MONTHS = ['jan', 'feb', 'mar', 'apr', 'máj', 'jún', 'júl', 'aug', 'sep', 'okt', 'nov', 'dec'];
	var DAYS = ['ne', 'po', 'ut', 'st', 'št', 'pi', 'so'];
	function pad(n) { return n < 10 ? '0' + n : '' + n; }
	function listDate(ts) {
		if (!ts) { return ''; }
		var d = new Date(ts * 1000), n = new Date();
		if (d.toDateString() === n.toDateString()) { return d.getHours() + ':' + pad(d.getMinutes()); }
		if (d.getFullYear() === n.getFullYear()) { return d.getDate() + '. ' + (d.getMonth() + 1) + '.'; }
		return d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getFullYear();
	}
	function fullDate(ts) {
		if (!ts) { return ''; }
		var d = new Date(ts * 1000);
		return d.getDate() + '. ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + ', ' + d.getHours() + ':' + pad(d.getMinutes());
	}
	function ago(ts) {
		var s = Math.round(Date.now() / 1000 - ts);
		if (s < 0) { return ''; }
		if (s < 60) { return 'pred chvíľou'; }
		if (s < 3600) { var m = Math.round(s / 60); return 'pred ' + m + (m === 1 ? ' minútou' : ' minútami'); }
		if (s < 86400) { var hh = Math.round(s / 3600); return 'pred ' + hh + (hh === 1 ? ' hodinou' : ' hodinami'); }
		var dd = Math.round(s / 86400);
		if (dd < 8) { return 'pred ' + dd + (dd === 1 ? ' dňom' : ' dňami'); }
		return '';
	}
	function snoozeLabel(ts) {
		var d = new Date(ts * 1000);
		return DAYS[d.getDay()] + ' ' + d.getDate() + '. ' + (d.getMonth() + 1) + '. ' + d.getHours() + ':' + pad(d.getMinutes());
	}
	function size(b) { return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' kB'; }
	var COLORS = ['#1a73e8', '#e8710a', '#188038', '#d93025', '#9334e6', '#c5221f', '#129eaf', '#b06000', '#3367d6', '#e52592'];
	function avatar(name, email, me) {
		var s = (name || email || '?').trim();
		var letter = me ? (C.site || 'J').charAt(0).toUpperCase() : s.replace(/^["'(]+/, '').charAt(0).toUpperCase();
		var hsh = 0; for (var i = 0; i < (email || s).length; i++) { hsh = (hsh * 31 + (email || s).charCodeAt(i)) | 0; }
		return h('span', { class: 'jem-avatar', style: { background: me ? '#0b57d0' : COLORS[Math.abs(hsh) % COLORS.length] }, text: letter });
	}
	function fileKind(name, type) {
		var ext = (name.split('.').pop() || '').toLowerCase();
		if (/^image\//.test(type || '') || /^(png|jpe?g|gif|webp|svg)$/.test(ext)) { return 'img'; }
		if (ext === 'pdf' || type === 'application/pdf') { return 'pdf'; }
		if (/^(docx?|odt|rtf)$/.test(ext)) { return 'doc'; }
		if (/^(xlsx?|ods|csv)$/.test(ext)) { return 'xls'; }
		if (/^(zip|rar|7z|gz)$/.test(ext)) { return 'zip'; }
		return 'file';
	}
	function addrText(a) { return a[0] ? a[0] + ' <' + a[1] + '>' : a[1]; }

	/* ================= Stav aplikácie ================= */

	var FOLDERS = [
		{ key: 'inbox', label: 'Doručené', icon: 'inbox' },
		{ key: 'starred', label: 'S hviezdičkou', icon: 'star' },
		{ key: 'snoozed', label: 'Odložené', icon: 'clock' },
		{ key: 'important', label: 'Dôležité', icon: 'imp' },
		{ key: 'sent', label: 'Odoslané', icon: 'send' },
		{ key: 'drafts', label: 'Koncepty', icon: 'draft' },
		{ key: 'archive', label: 'Archív', icon: 'archive' },
		{ key: 'spam', label: 'Spam', icon: 'spam' },
		{ key: 'trash', label: 'Kôš', icon: 'trash' }
	];
	var S = {
		boot: null, counts: {}, custom: [], me: [],
		view: 'inbox', page: 1, q: '', threadId: null,
		threads: [], total: 0, more: false, per: 50,
		sel: {}, focus: -1, busy: false, current: null,
		contacts: null, imgOk: {}, navOpen: false, listReq: 0
	};
	var el = {};

	function viewLabel(v) {
		if (v === 'search') { return 'Výsledky hľadania'; }
		if (v.indexOf('f:') === 0) { var f = S.custom.filter(function (c) { return c.key === v; })[0]; return f ? f.label : v.slice(2); }
		var x = FOLDERS.filter(function (f) { return f.key === v; })[0];
		return x ? x.label : v;
	}

	/* ================= Smerovanie (adresa za #) ================= */

	function parseHash() {
		var raw = decodeURIComponent((location.hash || '#inbox').slice(1));
		var r = { view: 'inbox', page: 1, q: '', thread: null, compose: false };
		if (!raw) { return r; }
		var parts = raw.split('/');
		if (parts[0] === 'search') {
			r.view = 'search'; r.q = parts[1] || '';
			parts = parts.slice(2);
		} else {
			r.view = parts[0] || 'inbox';
			parts = parts.slice(1);
		}
		while (parts.length) {
			var p = parts.shift();
			if (/^p\d+$/.test(p)) { r.page = parseInt(p.slice(1), 10) || 1; }
			else if (p === 't' && parts.length) { r.thread = parts.shift(); }
			else if (p === 'compose') { r.compose = true; }
			else if (p === 'q' && parts.length) { r.q = parts.shift(); }
		}
		return r;
	}
	function hashFor(view, page, q, thread) {
		var s = view === 'search' ? 'search/' + encodeURIComponent(q || '') : encodeURIComponent(view);
		if (view !== 'search' && q) { s += '/q/' + encodeURIComponent(q); }
		if (page > 1) { s += '/p' + page; }
		if (thread) { s += '/t/' + encodeURIComponent(thread); }
		return '#' + s;
	}
	function go(view, page, q, thread) {
		var hs = hashFor(view, page || 1, q || '', thread || null);
		if (location.hash === hs) { route(); } else { location.hash = hs; }
	}
	function route() {
		var r = parseHash();
		var listChanged = r.view !== S.view || r.page !== S.page || r.q !== S.q || !S.threads.length && !S.loaded;
		S.view = r.view; S.page = r.page; S.q = r.q;
		if (el.search) { el.search.value = r.view === 'search' ? r.q : (r.q || ''); }
		renderNav();
		closeMenus();
		if (r.compose) {
			openCompose({ mode: 'new' });
			history.replaceState(null, '', hashFor(r.view, r.page, r.q, r.thread));
		}
		if (r.thread) {
			S.threadId = r.thread;
			var t = findThread(r.thread);
			if (t) { openThread(t); }
			else { loadList(true).then(function () { var t2 = findThread(r.thread); if (t2) { openThread(t2); } else { go(S.view, S.page, S.q); } }); }
			return;
		}
		S.threadId = null; S.current = null;
		if (listChanged || S.mode !== 'list') { S.sel = {}; S.focus = -1; loadList(); }
		else { renderMain(); }
	}
	function findThread(id) {
		for (var i = 0; i < S.threads.length; i++) { if (S.threads[i].id === id) { return S.threads[i]; } }
		return null;
	}

	/* ================= Kostra ================= */

	function renderShell() {
		clear(root);
		el.search = h('input', { type: 'search', class: 'jem-search-input', placeholder: 'Hľadať v pošte', 'aria-label': 'Hľadať v pošte',
			onkeydown: function (e) {
				if (e.key === 'Enter') { var q = el.search.value.trim(); go(q ? 'search' : 'inbox', 1, q); el.search.blur(); }
				if (e.key === 'Escape') { el.search.value = ''; el.search.blur(); }
			} });
		var searchBox = h('div', { class: 'jem-search' },
			h('button', { class: 'jem-ib', title: 'Hľadať', onclick: function () { var q = el.search.value.trim(); go(q ? 'search' : 'inbox', 1, q); } }, icon('search')),
			el.search,
			h('button', { class: 'jem-ib jem-search-clear', title: 'Vymazať hľadanie', onclick: function () { el.search.value = ''; if (S.view === 'search' || S.q) { go(S.view === 'search' ? 'inbox' : S.view, 1, ''); } } }, icon('x'))
		);
		var me = S.me[0] || { email: '', name: '' };
		el.top = h('header', { class: 'jem-top' },
			h('button', { class: 'jem-ib jem-burger', title: 'Hlavná ponuka', onclick: function () { root.classList.toggle('jem-nav-collapsed'); } }, icon('menu')),
			h('a', { class: 'jem-logo', href: '#inbox', title: 'Jupiter Email' }, logo(), h('span', { class: 'jem-logo-text' }, h('b', { text: 'Jupiter' }), ' Email')),
			searchBox,
			h('div', { class: 'jem-top-right' },
				h('a', { class: 'jem-ib', href: C.settings, title: 'Nastavenia' }, icon('gear')),
				h('span', { class: 'jem-me', title: (me.name ? me.name + ' ' : '') + '<' + me.email + '>' }, avatar(me.name, me.email, true))
			)
		);
		el.nav = h('nav', { class: 'jem-nav' });
		el.main = h('main', { class: 'jem-main' });
		el.dock = h('div', { class: 'jem-dock' });
		el.toast = h('div', { class: 'jem-toast', role: 'status', 'aria-live': 'polite' });
		root.appendChild(el.top);
		root.appendChild(h('div', { class: 'jem-body' }, el.nav, el.main));
		root.appendChild(el.dock);
		root.appendChild(el.toast);
	}

	function renderNav() {
		if (!el.nav) { return; }
		clear(el.nav);
		el.nav.appendChild(h('button', { class: 'jem-compose-btn', onclick: function () { openCompose({ mode: 'new' }); } }, icon('pencil'), h('span', { text: 'Napísať' })));
		var ul = h('ul', { class: 'jem-folders' });
		FOLDERS.forEach(function (f) {
			var n = f.key === 'inbox' ? S.counts.inbox : f.key === 'spam' ? S.counts.spam : f.key === 'drafts' ? S.counts.drafts : 0;
			ul.appendChild(navItem(f.key, f.label, f.icon, n, f.key === 'inbox' || f.key === 'spam'));
		});
		el.nav.appendChild(ul);
		if (S.custom.length) {
			el.nav.appendChild(h('div', { class: 'jem-nav-h', text: 'Priečinky' }));
			var ul2 = h('ul', { class: 'jem-folders' });
			S.custom.forEach(function (c) { ul2.appendChild(navItem(c.key, c.label, 'folder', c.count, true)); });
			el.nav.appendChild(ul2);
		}
		el.nav.appendChild(h('div', { class: 'jem-nav-foot' }, h('span', { text: 'Jupiter Email v' + (C.version || '') })));
		var t = S.counts.inbox ? 'Doručené (' + S.counts.inbox + ') – Jupiter Email' : viewLabel(S.view) + ' – Jupiter Email';
		document.title = t;
	}
	function navItem(key, label, ic, count, bold) {
		var active = S.view === key;
		return h('li', null, h('a', {
			href: hashFor(key, 1, ''), class: 'jem-folder' + (active ? ' is-active' : '') + (count && bold ? ' has-unread' : ''),
			title: label,
			ondragover: function (e) { if (S.drag && key !== 'drafts' && key !== 'starred' && key !== 'important') { e.preventDefault(); this.classList.add('is-drop'); } },
			ondragleave: function () { this.classList.remove('is-drop'); },
			ondrop: function (e) { e.preventDefault(); this.classList.remove('is-drop'); dropOn(key); },
			onclick: function () { if (window.innerWidth < 900) { root.classList.add('jem-nav-collapsed'); } if (S.view === key && !S.threadId) { loadList(); } }
		}, icon(ic), h('span', { class: 'jem-folder-label', text: label }), count ? h('span', { class: 'jem-folder-count', text: String(count) }) : null));
	}

	/* ================= Zoznam ================= */

	function loadList(silent) {
		var req = ++S.listReq;
		if (!silent) { setBusy(true); }
		if (S.view === 'search' && !S.q) { S.threads = []; S.total = 0; S.loaded = true; setBusy(false); renderMain(); return Promise.resolve(); }
		return api('list', { view: S.view, q: S.q, page: S.page }).then(function (d) {
			if (req !== S.listReq) { return; }
			S.threads = d.threads || []; S.total = d.total || 0; S.more = !!d.more; S.per = d.per || 50; S.loaded = true;
			if (d.counts) { S.counts = d.counts; renderNav(); }
			var keep = {};
			S.threads.forEach(function (t) { if (S.sel[t.id]) { keep[t.id] = true; } });
			S.sel = keep;
			if (!S.threadId) { S.mode = 'list'; renderMain(); }
		}).catch(function (e) {
			if (req !== S.listReq) { return; }
			if (e.data && e.data.setup) { renderSetup(); return; }
			S.mode = 'list'; S.threads = []; S.total = 0; renderMain(e.message);
		}).then(function () { if (req === S.listReq) { setBusy(false); } });
	}
	function setBusy(b) { S.busy = b; root.classList.toggle('is-busy', !!b); }

	function renderSetup() {
		clear(el.main);
		el.main.appendChild(h('div', { class: 'jem-empty' },
			h('div', { class: 'jem-empty-icon' }, logo()),
			h('h2', { text: 'Vitajte v Jupiter Email' }),
			h('p', { text: 'Najprv nastavte schránku – adresu, heslo a servery IMAP/SMTP.' }),
			h('a', { class: 'jem-btn jem-btn-primary', href: C.settings, text: 'Otvoriť nastavenia' })));
	}

	function selectedThreads() { return S.threads.filter(function (t) { return S.sel[t.id]; }); }

	function renderMain(err) {
		if (S.threadId && S.current) { return; }
		S.mode = 'list';
		clear(el.main);
		el.main.appendChild(h('div', { class: 'jem-progress' }));
		el.main.appendChild(renderListToolbar());
		var list = h('div', { class: 'jem-list', role: 'list' });
		if (S.view === 'snoozed' && S.threads.length) { list.appendChild(h('div', { class: 'jem-banner', text: 'Odložené konverzácie sa v zvolený čas vrátia do Doručenej pošty ako neprečítané.' })); }
		if (S.view === 'trash' && S.threads.length) { list.appendChild(h('div', { class: 'jem-banner', text: 'Správy v koši zostávajú, kým ich nezmažete natrvalo.' })); }
		if (S.view === 'search') { list.appendChild(h('div', { class: 'jem-banner', text: 'Hľadá sa v Doručených, Archíve, Odoslaných a Odložených. Spam a Kôš sa neprehľadávajú.' })); }
		if (err) {
			list.appendChild(h('div', { class: 'jem-empty jem-empty-err' }, h('p', { text: err }), h('button', { class: 'jem-btn', text: 'Skúsiť znova', onclick: function () { loadList(); } })));
		} else if (!S.threads.length) {
			list.appendChild(emptyState());
		} else {
			S.threads.forEach(function (t, i) { list.appendChild(renderRow(t, i)); });
		}
		el.main.appendChild(list);
		el.list = list;
	}
	function emptyState() {
		var msg = {
			inbox: 'Žiadne nové správy! Užite si deň.', starred: 'Žiadne konverzácie s hviezdičkou. Hviezdičkou si označte dôležité správy, aby ste ich ľahko našli.',
			snoozed: 'Nič nie je odložené. Konverzáciu odložíte ikonou hodín – vráti sa, keď ju budete potrebovať.', important: 'Žiadne dôležité konverzácie.',
			sent: 'Žiadne odoslané správy.', drafts: 'Nemáte uložené koncepty.', archive: 'Archív je prázdny.', spam: 'Hurá, v spame nie je nič!', trash: 'Kôš je prázdny.',
			search: S.q ? 'Hľadaniu „' + S.q + '“ nezodpovedá žiadna správa.' : 'Zadajte, čo hľadáte.'
		}[S.view] || 'Priečinok je prázdny.';
		return h('div', { class: 'jem-empty' }, h('div', { class: 'jem-empty-icon' }, icon(S.view === 'inbox' ? 'check' : 'inbox')), h('p', { text: msg }));
	}

	function renderListToolbar() {
		var sel = selectedThreads();
		var all = S.threads.length && sel.length === S.threads.length;
		var cb = h('span', { class: 'jem-cb' + (all ? ' is-on' : sel.length ? ' is-mixed' : ''), role: 'checkbox', 'aria-checked': all ? 'true' : sel.length ? 'mixed' : 'false', tabindex: 0, title: 'Vybrať',
			onclick: function () { if (sel.length) { S.sel = {}; } else { S.threads.forEach(function (t) { S.sel[t.id] = true; }); } renderMain(); } });
		var bar = h('div', { class: 'jem-toolbar' },
			h('div', { class: 'jem-tb-sel' }, cb, h('button', { class: 'jem-ib jem-ib-sm', title: 'Výber', onclick: function (e) { selectMenu(e.currentTarget); } }, icon('down'))));
		if (S.view === 'drafts') {
			if (sel.length) { bar.appendChild(tb('trash', 'Zahodiť koncepty', function () { discardDrafts(sel); })); }
			else { bar.appendChild(tb('refresh', 'Obnoviť', function () { loadList(); })); }
		} else if (sel.length) {
			bulkButtons(sel).forEach(function (b) { bar.appendChild(b); });
		} else {
			bar.appendChild(tb('refresh', 'Obnoviť', function () { loadList(); }));
			bar.appendChild(tb('more', 'Ďalšie', function (e) { moreMenu(e.currentTarget); }));
		}
		bar.appendChild(h('div', { class: 'jem-tb-space' }));
		if (S.total) {
			var from = (S.page - 1) * S.per + 1, to = Math.min(S.total, S.page * S.per);
			bar.appendChild(h('span', { class: 'jem-pager-text', text: from + ' – ' + to + ' z ' + (S.more ? 'viac ako ' : '') + S.total }));
		}
		bar.appendChild(tb('left', 'Novšie', function () { go(S.view, S.page - 1, S.q); }, S.page <= 1));
		bar.appendChild(tb('right', 'Staršie', function () { go(S.view, S.page + 1, S.q); }, S.page * S.per >= S.total));
		return bar;
	}
	function tb(ic, title, fn, disabled) {
		return h('button', { class: 'jem-ib', title: title, 'aria-label': title, disabled: !!disabled, onclick: fn }, icon(ic));
	}
	function sep() { return h('span', { class: 'jem-tb-sep' }); }

	/** Tlačidlá pre vybrané konverzácie (podľa priečinka). */
	function bulkButtons(sel) {
		var v = S.view, out = [];
		var anyUnread = sel.some(function (t) { return t.unread; });
		if (v === 'trash') {
			out.push(tb('inbox', 'Presunúť do Doručenej pošty', function () { act('inbox', sel); }));
			out.push(h('button', { class: 'jem-tb-text', onclick: function () { if (confirm('Natrvalo zmazať vybrané konverzácie? Túto akciu nie je možné vrátiť.')) { act('forever', sel); } }, text: 'Odstrániť natrvalo' }));
		} else if (v === 'spam') {
			out.push(h('button', { class: 'jem-tb-text', onclick: function () { act('notspam', sel); }, text: 'Toto nie je spam' }));
			out.push(tb('trash', 'Odstrániť', function () { act('trash', sel); }));
		} else {
			if (v === 'inbox' || v.indexOf('f:') === 0 || v === 'snoozed') { out.push(tb('archive', 'Archivovať', function () { act('archive', sel); })); }
			if (v === 'archive') { out.push(tb('inbox', 'Presunúť do Doručenej pošty', function () { act('inbox', sel); })); }
			if (v !== 'sent') { out.push(tb('spam', 'Nahlásiť spam', function () { act('spam', sel); })); }
			out.push(tb('trash', 'Odstrániť', function () { act('trash', sel); }));
		}
		out.push(sep());
		out.push(tb(anyUnread ? 'mailopen' : 'mail', anyUnread ? 'Označiť ako prečítané' : 'Označiť ako neprečítané', function () { act(anyUnread ? 'read' : 'unread', sel); }));
		if (v === 'snoozed') { out.push(h('button', { class: 'jem-tb-text', onclick: function () { act('unsnooze', sel); }, text: 'Zrušiť odloženie' })); }
		else if (v === 'inbox' || v === 'archive' || v.indexOf('f:') === 0) { out.push(tb('clock', 'Odložiť', function (e) { snoozeMenu(e.currentTarget, sel); })); }
		if (v !== 'sent' && v !== 'trash') { out.push(tb('move', 'Presunúť do', function (e) { moveMenu(e.currentTarget, sel); })); }
		out.push(tb('more', 'Ďalšie', function (e) { moreMenu(e.currentTarget, sel); }));
		return out;
	}

	function renderRow(t, i) {
		var isSel = !!S.sel[t.id];
		var row = h('div', { class: 'jem-row' + (t.unread ? ' is-unread' : '') + (isSel ? ' is-sel' : '') + (S.focus === i ? ' is-focus' : ''), role: 'listitem', tabindex: -1, draggable: t.draft ? null : 'true',
			'data-id': t.id,
			onclick: function (e) {
				if (e.target.closest('.jem-row-cb,.jem-row-star,.jem-row-imp,.jem-hover-acts,.jem-chip')) { return; }
				if (t.draft) { openDraft(t.draft); return; }
				go(S.view, S.page, S.q, t.id);
			},
			ondragstart: function (e) {
				if (!S.sel[t.id]) { S.sel = {}; S.sel[t.id] = true; }
				S.drag = true;
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData('text/plain', t.id);
				var n = selectedThreads().length;
				var g = h('div', { class: 'jem-drag-ghost', text: n === 1 ? 'Presunúť konverzáciu' : 'Presunúť ' + n + ' konverzácie' });
				document.body.appendChild(g); e.dataTransfer.setDragImage(g, 10, 10); setTimeout(function () { g.remove(); }, 0);
			},
			ondragend: function () { S.drag = false; }
		});
		var cb = h('span', { class: 'jem-row-cb jem-cb' + (isSel ? ' is-on' : ''), role: 'checkbox', 'aria-checked': isSel ? 'true' : 'false', title: 'Vybrať',
			onclick: function (e) {
				e.stopPropagation();
				if (e.shiftKey && S.lastClick !== undefined) {
					var a = Math.min(S.lastClick, i), b = Math.max(S.lastClick, i);
					for (var k = a; k <= b; k++) { S.sel[S.threads[k].id] = true; }
				} else if (S.sel[t.id]) { delete S.sel[t.id]; } else { S.sel[t.id] = true; }
				S.lastClick = i; renderMain();
			} });
		var star = t.draft ? h('span', { class: 'jem-row-star' }) : h('button', { class: 'jem-row-star' + (t.starred ? ' is-on' : ''), title: t.starred ? 'S hviezdičkou' : 'Bez hviezdičky',
			onclick: function (e) { e.stopPropagation(); toggleStar(t); } }, icon('star'));
		var imp = t.draft ? h('span', { class: 'jem-row-imp' }) : h('button', { class: 'jem-row-imp' + (t.important ? ' is-on' : ''), title: t.important ? 'Dôležité – kliknutím zrušíte' : 'Označiť ako dôležité',
			onclick: function (e) { e.stopPropagation(); toggleImportant(t); } }, icon('imp'));
		var who = h('div', { class: 'jem-row-who' });
		if (S.view === 'sent' && t.to) {
			who.appendChild(h('span', { class: 'jem-row-to', text: 'Komu: ' }));
			who.appendChild(h('span', { text: t.to }));
		} else if (t.draft) {
			who.appendChild(h('span', { class: 'jem-draft-label', text: 'Koncept' }));
			if (t.to) { who.appendChild(h('span', { class: 'jem-row-to', text: ' ' + t.to })); }
		} else {
			(t.names || []).forEach(function (n, k) {
				if (k) { who.appendChild(document.createTextNode(k === 1 && t.names.length === 3 && t.count > 3 ? ' .. ' : ', ')); }
				who.appendChild(h('span', { class: n.unread ? 'is-unread-name' : '', text: n.n }));
			});
			if (t.count > 1) { who.appendChild(h('span', { class: 'jem-row-count', text: ' ' + t.count })); }
		}
		var subj = h('div', { class: 'jem-row-subj' });
		if (S.view === 'search' || S.view === 'starred' || S.view === 'important') {
			var lbl = folderChip(t);
			if (lbl) { subj.appendChild(lbl); }
		}
		subj.appendChild(h('span', { class: 'jem-row-subject', text: t.subject || '(bez predmetu)' }));
		if (t.snippet) { subj.appendChild(h('span', { class: 'jem-row-snippet', text: '\u00a0– ' + t.snippet })); }
		var mid = h('div', { class: 'jem-row-mid' }, subj);
		if (t.files && t.files.length) {
			var chips = h('div', { class: 'jem-row-chips' });
			t.files.forEach(function (f) {
				chips.appendChild(h('span', { class: 'jem-chip jem-fk-' + fileKind(f), title: f, onclick: function (e) { e.stopPropagation(); go(S.view, S.page, S.q, t.id); } }, h('span', { class: 'jem-chip-ic' }), h('span', { class: 'jem-chip-name', text: f })));
			});
			if (t.nfiles > t.files.length) { chips.appendChild(h('span', { class: 'jem-chip-more', text: '+' + (t.nfiles - t.files.length) })); }
			mid.appendChild(chips);
		}
		var dateTxt = t.snoozed_until ? snoozeLabel(t.snoozed_until) : listDate(t.date);
		var date = h('div', { class: 'jem-row-date' + (t.snoozed_until ? ' is-snoozed' : ''), title: fullDate(t.date) }, t.att && !(t.files && t.files.length) ? icon('clip', 'jem-row-clip') : null, h('span', { text: dateTxt }));
		var hov = h('div', { class: 'jem-hover-acts' });
		if (!t.draft) {
			if (S.view === 'inbox' || S.view.indexOf('f:') === 0 || S.view === 'snoozed') { hov.appendChild(rowAct('archive', 'Archivovať', function () { act('archive', [t]); })); }
			if (S.view === 'archive' || S.view === 'trash' || S.view === 'spam') { hov.appendChild(rowAct('inbox', 'Presunúť do Doručenej pošty', function () { act(S.view === 'spam' ? 'notspam' : 'inbox', [t]); })); }
			hov.appendChild(rowAct('trash', S.view === 'trash' ? 'Odstrániť natrvalo' : 'Odstrániť', function () { if (S.view !== 'trash' || confirm('Natrvalo zmazať konverzáciu?')) { act('trash', [t]); } }));
			hov.appendChild(rowAct(t.unread ? 'mailopen' : 'mail', t.unread ? 'Označiť ako prečítané' : 'Označiť ako neprečítané', function () { act(t.unread ? 'read' : 'unread', [t]); }));
			if (S.view === 'inbox' || S.view === 'archive' || S.view.indexOf('f:') === 0) { hov.appendChild(rowAct('clock', 'Odložiť', function (e) { snoozeMenu(e.currentTarget, [t]); })); }
		} else {
			hov.appendChild(rowAct('trash', 'Zahodiť koncept', function () { discardDrafts([t]); }));
		}
		row.appendChild(h('div', { class: 'jem-row-lead' }, h('span', { class: 'jem-drag-handle' }), cb, star, imp));
		row.appendChild(who);
		row.appendChild(mid);
		row.appendChild(date);
		row.appendChild(hov);
		return row;
	}
	function rowAct(ic, title, fn) {
		return h('button', { class: 'jem-ib jem-ib-sm', title: title, 'aria-label': title, onclick: function (e) { e.stopPropagation(); fn(e); } }, icon(ic));
	}
	function folderChip(t) {
		var keys = (t.members || []).map(function (m) { return m.k; });
		var k = keys.indexOf('inbox') >= 0 ? 'inbox' : keys[0];
		if (!k || k === 'sent' && keys.length > 1) { return null; }
		var lbl = { inbox: 'Doručené', archive: 'Archív', sent: 'Odoslané', snoozed: 'Odložené', spam: 'Spam', trash: 'Kôš' }[k] || (k.indexOf('f:') === 0 ? k.slice(2) : '');
		return lbl ? h('span', { class: 'jem-label-chip', text: lbl }) : null;
	}

	/* ================= Akcie ================= */

	var INVIEW = { // ktorých správ konverzácie sa akcia týka
		def: function (t) { return (t.members || []).filter(function (m) { return S.view === 'sent' || m.k !== 'sent' || S.view === 'starred' || S.view === 'important' || S.view === 'search'; }); }
	};
	function membersOf(list, op) {
		var out = [];
		list.forEach(function (t) {
			var ms = (op === 'read' || op === 'unread' || op === 'unstar' || op === 'important' || op === 'unimportant') ? (t.members || []) : INVIEW.def(t);
			ms.forEach(function (m) { out.push({ k: m.k, u: m.u }); });
		});
		return out;
	}
	var REMOVES = { archive: 1, trash: 1, forever: 1, spam: 1, notspam: 1, move: 1, snooze: 1, unsnooze: 1, inbox: 1 };
	var DONE = {
		archive: 'Konverzácia bola archivovaná.', trash: 'Konverzácia bola presunutá do koša.', forever: 'Konverzácia bola natrvalo odstránená.',
		spam: 'Konverzácia bola nahlásená ako spam.', notspam: 'Konverzácia bola presunutá do Doručenej pošty.', move: 'Konverzácia bola presunutá.',
		snooze: 'Konverzácia bola odložená.', unsnooze: 'Konverzácia sa vrátila do Doručenej pošty.', inbox: 'Konverzácia bola presunutá do Doručenej pošty.',
		read: 'Označené ako prečítané.', unread: 'Označené ako neprečítané.'
	};
	function act(op, list, extra) {
		if (!list || !list.length) { return Promise.resolve(); }
		var data = Object.assign({ op: op, members: membersOf(list, op) }, extra || {});
		var ids = list.map(function (t) { return t.id; });
		// Okamžitá odozva (optimisticky), potom server.
		if (REMOVES[op] && !(op === 'inbox' && S.view === 'inbox')) {
			S.threads = S.threads.filter(function (t) { return ids.indexOf(t.id) < 0; });
			S.total = Math.max(0, S.total - ids.length);
			ids.forEach(function (id) { delete S.sel[id]; });
		} else if (op === 'read' || op === 'unread') {
			list.forEach(function (t) { t.unread = op === 'unread'; (t.names || []).forEach(function (n) { n.unread = false; }); });
		}
		var leaving = S.threadId && ids.indexOf(S.threadId) >= 0 && (REMOVES[op] || op === 'unread');
		if (leaving) { S.threadId = null; S.current = null; history.pushState(null, '', hashFor(S.view, S.page, S.q)); }
		if (!S.threadId) { renderMain(); }
		var n = list.length;
		return api('act', data).then(function (d) {
			if (d.counts) { S.counts = d.counts; renderNav(); }
			if (DONE[op]) { toast(n > 1 ? DONE[op].replace('Konverzácia bola', n + ' konverzácie boli').replace('Konverzácia sa vrátila', n + ' konverzácie sa vrátili') : DONE[op]); }
			if (d.warn) { toast(d.warn, 'warn'); }
			if (REMOVES[op] && !S.threadId) { loadList(true); }
		}).catch(function (e) { toast(e.message, 'err'); loadList(true); });
	}
	function toggleStar(t) {
		t.starred = !t.starred;
		if (!S.threadId) { renderMain(); }
		var last = t.last || (t.members && t.members[0] && { k: t.members[0].k, u: t.members[0].u[t.members[0].u.length - 1] });
		var data = t.starred ? { op: 'star', members: [{ k: last.k, u: [last.u] }] } : { op: 'unstar', members: membersOf([t], 'unstar') };
		api('act', data).catch(function (e) { t.starred = !t.starred; toast(e.message, 'err'); if (!S.threadId) { renderMain(); } });
		if (S.view === 'starred' && !t.starred) { setTimeout(function () { loadList(true); }, 400); }
	}
	function toggleImportant(t) {
		t.important = !t.important;
		if (!S.threadId) { renderMain(); }
		act(t.important ? 'important' : 'unimportant', [t]).then(function () { toast(t.important ? 'Označené ako dôležité.' : 'Označenie Dôležité bolo zrušené.'); });
	}
	function dropOn(key) {
		S.drag = false;
		var sel = selectedThreads();
		if (!sel.length || key === S.view) { return; }
		if (key === 'inbox') { act('inbox', sel); }
		else if (key === 'archive') { act('archive', sel); }
		else if (key === 'trash') { act('trash', sel); }
		else if (key === 'spam') { act('spam', sel); }
		else if (key === 'snoozed' || key === 'sent' || key === 'drafts') { toast('Sem sa konverzácie presúvať nedajú.', 'warn'); }
		else { act('move', sel, { target: key }); }
	}

	/* ================= Ponuky ================= */

	function closeMenus() { document.querySelectorAll('.jem-menu').forEach(function (m) { m.remove(); }); }
	function menu(anchor, items, cls) {
		closeMenus();
		var m = h('div', { class: 'jem-menu' + (cls ? ' ' + cls : ''), role: 'menu' });
		items.forEach(function (it) {
			if (!it) { return; }
			if (it === '-') { m.appendChild(h('div', { class: 'jem-menu-sep' })); return; }
			if (it.head) { m.appendChild(h('div', { class: 'jem-menu-head', text: it.head })); return; }
			if (it.node) { m.appendChild(it.node); return; }
			m.appendChild(h('button', { class: 'jem-menu-item', role: 'menuitem', disabled: !!it.disabled, onclick: function () { closeMenus(); it.fn(); } },
				it.icon ? icon(it.icon) : h('span', { class: 'jem-menu-noicon' }), h('span', { class: 'jem-menu-label', text: it.label }), it.hint ? h('span', { class: 'jem-menu-hint', text: it.hint }) : null));
		});
		document.body.appendChild(m);
		var r = anchor.getBoundingClientRect();
		var top = r.bottom + 4, left = r.left;
		m.style.top = top + 'px'; m.style.left = left + 'px';
		var mr = m.getBoundingClientRect();
		if (mr.right > window.innerWidth - 8) { m.style.left = Math.max(8, window.innerWidth - mr.width - 8) + 'px'; }
		if (mr.bottom > window.innerHeight - 8) { m.style.top = Math.max(8, r.top - mr.height - 4) + 'px'; }
		setTimeout(function () { var b = m.querySelector('button:not([disabled]),input'); if (b) { b.focus(); } }, 0);
		return m;
	}
	function selectMenu(a) {
		var pick = function (fn) { return function () { S.sel = {}; S.threads.forEach(function (t) { if (fn(t)) { S.sel[t.id] = true; } }); renderMain(); }; };
		menu(a, [
			{ label: 'Všetky', fn: pick(function () { return true; }) },
			{ label: 'Žiadne', fn: pick(function () { return false; }) },
			{ label: 'Prečítané', fn: pick(function (t) { return !t.unread; }) },
			{ label: 'Neprečítané', fn: pick(function (t) { return t.unread; }) },
			{ label: 'S hviezdičkou', fn: pick(function (t) { return t.starred; }) },
			{ label: 'Bez hviezdičky', fn: pick(function (t) { return !t.starred; }) }
		]);
	}
	function snoozeMenu(a, list) {
		var now = new Date();
		var later = new Date(now.getTime() + 3 * 3600 * 1000); later.setMinutes(0);
		var tom = new Date(now); tom.setDate(tom.getDate() + 1);
		var input = h('input', { type: 'datetime-local', class: 'jem-dt', min: now.toISOString().slice(0, 16) });
		var custom = h('div', { class: 'jem-menu-custom' }, input, h('button', { class: 'jem-btn jem-btn-primary jem-btn-sm', text: 'Odložiť', onclick: function () {
			if (!input.value) { input.focus(); return; }
			closeMenus(); act('snooze', list, { when: 'custom', custom: input.value });
		} }));
		menu(a, [
			{ head: 'Odložiť do…' },
			{ label: 'Neskôr dnes', hint: DAYS[later.getDay()] + ' ' + later.getHours() + ':00', fn: function () { act('snooze', list, { when: 'later' }); } },
			{ label: 'Zajtra', hint: DAYS[tom.getDay()] + ' 8:00', fn: function () { act('snooze', list, { when: 'tomorrow' }); } },
			{ label: 'Tento víkend', hint: 'so 8:00', fn: function () { act('snooze', list, { when: 'weekend' }); } },
			{ label: 'Budúci týždeň', hint: 'po 8:00', fn: function () { act('snooze', list, { when: 'nextweek' }); } },
			'-',
			{ head: 'Vybrať dátum a čas' },
			{ node: custom }
		], 'jem-menu-snooze');
	}
	function moveMenu(a, list) {
		var items = [{ head: 'Presunúť do:' }];
		var targets = [['inbox', 'Doručené'], ['archive', 'Archív']].concat(S.custom.map(function (c) { return [c.key, c.label]; })).concat([['spam', 'Spam'], ['trash', 'Kôš']]);
		targets.forEach(function (x) {
			if (x[0] === S.view) { return; }
			items.push({ label: x[1], icon: x[0] === 'inbox' ? 'inbox' : x[0] === 'archive' ? 'archive' : x[0] === 'spam' ? 'spam' : x[0] === 'trash' ? 'trash' : 'folder', fn: function () {
				var op = x[0] === 'inbox' ? 'inbox' : x[0] === 'archive' ? 'archive' : x[0] === 'spam' ? 'spam' : x[0] === 'trash' ? 'trash' : 'move';
				act(op, list, op === 'move' ? { target: x[0] } : null);
			} });
		});
		menu(a, items);
	}
	function moreMenu(a, list) {
		var items = [];
		if (list && list.length) {
			var anyStar = list.some(function (t) { return t.starred; });
			var anyImp = list.some(function (t) { return t.important; });
			items.push({ label: 'Označiť ako prečítané', icon: 'mailopen', fn: function () { act('read', list); } });
			items.push({ label: 'Označiť ako neprečítané', icon: 'mail', fn: function () { act('unread', list); } });
			items.push({ label: anyImp ? 'Označiť ako nedôležité' : 'Označiť ako dôležité', icon: 'imp', fn: function () { list.forEach(function (t) { t.important = !anyImp; }); act(anyImp ? 'unimportant' : 'important', list); } });
			items.push({ label: anyStar ? 'Odstrániť hviezdičku' : 'Pridať hviezdičku', icon: 'star', fn: function () {
				list.forEach(function (t) { t.starred = !anyStar; });
				if (anyStar) { act('unstar', list); } else {
					api('act', { op: 'star', members: list.map(function (t) { return { k: t.last.k, u: [t.last.u] }; }) }).then(function () { renderMain(); }).catch(function (e) { toast(e.message, 'err'); });
				}
			} });
		} else {
			items.push({ label: 'Označiť všetko ako prečítané', icon: 'mailopen', fn: function () { act('read', S.threads.filter(function (t) { return t.unread; })); } });
			items.push({ label: 'Klávesové skratky', icon: 'textfmt', fn: showShortcuts });
			items.push({ label: 'Nastavenia schránky', icon: 'gear', fn: function () { location.href = C.settings; } });
		}
		menu(a, items);
	}

	/* ================= Konverzácia ================= */

	function openThread(t) {
		S.current = t; S.mode = 'thread'; S.threadId = t.id;
		clear(el.main);
		el.main.appendChild(h('div', { class: 'jem-progress' }));
		el.main.appendChild(threadToolbar(t));
		var wrap = h('div', { class: 'jem-thread' });
		var head = h('div', { class: 'jem-thread-head' },
			h('h2', { class: 'jem-thread-subj', text: t.subject || '(bez predmetu)' }),
			folderChip(t),
			h('div', { class: 'jem-tb-space' }),
			h('button', { class: 'jem-ib', title: 'Tlačiť konverzáciu', onclick: function () { printThread(); } }, icon('print')));
		wrap.appendChild(head);
		var msgsEl = h('div', { class: 'jem-msgs' }, h('div', { class: 'jem-loading', text: 'Načítavam…' }));
		wrap.appendChild(msgsEl);
		el.main.appendChild(wrap);
		el.thread = wrap;
		setBusy(true);
		return api('thread', { members: t.members, img: Object.keys(S.imgOk) }).then(function (d) {
			if (S.current !== t) { return; }
			t.unread = false;
			(t.names || []).forEach(function (n) { n.unread = false; });
			if (d.counts) { S.counts = d.counts; renderNav(); }
			t.msgs = d.messages;
			renderMessages(t, msgsEl);
		}).catch(function (e) {
			clear(msgsEl).appendChild(h('div', { class: 'jem-empty jem-empty-err' }, h('p', { text: e.message }), h('button', { class: 'jem-btn', text: 'Späť na zoznam', onclick: function () { go(S.view, S.page, S.q); } })));
		}).then(function () { setBusy(false); });
	}

	function threadToolbar(t) {
		var v = S.view;
		var bar = h('div', { class: 'jem-toolbar' }, tb('back', 'Späť na ' + viewLabel(v), function () { go(v, S.page, S.q); }));
		bulkButtons([t]).forEach(function (b) { bar.appendChild(b); });
		bar.appendChild(h('div', { class: 'jem-tb-space' }));
		var idx = S.threads.indexOf(t);
		if (idx >= 0) {
			bar.appendChild(h('span', { class: 'jem-pager-text', text: ((S.page - 1) * S.per + idx + 1) + ' z ' + S.total }));
			bar.appendChild(tb('left', 'Novšia', function () { go(v, S.page, S.q, S.threads[idx - 1].id); }, idx <= 0));
			bar.appendChild(tb('right', 'Staršia', function () { go(v, S.page, S.q, S.threads[idx + 1].id); }, idx >= S.threads.length - 1));
		}
		return bar;
	}

	function renderMessages(t, box) {
		clear(box);
		var msgs = t.msgs || [];
		var lastIdx = msgs.length - 1;
		msgs.forEach(function (m, i) {
			m._open = m._open !== undefined ? m._open : (i === lastIdx || m.unread);
		});
		// Veľa starších správ zbalí do jedného riadka (ako Gmail).
		var collapsedRun = msgs.length > 4 && !t._expandAll;
		msgs.forEach(function (m, i) {
			if (collapsedRun && i > 0 && i < lastIdx - 1 && !m._open) {
				if (i === 1) {
					var hidden = msgs.slice(1, lastIdx - 1).filter(function (x) { return !x._open; }).length;
					box.appendChild(h('button', { class: 'jem-msg-fold', onclick: function () { t._expandAll = true; renderMessages(t, box); } }, h('span', { class: 'jem-msg-fold-n', text: String(hidden) }), h('span', { text: 'staršie správy' })));
				}
				return;
			}
			box.appendChild(m._open ? messageFull(t, m, i === lastIdx) : messageCollapsed(t, m, box));
		});
		var last = msgs[lastIdx];
		if (last) {
			var multi = (last.to || []).length + (last.cc || []).length > 1;
			box.appendChild(h('div', { class: 'jem-reply-bar' },
				h('button', { class: 'jem-pill', onclick: function () { inlineReply(t, last, 'reply', box); } }, icon('reply'), h('span', { text: 'Odpovedať' })),
				multi ? h('button', { class: 'jem-pill', onclick: function () { inlineReply(t, last, 'replyall', box); } }, icon('replyall'), h('span', { text: 'Odpovedať všetkým' })) : null,
				h('button', { class: 'jem-pill', onclick: function () { inlineReply(t, last, 'forward', box); } }, icon('forward'), h('span', { text: 'Preposlať' }))));
		}
	}

	function messageCollapsed(t, m, box) {
		return h('div', { class: 'jem-msg is-collapsed' + (m.unread ? ' is-unread' : ''), tabindex: 0, onclick: function () { m._open = true; renderMessages(t, box); } },
			avatar(m.from.n, m.from.e, m.from.me),
			h('div', { class: 'jem-msg-c-who', text: m.from.me ? 'ja' : (m.from.n || m.from.e) }),
			h('div', { class: 'jem-msg-c-snip', text: m.snippet || '' }),
			h('div', { class: 'jem-msg-c-date', text: listDate(m.date) }));
	}

	function messageFull(t, m, isLast) {
		var box = h('div', { class: 'jem-msg' + (m.unread ? ' is-unread' : '') });
		var to = (m.to || []).concat(m.cc || []).map(function (a) { return S.me.some(function (x) { return x.email === (a[1] || '').toLowerCase(); }) ? 'ja' : (a[0] || a[1]); });
		var details = h('div', { class: 'jem-msg-details', hidden: true },
			detailRow('od:', addrText([m.from.n, m.from.e])),
			detailRow('komu:', (m.to || []).map(addrText).join(', ')),
			(m.cc || []).length ? detailRow('kópia:', m.cc.map(addrText).join(', ')) : null,
			(m.replyTo || []).length ? detailRow('odpovedať:', m.replyTo.map(addrText).join(', ')) : null,
			detailRow('dátum:', fullDate(m.date)),
			detailRow('predmet:', m.subject || ''));
		var star = h('button', { class: 'jem-ib jem-msg-star' + (m.starred ? ' is-on' : ''), title: m.starred ? 'S hviezdičkou' : 'Bez hviezdičky', onclick: function (e) {
			e.stopPropagation(); m.starred = !m.starred; star.classList.toggle('is-on', m.starred);
			t.starred = t.msgs.some(function (x) { return x.starred; });
			api('act', { op: m.starred ? 'star' : 'unstar', members: [{ k: m.k, u: [m.u] }] }).catch(function (er) { toast(er.message, 'err'); });
		} }, icon('star'));
		var head = h('div', { class: 'jem-msg-head' },
			avatar(m.from.n, m.from.e, m.from.me),
			h('div', { class: 'jem-msg-who' },
				h('div', { class: 'jem-msg-from' }, h('b', { text: m.from.n || m.from.e }), m.from.n ? h('span', { class: 'jem-msg-email', text: ' <' + m.from.e + '>' }) : null),
				h('button', { class: 'jem-msg-to', onclick: function (e) { e.stopPropagation(); details.hidden = !details.hidden; } }, h('span', { text: 'komu: ' + (to.join(', ') || '—') }), icon('down', 'jem-i-xs'))),
			h('div', { class: 'jem-msg-meta' },
				m.atts && m.atts.length ? icon('clip', 'jem-i-sm') : null,
				h('span', { class: 'jem-msg-date', title: fullDate(m.date), text: fullDate(m.date) + (ago(m.date) ? ' (' + ago(m.date) + ')' : '') }),
				star,
				h('button', { class: 'jem-ib', title: 'Odpovedať', onclick: function (e) { e.stopPropagation(); inlineReply(t, m, 'reply', box.parentNode); } }, icon('reply')),
				h('button', { class: 'jem-ib', title: 'Ďalšie', onclick: function (e) {
					e.stopPropagation();
					menu(e.currentTarget, [
						{ label: 'Odpovedať', icon: 'reply', fn: function () { inlineReply(t, m, 'reply', box.parentNode); } },
						{ label: 'Odpovedať všetkým', icon: 'replyall', fn: function () { inlineReply(t, m, 'replyall', box.parentNode); } },
						{ label: 'Preposlať', icon: 'forward', fn: function () { inlineReply(t, m, 'forward', box.parentNode); } },
						'-',
						{ label: 'Odstrániť túto správu', icon: 'trash', fn: function () { deleteOne(t, m); } },
						{ label: 'Označiť ako neprečítané', icon: 'mail', fn: function () { api('act', { op: 'unread', members: [{ k: m.k, u: [m.u] }] }).then(function () { go(S.view, S.page, S.q); }); } },
						m.from.e && !m.from.me ? { label: 'Vždy zobrazovať obrázky od ' + m.from.e, icon: 'image', fn: function () { trustSender(t, m); } } : null
					]);
				} }, icon('more'))));
		head.addEventListener('click', function (e) {
			if (e.target.closest('button,a')) { return; }
			if (!isLast) { m._open = false; renderMessages(t, box.parentNode); }
		});
		box.appendChild(head);
		box.appendChild(details);
		if (m.blocked) {
			box.appendChild(h('div', { class: 'jem-img-note' },
				h('span', { text: 'Obrázky z internetu sú skryté, aby odosielateľ nevedel, že ste správu otvorili. ' }),
				h('button', { class: 'jem-link', text: 'Zobraziť obrázky', onclick: function () { S.imgOk[m.k + ':' + m.u] = true; reloadThread(t); } }),
				m.from.e ? h('span', { text: ' · ' }) : null,
				m.from.e ? h('button', { class: 'jem-link', text: 'Vždy zobrazovať od ' + m.from.e, onclick: function () { trustSender(t, m); } }) : null));
		}
		var body = h('div', { class: 'jem-msg-body' });
		if (m.doc !== undefined) {
			var frame = h('iframe', { class: 'jem-frame', sandbox: 'allow-same-origin allow-popups allow-popups-to-escape-sandbox', title: 'Obsah správy' });
			frame.srcdoc = m.doc;
			frame.addEventListener('load', function () { fitFrame(frame, body); });
			body.appendChild(frame);
		} else {
			body.appendChild(h('div', { class: 'jem-text' }, linkify(m.text || '')));
			if (m.quote) {
				var q = h('div', { class: 'jem-text jem-quote-text', hidden: true }, linkify(m.quote));
				body.appendChild(h('button', { class: 'jem-dots', title: 'Zobraziť citovaný text', onclick: function () { q.hidden = !q.hidden; } }, '•••'));
				body.appendChild(q);
			}
		}
		box.appendChild(body);
		if (m.atts && m.atts.length) { box.appendChild(attachmentsBlock(m)); }
		return box;
	}
	function detailRow(k, v) { return h('div', { class: 'jem-detail' }, h('span', { class: 'jem-detail-k', text: k }), h('span', { class: 'jem-detail-v', text: v })); }

	function fitFrame(frame, body) {
		try {
			var doc = frame.contentDocument;
			if (!doc) { return; }
			var fit = function () { frame.style.height = Math.max(40, doc.documentElement.scrollHeight + 4) + 'px'; };
			fit();
			Array.prototype.forEach.call(doc.images, function (im) { im.addEventListener('load', fit); });
			setTimeout(fit, 400); setTimeout(fit, 1500);
			var quoted = doc.querySelector('.gmail_quote,blockquote[type=cite],.yahoo_quoted,#divRplyFwdMsg,#appendonsend');
			if (quoted && !body.querySelector('.jem-dots')) {
				var btn = h('button', { class: 'jem-dots', title: 'Zobraziť citovaný text', onclick: function () { doc.body.classList.toggle('jem-hide-quote'); fit(); } }, '•••');
				body.appendChild(btn);
			}
			Array.prototype.forEach.call(doc.querySelectorAll('a[href]'), function (a) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener noreferrer'); });
		} catch (e) { frame.style.height = '600px'; }
	}

	function linkify(text) {
		var frag = document.createDocumentFragment();
		var re = /(https?:\/\/[^\s<>"']+|[\w.+-]+@[\w-]+\.[\w.-]+)/g, last = 0, m;
		while ((m = re.exec(text))) {
			if (m.index > last) { frag.appendChild(document.createTextNode(text.slice(last, m.index))); }
			var u = m[0].replace(/[.,);:]+$/, '');
			var isMail = u.indexOf('@') > 0 && u.indexOf('://') < 0;
			frag.appendChild(h('a', { href: isMail ? 'mailto:' + u : u, target: '_blank', rel: 'noopener noreferrer', text: u,
				onclick: isMail ? function (e) { e.preventDefault(); openCompose({ mode: 'new', to: this.textContent }); } : null }));
			if (u.length < m[0].length) { frag.appendChild(document.createTextNode(m[0].slice(u.length))); }
			last = m.index + m[0].length;
		}
		frag.appendChild(document.createTextNode(text.slice(last)));
		return frag;
	}

	function attachmentsBlock(m) {
		var wrap = h('div', { class: 'jem-atts' }, h('div', { class: 'jem-atts-h', text: m.atts.length === 1 ? 'Jedna príloha' : m.atts.length + ' prílohy' }));
		var grid = h('div', { class: 'jem-atts-grid' });
		m.atts.forEach(function (a) {
			var kind = fileKind(a.name, a.type);
			var prev = a.img && a.view ? h('img', { src: a.view, alt: '', loading: 'lazy' }) : h('div', { class: 'jem-att-big jem-fk-' + kind }, h('span', { text: (a.name.split('.').pop() || '').toUpperCase().slice(0, 4) }));
			grid.appendChild(h('div', { class: 'jem-att' },
				h('a', { class: 'jem-att-prev', href: a.view || a.url, target: a.view ? '_blank' : null, rel: 'noopener', title: 'Otvoriť ' + a.name }, prev),
				h('div', { class: 'jem-att-foot' },
					h('span', { class: 'jem-chip-ic jem-fk-' + kind }),
					h('span', { class: 'jem-att-name', title: a.name, text: a.name }),
					h('span', { class: 'jem-att-size', text: size(a.size) }),
					h('a', { class: 'jem-ib jem-ib-sm', href: a.url, title: 'Stiahnuť' }, icon('download')))));
		});
		wrap.appendChild(grid);
		return wrap;
	}

	function reloadThread(t) { delete t.msgs; openThread(t); }
	function trustSender(t, m) {
		api('trust', { email: m.from.e }).then(function () { toast('Obrázky od ' + m.from.e + ' sa budú zobrazovať vždy.'); reloadThread(t); }).catch(function (e) { toast(e.message, 'err'); });
	}
	function deleteOne(t, m) {
		var trash = m.k === 'trash';
		if (trash && !confirm('Natrvalo zmazať túto správu?')) { return; }
		api('act', { op: 'trash', members: [{ k: m.k, u: [m.u] }] }).then(function () {
			toast(trash ? 'Správa bola natrvalo odstránená.' : 'Správa bola presunutá do koša.');
			t.msgs = t.msgs.filter(function (x) { return x !== m; });
			t.members.forEach(function (g) { if (g.k === m.k) { g.u = g.u.filter(function (u) { return u !== m.u; }); } });
			if (!t.msgs.length) { go(S.view, S.page, S.q); loadList(true); } else { renderMessages(t, el.thread.querySelector('.jem-msgs')); }
		}).catch(function (e) { toast(e.message, 'err'); });
	}
	function printThread() {
		var t = S.current; if (!t || !t.msgs) { return; }
		var w = window.open('', '_blank'); if (!w) { return; }
		var d = w.document;
		d.open(); d.write('<!doctype html><meta charset="utf-8"><title></title><style>body{font:14px Arial,sans-serif;margin:24px;color:#222}h1{font-size:20px}.m{border-top:1px solid #ccc;padding:12px 0}.h{color:#555;margin-bottom:8px}pre{white-space:pre-wrap;font:inherit}iframe{width:100%;border:0}</style><h1></h1>'); d.close();
		d.title = t.subject || '';
		d.querySelector('h1').textContent = t.subject || '';
		t.msgs.forEach(function (m) {
			var div = d.createElement('div'); div.className = 'm';
			var hd = d.createElement('div'); hd.className = 'h'; hd.textContent = (m.from.n ? m.from.n + ' <' + m.from.e + '>' : m.from.e) + ' · ' + fullDate(m.date);
			div.appendChild(hd);
			if (m.doc !== undefined) { var f = d.createElement('iframe'); f.setAttribute('sandbox', 'allow-same-origin'); f.srcdoc = m.doc; f.onload = function () { try { f.style.height = f.contentDocument.documentElement.scrollHeight + 'px'; } catch (e) {} }; div.appendChild(f); }
			else { var p = d.createElement('pre'); p.textContent = m.text || ''; div.appendChild(p); }
			d.body.appendChild(div);
		});
		setTimeout(function () { w.print(); }, 800);
	}

	/* ================= Písanie správ ================= */

	var composers = [];

	function loadContacts() {
		if (S.contacts) { return Promise.resolve(S.contacts); }
		return api('contacts', {}).then(function (c) { S.contacts = c || []; return S.contacts; }).catch(function () { S.contacts = []; return S.contacts; });
	}

	function inlineReply(t, m, mode, box) {
		var existing = box.querySelector('.jem-compose.is-inline');
		if (existing && existing._c) { existing._c.focus(); return; }
		var bar = box.querySelector('.jem-reply-bar');
		var c = new Compose({ mode: mode, ref: { k: m.k, u: m.u }, inline: true, thread: t });
		if (bar) { bar.replaceWith(c.el); } else { box.appendChild(c.el); }
		c.el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}
	function openCompose(o) {
		if (composers.length >= 3) { composers[0].minimize(true); }
		var c = new Compose(o);
		el.dock.appendChild(c.el);
		return c;
	}
	function openDraft(id) {
		var open = composers.filter(function (c) { return c.draftId === id; })[0];
		if (open) { open.minimize(false); open.focus(); return; }
		api('draft_get', { id: id }).then(function (d) {
			if (d.ref_k && d.mode !== 'new' && S.view === 'drafts') {
				openCompose({ mode: d.mode, ref: { k: d.ref_k, u: d.ref_u }, draft: d });
			} else { openCompose({ mode: d.mode || 'new', draft: d }); }
		}).catch(function (e) { toast(e.message, 'err'); });
	}
	function discardDrafts(list) {
		Promise.all(list.map(function (t) { return api('draft', { op: 'delete', id: t.draft }); })).then(function (r) {
			var last = r[r.length - 1]; if (last) { S.counts.drafts = last.drafts; renderNav(); }
			toast(list.length > 1 ? 'Koncepty boli zahodené.' : 'Koncept bol zahodený.');
			S.sel = {}; loadList(true);
		}).catch(function (e) { toast(e.message, 'err'); });
	}

	function Compose(o) {
		var self = this;
		this.o = o || {};
		this.mode = this.o.mode || 'new';
		this.ref = this.o.ref || null;
		this.inline = !!this.o.inline;
		this.files = [];
		this.fwdAtts = [];
		this.draftId = this.o.draft ? this.o.draft.id : '';
		this.dirty = false;
		this.closed = false;
		composers.push(this);

		var titles = { new: 'Nová správa', reply: 'Odpoveď', replyall: 'Odpoveď všetkým', forward: 'Preposlanie' };
		this.title = h('span', { class: 'jem-cw-title', text: titles[this.mode] || 'Nová správa' });

		// Od
		this.from = null;
		if (S.me.length > 1) {
			this.from = h('select', { class: 'jem-cw-from', 'aria-label': 'Od' });
			S.me.forEach(function (m) { self.from.appendChild(h('option', { value: m.email, text: (m.name ? m.name + ' ' : '') + '<' + m.email + '>' })); });
		}
		this.to = new Recipients('Komu');
		this.cc = new Recipients('Kópia');
		this.bcc = new Recipients('Skrytá kópia');
		this.ccRow = this.cc.row; this.ccRow.hidden = true;
		this.bccRow = this.bcc.row; this.bccRow.hidden = true;
		this.to.row.appendChild(h('span', { class: 'jem-cw-ccb' },
			h('button', { class: 'jem-link', text: 'Kópia', onclick: function () { self.ccRow.hidden = false; self.cc.input.focus(); this.remove(); } }),
			h('button', { class: 'jem-link', text: 'Skrytá', onclick: function () { self.bccRow.hidden = false; self.bcc.input.focus(); this.remove(); } })));
		this.subject = h('input', { type: 'text', class: 'jem-cw-subj', placeholder: 'Predmet', 'aria-label': 'Predmet', oninput: function () { self.changed(); } });
		this.editor = h('div', { class: 'jem-editor', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Text správy', oninput: function () { self.changed(); },
			onpaste: function (e) { self.paste(e); }, onkeydown: function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); self.send(); } } });
		this.attEl = h('div', { class: 'jem-cw-atts' });
		this.fileInput = h('input', { type: 'file', multiple: true, hidden: true, onchange: function () { Array.prototype.forEach.call(this.files, function (f) { self.addFile(f); }); this.value = ''; } });
		this.fmtBar = h('div', { class: 'jem-fmt', hidden: true },
			fmtBtn('undo', 'Späť', 'undo'), fmtBtn('redo', 'Znova', 'redo'), h('span', { class: 'jem-tb-sep' }),
			fmtTxt('B', 'Tučné (Ctrl+B)', 'bold', 'jem-fmt-b'), fmtTxt('I', 'Kurzíva (Ctrl+I)', 'italic', 'jem-fmt-i'), fmtTxt('U', 'Podčiarknuté (Ctrl+U)', 'underline', 'jem-fmt-u'),
			h('span', { class: 'jem-tb-sep' }),
			fmtBtn('olist', 'Číslovaný zoznam', 'insertOrderedList'), fmtBtn('list', 'Odrážky', 'insertUnorderedList'), fmtBtn('quote', 'Citácia', 'formatBlock', 'blockquote'),
			h('span', { class: 'jem-tb-sep' }),
			h('button', { class: 'jem-ib jem-ib-sm', title: 'Vložiť odkaz', onmousedown: function (e) { e.preventDefault(); self.link(); } }, icon('link')),
			fmtBtn('clearfmt', 'Odstrániť formátovanie', 'removeFormat'));
		function fmtBtn(ic, title, cmd, val) { return h('button', { class: 'jem-ib jem-ib-sm', title: title, onmousedown: function (e) { e.preventDefault(); document.execCommand(cmd, false, val || null); self.changed(); } }, icon(ic)); }
		function fmtTxt(txt, title, cmd, cls) { return h('button', { class: 'jem-ib jem-ib-sm ' + cls, title: title, text: txt, onmousedown: function (e) { e.preventDefault(); document.execCommand(cmd, false, null); self.changed(); } }); }

		this.sendBtn = h('button', { class: 'jem-send', onclick: function () { self.send(); } }, h('span', { text: 'Odoslať' }));
		var foot = h('div', { class: 'jem-cw-foot' },
			this.sendBtn,
			h('button', { class: 'jem-ib', title: 'Možnosti formátovania', onclick: function () { self.fmtBar.hidden = !self.fmtBar.hidden; this.classList.toggle('is-on', !self.fmtBar.hidden); } }, icon('textfmt')),
			h('button', { class: 'jem-ib', title: 'Priložiť súbory', onclick: function () { self.fileInput.click(); } }, icon('clip')),
			h('button', { class: 'jem-ib', title: 'Vložiť odkaz', onclick: function () { self.link(); } }, icon('link')),
			h('div', { class: 'jem-tb-space' }),
			this.status = h('span', { class: 'jem-cw-status' }),
			h('button', { class: 'jem-ib', title: 'Zahodiť koncept', onclick: function () { self.discard(); } }, icon('trash')));

		var fields = h('div', { class: 'jem-cw-fields' },
			this.from ? h('div', { class: 'jem-cw-row' }, h('span', { class: 'jem-cw-lbl', text: 'Od' }), this.from) : null,
			this.to.row, this.ccRow, this.bccRow,
			this.inline ? null : h('div', { class: 'jem-cw-row' }, this.subject));
		var bodyWrap = h('div', { class: 'jem-cw-body', ondragover: function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') >= 0) { e.preventDefault(); this.classList.add('is-drop'); } },
			ondragleave: function () { this.classList.remove('is-drop'); },
			ondrop: function (e) { if (e.dataTransfer.files.length) { e.preventDefault(); this.classList.remove('is-drop'); Array.prototype.forEach.call(e.dataTransfer.files, function (f) { self.addFile(f); }); } } },
			this.editor, this.attEl);

		if (this.inline) {
			this.el = h('div', { class: 'jem-compose is-inline' },
				h('div', { class: 'jem-cw-inline-head' }, avatar(S.me[0] ? S.me[0].name : '', S.me[0] ? S.me[0].email : '', true), h('span', { class: 'jem-cw-mode' }, icon(this.mode === 'forward' ? 'forward' : this.mode === 'replyall' ? 'replyall' : 'reply')), this.title),
				fields, bodyWrap, this.fmtBar, foot, this.fileInput);
		} else {
			this.head = h('div', { class: 'jem-cw-head', onclick: function (e) { if (!e.target.closest('button')) { self.minimize(!self.el.classList.contains('is-min')); } } },
				this.title, h('div', { class: 'jem-tb-space' }),
				h('button', { class: 'jem-cw-hb', title: 'Minimalizovať', onclick: function () { self.minimize(!self.el.classList.contains('is-min')); } }, icon('minimize')),
				h('button', { class: 'jem-cw-hb', title: 'Celá obrazovka', onclick: function () { self.el.classList.toggle('is-max'); self.el.classList.remove('is-min'); } }, icon('expand')),
				h('button', { class: 'jem-cw-hb', title: 'Uložiť a zavrieť', onclick: function () { self.close(); } }, icon('x')));
			this.el = h('div', { class: 'jem-compose is-float' }, this.head, h('div', { class: 'jem-cw-inner' }, fields, bodyWrap, this.fmtBar, foot, this.fileInput));
		}
		this.el._c = this;
		this.el.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !self.inline) { e.stopPropagation(); self.close(); } });

		var sig = (S.boot && S.boot.signature || '').trim();
		var sigHtml = sig ? '<br><div class="jem-sig">-- <br>' + escHtml(sig).replace(/\n/g, '<br>') + '</div>' : '';
		if (this.o.draft) {
			var d = this.o.draft;
			this.to.set(d.to); this.cc.set(d.cc); this.bcc.set(d.bcc);
			if (d.cc) { this.ccRow.hidden = false; } if (d.bcc) { this.bccRow.hidden = false; }
			this.subject.value = d.subject || '';
			this.editor.innerHTML = d.html || '';
			if (this.from && d.from) { this.from.value = d.from; }
			this.fwdAtts = []; // prílohy preposlanej správy sa pri koncepte znovu načítajú
			if (this.mode === 'forward' && this.ref) { this.loadRef(true, d.fwd_atts || []); }
		} else if (this.ref) {
			this.editor.innerHTML = '<br>' + sigHtml;
			this.loadRef(false);
		} else {
			this.editor.innerHTML = '<br>' + sigHtml;
			if (this.o.to) { this.to.set(this.o.to); }
		}
		setTimeout(function () { (self.mode === 'forward' || !self.ref) && !self.o.draft ? self.to.input.focus() : self.focus(); }, 30);
		this.timer = null;
	}
	Compose.prototype.loadRef = function (keepBody, keepAtts) {
		var self = this;
		this.status.textContent = 'Načítavam…';
		api('compose_data', { k: this.ref.k, u: this.ref.u, mode: this.mode }).then(function (d) {
			self.status.textContent = '';
			if (!keepBody) {
				if (d.to) { self.to.set(d.to); }
				if (d.cc) { self.cc.set(d.cc); self.ccRow.hidden = false; }
				self.subject.value = d.subject || '';
				if (d.quote) {
					var q = h('div', { class: 'jem-quoted' + (self.mode === 'forward' ? '' : ' is-folded') });
					q.innerHTML = d.quote;
					self.editor.appendChild(q);
					if (self.mode !== 'forward') {
						var dots = h('button', { class: 'jem-dots', contenteditable: 'false', title: 'Zobraziť citovaný text', onclick: function () { q.classList.remove('is-folded'); dots.remove(); } }, '•••');
						self.editor.insertBefore(dots, q);
					}
				}
			}
			if (self.from && d.from) { self.from.value = d.from; }
			self.fwdAtts = (d.atts || []).filter(function (a) { return !keepAtts || keepAtts.indexOf(a.i) >= 0 || !keepBody; }).map(function (a) { return { i: a.i, name: a.name, size: a.size, fwd: true }; });
			self.renderAtts();
			if (self.mode === 'forward' && !keepBody) { self.to.input.focus(); }
		}).catch(function (e) { self.status.textContent = ''; toast(e.message, 'err'); });
	};
	Compose.prototype.focus = function () {
		this.editor.focus();
		var r = document.createRange(); r.setStart(this.editor, 0); r.collapse(true);
		var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
	};
	Compose.prototype.changed = function () {
		var self = this;
		this.dirty = true;
		clearTimeout(this.timer);
		this.timer = setTimeout(function () { self.saveDraft(); }, 2500);
	};
	Compose.prototype.payload = function () {
		var html = this.editor.cloneNode(true);
		Array.prototype.forEach.call(html.querySelectorAll('.jem-dots'), function (d) { d.remove(); });
		Array.prototype.forEach.call(html.querySelectorAll('.jem-quoted'), function (q) { q.classList.remove('is-folded'); });
		return {
			from: this.from ? this.from.value : (S.me[0] ? S.me[0].email : ''),
			to: this.to.value(), cc: this.cc.value(), bcc: this.bcc.value(),
			subject: this.subject.value, html: html.innerHTML, mode: this.mode,
			ref_k: this.ref ? this.ref.k : '', ref_u: this.ref ? this.ref.u : 0,
			fwd_atts: this.fwdAtts.map(function (a) { return a.i; })
		};
	};
	Compose.prototype.isEmpty = function () {
		var txt = this.editor.textContent.replace(/\s|-/g, '');
		var sig = (S.boot && S.boot.signature || '').replace(/\s|-/g, '');
		return !this.to.value() && !this.subject.value.trim() && (txt === '' || txt === sig) && !this.files.length;
	};
	Compose.prototype.saveDraft = function () {
		var self = this;
		if (this.closed || this.sending || !this.dirty || this.isEmpty()) { return Promise.resolve(); }
		this.dirty = false;
		this.status.textContent = 'Ukladá sa…';
		var p = this.payload(); p.id = this.draftId;
		return api('draft', p).then(function (d) {
			self.draftId = d.id; self.status.textContent = 'Koncept uložený';
			S.counts.drafts = d.drafts; renderNav();
			if (self.files.length) { self.status.textContent = 'Koncept uložený (bez príloh)'; }
		}).catch(function () { self.status.textContent = 'Koncept sa neuložil'; });
	};
	Compose.prototype.addFile = function (f) {
		if (f.size > 25 * 1048576) { toast('Súbor ' + f.name + ' je väčší ako 25 MB.', 'err'); return; }
		this.files.push(f); this.renderAtts(); this.changed();
	};
	Compose.prototype.renderAtts = function () {
		var self = this;
		clear(this.attEl);
		this.fwdAtts.concat(this.files).forEach(function (f) {
			self.attEl.appendChild(h('div', { class: 'jem-cw-att' },
				h('span', { class: 'jem-chip-ic jem-fk-' + fileKind(f.name, f.type) }),
				h('span', { class: 'jem-cw-att-name', text: f.name }),
				h('span', { class: 'jem-cw-att-size', text: '(' + size(f.size) + ')' }),
				h('button', { class: 'jem-ib jem-ib-xs', title: 'Odstrániť prílohu', onclick: function () {
					if (f.fwd) { self.fwdAtts = self.fwdAtts.filter(function (x) { return x !== f; }); } else { self.files = self.files.filter(function (x) { return x !== f; }); }
					self.renderAtts(); self.changed();
				} }, icon('x'))));
		});
	};
	Compose.prototype.paste = function (e) {
		var cd = e.clipboardData; if (!cd) { return; }
		var files = Array.prototype.filter.call(cd.files || [], function (f) { return /^image\//.test(f.type); });
		if (files.length) { e.preventDefault(); var self = this; files.forEach(function (f) { self.addFile(new File([f], f.name || 'obrazok.png', { type: f.type })); }); toast('Obrázok bol pridaný ako príloha.'); return; }
		var html = cd.getData('text/html');
		if (html) { // vloženie s formátovaním, ale bez štýlov a skriptov cudzej stránky
			e.preventDefault();
			var tmp = document.createElement('div'); tmp.innerHTML = html;
			Array.prototype.forEach.call(tmp.querySelectorAll('script,style,meta,link,iframe,object,embed,form,input,button'), function (n) { n.remove(); });
			Array.prototype.forEach.call(tmp.querySelectorAll('*'), function (n) {
				Array.prototype.slice.call(n.attributes).forEach(function (a) { if (!/^(href|src|alt|colspan|rowspan)$/.test(a.name) || /^\s*javascript:/i.test(a.value)) { n.removeAttribute(a.name); } });
			});
			document.execCommand('insertHTML', false, tmp.innerHTML);
		}
	};
	Compose.prototype.link = function () {
		var sel = window.getSelection();
		var url = prompt('Adresa odkazu (URL):', 'https://');
		if (!url || url === 'https://') { return; }
		if (!/^(https?:|mailto:)/i.test(url)) { url = 'https://' + url; }
		this.editor.focus();
		if (sel && sel.toString()) { document.execCommand('createLink', false, url); }
		else { document.execCommand('insertHTML', false, '<a href="' + escHtml(url) + '">' + escHtml(url) + '</a>'); }
		this.changed();
	};
	Compose.prototype.minimize = function (on) { if (!this.inline) { this.el.classList.toggle('is-min', !!on); this.el.classList.remove('is-max'); } };
	Compose.prototype.send = function () {
		var self = this;
		if (this.sending) { return; }
		this.to.commit(); this.cc.commit(); this.bcc.commit();
		var p = this.payload();
		if (!p.to && !p.cc && !p.bcc) { toast('Zadajte aspoň jedného príjemcu.', 'err'); this.to.input.focus(); return; }
		var bad = this.to.invalid().concat(this.cc.invalid(), this.bcc.invalid());
		if (bad.length) { toast('Neplatná adresa: ' + bad.join(', '), 'err'); return; }
		if (!p.subject.trim() && this.mode === 'new' && !confirm('Odoslať správu bez predmetu?')) { return; }
		var att = this.fwdAtts.length + this.files.length;
		if (!att && /(príloh|prilo[žz]|v prílohe|attach)/i.test(this.editor.textContent.split('napísal(a):')[0]) && !confirm('V texte spomínate prílohu, ale žiadnu ste nepridali. Odoslať aj tak?')) { return; }
		this.sending = true;
		clearTimeout(this.timer);
		this.sendBtn.disabled = true; this.sendBtn.firstChild.textContent = 'Odosiela sa…';
		p.draft = this.draftId;
		api('send', p, this.files).then(function (d) {
			S.counts.drafts = d.drafts; renderNav();
			self.closed = true; self.remove();
			toast('Správa bola odoslaná.');
			S.contacts = null;
			if (self.o.thread && S.current === self.o.thread) { reloadThreadSoon(self.o.thread); }
			else if (S.view === 'sent' || S.view === 'drafts') { loadList(true); }
		}).catch(function (e) {
			self.sending = false; self.sendBtn.disabled = false; self.sendBtn.firstChild.textContent = 'Odoslať';
			toast(e.message, 'err');
		});
	};
	Compose.prototype.close = function () {
		var self = this;
		if (this.isEmpty()) { if (this.draftId) { api('draft', { op: 'delete', id: this.draftId }).then(function (d) { S.counts.drafts = d.drafts; renderNav(); }); } this.closed = true; this.remove(); return; }
		this.dirty = true;
		clearTimeout(this.timer);
		this.saveDraft().then(function () { self.closed = true; self.remove(); toast('Koncept bol uložený.'); if (S.view === 'drafts') { loadList(true); } });
	};
	Compose.prototype.discard = function () {
		var self = this;
		clearTimeout(this.timer);
		this.closed = true;
		if (this.draftId) { api('draft', { op: 'delete', id: this.draftId }).then(function (d) { S.counts.drafts = d.drafts; renderNav(); if (S.view === 'drafts') { loadList(true); } }); }
		this.remove();
		toast('Koncept bol zahodený.');
	};
	Compose.prototype.remove = function () {
		var self = this;
		composers = composers.filter(function (c) { return c !== self; });
		if (this.inline && this.o.thread && this.el.parentNode) {
			var box = this.el.parentNode; this.el.remove();
			if (S.current === this.o.thread) { renderMessages(this.o.thread, box); }
		} else { this.el.remove(); }
	};
	function reloadThreadSoon(t) { setTimeout(function () { if (S.current === t) { t.members = t.members || []; loadList(true).then(function () { var nt = findThread(t.id) || t; if (S.threadId === t.id) { reloadThread(nt); } }); } }, 600); }
	function escHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

	/** Pole adries s „čipmi“ a našepkávaním z kontaktov. */
	function Recipients(label) {
		var self = this;
		this.chips = [];
		this.box = h('div', { class: 'jem-rcp', onclick: function (e) { if (e.target === self.box) { self.input.focus(); } } });
		this.input = h('input', { type: 'text', class: 'jem-rcp-input', 'aria-label': label, autocomplete: 'off',
			oninput: function () { self.suggest(); },
			onkeydown: function (e) {
				var sug = self.sugEl && !self.sugEl.hidden ? self.sugEl.querySelectorAll('.jem-sug') : [];
				if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && sug.length) { e.preventDefault(); self.si = Math.max(0, Math.min(sug.length - 1, (self.si || 0) + (e.key === 'ArrowDown' ? 1 : -1))); self.markSug(); return; }
				if ((e.key === 'Enter' || e.key === 'Tab') && sug.length && self.si >= 0) { e.preventDefault(); sug[self.si].dispatchEvent(new MouseEvent('mousedown')); return; }
				if (e.key === 'Enter' || e.key === ',' || e.key === ';') { if (self.input.value.trim()) { e.preventDefault(); self.commit(); } }
				if (e.key === 'Backspace' && !self.input.value && self.chips.length) { self.removeChip(self.chips[self.chips.length - 1]); }
				if (e.key === 'Escape' && self.sugEl) { self.sugEl.hidden = true; }
			},
			onblur: function () { setTimeout(function () { self.commit(); if (self.sugEl) { self.sugEl.hidden = true; } }, 150); },
			onpaste: function () { setTimeout(function () { self.commit(); }, 0); } });
		this.box.appendChild(this.input);
		this.sugEl = h('div', { class: 'jem-sugs', hidden: true });
		this.row = h('div', { class: 'jem-cw-row jem-cw-rcp' }, h('span', { class: 'jem-cw-lbl', text: label }), h('div', { class: 'jem-rcp-wrap' }, this.box, this.sugEl));
	}
	Recipients.prototype.parse = function (str) {
		var out = [];
		String(str || '').split(/[,;](?=(?:[^"]*"[^"]*")*[^"]*$)/).forEach(function (p) {
			p = p.trim(); if (!p) { return; }
			var m = p.match(/^(.*)<([^>]+)>\s*$/);
			if (m) { out.push({ n: m[1].trim().replace(/^"|"$/g, ''), e: m[2].trim() }); } else { out.push({ n: '', e: p.replace(/^<|>$/g, '') }); }
		});
		return out;
	};
	Recipients.prototype.set = function (str) { var self = this; this.chips.slice().forEach(function (c) { self.removeChip(c); }); this.parse(str).forEach(function (a) { self.addChip(a); }); };
	Recipients.prototype.commit = function () { var self = this; var v = this.input.value; if (!v.trim()) { return; } this.input.value = ''; this.parse(v).forEach(function (a) { self.addChip(a); }); if (this.sugEl) { this.sugEl.hidden = true; } };
	Recipients.prototype.addChip = function (a) {
		var self = this;
		if (this.chips.some(function (c) { return c.a.e.toLowerCase() === a.e.toLowerCase(); })) { return; }
		var ok = /^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(a.e);
		var chip = { a: a, el: h('span', { class: 'jem-rcp-chip' + (ok ? '' : ' is-bad'), title: a.e }, avatar(a.n, a.e, false), h('span', { text: a.n || a.e }),
			h('button', { class: 'jem-rcp-x', title: 'Odstrániť', onclick: function (e) { e.stopPropagation(); self.removeChip(chip); } }, icon('x'))) };
		this.chips.push(chip);
		this.box.insertBefore(chip.el, this.input);
	};
	Recipients.prototype.removeChip = function (c) { this.chips = this.chips.filter(function (x) { return x !== c; }); c.el.remove(); };
	Recipients.prototype.value = function () { return this.chips.map(function (c) { return c.a.n ? '"' + c.a.n.replace(/"/g, '') + '" <' + c.a.e + '>' : c.a.e; }).join(', '); };
	Recipients.prototype.invalid = function () { return this.chips.filter(function (c) { return c.el.classList.contains('is-bad'); }).map(function (c) { return c.a.e; }); };
	Recipients.prototype.suggest = function () {
		var self = this, q = this.input.value.trim().toLowerCase();
		if (q.length < 1) { this.sugEl.hidden = true; return; }
		loadContacts().then(function (list) {
			if (self.input.value.trim().toLowerCase() !== q) { return; }
			var hits = list.filter(function (c) { return c.e.indexOf(q) >= 0 || (c.n || '').toLowerCase().indexOf(q) >= 0; }).slice(0, 7);
			clear(self.sugEl);
			if (!hits.length) { self.sugEl.hidden = true; return; }
			hits.forEach(function (c) {
				self.sugEl.appendChild(h('div', { class: 'jem-sug', onmousedown: function (e) { e.preventDefault(); self.input.value = ''; self.addChip({ n: c.n, e: c.e }); self.sugEl.hidden = true; self.input.focus(); } },
					avatar(c.n, c.e, false), h('div', null, h('div', { class: 'jem-sug-n', text: c.n || c.e }), c.n ? h('div', { class: 'jem-sug-e', text: c.e }) : null)));
			});
			self.si = 0; self.markSug(); self.sugEl.hidden = false;
		});
	};
	Recipients.prototype.markSug = function () { var self = this; Array.prototype.forEach.call(this.sugEl.querySelectorAll('.jem-sug'), function (s, i) { s.classList.toggle('is-active', i === self.si); }); };

	/* ================= Oznámenia a skratky ================= */

	var toastTimer;
	function toast(msg, type) {
		clear(el.toast);
		el.toast.className = 'jem-toast is-on' + (type ? ' is-' + type : '');
		el.toast.appendChild(h('span', { text: msg }));
		el.toast.appendChild(h('button', { class: 'jem-ib jem-ib-xs', title: 'Zavrieť', onclick: function () { el.toast.className = 'jem-toast'; } }, icon('x')));
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { el.toast.className = 'jem-toast'; }, type === 'err' ? 9000 : 5000);
	}

	function showShortcuts() {
		var rows = [['c', 'Napísať'], ['/', 'Hľadať'], ['j / k', 'Ďalšia / predošlá konverzácia'], ['o alebo Enter', 'Otvoriť'], ['u', 'Späť na zoznam'], ['x', 'Vybrať konverzáciu'],
			['e', 'Archivovať'], ['#', 'Odstrániť'], ['!', 'Nahlásiť spam'], ['s', 'Hviezdička'], ['Shift + i / u', 'Prečítané / neprečítané'], ['r', 'Odpovedať'], ['a', 'Odpovedať všetkým'], ['f', 'Preposlať'], ['Ctrl + Enter', 'Odoslať správu'], ['Esc', 'Zavrieť okno']];
		var box = h('div', { class: 'jem-modal-back', onclick: function (e) { if (e.target === box) { box.remove(); } } },
			h('div', { class: 'jem-modal', role: 'dialog', 'aria-label': 'Klávesové skratky' },
				h('div', { class: 'jem-modal-head' }, h('h3', { text: 'Klávesové skratky' }), h('button', { class: 'jem-ib', title: 'Zavrieť', onclick: function () { box.remove(); } }, icon('x'))),
				h('table', { class: 'jem-keys' }, rows.map(function (r) { return h('tr', null, h('td', null, h('kbd', { text: r[0] })), h('td', { text: r[1] })); }))));
		document.body.appendChild(box);
	}

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { closeMenus(); var mb = document.querySelector('.jem-modal-back'); if (mb) { mb.remove(); } }
		var t = e.target;
		if (e.ctrlKey || e.metaKey || e.altKey || (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)))) { return; }
		if (document.querySelector('.jem-modal-back')) { return; }
		var k = e.key;
		if (k === 'c') { e.preventDefault(); openCompose({ mode: 'new' }); return; }
		if (k === '/') { e.preventDefault(); el.search.focus(); return; }
		if (k === '?') { showShortcuts(); return; }
		if (S.mode === 'thread' && S.current) {
			var cur = S.current, msgs = cur.msgs || [], last = msgs[msgs.length - 1], box = el.thread && el.thread.querySelector('.jem-msgs');
			if (k === 'u') { go(S.view, S.page, S.q); }
			else if (k === 'e') { act('archive', [cur]); }
			else if (k === '#') { act('trash', [cur]); }
			else if (k === '!') { act('spam', [cur]); }
			else if (k === 's') { toggleStar(cur); }
			else if (k === 'U') { act('unread', [cur]); }
			else if ((k === 'r' || k === 'a' || k === 'f') && last && box) { e.preventDefault(); inlineReply(cur, last, k === 'r' ? 'reply' : k === 'a' ? 'replyall' : 'forward', box); }
			else if (k === 'j' || k === 'k') { var i = S.threads.indexOf(cur), n = S.threads[i + (k === 'j' ? 1 : -1)]; if (n) { go(S.view, S.page, S.q, n.id); } }
			return;
		}
		if (S.mode !== 'list' || !S.threads.length) { return; }
		if (k === 'j' || k === 'k') {
			e.preventDefault();
			S.focus = Math.max(0, Math.min(S.threads.length - 1, S.focus + (k === 'j' ? 1 : -1)));
			renderMain();
			var r = el.list && el.list.querySelectorAll('.jem-row')[S.focus]; if (r) { r.scrollIntoView({ block: 'nearest' }); }
			return;
		}
		var ft = S.threads[S.focus];
		var targets = selectedThreads().length ? selectedThreads() : (ft ? [ft] : []);
		if ((k === 'o' || k === 'Enter') && ft) { if (ft.draft) { openDraft(ft.draft); } else { go(S.view, S.page, S.q, ft.id); } }
		else if (k === 'x' && ft) { if (S.sel[ft.id]) { delete S.sel[ft.id]; } else { S.sel[ft.id] = true; } renderMain(); }
		else if (k === 'e') { act('archive', targets); }
		else if (k === '#') { act('trash', targets); }
		else if (k === '!') { act('spam', targets); }
		else if (k === 's' && ft) { toggleStar(ft); }
		else if (k === 'I') { act('read', targets); }
		else if (k === 'U') { act('unread', targets); }
	});
	document.addEventListener('mousedown', function (e) { if (!e.target.closest('.jem-menu')) { closeMenus(); } });
	window.addEventListener('resize', closeMenus);
	window.addEventListener('hashchange', route);
	window.addEventListener('beforeunload', function (e) {
		var pending = composers.filter(function (c) { return c.dirty && !c.isEmpty(); });
		pending.forEach(function (c) { c.saveDraft(); });
		if (composers.some(function (c) { return c.sending; })) { e.preventDefault(); e.returnValue = ''; }
	});

	// Nová pošta: kontrola každú minútu (len keď je okno viditeľné a nič sa nedeje).
	setInterval(function () {
		if (document.hidden || S.busy || !S.boot) { return; }
		if (S.mode === 'list' && S.page === 1 && !selectedThreads().length && !document.querySelector('.jem-menu') && S.view !== 'drafts') {
			var before = S.counts.inbox || 0;
			loadList(true).then(function () { if ((S.counts.inbox || 0) > before && S.view !== 'inbox') { toast('Máte novú poštu v Doručených.'); } });
		} else {
			api('counts', {}).then(function (d) { S.counts = d.counts; renderNav(); }).catch(function () {});
		}
	}, 60000);

	/* ================= Štart ================= */

	function start() {
		if (!C.configured) {
			S.me = [];
			renderShell(); renderNav(); renderSetup(); return;
		}
		renderShell();
		renderNav();
		setBusy(true);
		el.main.appendChild(h('div', { class: 'jem-loading', text: 'Pripájam sa k schránke…' }));
		api('boot', {}).then(function (b) {
			S.boot = b; S.me = b.me || []; S.counts = b.counts || {}; S.custom = b.custom || []; S.per = b.per || 50;
			renderShell();
			if (window.innerWidth < 900) { root.classList.add('jem-nav-collapsed'); }
			route();
		}).catch(function (e) {
			setBusy(false);
			if (e.data && e.data.setup) { renderSetup(); return; }
			clear(el.main).appendChild(h('div', { class: 'jem-empty jem-empty-err' },
				h('h2', { text: 'Nepodarilo sa pripojiť k schránke' }), h('p', { text: e.message }),
				h('div', null, h('button', { class: 'jem-btn', text: 'Skúsiť znova', onclick: start }), ' ', h('a', { class: 'jem-btn', href: C.settings, text: 'Nastavenia' }))));
		});
	}
	start();
})();
