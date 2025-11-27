<?php
/**
 * Shared post type helpers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use function array_filter;
use function array_map;
use function array_values;
use function get_post_types;
use function in_array;
use function post_type_supports;

/**
 * Provides reusable logic for determining analyzer-supported post types.
 */
class PostTypes {
		/**
		 * Returns post types eligible for analyzer features.
		 *
		 * @return string[]
		 */
	public static function analyzable(): array {
			$post_types = get_post_types(
				array(
					'show_ui' => true,
				),
				'names'
			);

				$post_types = array_values(
					array_filter(
						array_map( 'strval', $post_types ),
						static function ( string $type ): bool {
							if ( in_array(
								$type,
								array(
									'attachment',
									'revision',
									'nav_menu_item',
									'custom_css',
									'customize_changeset',
									'wp_block',
									'wp_template',
									'wp_template_part',
									'wp_global_styles',
									// Exclude Nectar Slider and other slider post types to prevent interference
									'nectar_slider',
									'home_slider',
								),
								true
							) ) {
								return false;
							}

									return post_type_supports( $type, 'editor' );
						}
					)
				);

		if ( empty( $post_types ) ) {
				return array( 'post', 'page' );
		}

			return $post_types;
	}
}
