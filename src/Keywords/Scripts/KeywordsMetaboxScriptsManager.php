<?php
/**
 * Manages scripts for the Keywords Metabox.
 *
 * @package FP\SEO\Keywords\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Keywords\Scripts;

use function get_current_screen;
use function get_the_ID;
use function wp_create_nonce;

/**
 * Manages scripts for the Keywords Metabox.
 */
class KeywordsMetaboxScriptsManager {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_footer', array( $this, 'inject_scripts' ) );
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
		
		$this->render_scripts();
	}

	/**
	 * Render all scripts.
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		$post_id = get_the_ID();
		$nonce   = wp_create_nonce( 'fp_seo_keywords_nonce' );
		?>
		<script>
		jQuery(document).ready(function($) {
			// Tab switching
			$('.fp-seo-keywords-tab').on('click', function() {
				var tab = $(this).data('tab');
				$('.fp-seo-keywords-tab').removeClass('active');
				$('.fp-seo-keywords-tab-content').removeClass('active');
				$(this).addClass('active');
				$('#' + tab).addClass('active');
			});

			// Add keyword functionality
			function addKeyword(inputId, listId, nameAttr) {
				$('#' + inputId).on('keypress', function(e) {
					if (e.which === 13) { // Enter key
						e.preventDefault();
						addKeywordToList($(this).val(), listId, nameAttr);
						$(this).val('');
					}
				});

				$('#fp-seo-add-' + inputId.replace('fp-seo-', '').replace('-input', '') + '-keyword').on('click', function() {
					var keyword = $('#' + inputId).val();
					if (keyword.trim()) {
						addKeywordToList(keyword, listId, nameAttr);
						$('#' + inputId).val('');
					}
				});
			}

			function addKeywordToList(keyword, listId, nameAttr) {
				if (!keyword.trim()) return;

				var keywordItem = $('<div class="fp-seo-keyword-item">' +
					'<input type="hidden" name="' + nameAttr + '[]" value="' + keyword + '">' +
					'<span class="fp-seo-keyword-text">' + keyword + '</span>' +
					'<button type="button" class="fp-seo-remove-keyword">×</button>' +
					'</div>');

				$('#' + listId).append(keywordItem);
			}

			// Remove keyword functionality
			$(document).on('click', '.fp-seo-remove-keyword', function() {
				$(this).closest('.fp-seo-keyword-item').remove();
			});

			// Use suggestion functionality
			$(document).on('click', '.fp-seo-use-suggestion', function() {
				var keyword = $(this).closest('.fp-seo-suggestion-item').data('keyword');
				var currentTab = $('.fp-seo-keywords-tab.active').data('tab');
				
				switch (currentTab) {
					case 'primary':
						$('#fp-seo-primary-keyword').val(keyword);
						break;
					case 'secondary':
						addKeywordToList(keyword, 'fp-seo-secondary-keywords-list', 'fp_seo_secondary_keywords');
						break;
					case 'long-tail':
						addKeywordToList(keyword, 'fp-seo-long-tail-keywords-list', 'fp_seo_long_tail_keywords');
						break;
					case 'semantic':
						addKeywordToList(keyword, 'fp-seo-semantic-keywords-list', 'fp_seo_semantic_keywords');
						break;
				}
			});

			// Initialize add keyword functionality
			addKeyword('fp-seo-secondary-keyword-input', 'fp-seo-secondary-keywords-list', 'fp_seo_secondary_keywords');
			addKeyword('fp-seo-long-tail-keyword-input', 'fp-seo-long-tail-keywords-list', 'fp_seo_long_tail_keywords');
			addKeyword('fp-seo-semantic-keyword-input', 'fp-seo-semantic-keywords-list', 'fp_seo_semantic_keywords');

			// Analyze keywords
			$('#fp-seo-analyze-keywords').on('click', function() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_analyze_keywords',
						post_id: <?php echo esc_js( (string) $post_id ); ?>,
						nonce: '<?php echo esc_js( $nonce ); ?>'
					},
					success: function(response) {
						if (response.success) {
							location.reload(); // Refresh to show analysis
						} else {
							alert('Error: ' + response.data);
						}
					}
				});
			});

			// Optimize keywords
			$('#fp-seo-optimize-keywords').on('click', function() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_optimize_keywords',
						post_id: <?php echo esc_js( (string) $post_id ); ?>,
						nonce: '<?php echo esc_js( $nonce ); ?>'
					},
					success: function(response) {
						if (response.success) {
							// Update fields with AI suggestions
							if (response.data.primary) {
								$('#fp-seo-primary-keyword').val(response.data.primary);
							}
							if (response.data.secondary) {
								response.data.secondary.forEach(function(keyword) {
									addKeywordToList(keyword, 'fp-seo-secondary-keywords-list', 'fp_seo_secondary_keywords');
								});
							}
						} else {
							alert('Error: ' + response.data);
						}
					}
				});
			});
		});
		</script>
		<?php
	}
}


