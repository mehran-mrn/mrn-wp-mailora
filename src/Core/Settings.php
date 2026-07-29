<?php
/**
 * Typed settings gateway.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Core;

defined( 'ABSPATH' ) || exit;

final class Settings {
	private const OPTION = 'mrn_mailora_settings';

	private SecretVault $vault;

	/** @var array<string, array<int, string>> */
	private array $secret_fields = array(
		'smtp'       => array( 'password' ),
		'sendgrid'   => array( 'api_key' ),
		'brevo'      => array( 'api_key' ),
		'mailgun'    => array( 'api_key' ),
		'postmark'   => array( 'api_key' ),
		'resend'     => array( 'api_key' ),
		'mailersend' => array( 'api_key' ),
		'smtp2go'    => array( 'api_key' ),
		'gmail'      => array( 'client_secret', 'access_token', 'refresh_token' ),
		'microsoft'  => array( 'client_secret', 'access_token', 'refresh_token' ),
		'ses'        => array( 'secret_key', 'session_token' ),
	);

	public function __construct( ?SecretVault $vault = null ) {
		$this->vault = $vault ?? new SecretVault();
	}

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return array(
			'provider'         => 'native',
			'from_email'       => (string) get_option( 'admin_email', '' ),
			'from_name'        => (string) get_bloginfo( 'name' ),
			'force_from_email' => true,
			'force_from_name'  => true,
			'return_path'      => false,
			'logging'          => true,
			'log_content'      => false,
			'retention_days'   => 30,
			'providers'        => array(),
		);
	}

	/** @return array<string, mixed> */
	public function all(): array {
		$value = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	public function get( string $key, mixed $fallback = null ): mixed {
		$settings = $this->all();
		return $settings[ $key ] ?? $fallback;
	}

	public function provider_id(): string {
		return sanitize_key( (string) $this->get( 'provider', 'native' ) );
	}

	/** @return array<string, mixed> */
	public function provider( ?string $id = null ): array {
		$id        = sanitize_key( $id ?? $this->provider_id() );
		$providers = (array) $this->get( 'providers', array() );
		$config    = isset( $providers[ $id ] ) && is_array( $providers[ $id ] ) ? $providers[ $id ] : array();
		foreach ( $this->secret_fields[ $id ] ?? array() as $field ) {
			if ( ! empty( $config[ $field ] ) ) {
				$config[ $field ] = $this->vault->decrypt( (string) $config[ $field ] );
			}
		}
		return $config;
	}

	public function has_secret( string $provider, string $field ): bool {
		$all = $this->all();
		return ! empty( $all['providers'][ $provider ][ $field ] );
	}

	public function secret_mask( string $provider, string $field ): string {
		$all = $this->all();
		return $this->vault->mask( (string) ( $all['providers'][ $provider ][ $field ] ?? '' ) );
	}

	/**
	 * Sanitize and persist settings. Blank secret fields retain their old values.
	 *
	 * @param array<string, mixed> $input Raw input.
	 */
	public function save( array $input ): void {
		$current  = $this->all();
		$provider = sanitize_key( (string) ( $input['provider'] ?? 'native' ) );
		$allowed  = array( 'native', 'smtp', 'sendgrid', 'brevo', 'mailgun', 'postmark', 'resend', 'mailersend', 'smtp2go', 'gmail', 'microsoft', 'ses' );
		if ( ! in_array( $provider, $allowed, true ) ) {
			$provider = 'native';
		}

		$clean = array(
			'provider'         => $provider,
			'from_email'       => sanitize_email( (string) ( $input['from_email'] ?? '' ) ),
			'from_name'        => sanitize_text_field( (string) ( $input['from_name'] ?? '' ) ),
			'force_from_email' => ! empty( $input['force_from_email'] ),
			'force_from_name'  => ! empty( $input['force_from_name'] ),
			'return_path'      => ! empty( $input['return_path'] ),
			'logging'          => ! empty( $input['logging'] ),
			'log_content'      => ! empty( $input['log_content'] ),
			'retention_days'   => min( 365, max( 1, absint( $input['retention_days'] ?? 30 ) ) ),
			'providers'        => (array) ( $current['providers'] ?? array() ),
		);

		$provider_input                  = isset( $input['providers'][ $provider ] ) && is_array( $input['providers'][ $provider ] )
			? $input['providers'][ $provider ]
			: array();
		$clean['providers'][ $provider ] = $this->sanitize_provider( $provider, $provider_input, (array) ( $clean['providers'][ $provider ] ?? array() ) );

		update_option( self::OPTION, $clean, false );
		do_action( 'mrn_mailora_settings_saved', $clean );
	}

	/**
	 * Merge trusted runtime values such as refreshed OAuth tokens.
	 *
	 * @param array<string, mixed> $values Provider values.
	 */
	public function update_provider( string $provider, array $values ): void {
		$provider                          = sanitize_key( $provider );
		$current                           = $this->all();
		$old                               = (array) ( $current['providers'][ $provider ] ?? array() );
		$current['providers'][ $provider ] = $this->sanitize_provider( $provider, array_merge( $old, $values ), $old );
		update_option( self::OPTION, $current, false );
	}

	/**
	 * @param array<string, mixed> $input Provider values.
	 * @param array<string, mixed> $old Existing provider values.
	 * @return array<string, mixed>
	 */
	private function sanitize_provider( string $provider, array $input, array $old ): array {
		$clean = array();
		foreach ( $input as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( in_array( $key, array( 'port' ), true ) ) {
				$clean[ $key ] = min( 65535, max( 1, absint( $value ) ) );
			} elseif ( in_array( $key, array( 'auto_tls', 'auth' ), true ) ) {
				$clean[ $key ] = ! empty( $value );
			} elseif ( in_array( $key, array( 'endpoint', 'base_url' ), true ) ) {
				$clean[ $key ] = esc_url_raw( (string) $value );
			} elseif ( in_array( $key, array( 'domain', 'host', 'region', 'encryption' ), true ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			} else {
				$clean[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		foreach ( $this->secret_fields[ $provider ] ?? array() as $field ) {
			if ( empty( $input[ $field ] ) ) {
				if ( isset( $old[ $field ] ) ) {
					$clean[ $field ] = $old[ $field ];
				}
			} else {
				$clean[ $field ] = $this->vault->encrypt( (string) $input[ $field ] );
			}
		}

		return $clean;
	}
}
