<?php
/**
 * Contextual admin notices.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\Utils\OptionsHelper;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Displays contextual admin notices for the plugin.
 */
class Notices {

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	private ?HookManagerInterface $hook_manager = null;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Hooks the notice renderer into WordPress.
	 */
	public function register(): void {
		if ( $this->hook_manager ) {
			$this->hook_manager->add_action( 'admin_notices', array( $this, 'render' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'render' ) );
		}
	}

	/**
	 * Outputs the notices when appropriate.
	 */
	public function render(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			return;
		}

		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		$options = OptionsHelper::get();

		$notices = array();

		if ( ! empty( $options['performance']['enable_psi'] ) && '' === ( $options['performance']['psi_api_key'] ?? '' ) ) {
			$notices[] = array(
				'type'    => 'warning',
				'message' => __( 'PageSpeed Insights is enabled but no API key is configured. Add a key or disable PSI hints.', 'fp-seo-performance' ),
			);
		}

		if ( ! empty( $options['general']['admin_bar_badge'] ) && empty( $options['general']['enable_analyzer'] ) ) {
			$notices[] = array(
				'type'    => 'warning',
				'message' => __( 'The admin bar badge requires the analyzer to be enabled. Update your general settings to avoid missing scores.', 'fp-seo-performance' ),
			);
		}

		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Determines whether the current admin screen belongs to the plugin.
	 */
	private function is_plugin_screen(): bool {
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( str_starts_with( $page, 'fp-seo-performance' ) ) {
				return true;
			}
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && false !== strpos( (string) $screen->id, 'fp-seo-performance' ) ) {
				return true;
			}
		}

		return false;
	}
}
