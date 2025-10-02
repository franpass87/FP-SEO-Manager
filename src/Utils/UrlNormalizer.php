<?php
/**
 * URL normalization helpers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use function ctype_xdigit;
use function html_entity_decode;
use function is_array;
use function rawurldecode;
use function str_ireplace;
use function str_replace;
use function strpos;
use function trim;
use function strlen;
use function wp_parse_url;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * Provides helpers for normalizing user supplied URLs before outbound requests.
 */
class UrlNormalizer {

        /**
         * Reserved encodings that should remain percent-encoded within query strings.
         *
         * @var string[]
         */
        private const RESERVED_QUERY_ENCODINGS = array(
                '%26', // &
                '%3D', // =
                '%23', // #
                '%3A', // :
                '%2F', // /
                '%3F', // ?
                '%25', // %
                '%2B', // +
        );

        /**
         * Reserved encodings that should remain percent-encoded within URL paths.
         *
         * @var string[]
         */
        private const RESERVED_PATH_ENCODINGS = array(
                '%2F', // /
                '%3F', // ?
                '%23', // #
                '%3D', // =
                '%25', // %
        );

        /**
         * Reserved encodings that should remain percent-encoded within URL fragments.
         *
         * @var string[]
         */
        private const RESERVED_FRAGMENT_ENCODINGS = array(
                '%2F', // /
                '%3F', // ?
                '%23', // #
                '%3D', // =
                '%25', // %
        );

        /**
         * Reserved encodings that should remain percent-encoded within credentials.
         *
         * @var string[]
         */
        private const RESERVED_CREDENTIAL_ENCODINGS = array(
                '%3A', // :
                '%40', // @
                '%2F', // /
                '%3F', // ?
                '%23', // #
                '%25', // %
        );

        /**
         * Decodes HTML entities and safe percent-encodings while preserving reserved characters.
         *
         * @param string $url Raw URL that may be HTML-entity encoded or percent-encoded.
         */
        public static function normalize( string $url ): string {
                $decoded = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

                $parts = wp_parse_url( $decoded );

                if ( ! is_array( $parts ) ) {
                        return rawurldecode( $decoded );
                }

                if ( ! isset( $parts['scheme'] ) && isset( $parts['path'] ) ) {
                        $decoded_once = rawurldecode( $decoded );

                        if ( $decoded_once !== $decoded ) {
                                $reparsed = wp_parse_url( $decoded_once );

                                if ( is_array( $reparsed ) && isset( $reparsed['scheme'] ) ) {
                                        $decoded = $decoded_once;
                                        $parts   = $reparsed;
                                }
                        }
                }

                if ( isset( $parts['user'] ) && '' !== $parts['user'] ) {
                        $parts['user'] = self::decode_component_preserving( (string) $parts['user'], self::RESERVED_CREDENTIAL_ENCODINGS );
                }

                if ( isset( $parts['pass'] ) && '' !== $parts['pass'] ) {
                        $parts['pass'] = self::decode_component_preserving( (string) $parts['pass'], self::RESERVED_CREDENTIAL_ENCODINGS );
                }

                if ( isset( $parts['host'] ) && '' !== $parts['host'] ) {
                        $parts['host'] = self::decode_component_preserving( (string) $parts['host'], array() );
                }

                if ( isset( $parts['path'] ) && '' !== $parts['path'] ) {
                        $parts['path'] = self::decode_component_preserving( (string) $parts['path'], self::RESERVED_PATH_ENCODINGS );
                }

                if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
                        $parts['query'] = self::decode_component_preserving(
                                (string) $parts['query'],
                                self::RESERVED_QUERY_ENCODINGS,
                                array( '%26', '%3D' )
                        );
                }

