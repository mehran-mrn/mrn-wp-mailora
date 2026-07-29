# MRN Mailora

MRN Mailora is a standalone, production-focused email delivery plugin for WordPress. It replaces fragile host-dependent mail delivery with a secure transport layer, a polished RTL-friendly control center, actionable logs, OAuth, diagnostics, and WP-CLI tooling.

![MRN Mailora logo](assets/images/mailora-logo.png)

## Highlights

- Native WordPress mail and universal SMTP with TLS/SSL
- SendGrid, Brevo, Mailgun, Postmark, Resend, MailerSend and SMTP2GO APIs
- Gmail API and Microsoft Graph with OAuth 2.0 refresh tokens
- Amazon SES API v2 with AWS Signature Version 4
- CC, BCC, Reply-To, HTML, plain text and attachments
- Authenticated encryption for credentials using Sodium or AES-256-GCM
- Privacy-first email logs (message bodies are off by default)
- Delivery metrics, test email, SPF/MX diagnostics and Site Health data
- Responsive Persian/RTL admin experience with accessible interaction states
- WP-CLI status, test and log cleanup commands
- Scheduled log retention and safe uninstall behavior
- No runtime Composer dependency and no external telemetry

## Requirements

- WordPress 6.4+
- PHP 8.0+
- HTTPS is strongly recommended for OAuth transports

## Installation

1. Download `mrn-mailora-1.0.0.zip` from Releases.
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Activate **MRN Mailora**.
4. Open **Mailora → تنظیمات ارسال**, select a transport, save credentials, and send a test email.

## Provider notes

### SMTP

Enter the SMTP host, port, encryption, username and password supplied by your email provider. Port 587 with TLS is the recommended default.

### HTTP API providers

Create an API key with permission to send email. Verify the sender/domain in the provider dashboard, then save the key in Mailora. Secrets are encrypted before they reach the WordPress options table.

### Gmail

Create an OAuth web application in Google Cloud, enable the Gmail API, and add the callback URL shown under **Mailora → آزمایش و عیب‌یابی** as an authorized redirect URI. Save the Client ID and Client Secret before clicking **اتصال حساب**.

Required scope:

```text
https://www.googleapis.com/auth/gmail.send
```

### Microsoft 365

Create an app registration in Microsoft Entra, add the Mailora callback as a Web redirect URI, and grant delegated `Mail.Send` plus `offline_access`. Use `common` as tenant for multi-tenant accounts, or enter your tenant ID.

### Amazon SES

Use an IAM principal limited to `ses:SendEmail` and configure Access Key ID, Secret Access Key and region. Temporary session tokens are supported. Verify the sender/domain in the same SES region.

## Security model

- Credentials use libsodium `secretbox` where available and AES-256-GCM otherwise.
- The encryption key is derived from the site authentication salt and is never stored by the plugin.
- Existing secrets are never printed back into the settings form.
- OAuth state is user-bound, nonce-protected and expires after ten minutes.
- Every modifying admin action requires `manage_options` plus a WordPress nonce.
- Log content is disabled by default. When enabled, Mailora stores only a short plain-text preview.
- Provider error responses are normalized; credentials are not written to logs.

Changing WordPress authentication salts makes previously encrypted values unreadable. Re-enter transport credentials after a salt rotation.

## WP-CLI

```bash
wp mailora status
wp mailora test user@example.com
wp mailora clear_logs
```

## Development

```powershell
./tools/verify.ps1
./tools/package.ps1 -Version 1.0.0
```

Core smoke tests have no external dependency:

```bash
php tests/run.php
```

Optional WordPress Coding Standards checks:

```bash
composer install
composer lint
```

## Hooks

```php
add_filter( 'mrn_mailora_providers', function ( array $providers ): array {
    // Extend the provider catalog.
    return $providers;
} );

add_action( 'mrn_mailora_sent', function ( $message, $result ): void {
    // Observe successful API deliveries.
}, 10, 2 );

add_action( 'mrn_mailora_failed', function ( $message, $result ): void {
    // Observe failed API deliveries.
}, 10, 2 );
```

## Data removal

Deactivation preserves configuration and logs. To remove all data during uninstall, add this to `wp-config.php` first:

```php
define( 'MRN_MAILORA_REMOVE_DATA', true );
```

## License

GPL-2.0-or-later.

