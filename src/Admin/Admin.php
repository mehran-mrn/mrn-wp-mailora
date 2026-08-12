<?php
/**
 * Mailora administration experience.
 *
 * @package MRN\Mailora
 */

namespace MRN\Mailora\Admin;

use MRN\Mailora\Core\Settings;
use MRN\Mailora\Core\I18n;
use MRN\Mailora\Infrastructure\LogRepository;
use MRN\Mailora\Mail\Dispatcher;
use MRN\Mailora\Mail\OAuth;
use MRN\Mailora\Mail\ProviderRegistry;

defined( 'ABSPATH' ) || exit;

final class Admin {
	private const CAPABILITY = 'manage_options';

	public function __construct(
		private Settings $settings,
		private LogRepository $logs,
		private ProviderRegistry $providers,
		private OAuth $oauth,
		private Dispatcher $dispatcher
	) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_mrn_mailora_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_mrn_mailora_test_email', array( $this, 'ajax_test_email' ) );
		add_action( 'wp_ajax_mrn_mailora_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_mrn_mailora_diagnostics', array( $this, 'ajax_diagnostics' ) );
		add_action( 'admin_notices', array( $this, 'welcome_notice' ) );
	}

	public function menu(): void {
		add_menu_page( 'MRN Mailora', 'Mailora', self::CAPABILITY, 'mrn-mailora', array( $this, 'dashboard' ), 'dashicons-email-alt2', 56 );
		add_submenu_page( 'mrn-mailora', $this->t( 'Mailora Dashboard', 'داشبورد Mailora' ), $this->t( 'Dashboard', 'داشبورد' ), self::CAPABILITY, 'mrn-mailora', array( $this, 'dashboard' ) );
		add_submenu_page( 'mrn-mailora', $this->t( 'Mailora Settings', 'تنظیمات Mailora' ), $this->t( 'Delivery Settings', 'تنظیمات ارسال' ), self::CAPABILITY, 'mrn-mailora-settings', array( $this, 'settings_page' ) );
		add_submenu_page( 'mrn-mailora', $this->t( 'Mailora Logs', 'گزارش‌های Mailora' ), $this->t( 'Email Logs', 'گزارش ایمیل‌ها' ), self::CAPABILITY, 'mrn-mailora-logs', array( $this, 'logs_page' ) );
		add_submenu_page( 'mrn-mailora', $this->t( 'Mailora Tools', 'ابزارهای Mailora' ), $this->t( 'Testing & Diagnostics', 'آزمایش و عیب‌یابی' ), self::CAPABILITY, 'mrn-mailora-tools', array( $this, 'tools_page' ) );
	}

	public function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'mrn-mailora' ) ) {
			return;
		}
		wp_enqueue_style( 'mrn-mailora-admin', MRN_MAILORA_URL . 'assets/css/admin.css', array(), MRN_MAILORA_VERSION );
		wp_enqueue_script( 'mrn-mailora-admin', MRN_MAILORA_URL . 'assets/js/admin.js', array(), MRN_MAILORA_VERSION, true );
		wp_localize_script(
			'mrn-mailora-admin',
			'MRNMailora',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'mrn_mailora_admin' ),
				'i18n'    => array(
					'working'      => $this->t( 'Working…', 'در حال انجام…' ),
					'saved'        => $this->t( 'Settings saved successfully.', 'تنظیمات با موفقیت ذخیره شد.' ),
					'error'        => $this->t( 'Something went wrong. Please try again.', 'خطایی رخ داد. دوباره تلاش کنید.' ),
					'unsaved'      => $this->t( 'Changes have not been saved yet.', 'تغییرات هنوز ذخیره نشده‌اند.' ),
					'allSaved'     => $this->t( 'All changes have been saved.', 'همه تغییرات ذخیره شده‌اند.' ),
					'saveSettings' => $this->t( 'Save Settings', 'ذخیره تنظیمات' ),
					'sendTest'     => $this->t( 'Send Test', 'ارسال آزمایشی' ),
					'remoteId'     => $this->t( 'ID:', 'شناسه:' ),
					'confirmClear' => $this->t( 'Delete all email logs? This action cannot be undone.', 'تمام گزارش‌های ایمیل پاک شوند؟ این عملیات قابل بازگشت نیست.' ),
					'runAgain'     => $this->t( 'Run Check Again', 'اجرای دوباره بررسی' ),
					'copied'       => $this->t( 'Address copied to the clipboard.', 'نشانی در حافظه کپی شد.' ),
					'copyFailed'   => $this->t( 'Automatic copying was not available.', 'کپی خودکار ممکن نبود.' ),
				),
			)
		);
	}

	public function dashboard(): void {
		$this->guard();
		$stats    = $this->logs->stats();
		$recent   = $this->logs->recent( 8 );
		$provider = $this->providers->definitions()[ $this->settings->provider_id() ] ?? array( 'name' => $this->settings->provider_id() );
		$rate     = $stats['total'] ? round( ( $stats['sent'] / $stats['total'] ) * 100, 1 ) : 100;
		$this->open_page( $this->t( 'Email Control Center', 'مرکز کنترل ایمیل' ), $this->t( 'See delivery performance over the past 30 days at a glance.', 'وضعیت ارسال‌های ۳۰ روز گذشته را در یک نگاه ببینید.' ), 'dashboard' );
		?>
		<section class="mailora-hero">
			<div>
				<span class="mailora-eyebrow">DELIVERY CONTROL CENTER</span>
				<h2><?php echo esc_html( $this->t( 'Reliable, precise WordPress email.', 'ایمیل‌های وردپرس، دقیق و قابل اعتماد.' ) ); ?></h2>
				<p><?php echo esc_html( $this->t( 'Mailora manages delivery, makes errors visible, and monitors sending reputation.', 'Mailora مسیر ارسال را مدیریت می‌کند، خطاها را شفاف نشان می‌دهد و اعتبار ارسال را زیر نظر دارد.' ) ); ?></p>
				<div class="mailora-actions">
					<a class="mailora-button is-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=mrn-mailora-tools' ) ); ?>"><?php echo esc_html( $this->t( 'Send Test Email', 'ارسال ایمیل آزمایشی' ) ); ?></a>
					<a class="mailora-button" href="<?php echo esc_url( admin_url( 'admin.php?page=mrn-mailora-settings' ) ); ?>"><?php echo esc_html( $this->t( 'Manage Delivery Method', 'مدیریت روش ارسال' ) ); ?></a>
				</div>
			</div>
			<div class="mailora-health-orbit" style="--rate:<?php echo esc_attr( (string) $rate ); ?>">
				<div><strong><?php echo esc_html( number_format_i18n( $rate, 1 ) ); ?>%</strong><span><?php echo esc_html( $this->t( 'Delivery success', 'موفقیت ارسال' ) ); ?></span></div>
			</div>
		</section>
		<div class="mailora-stat-grid">
			<?php
			$this->stat_card( $this->t( 'Total Emails', 'کل ارسال‌ها' ), (string) number_format_i18n( $stats['total'] ), 'dashicons-email-alt', $this->t( 'Last 30 days', '۳۰ روز اخیر' ) );
			$this->stat_card( $this->t( 'Sent', 'ارسال موفق' ), (string) number_format_i18n( $stats['sent'] ), 'dashicons-yes-alt', $this->t( 'Accepted by service', 'تحویل به سرویس' ), 'success' );
			$this->stat_card( $this->t( 'Failed', 'ناموفق' ), (string) number_format_i18n( $stats['failed'] ), 'dashicons-warning', $this->t( 'Needs attention', 'نیازمند بررسی' ), $stats['failed'] ? 'danger' : '' );
			$this->stat_card( $this->t( 'Average Response', 'میانگین پاسخ' ), number_format_i18n( $stats['average_ms'] ) . ' ms', 'dashicons-performance', $this->t( 'Connection time', 'زمان ارتباط' ) );
			?>
		</div>
		<div class="mailora-grid mailora-grid-2">
			<section class="mailora-card">
				<div class="mailora-card-head"><div><span class="mailora-kicker">TRANSPORT</span><h3><?php echo esc_html( $this->t( 'Active Delivery Method', 'روش ارسال فعال' ) ); ?></h3></div><span class="mailora-status-dot"></span></div>
				<div class="mailora-provider-active">
					<span class="dashicons <?php echo esc_attr( $provider['icon'] ?? 'dashicons-email' ); ?>"></span>
					<div><strong><?php echo esc_html( $provider['name'] ); ?></strong><p><?php echo esc_html( $provider['description'] ?? '' ); ?></p></div>
				</div>
			</section>
			<section class="mailora-card">
				<div class="mailora-card-head"><div><span class="mailora-kicker">SENDER IDENTITY</span><h3><?php echo esc_html( $this->t( 'Sender Identity', 'هویت فرستنده' ) ); ?></h3></div></div>
				<div class="mailora-identity"><span><?php echo esc_html( $this->initial( (string) $this->settings->get( 'from_name', 'M' ) ) ); ?></span><div><strong><?php echo esc_html( (string) $this->settings->get( 'from_name' ) ); ?></strong><code><?php echo esc_html( (string) $this->settings->get( 'from_email' ) ); ?></code></div></div>
			</section>
		</div>
		<section class="mailora-card">
			<div class="mailora-card-head"><div><span class="mailora-kicker">RECENT ACTIVITY</span><h3><?php echo esc_html( $this->t( 'Recent Emails', 'آخرین ایمیل‌ها' ) ); ?></h3></div><a href="<?php echo esc_url( admin_url( 'admin.php?page=mrn-mailora-logs' ) ); ?>"><?php echo esc_html( $this->t( 'View All', 'مشاهده همه' ) ); ?></a></div>
			<?php $this->logs_table( $recent, true ); ?>
		</section>
		<?php
		$this->close_page();
	}

	public function settings_page(): void {
		$this->guard();
		$all       = $this->settings->all();
		$active    = $this->settings->provider_id();
		$providers = $this->providers->definitions();
		$this->open_page( $this->t( 'Delivery Settings', 'تنظیمات ارسال' ), $this->t( 'Configure the sender identity and email delivery gateway.', 'هویت فرستنده و درگاه تحویل ایمیل را پیکربندی کنید.' ), 'settings' );
		?>
		<form id="mailora-settings-form" class="mailora-settings-form">
			<section class="mailora-card">
				<div class="mailora-section-title"><span>1</span><div><h3><?php echo esc_html( $this->t( 'Sender Identity', 'هویت فرستنده' ) ); ?></h3><p><?php echo esc_html( $this->t( 'The name and address recipients will see.', 'نام و نشانی‌ای که گیرندگان مشاهده می‌کنند.' ) ); ?></p></div></div>
				<div class="mailora-form-grid">
					<?php $this->field( 'from_name', $this->t( 'Sender Name', 'نام فرستنده' ), (string) $all['from_name'], $this->t( 'e.g. Acme Store', 'مثلاً مثنوی معنوی' ) ); ?>
					<?php $this->field( 'from_email', $this->t( 'Sender Email', 'ایمیل فرستنده' ), (string) $all['from_email'], 'mail@example.com', 'email', $this->t( 'Use an address on this website\'s domain when possible.', 'بهتر است از دامنه همین وب‌سایت باشد.' ) ); ?>
				</div>
				<div class="mailora-toggle-row">
					<?php $this->toggle( 'force_from_name', $this->t( 'Apply name to every email', 'اعمال نام روی همه ایمیل‌ها' ), ! empty( $all['force_from_name'] ) ); ?>
					<?php $this->toggle( 'force_from_email', $this->t( 'Apply address to every email', 'اعمال نشانی روی همه ایمیل‌ها' ), ! empty( $all['force_from_email'] ) ); ?>
					<?php $this->toggle( 'return_path', $this->t( 'Match the Return-Path', 'همسان‌سازی Return-Path' ), ! empty( $all['return_path'] ) ); ?>
				</div>
			</section>
			<section class="mailora-card">
				<div class="mailora-section-title"><span>2</span><div><h3><?php echo esc_html( $this->t( 'Delivery Method', 'روش ارسال' ) ); ?></h3><p><?php echo esc_html( $this->t( 'Choose a service; only fields for that service are saved.', 'سرویس مناسب را انتخاب کنید؛ فقط فیلدهای همان سرویس ذخیره می‌شوند.' ) ); ?></p></div></div>
				<div class="mailora-provider-grid">
					<?php foreach ( $providers as $id => $provider ) : ?>
						<label class="mailora-provider-card <?php echo $active === $id ? 'is-active' : ''; ?>" data-provider-card="<?php echo esc_attr( $id ); ?>">
							<input type="radio" name="provider" value="<?php echo esc_attr( $id ); ?>" <?php checked( $active, $id ); ?>>
							<span class="dashicons <?php echo esc_attr( $provider['icon'] ); ?>"></span>
							<strong><?php echo esc_html( $provider['name'] ); ?></strong>
							<small><?php echo esc_html( strtoupper( $provider['type'] ) ); ?></small>
							<p><?php echo esc_html( $provider['description'] ); ?></p>
							<i class="dashicons dashicons-yes-alt"></i>
						</label>
					<?php endforeach; ?>
				</div>
				<div class="mailora-provider-configs">
					<?php foreach ( array_keys( $providers ) as $id ) : ?>
						<div class="mailora-provider-config <?php echo $active === $id ? 'is-active' : ''; ?>" data-provider-config="<?php echo esc_attr( $id ); ?>">
							<?php $this->provider_fields( $id ); ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="mailora-card">
				<div class="mailora-section-title"><span>3</span><div><h3><?php echo esc_html( $this->t( 'Logging & Privacy', 'گزارش‌گیری و حریم خصوصی' ) ); ?></h3><p><?php echo esc_html( $this->t( 'Keep delivery results for diagnostics without storing email content.', 'برای عیب‌یابی، نتیجه ارسال‌ها را بدون ذخیره محتوای ایمیل نگه دارید.' ) ); ?></p></div></div>
				<div class="mailora-toggle-row">
					<?php $this->toggle( 'logging', $this->t( 'Log delivery events', 'ثبت رویدادهای ارسال' ), ! empty( $all['logging'] ) ); ?>
					<?php $this->toggle( 'log_content', $this->t( 'Store a content preview', 'ذخیره پیش‌نمایش محتوا' ), ! empty( $all['log_content'] ), $this->t( 'Disabled by default and suitable for sensitive data.', 'به‌صورت پیش‌فرض خاموش و مناسب داده‌های حساس است.' ) ); ?>
				</div>
				<div class="mailora-form-grid"><?php $this->field( 'retention_days', $this->t( 'Log Retention (days)', 'نگهداری گزارش‌ها (روز)' ), (string) $all['retention_days'], '30', 'number' ); ?></div>
			</section>
			<div class="mailora-sticky-save"><span id="mailora-save-state"><?php echo esc_html( $this->t( 'No unsaved changes.', 'تغییری ذخیره نشده است.' ) ); ?></span><button class="mailora-button is-primary" type="submit"><?php echo esc_html( $this->t( 'Save Settings', 'ذخیره تنظیمات' ) ); ?></button></div>
		</form>
		<?php
		$this->close_page();
	}

	public function logs_page(): void {
		$this->guard();
		$status = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$items  = $this->logs->recent( 100, $status, $search );
		$this->open_page( $this->t( 'Email Logs', 'گزارش ایمیل‌ها' ), $this->t( 'Delivery history, response times, and error details.', 'تاریخچه تحویل، زمان پاسخ و جزئیات خطاها.' ), 'logs' );
		?>
		<section class="mailora-card">
			<div class="mailora-toolbar">
				<form method="get"><input type="hidden" name="page" value="mrn-mailora-logs"><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr( $this->t( 'Search recipient or subject…', 'جست‌وجوی گیرنده یا موضوع…' ) ); ?>"><select name="status"><option value=""><?php echo esc_html( $this->t( 'All statuses', 'همه وضعیت‌ها' ) ); ?></option><option value="sent" <?php selected( $status, 'sent' ); ?>><?php echo esc_html( $this->t( 'Sent', 'موفق' ) ); ?></option><option value="failed" <?php selected( $status, 'failed' ); ?>><?php echo esc_html( $this->t( 'Failed', 'ناموفق' ) ); ?></option></select><button class="mailora-button"><?php echo esc_html( $this->t( 'Filter', 'فیلتر' ) ); ?></button></form>
				<button class="mailora-button is-danger is-quiet" id="mailora-clear-logs"><?php echo esc_html( $this->t( 'Clear Logs', 'پاک‌کردن گزارش‌ها' ) ); ?></button>
			</div>
			<?php $this->logs_table( $items ); ?>
		</section>
		<?php
		$this->close_page();
	}

	public function tools_page(): void {
		$this->guard();
		$this->open_page( $this->t( 'Testing & Diagnostics', 'آزمایش و عیب‌یابی' ), $this->t( 'Verify delivery before relying on email for critical messages.', 'پیش از اتکا به ایمیل‌های حیاتی، مسیر تحویل را بررسی کنید.' ), 'tools' );
		?>
		<div class="mailora-grid mailora-grid-2">
			<section class="mailora-card mailora-test-card">
				<div class="mailora-card-icon"><span class="dashicons dashicons-email-alt2"></span></div>
				<h3><?php echo esc_html( $this->t( 'Send a Test Email', 'ارسال ایمیل آزمایشی' ) ); ?></h3>
				<p><?php echo esc_html( $this->t( 'A real HTML message will be sent through the active method.', 'یک پیام HTML واقعی از روش فعال فعلی ارسال می‌شود.' ) ); ?></p>
				<form id="mailora-test-form">
					<?php $this->field( 'to', $this->t( 'Recipient', 'گیرنده' ), (string) wp_get_current_user()->user_email, 'you@example.com', 'email' ); ?>
					<button class="mailora-button is-primary is-wide" type="submit"><?php echo esc_html( $this->t( 'Send Test', 'ارسال آزمایشی' ) ); ?></button>
				</form>
				<div id="mailora-test-result" class="mailora-result" hidden></div>
			</section>
			<section class="mailora-card mailora-diagnostics-card">
				<div class="mailora-card-icon is-violet"><span class="dashicons dashicons-shield-alt"></span></div>
				<h3><?php echo esc_html( $this->t( 'Infrastructure Health', 'سلامت زیرساخت' ) ); ?></h3>
				<p><?php echo esc_html( $this->t( 'Checks PHP, encryption, sender DNS, and configuration status.', 'PHP، رمزنگاری، DNS فرستنده و وضعیت پیکربندی بررسی می‌شود.' ) ); ?></p>
				<button class="mailora-button is-wide" id="mailora-run-diagnostics"><?php echo esc_html( $this->t( 'Run Health Check', 'اجرای بررسی سلامت' ) ); ?></button>
				<div id="mailora-diagnostics-result" class="mailora-diagnostic-list"></div>
			</section>
		</div>
		<section class="mailora-card">
			<div class="mailora-section-title"><span class="dashicons dashicons-admin-links"></span><div><h3><?php echo esc_html( $this->t( 'OAuth Callback URL', 'نشانی بازگشت OAuth' ) ); ?></h3><p><?php echo esc_html( $this->t( 'Register this exact address in Google Cloud or Microsoft Entra.', 'این نشانی را در Google Cloud یا Microsoft Entra دقیقاً ثبت کنید.' ) ); ?></p></div></div>
			<div class="mailora-copy-field"><code><?php echo esc_html( $this->oauth->callback_url() ); ?></code><button type="button" data-copy="<?php echo esc_attr( $this->oauth->callback_url() ); ?>"><?php echo esc_html( $this->t( 'Copy', 'کپی' ) ); ?></button></div>
		</section>
		<?php
		$this->close_page();
	}

	public function ajax_save_settings(): void {
		$this->ajax_guard();
		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->settings->save( $input );
		wp_send_json_success( array( 'message' => $this->t( 'Settings were saved securely.', 'تنظیمات با موفقیت و به‌صورت امن ذخیره شد.' ) ) );
	}

	public function ajax_test_email(): void {
		$this->ajax_guard();
		$to = sanitize_email( wp_unslash( $_POST['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => $this->t( 'The recipient address is not valid.', 'نشانی گیرنده معتبر نیست.' ) ), 422 );
		}
		$result = $this->dispatcher->send_test( $to );
		if ( $result->success ) {
			wp_send_json_success(
				array(
					'message'  => $this->t( 'The email was successfully handed to the delivery service.', 'ایمیل با موفقیت به سرویس ارسال تحویل شد.' ),
					'remoteId' => $result->remote_id,
				)
			);
		}
		wp_send_json_error( array( 'message' => $result->message ), 500 );
	}

	public function ajax_clear_logs(): void {
		$this->ajax_guard();
		$this->logs->clear();
		wp_send_json_success( array( 'message' => $this->t( 'All email logs were deleted.', 'تمام گزارش‌های ایمیل پاک شدند.' ) ) );
	}

	public function ajax_diagnostics(): void {
		$this->ajax_guard();
		$email    = (string) $this->settings->get( 'from_email', '' );
		$domain   = str_contains( $email, '@' ) ? substr( strrchr( $email, '@' ), 1 ) : '';
		$provider = $this->settings->provider_id();
		$checks   = array(
			array(
				'label'  => $this->t( 'PHP 8.0 or newer', 'نسخه PHP 8.0 یا جدیدتر' ),
				'ok'     => version_compare( PHP_VERSION, '8.0', '>=' ),
				'detail' => PHP_VERSION,
			),
			array(
				'label'  => $this->t( 'Secure credential encryption', 'رمزنگاری امن کلیدها' ),
				'ok'     => function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' ),
				'detail' => function_exists( 'sodium_crypto_secretbox' ) ? 'Sodium' : 'OpenSSL',
			),
			array(
				'label'  => $this->t( 'Valid sender identity', 'هویت فرستنده معتبر' ),
				'ok'     => (bool) is_email( $email ),
				'detail' => $email,
			),
			array(
				'label'  => $this->t( 'Active delivery method', 'روش ارسال فعال' ),
				'ok'     => 'native' !== $provider,
				'detail' => $provider,
			),
		);
		if ( $domain && function_exists( 'checkdnsrr' ) ) {
			$checks[] = array(
				'label'  => $this->t( 'Domain DNS record', 'رکورد DNS دامنه' ),
				'ok'     => checkdnsrr( $domain, 'MX' ),
				'detail' => $domain,
			);
			$checks[] = array(
				'label'  => $this->t( 'SPF record', 'رکورد SPF' ),
				'ok'     => $this->has_spf( $domain ),
				'detail' => $domain,
			);
		}
		wp_send_json_success( array( 'checks' => $checks ) );
	}

	private function provider_fields( string $id ): void {
		$config = $this->settings->provider( $id );
		echo '<div class="mailora-config-head"><h3>' . esc_html( $this->providers->definitions()[ $id ]['name'] ) . '</h3></div>';
		if ( 'native' === $id ) {
			echo '<div class="mailora-callout"><span class="dashicons dashicons-info-outline"></span><p>' . esc_html( $this->t( 'Mailora still manages logs and sender identity; final delivery is handled by the server configuration.', 'Mailora همچنان گزارش و هویت فرستنده را مدیریت می‌کند؛ تحویل نهایی بر عهده تنظیمات سرور خواهد بود.' ) ) . '</p></div>';
			return;
		}
		echo '<div class="mailora-form-grid">';
		if ( 'smtp' === $id ) {
			$this->field( "providers[$id][host]", $this->t( 'SMTP Host', 'میزبان SMTP' ), (string) ( $config['host'] ?? '' ), 'smtp.example.com' );
			$this->field( "providers[$id][port]", $this->t( 'Port', 'پورت' ), (string) ( $config['port'] ?? '587' ), '587', 'number' );
			$this->select(
				"providers[$id][encryption]",
				$this->t( 'Encryption', 'رمزنگاری' ),
				(string) ( $config['encryption'] ?? 'tls' ),
				array(
					'tls'  => 'TLS',
					'ssl'  => 'SSL',
					'none' => $this->t( 'None', 'بدون رمزنگاری' ),
				)
			);
			$this->field( "providers[$id][username]", $this->t( 'Username', 'نام کاربری' ), (string) ( $config['username'] ?? '' ), 'mail@example.com' );
			$this->secret( $id, 'password', $this->t( 'SMTP Password', 'رمز عبور SMTP' ) );
			echo '</div><div class="mailora-toggle-row">';
			$this->toggle( "providers[$id][auth]", $this->t( 'SMTP Authentication', 'احراز هویت SMTP' ), ! isset( $config['auth'] ) || ! empty( $config['auth'] ) );
			$this->toggle( "providers[$id][auto_tls]", $this->t( 'Automatic TLS Upgrade', 'ارتقای خودکار TLS' ), ! isset( $config['auto_tls'] ) || ! empty( $config['auto_tls'] ) );
			echo '</div>';
			return;
		}
		if ( in_array( $id, array( 'sendgrid', 'brevo', 'postmark', 'resend', 'mailersend', 'smtp2go' ), true ) ) {
			$this->secret( $id, 'api_key', $this->t( 'API Key', 'کلید API' ) );
		} elseif ( 'mailgun' === $id ) {
			$this->secret( $id, 'api_key', $this->t( 'Private API Key', 'کلید خصوصی API' ) );
			$this->field( "providers[$id][domain]", $this->t( 'Mailgun Sending Domain', 'دامنه ارسال Mailgun' ), (string) ( $config['domain'] ?? '' ), 'mg.example.com' );
			$this->select(
				"providers[$id][region]",
				$this->t( 'Region', 'منطقه' ),
				(string) ( $config['region'] ?? 'us' ),
				array(
					'us' => 'United States',
					'eu' => 'Europe',
				)
			);
		} elseif ( in_array( $id, array( 'gmail', 'microsoft' ), true ) ) {
			$this->field( "providers[$id][client_id]", 'Client ID', (string) ( $config['client_id'] ?? '' ), 'OAuth Client ID' );
			$this->secret( $id, 'client_secret', 'Client Secret' );
			if ( 'microsoft' === $id ) {
				$this->field( "providers[$id][tenant]", 'Tenant', (string) ( $config['tenant'] ?? 'common' ), 'common' );
			}
			echo '</div><div class="mailora-oauth-box"><div><strong>' . esc_html( $this->t( 'Secure OAuth 2.0 Connection', 'اتصال امن OAuth 2.0' ) ) . '</strong><p>' . esc_html( $this->t( 'Save the Client ID and Secret, then connect the account.', 'ابتدا Client ID و Secret را ذخیره کنید، سپس اتصال حساب را انجام دهید.' ) ) . '</p></div>';
			$url = $this->oauth->authorization_url( $id );
			if ( ! empty( $config['access_token'] ) ) {
				echo '<span class="mailora-badge is-success">' . esc_html( $this->t( 'Connected', 'متصل' ) ) . '</span>';
			} elseif ( $url ) {
				echo '<a class="mailora-button" href="' . esc_url( $url ) . '">' . esc_html( $this->t( 'Connect Account', 'اتصال حساب' ) ) . '</a>';
			} else {
				echo '<span class="mailora-badge">' . esc_html( $this->t( 'Save credentials first', 'منتظر ذخیره شناسه' ) ) . '</span>';
			}
			echo '</div>';
			return;
		} elseif ( 'ses' === $id ) {
			$this->field( "providers[$id][access_key]", 'AWS Access Key ID', (string) ( $config['access_key'] ?? '' ), 'AKIA…' );
			$this->secret( $id, 'secret_key', 'AWS Secret Access Key' );
			$this->field( "providers[$id][region]", 'AWS Region', (string) ( $config['region'] ?? 'us-east-1' ), 'us-east-1' );
			$this->secret( $id, 'session_token', $this->t( 'Session Token (optional)', 'Session Token (اختیاری)' ) );
		}
		echo '</div>';
	}

	private function field( string $name, string $label, string $value, string $placeholder = '', string $type = 'text', string $help = '' ): void {
		printf( '<label class="mailora-field"><span>%s</span><input type="%s" name="%s" value="%s" placeholder="%s">%s</label>', esc_html( $label ), esc_attr( $type ), esc_attr( $name ), esc_attr( $value ), esc_attr( $placeholder ), $help ? '<small>' . esc_html( $help ) . '</small>' : '' );
	}

	private function secret( string $provider, string $field, string $label ): void {
		$mask = $this->settings->secret_mask( $provider, $field );
		$this->field( "providers[$provider][$field]", $label, '', $mask ? $mask : '••••••••••••', 'password', $mask ? $this->t( 'The saved value is preserved. Enter a new value only to replace it.', 'مقدار ذخیره‌شده حفظ می‌شود؛ فقط برای تغییر، مقدار تازه وارد کنید.' ) : '' );
	}

	/** @param array<string, string> $options */
	private function select( string $name, string $label, string $value, array $options ): void {
		echo '<label class="mailora-field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $key => $text ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></label>';
	}

	private function toggle( string $name, string $label, bool $checked, string $help = '' ): void {
		echo '<label class="mailora-toggle"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, true, false ) . '><i></i><span><strong>' . esc_html( $label ) . '</strong>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</span></label>';
	}

	/** @param array<int, object> $items */
	private function logs_table( array $items, bool $compact = false ): void {
		if ( ! $items ) {
			echo '<div class="mailora-empty"><span class="dashicons dashicons-email-alt"></span><strong>' . esc_html( $this->t( 'No emails have been logged yet', 'هنوز ایمیلی ثبت نشده است' ) ) . '</strong><p>' . esc_html( $this->t( 'Details will appear here after the first delivery.', 'پس از اولین ارسال، جزئیات اینجا نمایش داده می‌شود.' ) ) . '</p></div>';
			return;
		}
		echo '<div class="mailora-table-wrap"><table class="mailora-table"><thead><tr><th>' . esc_html( $this->t( 'Status', 'وضعیت' ) ) . '</th><th>' . esc_html( $this->t( 'Recipient / Subject', 'گیرنده / موضوع' ) ) . '</th><th>' . esc_html( $this->t( 'Method', 'روش' ) ) . '</th><th>' . esc_html( $this->t( 'Response Time', 'زمان پاسخ' ) ) . '</th><th>' . esc_html( $this->t( 'Date', 'تاریخ' ) ) . '</th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			$status = 'sent' === $item->status;
			echo '<tr><td><span class="mailora-badge ' . ( $status ? 'is-success' : 'is-danger' ) . '"><i></i>' . esc_html( $status ? $this->t( 'Sent', 'موفق' ) : $this->t( 'Failed', 'ناموفق' ) ) . '</span></td>';
			echo '<td><strong>' . esc_html( $item->recipients ) . '</strong><small>' . esc_html( $item->subject ) . '</small>';
			if ( ! $compact && ! $status && $item->error ) {
				echo '<em>' . esc_html( $item->error ) . '</em>';
			}
			echo '</td><td><code>' . esc_html( $item->transport ) . '</code></td><td>' . esc_html( number_format_i18n( $item->duration_ms ) ) . ' ms</td><td>' . esc_html( wp_date( 'Y/m/d H:i', strtotime( $item->created_at . ' UTC' ) ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private function stat_card( string $label, string $value, string $icon, string $hint, string $tone = '' ): void {
		echo '<article class="mailora-stat ' . esc_attr( $tone ) . '"><span class="dashicons ' . esc_attr( $icon ) . '"></span><div><small>' . esc_html( $label ) . '</small><strong>' . esc_html( $value ) . '</strong><em>' . esc_html( $hint ) . '</em></div></article>';
	}

	private function open_page( string $title, string $subtitle, string $active ): void {
		?>
		<div class="wrap mailora-wrap" dir="<?php echo esc_attr( I18n::direction() ); ?>">
			<header class="mailora-header">
				<div class="mailora-brand"><img src="<?php echo esc_url( MRN_MAILORA_URL . 'assets/images/mailora-logo.png' ); ?>" alt=""><div><strong>MRN <span>Mailora</span></strong><small><?php echo esc_html( $this->t( 'Smart Email Delivery', 'ارسال هوشمند ایمیل' ) ); ?></small></div></div>
				<nav>
					<?php
					foreach ( array(
						'dashboard' => array( $this->t( 'Dashboard', 'داشبورد' ), 'mrn-mailora' ),
						'settings'  => array( $this->t( 'Settings', 'تنظیمات' ), 'mrn-mailora-settings' ),
						'logs'      => array( $this->t( 'Logs', 'گزارش‌ها' ), 'mrn-mailora-logs' ),
						'tools'     => array( $this->t( 'Tools', 'ابزارها' ), 'mrn-mailora-tools' ),
					) as $key => $item ) {
						echo '<a class="' . ( $active === $key ? 'is-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $item[1] ) ) . '">' . esc_html( $item[0] ) . '</a>';
					}
					?>
				</nav>
				<span class="mailora-version">v<?php echo esc_html( MRN_MAILORA_VERSION ); ?></span>
			</header>
			<div class="mailora-page-title"><div><h1><?php echo esc_html( $title ); ?></h1><p><?php echo esc_html( $subtitle ); ?></p></div><span class="mailora-live"><i></i> <?php echo esc_html( $this->t( 'Mailora is active', 'Mailora فعال است' ) ); ?></span></div>
			<div id="mailora-toast" class="mailora-toast" role="status" hidden></div>
			<?php $this->oauth_notice(); ?>
		<?php
	}

	private function close_page(): void {
		echo '<footer class="mailora-footer"><span>' . esc_html( $this->t( 'MRN Mailora — built for reliable delivery', 'MRN Mailora — ساخته‌شده برای تحویل مطمئن' ) ) . '</span><a href="https://mehranmarandi.ir" target="_blank" rel="noopener">mehran marandi</a></footer></div>';
	}

	private function guard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html( $this->t( 'You do not have permission to access this page.', 'شما اجازه دسترسی به این بخش را ندارید.' ) ) );
		}
	}

	private function ajax_guard(): void {
		check_ajax_referer( 'mrn_mailora_admin', 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => $this->t( 'You do not have sufficient permission.', 'دسترسی کافی نیست.' ) ), 403 );
		}
	}

	private function has_spf( string $domain ): bool {
		if ( ! function_exists( 'dns_get_record' ) ) {
			return false;
		}
		$records = dns_get_record( $domain, DNS_TXT );
		foreach ( $records ? $records : array() as $record ) {
			if ( str_starts_with( strtolower( (string) ( $record['txt'] ?? '' ) ), 'v=spf1' ) ) {
				return true;
			}
		}
		return false;
	}

	private function initial( string $name ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $name, 0, 1 );
		}
		return substr( $name, 0, 1 );
	}

	private function oauth_notice(): void {
		$status = sanitize_key( wp_unslash( $_GET['mailora_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $status ) {
			return;
		}
		$messages = array(
			'oauth_connected' => array( false, $this->t( 'The account was connected securely.', 'حساب با موفقیت و به‌صورت امن متصل شد.' ) ),
			'oauth_expired'   => array( true, $this->t( 'The connection request expired. Please try again.', 'فرایند اتصال منقضی شده است؛ دوباره تلاش کنید.' ) ),
			'oauth_invalid'   => array( true, $this->t( 'The OAuth response was not valid.', 'پاسخ OAuth معتبر نبود.' ) ),
			'oauth_failed'    => array( true, $this->t( 'The account connection could not be completed.', 'اتصال حساب کامل نشد.' ) ),
		);
		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}
		$detail = sanitize_text_field( wp_unslash( $_GET['detail'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="mailora-result ' . ( $messages[ $status ][0] ? 'is-error' : '' ) . '">' . esc_html( $messages[ $status ][1] );
		if ( $detail ) {
			echo ' <small>' . esc_html( $detail ) . '</small>';
		}
		echo '</div>';
	}

	public function welcome_notice(): void {
		if ( ! get_transient( 'mrn_mailora_activated' ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		delete_transient( 'mrn_mailora_activated' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html( $this->t( 'MRN Mailora is ready.', 'MRN Mailora آماده است.' ) ) . '</strong> <a href="' . esc_url( admin_url( 'admin.php?page=mrn-mailora-settings' ) ) . '">' . esc_html( $this->t( 'Configure your email delivery method.', 'روش ارسال ایمیل را پیکربندی کنید.' ) ) . '</a></p></div>';
	}

	private function t( string $english, string $persian ): string {
		return I18n::translate( $english, $persian );
	}
}
