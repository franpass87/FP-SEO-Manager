<?php
/**
 * Content Extractor - Extracts citations, entities, FAQ from post content
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

/**
 * Extracts structured data from content
 */
class Extractor {

	/**
	 * Extract all data from post
	 *
	 * @param \WP_Post $post Post object.
	 * @return array{keywords:array<string>,entities:array<string,array<string>>,citations:array<array{url:string,title:string}>,faq:array<array{q:string,a:string}>}
	 */
	public function extract( \WP_Post $post ): array {
		return array(
			'keywords'  => $this->extract_keywords( $post ),
			'entities'  => $this->extract_entities( $post ),
			'citations' => $this->extract_citations( $post ),
			'faq'       => $this->extract_faq( $post ),
		);
	}

	/**
	 * Extract keywords from tags
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<string>
	 */
	public function extract_keywords( \WP_Post $post ): array {
		$tags = get_the_tags( $post->ID );
		if ( ! $tags || is_wp_error( $tags ) ) {
			return array();
		}

		$keywords = array();
		foreach ( $tags as $tag ) {
			$keywords[] = $tag->name;
		}

		return $keywords;
	}

	/**
	 * Extract entities (people, orgs, places) from taxonomies
	 *
	 * @param \WP_Post $post Post object.
	 * @return array{people:array<string>,orgs:array<string>,places:array<string>}
	 */
	public function extract_entities( \WP_Post $post ): array {
		$entities = array(
			'people' => array(),
			'orgs'   => array(),
			'places' => array(),
		);

		// Check for custom taxonomies
		$taxonomies = get_object_taxonomies( $post->post_type );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			// Map taxonomy to entity type
			$entity_type = $this->map_taxonomy_to_entity( $taxonomy );
			if ( ! $entity_type ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$entities[ $entity_type ][] = $term->name;
			}
		}

		// Deduplicate
		$entities['people'] = array_values( array_unique( $entities['people'] ) );
		$entities['orgs']   = array_values( array_unique( $entities['orgs'] ) );
		$entities['places'] = array_values( array_unique( $entities['places'] ) );

		return $entities;
	}

	/**
	 * Extract citations from content links and shortcodes
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<array{url:string,title:string,author?:string,publisher?:string,accessed?:string}>
	 */
	public function extract_citations( \WP_Post $post ): array {
		$citations = array();

		// Extract from [fp_citation] shortcodes
		$pattern = '/\[fp_citation([^\]]+)\]/';
		preg_match_all( $pattern, $post->post_content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $attrs_str ) {
				$attrs = shortcode_parse_atts( $attrs_str );
				if ( ! empty( $attrs['url'] ) ) {
					$citation = array(
						'url'   => $attrs['url'],
						'title' => $attrs['title'] ?? '',
					);

					if ( ! empty( $attrs['author'] ) ) {
						$citation['author'] = $attrs['author'];
					}
					if ( ! empty( $attrs['publisher'] ) ) {
						$citation['publisher'] = $attrs['publisher'];
					}
					if ( ! empty( $attrs['accessed'] ) ) {
						$citation['accessed'] = $attrs['accessed'];
					}

					$citations[] = $citation;
				}
			}
		}

		// Extract outbound links from content
		$dom = new \DOMDocument();
		@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $post->post_content );
		$links = $dom->getElementsByTagName( 'a' );

		foreach ( $links as $link ) {
			$url = $link->getAttribute( 'href' );

			// Skip internal links
			if ( $this->is_internal_link( $url ) ) {
				continue;
			}

			// Skip anchors and special protocols
			if ( empty( $url ) || strpos( $url, '#' ) === 0 || strpos( $url, 'mailto:' ) === 0 ) {
				continue;
			}

			$title = $link->nodeValue ?? '';

			// Avoid duplicates
			$already_exists = false;
			foreach ( $citations as $existing ) {
				if ( $existing['url'] === $url ) {
					$already_exists = true;
					break;
				}
			}

			if ( ! $already_exists ) {
				$citations[] = array(
					'url'   => $url,
					'title' => $title,
				);
			}
		}

		return $citations;
	}

	/**
	 * Extract FAQ from shortcodes
	 *
	 * @param \WP_Post $post Post object.
	 * @return array<array{q:string,a:string}>
	 */
	public function extract_faq( \WP_Post $post ): array {
		$faq = array();

		// Extract from [fp_faq] shortcodes
		$pattern = '/\[fp_faq([^\]]+)\]/';
		preg_match_all( $pattern, $post->post_content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $attrs_str ) {
				$attrs = shortcode_parse_atts( $attrs_str );
				if ( ! empty( $attrs['q'] ) && ! empty( $attrs['a'] ) ) {
					$faq[] = array(
						'q' => $attrs['q'],
						'a' => $attrs['a'],
					);
				}
			}
		}

		return $faq;
	}

	/**
	 * Map taxonomy name to entity type
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string|null 'people', 'orgs', 'places', or null.
	 */
	private function map_taxonomy_to_entity( string $taxonomy ): ?string {
		// Common patterns for custom taxonomies
		$patterns = array(
			'people'   => array( 'person', 'people', 'author', 'autore', 'persona' ),
			'orgs'     => array( 'organization', 'org', 'company', 'azienda', 'organizzazione' ),
			'places'   => array( 'place', 'location', 'luogo', 'località', 'region', 'regione' ),
		);

		foreach ( $patterns as $type => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( stripos( $taxonomy, $keyword ) !== false ) {
					return $type;
				}
			}
		}

		return null;
	}

	/**
	 * Check if URL is internal
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	private function is_internal_link( string $url ): bool {
		$site_url = home_url( '/' );
		$parsed_site = parse_url( $site_url );
		$parsed_url  = parse_url( $url );

		if ( ! isset( $parsed_url['host'] ) ) {
			return true; // Relative URL = internal
		}

		return $parsed_url['host'] === $parsed_site['host'];
	}
}

