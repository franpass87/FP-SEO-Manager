<?php
/**
 * Semantic Chunking Engine for AI Context Windows
 *
 * Divides content into semantically meaningful chunks optimized for AI engines.
 * Respects context window limits while maintaining semantic coherence.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use WP_Post;

/**
 * Chunks content semantically for AI consumption
 */
class SemanticChunker {

	/**
	 * Maximum tokens per chunk (safe for all AI models)
	 */
	private const MAX_TOKENS_PER_CHUNK = 2048;

	/**
	 * Overlap tokens between chunks for context continuity
	 */
	private const OVERLAP_TOKENS = 200;

	/**
	 * Average characters per token (rough estimate for Italian/English)
	 */
	private const CHARS_PER_TOKEN = 4;

	/**
	 * Chunk content into semantic pieces
	 *
	 * @param int $post_id    Post ID.
	 * @param int $max_tokens Maximum tokens per chunk.
	 * @return array<int, array<string, mixed>> Semantic chunks.
	 */
	public function chunk_content( int $post_id, int $max_tokens = self::MAX_TOKENS_PER_CHUNK ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		// Prepare content
		$content = $this->prepare_content( $post );

		// Parse content structure
		$sections = $this->parse_sections( $content, $post );

		// Create chunks from sections
		$chunks = $this->create_chunks_from_sections( $sections, $post, $max_tokens );

		// Add metadata to chunks
		return $this->enrich_chunks( $chunks, $post );
	}

	/**
	 * Prepare content for chunking
	 *
	 * @param WP_Post $post Post object.
	 * @return string Prepared content.
	 */
	private function prepare_content( WP_Post $post ): string {
		$content = $post->post_content;

		// Remove shortcodes but keep their content
		$content = strip_shortcodes( $content );

		// Preserve headings structure (we'll use them for chunking)
		// Keep HTML structure for now (will parse it)

		return $content;
	}

	/**
	 * Parse content into logical sections based on headings
	 *
	 * @param string  $content HTML content.
	 * @param WP_Post $post    Post object.
	 * @return array<int, array<string, mixed>> Sections.
	 */
	private function parse_sections( string $content, WP_Post $post ): array {
		$sections = array();

		// Add introduction section (content before first heading)
		$intro_pattern = '/^(.*?)(?=<h[2-6]|$)/is';
		if ( preg_match( $intro_pattern, $content, $matches ) ) {
			$intro_content = trim( $matches[1] );
			if ( ! empty( $intro_content ) ) {
				$sections[] = array(
					'level'   => 1,
					'title'   => 'Introduction',
					'content' => $intro_content,
					'path'    => array( $post->post_title ),
				);
			}
		}

		// Split by headings (h2-h6)
		$pattern = '/<h([2-6])[^>]*>(.*?)<\/h\1>(.*?)(?=<h[2-6]|$)/is';
		preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER );

		$path_stack = array( $post->post_title );

		foreach ( $matches as $match ) {
			$level         = (int) $match[1];
			$heading_title = wp_strip_all_tags( $match[2] );
			$section_content = $match[3];

			// Update breadcrumb path
			// Remove deeper levels if we went back up
			while ( count( $path_stack ) > $level ) {
				array_pop( $path_stack );
			}

			$path_stack[] = $heading_title;

			$sections[] = array(
				'level'   => $level,
				'title'   => $heading_title,
				'content' => trim( $section_content ),
				'path'    => $path_stack, // Breadcrumb trail
			);
		}

