/**
 * Éditeur Elementor : la liste « Contenu » des conditions d'accès suit la page en direct
 * (les widgets ajoutés ou renommés apparaissent sans enregistrer ni recharger).
 */
(function () {
	var TYPES = { cf_video: 'Vidéo', cf_document: 'Document', cf_exercice: 'Exercice', cf_quiz: 'Quiz', cf_h5p: 'H5P' };

	function collect() {
		var out = [];
		var root = window.elementor && elementor.getPreviewContainer && elementor.getPreviewContainer();
		(function walk(c) {
			(c && c.children ? c.children : []).forEach(function (ch) {
				var type = ch.model && ch.model.get('widgetType');
				if (type && TYPES[type]) {
					var title = ch.settings && ch.settings.get('title');
					out.push(['el_' + ch.id, title ? title : TYPES[type] + ' (sans titre)']);
				}
				walk(ch);
			});
		})(root);
		return out;
	}

	function refresh() {
		try {
			var items = collect();
			Object.keys(TYPES).forEach(function (w) {
				var wc = elementor.widgetsCache && elementor.widgetsCache[w];
				var f = wc && wc.controls && wc.controls.conds && wc.controls.conds.fields && wc.controls.conds.fields.item;
				if (!f) {
					return;
				}
				var o = { '': (f.options && f.options['']) || '—' };
				items.forEach(function (i) {
					o[i[0]] = i[1];
				});
				f.options = o;
			});
		} catch (e) {
			// Sans gravité : la liste enregistrée reste utilisée.
		}
	}

	window.addEventListener('elementor/init', function () {
		Object.keys(TYPES).forEach(function (w) {
			elementor.hooks.addAction('panel/open_editor/widget/' + w, refresh);
		});
	});
	// Avant l'ouverture d'un panneau (les lignes de conditions lisent les options à ce moment-là).
	document.addEventListener('mousedown', refresh, true);
})();
