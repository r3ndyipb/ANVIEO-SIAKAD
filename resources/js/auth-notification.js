import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import '../css/auth-notification.css';

const notificationElement = document.querySelector('[data-auth-notification]');

if (notificationElement) {
	try {
		const notification = JSON.parse(notificationElement.textContent);
		const isSuccess = notification.icon === 'success';
		const isError = notification.icon === 'error';
		const isWarning = notification.icon === 'warning';

		Swal.fire({
			toast: true,
			position: 'bottom-end',
			icon: notification.icon,
			title: notification.title,
			text: notification.text,
			showConfirmButton: false,
			timer: 3500,
			timerProgressBar: false,
			background: isSuccess ? '#059669' : (isError ? '#dc2626' : (isWarning ? '#d97706' : '#ffffff')),
			color: isSuccess || isError || isWarning ? '#ffffff' : '#0f172a',
			iconColor: isSuccess || isError || isWarning ? '#ffffff' : undefined,
			customClass: {
				popup: `simak-auth-toast ${isSuccess ? 'simak-auth-toast-success' : ''} ${isError ? 'simak-auth-toast-error' : ''}`,
				title: 'simak-auth-toast-title',
				htmlContainer: 'simak-auth-toast-text',
			},
		});
	} catch (e) {
		console.error('Failed to parse auth notification', e);
	}
}
