<?php
/**
 * Dependency-free core smoke tests.
 *
 * Run: php tests/run.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MRN_MAILORA_DIR', dirname( __DIR__ ) . '/' );
define( 'MRN_MAILORA_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );

$test_locale = 'en_US';
$test_options = array();

function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function get_option( $key, $default = false ) {
	global $test_options;
	return $test_options[ $key ] ?? $default;
}
function get_bloginfo( $key = '' ) { return 'MRN Test'; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, $args ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . ltrim( $path, '/' ); }
function wp_create_nonce( $action = -1 ) { return 'test-nonce'; }
function get_current_user_id() { return 1; }
function set_transient( $key, $value, $expiration = 0 ) { return true; }
function determine_locale() {
	global $test_locale;
	return $test_locale;
}
function __( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return $text;
}
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_file_name( $value ) { return basename( $value ); }
function wp_basename( $value ) { return basename( $value ); }
function wp_check_filetype( $value ) { return array( 'type' => 'text/plain' ); }
function wp_generate_password( $length = 12 ) { return str_repeat( 'x', $length ); }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000000'; }

require MRN_MAILORA_DIR . 'src/Core/Autoloader.php';
\MRN\Mailora\Core\Autoloader::register();

$tests = 0;
$assert = static function ( bool $condition, string $message ) use ( &$tests ): void {
	++$tests;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
};

$vault  = new \MRN\Mailora\Core\SecretVault();
$secret = 'smtp-password-123';
$cipher = $vault->encrypt( $secret );
$assert( $secret !== $cipher, 'vault must not expose plaintext' );
$assert( $secret === $vault->decrypt( $cipher ), 'vault round-trip' );
$assert( str_ends_with( $vault->mask( $cipher ), '-123' ), 'mask keeps only suffix' );

$message = new \MRN\Mailora\Mail\Message(
	array( 'Mehran <mehran@example.com>', 'second@example.com' ),
	'Test',
	'Hello'
);
$assert( 2 === count( $message->recipients() ), 'recipient parsing' );
$assert( 'mehran@example.com' === $message->recipients()[0]['email'], 'named address parsing' );

$test_options['mrn_mailora_settings'] = array(
	'provider'  => 'gmail',
	'providers' => array(
		'gmail' => array( 'client_id' => 'oauth-client.apps.googleusercontent.com' ),
	),
);
$oauth       = new \MRN\Mailora\Mail\OAuth( new \MRN\Mailora\Core\Settings() );
$oauth_url   = $oauth->authorization_url( 'gmail' );
$oauth_query = array();
parse_str( (string) parse_url( $oauth_url, PHP_URL_QUERY ), $oauth_query );
$assert(
	'https://example.com/wp-admin/admin.php?page=mrn-mailora-settings&mailora_oauth=callback' === ( $oauth_query['redirect_uri'] ?? '' ),
	'OAuth callback query string must remain inside redirect_uri'
);
$assert(
	str_contains( $oauth_url, 'redirect_uri=https%3A%2F%2Fexample.com%2Fwp-admin%2Fadmin.php%3Fpage%3Dmrn-mailora-settings%26mailora_oauth%3Dcallback' ),
	'OAuth callback must use RFC 3986 encoding'
);
$test_options['mrn_mailora_settings']['providers']['microsoft'] = array(
	'client_id' => 'microsoft-oauth-client',
	'tenant'    => 'common',
);
$microsoft_url   = $oauth->authorization_url( 'microsoft' );
$microsoft_query = array();
parse_str( (string) parse_url( $microsoft_url, PHP_URL_QUERY ), $microsoft_query );
$assert(
	'https://example.com/wp-admin/admin.php?page=mrn-mailora-settings&mailora_oauth=callback' === ( $microsoft_query['redirect_uri'] ?? '' ),
	'Microsoft OAuth callback query string must remain inside redirect_uri'
);
$assert(
	str_contains( $microsoft_url, '%26mailora_oauth%3Dcallback' ),
	'Microsoft OAuth callback must use RFC 3986 encoding'
);

$result = \MRN\Mailora\Mail\Result::success( 'abc123' );
$assert( $result->success && 'abc123' === $result->remote_id, 'success result' );

$assert( 'Smart Email Delivery' === \MRN\Mailora\Core\I18n::translate( 'Smart Email Delivery', 'ارسال هوشمند ایمیل' ), 'English localization' );
$assert( 'ltr' === \MRN\Mailora\Core\I18n::direction(), 'English direction' );
$test_locale = 'fa_IR';
$assert( 'ارسال هوشمند ایمیل' === \MRN\Mailora\Core\I18n::translate( 'Smart Email Delivery', 'ارسال هوشمند ایمیل' ), 'Persian localization' );
$assert( 'rtl' === \MRN\Mailora\Core\I18n::direction(), 'Persian direction' );

fwrite( STDOUT, "OK: {$tests} assertions passed.\n" );
