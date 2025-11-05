<?php
/**
 * GEO Router - Handles all /geo/* and /.well-known/ai.txt endpoints
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

/**
 * Routes GEO-related endpoints
 */
class Router {

	/**
	 * Register routing hooks
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_geo_requests' ), 5 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Add custom rewrite rules for GEO endpoints
	 */
	public function add_rewrite_rules(): void {
		// /.well-known/ai.txt
		add_rewrite_rule(
			'^\.well-known/ai\.txt$',
			'index.php?fp_geo_route=ai_txt',
			'top'
		);

		// /geo-sitemap.xml
		add_rewrite_rule(
			'^geo-sitemap\.xml$',
			'index.php?fp_geo_route=geo_sitemap',
			'top'
		);

		// /geo/site.json
		add_rewrite_rule(
			'^geo/site\.json$',
			'index.php?fp_geo_route=site_json',
			'top'
		);

		// /geo/updates.json
		add_rewrite_rule(
			'^geo/updates\.json$',
			'index.php?fp_geo_route=updates_json',
			'top'
		);

		// /geo/content/{post_id}.json
		add_rewrite_rule(
			'^geo/content/([0-9]+)\.json$',
			'index.php?fp_geo_route=content_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// NEW AI-FIRST ENDPOINTS

		// /geo/content/{post_id}/qa.json - Q&A Pairs
		add_rewrite_rule(
			'^geo/content/([0-9]+)/qa\.json$',
			'index.php?fp_geo_route=qa_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// /geo/content/{post_id}/chunks.json - Semantic Chunks
		add_rewrite_rule(
			'^geo/content/([0-9]+)/chunks\.json$',
			'index.php?fp_geo_route=chunks_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// /geo/content/{post_id}/entities.json - Entity Graph
		add_rewrite_rule(
			'^geo/content/([0-9]+)/entities\.json$',
			'index.php?fp_geo_route=entities_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// /geo/content/{post_id}/authority.json - Authority Signals
		add_rewrite_rule(
			'^geo/content/([0-9]+)/authority\.json$',
			'index.php?fp_geo_route=authority_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// /geo/content/{post_id}/variants.json - Conversational Variants
		add_rewrite_rule(
			'^geo/content/([0-9]+)/variants\.json$',
			'index.php?fp_geo_route=variants_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// /geo/content/{post_id}/images.json - Multi-Modal Data
		add_rewrite_rule(
			'^geo/content/([0-9]+)/images\.json$',
			'index.php?fp_geo_route=images_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// /geo/content/{post_id}/embeddings.json - Vector Embeddings
		add_rewrite_rule(
			'^geo/content/([0-9]+)/embeddings\.json$',
			'index.php?fp_geo_route=embeddings_json&fp_geo_post_id=$matches[1]',
			'top'
		);

		// /geo/training-data.jsonl - AI Training Dataset
		add_rewrite_rule(
			'^geo/training-data\.jsonl$',
			'index.php?fp_geo_route=training_data',
			'top'
		);
	}

	/**
	 * Add custom query vars
	 *
	 * @param array<string> $vars Query vars.
	 * @return array<string>
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'fp_geo_route';
		$vars[] = 'fp_geo_post_id';
		return $vars;
	}

	/**
	 * Handle GEO endpoint requests
	 */
	public function handle_geo_requests(): void {
		$route = get_query_var( 'fp_geo_route', '' );

		if ( empty( $route ) ) {
			return;
		}

		// Prevent WP from loading unnecessary scripts
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'rsd_link' );

		switch ( $route ) {
			case 'ai_txt':
				$this->serve_ai_txt();
				break;

			case 'geo_sitemap':
				$this->serve_geo_sitemap();
				break;

			case 'site_json':
				$this->serve_site_json();
				break;

			case 'updates_json':
				$this->serve_updates_json();
				break;

			case 'content_json':
				$this->serve_content_json();
				break;

			// NEW AI-FIRST ENDPOINTS
			case 'qa_json':
				$this->serve_qa_json();
				break;

			case 'chunks_json':
				$this->serve_chunks_json();
				break;

			case 'entities_json':
				$this->serve_entities_json();
				break;

			case 'authority_json':
				$this->serve_authority_json();
				break;

			case 'variants_json':
				$this->serve_variants_json();
				break;

			case 'images_json':
				$this->serve_images_json();
				break;

			case 'embeddings_json':
				$this->serve_embeddings_json();
				break;

			case 'training_data':
				$this->serve_training_data();
				break;

			default:
				// Invalid route, let WP handle 404
				return;
		}

		exit;
	}

	/**
	 * Serve /.well-known/ai.txt
	 */
	private function serve_ai_txt(): void {
		$generator = new AiTxt();
		$content   = $generator->generate();

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Robots-Tag: noindex' );

		echo $content;
	}

	/**
	 * Serve /geo-sitemap.xml
	 */
	private function serve_geo_sitemap(): void {
		$generator = new GeoSitemap();
		$xml       = $generator->generate();

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'Cache-Control: public, max-age=900' );
		header( 'X-Robots-Tag: noindex' );

		echo $xml;
	}

	/**
	 * Serve /geo/site.json
	 */
	private function serve_site_json(): void {
		$generator = new SiteJson();
		$data      = $generator->generate();

		$this->send_json_response( $data );
	}

	/**
	 * Serve /geo/updates.json
	 */
	private function serve_updates_json(): void {
		$generator = new UpdatesJson();
		$data      = $generator->generate();

		$this->send_json_response( $data );
	}

	/**
	 * Serve /geo/content/{post_id}.json
	 */
	private function serve_content_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$generator = new ContentJson();
		$data      = $generator->generate( $post_id );

		if ( null === $data ) {
			$this->send_404();
			return;
		}

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Send JSON response with headers
	 *
	 * @param array<string,mixed> $data    JSON data.
	 * @param int                 $post_id Optional post ID for ETag.
	 */
	private function send_json_response( array $data, int $post_id = 0 ): void {
		$options = get_option( 'fp_seo_performance', array() );
		$geo     = $options['geo'] ?? array();
		$pretty  = ! empty( $geo['pretty_print'] );

		$json = $pretty
			? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			: wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// Generate ETag
		$etag = 'W/"' . md5( $json ) . '"';

		// Check If-None-Match (sanitize header value for security)
		$if_none_match = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		if ( $if_none_match === $etag ) {
			http_response_code( 304 );
			header( 'ETag: ' . $etag );
			exit;
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=300' );
		header( 'ETag: ' . $etag );
		header( 'X-Robots-Tag: noindex' );

		// Add Last-Modified if post-specific
		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$modified = strtotime( $post->post_modified_gmt );
				header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' );
			}
		}

		echo $json;
	}

	/**
	 * Send 404 response
	 */
	private function send_404(): void {
		http_response_code( 404 );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( array( 'error' => 'Not Found' ) );
	}

	// NEW AI-FIRST ENDPOINT HANDLERS

	/**
	 * Serve /geo/content/{post_id}/qa.json
	 */
	private function serve_qa_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$extractor = new \FP\SEO\AI\QAPairExtractor();
		$qa_pairs  = $extractor->get_qa_pairs( $post_id );

		if ( empty( $qa_pairs ) ) {
			// Try to generate them
			$qa_pairs = $extractor->extract_qa_pairs( $post_id );
		}

		$data = array(
			'post_id'  => $post_id,
			'qa_pairs' => $qa_pairs,
			'total'    => count( $qa_pairs ),
			'faq_schema' => $extractor->get_faq_schema( $post_id ),
		);

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Serve /geo/content/{post_id}/chunks.json
	 */
	private function serve_chunks_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$chunker = new SemanticChunker();
		$chunks  = $chunker->chunk_content( $post_id );

		$data = array(
			'post_id'      => $post_id,
			'chunks'       => $chunks,
			'total_chunks' => count( $chunks ),
		);

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Serve /geo/content/{post_id}/entities.json
	 */
	private function serve_entities_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$graph = new EntityGraph();
		$data  = $graph->build_entity_graph( $post_id );

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Serve /geo/content/{post_id}/authority.json
	 */
	private function serve_authority_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$authority = new AuthoritySignals();
		$data      = $authority->get_authority_signals( $post_id );

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Serve /geo/content/{post_id}/variants.json
	 */
	private function serve_variants_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$generator = new \FP\SEO\AI\ConversationalVariants();
		$variants  = $generator->get_all_variants( $post_id );

		if ( empty( $variants ) ) {
			// Try to generate them
			$variants = $generator->generate_variants( $post_id );
		}

		$data = array(
			'post_id'  => $post_id,
			'variants' => $variants,
			'types'    => \FP\SEO\AI\ConversationalVariants::get_variant_types(),
		);

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Serve /geo/content/{post_id}/images.json
	 */
	private function serve_images_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$optimizer = new MultiModalOptimizer();
		$data      = $optimizer->get_optimization_data( $post_id );

		if ( null === $data ) {
			// Generate optimization data
			$data = $optimizer->optimize_images( $post_id );
		}

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Serve /geo/content/{post_id}/embeddings.json
	 */
	private function serve_embeddings_json(): void {
		$post_id = (int) get_query_var( 'fp_geo_post_id', 0 );

		if ( $post_id <= 0 ) {
			$this->send_404();
			return;
		}

		$generator = new \FP\SEO\AI\EmbeddingsGenerator();
		$data      = $generator->get_embeddings( $post_id );

		if ( empty( $data ) ) {
			// Return info about embeddings not available
			$data = array(
				'post_id' => $post_id,
				'error'   => 'Embeddings not generated yet',
				'message' => 'Call this endpoint to trigger generation',
			);
		}

		$this->send_json_response( $data, $post_id );
	}

	/**
	 * Serve /geo/training-data.jsonl
	 */
	private function serve_training_data(): void {
		$formatter = new TrainingDatasetFormatter();
		$jsonl     = $formatter->export_site_dataset( 50 ); // Export 50 most recent posts

		header( 'Content-Type: application/x-ndjson; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Content-Disposition: inline; filename="training-data.jsonl"' );

		echo $jsonl;
	}
}

