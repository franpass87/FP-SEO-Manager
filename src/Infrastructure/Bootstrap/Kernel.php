<?php
/**
 * Plugin kernel - handles bootstrap, autoloading, and early lifecycle.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Bootstrap;

use RuntimeException;

/**
 * Main plugin kernel responsible for bootstrap and lifecycle management.
 */
class Kernel {

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Whether the kernel has been booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @param string $version     Plugin version.
	 */
	public function __construct( string $plugin_file, string $version ) {
		$this->plugin_file = $plugin_file;
		$this->version     = $version;
	}

	/**
	 * Determine if the plugin should load.
	 *
	 * Handles early exit conditions (e.g., media library pages).
	 *
	 * @return bool True if plugin should load, false otherwise.
	 */
	public function should_load(): bool {
		// Only block on pure media library pages, NOT when editing posts
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$php_self = isset( $_SERVER['PHP_SELF'] ) ? $_SERVER['PHP_SELF'] : '';
		$ajax_action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';

		// Check if we're on upload.php (more reliable check)
		$is_upload_page = (
			strpos( $php_self, '/upload.php' ) !== false ||
			strpos( $request_uri, 'upload.php' ) !== false
		);
		
		// Block on upload.php UNLESS it's a single item view (which might be used in post editor context)
		$is_single_item = (
			isset( $_GET['item'] ) ||
			strpos( $request_uri, 'item=' ) !== false
		);

		$is_pure_media_library = (
			// Media library grid view (upload.php without item parameter means grid view)
			( $is_upload_page && ! $is_single_item ) ||
			// Media upload popup (not when inserting into post)
			strpos( $php_self, '/media-new.php' ) !== false ||
			strpos( $request_uri, 'media-new.php' ) !== false ||
			// AJAX calls for media library grid only
			( strpos( $request_uri, 'admin-ajax.php' ) !== false &&
			  in_array( $ajax_action, array( 'query-attachments' ), true )
			)
		);

		return ! $is_pure_media_library;
	}

	/**
	 * Register OpenAI autoloader to prevent double inclusion in junction environments.
	 * Must be called BEFORE Composer's autoloader.
	 *
	 * @return void
	 */
	private function register_openai_autoloader(): void {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		// If class already exists, don't register autoloader
		if ( class_exists( '\\OpenAI\\OpenAI', false ) ) {
			return;
		}

		// Find the OpenAI.php file path
		$plugin_dir = dirname( $this->plugin_file );
		$openai_file = $plugin_dir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'openai-php' . DIRECTORY_SEPARATOR . 'client' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'OpenAI.php';
		
		// Normalize path using realpath to handle junctions
		$real_file = realpath( $openai_file );
		if ( $real_file !== false ) {
			$openai_file = $real_file;
		}

		// Register autoloader with high priority (prepend=true) to run BEFORE Composer's autoloader
		spl_autoload_register( function( $class ) use ( $openai_file ) {
			// Only handle OpenAI\OpenAI class
			if ( $class !== 'OpenAI\\OpenAI' ) {
				return false;
			}

			// If class already exists, don't try to load it
			if ( class_exists( $class, false ) ) {
				return true;
			}

			// Check if file exists and is readable
			if ( ! file_exists( $openai_file ) || ! is_readable( $openai_file ) ) {
				return false;
			}

			// Check if file is already included using realpath
			$included_files = get_included_files();
			foreach ( $included_files as $included_file ) {
				$included_real = realpath( $included_file );
				if ( $included_real !== false && $included_real === $openai_file ) {
					// File already included - class should exist
					return class_exists( $class, false );
				}
			}

			// Load file using require_once (safe even if already included via different path)
			try {
				require_once $openai_file;
				return class_exists( $class, false );
			} catch ( \Throwable $e ) {
				// Silently fail - let other autoloaders try
				return false;
			}
		}, true, true ); // prepend=true, throw=true
	}

