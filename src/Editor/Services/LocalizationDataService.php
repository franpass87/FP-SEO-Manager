<?php
/**
 * Service for preparing localization data for JavaScript.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Analysis\Result;
use FP\SEO\Editor\Metabox;
use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\Utils\Options;
use WP_Post;
use function admin_url;
use function wp_create_nonce;

/**
 * Service for preparing localization data for JavaScript.
 */
class LocalizationDataService {

	/**
	 * Prepare localization data for JavaScript.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $analysis Analysis result.
	 * @param bool    $enabled Whether analyzer is enabled.
	 * @param bool    $excluded Whether post is excluded.
	 * @return array Localization data.
	 */
	public function prepare_data( WP_Post $post, array $analysis, bool $enabled, bool $excluded ): array {
		// Get AI configuration
		$ai_enabled = Options::get_option( 'ai.enable_auto_generation', true );
		$api_key    = Options::get_option( 'ai.openai_api_key', '' );
		
		// Also check via OpenAiClient to ensure consistency
		$is_configured = false;
		try {
			$openai_client = new OpenAiClient();
			$is_configured = $openai_client->is_configured();
		} catch ( \Throwable $e ) {
			// OpenAiClient unavailable — fall back to api_key check only
		}
		
		// Use the more reliable check from OpenAiClient
		$api_key_present = $is_configured || ! empty( $api_key );

		return array(
			'postId'   => (int) $post->ID,
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( Metabox::AJAX_ACTION ),
			'saveNonce' => wp_create_nonce( Metabox::AJAX_SAVE_FIELDS ),
			'saveAction' => Metabox::AJAX_SAVE_FIELDS,
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
	}
}








