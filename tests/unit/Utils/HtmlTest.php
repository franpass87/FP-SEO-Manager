<?php
/**
 * HTML utility tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }
}

namespace FP\SEO\Tests\Unit\Utils {

use Brain\Monkey;
use Error;
use FP\SEO\Utils\Html;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \FP\SEO\Utils\Html
 */
class HtmlTest extends TestCase {
    public function test_esc_text_uses_wordpress_helper(): void {
        Monkey\setUp();

        try {
            when('esc_html')->alias(static fn($value) => is_string($value) ? strtoupper($value) : '');

            self::assertSame('HELLO', Html::esc_text('hello'));
        } finally {
            Monkey\tearDown();
        }
    }

    public function test_esc_text_handles_null(): void {
        Monkey\setUp();

        try {
            when('esc_html')->alias(static fn($value) => strtoupper((string) $value));

            self::assertSame('', Html::esc_text(null));
        } finally {
            Monkey\tearDown();
        }
    }

    public function test_esc_text_falls_back_when_wordpress_helper_errors(): void {
        Monkey\setUp();

        try {
            when('esc_html')->alias(static function (): string {
                throw new Error('esc_html unavailable');
            });

            self::assertSame('hello &amp; world', Html::esc_text('hello & world'));
        } finally {
            Monkey\tearDown();
        }
    }

    public function test_esc_text_respects_site_charset_in_fallback(): void {
        Monkey\setUp();

        try {
            when('esc_html')->alias(static function (): string {
                throw new Error('esc_html unavailable');
            });

            when('get_bloginfo')->alias(static function (string $show): string {
                return 'charset' === $show ? 'ISO-8859-1' : '';
            });

            $input = "\xA3";

            self::assertSame($input, Html::esc_text($input));
        } finally {
            Monkey\tearDown();
        }
    }
}
}
