<?php
/**
 * Editor metabox unit tests.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Unit\Editor;

use Brain\Monkey;
use FP\SEO\Editor\Metabox;
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * Metabox behaviour coverage.
 *
 * @covers \FP\SEO\Editor\Metabox
 */
class MetaboxTest extends TestCase {

	/**
	 * Tracks calls to update_post_meta during a test run.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $updated_meta = array();

	/**
	 * Tracks calls to delete_post_meta during a test run.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $deleted_meta = array();

	/**
	 * Prepares Brain Monkey stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->updated_meta = array();
		$this->deleted_meta = array();

                when( 'sanitize_text_field' )->alias(
                        static function ( $value ) {
                                return $value;
                        }
                );
                when( 'esc_url_raw' )->alias(
                        static function ( $value ) {
                                return $value;
                        }
                );

                when( 'wp_unslash' )->alias(
                        static function ( $value ) {
                                return $value;
                        }
		);

		when( 'wp_verify_nonce' )->justReturn( true );
		when( 'current_user_can' )->justReturn( true );

		$updates = &$this->updated_meta;
		when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, string $value ) use ( &$updates ): void {
				$updates[] = array(
					'post_id' => $post_id,
					'key'     => $key,
					'value'   => $value,
				);
			}
		);

		$deletes = &$this->deleted_meta;
		when( 'delete_post_meta' )->alias(
			static function ( int $post_id, string $key ) use ( &$deletes ): void {
				$deletes[] = array(
					'post_id' => $post_id,
					'key'     => $key,
				);
			}
		);
	}

	/**
	 * Cleans up Brain Monkey state.
	 */
        protected function tearDown(): void {
                $_POST = array();
                Monkey\tearDown();
                parent::tearDown();
        }

        /**
         * Ensures register wires hooks without passing parameters to callbacks.
         */
        public function test_register_sets_hooks_without_arguments(): void {
                $metabox     = new Metabox();
                $invocations = array();

                when( 'add_action' )->alias(
                        static function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( &$invocations ): bool {
                                $invocations[] = array( $hook, $callback, $priority, $accepted_args );

                                return true;
                        }
                );

                $metabox->register();

                self::assertCount( 4, $invocations );

                $assert_hook_args = static function ( string $hook ) use ( $invocations ): void {
                        $matches = array_values(
                                array_filter(
                                        $invocations,
                                        static fn( array $call ): bool => $hook === $call[0]
                                )
                        );

                        self::assertNotEmpty( $matches );
                        self::assertSame( 0, $matches[0][3] );
                };

                $assert_hook_args( 'admin_enqueue_scripts' );
                $assert_hook_args( 'add_meta_boxes' );
        }

	/**
	 * Ensures the metabox saves the exclusion flag when checked.
	 */
	public function test_save_meta_sets_exclusion_flag(): void {
		$_POST = array(
			'fp_seo_performance_nonce'   => 'nonce',
			'fp_seo_performance_exclude' => '1',
		);

		$metabox = new Metabox();
		$metabox->save_meta( 123 );

		self::assertCount( 1, $this->updated_meta );
		self::assertSame(
			array(
				'post_id' => 123,
				'key'     => '_fp_seo_performance_exclude',
				'value'   => '1',
			),
			$this->updated_meta[0]
		);
		self::assertSame( array(), $this->deleted_meta );
	}

	/**
	 * Ensures clearing the exclusion checkbox removes stored metadata.
	 */
	public function test_save_meta_clears_exclusion_flag_when_unchecked(): void {
		$_POST = array(
			'fp_seo_performance_nonce' => 'nonce',
		);

		$metabox = new Metabox();
		$metabox->save_meta( 123 );

		self::assertSame( array(), $this->updated_meta );
		self::assertCount( 1, $this->deleted_meta );
		self::assertSame(
			array(
				'post_id' => 123,
				'key'     => '_fp_seo_performance_exclude',
			),
			$this->deleted_meta[0]
		);
	}

	/**
	 * Ensures save handler bails early when nonce missing.
	 */
	public function test_save_meta_requires_nonce(): void {
		$metabox = new Metabox();
		$metabox->save_meta( 123 );

		self::assertSame( array(), $this->updated_meta );
		self::assertSame( array(), $this->deleted_meta );
	}
}
