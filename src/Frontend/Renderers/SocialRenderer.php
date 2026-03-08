<?php
/**
 * Social media meta tags renderer for frontend.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Renderers;

use FP\SEO\Social\ImprovedSocialMediaManager;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use function is_admin;
use function is_feed;
use function is_singular;
use function get_queried_object_id;

/**
 * Renders social media meta tags (Open Graph, Twitter Cards) in the frontend head.
 */
class SocialRenderer extends AbstractRenderer {

	/**
	 * Social media manager instance.
	 *
	 * @var ImprovedSocialMediaManager
	 */
	private ImprovedSocialMediaManager $social_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface        $hook_manager   Hook manager.
	 * @param ImprovedSocialMediaManager $social_manager Social media manager.
	 */
	public function __construct(
		HookManagerInterface $hook_manager,
		ImprovedSocialMediaManager $social_manager
	) {
		parent::__construct( $hook_manager );
		$this->social_manager = $social_manager;
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
	 * Render social media meta tags.
	 *
	 * @param mixed $context Rendering context (not used for social meta).
	 * @return string Rendered output.
	 */
	public function render( $context = null ): string {
		if ( is_admin() || is_feed() ) {
			return '';
		}

		// Generate tags for singular posts/pages and homepage
		$post_id = 0;
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
		} elseif ( is_front_page() && is_home() ) {
			// Blog homepage
			$post_id = get_option( 'page_for_posts' );
		} elseif ( is_front_page() ) {
			// Static homepage
			$post_id = get_option( 'page_on_front' );
		}

		if ( ! $post_id ) {
			return '';
		}

		ob_start();
		// Delegate to ImprovedSocialMediaManager's output method
		// This maintains backward compatibility while using the new renderer structure
		if ( method_exists( $this->social_manager, 'output_meta_tags' ) ) {
			$this->social_manager->output_meta_tags();
		}
		$output = ob_get_clean();
		if ( false === $output ) {
			return '';
		}
		
		// Output the result (wp_head hook expects direct output, not return)
		if ( ! empty( $output ) ) {
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		
		return $output;
	}
}


