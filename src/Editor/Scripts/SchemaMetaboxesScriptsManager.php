<?php
/**
 * Manages scripts for the Schema Metaboxes (FAQ and HowTo).
 *
 * @package FP\SEO\Editor\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Scripts;

use function absint;
use function esc_js;
use function get_current_screen;
use function wp_create_nonce;

/**
 * Manages scripts for the Schema Metaboxes.
 */
class SchemaMetaboxesScriptsManager {
	/**
	 * @var int|null
	 */
	private $post_id;

	/**
	 * Set post ID for script generation.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function set_post_id( int $post_id ): void {
		$this->post_id = $post_id;
	}

	/**
	 * Get inline JavaScript.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_inline_js( int $post_id = 0 ): string {
		$post_id = $post_id > 0 ? absint( $post_id ) : 0;
		
		ob_start();
		$this->render_scripts( $post_id );
		return ob_get_clean();
	}

	/**
	 * Render all scripts.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_scripts( int $post_id ): void {
		?>
		jQuery(document).ready(function($) {
			<?php $this->render_faq_scripts( $post_id ); ?>
			<?php $this->render_howto_scripts( $post_id ); ?>
		});
		<?php
	}

	/**
	 * Render FAQ scripts.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_faq_scripts( int $post_id ): void {
		?>
		// FAQ Management
		var faqIndex = $('.fp-seo-faq-item').length;

		// Generate FAQ with AI
		$('#fp-seo-generate-faq-ai').on('click', function() {
			var $btn = $(this);
			var postId = <?php echo esc_js( (string) $post_id ); ?>;
			
			// Prevent multiple clicks
			if ($btn.prop('disabled')) {
				return;
			}
			
			// Show loading state
			if (typeof FPSeoUI !== 'undefined') {
				FPSeoUI.showLoading($btn, 'Generando FAQ con AI...');
			} else {
				$btn.prop('disabled', true).text('Generando...');
			}
			
			// Safety timeout
			var safetyTimeout = setTimeout(function() {
				if (typeof FPSeoUI !== 'undefined') {
					FPSeoUI.hideLoading($btn);
				} else {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
				}
				if (typeof FPSeoUI !== 'undefined') {
					FPSeoUI.showNotification('Timeout. Riprova.', 'error');
				}
			}, 30000);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				timeout: 25000,
				data: {
					action: 'fp_seo_generate_faq',
					post_id: postId,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_ai_first' ) ); ?>'
				},
				success: function(response) {
					clearTimeout(safetyTimeout);
					
					if (typeof FPSeoUI !== 'undefined') {
						FPSeoUI.hideLoading($btn);
					} else {
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
					}
					
					if (response && response.success && response.data && response.data.faq_questions) {
						// Clear existing FAQs
						$('#fp-seo-faq-list').empty();
						faqIndex = 0;
						
						// Add generated FAQs
						var template = $('#fp-seo-faq-template').html();
						response.data.faq_questions.forEach(function(faq) {
							var newItem = template.replace(/__INDEX__/g, faqIndex);
							var $newItem = $(newItem);
							$newItem.find('input[name*="[question]"').val(faq.question || '');
							$newItem.find('textarea[name*="[answer]"').val(faq.answer || '');
							$('#fp-seo-faq-list').append($newItem);
							faqIndex++;
						});
						
						updateFaqNumbers();
						
						// Initialize character counts
						$('.fp-seo-faq-item textarea').each(function() {
							var count = $(this).val().length;
							$(this).closest('.fp-seo-form-group').find('.fp-seo-char-count').text(count);
						});
						
						if (typeof FPSeoUI !== 'undefined') {
							FPSeoUI.showNotification('Generate ' + response.data.total + ' FAQ con successo!', 'success');
						} else {
							alert('Generate ' + response.data.total + ' FAQ con successo!');
						}
					} else {
						var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Errore durante la generazione delle FAQ';
						if (typeof FPSeoUI !== 'undefined') {
							FPSeoUI.showNotification(errorMsg, 'error');
						} else {
							alert(errorMsg);
						}
					}
				},
				error: function(xhr, status, error) {
					clearTimeout(safetyTimeout);
					
					if (typeof FPSeoUI !== 'undefined') {
						FPSeoUI.hideLoading($btn);
					} else {
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
					}
					
					var errorMsg = 'Errore durante la generazione delle FAQ. Riprova.';
					if (status === 'timeout') {
						errorMsg = 'Timeout. Riprova.';
					} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}
					
					if (typeof FPSeoUI !== 'undefined') {
						FPSeoUI.showNotification(errorMsg, 'error');
					} else {
						alert(errorMsg);
					}
				},
				complete: function() {
					// Always ensure button is restored
					clearTimeout(safetyTimeout);
					setTimeout(function() {
						if (typeof FPSeoUI !== 'undefined') {
							FPSeoUI.hideLoading($btn);
						} else {
							$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
						}
					}, 100);
				}
			});
		});

		// Add FAQ
		$('.fp-seo-add-faq').on('click', function() {
			var template = $('#fp-seo-faq-template').html();
			var newItem = template.replace(/__INDEX__/g, faqIndex);
			$('#fp-seo-faq-list').append(newItem);
			faqIndex++;
			updateFaqNumbers();
		});

		// Remove FAQ
		$(document).on('click', '.fp-seo-remove-faq', function() {
			if (confirm('Sei sicuro di voler rimuovere questa FAQ?')) {
				$(this).closest('.fp-seo-faq-item').fadeOut(300, function() {
					$(this).remove();
					updateFaqNumbers();
				});
			}
		});

		// Character count
		$(document).on('input', '.fp-seo-faq-item textarea', function() {
			var count = $(this).val().length;
			$(this).closest('.fp-seo-form-group').find('.fp-seo-char-count').text(count);
		});

		function updateFaqNumbers() {
			$('.fp-seo-faq-item').each(function(index) {
				$(this).find('.faq-num').text(index + 1);
				
				// Update input names
				var newIndex = index;
				$(this).find('input, textarea').each(function() {
					var name = $(this).attr('name');
					if (name) {
						var baseName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
						$(this).attr('name', baseName);
					}
				});
			});
		}
		<?php
	}

	/**
	 * Render HowTo scripts.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_howto_scripts( int $post_id ): void {
		?>
		// HowTo Management
		var stepIndex = $('.fp-seo-howto-step').length;

		// Generate HowTo Steps with AI
		$('#fp-seo-generate-howto-ai').on('click', function() {
			var $btn = $(this);
			var postId = <?php echo esc_js( (string) $post_id ); ?>;
			
			// Prevent multiple clicks
			if ($btn.prop('disabled')) {
				return;
			}
			
			// Show loading state
			if (typeof FPSeoUI !== 'undefined') {
				FPSeoUI.showLoading($btn, 'Generando step con AI...');
			} else {
				$btn.prop('disabled', true).text('Generando...');
			}
			
			// Safety timeout
			var safetyTimeout = setTimeout(function() {
				if (typeof FPSeoUI !== 'undefined') {
					FPSeoUI.hideLoading($btn);
				} else {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
				}
				if (typeof FPSeoUI !== 'undefined') {
					FPSeoUI.showNotification('Timeout. Riprova.', 'error');
				}
			}, 30000);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				timeout: 25000,
				data: {
					action: 'fp_seo_generate_howto',
					post_id: postId,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_ai_first' ) ); ?>'
				},
				success: function(response) {
					clearTimeout(safetyTimeout);
					
					if (typeof FPSeoUI !== 'undefined') {
						FPSeoUI.hideLoading($btn);
					} else {
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
					}
					
					if (response && response.success && response.data && response.data.steps) {
						// Clear existing steps
						$('#fp-seo-howto-steps-list').empty();
						stepIndex = 0;
						
						// Add generated steps
						var template = $('#fp-seo-howto-step-template').html();
						response.data.steps.forEach(function(step) {
							var newStep = template.replace(/__INDEX__/g, stepIndex);
							var $newStep = $(newStep);
							$newStep.find('input[name*="[name]"').val(step.name || '');
							$newStep.find('textarea[name*="[text]"').val(step.text || '');
							$newStep.find('input[name*="[url]"').val(step.url || '');
							$('#fp-seo-howto-steps-list').append($newStep);
							stepIndex++;
						});
						
						updateStepNumbers();
						
						if (typeof FPSeoUI !== 'undefined') {
							FPSeoUI.showNotification('Generate ' + response.data.total + ' step con successo!', 'success');
						} else {
							alert('Generate ' + response.data.total + ' step con successo!');
						}
					} else {
						var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Errore durante la generazione degli step';
						if (typeof FPSeoUI !== 'undefined') {
							FPSeoUI.showNotification(errorMsg, 'error');
						} else {
							alert(errorMsg);
						}
					}
				},
				error: function(xhr, status, error) {
					clearTimeout(safetyTimeout);
					
					if (typeof FPSeoUI !== 'undefined') {
						FPSeoUI.hideLoading($btn);
					} else {
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
					}
					
					var errorMsg = 'Errore durante la generazione degli step. Riprova.';
					if (status === 'timeout') {
						errorMsg = 'Timeout. Riprova.';
					} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMsg = xhr.responseJSON.data.message;
					}
					
					if (typeof FPSeoUI !== 'undefined') {
						FPSeoUI.showNotification(errorMsg, 'error');
					} else {
						alert(errorMsg);
					}
				},
				complete: function() {
					// Always ensure button is restored
					clearTimeout(safetyTimeout);
					setTimeout(function() {
						if (typeof FPSeoUI !== 'undefined') {
							FPSeoUI.hideLoading($btn);
						} else {
							$btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Genera con AI');
						}
					}, 100);
				}
			});
		});

		// Add Step
		$('.fp-seo-add-step').on('click', function() {
			var template = $('#fp-seo-howto-step-template').html();
			var newStep = template.replace(/__INDEX__/g, stepIndex);
			$('#fp-seo-howto-steps-list').append(newStep);
			stepIndex++;
			updateStepNumbers();
		});

		// Remove Step
		$(document).on('click', '.fp-seo-remove-step', function() {
			if (confirm('Sei sicuro di voler rimuovere questo step?')) {
				$(this).closest('.fp-seo-howto-step').fadeOut(300, function() {
					$(this).remove();
					updateStepNumbers();
				});
			}
		});

		// Move Step Up
		$(document).on('click', '.fp-seo-move-up', function() {
			var step = $(this).closest('.fp-seo-howto-step');
			var prev = step.prev('.fp-seo-howto-step');
			if (prev.length) {
				step.fadeOut(200, function() {
					step.insertBefore(prev).fadeIn(200);
					updateStepNumbers();
				});
			}
		});

		// Move Step Down
		$(document).on('click', '.fp-seo-move-down', function() {
			var step = $(this).closest('.fp-seo-howto-step');
			var next = step.next('.fp-seo-howto-step');
			if (next.length) {
				step.fadeOut(200, function() {
					step.insertAfter(next).fadeIn(200);
					updateStepNumbers();
				});
			}
		});

		function updateStepNumbers() {
			$('.fp-seo-howto-step').each(function(index) {
				$(this).find('.step-num').text(index + 1);
				
				// Update input names
				var newIndex = index;
				$(this).find('input, textarea').each(function() {
					var name = $(this).attr('name');
					if (name) {
						var baseName = name.replace(/\[\d+\]/, '[' + newIndex + ']');
						$(this).attr('name', baseName);
					}
				});
			});
		}
		<?php
	}
}


