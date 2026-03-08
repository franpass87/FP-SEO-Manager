<?php
/**
 * Comprehensive backend tests for all admin pages.
 *
 * @package FP\SEO\Tests\Backend
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Backend;

use Brain\Monkey;
use FP\SEO\Admin\BulkAuditPage;
use FP\SEO\Admin\Menu;
use FP\SEO\Admin\PerformanceDashboard;
use FP\SEO\Admin\SettingsPage;
use FP\SEO\Admin\TestSuitePage;
use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Comprehensive tests for all admin pages.
 *
 * @covers \FP\SEO\Admin\Menu
 * @covers \FP\SEO\Admin\SettingsPage
 * @covers \FP\SEO\Admin\BulkAuditPage
 * @covers \FP\SEO\Admin\PerformanceDashboard
 * @covers \FP\SEO\Admin\TestSuitePage
 */
final class AdminPagesTest extends TestCase {

	/**
	 * Sets up Brain Monkey stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		when( '__' )->returnArg( 1 );
		when( 'esc_html__' )->returnArg( 1 );
		when( 'esc_html' )->returnArg( 1 );
		when( 'esc_attr' )->returnArg( 1 );
		when( 'esc_url' )->returnArg( 1 );
		when( 'esc_textarea' )->returnArg( 1 );
		when( 'wp_unslash' )->alias( static fn( $value ) => $value );
		when( 'sanitize_text_field' )->alias( static fn( $value ) => (string) $value );
		when( 'sanitize_key' )->alias( static fn( $value ) => is_string( $value ) ? strtolower( $value ) : '' );
		when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://example.com/wp-admin/' . ltrim( $path, '/' ) );
		when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ): string {
				return $url . '?' . http_build_query( $args );
			}
		);
		when( 'wp_json_encode' )->alias(
			static fn( $value ) => json_encode( $value ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		);
		when( 'number_format_i18n' )->alias( static function ( $number ): string {
			return (string) $number;
		} );
		when( 'get_option' )->justReturn( Options::get_defaults() );
		when( 'post_type_supports' )->alias(
			static function ( string $type, string $feature ): bool {
				if ( 'editor' !== $feature ) {
					return false;
				}
				return in_array( $type, array( 'post', 'page' ), true );
			}
		);
	}

	/**
	 * Tears down Brain Monkey state.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		unset( $_POST['fp_seo_perf_import_blob'], $_GET['tab'] );
	}

	// ============================================
	// DASHBOARD PRINCIPALE TESTS
	// ============================================

	/**
	 * Test dashboard rendering.
	 */
	public function test_dashboard_rendering(): void {
		$now = 2_000;

		when( 'current_user_can' )->justReturn( true );
		when( 'esc_html_e' )->alias( static function ( $text ): void {
			echo (string) $text;
		} );
		when( 'get_option' )->justReturn(
			Options::merge_defaults(
				array(
					'general'     => array(
						'enable_analyzer' => true,
						'admin_bar_badge' => true,
					),
					'analysis'    => array(
						'checks' => array(
							'title_length'       => true,
							'meta_description'   => true,
							'h1_presence'        => true,
							'headings_structure' => true,
							'image_alt'          => true,
						),
					),
					'performance' => array(
						'enable_psi'  => true,
						'psi_api_key' => 'abc123',
						'heuristics'  => array(
							'image_alt_coverage' => true,
							'inline_css'         => false,
							'image_count'        => true,
							'heading_depth'      => true,
						),
					),
				)
			)
		);

		when( 'get_post_types' )->justReturn( array( 'post', 'page', 'attachment' ) );
		when( 'wp_count_posts' )->alias( static function ( $type ) {
			$counts = new \stdClass();
			if ( 'post' === $type ) {
				$counts->publish = 12;
			} elseif ( 'page' === $type ) {
				$counts->publish = 8;
			} else {
				$counts->publish = 0;
			}
			return $counts;
		} );

		expect( 'get_posts' )
			->once()
			->andReturn( array( 21 ) );

		expect( 'get_transient' )
			->once()
			->with( BulkAuditPage::CACHE_KEY )
			->andReturn(
				array(
					10 => array(
						'post_id'  => 10,
						'score'    => 82,
						'status'   => 'green',
						'warnings' => 0,
						'updated'  => 1_500,
					),
					20 => array(
						'post_id'  => 20,
						'score'    => 55,
						'status'   => 'red',
						'warnings' => 3,
						'updated'  => 1_800,
					),
				)
			);

		when( 'get_the_title' )->alias( static function ( int $post_id ): string {
			return 'Post ' . $post_id;
		} );
		when( 'get_edit_post_link' )->alias( static function ( int $post_id ): string {
			return 'https://example.com/edit/' . $post_id;
		} );
		when( 'current_time' )->justReturn( $now );
		when( 'human_time_diff' )->alias( static function ( int $from, int $to ): string {
			return '16 mins';
		} );
		when( 'wp_date' )->alias( static function (): string {
			return '1970-01-01 00:25';
		} );

		$menu = new Menu( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		ob_start();
		$menu->render_dashboard();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'SEO Performance Dashboard', $output );
	}

