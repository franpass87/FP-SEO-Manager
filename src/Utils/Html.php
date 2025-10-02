<?php
/**
 * HTML helper utilities.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use function esc_html;
use function function_exists;
use function get_bloginfo;
use function htmlspecialchars;
use Throwable;

/**
 * Helper utilities for rendering sanitized HTML.
 */
class Html {
    /**
     * Escapes text for safe HTML output.
     *
     * @param string|null $text Raw text.
     */
    public static function esc_text(?string $text): string {
        $value = $text ?? '';

        if (defined('ABSPATH') && function_exists('esc_html')) {
            try {
                return esc_html($value);
            } catch (Throwable $exception) {
                // Fall through to the HTML-escaping fallback.
            }
        }

        $charset = 'UTF-8';

        if (defined('ABSPATH') && function_exists('get_bloginfo')) {
            $blog_charset = (string) get_bloginfo('charset');

            if ('' !== $blog_charset) {
                $charset = $blog_charset;
            }
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, $charset);
    }
}
