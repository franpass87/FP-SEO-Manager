/**
 * Bulk Auditor State Management
 * Gestisce lo stato dell'auditor bulk
 *
 * @package FP\SEO
 */

export class BulkAuditorState {
	constructor() {
		this.busy = false;
		this.selectedIds = new Set();
	}

	/**
	 * Imposta lo stato busy
	 * @param {boolean} busy
	 */
	setBusy(busy) {
		this.busy = busy;
	}

	/**
	 * Verifica se è busy
	 * @returns {boolean}
	 */
	isBusy() {
		return this.busy;
	}

	/**
	 * Aggiunge un ID alla selezione
	 * @param {string|number} id
	 */
	addSelection(id) {
		this.selectedIds.add(String(id));
	}

	/**
	 * Rimuove un ID dalla selezione
	 * @param {string|number} id
	 */
	removeSelection(id) {
		this.selectedIds.delete(String(id));
	}

	/**
	 * Imposta tutti gli ID selezionati
	 * @param {Array} ids
	 */
	setSelection(ids) {
		this.selectedIds = new Set(ids.map(id => String(id)));
	}

	/**
	 * Cancella la selezione
	 */
	clearSelection() {
		this.selectedIds.clear();
	}

	/**
	 * Ottiene gli ID selezionati
	 * @returns {Array}
	 */
	getSelectedIds() {
		return Array.from(this.selectedIds);
	}

	/**
	 * Verifica se un ID è selezionato
	 * @param {string|number} id
	 * @returns {boolean}
	 */
	isSelected(id) {
		return this.selectedIds.has(String(id));
	}
}