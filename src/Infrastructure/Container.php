<?php
/**
 * Dependency injection container.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure;

use RuntimeException;

/**
 * Minimal dependency injection container for plugin services.
 */
class Container {

	/**
	 * Holds service bindings or cached instances.
	 *
	 * @var array<class-string, callable|object>
	 */
	private array $bindings = array();

	/**
	 * Service tags for grouping related services.
	 *
	 * @var array<string, array<class-string>>
	 */
	private array $tags = array();

	/**
	 * Tracks services currently being resolved to detect circular dependencies.
	 *
	 * @var array<class-string, bool>
	 */
	private array $resolving = array();

	/**
	 * Registers a binding with the container.
	 *
	 * @param string   $id       Class identifier.
	 * @param callable $concrete Factory callback.
	 */
	public function bind( string $id, callable $concrete ): void {
		// Validate ID is not empty
		if ( empty( $id ) ) {
			throw new RuntimeException( 'Cannot bind service with empty ID' );
		}

		// Validate concrete is callable
		if ( ! is_callable( $concrete ) ) {
			throw new RuntimeException( sprintf( 'Cannot bind service %s: factory is not callable', esc_html( $id ) ) );
		}

		$this->bindings[ $id ] = $concrete;
	}

	/**
	 * Registers a lazy singleton for the given class name.
	 *
	 * Note: If a class is registered multiple times, the last registration wins.
	 * This allows overriding bindings if needed.
	 *
	 * @param string $id Class identifier.
	 * @param callable|null $factory Optional factory function.
	 */
	public function singleton( string $id, ?callable $factory = null ): void {
		// Validate ID is not empty
		if ( empty( $id ) ) {
			throw new RuntimeException( 'Cannot register singleton with empty ID' );
		}

		// Validate factory is callable if provided
		if ( null !== $factory && ! is_callable( $factory ) ) {
			throw new RuntimeException( sprintf( 'Cannot register singleton %s: factory is not callable', esc_html( $id ) ) );
		}

		if ( $factory ) {
			$this->bindings[ $id ] = static function ( Container $container ) use ( $factory, $id ) {
				static $instance = null;

				if ( null === $instance ) {
					try {
						$instance = $factory( $container );
						// Validate instance immediately to prevent caching null values
						if ( ! is_object( $instance ) ) {
							// Reset to null so it can be retried, but throw exception now
							$instance = null;
							$safe_id = function_exists( 'esc_html' ) ? esc_html( $id ) : $id;
							throw new \RuntimeException( 
								sprintf( 'Factory for %s returned non-object', $safe_id )
							);
						}
					} catch ( \Throwable $e ) {
						// Don't cache the error - allow retry on next call
						// The exception will be propagated to the caller
						throw $e;
					}
				}

				return $instance;
			};
		} else {
			$this->bindings[ $id ] = static function ( Container $container ) use ( $id ) {
				static $instance = null;

				if ( null === $instance ) {
					try {
						$instance = $container->resolve( $id );
						// Note: resolve() already validates and throws if not object
						// This check is redundant but provides extra safety
						if ( ! is_object( $instance ) ) {
							$instance = null;
							$safe_id = function_exists( 'esc_html' ) ? esc_html( $id ) : $id;
							throw new \RuntimeException( 
								sprintf( 'Resolved instance for %s is not an object', $safe_id )
							);
						}
					} catch ( \Throwable $e ) {
						// Don't cache the error - allow retry on next call
						// The exception will be propagated to the caller
						throw $e;
					}
				}

				return $instance;
			};
		}
	}

	/**
	 * Retrieves an entry from the container.
	 *
	 * @param string $id Class identifier.
	 *
	 * @return object Resolved instance.
	 * @throws RuntimeException When the class cannot be resolved or circular dependency detected.
	 */
	public function get( string $id ): object {
		// Validate ID is not empty
		if ( empty( $id ) ) {
			throw new RuntimeException( 'Cannot resolve service with empty ID' );
		}

		// Check for circular dependency
		// Note: isset() check is sufficient since we only set boolean true values
		if ( isset( $this->resolving[ $id ] ) ) {
			$safe_id = function_exists( 'esc_html' ) ? esc_html( $id ) : $id;
			throw new RuntimeException( 
				sprintf( 'Circular dependency detected while resolving %s', $safe_id )
			);
		}

		if ( ! isset( $this->bindings[ $id ] ) ) {
			$this->resolving[ $id ] = true;
			try {
				$instance = $this->resolve( $id );
			} finally {
				unset( $this->resolving[ $id ] );
			}
			return $instance;
		}

		$binding = $this->bindings[ $id ];

		if ( is_object( $binding ) && ! is_callable( $binding ) ) {
			return $binding;
		}

		// Ensure binding is callable before invoking
		if ( ! is_callable( $binding ) ) {
			$safe_id = function_exists( 'esc_html' ) ? esc_html( $id ) : $id;
			throw new RuntimeException( 
				sprintf( 'Binding for %s is not callable and not an object instance', $safe_id )
			);
		}

		// Mark as resolving before calling factory
		$this->resolving[ $id ] = true;
		try {
			$instance = $binding( $this );
			
			// Verifica che l'istanza sia valida
			if ( ! is_object( $instance ) ) {
				$safe_id = function_exists( 'esc_html' ) ? esc_html( $id ) : $id;
				throw new \RuntimeException( sprintf( 'Binding for %s returned non-object', $safe_id ) );
			}
			
			return $instance;
		} catch ( \RuntimeException $e ) {
			// Se è un RuntimeException per funzioni WordPress non disponibili, rilancia
			$error_message = $e->getMessage();
			if ( is_string( $error_message ) && strpos( $error_message, 'WordPress functions not available' ) !== false ) {
				throw $e;
			}
			// Per altri errori, rilancia come RuntimeException con più dettagli
			$safe_id = function_exists( 'esc_html' ) ? esc_html( $id ) : $id;
			$safe_message = function_exists( 'esc_html' ) ? esc_html( $e->getMessage() ) : $e->getMessage();
			throw new \RuntimeException( 
				sprintf( 'Failed to resolve %s: %s', $safe_id, $safe_message ), 
				0, 
				$e 
			);
		} catch ( \Throwable $e ) {
			// Cattura anche Error e altri Throwable
			$safe_id = function_exists( 'esc_html' ) ? esc_html( $id ) : $id;
			$safe_message = function_exists( 'esc_html' ) ? esc_html( $e->getMessage() ) : $e->getMessage();
			$safe_file = function_exists( 'esc_html' ) ? esc_html( $e->getFile() ) : $e->getFile();
			throw new \RuntimeException( 
				sprintf( 'Fatal error resolving %s: %s in %s:%d', 
					$safe_id, 
					$safe_message,
					$safe_file,
					$e->getLine()
				), 
				0, 
				$e 
			);
		} finally {
			// Always unset resolving flag
			unset( $this->resolving[ $id ] );
		}
	}

