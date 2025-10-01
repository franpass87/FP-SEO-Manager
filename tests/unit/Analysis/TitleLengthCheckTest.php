<?php
/**
 * Title length check tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use FP\SEO\Analysis\Checks\TitleLengthCheck;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use PHPUnit\Framework\TestCase;

/**
 * Title length check unit tests.
 */
final class TitleLengthCheckTest extends TestCase {
	/**
	 * Ensures ideal title lengths pass.
	 *
	 * @return void
	 */
	public function test_passes_with_recommended_length(): void {
		$context = new Context(
			null,
			'<p>Sample</p>',
			'Balanced SEO title hitting exactly fifty-seven characters',
			''
		);

		$check  = new TitleLengthCheck();
		$result = $check->run( $context );

		self::assertSame( Result::STATUS_PASS, $result->status() );
		self::assertSame( 57, $result->details()['length'] );
	}

	/**
	 * Ensures short titles fail the check.
	 *
	 * @return void
	 */
	public function test_warns_when_length_outside_range(): void {
		$context = new Context(
			null,
			'',
			'A short title',
			''
		);

		$check  = new TitleLengthCheck();
		$result = $check->run( $context );

		self::assertSame( Result::STATUS_FAIL, $result->status() );
		self::assertSame( 13, $result->details()['length'] );
	}

	/**
	 * Ensures empty titles fail the check.
	 *
	 * @return void
	 */
	public function test_requires_title_presence(): void {
		$context = new Context( null, '', '', '' );
		$check   = new TitleLengthCheck();

		$result = $check->run( $context );

		self::assertSame( Result::STATUS_FAIL, $result->status() );
	}
}
