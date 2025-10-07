/**
 * Editor Metabox State Management
 * Gestisce lo stato della metabox dell'editor
 *
 * @package FP\SEO
 */

export class MetaboxState {
	constructor(config) {
		this.enabled = !!config.enabled;
		this.excluded = !!config.excluded;
		this.lastPayload = null;
		this.timer = null;
		this.busy = false;
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
	 * Imposta lo stato excluded
	 * @param {boolean} excluded
	 */
	setExcluded(excluded) {
		this.excluded = excluded;
	}

	/**
	 * Verifica se è excluded
	 * @returns {boolean}
	 */
	isExcluded() {
		return this.excluded;
	}

	/**
	 * Verifica se è enabled
	 * @returns {boolean}
	 */
	isEnabled() {
		return this.enabled;
	}

	/**
	 * Imposta l'ultimo payload inviato
	 * @param {string} payload
	 */
	setLastPayload(payload) {
		this.lastPayload = payload;
	}

	/**
	 * Ottiene l'ultimo payload
	 * @returns {string|null}
	 */
	getLastPayload() {
		return this.lastPayload;
	}

	/**
	 * Imposta il timer
	 * @param {number} timer
	 */
	setTimer(timer) {
		this.timer = timer;
	}

	/**
	 * Cancella il timer
	 */
	clearTimer() {
		if (this.timer) {
			window.clearTimeout(this.timer);
			this.timer = null;
		}
	}

	/**
	 * Ottiene il timer
	 * @returns {number|null}
	 */
	getTimer() {
		return this.timer;
	}
}