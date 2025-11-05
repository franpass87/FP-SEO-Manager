<?php
/**
 * Entity & Relationship Graph for AI Understanding
 *
 * Builds knowledge graphs of entities and their relationships for AI engines.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use WP_Post;

/**
 * Builds entity relationship graphs for AI consumption
 */
class EntityGraph {

	/**
	 * Meta key for manually defined entities
	 */
	private const META_ENTITIES = '_fp_seo_entities';

	/**
	 * Meta key for manually defined relationships
	 */
	private const META_RELATIONSHIPS = '_fp_seo_relationships';

	/**
	 * Build complete entity graph for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Entity graph.
	 */
	public function build_entity_graph( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		// Extract entities
		$entities = $this->extract_entities( $post );

		// Build relationships
		$relationships = $this->build_relationships( $entities, $post );

		// Add context
		$context = $this->build_context( $post );

		return array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Dataset',
			'name'          => 'Knowledge Graph: ' . $post->post_title,
			'description'   => 'Entity relationships extracted from content',
			'url'           => get_permalink( $post ),
			'entities'      => $entities,
			'relationships' => $relationships,
			'context'       => $context,
			'statistics'    => $this->calculate_statistics( $entities, $relationships ),
		);
	}

	/**
	 * Extract entities from content
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Entities.
	 */
	private function extract_entities( WP_Post $post ): array {
		$entities = array();

		// Get manually defined entities first
		$manual_entities = get_post_meta( $post->ID, self::META_ENTITIES, true );
		if ( is_array( $manual_entities ) ) {
			$entities = $manual_entities;
		}

		// Auto-extract from content
		$auto_entities = $this->auto_extract_entities( $post );

		// Merge (manual takes precedence)
		foreach ( $auto_entities as $auto_entity ) {
			$exists = false;
			foreach ( $entities as $entity ) {
				if ( strtolower( $entity['name'] ) === strtolower( $auto_entity['name'] ) ) {
					$exists = true;
					break;
				}
			}

			if ( ! $exists ) {
				$entities[] = $auto_entity;
			}
		}

		// Enrich entities with additional data
		return array_map( array( $this, 'enrich_entity' ), $entities );
	}

	/**
	 * Auto-extract entities from content
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Auto-extracted entities.
	 */
	private function auto_extract_entities( WP_Post $post ): array {
		$content  = wp_strip_all_tags( $post->post_content );
		$entities = array();

		// Extract proper nouns (capitalized words)
		preg_match_all( '/\b[A-ZÀÈÉÌÒÙ][a-zàèéìòù]{2,}(?:\s+[A-ZÀÈÉÌÒÙ][a-zàèéìòù]{2,})*\b/', $content, $matches );

		if ( empty( $matches[0] ) ) {
			return $entities;
		}

		// Count occurrences
		$entity_counts = array_count_values( $matches[0] );

		// Filter entities that appear at least twice
		$filtered = array_filter( $entity_counts, function ( $count ) {
			return $count >= 2;
		} );

		arsort( $filtered );

		// Get top 20 entities
		$top_entities = array_slice( array_keys( $filtered ), 0, 20 );

		foreach ( $top_entities as $entity_name ) {
			$entities[] = array(
				'name'        => $entity_name,
				'type'        => $this->detect_entity_type( $entity_name, $content ),
				'description' => $this->extract_entity_description( $entity_name, $content ),
				'aliases'     => array(),
				'properties'  => array(),
				'confidence'  => $this->calculate_entity_confidence( $entity_name, $content ),
			);
		}

		return $entities;
	}

	/**
	 * Detect entity type
	 *
	 * @param string $entity_name Entity name.
	 * @param string $content     Full content.
	 * @return string Entity type.
	 */
	private function detect_entity_type( string $entity_name, string $content ): string {
		// Context analysis around entity mentions
		$pattern = '/(?:.{0,50})\b' . preg_quote( $entity_name, '/' ) . '\b(?:.{0,50})/i';
		preg_match_all( $pattern, $content, $contexts );

		$context_text = implode( ' ', $contexts[0] ?? array() );
		$context_lower = strtolower( $context_text );

		// Technology indicators
		if ( preg_match( '/\b(software|plugin|tool|framework|library|api|platform)\b/i', $context_lower ) ) {
			return 'Software';
		}

		// Company indicators
		if ( preg_match( '/\b(company|azienda|corporation|inc|ltd|srl|spa)\b/i', $context_lower ) ) {
			return 'Organization';
		}

		// Person indicators
		if ( preg_match( '/\b(developer|author|founder|ceo|expert|specialist)\b/i', $context_lower ) ) {
			return 'Person';
		}

		// Concept indicators
		if ( preg_match( '/\b(concept|idea|method|technique|approach|principle)\b/i', $context_lower ) ) {
			return 'Concept';
		}

		// Location indicators
		if ( preg_match( '/\b(city|country|region|location|place)\b/i', $context_lower ) ) {
			return 'Place';
		}

		return 'Thing'; // Generic
	}

	/**
	 * Extract entity description from content
	 *
	 * @param string $entity_name Entity name.
	 * @param string $content     Full content.
	 * @return string Entity description.
	 */
	private function extract_entity_description( string $entity_name, string $content ): string {
		// Look for definition patterns
		$patterns = array(
			'/\b' . preg_quote( $entity_name, '/' ) . '\s+(?:è|sono|rappresenta|significa)\s+([^.]{10,150})\./i',
			'/\b' . preg_quote( $entity_name, '/' ) . '\s+(?:is|are|represents|means)\s+([^.]{10,150})\./i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $matches ) ) {
				return trim( $matches[1] );
			}
		}

		// Fallback: get first sentence containing entity
		$sentences = preg_split( '/[.!?]/', $content );
		foreach ( $sentences as $sentence ) {
			if ( stripos( $sentence, $entity_name ) !== false && strlen( $sentence ) > 20 ) {
				return trim( $sentence );
			}
		}

		return '';
	}

	/**
	 * Calculate entity confidence score
	 *
	 * @param string $entity_name Entity name.
	 * @param string $content     Full content.
	 * @return float Confidence (0-1).
	 */
	private function calculate_entity_confidence( string $entity_name, string $content ): float {
		$mentions = substr_count( strtolower( $content ), strtolower( $entity_name ) );

		// More mentions = higher confidence
		if ( $mentions >= 10 ) {
			return 0.95;
		}

		if ( $mentions >= 5 ) {
			return 0.85;
		}

		if ( $mentions >= 3 ) {
			return 0.75;
		}

		return 0.6;
	}

	/**
	 * Enrich entity with additional data
	 *
	 * @param array<string, mixed> $entity Entity data.
	 * @return array<string, mixed> Enriched entity.
	 */
	private function enrich_entity( array $entity ): array {
		// Ensure all required fields
		$entity = array_merge(
			array(
				'name'        => '',
				'type'        => 'Thing',
				'description' => '',
				'aliases'     => array(),
				'properties'  => array(),
				'confidence'  => 0.7,
			),
			$entity
		);

		// Sanitize
		$entity['name']        = sanitize_text_field( $entity['name'] );
		$entity['type']        = sanitize_text_field( $entity['type'] );
		$entity['description'] = sanitize_textarea_field( $entity['description'] );

		// Add Schema.org context
		$entity['@type'] = $this->map_to_schema_type( $entity['type'] );

		return $entity;
	}

	/**
	 * Map custom type to Schema.org type
	 *
	 * @param string $custom_type Custom entity type.
	 * @return string Schema.org type.
	 */
	private function map_to_schema_type( string $custom_type ): string {
		$mapping = array(
			'Person'       => 'Person',
			'Organization' => 'Organization',
			'Place'        => 'Place',
			'Software'     => 'SoftwareApplication',
			'Concept'      => 'Thing',
			'Thing'        => 'Thing',
		);

		return $mapping[ $custom_type ] ?? 'Thing';
	}

	/**
	 * Build relationships between entities
	 *
	 * @param array<int, array<string, mixed>> $entities Entities.
	 * @param WP_Post                          $post     Post object.
	 * @return array<int, array<string, mixed>> Relationships.
	 */
	private function build_relationships( array $entities, WP_Post $post ): array {
		$relationships = array();

		// Get manual relationships
		$manual_relationships = get_post_meta( $post->ID, self::META_RELATIONSHIPS, true );
		if ( is_array( $manual_relationships ) ) {
			$relationships = $manual_relationships;
		}

		// Auto-detect relationships from content proximity
		$auto_relationships = $this->auto_detect_relationships( $entities, $post );

		// Merge
		$relationships = array_merge( $relationships, $auto_relationships );

		// Remove duplicates
		$relationships = $this->deduplicate_relationships( $relationships );

		return array_map( array( $this, 'enrich_relationship' ), $relationships );
	}

	/**
	 * Auto-detect relationships from entity co-occurrence
	 *
	 * @param array<int, array<string, mixed>> $entities Entities.
	 * @param WP_Post                          $post     Post object.
	 * @return array<int, array<string, mixed>> Detected relationships.
	 */
	private function auto_detect_relationships( array $entities, WP_Post $post ): array {
		if ( count( $entities ) < 2 ) {
			return array();
		}

		$content       = wp_strip_all_tags( $post->post_content );
		$relationships = array();

		// Check entity pairs for co-occurrence in same sentence
		foreach ( $entities as $i => $entity1 ) {
			foreach ( $entities as $j => $entity2 ) {
				if ( $i >= $j ) {
					continue; // Skip same entity and already checked pairs
				}

				$predicate = $this->detect_relationship_predicate(
					$entity1['name'],
					$entity2['name'],
					$content
				);

				if ( $predicate ) {
					$relationships[] = array(
						'subject'   => $entity1['name'],
						'predicate' => $predicate,
						'object'    => $entity2['name'],
						'confidence' => 0.7,
					);
				}
			}
		}

		return $relationships;
	}

	/**
	 * Detect relationship predicate between two entities
	 *
	 * @param string $entity1 First entity name.
	 * @param string $entity2 Second entity name.
	 * @param string $content Full content.
	 * @return string|null Predicate or null if no relationship.
	 */
	private function detect_relationship_predicate( string $entity1, string $entity2, string $content ): ?string {
		// Look for both entities in same sentence
		$sentences = preg_split( '/[.!?]/', $content );

		foreach ( $sentences as $sentence ) {
			$has_entity1 = stripos( $sentence, $entity1 ) !== false;
			$has_entity2 = stripos( $sentence, $entity2 ) !== false;

			if ( ! $has_entity1 || ! $has_entity2 ) {
				continue;
			}

			// Analyze verbs/predicates between entities
			$sentence_lower = strtolower( $sentence );

			// Common relationship predicates
			$predicates = array(
				'uses'         => array( 'usa', 'utilizza', 'use', 'uses' ),
				'created_by'   => array( 'creato da', 'sviluppato da', 'created by', 'developed by' ),
				'part_of'      => array( 'parte di', 'componente di', 'part of', 'component of' ),
				'works_with'   => array( 'funziona con', 'lavora con', 'works with', 'integrates with' ),
				'based_on'     => array( 'basato su', 'based on', 'built on' ),
				'requires'     => array( 'richiede', 'necessita', 'requires', 'needs' ),
				'provides'     => array( 'fornisce', 'offre', 'provides', 'offers' ),
				'improves'     => array( 'migliora', 'ottimizza', 'improves', 'enhances' ),
				'replaces'     => array( 'sostituisce', 'rimpiazza', 'replaces' ),
				'similar_to'   => array( 'simile a', 'come', 'similar to', 'like' ),
			);

			foreach ( $predicates as $predicate => $patterns ) {
				foreach ( $patterns as $pattern ) {
					if ( strpos( $sentence_lower, $pattern ) !== false ) {
						return $predicate;
					}
				}
			}

			// Default: related_to if entities co-occur
			return 'related_to';
		}

		return null;
	}

	/**
	 * Deduplicate relationships
	 *
	 * @param array<int, array<string, mixed>> $relationships Relationships.
	 * @return array<int, array<string, mixed>> Deduplicated relationships.
	 */
	private function deduplicate_relationships( array $relationships ): array {
		$seen = array();
		$unique = array();

		foreach ( $relationships as $rel ) {
			$key = strtolower( $rel['subject'] . '|' . $rel['predicate'] . '|' . $rel['object'] );

			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$unique[] = $rel;
			}
		}

		return $unique;
	}

	/**
	 * Enrich relationship with additional data
	 *
	 * @param array<string, mixed> $relationship Relationship data.
	 * @return array<string, mixed> Enriched relationship.
	 */
	private function enrich_relationship( array $relationship ): array {
		return array(
			'subject'    => sanitize_text_field( $relationship['subject'] ?? '' ),
			'predicate'  => sanitize_text_field( $relationship['predicate'] ?? 'related_to' ),
			'object'     => sanitize_text_field( $relationship['object'] ?? '' ),
			'confidence' => min( 1.0, max( 0.0, (float) ( $relationship['confidence'] ?? 0.7 ) ) ),
		);
	}

	/**
	 * Build graph context
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Context data.
	 */
	private function build_context( WP_Post $post ): array {
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		return array(
			'domain'     => implode( ', ', $categories ),
			'topics'     => $tags,
			'language'   => $this->detect_language( $post ),
			'post_type'  => $post->post_type,
			'created_at' => gmdate( 'c', strtotime( $post->post_date_gmt ) ),
			'updated_at' => gmdate( 'c', strtotime( $post->post_modified_gmt ) ),
		);
	}

	/**
	 * Detect content language
	 *
	 * @param WP_Post $post Post object.
	 * @return string Language code.
	 */
	private function detect_language( WP_Post $post ): string {
		// Check WordPress locale
		$locale = get_locale();

		// Map locale to language code
		$language_map = array(
			'it_IT' => 'it',
			'en_US' => 'en',
			'en_GB' => 'en',
			'es_ES' => 'es',
			'fr_FR' => 'fr',
			'de_DE' => 'de',
		);

		return $language_map[ $locale ] ?? 'en';
	}

	/**
	 * Calculate graph statistics
	 *
	 * @param array<int, array<string, mixed>> $entities      Entities.
	 * @param array<int, array<string, mixed>> $relationships Relationships.
	 * @return array<string, int> Statistics.
	 */
	private function calculate_statistics( array $entities, array $relationships ): array {
		$entity_types = array_count_values( array_column( $entities, 'type' ) );
		$predicates   = array_count_values( array_column( $relationships, 'predicate' ) );

		return array(
			'total_entities'      => count( $entities ),
			'total_relationships' => count( $relationships ),
			'entity_types'        => $entity_types,
			'predicate_types'     => $predicates,
			'graph_density'       => $this->calculate_graph_density( count( $entities ), count( $relationships ) ),
		);
	}

	/**
	 * Calculate graph density (how connected the graph is)
	 *
	 * @param int $entity_count       Number of entities.
	 * @param int $relationship_count Number of relationships.
	 * @return float Density (0-1).
	 */
	private function calculate_graph_density( int $entity_count, int $relationship_count ): float {
		if ( $entity_count < 2 ) {
			return 0.0;
		}

		// Maximum possible relationships in undirected graph
		$max_relationships = ( $entity_count * ( $entity_count - 1 ) ) / 2;

		if ( $max_relationships === 0 || $max_relationships < 1 ) {
			return 0.0;
		}

		return min( 1.0, $relationship_count / max( 1, $max_relationships ) );
	}

	/**
	 * Add manual entity
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $entity  Entity data.
	 * @return bool Success.
	 */
	public function add_entity( int $post_id, array $entity ): bool {
		$entities   = get_post_meta( $post_id, self::META_ENTITIES, true );
		$entities   = is_array( $entities ) ? $entities : array();

		$entities[] = $this->enrich_entity( $entity );

		return update_post_meta( $post_id, self::META_ENTITIES, $entities );
	}

	/**
	 * Add manual relationship
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $subject   Subject entity.
	 * @param string $predicate Relationship predicate.
	 * @param string $object    Object entity.
	 * @return bool Success.
	 */
	public function add_relationship( int $post_id, string $subject, string $predicate, string $object ): bool {
		$relationships = get_post_meta( $post_id, self::META_RELATIONSHIPS, true );
		$relationships = is_array( $relationships ) ? $relationships : array();

		$relationships[] = array(
			'subject'    => sanitize_text_field( $subject ),
			'predicate'  => sanitize_text_field( $predicate ),
			'object'     => sanitize_text_field( $object ),
			'confidence' => 1.0, // Manual = high confidence
		);

		return update_post_meta( $post_id, self::META_RELATIONSHIPS, $relationships );
	}
}

