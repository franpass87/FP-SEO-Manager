<?php
/**
 * Applica traduzioni italiane per stringhe inglesi del dominio fp-seo-performance
 * quando la locale utente/sito è italiana e il file .mo non fornisce la voce.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\I18n;

use function add_filter;
use function dirname;
use function get_locale;
use function get_user_locale;
use function is_admin;
use function is_readable;
use function strtolower;
use function str_starts_with;

/**
 * Ponte gettext → tabella `languages/en-it-admin-table.php`.
 */
final class ItalianGettextBridge {

	/**
	 * Registra il filtro gettext.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'gettext', array( self::class, 'filter_gettext' ), 20, 3 );
	}

	/**
	 * Sostituisce la stringa se presente in tabella e la MO non ha già tradotto.
	 *
	 * @param string $translation Traduzione corrente (da .mo o uguale a $text).
	 * @param string $text        Testo originale (msgid).
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function filter_gettext( string $translation, string $text, string $domain ): string {
		if ( 'fp-seo-performance' !== $domain || ! self::is_italian_locale() ) {
			return $translation;
		}
		if ( $translation !== $text ) {
			return $translation;
		}
		static $map = null;
		if ( null === $map ) {
			$file = dirname( \FP_SEO_PERFORMANCE_FILE ) . '/languages/en-it-admin-table.php';
			$map  = ( is_readable( $file ) ) ? require $file : array();
		}
		return $map[ $text ] ?? $translation;
	}

	/**
	 * True se l’interfaccia deve usare l’italiano (it_IT, it_CH, …).
	 *
	 * @return bool
	 */
	private static function is_italian_locale(): bool {
		$locale = ( is_admin() && function_exists( 'get_user_locale' ) )
			? get_user_locale()
			: get_locale();

		return str_starts_with( strtolower( $locale ), 'it' );
	}
}
