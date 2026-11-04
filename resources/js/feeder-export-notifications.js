import Swal from 'sweetalert2';

const pendingNotifications = window.simakFeederExportNotifications ||= [];

const showError = (message) => {
	if (!message) return;

	Swal.fire({
		icon: 'error',
		title: 'Export ke Feeder gagal',
		text: message,
		confirmButtonText: 'Tutup',
	});
};

document.addEventListener('simak:feeder-export-error', (event) => {
	const message = event.detail?.message;
	if (!message) return;

	const pendingIndex = pendingNotifications.indexOf(message);
	if (pendingIndex !== -1) pendingNotifications.splice(pendingIndex, 1);
	showError(message);
});

pendingNotifications.splice(0).forEach(showError);
