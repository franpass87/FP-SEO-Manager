/**
 * Editor Metabox Entry Point
 * Punto di ingresso per la metabox dell'editor modulare
 *
 * @package FP\SEO
 */

import { initEditorMetabox } from './modules/editor-metabox/index.js';

(function(window, document) {
	'use strict';

	if (!window || !document) {
		return;
	}

	// Attende il caricamento del DOM
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const config = window.fpSeoPerformanceMetabox || null;

		if (!config) {
			return;
		}

		initEditorMetabox(config);
	}
})(window, window.document);