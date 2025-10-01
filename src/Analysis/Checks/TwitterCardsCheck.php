<?php
/**
 * Check for Twitter card metadata.
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
 * Validates Twitter card tags.
 */
class TwitterCardsCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'twitter_cards';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Twitter cards' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks presence of essential Twitter card tags.' );
	}

	/**
	 * Evaluate Twitter card metadata coverage.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$required = array( 'twitter:card', 'twitter:title', 'twitter:description' );
		$missing  = array();
		$present  = array();

		foreach ( $required as $key ) {
			$value = (string) $context->meta_content( 'name', $key );

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
				I18n::translate( 'Twitter card metadata looks complete.' ),
				0.06
			);
		}

		$status = count( $missing ) >= 2 ? Result::STATUS_FAIL : Result::STATUS_WARN;
		$hint   = I18n::translate( 'Add missing Twitter card tags to control social previews.' );

		return new Result(
			$status,
			array(
				'missing' => $missing,
				'tags'    => $present,
			),
			$hint,
			0.06
		);
	}
}
