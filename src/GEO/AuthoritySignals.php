<?php
/**
 * Authority & Trust Signals for AI Engines
 *
 * Provides authority and trust signals to AI engines to improve citation likelihood.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use WP_Post;

/**
 * Manages authority and trust signals for AI consumption
 */
class AuthoritySignals {

	/**
	 * Get comprehensive authority signals for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Authority signals.
	 */
	public function get_authority_signals( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		return array(
			'author'          => $this->get_author_authority( $post ),
			'content_signals' => $this->get_content_signals( $post ),
			'site_signals'    => $this->get_site_signals(),
			'references'      => $this->get_references( $post ),
			'social_signals'  => $this->get_social_signals( $post ),
			'technical_signals' => $this->get_technical_signals( $post ),
			'overall_score'   => $this->calculate_overall_authority_score( $post ),
		);
	}

	/**
	 * Get author authority data
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Author authority data.
	 */
	private function get_author_authority( WP_Post $post ): array {
		$author_id = $post->post_author;
		$author    = get_userdata( $author_id );

		if ( ! $author ) {
			return array();
		}

		return array(
			'name'        => $author->display_name,
			'credentials' => $this->get_author_credentials( $author_id ),
			'expertise_areas' => $this->get_expertise_areas( $author_id ),
			'social_proof' => $this->get_author_social_proof( $author_id ),
			'website'     => get_author_posts_url( $author_id ),
			'verification' => $this->get_author_verification( $author_id ),
			'bio'         => get_user_meta( $author_id, 'description', true ),
		);
	}

	/**
	 * Get author credentials
	 *
	 * @param int $author_id Author ID.
	 * @return array<string, mixed> Credentials.
	 */
	private function get_author_credentials( int $author_id ): array {
		$certifications = get_user_meta( $author_id, 'fp_author_certifications', true );
		$certifications = is_array( $certifications ) ? $certifications : array();

		return array(
			'title'               => get_user_meta( $author_id, 'fp_author_title', true ) ?: 'Content Creator',
			'certifications'      => array_map( 'sanitize_text_field', $certifications ),
			'experience_years'    => (int) get_user_meta( $author_id, 'fp_author_experience_years', true ),
			'publications'        => count_user_posts( $author_id, 'post', true ),
			'speaking_engagements' => (int) get_user_meta( $author_id, 'fp_author_speaking_engagements', true ),
			'education'           => get_user_meta( $author_id, 'fp_author_education', true ),
		);
	}

	/**
	 * Get expertise areas
	 *
	 * @param int $author_id Author ID.
	 * @return array<int, string> Expertise areas.
	 */
	private function get_expertise_areas( int $author_id ): array {
		$expertise = get_user_meta( $author_id, 'fp_author_expertise', true );

		if ( ! is_array( $expertise ) ) {
			// Auto-detect from categories of posts
			return $this->auto_detect_expertise( $author_id );
		}

		return array_map( 'sanitize_text_field', $expertise );
	}

	/**
	 * Auto-detect expertise from author's posts
	 *
	 * @param int $author_id Author ID.
	 * @return array<int, string> Detected expertise areas.
	 */
	private function auto_detect_expertise( int $author_id ): array {
		$posts = get_posts( array(
			'author'         => $author_id,
			'posts_per_page' => 50,
			'post_status'    => 'publish',
		) );

		$all_categories = array();

		foreach ( $posts as $post ) {
			$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
			$all_categories = array_merge( $all_categories, $categories );
		}

		// Count category frequencies
		$category_counts = array_count_values( $all_categories );

		// Sort by frequency
		arsort( $category_counts );

		// Return top 5
		return array_slice( array_keys( $category_counts ), 0, 5 );
	}

	/**
	 * Get author social proof
	 *
	 * @param int $author_id Author ID.
	 * @return array<string, int> Social proof metrics.
	 */
	private function get_author_social_proof( int $author_id ): array {
		return array(
			'followers'    => (int) get_user_meta( $author_id, 'fp_author_followers', true ),
			'endorsements' => (int) get_user_meta( $author_id, 'fp_author_endorsements', true ),
			'citations'    => (int) get_user_meta( $author_id, 'fp_author_citations', true ),
		);
	}

	/**
	 * Get author verification status
	 *
	 * @param int $author_id Author ID.
	 * @return array<string, mixed> Verification data.
	 */
	private function get_author_verification( int $author_id ): array {
		$verified    = (bool) get_user_meta( $author_id, 'fp_author_verified', true );
		$verified_at = get_user_meta( $author_id, 'fp_author_verified_at', true );

		return array(
			'type'        => $verified ? 'domain_verified' : 'unverified',
			'verified_at' => $verified && $verified_at ? gmdate( 'c', strtotime( $verified_at ) ) : null,
			'verified'    => $verified,
		);
	}

