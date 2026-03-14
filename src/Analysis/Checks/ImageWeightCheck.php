<?php
/**
 * Check heavy image assets.
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
 * Flags attachments with large original file size.
 */
class ImageWeightCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'image_weight';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Image weight' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks if embedded images are too heavy for fast loading.' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( Context $context ): Result {
		$images = $context->images();
		if ( empty( $images ) ) {
			return new Result( Result::STATUS_WARN, array( 'heavy' => 0, 'total' => 0 ), I18n::translate( '⚠️ Nessuna immagine trovata per analizzare il peso.' ), 0.05 );
		}

		$heavy = 0;
		$total_with_meta = 0;

		foreach ( $images as $image ) {
			$class = (string) $image->getAttribute( 'class' );
			if ( ! preg_match( '/wp-image-([0-9]+)/', $class, $matches ) ) {
				continue;
			}

			$attachment_id = (int) ( $matches[1] ?? 0 );
			if ( $attachment_id < 1 ) {
				continue;
			}

			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! is_array( $meta ) ) {
				continue;
			}

			$size_bytes = isset( $meta['filesize'] ) ? (int) $meta['filesize'] : 0;
			if ( $size_bytes <= 0 ) {
				continue;
			}

			++$total_with_meta;
			if ( $size_bytes > 350000 ) {
				++$heavy;
			}
		}

		if ( 0 === $total_with_meta ) {
			return new Result( Result::STATUS_WARN, array( 'heavy' => 0, 'total' => count( $images ) ), I18n::translate( '⚠️ Impossibile leggere il peso file dalle immagini presenti.' ), 0.05 );
		}

		if ( 0 === $heavy ) {
			return new Result( Result::STATUS_PASS, array( 'heavy' => 0, 'total' => $total_with_meta ), I18n::translate( '✅ Peso immagini nella soglia consigliata.' ), 0.05 );
		}

		$status = $heavy > ( $total_with_meta / 2 ) ? Result::STATUS_FAIL : Result::STATUS_WARN;
		$hint   = sprintf(
			/* translators: 1: heavy images, 2: images analyzed */
			I18n::translate( '⚠️ %1$d/%2$d immagini superano ~350KB. Considera WebP/AVIF o compressione.' ),
			$heavy,
			$total_with_meta
		);

		return new Result( $status, array( 'heavy' => $heavy, 'total' => $total_with_meta ), $hint, 0.05 );
	}
}

