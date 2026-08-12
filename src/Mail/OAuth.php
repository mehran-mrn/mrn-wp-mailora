<?php
/**
 * OAuth 2.0 authorization and refresh for Gmail and Microsoft Graph.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

use MRN\Mailora\Core\Settings;
use MRN\Mailora\Core\I18n;

defined( 'ABSPATH' ) || exit;

final class OAuth {
	public function __construct( private Settings $settings ) {}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_handle_callback' ) );
	}

	public function callback_url(): string {
		return admin_url( 'admin.php?page=mrn-mailora-settings&mailora_oauth=callback' );
	}

	public function authorization_url( string $provider ): string {
		$config = $this->settings->provider( $provider );
		if ( empty( $config['client_id'] ) ) {
			return '';
		}

		$nonce = wp_create_nonce( 'mrn_mailora_oauth_' . $provider );
		$state = $nonce . '.' . wp_generate_password( 24, false, false );
		set_transient( $this->state_key( $state ), $provider, 10 * MINUTE_IN_SECONDS );

		if ( 'gmail' === $provider ) {
			return $this->authorization_endpoint(
				'https://accounts.google.com/o/oauth2/v2/auth',
				array(
					'client_id'     => $config['client_id'],
					'redirect_uri'  => $this->callback_url(),
					'response_type' => 'code',
					'access_type'   => 'offline',
					'prompt'        => 'consent',
					'scope'         => 'https://www.googleapis.com/auth/gmail.send',
					'state'         => $state,
				)
			);
		}

		$tenant = sanitize_text_field( (string) ( $config['tenant'] ?? 'common' ) );
		return $this->authorization_endpoint(
			'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/authorize',
			array(
				'client_id'     => $config['client_id'],
				'redirect_uri'  => $this->callback_url(),
				'response_type' => 'code',
				'response_mode' => 'query',
				'scope'         => 'offline_access https://graph.microsoft.com/Mail.Send',
				'state'         => $state,
			)
		);
	}

	/**
	 * Build an OAuth endpoint while preserving a callback URL that has its own query string.
	 *
	 * add_query_arg() does not reliably encode newly supplied nested URL values, so the
	 * callback's ampersand can otherwise become a top-level OAuth parameter.
	 *
	 * @param array<string, scalar> $query OAuth query arguments.
	 */
	private function authorization_endpoint( string $endpoint, array $query ): string {
		return $endpoint . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	public function maybe_handle_callback(): void {
		$action = sanitize_key( wp_unslash( $_GET['mailora_oauth'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'callback' !== $action || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state  = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
		$stored = $state ? (string) get_transient( $this->state_key( $state ) ) : '';
		if ( $state ) {
			delete_transient( $this->state_key( $state ) );
		}
		if ( ! $stored ) {
			$this->redirect( 'oauth_expired' );
		}
		$provider = sanitize_key( $stored );
		$nonce    = str_contains( $state, '.' ) ? strstr( $state, '.', true ) : '';
		$code     = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'mrn_mailora_oauth_' . $provider ) || ! $code ) {
			$this->redirect( 'oauth_invalid' );
		}

		$tokens = $this->request_tokens( $provider, $code, false );
		if ( is_wp_error( $tokens ) ) {
			$this->redirect( 'oauth_failed', $tokens->get_error_message() );
		}
		$this->store_tokens( $provider, $tokens );
		$this->redirect( 'oauth_connected' );
	}

	public function access_token( string $provider ): string|\WP_Error {
		$config = $this->settings->provider( $provider );
		if ( empty( $config['access_token'] ) ) {
			return new \WP_Error( 'mailora_oauth_missing', I18n::translate( 'The OAuth account is not connected yet.', 'حساب OAuth هنوز متصل نشده است.' ) );
		}
		if ( (int) ( $config['token_expires_at'] ?? 0 ) > time() + 90 ) {
			return (string) $config['access_token'];
		}
		if ( empty( $config['refresh_token'] ) ) {
			return new \WP_Error( 'mailora_oauth_expired', I18n::translate( 'The OAuth session expired. Reconnect the account.', 'نشست OAuth منقضی شده؛ حساب را دوباره متصل کنید.' ) );
		}

		$tokens = $this->request_tokens( $provider, (string) $config['refresh_token'], true );
		if ( is_wp_error( $tokens ) ) {
			return $tokens;
		}
		$this->store_tokens( $provider, $tokens, (string) $config['refresh_token'] );
		return (string) ( $tokens['access_token'] ?? '' );
	}

	/** @return array<string, mixed>|\WP_Error */
	private function request_tokens( string $provider, string $value, bool $refresh ) {
		$config = $this->settings->provider( $provider );
		$body   = array(
			'client_id'     => (string) ( $config['client_id'] ?? '' ),
			'client_secret' => (string) ( $config['client_secret'] ?? '' ),
		);
		if ( $refresh ) {
			$body['grant_type']    = 'refresh_token';
			$body['refresh_token'] = $value;
		} else {
			$body['grant_type']   = 'authorization_code';
			$body['code']         = $value;
			$body['redirect_uri'] = $this->callback_url();
		}

		if ( 'gmail' === $provider ) {
			$url = 'https://oauth2.googleapis.com/token';
		} else {
			$tenant        = sanitize_text_field( (string) ( $config['tenant'] ?? 'common' ) );
			$url           = 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token';
			$body['scope'] = 'offline_access https://graph.microsoft.com/Mail.Send';
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( wp_remote_retrieve_response_code( $response ) >= 300 || empty( $data['access_token'] ) ) {
			return new \WP_Error( 'mailora_oauth_token', sanitize_text_field( (string) ( $data['error_description'] ?? $data['error'] ?? I18n::translate( 'Could not obtain an OAuth token.', 'دریافت توکن OAuth ناموفق بود.' ) ) ) );
		}
		return $data;
	}

	/** @param array<string, mixed> $tokens */
	private function store_tokens( string $provider, array $tokens, string $fallback_refresh = '' ): void {
		$this->settings->update_provider(
			$provider,
			array(
				'access_token'     => (string) $tokens['access_token'],
				'refresh_token'    => (string) ( $tokens['refresh_token'] ?? $fallback_refresh ),
				'token_expires_at' => time() + max( 60, absint( $tokens['expires_in'] ?? 3600 ) ),
			)
		);
	}

	private function redirect( string $status, string $message = '' ): void {
		$url = add_query_arg(
			array_filter(
				array(
					'page'           => 'mrn-mailora-settings',
					'mailora_notice' => $status,
					'detail'         => $message,
				)
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	private function state_key( string $state ): string {
		return 'mrn_mailora_oauth_' . get_current_user_id() . '_' . hash( 'sha256', $state );
	}
}
