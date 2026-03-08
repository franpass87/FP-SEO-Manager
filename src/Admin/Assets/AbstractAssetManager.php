<?php
/**
 * Abstract base class for asset managers.
 *
 * @package FP\SEO\Admin\Assets
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Assets;

/**
 * Abstract base class for asset managers (styles and scripts).
 */
abstract class AbstractAssetManager {

	/**
	 * Register hooks for the asset manager.
	 *
	 * @return void
	 */
	abstract public function register_hooks(): void;

	/**
	 * Check if the current screen matches the target screen.
	 *
	 * @param string|array<string> $screen_id Screen ID(s) to match.
	 * @return bool True if matches, false otherwise.
	 */
	protected function is_target_screen( $screen_id ): bool {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		if ( is_array( $screen_id ) ) {
			return in_array( $screen->id, $screen_id, true );
		}

		return $screen->id === $screen_id;
	}

	/**
	 * Get current screen ID.
	 *
	 * @return string|null Screen ID or null if not available.
	 */
	protected function get_current_screen_id(): ?string {
		$screen = get_current_screen();
		return $screen ? $screen->id : null;
	}
}








