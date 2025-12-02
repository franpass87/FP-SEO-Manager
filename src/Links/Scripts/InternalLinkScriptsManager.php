<?php
/**
 * Manages scripts for the Internal Link Manager metabox.
 *
 * @package FP\SEO\Links\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Links\Scripts;

use function admin_url;
use function esc_js;
use function get_current_screen;
use function get_the_ID;
use function wp_create_nonce;

/**
 * Manages scripts for the Internal Link Manager metabox.
 */
class InternalLinkScriptsManager {
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
		?>
		<script>
		jQuery(document).ready(function($) {
			<?php $this->render_insert_link_handler(); ?>
			<?php $this->render_preview_link_handler(); ?>
			<?php $this->render_refresh_suggestions_handler(); ?>
			<?php $this->render_analyze_links_handler(); ?>
		});
		</script>
		<?php
	}

	/**
	 * Render insert link handler.
	 *
	 * @return void
	 */
	private function render_insert_link_handler(): void {
		?>
		// Insert link functionality
		$('.fp-seo-insert-link').on('click', function() {
			var postId = $(this).data('post-id');
			var anchorText = $(this).data('anchor-text');
			var url = $(this).data('url');
			
			// Insert link into editor
			if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
				wp.media.editor.insert('[[' + anchorText + ']](' + url + ')');
			} else {
				// Fallback for classic editor
				var link = '<a href="' + url + '">' + anchorText + '</a>';
				// This would need to be implemented based on your editor setup
				alert('Link: ' + link);
			}
		});
		<?php
	}

	/**
	 * Render preview link handler.
	 *
	 * @return void
	 */
	private function render_preview_link_handler(): void {
		?>
		// Preview link functionality
		$('.fp-seo-preview-link').on('click', function() {
			var postId = $(this).data('post-id');
			window.open('<?php echo esc_js( admin_url( 'post.php?post=' ) ); ?>' + postId + '&action=edit', '_blank');
		});
		<?php
	}

	/**
	 * Render refresh suggestions handler.
	 *
	 * @return void
	 */
	private function render_refresh_suggestions_handler(): void {
		?>
		// Refresh suggestions
		$('#fp-seo-refresh-suggestions').on('click', function() {
			location.reload();
		});
		<?php
	}

	/**
	 * Render analyze links handler.
	 *
	 * @return void
	 */
	private function render_analyze_links_handler(): void {
		$post_id = get_the_ID();
		?>
		// Analyze links
		$('#fp-seo-analyze-links').on('click', function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'fp_seo_analyze_internal_links',
					post_id: <?php echo esc_js( (string) $post_id ); ?>,
					nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_links_nonce' ) ); ?>'
				},
				success: function(response) {
					if (response.success) {
						alert('Link analysis completed: ' + response.data.message);
					} else {
						alert('Error: ' + response.data);
					}
				}
			});
		});
		<?php
	}
}


