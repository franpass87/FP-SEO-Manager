<?php
/**
 * Analyzer result payload value object.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Analysis;

/**
 * Immutable analyzer result representation.
 */
class Result {
	/**
	 * Pass status key.
	 */
	public const STATUS_PASS = 'pass';

	/**
	 * Warning status key.
	 */
	public const STATUS_WARN = 'warn';

	/**
	 * Failure status key.
	 */
	public const STATUS_FAIL = 'fail';

	/**
	 * Result status string.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Details payload.
	 *
	 * @var array<string, mixed>
	 */
	private array $details;

	/**
	 * Fix hint string.
	 *
	 * @var string
	 */
	private string $fix_hint;

	/**
	 * Weight value.
	 *
	 * @var float
	 */
	private float $weight;

	/**
	 * Constructor.
	 *
	 * @param string               $status   Result status.
	 * @param array<string, mixed> $details  Arbitrary detail payload.
	 * @param string               $fix_hint Suggested fix hint text.
	 * @param float                $weight   Weight contribution (0..1).
	 */
	public function __construct( string $status, array $details, string $fix_hint, float $weight ) {
		$this->status   = $status;
		$this->details  = $details;
		$this->fix_hint = $fix_hint;
		$this->weight   = $weight;
	}

	/**
	 * Status accessor.
	 *
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Detail accessor.
	 *
	 * @return array<string, mixed>
	 */
	public function details(): array {
		return $this->details;
	}

	/**
	 * Fix hint accessor.
	 *
	 * @return string
	 */
	public function fix_hint(): string {
		return $this->fix_hint;
	}

	/**
	 * Weight accessor.
	 *
	 * @return float
	 */
	public function weight(): float {
		return $this->weight;
	}

	/**
	 * Export as array representation.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'status'   => $this->status,
			'details'  => $this->details,
			'fix_hint' => $this->fix_hint,
			'weight'   => $this->weight,
		);
	}
}
