<?php
/**
 * Abstract metabox base class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Metaboxes;

use FP\SEO\Infrastructure\Contracts\HookManagerInterface;

/**
 * Abstract base class for admin metaboxes.
 *
 * Provides common functionality for all metaboxes.
 */
abstract class AbstractMetabox {

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface|null
	 */
	protected ?HookManagerInterface $hook_manager = null;

	/**
	 * Metabox ID.
	 *
	 * @var string
	 */
	protected string $id;

	/**
	 * Metabox title.
	 *
	 * @var string
	 */
	protected string $title;

	/**
	 * Post types where metabox should appear.
	 *
	 * @var array<string>
	 */
	protected array $post_types;

	/**
	 * Metabox context (normal, side, advanced).
	 *
	 * @var string
	 */
	protected string $context = 'normal';

	/**
	 * Metabox priority (high, core, default, low).
	 *
	 * @var string
	 */
	protected string $priority = 'default';

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface|null $hook_manager Optional hook manager instance.
	 */
	public function __construct( ?HookManagerInterface $hook_manager = null ) {
		$this->hook_manager = $hook_manager;
		$this->init();
	}

	/**
	 * Initialize metabox properties.
	 *
	 * Subclasses should override to set id, title, post_types, etc.
	 *
	 * @return void
	 */
	abstract protected function init(): void;

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( $this->post_types as $post_type ) {
			$hook = 'add_meta_boxes_' . $post_type;
			$this->add_action( $hook, array( $this, 'add_metabox' ), 10, 0 );
		}
	}

	/**
	 * Add the metabox to WordPress.
	 *
	 * @return void
	 */
	public function add_metabox(): void {
		add_meta_box(
			$this->id,
			$this->title,
			array( $this, 'render' ),
			$this->post_types,
			$this->context,
			$this->priority
		);
	}

	/**
	 * Render the metabox content.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	abstract public function render( \WP_Post $post ): void;

	/**
	 * Save metabox data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	abstract public function save( int $post_id ): void;

	/**
	 * Register a WordPress action hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	protected function add_action( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		if ( $this->hook_manager ) {
			$this->hook_manager->add_action( $hook, $callback, $priority, $args );
		} else {
			add_action( $hook, $callback, $priority, $args );
		}
	}

	/**
	 * Register a WordPress filter hook.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	protected function add_filter( string $hook, callable $callback, int $priority = 10, int $args = 1 ): void {
		if ( $this->hook_manager ) {
			$this->hook_manager->add_filter( $hook, $callback, $priority, $args );
		} else {
			add_filter( $hook, $callback, $priority, $args );
		}
	}
}
