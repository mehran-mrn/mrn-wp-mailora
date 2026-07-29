<?php
/**
 * Email log persistence and reporting.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Infrastructure;

use MRN\Mailora\Core\Settings;
use MRN\Mailora\Mail\Message;
use MRN\Mailora\Mail\Result;

defined( 'ABSPATH' ) || exit;

final class LogRepository {
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'mrn_mailora_logs';
	}

	public function record( Message $message, string $transport, Result $result, int $duration_ms ): void {
		$settings = new Settings();
		if ( ! $settings->get( 'logging', true ) ) {
			return;
		}

		global $wpdb;
		$preview    = $settings->get( 'log_content', false )
			? wp_trim_words( wp_strip_all_tags( $message->body ), 42, '…' )
			: '';
		$recipients = array_column( $message->recipients(), 'email' );
		$wpdb->insert(
			$this->table(),
			array(
				'status'       => $result->success ? 'sent' : 'failed',
				'transport'    => sanitize_key( $transport ),
				'recipients'   => implode( ', ', array_map( 'sanitize_email', $recipients ) ),
				'subject'      => sanitize_text_field( $message->subject ),
				'preview'      => sanitize_textarea_field( $preview ),
				'error'        => $result->success ? null : sanitize_textarea_field( $result->message ),
				'meta'         => wp_json_encode(
					array_filter(
						array(
							'remote_id'   => $result->remote_id,
							'details'     => $result->meta,
							'attachments' => count( $message->attachments ),
						)
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				),
				'duration_ms'  => max( 0, $duration_ms ),
				'initiated_by' => get_current_user_id(),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/** @return array<int, object> */
	public function recent( int $limit = 30, string $status = '', string $search = '' ): array {
		global $wpdb;
		$table = $this->table();
		$where = array( '1=1' );
		$args  = array();
		if ( in_array( $status, array( 'sent', 'failed' ), true ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}
		if ( $search ) {
			$where[] = '(recipients LIKE %s OR subject LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$args[]  = $like;
			$args[]  = $like;
		}
		$sql    = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$args[] = min( 200, max( 1, $limit ) );
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<string, int|float> */
	public function stats(): array {
		global $wpdb;
		$table = $this->table();
		$sql   = "SELECT
				COUNT(*) total,
				SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) sent,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed,
				COALESCE(AVG(duration_ms), 0) average_ms
			FROM {$table}
			WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row   = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'total'      => (int) ( $row['total'] ?? 0 ),
			'sent'       => (int) ( $row['sent'] ?? 0 ),
			'failed'     => (int) ( $row['failed'] ?? 0 ),
			'average_ms' => round( (float) ( $row['average_ms'] ?? 0 ) ),
		);
	}

	public function cleanup(): void {
		global $wpdb;
		$days   = min( 365, max( 1, (int) ( new Settings() )->get( 'retention_days', 30 ) ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->table() . ' WHERE created_at < %s', $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function clear(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $this->table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
