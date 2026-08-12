<?php
/**
 * Mail provider catalog and factory.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

use MRN\Mailora\Core\Settings;
use MRN\Mailora\Core\I18n;

defined( 'ABSPATH' ) || exit;

final class ProviderRegistry {
	public function __construct(
		private Settings $settings,
		private OAuth $oauth
	) {}

	/** @return array<string, array<string, mixed>> */
	public function definitions(): array {
		$providers = array(
			'native'     => array(
				'name'        => I18n::translate( 'WordPress Default', 'ارسال پیش‌فرض وردپرس' ),
				'type'        => 'local',
				'icon'        => 'dashicons-wordpress',
				'description' => I18n::translate( 'Uses wp_mail and your hosting configuration directly.', 'استفاده مستقیم از wp_mail و تنظیمات میزبان.' ),
			),
			'smtp'       => array(
				'name'        => I18n::translate( 'Custom SMTP', 'SMTP اختصاصی' ),
				'type'        => 'smtp',
				'icon'        => 'dashicons-admin-network',
				'description' => I18n::translate( 'Works with any SMTP service using TLS, SSL, and authentication.', 'سازگار با هر سرویس SMTP با TLS، SSL و احراز هویت.' ),
			),
			'sendgrid'   => array(
				'name'        => 'SendGrid',
				'type'        => 'api',
				'icon'        => 'dashicons-cloud',
				'description' => I18n::translate( 'Fast delivery through the SendGrid v3 API.', 'ارسال سریع از API نسخه ۳ سندگرید.' ),
			),
			'brevo'      => array(
				'name'        => 'Brevo',
				'type'        => 'api',
				'icon'        => 'dashicons-email-alt',
				'description' => I18n::translate( 'Brevo (Sendinblue) transactional API.', 'API تراکنشی Brevo (Sendinblue).' ),
			),
			'mailgun'    => array(
				'name'        => 'Mailgun',
				'type'        => 'api',
				'icon'        => 'dashicons-performance',
				'description' => I18n::translate( 'Transactional delivery from the US or EU region.', 'ارسال تراکنشی از منطقه US یا EU.' ),
			),
			'postmark'   => array(
				'name'        => 'Postmark',
				'type'        => 'api',
				'icon'        => 'dashicons-marker',
				'description' => I18n::translate( 'Transactional delivery using a Server Token.', 'ارسال تراکنشی با Server Token.' ),
			),
			'resend'     => array(
				'name'        => 'Resend',
				'type'        => 'api',
				'icon'        => 'dashicons-arrow-right-alt',
				'description' => I18n::translate( 'Modern Resend API with attachment support.', 'API مدرن Resend با پشتیبانی پیوست.' ),
			),
			'mailersend' => array(
				'name'        => 'MailerSend',
				'type'        => 'api',
				'icon'        => 'dashicons-paperclip',
				'description' => I18n::translate( 'MailerSend transactional email API.', 'API ایمیل تراکنشی MailerSend.' ),
			),
			'smtp2go'    => array(
				'name'        => 'SMTP2GO API',
				'type'        => 'api',
				'icon'        => 'dashicons-migrate',
				'description' => I18n::translate( 'Direct delivery through the v3 API.', 'ارسال مستقیم از API نسخه ۳.' ),
			),
			'gmail'      => array(
				'name'        => 'Google / Gmail',
				'type'        => 'oauth',
				'icon'        => 'dashicons-google',
				'description' => I18n::translate( 'OAuth 2.0 and Gmail API without storing the account password.', 'OAuth 2.0 و Gmail API؛ بدون ذخیره رمز حساب.' ),
			),
			'microsoft'  => array(
				'name'        => 'Microsoft 365',
				'type'        => 'oauth',
				'icon'        => 'dashicons-windows',
				'description' => I18n::translate( 'Microsoft Graph for Outlook and Microsoft 365.', 'Microsoft Graph برای Outlook و Microsoft 365.' ),
			),
			'ses'        => array(
				'name'        => 'Amazon SES',
				'type'        => 'api',
				'icon'        => 'dashicons-amazon',
				'description' => I18n::translate( 'AWS Signature V4 with the SES API v2.', 'امضای AWS Signature V4 و SES API v2.' ),
			),
		);
		return (array) apply_filters( 'mrn_mailora_providers', $providers );
	}

	public function is_api( ?string $provider = null ): bool {
		$id = $provider ?? $this->settings->provider_id();
		return ! in_array( $id, array( 'native', 'smtp' ), true );
	}

	public function make( ?string $provider = null ): MailerInterface {
		$id = sanitize_key( $provider ?? $this->settings->provider_id() );
		return new ApiMailer( $id, $this->settings, $this->oauth );
	}
}
