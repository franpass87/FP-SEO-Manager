<?php
/**
 * Lightweight translation helpers.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use function __;
use function function_exists;

/**
 * Provides WordPress-aware translation fallbacks.
 */
class I18n {
	/**
	 * Translate text when possible, otherwise return original string.
	 *
	 * @param string $text Text to translate.
	 *
	 * @return string
	 */
	public static function translate( string $text ): string {
		if ( function_exists( '__' ) ) {
				return __( $text, 'fp-seo-performance' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Dynamic runtime string.
		}

		return $text;
	}
}
