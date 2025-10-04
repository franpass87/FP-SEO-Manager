<?php
/**
 * Check for internal link density.
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
use function function_exists;
use function home_url;
use function ceil;
use function count;
use function in_array;
use function max;
use function parse_url; // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
use function preg_match;
use function preg_split;
use function strtolower;
use function str_starts_with;
use function trim;
use function wp_parse_url;
use const PREG_SPLIT_NO_EMPTY;

/**
 * Validates internal linking coverage.
 */
class InternalLinksCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'internal_links';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Internal links' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Ensures the content includes enough contextual internal links.' );
	}

		/**
		 * Evaluate internal link density.
		 *
		 * @param Context $context Analyzer context payload.
		 *
		 * @return Result
		 */
	public function run( Context $context ): Result {
			$text  = $context->plain_text();
			$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

		if ( false === $words ) {
				$words = array();
		}

				$word_count = count( $words );
				$anchors    = $context->anchors();
				$link_count = 0;

				$site_host = '';
				$site_url  = home_url( '/' );

		if ( '' !== $site_url ) {
						$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $site_url ) : parse_url( $site_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

			if ( is_array( $parts ) && isset( $parts['host'] ) ) {
										$site_host = strtolower( (string) $parts['host'] );
			}
		}

		foreach ( $anchors as $anchor ) {
				$href = trim( (string) $anchor->getAttribute( 'href' ) );

			if ( '' === $href ) {
						continue;
			}

			if ( str_starts_with( $href, '#' ) ) {
							continue;
			}

			if ( preg_match( '#^(mailto:|tel:|javascript:)#i', $href ) ) {
				continue;
			}

								$parsed = function_exists( 'wp_parse_url' ) ? wp_parse_url( $href ) : parse_url( $href ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

			if ( false === $parsed ) {
				continue;
			}

			if ( isset( $parsed['host'] ) && '' !== $parsed['host'] ) {
				if ( '' === $site_host ) {
						continue;
				}

				if ( strtolower( (string) $parsed['host'] ) !== $site_host ) {
						continue;
				}
			}

			if ( isset( $parsed['scheme'] ) && '' !== $parsed['scheme'] ) {
				$scheme = strtolower( (string) $parsed['scheme'] );

				if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
						continue;
				}
			}

							++$link_count;
		}

			$required = 0;

		if ( $word_count >= 150 ) {
					$required = max( 1, (int) ceil( $word_count / 300 ) );
		}

		if ( 0 === $required ) {
				return new Result(
					Result::STATUS_PASS,
					array(
						'word_count' => $word_count,
						'links'      => $link_count,
						'required'   => $required,
					),
					I18n::translate( 'Content length is short; internal links optional.' ),
					0.10
				);
		}

		if ( $link_count >= $required ) {
				return new Result(
					Result::STATUS_PASS,
					array(
						'word_count' => $word_count,
						'links'      => $link_count,
						'required'   => $required,
					),
					I18n::translate( 'Internal link coverage meets recommendations.' ),
					0.10
				);
		}

				$status = 0 === $link_count ? Result::STATUS_FAIL : Result::STATUS_WARN;
				$hint   = 0 === $link_count
					? I18n::translate( 'Add contextual internal links to related content.' )
					: I18n::translate( 'Add a few more internal links to boost discoverability.' );

				return new Result(
					$status,
					array(
						'word_count' => $word_count,
						'links'      => $link_count,
						'required'   => $required,
					),
					$hint,
					0.10
				);
	}
}
