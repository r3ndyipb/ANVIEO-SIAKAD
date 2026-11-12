import Swal from 'sweetalert2';

const showLmsNotification = (notification) => {
	const translate = (text) => window.simakTranslate?.(text) || text;
	Swal.fire({
		toast: true,
		position: 'bottom-end',
		icon: notification.icon,
		title: translate(notification.title),
		text: translate(notification.text),
		confirmButtonText: translate('Mengerti'),
		cancelButtonText: translate('Batal'),
		denyButtonText: translate('Tidak'),
		showConfirmButton: false,
		timer: 4500,
		timerProgressBar: false,
	});
};

window.simakLmsNotify = showLmsNotification;
document.addEventListener('simak:lms-notification', (event) => {
	if (event.detail?.text) showLmsNotification(event.detail);
});

const notificationElement = document.querySelector('[data-lms-notification]');

if (notificationElement) {
	const notification = JSON.parse(notificationElement.textContent);
	if (window.simakLocale === 'en' && ! window.simakTranslate) {
		let shown = false;
		const showOnce = () => {
			if (shown) return;
			shown = true;
			showLmsNotification(notification);
		};
		document.addEventListener('simak:translations-loaded', showOnce, {once: true});
		window.setTimeout(showOnce, 1000);
	} else {
		showLmsNotification(notification);
	}
}
