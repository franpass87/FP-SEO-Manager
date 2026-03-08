<?php
/**
 * Registry for metabox sections.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use WP_Post;

/**
 * Registry for managing metabox sections.
 */
class SectionRegistry {
	/**
	 * Registered sections.
	 *
	 * @var array<string, SectionInterface>
	 */
	private array $sections = array();

	/**
	 * Register a section.
	 *
	 * @param SectionInterface $section Section to register.
	 * @return void
	 */
	public function register( SectionInterface $section ): void {
		$this->sections[ $section->get_id() ] = $section;
	}

	/**
	 * Unregister a section.
	 *
	 * @param string $section_id Section ID.
	 * @return void
	 */
	public function unregister( string $section_id ): void {
		unset( $this->sections[ $section_id ] );
	}

	/**
	 * Get all registered sections.
	 *
	 * @return array<string, SectionInterface> All sections.
	 */
	public function get_all(): array {
		return $this->sections;
	}

	/**
	 * Get enabled sections for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, SectionInterface> Enabled sections.
	 */
	public function get_enabled( WP_Post $post ): array {
		$enabled = array();

		foreach ( $this->sections as $section_id => $section ) {
			if ( $section->is_enabled( $post ) ) {
				$enabled[ $section_id ] = $section;
			}
		}

		return $enabled;
	}

	/**
	 * Get sections sorted by priority.
	 *
	 * @param WP_Post|null $post Optional post object (for enabled filtering).
	 * @return array<SectionInterface> Sections sorted by priority.
	 */
	public function get_by_priority( ?WP_Post $post = null ): array {
		$sections = $post ? $this->get_enabled( $post ) : $this->get_all();

		usort(
			$sections,
			function( SectionInterface $a, SectionInterface $b ): int {
				return $a->get_priority() <=> $b->get_priority();
			}
		);

		return array_values( $sections );
	}

	/**
	 * Get a specific section by ID.
	 *
	 * @param string $section_id Section ID.
	 * @return SectionInterface|null Section or null if not found.
	 */
	public function get( string $section_id ): ?SectionInterface {
		return $this->sections[ $section_id ] ?? null;
	}

	/**
	 * Check if a section is registered.
	 *
	 * @param string $section_id Section ID.
	 * @return bool True if registered.
	 */
	public function has( string $section_id ): bool {
		return isset( $this->sections[ $section_id ] );
	}
}


