/**
 * Bulk Auditor Entry Point
 * Punto di ingresso per il bulk auditor modulare
 *
 * @package FP\SEO
 */

import { initBulkAuditor } from './modules/bulk-auditor/index.js';

(function() {
	'use strict';

	// Attende il caricamento del DOM
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const config = window.fpSeoPerformanceBulk || {};
		
		if (!config.ajaxUrl) {
			return;
		}

		initBulkAuditor(config);
	}
})();