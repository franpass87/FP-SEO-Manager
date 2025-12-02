<?php
/**
 * Manages script and style enqueueing for the SEO metabox.
 *
 * @package FP\SEO\Editor\Managers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Managers;

use FP\SEO\Analysis\Result;
use FP\SEO\Editor\Metabox;
use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Utils\Logger;
use FP\SEO\Utils\Options;
use WP_Post;
use function admin_url;
use function get_current_screen;
use function in_array;
use function is_admin;
use function wp_create_nonce;
use function wp_enqueue_media;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_localize_script;

/**
 * Manages assets (scripts and styles) for the metabox.
 */
class AssetsManager {
	/**
	 * @var Metabox
	 */
	private $metabox;

	/**
	 * Constructor.
	 *
	 * @param Metabox $metabox Metabox instance.
	 */
	public function __construct( Metabox $metabox ) {
		$this->metabox = $metabox;
	}

	/**
	 * Register enqueue hook.
	 *
	 * @return void
	 */
	public function register(): void {
		// Use priority 5 to ensure wp.media is loaded early, before other plugins
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 5, 0 );
	}

	/**
	 * Enqueue scripts and styles when editing supported post types.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Only enqueue in admin context
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		if ( empty( $screen->post_type ) || ! in_array( $screen->post_type, $this->metabox->get_supported_post_types(), true ) ) {
			return;
		}

		// CRITICAL: Never run on media library or upload pages to avoid interference
		$is_media_page = in_array( $screen->base, array( 'upload', 'media' ), true ) || $screen->id === 'upload';
		if ( $is_media_page ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		// Ensure wp.media is available for image uploads (including featured image)
		// This must be called early to support WordPress core featured image button
		wp_enqueue_media();

		// Also ensure set-post-thumbnail script is loaded (required for featured image button)
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'set-post-thumbnail' );
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_enqueue_script( 'fp-seo-performance-editor' );
		wp_enqueue_script( 'fp-seo-performance-serp-preview' );
		wp_enqueue_script( 'fp-seo-performance-ai-generator' );
		wp_enqueue_script( 'fp-seo-performance-metabox-ai-fields' );

		// Prepare data for JavaScript
		$this->localize_scripts( $post );
	}

	/**
	 * Localize scripts with data.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	private function localize_scripts( WP_Post $post ): void {
		$options  = Options::get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->metabox->is_post_excluded( (int) $post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			try {
				// Use AnalysisRunner if available, otherwise fallback to metabox method
				$analysis_runner = $this->metabox->get_analysis_runner();
				if ( $analysis_runner ) {
					$analysis = $analysis_runner->run( $post );
				} else {
					$analysis = $this->metabox->run_analysis_for_post( $post );
				}
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Error running analysis in AssetsManager', array(
						'post_id' => $post->ID ?? 0,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				$analysis = array();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Fatal error running analysis in AssetsManager', array(
						'post_id' => $post->ID ?? 0,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				$analysis = array();
			}
		}

		// Get AI configuration
		$ai_enabled = Options::get_option( 'ai.enable_auto_generation', true );
		$api_key    = Options::get_option( 'ai.openai_api_key', '' );

		// Debug: Verify API key retrieval
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$all_options = Options::get();
			Logger::debug( 'FP SEO: AI configuration check in AssetsManager', array(
				'ai_enabled' => $ai_enabled,
				'api_key_length' => strlen( $api_key ),
				'api_key_empty' => empty( $api_key ),
				'ai_section_exists' => isset( $all_options['ai'] ),
				'ai_openai_api_key_exists' => isset( $all_options['ai']['openai_api_key'] ),
				'ai_openai_api_key_length' => isset( $all_options['ai']['openai_api_key'] ) ? strlen( $all_options['ai']['openai_api_key'] ) : 0,
				'api_key_via_get_option' => strlen( Options::get_option( 'ai.openai_api_key', '' ) ),
			) );
		}

		// Also check via OpenAiClient to ensure consistency
		$openai_client = new OpenAiClient();
		$is_configured = $openai_client->is_configured();

		// Use the more reliable check from OpenAiClient
		$api_key_present = $is_configured || ! empty( $api_key );

		// Localize main editor script
		$localized_data = array(
			'postId'   => (int) $post->ID,
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'fp_seo_performance_analyze' ),
			'saveNonce' => wp_create_nonce( 'fp_seo_performance_save_fields' ),
			'saveAction' => 'fp_seo_performance_save_fields',
			'enabled'  => $enabled,
			'excluded' => $excluded,
			'aiEnabled' => $ai_enabled,
			'apiKeyPresent' => $api_key_present,
			'initial'  => $analysis,
			'labels'   => array(
				'score'      => __( 'SEO Score', 'fp-seo-performance' ),
				'indicators' => __( 'Analisi SEO', 'fp-seo-performance' ),
				'notes'      => __( 'Raccomandazioni', 'fp-seo-performance' ),
				'none'       => __( 'Tutti gli indicatori sono ottimali.', 'fp-seo-performance' ),
				'disabled'   => __( 'Analizzatore disabilitato nelle impostazioni.', 'fp-seo-performance' ),
				'excluded'   => __( 'This content is excluded from SEO analysis.', 'fp-seo-performance' ),
				'loading'    => __( 'Analyzing content…', 'fp-seo-performance' ),
				'error'      => __( 'Unable to analyze content. Please try again.', 'fp-seo-performance' ),
			),
			'legend'   => array(
				Result::STATUS_PASS => __( 'Ottimo', 'fp-seo-performance' ),
				Result::STATUS_WARN => __( 'Attenzione', 'fp-seo-performance' ),
				Result::STATUS_FAIL => __( 'Critico', 'fp-seo-performance' ),
			),
		);

		wp_localize_script(
			'fp-seo-performance-editor',
			'fpSeoPerformanceMetabox',
			$localized_data
		);

		// Also localize for the AI fields script
		wp_localize_script(
			'fp-seo-performance-metabox-ai-fields',
			'fpSeoAiFields',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fp_seo_ai_generate' ),
				'postId'  => (int) $post->ID,
				'enabled' => $ai_enabled && $api_key_present,
			)
		);

		// Also localize for AI generator script
		wp_localize_script(
			'fp-seo-performance-ai-generator',
			'fpSeoAiGenerator',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fp_seo_ai_generate' ),
				'postId'  => (int) $post->ID,
				'enabled' => $ai_enabled && $api_key_present,
			)
		);
	}
}

