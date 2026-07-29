<?php
/**
 * Normalized email value object.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

defined( 'ABSPATH' ) || exit;

final class Message {
	/** @param array<int, string> $to @param array<string, array<int, string>> $headers @param array<int, string> $attachments */
	public function __construct(
		public array $to,
		public string $subject,
		public string $body,
		public array $headers = array(),
		public array $attachments = array(),
		public bool $html = false
	) {}

	/** @param array<string, mixed> $args */
	public static function from_wp_mail( array $args ): self {
		$to          = is_array( $args['to'] ?? null ) ? $args['to'] : preg_split( '/\s*,\s*/', (string) ( $args['to'] ?? '' ) );
		$raw_headers = $args['headers'] ?? array();
		if ( is_string( $raw_headers ) ) {
			$raw_headers = preg_split( '/\r\n|\r|\n/', $raw_headers );
		}

		$headers = array(
			'cc'       => array(),
			'bcc'      => array(),
			'reply-to' => array(),
			'other'    => array(),
		);
		$html    = false;
		foreach ( (array) $raw_headers as $line ) {
			$line = trim( (string) $line );
			if ( ! str_contains( $line, ':' ) ) {
				continue;
			}
			list( $name, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$key                  = strtolower( $name );
			if ( in_array( $key, array( 'cc', 'bcc', 'reply-to' ), true ) ) {
				$headers[ $key ][] = $value;
			} else {
				$headers['other'][] = $line;
			}
			if ( 'content-type' === $key && str_contains( strtolower( $value ), 'text/html' ) ) {
				$html = true;
			}
		}

		$attachments = $args['attachments'] ?? array();
		if ( is_string( $attachments ) ) {
			$attachments = preg_split( '/\r\n|\r|\n/', $attachments );
		}

		return new self(
			array_values( array_filter( array_map( 'trim', (array) $to ) ) ),
			(string) ( $args['subject'] ?? '' ),
			(string) ( $args['message'] ?? '' ),
			$headers,
			array_values( array_filter( array_map( 'strval', (array) $attachments ) ) ),
			$html
		);
	}

	/** @return array<int, array{email:string,name:string}> */
	public function recipients( string $type = 'to' ): array {
		$values = 'to' === $type ? $this->to : ( $this->headers[ $type ] ?? array() );
		$out    = array();
		foreach ( $values as $value ) {
			if ( preg_match( '/^(.*?)<([^>]+)>$/', $value, $match ) ) {
				$out[] = array(
					'email' => sanitize_email( trim( $match[2] ) ),
					'name'  => trim( $match[1], " \t\n\r\0\x0B\"" ),
				);
			} else {
				$out[] = array(
					'email' => sanitize_email( $value ),
					'name'  => '',
				);
			}
		}
		return array_values( array_filter( $out, static fn( array $item ): bool => is_email( $item['email'] ) !== false ) );
	}

	/** @return array<int, array{filename:string,type:string,content:string}> */
	public function attachment_payloads(): array {
		$out = array();
		foreach ( $this->attachments as $path ) {
			if ( ! is_readable( $path ) || ! is_file( $path ) ) {
				continue;
			}
			$out[] = array(
				'filename' => wp_basename( $path ),
				'type'     => (string) ( wp_check_filetype( $path )['type'] ?? 'application/octet-stream' ),
				'content'  => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment path.
			);
		}
		return $out;
	}
}
