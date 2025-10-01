<?php
/**
 * Dependency injection container.
 *
 * @package FP\SEO
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
	 * Registers a binding with the container.
	 *
	 * @param string   $id       Class identifier.
	 * @param callable $concrete Factory callback.
	 */
	public function bind( string $id, callable $concrete ): void {
		$this->bindings[ $id ] = $concrete;
	}

	/**
	 * Registers a lazy singleton for the given class name.
	 *
	 * @param string $id Class identifier.
	 */
	public function singleton( string $id ): void {
		$this->bindings[ $id ] = static function ( Container $container ) use ( $id ) {
			static $instance = null;

			if ( null === $instance ) {
				$instance = $container->resolve( $id );
			}

			return $instance;
		};
	}

	/**
	 * Retrieves an entry from the container.
	 *
	 * @param string $id Class identifier.
	 *
	 * @return object Resolved instance.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->bindings[ $id ] ) ) {
			return $this->resolve( $id );
		}

		$binding = $this->bindings[ $id ];

		if ( is_object( $binding ) && ! is_callable( $binding ) ) {
			return $binding;
		}

		return $binding( $this );
	}

	/**
	 * Instantiates a concrete class by name.
	 *
	 * @param string $id Class identifier.
	 *
	 * @throws RuntimeException When the class cannot be found.
	 *
	 * @return object Instantiated object.
	 */
	private function resolve( string $id ): object {
		if ( ! class_exists( $id ) ) {
			$message_id = (string) $id;
			if ( function_exists( 'esc_html' ) ) {
				$message_id = esc_html( $message_id );
			}

			throw new RuntimeException( sprintf( 'Class %s not found', $message_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message.
		}

		return new $id();
	}
}
