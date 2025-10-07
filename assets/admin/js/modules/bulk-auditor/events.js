/**
 * Bulk Auditor Event Handlers
 * Gestisce gli eventi dell'interfaccia bulk auditor
 *
 * @package FP\SEO
 */

/**
 * Verifica se un evento deve essere ignorato (click su elementi interattivi)
 * @param {Event} event
 * @returns {boolean}
 */
export function shouldIgnoreEvent(event) {
	const interactiveSelector = 'a, button, input, label, select, textarea';
	const target = event.target;

	if (!target) {
		return false;
	}

	return target.closest(interactiveSelector) !== null;
}

/**
 * Gestisce il click su una riga
 * @param {Event} event
 * @param {HTMLElement} row
 * @param {Function} onToggle
 */
export function handleRowClick(event, row, onToggle) {
	if (shouldIgnoreEvent(event)) {
		return;
	}

	const checkbox = row.querySelector('input[name="post_ids[]"]');
	
	if (!checkbox) {
		return;
	}

	const nextState = !checkbox.checked;
	checkbox.checked = nextState;
	
	if (onToggle) {
		onToggle(checkbox.value, nextState);
	}

	row.focus();
}

/**
 * Gestisce la navigazione con tastiera
 * @param {Event} event
 * @param {HTMLElement} row
 * @param {string} rowSelector
 * @param {Function} onToggle
 */
export function handleKeyboardNavigation(event, row, rowSelector, onToggle) {
	const key = event.key;

	if (key === 'ArrowDown' || key === 'Down') {
		event.preventDefault();
		const nextRow = row.nextElementSibling;
		if (nextRow && nextRow.matches(rowSelector)) {
			nextRow.focus();
		}
		return;
	}

	if (key === 'ArrowUp' || key === 'Up') {
		event.preventDefault();
		const prevRow = row.previousElementSibling;
		if (prevRow && prevRow.matches(rowSelector)) {
			prevRow.focus();
		}
		return;
	}

	if (key === ' ' || key === 'Spacebar') {
		event.preventDefault();
		const checkbox = row.querySelector('input[name="post_ids[]"]');
		
		if (checkbox) {
			const nextState = !checkbox.checked;
			checkbox.checked = nextState;
			
			if (onToggle) {
				onToggle(checkbox.value, nextState);
			}
		}
		return;
	}

	if (key === 'Enter') {
		const link = row.querySelector('a');
		
		if (link) {
			event.preventDefault();
			window.location.assign(link.getAttribute('href'));
		}
	}
}