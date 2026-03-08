<?php
/**
 * Abstract base class for scripts managers.
 *
 * @package FP\SEO\Admin\Assets
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Assets;

use function get_current_screen;

/**
 * Abstract base class for scripts managers.
 */
abstract class AbstractScriptsManager extends AbstractAssetManager {

	/**
	 * Register hooks for scripts injection.
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
		if ( ! $this->should_inject_scripts() ) {
			return;
		}

		$this->render_scripts();
	}

	/**
	 * Check if scripts should be injected.
	 *
	 * Override in subclasses to add custom logic.
	 *
	 * @return bool True if should inject, false otherwise.
	 */
	protected function should_inject_scripts(): bool {
		return true;
	}

	/**
	 * Render all scripts.
	 *
	 * Override in subclasses to provide custom scripts.
	 *
	 * @return void
	 */
	abstract protected function render_scripts(): void;

	/**
	 * Output script tag with content.
	 *
	 * @param string $script_id Script tag ID.
	 * @param string $content   JavaScript content.
	 * @return void
	 */
	protected function output_script_tag( string $script_id, string $content ): void {
		echo '<script id="' . esc_attr( $script_id ) . '">' . "\n";
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</script>' . "\n";
	}

	/**
	 * Get all JavaScript code.
	 *
	 * Override in subclasses to provide JavaScript code.
	 *
	 * @return string JavaScript code.
	 */
	abstract public function get_scripts(): string;
}








