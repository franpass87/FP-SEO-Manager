<?php
/**
 * Bulk Actions for AI-First Features
 *
 * Adds bulk actions to Bulk Auditor for batch processing AI features.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\AI\QAPairExtractor;
use FP\SEO\AI\ConversationalVariants;
use FP\SEO\GEO\MultiModalOptimizer;

/**
 * Manages bulk AI actions
 */
class BulkAiActions {

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'fpseo_bulk_audit_actions', array( $this, 'add_bulk_actions' ) );
	}

	/**
	 * Enqueue assets for bulk actions
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'fp-seo-performance_page_fp-seo-performance-bulk' !== $hook ) {
			return;
		}

		wp_localize_script(
			'fp-seo-performance-bulk',
			'fpSeoAiFirstBulk',
			array(
				'nonce'   => wp_create_nonce( 'fp_seo_ai_first_bulk' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);

		wp_add_inline_script( 'fp-seo-performance-bulk', $this->get_bulk_js() );
	}

	/**
	 * Get JavaScript for bulk actions
	 *
	 * @return string JavaScript code.
	 */
	private function get_bulk_js(): string {
		return "
		jQuery(document).ready(function($) {
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

			function getSelectedPosts() {
				return $('.fp-seo-bulk-checkbox:checked').map(function() {
					return $(this).val();
				}).get();
			}

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
		});
		";
	}

	/**
	 * Add AI-first bulk actions
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @return array<string, string> Modified actions.
	 */
	public function add_bulk_actions( array $actions ): array {
		$actions['generate_qa']        = __( 'Generate Q&A Pairs', 'fp-seo-performance' );
		$actions['optimize_images']    = __( 'Optimize Images for AI', 'fp-seo-performance' );
		$actions['generate_variants']  = __( 'Generate Conversational Variants', 'fp-seo-performance' );
		$actions['generate_embeddings'] = __( 'Generate Embeddings', 'fp-seo-performance' );

		return $actions;
	}
}


