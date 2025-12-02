<?php
/**
 * Outputs social media meta tags.
 *
 * @package FP\SEO\Social\Output
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Social\Output;

use function esc_attr;
use function get_bloginfo;
use function get_locale;
use function get_option;

/**
 * Outputs social media meta tags.
 */
class SocialMetaTagsOutput {
	/**
	 * Output all social media meta tags.
	 *
	 * @param array<string, mixed> $social_meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	public function output( array $social_meta, array $defaults ): void {
		echo "\n<!-- FP SEO Performance Social Media Tags -->\n";
		
		// Open Graph tags
		$this->output_open_graph_tags( $social_meta, $defaults );
		
		// Twitter Card tags
		$this->output_twitter_card_tags( $social_meta, $defaults );
		
		// LinkedIn tags
		$this->output_linkedin_tags( $social_meta, $defaults );
		
		// Pinterest tags
		$this->output_pinterest_tags( $social_meta, $defaults );
		
		echo "<!-- End FP SEO Performance Social Media Tags -->\n";
	}

	/**
	 * Output Open Graph meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	private function output_open_graph_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['facebook_title'] ) ? $meta['facebook_title'] : $defaults['title'];
		$description = ! empty( $meta['facebook_description'] ) ? $meta['facebook_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$og_tags = array(
			'og:title' => $title,
			'og:description' => $description,
			'og:type' => 'article',
			'og:url' => $permalink,
			'og:site_name' => get_bloginfo( 'name' ),
			'og:locale' => get_locale(),
		);

		// Add image
		$og_image = $this->get_social_image( $meta, 'facebook', $defaults['post_id'] );
		if ( $og_image ) {
			$og_tags['og:image'] = $og_image;
			$og_tags['og:image:width'] = 1200;
			$og_tags['og:image:height'] = 630;
			$og_tags['og:image:alt'] = $title;
		}

		foreach ( $og_tags as $property => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output Twitter Card meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	private function output_twitter_card_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['twitter_title'] ) ? $meta['twitter_title'] : $defaults['title'];
		$description = ! empty( $meta['twitter_description'] ) ? $meta['twitter_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$twitter_tags = array(
			'twitter:card' => $meta['twitter_card_type'] ?? 'summary_large_image',
			'twitter:title' => $title,
			'twitter:description' => $description,
			'twitter:url' => $permalink,
		);

		// Add image
		$twitter_image = $this->get_social_image( $meta, 'twitter', $defaults['post_id'] );
		if ( $twitter_image ) {
			$twitter_tags['twitter:image'] = $twitter_image;
			$twitter_tags['twitter:image:alt'] = $title;
		}

		foreach ( $twitter_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output LinkedIn meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	private function output_linkedin_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['linkedin_title'] ) ? $meta['linkedin_title'] : $defaults['title'];
		$description = ! empty( $meta['linkedin_description'] ) ? $meta['linkedin_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$linkedin_tags = array(
			'linkedin:title' => $title,
			'linkedin:description' => $description,
			'linkedin:url' => $permalink,
		);

		$linkedin_image = $this->get_social_image( $meta, 'linkedin', $defaults['post_id'] );
		if ( $linkedin_image ) {
			$linkedin_tags['linkedin:image'] = $linkedin_image;
		}

		foreach ( $linkedin_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Output Pinterest meta tags.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 * @param array<string, mixed> $defaults Default values.
	 * @return void
	 */
	private function output_pinterest_tags( array $meta, array $defaults ): void {
		$title       = ! empty( $meta['pinterest_title'] ) ? $meta['pinterest_title'] : $defaults['title'];
		$description = ! empty( $meta['pinterest_description'] ) ? $meta['pinterest_description'] : $defaults['description'];
		$permalink   = $defaults['permalink'];

		$pinterest_tags = array(
			'pinterest:title' => $title,
			'pinterest:description' => $description,
			'pinterest:url' => $permalink,
		);

		$pinterest_image = $this->get_social_image( $meta, 'pinterest', $defaults['post_id'] );
		if ( $pinterest_image ) {
			$pinterest_tags['pinterest:image'] = $pinterest_image;
		}

		foreach ( $pinterest_tags as $name => $content ) {
			if ( ! empty( $content ) ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
	}

	/**
	 * Get social image for platform.
	 *
	 * @param array<string, mixed> $meta Social meta data.
	 * @param string $platform Platform name.
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	private function get_social_image( array $meta, string $platform, int $post_id ): ?string {
		// Check for platform-specific image
		$platform_image = $meta[ $platform . '_image' ] ?? null;
		if ( ! empty( $platform_image ) ) {
			return $platform_image;
		}

		// Featured image check removed - no longer using featured images

		// Check for default social image
		$default_image = get_option( 'fp_seo_social_default_image' );
		if ( $default_image ) {
			return $default_image;
		}

		return null;
	}
}


