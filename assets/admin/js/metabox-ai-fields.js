/**
 * AI Field Generator for SEO Metabox
 *
 * Handles AI-powered field generation for SEO Title, Meta Description, and Slug.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

(function($) {
	'use strict';

	// Wait for DOM and jQuery to be ready
	var initAttempts = 0;
	var maxAttempts = 50; // Max 5 seconds (50 * 100ms)
	
	function initAiFieldGenerator() {
		initAttempts++;
		
		// Check if jQuery is available
		if (typeof jQuery === 'undefined') {
			if (initAttempts < maxAttempts) {
				setTimeout(initAiFieldGenerator, 100);
				return;
			}
			console.error('FP SEO: jQuery not available after', initAttempts, 'attempts');
			return;
		}
		
		// Use $ as jQuery
		var $ = jQuery;
		
		// Ensure DOM is ready
		$(function() {
			// Get ajaxurl from WordPress localized script or use default
			var ajaxUrl = typeof fpSeoPerformanceMetabox !== 'undefined' ? fpSeoPerformanceMetabox.ajaxUrl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
			
			if (!ajaxUrl) {
				console.error('FP SEO: ajaxurl not available');
				return;
			}

			// Get AI configuration from localized script
			var aiEnabled = typeof fpSeoPerformanceMetabox !== 'undefined' ? fpSeoPerformanceMetabox.aiEnabled : false;
			var apiKeyPresent = typeof fpSeoPerformanceMetabox !== 'undefined' ? fpSeoPerformanceMetabox.apiKeyPresent : false;

			// Helper function to get editor content (Classic or Gutenberg)
			function getEditorContent() {
				// Try Classic Editor first
				if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
					return tinyMCE.activeEditor.getContent();
				}
				
				// Try textarea (when in Text mode)
				const $textarea = $('#content');
				if ($textarea.length) {
					return $textarea.val();
				}
				
				// Try Gutenberg
				if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
					const editor = wp.data.select('core/editor');
					if (editor && typeof editor.getEditedPostContent === 'function') {
						return editor.getEditedPostContent();
					}
				}
				
				return '';
			}

			// Helper function to get post title
			function getPostTitle() {
				// Try Classic Editor
				const $title = $('#title');
				if ($title.length) {
					return $title.val();
				}
				
				// Try Gutenberg
				if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
					const editor = wp.data.select('core/editor');
					if (editor && typeof editor.getEditedPostAttribute === 'function') {
						return editor.getEditedPostAttribute('title');
					}
				}
				
				return '';
			}

			// Helper function to show error near button
			function showFieldError($btn, message) {
				const $parent = $btn.closest('div[style*="flex"]');
				if (!$parent.length) return;
				
				$parent.css('position', 'relative');
				
				const $error = $('<div class="fp-seo-ai-error" style="position: absolute; top: 100%; left: 0; right: 0; margin-top: 8px; padding: 10px 14px; background: #fee2e2; border: 2px solid #ef4444; border-radius: 8px; font-size: 12px; color: #dc2626; z-index: 100; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.1);"></div>');
				$error.html('<strong>⚠️ Errore:</strong> ' + message);
				
				// Remove any existing error
				$parent.find('.fp-seo-ai-error').remove();
				$parent.append($error);
				
				setTimeout(function() {
					$error.fadeOut(function() {
						$(this).remove();
					});
				}, 8000);
			}

			// Wait a bit more for metabox to be fully rendered
			setTimeout(function() {
				// Check if buttons exist
				const $buttons = $('.fp-seo-ai-generate-field-btn');
				if ($buttons.length === 0) {
					if (initAttempts < maxAttempts) {
						console.warn('FP SEO: AI buttons not found, retrying... (attempt', initAttempts, ')');
						setTimeout(initAiFieldGenerator, 200);
						return;
					}
					console.error('FP SEO: AI buttons not found after', initAttempts, 'attempts');
					return;
				}
				
				console.log('FP SEO: Found', $buttons.length, 'AI buttons');
		
				// Remove any existing handlers to prevent duplicates
				$(document).off('click', '.fp-seo-ai-generate-field-btn');
				
				// Handle click on AI field generation buttons using event delegation
				$(document).on('click', '.fp-seo-ai-generate-field-btn', function(e) {
					e.preventDefault();
					e.stopPropagation();
					
					console.log('FP SEO: AI button clicked', this);
			
					const $btn = $(this);
					const field = $btn.data('field');
					const targetId = $btn.data('target-id');
					const postId = $btn.data('post-id');
					const nonce = $btn.data('nonce');
					
					console.log('FP SEO: Button data', { field, targetId, postId, nonce: nonce ? 'present' : 'missing' });
					
					// Validation
					if (!field || !targetId || !postId || !nonce) {
						alert('Configurazione non valida. Verifica che il plugin sia configurato correttamente.');
						console.error('FP SEO: Invalid button configuration', { field, targetId, postId, nonce: !!nonce });
						return;
					}

					// Check if AI is enabled and API key is configured
					if (!aiEnabled || !apiKeyPresent) {
						alert('AI non configurata. Vai in Impostazioni > FP SEO Performance > AI per configurare la chiave API.');
						return;
					}

					// Get content and title
					const content = getEditorContent();
					const title = getPostTitle();
					
					if (!content || !title) {
						alert('Contenuto o titolo mancante. Assicurati di aver scritto del contenuto prima di generare.');
						return;
					}

					// Disable button and show loading
					$btn.prop('disabled', true);
					const originalHtml = $btn.html();
					$btn.html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear; margin: 0;"></span>');

					// Call AJAX
					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'fp_seo_generate_ai_content',
							nonce: nonce,
							post_id: postId,
							content: content,
							title: title,
							focus_keyword: '',
						},
						success: function(response) {
							if (response.success && response.data) {
								// Fill the specific field
								const $target = $('#' + targetId);
								
								if ($target.length) {
									let value = '';
									switch(field) {
										case 'seo_title':
											value = response.data.seo_title || '';
											break;
										case 'meta_description':
											value = response.data.meta_description || '';
											break;
										case 'slug':
											value = response.data.slug || '';
											break;
									}
									
									if (value) {
										$target.val(value).trigger('input');
										
										// Highlight with animation
										$target.css({
											'background': '#f0fdf4',
											'border-color': '#10b981',
											'transition': 'all 0.3s ease'
										});
										
										setTimeout(function() {
											$target.css({
												'background': '#fff',
												'transition': 'all 0.5s ease'
											});
										}, 2000);
										
										// Show success checkmark
										const $success = $('<span class="fp-seo-ai-success" style="margin-left: 8px; color: #10b981; font-size: 18px; animation: fadeIn 0.3s ease;">✓</span>');
										$btn.after($success);
										setTimeout(function() {
											$success.fadeOut(function() { $(this).remove(); });
										}, 3000);
									}
								}
							} else {
								const errorMsg = response.data?.message || 'Errore durante la generazione';
								showFieldError($btn, errorMsg);
							}
						},
						error: function(xhr, status, error) {
							console.error('AI Field Generation Error:', error);
							
							let errorMessage = 'Errore di connessione. Riprova più tardi.';
							
							if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
								errorMessage = xhr.responseJSON.data.message;
							} else if (xhr.statusText) {
								errorMessage = 'Errore del server (' + xhr.status + '): ' + xhr.statusText;
							}
							
							showFieldError($btn, errorMessage);
						},
						complete: function() {
							// Restore button
							$btn.prop('disabled', false);
							$btn.html(originalHtml);
						}
					});
				});

				// Add rotation animation
				if (!document.getElementById('fp-seo-ai-field-animations')) {
					const style = document.createElement('style');
					style.id = 'fp-seo-ai-field-animations';
					style.textContent = `
						@keyframes rotation {
							from { transform: rotate(0deg); }
							to { transform: rotate(360deg); }
						}
						@keyframes fadeIn {
							from { opacity: 0; transform: scale(0.5); }
							to { opacity: 1; transform: scale(1); }
						}
					`;
					document.head.appendChild(style);
				}
				
				console.log('FP SEO: AI Field Generator initialized successfully');
				console.log('FP SEO: AJAX URL =', ajaxUrl);
				
				// Test if buttons are clickable
				$('.fp-seo-ai-generate-field-btn').each(function() {
					const $btn = $(this);
					console.log('FP SEO: Button found', {
						field: $btn.data('field'),
						targetId: $btn.data('target-id'),
						visible: $btn.is(':visible'),
						enabled: !$btn.prop('disabled'),
						clickable: $btn.css('pointer-events') !== 'none',
						hasNonce: !!$btn.data('nonce'),
						hasPostId: !!$btn.data('post-id')
					});
				});
			}, 100); // Small delay to ensure metabox is rendered
		});
	}

	// Start initialization - wait for jQuery to be loaded first
	if (typeof jQuery === 'undefined') {
		// jQuery not loaded yet, wait for it
		var checkJQuery = setInterval(function() {
			if (typeof jQuery !== 'undefined') {
				clearInterval(checkJQuery);
				// Now wait for DOM to be ready
				jQuery(document).ready(initAiFieldGenerator);
			}
		}, 50);
		
		// Safety timeout
		setTimeout(function() {
			clearInterval(checkJQuery);
			if (typeof jQuery !== 'undefined') {
				jQuery(document).ready(initAiFieldGenerator);
			} else {
				console.error('FP SEO: jQuery not loaded after 5 seconds');
			}
		}, 5000);
	} else {
		// jQuery is available, wait for DOM
		jQuery(document).ready(initAiFieldGenerator);
	}
	
	// Debug log
	console.log('FP SEO: AI Field Generator script loaded');
})(typeof jQuery !== 'undefined' ? jQuery : null);

// Ensure SEO fields are always included in POST, even if empty
// This fixes the issue where empty fields are not sent in the form submission
// Register this handler separately to ensure it's always executed
(function($) {
	if (typeof $ === 'undefined') {
		// Wait for jQuery
		var checkJQuery = setInterval(function() {
			if (typeof jQuery !== 'undefined') {
				clearInterval(checkJQuery);
				registerSubmitHandler(jQuery);
			}
		}, 100);
		
		setTimeout(function() {
			clearInterval(checkJQuery);
			if (typeof jQuery !== 'undefined') {
				registerSubmitHandler(jQuery);
			}
		}, 5000);
	} else {
		registerSubmitHandler($);
	}
	
	function registerSubmitHandler($) {
		// Use document ready to ensure DOM is loaded
		$(document).ready(function() {
			var $titleField = $('#fp-seo-title');
			var $descField = $('#fp-seo-meta-description');
			var $form = $('#post');
			
			if (!$form.length) {
				console.error('FP SEO: Form #post not found');
				return;
			}
			
			// Get Focus Keyword and Secondary Keywords fields
			var $focusKeywordField = $('#fp-seo-focus-keyword');
			var $secondaryKeywordsField = $('#fp-seo-secondary-keywords');
			
			// Add real-time listeners to update hidden fields when visible fields change
			if ($titleField.length) {
				$titleField.on('input change', function() {
					var val = $(this).val() || '';
					var $hidden = $form.find('#fp-seo-title-hidden-backup');
					if ($hidden.length) {
						$hidden.val(val).attr('value', val);
					} else {
						// Create if doesn't exist
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_title',
							value: val,
							id: 'fp-seo-title-hidden-backup'
						}));
					}
					// Ensure sent flag
					if (!$form.find('#fp-seo-title-sent').length) {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_title_sent',
							value: '1',
							id: 'fp-seo-title-sent'
						}));
					}
				});
			}
			
			if ($descField.length) {
				$descField.on('input change', function() {
					var val = $(this).val() || '';
					var $hidden = $form.find('#fp-seo-meta-description-hidden-backup');
					if ($hidden.length) {
						$hidden.val(val).attr('value', val);
					} else {
						// Create if doesn't exist
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_meta_description',
							value: val,
							id: 'fp-seo-meta-description-hidden-backup'
						}));
					}
					// Ensure sent flag
					if (!$form.find('#fp-seo-meta-description-sent').length) {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_meta_description_sent',
							value: '1',
							id: 'fp-seo-meta-description-sent'
						}));
					}
				});
			}
			
			if ($focusKeywordField.length) {
				$focusKeywordField.on('input change', function() {
					var val = $(this).val() || '';
					var $hidden = $form.find('#fp-seo-focus-keyword-hidden-backup');
					if ($hidden.length) {
						$hidden.val(val).attr('value', val);
					} else {
						// Create if doesn't exist
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_focus_keyword',
							value: val,
							id: 'fp-seo-focus-keyword-hidden-backup'
						}));
					}
				});
			}
			
			if ($secondaryKeywordsField.length) {
				$secondaryKeywordsField.on('input change', function() {
					var val = $(this).val() || '';
					var $hidden = $form.find('#fp-seo-secondary-keywords-hidden-backup');
					if ($hidden.length) {
						$hidden.val(val).attr('value', val);
					} else {
						// Create if doesn't exist
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_secondary_keywords',
							value: val,
							id: 'fp-seo-secondary-keywords-hidden-backup'
						}));
					}
				});
			}
			
			// CRITICAL: Always ensure metabox present flag exists
			if (!$form.find('input[name="fp_seo_performance_metabox_present"]').length) {
				$form.append($('<input>', {
					type: 'hidden',
					name: 'fp_seo_performance_metabox_present',
					value: '1'
				}));
			}
			
			// Ensure fields are always present in the form, even if empty
			// This is critical for WordPress to include them in POST
			function ensureFieldsInForm() {
				// ALWAYS get fresh values from visible fields
				var titleValue = $titleField.length ? ($titleField.val() || '') : '';
				var descValue = $descField.length ? ($descField.val() || '') : '';
				
				// Get Focus Keyword and Secondary Keywords - ALWAYS get fresh values
				var $focusKeywordField = $('#fp-seo-focus-keyword');
				var $secondaryKeywordsField = $('#fp-seo-secondary-keywords');
				var focusKeywordValue = $focusKeywordField.length ? ($focusKeywordField.val() || '') : '';
				var secondaryKeywordsValue = $secondaryKeywordsField.length ? ($secondaryKeywordsField.val() || '') : '';
				
				// Remove any existing hidden duplicates (keep only the original visible fields)
				$form.find('input[name="fp_seo_title"][type="hidden"]').not($titleField).remove();
				$form.find('input[name="fp_seo_meta_description"][type="hidden"]').not($descField).remove();
				
				// Always ensure the visible fields have their values set
				// This is important because WordPress reads from the visible fields
				if ($titleField.length) {
					// Force update the value attribute to ensure it's in POST
					$titleField.attr('value', titleValue);
					$titleField.val(titleValue);
					
					// ALWAYS update or create hidden backup to ensure field is in POST
					// WordPress sometimes doesn't send fields from metaboxes if they're not recognized
					var $hiddenTitle = $form.find('#fp-seo-title-hidden-backup');
					if ($hiddenTitle.length) {
						$hiddenTitle.val(titleValue);
						$hiddenTitle.attr('value', titleValue);
					} else {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_title',
							value: titleValue,
							id: 'fp-seo-title-hidden-backup'
						}));
					}
					
					// Add sent flag to indicate field was explicitly sent
					if (!$form.find('#fp-seo-title-sent').length) {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_title_sent',
							value: '1',
							id: 'fp-seo-title-sent'
						}));
					}
				}
				
				if ($descField.length) {
					// Force update the value for textarea
					$descField.val(descValue);
					
					// ALWAYS update or create hidden backup to ensure field is in POST
					// WordPress sometimes doesn't send textareas from metaboxes if they're not recognized
					var $hiddenDesc = $form.find('#fp-seo-meta-description-hidden-backup');
					if ($hiddenDesc.length) {
						$hiddenDesc.val(descValue);
						$hiddenDesc.attr('value', descValue);
					} else {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_meta_description',
							value: descValue,
							id: 'fp-seo-meta-description-hidden-backup'
						}));
					}
					
					// Add sent flag to indicate field was explicitly sent
					if (!$form.find('#fp-seo-meta-description-sent').length) {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_meta_description_sent',
							value: '1',
							id: 'fp-seo-meta-description-sent'
						}));
					}
				}
				
				// Ensure Focus Keyword is in form - ALWAYS update value
				if ($focusKeywordField.length) {
					// Update visible field value attribute
					$focusKeywordField.attr('value', focusKeywordValue);
					$focusKeywordField.val(focusKeywordValue);
					
					// ALWAYS update or create hidden backup for Focus Keyword
					var $hiddenFocusKeyword = $form.find('#fp-seo-focus-keyword-hidden-backup');
					if ($hiddenFocusKeyword.length) {
						$hiddenFocusKeyword.val(focusKeywordValue);
						$hiddenFocusKeyword.attr('value', focusKeywordValue);
					} else {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_focus_keyword',
							value: focusKeywordValue,
							id: 'fp-seo-focus-keyword-hidden-backup'
						}));
					}
				}
				
				// Ensure Secondary Keywords is in form - ALWAYS update value
				if ($secondaryKeywordsField.length) {
					// Update visible field
					$secondaryKeywordsField.val(secondaryKeywordsValue);
					
					// ALWAYS update or create hidden backup for Secondary Keywords
					var $hiddenSecondaryKeywords = $form.find('#fp-seo-secondary-keywords-hidden-backup');
					if ($hiddenSecondaryKeywords.length) {
						$hiddenSecondaryKeywords.val(secondaryKeywordsValue);
						$hiddenSecondaryKeywords.attr('value', secondaryKeywordsValue);
					} else {
						$form.append($('<input>', {
							type: 'hidden',
							name: 'fp_seo_secondary_keywords',
							value: secondaryKeywordsValue,
							id: 'fp-seo-secondary-keywords-hidden-backup'
						}));
					}
				}
				
				// CRITICAL: Ensure metabox present flag is ALWAYS set
				if (!$form.find('input[name="fp_seo_performance_metabox_present"]').length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_performance_metabox_present',
						value: '1'
					}));
				}
				
				console.log('FP SEO: Fields ensured in form', {
					title: titleValue ? titleValue.substring(0, 30) + '...' : '(empty)',
					description: descValue ? descValue.substring(0, 30) + '...' : '(empty)',
					focusKeyword: focusKeywordValue ? focusKeywordValue.substring(0, 30) + '...' : '(empty)',
					secondaryKeywords: secondaryKeywordsValue ? secondaryKeywordsValue.substring(0, 30) + '...' : '(empty)',
					titleFieldExists: $titleField.length > 0,
					descFieldExists: $descField.length > 0,
					focusKeywordExists: $focusKeywordField.length > 0,
					secondaryKeywordsExists: $secondaryKeywordsField.length > 0,
					hiddenTitleBackup: $form.find('#fp-seo-title-hidden-backup').length > 0,
					hiddenDescBackup: $form.find('#fp-seo-meta-description-hidden-backup').length > 0,
					metaboxPresent: $form.find('input[name="fp_seo_performance_metabox_present"]').length > 0
				});
			}
			
			// Intercept form submit early to ensure fields are present
			// Use capture phase to run before any other handlers
			$form.on('submit', function(e) {
				console.log('FP SEO: Form submit intercepted - ensuring all fields are present');
				
				// Run synchronously before form submits
				ensureFieldsInForm();
				
				// RIMOSSO: Salvataggio AJAX sincrono - async: false è deprecato e causa errori
				// Gli hook WordPress con multiple priorità dovrebbero essere sufficienti
				// I campi sono già garantiti nel form tramite ensureFieldsInForm()
				
				// Force ensure all fields one more time
				var titleValue = $titleField.length ? ($titleField.val() || '') : '';
				var descValue = $descField.length ? ($descField.val() || '') : '';
				var $focusKeywordField = $('#fp-seo-focus-keyword');
				var $secondaryKeywordsField = $('#fp-seo-secondary-keywords');
				var focusKeywordValue = $focusKeywordField.length ? ($focusKeywordField.val() || '') : '';
				var secondaryKeywordsValue = $secondaryKeywordsField.length ? ($secondaryKeywordsField.val() || '') : '';
				
				// Log what we're about to submit
				console.log('FP SEO: About to submit form with values:', {
					title: titleValue,
					desc: descValue,
					focusKeyword: focusKeywordValue,
					secondaryKeywords: secondaryKeywordsValue,
					metaboxPresent: $form.find('input[name="fp_seo_performance_metabox_present"]').length > 0,
					hiddenTitle: $form.find('#fp-seo-title-hidden-backup').val(),
					hiddenDesc: $form.find('#fp-seo-meta-description-hidden-backup').val(),
					hiddenKeyword: $form.find('#fp-seo-focus-keyword-hidden-backup').val()
				});
				
				// CRITICAL: Force update all hidden fields one last time
				var $hiddenTitle = $form.find('#fp-seo-title-hidden-backup');
				if ($hiddenTitle.length) {
					$hiddenTitle.val(titleValue).attr('value', titleValue);
				} else if ($titleField.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_title',
						value: titleValue,
						id: 'fp-seo-title-hidden-backup'
					}));
				}
				
				var $hiddenDesc = $form.find('#fp-seo-meta-description-hidden-backup');
				if ($hiddenDesc.length) {
					$hiddenDesc.val(descValue).attr('value', descValue);
				} else if ($descField.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_meta_description',
						value: descValue,
						id: 'fp-seo-meta-description-hidden-backup'
					}));
				}
				
				var $hiddenKeyword = $form.find('#fp-seo-focus-keyword-hidden-backup');
				if ($hiddenKeyword.length) {
					$hiddenKeyword.val(focusKeywordValue).attr('value', focusKeywordValue);
				} else if ($focusKeywordField.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_focus_keyword',
						value: focusKeywordValue,
						id: 'fp-seo-focus-keyword-hidden-backup'
					}));
				}
				
				// CRITICAL: Always ensure metabox present flag
				var $metaboxPresent = $form.find('input[name="fp_seo_performance_metabox_present"]');
				if (!$metaboxPresent.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_performance_metabox_present',
						value: '1'
					}));
				}
				
				// Ensure sent flags
				if (!$form.find('#fp-seo-title-sent').length && $titleField.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_title_sent',
						value: '1',
						id: 'fp-seo-title-sent'
					}));
				}
				
				if (!$form.find('#fp-seo-meta-description-sent').length && $descField.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_meta_description_sent',
						value: '1',
						id: 'fp-seo-meta-description-sent'
					}));
				}
				
				// Final verification
				console.log('FP SEO: Final verification before submit:', {
					hiddenTitleExists: $form.find('#fp-seo-title-hidden-backup').length > 0,
					hiddenTitleValue: $form.find('#fp-seo-title-hidden-backup').val(),
					hiddenDescExists: $form.find('#fp-seo-meta-description-hidden-backup').length > 0,
					hiddenDescValue: $form.find('#fp-seo-meta-description-hidden-backup').val(),
					hiddenKeywordExists: $form.find('#fp-seo-focus-keyword-hidden-backup').length > 0,
					hiddenKeywordValue: $form.find('#fp-seo-focus-keyword-hidden-backup').val(),
					metaboxPresent: $form.find('input[name="fp_seo_performance_metabox_present"]').length > 0
				});
				
				// Don't prevent default - let form submit normally
				// The fields are now guaranteed to be in the form
				
				// Force ensure Focus Keyword and Secondary Keywords are in form
				var $focusKeywordField = $('#fp-seo-focus-keyword');
				var $secondaryKeywordsField = $('#fp-seo-secondary-keywords');
				var focusKeywordValue = $focusKeywordField.length ? ($focusKeywordField.val() || '') : '';
				var secondaryKeywordsValue = $secondaryKeywordsField.length ? ($secondaryKeywordsField.val() || '') : '';
				
				// Ensure Focus Keyword hidden backup
				var $hiddenFocusKeyword = $form.find('#fp-seo-focus-keyword-hidden-backup');
				if ($hiddenFocusKeyword.length) {
					$hiddenFocusKeyword.val(focusKeywordValue);
				} else if ($focusKeywordField.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_focus_keyword',
						value: focusKeywordValue,
						id: 'fp-seo-focus-keyword-hidden-backup'
					}));
				}
				
				// Ensure Secondary Keywords hidden backup
				var $hiddenSecondaryKeywords = $form.find('#fp-seo-secondary-keywords-hidden-backup');
				if ($hiddenSecondaryKeywords.length) {
					$hiddenSecondaryKeywords.val(secondaryKeywordsValue);
				} else if ($secondaryKeywordsField.length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_secondary_keywords',
						value: secondaryKeywordsValue,
						id: 'fp-seo-secondary-keywords-hidden-backup'
					}));
				}
				
				// CRITICAL: Always ensure metabox present flag
				if (!$form.find('input[name="fp_seo_performance_metabox_present"]').length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_performance_metabox_present',
						value: '1'
					}));
				}
				
				// Double-check fields are in form before submit
				var titleValue = $titleField.length ? ($titleField.val() || '') : '';
				var descValue = $descField.length ? ($descField.val() || '') : '';
				
				// Force add hidden fields if not present
				if (!$form.find('#fp-seo-title-hidden-backup').length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_title',
						value: titleValue,
						id: 'fp-seo-title-hidden-backup'
					}));
				} else {
					$form.find('#fp-seo-title-hidden-backup').val(titleValue);
				}
				
				// Ensure sent flag exists
				if (!$form.find('#fp-seo-title-sent').length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_title_sent',
						value: '1',
						id: 'fp-seo-title-sent'
					}));
				}
				
				if (!$form.find('#fp-seo-meta-description-hidden-backup').length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_meta_description',
						value: descValue,
						id: 'fp-seo-meta-description-hidden-backup'
					}));
				} else {
					$form.find('#fp-seo-meta-description-hidden-backup').val(descValue);
				}
				
				// Ensure sent flag exists
				if (!$form.find('#fp-seo-meta-description-sent').length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_meta_description_sent',
						value: '1',
						id: 'fp-seo-meta-description-sent'
					}));
				}
				
				// Add sent flag to indicate field was explicitly sent
				if (!$form.find('#fp-seo-meta-description-sent').length) {
					$form.append($('<input>', {
						type: 'hidden',
						name: 'fp_seo_meta_description_sent',
						value: '1',
						id: 'fp-seo-meta-description-sent'
					}));
				}
				
				// CRITICAL: Ensure metabox present flag is ALWAYS set
				$form.find('input[name="fp_seo_performance_metabox_present"]').remove();
				$form.append($('<input>', {
					type: 'hidden',
					name: 'fp_seo_performance_metabox_present',
					value: '1'
				}));
				
				console.log('FP SEO: Fields verified before submit', {
					title: titleValue ? titleValue.substring(0, 30) + '...' : '(empty)',
					description: descValue ? descValue.substring(0, 30) + '...' : '(empty)',
					hiddenTitleExists: $form.find('#fp-seo-title-hidden-backup').length > 0,
					hiddenDescExists: $form.find('#fp-seo-meta-description-hidden-backup').length > 0,
					metaboxPresent: $form.find('input[name="fp_seo_performance_metabox_present"]').length > 0
				});
				
				// Don't prevent default - let form submit normally
			});
			
			// Also ensure fields when clicking publish/save buttons (before submit)
			// Use multiple events to catch all cases
			$(document).on('mousedown', '#publish, #save-post, #save-post-ajax, input[name="save"], input[name="publish"]', function() {
				console.log('FP SEO: Button clicked, ensuring fields');
				// Run immediately on mousedown (before click/submit)
				ensureFieldsInForm();
			});
			
			// Also intercept click events
			$(document).on('click', '#publish, #save-post, #save-post-ajax, input[name="save"], input[name="publish"]', function(e) {
				console.log('FP SEO: Button click intercepted');
				// Small delay to ensure fields are set before form serialization
				setTimeout(function() {
					ensureFieldsInForm();
				}, 10);
			});
			
			// Ensure fields are present on page load (in case of autosave)
			ensureFieldsInForm();
			
			// Also ensure fields periodically (for autosave)
			setInterval(ensureFieldsInForm, 5000);
			
			// GUTENBERG SUPPORT: Intercetta il salvataggio di Gutenberg
			if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
				console.log('FP SEO: Gutenberg detected, registering save interceptor');
				
				// Intercetta il salvataggio di Gutenberg
				var unsubscribe = wp.data.subscribe(function() {
					var editor = wp.data.select('core/editor');
					if (!editor) return;
					
					var isSaving = editor.isSavingPost();
					var isAutosaving = editor.isAutosavingPost();
					
					// Quando inizia il salvataggio, assicura che i campi siano nel form
					if (isSaving && !isAutosaving) {
						console.log('FP SEO: Gutenberg save detected, ensuring fields');
						ensureFieldsInForm();
						
						// Aggiungi i campi SEO ai meta fields di Gutenberg
						var currentMeta = editor.getEditedPostAttribute('meta') || {};
						var titleValue = $titleField.length ? ($titleField.val() || '') : '';
						var descValue = $descField.length ? ($descField.val() || '') : '';
						
						if (titleValue || descValue) {
							var newMeta = Object.assign({}, currentMeta);
							if (titleValue) {
								newMeta._fp_seo_title = titleValue;
							}
							if (descValue) {
								newMeta._fp_seo_meta_description = descValue;
							}
							
							// Aggiorna i meta fields in Gutenberg
							wp.data.dispatch('core/editor').editPost({
								meta: newMeta
							});
							
							console.log('FP SEO: Gutenberg meta fields updated', {
								title: titleValue ? titleValue.substring(0, 30) + '...' : '(empty)',
								description: descValue ? descValue.substring(0, 30) + '...' : '(empty)'
							});
						}
					}
				});
				
				// Salva unsubscribe per cleanup (opzionale)
				if (typeof window.fpSeoGutenbergUnsubscribe === 'undefined') {
					window.fpSeoGutenbergUnsubscribe = unsubscribe;
				}
			}
			
			console.log('FP SEO: Form submit handler registered', {
				titleFieldFound: $titleField.length > 0,
				descFieldFound: $descField.length > 0,
				formFound: $form.length > 0,
				gutenbergDetected: typeof wp !== 'undefined' && typeof wp.data !== 'undefined'
			});
		});
	}
})(typeof jQuery !== 'undefined' ? jQuery : null);

