<?php
/**
 * Check for FAQ Schema markup - Essential for Google AI Overview.
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
use function array_unique;
use function count;
use function in_array;
use function is_array;
use function json_decode;
use function sprintf;
use function strtolower;
use const JSON_THROW_ON_ERROR;

/**
 * Validates FAQ Schema for Google AI Overview optimization.
 *
 * FAQ Schema è uno dei fattori più importanti per apparire nelle AI Overview
 * di Google, permettendo ai contenuti di essere estratti come risposte dirette.
 */
class FaqSchemaCheck implements CheckInterface {
	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'faq_schema';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return I18n::translate( 'FAQ Schema per AI Overview' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return I18n::translate( 'Verifica la presenza di FAQ Schema markup, essenziale per apparire nelle Google AI Overview e nelle ricerche conversazionali.' );
	}

	/**
	 * Evaluate FAQ Schema presence and quality.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result
	 */
	public function run( Context $context ): Result {
		$blocks = $context->json_ld_blocks();
		$types  = array();
		$faq_data = array();

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

			$this->collect_faq_data( $decoded, $types, $faq_data );
		}

		$types = array_unique( array_map( 'strtolower', $types ) );
		$has_faq = in_array( 'faqpage', $types, true );

		// Nessun FAQ Schema trovato
		// FAQ è opzionale - non penalizzare se manca, ma suggerisci se potrebbe essere utile
		if ( ! $has_faq ) {
			// Verifica se il contenuto potrebbe beneficiare di FAQ
			$content = strtolower( $context->plain_text() );
			$might_benefit = $this->might_benefit_from_faq( $content );
			
			if ( $might_benefit ) {
				// Contenuto potrebbe beneficiare di FAQ - warning soft
				return new Result(
					Result::STATUS_WARN,
					array(
						'has_faq' => false,
						'questions' => 0,
						'recommendation' => 'add_faq_schema',
						'might_benefit' => true,
					),
					I18n::translate( 'Considera di aggiungere FAQ Schema per migliorare la visibilità nelle AI Overview di Google. Le FAQ aumentano le probabilità di apparire come risposta diretta.' ),
					0.10
				);
			} else {
				// Contenuto non sembra beneficiare di FAQ - passa senza penalizzazione
				return new Result(
					Result::STATUS_PASS,
					array(
						'has_faq' => false,
						'questions' => 0,
						'note' => 'not_applicable',
						'might_benefit' => false,
					),
					I18n::translate( 'FAQ Schema non necessario per questo tipo di contenuto.' ),
					0.10
				);
			}
		}

		// FAQ Schema presente - verifica qualità
		$question_count = count( $faq_data );

		if ( $question_count < 3 ) {
			return new Result(
				Result::STATUS_WARN,
				array(
					'has_faq' => true,
					'questions' => $question_count,
					'recommendation' => 'add_more_questions',
				),
				I18n::translate( 'FAQ Schema rilevato ma con poche domande. Aggiungi almeno 3-5 domande per massimizzare la visibilità nelle AI Overview.' ),
				0.10
			);
		}

		// FAQ Schema ottimale
		return new Result(
			Result::STATUS_PASS,
			array(
				'has_faq' => true,
				'questions' => $question_count,
				'optimization' => 'optimal_for_ai',
			),
			I18n::translate( sprintf( 'Ottimo! FAQ Schema rilevato con %d domande. Questo migliora significativamente le probabilità di apparire nelle Google AI Overview.', $question_count ) ),
			0.10
		);
	}

	/**
	 * Recursively collect FAQ data from JSON-LD payloads.
	 *
	 * @param mixed              $payload JSON-LD value.
	 * @param array<int, string> $types  Aggregated type list.
	 * @param array<int, array>  $faq_data FAQ questions and answers.
	 */
	private function collect_faq_data( $payload, array &$types, array &$faq_data ): void {
		if ( is_array( $payload ) ) {
			if ( isset( $payload['@type'] ) ) {
				if ( is_array( $payload['@type'] ) ) {
					foreach ( $payload['@type'] as $type ) {
						$types[] = (string) $type;
					}
				} else {
					$types[] = (string) $payload['@type'];
				}

				// Se è FAQPage, raccoglie le domande
				if ( strtolower( (string) $payload['@type'] ) === 'faqpage' && isset( $payload['mainEntity'] ) ) {
					if ( is_array( $payload['mainEntity'] ) ) {
						foreach ( $payload['mainEntity'] as $entity ) {
							if ( is_array( $entity ) && isset( $entity['@type'] ) && strtolower( (string) $entity['@type'] ) === 'question' ) {
								$faq_data[] = $entity;
							}
						}
					}
				}
			}

			foreach ( $payload as $value ) {
				$this->collect_faq_data( $value, $types, $faq_data );
			}
		}
	}

	/**
	 * Check if content might benefit from FAQ Schema.
	 *
	 * @param string $content Plain text content.
	 * @return bool
	 */
	private function might_benefit_from_faq( string $content ): bool {
		// FAQ indicators - content that might benefit from FAQ
		$faq_indicators = array(
			'domanda',
			'risposta',
			'frequently asked',
			'domande frequenti',
			'faq',
			'chiedi',
			'vuoi sapere',
			'curiosità',
			'dubbio',
			'perché',
			'come mai',
			'quando',
			'dove',
			'chi',
			'cosa',
		);

		foreach ( $faq_indicators as $indicator ) {
			if ( strpos( $content, $indicator ) !== false ) {
				return true;
			}
		}

		return false;
	}
}