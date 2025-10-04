<?php
/**
 * Performance signal provider.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Perf;

use FP\SEO\Utils\Options;
use FP\SEO\Utils\UrlNormalizer;
use function add_query_arg;
use function count;
use function defined;
use function esc_html__;
use function get_transient;
use function home_url;
use function html_entity_decode;
use function is_array;
use function is_numeric;
use function is_string;
use function is_wp_error;
use function json_decode;
use function max;
use function md5;
use function min;
use function rawurldecode;
use function wp_parse_url;
use function round;
use function set_transient;
use function sprintf;
use function strtolower;
use function strtoupper;
use function trim;
use function wp_remote_get;
use function wp_remote_retrieve_body;

/**
 * Provides Core Web Vitals or heuristic performance signals.
 */
class Signals {
	/**
	 * Transient cache key prefix.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'fp_seo_perf_signals_';

	/**
	 * Inline CSS warning threshold (bytes ~ 45KB).
	 *
	 * @var int
	 */
	private const INLINE_CSS_THRESHOLD = 46080;

	/**
	 * Image count warning threshold.
	 *
	 * @var int
	 */
	private const IMAGE_COUNT_THRESHOLD = 15;

	/**
	 * Collects performance metrics for the analyzer.
	 *
	 * @param string               $url     URL to evaluate. Defaults to homepage when empty.
	 * @param array<string, mixed> $context Optional local heuristics context (image counts, headings, etc.).
	 * @param bool                 $refresh When true, bypasses cached PSI responses.
	 *
	 * @return array<string, mixed> Signal payload.
	 */
	public function collect( string $url = '', array $context = array(), bool $refresh = false ): array {
			$options     = Options::get();
			$performance = $options['performance'] ?? array();
			$enable      = (bool) ( $performance['enable_psi'] ?? false );
			$api_key     = trim( (string) ( $performance['psi_api_key'] ?? '' ) );
			$heuristics  = is_array( $performance['heuristics'] ?? null ) ? $performance['heuristics'] : array();

		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		if ( $enable && '' !== $api_key ) {
			return $this->collect_from_psi( $url, $api_key, $refresh );
		}

			return $this->collect_heuristics( $context, $heuristics );
	}

