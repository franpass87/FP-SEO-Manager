<?php
/**
 * Manages styles for the Freshness MetaBox.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use function get_current_screen;

/**
 * Manages styles for the Freshness MetaBox.
 */
class FreshnessMetaBoxStylesManager {
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
		.fp-seo-freshness-metabox p {
			margin: 12px 0;
		}
		.fp-seo-freshness-metabox .description {
			display: block;
			margin-top: 5px;
			font-size: 12px;
			color: #6b7280;
		}
		</style>
		<?php
	}
}


