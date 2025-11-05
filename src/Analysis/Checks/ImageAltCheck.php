<?php
/**
 * Check for image alt attribute coverage.
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
use DOMElement;
use function count;
use function round;
use function sprintf;

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
				I18n::translate( '⚠️ Nessuna immagine trovata. Aggiungi immagini con alt text.' ),
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

		$missing  = $total - $with_alt;
		$coverage = round( ( $with_alt / $total ) * 100 );
		$status   = Result::STATUS_PASS;
		$hint     = sprintf(
			/* translators: 1: images with alt, 2: total images, 3: coverage percentage */
			I18n::translate( '✅ Perfetto! %1$d/%2$d immagini con alt text (%3$d%%)' ),
			$with_alt,
			$total,
			$coverage
		);

		if ( $coverage < 80 ) {
			$status = Result::STATUS_WARN;
			$hint   = sprintf(
				/* translators: 1: missing images, 2: total images, 3: coverage percentage */
				I18n::translate( '⚠️ Mancano %1$d alt text su %2$d immagini (%3$d%%). Aggiungili per raggiungere 80%%+' ),
				$missing,
				$total,
				$coverage
			);
		}

		if ( $coverage < 50 ) {
			$status = Result::STATUS_FAIL;
			$hint   = sprintf(
				/* translators: 1: missing images, 2: total images, 3: coverage percentage */
				I18n::translate( '❌ Solo %3$d%% coperto! Aggiungi alt text a %1$d immagini su %2$d (serve 80%%+)' ),
				$missing,
				$total,
				$coverage
			);
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
