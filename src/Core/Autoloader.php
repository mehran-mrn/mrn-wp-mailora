<?php
/**
 * Tiny dependency-free PSR-4 autoloader.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
	public static function register(): void {
		spl_autoload_register(
			static function ( string $class_name ): void {
				$prefix = 'MRN\\Mailora\\';
				if ( 0 !== strpos( $class_name, $prefix ) ) {
					return;
				}

				$relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class_name, strlen( $prefix ) ) );
				$file     = MRN_MAILORA_DIR . 'src' . DIRECTORY_SEPARATOR . $relative . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
