<?php
/**
 * Schema markup renderer for frontend.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Frontend\Renderers;

use FP\SEO\Schema\AdvancedSchemaManager;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use function is_admin;
use function wp_json_encode;

/**
 * Renders Schema.org markup in the frontend head.
 */
class SchemaRenderer extends AbstractRenderer {

	/**
	 * Schema manager instance.
	 *
	 * @var AdvancedSchemaManager
	 */
	private AdvancedSchemaManager $schema_manager;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface   $hook_manager   Hook manager.
	 * @param AdvancedSchemaManager  $schema_manager Schema manager.
	 */
	public function __construct(
		HookManagerInterface $hook_manager,
		AdvancedSchemaManager $schema_manager
	) {
		parent::__construct( $hook_manager );
		$this->schema_manager = $schema_manager;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'wp_head', array( $this, 'render' ), 1 );
	}

	/**
	 * Render schema markup.
	 *
	 * @param mixed $context Rendering context (not used for schema).
	 * @return string Rendered output.
	 */
	public function render( $context = null ): string {
		if ( is_admin() ) {
			return '';
		}

		$schemas = $this->schema_manager->get_active_schemas_public();

		if ( empty( $schemas ) ) {
			return '';
		}

		$output = "\n<!-- FP SEO Performance Schema Markup -->\n";
		foreach ( $schemas as $schema ) {
			$encoded = wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			if ( false === $encoded ) {
				continue;
			}
			$output .= '<script type="application/ld+json">' . "\n";
			$output .= $encoded;
			$output .= "\n" . '</script>' . "\n";
		}
		$output .= "<!-- End FP SEO Performance Schema Markup -->\n";
		
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $output;
	}
}

