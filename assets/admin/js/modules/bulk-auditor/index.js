/**
 * Bulk Auditor Main Module
 * Modulo principale che coordina il bulk auditor
 *
 * @package FP\SEO
 */

import { BulkAuditorState } from './state.js';
import { BulkAuditorUI } from './ui.js';
import { processInChunks } from './api.js';
import { handleRowClick, handleKeyboardNavigation } from './events.js';

/**
 * Inizializza il Bulk Auditor
 * @param {Object} config - Configurazione dal backend
 */
export function initBulkAuditor(config) {
	const form = document.querySelector('[data-fp-seo-bulk-form]');

	if (!form) {
		return;
	}

	const elements = {
		form,
		status: form.querySelector('[data-fp-seo-bulk-status]'),
		analyzeButton: form.querySelector('[data-fp-seo-bulk-analyze]'),
		selectAll: form.querySelector('[data-fp-seo-bulk-select-all]')
	};

	const state = new BulkAuditorState();
	const ui = new BulkAuditorUI(elements);
	const rowSelector = '[data-fp-seo-bulk-row]';
	const chunkSize = parseInt(config.chunkSize, 10) || 5;
	const messages = config.messages || {};

	// Sincronizza stato iniziale
	syncAllRows();

	// Event: Click su Analizza
	elements.analyzeButton?.addEventListener('click', async () => {
		const selectedIds = ui.getSelectedIdsFromCheckboxes();

		if (!selectedIds.length) {
			ui.showMessage(messages.noneSelected || '');
			return;
		}

		ui.setControlsDisabled(true);
		
		try {
			await processInChunks(
				config,
				selectedIds,
				handleProgress,
				chunkSize
			);
			
			ui.showMessage(formatMessage(messages.complete || '', selectedIds.length));
		} catch (error) {
			ui.showMessage(messages.error || '');
		} finally {
			ui.setControlsDisabled(false);
		}
	});

	// Event: Select All
	elements.selectAll?.addEventListener('change', (e) => {
		const checked = e.target.checked;
		const checkboxes = form.querySelectorAll('input[name="post_ids[]"]');
		
		checkboxes.forEach(checkbox => {
			checkbox.checked = checked;
			const row = checkbox.closest(rowSelector);
			ui.setRowSelection(row, checked);
		});
	});

	// Event: Cambio singolo checkbox
	form.addEventListener('change', (e) => {
		if (e.target.matches('input[name="post_ids[]"]')) {
			const row = e.target.closest(rowSelector);
			ui.setRowSelection(row, e.target.checked);
		}
	});

	// Event: Click su riga
	form.addEventListener('click', (e) => {
		const row = e.target.closest(rowSelector);
		
		if (row) {
			handleRowClick(e, row, (id, selected) => {
				ui.setRowSelection(row, selected);
			});
		}
	});

	// Event: Navigazione tastiera
	form.addEventListener('keydown', (e) => {
		const row = e.target.closest(rowSelector);
		
		if (row) {
			handleKeyboardNavigation(e, row, rowSelector, (id, selected) => {
				ui.setRowSelection(row, selected);
			});
		}
	});

	/**
	 * Gestisce il progresso dell'analisi
	 * @param {Object} progress
	 */
	function handleProgress(progress) {
		const { processed, total, results } = progress;
		
		ui.showMessage(formatMessage(messages.processing || '', processed, total));
		
		results.forEach(result => {
			ui.updateRow(result);
		});
	}

	/**
	 * Formatta un messaggio con placeholder
	 * @param {string} template
	 * @param {number} value1
	 * @param {number} value2
	 * @returns {string}
	 */
	function formatMessage(template, value1, value2) {
		if (typeof template !== 'string') {
			return '';
		}

		let message = template;

		if (value2 !== undefined) {
			message = message.replace('%2$d', value2);
		}

		if (value1 !== undefined) {
			message = message.replace('%1$d', value1);
		}

		return message;
	}

	/**
	 * Sincronizza lo stato di tutte le righe
	 */
	function syncAllRows() {
		const rows = form.querySelectorAll(rowSelector);
		
		rows.forEach(row => {
			const checkbox = row.querySelector('input[name="post_ids[]"]');
			ui.setRowSelection(row, checkbox ? checkbox.checked : false);
		});
	}
}