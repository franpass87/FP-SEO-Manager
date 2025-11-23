<?php
/**
 * Analyzer execution context.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Analysis;

use DOMDocument;
use DOMElement;
use DOMXPath;
use function function_exists;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

/**
 * Value object representing the analyzer context payload.
 */
class Context {
	/**
	 * Associated post identifier.
	 *
	 * @var int|null
	 */
	private $post_id;

	/**
	 * Raw HTML payload for the analysis.
	 *
	 * @var string
	 */
	private string $html;

	/**
	 * Document title.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Meta description content.
	 *
	 * @var string
	 */
	private string $meta_description;

	/**
	 * Canonical URL.
	 *
	 * @var string|null
	 */
	private $canonical;

	/**
	 * Robots directive string.
	 *
	 * @var string|null
	 */
	private $robots;

	/**
	 * Focus keyword (primary keyword target).
	 *
	 * @var string
	 */
	private string $focus_keyword;

	/**
	 * Secondary keywords.
	 *
	 * @var array<int, string>
	 */
	private array $secondary_keywords;

	/**
	 * Miscellaneous theme or environment hints.
	 *
	 * @var array<string, mixed>
	 */
	private array $theme_hints;

	/**
	 * Cached DOMDocument instance.
	 *
	 * @var DOMDocument|null
	 */
	private $dom;

	/**
	 * Cached DOMXPath instance.
	 *
	 * @var DOMXPath|null
	 */
	private $xpath;

	/**
	 * Constructor.
	 *
	 * @param int|null            $post_id            Optional post identifier.
	 * @param string              $html               Raw HTML markup for the content body.
	 * @param string              $title              Content title.
	 * @param string              $meta_description   Meta description string.
	 * @param string|null         $canonical          Canonical URL.
	 * @param string|null         $robots             Robots directive string.
	 * @param string              $focus_keyword      Primary focus keyword.
	 * @param array<int, string>  $secondary_keywords Secondary keywords array.
	 * @param array<string,mixed> $theme_hints        Additional hints for analyzers.
	 */
	public function __construct(
		?int $post_id,
		string $html,
		string $title = '',
		string $meta_description = '',
		?string $canonical = null,
		?string $robots = null,
		string $focus_keyword = '',
		array $secondary_keywords = array(),
		array $theme_hints = array()
	) {
		$this->post_id            = $post_id;
		// Process HTML to extract WPBakery content if needed
		$this->html               = $this->process_html_content( $html );
		$this->title              = $title;
		$this->meta_description   = $meta_description;
		$this->canonical          = $canonical;
		$this->robots             = $robots;
		$this->focus_keyword      = $focus_keyword;
		$this->secondary_keywords = $secondary_keywords;
		$this->theme_hints        = $theme_hints;
	}

	/**
	 * Process HTML content to include rendered WPBakery shortcodes.
	 *
	 * @param string $html Raw HTML content.
	 * @return string Processed HTML content.
	 */
	private function process_html_content( string $html ): string {
		// If content contains WPBakery shortcodes, render them to HTML
		if ( strpos( $html, '[vc_' ) !== false || strpos( $html, '[vc_row' ) !== false ) {
			// Execute shortcodes to get rendered HTML
			if ( function_exists( 'do_shortcode' ) ) {
				$rendered = do_shortcode( $html );
				// Combine original HTML with rendered content for better analysis
				// This ensures both shortcode attributes and rendered content are available
				return $html . "\n" . $rendered;
			}
		}

		return $html;
	}

	/**
	 * Retrieve the post identifier if available.
	 *
	 * @return int|null
	 */
	public function post_id(): ?int {
		return $this->post_id;
	}

	/**
	 * Retrieve raw HTML markup.
	 *
	 * @return string
	 */
	public function html(): string {
		return $this->html;
	}

	/**
	 * Retrieve the document title.
	 *
	 * @return string
	 */
	public function title(): string {
		return $this->title;
	}

