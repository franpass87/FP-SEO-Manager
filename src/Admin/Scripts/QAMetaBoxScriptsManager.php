<?php
/**
 * Manages scripts for the Q&A MetaBox.
 *
 * @package FP\SEO\Admin\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

use WP_Post;
use function esc_html_e;
use function esc_js;
use function esc_url;
use function get_current_screen;
use function home_url;
use function wp_create_nonce;

/**
 * Manages scripts for the Q&A MetaBox.
 */
class QAMetaBoxScriptsManager {
	/**
	 * @var WP_Post|null
	 */
	private $post;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'inject_scripts' ) );
	}

	/**
	 * Set post context.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function set_post( WP_Post $post ): void {
		$this->post = $post;
	}

	/**
	 * Inject scripts in admin footer.
	 *
	 * @return void
	 */
	public function inject_scripts(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		
		global $post;
		if ( ! $post ) {
			return;
		}
		
		$this->post = $post;
		$this->render_scripts();
	}

	/**
	 * Render all scripts.
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		if ( ! $this->post ) {
			return;
		}
		?>
		<script>
		jQuery(document).ready(function($) {
			<?php $this->render_generate_qa_handler(); ?>
			<?php $this->render_update_qa_list_function(); ?>
			<?php $this->render_delete_qa_handler(); ?>
			<?php $this->render_add_manual_qa_handler(); ?>
			
			// Initialize: sync hidden field with existing Q&A in DOM (in case page was loaded with existing Q&A but hidden field is empty)
			const $hiddenField = $('#fp-seo-qa-pairs-data');
			if ($hiddenField.length) {
				const hiddenValue = $hiddenField.val();
				const hiddenPairs = getAllQAPairsFromHiddenField();
				const domPairs = getAllQAPairsFromDOM();
				
				// If hidden field is empty or invalid, try to populate from DOM
				if (!hiddenValue || hiddenValue === '[]' || hiddenPairs.length === 0) {
					if (domPairs.length > 0) {
						updateQAHiddenField(domPairs);
						console.log('[FP-SEO] Initialized hidden field with', domPairs.length, 'Q&A pairs from DOM');
					}
				} else if (domPairs.length > 0) {
					// If both have data, DOM is source of truth (it reflects current UI state)
					// But only update if they differ to avoid unnecessary updates
					if (JSON.stringify(domPairs) !== JSON.stringify(hiddenPairs)) {
						updateQAHiddenField(domPairs);
						console.log('[FP-SEO] Synced hidden field with DOM (DOM had different data)');
					}
				}
			}
			
		// Function to sync Q&A pairs to hidden field before form submission
		function syncQAPairsToHiddenField() {
			console.log('[FP-SEO] syncQAPairsToHiddenField called');
			
			const $hiddenField = $('#fp-seo-qa-pairs-data');
			const $form = $('#post');
			
			if (!$hiddenField.length) {
				console.error('[FP-SEO] syncQAPairsToHiddenField - Hidden field not found!');
				return;
			}
			
			if (!$form.length) {
				console.error('[FP-SEO] syncQAPairsToHiddenField - Form not found!');
				return;
			}
			
			// CRITICAL: Always read from DOM first, as hidden field might be stale
			// DOM is the source of truth for current UI state
			let allPairs = getAllQAPairsFromDOM();
			console.log('[FP-SEO] syncQAPairsToHiddenField - DOM pairs:', allPairs ? allPairs.length : 0);
			
			// If DOM extraction failed or returned empty, check hidden field as fallback
			// But only if we're not explicitly trying to clear it (i.e., DOM shows empty list)
			const hiddenPairs = getAllQAPairsFromHiddenField();
			if ((!allPairs || allPairs.length === 0) && hiddenPairs.length > 0) {
				// Check if DOM actually has Q&A pairs but extraction failed
				const pairElements = document.querySelectorAll('#fp-seo-qa-list .fp-seo-qa-pair');
				if (pairElements.length > 0) {
					// DOM has pairs but extraction failed - keep hidden field value
					console.warn('[FP-SEO] syncQAPairsToHiddenField - DOM has', pairElements.length, 'pairs but extraction failed, keeping hidden field value');
					allPairs = hiddenPairs;
				} else {
					// DOM is truly empty - use empty array to clear saved Q&A
					allPairs = [];
				}
			}
			
			// CRITICAL: Always update hidden field, even if empty (to ensure deleted Q&A are not saved)
			// Use empty array if no pairs found
			const pairsToSave = allPairs || [];
			updateQAHiddenField(pairsToSave);
			console.log('[FP-SEO] syncQAPairsToHiddenField - Updated hidden field with', pairsToSave.length, 'Q&A pairs');
			
			// Ensure field is inside form
			if (!$form[0].contains($hiddenField[0])) {
				$form.append($hiddenField);
				console.log('[FP-SEO] Moved hidden field into form');
			}
			
			// Trigger change event to ensure WordPress recognizes the field change
			$hiddenField.trigger('change').trigger('input');
			
			// Also add a clone directly to form to ensure it's included
			// Remove any existing clones first
			$form.find('#fp-seo-qa-pairs-data-clone').remove();
			
			// Get the current value from hidden field (after update)
			const qaData = $hiddenField.val() || '[]';
			
			// Create clone using DOM API to avoid HTML escaping issues
			const cloneInput = document.createElement('input');
			cloneInput.type = 'hidden';
			cloneInput.id = 'fp-seo-qa-pairs-data-clone';
			cloneInput.name = 'fp_seo_qa_pairs_data';
			cloneInput.value = qaData; // Set value directly, no HTML escaping needed
			$form[0].appendChild(cloneInput);
			
			const finalValue = $hiddenField.val();
			console.log('[FP-SEO] syncQAPairsToHiddenField - Final hidden field value length:', finalValue ? finalValue.length : 0);
			console.log('[FP-SEO] syncQAPairsToHiddenField - Final hidden field value (first 500 chars):', finalValue ? finalValue.substring(0, 500) : 'empty');
			console.log('[FP-SEO] syncQAPairsToHiddenField - Added clone to form with value length:', qaData.length);
		}
		
		// CRITICAL: Sync Q&A pairs continuously to ensure hidden field is always up-to-date
		// This is more reliable than trying to sync only before submit
		setInterval(function() {
			const $hiddenField = $('#fp-seo-qa-pairs-data');
			if ($hiddenField.length) {
				const domPairs = getAllQAPairsFromDOM();
				const hiddenPairs = getAllQAPairsFromHiddenField();
				
				// Only sync if DOM and hidden field differ
				if (JSON.stringify(domPairs) !== JSON.stringify(hiddenPairs)) {
					console.log('[FP-SEO] Auto-sync detected difference, updating hidden field');
					updateQAHiddenField(domPairs);
				}
			}
		}, 500); // Check every 500ms for faster sync
		
		// CRITICAL: Intercept WordPress save mechanism to ensure Q&A data is included
		// WordPress might use AJAX or other mechanisms, so we intercept at multiple levels
		const $form = $('#post');
		if ($form.length) {
			// 1. Form submit event (standard form submission)
			$form.on('submit', function(e) {
				console.log('[FP-SEO] Form submit handler triggered');
				syncQAPairsToHiddenField();
				// Force field to be included by ensuring it's in the form
				const $hiddenField = $('#fp-seo-qa-pairs-data');
				if ($hiddenField.length && !$form[0].contains($hiddenField[0])) {
					$form.append($hiddenField);
					console.log('[FP-SEO] Moved hidden field into form');
				}
			});
			
			// 2. Intercept WordPress autosave (if it exists)
			if (typeof wp !== 'undefined' && wp.autosave) {
				const originalAutosave = wp.autosave.server.triggerSave;
				if (originalAutosave) {
					wp.autosave.server.triggerSave = function() {
						console.log('[FP-SEO] WordPress autosave intercepted');
						syncQAPairsToHiddenField();
						return originalAutosave.apply(this, arguments);
					};
				}
			}
			
			// 3. Intercept all button clicks (mousedown for earliest interception)
			$(document).on('mousedown', '#save-post, #publish, button[name="save"], input[name="publish"], #save-action input', function(e) {
				console.log('[FP-SEO] Save/Publish button mousedown - syncing Q&A');
				syncQAPairsToHiddenField();
				// Ensure field is in form
				const $hiddenField = $('#fp-seo-qa-pairs-data');
				const $form = $('#post');
				if ($hiddenField.length && $form.length && !$form[0].contains($hiddenField[0])) {
					$form.append($hiddenField.clone().attr('id', 'fp-seo-qa-pairs-data-clone'));
					console.log('[FP-SEO] Added hidden field clone to form');
				}
			});
			
			// 4. Capture phase event listener (runs before other handlers)
			document.addEventListener('click', function(e) {
				const target = e.target;
				if (target && (target.id === 'save-post' || target.id === 'publish' || 
				    target.name === 'save' || target.name === 'publish' ||
				    (target.tagName === 'INPUT' && target.type === 'submit'))) {
					console.log('[FP-SEO] Save/Publish button clicked (capture) - syncing Q&A');
					
					// CRITICAL: Sync FIRST synchronously, then create clone
					// Don't use setTimeout - sync immediately
					const $hiddenField = $('#fp-seo-qa-pairs-data');
					const $form = $('#post');
					
					if ($hiddenField.length && $form.length) {
						// Get Q&A pairs from DOM directly (most reliable)
						const domPairs = getAllQAPairsFromDOM();
						const qaData = JSON.stringify(domPairs || []);
						
						// Update hidden field immediately
						$hiddenField.val(qaData);
						
						// Remove any existing clones
						$form.find('#fp-seo-qa-pairs-data-clone').remove();
						
						// Create clone with correct value
						const cloneInput = document.createElement('input');
						cloneInput.type = 'hidden';
						cloneInput.id = 'fp-seo-qa-pairs-data-clone';
						cloneInput.name = 'fp_seo_qa_pairs_data';
						cloneInput.value = qaData; // Set value directly
						$form[0].appendChild(cloneInput);
						
						console.log('[FP-SEO] Added Q&A data clone to form with value length:', qaData.length);
						console.log('[FP-SEO] Clone value (first 200 chars):', qaData.substring(0, 200));
						
						// Also ensure original field is in form
						if (!$form[0].contains($hiddenField[0])) {
							$form.append($hiddenField);
						}
					}
				}
			}, true); // Use capture phase
			
			// 5. Intercept XMLHttpRequest to add Q&A data to AJAX saves
			if (typeof XMLHttpRequest !== 'undefined') {
				const originalOpen = XMLHttpRequest.prototype.open;
				const originalSend = XMLHttpRequest.prototype.send;
				
				XMLHttpRequest.prototype.open = function(method, url, ...args) {
					this._url = url;
					this._method = method;
					return originalOpen.apply(this, [method, url, ...args]);
				};
				
				XMLHttpRequest.prototype.send = function(data) {
					// If this is a POST to post.php or admin-ajax.php (WordPress post save)
					if (this._method === 'POST' && this._url && 
						(this._url.includes('post.php') || this._url.includes('admin-ajax.php'))) {
						
						// Sync Q&A data first
						syncQAPairsToHiddenField();
						
						// Small delay to ensure sync completes
						const self = this;
						
						setTimeout(function() {
							const $hiddenField = $('#fp-seo-qa-pairs-data');
							if ($hiddenField.length) {
								const qaData = $hiddenField.val() || '[]';
								
								// Add to FormData if it's FormData
								if (data instanceof FormData) {
									// Check if already present
									if (!data.has('fp_seo_qa_pairs_data')) {
										data.append('fp_seo_qa_pairs_data', qaData);
										console.log('[FP-SEO] Added Q&A data to FormData, length:', qaData.length);
									}
								} else if (typeof data === 'string') {
									// Add to URL-encoded string if not already present
									if (!data.includes('fp_seo_qa_pairs_data=')) {
										const separator = data.includes('&') ? '&' : '';
										data = data + separator + 'fp_seo_qa_pairs_data=' + encodeURIComponent(qaData);
										console.log('[FP-SEO] Added Q&A data to URL-encoded string, length:', qaData.length);
									}
								}
							}
							
							// Call original send with potentially modified data
							return originalSend.apply(self, [data]);
						}, 10);
						
						return; // Don't call original send yet, wait for setTimeout
					}
					
					return originalSend.apply(this, [data]);
				};
			}
			
			// 6. Intercept fetch API (WordPress might use fetch for some operations)
			if (typeof fetch !== 'undefined') {
				const originalFetch = window.fetch;
				window.fetch = function(url, options) {
					// If this is a POST request that might be saving a post
					if (options && options.method === 'POST' && 
						(typeof url === 'string' && (url.includes('post.php') || url.includes('admin-ajax.php')))) {
						
						// Sync Q&A data first
						syncQAPairsToHiddenField();
						
						// Add Q&A data to request body
						const $hiddenField = $('#fp-seo-qa-pairs-data');
						if ($hiddenField.length) {
							const qaData = $hiddenField.val() || '[]';
							
							if (options.body instanceof FormData) {
								if (!options.body.has('fp_seo_qa_pairs_data')) {
									options.body.append('fp_seo_qa_pairs_data', qaData);
									console.log('[FP-SEO] Added Q&A data to fetch FormData');
								}
							} else if (typeof options.body === 'string') {
								if (!options.body.includes('fp_seo_qa_pairs_data=')) {
									const separator = options.body.includes('&') ? '&' : '';
									options.body += separator + 'fp_seo_qa_pairs_data=' + encodeURIComponent(qaData);
									console.log('[FP-SEO] Added Q&A data to fetch body string');
								}
							}
						}
					}
					
					return originalFetch.apply(this, arguments);
				};
			}
			
			// 5. Intercept beforeunload (last chance before navigation)
			$(window).on('beforeunload', function() {
				syncQAPairsToHiddenField();
			});
		}
		});
		</script>
		<?php
	}

	/**
	 * Render generate Q&A handler.
	 *
	 * @return void
	 */
	private function render_generate_qa_handler(): void {
		?>
		// Generate Q&A with AI
		$('#fp-seo-generate-qa-btn').on('click', function() {
			console.log('[FP-SEO] Q&A Generate button clicked');
			const $btn = $(this);
			const postId = $btn.data('post-id');
			const originalText = $btn.html();

			console.log('[FP-SEO] Q&A Generate - postId:', postId);
			if (!postId) {
				console.error('[FP-SEO] Q&A Generate - No post ID found');
				alert('Errore: Post ID non trovato.');
				return;
			}

			$btn.prop('disabled', true).html('⏳ Generazione in corso...');

			const nonce = '<?php echo esc_js( wp_create_nonce( 'fp_seo_ai_first' ) ); ?>';
			console.log('[FP-SEO] Q&A Generate - Sending AJAX request', {
				action: 'fp_seo_generate_qa',
				post_id: postId,
				nonce: nonce ? nonce.substring(0, 10) + '...' : 'MISSING'
			});

			// Call AJAX endpoint to generate Q&A
			// GPT-4o Mini is faster, so we set a reasonable timeout (60 seconds)
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				timeout: 60000, // 60 seconds should be enough for GPT-4o Mini
				data: {
					action: 'fp_seo_generate_qa',
					post_id: postId,
					nonce: nonce
				},
				success: function(response) {
					console.log('[FP-SEO] Q&A Generate - Success response:', response);
					if (response.success && response.data && response.data.qa_pairs) {
						$btn.prop('disabled', false).html('✅ ' + response.data.message);
						
						// Update Q&A list without reloading page
						updateQAList(response.data.qa_pairs);
						
						// Show success message
						setTimeout(function() {
							$btn.html(originalText);
						}, 2000);
					} else {
						console.warn('[FP-SEO] Q&A Generate - Success but no pairs:', response);
						$btn.prop('disabled', false).html(originalText);
						alert('Errore: ' + (response.data?.message || 'Generazione fallita'));
					}
				},
				error: function(xhr, status, error) {
					console.error('[FP-SEO] Q&A Generation Error:', {
						status: status,
						error: error,
						responseText: xhr.responseText,
						statusCode: xhr.status
					});
					$btn.prop('disabled', false).html(originalText);
					alert('Errore durante la generazione. Verifica la console per dettagli.');
				}
			});
		});
		<?php
	}

	/**
	 * Render update Q&A list function.
	 *
	 * @return void
	 */
	private function render_update_qa_list_function(): void {
		$post_id = $this->post ? $this->post->ID : 0;
		$json_url = home_url( '/geo/content/' . $post_id . '/qa.json' );
		?>
		// Function to update hidden field with Q&A pairs data
		function updateQAHiddenField(qaPairs) {
			const $hiddenField = $('#fp-seo-qa-pairs-data');
			if ($hiddenField.length) {
				$hiddenField.val(JSON.stringify(qaPairs || []));
				console.log('[FP-SEO] Updated hidden field with', qaPairs ? qaPairs.length : 0, 'Q&A pairs');
			}
		}
		
		// Function to get all Q&A pairs from hidden field
		function getAllQAPairsFromHiddenField() {
			const $hiddenField = $('#fp-seo-qa-pairs-data');
			if ($hiddenField.length && $hiddenField.val()) {
				try {
					const parsed = JSON.parse($hiddenField.val());
					if (Array.isArray(parsed)) {
						return parsed;
					}
				} catch (e) {
					console.error('[FP-SEO] Error parsing hidden field JSON:', e);
				}
			}
			return [];
		}
		
		// Function to get all Q&A pairs from DOM
		function getAllQAPairsFromDOM() {
			const pairs = [];
			const qaList = document.querySelector('#fp-seo-qa-list');
			if (!qaList) {
				console.log('[FP-SEO] getAllQAPairsFromDOM - qaList not found');
				return pairs;
			}
			
			const pairElements = qaList.querySelectorAll('.fp-seo-qa-pair');
			console.log('[FP-SEO] getAllQAPairsFromDOM - Found', pairElements.length, 'pair elements');
			
			pairElements.forEach(function(pairEl, index) {
				// Use DOM selectors to extract Q&A - more reliable than regex
				// Structure: <div class="fp-seo-qa-pair"><div><div><strong>Q:</strong> <span>question</span></div><div><strong>A:</strong> <span>answer</span></div></div></div>
				
				let question = '';
				let answer = '';
				
				// Method 1: Find strong tags directly and get the next sibling span
				// The structure is: <strong>Q:</strong> <span>question</span>
				//                     <strong>A:</strong> <span>answer</span>
				const allStrongs = pairEl.querySelectorAll('strong');
				
				for (let i = 0; i < allStrongs.length; i++) {
					const strong = allStrongs[i];
					const strongText = strong.textContent || '';
					
					// Check if this is the Q: strong
					if (strongText.trim() === 'Q:' && !question) {
						// Try nextElementSibling first (most direct)
						let span = strong.nextElementSibling;
						if (span && span.tagName === 'SPAN') {
							question = span.textContent.trim();
						} else {
							// Fallback: find span in the same parent div
							const parentDiv = strong.closest('div');
							if (parentDiv) {
								const spans = parentDiv.querySelectorAll('span');
								if (spans.length > 0) {
									question = spans[0].textContent.trim();
								}
							}
						}
					}
					
					// Check if this is the A: strong
					if (strongText.trim() === 'A:' && !answer) {
						// Try nextElementSibling first (most direct)
						let span = strong.nextElementSibling;
						if (span && span.tagName === 'SPAN') {
							answer = span.textContent.trim();
						} else {
							// Fallback: find span in the same parent div
							const parentDiv = strong.closest('div');
							if (parentDiv) {
								const spans = parentDiv.querySelectorAll('span');
								if (spans.length > 0) {
									answer = spans[0].textContent.trim();
								}
							}
						}
					}
				}
				
				// Method 2: If not found, try direct textContent parsing (fallback)
				if (!question || !answer) {
					const text = pairEl.textContent || '';
					
					// More robust regex that handles multiline
					// Match Q: followed by text until A: or end
					const qMatch = text.match(/Q:\s*([^\n]+(?:\n(?!\s*A:)[^\n]+)*)/);
					// Match A: followed by text until ⭐ or end
					const aMatch = text.match(/A:\s*([^\n]+(?:\n(?!\s*⭐)[^\n]+)*)/);
					
					if (qMatch) {
						question = qMatch[1].trim();
					}
					if (aMatch) {
						answer = aMatch[1].trim();
					}
				}
				
				if (question && answer) {
					// Extract confidence and type from text
					const text = pairEl.textContent || '';
					const confidenceMatch = text.match(/⭐\s*([\d.]+)/);
					const typeMatch = text.match(/🏷️\s*(\w+)/);
					
					pairs.push({
						question: question,
						answer: answer,
						confidence: confidenceMatch ? parseFloat(confidenceMatch[1]) : 1.0,
						question_type: typeMatch ? typeMatch[1] : 'manual',
						keywords: []
					});
					console.log('[FP-SEO] getAllQAPairsFromDOM - Extracted pair', index, ':', question.substring(0, 50));
				} else {
					console.warn('[FP-SEO] getAllQAPairsFromDOM - Could not extract Q&A from pair', index, {
						question: question ? question.substring(0, 30) : 'MISSING',
						answer: answer ? answer.substring(0, 30) : 'MISSING',
						html: pairEl.innerHTML.substring(0, 200)
					});
				}
			});
			
			console.log('[FP-SEO] getAllQAPairsFromDOM - Returning', pairs.length, 'pairs');
			return pairs;
		}
		
		// Function to update Q&A list
		function updateQAList(qaPairs) {
			const $list = $('#fp-seo-qa-list');
			
			console.log('[FP-SEO] updateQAList called with', qaPairs ? qaPairs.length : 0, 'pairs');
			
			// Update hidden field
			updateQAHiddenField(qaPairs);
			
			if (!qaPairs || qaPairs.length === 0) {
				$list.html('<p class="description" style="text-align: center; padding: 30px; background: #fafafa; border-radius: 6px;"><?php esc_html_e( 'Nessuna Q&A pair disponibile. Clicca "Genera Q&A Automaticamente" o aggiungine una manualmente.', 'fp-seo-performance' ); ?></p>');
				updateQAHiddenField([]);
				return;
			}
			
			let html = '';
			qaPairs.forEach(function(pair, index) {
				html += '<div class="fp-seo-qa-pair" data-index="' + index + '" style="margin-bottom: 15px; padding: 15px; background: white; border: 1px solid #e5e7eb; border-radius: 6px;">';
				html += '<div style="display: flex; justify-content: space-between; align-items: start;">';
				html += '<div style="flex: 1;">';
				html += '<div style="margin-bottom: 10px;"><strong style="color: #1e40af;">Q:</strong> <span>' + escapeHtml(pair.question || '') + '</span></div>';
				html += '<div style="margin-bottom: 10px;"><strong style="color: #059669;">A:</strong> <span>' + escapeHtml(pair.answer || '') + '</span></div>';
				html += '<div style="font-size: 12px; color: #6b7280;">';
				html += '<span title="Confidence Score">⭐ ' + (pair.confidence ? parseFloat(pair.confidence).toFixed(2) : '1.00') + '</span>';
				html += '<span style="margin-left: 15px;" title="Type">🏷️ ' + (pair.question_type || 'informational') + '</span>';
				if (pair.keywords && pair.keywords.length > 0) {
					html += '<span style="margin-left: 15px;" title="Keywords">🔑 ' + escapeHtml(pair.keywords.slice(0, 3).join(', ')) + '</span>';
				}
				html += '</div>';
				html += '</div>';
				html += '<button type="button" class="button fp-seo-delete-qa" data-index="' + index + '" style="color: #dc2626;">×</button>';
				html += '</div>';
				html += '</div>';
			});
			
			const totalCount = qaPairs.length;
			console.log('[FP-SEO] updateQAList - Total count:', totalCount);
			
			html += '<p style="text-align: center; font-size: 13px; color: #64748b;">';
			html += '<?php echo esc_html__( 'Totale: ', 'fp-seo-performance' ); ?>' + totalCount + ' <?php echo esc_html__( 'Q&A pairs | Endpoint: ', 'fp-seo-performance' ); ?>';
			html += '<a href="<?php echo esc_url( $json_url ); ?>" target="_blank"><?php esc_html_e( 'Visualizza JSON', 'fp-seo-performance' ); ?> →</a>';
			html += '</p>';
			
			$list.html(html);
			
			// Note: Delete handlers are handled by event delegation in render_delete_qa_handler()
			// No need to re-bind handlers here as event delegation works for all elements
		}
		
		// Helper to escape HTML
		function escapeHtml(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
		}
		<?php
	}

	/**
	 * Render delete Q&A handler.
	 *
	 * @return void
	 */
	private function render_delete_qa_handler(): void {
		?>
		// Delete Q&A handler for existing pairs (loaded from server)
		// This handler is used for Q&A pairs that are already in the DOM when the page loads
		// Note: Q&A pairs added/modified after page load are handled by updateQAList() function
		$(document).on('click', '.fp-seo-delete-qa', function() {
			if ( confirm('Eliminare questa Q&A pair?') ) {
				const $pair = $(this).closest('.fp-seo-qa-pair');
				$pair.fadeOut(300, function() {
					$pair.remove();
					// CRITICAL: Update hidden field after deletion
					// This ensures the deleted Q&A is not saved when the form is submitted
					const allPairs = getAllQAPairsFromDOM();
					updateQAHiddenField(allPairs);
					console.log('[FP-SEO] Delete handler - Updated hidden field with', allPairs ? allPairs.length : 0, 'Q&A pairs after deletion');
				});
			}
		});
		<?php
	}

	/**
	 * Render add manual Q&A handler.
	 *
	 * @return void
	 */
	private function render_add_manual_qa_handler(): void {
		?>
		// Add manual Q&A
		$('#fp-seo-add-manual-qa').on('click', function() {
			const question = $('#fp-seo-manual-question').val().trim();
			const answer = $('#fp-seo-manual-answer').val().trim();

			if (!question || !answer) {
				alert('Compila sia domanda che risposta.');
				return;
			}

			// Get existing pairs from hidden field (more reliable than DOM parsing)
			const $hiddenField = $('#fp-seo-qa-pairs-data');
			let existingPairs = [];
			if ($hiddenField.length && $hiddenField.val()) {
				try {
					existingPairs = JSON.parse($hiddenField.val());
					if (!Array.isArray(existingPairs)) {
						existingPairs = [];
					}
				} catch (e) {
					console.error('[FP-SEO] Error parsing existing Q&A pairs:', e);
					existingPairs = [];
				}
			}
			
			// Add new pair
			existingPairs.push({
				question: question,
				answer: answer,
				confidence: 1.0,
				question_type: 'manual',
				keywords: []
			});
			
			// Update list and hidden field
			updateQAList(existingPairs);

			// Clear inputs
			$('#fp-seo-manual-question').val('');
			$('#fp-seo-manual-answer').val('');

			alert('✅ Q&A aggiunta! Salva il post per confermare.');
		});
		<?php
	}
}
















