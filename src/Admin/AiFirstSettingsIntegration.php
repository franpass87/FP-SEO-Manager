<?php
/**
 * AI-First Settings Tab Integration
 *
 * Integrates AI-First settings tab into main settings page.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Admin\Settings\AiFirstTabRenderer;

/**
 * Integrates AI-First tab into settings
 */
class AiFirstSettingsIntegration {

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_filter( 'fpseo_settings_tabs', array( $this, 'add_tab' ) );
		add_action( 'fpseo_settings_render_tab_ai_first', array( $this, 'render_tab' ) );
	}

	/**
	 * Add AI-First tab
	 *
	 * @param array<string, string> $tabs Existing tabs.
	 * @return array<string, string> Modified tabs.
	 */
	public function add_tab( array $tabs ): array {
		// Insert AI-First tab after 'ai' tab if exists, otherwise before 'advanced'
		$position = array_key_exists( 'advanced', $tabs ) ? 'advanced' : null;

		if ( $position ) {
			$new_tabs = array();

			foreach ( $tabs as $key => $label ) {
				if ( $key === $position ) {
					$new_tabs['ai_first'] = __( 'AI-First', 'fp-seo-performance' );
				}
				$new_tabs[ $key ] = $label;
			}

			return $new_tabs;
		}

		$tabs['ai_first'] = __( 'AI-First', 'fp-seo-performance' );

		return $tabs;
	}

	/**
	 * Render AI-First tab
	 *
	 * @param array<string, mixed> $options Current options.
	 */
	public function render_tab( array $options ): void {
		$renderer = new AiFirstTabRenderer();
		$renderer->render( $options );
	}
}


