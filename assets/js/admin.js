(() => {
	'use strict';

	const cfg = window.MRNMailora || {};
	const $ = (selector, scope = document) => scope.querySelector(selector);
	const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

	const toast = (message, error = false) => {
		const el = $('#mailora-toast');
		if (!el) return;
		el.textContent = message;
		el.classList.toggle('is-error', error);
		el.hidden = false;
		window.clearTimeout(toast.timer);
		toast.timer = window.setTimeout(() => { el.hidden = true; }, 4500);
	};

	const request = async (action, values = {}) => {
		const body = new URLSearchParams({ action, nonce: cfg.nonce, ...values });
		const response = await fetch(cfg.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			credentials: 'same-origin',
			body,
		});
		const json = await response.json().catch(() => ({ success: false, data: { message: cfg.i18n.error } }));
		if (!response.ok || !json.success) {
			throw new Error(json?.data?.message || cfg.i18n.error);
		}
		return json.data;
	};

	const settingsForm = $('#mailora-settings-form');
	if (settingsForm) {
		const saveState = $('#mailora-save-state');
		const chooseProvider = (id) => {
			$$('[data-provider-card]').forEach((el) => el.classList.toggle('is-active', el.dataset.providerCard === id));
			$$('[data-provider-config]').forEach((el) => el.classList.toggle('is-active', el.dataset.providerConfig === id));
		};

		$$('input[name="provider"]', settingsForm).forEach((radio) => {
			radio.addEventListener('change', () => chooseProvider(radio.value));
		});
		settingsForm.addEventListener('input', () => {
			if (saveState) saveState.textContent = 'تغییرات هنوز ذخیره نشده‌اند.';
		});
		settingsForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			const button = $('button[type="submit"]', settingsForm);
			button.disabled = true;
			button.textContent = cfg.i18n.working;
			const params = new URLSearchParams();
			params.set('action', 'mrn_mailora_save_settings');
			params.set('nonce', cfg.nonce);
			new FormData(settingsForm).forEach((value, key) => {
				const nested = key.replace(/\]/g, '').replace(/\[/g, '][');
				params.append(`settings[${nested}]`, value);
			});

			try {
				const response = await fetch(cfg.ajaxUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					credentials: 'same-origin',
					body: params,
				});
				const json = await response.json();
				if (!response.ok || !json.success) throw new Error(json?.data?.message || cfg.i18n.error);
				toast(json.data.message || cfg.i18n.saved);
				if (saveState) saveState.textContent = 'همه تغییرات ذخیره شده‌اند.';
			} catch (error) {
				toast(error.message, true);
			} finally {
				button.disabled = false;
				button.textContent = 'ذخیره تنظیمات';
			}
		});
	}

	const testForm = $('#mailora-test-form');
	if (testForm) {
		testForm.addEventListener('submit', async (event) => {
			event.preventDefault();
			const button = $('button[type="submit"]', testForm);
			const result = $('#mailora-test-result');
			button.disabled = true;
			button.textContent = cfg.i18n.working;
			result.hidden = true;
			try {
				const data = await request('mrn_mailora_test_email', { to: $('[name="to"]', testForm).value });
				result.textContent = data.message + (data.remoteId ? ` شناسه: ${data.remoteId}` : '');
				result.classList.remove('is-error');
				result.hidden = false;
				toast(data.message);
			} catch (error) {
				result.textContent = error.message;
				result.classList.add('is-error');
				result.hidden = false;
				toast(error.message, true);
			} finally {
				button.disabled = false;
				button.textContent = 'ارسال آزمایشی';
			}
		});
	}

	const clearLogs = $('#mailora-clear-logs');
	if (clearLogs) {
		clearLogs.addEventListener('click', async () => {
			if (!window.confirm('تمام گزارش‌های ایمیل پاک شوند؟ این عملیات قابل بازگشت نیست.')) return;
			clearLogs.disabled = true;
			try {
				const data = await request('mrn_mailora_clear_logs');
				toast(data.message);
				window.setTimeout(() => window.location.reload(), 600);
			} catch (error) {
				toast(error.message, true);
				clearLogs.disabled = false;
			}
		});
	}

	const diagnostics = $('#mailora-run-diagnostics');
	if (diagnostics) {
		diagnostics.addEventListener('click', async () => {
			const list = $('#mailora-diagnostics-result');
			diagnostics.disabled = true;
			diagnostics.textContent = cfg.i18n.working;
			try {
				const data = await request('mrn_mailora_diagnostics');
				list.innerHTML = '';
				data.checks.forEach((check) => {
					const row = document.createElement('div');
					row.className = `mailora-diagnostic-item${check.ok ? '' : ' is-failed'}`;
					const label = document.createElement('span');
					const state = document.createElement('i');
					label.textContent = check.label;
					state.textContent = `${check.ok ? '✓' : '×'} ${check.detail || ''}`;
					row.append(label, state);
					list.append(row);
				});
			} catch (error) {
				toast(error.message, true);
			} finally {
				diagnostics.disabled = false;
				diagnostics.textContent = 'اجرای دوباره بررسی';
			}
		});
	}

	$$('[data-copy]').forEach((button) => {
		button.addEventListener('click', async () => {
			try {
				await navigator.clipboard.writeText(button.dataset.copy);
				toast('نشانی در حافظه کپی شد.');
			} catch {
				toast('کپی خودکار ممکن نبود.', true);
			}
		});
	});
})();
