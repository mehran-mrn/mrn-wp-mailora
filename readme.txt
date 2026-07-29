=== MRN Mailora ===
Contributors: mehran-mrn
Tags: smtp, email, mail, gmail, sendgrid
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ارسال حرفه‌ای ایمیل وردپرس با SMTP، API، OAuth، گزارش‌گیری امن و پنل مدیریتی فارسی.

== Description ==

MRN Mailora یک لایه مستقل و حرفه‌ای برای تحویل ایمیل‌های وردپرس است.

= روش‌های ارسال =

* ارسال پیش‌فرض وردپرس
* SMTP با TLS/SSL
* SendGrid
* Brevo
* Mailgun
* Postmark
* Resend
* MailerSend
* SMTP2GO
* Gmail API با OAuth 2.0
* Microsoft Graph با OAuth 2.0
* Amazon SES API v2

= امکانات =

* رمزنگاری احرازشده اطلاعات حساس
* گزارش ارسال با رویکرد privacy-first
* داشبورد آمار و وضعیت تحویل
* ایمیل آزمایشی و عیب‌یابی MX/SPF
* پشتیبانی CC، BCC، Reply-To و پیوست
* سازگار با RTL و موبایل
* ابزار WP-CLI
* بدون تله‌متری و وابستگی زمان اجرا

== Installation ==

1. فایل ZIP را از بخش افزونه‌های وردپرس بارگذاری کنید.
2. افزونه MRN Mailora را فعال کنید.
3. از منوی Mailora وارد تنظیمات ارسال شوید.
4. روش ارسال را پیکربندی و یک ایمیل آزمایشی ارسال کنید.

== Frequently Asked Questions ==

= آیا محتوای ایمیل ذخیره می‌شود؟ =

خیر. ذخیره پیش‌نمایش محتوا به‌صورت پیش‌فرض خاموش است و باید آگاهانه فعال شود.

= اطلاعات API چگونه نگهداری می‌شوند؟ =

کلیدها با Sodium یا AES-256-GCM و کلیدی مشتق‌شده از saltهای وردپرس رمز می‌شوند.

= آیا با ووکامرس و فرم‌سازها سازگار است؟ =

بله. Mailora مسیر استاندارد wp_mail را مدیریت می‌کند و با افزونه‌هایی که از API استاندارد وردپرس استفاده می‌کنند سازگار است.

== Changelog ==

= 1.0.0 =

* انتشار نخست.
* ۱۲ روش ارسال بومی، SMTP، API و OAuth.
* پنل مدیریت، گزارش، تست، عیب‌یابی و WP-CLI.
