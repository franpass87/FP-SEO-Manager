<?php
/**
 * Core service provider.
 *
 * Registers fundamental services like Cache, Logger (static), and HealthChecker.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\ConditionalServiceTrait;
use FP\SEO\Infrastructure\Traits\HookHelperTrait;
use FP\SEO\SiteHealth\SeoHealth;
use FP\SEO\Infrastructure\Contracts\CacheInterface;
use FP\SEO\Core\Services\Cache\WordPressCache;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;
use FP\SEO\Core\Services\Logger\WordPressLogger;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
use FP\SEO\Core\Services\Options\OptionsManager;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Bootstrap\HookManager;
use FP\SEO\Data\Contracts\PostRepositoryInterface;
use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use FP\SEO\Data\Repositories\PostRepository;
use FP\SEO\Data\Repositories\PostMetaRepository;
use FP\SEO\Perf\Signals;
use FP\SEO\History\ScoreHistory;
use FP\SEO\Core\Services\Validation\ValidationServiceInterface;
use FP\SEO\Core\Services\Validation\WordPressValidationService;
use FP\SEO\Core\Services\Sanitization\SanitizationServiceInterface;
use FP\SEO\Core\Services\Sanitization\WordPressSanitizationService;
use FP\SEO\Core\Services\Http\HttpClientInterface;
use FP\SEO\Core\Services\Http\WordPressHttpClient;
use FP\SEO\Core\Services\Environment\EnvironmentService;
use FP\SEO\Core\Services\ExceptionHandler\ExceptionHandlerService;

/**
 * Core service provider.
 *
 * Registers essential services that other providers depend on.
 */
class CoreServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;
	use ConditionalServiceTrait;
	use HookHelperTrait;

	/**
	 * Register core services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Cache service — singleton condiviso tra interfaccia e classe concreta
		$container->singleton( CacheInterface::class, function() {
			return new WordPressCache();
		} );
		$container->singleton( WordPressCache::class, function( Container $c ) {
			return $c->get( CacheInterface::class );
		} );

		// Logger service — singleton condiviso
		$container->singleton( LoggerInterface::class, function() {
			return new WordPressLogger();
		} );
		$container->singleton( WordPressLogger::class, function( Container $c ) {
			return $c->get( LoggerInterface::class );
		} );

		// Options service — singleton condiviso
		$container->singleton( OptionsInterface::class, function( Container $c ) {
			return new OptionsManager( $c->get( CacheInterface::class ) );
		} );
		$container->singleton( OptionsManager::class, function( Container $c ) {
			return $c->get( OptionsInterface::class );
		} );

		// Hook Manager — singleton condiviso
		$container->singleton( HookManagerInterface::class, function() {
			return new HookManager();
		} );
		$container->singleton( HookManager::class, function( Container $c ) {
			return $c->get( HookManagerInterface::class );
		} );

		// Repositories — singleton condivisi
		$container->singleton( PostRepositoryInterface::class, function() {
			return new PostRepository();
		} );
		$container->singleton( PostRepository::class, function( Container $c ) {
			return $c->get( PostRepositoryInterface::class );
		} );
		$container->singleton( PostMetaRepositoryInterface::class, function() {
			return new PostMetaRepository();
		} );
		$container->singleton( PostMetaRepository::class, function( Container $c ) {
			return $c->get( PostMetaRepositoryInterface::class );
		} );

		// Site Health - registers WordPress Site Health checks
		$container->singleton( SeoHealth::class, function() {
			return new SeoHealth( new Signals() );
		} );

		// Score History - tracks SEO score changes over time
		$container->singleton( ScoreHistory::class );

		// Validation service — singleton condiviso
		$container->singleton( ValidationServiceInterface::class, function() {
			return new WordPressValidationService();
		} );
		$container->singleton( WordPressValidationService::class, function( Container $c ) {
			return $c->get( ValidationServiceInterface::class );
		} );

		// Sanitization service — singleton condiviso
		$container->singleton( SanitizationServiceInterface::class, function() {
			return new WordPressSanitizationService();
		} );
		$container->singleton( WordPressSanitizationService::class, function( Container $c ) {
			return $c->get( SanitizationServiceInterface::class );
		} );

		// HTTP client service — singleton condiviso
		$container->singleton( HttpClientInterface::class, function() {
			return new WordPressHttpClient();
		} );
		$container->singleton( WordPressHttpClient::class, function( Container $c ) {
			return $c->get( HttpClientInterface::class );
		} );

		// Environment service - environment checks
		$container->singleton( EnvironmentService::class );

		// Exception handler service - centralized exception handling
		$container->singleton( ExceptionHandlerService::class, function( Container $container ) {
			$logger = $container->get( LoggerInterface::class );
			return new ExceptionHandlerService( $logger );
		} );
	}

	/**
	 * Boot core services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Load plugin text domain
		$this->load_textdomain( $container );

		// Register capability for administrator role
		$this->register_capability( $container );

		// Register Site Health checks
		$this->boot_service(
			$container,
			SeoHealth::class,
			'warning',
			'Failed to register SeoHealth'
		);

		// Register Score History (defer to admin_init for admin-only hook)
		if ( $this->is_admin_context() ) {
			$this->defer_to_admin_init( $container, function( Container $container ) {
				$this->boot_service(
					$container,
					ScoreHistory::class,
					'warning',
					'Failed to register ScoreHistory'
				);
			}, 20 );
		}
	}

	/**
	 * Load plugin text domain.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	private function load_textdomain( Container $container ): void {
		$hook_manager = $container->get( HookManagerInterface::class );
		$hook_manager->add_action(
			'init',
			function() {
				load_plugin_textdomain(
					'fp-seo-performance',
					false,
					dirname( plugin_basename( FP_SEO_PERFORMANCE_FILE ) ) . '/languages'
				);
			},
			0
		);
	}

	/**
	 * Register capability for administrator role.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	private function register_capability( Container $container ): void {
		$hook_manager = $container->get( HookManagerInterface::class );
		$hook_manager->add_action(
			'init',
			function() use ( $container ) {
				if ( ! function_exists( 'get_role' ) ) {
					return;
				}

				$options = $container->get( OptionsInterface::class );
				$capability = $options->get_capability();

				if ( empty( $capability ) || 'manage_options' === $capability ) {
					return;
				}

				$administrator = get_role( 'administrator' );

				if ( $administrator && ! $administrator->has_cap( $capability ) ) {
					$administrator->add_cap( $capability );
				}
			},
			5
		);
	}

	/**
	 * Run activation routines for core services.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Database migrations are now handled by DataServiceProvider
		// This method is kept for backward compatibility but does nothing
		// The ScoreHistory table creation is now handled via migrations
	}
}
