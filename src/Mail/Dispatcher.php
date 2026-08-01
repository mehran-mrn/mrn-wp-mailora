<?php
/**
 * WordPress mail pipeline integration.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

use MRN\Mailora\Core\Settings;
use MRN\Mailora\Core\I18n;
use MRN\Mailora\Infrastructure\LogRepository;

defined( 'ABSPATH' ) || exit;

final class Dispatcher {
	private ?Result $last_result = null;
	private float $started_at    = 0.0;

	public function __construct(
		private Settings $settings,
		private LogRepository $logs,
		private ProviderRegistry $registry
	) {}

	public function register(): void {
		add_filter( 'pre_wp_mail', array( $this, 'intercept' ), 10, 2 );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 999 );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 999 );
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 999 );
		add_action( 'wp_mail_succeeded', array( $this, 'on_native_success' ) );
		add_action( 'wp_mail_failed', array( $this, 'on_native_failure' ) );
	}

	/** @param null|bool $short_circuit @param array<string, mixed> $atts */
	public function intercept( $short_circuit, array $atts ) {
		if ( null !== $short_circuit || ! $this->registry->is_api() ) {
			$this->started_at = microtime( true );
			return $short_circuit;
		}

		$message           = Message::from_wp_mail( $atts );
		$started           = microtime( true );
		$result            = $this->registry->make()->send( $message );
		$duration          = (int) round( ( microtime( true ) - $started ) * 1000 );
		$this->last_result = $result;
		$this->logs->record( $message, $this->settings->provider_id(), $result, $duration );

		if ( $result->success ) {
			do_action( 'mrn_mailora_sent', $message, $result );
			return true;
		}
		do_action( 'mrn_mailora_failed', $message, $result );
		return false;
	}

	public function filter_from_email( string $email ): string {
		return $this->settings->get( 'force_from_email', true )
			? sanitize_email( (string) $this->settings->get( 'from_email', $email ) )
			: $email;
	}

	public function filter_from_name( string $name ): string {
		return $this->settings->get( 'force_from_name', true )
			? sanitize_text_field( (string) $this->settings->get( 'from_name', $name ) )
			: $name;
	}

	public function configure_phpmailer( \PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
		if ( 'smtp' !== $this->settings->provider_id() ) {
			return;
		}

		$config = $this->settings->provider( 'smtp' );
		$phpmailer->isSMTP();
		$phpmailer->Host        = (string) ( $config['host'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port        = absint( $config['port'] ?? 587 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAuth    = ! empty( $config['auth'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Username    = (string) ( $config['username'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Password    = (string) ( $config['password'] ?? '' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAutoTLS = ! isset( $config['auto_tls'] ) || ! empty( $config['auto_tls'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$encryption             = (string) ( $config['encryption'] ?? 'tls' );
		$phpmailer->SMTPSecure  = in_array( $encryption, array( 'ssl', 'tls' ), true ) ? $encryption : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Timeout     = 20; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( $this->settings->get( 'return_path', false ) ) {
			$phpmailer->Sender = sanitize_email( (string) $this->settings->get( 'from_email', '' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}

	/** @param array<string, mixed> $mail_data */
	public function on_native_success( array $mail_data ): void {
		if ( $this->registry->is_api() ) {
			return;
		}
		$message           = Message::from_wp_mail( $mail_data );
		$result            = Result::success();
		$this->last_result = $result;
		$this->logs->record( $message, $this->settings->provider_id(), $result, $this->elapsed() );
	}

	public function on_native_failure( \WP_Error $error ): void {
		if ( $this->registry->is_api() ) {
			return;
		}
		$data              = $error->get_error_data();
		$message           = Message::from_wp_mail( is_array( $data ) ? $data : array() );
		$result            = Result::failure( $error->get_error_message(), array( 'code' => $error->get_error_code() ) );
		$this->last_result = $result;
		$this->logs->record( $message, $this->settings->provider_id(), $result, $this->elapsed() );
	}

	public function send_test( string $to ): Result {
		$this->last_result = null;
		$site              = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$body              = '<div dir="' . esc_attr( I18n::direction() ) . '" style="font-family:Tahoma,Arial,sans-serif;max-width:620px;margin:auto;padding:34px;border:1px solid #e7e5f4;border-radius:20px">'
			. '<h2 style="color:#312e81">' . esc_html( I18n::translate( 'Mailora sent the email successfully ✨', 'Mailora با موفقیت ایمیل را ارسال کرد ✨' ) ) . '</h2>'
			. '<p>' . esc_html( I18n::translate( 'This is a test message from', 'این یک پیام آزمایشی از سایت' ) ) . ' <strong>' . esc_html( $site ) . '</strong>.</p>'
			. '<p style="color:#64748b">' . esc_html( I18n::translate( 'Delivery method:', 'روش ارسال:' ) ) . ' ' . esc_html( $this->settings->provider_id() ) . '<br>' . esc_html( I18n::translate( 'Time:', 'زمان:' ) ) . ' ' . esc_html( wp_date( 'Y/m/d H:i:s' ) ) . '</p></div>';
		$ok                = wp_mail( $to, I18n::translate( 'MRN Mailora delivery test — ', 'آزمایش ارسال MRN Mailora — ' ) . $site, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		if ( $this->last_result ) {
			return $this->last_result;
		}
		return $ok ? Result::success() : Result::failure( I18n::translate( 'WordPress reported a failure. Check the error log.', 'وردپرس نتیجه ناموفق برگرداند. گزارش خطا را بررسی کنید.' ) );
	}

	private function elapsed(): int {
		return $this->started_at > 0 ? (int) round( ( microtime( true ) - $this->started_at ) * 1000 ) : 0;
	}
}
