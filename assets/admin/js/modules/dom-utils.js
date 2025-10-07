/**
 * DOM Utilities Module
 * Funzioni di utilità per la manipolazione del DOM
 *
 * @package FP\SEO
 */

/**
 * Cancella tutti i figli di un elemento
 * @param {HTMLElement} element - Elemento da svuotare
 */
export function clearList(element) {
	if (!element) {
		return;
	}

	while (element.firstChild) {
		element.removeChild(element.firstChild);
	}
}

/**
 * Crea un elemento HTML con attributi e contenuto
 * @param {string} tag - Tag HTML
 * @param {Object} attributes - Attributi da impostare
 * @param {string|HTMLElement|Array} content - Contenuto dell'elemento
 * @returns {HTMLElement}
 */
export function createElement(tag, attributes = {}, content = null) {
	const element = document.createElement(tag);

	Object.entries(attributes).forEach(([key, value]) => {
		if (key === 'className') {
			element.className = value;
		} else if (key === 'dataset') {
			Object.entries(value).forEach(([dataKey, dataValue]) => {
				element.dataset[dataKey] = dataValue;
			});
		} else {
			element.setAttribute(key, value);
		}
	});

	if (content) {
		if (typeof content === 'string') {
			element.textContent = content;
		} else if (Array.isArray(content)) {
			content.forEach(child => {
				if (child instanceof HTMLElement) {
					element.appendChild(child);
				}
			});
		} else if (content instanceof HTMLElement) {
			element.appendChild(content);
		}
	}

	return element;
}

/**
 * Trova l'elemento più vicino che soddisfa un selettore
 * @param {HTMLElement} element - Elemento di partenza
 * @param {string} selector - Selettore CSS
 * @returns {HTMLElement|null}
 */
export function closest(element, selector) {
	if (!element) {
		return null;
	}

	return element.closest ? element.closest(selector) : null;
}