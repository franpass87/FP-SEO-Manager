/**
 * Bulk Auditor UI Module
 * Gestisce l'interfaccia utente del bulk auditor
 *
 * @package FP\SEO
 */

/**
 * Classe per gestire l'interfaccia del Bulk Auditor
 */
export class BulkAuditorUI {
	constructor(elements) {
		this.elements = elements;
	}

	/**
	 * Mostra un messaggio di stato
	 * @param {string} message
	 */
	showMessage(message) {
		if (!this.elements.status) {
			return;
		}

		this.elements.status.textContent = message || '';
		
		if (message) {
			this.elements.status.removeAttribute('hidden');
		} else {
			this.elements.status.setAttribute('hidden', 'true');
		}
	}

	/**
	 * Abilita/disabilita i controlli
	 * @param {boolean} disabled
	 */
	setControlsDisabled(disabled) {
		if (this.elements.analyzeButton) {
			this.elements.analyzeButton.disabled = disabled;
			this.elements.analyzeButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
		}

		if (this.elements.selectAll) {
			this.elements.selectAll.disabled = disabled;
		}

		const checkboxes = this.elements.form?.querySelectorAll('input[name="post_ids[]"]');
		checkboxes?.forEach(checkbox => {
			checkbox.disabled = disabled;
		});
	}

	/**
	 * Aggiorna una riga della tabella con i nuovi dati
	 * @param {Object} rowData
	 */
	updateRow(rowData) {
		const postId = rowData.post_id || rowData.postId;
		
		if (!postId || !this.elements.form) {
			return;
		}

		const row = this.elements.form.querySelector(`[data-post-id="${postId}"]`);
		
		if (!row) {
			return;
		}

		if (rowData.status) {
			row.setAttribute('data-status', rowData.status);
		}

		const score = rowData.score !== undefined ? rowData.score : '—';
		const warnings = rowData.warnings !== undefined ? rowData.warnings : '—';
		const updated = rowData.updated_h || rowData.updatedHuman || rowData.updated_human || '';

		const scoreEl = row.querySelector('[data-fp-seo-bulk-score]');
		if (scoreEl) {
			scoreEl.textContent = score;
		}

		const warningsEl = row.querySelector('[data-fp-seo-bulk-warnings]');
		if (warningsEl) {
			warningsEl.textContent = warnings;
		}

		const updatedEl = row.querySelector('[data-fp-seo-bulk-updated]');
		if (updatedEl) {
			updatedEl.textContent = updated || (rowData.updated ? rowData.updated : '—');
		}
	}

	/**
	 * Imposta lo stato di selezione di una riga
	 * @param {HTMLElement} row
	 * @param {boolean} selected
	 */
	setRowSelection(row, selected) {
		if (!row) {
			return;
		}

		row.setAttribute('aria-selected', selected ? 'true' : 'false');
	}

	/**
	 * Mette il focus su una riga
	 * @param {HTMLElement} row
	 */
	focusRow(row) {
		if (row) {
			row.focus();
		}
	}

	/**
	 * Ottiene tutti gli ID selezionati dai checkbox
	 * @returns {Array}
	 */
	getSelectedIdsFromCheckboxes() {
		if (!this.elements.form) {
			return [];
		}

		const checkboxes = this.elements.form.querySelectorAll('input[name="post_ids[]"]:checked');
		return Array.from(checkboxes).map(cb => cb.value);
	}
}