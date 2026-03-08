<?php
/**
 * Abstract base class for styles managers.
 *
 * @package FP\SEO\Admin\Assets
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Assets;

use function get_current_screen;

/**
 * Abstract base class for styles managers.
 */
abstract class AbstractStylesManager extends AbstractAssetManager {

	/**
	 * Register hooks for styles injection.
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
		if ( ! $this->should_inject_styles() ) {
			return;
		}

		$this->render_styles();
	}

	/**
	 * Check if styles should be injected.
	 *
	 * Override in subclasses to add custom logic.
	 *
	 * @return bool True if should inject, false otherwise.
	 */
	protected function should_inject_styles(): bool {
		return true;
	}

	/**
	 * Render all styles.
	 *
	 * Override in subclasses to provide custom styles.
	 *
	 * @return void
	 */
	abstract protected function render_styles(): void;

	/**
	 * Output style tag with content.
	 *
	 * @param string $style_id Style tag ID.
	 * @param string $content  CSS content.
	 * @return void
	 */
	protected function output_style_tag( string $style_id, string $content ): void {
		echo '<style id="' . esc_attr( $style_id ) . '">' . "\n";
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</style>' . "\n";
	}
}








