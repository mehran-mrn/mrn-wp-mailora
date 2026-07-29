<?php
/**
 * Transport result.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

defined( 'ABSPATH' ) || exit;

final class Result {
	/** @param array<string, mixed> $meta */
	private function __construct(
		public bool $success,
		public string $message = '',
		public string $remote_id = '',
		public array $meta = array()
	) {}

	/** @param array<string, mixed> $meta */
	public static function success( string $remote_id = '', array $meta = array() ): self {
		return new self( true, '', $remote_id, $meta );
	}

	/** @param array<string, mixed> $meta */
	public static function failure( string $message, array $meta = array() ): self {
		return new self( false, $message, '', $meta );
	}
}
