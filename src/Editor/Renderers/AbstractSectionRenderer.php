<?php
/**
 * Abstract base class for section renderers.
 *
 * @package FP\SEO\Editor\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Renderers;

use FP\SEO\Editor\Helpers\PostValidationHelper;
use FP\SEO\Utils\Logger;
use WP_Post;
use function esc_attr;
use function esc_html;
use function esc_textarea;
use function esc_url;

/**
 * Abstract base class for section renderers.
 *
 * Provides common functionality like escape helpers, validation, and logging.
 */
abstract class AbstractSectionRenderer {
	/**
	 * Validate post object.
	 *
	 * @param WP_Post $post Post object to validate.
	 * @return WP_Post|null Validated post or null if invalid.
	 */
	protected function validate_post( WP_Post $post ): ?WP_Post {
		return PostValidationHelper::validate_post( $post );
	}

	/**
	 * Log render operation.
	 *
	 * @param string  $section Section identifier.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	protected function log_render( string $section, WP_Post $post ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		Logger::debug(
			"Rendering section: {$section}",
			array(
				'section'  => $section,
				'post_id'  => $post->ID,
				'post_type' => $post->post_type,
			)
		);
	}

	/**
	 * Escape HTML.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	protected function esc_html( string $text ): string {
		return esc_html( $text );
	}

	/**
	 * Escape HTML attribute.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	protected function esc_attr( string $text ): string {
		return esc_attr( $text );
	}

	/**
	 * Escape URL.
	 *
	 * @param string $url URL to escape.
	 * @return string Escaped URL.
	 */
	protected function esc_url( string $url ): string {
		return esc_url( $url );
	}

	/**
	 * Escape textarea content.
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	protected function esc_textarea( string $text ): string {
		return esc_textarea( $text );
	}
}


