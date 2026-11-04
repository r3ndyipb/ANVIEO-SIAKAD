import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const notificationElement = document.querySelector('[data-auth-notification]');

if (notificationElement) {
	const notification = JSON.parse(notificationElement.textContent);

	Swal.fire({
		toast: true,
		position: 'top-end',
		icon: notification.icon,
		title: notification.title,
		text: notification.text,
		showConfirmButton: false,
		timer: 3500,
		timerProgressBar: true,
	});
}
