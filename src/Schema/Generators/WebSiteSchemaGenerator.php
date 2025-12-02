<?php
/**
 * WebSite schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use function get_bloginfo;
use function home_url;

/**
 * Generates WebSite schema.
 */
class WebSiteSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate WebSite schema.
	 *
	 * @param int|null $post_id Optional post ID (not used for WebSite).
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		$search_action = array(
			'@type' => 'SearchAction',
			'target' => array(
				'@type' => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		);

		$schema = $this->build_base_schema();
		$schema['name'] = get_bloginfo( 'name' );
		$schema['url'] = home_url();
		$schema['description'] = get_bloginfo( 'description' );
		$schema['potentialAction'] = $search_action;

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'WebSite';
	}
}


