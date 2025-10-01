<?php
/**
 * Asset registration utilities.
 *
 * @package FP\SEO
 */

declare( strict_types=1 );

namespace FP\SEO\Utils;

use function add_action;
use function plugins_url;
use function wp_register_script;
use function wp_register_style;
use function wp_script_is;
use function wp_style_is;

/**
 * Handles registration of plugin assets.
 */
class Assets {

	/**
	 * Hooks asset registration into admin requests.
	 */
        public function register(): void {
                add_action( 'admin_init', array( $this, 'register_admin_assets' ), 10, 0 );
                add_action( 'admin_enqueue_scripts', array( $this, 'ensure_admin_handles' ), 5, 0 );
        }

        /**
         * Registers admin asset handles early in the request.
         */
        public function register_admin_assets(): void {
                $this->register_handles();
        }

        /**
         * Ensures admin handles exist before other callbacks enqueue them.
         */
        public function ensure_admin_handles(): void {
                if ( $this->handles_registered() ) {
                        return;
                }

                $this->register_handles();
        }

        /**
         * Registers asset handles used across admin screens.
         */
        private function register_handles(): void {
                $version = $this->asset_version();

                wp_register_style(
                        'fp-seo-performance-admin',
                        plugins_url( 'assets/admin/admin.css', FP_SEO_PERFORMANCE_FILE ),
                        array(),
                        $version
                );

                wp_register_script(
                        'fp-seo-performance-admin',
                        plugins_url( 'assets/admin/admin.js', FP_SEO_PERFORMANCE_FILE ),
                        array( 'jquery' ),
                        $version,
                        true
                );

                wp_register_script(
                        'fp-seo-performance-editor',
                        plugins_url( 'assets/admin/editor-metabox.js', FP_SEO_PERFORMANCE_FILE ),
                        array( 'jquery' ),
                        $version,
                        true
                );

                wp_register_script(
                        'fp-seo-performance-bulk',
                        plugins_url( 'assets/admin/bulk-auditor.js', FP_SEO_PERFORMANCE_FILE ),
                        array( 'jquery' ),
                        $version,
                        true
                );
        }

        /**
         * Determines whether all admin handles are registered.
         */
        private function handles_registered(): bool {
                return wp_style_is( 'fp-seo-performance-admin', 'registered' )
                        && wp_script_is( 'fp-seo-performance-admin', 'registered' )
                        && wp_script_is( 'fp-seo-performance-editor', 'registered' )
                        && wp_script_is( 'fp-seo-performance-bulk', 'registered' );
        }

        /**
         * Resolve the version string used for asset registration.
         */
        private function asset_version(): string {
                if ( defined( 'FP_SEO_PERFORMANCE_VERSION' ) && '' !== FP_SEO_PERFORMANCE_VERSION ) {
                        return FP_SEO_PERFORMANCE_VERSION;
                }

                return '0.1.0';
        }
}
