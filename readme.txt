=== MRN Mailora ===
Contributors: mehran-mrn
Tags: smtp, email, mail, gmail, sendgrid
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional WordPress email delivery with SMTP, API, OAuth, secure logging, and a bilingual English/Persian admin experience.

== Description ==

MRN Mailora is an independent, production-focused delivery layer for WordPress email.

= Delivery methods =

* Native WordPress delivery
* SMTP with TLS/SSL
* SendGrid
* Brevo
* Mailgun
* Postmark
* Resend
* MailerSend
* SMTP2GO
* Gmail API with OAuth 2.0
* Microsoft Graph with OAuth 2.0
* Amazon SES API v2

= Features =

* Authenticated encryption for sensitive credentials
* Privacy-first delivery logs
* Delivery metrics dashboard
* Test email and MX/SPF diagnostics
* CC, BCC, Reply-To, and attachment support
* English/LTR and Persian/RTL interfaces
* WP-CLI tools
* No telemetry or runtime dependency

== Installation ==

1. Upload the ZIP from the WordPress Plugins screen.
2. Activate MRN Mailora.
3. Open Mailora and choose Delivery Settings.
4. Configure a delivery method and send a test email.

== Frequently Asked Questions ==

= Is email content stored? =

No. Content previews are disabled by default and must be enabled explicitly.

= How are API credentials stored? =

Credentials are encrypted with Sodium or AES-256-GCM using a key derived from WordPress salts.

= Does it work with WooCommerce and form plugins? =

Yes. Mailora manages the standard wp_mail pipeline and works with plugins that use the WordPress mail API.

== Changelog ==

= 1.0.0 =

* Initial release.
* 12 native, SMTP, API, and OAuth delivery methods.
* Bilingual admin, logs, tests, diagnostics, and WP-CLI.