	/**
	 * Retrieves metrics from PSI with caching.
	 *
	 * @param string $url     Page URL.
	 * @param string $api_key API key.
	 * @param bool   $refresh Force refresh bypassing cache.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_from_psi( string $url, string $api_key, bool $refresh = false ): array {
			$normalized_url = $this->normalize_page_url( $url );
			$cache_key      = $this->build_cache_key( $normalized_url );

		if ( ! $refresh ) {
				$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				if ( ! isset( $cached['performance_score'] ) ) {
						$cached['performance_score'] = null;
				}

								return $cached;
			}
		}

			$endpoint = add_query_arg(
				array(
					'url'      => $normalized_url,
					'key'      => $api_key,
					'strategy' => 'mobile',
				),
				'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
			);

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
				return array(
					'source'            => 'psi',
					'url'               => $normalized_url,
					'endpoint'          => $endpoint,
					'error'             => $response->get_error_message(),
					'metrics'           => array(),
					'opportunities'     => array(),
					'performance_score' => null,
				);
		}

			$body    = (string) wp_remote_retrieve_body( $response );
			$payload = json_decode( $body, true );

		if ( ! is_array( $payload ) ) {
				return array(
					'source'            => 'psi',
					'url'               => $normalized_url,
					'endpoint'          => $endpoint,
					'error'             => esc_html__( 'Unexpected PageSpeed Insights payload.', 'fp-seo-performance' ),
					'metrics'           => array(),
					'opportunities'     => array(),
					'performance_score' => null,
				);
		}

			$api_error = $this->extract_psi_error_message( $payload );

		if ( null !== $api_error ) {
				return array(
					'source'            => 'psi',
					'url'               => $normalized_url,
					'endpoint'          => $endpoint,
					'error'             => $api_error,
					'metrics'           => array(),
					'opportunities'     => array(),
					'performance_score' => null,
				);
		}

			$metrics = $this->parse_core_web_vitals( $payload );
			$ops     = $this->parse_opportunities( $payload );

			$performance_score = $this->extract_performance_score( $payload );

			$result = array(
				'source'            => 'psi',
				'url'               => $normalized_url,
				'endpoint'          => $endpoint,
				'metrics'           => $metrics,
				'opportunities'     => $ops,
				'performance_score' => $performance_score,
				'cached'            => false,
			);

			$ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
			set_transient( $cache_key, $result, $ttl );

			return $result;
	}

		/**
		 * Builds heuristic results when PSI is unavailable.
		 *
		 * @param array<string, mixed> $context Local context metrics.
		 * @param array<string, bool>  $toggles Heuristic toggle overrides keyed by metric identifier.
		 *
		 * @return array<string, mixed>
		 */
	private function collect_heuristics( array $context, array $toggles = array() ): array {
			$images_total       = max( 0, (int) ( $context['images']['total'] ?? 0 ) );
			$images_missing_alt = max( 0, (int) ( $context['images']['missing_alt'] ?? 0 ) );
			$inline_css_bytes   = max( 0, (int) ( $context['inline_css_bytes'] ?? 0 ) );
			$max_heading_depth  = max( 0, (int) ( $context['headings']['depth'] ?? 0 ) );

			$defaults = Options::get_defaults()['performance']['heuristics'];
			$toggles  = array_merge( $defaults, array_map( 'boolval', $toggles ) );

			$metrics = array();
			$opps    = array();

		if ( $toggles['image_alt_coverage'] && $images_total > 0 ) {
				$coverage                      = max( 0, min( 1, 1 - ( $images_missing_alt / $images_total ) ) );
				$metrics['image_alt_coverage'] = array(
					'label' => esc_html__( 'Image alternative text coverage', 'fp-seo-performance' ),
					'value' => round( $coverage * 100, 1 ),
					'unit'  => '%',
				);

				if ( $coverage < 0.8 ) {
					$opps[] = array(
						'id'          => 'image-alt-coverage',
						'label'       => esc_html__( 'Add missing alternative text to images', 'fp-seo-performance' ),
						'description' => esc_html__( 'Improving alternative text helps with Core Web Vitals for LCP images and accessibility.', 'fp-seo-performance' ),
						'priority'    => 'medium',
					);
				}
		}

		if ( $toggles['inline_css'] && $inline_css_bytes > self::INLINE_CSS_THRESHOLD ) {
				$opps[] = array(
					'id'          => 'inline-css',
					'label'       => esc_html__( 'Reduce inline CSS size', 'fp-seo-performance' ),
					'description' => sprintf(
					/* translators: %s: Inline CSS size in kilobytes. */
						esc_html__( 'Inline styles add %s KB to the page. Consider extracting critical CSS and deferring the rest.', 'fp-seo-performance' ),
						round( $inline_css_bytes / 1024 )
					),
					'priority'    => 'medium',
				);
		}

		if ( $toggles['image_count'] && $images_total > self::IMAGE_COUNT_THRESHOLD ) {
					$opps[] = array(
						'id'          => 'image-count',
						'label'       => esc_html__( 'Review number of images on the page', 'fp-seo-performance' ),
						'description' => esc_html__( 'Large numbers of images can slow down rendering. Consider lazy-loading or trimming media.', 'fp-seo-performance' ),
						'priority'    => 'low',
					);
		}

		if ( $toggles['heading_depth'] && $max_heading_depth > 4 ) {
				$opps[] = array(
					'id'          => 'heading-depth',
					'label'       => esc_html__( 'Simplify heading structure', 'fp-seo-performance' ),
					'description' => esc_html__( 'Complex heading hierarchies often indicate heavy DOM depth, which can impact INP.', 'fp-seo-performance' ),
					'priority'    => 'low',
				);
		}

				return array(
					'source'        => 'local',
					'metrics'       => $metrics,
					'opportunities' => $opps,
				);
	}