	/**
	 * Retrieve the meta description string.
	 *
	 * @return string
	 */
	public function meta_description(): string {
		return $this->meta_description;
	}

	/**
	 * Retrieve the canonical URL if supplied.
	 *
	 * @return string|null
	 */
	public function canonical(): ?string {
		return $this->canonical;
	}

	/**
	 * Retrieve the robots directive string.
	 *
	 * @return string|null
	 */
	public function robots(): ?string {
		return $this->robots;
	}

	/**
	 * Retrieve the focus keyword.
	 *
	 * @return string
	 */
	public function focus_keyword(): string {
		return $this->focus_keyword;
	}

	/**
	 * Retrieve the secondary keywords.
	 *
	 * @return array<int, string>
	 */
	public function secondary_keywords(): array {
		return $this->secondary_keywords;
	}

	/**
	 * Check if a focus keyword is set.
	 *
	 * @return bool
	 */
	public function has_focus_keyword(): bool {
		return '' !== trim( $this->focus_keyword );
	}

	/**
	 * Get all keywords (focus + secondary).
	 *
	 * @return array<int, string>
	 */
	public function all_keywords(): array {
		$keywords = array();
		
		if ( $this->has_focus_keyword() ) {
			$keywords[] = $this->focus_keyword;
		}
		
		return array_merge( $keywords, $this->secondary_keywords );
	}

	/**
	 * Retrieve theme hints.
	 *
	 * @return array<string, mixed>
	 */
	public function theme_hints(): array {
		return $this->theme_hints;
	}

	/**
	 * Extract the DOMDocument for the current HTML payload.
	 *
	 * @return DOMDocument|null
	 */
	public function dom(): ?DOMDocument {
		if ( isset( $this->dom ) ) {
			return $this->dom;
		}

		if ( '' === trim( $this->html ) ) {
			$this->dom = null;
			return null;
		}

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $this->html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$this->dom = $loaded ? $dom : null;

		return $this->dom;
	}

	/**
	 * Retrieve a DOMXPath helper if DOM is available.
	 *
	 * @return DOMXPath|null
	 */
	public function xpath(): ?DOMXPath {
		if ( isset( $this->xpath ) ) {
			return $this->xpath;
		}

		$dom = $this->dom();

		if ( null === $dom ) {
			$this->xpath = null;
			return null;
		}

		$this->xpath = new DOMXPath( $dom );

		return $this->xpath;
	}

	/**
	 * Attempt to locate a meta tag by attribute and return its content.
	 *
	 * @param string $attribute Attribute name to match.
	 * @param string $value     Expected attribute value.
	 *
	 * @return string|null
	 */
	public function meta_content( string $attribute, string $value ): ?string {
		$dom = $this->dom();

		if ( null === $dom ) {
			return null;
		}

		$meta_elements = $dom->getElementsByTagName( 'meta' );

		foreach ( $meta_elements as $meta ) {
			/* @var DOMElement $meta DOM element. */
			if ( strtolower( $meta->getAttribute( $attribute ) ) === strtolower( $value ) ) {
				return trim( (string) $meta->getAttribute( 'content' ) );
			}
		}

		return null;
	}

	/**
	 * Locate a link element and retrieve its href.
	 *
	 * @param string $rel Relationship value to match.
	 *
	 * @return string|null
	 */
	public function link_href( string $rel ): ?string {
		$dom = $this->dom();

		if ( null === $dom ) {
			return null;
		}

		$links = $dom->getElementsByTagName( 'link' );

		foreach ( $links as $link ) {
			/* @var DOMElement $link DOM element. */
			if ( strtolower( $link->getAttribute( 'rel' ) ) === strtolower( $rel ) ) {
				return trim( (string) $link->getAttribute( 'href' ) );
			}
		}

		return null;
	}

