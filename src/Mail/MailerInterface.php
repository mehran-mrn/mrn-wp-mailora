<?php
/**
 * Mail transport contract.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Mail;

defined( 'ABSPATH' ) || exit;

interface MailerInterface {
	public function id(): string;
	public function send( Message $message ): Result;
}
