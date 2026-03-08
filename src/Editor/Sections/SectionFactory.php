<?php
/**
 * Factory for creating and registering metabox sections.
 *
 * @package FP\SEO\Editor\Sections
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Sections;

use FP\SEO\Editor\CheckHelpText;
use FP\SEO\Editor\Renderers\AIRenderer;
use FP\SEO\Editor\Renderers\AnalysisSectionRenderer;
use FP\SEO\Editor\Renderers\GscMetricsRenderer;
use FP\SEO\Editor\Renderers\HeaderRenderer;
use FP\SEO\Editor\Renderers\InternalLinksRenderer;
use FP\SEO\Editor\Renderers\SchemaRenderer;
use FP\SEO\Editor\Renderers\SerpFieldsRenderer;
use FP\SEO\Editor\Renderers\SerpPreviewRenderer;
use FP\SEO\Editor\Renderers\SocialRenderer;

/**
 * Factory for creating and registering all metabox sections.
 */
class SectionFactory {
	/**
	 * Create and register all sections in the registry.
	 *
	 * @param SectionRegistry $registry Section registry instance.
	 * @return void
	 */
	public static function register_all( SectionRegistry $registry ): void {
		// Header section (priority 1)
		$header_renderer = new HeaderRenderer();
		$registry->register( new HeaderSection( $header_renderer, 1 ) );

		// SERP Preview section (priority 5)
		$serp_preview_renderer = new SerpPreviewRenderer();
		$registry->register( new SerpPreviewSection( $serp_preview_renderer, 5 ) );

		// SERP Optimization section (priority 10)
		$serp_fields_renderer = new SerpFieldsRenderer();
		$registry->register( new SerpSection( $serp_fields_renderer, 10 ) );

		// GSC Metrics section (priority 15)
		$gsc_renderer = new GscMetricsRenderer();
		$registry->register( new GscSection( $gsc_renderer, 15 ) );

		// Analysis section (priority 20)
		$check_help_text = new CheckHelpText();
		$analysis_renderer = new AnalysisSectionRenderer( $check_help_text );
		$registry->register( new AnalysisSection( $analysis_renderer, 20 ) );

		// AI section (priority 25)
		$ai_renderer = new AIRenderer();
		$registry->register( new AISection( $ai_renderer, 25 ) );

		// Social section (priority 30)
		$social_renderer = new SocialRenderer();
		$registry->register( new SocialSection( $social_renderer, 30 ) );

		// Internal Links section (priority 35)
		$links_renderer = new InternalLinksRenderer();
		$registry->register( new InternalLinksSection( $links_renderer, 35 ) );

		// Schema section (priority 40)
		$schema_renderer = new SchemaRenderer();
		$registry->register( new SchemaSection( $schema_renderer, 40 ) );

		// Images section (priority 50 - disabled by default)
		$registry->register( new ImagesSection( 50 ) );
	}
}


