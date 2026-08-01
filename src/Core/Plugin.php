<?php
/**
 * Plugin composition root.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Core;

use MRN\Mailora\Admin\Admin;
use MRN\Mailora\Infrastructure\LogRepository;
use MRN\Mailora\Mail\Dispatcher;
use MRN\Mailora\Mail\OAuth;
use MRN\Mailora\Mail\ProviderRegistry;
use MRN\Mailora\Support\CliCommand;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static ?self $instance = null;
	private bool $booted           = false;

	private function __construct() {}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		Installer::maybe_upgrade();
		load_plugin_textdomain( 'mrn-mailora', false, dirname( plugin_basename( MRN_MAILORA_FILE ) ) . '/languages' );

		$settings   = new Settings();
		$logs       = new LogRepository();
		$oauth      = new OAuth( $settings );
		$registry   = new ProviderRegistry( $settings, $oauth );
		$dispatcher = new Dispatcher( $settings, $logs, $registry );

		$dispatcher->register();
		$oauth->register();

		add_action( 'mrn_mailora_cleanup', array( $logs, 'cleanup' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MRN_MAILORA_FILE ), array( $this, 'action_links' ) );
		add_filter( 'debug_information', array( $this, 'debug_information' ) );

		if ( is_admin() ) {
			( new Admin( $settings, $logs, $registry, $oauth, $dispatcher ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'mailora', new CliCommand( $dispatcher, $settings, $logs ) );
		}

		do_action( 'mrn_mailora_loaded', $registry );
	}

	/** @param array<int, string> $links */
	public function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mrn-mailora' ) ) . '">' . esc_html( I18n::translate( 'Delivery Center', 'مرکز ارسال' ) ) . '</a>' );
		return $links;
	}

	/** @param array<string, mixed> $info */
	public function debug_information( array $info ): array {
		$settings            = new Settings();
		$info['mrn-mailora'] = array(
			'label'  => 'MRN Mailora',
			'fields' => array(
				'version'  => array(
					'label' => 'Version',
					'value' => MRN_MAILORA_VERSION,
				),
				'provider' => array(
					'label' => 'Active transport',
					'value' => $settings->provider_id(),
				),
				'logging'  => array(
					'label' => 'Logging',
					'value' => $settings->get( 'logging' ) ? 'enabled' : 'disabled',
				),
				'php'      => array(
					'label' => 'PHP',
					'value' => PHP_VERSION,
				),
			),
		);
		return $info;
	}
}
