<?php
/**
 * Installation and schema upgrades.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Core;

defined( 'ABSPATH' ) || exit;

final class Installer {
	public static function activate(): void {
		self::install();
		if ( ! wp_next_scheduled( 'mrn_mailora_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mrn_mailora_cleanup' );
		}
		set_transient( 'mrn_mailora_activated', 1, MINUTE_IN_SECONDS );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'mrn_mailora_cleanup' );
	}

	public static function maybe_upgrade(): void {
		if ( MRN_MAILORA_DB_VERSION !== get_option( 'mrn_mailora_db_version' ) ) {
			self::install();
		}
	}

	private static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'mrn_mailora_logs';
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				status varchar(20) NOT NULL DEFAULT 'sent',
				transport varchar(32) NOT NULL DEFAULT 'native',
				recipients text NOT NULL,
				subject text NOT NULL,
				preview text NULL,
				error text NULL,
				meta longtext NULL,
				duration_ms int(10) unsigned NOT NULL DEFAULT 0,
				initiated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status_created (status, created_at),
				KEY transport_created (transport, created_at),
				KEY created_at (created_at)
			) {$charset};"
		);

		add_option( 'mrn_mailora_settings', Settings::defaults(), '', false );
		update_option( 'mrn_mailora_db_version', MRN_MAILORA_DB_VERSION, false );
	}
}