	/**
	 * Tag a service with one or more tags.
	 *
	 * @param string   $tag  Tag name.
	 * @param string ...$ids Service class identifiers.
	 * @return void
	 */
	public function tag( string $tag, string ...$ids ): void {
		// Validate tag is not empty
		if ( empty( $tag ) ) {
			return;
		}

		if ( ! isset( $this->tags[ $tag ] ) ) {
			$this->tags[ $tag ] = array();
		}

		// Ensure tags[tag] is an array before iterating
		if ( ! is_array( $this->tags[ $tag ] ) ) {
			$this->tags[ $tag ] = array();
		}

		foreach ( $ids as $id ) {
			// Validate ID is a non-empty string before adding to tag
			if ( ! is_string( $id ) || empty( $id ) ) {
				continue; // Skip invalid IDs
			}
			if ( ! in_array( $id, $this->tags[ $tag ], true ) ) {
				$this->tags[ $tag ][] = $id;
			}
		}
	}

	/**
	 * Resolve all services tagged with the given tag.
	 *
	 * @param string $tag Tag name.
	 * @return array<object> Array of resolved service instances.
	 */
	public function resolveTagged( string $tag ): array {
		// Validate tag is not empty
		if ( empty( $tag ) ) {
			return array();
		}

		if ( ! isset( $this->tags[ $tag ] ) ) {
			return array();
		}

		// Validate that tags[tag] is an array before iterating
		if ( ! is_array( $this->tags[ $tag ] ) ) {
			return array();
		}

		$services = array();
		foreach ( $this->tags[ $tag ] as $id ) {
			// Validate ID is a non-empty string before attempting resolution
			if ( ! is_string( $id ) || empty( $id ) ) {
				continue; // Skip invalid IDs
			}
			try {
				$services[] = $this->get( $id );
			} catch ( \Throwable $e ) {
				// Skip services that cannot be resolved.
				continue;
			}
		}

		return $services;
	}

	/**
	 * Instantiates a concrete class by name.
	 *
	 * Note: This method only works for classes with no constructor dependencies.
	 * Classes with constructor dependencies must be registered with a factory.
	 *
	 * @param string $id Class identifier.
	 *
	 * @throws RuntimeException When the class cannot be found, is invalid, or has constructor dependencies.
	 *
	 * @return object Instantiated object.
	 */
	private function resolve( string $id ): object {
		// Validate class name format to prevent code injection
		// Allow only alphanumeric, backslash, underscore characters (valid PHP class names)
		if ( ! preg_match( '/^[a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*$/', $id ) ) {
			throw new RuntimeException( sprintf( 'Invalid class name format: %s', esc_html( $id ) ) );
		}

		if ( ! class_exists( $id ) ) {
			$message_id = (string) $id;
			if ( function_exists( 'esc_html' ) ) {
				$message_id = esc_html( $message_id );
			}

			throw new RuntimeException( sprintf( 'Class %s not found', $message_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message.
		}

		try {
			return new $id();
		} catch ( \ArgumentCountError $e ) {
			// Class has required constructor parameters
			throw new RuntimeException( 
				sprintf( 
					'Class %s requires constructor parameters. Please register it with a factory using container->singleton( %s, function( Container $container ) { ... } )', 
					esc_html( $id ),
					esc_html( $id )
				)
			);
		} catch ( \Error $e ) {
			// Catch other fatal errors (e.g., class instantiation errors, type errors)
			$safe_id = esc_html( $id );
			$safe_message = function_exists( 'esc_html' ) ? esc_html( $e->getMessage() ) : $e->getMessage();
			throw new RuntimeException( 
				sprintf( 
					'Failed to instantiate class %s: %s', 
					$safe_id,
					$safe_message
				),
				0,
				$e
			);
		}
	}
}
