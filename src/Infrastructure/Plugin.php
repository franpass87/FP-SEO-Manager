<?php
/**
 * Plugin bootstrapper.
 *
 * @package FP\SEO
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure;

use FP\SEO\Admin\AdminBarBadge;
use FP\SEO\Admin\Menu;
use FP\SEO\Admin\Notices;
use FP\SEO\Admin\SettingsPage;
use FP\SEO\Admin\BulkAuditPage;
use FP\SEO\Editor\Metabox;
use FP\SEO\SiteHealth\SeoHealth;
use FP\SEO\Utils\Assets;

/**
 * Central plugin bootstrapper wiring services together.
 */
class Plugin {

	/**
	 * Shared singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Sets up the container.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Retrieves the singleton plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers core hooks required for bootstrapping.
	 */
	public function init(): void {
		register_activation_hook( FP_SEO_PERFORMANCE_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( FP_SEO_PERFORMANCE_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Boots plugin services after all plugins load.
	 */
	public function boot(): void {
		load_plugin_textdomain( 'fp-seo-performance', false, dirname( plugin_basename( FP_SEO_PERFORMANCE_FILE ) ) . '/languages' );

		$this->container->singleton( Menu::class );
		$this->container->singleton( SettingsPage::class );
		$this->container->singleton( BulkAuditPage::class );
				$this->container->singleton( Notices::class );
				$this->container->singleton( Assets::class );
				$this->container->singleton( AdminBarBadge::class );
				$this->container->singleton( Metabox::class );
		$this->container->singleton( SeoHealth::class );

		$this->container->get( Menu::class )->register();
		$this->container->get( SettingsPage::class )->register();
		$this->container->get( BulkAuditPage::class )->register();
				$this->container->get( Notices::class )->register();
				$this->container->get( Assets::class )->register();
				$this->container->get( AdminBarBadge::class )->register();
				$this->container->get( Metabox::class )->register();
		$this->container->get( SeoHealth::class )->register();
	}

	/**
	 * Runs activation routines.
	 */
	public function activate(): void {
		// Placeholder for activation routines.
	}

	/**
	 * Runs deactivation routines.
	 */
	public function deactivate(): void {
		// Placeholder for deactivation routines.
	}
}
