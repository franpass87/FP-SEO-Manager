<?php
/**
 * Base class for settings tab renderers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Settings;

/**
 * Abstract base for settings tab rendering.
 */
abstract class SettingsTabRenderer {

	/**
	 * Renders the tab content.
	 *
	 * @param array<string, mixed> $options Current plugin options.
	 */
	abstract public function render( array $options ): void;

	/**
	 * Gets the option key for form fields.
	 */
	protected function get_option_key(): string {
		return 'fp_seo_perf_options';
	}
}