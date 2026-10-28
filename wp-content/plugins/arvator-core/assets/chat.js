/* Arvator — assistant de discussion. Aucune dépendance. Les messages ne sont gardés que dans l'onglet (sessionStorage). */
(function () {
	'use strict';

	var cfg = window.ARVATOR_CHAT;
	if (!cfg || !cfg.url || !window.fetch) {
		return;
	}
	var t = cfg.i18n || {};
	var KEY = 'arvChat';
	var MAX_KEEP = 30;   // messages gardés à l'écran
	var MAX_SEND = 8;    // messages envoyés à l'assistant (le workflow n'en lit pas plus)
	var MAX_LEN = 600;   // caractères par message (idem)
	var history = load();
	var busy = false;

	/* ------------------------------------------------------------ Construction */

	var icons = {
		chat: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/></svg>',
		close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
		reset: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>',
		send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
	};

	var root = el('div', 'arv-chat');
	var bubble = el('button', 'arv-chat__bubble');
	bubble.type = 'button';
	bubble.innerHTML = icons.chat;
	bubble.setAttribute('aria-label', t.open || '');
	bubble.setAttribute('aria-expanded', 'false');
	bubble.setAttribute('aria-controls', 'arv-chat-panel');

	var panel = el('section', 'arv-chat__panel');
	panel.id = 'arv-chat-panel';
	panel.hidden = true;
	panel.setAttribute('role', 'dialog');
	panel.setAttribute('aria-labelledby', 'arv-chat-title');

	var head = el('header', 'arv-chat__head');
	var avatar = el('span', 'arv-chat__avatar');
	avatar.textContent = 'A';
	avatar.setAttribute('aria-hidden', 'true');
	var who = el('div', 'arv-chat__who');
	var title = el('h2', 'arv-chat__title');
	title.id = 'arv-chat-title';
	title.textContent = t.title || '';
	var status = el('p', 'arv-chat__status');
	status.textContent = t.status || '';
	who.appendChild(title);
	who.appendChild(status);
	var resetBtn = iconButton(icons.reset, t.reset);
	var closeBtn = iconButton(icons.close, t.close);
	head.appendChild(avatar);
	head.appendChild(who);
	head.appendChild(resetBtn);
	head.appendChild(closeBtn);

	var log = el('div', 'arv-chat__log');
	log.setAttribute('role', 'log');
	log.setAttribute('aria-live', 'polite');

	var form = el('form', 'arv-chat__form');
	form.noValidate = true;
	var input = el('textarea', 'arv-chat__input');
	input.rows = 1;
	input.maxLength = MAX_LEN;
	input.placeholder = t.placeholder || '';
	input.setAttribute('aria-label', t.label || '');
	var send = el('button', 'arv-chat__send');
	send.type = 'submit';
	send.innerHTML = icons.send;
	send.setAttribute('aria-label', t.send || '');
	form.appendChild(input);
	form.appendChild(send);

	var notice = el('p', 'arv-chat__notice');
	notice.appendChild(document.createTextNode((t.notice || '') + ' '));
	if (t.privacyUrl) {
		var pl = el('a');
		pl.href = t.privacyUrl;
		pl.textContent = t.privacy || '';
		notice.appendChild(pl);
	}

	panel.appendChild(head);
	panel.appendChild(log);
	panel.appendChild(form);
	panel.appendChild(notice);
	root.appendChild(panel);
	root.appendChild(bubble);
	document.body.appendChild(root);

	render();

	/* ------------------------------------------------------------ Ouverture / fermeture */

	function open() {
		panel.hidden = false;
		root.classList.add('is-open');
		bubble.setAttribute('aria-expanded', 'true');
		if (window.matchMedia('(max-width: 600px)').matches) {
			document.documentElement.classList.add('arv-chat-lock');
		}
		scrollDown();
		input.focus();
	}

	function close() {
		panel.hidden = true;
		root.classList.remove('is-open');
		bubble.setAttribute('aria-expanded', 'false');
		document.documentElement.classList.remove('arv-chat-lock');
		bubble.focus();
	}

	bubble.addEventListener('click', function () {
		if (panel.hidden) {
			open();
		} else {
			close();
		}
	});
	closeBtn.addEventListener('click', close);
	panel.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			close();
		}
	});
	resetBtn.addEventListener('click', function () {
		if (busy) {
			return;
		}
		history = [];
		save();
		render();
		input.focus();
	});

	/* ------------------------------------------------------------ Envoi */

	input.addEventListener('input', grow);
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
			e.preventDefault();
			submit();
		}
	});
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		submit();
	});

	function submit() {
		var text = input.value.trim().slice(0, MAX_LEN);
		if (!text || busy) {
			return;
		}
		input.value = '';
		grow();
		history.push({ role: 'user', content: text });
		save();
		addBubble('user', text);
		setBusy(true);
		var typing = addTyping();

		var ctrl = window.AbortController ? new AbortController() : null;
		var timer = setTimeout(function () {
			if (ctrl) {
				ctrl.abort();
			}
		}, 45000);

		fetch(cfg.url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ messages: outgoing() }),
			credentials: 'omit',
			signal: ctrl ? ctrl.signal : undefined
		})
			.then(function (r) {
				if (!r.ok) {
					throw new Error('HTTP ' + r.status);
				}
				return r.json();
			})
			.then(function (data) {
				var reply = data && typeof data.reply === 'string' ? data.reply.trim() : '';
				if (!reply) {
					throw new Error('empty');
				}
				history.push({ role: 'assistant', content: reply });
				save();
				typing.remove();
				addBubble('bot', reply);
			})
			.catch(function () {
				typing.remove();
				addBubble('bot', t.error || '', true);
			})
			.then(function () {
				clearTimeout(timer);
				setBusy(false);
				input.focus();
			});
	}

	/** Derniers échanges, en commençant par une question du visiteur. */
	function outgoing() {
		var msgs = history.slice(-MAX_SEND);
		while (msgs.length && msgs[0].role !== 'user') {
			msgs.shift();
		}
		return msgs.map(function (m) {
			return { role: m.role, content: String(m.content).slice(0, MAX_LEN) };
		});
	}

	function setBusy(b) {
		busy = b;
		send.disabled = b;
		resetBtn.disabled = b;
	}

	/* ------------------------------------------------------------ Affichage */

	function render() {
		log.textContent = '';
		addBubble('bot', t.greeting || '');
		history.forEach(function (m) {
			addBubble(m.role === 'user' ? 'user' : 'bot', m.content);
		});
	}

	function addBubble(who, text, isError) {
		var b = el('div', 'arv-chat__msg arv-chat__msg--' + who + (isError ? ' arv-chat__msg--error' : ''));
		linkify(b, who === 'bot' ? String(text).replace(/\*\*(.+?)\*\*/g, '$1') : text); // gras Markdown retiré
		log.appendChild(b);
		scrollDown();
		return b;
	}

	function addTyping() {
		var b = el('div', 'arv-chat__msg arv-chat__msg--bot');
		var dots = el('span', 'arv-chat__typing');
		dots.setAttribute('role', 'status');
		dots.setAttribute('aria-label', t.typing || '');
		dots.innerHTML = '<span></span><span></span><span></span>';
		b.appendChild(dots);
		log.appendChild(b);
		scrollDown();
		return b;
	}

	/** Texte brut (jamais de HTML) ; seules les adresses web et email deviennent des liens. */
	function linkify(node, text) {
		var re = /(https?:\/\/[^\s<>"']+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/g;
		var last = 0;
		var m;
		while ((m = re.exec(text)) !== null) {
			var raw = m[0].replace(/[.,;:!?)\]]+$/, '');
			if (m.index > last) {
				node.appendChild(document.createTextNode(text.slice(last, m.index)));
			}
			var a = el('a');
			a.textContent = raw;
			if (raw.indexOf('@') > 0 && raw.indexOf('://') < 0) {
				a.href = 'mailto:' + raw;
			} else {
				a.href = raw;
				if (!sameSite(raw)) {
					a.target = '_blank';
					a.rel = 'noopener noreferrer';
				}
			}
			node.appendChild(a);
			last = m.index + raw.length;
			re.lastIndex = last;
		}
		if (last < text.length) {
			node.appendChild(document.createTextNode(text.slice(last)));
		}
	}

	function sameSite(url) {
		try {
			var h = new URL(url).hostname.replace(/^www\./, '');
			return h === String(cfg.site || '').replace(/^www\./, '');
		} catch (e) {
			return false;
		}
	}

	function scrollDown() {
		log.scrollTop = log.scrollHeight;
	}

	function grow() {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 120) + 'px';
	}

	/* ------------------------------------------------------------ Outils */

	function el(tag, cls) {
		var n = document.createElement(tag);
		if (cls) {
			n.className = cls;
		}
		return n;
	}

	function iconButton(svg, label) {
		var b = el('button', 'arv-chat__icon-btn');
		b.type = 'button';
		b.innerHTML = svg;
		b.setAttribute('aria-label', label || '');
		b.title = label || '';
		return b;
	}

	function load() {
		try {
			var v = JSON.parse(window.sessionStorage.getItem(KEY) || '[]');
			return Array.isArray(v) ? v.filter(function (m) {
				return m && typeof m.content === 'string' && (m.role === 'user' || m.role === 'assistant');
			}).slice(-MAX_KEEP) : [];
		} catch (e) {
			return [];
		}
	}

	function save() {
		history = history.slice(-MAX_KEEP);
		try {
			window.sessionStorage.setItem(KEY, JSON.stringify(history));
		} catch (e) { /* navigation privée : la conversation n'est simplement pas gardée */ }
	}
}());