	/**
	 * Get content quality signals
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Content signals.
	 */
	private function get_content_signals( WP_Post $post ): array {
		return array(
			'fact_checked'       => $this->is_fact_checked( $post ),
			'peer_reviewed'      => $this->is_peer_reviewed( $post ),
			'references_count'   => $this->count_references( $post ),
			'external_citations' => $this->count_external_citations( $post ),
			'backlinks_quality'  => $this->estimate_backlinks_quality( $post ),
			'content_depth_score' => $this->calculate_content_depth( $post ),
			'originality_score'  => $this->estimate_originality( $post ),
			'accuracy_score'     => $this->estimate_accuracy( $post ),
		);
	}

	/**
	 * Check if content is fact-checked
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if fact-checked.
	 */
	private function is_fact_checked( WP_Post $post ): bool {
		return (bool) get_post_meta( $post->ID, '_fp_seo_fact_checked', true );
	}

	/**
	 * Check if content is peer-reviewed
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if peer-reviewed.
	 */
	private function is_peer_reviewed( WP_Post $post ): bool {
		return (bool) get_post_meta( $post->ID, '_fp_seo_peer_reviewed', true );
	}

	/**
	 * Count references in content
	 *
	 * @param WP_Post $post Post object.
	 * @return int Reference count.
	 */
	private function count_references( WP_Post $post ): int {
		$references = get_post_meta( $post->ID, '_fp_seo_sources', true );

		if ( ! is_array( $references ) ) {
			// Auto-detect from content links
			return $this->auto_count_references( $post );
		}

		return count( $references );
	}

	/**
	 * Auto-count references from content links
	 *
	 * @param WP_Post $post Post object.
	 * @return int Estimated reference count.
	 */
	private function auto_count_references( WP_Post $post ): int {
		$content = $post->post_content;

		// Count external links (excluding own domain)
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\']/i', $content, $matches );

		$external_links = 0;
		$home_url       = home_url();

		foreach ( $matches[1] as $url ) {
			if ( strpos( $url, $home_url ) === false && strpos( $url, 'http' ) === 0 ) {
				$external_links++;
			}
		}

