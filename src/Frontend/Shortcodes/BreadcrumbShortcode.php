<?php
/**
 * Visual breadcrumb shortcode [fp_breadcrumb].
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Shortcodes;

use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Redirects\RedirectsOptions;
use FP\SEO\Schema\Generators\BreadcrumbSchemaGenerator;

/**
 * Renders visual breadcrumbs reusing schema generator data.
 */
class BreadcrumbShortcode {
	/**
	 * Hook manager.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register shortcode.
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'init', array( $this, 'register_shortcode' ), 12, 0 );
	}

	/**
	 * Register shortcode handler.
	 */
	public function register_shortcode(): void {
		add_shortcode( 'fp_breadcrumb', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render breadcrumb HTML.
	 *
	 * @param array<string,string> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ): string {
		$options = RedirectsOptions::get_breadcrumb();
		if ( empty( $options['enabled'] ) ) {
			return '';
		}

		$generator = new BreadcrumbSchemaGenerator();
		$schema    = $generator->generate();
		$list      = isset( $schema['itemListElement'] ) && is_array( $schema['itemListElement'] ) ? $schema['itemListElement'] : array();
		if ( empty( $list ) ) {
			return '';
		}

		if ( empty( $options['show_home'] ) && isset( $list[0]['name'] ) && 'home' === strtolower( (string) $list[0]['name'] ) ) {
			array_shift( $list );
		}

		$html = '<nav class="fpseo-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'fp-seo-performance' ) . '">';
		$html .= '<ol class="fpseo-breadcrumb-list">';

		$total = count( $list );
		foreach ( $list as $index => $item ) {
			$name = esc_html( (string) ( $item['name'] ?? '' ) );
			$url  = esc_url( (string) ( $item['item'] ?? '#' ) );
			$is_last = ( $index + 1 ) === $total;
			$html .= '<li class="fpseo-breadcrumb-item">';
			if ( $is_last ) {
				$html .= '<span aria-current="page">' . $name . '</span>';
			} else {
				$html .= '<a href="' . $url . '">' . $name . '</a><span class="fpseo-breadcrumb-sep">/</span>';
			}
			$html .= '</li>';
		}

		$html .= '</ol></nav>';
		return $html;
	}
}

