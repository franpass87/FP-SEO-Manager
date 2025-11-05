<?php
/**
 * AI Training Dataset Formatter
 *
 * Formats content as training datasets for AI models (JSONL format).
 * Optimized for potential inclusion in AI training data.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use FP\SEO\AI\QAPairExtractor;
use WP_Post;

/**
 * Formats content for AI training datasets
 */
class TrainingDatasetFormatter {

	/**
	 * Q&A extractor instance
	 *
	 * @var QAPairExtractor
	 */
	private QAPairExtractor $qa_extractor;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->qa_extractor = new QAPairExtractor();
	}

	/**
	 * Format post as training dataset
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Training dataset.
	 */
	public function format_as_training_data( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		return array(
			'dataset_version' => '1.0',
			'type'            => 'qa_training',
			'metadata'        => $this->get_metadata( $post ),
			'examples'        => $this->generate_training_examples( $post ),
			'context'         => $this->get_training_context( $post ),
			'quality_score'   => $this->calculate_dataset_quality( $post ),
		);
	}

	/**
	 * Get metadata for dataset
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Metadata.
	 */
	private function get_metadata( WP_Post $post ): array {
		return array(
			'source'       => get_permalink( $post ),
			'title'        => $post->post_title,
			'author'       => get_the_author_meta( 'display_name', $post->post_author ),
			'published'    => gmdate( 'c', strtotime( $post->post_date_gmt ) ),
			'updated'      => gmdate( 'c', strtotime( $post->post_modified_gmt ) ),
			'language'     => $this->detect_language( $post ),
			'domain'       => $this->get_content_domain( $post ),
			'license'      => $this->get_content_license(),
			'quality_score' => $this->estimate_content_quality( $post ),
			'fact_checked' => $this->is_fact_checked( $post ),
		);
	}

	/**
	 * Generate training examples from post
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Training examples.
	 */
	private function generate_training_examples( WP_Post $post ): array {
		$examples = array();

		// Get Q&A pairs as training examples
		$qa_pairs = $this->qa_extractor->get_qa_pairs( $post->ID );

		if ( empty( $qa_pairs ) ) {
			// Generate them if not exist
			$qa_pairs = $this->qa_extractor->extract_qa_pairs( $post->ID );
		}

		foreach ( $qa_pairs as $pair ) {
			// Only use high-confidence pairs for training
			if ( ( $pair['confidence'] ?? 0 ) < 0.7 ) {
				continue;
			}

			$examples[] = array(
				'input'      => $pair['question'],
				'output'     => $pair['answer'],
				'context'    => $pair['source_section'] ?? '',
				'difficulty' => $this->assess_difficulty( $pair ),
				'keywords'   => $pair['keywords'] ?? array(),
				'quality'    => $pair['confidence'] ?? 0.7,
			);
		}

		// Add factual statements as examples
		$factual_examples = $this->extract_factual_statements( $post );
		$examples         = array_merge( $examples, $factual_examples );

		// Limit total examples
		return array_slice( $examples, 0, 20 );
	}

	/**
	 * Extract factual statements from content
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Factual examples.
	 */
	private function extract_factual_statements( WP_Post $post ): array {
		$content   = wp_strip_all_tags( $post->post_content );
		$examples  = array();

		// Split into sentences
		$sentences = preg_split( '/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = array_filter( array_map( 'trim', $sentences ) );

		foreach ( $sentences as $sentence ) {
			// Look for factual patterns (contains numbers, dates, specific claims)
			if ( $this->is_factual_sentence( $sentence ) ) {
				$examples[] = array(
					'input'      => 'Provide information about: ' . $this->extract_topic( $sentence ),
					'output'     => $sentence,
					'context'    => $post->post_title,
					'difficulty' => 'intermediate',
					'keywords'   => $this->extract_sentence_keywords( $sentence ),
					'quality'    => 0.8,
				);
			}

			// Limit factual examples
			if ( count( $examples ) >= 5 ) {
				break;
			}
		}

		return $examples;
	}

	/**
	 * Check if sentence is factual
	 *
	 * @param string $sentence Sentence to check.
	 * @return bool True if factual.
	 */
	private function is_factual_sentence( string $sentence ): bool {
		// Must be reasonable length
		$word_count = str_word_count( $sentence );
		if ( $word_count < 8 || $word_count > 40 ) {
			return false;
		}

		// Contains numbers (often indicates facts/data)
		if ( preg_match( '/\d+/', $sentence ) ) {
			return true;
		}

		// Contains definitive language
		$factual_patterns = array( 'è', 'sono', 'was', 'were', 'has', 'have', 'contains', 'includes' );

		foreach ( $factual_patterns as $pattern ) {
			if ( stripos( $sentence, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract topic from sentence
	 *
	 * @param string $sentence Sentence.
	 * @return string Topic.
	 */
	private function extract_topic( string $sentence ): string {
		// Extract first few words as topic
		$words = explode( ' ', $sentence );
		$topic = implode( ' ', array_slice( $words, 0, 5 ) );

		return $topic . '...';
	}

	/**
	 * Extract keywords from sentence
	 *
	 * @param string $sentence Sentence.
	 * @return array<int, string> Keywords.
	 */
	private function extract_sentence_keywords( string $sentence ): array {
		$words = str_word_count( strtolower( $sentence ), 1 );

		// Remove stopwords
		$stopwords = array( 'il', 'lo', 'la', 'i', 'gli', 'le', 'di', 'a', 'da', 'in', 'con', 'per', 'the', 'a', 'an', 'of', 'to', 'in' );
		$words     = array_diff( $words, $stopwords );

		// Filter short words
		$words = array_filter( $words, function ( $word ) {
			return strlen( $word ) > 3;
		} );

		return array_slice( array_values( $words ), 0, 3 );
	}

	/**
	 * Assess difficulty level of Q&A pair
	 *
	 * @param array<string, mixed> $pair Q&A pair.
	 * @return string Difficulty level.
	 */
	private function assess_difficulty( array $pair ): string {
		$question = $pair['question'] ?? '';
		$answer   = $pair['answer'] ?? '';

		// Simple heuristics
		$answer_word_count = str_word_count( $answer );

		// Longer, complex answers = harder
		if ( $answer_word_count > 100 ) {
			return 'advanced';
		}

		if ( $answer_word_count > 50 ) {
			return 'intermediate';
		}

		// Question complexity
		$complex_words = array( 'perché', 'come', 'differenza', 'confronta', 'spiega', 'why', 'how', 'difference', 'compare', 'explain' );

		foreach ( $complex_words as $word ) {
			if ( stripos( $question, $word ) !== false ) {
				return 'intermediate';
			}
		}

		return 'beginner';
	}

	/**
	 * Get training context
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Context.
	 */
	private function get_training_context( WP_Post $post ): array {
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags       = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );

		return array(
			'domain'      => implode( ', ', $categories ),
			'topics'      => array_slice( $tags, 0, 10 ),
			'post_type'   => $post->post_type,
			'word_count'  => str_word_count( wp_strip_all_tags( $post->post_content ) ),
			'target_audience' => $this->detect_target_audience( $post ),
		);
	}

	/**
	 * Detect target audience
	 *
	 * @param WP_Post $post Post object.
	 * @return string Target audience.
	 */
	private function detect_target_audience( WP_Post $post ): string {
		$content = strtolower( wp_strip_all_tags( $post->post_content ) );

		// Technical indicators
		if ( preg_match( '/\b(api|function|method|class|database|sql|code)\b/', $content ) ) {
			return 'technical';
		}

		// Business indicators
		if ( preg_match( '/\b(business|strategy|marketing|sales|revenue)\b/', $content ) ) {
			return 'business';
		}

		// Beginner indicators
		if ( preg_match( '/\b(guida|tutorial|introduzione|beginner|guide|introduction)\b/', $content ) ) {
			return 'beginner';
		}

		return 'general';
	}

	/**
	 * Calculate dataset quality score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Quality score (0-1).
	 */
	private function calculate_dataset_quality( WP_Post $post ): float {
		$score = 0.5; // Base score

		// Content length (longer = more comprehensive)
		$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
		if ( $word_count > 2000 ) {
			$score += 0.2;
		} elseif ( $word_count > 1000 ) {
			$score += 0.1;
		}

		// Has Q&A pairs
		$qa_pairs = $this->qa_extractor->get_qa_pairs( $post->ID );
		if ( count( $qa_pairs ) > 5 ) {
			$score += 0.2;
		}

		// Is fact-checked
		if ( $this->is_fact_checked( $post ) ) {
			$score += 0.1;
		}

		return min( 1.0, $score );
	}

	/**
	 * Detect content language
	 *
	 * @param WP_Post $post Post object.
	 * @return string Language code.
	 */
	private function detect_language( WP_Post $post ): string {
		$locale = get_locale();

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
	 * Get content domain
	 *
	 * @param WP_Post $post Post object.
	 * @return string Content domain.
	 */
	private function get_content_domain( WP_Post $post ): string {
		$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );

		if ( ! empty( $categories ) ) {
			return implode( ', ', array_slice( $categories, 0, 3 ) );
		}

		return 'General';
	}

	/**
	 * Get content license
	 *
	 * @return string License.
	 */
	private function get_content_license(): string {
		// Get from options or default
		$license = get_option( 'fp_seo_content_license', 'All Rights Reserved' );

		return sanitize_text_field( $license );
	}

	/**
	 * Estimate content quality
	 *
	 * @param WP_Post $post Post object.
	 * @return float Quality score (0-1).
	 */
	private function estimate_content_quality( WP_Post $post ): float {
		$score = 0.6; // Base

		// Readability (word count, sentence structure)
		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );

		if ( $word_count > 1500 ) {
			$score += 0.2;
		}

		// Has images
		if ( has_post_thumbnail( $post->ID ) ) {
			$score += 0.1;
		}

		// Recent content
		$age_days = ( time() - strtotime( $post->post_modified_gmt ) ) / DAY_IN_SECONDS;
		if ( $age_days < 180 ) {
			$score += 0.1;
		}

		return min( 1.0, $score );
	}

	/**
	 * Check if post is fact-checked
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if fact-checked.
	 */
	private function is_fact_checked( WP_Post $post ): bool {
		return (bool) get_post_meta( $post->ID, '_fp_seo_fact_checked', true );
	}

	/**
	 * Export dataset as JSONL format (for AI training)
	 *
	 * @param array<int, int> $post_ids Post IDs to export.
	 * @return string JSONL content.
	 */
	public function export_as_jsonl( array $post_ids ): string {
		$jsonl_lines = array();

		foreach ( $post_ids as $post_id ) {
			$dataset = $this->format_as_training_data( $post_id );

			if ( empty( $dataset ) || empty( $dataset['examples'] ) ) {
				continue;
			}

			// Each example becomes a JSONL line
			foreach ( $dataset['examples'] as $example ) {
				$jsonl_entry = array(
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => $example['input'],
						),
						array(
							'role'    => 'assistant',
							'content' => $example['output'],
						),
					),
					'metadata' => array(
						'source'     => $dataset['metadata']['source'] ?? '',
						'domain'     => $dataset['context']['domain'] ?? '',
						'difficulty' => $example['difficulty'] ?? 'intermediate',
						'quality'    => $example['quality'] ?? 0.7,
					),
				);

				$jsonl_lines[] = wp_json_encode( $jsonl_entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
		}

		return implode( "\n", $jsonl_lines );
	}

	/**
	 * Export dataset for site (all published posts)
	 *
	 * @param int $limit Maximum posts to export.
	 * @return string JSONL content.
	 */
	public function export_site_dataset( int $limit = 100 ): string {
		$posts = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		) );

		$post_ids = wp_list_pluck( $posts, 'ID' );

		return $this->export_as_jsonl( $post_ids );
	}
}


