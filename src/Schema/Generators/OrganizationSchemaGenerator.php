<?php
/**
 * Organization schema generator.
 *
 * @package FP\SEO\Schema\Generators
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Generators;

use FP\SEO\Schema\Helpers\SchemaHelper;
use function get_bloginfo;
use function get_option;
use function home_url;

/**
 * Generates Organization schema.
 */
class OrganizationSchemaGenerator extends AbstractSchemaGenerator {
	/**
	 * Generate Organization schema.
	 *
	 * @param int|null $post_id Optional post ID (not used for Organization).
	 * @return array<string, mixed>
	 */
	public function generate( ?int $post_id = null ): array {
		$options = get_option( 'fp_seo_performance', array() );
		$org_data = $options['schema']['organization'] ?? array();

		$schema = $this->build_base_schema();
		$schema['name'] = $org_data['name'] ?? get_bloginfo( 'name' );
		$schema['url'] = home_url();
		$schema['logo'] = array(
			'@type' => 'ImageObject',
			'url' => $org_data['logo'] ?? SchemaHelper::get_custom_logo_url(),
		);
		$schema['description'] = $org_data['description'] ?? get_bloginfo( 'description' );
		$schema['address'] = $this->get_address_schema( $org_data );
		$schema['contactPoint'] = $this->get_contact_point_schema( $org_data );
		$schema['sameAs'] = $this->get_social_links( $org_data );

		return $schema;
	}

	/**
	 * Get schema type.
	 *
	 * @return string
	 */
	protected function get_schema_type(): string {
		return 'Organization';
	}

	/**
	 * Get address schema.
	 *
	 * @param array<string, mixed> $org_data Organization data.
	 * @return array<string, mixed>|null
	 */
	private function get_address_schema( array $org_data ): ?array {
		$address = $org_data['address'] ?? array();
		if ( empty( $address ) ) {
			return null;
		}

		return array(
			'@type' => 'PostalAddress',
			'streetAddress' => $address['street'] ?? '',
			'addressLocality' => $address['city'] ?? '',
			'postalCode' => $address['postal_code'] ?? '',
			'addressRegion' => $address['region'] ?? '',
			'addressCountry' => $address['country'] ?? '',
		);
	}

	/**
	 * Get contact point schema.
	 *
	 * @param array<string, mixed> $org_data Organization data.
	 * @return array<string, mixed>|null
	 */
	private function get_contact_point_schema( array $org_data ): ?array {
		$contact = $org_data['contact'] ?? array();
		if ( empty( $contact['phone'] ) && empty( $contact['email'] ) ) {
			return null;
		}

		return array(
			'@type' => 'ContactPoint',
			'telephone' => $contact['phone'] ?? '',
			'email' => $contact['email'] ?? '',
			'contactType' => $contact['type'] ?? 'customer service',
		);
	}

	/**
	 * Get social links.
	 *
	 * @param array<string, mixed> $org_data Organization data.
	 * @return array<string>|null
	 */
	private function get_social_links( array $org_data ): ?array {
		$social = $org_data['social'] ?? array();
		if ( empty( $social ) ) {
			return null;
		}

		return array_filter( $social );
	}
}