                if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
                        $parts['fragment'] = self::decode_component_preserving( (string) $parts['fragment'], self::RESERVED_FRAGMENT_ENCODINGS );
                }

                return self::build_url_from_parts( $parts );
        }

        /**
         * Reconstructs a URL string from parse_url parts.
         *
         * @param array<string, mixed> $parts Parsed URL parts.
         */
        private static function build_url_from_parts( array $parts ): string {
                $raw_scheme = isset( $parts['scheme'] ) ? (string) $parts['scheme'] : '';
                $user       = isset( $parts['user'] ) ? (string) $parts['user'] : '';
                $pass       = isset( $parts['pass'] ) ? (string) $parts['pass'] : '';
                $host       = (string) ( $parts['host'] ?? '' );
                $port       = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
                $path       = (string) ( $parts['path'] ?? '' );
                $query      = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';
                $fragment   = isset( $parts['fragment'] ) && '' !== $parts['fragment'] ? '#' . $parts['fragment'] : '';

                $auth = '';
                if ( '' !== $user ) {
                        $auth = $user;
                        if ( '' !== $pass ) {
                                $auth .= ':' . $pass;
                        }
                        $auth .= '@';
                }

                $authority = '';
                if ( '' !== $host || '' !== $auth ) {
                        $authority = $auth . $host . $port;
                }

                $prefix = '';
                if ( '' !== $raw_scheme ) {
                        $prefix = $raw_scheme;

                        if ( '' !== $authority || ( '' !== $path && 0 === strpos( $path, '/' ) ) ) {
                                $prefix .= '://';
                        } else {
                                $prefix .= ':';
                        }
                } elseif ( '' !== $authority ) {
                        $prefix = '//';
                }

                if ( '' === $prefix && '' === $authority ) {
                        return $path . $query . $fragment;
                }

                return $prefix . $authority . $path . $query . $fragment;
        }

        /**
         * Decodes a URL component while preserving a set of reserved encodings.
         *
         * @param string   $component           Raw component value.
         * @param string[] $reserved_encodings Encoded sequences to keep untouched.
         */
        /**
         * Decodes safe percent-encoded sequences while preserving reserved encodings.
         *
         * @param string   $component        Raw component value.
         * @param string[] $reserved_encodings Encoded sequences that must remain percent-encoded.
         * @param string[] $preserve_double Encodings that should not collapse when double-encoded.
         */
        private static function decode_component_preserving( string $component, array $reserved_encodings, array $preserve_double = array() ): string {
                if ( '' === $component ) {
                        return $component;
                }

                $length       = strlen( $component );
                $result       = '';
                $reserved_set = array();
                $preserve_double_set = array();

                foreach ( $reserved_encodings as $encoded ) {
                        $reserved_set[ strtoupper( $encoded ) ] = true;
                }

                foreach ( $preserve_double as $encoded ) {
                        $preserve_double_set[ strtoupper( $encoded ) ] = true;
                }

                $changed = false;

                for ( $i = 0; $i < $length; ) {
                        if (
                                '%' === $component[ $i ]
                                && ( $i + 2 ) < $length
                                && ctype_xdigit( $component[ $i + 1 ] )
                                && ctype_xdigit( $component[ $i + 2 ] )
                        ) {
                                $encoded = '%' . strtoupper( $component[ $i + 1 ] . $component[ $i + 2 ] );

                                if (
                                        '%25' === $encoded
                                        && ( $i + 4 ) < $length
                                        && ctype_xdigit( $component[ $i + 3 ] )
                                        && ctype_xdigit( $component[ $i + 4 ] )
                                ) {
                                        $next_encoded = '%' . strtoupper( $component[ $i + 3 ] . $component[ $i + 4 ] );

                                        if ( isset( $preserve_double_set[ $next_encoded ] ) ) {
                                                $result .= '%25';
                                                $i      += 3;
                                                continue;
                                        }

                                        $result .= $next_encoded;
                                        $i      += 5;
                                        $changed = true;
                                        continue;
                                }

                                if ( isset( $reserved_set[ $encoded ] ) ) {
                                        $result .= $encoded;
                                        $i      += 3;
                                        continue;
                                }

                                $run       = '';
                                $run_start = $i;

                                while (
                                        ( $i + 2 ) < $length
                                        && '%' === $component[ $i ]
                                        && ctype_xdigit( $component[ $i + 1 ] )
                                        && ctype_xdigit( $component[ $i + 2 ] )
                                ) {
                                        $candidate = '%' . strtoupper( $component[ $i + 1 ] . $component[ $i + 2 ] );

                                        if (
                                                '%25' === $candidate
                                                && ( $i + 4 ) < $length
                                                && ctype_xdigit( $component[ $i + 3 ] )
                                                && ctype_xdigit( $component[ $i + 4 ] )
                                        ) {
                                                $next_encoded = '%' . strtoupper( $component[ $i + 3 ] . $component[ $i + 4 ] );

                                                if ( isset( $preserve_double_set[ $next_encoded ] ) ) {
                                                        break;
                                                }

                                                break;
                                        }

                                        if ( isset( $reserved_set[ $candidate ] ) ) {
                                                break;
                                        }

                                        $run .= $candidate;
                                        $i   += 3;
                                }

                                if ( '' === $run ) {
                                        $result .= $component[ $run_start ];
                                        $i       = $run_start + 1;
                                        continue;
                                }

                                $decoded = rawurldecode( $run );

                                if ( $decoded !== $run ) {
                                        $changed = true;
                                }

                                $result .= $decoded;
                                continue;
                        }

                        $result .= $component[ $i ];
                        $i++;
                }

                if ( $changed ) {
                        return self::decode_component_preserving( $result, $reserved_encodings, $preserve_double );
                }

                return $result;
        }
}
