/**
 * Editor Bindings Module
 * Collega gli eventi dell'editor alla metabox
 *
 * @package FP\SEO
 */

/**
 * Collega gli eventi dell'editor Classic
 * @param {Function} callback
 */
export function bindClassicEditor(callback) {
	const fields = ['title', 'content', 'excerpt'];

	fields.forEach(id => {
		const field = document.getElementById(id);

		if (!field) {
			return;
		}

		field.addEventListener('input', callback);
		field.addEventListener('change', callback);
	});
}

/**
 * Collega gli eventi dell'editor Gutenberg
 * @param {Function} callback
 */
export function bindBlockEditor(callback) {
	if (!window.wp || !window.wp.data || typeof window.wp.data.subscribe !== 'function') {
		return;
	}

	const select = window.wp.data.select('core/editor');

	if (!select) {
		return;
	}

	window.wp.data.subscribe(callback);
}