<?php
/**
 * Dependency-free core smoke tests.
 *
 * Run: php tests/run.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MRN_MAILORA_DIR', dirname( __DIR__ ) . '/' );
define( 'MRN_MAILORA_VERSION', 'test' );

function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
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

$result = \MRN\Mailora\Mail\Result::success( 'abc123' );
$assert( $result->success && 'abc123' === $result->remote_id, 'success result' );

fwrite( STDOUT, "OK: {$tests} assertions passed.\n" );
