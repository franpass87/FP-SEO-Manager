<?php
/**
 * ContactPage schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use function get_permalink;
use function get_the_excerpt;
use function get_the_title;
use function get_bloginfo;

/**
 * Generates ContactPage schema.
 */
class ContactPageSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate ContactPage schema.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		if ( ! $post_id ) {
			return array();
		}

		$schema = $this->build_base_schema();
		$schema['name'] = get_the_title( $post_id );
		$schema['url'] = get_permalink( $post_id );
		
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$schema['description'] = $excerpt;
		}

		// Add organization info if available
		$org_name = get_bloginfo( 'name' );
		if ( $org_name ) {
			$schema['mainEntity'] = array(
				'@type' => 'Organization',
				'name' => $org_name,
			);
		}

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'ContactPage';
	}
}



