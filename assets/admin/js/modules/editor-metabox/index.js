/**
 * Editor Metabox Main Module
 * Modulo principale che coordina la metabox dell'editor
 *
 * @package FP\SEO
 */

import { MetaboxState } from './state.js';
import { MetaboxUI } from './ui.js';
import { gatherPayload, hasPayloadChanged } from './content-provider.js';
import { sendAnalysis } from './api.js';
import { bindClassicEditor, bindBlockEditor } from './editor-bindings.js';

/**
 * Inizializza la metabox dell'editor
 * @param {Object} config - Configurazione dal backend
 */
export function initEditorMetabox(config) {
	const container = document.querySelector('[data-fp-seo-metabox]');

	if (!container) {
		return;
	}

	const elements = {
		container,
		scoreWrapper: container.querySelector('[data-fp-seo-score]'),
		scoreValue: container.querySelector('[data-fp-seo-score-value]'),
		indicatorList: container.querySelector('[data-fp-seo-indicators]'),
		recommendationList: container.querySelector('[data-fp-seo-recommendations]'),
		message: container.querySelector('[data-fp-seo-message]'),
		excludeToggle: container.querySelector('[data-fp-seo-exclude]'),
		focusKeywordField: container.querySelector('[data-fp-seo-focus-keyword]'),
		secondaryKeywordsField: container.querySelector('[data-fp-seo-secondary-keywords]')
	};

	const labels = config.labels || {};
	const legend = config.legend || {};
	const state = new MetaboxState(config);
	const ui = new MetaboxUI(elements, labels, legend);

	// Inizializzazione
	if (!state.isEnabled()) {
		ui.setMessage(labels.disabled || '');
	} else if (state.isExcluded()) {
		ui.setMessage(labels.excluded || '');
	} else if (config.initial) {
		ui.applyAnalysis(config.initial, state.isExcluded(), state.isEnabled());
	} else {
		performAnalysis(true);
	}

	// Collega gli eventi dell'editor
	bindClassicEditor(scheduleAnalysis);
	bindBlockEditor(scheduleAnalysis);

	// Collega il toggle exclude
	if (elements.excludeToggle) {
		elements.excludeToggle.addEventListener('change', handleExcludeToggle);
	}

	// Collega i campi keyword per real-time updates
	if (elements.focusKeywordField) {
		elements.focusKeywordField.addEventListener('input', scheduleAnalysis);
	}

	if (elements.secondaryKeywordsField) {
		elements.secondaryKeywordsField.addEventListener('input', scheduleAnalysis);
	}

	/**
	 * Programma un'analisi con debounce (ridotto a 500ms per maggiore reattività)
	 */
	function scheduleAnalysis() {
		if (!state.isEnabled() || state.isExcluded()) {
			return;
		}

		state.clearTimer();
		
		// Mostra subito indicatore "analyzing..." per feedback immediato
		ui.showLoading();

		const timer = window.setTimeout(() => {
			performAnalysis(false);
		}, 500); // Ridotto da 700ms a 500ms per maggiore reattività

		state.setTimer(timer);
	}

	/**
	 * Esegue l'analisi
	 * @param {boolean} force - Forza l'analisi anche se il payload non è cambiato
	 */
	async function performAnalysis(force) {
		if (!state.isEnabled() || state.isExcluded() || !config.ajaxUrl) {
			return;
		}

		const payload = gatherPayload();
		const serialized = JSON.stringify(payload);

		if (!force && !hasPayloadChanged(payload, state.getLastPayload())) {
			return;
		}

		state.setLastPayload(serialized);
		state.setBusy(true);
		state.setTimer(null);
		ui.showLoading();

		try {
			const result = await sendAnalysis(config, payload);
			
			state.setBusy(false);

			if (result.excluded) {
				state.setExcluded(true);
				ui.setMessage(labels.excluded || '');
				return;
			}

			ui.applyAnalysis(result, state.isExcluded(), state.isEnabled());
		} catch (error) {
			state.setBusy(false);
			ui.showError(error.message);
		}
	}

	/**
	 * Gestisce il cambio del toggle exclude
	 * @param {Event} event
	 */
	function handleExcludeToggle(event) {
		state.setExcluded(event.target.checked);

		if (state.isExcluded()) {
			ui.setMessage(labels.excluded || '');
			ui.renderIndicators([]);
			ui.renderRecommendations([]);
		} else {
			performAnalysis(true);
		}
	}
}