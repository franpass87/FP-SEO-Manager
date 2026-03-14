<?php
/**
 * Robots.txt frontend renderer.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Renderers;

use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Redirects\RedirectsOptions;

/**
 * Injects validated robots rules via WordPress robots_txt filter.
 */
class RobotsTxtRenderer {
	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface $hook_manager Hook manager instance.
	 */
	public function __construct( HookManagerInterface $hook_manager ) {
		$this->hook_manager = $hook_manager;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		$this->hook_manager->add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
	}

	/**
	 * Filter robots.txt content.
	 *
	 * @param string $output Existing robots rules.
	 * @param bool   $public Public blog flag.
	 * @return string
	 */
	public function filter_robots_txt( string $output, bool $public ): string {
		$options = RedirectsOptions::get_robots();
		if ( empty( $options['enabled'] ) ) {
			return $output;
		}

		$extra_rules = trim( (string) $options['extra_rules'] );
		$lines       = array();
		if ( '' !== $extra_rules ) {
			$raw_lines = preg_split( '/\r\n|\r|\n/', $extra_rules ) ?: array();
			foreach ( $raw_lines as $line ) {
				$line = trim( (string) $line );
				if ( '' === $line ) {
					continue;
				}
				if ( ! preg_match( '/^(User-agent|Allow|Disallow|Crawl-delay|Host)\s*:/i', $line ) ) {
					continue;
				}
				$lines[] = $line;
			}
		}

		$lines[] = 'Sitemap: ' . home_url( '/fp-sitemap.xml' );
		$custom  = implode( "\n", array_unique( $lines ) );
		if ( '' === $custom ) {
			return $output;
		}

		return trim( $output ) . "\n\n# FP SEO Performance\n" . $custom . "\n";
	}
}

