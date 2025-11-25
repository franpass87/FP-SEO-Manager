/**
 * Real-Time SERP Preview
 * Shows Google snippet preview as user types
 *
 * @package FP\SEO
 */

(function() {
	'use strict';

class SerpPreview {
	constructor() {
		this.title = '';
		this.description = '';
		this.url = '';
		this.listeners = []; // Track listeners for cleanup
		this.unsubscribeGutenberg = null; // Track Gutenberg subscription
		this.init();
	}

	init() {
		// Wait for DOM ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', () => {
				// Also wait a bit for Gutenberg to initialize
				setTimeout(() => this.setup(), 100);
			});
		} else {
			// DOM already loaded, but wait a bit for Gutenberg
			setTimeout(() => this.setup(), 100);
		}
		
		// Also listen for Gutenberg editor ready (only if not already subscribed)
		if (window.wp && window.wp.data && !this.unsubscribeGutenberg) {
			let gutenbergCheckCount = 0;
			const maxGutenbergChecks = 20; // Stop after 20 checks to avoid infinite loop
			
			const gutenbergUnsubscribe = window.wp.data.subscribe(() => {
				// Check if metabox is now available
				if (!this.previewElement && document.querySelector('.fp-seo-performance-metabox')) {
					gutenbergCheckCount++;
					if (gutenbergCheckCount <= maxGutenbergChecks) {
						setTimeout(() => {
							if (!this.previewElement) {
								this.setup();
							}
						}, 200);
					} else if (gutenbergCheckCount === maxGutenbergChecks + 1) {
						// Stop checking after max attempts
						if (typeof gutenbergUnsubscribe === 'function') {
							gutenbergUnsubscribe();
						}
					}
				}
			});
			
			// Store unsubscribe for cleanup (only if we don't have one from bindEvents)
			if (typeof gutenbergUnsubscribe === 'function' && !this.unsubscribeGutenberg) {
				this.unsubscribeGutenberg = gutenbergUnsubscribe;
			}
		}
	}

	setup() {
		// Retry creating preview container with multiple attempts
		let attempts = 0;
		const maxAttempts = 10;
		
		const tryCreate = () => {
			attempts++;
			const created = this.createPreviewContainer();
			
			if (created && this.previewElement) {
				this.bindEvents();
				this.updatePreview();
			} else if (attempts < maxAttempts) {
				// Retry after increasing delay
				setTimeout(tryCreate, 100 * attempts);
			} else {
				// Final fallback: try to create at the end of metabox
				console.warn('FP SEO: SERP Preview section not found, creating at end of metabox');
				const metabox = document.querySelector('.fp-seo-performance-metabox');
				if (metabox) {
					const container = document.createElement('div');
					container.className = 'fp-seo-serp-preview';
					container.innerHTML = `
						<h4 class="fp-seo-performance-metabox__section-heading">
							🔍 SERP Preview
						</h4>
						<div class="fp-seo-serp-preview__container">
							<div class="fp-seo-serp-preview__device-toggle">
								<button type="button" class="fp-seo-serp-device active" data-device="desktop">💻 Desktop</button>
								<button type="button" class="fp-seo-serp-device" data-device="mobile">📱 Mobile</button>
							</div>
							
							<div class="fp-seo-serp-preview__snippet" data-device="desktop">
								<div class="fp-seo-serp-preview__url"></div>
								<div class="fp-seo-serp-preview__title"></div>
								<div class="fp-seo-serp-preview__description"></div>
								<div class="fp-seo-serp-preview__date"></div>
							</div>
						</div>
					`;
					
					// Insert after SERP Optimization section if it exists, otherwise at the beginning
					const serpSection = metabox.querySelector('.fp-seo-serp-optimization-section');
					if (serpSection && serpSection.nextElementSibling) {
						serpSection.parentNode.insertBefore(container, serpSection.nextElementSibling);
					} else if (serpSection) {
						serpSection.insertAdjacentElement('afterend', container);
					} else {
						// Insert after header if available
						const header = metabox.querySelector('.fp-seo-performance-metabox__header');
						if (header && header.nextElementSibling) {
							header.parentNode.insertBefore(container, header.nextElementSibling);
						} else {
							metabox.insertBefore(container, metabox.firstChild);
						}
					}
					
					this.previewElement = container.querySelector('.fp-seo-serp-preview__snippet');
					if (this.previewElement) {
						this.bindEvents();
						this.updatePreview();
					}
				}
			}
		};
		
		// Start trying immediately
		tryCreate();
	}

	createPreviewContainer() {
		// Don't create if already exists
		if (this.previewElement && document.contains(this.previewElement)) {
			return true;
		}
		
		const metabox = document.querySelector('.fp-seo-performance-metabox');
		if (!metabox) {
			return false;
		}

		// Remove any existing preview to avoid duplicates
		const existingPreview = metabox.querySelector('.fp-seo-serp-preview');
		if (existingPreview) {
			existingPreview.remove();
		}

		const container = document.createElement('div');
		container.className = 'fp-seo-serp-preview';
		container.innerHTML = `
			<h4 class="fp-seo-performance-metabox__section-heading">
				🔍 SERP Preview
			</h4>
			<div class="fp-seo-serp-preview__container">
				<div class="fp-seo-serp-preview__device-toggle">
					<button type="button" class="fp-seo-serp-device active" data-device="desktop">💻 Desktop</button>
					<button type="button" class="fp-seo-serp-device" data-device="mobile">📱 Mobile</button>
				</div>
				
				<div class="fp-seo-serp-preview__snippet" data-device="desktop">
					<div class="fp-seo-serp-preview__url"></div>
					<div class="fp-seo-serp-preview__title"></div>
					<div class="fp-seo-serp-preview__description"></div>
					<div class="fp-seo-serp-preview__date"></div>
				</div>
			</div>
		`;
		
		// First, try to find SERP Optimization section and insert after it
		const serpOptimizationSection = metabox.querySelector('.fp-seo-serp-optimization-section, [data-section="serp-optimization"]');
		
		if (serpOptimizationSection) {
			// Find the next element sibling (skip text nodes)
			let nextElement = serpOptimizationSection.nextElementSibling;
			
			if (nextElement) {
				// Insert before the next element
				serpOptimizationSection.parentNode.insertBefore(container, nextElement);
			} else {
				// If it's the last child, insert after using insertAdjacentElement
				serpOptimizationSection.insertAdjacentElement('afterend', container);
			}
		} else {
			// Fallback: try to find by text content
			const sections = metabox.querySelectorAll('.fp-seo-performance-metabox__section');
			let foundSection = null;
			
			for (const section of sections) {
				const heading = section.querySelector('.fp-seo-performance-metabox__section-heading');
				if (heading && (heading.textContent.includes('SERP Optimization') || heading.textContent.includes('Ottimizzazione SERP'))) {
					foundSection = section;
					break;
				}
			}
			
			if (foundSection) {
				// Find the next element sibling (skip text nodes)
				let nextElement = foundSection.nextElementSibling;
				
				if (nextElement) {
					foundSection.parentNode.insertBefore(container, nextElement);
				} else {
					// Insert after using insertAdjacentElement
					foundSection.insertAdjacentElement('afterend', container);
				}
			} else {
				// Final fallback: insert after header or at the beginning
				const header = metabox.querySelector('.fp-seo-performance-metabox__header');
				if (header && header.nextElementSibling) {
					header.parentNode.insertBefore(container, header.nextElementSibling);
				} else {
					// Insert at the beginning of metabox content
					const firstSection = metabox.querySelector('.fp-seo-performance-metabox__section');
					if (firstSection) {
						firstSection.parentNode.insertBefore(container, firstSection);
					} else {
						metabox.appendChild(container);
					}
				}
			}
		}

		this.previewElement = container.querySelector('.fp-seo-serp-preview__snippet');
		return !!this.previewElement;
	}

	bindEvents() {
		// SEO Title (priority) - use SEO title if available, otherwise post title
		const seoTitleInput = document.querySelector('#fp-seo-title, [name="fp_seo_title"]');
		if (seoTitleInput) {
			const handler = () => this.updatePreview();
			seoTitleInput.addEventListener('input', handler);
			this.listeners.push({ element: seoTitleInput, event: 'input', handler });
		}

		// Post Title (fallback) - also listen to post title changes
		const titleInput = document.querySelector('#title, [name="post_title"]');
		if (titleInput) {
			const handler = () => this.updatePreview();
			titleInput.addEventListener('input', handler);
			this.listeners.push({ element: titleInput, event: 'input', handler });
		}

		// Classic editor content
		if (typeof tinymce !== 'undefined') {
			tinymce.on('AddEditor', (e) => {
				e.editor.on('change keyup', () => this.updatePreview());
			});
		}

		// Block editor (Gutenberg) - Save unsubscribe function
		if (wp && wp.data) {
			// Only subscribe if not already subscribed
			if (!this.unsubscribeGutenberg) {
				this.unsubscribeGutenberg = wp.data.subscribe(() => this.updatePreview());
			}
		}

		// Meta description - prioritize FP SEO field
		const metaDesc = document.querySelector('#fp-seo-meta-description, [name="fp_seo_meta_description"], #yoast_wpseo_metadesc');
		if (metaDesc) {
			const handler = () => this.updatePreview();
			metaDesc.addEventListener('input', handler);
			this.listeners.push({ element: metaDesc, event: 'input', handler });
		}

		// Device toggle
		document.querySelectorAll('.fp-seo-serp-device').forEach(button => {
			const handler = (e) => {
				e.preventDefault();
				document.querySelectorAll('.fp-seo-serp-device').forEach(b => b.classList.remove('active'));
				button.classList.add('active');
				const device = button.dataset.device;
				this.previewElement.dataset.device = device;
			};
			button.addEventListener('click', handler);
			this.listeners.push({ element: button, event: 'click', handler });
		});
	}

	/**
	 * Cleanup method to remove all event listeners and prevent memory leaks
	 * Call this when the component is destroyed or page is unloaded
	 */
	destroy() {
		// Remove all DOM event listeners
		this.listeners.forEach(({ element, event, handler }) => {
			if (element && element.removeEventListener) {
				element.removeEventListener(event, handler);
			}
		});
		this.listeners = [];

		// Unsubscribe from Gutenberg
		if (this.unsubscribeGutenberg && typeof this.unsubscribeGutenberg === 'function') {
			this.unsubscribeGutenberg();
			this.unsubscribeGutenberg = null;
		}
	}

		updatePreview() {
			this.collectData();
			this.renderPreview();
		}

		collectData() {
			// Get title - prioritize SEO title over post title
			let title = '';
			
			// First, try to get SEO title
			const seoTitleInput = document.querySelector('#fp-seo-title, [name="fp_seo_title"]');
			if (seoTitleInput && seoTitleInput.value) {
				title = seoTitleInput.value.trim();
			}
			
			// Fallback to post title if SEO title is empty
			if (!title) {
				const titleInput = document.querySelector('#title, [name="post_title"]');
				if (titleInput) {
					title = titleInput.value.trim();
				}
			}
			
			// Fallback to Gutenberg editor title
			if (!title && wp && wp.data) {
				const editor = wp.data.select('core/editor');
				if (editor) {
					title = editor.getEditedPostAttribute('title') || '';
				}
			}
			
			this.title = title;

			// Get URL/slug
			const slugInput = document.querySelector('#post_name, [name="post_name"]');
			const postId = document.querySelector('#post_ID');
			if (slugInput && slugInput.value) {
				this.url = window.location.origin + '/' + slugInput.value + '/';
			} else if (this.title) {
				const slug = this.title.toLowerCase()
					.replace(/[^a-z0-9]+/g, '-')
					.replace(/^-|-$/g, '');
				this.url = window.location.origin + '/' + slug + '/';
			}

			// Get description - prioritize FP SEO meta description
			let description = '';
			
			// Try FP SEO meta description field first
			const metaDesc = document.querySelector('#fp-seo-meta-description, [name="fp_seo_meta_description"]');
			if (metaDesc && metaDesc.value) {
				description = metaDesc.value.trim();
			}
			
			// Fallback to Yoast if FP SEO field is empty
			if (!description) {
				const yoastDesc = document.querySelector('#yoast_wpseo_metadesc');
				if (yoastDesc && yoastDesc.value) {
					description = yoastDesc.value.trim();
				}
			}

			// Fallback to excerpt
			if (!description) {
				const excerpt = document.querySelector('#excerpt, [name="excerpt"]');
				if (excerpt) {
					description = excerpt.value;
				}
			}

			// Fallback to content
			if (!description) {
				let content = '';
				
				// Classic editor
				if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
					content = tinymce.activeEditor.getContent({format: 'text'});
				}
				
				// Gutenberg
				if (wp && wp.data) {
					const blocks = wp.data.select('core/editor')?.getBlocks?.();
					if (blocks) {
						content = blocks.map(block => block.attributes.content || '').join(' ');
					}
				}

				// Classic textarea
				if (!content) {
					const contentTextarea = document.querySelector('#content');
					if (contentTextarea) {
						content = contentTextarea.value.replace(/<[^>]*>/g, '');
					}
				}

				description = this.truncateText(content, 160);
			}

			this.description = description;
		}

		renderPreview() {
			if (!this.previewElement) return;

			const urlElement = this.previewElement.querySelector('.fp-seo-serp-preview__url');
			const titleElement = this.previewElement.querySelector('.fp-seo-serp-preview__title');
			const descElement = this.previewElement.querySelector('.fp-seo-serp-preview__description');
			const dateElement = this.previewElement.querySelector('.fp-seo-serp-preview__date');

			// URL
			const displayUrl = this.url.replace(/^https?:\/\//, '').replace(/\/$/, '');
			urlElement.textContent = displayUrl;

			// Title
			const truncatedTitle = this.truncateText(this.title, 60);
			titleElement.textContent = truncatedTitle || 'Untitled';
			
			// Show pixel width warning
			const titleWidth = this.calculatePixelWidth(this.title);
			if (titleWidth > 600) {
				titleElement.classList.add('fp-seo-serp-preview__title--truncated');
			} else {
				titleElement.classList.remove('fp-seo-serp-preview__title--truncated');
			}

			// Description
			const truncatedDesc = this.truncateText(this.description, 160);
			descElement.textContent = truncatedDesc || 'No description available';

			const descWidth = this.description.length;
			if (descWidth > 160) {
				descElement.classList.add('fp-seo-serp-preview__description--truncated');
			} else {
				descElement.classList.remove('fp-seo-serp-preview__description--truncated');
			}

			// Date
			const now = new Date();
			const days = ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'];
			const months = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
			dateElement.textContent = `${days[now.getDay()]} ${now.getDate()} ${months[now.getMonth()]}`;
		}

		truncateText(text, maxLength) {
			if (!text) return '';
			text = text.trim();
			if (text.length <= maxLength) return text;
			return text.substring(0, maxLength).trim() + '...';
		}

		calculatePixelWidth(text) {
			// Rough estimation: average char = 10px in Google results
			return text.length * 10;
		}
	}

	// Initialize
	const serpPreview = new SerpPreview();

	// Auto-cleanup on page unload to prevent memory leaks
	window.addEventListener('beforeunload', () => {
		if (serpPreview && serpPreview.destroy) {
			serpPreview.destroy();
		}
	});
})();

