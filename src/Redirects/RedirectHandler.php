<?php
/**
 * Redirect Handler - Applies 301/302 redirects on template_redirect.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Redirects;

use FP\SEO\Utils\UrlNormalizer;

/**
 * Handles redirect matching and execution.
 */
class RedirectHandler {

	/**
	 * Redirect repository.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param RedirectRepository|null $repository Optional repository instance.
	 */
	public function __construct( ?RedirectRepository $repository = null ) {
		$this->repository = $repository ?? new RedirectRepository();
	}

	/**
	 * Register template_redirect hook with configurable priority.
	 */
	public function register(): void {
		$priority = RedirectsOptions::redirect_priority();
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), $priority );
	}

	/**
	 * Check current request and redirect if match found.
	 */
	public function maybe_redirect(): void {
		if ( ! RedirectsOptions::redirects_enabled() ) {
			return;
		}

		// Skip admin, cron, REST, AJAX
		if ( is_admin() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( '' === $request_uri ) {
			return;
		}

		$path = UrlNormalizer::normalize_path( strtok( $request_uri, '?' ) ?: '/' );

		$redirect = $this->repository->find_by_source( $path );

		if ( ! $redirect ) {
			return;
		}

		$target = $redirect['target_url'];

		// If target is relative, make absolute
		if ( ! str_starts_with( $target, 'http' ) ) {
			$target = home_url( $target );
		}

		// Preserve query string on redirect
		$query = strpos( $request_uri, '?' ) !== false ? substr( $request_uri, strpos( $request_uri, '?' ) ) : '';
		if ( '' !== $query ) {
			$target .= ( strpos( $target, '?' ) !== false ? '&' : '?' ) . ltrim( $query, '?' );
		}

		$this->repository->increment_hits( (int) $redirect['id'] );

		$code = $redirect['redirect_type'] === '302' ? 302 : 301;
		wp_safe_redirect( $target, $code, 'FP SEO Redirect Manager' );
		exit;
	}
}
