<?php
/**
 * Service for parsing AI responses.
 *
 * @package FP\SEO\AI\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\AI\Services;

/**
 * Parses AI responses from various optimization endpoints.
 */
class AIResponseParser {
	/**
	 * Parse AI response JSON.
	 *
	 * @param string $response AI response string.
	 * @return array<string, mixed> Parsed response data or error array.
	 */
	public function parse( string $response ): array {
		$data = json_decode( $response, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			return array( 'error' => 'Invalid JSON response from AI' );
		}

		return $data;
	}
}








