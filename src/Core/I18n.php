<?php
/**
 * Lightweight bundled English and Persian localization helpers.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Core;

defined( 'ABSPATH' ) || exit;

final class I18n {
	/**
	 * Translate an English source string, with a bundled Persian fallback.
	 */
	public static function translate( string $english, string $persian ): string {
		$translated = __( $english, 'mrn-mailora' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- English source strings are supplied by callers with bundled Persian fallbacks.
		if ( $translated !== $english ) {
			return $translated;
		}

		return self::is_persian() ? $persian : $english;
	}

	/**
	 * Whether the current request uses a Persian locale.
	 */
	public static function is_persian(): bool {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		return str_starts_with( strtolower( str_replace( '-', '_', $locale ) ), 'fa_' ) || 'fa' === strtolower( $locale );
	}

	/**
	 * Direction for the localized Mailora interface.
	 */
	public static function direction(): string {
		return self::is_persian() ? 'rtl' : 'ltr';
	}
}
