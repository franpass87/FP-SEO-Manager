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
	/**
	 * Disallow instantiation.
	 */
	private function __construct() {}

	/**
	 * Resolve the plugin version from WordPress helpers or the file header.
	 *
	 * @param string $plugin_file      Absolute path to the plugin bootstrap file.
	 * @param string $default_version  Fallback version string.
	 *
	 * @return string
	 */
	public static function resolve( string $plugin_file, string $default_version ): string {
		$version = '';

		if ( function_exists( 'get_file_data' ) ) {
			$header = get_file_data( $plugin_file, array( 'version' => 'Version' ) );

			if ( isset( $header['version'] ) ) {
				$version = trim( (string) $header['version'] );
			}
		} else {
			$plugin_source = file_get_contents( $plugin_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( false !== $plugin_source
						&& preg_match( '/^\s*\*\s*Version:\s*(.+)$/mi', $plugin_source, $matches ) ) {
					$version = trim( $matches[1] );
			}
		}

		if ( '' === $version ) {
			return $default_version;
		}

		return $version;
	}
}
