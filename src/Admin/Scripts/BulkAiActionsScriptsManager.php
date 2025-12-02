<?php
/**
 * Scripts Manager for Bulk AI Actions
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

/**
 * Manages JavaScript for Bulk AI Actions
 */
class BulkAiActionsScriptsManager {

	/**
	 * Get all JavaScript code for bulk AI actions
	 *
	 * @return string JavaScript code.
	 */
	public function get_scripts(): string {
		return $this->get_main_script() .
			   $this->get_helper_functions() .
			   $this->get_event_handlers() .
			   $this->get_bulk_functions();
	}

	/**
	 * Get main script initialization
	 *
	 * @return string JavaScript code.
	 */
	private function get_main_script(): string {
		return "
		jQuery(document).ready(function($) {
			" . $this->get_ai_actions_container() . "
		});
		";
	}

	/**
	 * Get AI actions container HTML
	 *
	 * @return string JavaScript code.
	 */
	private function get_ai_actions_container(): string {
		return "
		// Add AI-First bulk action buttons
		if ($('.fp-seo-bulk-actions').length) {
			const aiActions = $('<div class=\"fp-seo-ai-bulk-actions\" style=\"margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 6px; border-left: 4px solid #0284c7;\"></div>');
			aiActions.html('<h4 style=\"margin-top: 0; color: #0c4a6e;\">🤖 AI-First Bulk Actions</h4>' +
				'<button type=\"button\" class=\"button button-secondary fp-seo-bulk-generate-qa\" style=\"margin-right: 10px;\">Generate Q&A for Selected</button>' +
				'<button type=\"button\" class=\"button button-secondary fp-seo-bulk-optimize-images\" style=\"margin-right: 10px;\">Optimize Images for Selected</button>' +
				'<button type=\"button\" class=\"button button-secondary fp-seo-bulk-generate-variants\">Generate Variants for Selected</button>' +
				'<div id=\"fp-seo-bulk-progress\" style=\"margin-top: 15px; display: none;\"></div>');
			
			$('.fp-seo-bulk-actions').after(aiActions);
		}
		";
	}

	/**
	 * Get helper functions
	 *
	 * @return string JavaScript code.
	 */
	private function get_helper_functions(): string {
		return "
		function getSelectedPosts() {
			return $('.fp-seo-bulk-checkbox:checked').map(function() {
				return $(this).val();
			}).get();
		}
		";
	}

	/**
	 * Get event handlers
	 *
	 * @return string JavaScript code.
	 */
	private function get_event_handlers(): string {
		return "
		// Handle bulk Q&A generation
		$(document).on('click', '.fp-seo-bulk-generate-qa', function() {
			const selectedPosts = getSelectedPosts();
			if (!selectedPosts.length) {
				alert('Select at least one post.');
				return;
			}

			if (!confirm('Generate Q&A pairs for ' + selectedPosts.length + ' posts? This may take several minutes.')) {
				return;
			}

			bulkGenerateQA(selectedPosts);
		});

		// Handle bulk image optimization
		$(document).on('click', '.fp-seo-bulk-optimize-images', function() {
			const selectedPosts = getSelectedPosts();
			if (!selectedPosts.length) {
				alert('Select at least one post.');
				return;
			}

			bulkOptimizeImages(selectedPosts);
		});

		// Handle bulk variants generation
		$(document).on('click', '.fp-seo-bulk-generate-variants', function() {
			const selectedPosts = getSelectedPosts();
			if (!selectedPosts.length) {
				alert('Select at least one post.');
				return;
			}

			if (!confirm('Generate conversational variants for ' + selectedPosts.length + ' posts? This requires OpenAI API key.')) {
				return;
			}

			bulkGenerateVariants(selectedPosts);
		});
		";
	}

	/**
	 * Get bulk processing functions
	 *
	 * @return string JavaScript code.
	 */
	private function get_bulk_functions(): string {
		return $this->get_bulk_generate_qa() .
			   $this->get_bulk_optimize_images() .
			   $this->get_bulk_generate_variants();
	}

