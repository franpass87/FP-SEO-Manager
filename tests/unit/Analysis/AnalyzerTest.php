<?php
/**
 * Analyzer smoke tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Analysis;

use Brain\Monkey;
use FP\SEO\Analysis\Analyzer;
use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;
use const JSON_UNESCAPED_SLASHES;

/**
 * Analyzer integration smoke test.
 */
final class AnalyzerTest extends TestCase {
        protected function setUp(): void {
                parent::setUp();
                Monkey\setUp();
                when( '__' )->returnArg( 1 );
                when( 'esc_html__' )->returnArg( 1 );
                when( 'home_url' )->justReturn( 'https://example.com/' );
                when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
        }

        protected function tearDown(): void {
                Monkey\tearDown();
                parent::tearDown();
        }
	/**
	 * Ensures analyzer executes all checks and aggregates results.
	 *
	 * @return void
	 */
	public function test_runs_all_checks_and_aggregates_summary(): void {
		$meta_description = str_repeat( 'Helpful search description offering clarity and context. ', 2 ) . 'Encourages clicks with compelling value.';
		$content          = str_repeat( 'Insightful content supporting the analyzer evaluation. ', 40 );
                $html             = '<html><head>'
                . '<meta name="description" content="' . $meta_description . '" />'
                . '<meta name="robots" content="index,follow" />'
                . '<link rel="canonical" href="https://example.com/sample-page" />'
                . '<meta property="og:title" content="OG Title" />'
                . '<meta property="og:description" content="OG description content." />'
                . '<meta property="og:type" content="article" />'
                . '<meta property="og:url" content="https://example.com/sample-page" />'
                . '<meta property="og:image" content="https://example.com/og-image.jpg" />'
                . '<meta name="twitter:card" content="summary_large_image" />'
                . '<meta name="twitter:title" content="Twitter Title" />'
                . '<meta name="twitter:description" content="Twitter description goes here." />'
                . '<meta name="twitter:image" content="https://example.com/twitter-image.jpg" />'
                . '<script type="application/ld+json">' . json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
                        array(
                                '@context' => 'https://schema.org',
                                '@type'    => array( 'Organization', 'WebSite', 'BlogPosting' ),
				'name'     => 'Example',
			),
			JSON_UNESCAPED_SLASHES
		) . '</script>'
		. '</head><body>'
		. '<h1>Primary Heading</h1><h2>Section Heading</h2>'
		. '<p>' . $content . '<a href="/internal-link-one">Read more</a> '
		. '<a href="/another-internal">Explore further</a></p>'
		. '<img src="image.jpg" alt="Descriptive alt" />'
		. '</body></html>';

		$context = new Context(
			42,
			$html,
			'Balanced SEO title hitting exactly fifty-seven characters',
			$meta_description,
			'https://example.com/sample-page',
			'index,follow'
		);

		$analyzer = new Analyzer();
		$result   = $analyzer->analyze( $context );

		self::assertSame( Result::STATUS_PASS, $result['status'] );
		self::assertSame( count( $result['checks'] ), $result['summary']['total'] );
		self::assertArrayHasKey( 'title_length', $result['checks'] );
	}

	/**
	 * Ensures a failing check downgrades the overall analyzer status.
	 */
	public function test_overall_status_fails_when_any_check_fails(): void {
		$context = new Context( null, '<p>Example</p>' );

		$analyzer = new Analyzer(
			array(
				$this->create_stub_check( 'alpha', Result::STATUS_PASS, 0.6 ),
				$this->create_stub_check( 'beta', Result::STATUS_FAIL, 0.4 ),
			)
		);

		$result = $analyzer->analyze( $context );

		self::assertSame( Result::STATUS_FAIL, $result['status'] );
		self::assertSame( 1, $result['summary'][ Result::STATUS_FAIL ] );
		self::assertSame( 2, $result['summary']['total'] );
		self::assertSame( 'Resolve beta issues', $result['checks']['beta']['fix_hint'] );
	}

	/**
	 * Ensures warnings surface when no failures are present.
	 */
	public function test_overall_status_warns_when_only_warnings_present(): void {
		$context = new Context( null, '<p>Example</p>' );

		$analyzer = new Analyzer(
			array(
				$this->create_stub_check( 'alpha', Result::STATUS_PASS, 0.5 ),
				$this->create_stub_check( 'beta', Result::STATUS_WARN, 0.5 ),
			)
		);

		$result = $analyzer->analyze( $context );

		self::assertSame( Result::STATUS_WARN, $result['status'] );
		self::assertSame( 1, $result['summary'][ Result::STATUS_WARN ] );
		self::assertSame( 2, $result['summary']['total'] );
		self::assertArrayHasKey( 'beta', $result['checks'] );
	}

	/**
	 * Provides an analyzer check stub.
	 *
	 * @param string $id     Check identifier.
	 * @param string $status Result status.
	 * @param float  $weight Result weight.
	 */
	private function create_stub_check( string $id, string $status, float $weight ): CheckInterface {
			$mock = $this->createMock( CheckInterface::class );

			$mock->method( 'id' )->willReturn( $id );
			$mock->method( 'label' )->willReturn( ucfirst( $id ) );
			$mock->method( 'description' )->willReturn( 'Stub description' );
			$mock->method( 'run' )->willReturn(
				new Result(
					$status,
					array( 'checked' => $id ),
					'Resolve ' . $id . ' issues',
					$weight
				)
			);

			return $mock;
	}
}
