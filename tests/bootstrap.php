<?php
/**
 * PHPUnit bootstrap helpers.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( 'tests_add_filter' ) ) {
		/**
		 * Polyfill for tests_add_filter helper in WP test suite.
		 *
		 * @param string   $tag             Filter hook name.
		 * @param callable $function_to_add Callback to add.
		 * @param int      $priority        Priority for the filter.
		 * @param int      $accepted_args   Accepted argument count.
		 *
		 * @return bool Whether the function was added.
		 */
	function tests_add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
			return add_filter( $tag, $function_to_add, $priority, $accepted_args );
	}
}
