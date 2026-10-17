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
		showTitle: { type: 'boolean', default: true }
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

	/** En-tête commun : titre modifiable directement + réglage « Afficher le titre ». */
	function header(props, icon, placeholder) {
		var a = props.attributes;
		return el(Fragment, null,
			el(be.InspectorControls, null,
				el(c.PanelBody, { title: 'Élément suivi', initialOpen: true },
					el(c.TextControl, { label: 'Titre (aussi utilisé dans le suivi)', value: a.title, onChange: function (v) { props.setAttributes({ title: v }); }, __nextHasNoMarginBottom: true }),
					el(c.ToggleControl, { label: 'Afficher le titre dans la page', checked: a.showTitle, onChange: function (v) { props.setAttributes({ showTitle: v }); }, __nextHasNoMarginBottom: true })
				)
			),
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
		description: 'Un vrai bloc Vidéo ou YouTube/Vimeo (tous ses réglages) + le bouton « J\'ai terminé » pour l\'avancement.',
		category: 'catalogue-formations',
		icon: 'video-alt3',
		keywords: ['vidéo', 'video', 'youtube', 'vimeo', 'formation'],
		supports: SUPPORTS,
		attributes: attrs({ url: { type: 'string', default: '' } }),
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
			return el('div', blockProps,
				header(props, '🎬', 'Titre de la vidéo'),
				choose ? el(c.Placeholder, { icon: 'video-alt3', label: 'Vidéo suivie', instructions: 'D\'où vient la vidéo ?' },
					el(c.Button, { variant: 'primary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/video')]); } }, 'Fichier vidéo (téléverser / médiathèque)'),
					el(c.Button, { variant: 'secondary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/embed', { providerNameSlug: 'youtube', type: 'video', responsive: true })]); } }, 'YouTube'),
					el(c.Button, { variant: 'secondary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/embed', { providerNameSlug: 'vimeo', type: 'video', responsive: true })]); } }, 'Vimeo'),
					el(c.Button, { variant: 'secondary', onClick: function () { replaceInner(props.clientId, [wp.blocks.createBlock('core/embed')]); } }, 'Autre lien')
				) : el(InnerBlocks, { allowedBlocks: ['core/video', 'core/embed', 'core/paragraph', 'core/heading', 'core/list', 'core/image'], templateLock: false }),
				footer('✔ Bouton « J\'ai terminé » ajouté automatiquement pour l\'apprenant')
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
		attributes: attrs({ instructions: { type: 'string', default: '' } }),
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
			return el('div', blockProps,
				header(props, '✏️', 'Titre de l\'exercice'),
				el(InnerBlocks, {
					template: [['core/paragraph', { placeholder: 'Écrivez la consigne… (vous pouvez ajouter images, listes, tableaux, vidéos)' }]],
					templateLock: false
				}),
				footer('✍️ Zone de réponse de l\'apprenant (texte + fichier joint) ajoutée automatiquement')
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

	/* ---------- Encadré « Ajouter un contenu suivi » (colonne de droite) ---------- */
	var Panel = (wp.editor && wp.editor.PluginDocumentSettingPanel) || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
	if (wp.plugins && Panel) {
		var TYPES = [
			{ name: 'catalogue-formations/video', icon: '🎬', label: 'Vidéo' },
			{ name: 'catalogue-formations/document', icon: '📄', label: 'Document' },
			{ name: 'catalogue-formations/exercice', icon: '✏️', label: 'Exercice' },
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
				var res = { 'catalogue-formations/video': 0, 'catalogue-formations/document': 0, 'catalogue-formations/exercice': 0, 'catalogue-formations/h5p': 0 };
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
			var total = counts['catalogue-formations/video'] + counts['catalogue-formations/document'] + counts['catalogue-formations/exercice'] + counts['catalogue-formations/h5p'];
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
