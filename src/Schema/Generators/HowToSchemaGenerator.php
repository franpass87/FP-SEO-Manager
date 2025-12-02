<?php
/**
 * HowTo schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use function esc_url;
use function get_post_meta;
use function get_the_excerpt;
use function get_the_title;
use function sanitize_text_field;
use function wp_kses_post;

/**
 * Generates HowTo schema.
 */
class HowToSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate HowTo schema.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public function generate( ?int $post_id = null ): ?array {
		if ( ! $post_id ) {
			return null;
		}

		$howto_data = get_post_meta( $post_id, '_fp_seo_howto', true );
		
		if ( empty( $howto_data ) || ! is_array( $howto_data ) ) {
			return null;
		}

		$schema = $this->build_base_schema();
		$schema['name'] = sanitize_text_field( $howto_data['name'] ?? get_the_title( $post_id ) );
		$schema['description'] = wp_kses_post( $howto_data['description'] ?? get_the_excerpt( $post_id ) );

		// Add steps
		if ( ! empty( $howto_data['steps'] ) && is_array( $howto_data['steps'] ) ) {
			$schema['step'] = array();
			foreach ( $howto_data['steps'] as $index => $step ) {
				$schema['step'][] = array(
					'@type' => 'HowToStep',
					'position' => $index + 1,
					'name' => sanitize_text_field( $step['name'] ?? '' ),
					'text' => wp_kses_post( $step['text'] ?? '' ),
					'url' => ! empty( $step['url'] ) ? esc_url( $step['url'] ) : null,
				);
			}
		}

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'HowTo';
	}
}


