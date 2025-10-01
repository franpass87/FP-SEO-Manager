<?php
/**
 * Contract for analyzer checks.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis;

/**
 * Interface for individual analyzer checks.
 */
interface CheckInterface {
	/**
	 * Unique identifier for the check.
	 *
	 * @return string Check identifier.
	 */
	public function id(): string;

	/**
	 * Human readable label.
	 *
	 * @return string Check label.
	 */
	public function label(): string;

	/**
	 * Short description of what the check validates.
	 *
	 * @return string Check description.
	 */
	public function description(): string;

	/**
	 * Execute the check against the provided context.
	 *
	 * @param Context $context Analyzer context payload.
	 *
	 * @return Result Result payload for the analyzer response.
	 */
	public function run( Context $context ): Result;
}
