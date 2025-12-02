<?php
/**
 * Manages styles for the Q&A MetaBox.
 *
 * @package FP\SEO\Admin\Styles
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Styles;

use function get_current_screen;

/**
 * Manages styles for the Q&A MetaBox.
 */
class QAMetaBoxStylesManager {
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
		.fp-seo-qa-metabox {
			padding: 10px 0;
		}
		.fp-seo-qa-metabox .description {
			display: block;
			margin-top: 5px;
			font-size: 12px;
			color: #6b7280;
		}
		.fp-seo-qa-pair {
			transition: all 0.3s ease;
		}
		.fp-seo-qa-pair:hover {
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		</style>
		<?php
	}
}

