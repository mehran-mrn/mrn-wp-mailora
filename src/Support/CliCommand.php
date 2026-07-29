<?php
/**
 * WP-CLI operations.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Support;

use MRN\Mailora\Core\Settings;
use MRN\Mailora\Infrastructure\LogRepository;
use MRN\Mailora\Mail\Dispatcher;

defined( 'ABSPATH' ) || exit;

final class CliCommand {
	public function __construct(
		private Dispatcher $dispatcher,
		private Settings $settings,
		private LogRepository $logs
	) {}

	/**
	 * Show the active Mailora configuration.
	 */
	public function status(): void {
		$stats = $this->logs->stats();
		\WP_CLI\Utils\format_items(
			'table',
			array(
				array(
					'key'   => 'Version',
					'value' => MRN_MAILORA_VERSION,
				),
				array(
					'key'   => 'Transport',
					'value' => $this->settings->provider_id(),
				),
				array(
					'key'   => 'From',
					'value' => $this->settings->get( 'from_email' ),
				),
				array(
					'key'   => 'Logging',
					'value' => $this->settings->get( 'logging' ) ? 'enabled' : 'disabled',
				),
				array(
					'key'   => '30-day sent',
					'value' => $stats['sent'],
				),
				array(
					'key'   => '30-day failed',
					'value' => $stats['failed'],
				),
			),
			array( 'key', 'value' )
		);
	}

	/**
	 * Send a test message.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Recipient email address.
	 *
	 * @param array<int, string> $args Positional arguments.
	 */
	public function test( array $args ): void {
		$email = sanitize_email( $args[0] ?? '' );
		if ( ! is_email( $email ) ) {
			\WP_CLI::error( 'A valid recipient email is required.' );
		}
		$result = $this->dispatcher->send_test( $email );
		if ( ! $result->success ) {
			\WP_CLI::error( $result->message );
		}
		\WP_CLI::success( 'Test email accepted by ' . $this->settings->provider_id() . ( $result->remote_id ? ' (' . $result->remote_id . ')' : '' ) );
	}

	/**
	 * Delete all email logs.
	 */
	public function clear_logs(): void {
		$this->logs->clear();
		\WP_CLI::success( 'Mailora email logs cleared.' );
	}
}
