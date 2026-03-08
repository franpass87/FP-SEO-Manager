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
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Registers AI settings tab and related functionality.
 */
class AiSettings {

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		if ( $this->hook_manager ) {
			$this->hook_manager->add_filter( 'fpseo_settings_tabs', array( $this, 'add_ai_tab' ) );
			$this->hook_manager->add_action( 'fpseo_settings_render_tab_ai', array( $this, 'render_ai_tab' ) );
		} else {
			add_filter( 'fpseo_settings_tabs', array( $this, 'add_ai_tab' ) );
			add_action( 'fpseo_settings_render_tab_ai', array( $this, 'render_ai_tab' ) );
		}
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

