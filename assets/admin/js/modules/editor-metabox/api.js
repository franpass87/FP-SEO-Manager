/**
 * Editor Metabox API Module
 * Gestisce le chiamate AJAX per l'analisi
 *
 * @package FP\SEO
 */

/**
 * Invia una richiesta di analisi
 * @param {Object} config
 * @param {Object} payload
 * @returns {Promise}
 */
export function sendAnalysis(config, payload) {
	return new Promise((resolve, reject) => {
		if (!config.ajaxUrl) {
			reject(new Error('Missing AJAX URL'));
			return;
		}

		const formData = new FormData();
		formData.append('action', 'fp_seo_performance_analyze');
		formData.append('nonce', config.nonce);
		formData.append('postId', config.postId);
		
		Object.entries(payload).forEach(([key, value]) => {
			formData.append(key, value);
		});

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(response => response.json())
			.then(data => {
				if (!data || !data.success) {
					reject(new Error(data?.data?.message || 'Analysis failed'));
					return;
				}

				resolve(data.data);
			})
			.catch(error => reject(error));
	});
}