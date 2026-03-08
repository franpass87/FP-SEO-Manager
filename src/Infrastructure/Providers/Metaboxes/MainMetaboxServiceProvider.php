<?php
/**
 * Main Metabox service provider.
 *
 * Registers the main SEO metabox for WordPress editor.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\Metaboxes\MetaboxServicesProvider;
use FP\SEO\Editor\Metabox;
use FP\SEO\Editor\Services\AnalysisDataService;
use FP\SEO\Editor\Services\MetadataService;
use FP\SEO\Editor\Services\AnalysisRunner;
use FP\SEO\Editor\Services\MetaboxValidator;
use FP\SEO\Editor\Services\SeoFieldsSaver;
use FP\SEO\Editor\Handlers\AnalyzeAjaxHandler;
use FP\SEO\Editor\Handlers\SaveFieldsAjaxHandler;
use FP\SEO\Editor\Sections\SectionRegistry;
use FP\SEO\Editor\Sections\SectionFactory;
use FP\SEO\Editor\MetaboxRenderer;
use FP\SEO\Utils\PostTypes as PostTypesUtil;

/**
 * Main Metabox service provider.
 *
 * Registers the core SEO metabox used in WordPress editor.
 * This is the most critical metabox and uses 'error' log level.
 */
class MainMetaboxServiceProvider extends AbstractMetaboxServiceProvider {

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<\FP\SEO\Infrastructure\ServiceProviderInterface>>
	 */
	public function get_dependencies(): array {
		return array(
			CoreServiceProvider::class,
			MetaboxServicesProvider::class,
		);
	}

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	protected function get_metabox_class(): string {
		return Metabox::class;
	}

	/**
	 * Register main metabox service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register new modular services
		$container->singleton( AnalysisDataService::class, function() {
			return new AnalysisDataService();
		} );

		$container->singleton( MetadataService::class, function() {
			return new MetadataService();
		} );

		$container->singleton( AnalysisRunner::class, function() {
			return new AnalysisRunner();
		} );

		$container->singleton( MetaboxValidator::class, function( Container $container ) {
			return new MetaboxValidator( $container->get( LoggerInterface::class ) );
		} );

		$container->singleton( SeoFieldsSaver::class, function() {
			return new SeoFieldsSaver();
		} );

		// Register SectionRegistry
		$container->singleton( SectionRegistry::class, function( Container $container ) {
			$registry = new SectionRegistry();
			// Register all sections using factory
			SectionFactory::register_all( $registry );
			return $registry;
		} );

		// Register MetaboxRenderer with optional SectionRegistry
		$container->singleton( MetaboxRenderer::class, function( Container $container ) {
			// Try to get SectionRegistry, but allow it to be null for backward compatibility
			try {
				$section_registry = $container->get( SectionRegistry::class );
			} catch ( \Throwable $e ) {
				$section_registry = null;
			}
			return new MetaboxRenderer( $section_registry );
		} );

		// Register AJAX handlers
		$container->singleton( AnalyzeAjaxHandler::class, function( Container $container ) {
			$supported_types = PostTypesUtil::analyzable();
			return new AnalyzeAjaxHandler(
				$container->get( HookManagerInterface::class ),
				$container->get( AnalysisRunner::class ),
				$container->get( AnalysisDataService::class ),
				$supported_types
			);
		} );

		$container->singleton( SaveFieldsAjaxHandler::class, function( Container $container ) {
			$supported_types = PostTypesUtil::analyzable();
			return new SaveFieldsAjaxHandler(
				$container->get( HookManagerInterface::class ),
				$container->get( SeoFieldsSaver::class ),
				$container->get( MetaboxValidator::class ),
				$supported_types
			);
		} );

		// Register main SEO metabox as singleton with HookManager, Logger and Options dependencies
		$container->singleton( Metabox::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$logger       = $container->get( LoggerInterface::class );
			$options      = $container->get( OptionsInterface::class );
			return new Metabox( $hook_manager, $logger, $options );
		} );
	}

	/**
	 * Get the log level for booting this metabox.
	 *
	 * Main metabox is critical, so use 'error' level.
	 *
	 * @return string Log level.
	 */
	protected function get_boot_log_level(): string {
		return 'error';
	}

	/**
	 * Get the error message prefix for booting failures.
	 *
	 * @return string Error message prefix.
	 */
	protected function get_boot_error_message(): string {
		return 'Failed to register Metabox';
	}

	/**
	 * Boot main metabox service.
	 *
	 * Overrides parent to add additional debug logging.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {
		// CRITICAL: Wrap in try-catch to prevent fatal errors from breaking WordPress
		try {
			// Use parent implementation
			parent::boot_admin( $container );

			// Additional debug logging for main metabox
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				try {
					$metabox = $container->get( Metabox::class );
					$logger  = $container->get( LoggerInterface::class );
					$logger->debug( 'Metabox instance created', array( 'class' => get_class( $metabox ) ) );
					$logger->debug( 'Metabox::register() called successfully' );
				} catch ( \Throwable $e ) {
					// Silent fail in debug mode
					try {
						$logger = $container->get( LoggerInterface::class );
						$logger->error( 'FP SEO: Error getting Metabox instance in MainMetaboxServiceProvider', array(
							'error' => $e->getMessage(),
							'trace' => $e->getTraceAsString(),
						) );
					} catch ( \Throwable $logger_error ) {
						// If logger fails, silently continue
					}
				}
			}
		} catch ( \Throwable $e ) {
			// Log error but don't break WordPress
			try {
				$logger = $container->get( LoggerInterface::class );
				$logger->error( 'FP SEO: Fatal error in MainMetaboxServiceProvider::boot_admin()', array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				) );
			} catch ( \Throwable $logger_error ) {
				// If logger fails, silently continue
			}
			// Don't re-throw - allow WordPress to continue
		}
	}
}

