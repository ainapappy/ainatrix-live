/**
 * ainatrix live — panneau de publication sociale dans l'éditeur Gutenberg.
 *
 * Fill de PluginPostStatusInfo (wp.plugins), écrit en wp.element.createElement
 * pur : aucun build. Le statut de l'article est lu dynamiquement via
 * wp.data.select('core/editor') et le bouton de publication ne s'active que
 * pour un article publié.
 *
 * Données injectées côté PHP (wp_localize_script) : window.aixLiveEditor
 * = { settingsUrl, nonce, networks: [{key, label, color, path, gradient,
 * configured, account}] }.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.data || !wp.components || !wp.i18n) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginPostStatusInfo = wp.editPost.PluginPostStatusInfo;
	var Button = wp.components.Button;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	var settings = window.aixLiveEditor || {};
	var networks = Array.isArray(settings.networks) ? settings.networks : [];
	var GRADIENT_ID = 'aix-live-ig-gradient';
	var GRADIENT_STOPS = ['#FEDA75', '#FA7E1E', '#D62976', '#962FBF', '#4F5BD5'];

	/**
	 * Icône déclencheur « nœuds de partage », style dashicons/share-alt
	 * (SVG inline, stroke currentColor — hérite de l'état enfoncé du bouton).
	 */
	var TRIGGER_ICON = el(
		'svg',
		{
			width: 18,
			height: 18,
			viewBox: '0 0 24 24',
			fill: 'none',
			stroke: 'currentColor',
			strokeWidth: 2,
			strokeLinecap: 'round',
			'aria-hidden': true,
			focusable: 'false'
		},
		el('circle', { cx: 18, cy: 5, r: 3 }),
		el('circle', { cx: 6, cy: 12, r: 3 }),
		el('circle', { cx: 18, cy: 19, r: 3 }),
		el('line', { x1: 8.7, y1: 10.6, x2: 15.3, y2: 6.4 }),
		el('line', { x1: 8.7, y1: 13.4, x2: 15.3, y2: 17.6 })
	);

	/**
	 * Définition du dégradé officiel Instagram, rendue une seule fois et
	 * référencée par toutes les icônes concernées (fill: url(#…)).
	 */
	function renderGradientDefs() {
		return el(
			'svg',
			{ className: 'aix-live-gradient-defs', width: 0, height: 0, 'aria-hidden': true, focusable: 'false' },
			el(
				'defs',
				null,
				el(
					'linearGradient',
					{ id: GRADIENT_ID, x1: '0', y1: '1', x2: '1', y2: '0' },
					GRADIENT_STOPS.map(function (stop, index) {
						return el('stop', { key: stop, offset: (index * 25) + '%', stopColor: stop });
					})
				)
			)
		);
	}

	/**
	 * Icône de marque officielle d'un réseau (données PHP : chemin Simple
	 * Icons + couleur, ou dégradé Instagram).
	 */
	function brandIcon(net, size) {
		if (!net || !net.path) {
			return null;
		}

		return el(
			'svg',
			{ width: size, height: size, viewBox: '0 0 24 24', 'aria-hidden': true, focusable: 'false' },
			el('path', { fill: net.gradient ? 'url(#' + GRADIENT_ID + ')' : net.color, d: net.path })
		);
	}

	function EditorSocialPanel() {
		var isOpen = useState(false);
		var open = isOpen[0];
		var setOpen = isOpen[1];

		var selectedState = useState(function () {
			var initial = {};
			networks.forEach(function (net) {
				initial[net.key] = !!net.configured;
			});
			return initial;
		});
		var selected = selectedState[0];
		var setSelected = selectedState[1];

		var sendingState = useState(false);
		var sending = sendingState[0];
		var setSending = sendingState[1];

		var createNotice = useDispatch('core/notices').createNotice;

		/* Statut de l'article courant, réactif aux changements de l'éditeur. */
		var postStatus = useSelect(function (select) {
			var editor = select('core/editor');
			var post = editor && editor.getCurrentPost ? editor.getCurrentPost() : null;
			return post ? String(post.status || '') : '';
		}, []);
		var isPublished = postStatus === 'publish';

		function toggle() {
			setOpen(!open);
		}

		function toggleNetwork(key) {
			var next = Object.assign({}, selected);
			next[key] = !selected[key];
			setSelected(next);
		}

		function selectedCount() {
			return networks.filter(function (net) {
				return net.configured && selected[net.key];
			}).length;
		}

		/**
		 * Publication vers les réseaux sélectionnés.
		 *
		 * Point de branchement de l'appel réel : quand l'endpoint existera
		 * (REST ou admin-ajax), envoyer ici les réseaux sélectionnés et
		 * l'ID de l'article, avec settings.nonce (action
		 * 'aix_live_editor_publish') vérifié côté serveur. En attendant,
		 * un état d'envoi simulé est affiché et une notice informe que la
		 * fonctionnalité est à venir.
		 */
		function handlePublish() {
			if (sending || !isPublished) {
				return;
			}
			setSending(true);
			window.setTimeout(function () {
				setSending(false);
				createNotice(
					'info',
					__('Fonctionnalité de publication à venir.', 'ainatrix'),
					{ type: 'snackbar', isDismissible: true }
				);
			}, 800);
		}

		function renderRow(net) {
			var disabled = !net.configured;
			var sub;

			if (disabled) {
				sub = el(
					'a',
					{ href: settings.settingsUrl || '#' },
					__('Configurer dans Réglages', 'ainatrix')
				);
			} else if (net.account) {
				sub = net.account;
			} else {
				sub = null;
			}

			return el(
				'label',
				{
					key: net.key,
					className: 'aix-net' + (disabled ? ' aix-net--disabled' : '')
				},
				el('input', {
					type: 'checkbox',
					className: 'aix-net__check',
					checked: !!selected[net.key],
					disabled: disabled,
					onChange: function () {
						toggleNetwork(net.key);
					}
				}),
				el('span', { className: 'aix-net__ico', 'aria-hidden': true }, brandIcon(net, 20)),
				el(
					'span',
					{ className: 'aix-net__body' },
					el(
						'span',
						{ className: 'aix-net__name' },
						net.label,
						' ',
						el(
							'span',
							{
								className: 'aix-badge ' +
									(net.configured ? 'aix-badge--ok' : 'aix-badge--todo')
							},
							net.configured ? __('Configuré', 'ainatrix') : __('Non configuré', 'ainatrix')
						)
					),
					sub ? el('span', { className: 'aix-net__sub' }, sub) : null
				)
			);
		}

		return el(
			PluginPostStatusInfo,
			{ className: 'aix-live-status-info' },
			el(
				'div',
				{ className: 'aix-live-social' },
				el(
					Button,
					{
						className: 'aix-live-trigger',
						isPressed: open,
						'aria-expanded': open,
						onClick: toggle
					},
					TRIGGER_ICON,
					el(
						'span',
						{ className: 'aix-live-trigger__label' },
						__('Réseaux sociaux', 'ainatrix')
					)
				),
				open
					? el(
						'div',
						{ className: 'aix-live-panel' },
						networks.some(function (net) {
							return net.gradient;
						}) ? renderGradientDefs() : null,
						el(
							'p',
							{ className: 'aix-live-intro' },
							__('Diffuser cet article vers les réseaux configurés :', 'ainatrix')
						),
						el('div', { className: 'aix-live-nets' }, networks.map(renderRow)),
						el(
							'div',
							{ className: 'aix-live-actions' },
							el(
								Button,
								{
									variant: 'primary',
									className: 'aix-live-publish',
									disabled: !isPublished || sending,
									isBusy: sending,
									onClick: handlePublish
								},
								sending
									? __('Envoi en cours…', 'ainatrix')
									: __('Publier sur les réseaux', 'ainatrix')
							),
							isPublished
								? el(
									'p',
									{ className: 'aix-live-help aix-live-help--ok' },
									el('span', { className: 'aix-live-help__dot', 'aria-hidden': true }, '✓'),
									sprintf(
										/* translators: %d: nombre de réseaux sélectionnés. */
										__('%d réseau(x) sélectionné(s) — l’article est en ligne, la diffusion est possible.', 'ainatrix'),
										selectedCount()
									)
								)
								: el(
									'p',
									{ className: 'aix-live-help aix-live-help--warn' },
									el('span', { className: 'aix-live-help__dot', 'aria-hidden': true }, 'ⓘ'),
									__('L’article doit être publié pour être diffusé sur les réseaux sociaux.', 'ainatrix')
								)
						)
					)
					: null
			)
		);
	}

	registerPlugin('ainatrix-live-editor-panel', {
		render: EditorSocialPanel
	});
})(window.wp);
