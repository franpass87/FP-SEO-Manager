<?php
/**
 * GEO Shortcodes - [fp_claim], [fp_citation], [fp_faq]
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Shortcodes;

/**
 * Registers and handles GEO shortcodes
 */
class GeoShortcodes {

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_shortcode( 'fp_claim', array( $this, 'claim_shortcode' ) );
		add_shortcode( 'fp_citation', array( $this, 'citation_shortcode' ) );
		add_shortcode( 'fp_faq', array( $this, 'faq_shortcode' ) );
	}

	/**
	 * [fp_claim] shortcode
	 *
	 * @param array<string,string> $atts    Attributes.
	 * @param string|null          $content Enclosed content.
	 * @return string
	 */
	public function claim_shortcode( $atts, $content = null ): string {
		$atts = shortcode_atts(
			array(
				'statement'      => '',
				'confidence'     => '0.7',
				'evidence_url'   => '',
				'evidence_title' => '',
				'publisher'      => '',
				'author'         => '',
				'accessed'       => '',
			),
			$atts,
			'fp_claim'
		);

		$data_attrs = array(
			'data-statement'  => esc_attr( $atts['statement'] ),
			'data-confidence' => esc_attr( $atts['confidence'] ),
		);

		if ( ! empty( $atts['evidence_url'] ) ) {
			$data_attrs['data-evidence-url']   = esc_url( $atts['evidence_url'] );
			$data_attrs['data-evidence-title'] = esc_attr( $atts['evidence_title'] );
		}

		$attrs_string = '';
		foreach ( $data_attrs as $key => $value ) {
			$attrs_string .= sprintf( ' %s="%s"', $key, $value );
		}

		return sprintf(
			'<span class="fp-claim"%s>%s</span>',
			$attrs_string,
			$content ? do_shortcode( $content ) : esc_html( $atts['statement'] )
		);
	}

	/**
	 * [fp_citation] shortcode
	 *
	 * @param array<string,string> $atts Attributes.
	 * @return string
	 */
	public function citation_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'url'       => '',
				'title'     => '',
				'author'    => '',
				'publisher' => '',
				'accessed'  => '',
			),
			$atts,
			'fp_citation'
		);

		if ( empty( $atts['url'] ) ) {
			return '';
		}

		$data_attrs = array(
			'data-publisher' => esc_attr( $atts['publisher'] ),
			'data-author'    => esc_attr( $atts['author'] ),
			'data-accessed'  => esc_attr( $atts['accessed'] ),
		);

		$attrs_string = '';
		foreach ( $data_attrs as $key => $value ) {
			if ( ! empty( $value ) ) {
				$attrs_string .= sprintf( ' %s="%s"', $key, $value );
			}
		}

		$title = ! empty( $atts['title'] ) ? $atts['title'] : $atts['url'];

		return sprintf(
			'<a href="%s" class="fp-citation"%s rel="nofollow external">%s</a>',
			esc_url( $atts['url'] ),
			$attrs_string,
			esc_html( $title )
		);
	}

	/**
	 * [fp_faq] shortcode
	 *
	 * @param array<string,string> $atts Attributes.
	 * @return string
	 */
	public function faq_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'q' => '',
				'a' => '',
			),
			$atts,
			'fp_faq'
		);

		if ( empty( $atts['q'] ) || empty( $atts['a'] ) ) {
			return '';
		}

		return sprintf(
			'<div class="fp-faq" itemscope itemtype="https://schema.org/Question">
				<h3 class="fp-faq-question" itemprop="name">%s</h3>
				<div class="fp-faq-answer" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
					<div itemprop="text">%s</div>
				</div>
			</div>',
			esc_html( $atts['q'] ),
			wp_kses_post( wpautop( $atts['a'] ) )
		);
	}
}

