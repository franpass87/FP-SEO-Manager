/**
 * Bulk Auditor API Module
 * Gestisce le chiamate AJAX per l'analisi bulk
 *
 * @package FP\SEO
 */

/**
 * Invia un batch di post per l'analisi
 * @param {Object} config - Configurazione con ajaxUrl, action, nonce
 * @param {Array} postIds - Array di ID dei post
 * @returns {Promise}
 */
export function analyzeBatch(config, postIds) {
	return new Promise((resolve, reject) => {
		if (!config.ajaxUrl || !config.action) {
			reject(new Error('Missing configuration'));
			return;
		}

		const formData = new FormData();
		formData.append('action', config.action);
		formData.append('nonce', config.nonce);
		postIds.forEach(id => formData.append('post_ids[]', id));

		fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(response => response.json())
			.then(data => {
				if (!data || !data.success || !data.data || !data.data.results) {
					reject(new Error('Invalid response'));
					return;
				}

				resolve(data.data.results);
			})
			.catch(error => reject(error));
	});
}

/**
 * Processa i post in chunk
 * @param {Object} config - Configurazione
 * @param {Array} postIds - Array di ID dei post
 * @param {Function} onProgress - Callback di progresso
 * @param {number} chunkSize - Dimensione dei chunk
 * @returns {Promise}
 */
export async function processInChunks(config, postIds, onProgress, chunkSize = 5) {
	const batches = [];
	
	for (let i = 0; i < postIds.length; i += chunkSize) {
		batches.push(postIds.slice(i, i + chunkSize));
	}

	let processed = 0;
	const results = [];

	for (const batch of batches) {
		try {
			const batchResults = await analyzeBatch(config, batch);
			results.push(...batchResults);
			processed += batchResults.length;

			if (onProgress) {
				onProgress({
					processed,
					total: postIds.length,
					results: batchResults
				});
			}
		} catch (error) {
			throw error;
		}
	}

	return results;
}