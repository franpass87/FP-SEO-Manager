<?php
/**
 * Abstract base class for metabox sections.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use WP_Post;

/**
 * Abstract base class for metabox sections.
 *
 * Provides common functionality for section implementations.
 */
abstract class AbstractSection implements SectionInterface {
	/**
	 * Section ID.
	 *
	 * @var string
	 */
	protected string $section_id;

	/**
	 * Section priority (lower = rendered first).
	 *
	 * @var int
	 */
	protected int $priority;

	/**
	 * Constructor.
	 *
	 * @param string $section_id Section ID.
	 * @param int    $priority Section priority.
	 */
	public function __construct( string $section_id, int $priority = 10 ) {
		$this->section_id = $section_id;
		$this->priority   = $priority;
	}

	/**
	 * Get section unique identifier.
	 *
	 * @return string Section ID.
	 */
	public function get_id(): string {
		return $this->section_id;
	}

	/**
	 * Get section priority (lower = rendered first).
	 *
	 * @return int Priority value.
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Check if section is enabled for the given post.
	 *
	 * Default implementation always returns true.
	 * Override in subclasses for conditional enabling.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if enabled.
	 */
	public function is_enabled( WP_Post $post ): bool {
		return true;
	}
}


