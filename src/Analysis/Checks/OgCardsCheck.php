<?php
/**
 * Check for Open Graph metadata presence.
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
				$required = array( 'og:title', 'og:description', 'og:type', 'og:url', 'og:image' );
				$missing  = array();
				$present  = array();

		foreach ( $required as $key ) {
				$value = (string) $context->meta_content( 'property', $key );

			if ( 'og:image' === $key && '' === trim( $value ) ) {
						$value = (string) $context->meta_content( 'property', 'og:image:secure_url' );
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
					I18n::translate( '✅ Perfetto! Tutti i %d Open Graph tag richiesti sono presenti.' ),
					count( $required )
				),
				0.08
			);
		}

		$missing_count = count( $missing );
		$status        = $missing_count > 2 ? Result::STATUS_FAIL : Result::STATUS_WARN;
		$missing_list  = implode( ', ', $missing );
		
		if ( $missing_count > 2 ) {
			$hint = sprintf(
				/* translators: 1: count of missing tags, 2: list of missing tags */
				I18n::translate( '❌ Mancano %1$d Open Graph tag: %2$s. Aggiungili tutti!' ),
				$missing_count,
				$missing_list
			);
		} else {
			$hint = sprintf(
				/* translators: 1: count of missing tags, 2: list of missing tags */
				I18n::translate( '⚠️ Manca %1$d tag: %2$s. Aggiungilo per completare al 100%%!' ),
				$missing_count,
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
			0.08
		);
	}
}
