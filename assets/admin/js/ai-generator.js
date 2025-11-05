/**
 * AI Content Generator for FP SEO Performance
 * 
 * @package FP\SEO
 */

(function($) {
	'use strict';

	/**
	 * AI Generator class
	 */
	class FpSeoAiGenerator {
		constructor() {
			this.init();
		}

		init() {
			this.$generateBtn = $('#fp-seo-ai-generate-btn');
			this.$applyBtn = $('#fp-seo-ai-apply-btn');
			this.$copyBtn = $('#fp-seo-ai-copy-btn');
			this.$loading = $('#fp-seo-ai-loading');
			this.$results = $('#fp-seo-ai-results');
			this.$error = $('#fp-seo-ai-error');
			this.$errorMessage = $('#fp-seo-ai-error-message');

			this.bindEvents();
		}

		bindEvents() {
			this.$generateBtn.on('click', (e) => this.handleGenerate(e));
			this.$applyBtn.on('click', (e) => this.handleApply(e));
			this.$copyBtn.on('click', (e) => this.handleCopy(e));
		}

		/**
		 * Get current post content
		 */
		getPostContent() {
			// Check if Gutenberg editor is active
			if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
				const editor = wp.data.select('core/editor');
				if (editor) {
					return editor.getEditedPostContent();
				}
			}

			// Fallback to classic editor
			if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
				return tinyMCE.activeEditor.getContent();
			}

			// Fallback to textarea
			const $content = $('#content');
			if ($content.length) {
				return $content.val();
			}

			return '';
		}

		/**
		 * Get current post title
		 */
		getPostTitle() {
			// Check if Gutenberg editor is active
			if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
				const editor = wp.data.select('core/editor');
				if (editor) {
					return editor.getEditedPostAttribute('title');
				}
			}

			// Fallback to title input
			const $title = $('#title');
			if ($title.length) {
				return $title.val();
			}

			return '';
		}

	/**
	 * Handle generate button click
	 */
	async handleGenerate(e) {
		e.preventDefault();

		const postId = this.$generateBtn.data('post-id');
		const nonce = this.$generateBtn.data('nonce');
		const content = this.getPostContent();
		const title = this.getPostTitle();
		const focusKeyword = $('#fp-seo-ai-focus-keyword-input').val() || '';

		// Validate
		if (!content && !title) {
			this.showError('Per favore inserisci almeno un titolo o del contenuto prima di generare.');
			return;
		}

		// Show loading
		this.showLoading();
		this.hideError();
		this.hideResults();
		this.$generateBtn.prop('disabled', true);

		try {
			const response = await $.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fp_seo_generate_ai_content',
					nonce: nonce,
					post_id: postId,
					content: content,
					title: title,
					focus_keyword: focusKeyword
				}
			});

			if (response.success && response.data) {
				this.displayResults(response.data);
			} else {
				this.showError(response.data?.message || 'Errore durante la generazione del contenuto.');
			}
		} catch (error) {
			console.error('AI Generation Error:', error);
			
			// Try to extract error message from response
			let errorMessage = 'Errore di connessione. Riprova più tardi.';
			
			if (error.responseJSON && error.responseJSON.data && error.responseJSON.data.message) {
				errorMessage = error.responseJSON.data.message;
			} else if (error.statusText) {
				errorMessage = 'Errore del server (' + error.status + '): ' + error.statusText;
			}
			
			this.showError(errorMessage);
		} finally {
			this.hideLoading();
			this.$generateBtn.prop('disabled', false);
		}
	}

	/**
	 * Display generated results
	 */
	displayResults(data) {
		const seoTitle = data.seo_title || '';
		const metaDescription = data.meta_description || '';
		
		// Update fields
		$('#fp-seo-ai-title').val(seoTitle);
		$('#fp-seo-ai-description').val(metaDescription);
		$('#fp-seo-ai-slug').val(data.slug || '');
		$('#fp-seo-ai-keyword').val(data.focus_keyword || '');

		// Update character counts
		this.updateCharCount('#fp-seo-ai-title-count', seoTitle.length, 60);
		this.updateCharCount('#fp-seo-ai-description-count', metaDescription.length, 155);

		// Usa CSS animation invece di jQuery slideDown per performance
		this.$results
			.addClass('is-animating fp-seo-ai-success')
			.slideDown(300, () => {
				// Remove will-change dopo animazione per performance
				this.$results.removeClass('is-animating');
			});
	}

	/**
	 * Update character count display
	 * Usa classi CSS invece di inline styles per performance
	 */
	updateCharCount(selector, current, max) {
		const $counter = $(selector);
		if (!$counter.length) return;

		const percentage = (current / max) * 100;
		let statusClass = 'fp-seo-char-counter--ok';

		if (percentage > 100) {
			statusClass = 'fp-seo-char-counter--error';
		} else if (percentage > 90) {
			statusClass = 'fp-seo-char-counter--warning';
		}

	// Usa classi CSS per animazioni hardware-accelerated
	// Sanitize numbers to prevent potential XSS if code changes
	const safeCount = parseInt(current, 10) || 0;
	const safeMax = parseInt(max, 10) || 0;
	
	$counter
		.removeClass('fp-seo-char-counter--ok fp-seo-char-counter--warning fp-seo-char-counter--error')
		.addClass('fp-seo-char-counter ' + statusClass)
		.html('<span class="fp-seo-char-counter__current">' + safeCount + '</span>/' + safeMax);
	}

	/**
	 * Apply suggestions to the post
	 */
	handleApply(e) {
		e.preventDefault();

		const seoTitle = $('#fp-seo-ai-title').val();
		const metaDescription = $('#fp-seo-ai-description').val();
		const slug = $('#fp-seo-ai-slug').val();
		const focusKeyword = $('#fp-seo-ai-keyword').val();

		// Apply to SEO fields in the metabox
		if ($('#fp-seo-title').length) {
			$('#fp-seo-title').val(seoTitle).trigger('input');
		}
		if ($('#fp-seo-meta-description').length) {
			$('#fp-seo-meta-description').val(metaDescription).trigger('input');
		}
		if ($('#fp-seo-focus-keyword').length && focusKeyword) {
			$('#fp-seo-focus-keyword').val(focusKeyword);
		}

		// Apply to Gutenberg if available
		if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
			const editor = wp.data.dispatch('core/editor');
			if (editor) {
				// Update post title if empty or user confirms
				const currentTitle = this.getPostTitle();
				if (!currentTitle || confirm('Vuoi sostituire il titolo attuale con quello generato dall\'AI?')) {
					editor.editPost({ title: seoTitle });
				}

				// Update slug
				if (slug) {
					editor.editPost({ slug: slug });
				}

				this.showSuccessMessage('✨ Suggerimenti applicati con successo! SEO Title e Meta Description popolati.');
				
				// Micro-celebrazione visiva (solo CSS, 0 peso)
				this.$applyBtn.addClass('fp-seo-celebrate');
				setTimeout(() => this.$applyBtn.removeClass('fp-seo-celebrate'), 600);
				return;
			}
		}

		// Fallback for classic editor
		const $titleField = $('#title');
		if ($titleField.length) {
			const currentTitle = $titleField.val();
			if (!currentTitle || confirm('Vuoi sostituire il titolo attuale con quello generato dall\'AI?')) {
				$titleField.val(seoTitle);
			}
		}

		// Update slug field if exists
		const $slugField = $('#post_name, #editable-post-name');
		if ($slugField.length && slug) {
			$slugField.val(slug);
		}

		this.showSuccessMessage('✨ Suggerimenti applicati con successo! SEO Title e Meta Description popolati.');
		
		// Micro-celebrazione visiva (solo CSS, 0 peso)
		this.$applyBtn.addClass('fp-seo-celebrate');
		setTimeout(() => this.$applyBtn.removeClass('fp-seo-celebrate'), 600);
	}

		/**
		 * Copy results to clipboard
		 */
		async handleCopy(e) {
			e.preventDefault();

			const seoTitle = $('#fp-seo-ai-title').val();
			const metaDescription = $('#fp-seo-ai-description').val();
			const slug = $('#fp-seo-ai-slug').val();
			const focusKeyword = $('#fp-seo-ai-keyword').val();

			const text = `Titolo SEO: ${seoTitle}\n\nMeta Description: ${metaDescription}\n\nSlug: ${slug}\n\nFocus Keyword: ${focusKeyword}`;

			try {
				await navigator.clipboard.writeText(text);
				this.showSuccessMessage('Contenuti copiati negli appunti!');
			} catch (error) {
				console.error('Copy error:', error);
				this.showError('Impossibile copiare negli appunti.');
			}
		}

		/**
		 * Show loading indicator
		 */
		showLoading() {
			this.$loading.slideDown(200);
		}

		/**
		 * Hide loading indicator
		 */
		hideLoading() {
			this.$loading.slideUp(200);
		}

		/**
		 * Show error message
		 */
		showError(message) {
			this.$errorMessage.text(message);
			this.$error.slideDown(300);
		}

		/**
		 * Hide error message
		 */
		hideError() {
			this.$error.slideUp(200);
		}

		/**
		 * Hide results
		 */
		hideResults() {
			this.$results.slideUp(200);
		}

	/**
	 * Show success message
	 */
	showSuccessMessage(message) {
		// Create a temporary success notification (XSS safe)
		const $notice = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p></p></div>');
		$notice.find('p').text(message); // Use .text() to prevent XSS
		this.$results.before($notice);

			setTimeout(() => {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		}
	}

	// Initialize when DOM is ready
	$(document).ready(function() {
		if ($('#fp-seo-ai-generate-btn').length) {
			new FpSeoAiGenerator();
		}
	});

})(jQuery);

