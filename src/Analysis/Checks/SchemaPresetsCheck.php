<?php
/**
 * Check for schema.org preset coverage.
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
use Throwable;
use function array_intersect;
use function array_map;
use function array_unique;
use function implode;
use function in_array;
use function is_array;
use function json_decode;
use function strtolower;
use function strpos;
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
		return I18n::translate( 'Verifica la presenza di schema.org per Organization, WebSite, Article e BlogPosting. Include supporto per speakable markup (importante per AI vocali).' );
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
		$expected = array( 'organization', 'website', 'blogposting', 'article' );
		$found    = array_intersect( $expected, $types );
		$missing  = array();
		
		// Check for speakable markup in Article/BlogPosting (important for AI voice)
		$has_speakable = $this->check_speakable_markup( $blocks );

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
					'missing' => array( 'Organization', 'WebSite', 'Article', 'BlogPosting' ),
					'has_speakable' => false,
				),
				I18n::translate( 'Aggiungi schema.org JSON-LD per Organization, WebSite, Article o BlogPosting. Fondamentale per la visibilità nelle ricerche AI.' ),
				0.12
			);
		}

		if ( empty( $missing ) ) {
			$message = I18n::translate( 'Schema.org presets rilevati correttamente.' );
			
			if ( $has_speakable ) {
				$message .= ' ' . I18n::translate( 'Include speakable markup - ottimale per AI vocali e Google Assistant.' );
			}
			
			return new Result(
				Result::STATUS_PASS,
				array(
					'found'   => $types,
					'missing' => array(),
					'has_speakable' => $has_speakable,
				),
				$message,
				0.12
			);
		}

		$message = I18n::translate( 'Aggiungi gli schema mancanti per rich snippet più completi: ' . implode( ', ', $missing ) . '.' );
		
		if ( ! $has_speakable && ( in_array( 'article', $types, true ) || in_array( 'blogposting', $types, true ) ) ) {
			$message .= ' ' . I18n::translate( 'Considera di aggiungere speakable markup per ottimizzare per ricerche vocali e AI.' );
		}
		
		return new Result(
			Result::STATUS_WARN,
			array(
				'found'   => $types,
				'missing' => $missing,
				'has_speakable' => $has_speakable,
			),
			$message,
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

	/**
	 * Check for speakable markup in JSON-LD blocks.
	 *
	 * Speakable markup indica a Google quali parti del contenuto sono ottimali
	 * per essere lette ad alta voce dall'assistente vocale.
	 *
	 * @param array<int, string> $blocks JSON-LD blocks.
	 * @return bool
	 */
	private function check_speakable_markup( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( strpos( $block, '"speakable"' ) !== false || strpos( $block, "'speakable'" ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
