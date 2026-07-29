<?php
/**
 * MRN Mailora uninstall routine.
 *
 * Data is removed only when the explicit constant is enabled:
 * define( 'MRN_MAILORA_REMOVE_DATA', true );
 *
 * @package MRN\Mailora
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'mrn_mailora_cleanup' );

if ( ! defined( 'MRN_MAILORA_REMOVE_DATA' ) || true !== MRN_MAILORA_REMOVE_DATA ) {
	return;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mrn_mailora_logs' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
delete_option( 'mrn_mailora_settings' );
delete_option( 'mrn_mailora_db_version' );
