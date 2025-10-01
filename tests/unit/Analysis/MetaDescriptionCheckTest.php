<?php
/**
 * Meta description check tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use Brain\Monkey;
use FP\SEO\Analysis\Checks\MetaDescriptionCheck;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Meta description check unit tests.
 */
final class MetaDescriptionCheckTest extends TestCase {
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();
                when( '__' )->returnArg( 1 );
                when( 'esc_html__' )->returnArg( 1 );
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }
	/**
	 * Ensures valid meta descriptions pass.
	 *
	 * @return void
	 */
	public function test_passes_with_valid_length(): void {
		$description = str_repeat( 'Quality meta description content. ', 4 );
		$context     = new Context( null, '', '', $description );
		$check       = new MetaDescriptionCheck();

		$result = $check->run( $context );

		self::assertSame( Result::STATUS_PASS, $result->status() );
	}

	/**
	 * Ensures short meta descriptions warn.
	 *
	 * @return void
	 */
	public function test_warns_when_length_too_short(): void {
		$context = new Context( null, '', '', 'Too short description.' );
		$check   = new MetaDescriptionCheck();

		$result = $check->run( $context );

		self::assertSame( Result::STATUS_WARN, $result->status() );
	}

	/**
	 * Ensures descriptions can be sourced from markup.
	 *
	 * @return void
	 */
	public function test_reads_description_from_meta_tag(): void {
		$html    = '<meta name="description" content="' . str_repeat( 'Word ', 30 ) . '" />';
		$context = new Context( null, '<head>' . $html . '</head>', '', '' );
		$check   = new MetaDescriptionCheck();

		$result = $check->run( $context );

		self::assertSame( Result::STATUS_PASS, $result->status() );
	}
}
