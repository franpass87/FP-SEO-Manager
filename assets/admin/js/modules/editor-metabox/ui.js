/**
 * Editor Metabox UI Module
 * Gestisce l'interfaccia della metabox
 *
 * @package FP\SEO
 */

import { clearList, createElement } from '../dom-utils.js';

/**
 * Classe per gestire l'interfaccia della Metabox
 */
export class MetaboxUI {
	constructor(elements, labels, legend) {
		this.elements = elements;
		this.labels = labels;
		this.legend = legend;
	}

	/**
	 * Mostra un messaggio
	 * @param {string} text
	 */
	setMessage(text) {
		if (!this.elements.message) {
			return;
		}

		this.elements.message.textContent = text || '';
		this.elements.message.style.display = text ? 'block' : 'none';
	}

	/**
	 * Aggiorna il punteggio visualizzato
	 * @param {Object} data
	 */
	updateScore(data) {
		if (!this.elements.scoreWrapper || !this.elements.scoreValue) {
			return;
		}

		const score = data?.score?.score ?? 0;
		const status = data?.score?.status || 'pending';

		this.elements.scoreValue.textContent = String(score);
		this.elements.scoreWrapper.setAttribute('data-status', status);
	}

	/**
	 * Renderizza gli indicatori
	 * @param {Array} checks
	 */
	renderIndicators(checks) {
		clearList(this.elements.indicatorList);

		if (!this.elements.indicatorList || !checks || !checks.length) {
			return;
		}

		checks.forEach(check => {
			const item = this.createIndicatorElement(check);
			this.elements.indicatorList.appendChild(item);
		});
	}

	/**
	 * Crea un elemento indicatore
	 * @param {Object} check
	 * @returns {HTMLElement}
	 */
	createIndicatorElement(check) {
		const status = check.status || 'pending';
		const label = check.label || '';
		const hint = check.hint || '';
		const statusLabel = this.legend[status] || status;

		const statusBadge = createElement('span', {
			className: 'fp-seo-performance-indicator__status'
		}, statusLabel);

		const text = createElement('span', {
			className: 'fp-seo-performance-indicator__label'
		}, label);

		const item = createElement('li', {
			className: `fp-seo-performance-indicator fp-seo-performance-indicator--${status}`,
			role: 'listitem',
			tabindex: '0',
			'aria-label': `${statusLabel}: ${label}`,
			title: hint || undefined
		}, [statusBadge, text]);

		return item;
	}

	/**
	 * Renderizza le raccomandazioni
	 * @param {Array} items
	 */
	renderRecommendations(items) {
		clearList(this.elements.recommendationList);

		if (!this.elements.recommendationList || !items || !items.length) {
			return;
		}

		items.forEach(itemText => {
			const item = createElement('li', {}, itemText);
			this.elements.recommendationList.appendChild(item);
		});
	}

	/**
	 * Applica i risultati dell'analisi all'interfaccia
	 * @param {Object} result
	 * @param {boolean} excluded
	 * @param {boolean} enabled
	 */
	applyAnalysis(result, excluded, enabled) {
		if (!result) {
			return;
		}

		this.updateScore(result);
		this.renderIndicators(result.checks || []);
		this.renderRecommendations(result.score?.recommendations || []);

		if (excluded) {
			this.setMessage(this.labels.excluded || '');
			return;
		}

		if (!enabled) {
			this.setMessage(this.labels.disabled || '');
			return;
		}

		const recommendations = result.score?.recommendations?.length || 0;
		const status = result.score?.status || 'pending';

		if (recommendations === 0 && status === 'green') {
			this.setMessage(this.labels.none || '');
		} else {
			this.setMessage('');
		}
	}

	/**
	 * Mostra lo stato di caricamento
	 */
	showLoading() {
		this.setMessage(this.labels.loading || '');
	}

	/**
	 * Mostra un errore
	 * @param {string} message
	 */
	showError(message) {
		this.setMessage(message || this.labels.error || '');
	}
}