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
	 * @return array<string, mixed>|null
	 */
	public function generate( ?int $post_id = null ): ?array {
		if ( ! $post_id ) {
			return null;
		}

		$faq_questions = get_post_meta( $post_id, '_fp_seo_faq_questions', true );
		
		if ( empty( $faq_questions ) || ! is_array( $faq_questions ) ) {
			return null;
		}

		$main_entity = array();
		foreach ( $faq_questions as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}

			$main_entity[] = array(
				'@type' => 'Question',
				'name' => sanitize_text_field( $faq['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text' => wp_kses_post( $faq['answer'] ),
				),
			);
		}

		if ( empty( $main_entity ) ) {
			return null;
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


