<?php
/**
 * Article schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use FP\SEO\Schema\Helpers\SchemaHelper;
use function get_author_posts_url;
use function get_bloginfo;
use function get_post;
use function get_the_category;
use function get_the_date;
use function get_the_excerpt;
use function get_the_modified_date;
use function get_the_tags;
use function get_the_title;
use function get_permalink;
use function get_userdata;
use function strip_tags;
use function str_word_count;

/**
 * Generates Article schema.
 */
class ArticleSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate Article schema.
	 *
	 * @param int|null $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		if ( ! $post_id ) {
			return array();
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$author = get_userdata( $post->post_author );
		$categories = get_the_category( $post_id );
		$tags = get_the_tags( $post_id );

		$schema = $this->build_base_schema();
		$schema['headline'] = get_the_title( $post_id );
		$schema['url'] = get_permalink( $post_id );
		$schema['datePublished'] = get_the_date( 'c', $post_id );
		$schema['dateModified'] = get_the_modified_date( 'c', $post_id );
		$schema['author'] = array(
			'@type' => 'Person',
			'name' => $author->display_name ?? '',
			'url' => $author ? get_author_posts_url( $author->ID ) : '',
		);
		$schema['publisher'] = array(
			'@type' => 'Organization',
			'name' => get_bloginfo( 'name' ),
			'logo' => array(
				'@type' => 'ImageObject',
				'url' => SchemaHelper::get_custom_logo_url(),
			),
		);

		// Featured image removed from schema - no longer using featured images
		// Schema image will use social meta image or default only

		// Add excerpt
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$schema['description'] = $excerpt;
		}

		// Add categories
		if ( ! empty( $categories ) ) {
			$schema['articleSection'] = array();
			foreach ( $categories as $category ) {
				$schema['articleSection'][] = $category->name;
			}
		}

		// Add keywords
		if ( ! empty( $tags ) ) {
			$schema['keywords'] = array();
			foreach ( $tags as $tag ) {
				$schema['keywords'][] = $tag->name;
			}
		}

		// Add word count
		$word_count = str_word_count( strip_tags( $post->post_content ) );
		if ( $word_count > 0 ) {
			$schema['wordCount'] = $word_count;
		}

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'Article';
	}
}

