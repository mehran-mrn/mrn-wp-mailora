<?php
/**
 * HTTP API transports.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

use MRN\Mailora\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class ApiMailer implements MailerInterface {
	public function __construct(
		private string $provider,
		private Settings $settings,
		private OAuth $oauth
	) {}

	public function id(): string {
		return $this->provider;
	}

	public function send( Message $message ): Result {
		if ( ! $message->recipients() ) {
			return Result::failure( 'هیچ گیرنده معتبر ایمیلی پیدا نشد.' );
		}

		$from = array(
			'email' => sanitize_email( (string) $this->settings->get( 'from_email', get_option( 'admin_email' ) ) ),
			'name'  => sanitize_text_field( (string) $this->settings->get( 'from_name', get_bloginfo( 'name' ) ) ),
		);
		if ( ! is_email( $from['email'] ) ) {
			return Result::failure( 'نشانی فرستنده معتبر نیست.' );
		}

		try {
			return match ( $this->provider ) {
				'sendgrid'   => $this->send_sendgrid( $message, $from ),
				'brevo'      => $this->send_brevo( $message, $from ),
				'mailgun'    => $this->send_mailgun( $message, $from ),
				'postmark'   => $this->send_postmark( $message, $from ),
				'resend'     => $this->send_resend( $message, $from ),
				'mailersend' => $this->send_mailersend( $message, $from ),
				'smtp2go'    => $this->send_smtp2go( $message, $from ),
				'gmail'      => $this->send_gmail( $message, $from ),
				'microsoft'  => $this->send_microsoft( $message, $from ),
				'ses'        => $this->send_ses( $message, $from ),
				default      => Result::failure( 'ارسال‌گر انتخاب‌شده پشتیبانی نمی‌شود.' ),
			};
		} catch ( \Throwable $error ) {
			return Result::failure( $error->getMessage(), array( 'exception' => get_class( $error ) ) );
		}
	}

	/** @param array{email:string,name:string} $from */
	private function send_sendgrid( Message $message, array $from ): Result {
		$config  = $this->settings->provider( 'sendgrid' );
		$payload = array(
			'personalizations' => array(
				array_filter(
					array(
						'to'  => $message->recipients(),
						'cc'  => $message->recipients( 'cc' ),
						'bcc' => $message->recipients( 'bcc' ),
					)
				),
			),
			'from'             => $from,
			'subject'          => $message->subject,
			'content'          => array(
				array(
					'type'  => $message->html ? 'text/html' : 'text/plain',
					'value' => $message->body,
				),
			),
		);
		$reply   = $message->recipients( 'reply-to' );
		if ( $reply ) {
			$payload['reply_to'] = $reply[0];
		}
		$attachments = $message->attachment_payloads();
		if ( $attachments ) {
			$payload['attachments'] = array_map(
				static fn( array $item ): array => array(
					'content'     => $item['content'],
					'filename'    => $item['filename'],
					'type'        => $item['type'],
					'disposition' => 'attachment',
				),
				$attachments
			);
		}
		return $this->json_request( 'https://api.sendgrid.com/v3/mail/send', $payload, array( 'Authorization' => 'Bearer ' . ( $config['api_key'] ?? '' ) ), array( 200, 202 ) );
	}

	/** @param array{email:string,name:string} $from */
	private function send_brevo( Message $message, array $from ): Result {
		$config      = $this->settings->provider( 'brevo' );
		$payload     = array(
			'sender'                                       => $from,
			'to'                                           => $message->recipients(),
			'cc'                                           => $message->recipients( 'cc' ),
			'bcc'                                          => $message->recipients( 'bcc' ),
			'subject'                                      => $message->subject,
			$message->html ? 'htmlContent' : 'textContent' => $message->body,
		);
		$attachments = $message->attachment_payloads();
		if ( $attachments ) {
			$payload['attachment'] = array_map(
				static fn( array $item ): array => array(
					'content' => $item['content'],
					'name'    => $item['filename'],
				),
				$attachments
			);
		}
		return $this->json_request( 'https://api.brevo.com/v3/smtp/email', $payload, array( 'api-key' => (string) ( $config['api_key'] ?? '' ) ), array( 201 ) );
	}

	/** @param array{email:string,name:string} $from */
	private function send_mailgun( Message $message, array $from ): Result {
		$config = $this->settings->provider( 'mailgun' );
		$domain = sanitize_text_field( (string) ( $config['domain'] ?? '' ) );
		$base   = 'eu' === ( $config['region'] ?? 'us' ) ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net';
		$body   = array(
			'from'                           => $this->format_address( $from ),
			'to'                             => implode( ',', array_map( array( $this, 'format_address' ), $message->recipients() ) ),
			'subject'                        => $message->subject,
			$message->html ? 'html' : 'text' => $message->body,
		);
		foreach ( array( 'cc', 'bcc', 'reply-to' ) as $field ) {
			$items = $message->recipients( $field );
			if ( $items ) {
				$body[ 'reply-to' === $field ? 'h:Reply-To' : $field ] = implode( ',', array_map( array( $this, 'format_address' ), $items ) );
			}
		}
		return $this->request(
			$base . '/v3/' . rawurlencode( $domain ) . '/messages',
			array(
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( 'api:' . ( $config['api_key'] ?? '' ) ) ),
				'body'    => $body,
			),
			array( 200 )
		);
	}

	/** @param array{email:string,name:string} $from */
	private function send_postmark( Message $message, array $from ): Result {
		$config      = $this->settings->provider( 'postmark' );
		$attachments = array_map(
			static fn( array $item ): array => array(
				'Name'        => $item['filename'],
				'Content'     => $item['content'],
				'ContentType' => $item['type'],
			),
			$message->attachment_payloads()
		);
		$payload     = array_filter(
			array(
				'From'                                   => $this->format_address( $from ),
				'To'                                     => implode( ',', array_map( array( $this, 'format_address' ), $message->recipients() ) ),
				'Cc'                                     => implode( ',', array_map( array( $this, 'format_address' ), $message->recipients( 'cc' ) ) ),
				'Bcc'                                    => implode( ',', array_map( array( $this, 'format_address' ), $message->recipients( 'bcc' ) ) ),
				'ReplyTo'                                => implode( ',', array_map( array( $this, 'format_address' ), $message->recipients( 'reply-to' ) ) ),
				'Subject'                                => $message->subject,
				$message->html ? 'HtmlBody' : 'TextBody' => $message->body,
				'Attachments'                            => $attachments,
				'MessageStream'                          => (string) ( $config['stream'] ?? 'outbound' ),
			)
		);
		return $this->json_request( 'https://api.postmarkapp.com/email', $payload, array( 'X-Postmark-Server-Token' => (string) ( $config['api_key'] ?? '' ) ), array( 200 ) );
	}

	/** @param array{email:string,name:string} $from */
	private function send_resend( Message $message, array $from ): Result {
		$config  = $this->settings->provider( 'resend' );
		$payload = array_filter(
			array(
				'from'                           => $this->format_address( $from ),
				'to'                             => array_column( $message->recipients(), 'email' ),
				'cc'                             => array_column( $message->recipients( 'cc' ), 'email' ),
				'bcc'                            => array_column( $message->recipients( 'bcc' ), 'email' ),
				'reply_to'                       => array_column( $message->recipients( 'reply-to' ), 'email' ),
				'subject'                        => $message->subject,
				$message->html ? 'html' : 'text' => $message->body,
				'attachments'                    => array_map(
					static fn( array $item ): array => array(
						'filename' => $item['filename'],
						'content'  => $item['content'],
					),
					$message->attachment_payloads()
				),
			)
		);
		return $this->json_request( 'https://api.resend.com/emails', $payload, array( 'Authorization' => 'Bearer ' . ( $config['api_key'] ?? '' ) ), array( 200 ) );
	}

	/** @param array{email:string,name:string} $from */
	private function send_mailersend( Message $message, array $from ): Result {
		$config  = $this->settings->provider( 'mailersend' );
		$payload = array(
			'from'                           => $from,
			'to'                             => $message->recipients(),
			'cc'                             => $message->recipients( 'cc' ),
			'bcc'                            => $message->recipients( 'bcc' ),
			'subject'                        => $message->subject,
			$message->html ? 'html' : 'text' => $message->body,
		);
		$reply   = $message->recipients( 'reply-to' );
		if ( $reply ) {
			$payload['reply_to'] = $reply[0];
		}
		$attachments = $message->attachment_payloads();
		if ( $attachments ) {
			$payload['attachments'] = array_map(
				static fn( array $item ): array => array(
					'content'     => $item['content'],
					'filename'    => $item['filename'],
					'disposition' => 'attachment',
				),
				$attachments
			);
		}
		return $this->json_request( 'https://api.mailersend.com/v1/email', $payload, array( 'Authorization' => 'Bearer ' . ( $config['api_key'] ?? '' ) ), array( 202 ) );
	}

	/** @param array{email:string,name:string} $from */
	private function send_smtp2go( Message $message, array $from ): Result {
		$config  = $this->settings->provider( 'smtp2go' );
		$payload = array(
			'sender'                                   => $this->format_address( $from ),
			'to'                                       => array_map( array( $this, 'format_address' ), $message->recipients() ),
			'cc'                                       => array_map( array( $this, 'format_address' ), $message->recipients( 'cc' ) ),
			'bcc'                                      => array_map( array( $this, 'format_address' ), $message->recipients( 'bcc' ) ),
			'subject'                                  => $message->subject,
			$message->html ? 'html_body' : 'text_body' => $message->body,
			'attachments'                              => array_map(
				static fn( array $item ): array => array(
					'filename' => $item['filename'],
					'fileblob' => $item['content'],
					'mimetype' => $item['type'],
				),
				$message->attachment_payloads()
			),
		);
		return $this->json_request( 'https://api.smtp2go.com/v3/email/send', $payload, array( 'X-Smtp2go-Api-Key' => (string) ( $config['api_key'] ?? '' ) ), array( 200 ) );
	}

	/** @param array{email:string,name:string} $from */
	private function send_gmail( Message $message, array $from ): Result {
		$token = $this->oauth->access_token( 'gmail' );
		if ( is_wp_error( $token ) ) {
			return Result::failure( $token->get_error_message() );
		}
		$raw = rtrim( strtr( base64_encode( MimeBuilder::build( $message, $from ) ), '+/', '-_' ), '=' );
		return $this->json_request(
			'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
			array( 'raw' => $raw ),
			array( 'Authorization' => 'Bearer ' . $token ),
			array( 200 )
		);
	}

	/** @param array{email:string,name:string} $from */
	private function send_microsoft( Message $message, array $from ): Result {
		$token = $this->oauth->access_token( 'microsoft' );
		if ( is_wp_error( $token ) ) {
			return Result::failure( $token->get_error_message() );
		}
		$convert = static fn( array $item ): array => array(
			'emailAddress' => array_filter(
				array(
					'address' => $item['email'],
					'name'    => $item['name'],
				)
			),
		);
		$payload = array(
			'message'         => array(
				'subject'       => $message->subject,
				'body'          => array(
					'contentType' => $message->html ? 'HTML' : 'Text',
					'content'     => $message->body,
				),
				'toRecipients'  => array_map( $convert, $message->recipients() ),
				'ccRecipients'  => array_map( $convert, $message->recipients( 'cc' ) ),
				'bccRecipients' => array_map( $convert, $message->recipients( 'bcc' ) ),
				'attachments'   => array_map(
					static fn( array $item ): array => array(
						'@odata.type'  => '#microsoft.graph.fileAttachment',
						'name'         => $item['filename'],
						'contentType'  => $item['type'],
						'contentBytes' => $item['content'],
					),
					$message->attachment_payloads()
				),
			),
			'saveToSentItems' => true,
		);
		$reply   = $message->recipients( 'reply-to' );
		if ( $reply ) {
			$payload['message']['replyTo'] = array_map( $convert, $reply );
		}
		return $this->json_request( 'https://graph.microsoft.com/v1.0/me/sendMail', $payload, array( 'Authorization' => 'Bearer ' . $token ), array( 202 ) );
	}

	/** @param array{email:string,name:string} $from */
	private function send_ses( Message $message, array $from ): Result {
		$config  = $this->settings->provider( 'ses' );
		$region  = sanitize_text_field( (string) ( $config['region'] ?? 'us-east-1' ) );
		$host    = 'email.' . $region . '.amazonaws.com';
		$url     = 'https://' . $host . '/v2/email/outbound-emails';
		$raw     = MimeBuilder::build( $message, $from );
		$body    = wp_json_encode(
			array(
				'FromEmailAddress' => $this->format_address( $from ),
				'Destination'      => array(
					'ToAddresses'  => array_column( $message->recipients(), 'email' ),
					'CcAddresses'  => array_column( $message->recipients( 'cc' ), 'email' ),
					'BccAddresses' => array_column( $message->recipients( 'bcc' ), 'email' ),
				),
				'Content'          => array( 'Raw' => array( 'Data' => base64_encode( $raw ) ) ),
			),
			JSON_UNESCAPED_SLASHES
		);
		$headers = $this->aws_headers( $host, $region, (string) $body, $config );
		return $this->request(
			$url,
			array(
				'headers' => $headers,
				'body'    => $body,
			),
			array( 200 )
		);
	}

	/** @param array<string, mixed> $payload @param array<string, string> $headers @param array<int, int> $success */
	private function json_request( string $url, array $payload, array $headers, array $success ): Result {
		$headers['Content-Type'] = 'application/json; charset=utf-8';
		return $this->request(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			),
			$success
		);
	}

	/** @param array<string, mixed> $args @param array<int, int> $success */
	private function request( string $url, array $args, array $success ): Result {
		$args     = wp_parse_args(
			$args,
			array(
				'timeout'     => 30,
				'redirection' => 2,
				'user-agent'  => 'MRN-Mailora/' . MRN_MAILORA_VERSION . '; ' . home_url( '/' ),
			)
		);
		$response = wp_remote_post( esc_url_raw( $url ), $args );
		if ( is_wp_error( $response ) ) {
			return Result::failure( $response->get_error_message(), array( 'code' => $response->get_error_code() ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! in_array( $code, $success, true ) ) {
			$error = $data['message'] ?? $data['error']['message'] ?? $data['errors'][0]['message'] ?? wp_remote_retrieve_response_message( $response );
			return Result::failure( sprintf( 'پاسخ سرویس (%d): %s', $code, sanitize_text_field( (string) $error ) ), array( 'http_code' => $code ) );
		}
		$id = (string) ( $data['id'] ?? $data['messageId'] ?? $data['MessageId'] ?? wp_remote_retrieve_header( $response, 'x-message-id' ) );
		return Result::success( sanitize_text_field( $id ), array( 'http_code' => $code ) );
	}

	/** @param array{email:string,name:string} $address */
	private function format_address( array $address ): string {
		return $address['name'] ? sprintf( '%s <%s>', $address['name'], $address['email'] ) : $address['email'];
	}

	/** @param array<string, mixed> $config @return array<string, string> */
	private function aws_headers( string $host, string $region, string $body, array $config ): array {
		$access  = (string) ( $config['access_key'] ?? '' );
		$secret  = (string) ( $config['secret_key'] ?? '' );
		$token   = (string) ( $config['session_token'] ?? '' );
		$date    = gmdate( 'Ymd' );
		$amz     = gmdate( 'Ymd\THis\Z' );
		$hash    = hash( 'sha256', $body );
		$headers = array(
			'content-type'         => 'application/json',
			'host'                 => $host,
			'x-amz-content-sha256' => $hash,
			'x-amz-date'           => $amz,
		);
		if ( $token ) {
			$headers['x-amz-security-token'] = $token;
		}
		ksort( $headers );
		$canonical_headers = '';
		foreach ( $headers as $key => $value ) {
			$canonical_headers .= strtolower( $key ) . ':' . trim( preg_replace( '/\s+/', ' ', $value ) ) . "\n";
		}
		$signed_headers           = implode( ';', array_keys( $headers ) );
		$canonical_request        = "POST\n/v2/email/outbound-emails\n\n{$canonical_headers}\n{$signed_headers}\n{$hash}";
		$scope                    = $date . '/' . $region . '/ses/aws4_request';
		$string_to_sign           = "AWS4-HMAC-SHA256\n{$amz}\n{$scope}\n" . hash( 'sha256', $canonical_request );
		$date_key                 = hash_hmac( 'sha256', $date, 'AWS4' . $secret, true );
		$region_key               = hash_hmac( 'sha256', $region, $date_key, true );
		$service_key              = hash_hmac( 'sha256', 'ses', $region_key, true );
		$signing_key              = hash_hmac( 'sha256', 'aws4_request', $service_key, true );
		$signature                = hash_hmac( 'sha256', $string_to_sign, $signing_key );
		$headers['Authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $access . '/' . $scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;
		return $headers;
	}
}