		return $sections;
	}

	/**
	 * Create chunks from sections respecting token limits
	 *
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @param WP_Post                          $post     Post object.
	 * @param int                              $max_tokens Max tokens per chunk.
	 * @return array<int, array<string, mixed>> Chunks.
	 */
	private function create_chunks_from_sections( array $sections, WP_Post $post, int $max_tokens ): array {
		$chunks         = array();
		$current_chunk  = '';
		$current_path   = array();
		$current_topics = array();
		$chunk_index    = 0;

		foreach ( $sections as $section ) {
			$section_content = wp_strip_all_tags( $section['content'] );
			$section_tokens  = $this->estimate_tokens( $section_content );

			// If section alone exceeds limit, split it further
			if ( $section_tokens > $max_tokens ) {
				// Save current chunk if not empty
				if ( ! empty( $current_chunk ) ) {
					$chunks[] = $this->create_chunk(
						$chunk_index++,
						$current_chunk,
						$current_path,
						$current_topics,
						$post
					);
					$current_chunk  = '';
					$current_topics = array();
				}

				// Split large section into paragraphs
				$paragraphs = $this->split_into_paragraphs( $section_content );
				$para_chunk = '';

				foreach ( $paragraphs as $paragraph ) {
					$para_tokens = $this->estimate_tokens( $para_chunk . "\n\n" . $paragraph );

					if ( $para_tokens > $max_tokens && ! empty( $para_chunk ) ) {
						// Save paragraph chunk
						$chunks[] = $this->create_chunk(
							$chunk_index++,
							$para_chunk,
							$section['path'],
							array( $section['title'] ),
							$post
						);
						$para_chunk = $paragraph; // Start new chunk with current paragraph
					} else {
						$para_chunk .= ( empty( $para_chunk ) ? '' : "\n\n" ) . $paragraph;
					}
				}

				// Save remaining paragraph chunk
				if ( ! empty( $para_chunk ) ) {
					$chunks[] = $this->create_chunk(
						$chunk_index++,
						$para_chunk,
						$section['path'],
						array( $section['title'] ),
						$post
					);
				}

				$current_path = $section['path'];
				continue;
			}

			// Try to add section to current chunk
			$combined         = $current_chunk . "\n\n" . $section_content;
			$combined_tokens  = $this->estimate_tokens( $combined );

			if ( $combined_tokens > $max_tokens && ! empty( $current_chunk ) ) {
				// Save current chunk
				$chunks[] = $this->create_chunk(
					$chunk_index++,
					$current_chunk,
					$current_path,
					$current_topics,
					$post
				);

				// Start new chunk with overlap
				$overlap        = $this->get_overlap_text( $current_chunk );
				$current_chunk  = $overlap . "\n\n" . $section_content;
				$current_path   = $section['path'];
				$current_topics = array( $section['title'] );
			} else {
				// Add to current chunk
				$current_chunk   = empty( $current_chunk ) ? $section_content : $combined;
				$current_path    = $section['path'];
				$current_topics[] = $section['title'];
			}
		}

		// Save final chunk
		if ( ! empty( $current_chunk ) ) {
			$chunks[] = $this->create_chunk(
				$chunk_index,
				$current_chunk,
				$current_path,
				$current_topics,
				$post
			);
		}

		return $chunks;
	}

	/**
	 * Split text into paragraphs
	 *
	 * @param string $text Text to split.
	 * @return array<int, string> Paragraphs.
	 */
	private function split_into_paragraphs( string $text ): array {
		// Split by double newlines or paragraph tags
		$paragraphs = preg_split( '/\n\s*\n|<\/?p[^>]*>/i', $text );

		// Filter empty paragraphs
		return array_values( array_filter( array_map( 'trim', $paragraphs ) ) );
	}

	/**
	 * Create a single chunk with metadata
	 *
	 * @param int                  $index   Chunk index.
	 * @param string               $content Chunk content.
	 * @param array<int, string>   $path    Breadcrumb path.
	 * @param array<int, string>   $topics  Section topics.
	 * @param WP_Post              $post    Post object.
	 * @return array<string, mixed> Chunk data.
	 */
	private function create_chunk( int $index, string $content, array $path, array $topics, WP_Post $post ): array {
		return array(
			'chunk_id'   => $index + 1,
			'content'    => trim( $content ),
			'context'    => implode( ' > ', $path ),
			'topics'     => array_unique( $topics ),
			'keywords'   => $this->extract_keywords( $content ),
			'entities'   => $this->extract_entities( $content ),
			'token_count' => $this->estimate_tokens( $content ),
			'char_count' => strlen( $content ),
			'word_count' => str_word_count( $content ),
		);
	}

	/**
	 * Get overlap text from end of previous chunk
	 *
	 * @param string $text Text to get overlap from.
	 * @return string Overlap text.
	 */
	private function get_overlap_text( string $text ): string {
		$max_chars = self::OVERLAP_TOKENS * self::CHARS_PER_TOKEN;

		if ( mb_strlen( $text ) <= $max_chars ) {
			return $text;
		}

		// Get last N characters
		$overlap = mb_substr( $text, -$max_chars );

		// Find last sentence boundary
		$last_period = mb_strrpos( $overlap, '.' );

		if ( $last_period !== false ) {
			return mb_substr( $overlap, $last_period + 1 );
		}

		return $overlap;
	}

	/**
	 * Estimate token count for text
	 *
	 * @param string $text Text to estimate.
	 * @return int Estimated tokens.
	 */
	private function estimate_tokens( string $text ): int {
		// Rough estimate: 1 token ≈ 4 characters (for Italian/English)
		return (int) ceil( mb_strlen( $text ) / self::CHARS_PER_TOKEN );
	}

	/**
	 * Extract keywords from chunk
	 *
	 * @param string $content Chunk content.
	 * @return array<int, string> Keywords.
	 */
	private function extract_keywords( string $content ): array {
		// Simple TF-IDF-like extraction
		$words = str_word_count( strtolower( $content ), 1, 'àèéìòù' );

		// Remove stopwords
		$stopwords = array(
			'il', 'lo', 'la', 'i', 'gli', 'le',
			'un', 'uno', 'una',
			'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
			'che', 'e', 'è', 'sono', 'del', 'della', 'dei', 'delle',
			'questo', 'quello', 'questi', 'quelli',
			'come', 'quando', 'dove', 'perché', 'se',
		);

		$words = array_diff( $words, $stopwords );

		// Count word frequencies
		$word_counts = array_count_values( $words );

		// Filter short words
		$word_counts = array_filter( $word_counts, function ( $count, $word ) {
			return strlen( $word ) > 3 && $count > 1;
		}, ARRAY_FILTER_USE_BOTH );

		// Sort by frequency
		arsort( $word_counts );

		// Return top 10
		return array_slice( array_keys( $word_counts ), 0, 10 );
	}

	/**
	 * Extract named entities from chunk
	 *
	 * @param string $content Chunk content.
	 * @return array<int, string> Entities.
	 */
	private function extract_entities( string $content ): array {
		$entities = array();

		// Find capitalized words (potential named entities)
		preg_match_all( '/\b[A-ZÀÈÉÌÒÙ][a-zàèéìòù]{2,}(?:\s+[A-ZÀÈÉÌÒÙ][a-zàèéìòù]{2,})*\b/', $content, $matches );

		if ( ! empty( $matches[0] ) ) {
			// Count occurrences
			$entity_counts = array_count_values( $matches[0] );

			// Filter entities that appear more than once
			$entities = array_keys( array_filter( $entity_counts, function ( $count ) {
				return $count > 1;
			} ) );

			// Limit to top 10
			$entities = array_slice( $entities, 0, 10 );
		}

		return $entities;
	}

	/**
	 * Enrich chunks with additional metadata
	 *
	 * @param array<int, array<string, mixed>> $chunks Chunks.
	 * @param WP_Post                          $post   Post object.
	 * @return array<int, array<string, mixed>> Enriched chunks.
	 */
	private function enrich_chunks( array $chunks, WP_Post $post ): array {
		$total_chunks = count( $chunks );

		foreach ( $chunks as $index => &$chunk ) {
			// Add navigation
			$chunk['prev_chunk'] = $index > 0 ? $index : null;
			$chunk['next_chunk'] = $index < $total_chunks - 1 ? $index + 2 : null;

			// Add confidence score (how well this chunk represents the topic)
			$chunk['confidence_score'] = $this->calculate_chunk_confidence( $chunk, $post );

			// Add semantic fingerprint (for similarity matching)
			$chunk['fingerprint'] = md5( implode( ',', $chunk['keywords'] ) );

			// Add source info
			$chunk['source'] = array(
				'post_id'    => $post->ID,
				'post_title' => $post->post_title,
				'post_url'   => get_permalink( $post ),
				'author'     => get_the_author_meta( 'display_name', $post->post_author ),
				'published'  => gmdate( 'c', strtotime( $post->post_date_gmt ) ),
				'updated'    => gmdate( 'c', strtotime( $post->post_modified_gmt ) ),
			);
		}

		return $chunks;
	}

	/**
	 * Calculate confidence score for a chunk
	 *
	 * @param array<string, mixed> $chunk Chunk data.
	 * @param WP_Post              $post  Post object.
	 * @return float Confidence score (0-1).
	 */
	private function calculate_chunk_confidence( array $chunk, WP_Post $post ): float {
		$score = 0.5; // Base score

		// Longer chunks = more complete info
		$word_count = $chunk['word_count'] ?? 0;
		if ( $word_count > 200 ) {
			$score += 0.2;
		}

		// More keywords = more informative
		$keyword_count = count( $chunk['keywords'] ?? array() );
		if ( $keyword_count > 5 ) {
			$score += 0.2;
		}

		// Has entities = more concrete
		if ( ! empty( $chunk['entities'] ) ) {
			$score += 0.1;
		}

		return min( 1.0, $score );
	}

	/**
	 * Get chunk by ID
	 *
	 * @param int $post_id  Post ID.
	 * @param int $chunk_id Chunk ID.
	 * @return array<string, mixed>|null Chunk data or null.
	 */
	public function get_chunk( int $post_id, int $chunk_id ): ?array {
		$chunks = $this->chunk_content( $post_id );

		foreach ( $chunks as $chunk ) {
			if ( $chunk['chunk_id'] === $chunk_id ) {
				return $chunk;
			}
		}

		return null;
	}

	/**
	 * Get chunks with minimum confidence
	 *
	 * @param int   $post_id        Post ID.
	 * @param float $min_confidence Minimum confidence threshold.
	 * @return array<int, array<string, mixed>> High-confidence chunks.
	 */
	public function get_high_confidence_chunks( int $post_id, float $min_confidence = 0.7 ): array {
		$chunks = $this->chunk_content( $post_id );

		return array_values( array_filter( $chunks, function ( $chunk ) use ( $min_confidence ) {
			return ( $chunk['confidence_score'] ?? 0 ) >= $min_confidence;
		} ) );
	}
}


