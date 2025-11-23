<?php
/**
 * WPBakery Page Builder content extractor.
 *
 * Extracts text content from WPBakery shortcodes for SEO analysis.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Extracts readable text content from WPBakery shortcodes.
 */
class WPBakeryContentExtractor {

	/**
	 * Extract plain text from content that may contain WPBakery shortcodes.
	 *
	 * @param string $content Raw content with shortcodes.
	 * @return string Extracted plain text.
	 */
	public static function extract_text( string $content ): string {
		if ( empty( $content ) ) {
			return '';
		}

		// Check if content contains WPBakery shortcodes
		if ( strpos( $content, '[vc_' ) === false && strpos( $content, '[vc_row' ) === false ) {
			// No WPBakery shortcodes, use standard WordPress processing
			return self::extract_from_standard_content( $content );
		}

		// Extract text from WPBakery shortcodes
		$extracted_text = self::extract_from_wpbakery_shortcodes( $content );

		// Also extract from any remaining HTML
		$html_text = self::extract_from_standard_content( $content );

		// Combine both
		$combined = trim( $extracted_text . ' ' . $html_text );
		return preg_replace( '/\s+/', ' ', $combined );
	}

	/**
	 * Extract text from standard WordPress content (HTML + shortcodes).
	 *
	 * @param string $content Raw content.
	 * @return string Extracted text.
	 */
	private static function extract_from_standard_content( string $content ): string {
		// First, execute shortcodes to get rendered HTML
		if ( function_exists( 'do_shortcode' ) ) {
			$content = do_shortcode( $content );
		}

		// Strip HTML tags
		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $content, false );
		}

		return strip_tags( $content );
	}

	/**
	 * Extract text from WPBakery shortcodes.
	 *
	 * @param string $content Content with WPBakery shortcodes.
	 * @return string Extracted text.
	 */
	private static function extract_from_wpbakery_shortcodes( string $content ): string {
		$extracted_texts = array();

		// Extract text from common WPBakery text attributes
		$text_attributes = array(
			'text',
			'text_content',
			'quote',
			'heading',
			'title',
			'description',
			'content',
			'label',
			'caption',
			'subtitle',
			'pre_heading',
		);

		// Pattern to match shortcode attributes
		foreach ( $text_attributes as $attr ) {
			// Match attribute="value" or attribute='value'
			$pattern = '/' . preg_quote( $attr, '/' ) . '\s*=\s*["\']([^"\']+)["\']/i';
			if ( preg_match_all( $pattern, $content, $matches ) ) {
				foreach ( $matches[1] as $match ) {
					$text = trim( $match );
					if ( ! empty( $text ) && $text !== 'true' && $text !== 'false' ) {
						$extracted_texts[] = $text;
					}
				}
			}
		}

		// Extract text content between shortcode tags
		$extracted_texts = array_merge( $extracted_texts, self::extract_text_between_shortcodes( $content ) );

		// Extract from specific WPBakery elements
		$extracted_texts = array_merge( $extracted_texts, self::extract_from_specific_elements( $content ) );

		// Remove duplicates and empty strings
		$extracted_texts = array_filter( array_unique( $extracted_texts ), 'strlen' );

		return implode( ' ', $extracted_texts );
	}

	/**
	 * Extract text content between shortcode opening and closing tags.
	 *
	 * @param string $content Content with shortcodes.
	 * @return array<int, string> Extracted text snippets.
	 */
	private static function extract_text_between_shortcodes( string $content ): array {
		$extracted = array();
		$stack = array();
		$regex = '/\[([a-zA-Z_]+)([^\]]*)\]|\[\/([a-zA-Z_]+)\]|([^[]+)/';

		preg_match_all( $regex, $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			if ( ! empty( $match[1] ) ) {
				// Opening shortcode tag
				$stack[] = array(
					'tag' => $match[1],
					'text' => '',
				);
			} elseif ( ! empty( $match[3] ) ) {
				// Closing shortcode tag
				$nested_content = '';
				while ( ! empty( $stack ) ) {
					$top = array_pop( $stack );
					if ( $top['tag'] === $match[3] ) {
						$text = trim( $top['text'] );
						if ( ! empty( $text ) ) {
							$extracted[] = $text;
						}
						break;
					} else {
						$nested_content = $top['text'] . $nested_content;
					}
				}
				if ( ! empty( $stack ) ) {
					$stack[ count( $stack ) - 1 ]['text'] .= $nested_content;
				}
			} elseif ( ! empty( $match[4] ) ) {
				// Text content
				if ( ! empty( $stack ) ) {
					$stack[ count( $stack ) - 1 ]['text'] .= $match[4];
				} else {
					// Text outside shortcodes
					$text = trim( $match[4] );
					if ( ! empty( $text ) ) {
						$extracted[] = $text;
					}
				}
			}
		}

		return $extracted;
	}

	/**
	 * Extract text from specific WPBakery elements.
	 *
	 * @param string $content Content with shortcodes.
	 * @return array<int, string> Extracted text snippets.
	 */
	private static function extract_from_specific_elements( string $content ): array {
		$extracted = array();

		// Extract from vc_column_text
		if ( preg_match_all( '/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/is', $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$text = wp_strip_all_tags( $match );
				if ( ! empty( trim( $text ) ) ) {
					$extracted[] = trim( $text );
				}
			}
		}

		// Extract from vc_custom_heading
		if ( preg_match_all( '/\[vc_custom_heading[^\]]*heading\s*=\s*["\']([^"\']+)["\'][^\]]*\]/i', $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$text = trim( $match );
				if ( ! empty( $text ) ) {
					$extracted[] = $text;
				}
			}
		}

		// Extract from vc_message
		if ( preg_match_all( '/\[vc_message[^\]]*message\s*=\s*["\']([^"\']+)["\'][^\]]*\]/i', $content, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				$text = trim( $match );
				if ( ! empty( $text ) ) {
					$extracted[] = $text;
				}
			}
		}

		// Extract from vc_toggle (FAQ-like)
		if ( preg_match_all( '/\[vc_toggle[^\]]*title\s*=\s*["\']([^"\']+)["\'][^\]]*\](.*?)\[\/vc_toggle\]/is', $content, $matches ) ) {
			foreach ( $matches[1] as $index => $title ) {
				$text = trim( $title );
				if ( ! empty( $text ) ) {
					$extracted[] = $text;
				}
				// Also extract content
				if ( isset( $matches[2][ $index ] ) ) {
					$content_text = wp_strip_all_tags( $matches[2][ $index ] );
					if ( ! empty( trim( $content_text ) ) ) {
						$extracted[] = trim( $content_text );
					}
				}
			}
		}

		// Extract from vc_accordion items
		if ( preg_match_all( '/\[vc_toggle[^\]]*title\s*=\s*["\']([^"\']+)["\'][^\]]*\](.*?)\[\/vc_toggle\]/is', $content, $matches ) ) {
			foreach ( $matches[1] as $index => $title ) {
				$text = trim( $title );
				if ( ! empty( $text ) ) {
					$extracted[] = $text;
				}
			}
		}

		return $extracted;
	}

	/**
	 * Extract headings from WPBakery content.
	 *
	 * @param string $content Content with shortcodes.
	 * @return array<int, array{level: int, text: string}> Headings with level.
	 */
	public static function extract_headings( string $content ): array {
		$headings = array();

		if ( empty( $content ) ) {
			return $headings;
		}

		// Extract from vc_custom_heading with tag attribute
		if ( preg_match_all( '/\[vc_custom_heading[^\]]*tag\s*=\s*["\'](h[1-6])["\'][^\]]*heading\s*=\s*["\']([^"\']+)["\'][^\]]*\]/i', $content, $matches ) ) {
			foreach ( $matches[1] as $index => $tag ) {
				$level = (int) substr( $tag, 1 );
				$text = trim( $matches[2][ $index ] ?? '' );
				if ( ! empty( $text ) ) {
					$headings[] = array(
						'level' => $level,
						'text'  => $text,
					);
				}
			}
		}

		// Also extract from rendered HTML (if shortcodes were executed)
		if ( function_exists( 'do_shortcode' ) ) {
			$rendered = do_shortcode( $content );
			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $rendered );
			libxml_clear_errors();

			$h_tags = $dom->getElementsByTagName( 'h1' );
			foreach ( $h_tags as $h ) {
				$text = trim( $h->textContent ?? '' );
				if ( ! empty( $text ) ) {
					$headings[] = array( 'level' => 1, 'text' => $text );
				}
			}

			for ( $i = 2; $i <= 6; $i++ ) {
				$h_tags = $dom->getElementsByTagName( 'h' . $i );
				foreach ( $h_tags as $h ) {
					$text = trim( $h->textContent ?? '' );
					if ( ! empty( $text ) ) {
						$headings[] = array( 'level' => $i, 'text' => $text );
					}
				}
			}
		}

		return $headings;
	}
}

