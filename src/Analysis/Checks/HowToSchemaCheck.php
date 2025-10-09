<?php
/**
 * Check for HowTo Schema markup - Important for Google AI Overview.
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
use function array_map;
use function count;
use function is_array;
use function json_decode;
use function strtolower;
use const JSON_THROW_ON_ERROR;

/**
 * Validates HowTo Schema for Google AI Overview optimization.
 *
 * HowTo Schema aiuta Google a comprendere guide e tutorial step-by-step,
 * migliorando la visibilità nelle AI Overview per query procedurali.
 */
class HowToSchemaCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'howto_schema';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'HowTo Schema per AI Overview' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Verifica la presenza di HowTo Schema markup per guide e tutorial, importante per apparire nelle AI Overview con contenuti procedurali.' );
	}

	/**
	 * Evaluate HowTo Schema presence and quality.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$blocks = $context->json_ld_blocks();
		$types  = array();
		$howto_data = array();

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

			$this->collect_howto_data( $decoded, $types, $howto_data );
		}

		$types = array_unique( array_map( 'strtolower', $types ) );
		$has_howto = in_array( 'howto', $types, true );

		// Verifica se il contenuto sembra essere una guida
		$content = strtolower( $context->plain_text() );
		$is_guide = $this->seems_like_guide( $content );

		// Nessun HowTo Schema ma sembra una guida
		if ( ! $has_howto && $is_guide ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'has_howto' => false,
					'is_guide' => true,
					'steps' => 0,
					'recommendation' => 'add_howto_schema',
				),
				I18n::translate( 'Questo contenuto sembra una guida. Aggiungi HowTo Schema per migliorare la visibilità nelle AI Overview per query come "come fare" o "guida a".' ),
				0.08
			);
		}

		// Nessun HowTo Schema e non sembra una guida
		if ( ! $has_howto ) {
			return new Result(
				Result::STATUS_PASS,
				array(
					'has_howto' => false,
					'is_guide' => false,
					'note' => 'not_applicable',
				),
				I18n::translate( 'HowTo Schema non necessario per questo tipo di contenuto.' ),
				0.08
			);
		}

		// HowTo Schema presente - verifica qualità
		$step_count = count( $howto_data );

		if ( $step_count < 3 ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'has_howto' => true,
					'steps' => $step_count,
					'recommendation' => 'add_more_steps',
				),
				I18n::translate( 'HowTo Schema rilevato ma con pochi step. Le guide con 3+ step ben strutturati hanno maggiore visibilità nelle AI Overview.' ),
				0.08
			);
		}

		// HowTo Schema ottimale
		return new Result(
			Result::STATUS_PASS,
			array(
				'has_howto' => true,
				'steps' => $step_count,
				'optimization' => 'optimal_for_ai',
			),
			I18n::translate( sprintf( 'Eccellente! HowTo Schema rilevato con %d step. Ottimizzato per le AI Overview di Google.', $step_count ) ),
			0.08
		);
	}

	/**
	 * Recursively collect HowTo data from JSON-LD payloads.
	 *
	 * @param mixed              $payload JSON-LD value.
	 * @param array<int, string> $types  Aggregated type list.
	 * @param array<int, array>  $howto_data HowTo steps.
	 */
	private function collect_howto_data( $payload, array &$types, array &$howto_data ): void {
		if ( is_array( $payload ) ) {
			if ( isset( $payload['@type'] ) ) {
				if ( is_array( $payload['@type'] ) ) {
					foreach ( $payload['@type'] as $type ) {
						$types[] = (string) $type;
					}
				} else {
					$types[] = (string) $payload['@type'];
				}

				// Se è HowTo, raccoglie gli step
				if ( strtolower( (string) $payload['@type'] ) === 'howto' && isset( $payload['step'] ) ) {
					if ( is_array( $payload['step'] ) ) {
						foreach ( $payload['step'] as $step ) {
							if ( is_array( $step ) && isset( $step['@type'] ) && strtolower( (string) $step['@type'] ) === 'howtoStep' ) {
								$howto_data[] = $step;
							}
						}
					}
				}
			}

			foreach ( $payload as $value ) {
				$this->collect_howto_data( $value, $types, $howto_data );
			}
		}
	}

	/**
	 * Check if content seems like a guide or tutorial.
	 *
	 * @param string $content Plain text content.
	 * @return bool
	 */
	private function seems_like_guide( string $content ): bool {
		$guide_indicators = array(
			'come fare',
			'guida a',
			'tutorial',
			'passo',
			'step',
			'fase',
			'procedura',
			'istruzioni',
			'passaggio 1',
			'passo 1',
			'primo passo',
			'prima fase',
		);

		foreach ( $guide_indicators as $indicator ) {
			if ( strpos( $content, $indicator ) !== false ) {
				return true;
			}
		}

		return false;
	}
}