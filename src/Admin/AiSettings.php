<?php
/**
 * AI Settings integration.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Settings\AiTabRenderer;

/**
 * Registers AI settings tab and related functionality.
 */
class AiSettings {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'fpseo_settings_tabs', array( $this, 'add_ai_tab' ) );
		add_action( 'fpseo_settings_render_tab_ai', array( $this, 'render_ai_tab' ) );
	}

	/**
	 * Add AI tab to settings.
	 *
	 * @param array<string, string> $tabs Existing tabs.
	 * @return array<string, string>
	 */
	public function add_ai_tab( array $tabs ): array {
		$tabs['ai'] = __( 'AI', 'fp-seo-performance' );
		return $tabs;
	}

	/**
	 * Render AI tab content.
	 *
	 * @param array<string, mixed> $options Current options.
	 */
	public function render_ai_tab( array $options ): void {
		$renderer = new AiTabRenderer();
		$renderer->render( $options );
	}
}

