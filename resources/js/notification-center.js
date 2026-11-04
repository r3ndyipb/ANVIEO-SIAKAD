document.querySelectorAll('[data-notification-center]').forEach((root) => {
	const trigger = root.querySelector('[data-notification-trigger]');
	const panel = root.querySelector('[data-notification-panel]');
	const list = root.querySelector('[data-notification-list]');
	const badge = root.querySelector('[data-notification-count]');
	const markAllButtons = [...root.querySelectorAll('[data-notification-mark-all]')];
	const triggerLabel = root.dataset.notificationLabel || 'Notifikasi';
	let requestBusy = false;

	const setCount = (count) => {
		const unread = Number(count) || 0;
		badge.textContent = unread > 99 ? '99+' : String(unread);
		badge.hidden = unread === 0;
		trigger.classList.toggle('has-unread', unread > 0);
		trigger.setAttribute('aria-label', unread > 0
			? `${triggerLabel}, ${unread} belum dibaca`
			: triggerLabel);
	};
	const showError = (message) => {
		const error = document.createElement('p');
		error.className = 'notification-error';
		error.setAttribute('role', 'alert');
		error.textContent = message;
		list.replaceChildren(error);
	};
	const request = async (url, method = 'GET') => {
		const response = await fetch(url, {
			method,
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				...(method !== 'GET' ? { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } : {}),
			},
		});
		const payload = await response.json();
		if (!response.ok) throw new Error(payload.message || `Notifikasi gagal dimuat (${response.status}).`);
		return payload;
	};
	const renderNotifications = (notifications, canMarkRead) => {
		if (!notifications.length) {
			const empty = document.createElement('div');
			empty.className = 'notification-empty';
			const message = document.createElement('p');
			message.textContent = root.dataset.emptyMessage || 'Belum ada notifikasi baru';
			empty.innerHTML = '<span class="notification-empty-check" aria-hidden="true">✓</span>';
			empty.append(message);
			list.replaceChildren(empty);
			return;
		}

		const items = notifications.map((notification) => {
			const item = document.createElement('div');
			item.className = `notification-item${notification.is_read ? '' : ' is-unread'}`;
			const contentLink = document.createElement(notification.url ? 'a' : 'div');
			contentLink.className = 'notification-item-content';
			if (notification.url) {
				contentLink.href = notification.url;
				contentLink.dataset.notificationId = notification.id;
				contentLink.dataset.notificationUrl = notification.url;
			}
			const content = document.createElement('span');
			content.className = 'notification-item-copy';
			const translatedNotification = window.simakTranslateNotification?.(notification) || notification;
			const title = document.createElement('strong');
			title.textContent = translatedNotification.title;
			const message = document.createElement('span');
			message.textContent = translatedNotification.message;
			const date = document.createElement('small');
			date.textContent = new Date(`${notification.created_at.replace(' ', 'T')}+09:00`).toLocaleString(
				window.simakLocale === 'en' ? 'en-US' : 'id-ID',
				{
				timeZone: 'Asia/Jayapura',
				dateStyle: 'medium',
				timeStyle: 'short',
				},
			);
			content.append(title, message, date);
			contentLink.append(content);
			if (!notification.is_read) {
				const dot = document.createElement('i');
				dot.className = 'notification-unread-dot';
				dot.setAttribute('aria-hidden', 'true');
				item.append(dot);
			}
			item.append(contentLink);
			if (!notification.is_read && canMarkRead) {
				const markRead = document.createElement('button');
				markRead.type = 'button';
				markRead.className = 'notification-item-action';
				markRead.dataset.notificationMarkId = notification.id;
				markRead.textContent = 'Tandai sudah dibaca';
				item.append(markRead);
			}
			return item;
		});
		list.replaceChildren(...items);
	};
	const load = async () => {
		if (requestBusy) return;
		requestBusy = true;
		try {
			const result = await request(root.dataset.notificationsUrl);
			setCount(result.unread_count);
			markAllButtons.forEach((button) => {
				button.hidden = result.can_mark_read !== true;
			});
			renderNotifications(result.notifications, result.can_mark_read === true);
		} catch (error) {
			showError(error.message);
		} finally {
			requestBusy = false;
		}
	};
	document.addEventListener('simak:translations-loaded', load);

	trigger.addEventListener('click', () => {
		const open = trigger.getAttribute('aria-expanded') !== 'true';
		trigger.setAttribute('aria-expanded', String(open));
		panel.hidden = !open;
		if (open) load();
	});
	markAllButtons.forEach((markAll) => markAll.addEventListener('click', async () => {
		markAllButtons.forEach((button) => { button.disabled = true; });
		try {
			const result = await request(root.dataset.markAllReadUrl, 'PATCH');
			setCount(result.unread_count);
			const refreshed = await request(root.dataset.notificationsUrl);
			setCount(refreshed.unread_count);
			renderNotifications(refreshed.notifications, refreshed.can_mark_read === true);
		} catch (error) {
			showError(error.message);
		} finally {
			markAllButtons.forEach((button) => { button.disabled = false; });
		}
	}));
	list.addEventListener('click', async (event) => {
		const markButton = event.target.closest('[data-notification-mark-id]');
		const link = event.target.closest('[data-notification-id]');
		if (!markButton && !link) return;
		event.preventDefault();
		const notificationId = markButton?.dataset.notificationMarkId || link.dataset.notificationId;
		const target = markButton ? '' : link.dataset.notificationUrl;
		try {
			if (markButton) markButton.disabled = true;
			await request(root.dataset.markReadUrlTemplate.replace('__NOTIFICATION__', encodeURIComponent(notificationId)), 'PATCH');
			if (target) {
				window.location.assign(target);
			} else {
				await load();
			}
		} catch (error) {
			showError(error.message);
		} finally {
			if (markButton) markButton.disabled = false;
		}
	});
	document.addEventListener('click', (event) => {
		if (!root.contains(event.target)) {
			trigger.setAttribute('aria-expanded', 'false');
			panel.hidden = true;
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !panel.hidden) {
			trigger.setAttribute('aria-expanded', 'false');
			panel.hidden = true;
			trigger.focus();
		}
	});

	load();
	window.setInterval(load, 60000);
});
