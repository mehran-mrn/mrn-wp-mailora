<?php
/**
 * Plugin Name:       MRN Mailora
 * Plugin URI:        https://github.com/mehran-mrn/mrn-wp-mailora
 * Description:       زیرساخت حرفه‌ای و امن ارسال ایمیل وردپرس با SMTP، API، OAuth، گزارش‌گیری و عیب‌یابی.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Mehran MRN
 * Author URI:        https://github.com/mehran-mrn
 * Text Domain:       mrn-mailora
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * @package MRN\Mailora
 */

defined( 'ABSPATH' ) || exit;

define( 'MRN_MAILORA_VERSION', '1.0.0' );
define( 'MRN_MAILORA_DB_VERSION', '1.0.0' );
define( 'MRN_MAILORA_FILE', __FILE__ );
define( 'MRN_MAILORA_DIR', plugin_dir_path( __FILE__ ) );
define( 'MRN_MAILORA_URL', plugin_dir_url( __FILE__ ) );

require_once MRN_MAILORA_DIR . 'src/Core/Autoloader.php';

\MRN\Mailora\Core\Autoloader::register();

register_activation_hook( __FILE__, array( \MRN\Mailora\Core\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \MRN\Mailora\Core\Installer::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		\MRN\Mailora\Core\Plugin::instance()->boot();
	}
);
