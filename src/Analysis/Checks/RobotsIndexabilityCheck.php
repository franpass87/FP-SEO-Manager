<?php
/**
 * Check for robots directives that affect indexability.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use function array_filter;
use function array_map;
use function explode;
use function in_array;
use function strtolower;
use function trim;

/**
 * Validates robots meta directives.
 */
class RobotsIndexabilityCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'robots_indexability';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Robots directives' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Ensures robots directives allow search engines to index the page.' );
	}

	/**
	 * Evaluate robots directives for indexability.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$robots = $context->robots();

		if ( null === $robots || '' === trim( $robots ) ) {
			$robots = (string) $context->meta_content( 'name', 'robots' );
		}

		$robots = trim( strtolower( (string) $robots ) );

		if ( '' === $robots ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'directive' => '',
				),
				I18n::translate( 'No robots meta tag found. Ensure indexable pages are accessible.' ),
				0.10
			);
		}

		$parts = array_map( 'trim', explode( ',', $robots ) );
		$parts = array_filter( $parts );

		if ( in_array( 'noindex', $parts, true ) ) {
			return new Result(
				Result::STATUS_FAIL,
				array(
					'directive' => $robots,
				),
				I18n::translate( 'Remove the noindex directive to allow search engines to index this page.' ),
				0.10
			);
		}

		if ( in_array( 'nofollow', $parts, true ) ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'directive' => $robots,
				),
				I18n::translate( 'Consider removing nofollow to let link equity flow from this page.' ),
				0.10
			);
		}

		return new Result(
			Result::STATUS_PASS,
			array(
				'directive' => $robots,
			),
			I18n::translate( 'Robots directives allow indexing.' ),
			0.10
		);
	}
}
