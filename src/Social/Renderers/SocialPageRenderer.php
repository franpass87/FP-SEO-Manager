<?php
/**
 * Renders the Social Media admin page.
 *
 * @package FP\SEO\Social\Renderers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social\Renderers;

use FP\SEO\Social\ImprovedSocialMediaManager;
use function esc_html;
use function esc_html_e;

/**
 * Renders the Social Media admin page.
 */
class SocialPageRenderer {
	/**
	 * @var ImprovedSocialMediaManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param ImprovedSocialMediaManager $manager Social media manager instance.
	 */
	public function __construct( ImprovedSocialMediaManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Render the social media admin page.
	 *
	 * @return void
	 */
	public function render(): void {
		?>
		<div class="wrap fp-seo-ui">
			<div class="fp-seo-container">
				<h1 class="fp-seo-heading-1">
					<span class="fp-seo-social-icon">📱</span>
					<?php esc_html_e( 'Social Media Optimization', 'fp-seo-performance' ); ?>
				</h1>
				
				<div class="fp-seo-grid fp-seo-grid-3">
					<div class="fp-seo-card">
						<div class="fp-seo-card-body">
							<h3 class="fp-seo-heading-3"><?php esc_html_e( 'Posts with Social Meta', 'fp-seo-performance' ); ?></h3>
							<div class="fp-seo-stat-number"><?php echo esc_html( (string) $this->get_posts_with_social_meta_count() ); ?></div>
						</div>
					</div>
					
					<div class="fp-seo-card">
						<div class="fp-seo-card-body">
							<h3 class="fp-seo-heading-3"><?php esc_html_e( 'Platforms Supported', 'fp-seo-performance' ); ?></h3>
							<div class="fp-seo-stat-number"><?php echo esc_html( (string) count( ImprovedSocialMediaManager::PLATFORMS ) ); ?></div>
						</div>
					</div>
					
					<div class="fp-seo-card">
						<div class="fp-seo-card-body">
							<h3 class="fp-seo-heading-3"><?php esc_html_e( 'Optimization Score', 'fp-seo-performance' ); ?></h3>
							<div class="fp-seo-stat-number"><?php echo esc_html( (string) $this->get_optimization_score() ); ?>%</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get count of posts with social meta.
	 *
	 * @return int
	 */
	private function get_posts_with_social_meta_count(): int {
		global $wpdb;
		
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_fp_seo_social_meta' AND meta_value != ''"
		);
		
		return (int) $count;
	}

	/**
	 * Get optimization score.
	 *
	 * @return int
	 */
	private function get_optimization_score(): int {
		// Simple calculation - in real implementation, this would be more sophisticated
		$count_posts = wp_count_posts( 'post' );
		$total_posts = isset( $count_posts->publish ) ? (int) $count_posts->publish : 0;
		$optimized_posts = $this->get_posts_with_social_meta_count();

		if ( $total_posts <= 0 ) {
			return 0;
		}

		$score = ( $optimized_posts / $total_posts ) * 100;

		return (int) max( 0, min( 100, round( $score ) ) );
	}
}


