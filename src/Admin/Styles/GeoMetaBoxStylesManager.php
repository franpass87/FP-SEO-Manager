<?php
/**
 * Manages styles for the GEO MetaBox.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use function get_current_screen;

/**
 * Manages styles for the GEO MetaBox.
 */
class GeoMetaBoxStylesManager {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_head', array( $this, 'inject_styles' ) );
	}

	/**
	 * Inject styles in admin head.
	 *
	 * @return void
	 */
	public function inject_styles(): void {
		$screen = get_current_screen();
		
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		
		$this->render_styles();
	}

	/**
	 * Render all styles.
	 *
	 * @return void
	 */
	private function render_styles(): void {
		?>
		<style>
		.fp-seo-geo-claim {
			padding: 16px;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			margin-bottom: 12px;
		}
		.fp-seo-geo-claim-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 12px;
			padding-bottom: 8px;
			border-bottom: 1px solid #ddd;
		}
		.fp-seo-geo-evidence-container {
			margin-top: 12px;
			padding-top: 12px;
			border-top: 1px solid #ddd;
		}
		</style>
		<?php
	}
}


