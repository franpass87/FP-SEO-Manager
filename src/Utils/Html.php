<?php
/**
 * HTML helper utilities.
 *
 * @package FP\SEO
 */

declare(strict_types=1);


/**
 * Helper utilities for rendering sanitized HTML.
 */
class Html {

	/**
	 * Escapes text for safe HTML output.
	 *
	 * @param string|null $text Raw text.
	 */
	public static function esc_text( ?string $text ): string {
		return esc_html( $text ?? '' );
	}
}
