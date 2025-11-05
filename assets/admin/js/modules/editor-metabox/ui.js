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

		// Create summary badges
		this.renderSummary(checks);

		// Create indicator items with stagger effect
		checks.forEach((check, index) => {
			const item = this.createIndicatorElement(check);
			// Aggiungi delay progressivo (50ms per elemento)
			item.style.animationDelay = `${index * 0.05}s`;
			this.elements.indicatorList.appendChild(item);
		});
	}

	/**
	 * Renderizza il summary badge
	 * @param {Array} checks
	 */
	renderSummary(checks) {
		// Remove existing summary
		const existingSummary = this.elements.indicatorList.parentElement?.querySelector('.fp-seo-performance-summary');
		if (existingSummary) {
			existingSummary.remove();
		}

		// Count by status
		const counts = {
			fail: 0,
			warn: 0,
			pass: 0
		};

		checks.forEach(check => {
			const status = check.status || 'pending';
			if (counts.hasOwnProperty(status)) {
				counts[status]++;
			}
		});

		// Create summary container
		const summary = createElement('div', {
			className: 'fp-seo-performance-summary'
		});

		// Add badges
		if (counts.fail > 0) {
			const badge = createElement('span', {
				className: 'fp-seo-performance-summary__badge fp-seo-performance-summary__badge--fail'
			}, `❌ ${counts.fail} Fail`);
			summary.appendChild(badge);
		}

		if (counts.warn > 0) {
			const badge = createElement('span', {
				className: 'fp-seo-performance-summary__badge fp-seo-performance-summary__badge--warn'
			}, `⚠️ ${counts.warn} Warning`);
			summary.appendChild(badge);
		}

		if (counts.pass > 0) {
			const badge = createElement('span', {
				className: 'fp-seo-performance-summary__badge fp-seo-performance-summary__badge--pass'
			}, `✅ ${counts.pass} Pass`);
			summary.appendChild(badge);
		}

		// Insert before indicator list
		if (summary.children.length > 0) {
			this.elements.indicatorList.parentElement?.insertBefore(summary, this.elements.indicatorList);
		}
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

		const icon = createElement('span', {
			className: 'fp-seo-performance-indicator__icon'
		});

		const text = createElement('span', {
			className: 'fp-seo-performance-indicator__label'
		}, label);

		const children = [icon, text];

		// Aggiungi tooltip se c'è un hint
		if (hint) {
			const tooltip = createElement('span', {
				className: 'fp-seo-performance-indicator__tooltip'
			}, hint);
			children.push(tooltip);
		}

		const item = createElement('li', {
			className: `fp-seo-performance-indicator fp-seo-performance-indicator--${status}`,
			role: 'listitem',
			tabindex: '0',
			'aria-label': `${statusLabel}: ${label}`
		}, children);

		return item;
	}

	/**
	 * Renderizza le raccomandazioni
	 * @param {Array} items
	 */
	renderRecommendations(items) {
		const recommendationList = this.elements.recommendationList;
		const emptyMessage = document.querySelector('[data-fp-seo-recommendations-empty]');
		const countBadge = document.querySelector('[data-fp-seo-recommendations-count]');

		if (!recommendationList) {
			return;
		}

		clearList(recommendationList);

		// Update count badge
		if (countBadge) {
			countBadge.textContent = (items && items.length) || 0;
		}

		// Show empty state or list
		if (!items || items.length === 0) {
			recommendationList.style.display = 'none';
			if (emptyMessage) {
				emptyMessage.style.display = 'block';
			}
			return;
		}

		// Hide empty message and show list
		if (emptyMessage) {
			emptyMessage.style.display = 'none';
		}
		recommendationList.style.display = 'block';

		// Add items with stagger effect
		items.forEach((itemText, index) => {
			const item = createElement('li', {}, itemText);
			// Aggiungi delay progressivo (80ms per elemento)
			item.style.animationDelay = `${index * 0.08}s`;
			recommendationList.appendChild(item);
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
		
		// Aggiungi skeleton loader agli indicatori
		if (this.elements.indicatorList) {
			const items = this.elements.indicatorList.querySelectorAll('.fp-seo-performance-indicator');
			items.forEach(item => {
				// Rimuovi lo stato attuale e aggiungi pending (pulsante)
				item.classList.remove('fp-seo-performance-indicator--pass', 'fp-seo-performance-indicator--warn', 'fp-seo-performance-indicator--fail');
				item.classList.add('fp-seo-performance-indicator--pending');
			});
		}
	}

	/**
	 * Mostra un errore
	 * @param {string} message
	 */
	showError(message) {
		this.setMessage(message || this.labels.error || '');
	}
}