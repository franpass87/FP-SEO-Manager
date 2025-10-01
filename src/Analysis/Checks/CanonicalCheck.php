<?php
/**
 * Check for canonical tag presence and validity.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use function filter_var;
use function trim;
use const FILTER_VALIDATE_URL;

/**
 * Validates canonical tag presence and URL validity.
 */
class CanonicalCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'canonical';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Canonical URL' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks if a canonical URL is defined and valid.' );
	}

	/**
	 * Evaluate canonical tag presence and validity.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$canonical = trim( (string) $context->canonical() );

		if ( '' === $canonical ) {
			$canonical = (string) $context->link_href( 'canonical' );
		}

		$canonical = trim( $canonical );

		if ( '' === $canonical ) {
			return new Result(
				Result::STATUS_FAIL,
				array(
					'canonical' => '',
				),
				I18n::translate( 'Set a canonical URL to avoid duplicate content issues.' ),
				0.10
			);
		}

		if ( false === filter_var( $canonical, FILTER_VALIDATE_URL ) ) {
			return new Result(
				Result::STATUS_FAIL,
				array(
					'canonical' => $canonical,
				),
				I18n::translate( 'Canonical URL must be an absolute, valid URL.' ),
				0.10
			);
		}

		return new Result(
			Result::STATUS_PASS,
			array(
				'canonical' => $canonical,
			),
			I18n::translate( 'Canonical tag looks valid.' ),
			0.10
		);
	}
}
