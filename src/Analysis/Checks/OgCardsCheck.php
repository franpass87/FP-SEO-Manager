<?php
/**
 * Check for Open Graph metadata presence.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use function count;
use function trim;

/**
 * Validates Open Graph tags for social sharing.
 */
class OgCardsCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'og_cards';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Open Graph tags' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks presence of essential Open Graph tags.' );
	}

	/**
	 * Evaluate Open Graph metadata coverage.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$required = array( 'og:title', 'og:description', 'og:type', 'og:url' );
		$missing  = array();
		$present  = array();

		foreach ( $required as $key ) {
			$value = (string) $context->meta_content( 'property', $key );

			if ( '' === trim( $value ) ) {
				$missing[] = $key;
				continue;
			}

			$present[ $key ] = $value;
		}

		if ( empty( $missing ) ) {
			return new Result(
				Result::STATUS_PASS,
				array(
					'tags' => $present,
				),
				I18n::translate( 'Required Open Graph tags detected.' ),
				0.08
			);
		}

		$status = count( $missing ) > 2 ? Result::STATUS_FAIL : Result::STATUS_WARN;
		$hint   = I18n::translate( 'Add missing Open Graph tags for better social sharing previews.' );

		return new Result(
			$status,
			array(
				'missing' => $missing,
				'tags'    => $present,
			),
			$hint,
			0.08
		);
	}
}
