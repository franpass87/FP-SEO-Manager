<?php
/**
 * Check for image alt attribute coverage.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use DOMElement;
use function count;
use function round;

/**
 * Validates whether images contain descriptive alt attributes.
 */
class ImageAltCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'image_alt';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Image alt text' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Ensures images include descriptive alt text for accessibility and SEO.' );
	}

	/**
	 * Evaluate image alt attribute coverage.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$images = $context->images();
		$total  = count( $images );

		if ( 0 === $total ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'coverage' => 0,
					'total'    => 0,
				),
				I18n::translate( 'Add relevant images with alt text to enrich the page.' ),
				0.08
			);
		}

		$with_alt = 0;

		foreach ( $images as $image ) {
			$alt = trim( (string) $image->getAttribute( 'alt' ) );

			if ( '' !== $alt ) {
				++$with_alt;
			}
		}

		$coverage = round( ( $with_alt / $total ) * 100 );
		$status   = Result::STATUS_PASS;
		$hint     = I18n::translate( 'Image alt coverage looks healthy.' );

		if ( $coverage < 80 ) {
			$status = Result::STATUS_WARN;
			$hint   = I18n::translate( 'Add alt attributes describing the image content.' );
		}

		if ( $coverage < 50 ) {
			$status = Result::STATUS_FAIL;
			$hint   = I18n::translate( 'Most images are missing alt text. Update them for accessibility.' );
		}

		return new Result(
			$status,
			array(
				'coverage' => $coverage,
				'total'    => $total,
				'with_alt' => $with_alt,
			),
			$hint,
			0.08
		);
	}
}
