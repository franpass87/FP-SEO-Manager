<?php
/**
 * Check for schema.org preset coverage.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis\Checks;

use FP\SEO\Analysis\CheckInterface;
use FP\SEO\Analysis\Context;
use FP\SEO\Analysis\Result;
use FP\SEO\Utils\I18n;
use Throwable;
use function array_intersect;
use function array_map;
use function array_unique;
use function in_array;
use function is_array;
use function json_decode;
use function strtolower;
use function ucfirst;
use const JSON_THROW_ON_ERROR;

/**
 * Validates schema.org JSON-LD presets.
 */
class SchemaPresetsCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'schema_presets';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'Schema presets' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Checks for Organization, WebSite, or BlogPosting schema presets.' );
	}

	/**
	 * Evaluate schema.org preset coverage.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$blocks = $context->json_ld_blocks();
		$types  = array();

		foreach ( $blocks as $block ) {
			try {
				$decoded = json_decode( $block, true, 512, JSON_THROW_ON_ERROR );
			} catch ( Throwable $exception ) {
				unset( $exception );
				continue;
			}

			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$this->collect_types( $decoded, $types );
		}

		$types    = array_unique( array_map( 'strtolower', $types ) );
		$expected = array( 'organization', 'website', 'blogposting' );
		$found    = array_intersect( $expected, $types );
		$missing  = array();

		foreach ( $expected as $candidate ) {
			if ( ! in_array( $candidate, $types, true ) ) {
				$missing[] = ucfirst( $candidate );
			}
		}

		if ( empty( $types ) || empty( $found ) ) {
			return new Result(
				Result::STATUS_FAIL,
				array(
					'found'   => $types,
					'missing' => array( 'Organization', 'WebSite', 'BlogPosting' ),
				),
				I18n::translate( 'Add schema.org JSON-LD for Organization, WebSite, or BlogPosting.' ),
				0.12
			);
		}

		if ( empty( $missing ) ) {
			return new Result(
				Result::STATUS_PASS,
				array(
					'found'   => $types,
					'missing' => array(),
				),
				I18n::translate( 'Required schema.org presets detected.' ),
				0.12
			);
		}

		return new Result(
			Result::STATUS_WARN,
			array(
				'found'   => $types,
				'missing' => $missing,
			),
			I18n::translate( 'Add the remaining schema presets for richer snippets.' ),
			0.12
		);
	}

	/**
	 * Recursively gather @type values from JSON-LD payloads.
	 *
	 * @param mixed              $payload JSON-LD value.
	 * @param array<int, string> $types  Aggregated type list.
	 */
	private function collect_types( $payload, array &$types ): void {
		if ( is_array( $payload ) ) {
			if ( isset( $payload['@type'] ) ) {
				if ( is_array( $payload['@type'] ) ) {
					foreach ( $payload['@type'] as $type ) {
						$types[] = (string) $type;
					}
				} else {
					$types[] = (string) $payload['@type'];
				}
			}

			foreach ( $payload as $value ) {
				$this->collect_types( $value, $types );
			}
		}
	}
}
