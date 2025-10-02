<?php
/**
 * Plugin version utilities.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use function file_get_contents;
use function function_exists;
use function get_file_data;
use function preg_match;
use function trim;

/**
 * Resolves the plugin version from available metadata.
 */
final class Version {
        private function __construct() {}

        /**
         * Resolve the plugin version from WordPress helpers or the file header.
         */
        public static function resolve( string $plugin_file, string $default ): string {
                $version = '';

                if ( function_exists( 'get_file_data' ) ) {
                        $header = get_file_data( $plugin_file, array( 'version' => 'Version' ) );

                        if ( isset( $header['version'] ) ) {
                                $version = trim( (string) $header['version'] );
                        }
                } else {
                        $plugin_source = file_get_contents( $plugin_file );

                        if ( false !== $plugin_source
                                && preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $plugin_source, $matches ) ) {
                                $version = trim( $matches[1] );
                        }
                }

                if ( '' === $version ) {
                        return $default;
                }

                return $version;
        }
}
