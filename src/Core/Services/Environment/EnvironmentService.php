<?php
/**
 * Environment service - checks PHP/WordPress versions and compatibility.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Environment;

/**
 * Environment checking service.
 */
class EnvironmentService {

	/**
	 * Minimum PHP version required.
	 *
	 * @var string
	 */
	private string $min_php_version = '8.0';

	/**
	 * Minimum WordPress version required.
	 *
	 * @var string
	 */
	private string $min_wp_version = '6.2';

	/**
	 * Constructor.
	 *
	 * @param string $min_php_version Minimum PHP version.
	 * @param string $min_wp_version  Minimum WordPress version.
	 */
	public function __construct( string $min_php_version = '8.0', string $min_wp_version = '6.2' ) {
		$this->min_php_version = $min_php_version;
		$this->min_wp_version  = $min_wp_version;
	}

	/**
	 * Check if PHP version meets requirements.
	 *
	 * @return bool True if PHP version is sufficient.
	 */
	public function is_php_version_sufficient(): bool {
		return version_compare( PHP_VERSION, $this->min_php_version, '>=' );
	}

	/**
	 * Check if WordPress version meets requirements.
	 *
	 * @return bool True if WordPress version is sufficient.
	 */
	public function is_wp_version_sufficient(): bool {
		global $wp_version;
		return version_compare( $wp_version ?? '0.0', $this->min_wp_version, '>=' );
	}

	/**
	 * Check if running in multisite environment.
	 *
	 * @return bool True if multisite.
	 */
	public function is_multisite(): bool {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * Check if running in admin context.
	 *
	 * @return bool True if in admin.
	 */
	public function is_admin(): bool {
		return function_exists( 'is_admin' ) && is_admin();
	}

	/**
	 * Check if running in AJAX context.
	 *
	 * @return bool True if AJAX.
	 */
	public function is_ajax(): bool {
		return function_exists( 'wp_doing_ajax' ) && wp_doing_ajax();
	}

	/**
	 * Check if running in REST API context.
	 *
	 * @return bool True if REST API.
	 */
	public function is_rest_api(): bool {
		return function_exists( 'rest_get_server' ) && defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Check if running in WP-CLI context.
	 *
	 * @return bool True if WP-CLI.
	 */
	public function is_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Check if running in cron context.
	 *
	 * @return bool True if cron.
	 */
	public function is_cron(): bool {
		return function_exists( 'wp_doing_cron' ) && wp_doing_cron();
	}

	/**
	 * Get PHP version.
	 *
	 * @return string PHP version.
	 */
	public function get_php_version(): string {
		return PHP_VERSION;
	}

	/**
	 * Get WordPress version.
	 *
	 * @return string WordPress version.
	 */
	public function get_wp_version(): string {
		global $wp_version;
		return $wp_version ?? '0.0';
	}

	/**
	 * Get minimum required PHP version.
	 *
	 * @return string Minimum PHP version.
	 */
	public function get_min_php_version(): string {
		return $this->min_php_version;
	}

	/**
	 * Get minimum required WordPress version.
	 *
	 * @return string Minimum WordPress version.
	 */
	public function get_min_wp_version(): string {
		return $this->min_wp_version;
	}

	/**
	 * Check if a plugin is active.
	 *
	 * @param string $plugin_file Plugin file path (e.g., 'plugin-name/plugin-name.php').
	 * @return bool True if active.
	 */
	public function is_plugin_active( string $plugin_file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin_file );
	}

	/**
	 * Check if a function exists.
	 *
	 * @param string $function_name Function name.
	 * @return bool True if function exists.
	 */
	public function function_exists( string $function_name ): bool {
		return function_exists( $function_name );
	}

	/**
	 * Check if a class exists.
	 *
	 * @param string $class_name Class name.
	 * @return bool True if class exists.
	 */
	public function class_exists( string $class_name ): bool {
		return class_exists( $class_name );
	}
}



