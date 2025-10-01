<?php
/**
 * Performance signal provider.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Perf;

use FP\SEO\Utils\Options;
use function add_query_arg;
use function count;
use function defined;
use function esc_html__;
use function get_transient;
use function home_url;
use function is_array;
use function is_wp_error;
use function json_decode;
use function max;
use function md5;
use function min;
use function rawurlencode;
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
		$cache_key = self::TRANSIENT_PREFIX . md5( strtolower( $url ) );

		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				$cached['cached'] = true;
				return $cached;
			}
		}

		$endpoint = add_query_arg(
			array(
				'url'      => rawurlencode( $url ),
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
				'source'        => 'psi',
				'url'           => $url,
				'endpoint'      => $endpoint,
				'error'         => $response->get_error_message(),
				'metrics'       => array(),
				'opportunities' => array(),
			);
		}

		$body    = (string) wp_remote_retrieve_body( $response );
		$payload = json_decode( $body, true );

		if ( ! is_array( $payload ) ) {
			return array(
				'source'        => 'psi',
				'url'           => $url,
				'endpoint'      => $endpoint,
				'error'         => esc_html__( 'Unexpected PageSpeed Insights payload.', 'fp-seo-performance' ),
				'metrics'       => array(),
				'opportunities' => array(),
			);
		}

		$metrics = $this->parse_core_web_vitals( $payload );
		$ops     = $this->parse_opportunities( $payload );

		$result = array(
			'source'        => 'psi',
			'url'           => $url,
			'endpoint'      => $endpoint,
			'metrics'       => $metrics,
			'opportunities' => $ops,
			'cached'        => false,
		);

		$ttl = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
		set_transient( $cache_key, $result, $ttl );

		return $result;
	}

	/**
	 * Builds heuristic results when PSI is unavailable.
	 *
	 * @param array<string, mixed> $context Local context metrics.
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
}
