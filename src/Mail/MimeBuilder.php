<?php
/**
 * RFC-style raw MIME generator for Gmail and Amazon SES.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

defined( 'ABSPATH' ) || exit;

final class MimeBuilder {
	/** @param array{email:string,name:string} $from */
	public static function build( Message $message, array $from ): string {
		$boundary = 'mailora_' . wp_generate_password( 24, false, false );
		$eol      = "\r\n";
		$lines    = array(
			'MIME-Version: 1.0',
			'Date: ' . gmdate( 'D, d M Y H:i:s O' ),
			'Message-ID: <' . wp_generate_uuid4() . '@' . self::domain( $from['email'] ) . '>',
			'From: ' . self::address( $from ),
			'To: ' . implode( ', ', array_map( array( self::class, 'address' ), $message->recipients() ) ),
			'Subject: =?UTF-8?B?' . base64_encode( $message->subject ) . '?=',
		);
		foreach ( array(
			'cc'       => 'Cc',
			'bcc'      => 'Bcc',
			'reply-to' => 'Reply-To',
		) as $key => $label ) {
			$items = $message->recipients( $key );
			if ( $items ) {
				$lines[] = $label . ': ' . implode( ', ', array_map( array( self::class, 'address' ), $items ) );
			}
		}

		$attachments = $message->attachment_payloads();
		if ( ! $attachments ) {
			$lines[] = 'Content-Type: ' . ( $message->html ? 'text/html' : 'text/plain' ) . '; charset=UTF-8';
			$lines[] = 'Content-Transfer-Encoding: base64';
			$lines[] = '';
			$lines[] = chunk_split( base64_encode( $message->body ), 76, $eol );
			return implode( $eol, $lines );
		}

		$lines[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
		$lines[] = '';
		$lines[] = '--' . $boundary;
		$lines[] = 'Content-Type: ' . ( $message->html ? 'text/html' : 'text/plain' ) . '; charset=UTF-8';
		$lines[] = 'Content-Transfer-Encoding: base64';
		$lines[] = '';
		$lines[] = chunk_split( base64_encode( $message->body ), 76, $eol );
		foreach ( $attachments as $attachment ) {
			$filename = sanitize_file_name( $attachment['filename'] );
			$lines[]  = '--' . $boundary;
			$lines[]  = 'Content-Type: ' . $attachment['type'] . '; name="' . $filename . '"';
			$lines[]  = 'Content-Disposition: attachment; filename="' . $filename . '"';
			$lines[]  = 'Content-Transfer-Encoding: base64';
			$lines[]  = '';
			$lines[]  = chunk_split( $attachment['content'], 76, $eol );
		}
		$lines[] = '--' . $boundary . '--';
		return implode( $eol, $lines );
	}

	/** @param array{email:string,name:string} $address */
	private static function address( array $address ): string {
		return $address['name']
			? '=?UTF-8?B?' . base64_encode( $address['name'] ) . '?= <' . $address['email'] . '>'
			: $address['email'];
	}

	private static function domain( string $email ): string {
		return str_contains( $email, '@' ) ? substr( strrchr( $email, '@' ), 1 ) : 'localhost';
	}
}
