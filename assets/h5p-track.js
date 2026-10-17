/**
 * Filet de sécurité : capte la fin d'une activité H5P (événement xAPI « completed » / « answered »)
 * et envoie le score pour l'avancement de la notion.
 */
(function () {
	'use strict';
	var boxes = document.querySelectorAll('.cf-h5p[data-content]');
	if (!boxes.length || !window.cfH5P) {
		return;
	}
	var byContent = {};
	boxes.forEach(function (b) { byContent[b.getAttribute('data-content')] = b; });
	var sent = {};

	function send(box, score, max) {
		var key = box.getAttribute('data-key') + ':' + score + '/' + max;
		if (sent[key]) { return; }
		sent[key] = true;
		var body = new URLSearchParams({
			action: 'if_h5p_result',
			notion_id: box.getAttribute('data-notion'),
			key: box.getAttribute('data-key'),
			content_id: box.getAttribute('data-content'),
			nonce: box.getAttribute('data-nonce'),
			score: String(score),
			max: String(max)
		});
		fetch(cfH5P.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (!r || !r.success) { return; }
				var st = box.querySelector('.cf-h5p-status');
				if (st) { st.textContent = r.data.status; st.classList.toggle('is-passed', !!r.data.passed); }
				var bar = document.querySelector('.if-summary .if-progress span');
				var txt = document.querySelector('.if-summary small');
				if (bar && r.data.progress) { bar.style.width = r.data.progress.percent + '%'; }
				if (txt && r.data.progress) { txt.textContent = r.data.progress.percent + ' % — ' + r.data.progress.done + ' / ' + r.data.progress.total; }
			})
			.catch(function () {});
	}

	function onXapi(event) {
		try {
			var st = event.data.statement;
			var verb = st.verb && st.verb.id || '';
			if (!/(completed|answered)$/.test(verb) || !st.result || !st.result.score) { return; }
			// Seulement l'activité entière, pas une sous-question.
			var parent = st.context && st.context.contextActivities && st.context.contextActivities.parent;
			if (parent && parent.length) { return; }
			var id = String(event.getVerifiedStatementValue(['object', 'definition', 'extensions', 'http://h5p.org/x-api/h5p-local-content-id']));
			var box = byContent[id];
			if (box) { send(box, st.result.score.raw || 0, st.result.score.max || 0); }
		} catch (e) { /* rien */ }
	}

	var hooked = [];
	function hook(win) {
		try {
			if (win && win.H5P && win.H5P.externalDispatcher && hooked.indexOf(win) === -1) {
				win.H5P.externalDispatcher.on('xAPI', onXapi);
				hooked.push(win);
			}
		} catch (e) { /* autre origine */ }
	}
	// H5P peut être dans la page ou dans un cadre (iframe) : on écoute les deux.
	var tries = 0;
	var timer = setInterval(function () {
		hook(window);
		document.querySelectorAll('iframe.h5p-iframe').forEach(function (f) { hook(f.contentWindow); });
		if (++tries > 40) { clearInterval(timer); }
	}, 500);
})();
