<?php
/**
 * Site Health integration.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\SiteHealth;

use FP\SEO\Perf\Signals;
use FP\SEO\Utils\Options;
use FP\SEO\Utils\UrlNormalizer;
use function esc_html;
use function wp_remote_retrieve_response_code;

/**
 * Registers Site Health checks for the plugin.
 */
class SeoHealth {
		/**
		 * Signals provider for PSI data.
		 *
		 * @var Signals
		 */
	private Signals $signals;

		/**
		 * Constructor.
		 *
		 * @param Signals|null $signals Optional signals provider.
		 */
	public function __construct( ?Signals $signals = null ) {
			$this->signals = $signals ?? new Signals();
	}

		/**
		 * Hooks Site Health test registration.
		 */
	public function register(): void {
			add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * Adds Site Health test entries for SEO and performance checks.
	 *
	 * @param array<string, mixed> $tests Registered test definitions.
	 *
	 * @return array<string, mixed> Modified test array.
	 */
	public function add_tests( array $tests ): array {
		$tests['direct']['fp_seo_performance_seo']  = array(
			'label' => __( 'Homepage SEO metadata', 'fp-seo-performance' ),
			'test'  => array( $this, 'run_seo_test' ),
		);
		$tests['direct']['fp_seo_performance_perf'] = array(
			'label' => __( 'Homepage performance insights', 'fp-seo-performance' ),
			'test'  => array( $this, 'run_performance_test' ),
		);

		return $tests;
	}

	/**
	 * Runs homepage SEO checks ensuring basic metadata is exposed.
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function run_seo_test(): array {
		$badge            = $this->seo_badge();
		$home_url         = home_url( '/' );
				$response = wp_remote_get(
					$home_url,
					array(
						'timeout'     => 10,
						'headers'     => array(),
						'redirection' => 3,
					)
				);

		if ( is_wp_error( $response ) ) {
			return array(
				'label'       => __( 'Unable to verify homepage SEO metadata', 'fp-seo-performance' ),
				'status'      => 'critical',
				'badge'       => $badge,
				'description' => __( 'We could not load the homepage to review its SEO metadata. Check your site is reachable and try again.', 'fp-seo-performance' ),
				'actions'     => array(
					sprintf(
						'<a href="%s" target="_blank" rel="noopener">%s</a>',
						esc_url( $home_url ),
						esc_html__( 'Open homepage', 'fp-seo-performance' )
					),
				),
			);
		}

				$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
				return array(
					'label'       => __( 'Homepage returned an unexpected HTTP status', 'fp-seo-performance' ),
					'status'      => 'critical',
					'badge'       => $badge,
					'description' => sprintf(
							/* translators: %d: HTTP status code. */
						__( 'The homepage responded with HTTP %d so SEO metadata could not be verified. Resolve the issue and try again.', 'fp-seo-performance' ),
						$status_code
					),
					'actions'     => array(
						sprintf(
							'<a href="%s" target="_blank" rel="noopener">%s</a>',
							esc_url( $home_url ),
							esc_html__( 'Open homepage', 'fp-seo-performance' )
						),
					),
				);
		}

				$body = (string) wp_remote_retrieve_body( $response );

		if ( '' === trim( $body ) ) {
			return array(
				'label'       => __( 'Homepage returned an empty response', 'fp-seo-performance' ),
				'status'      => 'critical',
				'badge'       => $badge,
				'description' => __( 'The homepage response was empty so SEO metadata could not be verified.', 'fp-seo-performance' ),
			);
		}

		$issues  = array();
		$actions = array();

		$title_value = '';
		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $body, $title_match ) ) {
			$title_value = trim( wp_strip_all_tags( $title_match[1] ) );
		}

		if ( '' === $title_value ) {
			$issues[]  = __( 'Homepage is missing a <title> element.', 'fp-seo-performance' );
			$actions[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ),
				esc_html__( 'Update site title & tagline', 'fp-seo-performance' )
			);
		}

		if ( ! preg_match( "#<meta\s+name=[\"']description[\"'][^>]*content=[\"']([^\"']+)[\"']#i", $body ) ) {
			$issues[]  = __( 'Homepage is missing a meta description.', 'fp-seo-performance' );
			$actions[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ),
				esc_html__( 'Add a site meta description', 'fp-seo-performance' )
			);
		}

		if ( ! preg_match( "#<link\s+rel=[\"']canonical[\"'][^>]*href=[\"']([^\"']+)[\"']#i", $body ) ) {
			$issues[]  = __( 'Homepage is missing a canonical URL.', 'fp-seo-performance' );
			$actions[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=fp-seo-performance-settings&tab=analysis' ) ),
				esc_html__( 'Review canonical settings', 'fp-seo-performance' )
			);
		}

		$should_check_robots = '1' === (string) get_option( 'blog_public', '1' );

		if ( $should_check_robots ) {
			$robots_allows_indexing = true;

			$robots_content = '';

			if ( preg_match( "#<meta\s+name=[\"']robots[\"'][^>]*content=[\"']([^\"']+)[\"']#i", $body, $robots_match ) ) {
				$robots_content = strtolower( $robots_match[1] );
				if ( str_contains( $robots_content, 'noindex' ) || str_contains( $robots_content, 'nofollow' ) ) {
					$robots_allows_indexing = false;
				}
			}

			if ( ! $robots_allows_indexing ) {
				$issues[]  = __( 'Homepage robots directives block indexing.', 'fp-seo-performance' );
				$actions[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'options-reading.php' ) ),
					esc_html__( 'Review search engine visibility', 'fp-seo-performance' )
				);
			}
		}

		$actions = array_values( array_unique( $actions ) );

		if ( empty( $issues ) ) {
			return array(
				'label'       => __( 'Homepage exposes SEO metadata', 'fp-seo-performance' ),
				'status'      => 'good',
				'badge'       => $badge,
				'description' => __( 'The homepage provides core SEO tags including title, description, canonical URL, and indexing directives.', 'fp-seo-performance' ),
			);
		}

		return array(
			'label'       => __( 'Homepage SEO metadata needs attention', 'fp-seo-performance' ),
			'status'      => 'recommended',
			'badge'       => $badge,
			'description' => sprintf(
				'%s<ul><li>%s</li></ul>',
				esc_html__( 'Review the homepage to resolve the following:', 'fp-seo-performance' ),
				implode( '</li><li>', array_map( 'esc_html', $issues ) )
			),
			'actions'     => $actions,
		);
	}

	/**
	 * Runs homepage performance checks leveraging PSI when configured.
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function run_performance_test(): array {
		$badge    = $this->performance_badge();
		$options  = Options::get();
		$enable   = (bool) ( $options['performance']['enable_psi'] ?? false );
		$api_key  = trim( (string) ( $options['performance']['psi_api_key'] ?? '' ) );
		$home_url = home_url( '/' );

		if ( ! $enable || '' === $api_key ) {
			return array(
				'label'       => __( 'PageSpeed Insights API key not configured', 'fp-seo-performance' ),
				'status'      => 'recommended',
				'badge'       => $badge,
				'description' => __( 'Add a Google PageSpeed Insights API key to fetch Core Web Vitals directly in Site Health.', 'fp-seo-performance' ),
				'actions'     => array(
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=fp-seo-performance-settings&tab=performance' ) ),
						esc_html__( 'Configure PSI settings', 'fp-seo-performance' )
					),
				),
			);
		}

				$request_url = UrlNormalizer::normalize( $home_url );
				$report      = $this->signals->collect( $request_url );

		if ( 'psi' !== (string) ( $report['source'] ?? '' ) ) {
				return array(
					'label'       => __( 'PageSpeed Insights data unavailable', 'fp-seo-performance' ),
					'status'      => 'recommended',
					'badge'       => $badge,
					'description' => __( 'The performance signals service did not return PageSpeed Insights metrics. Try refreshing the cache or verify PSI configuration.', 'fp-seo-performance' ),
					'actions'     => array(
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'admin.php?page=fp-seo-performance-settings&tab=performance' ) ),
							esc_html__( 'Review PSI configuration', 'fp-seo-performance' )
						),
					),
				);
		}

				$error = isset( $report['error'] ) ? trim( (string) $report['error'] ) : '';

		if ( '' !== $error ) {
				return array(
					'label'       => __( 'PageSpeed Insights API returned an error', 'fp-seo-performance' ),
					'status'      => 'recommended',
					'badge'       => $badge,
					'description' => sprintf(
						'%s %s',
						esc_html__( 'The PageSpeed Insights API responded with an error:', 'fp-seo-performance' ),
						esc_html( $error )
					),
					'actions'     => array(
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( admin_url( 'admin.php?page=fp-seo-performance-settings&tab=performance' ) ),
							esc_html__( 'Review PSI configuration', 'fp-seo-performance' )
						),
					),
				);
		}

				$score    = $report['performance_score'] ?? null;
				$endpoint = (string) ( $report['endpoint'] ?? '' );

		if ( null === $score ) {
				return array(
					'label'       => __( 'Unexpected PageSpeed Insights response', 'fp-seo-performance' ),
					'status'      => 'recommended',
					'badge'       => $badge,
					'description' => __( 'The PageSpeed Insights response did not include a performance score. Double-check the queried URL and API quota.', 'fp-seo-performance' ),
					'actions'     => array(
						sprintf(
							'<a href="%s" target="_blank" rel="noopener">%s</a>',
							esc_url( '' !== $endpoint ? $endpoint : 'https://pagespeed.web.dev/' ),
							esc_html__( 'Open PSI report', 'fp-seo-performance' )
						),
					),
				);
		}

				return array(
					'label'       => __( 'PageSpeed Insights score available', 'fp-seo-performance' ),
					'status'      => 'good',
					'badge'       => $badge,
					'description' => sprintf(
							/* translators: %d: PSI performance score. */
						esc_html__( 'Google PageSpeed Insights reports a performance score of %d for the homepage.', 'fp-seo-performance' ),
						(int) $score
					),
					'actions'     => array(
						sprintf(
							'<a href="%s" target="_blank" rel="noopener">%s</a>',
							esc_url( '' !== $endpoint ? $endpoint : 'https://pagespeed.web.dev/' ),
							esc_html__( 'View detailed PSI report', 'fp-seo-performance' )
						),
					),
				);
	}

	/**
	 * Provides the badge used for SEO checks.
	 *
	 * @return array<string, string> Badge metadata.
	 */
	private function seo_badge(): array {
		return array(
			'label' => __( 'SEO', 'fp-seo-performance' ),
			'color' => 'blue',
		);
	}

	/**
	 * Provides the badge used for performance checks.
	 *
	 * @return array<string, string> Badge metadata.
	 */
	private function performance_badge(): array {
			return array(
				'label' => __( 'Performance', 'fp-seo-performance' ),
				'color' => 'orange',
			);
	}
}
