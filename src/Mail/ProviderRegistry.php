<?php
/**
 * Mail provider catalog and factory.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

use MRN\Mailora\Core\Settings;

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
				'name'        => 'ارسال پیش‌فرض وردپرس',
				'type'        => 'local',
				'icon'        => 'dashicons-wordpress',
				'description' => 'استفاده مستقیم از wp_mail و تنظیمات میزبان.',
			),
			'smtp'       => array(
				'name'        => 'SMTP اختصاصی',
				'type'        => 'smtp',
				'icon'        => 'dashicons-admin-network',
				'description' => 'سازگار با هر سرویس SMTP با TLS، SSL و احراز هویت.',
			),
			'sendgrid'   => array(
				'name'        => 'SendGrid',
				'type'        => 'api',
				'icon'        => 'dashicons-cloud',
				'description' => 'ارسال سریع از API نسخه ۳ سندگرید.',
			),
			'brevo'      => array(
				'name'        => 'Brevo',
				'type'        => 'api',
				'icon'        => 'dashicons-email-alt',
				'description' => 'API تراکنشی Brevo (Sendinblue).',
			),
			'mailgun'    => array(
				'name'        => 'Mailgun',
				'type'        => 'api',
				'icon'        => 'dashicons-performance',
				'description' => 'ارسال تراکنشی از منطقه US یا EU.',
			),
			'postmark'   => array(
				'name'        => 'Postmark',
				'type'        => 'api',
				'icon'        => 'dashicons-marker',
				'description' => 'ارسال تراکنشی با Server Token.',
			),
			'resend'     => array(
				'name'        => 'Resend',
				'type'        => 'api',
				'icon'        => 'dashicons-arrow-right-alt',
				'description' => 'API مدرن Resend با پشتیبانی پیوست.',
			),
			'mailersend' => array(
				'name'        => 'MailerSend',
				'type'        => 'api',
				'icon'        => 'dashicons-paperclip',
				'description' => 'API ایمیل تراکنشی MailerSend.',
			),
			'smtp2go'    => array(
				'name'        => 'SMTP2GO API',
				'type'        => 'api',
				'icon'        => 'dashicons-migrate',
				'description' => 'ارسال مستقیم از API نسخه ۳.',
			),
			'gmail'      => array(
				'name'        => 'Google / Gmail',
				'type'        => 'oauth',
				'icon'        => 'dashicons-google',
				'description' => 'OAuth 2.0 و Gmail API؛ بدون ذخیره رمز حساب.',
			),
			'microsoft'  => array(
				'name'        => 'Microsoft 365',
				'type'        => 'oauth',
				'icon'        => 'dashicons-windows',
				'description' => 'Microsoft Graph برای Outlook و Microsoft 365.',
			),
			'ses'        => array(
				'name'        => 'Amazon SES',
				'type'        => 'api',
				'icon'        => 'dashicons-amazon',
				'description' => 'امضای AWS Signature V4 و SES API v2.',
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
