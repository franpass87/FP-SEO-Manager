<?php
/**
 * Breadcrumb schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use function __;
use function get_category_link;
use function get_post;
use function get_post_ancestors;
use function get_post_type;
use function get_post_type_archive_link;
use function get_post_type_object;
use function get_queried_object;
use function get_tag_link;
use function get_the_category;
use function get_the_title;
use function get_permalink;
use function home_url;
use function is_category;
use function is_singular;
use function is_tag;

/**
 * Generates BreadcrumbList schema.
 */
class BreadcrumbSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate BreadcrumbList schema.
	 *
	 * @param int|null $post_id Optional post ID (not used, uses current page).
	 * @return array<string, mixed>|null
	 */
	public function generate( ?int $post_id = null ): ?array {
		$breadcrumbs = $this->get_breadcrumb_items();
		
		if ( empty( $breadcrumbs ) ) {
			return null;
		}

		$list_items = array();
		foreach ( $breadcrumbs as $index => $breadcrumb ) {
			$list_items[] = array(
				'@type' => 'ListItem',
				'position' => $index + 1,
				'name' => $breadcrumb['name'],
				'item' => $breadcrumb['url'],
			);
		}

		$schema = $this->build_base_schema();
		$schema['itemListElement'] = $list_items;

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'BreadcrumbList';
	}

	/**
	 * Get breadcrumb items for current page.
	 *
	 * @return array<array{name: string, url: string}>
	 */
	private function get_breadcrumb_items(): array {
		$breadcrumbs = array();

		// Home
		$breadcrumbs[] = array(
			'name' => __( 'Home', 'fp-seo-performance' ),
			'url' => home_url(),
		);

		if ( is_singular() ) {
			$post = get_post();
			$post_type = get_post_type();

			// Add post type archive if exists
			$post_type_obj = get_post_type_object( $post_type );
			if ( $post_type_obj && $post_type_obj->has_archive ) {
				$breadcrumbs[] = array(
					'name' => $post_type_obj->labels->name,
					'url' => get_post_type_archive_link( $post_type ),
				);
			}

			// Add categories for posts
			if ( 'post' === $post_type ) {
				$categories = get_the_category();
				if ( ! empty( $categories ) ) {
					$category = $categories[0];
					$breadcrumbs[] = array(
						'name' => $category->name,
						'url' => get_category_link( $category->term_id ),
					);
				}
			}

			// Add parent pages
			if ( $post->post_parent ) {
				$parent_pages = get_post_ancestors( $post->ID );
				$parent_pages = array_reverse( $parent_pages );
				
				foreach ( $parent_pages as $parent_id ) {
					$breadcrumbs[] = array(
						'name' => get_the_title( $parent_id ),
						'url' => get_permalink( $parent_id ),
					);
				}
			}

			// Current page
			$breadcrumbs[] = array(
				'name' => get_the_title(),
				'url' => get_permalink(),
			);
		} elseif ( is_category() ) {
			$category = get_queried_object();
			$breadcrumbs[] = array(
				'name' => $category->name,
				'url' => get_category_link( $category->term_id ),
			);
		} elseif ( is_tag() ) {
			$tag = get_queried_object();
			$breadcrumbs[] = array(
				'name' => $tag->name,
				'url' => get_tag_link( $tag->term_id ),
			);
		}

		return $breadcrumbs;
	}
}