	/**
	 * Extracts Core Web Vitals metrics from PSI payload.
	 *
	 * @param array<string, mixed> $payload PSI response payload.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function parse_core_web_vitals( array $payload ): array {
		$metrics = array();
		$map     = array(
			'LARGEST_CONTENTFUL_PAINT_MS'            => 'lcp',
			'CUMULATIVE_LAYOUT_SHIFT_SCORE'          => 'cls',
			'INTERACTION_TO_NEXT_PAINT'              => 'inp',
			'EXPERIMENTAL_INTERACTION_TO_NEXT_PAINT' => 'inp',
		);

		$experience = $payload['loadingExperience']['metrics'] ?? array();

		foreach ( $map as $psi_key => $metric_key ) {
			if ( isset( $metrics[ $metric_key ] ) ) {
				continue;
			}

			$metric = $experience[ $psi_key ] ?? null;
			if ( ! is_array( $metric ) ) {
				continue;
			}

			$category   = strtolower( (string) ( $metric['category'] ?? '' ) );
			$percentile = isset( $metric['percentile'] ) ? (int) $metric['percentile'] : null;

			$metrics[ $metric_key ] = array(
				'label'      => $this->metric_label( $metric_key ),
				'category'   => $category,
				'percentile' => $percentile,
			);
		}

		return $metrics;
	}

	/**
	 * Parses opportunity audits from PSI payload.
	 *
	 * @param array<string, mixed> $payload PSI response payload.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_opportunities( array $payload ): array {
			$audits = $payload['lighthouseResult']['audits'] ?? array();
		if ( ! is_array( $audits ) ) {
				return array();
		}

			$opps = array();

		foreach ( $audits as $audit_id => $audit ) {
			if ( ! is_array( $audit ) ) {
				continue;
			}

			$details = $audit['details'] ?? array();
			if ( ! is_array( $details ) || ( $details['type'] ?? '' ) !== 'opportunity' ) {
				continue;
			}

			$opps[] = array(
				'id'          => (string) $audit_id,
				'label'       => (string) ( $audit['title'] ?? $audit_id ),
				'description' => (string) ( $audit['description'] ?? '' ),
				'score'       => isset( $audit['score'] ) ? (float) $audit['score'] : null,
			);

			if ( count( $opps ) >= 5 ) {
				break;
			}
		}

			return $opps;
	}

		/**
		 * Extracts the Lighthouse performance score from the PSI payload.
		 *
		 * @param array<string, mixed> $payload PSI response payload.
		 */
	private function extract_performance_score( array $payload ): ?int {
			$category = $payload['lighthouseResult']['categories']['performance']['score'] ?? null;

		if ( is_numeric( $category ) ) {
				return (int) round( (float) $category * 100 );
		}

			return null;
	}

		/**
		 * Provides localized metric labels.
		 *
		 * @param string $metric Metric key.
		 *
		 * @return string
		 */
	private function metric_label( string $metric ): string {
		switch ( $metric ) {
			case 'lcp':
				return esc_html__( 'Largest Contentful Paint', 'fp-seo-performance' );
			case 'cls':
				return esc_html__( 'Cumulative Layout Shift', 'fp-seo-performance' );
			case 'inp':
				return esc_html__( 'Interaction to Next Paint', 'fp-seo-performance' );
			default:
				return strtoupper( $metric );
		}
	}

		/**
		 * Normalizes the requested page URL for PSI requests.
		 *
		 * @param string $url Page URL, potentially percent-encoded already.
		 */
	private function normalize_page_url( string $url ): string {
			return UrlNormalizer::normalize( $url );
	}

		/**
		 * Generates a cache key for a normalized PSI URL while respecting path casing.
		 *
		 * @param string $normalized_url Normalized page URL.
		 */
	private function build_cache_key( string $normalized_url ): string {
			$source = $normalized_url;
			$parts  = wp_parse_url( $normalized_url );

		if ( is_array( $parts ) ) {
				$scheme   = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) . '://' : '';
				$host     = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
				$port     = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
				$path     = $parts['path'] ?? '';
				$query    = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
				$fragment = isset( $parts['fragment'] ) && '' !== $parts['fragment'] ? '#' . $parts['fragment'] : '';

			if ( '' !== $scheme || '' !== $host ) {
				$source = $scheme . $host . $port . $path . $query . $fragment;
			}
		}

			return self::TRANSIENT_PREFIX . md5( $source );
	}

		/**
		 * Extracts an error message from a PSI response payload, if present.
		 *
		 * @param array<string, mixed> $payload PSI response payload.
		 */
	private function extract_psi_error_message( array $payload ): ?string {
			$error = $payload['error'] ?? null;

		if ( ! is_array( $error ) ) {
				return null;
		}

			$message = $error['message'] ?? null;

		if ( is_string( $message ) && '' !== trim( $message ) ) {
				return trim( $message );
		}

			$details = $error['errors'] ?? null;

		if ( ! is_array( $details ) ) {
				return null;
		}

		foreach ( $details as $detail ) {
			if ( ! is_array( $detail ) ) {
					continue;
			}

				$detail_message = $detail['message'] ?? null;

			if ( is_string( $detail_message ) && '' !== trim( $detail_message ) ) {
					return trim( $detail_message );
			}
		}

			return null;
	}
}
