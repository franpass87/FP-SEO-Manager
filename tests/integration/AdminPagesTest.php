<?php
/**
 * Integration coverage for admin pages.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use Brain\Monkey;
use FP\SEO\Admin\SettingsPage;
use FP\SEO\Utils\Options;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Ensures admin routes perform nonce validation and redirects.
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
	}

	/**
	 * Tears down Brain Monkey state.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
		unset( $_POST['fp_seo_perf_import_blob'], $_GET['tab'] );
	}

	/**
	 * Verifies settings import enforces nonce validation and redirects back.
	 */
	public function test_settings_import_validates_nonce_and_redirects(): void {
		$page = new SettingsPage();

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

		$updated = null;
		when( 'update_option' )->alias(
			static function ( string $option, $value ) use ( &$updated ): void {
				$updated = $value;
			}
		);

		$settings_errors = array();
		when( 'add_settings_error' )->alias(
			static function ( $group, $code, $message, $type = 'error' ) use ( &$settings_errors ): void {
				$settings_errors[] = array(
					'group'   => $group,
					'code'    => $code,
					'message' => $message,
					'type'    => $type,
				);
			}
		);

		expect( 'wp_safe_redirect' )
		->once()
		->with( 'https://example.com/wp-admin/admin.php?page=fp-seo-performance-settings&tab=general' )
		->andThrow( new RuntimeException( 'redirect' ) );

		try {
			$page->handle_import();
			self::fail( 'Expected redirect exception was not thrown.' );
		} catch ( RuntimeException $exception ) {
			self::assertSame( 'redirect', $exception->getMessage() );
		}

		self::assertTrue( $nonce_checked, 'Nonce should be validated before import.' );
		self::assertIsArray( $updated );
		self::assertNotEmpty( $settings_errors );
		self::assertSame( 'fp_seo_perf_import_success', $settings_errors[0]['code'] );
	}
}
