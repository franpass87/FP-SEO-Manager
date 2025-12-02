<?php
/**
 * Abstract base class for schema generators.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

/**
 * Abstract base class for schema generators.
 */
abstract class AbstractSchemaGenerator {
	/**
	 * Generate schema data.
	 *
	 * @param int|null $post_id Optional post ID.
	 * @return array<string, mixed>
	 */
	abstract public function generate( ?int $post_id = null ): array;

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	abstract protected function get_schema_type(): string;

	/**
	 * Build base schema structure.
	 *
	 * @return array<string, mixed>
	 */
	protected function build_base_schema(): array {
		return array(
			'@context' => 'https://schema.org',
			'@type' => $this->get_schema_type(),
		);
	}
}


