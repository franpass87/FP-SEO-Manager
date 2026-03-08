<?php
/**
 * API key encryption service using WordPress secrets.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Security;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;

/**
 * Encrypts and decrypts API keys using WordPress secrets.
 */
class ApiKeyEncryption {

	/**
	 * Encryption method.
	 */
	private const ENCRYPTION_METHOD = 'AES-256-CBC';

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get encryption key from WordPress salts.
	 *
	 * @return string Encryption key.
	 */
	private function get_encryption_key(): string {
		// Use WordPress salts for encryption key
		$salt = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );
		
		// Hash to get consistent 32-byte key for AES-256
		return substr( hash( 'sha256', $salt, true ), 0, 32 );
	}

	/**
	 * Get initialization vector.
	 *
	 * @return string IV.
	 */
	private function get_iv(): string {
		// Use WordPress salt for IV (16 bytes for AES-256-CBC)
		$salt = wp_salt( 'logged_in' );
		return substr( hash( 'sha256', $salt, true ), 0, 16 );
	}

	/**
	 * Encrypt API key.
	 *
	 * @param string $api_key Plain API key.
	 * @return string Encrypted API key (base64 encoded).
	 */
	public function encrypt( string $api_key ): string {
		if ( empty( $api_key ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			$this->logger->warning( 'OpenSSL not available, API key stored unencrypted' );
			return base64_encode( $api_key ); // Fallback to base64 encoding
		}

		try {
			$key = $this->get_encryption_key();
			$iv = $this->get_iv();
			
			$encrypted = openssl_encrypt( $api_key, self::ENCRYPTION_METHOD, $key, 0, $iv );
			
			if ( $encrypted === false ) {
				$this->logger->error( 'Failed to encrypt API key', array(
					'openssl_error' => openssl_error_string(),
				) );
				return base64_encode( $api_key ); // Fallback
			}

			return $encrypted;
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Exception encrypting API key', array(
				'error' => $e->getMessage(),
			) );
			return base64_encode( $api_key ); // Fallback
		}
	}

	/**
	 * Decrypt API key.
	 *
	 * @param string $encrypted_api_key Encrypted API key (base64 encoded).
	 * @return string Decrypted API key.
	 */
	public function decrypt( string $encrypted_api_key ): string {
		if ( empty( $encrypted_api_key ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// Try base64 decode as fallback
			$decoded = base64_decode( $encrypted_api_key, true );
			return $decoded !== false ? $decoded : $encrypted_api_key;
		}

		try {
			$key = $this->get_encryption_key();
			$iv = $this->get_iv();
			
			$decrypted = openssl_decrypt( $encrypted_api_key, self::ENCRYPTION_METHOD, $key, 0, $iv );
			
			if ( $decrypted === false ) {
				// Try base64 decode as fallback (for old unencrypted keys)
				$decoded = base64_decode( $encrypted_api_key, true );
				if ( $decoded !== false && strpos( $decoded, 'sk-' ) === 0 ) {
					// Looks like an OpenAI key, return it
					return $decoded;
				}
				$this->logger->warning( 'Failed to decrypt API key, may be unencrypted', array(
					'openssl_error' => openssl_error_string(),
				) );
				return $encrypted_api_key; // Return as-is if decryption fails
			}

			return $decrypted;
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Exception decrypting API key', array(
				'error' => $e->getMessage(),
			) );
			// Try base64 decode as fallback
			$decoded = base64_decode( $encrypted_api_key, true );
			return $decoded !== false ? $decoded : $encrypted_api_key;
		}
	}

	/**
	 * Check if API key is encrypted.
	 *
	 * @param string $api_key API key to check.
	 * @return bool True if encrypted.
	 */
	public function is_encrypted( string $api_key ): bool {
		// Encrypted keys are base64 encoded and don't start with typical API key prefixes
		if ( empty( $api_key ) ) {
			return false;
		}

		// Check if it looks like a plain API key (e.g., OpenAI keys start with "sk-")
		if ( preg_match( '/^sk-[a-zA-Z0-9]+/', $api_key ) ) {
			return false;
		}

		// Check if it's base64 encoded (encrypted keys are base64)
		$decoded = base64_decode( $api_key, true );
		if ( $decoded === false ) {
			return false;
		}

		// If decoded doesn't look like a plain key, it's probably encrypted
		return ! preg_match( '/^sk-[a-zA-Z0-9]+/', $decoded );
	}
}




