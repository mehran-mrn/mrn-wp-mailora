<?php
/**
 * Authenticated encryption for provider credentials.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Core;

defined( 'ABSPATH' ) || exit;

final class SecretVault {
	private const PREFIX = 'mailora:v1:';

	public function encrypt( string $plain ): string {
		if ( '' === $plain || str_starts_with( $plain, self::PREFIX ) ) {
			return $plain;
		}

		$key = $this->key();
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $key );
			return self::PREFIX . 's:' . base64_encode( $nonce . $cipher );
		}

		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			return '';
		}
		return self::PREFIX . 'o:' . base64_encode( $iv . $tag . $cipher );
	}

	public function decrypt( string $stored ): string {
		if ( ! str_starts_with( $stored, self::PREFIX ) ) {
			return $stored;
		}

		$payload = substr( $stored, strlen( self::PREFIX ) );
		$driver  = substr( $payload, 0, 1 );
		$raw     = base64_decode( substr( $payload, 2 ), true );
		if ( false === $raw ) {
			return '';
		}

		if ( 's' === $driver && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $this->key() );
			return false === $plain ? '' : $plain;
		}

		if ( 'o' === $driver ) {
			$plain = openssl_decrypt(
				substr( $raw, 28 ),
				'aes-256-gcm',
				$this->key(),
				OPENSSL_RAW_DATA,
				substr( $raw, 0, 12 ),
				substr( $raw, 12, 16 )
			);
			return false === $plain ? '' : $plain;
		}

		return '';
	}

	public function mask( string $stored ): string {
		$value = $this->decrypt( $stored );
		if ( '' === $value ) {
			return '';
		}
		return str_repeat( '•', min( 12, max( 6, strlen( $value ) - 4 ) ) ) . substr( $value, -4 );
	}

	private function key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) . '|mrn-mailora|vault', true );
	}
}
