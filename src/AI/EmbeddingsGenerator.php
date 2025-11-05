<?php
/**
 * Vector Embeddings Generator for Semantic Search
 *
 * Generates vector embeddings using OpenAI Embeddings API for semantic similarity matching.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI;

use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\GEO\SemanticChunker;
use WP_Post;

/**
 * Generates and manages vector embeddings for content
 */
class EmbeddingsGenerator {

	/**
	 * Meta key for embeddings data
	 */
	private const META_EMBEDDINGS = '_fp_seo_embeddings';

	/**
	 * Embedding model
	 */
	private const EMBEDDING_MODEL = 'text-embedding-3-small';

	/**
	 * Embedding dimensions
	 */
	private const EMBEDDING_DIMENSIONS = 1536;

	/**
	 * OpenAI client instance
	 *
	 * @var OpenAiClient
	 */
	private OpenAiClient $openai_client;

	/**
	 * Semantic chunker instance
	 *
	 * @var SemanticChunker
	 */
	private SemanticChunker $chunker;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->openai_client = new OpenAiClient();
		$this->chunker       = new SemanticChunker();
	}

	/**
	 * Generate embeddings for a post
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force regeneration.
	 * @return array<string, mixed> Embeddings data.
	 */
	public function generate_embeddings( int $post_id, bool $force = false ): array {
		// Check cache
		if ( ! $force ) {
			$cached = get_post_meta( $post_id, self::META_EMBEDDINGS, true );

			if ( is_array( $cached ) && ! empty( $cached['embeddings'] ) ) {
				return $cached;
			}
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		// Check if OpenAI is configured
		if ( ! $this->openai_client->is_configured() ) {
			return array(
				'error'   => 'OpenAI not configured',
				'message' => __( 'Configure OpenAI API key to generate embeddings.', 'fp-seo-performance' ),
			);
		}

		// Get semantic chunks
		$chunks = $this->chunker->chunk_content( $post_id, 512 ); // Smaller chunks for embeddings

		if ( empty( $chunks ) ) {
			return array();
		}

		// Generate embeddings for each chunk
		$embeddings_data = $this->generate_chunk_embeddings( $chunks, $post );

		if ( ! empty( $embeddings_data ) ) {
			// Cache embeddings
			update_post_meta( $post_id, self::META_EMBEDDINGS, $embeddings_data );
		}

		return $embeddings_data;
	}

	/**
	 * Generate embeddings for chunks
	 *
	 * @param array<int, array<string, mixed>> $chunks Semantic chunks.
	 * @param WP_Post                          $post   Post object.
	 * @return array<string, mixed> Embeddings data.
	 */
	private function generate_chunk_embeddings( array $chunks, WP_Post $post ): array {
		$chunk_embeddings = array();
		$texts_to_embed   = array();

		// Prepare texts for batch embedding
		foreach ( $chunks as $chunk ) {
			$texts_to_embed[] = $chunk['content'];
		}

		try {
			// Note: OpenAI PHP client doesn't support embeddings API yet in our version
			// This is a placeholder structure for when it's available or using REST API
			$embeddings = $this->call_embeddings_api( $texts_to_embed );

			// Combine chunks with their embeddings
			foreach ( $chunks as $index => $chunk ) {
				$chunk_embeddings[] = array(
					'chunk_id'   => $chunk['chunk_id'],
					'content'    => $chunk['content'],
					'embedding'  => $embeddings[ $index ] ?? array(),
					'keywords'   => $chunk['keywords'],
					'context'    => $chunk['context'],
				);
			}

			// Generate semantic fingerprint (hash of first embedding for quick comparison)
			$fingerprint = ! empty( $embeddings[0] ) ? md5( json_encode( $embeddings[0] ) ) : '';

			return array(
				'model'               => self::EMBEDDING_MODEL,
				'dimensions'          => self::EMBEDDING_DIMENSIONS,
				'chunks'              => $chunk_embeddings,
				'total_chunks'        => count( $chunk_embeddings ),
				'semantic_fingerprint' => $fingerprint,
				'generated_at'        => gmdate( 'c' ),
				'post_id'             => $post->ID,
				'post_title'          => $post->post_title,
				'post_url'            => get_permalink( $post ),
			);

		} catch ( \Exception $e ) {
			error_log( 'FP SEO Embeddings Error: ' . $e->getMessage() );

			return array(
				'error'   => 'API Error',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Call OpenAI Embeddings API
	 *
	 * @param array<int, string> $texts Texts to embed.
	 * @return array<int, array<int, float>> Embeddings vectors.
	 */
	private function call_embeddings_api( array $texts ): array {
		// Get API key from options
		$api_key = \FP\SEO\Utils\Options::get_option( 'ai.openai_api_key', '' );

		if ( empty( $api_key ) ) {
			throw new \Exception( 'OpenAI API key not configured' );
		}

		// Call OpenAI Embeddings API via REST
		$response = wp_remote_post(
			'https://api.openai.com/v1/embeddings',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model' => self::EMBEDDING_MODEL,
					'input' => $texts,
				) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'API request failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$body = wp_remote_retrieve_body( $response );
			throw new \Exception( 'API returned error: ' . $status_code . ' - ' . $body );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['data'] ) ) {
			throw new \Exception( 'Invalid API response format' );
		}

		// Extract embeddings from response
		$embeddings = array();

		foreach ( $data['data'] as $item ) {
			$embeddings[] = $item['embedding'] ?? array();
		}

		return $embeddings;
	}

	/**
	 * Calculate similarity between two embedding vectors (cosine similarity)
	 *
	 * @param array<int, float> $embedding1 First embedding vector.
	 * @param array<int, float> $embedding2 Second embedding vector.
	 * @return float Similarity score (0-1).
	 */
	public function calculate_similarity( array $embedding1, array $embedding2 ): float {
		if ( empty( $embedding1 ) || empty( $embedding2 ) ) {
			return 0.0;
		}

		if ( count( $embedding1 ) !== count( $embedding2 ) ) {
			return 0.0;
		}

		// Calculate cosine similarity
		$dot_product = 0.0;
		$magnitude1  = 0.0;
		$magnitude2  = 0.0;

		for ( $i = 0; $i < count( $embedding1 ); $i++ ) {
			$dot_product += $embedding1[ $i ] * $embedding2[ $i ];
			$magnitude1  += $embedding1[ $i ] * $embedding1[ $i ];
			$magnitude2  += $embedding2[ $i ] * $embedding2[ $i ];
		}

		$magnitude1 = sqrt( $magnitude1 );
		$magnitude2 = sqrt( $magnitude2 );

		if ( $magnitude1 == 0 || $magnitude2 == 0 ) {
			return 0.0;
		}

		// Cosine similarity
		$similarity = $dot_product / ( $magnitude1 * $magnitude2 );

		// Normalize to 0-1 range (cosine similarity is -1 to 1)
		return ( $similarity + 1 ) / 2;
	}

	/**
	 * Find similar content based on embeddings
	 *
	 * @param int   $post_id           Source post ID.
	 * @param int   $limit             Number of similar posts to find.
	 * @param float $min_similarity    Minimum similarity threshold.
	 * @return array<int, array<string, mixed>> Similar posts.
	 */
	public function find_similar_content( int $post_id, int $limit = 5, float $min_similarity = 0.7 ): array {
		$source_embeddings = $this->get_embeddings( $post_id );

		if ( empty( $source_embeddings['chunks'] ) ) {
			return array();
		}

		// Get source embedding (use first chunk as representative)
		$source_embedding = $source_embeddings['chunks'][0]['embedding'] ?? array();

		if ( empty( $source_embedding ) ) {
			return array();
		}

		// Find posts with embeddings
		global $wpdb;

		$posts_with_embeddings = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = %s 
				AND post_id != %d
				LIMIT 100",
				self::META_EMBEDDINGS,
				$post_id
			)
		);

		if ( empty( $posts_with_embeddings ) ) {
			return array();
		}

		// Calculate similarity with each post
		$similarities = array();

		foreach ( $posts_with_embeddings as $compare_post_id ) {
			$compare_embeddings = $this->get_embeddings( (int) $compare_post_id );

			if ( empty( $compare_embeddings['chunks'] ) ) {
				continue;
			}

			$compare_embedding = $compare_embeddings['chunks'][0]['embedding'] ?? array();

			if ( empty( $compare_embedding ) ) {
				continue;
			}

			$similarity = $this->calculate_similarity( $source_embedding, $compare_embedding );

			if ( $similarity >= $min_similarity ) {
				$similarities[] = array(
					'post_id'    => $compare_post_id,
					'similarity' => $similarity,
					'title'      => get_the_title( $compare_post_id ),
					'url'        => get_permalink( $compare_post_id ),
				);
			}
		}

		// Sort by similarity
		usort( $similarities, function ( $a, $b ) {
			return $b['similarity'] <=> $a['similarity'];
		} );

		// Return top N
		return array_slice( $similarities, 0, $limit );
	}

	/**
	 * Get cached embeddings for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Embeddings data.
	 */
	public function get_embeddings( int $post_id ): array {
		$embeddings = get_post_meta( $post_id, self::META_EMBEDDINGS, true );

		return is_array( $embeddings ) ? $embeddings : array();
	}

	/**
	 * Clear embeddings cache
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success.
	 */
	public function clear_embeddings( int $post_id ): bool {
		return delete_post_meta( $post_id, self::META_EMBEDDINGS );
	}

	/**
	 * Get embedding statistics
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Statistics.
	 */
	public function get_embedding_stats( int $post_id ): array {
		$embeddings = $this->get_embeddings( $post_id );

		if ( empty( $embeddings ) ) {
			return array(
				'has_embeddings'  => false,
				'total_chunks'    => 0,
				'model'           => '',
				'dimensions'      => 0,
			);
		}

		return array(
			'has_embeddings'  => true,
			'total_chunks'    => $embeddings['total_chunks'] ?? 0,
			'model'           => $embeddings['model'] ?? '',
			'dimensions'      => $embeddings['dimensions'] ?? 0,
			'generated_at'    => $embeddings['generated_at'] ?? '',
		);
	}

	/**
	 * Batch generate embeddings for multiple posts
	 *
	 * @param array<int, int> $post_ids Post IDs.
	 * @param bool            $force    Force regeneration.
	 * @return array<string, mixed> Batch results.
	 */
	public function batch_generate_embeddings( array $post_ids, bool $force = false ): array {
		$results = array(
			'success' => array(),
			'failed'  => array(),
			'skipped' => array(),
		);

		foreach ( $post_ids as $post_id ) {
			// Skip if already has embeddings and not forcing
			if ( ! $force ) {
				$existing = $this->get_embeddings( $post_id );

				if ( ! empty( $existing['embeddings'] ) ) {
					$results['skipped'][] = $post_id;
					continue;
				}
			}

			try {
				$embeddings = $this->generate_embeddings( $post_id, $force );

				if ( ! isset( $embeddings['error'] ) ) {
					$results['success'][] = $post_id;
				} else {
					$results['failed'][] = array(
						'post_id' => $post_id,
						'error'   => $embeddings['message'] ?? 'Unknown error',
					);
				}
			} catch ( \Exception $e ) {
				$results['failed'][] = array(
					'post_id' => $post_id,
					'error'   => $e->getMessage(),
				);
			}

			// Rate limiting: sleep briefly between requests
			usleep( 500000 ); // 0.5 seconds
		}

		return $results;
	}
}