	/**
	 * Test dashboard requires capability.
	 */
	public function test_dashboard_requires_capability(): void {
		when( 'current_user_can' )->justReturn( false );
		expect( 'wp_die' )->once()->andReturnUsing( static function ( $message ): void {
			throw new RuntimeException( (string) $message );
		} );

		$menu = new Menu( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Sorry, you are not allowed to access this page.' );

		$menu->render_dashboard();
	}

	/**
	 * Test dashboard statistics collection.
	 */
	public function test_dashboard_statistics_collection(): void {
		when( 'current_user_can' )->justReturn( true );
		when( 'get_option' )->justReturn( Options::get_defaults() );
		when( 'get_post_types' )->justReturn( array( 'post', 'page' ) );
		when( 'wp_count_posts' )->alias( static function ( $type ) {
			$counts = new \stdClass();
			$counts->publish = 10;
			return $counts;
		} );
		expect( 'get_posts' )->once()->andReturn( array() );
		expect( 'get_transient' )->once()->andReturn( array() );

		$menu = new Menu( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		ob_start();
		$menu->render_dashboard();
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	/**
	 * Test dashboard timestamp formatting.
	 */
	public function test_dashboard_timestamp_formatting(): void {
		when( 'current_user_can' )->justReturn( true );
		when( 'get_option' )->justReturn( Options::get_defaults() );
		when( 'get_post_types' )->justReturn( array( 'post' ) );
		when( 'wp_count_posts' )->alias( static function () {
			$counts = new \stdClass();
			$counts->publish = 0;
			return $counts;
		} );
		expect( 'get_posts' )->once()->andReturn( array() );
		expect( 'get_transient' )->once()->andReturn( array() );
		when( 'human_time_diff' )->alias( static function (): string {
			return '5 mins';
		} );

		$menu = new Menu( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		ob_start();
		$menu->render_dashboard();
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	// ============================================
	// SETTINGS PAGE TESTS
	// ============================================

	/**
	 * Test settings page rendering.
	 */
	public function test_settings_page_rendering(): void {
		when( 'current_user_can' )->justReturn( true );
		when( 'settings_fields' )->alias( static function (): void {
			echo '<input type="hidden" name="option_page" />';
		} );
		when( 'submit_button' )->alias( static function (): void {
			echo '<button type="submit">Save</button>';
		} );
		when( 'settings_errors' )->alias( static function (): void {
			// No errors.
		} );

		$page = new SettingsPage( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		ob_start();
		$page->render();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'FP SEO Performance Settings', $output );
	}

	/**
	 * Test settings page tabs.
	 */
	public function test_settings_page_tabs(): void {
		when( 'current_user_can' )->justReturn( true );
		when( 'settings_fields' )->alias( static function (): void {} );
		when( 'submit_button' )->alias( static function (): void {} );
		when( 'settings_errors' )->alias( static function (): void {} );

		$_GET['tab'] = 'analysis';

		$page = new SettingsPage( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		ob_start();
		$page->render();
		$output = ob_get_clean();

		self::assertIsString( $output );
		self::assertStringContainsString( 'Analysis', $output );
	}

	/**
	 * Test settings import validates nonce.
	 */
	public function test_settings_import_validates_nonce(): void {
		$page = new SettingsPage( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		$import                           = Options::get_defaults();
		$_POST['fp_seo_perf_import_blob'] = wp_json_encode( $import );
		$_GET['tab']                      = 'general';

		when( 'get_option' )->alias( static fn() => $import );
		expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );

		$nonce_checked = false;
		expect( 'check_admin_referer' )
			->once()
			->with( 'fp_seo_perf_import' )
			->andReturnUsing(
				static function () use ( &$nonce_checked ) {
					$nonce_checked = true;
					return true;
				}
			);

		when( 'update_option' )->alias( static function (): void {} );
		when( 'add_settings_error' )->alias( static function (): void {} );

		expect( 'wp_safe_redirect' )
			->once()
			->andThrow( new RuntimeException( 'redirect' ) );

		try {
			$page->handle_import();
			self::fail( 'Expected redirect exception was not thrown.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'redirect', $exception->getMessage() );
		}

		self::assertTrue( $nonce_checked, 'Nonce should be validated before import.' );
	}

	/**
	 * Test settings sanitization.
	 */
	public function test_settings_sanitization(): void {
		when( 'current_user_can' )->justReturn( true );
		when( 'get_option' )->justReturn( Options::get_defaults() );

		$page = new SettingsPage( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		$input = array(
			'general' => array(
				'enable_analyzer' => '1',
			),
		);

		$sanitized = $page->sanitize_options( $input );

		self::assertIsArray( $sanitized );
		self::assertArrayHasKey( 'general', $sanitized );
	}

	// ============================================
	// BULK AUDITOR TESTS
	// ============================================

	/**
	 * Test bulk auditor page rendering.
	 */
	public function test_bulk_auditor_rendering(): void {
		when( 'current_user_can' )->justReturn( true );
		when( 'wp_nonce_field' )->alias( static function (): void {
			echo '<input type="hidden" name="_wpnonce" />';
		} );

		$options = $this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class );
		$options->method( 'get_capability' )->willReturn( 'manage_options' );

		$page = new BulkAuditPage(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$options
		);

		ob_start();
		$page->render();
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	/**
	 * Test bulk auditor AJAX handler.
	 */
	public function test_bulk_auditor_ajax_handler(): void {
		$_POST['nonce'] = 'test-nonce';
		$_POST['post_ids'] = array( '1', '2', '3' );

		expect( 'check_ajax_referer' )
			->once()
			->with( BulkAuditPage::NONCE_ACTION, 'nonce' )
			->andReturn( true );

		expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );

		$options = $this->createMock( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class );
		$options->method( 'get_capability' )->willReturn( 'manage_options' );

		$page = new BulkAuditPage(
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ),
			$options
		);

		// Mock get_post
		when( 'get_post' )->alias( static function ( $id ) {
			$post = new \stdClass();
			$post->ID = (int) $id;
			$post->post_title = 'Test Post ' . $id;
			$post->post_content = 'Content';
			$post->post_type = 'post';
			$post->post_status = 'publish';
			return $post;
		} );

		when( 'get_post_meta' )->justReturn( '' );
		when( 'set_transient' )->alias( static function (): bool {
			return true;
		} );

		expect( 'wp_send_json_success' )->once();

		$page->handle_ajax_analyze();
	}

	// ============================================
	// PERFORMANCE DASHBOARD TESTS
	// ============================================

	/**
	 * Test performance dashboard rendering.
	 */
	public function test_performance_dashboard_rendering(): void {
		$health_checker = $this->createMock( \FP\SEO\Utils\HealthChecker::class );
		$health_checker->method( 'run_health_check' )->willReturn( array() );

		$monitor = $this->createMock( \FP\SEO\Utils\PerformanceMonitor::class );
		$monitor->method( 'get_summary' )->willReturn( array() );

		$db_optimizer = $this->createMock( \FP\SEO\Utils\DatabaseOptimizer::class );
		$db_optimizer->method( 'get_performance_stats' )->willReturn( array() );

		$page = new PerformanceDashboard(
			$health_checker,
			$monitor,
			$db_optimizer,
			null,
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		ob_start();
		$page->render_dashboard();
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	/**
	 * Test performance dashboard health check AJAX.
	 */
	public function test_performance_dashboard_health_check_ajax(): void {
		$_POST['nonce'] = 'test-nonce';

		expect( 'check_ajax_referer' )
			->once()
			->with( 'fp_seo_health_check', 'nonce' )
			->andReturn( true );

		expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );

		$health_checker = $this->createMock( \FP\SEO\Utils\HealthChecker::class );
		$health_checker->method( 'run_health_check' )->willReturn( array( 'status' => 'ok' ) );

		$monitor = $this->createMock( \FP\SEO\Utils\PerformanceMonitor::class );
		$db_optimizer = $this->createMock( \FP\SEO\Utils\DatabaseOptimizer::class );

		$page = new PerformanceDashboard(
			$health_checker,
			$monitor,
			$db_optimizer,
			null,
			$this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class )
		);

		expect( 'wp_send_json_success' )->once();

		$page->ajax_run_health_check();
	}

	// ============================================
	// TEST SUITE PAGE TESTS
	// ============================================

	/**
	 * Test test suite page rendering.
	 */
	public function test_test_suite_page_rendering(): void {
		when( 'current_user_can' )->justReturn( true );

		$page = new TestSuitePage( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		ob_start();
		$page->render_test_page();
		$output = ob_get_clean();

		self::assertIsString( $output );
	}

	/**
	 * Test test suite page requires capability.
	 */
	public function test_test_suite_page_requires_capability(): void {
		when( 'current_user_can' )->justReturn( false );
		expect( 'wp_die' )->once()->andReturnUsing( static function ( $message ): void {
			throw new RuntimeException( (string) $message );
		} );

		$page = new TestSuitePage( $this->createMock( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class ) );

		$this->expectException( RuntimeException::class );

		$page->render_test_page();
	}
}



