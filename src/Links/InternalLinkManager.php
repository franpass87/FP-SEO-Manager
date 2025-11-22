<?php
/**
 * Internal Link Manager
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Links;

use FP\SEO\Utils\Cache;
use FP\SEO\Utils\PerformanceConfig;

/**
 * Handles internal link suggestions and optimization.
 */
class InternalLinkManager {

	/**
	 * Link suggestion cache group.
	 */
	private const CACHE_GROUP = 'fp_seo_internal_links';

	/**
	 * Minimum content length for link suggestions.
	 */
	private const MIN_CONTENT_LENGTH = 100;

	/**
	 * Maximum number of suggestions per post.
	 */
	private const MAX_SUGGESTIONS = 10;

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_links_menu' ) );
		add_action( 'wp_ajax_fp_seo_get_link_suggestions', array( $this, 'ajax_get_link_suggestions' ) );
		add_action( 'wp_ajax_fp_seo_analyze_internal_links', array( $this, 'ajax_analyze_internal_links' ) );
		add_action( 'wp_ajax_fp_seo_optimize_internal_links', array( $this, 'ajax_optimize_internal_links' ) );
		// Non registra la metabox separata - il contenuto è integrato in Metabox.php
		// add_action( 'add_meta_boxes', array( $this, 'add_links_metabox' ) );
		// DISABLED: output_link_analysis causes issues in frontend - only register in admin
		// add_action( 'wp_head', array( $this, 'output_link_analysis' ) );
		if ( is_admin() ) {
			add_action( 'admin_head', array( $this, 'output_link_analysis' ) );
		}
	}

	/**
	 * Add Internal Links menu to admin.
	 */
	public function add_links_menu(): void {
		add_submenu_page(
			'fp-seo-performance',
			__( 'Internal Links', 'fp-seo-performance' ),
			__( 'Internal Links', 'fp-seo-performance' ),
			'manage_options',
			'fp-seo-internal-links',
			array( $this, 'render_links_page' )
		);
	}

	/**
	 * Add internal links metabox to post editor.
	 */
	public function add_links_metabox(): void {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'fp_seo_internal_links',
				__( 'Internal Link Suggestions', 'fp-seo-performance' ),
				array( $this, 'render_links_metabox' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Get link suggestions for a post.
	 *
	 * @param int $post_id Post ID.
	 * @param array<string, mixed> $options Options for suggestions.
	 * @return array<string, mixed>
	 */
	public function get_link_suggestions( int $post_id, array $options = array() ): array {
		$cache_key = 'fp_seo_link_suggestions_' . $post_id . '_' . md5( serialize( $options ) );
		
		return Cache::remember( $cache_key, function() use ( $post_id, $options ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return array();
			}

			$content = $post->post_content;
			$title = $post->post_title;
			$excerpt = $post->post_excerpt;

			// Extract keywords from content
			$keywords = $this->extract_keywords( $content . ' ' . $title . ' ' . $excerpt );
			
			// Get potential target posts
			$target_posts = $this->get_potential_target_posts( $post_id, $keywords, $options );
			
			// Score and rank suggestions
			$suggestions = $this->score_link_suggestions( $post_id, $target_posts, $keywords, $content );
			
			// Limit results
			return array_slice( $suggestions, 0, self::MAX_SUGGESTIONS );
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Extract keywords from content.
	 *
	 * @param string $content Content to analyze.
	 * @return array<string, int>
	 */
	private function extract_keywords( string $content ): array {
		// Remove HTML tags and normalize
		$content = wp_strip_all_tags( $content );
		$content = strtolower( $content );
		
		// Remove common stop words
		$stop_words = array(
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
			'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
			'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those',
			'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'
		);
		
		// Split into words and filter
		$words = preg_split( '/\s+/', $content );
		$words = array_filter( $words, function( $word ) use ( $stop_words ) {
			return strlen( $word ) > 2 && ! in_array( $word, $stop_words, true );
		});
		
		// Count word frequency
		$keyword_counts = array_count_values( $words );
		
		// Sort by frequency
		arsort( $keyword_counts );
		
		return array_slice( $keyword_counts, 0, 20, true );
	}

	/**
	 * Get potential target posts for linking.
	 *
	 * @param int $post_id Current post ID.
	 * @param array<string, int> $keywords Keywords to match.
	 * @param array<string, mixed> $options Options.
	 * @return array<int, WP_Post>
	 */
	private function get_potential_target_posts( int $post_id, array $keywords, array $options ): array {
		$args = array(
			'post_type' => $options['post_types'] ?? array( 'post', 'page' ),
			'post_status' => 'publish',
			'post__not_in' => array( $post_id ),
			'posts_per_page' => 50,
			'orderby' => 'relevance',
			'meta_query' => array(
				array(
					'key' => '_fp_seo_focus_keywords',
					'compare' => 'EXISTS'
				)
			)
		);

		// Add keyword search if available
		if ( ! empty( $keywords ) ) {
			$search_terms = array_keys( array_slice( $keywords, 0, 5, true ) );
			$args['s'] = implode( ' ', $search_terms );
		}

		$posts = get_posts( $args );
		
		// If not enough posts found, get more without keyword search
		if ( count( $posts ) < 20 ) {
			unset( $args['s'] );
			$args['posts_per_page'] = 100;
			$additional_posts = get_posts( $args );
			$posts = array_merge( $posts, $additional_posts );
		}

		return $posts;
	}

	/**
	 * Score and rank link suggestions.
	 *
	 * @param int $post_id Current post ID.
	 * @param array<int, WP_Post> $target_posts Target posts.
	 * @param array<string, int> $keywords Keywords.
	 * @param string $content Current content.
	 * @return array<string, mixed>
	 */
	private function score_link_suggestions( int $post_id, array $target_posts, array $keywords, string $content ): array {
		$suggestions = array();

		foreach ( $target_posts as $target_post ) {
			$score = $this->calculate_link_score( $post_id, $target_post, $keywords, $content );
			
			if ( $score > 0.3 ) { // Minimum relevance threshold
				$suggestions[] = array(
					'post_id' => $target_post->ID,
					'title' => $target_post->post_title,
					'url' => get_permalink( $target_post->ID ),
					'excerpt' => get_the_excerpt( $target_post->ID ),
					'score' => $score,
					'keywords' => $this->get_matching_keywords( $target_post, $keywords ),
					'anchor_text' => $this->suggest_anchor_text( $target_post, $keywords ),
					'context' => $this->find_best_context( $content, $target_post, $keywords )
				);
			}
		}

		// Sort by score
		usort( $suggestions, function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		});

		return $suggestions;
	}

	/**
	 * Calculate link relevance score.
	 *
	 * @param int $post_id Current post ID.
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Keywords.
	 * @param string $content Current content.
	 * @return float
	 */
	private function calculate_link_score( int $post_id, $target_post, array $keywords, string $content ): float {
		$score = 0.0;

		// Keyword matching (40% of score)
		$target_keywords = $this->extract_keywords( $target_post->post_content . ' ' . $target_post->post_title );
		$keyword_matches = array_intersect_key( $keywords, $target_keywords );
		$keyword_score = count( $keyword_matches ) / max( count( $keywords ), 1 );
		$score += $keyword_score * 0.4;

		// Content similarity (30% of score)
		$content_similarity = $this->calculate_content_similarity( $content, $target_post->post_content );
		$score += $content_similarity * 0.3;

		// Post authority (20% of score)
		$authority_score = $this->calculate_post_authority( $target_post );
		$score += $authority_score * 0.2;

		// Category relevance (10% of score)
		$category_score = $this->calculate_category_relevance( $post_id, $target_post->ID );
		$score += $category_score * 0.1;

		return min( $score, 1.0 );
	}

	/**
	 * Calculate content similarity between two texts.
	 *
	 * @param string $text1 First text.
	 * @param string $text2 Second text.
	 * @return float
	 */
	private function calculate_content_similarity( string $text1, string $text2 ): float {
		$text1 = wp_strip_all_tags( strtolower( $text1 ) );
		$text2 = wp_strip_all_tags( strtolower( $text2 ) );

		$words1 = array_unique( preg_split( '/\s+/', $text1 ) );
		$words2 = array_unique( preg_split( '/\s+/', $text2 ) );

		$intersection = array_intersect( $words1, $words2 );
		$union = array_unique( array_merge( $words1, $words2 ) );

		return count( $intersection ) / max( count( $union ), 1 );
	}

	/**
	 * Calculate post authority score.
	 *
	 * @param WP_Post $post Post object.
	 * @return float
	 */
	private function calculate_post_authority( $post ): float {
		$score = 0.0;

		// Comment count (normalized)
		$comment_count = get_comments_number( $post->ID );
		$score += min( $comment_count / 10, 1.0 ) * 0.3;

		// View count (if available)
		$view_count = get_post_meta( $post->ID, 'post_views_count', true );
		if ( $view_count ) {
			$score += min( (int) $view_count / 1000, 1.0 ) * 0.4;
		}

		// Social shares (if available)
		$shares = get_post_meta( $post->ID, 'social_shares', true );
		if ( $shares ) {
			$score += min( (int) $shares / 100, 1.0 ) * 0.3;
		}

		return min( $score, 1.0 );
	}

	/**
	 * Calculate category relevance between posts.
	 *
	 * @param int $post1_id First post ID.
	 * @param int $post2_id Second post ID.
	 * @return float
	 */
	private function calculate_category_relevance( int $post1_id, int $post2_id ): float {
		$categories1 = wp_get_post_categories( $post1_id );
		$categories2 = wp_get_post_categories( $post2_id );

		if ( empty( $categories1 ) || empty( $categories2 ) ) {
			return 0.0;
		}

		$intersection = array_intersect( $categories1, $categories2 );
		$union = array_unique( array_merge( $categories1, $categories2 ) );

		return count( $intersection ) / max( count( $union ), 1 );
	}

	/**
	 * Get matching keywords between posts.
	 *
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Source keywords.
	 * @return array<string>
	 */
	private function get_matching_keywords( $target_post, array $keywords ): array {
		$target_keywords = $this->extract_keywords( $target_post->post_content . ' ' . $target_post->post_title );
		$matches = array_intersect_key( $keywords, $target_keywords );
		
		return array_keys( $matches );
	}

	/**
	 * Suggest anchor text for link.
	 *
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Keywords.
	 * @return string
	 */
	private function suggest_anchor_text( $target_post, array $keywords ): string {
		$title = $target_post->post_title;
		
		// Check if any keywords match the title
		$title_words = explode( ' ', strtolower( $title ) );
		foreach ( array_keys( $keywords ) as $keyword ) {
			if ( in_array( $keyword, $title_words, true ) ) {
				return $title; // Use full title if keyword matches
			}
		}

		// Use first few words of title
		$words = explode( ' ', $title );
		return implode( ' ', array_slice( $words, 0, 4 ) );
	}

	/**
	 * Find best context for link placement.
	 *
	 * @param string $content Current content.
	 * @param WP_Post $target_post Target post.
	 * @param array<string, int> $keywords Keywords.
	 * @return string
	 */
	private function find_best_context( string $content, $target_post, array $keywords ): string {
		$sentences = preg_split( '/[.!?]+/', $content );
		$best_sentence = '';
		$best_score = 0;

		foreach ( $sentences as $sentence ) {
			$sentence_keywords = $this->extract_keywords( $sentence );
			$matches = array_intersect_key( $keywords, $sentence_keywords );
			$score = count( $matches );

			if ( $score > $best_score ) {
				$best_score = $score;
				$best_sentence = trim( $sentence );
			}
		}

		return $best_sentence ?: wp_trim_words( $content, 20 );
	}

	/**
	 * Render internal links metabox.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_links_metabox( $post ): void {
		$suggestions = $this->get_link_suggestions( $post->ID );
		$existing_links = $this->get_existing_internal_links( $post->ID );
		
		?>
		<div class="fp-seo-internal-links-metabox">
			<div class="fp-seo-links-stats">
				<div class="fp-seo-link-stat">
					<span class="fp-seo-link-stat-number"><?php echo count( $existing_links ); ?></span>
					<span class="fp-seo-link-stat-label"><?php esc_html_e( 'Existing Links', 'fp-seo-performance' ); ?></span>
				</div>
				<div class="fp-seo-link-stat">
					<span class="fp-seo-link-stat-number"><?php echo count( $suggestions ); ?></span>
					<span class="fp-seo-link-stat-label"><?php esc_html_e( 'Suggestions', 'fp-seo-performance' ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $suggestions ) ) : ?>
				<div class="fp-seo-link-suggestions">
					<h4><?php esc_html_e( 'Link Suggestions', 'fp-seo-performance' ); ?></h4>
					<div class="fp-seo-suggestions-list">
						<?php foreach ( $suggestions as $suggestion ) : ?>
							<div class="fp-seo-suggestion-item" data-post-id="<?php echo esc_attr( $suggestion['post_id'] ); ?>">
								<div class="fp-seo-suggestion-header">
									<span class="fp-seo-suggestion-title"><?php echo esc_html( $suggestion['title'] ); ?></span>
									<span class="fp-seo-suggestion-score"><?php echo round( $suggestion['score'] * 100 ); ?>%</span>
								</div>
								<div class="fp-seo-suggestion-excerpt"><?php echo esc_html( $suggestion['excerpt'] ); ?></div>
								<div class="fp-seo-suggestion-keywords">
									<?php foreach ( $suggestion['keywords'] as $keyword ) : ?>
										<span class="fp-seo-keyword-tag"><?php echo esc_html( $keyword ); ?></span>
									<?php endforeach; ?>
								</div>
								<div class="fp-seo-suggestion-actions">
									<button type="button" class="button button-small fp-seo-insert-link" 
											data-post-id="<?php echo esc_attr( $suggestion['post_id'] ); ?>"
											data-anchor-text="<?php echo esc_attr( $suggestion['anchor_text'] ); ?>"
											data-url="<?php echo esc_attr( $suggestion['url'] ); ?>">
										<?php esc_html_e( 'Insert Link', 'fp-seo-performance' ); ?>
									</button>
									<button type="button" class="button button-small fp-seo-preview-link" 
											data-post-id="<?php echo esc_attr( $suggestion['post_id'] ); ?>">
										<?php esc_html_e( 'Preview', 'fp-seo-performance' ); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else : ?>
				<div class="fp-seo-no-suggestions">
					<p><?php esc_html_e( 'No link suggestions available. Try adding more content or focus keywords.', 'fp-seo-performance' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="fp-seo-links-actions">
				<button type="button" class="button" id="fp-seo-refresh-suggestions">
					<?php esc_html_e( 'Refresh Suggestions', 'fp-seo-performance' ); ?>
				</button>
				<button type="button" class="button" id="fp-seo-analyze-links">
					<?php esc_html_e( 'Analyze Links', 'fp-seo-performance' ); ?>
				</button>
			</div>
		</div>

		<style>
		.fp-seo-internal-links-metabox {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		}
		
		.fp-seo-links-stats {
			display: flex;
			gap: 15px;
			margin-bottom: 15px;
		}
		
		.fp-seo-link-stat {
			text-align: center;
			padding: 10px;
			background: #f8f9fa;
			border-radius: 4px;
			flex: 1;
		}
		
		.fp-seo-link-stat-number {
			display: block;
			font-size: 18px;
			font-weight: 600;
			color: #0073aa;
		}
		
		.fp-seo-link-stat-label {
			display: block;
			font-size: 11px;
			color: #666;
			text-transform: uppercase;
		}
		
		.fp-seo-suggestions-list {
			max-height: 300px;
			overflow-y: auto;
		}
		
		.fp-seo-suggestion-item {
			border: 1px solid #ddd;
			border-radius: 4px;
			padding: 10px;
			margin-bottom: 8px;
			background: #fff;
		}
		
		.fp-seo-suggestion-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 5px;
		}
		
		.fp-seo-suggestion-title {
			font-weight: 600;
			font-size: 12px;
			color: #1d2129;
		}
		
		.fp-seo-suggestion-score {
			background: #0073aa;
			color: #fff;
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			font-weight: 600;
		}
		
		.fp-seo-suggestion-excerpt {
			font-size: 11px;
			color: #666;
			margin-bottom: 8px;
			line-height: 1.4;
		}
		
		.fp-seo-suggestion-keywords {
			margin-bottom: 8px;
		}
		
		.fp-seo-keyword-tag {
			display: inline-block;
			background: #e3f2fd;
			color: #1976d2;
			padding: 2px 6px;
			border-radius: 10px;
			font-size: 10px;
			margin-right: 4px;
			margin-bottom: 2px;
		}
		
		.fp-seo-suggestion-actions {
			display: flex;
			gap: 5px;
		}
		
		.fp-seo-suggestion-actions .button {
			font-size: 11px;
			padding: 4px 8px;
			height: auto;
		}
		
		.fp-seo-no-suggestions {
			text-align: center;
			padding: 20px;
			color: #666;
		}
		
		.fp-seo-links-actions {
			margin-top: 15px;
			display: flex;
			gap: 8px;
		}
		
		.fp-seo-links-actions .button {
			flex: 1;
			text-align: center;
		}
		</style>

		<script>
		jQuery(document).ready(function($) {
			// Insert link functionality
			$('.fp-seo-insert-link').on('click', function() {
				var postId = $(this).data('post-id');
				var anchorText = $(this).data('anchor-text');
				var url = $(this).data('url');
				
				// Insert link into editor
				if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
					wp.media.editor.insert('[[' + anchorText + ']](' + url + ')');
				} else {
					// Fallback for classic editor
					var link = '<a href="' + url + '">' + anchorText + '</a>';
					// This would need to be implemented based on your editor setup
					alert('Link: ' + link);
				}
			});

			// Preview link functionality
			$('.fp-seo-preview-link').on('click', function() {
				var postId = $(this).data('post-id');
				window.open('<?php echo admin_url( 'post.php?post=' ); ?>' + postId + '&action=edit', '_blank');
			});

			// Refresh suggestions
			$('#fp-seo-refresh-suggestions').on('click', function() {
				location.reload();
			});

			// Analyze links
			$('#fp-seo-analyze-links').on('click', function() {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'fp_seo_analyze_internal_links',
						post_id: <?php echo get_the_ID(); ?>,
						nonce: '<?php echo wp_create_nonce( 'fp_seo_links_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							alert('Link analysis completed: ' + response.data.message);
						} else {
							alert('Error: ' + response.data);
						}
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Get existing internal links in post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function get_existing_internal_links( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$content = $post->post_content;
		$links = array();

		// Find all internal links
		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$url = $match[1];
			$text = $match[2];

			// Check if it's an internal link
			if ( strpos( $url, home_url() ) === 0 || strpos( $url, '/' ) === 0 ) {
				$links[] = array(
					'url' => $url,
					'text' => $text,
					'post_id' => url_to_postid( $url )
				);
			}
		}

		return $links;
	}

	/**
	 * Render Internal Links management page.
	 */
	public function render_links_page(): void {
		$site_analysis = $this->get_site_link_analysis();
		
		?>
		<div class="wrap fp-seo-links-wrap">
			<h1><?php esc_html_e( 'Internal Link Analysis', 'fp-seo-performance' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Analizza e ottimizza i link interni per migliorare SEO e navigazione del sito', 'fp-seo-performance' ); ?></p>

			<!-- Banner introduttivo -->
			<div class="fp-seo-intro-banner">
				<div class="fp-seo-intro-icon">🔗</div>
				<div class="fp-seo-intro-content">
					<h2><?php esc_html_e( 'Perché i Link Interni sono Importanti?', 'fp-seo-performance' ); ?></h2>
					<p><?php esc_html_e( 'I link interni sono fondamentali per SEO e user experience. Un buon sistema di linking interno:', 'fp-seo-performance' ); ?></p>
					<ul class="fp-seo-intro-list">
						<li><strong>Migliora il ranking:</strong> Distribuisce autorità SEO (PageRank) tra le pagine</li>
						<li><strong>Aiuta Google:</strong> Il crawler scopre e indicizza nuovi contenuti più velocemente</li>
						<li><strong>Aumenta il tempo sul sito:</strong> Gli utenti navigano più pagine (-40% bounce rate)</li>
						<li><strong>Crea topic clusters:</strong> Collega contenuti correlati per autorità tematica</li>
					</ul>
					<p class="fp-seo-tip">💡 <strong>Best Practice:</strong> 3-5 link interni per post, anchor text descrittivi, link a pillar content</p>
				</div>
			</div>
			
			<div class="fp-seo-links-dashboard">
				<div class="fp-seo-links-stats-grid">
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">🔗</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Total Internal Links', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero totale di link interni nel sito. Più link = migliore distribuzione PageRank. Target: almeno 3-5 link per post.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo esc_html( $site_analysis['total_links'] ); ?></span>
							<p class="fp-seo-stat-desc"><?php esc_html_e( 'Link nel sito', 'fp-seo-performance' ); ?></p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">⚠️</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Orphaned Posts', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Post senza link interni in entrata = difficili da trovare per Google e utenti. Questi post rischiano di non rankare. Obiettivo: 0 post orfani!', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number <?php echo $site_analysis['orphaned_posts'] > 0 ? 'stat-warn' : 'stat-good'; ?>">
								<?php echo esc_html( $site_analysis['orphaned_posts'] ); ?>
							</span>
							<p class="fp-seo-stat-desc">
								<?php echo $site_analysis['orphaned_posts'] > 0 ? '🔴 ' . esc_html__( 'Da correggere!', 'fp-seo-performance' ) : '✅ ' . esc_html__( 'Perfetto', 'fp-seo-performance' ); ?>
							</p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">📊</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Link Density', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Percentuale di contenuto che è composta da link. Ottimale: 1-3%. Troppo bassa = poche opportunità; troppo alta = spam.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo esc_html( round( $site_analysis['link_density'], 1 ) ); ?>%</span>
							<p class="fp-seo-stat-desc">
								<?php 
								$density = $site_analysis['link_density'];
								if ($density >= 1 && $density <= 3) {
									echo '✅ ' . esc_html__( 'Ottimale', 'fp-seo-performance' );
								} elseif ($density < 1) {
									echo '⚠️ ' . esc_html__( 'Bassa', 'fp-seo-performance' );
								} else {
									echo '⚠️ ' . esc_html__( 'Alta', 'fp-seo-performance' );
								}
								?>
							</p>
						</div>
					</div>
					<div class="fp-seo-stat-card">
						<div class="fp-seo-stat-icon">📈</div>
						<div class="fp-seo-stat-content">
							<h3>
								<?php esc_html_e( 'Avg Links per Post', 'fp-seo-performance' ); ?>
								<span class="fp-seo-tooltip-trigger" title="<?php esc_attr_e( 'Numero medio di link interni per post. Ottimale: 3-5 link. Meno di 2 = insufficiente; più di 8 = eccessivo.', 'fp-seo-performance' ); ?>">ℹ️</span>
							</h3>
							<span class="fp-seo-stat-number"><?php echo esc_html( round( $site_analysis['avg_links_per_post'], 1 ) ); ?></span>
							<p class="fp-seo-stat-desc">
								<?php 
								$avg = $site_analysis['avg_links_per_post'];
								if ($avg >= 3 && $avg <= 5) {
									echo '✅ ' . esc_html__( 'Ottimale', 'fp-seo-performance' );
								} elseif ($avg < 3) {
									echo '⚠️ ' . esc_html__( 'Troppo pochi', 'fp-seo-performance' );
								} else {
									echo '⚠️ ' . esc_html__( 'Troppi', 'fp-seo-performance' );
								}
								?>
							</p>
						</div>
					</div>
				</div>

				<div class="fp-seo-links-analysis">
					<h2><?php esc_html_e( 'Link Analysis', 'fp-seo-performance' ); ?></h2>
					<div class="fp-seo-analysis-results">
						<?php $this->render_analysis_results( $site_analysis ); ?>
					</div>
				</div>
			</div>
		</div>

		<style>
		/* Common Styles (riuso da altre pagine) */
		.fp-seo-links-wrap {
			max-width: 1400px;
			margin: 0 auto;
		}

		.fp-seo-links-wrap > .description {
			font-size: 16px;
			color: #666;
			margin-bottom: 24px;
		}

		.fp-seo-intro-banner {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px;
			border-radius: 12px;
			margin: 20px 0 30px;
			display: flex;
			gap: 24px;
			box-shadow: 0 8px 16px rgba(102, 126, 234, 0.2);
		}

		.fp-seo-intro-icon {
			font-size: 48px;
			line-height: 1;
		}

		.fp-seo-intro-content h2 {
			color: white;
			margin: 0 0 16px;
			font-size: 24px;
		}

		.fp-seo-intro-content p {
			margin: 0 0 16px;
			font-size: 15px;
			opacity: 0.95;
		}

		.fp-seo-intro-list {
			margin: 0;
			padding-left: 0;
			list-style: none;
		}

		.fp-seo-intro-list li {
			padding: 6px 0;
			font-size: 14px;
			opacity: 0.9;
		}

		.fp-seo-tip {
			margin: 16px 0 0;
			padding: 12px 16px;
			background: rgba(255, 255, 255, 0.15);
			border-radius: 6px;
			font-size: 14px;
		}

		.fp-seo-links-dashboard {
			margin-top: 20px;
		}
		
		.fp-seo-links-stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
			gap: 20px;
			margin-bottom: 32px;
		}
		
		.fp-seo-stat-card {
			background: white;
			padding: 24px;
			border-radius: 12px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
			border: 2px solid #e5e7eb;
			transition: all 0.3s ease;
			text-align: center;
		}

		.fp-seo-stat-card:hover {
			transform: translateY(-4px);
			box-shadow: 0 8px 12px rgba(0,0,0,0.1);
		}

		.fp-seo-stat-icon {
			font-size: 32px;
			margin-bottom: 12px;
		}

		.fp-seo-stat-content {}
		
		.fp-seo-stat-card h3 {
			margin: 0 0 12px;
			font-size: 14px;
			color: #6b7280;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.fp-seo-stat-number {
			display: block;
			font-size: 42px;
			font-weight: 700;
			color: #2563eb;
			line-height: 1;
			margin-bottom: 8px;
		}

		.fp-seo-stat-number.stat-warn {
			color: #f59e0b;
		}

		.fp-seo-stat-number.stat-good {
			color: #059669;
		}

		.fp-seo-stat-desc {
			margin: 0;
			font-size: 13px;
			color: #6b7280;
		}

		.fp-seo-tooltip-trigger {
			display: inline-block;
			margin-left: 4px;
			cursor: help;
			opacity: 0.7;
			font-size: 12px;
			transition: opacity 0.2s;
		}

		.fp-seo-tooltip-trigger:hover {
			opacity: 1;
		}
		
		.fp-seo-links-analysis {
			background: #fff;
			border: 2px solid #e5e7eb;
			border-radius: 12px;
			padding: 24px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.05);
		}

		.fp-seo-links-analysis h2 {
			margin: 0 0 20px;
			font-size: 20px;
			color: #1f2937;
		}
		</style>
		<?php
	}

	/**
	 * Get site-wide link analysis.
	 *
	 * @return array<string, mixed>
	 */
	private function get_site_link_analysis(): array {
		$cache_key = 'fp_seo_site_link_analysis';
		
		return Cache::remember( $cache_key, function() {
			global $wpdb;
			
			// Get all published posts
		$posts = get_posts( array(
			'post_type' => array( 'post', 'page' ),
			'post_status' => 'publish',
			'posts_per_page' => 1000, // Limit to prevent memory issues on large sites
			'fields' => 'ids'
		) );
			
			$total_links = 0;
			$posts_with_links = 0;
			$orphaned_posts = 0;
			
			foreach ( $posts as $post_id ) {
				$links = $this->get_existing_internal_links( $post_id );
				$link_count = count( $links );
				
				$total_links += $link_count;
				if ( $link_count > 0 ) {
					$posts_with_links++;
				}
				
				// Check if post is orphaned (no incoming links)
				if ( $this->is_post_orphaned( $post_id ) ) {
					$orphaned_posts++;
				}
			}
			
			$total_posts = count( $posts );
			$link_density = $total_posts > 0 ? ( $posts_with_links / $total_posts ) * 100 : 0;
			$avg_links_per_post = $total_posts > 0 ? $total_links / $total_posts : 0;
			
			return array(
				'total_links' => $total_links,
				'orphaned_posts' => $orphaned_posts,
				'link_density' => $link_density,
				'avg_links_per_post' => $avg_links_per_post,
				'total_posts' => $total_posts,
				'posts_with_links' => $posts_with_links
			);
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Check if post is orphaned (no incoming links).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_post_orphaned( int $post_id ): bool {
		global $wpdb;
		
		$post_url = get_permalink( $post_id );
		$post_path = str_replace( home_url(), '', $post_url );
		
		// Search for links to this post
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_content LIKE %s 
			AND post_status = 'publish' 
			AND ID != %d",
			'%' . $wpdb->esc_like( $post_url ) . '%',
			$post_id
		) );
		
		return $count === 0;
	}

	/**
	 * Render analysis results.
	 *
	 * @param array<string, mixed> $analysis Analysis data.
	 */
	private function render_analysis_results( array $analysis ): void {
		?>
		<div class="fp-seo-analysis-summary">
			<div class="fp-seo-analysis-item">
				<strong><?php esc_html_e( 'Link Health Score:', 'fp-seo-performance' ); ?></strong>
				<span class="fp-seo-score <?php echo $analysis['link_density'] > 50 ? 'good' : ( $analysis['link_density'] > 25 ? 'warning' : 'poor' ); ?>">
					<?php echo $this->calculate_link_health_score( $analysis ); ?>%
				</span>
			</div>
			
			<div class="fp-seo-analysis-item">
				<strong><?php esc_html_e( 'Recommendations:', 'fp-seo-performance' ); ?></strong>
				<ul>
					<?php foreach ( $this->get_link_recommendations( $analysis ) as $recommendation ) : ?>
						<li><?php echo esc_html( $recommendation ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Calculate link health score.
	 *
	 * @param array<string, mixed> $analysis Analysis data.
	 * @return int
	 */
	private function calculate_link_health_score( array $analysis ): int {
		$score = 0;
		
		// Link density score (40%)
		if ( $analysis['link_density'] >= 70 ) {
			$score += 40;
		} elseif ( $analysis['link_density'] >= 50 ) {
			$score += 30;
		} elseif ( $analysis['link_density'] >= 30 ) {
			$score += 20;
		} else {
			$score += 10;
		}
		
		// Average links per post score (30%)
		if ( $analysis['avg_links_per_post'] >= 5 ) {
			$score += 30;
		} elseif ( $analysis['avg_links_per_post'] >= 3 ) {
			$score += 20;
		} elseif ( $analysis['avg_links_per_post'] >= 1 ) {
			$score += 10;
		}
		
		// Orphaned posts score (30%)
		$orphan_percentage = ( $analysis['orphaned_posts'] / max( $analysis['total_posts'], 1 ) ) * 100;
		if ( $orphan_percentage <= 10 ) {
			$score += 30;
		} elseif ( $orphan_percentage <= 25 ) {
			$score += 20;
		} elseif ( $orphan_percentage <= 50 ) {
			$score += 10;
		}
		
		return min( $score, 100 );
	}

	/**
	 * Get link recommendations based on analysis.
	 *
	 * @param array<string, mixed> $analysis Analysis data.
	 * @return array<string>
	 */
	private function get_link_recommendations( array $analysis ): array {
		$recommendations = array();
		
		if ( $analysis['link_density'] < 50 ) {
			$recommendations[] = __( 'Add more internal links to improve link density.', 'fp-seo-performance' );
		}
		
		if ( $analysis['avg_links_per_post'] < 3 ) {
			$recommendations[] = __( 'Increase average links per post to at least 3.', 'fp-seo-performance' );
		}
		
		if ( $analysis['orphaned_posts'] > 0 ) {
			$recommendations[] = sprintf( 
				__( 'Link to %d orphaned posts to improve site structure.', 'fp-seo-performance' ), 
				$analysis['orphaned_posts'] 
			);
		}
		
		if ( empty( $recommendations ) ) {
			$recommendations[] = __( 'Your internal linking structure looks good!', 'fp-seo-performance' );
		}
		
		return $recommendations;
	}

	/**
	 * AJAX handler for getting link suggestions.
	 */
	public function ajax_get_link_suggestions(): void {
		check_ajax_referer( 'fp_seo_links_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$options = $_POST['options'] ?? array();

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$suggestions = $this->get_link_suggestions( $post_id, $options );
		wp_send_json_success( $suggestions );
	}

	/**
	 * AJAX handler for analyzing internal links.
	 */
	public function ajax_analyze_internal_links(): void {
		check_ajax_referer( 'fp_seo_links_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$analysis = $this->analyze_post_links( $post_id );
		wp_send_json_success( $analysis );
	}

	/**
	 * Analyze internal links for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function analyze_post_links( int $post_id ): array {
		$existing_links = $this->get_existing_internal_links( $post_id );
		$suggestions = $this->get_link_suggestions( $post_id );
		
		return array(
			'existing_links' => count( $existing_links ),
			'suggestions' => count( $suggestions ),
			'link_density' => $this->calculate_post_link_density( $post_id ),
			'recommendations' => $this->get_post_link_recommendations( $post_id, $existing_links, $suggestions )
		);
	}

	/**
	 * Calculate link density for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return float
	 */
	private function calculate_post_link_density( int $post_id ): float {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0.0;
		}

		$content = wp_strip_all_tags( $post->post_content );
		$word_count = str_word_count( $content );
		$existing_links = $this->get_existing_internal_links( $post_id );
		$link_count = count( $existing_links );

		return $word_count > 0 ? ( $link_count / $word_count ) * 100 : 0.0;
	}

	/**
	 * Get recommendations for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @param array<string, mixed> $existing_links Existing links.
	 * @param array<string, mixed> $suggestions Link suggestions.
	 * @return array<string>
	 */
	private function get_post_link_recommendations( int $post_id, array $existing_links, array $suggestions ): array {
		$recommendations = array();
		$link_count = count( $existing_links );
		
		if ( $link_count < 3 ) {
			$recommendations[] = __( 'Add more internal links (recommended: 3-5 per post).', 'fp-seo-performance' );
		}
		
		if ( ! empty( $suggestions ) ) {
			$top_suggestion = $suggestions[0];
			$recommendations[] = sprintf( 
				__( 'Consider linking to "%s" (relevance: %d%%)', 'fp-seo-performance' ), 
				$top_suggestion['title'], 
				round( $top_suggestion['score'] * 100 )
			);
		}
		
		return $recommendations;
	}

	/**
	 * AJAX handler for optimizing internal links.
	 */
	public function ajax_optimize_internal_links(): void {
		check_ajax_referer( 'fp_seo_links_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );

		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post ID' );
		}

		$optimization = $this->optimize_post_links( $post_id );
		wp_send_json_success( $optimization );
	}

	/**
	 * Optimize internal links for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	private function optimize_post_links( int $post_id ): array {
		// This would implement AI-powered link optimization
		// For now, return basic optimization suggestions
		
		$suggestions = $this->get_link_suggestions( $post_id );
		$optimized_links = array();
		
		foreach ( array_slice( $suggestions, 0, 3 ) as $suggestion ) {
			$optimized_links[] = array(
				'post_id' => $suggestion['post_id'],
				'title' => $suggestion['title'],
				'anchor_text' => $suggestion['anchor_text'],
				'context' => $suggestion['context'],
				'score' => $suggestion['score']
			);
		}
		
		return array(
			'optimized_links' => $optimized_links,
			'message' => sprintf( 
				__( 'Found %d optimized link suggestions for this post.', 'fp-seo-performance' ), 
				count( $optimized_links ) 
			)
		);
	}

	/**
	 * Output link analysis data in head.
	 */
	public function output_link_analysis(): void {
		// DISABLED in frontend: Can interfere with page rendering
		// This analysis is heavy and not necessary for frontend display
		// Completely disabled in frontend to prevent conflicts
		if ( ! is_admin() || is_feed() ) {
			return;
		}

		// Double check we're in admin context
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		// Only output in admin or if explicitly enabled (but still check admin)
		$options = \FP\SEO\Utils\Options::get();
		$enable_link_analysis = $options['advanced']['enable_link_analysis_frontend'] ?? false;
		
		// Never output in frontend, even if enabled
		if ( ! is_admin() || ! $enable_link_analysis ) {
			return;
		}

		$link_analysis = $this->analyze_post_links( $post_id );
		
		echo "\n<!-- FP SEO Performance Internal Link Analysis -->\n";
		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( array(
			'@context' => 'https://schema.org',
			'@type' => 'WebPage',
			'identifier' => get_permalink( $post_id ),
			'linkAnalysis' => $link_analysis
		), JSON_PRETTY_PRINT );
		echo "\n</script>\n";
		echo "<!-- End FP SEO Performance Internal Link Analysis -->\n";
	}
}
