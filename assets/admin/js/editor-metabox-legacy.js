/**
 * Editor Metabox - Legacy Version (No ES6 Modules)
 * Fallback per compatibilità se ES6 modules non funzionano
 *
 * @package FP\SEO
 */

(function($, window, document) {
	'use strict';

	if (!$ || !window || !document) {
		console.error('FP SEO: jQuery or window not available');
		return;
	}

	// Attende il caricamento del DOM
	$(document).ready(function() {
		console.log('FP SEO: Editor metabox initializing...');
		
		const config = window.fpSeoPerformanceMetabox;

		if (!config) {
			console.error('FP SEO: Config not found! window.fpSeoPerformanceMetabox is undefined');
			console.log('Available:', Object.keys(window).filter(k => k.includes('fp')));
			return;
		}

		console.log('FP SEO: Config loaded', config);

		const $container = $('[data-fp-seo-metabox]');
		if (!$container.length) {
			console.error('FP SEO: Metabox container not found');
			return;
		}

		console.log('FP SEO: Container found', $container);

		const elements = {
			container: $container[0],
			scoreValue: $container.find('[data-fp-seo-score-value]')[0],
			scoreWrapper: $container.find('[data-fp-seo-score]')[0],
			message: $container.find('[data-fp-seo-message]')[0],
			excludeToggle: $container.find('[data-fp-seo-exclude]')[0]
		};

		let debounceTimer = null;

		// Inizializzazione
		if (!config.enabled) {
			setMessage(config.labels.disabled || 'Analyzer disabled');
			return;
		}

		if (config.excluded) {
			setMessage(config.labels.excluded || 'Content excluded');
			return;
		}

		console.log('FP SEO: Binding events to editor...');

		// Collega eventi Classic Editor
		bindClassicEditor();

		// Collega eventi Gutenberg
		bindGutenberg();

		console.log('FP SEO: Events bound successfully');

		/**
		 * Collega eventi Classic Editor
		 */
		function bindClassicEditor() {
			const fields = ['title', 'content', 'excerpt'];

			fields.forEach(function(id) {
				const field = document.getElementById(id);
				if (field) {
					console.log('FP SEO: Binding', id);
					$(field).on('input keyup change', scheduleAnalysis);
				}
			});

			// Focus keyword
			$('[data-fp-seo-focus-keyword]').on('input keyup', scheduleAnalysis);
			$('[data-fp-seo-secondary-keywords]').on('input keyup', scheduleAnalysis);
		}

		/**
		 * Collega eventi Gutenberg
		 */
		function bindGutenberg() {
			if (!window.wp || !window.wp.data) {
				console.log('FP SEO: Gutenberg not detected, using Classic mode');
				return;
			}

			const select = window.wp.data.select('core/editor');
			if (!select) {
				return;
			}

			console.log('FP SEO: Gutenberg detected, subscribing to changes');
			window.wp.data.subscribe(scheduleAnalysis);
		}

		/**
		 * Programma analisi con debounce
		 */
		function scheduleAnalysis() {
			console.log('FP SEO: scheduleAnalysis triggered');
			
			if (!config.enabled || config.excluded) {
				return;
			}

			if (debounceTimer) {
				clearTimeout(debounceTimer);
			}

			setMessage(config.labels.loading || 'Analyzing...');

			debounceTimer = setTimeout(function() {
				console.log('FP SEO: Performing analysis...');
				performAnalysis();
			}, 500);
		}

		/**
		 * Esegue l'analisi AJAX
		 */
		function performAnalysis() {
			const payload = gatherPayload();

			console.log('FP SEO: Sending AJAX request...', payload);

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				timeout: 30000, // 30 second timeout
				data: {
					action: 'fp_seo_performance_analyze',
					nonce: config.nonce,
					postId: config.postId,
					title: payload.title,
					content: payload.content,
					excerpt: payload.excerpt,
					focusKeyword: payload.focusKeyword,
					secondaryKeywords: payload.secondaryKeywords
				},
				success: function(response, textStatus, jqXHR) {
					console.log('FP SEO: AJAX success', response);
					
					if (response.success && response.data) {
						updateScore(response.data);
						setMessage('');
					} else {
						console.error('FP SEO: AJAX error', response);
						
						// Check for nonce expiration via response code
						if (response.data && response.data.code === 'rest_cookie_invalid_nonce') {
							setMessage('Sessione scaduta. Ricarica la pagina.');
						} else {
							setMessage(response.data?.message || config.labels.error || 'Error analyzing content');
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('FP SEO: AJAX failed', status, error, xhr);
					
					// Handle different error types
					if (status === 'timeout') {
						setMessage('Richiesta scaduta. Il server sta impiegando troppo tempo. Riprova.');
					} else if (xhr.status === 403) {
						setMessage('Sessione scaduta. Ricarica la pagina per continuare.');
					} else if (xhr.status === 0) {
						setMessage('Nessuna connessione. Verifica la tua connessione internet.');
					} else {
						setMessage(config.labels.error || 'Network error');
					}
				}
			});
		}

		/**
		 * Raccoglie i dati dal form
		 */
		function gatherPayload() {
			const titleField = document.getElementById('title');
			const contentField = document.getElementById('content');
			const excerptField = document.getElementById('excerpt');

			return {
				title: titleField ? titleField.value : '',
				content: contentField ? contentField.value : getGutenbergContent(),
				excerpt: excerptField ? excerptField.value : '',
				focusKeyword: $('[data-fp-seo-focus-keyword]').val() || '',
				secondaryKeywords: $('[data-fp-seo-secondary-keywords]').val() || ''
			};
		}

		/**
		 * Ottiene contenuto da Gutenberg
		 */
		function getGutenbergContent() {
			if (!window.wp || !window.wp.data) {
				return '';
			}

			const select = window.wp.data.select('core/editor');
			if (!select || !select.getEditedPostContent) {
				return '';
			}

			return select.getEditedPostContent();
		}

	/**
	 * Aggiorna lo score e l'analisi completa
	 */
	function updateScore(data) {
		if (!elements.scoreValue || !elements.scoreWrapper) {
			return;
		}

		const score = data.score?.score || 0;
		const status = data.score?.status || 'pending';

		$(elements.scoreValue).text(score);
		$(elements.scoreWrapper).attr('data-status', status);

		console.log('FP SEO: Score updated to', score, 'status:', status);

		// Aggiorna anche i check dell'analisi
		if (data.checks && Array.isArray(data.checks)) {
			updateAnalysisChecks(data.checks);
		}
	}

	/**
	 * Aggiorna la lista dei check SEO nell'UI
	 */
	function updateAnalysisChecks(checks) {
		const $analysisList = $('[data-fp-seo-analysis]');
		if (!$analysisList.length) {
			console.warn('FP SEO: Analysis list not found');
			return;
		}

		console.log('FP SEO: Updating analysis checks', checks.length, 'items');

		// Validazione input: checks deve essere un array
		if (!Array.isArray(checks)) {
			console.error('FP SEO: checks is not an array', typeof checks);
			return;
		}

		// Se non ci sono check, mostra messaggio di successo
		if (checks.length === 0) {
			const $parent = $analysisList.parent();
			if (!$parent.length) {
				console.warn('FP SEO: Parent element not found');
				return;
			}
			
			$parent.html(
				'<div class="fp-seo-performance-metabox__analysis-list--empty">' +
				'✅ ' + (config.labels.none || 'Ottimo! Tutti gli indicatori sono ottimali.') +
				'</div>'
			);
			updateSummaryBadges({ fail: 0, warn: 0, pass: 0 });
			return;
		}

		// Conta i check per status
		const statusCounts = {
			fail: 0,
			warn: 0,
			pass: 0
		};

		checks.forEach(function(check) {
			const status = check.status || 'pending';
			if (statusCounts.hasOwnProperty(status)) {
				statusCounts[status]++;
			}
		});

		// Aggiorna i badge di riepilogo
		updateSummaryBadges(statusCounts);

		// Genera l'HTML per ogni check
		let html = '';
		let delay = 0;

	checks.forEach(function(check) {
		delay += 0.05;
		const rawStatus = check.status || 'pending';
		
		// Whitelist validation per prevenire XSS tramite classi CSS
		const validStatuses = ['fail', 'warn', 'pass', 'pending'];
		const status = validStatuses.indexOf(rawStatus) !== -1 ? rawStatus : 'pending';
		
		let icon = '⚪';
		let statusText = 'In attesa';

		switch (status) {
			case 'fail':
				icon = '🔴';
				statusText = config.legend?.[status] || 'Critico';
				break;
			case 'warn':
				icon = '🟡';
				statusText = config.legend?.[status] || 'Attenzione';
				break;
			case 'pass':
				icon = '🟢';
				statusText = config.legend?.[status] || 'Ottimo';
				break;
		}

		html += '<li class="fp-seo-performance-analysis-item fp-seo-performance-analysis-item--' + status + '" style="animation-delay: ' + delay + 's">' +
				'<div class="fp-seo-performance-analysis-item__header">' +
				'<span class="fp-seo-performance-analysis-item__icon">' + icon + '</span>' +
				'<span class="fp-seo-performance-analysis-item__title">' + escapeHtml(check.label || '') + '</span>' +
				'<span class="fp-seo-performance-analysis-item__status">' + escapeHtml(statusText) + '</span>' +
				'</div>';

			if (check.hint) {
				html += '<div class="fp-seo-performance-analysis-item__description">' +
					escapeHtml(check.hint) +
					'</div>';
			}

			html += '</li>';
		});

		// Aggiorna l'HTML
		$analysisList.html(html);

		console.log('FP SEO: Analysis UI updated with', checks.length, 'checks');
	}

	/**
	 * Aggiorna i badge di riepilogo (fail/warn/pass)
	 */
	function updateSummaryBadges(counts) {
		const $summary = $('.fp-seo-performance-summary');
		if (!$summary.length) {
			console.warn('FP SEO: Summary badges not found');
			return;
		}

		let html = '';

		if (counts.fail > 0) {
			html += '<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--fail">' +
				'❌ ' + counts.fail + ' Critico' +
				'</span>';
		}

		if (counts.warn > 0) {
			html += '<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--warn">' +
				'⚠️ ' + counts.warn + ' Attenzione' +
				'</span>';
		}

		if (counts.pass > 0) {
			html += '<span class="fp-seo-performance-summary__badge fp-seo-performance-summary__badge--pass">' +
				'✅ ' + counts.pass + ' Ottimo' +
				'</span>';
		}

		$summary.html(html);
	}

	/**
	 * Escape HTML per prevenire XSS
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

		/**
		 * Mostra un messaggio
		 */
		function setMessage(text) {
			if (!elements.message) {
				return;
			}

			$(elements.message).text(text).toggle(!!text);
		}

		console.log('FP SEO: Initialization complete!');
	});

})(jQuery, window, document);