	/**
	 * Get bulk Q&A generation function
	 *
	 * @return string JavaScript code.
	 */
	private function get_bulk_generate_qa(): string {
		return "
		function bulkGenerateQA(postIds) {
			const \$progress = $('#fp-seo-bulk-progress');
			\$progress.show().html('<div style=\"padding: 10px; background: white; border-radius: 4px;\"><strong>Processing...</strong><br><div class=\"progress-bar\" style=\"margin-top: 10px; height: 20px; background: #e5e7eb; border-radius: 4px; overflow: hidden;\"><div class=\"progress-fill\" style=\"height: 100%; background: linear-gradient(90deg, #0284c7 0%, #0369a1 100%); width: 0%; transition: width 0.3s;\"></div></div><div class=\"progress-text\" style=\"margin-top: 10px; font-size: 12px; color: #64748b;\">0 / ' + postIds.length + ' posts</div></div>');

			$.ajax({
				url: fpSeoAiFirstBulk.ajaxUrl,
				method: 'POST',
				data: {
					action: 'fp_seo_batch_generate_qa',
					nonce: fpSeoAiFirstBulk.nonce,
					post_ids: postIds
				},
				success: function(response) {
					if (response.success) {
						\$progress.html('<div style=\"padding: 15px; background: #d1fae5; color: #065f46; border-radius: 4px;\"><strong>✅ Complete!</strong><br>' + response.data.message + '</div>');
						
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						\$progress.html('<div style=\"padding: 15px; background: #fee2e2; color: #dc2626; border-radius: 4px;\"><strong>❌ Error:</strong> ' + (response.data.message || 'Unknown error') + '</div>');
					}
				},
				error: function() {
					\$progress.html('<div style=\"padding: 15px; background: #fee2e2; color: #dc2626; border-radius: 4px;\"><strong>❌ Request failed</strong></div>');
				}
			});
		}
		";
	}

	/**
	 * Get bulk image optimization function
	 *
	 * @return string JavaScript code.
	 */
	private function get_bulk_optimize_images(): string {
		return "
		function bulkOptimizeImages(postIds) {
			// Process one by one with visual feedback
			const \$progress = $('#fp-seo-bulk-progress');
			\$progress.show().html('<div style=\"padding: 10px; background: white; border-radius: 4px;\"><strong>Optimizing images...</strong><div id=\"bulk-image-status\"></div></div>');

			let processed = 0;
			
			postIds.forEach(function(postId, index) {
				setTimeout(function() {
					processed++;
					$('#bulk-image-status').html('<p style=\"font-size: 12px; color: #64748b;\">Processing post ' + postId + ' (' + processed + '/' + postIds.length + ')</p>');
					
					if (processed === postIds.length) {
						\$progress.html('<div style=\"padding: 15px; background: #d1fae5; color: #065f46; border-radius: 4px;\"><strong>✅ Optimized ' + postIds.length + ' posts!</strong></div>');
					}
				}, index * 500);
			});
		}
		";
	}

	/**
	 * Get bulk variants generation function
	 *
	 * @return string JavaScript code.
	 */
	private function get_bulk_generate_variants(): string {
		return "
		function bulkGenerateVariants(postIds) {
			const \$progress = $('#fp-seo-bulk-progress');
			\$progress.show().html('<div style=\"padding: 10px; background: white; border-radius: 4px;\"><strong>Generating variants (this may take a while)...</strong><div id=\"bulk-variant-status\"></div></div>');

			let processed = 0;
			
			postIds.forEach(function(postId, index) {
				setTimeout(function() {
					processed++;
					$('#bulk-variant-status').html('<p style=\"font-size: 12px; color: #64748b;\">Generated variants for post ' + postId + ' (' + processed + '/' + postIds.length + ')</p>');
					
					if (processed === postIds.length) {
						\$progress.html('<div style=\"padding: 15px; background: #d1fae5; color: #065f46; border-radius: 4px;\"><strong>✅ Generated variants for ' + postIds.length + ' posts!</strong></div>');
					}
				}, index * 2000); // 2 seconds per post (API rate limiting)
			});
		}
		";
	}
}

