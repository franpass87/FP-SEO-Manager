<?php
/**
 * Check image filename quality for SEO.
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

/**
 * Flags generic image filenames (IMG_*, DSC_*, screenshot...).
 */
class ImageFilenameCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'image_filename';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Image filename quality' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Warns when images use generic filenames that reduce semantic relevance.' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( Context $context ): Result {
		$images = $context->images();
		if ( empty( $images ) ) {
			return new Result( Result::STATUS_WARN, array( 'bad' => 0, 'total' => 0 ), I18n::translate( '⚠️ Nessuna immagine trovata per analizzare i filename.' ), 0.05 );
		}

		$bad = 0;
		foreach ( $images as $image ) {
			$src = (string) $image->getAttribute( 'src' );
			$path = wp_parse_url( $src, PHP_URL_PATH );
			$name = strtolower( basename( (string) $path ) );
			if ( '' === $name ) {
				continue;
			}
			if ( preg_match( '/^(img|dsc|image|screenshot|photo)[\-_]?[0-9]*/', $name ) ) {
				++$bad;
			}
		}

		$total = count( $images );
		if ( 0 === $bad ) {
			return new Result( Result::STATUS_PASS, array( 'bad' => 0, 'total' => $total ), I18n::translate( '✅ Filename immagini descrittivi.' ), 0.05 );
		}

		$status = $bad > ( $total / 2 ) ? Result::STATUS_FAIL : Result::STATUS_WARN;
		$hint   = sprintf(
			/* translators: 1: generic file count, 2: total count */
			I18n::translate( '⚠️ %1$d/%2$d immagini hanno filename generici (es. IMG_1234). Rinominale in modo descrittivo.' ),
			$bad,
			$total
		);

		return new Result( $status, array( 'bad' => $bad, 'total' => $total ), $hint, 0.05 );
	}
}

