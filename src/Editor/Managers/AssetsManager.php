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
use FP\SEO\Utils\OptionsHelper;
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
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( $debug ) {
			Logger::debug( 'FP SEO: AssetsManager::register() called', array(
				'has_metabox'    => $this->metabox !== null,
				'current_filter' => current_filter(),
			) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 999, 0 );

		// Backup: register on init to ensure hook is registered early
		add_action( 'init', function() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 999, 0 );
		}, 999 );

		// If admin_enqueue_scripts already fired, call directly
		if ( did_action( 'admin_enqueue_scripts' ) ) {
			$this->enqueue_assets();
		}

		// Also hook current_screen for late registration
		if ( is_admin() ) {
			add_action( 'current_screen', array( $this, 'enqueue_assets' ), 999 );
		}
	}

	/**
	 * Enqueue scripts and styles when editing supported post types.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		
		// CRITICAL: Never run on media library or upload pages to avoid interference
		$is_media_page = $screen && (
			in_array( $screen->base, array( 'upload', 'media' ), true ) ||
			$screen->id === 'upload' ||
			$screen->id === 'attachment'
		);
		
		if ( $is_media_page ) {
			return;
		}
		
		// FORCE: Always enqueue on post editor pages
		if ( $screen && 'post' !== $screen->base ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_enqueue_script( 'fp-seo-performance-editor' );
		wp_enqueue_script( 'fp-seo-performance-serp-preview' );
		wp_enqueue_script( 'fp-seo-performance-ai-generator' );
		wp_enqueue_script( 'fp-seo-performance-metabox-ai-fields' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'FP SEO: AssetsManager scripts enqueued', array(
				'post_id'   => $post->ID ?? 0,
				'post_type' => $post->post_type ?? 'unknown',
			) );
		}

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
		$options  = OptionsHelper::get();
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
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					Logger::error( 'FP SEO: Error running analysis in AssetsManager', array(
						'post_id' => $post->ID ?? 0,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					) );
				}
				$analysis = array();
			}
		}

		// Get AI configuration
		$ai_enabled = $options['ai']['enable_auto_generation'] ?? true;
		$api_key    = $options['ai']['openai_api_key'] ?? '';

		// Debug: Verify API key retrieval
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'FP SEO: AI configuration check in AssetsManager', array(
				'ai_enabled'               => $ai_enabled,
				'api_key_length'           => strlen( $api_key ),
				'api_key_empty'            => empty( $api_key ),
				'ai_section_exists'        => isset( $options['ai'] ),
				'ai_openai_api_key_exists' => isset( $options['ai']['openai_api_key'] ),
				'ai_openai_api_key_length' => isset( $options['ai']['openai_api_key'] ) ? strlen( $options['ai']['openai_api_key'] ) : 0,
			) );
		}

		$api_key_value = $api_key;
		$is_configured = ! empty( $api_key_value );

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

	/**
	 * Print localization data directly (for use when scripts can't be enqueued normally).
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function print_localization_data( WP_Post $post ): void {
		// Build the same data structure as localize_scripts
		$options  = OptionsHelper::get();
		$enabled  = ! empty( $options['general']['enable_analyzer'] );
		$excluded = $this->metabox->is_post_excluded( (int) $post->ID );
		$analysis = array();

		if ( $enabled && ! $excluded ) {
			try {
				$analysis_runner = $this->metabox->get_analysis_runner();
				if ( $analysis_runner ) {
					$analysis = $analysis_runner->run( $post );
				} else {
					$analysis = $this->metabox->run_analysis_for_post( $post );
				}
			} catch ( \Throwable $e ) {
				$analysis = array();
			}
		}

		$ai_enabled      = $options['ai']['enable_auto_generation'] ?? true;
		$api_key         = $options['ai']['openai_api_key'] ?? '';
		$api_key_present = ! empty( $api_key );

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

		// Print inline script with localization data
		echo '<script type="text/javascript">' . "\n";
		$fp_seo_json = wp_json_encode( $localized_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $fp_seo_json ) {
			echo 'var fpSeoPerformanceMetabox = ' . $fp_seo_json . ';' . "\n";
		}
		echo '</script>' . "\n";
	}
}