	/**
	 * Register autoloader.
	 *
	 * @return void
	 */
	public function register_autoloader(): void {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		// Register OpenAI autoloader FIRST to prevent double inclusion in junction environments
		$this->register_openai_autoloader();
		
		$autoload      = dirname( $this->plugin_file ) . '/vendor/autoload.php';
		$real_autoload = realpath( $autoload );

		if ( $debug ) {
			error_log( '[FP-SEO] Kernel::register_autoloader - Entry, plugin_file: ' . $this->plugin_file );
			error_log( '[FP-SEO] Kernel::register_autoloader - autoload path: ' . $autoload );
			error_log( '[FP-SEO] Kernel::register_autoloader - real_autoload path: ' . ( $real_autoload !== false ? $real_autoload : 'NOT FOUND' ) );
			error_log( '[FP-SEO] Kernel::register_autoloader - is_readable: ' . ( is_readable( $autoload ) ? 'yes' : 'no' ) );
		}

		if ( ! is_readable( $autoload ) ) {
			if ( $debug ) {
				error_log( '[FP-SEO] Kernel::register_autoloader - Autoloader file not readable, using fallback only' );
			}
			// Fallback autoloader still needed even if vendor/autoload.php doesn't exist
			$this->register_fallback_autoloader();
			return;
		}

		if ( $real_autoload === false ) {
			$real_autoload = $autoload;
		}

		// Check if autoloader file has already been included
		// This prevents double inclusion in symlinked environments
		$included_files  = get_included_files();
		$already_included = false;
		
		foreach ( $included_files as $included_file ) {
			$included_real = realpath( $included_file );
			if ( $included_real === false ) {
				$included_real = $included_file;
			}
			// Check if this is our autoloader file (using realpath to handle symlinks)
			if ( $included_real === $real_autoload ) {
				$already_included = true;
				if ( $debug ) {
					error_log( '[FP-SEO] Kernel::register_autoloader - Autoloader already included: ' . $included_file );
				}
				break;
			}
		}
		
		if ( $already_included ) {
			// Autoloader already included - just register fallback autoloader for our namespace
			// Don't include it again to avoid redeclaration errors
			if ( $debug ) {
				$vendor_classes_loaded = class_exists( '\OpenAI\OpenAI', false );
				error_log( '[FP-SEO] Kernel::register_autoloader - Autoloader already included, OpenAI class exists: ' . ( $vendor_classes_loaded ? 'yes' : 'no' ) );
			}
			$this->register_fallback_autoloader();
			return;
		}

		// Check if vendor classes (OpenAI) are already loaded
		// If they are, we don't need to include autoloader again
		$vendor_classes_loaded = class_exists( '\OpenAI\OpenAI', false );

		if ( ! $vendor_classes_loaded ) {
			// Vendor classes not loaded - include autoloader
			if ( $debug ) {
				error_log( '[FP-SEO] Kernel::register_autoloader - Including autoloader from: ' . $real_autoload );
			}
			try {
				require_once $real_autoload;
				if ( $debug ) {
					error_log( '[FP-SEO] Kernel::register_autoloader - Autoloader included successfully' );
				}
			} catch ( \Throwable $e ) {
				if ( $debug ) {
					error_log( '[FP-SEO] Kernel::register_autoloader - Error including autoloader: ' . $e->getMessage() );
				}
				// Try with @ if there's a redeclaration error
				@require_once $real_autoload;
			}
		} elseif ( $debug ) {
			error_log( '[FP-SEO] Kernel::register_autoloader - Vendor classes already loaded, skipping autoloader inclusion' );
		}

		// Always register fallback autoloader for our plugin namespace
		// This ensures our plugin classes are always available even if PSR-4 autoloader fails
		$this->register_fallback_autoloader();
	}

	/**
	 * Register fallback autoloader for plugin namespace.
	 *
	 * @return void
	 */
	private function register_fallback_autoloader(): void {
		// Fallback autoloader for critical classes if PSR-4 doesn't work
		$plugin_dir = dirname( $this->plugin_file );
		spl_autoload_register(
			function ( $class ) use ( $plugin_dir ) {
				// Namespace del plugin
				if ( strpos( $class, 'FP\\SEO\\' ) !== 0 ) {
					return false;
				}

				// Rimuove il namespace base
				$relative_class = substr( $class, strlen( 'FP\\SEO\\' ) );

				// Converte namespace in percorso file
				$file = $plugin_dir . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';

				// Carica il file se esiste
				if ( file_exists( $file ) ) {
					require_once $file;
					return true;
				}

				return false;
			},
			true, // Prepend per avere priorità
			false // Non throw exception, ritorna false
		);
	}

	/**
	 * Register error handler.
	 *
	 * @return void
	 */
	public function register_error_handler(): void {
		// CRITICAL: Register error handler very early to catch fatal errors from other plugins
		// This helps prevent WordPress from completely crashing when other plugins have fatal errors
		register_shutdown_function(
			function () {
				$error = error_get_last();
				if ( $error !== null && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
					// Check if error is from FP-Multilanguage
					if ( strpos( $error['file'], 'FP-Multilanguage' ) !== false ) {
						// Log the error but don't break WordPress completely
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
							error_log( 'FP SEO: Detected fatal error from FP-Multilanguage: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'] );
						}
						// Don't prevent WordPress from showing the error, but log it for debugging
					}
				}
			}
		);
	}

	/**
	 * Initialize the kernel.
	 *
	 * Sets up autoloading and error handling.
	 * Note: Container and Plugin initialization happens separately.
	 *
	 * @return void
	 * @throws RuntimeException If initialization fails.
	 */
	public function init(): void {
		if ( $this->booted ) {
			return;
		}

		// Register autoloader
		$this->register_autoloader();

		// Register error handler
		$this->register_error_handler();

		$this->booted = true;
	}


	/**
	 * Check if kernel has been booted.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}
}

