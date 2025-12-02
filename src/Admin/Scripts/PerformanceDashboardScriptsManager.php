<?php
/**
 * Manages scripts for the Performance Dashboard page.
 *
 * @package FP\SEO\Admin\Scripts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

use function get_current_screen;
use function wp_create_nonce;
use function esc_js;

/**
 * Manages scripts for the Performance Dashboard page.
 */
class PerformanceDashboardScriptsManager {
	private const PAGE_SLUG = 'fp-seo-performance-dashboard';

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
		
		if ( ! $screen || 'fp-seo-performance_page_' . self::PAGE_SLUG !== $screen->id ) {
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
			<?php $this->render_health_check_handler(); ?>
			<?php $this->render_database_optimize_handler(); ?>
			<?php $this->render_assets_optimize_handler(); ?>
			<?php $this->render_cache_clear_handler(); ?>
		});
		</script>
		<?php
	}

	/**
	 * Render health check handler.
	 *
	 * @return void
	 */
	private function render_health_check_handler(): void {
		?>
		$('#run-health-check').on('click', function() {
			$('#fp-seo-loading-overlay').show();
			
			$.post(ajaxurl, {
				action: 'fp_seo_run_health_check',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_health_check' ) ); ?>'
			}, function(response) {
				$('#fp-seo-loading-overlay').hide();
				if (response.success) {
					location.reload();
				} else {
					alert('Error: ' + response.data);
				}
			});
		});
		<?php
	}

	/**
	 * Render database optimize handler.
	 *
	 * @return void
	 */
	private function render_database_optimize_handler(): void {
		?>
		$('#optimize-database').on('click', function() {
			$('#fp-seo-loading-overlay').show();
			
			$.post(ajaxurl, {
				action: 'fp_seo_optimize_database',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_optimize_database' ) ); ?>'
			}, function(response) {
				$('#fp-seo-loading-overlay').hide();
				if (response.success) {
					alert('Database optimized successfully!');
					location.reload();
				} else {
					alert('Error: ' + response.data);
				}
			});
		});
		<?php
	}

	/**
	 * Render assets optimize handler.
	 *
	 * @return void
	 */
	private function render_assets_optimize_handler(): void {
		?>
		$('#optimize-assets').on('click', function() {
			$('#fp-seo-loading-overlay').show();
			
			$.post(ajaxurl, {
				action: 'fp_seo_optimize_assets',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_optimize_assets' ) ); ?>'
			}, function(response) {
				$('#fp-seo-loading-overlay').hide();
				if (response.success) {
					alert('Assets optimized successfully!');
					location.reload();
				} else {
					alert('Error: ' + response.data);
				}
			});
		});
		<?php
	}

	/**
	 * Render cache clear handler.
	 *
	 * @return void
	 */
	private function render_cache_clear_handler(): void {
		?>
		$('#clear-cache').on('click', function() {
			$('#fp-seo-loading-overlay').show();
			
			$.post(ajaxurl, {
				action: 'fp_seo_clear_cache',
				nonce: '<?php echo esc_js( wp_create_nonce( 'fp_seo_clear_cache' ) ); ?>'
			}, function(response) {
				$('#fp-seo-loading-overlay').hide();
				if (response.success) {
					alert('Cache cleared successfully!');
					location.reload();
				} else {
					alert('Error: ' + response.data);
				}
			});
		});
		<?php
	}
}