	/**
	 * Retrieve all heading elements in document order grouped by level.
	 *
	 * @return array<int, array{level:int,text:string}>
	 */
	public function headings(): array {
				return $this->ordered_headings();
	}

	/**
	 * Retrieve all heading elements preserving document order.
	 *
	 * @return array<int, array{level:int,text:string}>
	 */
	public function ordered_headings(): array {
		// Check if content contains WPBakery shortcodes
		if ( strpos( $this->html, '[vc_' ) !== false || strpos( $this->html, '[vc_row' ) !== false ) {
			// Use WPBakery content extractor for headings
			if ( class_exists( '\FP\SEO\Utils\WPBakeryContentExtractor' ) ) {
				$wpbakery_headings = \FP\SEO\Utils\WPBakeryContentExtractor::extract_headings( $this->html );
				if ( ! empty( $wpbakery_headings ) ) {
					return $wpbakery_headings;
				}
			}
		}

		// Standard DOM extraction
		$xpath = $this->xpath();

		if ( null === $xpath ) {
			return array();
		}

		$nodes = $xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' );

		if ( false === $nodes ) {
			return array();
		}

		$headings = array();

		foreach ( $nodes as $node ) {
				/**
				 * DOM element instance.
				 *
				 * @var DOMElement $node
				 */
				$level      = (int) substr( $node->{'tagName'}, 1 );
				$headings[] = array(
					'level' => $level,
					'text'  => trim( $node->{'textContent'} ?? '' ),
				);
		}

		return $headings;
	}

	/**
	 * Retrieve all image elements.
	 *
	 * @return array<int, DOMElement>
	 */
	public function images(): array {
		$dom = $this->dom();

		if ( null === $dom ) {
			return array();
		}

		$nodes  = $dom->getElementsByTagName( 'img' );
		$images = array();

		foreach ( $nodes as $node ) {
			/* @var DOMElement $node DOM element. */
			$images[] = $node; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
		}

		return $images;
	}

	/**
	 * Retrieve all anchor elements.
	 *
	 * @return array<int, DOMElement>
	 */
	public function anchors(): array {
		$dom = $this->dom();

		if ( null === $dom ) {
			return array();
		}

		$nodes   = $dom->getElementsByTagName( 'a' );
		$anchors = array();

		foreach ( $nodes as $node ) {
			/* @var DOMElement $node DOM element. */
			$anchors[] = $node; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
		}

		return $anchors;
	}

	/**
	 * Retrieve script tags with JSON-LD payload.
	 *
	 * @return array<int, string>
	 */
	public function json_ld_blocks(): array {
		$dom = $this->dom();

		if ( null === $dom ) {
			return array();
		}

		$nodes  = $dom->getElementsByTagName( 'script' );
		$blocks = array();

		foreach ( $nodes as $node ) {
				/**
				 * DOM element instance.
				 *
				 * @var DOMElement $node
				 */
			if ( 'application/ld+json' !== strtolower( $node->getAttribute( 'type' ) ) ) {
						continue;
			}

					$blocks[] = trim( $node->{'textContent'} ?? '' );
		}

		return $blocks;
	}

	/**
	 * Retrieve plain text content stripped of markup.
	 *
	 * @return string
	 */
	public function plain_text(): string {
		// Check if content contains WPBakery shortcodes
		if ( strpos( $this->html, '[vc_' ) !== false || strpos( $this->html, '[vc_row' ) !== false ) {
			// Use WPBakery content extractor
			if ( class_exists( '\FP\SEO\Utils\WPBakeryContentExtractor' ) ) {
				return \FP\SEO\Utils\WPBakeryContentExtractor::extract_text( $this->html );
			}
		}

		// Standard processing for non-WPBakery content
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$stripped = wp_strip_all_tags( $this->html, false );
		} else {
			$stripped = strip_tags( $this->html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		}

		return trim( (string) preg_replace( '/\s+/', ' ', $stripped ) );
	}
}
