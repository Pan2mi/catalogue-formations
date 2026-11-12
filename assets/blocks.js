/**
 * Blocs « Vidéo suivie », « Document suivi », « Exercice ».
 * - Bloc sélectionné : champs à remplir.
 * - Bloc non sélectionné : aperçu réel, tel que l'apprenant le verra.
 * Pas de compilation nécessaire (JavaScript simple).
 */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useSelect = wp.data.useSelect;
	var be = wp.blockEditor;
	var c = wp.components;
	var SSR = wp.serverSideRender;

	function newKey(prefix) {
		return prefix + '_' + Math.random().toString(36).slice(2, 10);
	}

	/** Donne une clé unique au bloc (y compris après un copier-coller). */
	function useUniqueKey(props, prefix) {
		var key = props.attributes.key;
		var clientId = props.clientId;
		var isDuplicate = useSelect(function (select) {
			if (!key) {
				return false;
			}
			var store = select('core/block-editor');
			var ids = store.getClientIdsWithDescendants();
			for (var i = 0; i < ids.length; i++) {
				if (ids[i] === clientId) {
					return false;
				}
				var b = store.getBlock(ids[i]);
				if (b && b.name.indexOf('catalogue-formations/') === 0 && b.attributes.key === key) {
					return true;
				}
			}
			return false;
		}, [key, clientId]);
		useEffect(function () {
			if (!key || isDuplicate) {
				props.setAttributes({ key: newKey(prefix) });
			}
		}, [key, isDuplicate]);
	}

	function usePostId() {
		return useSelect(function (select) {
			var ed = select('core/editor');
			return ed ? ed.getCurrentPostId() : 0;
		}, []);
	}

	var InnerBlocks = be.InnerBlocks;

	var SUPPORTS = {
		html: false,
		anchor: true,
		align: ['left', 'center', 'right', 'wide', 'full'],
		spacing: { margin: true, padding: true },
		color: { background: true, text: true, gradients: true, link: true },
		typography: { fontSize: true, lineHeight: true },
		__experimentalBorder: { color: true, radius: true, style: true, width: true },
		shadow: true
	};
	var BASE_ATTRS = {
		key: { type: 'string', default: '' },
		title: { type: 'string', default: '' },
		showTitle: { type: 'boolean', default: true },
		free: { type: 'boolean', default: false },
		hidden: { type: 'boolean', default: false },
		rules: { type: 'object', default: {} }
	};
	function attrs(extra) {
		var o = {};
		Object.keys(BASE_ATTRS).forEach(function (k) { o[k] = BASE_ATTRS[k]; });
		Object.keys(extra).forEach(function (k) { o[k] = extra[k]; });
		return o;
	}

	function useInner(clientId) {
		return useSelect(function (select) { return select('core/block-editor').getBlocks(clientId); }, [clientId]);
	}
	function replaceInner(clientId, blocks) {
		wp.data.dispatch('core/block-editor').replaceInnerBlocks(clientId, blocks, false);
	}

	/* ---------- Restrictions d'accès (comme Moodle) ---------- */
	var ICONS = { 'catalogue-formations/video': '🎬', 'catalogue-formations/document': '📄', 'catalogue-formations/exercice': '✏️', 'catalogue-formations/h5p': '🧩', 'catalogue-formations/quiz': '❓' };

	/** Autres contenus suivis de la page (pour « tel contenu terminé » / « score minimum »). */
	function useOtherItems(clientId) {
		return useSelect(function (select) {
			var store = select('core/block-editor');
			var out = [];
			store.getClientIdsWithDescendants().forEach(function (id) {
				var b = store.getBlock(id);
				if (id !== clientId && b && ICONS[b.name] && b.attributes.key) {
					out.push({ key: b.attributes.key, label: ICONS[b.name] + ' ' + (b.attributes.title || 'Sans titre'), scored: b.name === 'catalogue-formations/h5p' || b.name === 'catalogue-formations/quiz' || (b.name === 'catalogue-formations/exercice' && b.attributes.gradeMax > 0) });
				}
			});
			return out;
		}, [clientId]);
	}

	function condSummary(r) {
		var n = r && r.conds ? r.conds.length : 0;
		if (!n) { return ''; }
		return '🔒 ' + n + ' condition' + (n > 1 ? 's' : '') + ' d\'accès' + (r.display === 'hidden' ? ' (caché sinon)' : '');
	}

	function RulesPanel(p) {
		var props = p.props;
		var a = props.attributes;
		var r = a.rules && a.rules.conds ? a.rules : { match: 'all', display: 'locked', conds: [] };
		var others = useOtherItems(props.clientId);
		function set(patch) {
			var n = { match: r.match || 'all', display: r.display || 'locked', conds: r.conds.slice() };
			Object.keys(patch).forEach(function (k) { n[k] = patch[k]; });
			props.setAttributes({ rules: n });
		}
		function setCond(i, patch) {
			var conds = r.conds.map(function (c, j) { if (j !== i) { return c; } var x = {}; Object.keys(c).forEach(function (k) { x[k] = c[k]; }); Object.keys(patch).forEach(function (k) { x[k] = patch[k]; }); return x; });
			set({ conds: conds });
		}
		function add(c) { set({ conds: r.conds.concat([c]) }); }
		function remove(i) { set({ conds: r.conds.filter(function (c, j) { return j !== i; }) }); }
		var itemOpts = function (scoredOnly) {
			var list = others.filter(function (o) { return !scoredOnly || o.scored; });
			return [{ value: '', label: '— Choisir un contenu —' }].concat(list.map(function (o) { return { value: o.key, label: o.label }; }));
		};
		var rows = r.conds.map(function (c, i) {
			var fields;
			if (c.type === 'done') {
				fields = el(wp.components.SelectControl, { label: 'Contenu à terminer', value: c.key || '', options: itemOpts(false), onChange: function (v) { setCond(i, { key: v }); }, __nextHasNoMarginBottom: true });
			} else if (c.type === 'score') {
				fields = el(Fragment, null,
					el(wp.components.SelectControl, { label: 'Contenu noté', help: itemOpts(true).length < 2 ? 'Ajoutez d\'abord un quiz, une activité H5P ou un exercice noté.' : '', value: c.key || '', options: itemOpts(true), onChange: function (v) { setCond(i, { key: v }); }, __nextHasNoMarginBottom: true }),
					el(wp.components.RangeControl, { label: 'Score minimum (%)', min: 0, max: 100, step: 5, value: c.min === undefined ? 50 : c.min, onChange: function (v) { setCond(i, { min: v || 0 }); } })
				);
			} else if (c.type === 'date') {
				fields = el(Fragment, null,
					el(wp.components.TextControl, { type: 'date', label: 'À partir du (facultatif)', value: c.from || '', onChange: function (v) { setCond(i, { from: v }); }, __nextHasNoMarginBottom: true }),
					el(wp.components.TextControl, { type: 'date', label: 'Jusqu\'au (facultatif)', value: c.until || '', onChange: function (v) { setCond(i, { until: v }); }, __nextHasNoMarginBottom: true })
				);
			} else {
				fields = el(wp.components.TextControl, { type: 'number', min: 0, label: 'Jours après le début de l\'accès de l\'apprenant', value: c.days === undefined ? 7 : c.days, onChange: function (v) { setCond(i, { days: parseInt(v, 10) || 0 }); }, __nextHasNoMarginBottom: true });
			}
			var names = { done: '✔ Contenu terminé', score: '🎯 Score minimum', date: '📅 Date', delay: '⏳ Délai' };
			return el('div', { key: i, className: 'cf-cond' },
				el('div', { className: 'cf-cond__head' }, el('strong', null, names[c.type]), el(wp.components.Button, { isDestructive: true, variant: 'link', onClick: function () { remove(i); } }, 'Retirer')),
				fields
			);
		});
		return el(c.PanelBody, { title: 'Restrictions d\'accès', initialOpen: !!(a.hidden || r.conds.length) },
			el(c.ToggleControl, { label: 'Masqué aux apprenants', help: 'Pour préparer ce contenu tranquillement : invisible pour les apprenants et hors avancement.', checked: !!a.hidden, onChange: function (v) { props.setAttributes({ hidden: v }); }, __nextHasNoMarginBottom: true }),
			el('hr'),
			el('p', { className: 'cf-help' }, 'Conditions pour accéder à ce contenu :'),
			rows,
			r.conds.length > 1 ? el(c.SelectControl, { label: 'Il faut', value: r.match || 'all', options: [{ value: 'all', label: 'toutes les conditions' }, { value: 'any', label: 'au moins une condition' }], onChange: function (v) { set({ match: v }); }, __nextHasNoMarginBottom: true }) : null,
			r.conds.length ? el(c.SelectControl, { label: 'Tant que ce n\'est pas rempli', value: r.display || 'locked', options: [{ value: 'locked', label: 'Afficher avec un cadenas et ce qu\'il faut faire' }, { value: 'hidden', label: 'Cacher complètement' }], onChange: function (v) { set({ display: v }); }, __nextHasNoMarginBottom: true }) : null,
			el('div', { className: 'cf-cond-add' },
				el(c.Button, { variant: 'secondary', size: 'small', onClick: function () { add({ type: 'done', key: '' }); } }, '+ Contenu terminé'),
				el(c.Button, { variant: 'secondary', size: 'small', onClick: function () { add({ type: 'score', key: '', min: 50 }); } }, '+ Score minimum'),
				el(c.Button, { variant: 'secondary', size: 'small', onClick: function () { add({ type: 'date', from: '', until: '' }); } }, '+ Date'),
				el(c.Button, { variant: 'secondary', size: 'small', onClick: function () { add({ type: 'delay', days: 7 }); } }, '+ Délai')
			)
		);
	}

	/** En-tête commun : titre modifiable directement + réglage « Afficher le titre ». */
	function header(props, icon, placeholder) {
		var a = props.attributes;
		return el(Fragment, null,
			el(be.InspectorControls, null,
				el(c.PanelBody, { title: 'Élément suivi', initialOpen: true },
					el(c.TextControl, { label: 'Titre (aussi utilisé dans le suivi)', value: a.title, onChange: function (v) { props.setAttributes({ title: v }); }, __nextHasNoMarginBottom: true }),
					el(c.ToggleControl, { label: 'Afficher le titre dans la page', checked: a.showTitle, onChange: function (v) { props.setAttributes({ showTitle: v }); }, __nextHasNoMarginBottom: true }),
					el(c.ToggleControl, { label: 'Toujours accessible', help: 'Même si la notion est en « Parcours dans l\'ordre » (réglage « Parcours » de la notion). Ce contenu ne bloque pas non plus les suivants.', checked: !!a.free, onChange: function (v) { props.setAttributes({ free: v }); }, __nextHasNoMarginBottom: true })
				),
				el(RulesPanel, { props: props })
			),
			(a.hidden || condSummary(a.rules)) ? el('p', { className: 'cf-badges' },
				a.hidden ? el('span', { className: 'cf-badge cf-badge--hidden' }, '👁 Masqué aux apprenants') : null,
				condSummary(a.rules) ? el('span', { className: 'cf-badge' }, condSummary(a.rules)) : null
			) : null,
			a.showTitle ? el(be.RichText, {
				tagName: 'h3', className: 'if-item__title', value: a.title, allowedFormats: [],
				placeholder: icon + ' ' + placeholder,
				onChange: function (v) { props.setAttributes({ title: v.replace(/<[^>]+>/g, '') }); }
			}) : el('p', { className: 'cf-hidden-title' }, icon + ' ' + (a.title || placeholder) + ' (titre masqué)')
		);
	}

	function footer(text) {
		return el('div', { className: 'cf-footer', contentEditable: false }, text);
	}

	/* ---------- Vidéo suivie ---------- */
	wp.blocks.registerBlockType('catalogue-formations/video', {
		apiVersion: 3,
		title: 'Vidéo suivie',
		description: 'Un vrai bloc Vidéo ou YouTube/Vimeo (tous ses réglages), validé automatiquement quand il est vu et/ou avec le bouton « J\'ai terminé ».',
		category: 'catalogue-formations',
		icon: 'video-alt3',
		keywords: ['vidéo', 'video', 'youtube', 'vimeo', 'formation'],
		supports: SUPPORTS,
		attributes: attrs({ url: { type: 'string', default: '' }, completion: { type: 'string', default: 'both' }, threshold: { type: 'number', default: 90 }, noSkip: { type: 'boolean', default: false } }),
		edit: function (props) {
			useUniqueKey(props, 'v');
			var a = props.attributes;
			var inner = useInner(props.clientId);
			// Ancien bloc (lien seul) → vrai bloc Vidéo / Intégration.
			useEffect(function () {
				if (a.url && inner.length === 0) {
					var isFile = /\.(mp4|m4v|webm|ogv|mov)(\?.*)?$/i.test(a.url);
					replaceInner(props.clientId, [isFile ? wp.blocks.createBlock('core/video', { src: a.url }) : wp.blocks.createBlock('core/embed', { url: a.url })]);
					props.setAttributes({ url: '' });
				}
			}, []);
			var blockProps = be.useBlockProps({ className: 'if-item if-item--video cf-tracked' });
			var choose = inner.length === 0 && !a.url;
			var auto = a.completion !== 'button';
			var footText = a.completion === 'auto' ? '✔ Validée automatiquement quand la vidéo est vue à ' + a.threshold + ' %' + (a.noSkip ? ' · avance rapide désactivée' : '')
				: a.completion === 'button' ? '✔ Bouton « J\'ai terminé » ajouté pour l\'apprenant'
				: '✔ Validée automatiquement à ' + a.threshold + ' % + bouton « J\'ai terminé »' + (a.noSkip ? ' · avance rapide désactivée' : '');
			return el('div', blockProps,
				el(be.InspectorControls, null,
					el(c.PanelBody, { title: 'Validation de la vidéo', initialOpen: true },
						el(c.SelectControl, {
							label: 'Comment la vidéo est validée', value: a.completion, __nextHasNoMarginBottom: true,
							options: [
								{ value: 'auto', label: 'Automatiquement, quand elle est vue' },
								{ value: 'both', label: 'Automatiquement + bouton « J\'ai terminé »' },
								{ value: 'button', label: 'Seulement avec le bouton' }
							],
							onChange: function (v) { props.setAttributes({ completion: v }); }
						}),
						auto ? el(c.RangeControl, { label: 'Vue à partir de (%)', help: 'Part de la vidéo réellement regardée.', min: 10, max: 100, step: 5, value: a.threshold, onChange: function (v) { props.setAttributes({ threshold: v || 90 }); } }) : null,
						auto ? el(c.ToggleControl, { label: 'Empêcher d\'avancer dans la vidéo', help: 'L\'apprenant ne peut pas aller plus loin que ce qu\'il a déjà regardé.', checked: a.noSkip, onChange: function (v) { props.setAttributes({ noSkip: v }); }, __nextHasNoMarginBottom: true }) : null,
						el('p', { className: 'cf-help' }, 'Fonctionne avec un fichier vidéo, YouTube et Vimeo.')
					)
				),
				header(props, '🎬', 'Titre de la vidéo'),
				choose ? el(c.Placeholder, { icon: 'video-alt3', label: 'Vidéo suivie', instructions: 'D\'où vient la vidéo ?' },
					el(c.Button, { variant: 'primary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/video')]); } }, 'Fichier vidéo (téléverser / médiathèque)'),
					el(c.Button, { variant: 'secondary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/embed', { providerNameSlug: 'youtube', type: 'video', responsive: true })]); } }, 'YouTube'),
					el(c.Button, { variant: 'secondary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/embed', { providerNameSlug: 'vimeo', type: 'video', responsive: true })]); } }, 'Vimeo'),
					el(c.Button, { variant: 'secondary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/embed')]); } }, 'Autre lien')
				) : el(InnerBlocks, { allowedBlocks: ['core/video', 'core/embed', 'core/paragraph', 'core/heading', 'core/list', 'core/image'], templateLock: false }),
				footer(footText)
			);
		},
		save: function () { return el(InnerBlocks.Content); }
	});

	/* ---------- Document suivi ---------- */
	wp.blocks.registerBlockType('catalogue-formations/document', {
		apiVersion: 3,
		title: 'Document suivi',
		description: 'Un vrai bloc Fichier (aperçu du PDF, bouton de téléchargement…), protégé : visible seulement avec l\'accès.',
		category: 'catalogue-formations',
		icon: 'media-document',
		keywords: ['pdf', 'document', 'fiche', 'fichier', 'formation'],
		supports: SUPPORTS,
		attributes: attrs({ attachmentId: { type: 'number', default: 0 }, fileName: { type: 'string', default: '' } }),
		edit: function (props) {
			useUniqueKey(props, 'p');
			var a = props.attributes;
			var inner = useInner(props.clientId);
			useEffect(function () {
				if (a.attachmentId && inner.length === 0 && wp.apiFetch) {
					wp.apiFetch({ path: '/wp/v2/media/' + a.attachmentId }).then(function (m) {
						replaceInner(props.clientId, [wp.blocks.createBlock('core/file', { id: m.id, href: m.source_url, fileName: a.fileName || (m.title && m.title.rendered) || 'Document', textLinkHref: m.source_url, showDownloadButton: true, displayPreview: true, previewHeight: 600 })]);
					}).catch(function () {});
				}
			}, []);
			var blockProps = be.useBlockProps({ className: 'if-item if-item--pdf cf-tracked' });
			return el('div', blockProps,
				header(props, '📄', 'Titre du document'),
				el(InnerBlocks, {
					allowedBlocks: ['core/file', 'core/paragraph', 'core/heading', 'core/list', 'core/image'],
					template: [['core/file', { displayPreview: true, showDownloadButton: true }]],
					templateLock: false
				}),
				footer('🔒 Lien protégé + bouton « J\'ai terminé » ajoutés automatiquement pour l\'apprenant')
			);
		},
		save: function () { return el(InnerBlocks.Content); }
	});

	/* ---------- Exercice ---------- */
	wp.blocks.registerBlockType('catalogue-formations/exercice', {
		apiVersion: 3,
		title: 'Exercice',
		description: 'Une consigne construite avec n\'importe quels blocs (texte, images, listes…) + la zone de réponse de l\'apprenant.',
		category: 'catalogue-formations',
		icon: 'edit-page',
		keywords: ['exercice', 'devoir', 'question', 'formation'],
		supports: SUPPORTS,
		attributes: attrs({ instructions: { type: 'string', default: '' }, validation: { type: 'string', default: 'teacher' }, gradeMax: { type: 'number', default: 0 } }),
		edit: function (props) {
			useUniqueKey(props, 'e');
			var a = props.attributes;
			var inner = useInner(props.clientId);
			useEffect(function () {
				if (a.instructions && inner.length === 0) {
					replaceInner(props.clientId, wp.blocks.rawHandler({ HTML: a.instructions }));
					props.setAttributes({ instructions: '' });
				}
			}, []);
			var blockProps = be.useBlockProps({ className: 'if-item if-item--exercise cf-tracked' });
			var foot = '✍️ Zone de réponse (texte + fichier) · ' + (a.validation === 'submit' ? 'terminé dès l\'envoi' : 'terminé quand vous le validez') + (a.gradeMax ? ' · noté sur ' + a.gradeMax : '');
			return el('div', blockProps,
				el(be.InspectorControls, null,
					el(c.PanelBody, { title: 'Validation de l\'exercice', initialOpen: true },
						el(c.SelectControl, { label: 'L\'exercice compte comme terminé', value: a.validation, options: [{ value: 'teacher', label: 'Quand vous le validez (correction)' }, { value: 'submit', label: 'Dès que la réponse est envoyée' }], onChange: function (v) { props.setAttributes({ validation: v }); }, __nextHasNoMarginBottom: true }),
						el(c.TextControl, { type: 'number', min: 0, step: 0.5, label: 'Noté sur (0 = pas de note)', help: 'Ex. 20. La note est facultative lors de la correction.', value: a.gradeMax, onChange: function (v) { props.setAttributes({ gradeMax: parseFloat(v) || 0 }); }, __nextHasNoMarginBottom: true }),
						el('p', { className: 'cf-help' }, 'À la correction : « Validé » ou « À refaire » (l\'apprenant peut renvoyer une réponse).')
					)
				),
				header(props, '✏️', 'Titre de l\'exercice'),
				el(InnerBlocks, {
					template: [['core/paragraph', { placeholder: 'Écrivez la consigne… (vous pouvez ajouter images, listes, tableaux, vidéos)' }]],
					templateLock: false
				}),
				footer(foot)
			);
		},
		save: function () { return el(InnerBlocks.Content); }
	});


	/* ---------- Activité H5P suivie ---------- */
	wp.blocks.registerBlockType('catalogue-formations/h5p', {
		apiVersion: 3,
		title: 'Activité H5P suivie',
		description: 'Une activité interactive H5P (quiz, vidéo interactive, glisser-déposer…). Elle compte dans l\'avancement quand l\'apprenant la réussit.',
		category: 'catalogue-formations',
		icon: 'games',
		keywords: ['h5p', 'quiz', 'interactif', 'activité', 'qcm', 'formation'],
		supports: SUPPORTS,
		attributes: attrs({ contentId: { type: 'number', default: 0 }, passScore: { type: 'number', default: 0 } }),
		edit: function (props) {
			useUniqueKey(props, 'h');
			var a = props.attributes;
			var st = wp.element.useState(null); var data = st[0], setData = st[1];
			function load() {
				wp.apiFetch({ path: '/catalogue-formations/v1/h5p-contents' }).then(setData).catch(function () { setData({ active: false, contents: [] }); });
			}
			useEffect(load, []);
			var blockProps = be.useBlockProps({ className: 'if-item if-item--h5p cf-tracked' });
			var chosen = data && data.contents.filter(function (x) { return x.id === a.contentId; })[0];
			var options = [{ value: 0, label: '— Choisir une activité —' }].concat((data ? data.contents : []).map(function (x) { return { value: x.id, label: x.title + (x.type ? ' (' + x.type + ')' : '') }; }));
			var passField = el(c.RangeControl, { label: 'Score minimum pour valider (%)', help: '0 = il suffit de terminer l\'activité.', min: 0, max: 100, step: 5, value: a.passScore, onChange: function (v) { props.setAttributes({ passScore: v || 0 }); } });
			var body;
			if (!data) {
				body = el(c.Spinner);
			} else if (!data.active) {
				body = el(c.Notice, { status: 'warning', isDismissible: false }, 'L\'extension H5P n\'est pas active. Installez-la (Extensions → Ajouter → « H5P ») pour utiliser ce bloc.');
			} else {
				body = el('div', { className: 'cf-h5p-edit' },
					el(c.SelectControl, { label: 'Activité H5P', value: a.contentId, options: options, onChange: function (v) { var id = parseInt(v, 10) || 0; var x = data.contents.filter(function (y) { return y.id === id; })[0]; props.setAttributes({ contentId: id, title: a.title || (x ? x.title : '') }); }, __nextHasNoMarginBottom: true }),
					el('div', { className: 'cf-h5p-actions' },
						el(c.Button, { variant: 'secondary', href: data.newUrl, target: '_blank' }, '+ Créer une activité H5P'),
						el(c.Button, { variant: 'tertiary', onClick: load }, 'Actualiser la liste')
					),
					chosen ? el('div', { className: 'cf-h5p-card' }, el('strong', null, '🧩 ' + chosen.title), el('span', null, chosen.type), el('em', null, a.passScore ? ('Validée à partir de ' + a.passScore + ' %') : 'Validée quand l\'apprenant la termine')) : null,
					!data.contents.length ? el('p', { className: 'cf-help' }, 'Aucune activité H5P pour le moment : créez-en une, puis cliquez sur « Actualiser la liste ».') : null
				);
			}
			return el('div', blockProps,
				el(be.InspectorControls, null, el(c.PanelBody, { title: 'Validation', initialOpen: true }, passField)),
				header(props, '🧩', 'Titre de l\'activité'),
				body,
				footer('🧩 L\'activité s\'affiche pour l\'apprenant ; son score compte dans l\'avancement')
			);
		},
		save: function () { return null; }
	});

	/* ---------- Quiz ---------- */
	var QTYPES = [
		{ value: 'single', label: 'QCM — une bonne réponse' },
		{ value: 'multiple', label: 'QCM — plusieurs bonnes réponses' },
		{ value: 'truefalse', label: 'Vrai / faux' },
		{ value: 'short', label: 'Réponse courte' }
	];
	function newQuestion(type) {
		var q = { type: type || 'single', text: '', explanation: '' };
		if (q.type === 'truefalse') { q.answerTrue = true; }
		else if (q.type === 'short') { q.answers = ''; }
		else { q.choices = [{ text: '', correct: true }, { text: '', correct: false }]; }
		return q;
	}
	function QuestionEditor(p) {
		var q = p.q, i = p.i, n = p.n;
		function upd(patch) { var x = {}; Object.keys(q).forEach(function (k) { x[k] = q[k]; }); Object.keys(patch).forEach(function (k) { x[k] = patch[k]; }); p.onChange(x); }
		function setChoice(j, patch) {
			var list = q.choices.map(function (ch, k) {
				var x = { text: ch.text, correct: ch.correct };
				if (k === j) { Object.keys(patch).forEach(function (kk) { x[kk] = patch[kk]; }); }
				else if (patch.correct && q.type === 'single') { x.correct = false; }
				return x;
			});
			upd({ choices: list });
		}
		var body;
		if (q.type === 'truefalse') {
			body = el(c.RadioControl, { label: 'Bonne réponse', selected: q.answerTrue ? 'true' : 'false', options: [{ value: 'true', label: 'Vrai' }, { value: 'false', label: 'Faux' }], onChange: function (v) { upd({ answerTrue: v === 'true' }); } });
		} else if (q.type === 'short') {
			body = el(c.TextareaControl, { label: 'Réponses acceptées (une par ligne)', help: 'Majuscules, accents et point final ne comptent pas.', rows: 3, value: Array.isArray(q.answers) ? q.answers.join('\n') : (q.answers || ''), onChange: function (v) { upd({ answers: v }); }, __nextHasNoMarginBottom: true });
		} else {
			body = el('div', { className: 'cf-q-choices' },
				el('p', { className: 'cf-help' }, q.type === 'single' ? 'Cochez LA bonne réponse :' : 'Cochez TOUTES les bonnes réponses :'),
				(q.choices || []).map(function (ch, j) {
					return el('div', { key: j, className: 'cf-q-choice' + (ch.correct ? ' is-correct' : '') },
						el('input', { type: q.type === 'single' ? 'radio' : 'checkbox', checked: !!ch.correct, 'aria-label': 'Bonne réponse', onChange: function (e) { setChoice(j, { correct: q.type === 'single' ? true : e.target.checked }); } }),
						el('input', { type: 'text', className: 'components-text-control__input', value: ch.text, placeholder: 'Réponse ' + (j + 1), onChange: function (e) { setChoice(j, { text: e.target.value }); } }),
						q.choices.length > 2 ? el(c.Button, { icon: 'no-alt', label: 'Retirer cette réponse', size: 'small', onClick: function () { upd({ choices: q.choices.filter(function (x, k) { return k !== j; }) }); } }) : null
					);
				}),
				el(c.Button, { variant: 'link', onClick: function () { upd({ choices: (q.choices || []).concat([{ text: '', correct: false }]) }); } }, '+ Ajouter une réponse')
			);
		}
		return el('div', { className: 'cf-q' },
			el('div', { className: 'cf-q__head' },
				el('strong', null, 'Question ' + (i + 1)),
				el(c.SelectControl, { value: q.type, options: QTYPES, onChange: function (v) { var nq = newQuestion(v); nq.text = q.text; nq.explanation = q.explanation; p.onChange(nq); }, __nextHasNoMarginBottom: true }),
				el(c.Button, { icon: 'arrow-up-alt2', label: 'Monter', size: 'small', disabled: i === 0, onClick: function () { p.move(-1); } }),
				el(c.Button, { icon: 'arrow-down-alt2', label: 'Descendre', size: 'small', disabled: i === n - 1, onClick: function () { p.move(1); } }),
				el(c.Button, { icon: 'trash', label: 'Supprimer la question', isDestructive: true, size: 'small', onClick: p.remove })
			),
			el(c.TextareaControl, { label: 'Question', rows: 2, value: q.text, onChange: function (v) { upd({ text: v }); }, __nextHasNoMarginBottom: true }),
			body,
			el(c.TextareaControl, { label: 'Explication (facultatif, montrée après la correction)', rows: 2, value: q.explanation || '', onChange: function (v) { upd({ explanation: v }); }, __nextHasNoMarginBottom: true })
		);
	}

	wp.blocks.registerBlockType('catalogue-formations/quiz', {
		apiVersion: 3,
		title: 'Quiz',
		description: 'QCM, vrai/faux, réponse courte : corrigé automatiquement, score gardé pour l\'avancement et les conditions.',
		category: 'catalogue-formations',
		icon: 'yes-alt',
		keywords: ['quiz', 'qcm', 'vrai', 'faux', 'question', 'évaluation', 'formation'],
		supports: SUPPORTS,
		attributes: attrs({ questions: { type: 'array', default: [] }, passScore: { type: 'number', default: 50 }, attempts: { type: 'number', default: 0 }, shuffle: { type: 'boolean', default: false }, showCorrection: { type: 'string', default: 'always' } }),
		edit: function (props) {
			useUniqueKey(props, 'q');
			var a = props.attributes;
			var qs = a.questions || [];
			useEffect(function () { if (!qs.length) { props.setAttributes({ questions: [newQuestion('single')] }); } }, []);
			function setQ(list) { props.setAttributes({ questions: list }); }
			var blockProps = be.useBlockProps({ className: 'if-item if-item--quiz cf-tracked' });
			var summary = qs.length + ' question' + (qs.length > 1 ? 's' : '') + ' · validé à partir de ' + a.passScore + ' %' + (a.attempts ? ' · ' + a.attempts + ' essai' + (a.attempts > 1 ? 's' : '') : ' · essais illimités');
			return el('div', blockProps,
				el(be.InspectorControls, null,
					el(c.PanelBody, { title: 'Réglages du quiz', initialOpen: true },
						el(c.RangeControl, { label: 'Score minimum pour valider (%)', min: 0, max: 100, step: 5, value: a.passScore, onChange: function (v) { props.setAttributes({ passScore: v || 0 }); } }),
						el(c.TextControl, { type: 'number', min: 0, label: 'Nombre d\'essais (0 = illimité)', value: a.attempts, onChange: function (v) { props.setAttributes({ attempts: parseInt(v, 10) || 0 }); }, __nextHasNoMarginBottom: true }),
						el(c.ToggleControl, { label: 'Mélanger questions et réponses', checked: a.shuffle, onChange: function (v) { props.setAttributes({ shuffle: v }); }, __nextHasNoMarginBottom: true }),
						el(c.SelectControl, { label: 'Montrer la correction', value: a.showCorrection, options: [{ value: 'always', label: 'Après chaque essai' }, { value: 'passed', label: 'Seulement une fois le quiz réussi' }, { value: 'never', label: 'Jamais (score seulement)' }], onChange: function (v) { props.setAttributes({ showCorrection: v }); }, __nextHasNoMarginBottom: true })
					)
				),
				header(props, '❓', 'Titre du quiz'),
				props.isSelected ? el('div', { className: 'cf-quiz-edit' },
					qs.map(function (q, i) {
						return el(QuestionEditor, {
							key: i, q: q, i: i, n: qs.length,
							onChange: function (nq) { setQ(qs.map(function (x, k) { return k === i ? nq : x; })); },
							remove: function () { setQ(qs.filter(function (x, k) { return k !== i; })); },
							move: function (d) { var l = qs.slice(); var t = l[i]; l[i] = l[i + d]; l[i + d] = t; setQ(l); }
						});
					}),
					el('div', { className: 'cf-q-add' }, QTYPES.map(function (t) {
						return el(c.Button, { key: t.value, variant: 'secondary', size: 'small', onClick: function () { setQ(qs.concat([newQuestion(t.value)])); } }, '+ ' + t.label);
					}))
				) : el('ol', { className: 'cf-quiz-preview' }, qs.map(function (q, i) { return el('li', { key: i }, q.text || el('em', null, 'Question sans texte')); })),
				footer('❓ ' + summary + (props.isSelected ? '' : ' — cliquez pour modifier les questions'))
			);
		},
		save: function () { return null; }
	});

	/* ---------- Encadré « Ajouter un contenu suivi » (colonne de droite) ---------- */
	var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
	if (wp.plugins && Panel) {
		var TYPES = [
			{ name: 'catalogue-formations/video', icon: '🎬', label: 'Vidéo' },
			{ name: 'catalogue-formations/document', icon: '📄', label: 'Document' },
			{ name: 'catalogue-formations/exercice', icon: '✏️', label: 'Exercice' },
			{ name: 'catalogue-formations/quiz', icon: '❓', label: 'Quiz' },
			{ name: 'catalogue-formations/h5p', icon: '🧩', label: 'Activité H5P' }
		];

		function insertTracked(name) {
			var sel = wp.data.select('core/block-editor');
			var dis = wp.data.dispatch('core/block-editor');
			var block = wp.blocks.createBlock(name);
			var current = sel.getSelectedBlockClientId();
			if (current) {
				var root = sel.getBlockRootClientId(current) || undefined;
				var index = sel.getBlockIndex(current) + 1;
				dis.insertBlocks(block, index, root, true);
			} else {
				dis.insertBlocks(block, undefined, undefined, true);
			}
			dis.selectBlock(block.clientId);
		}

		function useCounts() {
			return useSelect(function (select) {
				var store = select('core/block-editor');
				var res = { 'catalogue-formations/video': 0, 'catalogue-formations/document': 0, 'catalogue-formations/exercice': 0, 'catalogue-formations/h5p': 0, 'catalogue-formations/quiz': 0 };
				store.getClientIdsWithDescendants().forEach(function (id) {
					var b = store.getBlock(id);
					if (b && res.hasOwnProperty(b.name)) { res[b.name]++; }
				});
				return res;
			}, []);
		}
		function usePostType() {
			return useSelect(function (select) { return select('core/editor') ? select('core/editor').getCurrentPostType() : ''; }, []);
		}
		function QuickButtons() {
			var counts = useCounts();
			var total = counts['catalogue-formations/video'] + counts['catalogue-formations/document'] + counts['catalogue-formations/exercice'] + counts['catalogue-formations/h5p'] + counts['catalogue-formations/quiz'];
			return el(Fragment, null,
				el('p', { className: 'cf-quick-help' }, 'Clic = ajouté juste après l\'endroit où vous êtes dans la page.'),
				el('div', { className: 'cf-quick-buttons' }, TYPES.map(function (t) {
					return el(c.Button, { key: t.name, className: 'cf-quick-btn', variant: 'secondary', onClick: function () { insertTracked(t.name); } },
						el('span', { className: 'cf-quick-icon', 'aria-hidden': 'true' }, t.icon),
						el('span', null, t.label),
						counts[t.name] ? el('span', { className: 'cf-quick-count' }, counts[t.name]) : null
					);
				})),
				el('p', { className: 'cf-quick-total' }, total ? (total + (total > 1 ? ' éléments suivis' : ' élément suivi') + ' dans la page (comptent dans l\'avancement).') : 'Aucun élément suivi pour le moment.')
			);
		}

		// Onglet « Notion » de la colonne de droite.
		wp.plugins.registerPlugin('cf-quick-insert', {
			render: function () {
				if (usePostType() !== 'if_notion') {
					return null;
				}
				return el(Panel, { name: 'cf-quick-insert', title: 'Ajouter un contenu suivi', className: 'cf-quick', initialOpen: true }, el(QuickButtons));
			}
		});

		// Onglet « Bloc » : le même encadré, en haut des réglages de chaque bloc.
		if (wp.hooks && wp.compose) {
			wp.hooks.addFilter('editor.BlockEdit', 'cf/quick-insert', wp.compose.createHigherOrderComponent(function (BlockEdit) {
				return function (props) {
					var postType = usePostType();
					if (postType !== 'if_notion' || !props.isSelected) {
						return el(BlockEdit, props);
					}
					return el(Fragment, null,
						el(BlockEdit, props),
						el(be.InspectorControls, { group: 'default' },
							el(c.PanelBody, { title: 'Ajouter un contenu suivi', className: 'cf-quick', initialOpen: true }, el(QuickButtons))
						)
					);
				};
			}, 'cfQuickInsert'));
		}
	}
})(window.wp);
