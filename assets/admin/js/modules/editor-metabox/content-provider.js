/**
 * Content Provider Module
 * Estrae il contenuto dall'editor (Gutenberg o Classic)
 *
 * @package FP\SEO
 */

/**
 * Estrae il contenuto dall'editor Gutenberg
 * @returns {Object|null}
 */
function getGutenbergContent() {
	if (!window.wp || !window.wp.data || typeof window.wp.data.select !== 'function') {
		return null;
	}

	const select = window.wp.data.select('core/editor');

	if (!select) {
		return null;
	}

	const focusKeywordField = document.getElementById('fp-seo-focus-keyword');
	const secondaryKeywordsField = document.getElementById('fp-seo-secondary-keywords');

	return {
		title: select.getEditedPostAttribute('title') || '',
		content: select.getEditedPostAttribute('content') || '',
		excerpt: select.getEditedPostAttribute('excerpt') || '',
		metaDescription: (select.getEditedPostAttribute('meta') || {}).fp_seo_meta_description || '',
		canonical: (select.getEditedPostAttribute('meta') || {}).fp_seo_meta_canonical || '',
		robots: (select.getEditedPostAttribute('meta') || {}).fp_seo_meta_robots || '',
		focusKeyword: focusKeywordField ? focusKeywordField.value || '' : '',
		secondaryKeywords: secondaryKeywordsField ? secondaryKeywordsField.value || '' : ''
	};
}

/**
 * Estrae il contenuto dall'editor Classic
 * @returns {Object}
 */
function getClassicContent() {
	const payload = {
		title: '',
		content: '',
		excerpt: '',
		metaDescription: '',
		canonical: '',
		robots: '',
		focusKeyword: '',
		secondaryKeywords: ''
	};

	const titleField = document.getElementById('title');
	const contentField = document.getElementById('content');
	const excerptField = document.getElementById('excerpt');
	const focusKeywordField = document.getElementById('fp-seo-focus-keyword');
	const secondaryKeywordsField = document.getElementById('fp-seo-secondary-keywords');

	if (titleField) {
		payload.title = titleField.value || '';
	}

	if (contentField) {
		payload.content = contentField.value || '';
	}

	if (excerptField) {
		payload.excerpt = excerptField.value || '';
	}

	if (focusKeywordField) {
		payload.focusKeyword = focusKeywordField.value || '';
	}

	if (secondaryKeywordsField) {
		payload.secondaryKeywords = secondaryKeywordsField.value || '';
	}

	return payload;
}

/**
 * Raccoglie il contenuto dall'editor
 * @returns {Object}
 */
export function gatherPayload() {
	const gutenbergContent = getGutenbergContent();

	if (gutenbergContent) {
		return gutenbergContent;
	}

	const classicContent = getClassicContent();

	// Fallback: usa excerpt come meta description se non presente
	if (!classicContent.metaDescription && classicContent.excerpt) {
		classicContent.metaDescription = classicContent.excerpt;
	}

	return classicContent;
}

/**
 * Verifica se il payload è cambiato
 * @param {Object} payload
 * @param {string} lastPayload
 * @returns {boolean}
 */
export function hasPayloadChanged(payload, lastPayload) {
	const serialized = JSON.stringify(payload);
	return serialized !== lastPayload;
}