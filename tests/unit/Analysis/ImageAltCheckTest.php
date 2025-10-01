<?php
/**
 * Image alternative text check tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use FP\SEO\Analysis\Checks\ImageAltCheck;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use PHPUnit\Framework\TestCase;

/**
 * Image alt check unit tests.
 */
final class ImageAltCheckTest extends TestCase {
	/**
	 * Ensures full alt coverage passes.
	 *
	 * @return void
	 */
	public function test_passes_with_full_alt_coverage(): void {
		$html    = '<img src="one.jpg" alt="First image" /><img src="two.jpg" alt="Second" />';
		$context = new Context( null, $html, '', '' );
		$check   = new ImageAltCheck();

		$result = $check->run( $context );

		self::assertSame( Result::STATUS_PASS, $result->status() );
		self::assertSame( 100.0, $result->details()['coverage'] );
	}

	/**
	 * Ensures missing alt text fails.
	 *
	 * @return void
	 */
	public function test_warns_when_alt_missing(): void {
		$html    = '<img src="one.jpg" alt="" /><img src="two.jpg" />';
		$context = new Context( null, $html, '', '' );
		$check   = new ImageAltCheck();

		$result = $check->run( $context );

		self::assertSame( Result::STATUS_FAIL, $result->status() );
	}
}
