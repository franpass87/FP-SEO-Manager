<?php
/**
 * Check for Twitter card metadata.
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
use function count;
use function implode;
use function sprintf;
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
				$required = array( 'twitter:card', 'twitter:title', 'twitter:description', 'twitter:image' );
				$missing  = array();
				$present  = array();

		foreach ( $required as $key ) {
				$value = (string) $context->meta_content( 'name', $key );

			if ( 'twitter:image' === $key && '' === trim( $value ) ) {
						$value = (string) $context->meta_content( 'name', 'twitter:image:src' );
			}

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
				sprintf(
					/* translators: %d: count of tags */
					I18n::translate( '✅ Completo! Tutti i %d Twitter Card tag sono presenti.' ),
					count( $required )
				),
				0.06
			);
		}

		$missing_count = count( $missing );
		$status        = $missing_count >= 2 ? Result::STATUS_FAIL : Result::STATUS_WARN;
		$missing_list  = implode( ', ', $missing );
		
		if ( $missing_count >= 2 ) {
			$hint = sprintf(
				/* translators: 1: count of missing tags, 2: list of missing tags */
				I18n::translate( '❌ Mancano %1$d Twitter Card tag: %2$s. Aggiungili!' ),
				$missing_count,
				$missing_list
			);
		} else {
			$hint = sprintf(
				/* translators: 1: list of missing tags */
				I18n::translate( '⚠️ Manca solo: %1$s. Aggiungilo per completare!' ),
				$missing_list
			);
		}

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