		return $external_links;
	}

	/**
	 * Count external citations (how many times this content is cited)
	 *
	 * @param WP_Post $post Post object.
	 * @return int Citation count.
	 */
	private function count_external_citations( WP_Post $post ): int {
		// This would ideally be fetched from external API (e.g., Ahrefs, Moz)
		// For now, use cached value if available
		return (int) get_post_meta( $post->ID, '_fp_seo_external_citations', true );
	}

	/**
	 * Estimate backlinks quality score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Quality score (0-1).
	 */
	private function estimate_backlinks_quality( WP_Post $post ): float {
		// This would ideally use backlink data from SEO tools
		// For now, estimate based on engagement metrics
		$views    = (int) get_post_meta( $post->ID, '_fp_seo_views', true );
		$shares   = (int) get_post_meta( $post->ID, '_fp_seo_shares', true );
		$comments = wp_count_comments( $post->ID );

		$engagement = $views + ( $shares * 10 ) + ( $comments->approved * 5 );

		// Normalize to 0-1 (assume 1000 engagement is excellent)
		return min( 1.0, $engagement / 1000 );
	}

	/**
	 * Calculate content depth score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Depth score (0-1).
	 */
	private function calculate_content_depth( WP_Post $post ): float {
		$content    = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );

		// Count headings (indicates structure)
		$heading_count = substr_count( $post->post_content, '<h2' ) + substr_count( $post->post_content, '<h3' );

		// Count images
		$image_count = substr_count( $post->post_content, '<img' );

		// Calculate depth score
		$word_score    = min( 1.0, $word_count / 2000 ); // 2000 words = perfect
		$structure_score = min( 1.0, $heading_count / 10 ); // 10 headings = perfect
		$media_score   = min( 1.0, $image_count / 5 ); // 5 images = perfect

		// Weighted average
		return ( $word_score * 0.5 ) + ( $structure_score * 0.3 ) + ( $media_score * 0.2 );
	}

	/**
	 * Estimate content originality
	 *
	 * @param WP_Post $post Post object.
	 * @return float Originality score (0-1).
	 */
	private function estimate_originality( WP_Post $post ): float {
		// This would ideally use plagiarism detection API
		// For now, use heuristics
		$content = wp_strip_all_tags( $post->post_content );

		// Check for quotes (indicates research, not pure plagiarism)
		$quote_count = substr_count( $content, '"' ) / 2;

		// Longer content with citations is likely more original
		$word_count = str_word_count( $content );
		$ref_count  = $this->count_references( $post );

		if ( $word_count > 1500 && $ref_count > 5 ) {
			return 0.9;
		}

		if ( $word_count > 1000 && $ref_count > 3 ) {
			return 0.8;
		}

		return 0.7; // Default moderate score
	}

	/**
	 * Estimate content accuracy
	 *
	 * @param WP_Post $post Post object.
	 * @return float Accuracy score (0-1).
	 */
	private function estimate_accuracy( WP_Post $post ): float {
		$score = 0.7; // Base score

		// Fact-checked = higher accuracy
		if ( $this->is_fact_checked( $post ) ) {
			$score += 0.2;
		}

		// Has references = higher accuracy
		$ref_count = $this->count_references( $post );
		if ( $ref_count >= 10 ) {
			$score += 0.1;
		}

		return min( 1.0, $score );
	}

	/**
	 * Get site-wide signals
	 *
	 * @return array<string, mixed> Site signals.
	 */
	private function get_site_signals(): array {
		return array(
			'domain_age'             => $this->get_domain_age(),
			'https'                  => is_ssl(),
			'privacy_policy'         => $this->has_privacy_policy(),
			'contact_info_verified'  => $this->has_contact_info(),
			'about_page'             => $this->has_about_page(),
			'editorial_guidelines'   => $this->has_editorial_guidelines(),
			'sitemap_available'      => $this->has_sitemap(),
			'robots_txt_available'   => $this->has_robots_txt(),
		);
	}

	/**
	 * Get domain age in years
	 *
	 * @return int Domain age in years.
	 */
	private function get_domain_age(): int {
		// This would ideally use WHOIS data
		// For now, estimate from oldest post
		$oldest_post = get_posts( array(
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'ASC',
		) );

		if ( empty( $oldest_post ) ) {
			return 0;
		}

		$published_timestamp = strtotime( $oldest_post[0]->post_date );
		$years               = ( time() - $published_timestamp ) / YEAR_IN_SECONDS;

		return (int) $years;
	}

	/**
	 * Check if site has privacy policy
	 *
	 * @return bool True if privacy policy exists.
	 */
	private function has_privacy_policy(): bool {
		$privacy_policy_id = (int) get_option( 'wp_page_for_privacy_policy' );
		return $privacy_policy_id > 0;
	}

	/**
	 * Check if site has contact info
	 *
	 * @return bool True if contact info is available.
	 */
	private function has_contact_info(): bool {
		// Check for contact page
		$pages = get_pages( array(
			'number' => 100,
		) );

		foreach ( $pages as $page ) {
			if ( stripos( $page->post_title, 'contact' ) !== false || stripos( $page->post_title, 'contatti' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if site has about page
	 *
	 * @return bool True if about page exists.
	 */
	private function has_about_page(): bool {
		$pages = get_pages( array(
			'number' => 100,
		) );

		foreach ( $pages as $page ) {
			if ( stripos( $page->post_title, 'about' ) !== false || stripos( $page->post_title, 'chi siamo' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if site has editorial guidelines
	 *
	 * @return bool True if editorial guidelines exist.
	 */
	private function has_editorial_guidelines(): bool {
		return (bool) get_option( 'fp_seo_has_editorial_guidelines', false );
	}

	/**
	 * Check if sitemap is available
	 *
	 * @return bool True if sitemap exists.
	 */
	private function has_sitemap(): bool {
		// Check common sitemap locations
		$sitemap_url = home_url( '/sitemap.xml' );
		$response    = wp_remote_head( $sitemap_url, array( 'timeout' => 5 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Check if robots.txt is available
	 *
	 * @return bool True if robots.txt exists.
	 */
	private function has_robots_txt(): bool {
		$robots_url = home_url( '/robots.txt' );
		$response   = wp_remote_head( $robots_url, array( 'timeout' => 5 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Get references with quality scores
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> References.
	 */
	private function get_references( WP_Post $post ): array {
		$sources = get_post_meta( $post->ID, '_fp_seo_sources', true );

		if ( ! is_array( $sources ) ) {
			return array();
		}

		return array_map( function ( $source ) {
			return array(
				'url'             => esc_url_raw( $source['url'] ?? '' ),
				'title'           => sanitize_text_field( $source['title'] ?? '' ),
				'authority_score' => $this->estimate_source_authority( $source['url'] ?? '' ),
				'date'            => isset( $source['date'] ) ? gmdate( 'c', strtotime( $source['date'] ) ) : null,
			);
		}, $sources );
	}

	/**
	 * Estimate source authority score
	 *
	 * @param string $url Source URL.
	 * @return float Authority score (0-1).
	 */
	private function estimate_source_authority( string $url ): float {
		if ( empty( $url ) ) {
			return 0.5;
		}

		// High-authority domains
		$high_authority = array( 'wikipedia.org', 'gov', 'edu', 'nih.gov', 'who.int', 'nature.com', 'sciencedirect.com' );

		foreach ( $high_authority as $domain ) {
			if ( strpos( $url, $domain ) !== false ) {
				return 0.95;
			}
		}

		// Medium authority (news sites)
		$medium_authority = array( 'bbc.com', 'cnn.com', 'nytimes.com', 'reuters.com' );

		foreach ( $medium_authority as $domain ) {
			if ( strpos( $url, $domain ) !== false ) {
				return 0.8;
			}
		}

		return 0.6; // Default moderate score
	}

	/**
	 * Get social signals
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, int> Social signals.
	 */
	private function get_social_signals( WP_Post $post ): array {
		return array(
			'shares'   => (int) get_post_meta( $post->ID, '_fp_seo_shares', true ),
			'likes'    => (int) get_post_meta( $post->ID, '_fp_seo_likes', true ),
			'comments' => wp_count_comments( $post->ID )->approved,
		);
	}

	/**
	 * Get technical signals
	 *
	 * @param WP_Post $post Post object.
	 * @return array<string, mixed> Technical signals.
	 */
	private function get_technical_signals( WP_Post $post ): array {
		return array(
			'schema_markup'    => $this->has_schema_markup( $post ),
			'mobile_friendly'  => true, // Assume yes for WordPress
			'page_speed_score' => $this->get_page_speed_estimate( $post ),
			'accessibility'    => $this->get_accessibility_score( $post ),
		);
	}

	/**
	 * Check if post has schema markup
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True if schema markup exists.
	 */
	private function has_schema_markup( WP_Post $post ): bool {
		return strpos( $post->post_content, 'schema.org' ) !== false || 
		       ! empty( get_post_meta( $post->ID, '_fp_seo_schema', true ) );
	}

	/**
	 * Get page speed estimate
	 *
	 * @param WP_Post $post Post object.
	 * @return int Speed score (0-100).
	 */
	private function get_page_speed_estimate( WP_Post $post ): int {
		// This would ideally use PageSpeed Insights API
		// For now, estimate based on content size
		$content_size = strlen( $post->post_content );
		$image_count  = substr_count( $post->post_content, '<img' );

		$score = 90; // Base good score

		// Penalize for large content
		if ( $content_size > 50000 ) {
			$score -= 10;
		}

		// Penalize for many images
		if ( $image_count > 10 ) {
			$score -= 5;
		}

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Get accessibility score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Accessibility score (0-1).
	 */
	private function get_accessibility_score( WP_Post $post ): float {
		$score = 0.7; // Base score

		// Check for image alt tags
		$img_count     = substr_count( $post->post_content, '<img' );
		$img_alt_count = substr_count( $post->post_content, 'alt=' );

		if ( $img_count > 0 && $img_alt_count === $img_count ) {
			$score += 0.2; // All images have alt tags
		}

		// Check for headings structure
		if ( substr_count( $post->post_content, '<h2' ) > 0 ) {
			$score += 0.1;
		}

		return min( 1.0, $score );
	}

	/**
	 * Calculate overall authority score
	 *
	 * @param WP_Post $post Post object.
	 * @return float Overall authority score (0-1).
	 */
	private function calculate_overall_authority_score( WP_Post $post ): float {
		$author_signals  = $this->get_author_authority( $post );
		$content_signals = $this->get_content_signals( $post );

		// Weighted calculation
		$author_score  = isset( $author_signals['credentials']['publications'] ) && $author_signals['credentials']['publications'] > 50 ? 0.9 : 0.7;
		$content_score = $content_signals['content_depth_score'] ?? 0.7;
		$ref_score     = min( 1.0, ( $content_signals['references_count'] ?? 0 ) / 10 );

		// Weighted average
		return ( $author_score * 0.3 ) + ( $content_score * 0.4 ) + ( $ref_score * 0.3 );
	}
}


