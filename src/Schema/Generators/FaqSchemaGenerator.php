<?php
/**
 * FAQ schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use function get_post_meta;
use function sanitize_text_field;
use function wp_kses_post;

/**
 * Generates FAQPage schema.
 */
class FaqSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate FAQPage schema.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		if ( ! $post_id ) {
			return [];
		}

		// Use Q&A pairs as single source of truth (unified system)
		$qa_pairs = get_post_meta( $post_id, '_fp_seo_qa_pairs', true );
		
		if ( empty( $qa_pairs ) || ! is_array( $qa_pairs ) ) {
			return [];
		}

		$main_entity = array();
		foreach ( $qa_pairs as $pair ) {
			// Extract only question and answer for FAQ Schema
			$question = isset( $pair['question'] ) ? $pair['question'] : '';
			$answer = isset( $pair['answer'] ) ? $pair['answer'] : '';
			
			if ( empty( $question ) || empty( $answer ) ) {
				continue;
			}

			$main_entity[] = array(
				'@type' => 'Question',
				'name' => sanitize_text_field( $question ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text' => wp_kses_post( $answer ),
				),
			);
		}

		if ( empty( $main_entity ) ) {
			return array();
		}

		$schema = $this->build_base_schema();
		$schema['mainEntity'] = $main_entity;

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'FAQPage';
	}
}
















