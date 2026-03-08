<?php
/**
 * Keywords meta tags renderer for frontend.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Renderers;

use FP\SEO\Keywords\MultipleKeywordsManager;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use function is_admin;
use function is_feed;

/**
 * Renders keywords meta tags in the frontend head.
 */
class KeywordsRenderer extends AbstractRenderer {

	/**
	 * Keywords manager instance.
	 *
	 * @var MultipleKeywordsManager
	 */
	private MultipleKeywordsManager $keywords_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface      $hook_manager     Hook manager.
	 * @param MultipleKeywordsManager  $keywords_manager Keywords manager.
	 */
	public function __construct(
		HookManagerInterface $hook_manager,
		MultipleKeywordsManager $keywords_manager
	) {
		parent::__construct( $hook_manager );
		$this->keywords_manager = $keywords_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->hook_manager ) {
			$this->hook_manager->add_action( 'wp_head', array( $this, 'render' ), 1 );
		}
	}

	/**
	 * Render keywords meta tags.
	 *
	 * @param mixed $context Rendering context (not used for keywords).
	 * @return string Rendered output.
	 */
	public function render( $context = null ): string {
		if ( is_admin() || is_feed() ) {
			return '';
		}

		ob_start();
		// Delegate to MultipleKeywordsManager's output method
		// This maintains backward compatibility while using the new renderer structure
		if ( method_exists( $this->keywords_manager, 'output_keywords_meta' ) ) {
			$this->keywords_manager->output_keywords_meta();
		}
		$output = ob_get_clean();
		if ( false === $output ) {
			return '';
		}
		
		// Output the result (wp_head hook expects direct output, not return)
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		
		return $output;
	}
}


