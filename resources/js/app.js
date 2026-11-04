import './bootstrap';
import './auth-notification.js';
import './feeder-export-notifications.js';
import './lms-notifications.js';
import './notification-center';
import './page-loader.js';
import '../css/ekurikulum.css';

import DataTable from 'datatables.net-dt';
import 'datatables.net-buttons-dt';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import TomSelect from 'tom-select';
import JSZip from 'jszip';
import { ArrowLeft, Award, BadgeCheck, BookOpen, BookOpenCheck, Building2, CalendarDays, ChartNoAxesColumnIncreasing, CircleCheck, CircleMinus, CircleX, ClipboardCheck, ClipboardList, ClipboardPlus, Database, Eye, FileDown, FileSpreadsheet, FileText, GraduationCap, HeartHandshake, KeyRound, LayoutDashboard, ListChecks, LockOpen, LogOut, MapPinned, Pencil, Plus, Printer, RefreshCw, RotateCcw, Save, ScrollText, Search, Settings, ShieldCheck, Trash2, Upload, UserPlus, UserRoundPen, WalletCards, X, createElement } from 'lucide';
import 'datatables.net-dt/css/dataTables.dataTables.css';
import 'datatables.net-buttons-dt/css/buttons.dataTables.css';
import 'tom-select/dist/css/tom-select.css';

DataTable.Buttons.jszip(JSZip);

const translatePage = (uiTranslations) => {
	const lookupTranslation = (text) => {
		if (uiTranslations[text]) return uiTranslations[text];

		const dynamicPrefixes = [
			['Pesan Bimbingan Baru: ', 'New Supervision Message: '],
			['Undangan Ujian Tesis & Disertasi: ', 'Thesis & Dissertation Examination Invitation: '],
		];
		for (const [source, translated] of dynamicPrefixes) {
			if (text.startsWith(source)) return translated + text.slice(source.length);
		}

		const dynamicPatterns = [
			[/^Tahap (.+) dibuka kembali\. Anda dapat melanjutkan atau mengulangi tahap ini\.$/, (_, stage) => `The ${stage} stage has been reopened. You can continue or restart it.`],
			[/^Tahap (.+) direset\. Proses impor ulang siap dimulai\.$/, (_, stage) => `The ${stage} stage has been reset. The import can be started again.`],
			[/^Tahap sebelumnya selesai\. Siap melanjutkan ke (.+)\.$/, (_, stage) => `The previous stage is complete. Ready to continue to ${stage}.`],
			[/^Tahap (.+) akan diulang dari awal dan data tahap tersebut dapat diperbarui\.$/, (_, stage) => `The ${stage} stage will be restarted from the beginning, and its data may be updated.`],
			[/^Masih ada (\d+) Program Studi yang belum memiliki Fakultas valid\..*$/, (_, count) => `There are still ${count} study programs without a valid faculty assignment. Assign a faculty to every program before continuing.`],
			[/^Masih ada (\d+) Program Studi tanpa Fakultas yang valid\..*$/, (_, count) => `There are still ${count} study programs without a valid faculty assignment. Assign a faculty to every program before returning to the wizard.`],
			[/^Feeder gagal \(([^)]+)\): (.*)$/, (_, code, description) => `Feeder request failed (${code}): ${description}`],
			[/^Tahap (.+)$/, (_, stage) => `Stage ${stage}`],
			[/^Program Studi yang tidak lagi tersedia dilewati\.$/, () => 'Study programs that are no longer available were skipped.'],
			[/^Riwayat mahasiswa konflik untuk ID (.+) dilewati\.$/, (_, id) => `A conflicting history for student ID ${id} was skipped.`],
			[/^Riwayat pendidikan mahasiswa (.+) tidak ditemukan\.$/, (_, id) => `Education history for student ${id} was not found.`],
			[/^Riwayat pendidikan mahasiswa (.+) diperbarui dari checkpoint lama\.$/, (_, id) => `Education history for student ${id} was updated from an older checkpoint.`],
			[/^Riwayat checkpoint lama selesai; melanjutkan pagination profil mahasiswa\.$/, () => 'Older checkpoint history is complete; continuing student profile pagination.'],
			[/^Riwayat Program Studi selesai; melanjutkan ke Program Studi berikutnya\.$/, () => 'Study program history is complete; continuing with the next study program.'],
			[/^Perguruan tinggi tanpa kode dilewati pada impor All Prodi\.$/, () => 'Universities without a code were skipped during the all-programs import.'],
			[/^Referensi Kebutuhan Khusus selesai\.$/, () => 'Special needs references are complete.'],
			[/^Tahap koneksi Feeder telah diverifikasi\.$/, () => 'The Feeder connection stage has been verified.'],
			[/^(\d+) konflik ID\/NIM dilewati: (.*)\.$/, (_, count, details) => `${count} ID/student number conflicts skipped: ${details}.`],
			[/^(\d+) konflik ID\/NIM dilewati\.$/, (_, count) => `${count} ID/student number conflicts skipped.`],
			[/^(\d+) penugasan dengan Program Studi\/Fakultas yang belum valid dilewati\.$/, (_, count) => `${count} assignments with an invalid study program or faculty were skipped.`],
			[/^(\d+) riwayat tidak ditemukan; (\d+) pencarian gagal dengan error 121\.$/, (_, missing, failed) => `${missing} histories not found; ${failed} lookups failed with error 121.`],
			[/^(\d+) pencarian riwayat gagal dengan error 121\.$/, (_, failed) => `${failed} history lookups failed with error 121.`],
			[/^(\d+) riwayat pendidikan tidak ditemukan\.$/, (_, missing) => `${missing} education histories were not found.`],
			[/^(\d+) ID status mahasiswa yang kosong diperbaiki\.$/, (_, count) => `${count} missing student status IDs were fixed.`],
			[/^Aset koordinat GetWilayah tidak ditemukan: (.+)$/, (_, path) => `GetWilayah coordinate asset not found: ${path}`],
			[/^Aset koordinat GetWilayah tidak dapat dibaca: (.+)$/, (_, path) => `GetWilayah coordinate asset could not be read: ${path}`],
			[/^Aset koordinat GetWilayah tidak memiliki nilai numerik (.+) untuk kode (.+)\.$/, (_, column, code) => `GetWilayah coordinate asset has no numeric ${column} value for code ${code}.`],
			[/^Respons (.+) melebihi batas batch (\d+) baris\.$/, (_, action, limit) => `${action} response exceeds the batch limit of ${limit} rows.`],
			[/^Pencarian riwayat mahasiswa (.+) gagal dengan error 121; dilanjutkan ke mahasiswa berikutnya\.$/, (_, studentId) => `Education history lookup for student ${studentId} failed with error 121; continuing with the next student.`],
			[/^Negara mahasiswa tidak ditemukan pada referensi Feeder yang sudah diimpor: (.+)$/, (_, country) => `Student country was not found in the imported Feeder references: ${country}`],
			[/^Kunci gabungan All Prodi belum diterapkan\. Jalankan SQL upgrade (.+)\.$/, (_, path) => `The All Programs composite key has not been applied. Run the SQL upgrade at ${path}.`],
			[/^Dokumen dicetak pada (.+)\.$/, (_, date) => `Document printed on ${date}.`],
			[/^(.+) · Verifikasi Dokumen Digital$/, (_, institution) => `${institution} · Digital Document Verification`],
			[/^· Waktu: (.+) s\.d\. (.+) WIT$/, (_, start, end) => `· Time: ${start} to ${end} WIT`],
			[/^Mahasiswa belum melakukan registrasi ulang pada (.+)\. Pemrograman KRS belum dapat dilakukan\.$/, (_, calendar) => `The student has not re-enrolled for ${calendar}. Study plan entry is unavailable.`],
			[/^Masa pengisian KRS adalah (.+) s\.d\. (.+)\. Perubahan KRS saat ini tidak tersedia\.$/, (_, start, end) => `The study plan entry period is ${start} to ${end}. Changes are currently unavailable.`],
			[/^Jumlah mahasiswa berdasarkan tahun angkatan dari periode masuk, (.+) – (.+)\.$/, (_, start, end) => `Student counts by cohort year based on admission period, ${start}–${end}.`],
			[/^Motto default: (.+)\. Untuk menyimpan motto kustom, jalankan SQL upgrade motto terlebih dahulu\.$/, (_, motto) => `Default motto: ${motto}. To save a custom motto, first run the motto database upgrade.`],
			[/^Pendaftaran dibuka sampai (.+)\.$/, (_, date) => `Registration is open until ${date}.`],
			[/^Pendaftaran sampai (.+)\.$/, (_, date) => `Registration is open until ${date}.`],
			[/^Periode dibuka sampai (.+)\. Mahasiswa yang sudah pernah diusulkan pada periode ini tidak ditampilkan kembali\.$/, (_, date) => `The period is open until ${date}. Students already proposed for this period will not be shown again.`],
			[/^Total bobot: (.+)%$/, (_, total) => `Total weight: ${total}%`],
			[/^Total mahasiswa status A, N, C, dan M: (.+)\. Peta menampilkan mahasiswa dengan data provinsi; belum terpetakan\.$/, (_, total) => `Total students with status A, N, C, and M: ${total}. The map shows students with province data; unmapped students are excluded.`],
			[/^Total pertanyaan: (.+) · Aspek hasil mengikuti ID instrumen 1–6\.$/, (_, total) => `Total questions: ${total} · Result aspects follow instrument IDs 1–6.`],
			[/^Transkrip saat ini: (.+) SKS, mutu (.+), IPK (.+)\. Nilai terbaik tiap mata kuliah digunakan; penghapusan menghilangkan semua percobaan mata kuliah terpilih\.$/, (_, credits, quality, gpa) => `Current transcript: ${credits} credits, quality points ${quality}, GPA ${gpa}. The best grade for each course is used; removing a course deletes all attempts for that course.`],
			[/^Terisi Penuh \( (.+) \/ (.+) \)$/, (_, filled, capacity) => `Full (${filled} / ${capacity})`],
			[/^Jadwal ini sudah dikontrak matakuliah oleh (.+) pada KRS\. Mata kuliah, ruang, hari, dan jam dikunci; dosen pengampu, kelas, serta metadata Kampus Merdeka masih bisa diubah\.$/, (_, student) => `This schedule has already been selected by ${student} in a study plan. The course, room, day, and time are locked; the lecturer, class, and Kampus Merdeka metadata can still be changed.`],
			[/^Yakin ingin menghapus Pertemuan (.+)\?$/, (_, meeting) => `Are you sure you want to delete Meeting ${meeting}?`],
			[/^Yakin ingin menghapus diskusi “(.+)” beserta percakapannya\?$/, (_, title) => `Are you sure you want to delete the discussion “${title}” and its conversation?`],
			[/^Yakin ingin menghapus materi “(.+)”\?$/, (_, title) => `Are you sure you want to delete the material “${title}”?`],
			[/^Yakin ingin menghapus tugas “(.+)”\?$/, (_, title) => `Are you sure you want to delete the assignment “${title}”?`],
			[/^Yakin ingin menghapus ini\?$/, () => 'Are you sure you want to delete this?'],
			[/^(\d+) KRS ditambahkan, (\d+) nilai KRS diperbarui, (\d+) baris nilai dilewati, (\d+) pengesahan dibuat(?:, (\d+) jadwal feeder dibuat)?\.(?: Penyebab dilewati: (.*)\.)?( Seluruh nilai mahasiswa selesai diproses\.)?$/, (_, created, updated, skipped, approvals, schedules = '', reasons = '', complete = '') => {
				const translatedReasons = reasons
					.replaceAll('respons tidak valid', 'invalid response')
					.replaceAll('field nilai/identitas wajib kosong atau tidak valid', 'required grade or identity fields are empty or invalid');
				return `${created} study plans added, ${updated} study plan grades updated, ${skipped} grade rows skipped, and ${approvals} approvals created.${schedules !== '' ? ` ${schedules} feeder schedules created.` : ''}${translatedReasons ? ` Skipped because: ${translatedReasons}.` : ''}${complete ? ' All student grades have been processed.' : ''}`;
			}],
			[/^(\d+) profil mahasiswa disimpan setelah pencarian riwayat paralel\.(?: (\d+) riwayat tidak ditemukan; (\d+) pencarian gagal dengan error 121\.)?(?: (.*))?$/, (_, saved, missing = '0', failed = '0', extra = '') => `${saved} student profiles saved after parallel history lookups.${Number(missing) ? ` ${missing} histories not found; ${failed} lookups failed with error 121.` : ''}${extra ? ` ${extra.split(/(?<=\\.)\\s+/).map((part) => lookupTranslation(part) || part).join(' ')}` : ''}`],
			[/^(\d+) kalender ditambahkan, (\d+) diperbarui, (\d+) dilewati\.( Seluruh data kalender akademik selesai diproses\.)?$/, (_, created, updated, skipped, complete = '') => `${created} academic calendars added, ${updated} updated, and ${skipped} skipped.${complete ? ' All academic calendar data has been processed.' : ''}`],
			[/^Penugasan dosen terbaru selesai diproses\.(?: (\d+) penugasan dengan Program Studi\/Fakultas yang belum valid dilewati\.)?$/, (_, skipped = '0') => `The latest lecturer assignments have been processed.${Number(skipped) ? ` ${skipped} assignments with an invalid study program or faculty were skipped.` : ''}`],
			[/^Data mahasiswa dan riwayat pendidikan selesai diimpor\.(?: (\d+) konflik ID\/NIM dilewati\.)?(?: (\d+) pencarian riwayat gagal dengan error 121\.)?(?: (\d+) riwayat pendidikan tidak ditemukan\.)?$/, (_, conflicts = '0', failures = '0', missing = '0') => `Student data and education histories have been imported.${Number(conflicts) ? ` ${conflicts} ID/student number conflicts were skipped.` : ''}${Number(failures) ? ` ${failures} history lookups failed with error 121.` : ''}${Number(missing) ? ` ${missing} education histories were not found.` : ''}`],
			[/^(\d+) kalender ditambahkan, (\d+) diperbarui, (\d+) dilewati\.$/, (_, created, updated, skipped) => `${created} academic calendars added, ${updated} updated, and ${skipped} skipped.`],
			[/^(\d+) baris diproses, (\d+) baris dilewati\.$/, (_, processed, skipped) => `${processed} rows processed, ${skipped} rows skipped.`],
			[/^Waktu batch server terakhir: (.+) detik\.$/, (_, seconds) => `Last server batch duration: ${seconds} seconds.`],
			[/^Selamat datang,\s*(.+)\.$/, (_, name) => `Welcome, ${name}.`],
		];
		for (const [pattern, translate] of dynamicPatterns) {
			const match = text.match(pattern);
			if (match) return translate(...match);
		}

		const thesisImport = text.match(/^Import selesai: (\d+) baris diproses, (\d+) baris dilewati\.$/);
		if (thesisImport) return `Import complete: ${thesisImport[1]} rows processed, ${thesisImport[2]} rows skipped.`;

		const notificationFailure = text.match(/^Notifikasi gagal dimuat \((\d+)\)\.$/);
		if (notificationFailure) return `Failed to load notifications (${notificationFailure[1]}).`;

		return null;
	};
	const translateText = (text) => lookupTranslation(text) || text;
	window.simakTranslate = translateText;
	window.simakTranslateNotification = (notification) => {
		const translateMessagePrefix = (text) => {
			const prefixes = Object.keys(uiTranslations)
				.filter((key) => key.length > 12 && text.startsWith(key))
				.sort((left, right) => right.length - left.length);
			if (prefixes.length === 0) return text;
			const prefix = prefixes[0];

			return `${uiTranslations[prefix]}${text.slice(prefix.length)}`;
		};
		const translateNotificationMessage = (text) => {
			const titleProposal = text.match(/^Usulan judul baru dari (.+) \(([^)]+)\): (.*)$/);
			if (titleProposal) {
				text = `New title proposal from ${titleProposal[1]} (${titleProposal[2]}): ${titleProposal[3]}`;
			}
			const gradeRevision = text.match(/^(.*?) telah menginput nilai dan memberikan revisi untuk Ujian (.+?) tesis & disertasi Anda\./);
			if (!titleProposal && gradeRevision) {
				text = `${gradeRevision[1]} entered a grade and provided revisions for your ${gradeRevision[2]} thesis or dissertation examination.${text.slice(gradeRevision[0].length)}`;
			} else if (!titleProposal) {
				text = translateMessagePrefix(text);
			}

			const detailsStart = text.indexOf(' NIM: ');
			if (detailsStart === -1) return text;
			const intro = text.slice(0, detailsStart + 1);
			const details = text.slice(detailsStart + 1).replace(
				/(^| · )(NIM|Nama mahasiswa|Nama|Judul tesis & disertasi|Judul|Tanggal ajuan|Tanggal verifikasi|Komentar \/ catatan prodi|Dosen|Jenis ujian|Program studi|Jadwal|Ruang|Peran):/g,
				(match, separator, label) => `${separator}${translateText(`${label}:`).replace(/:$/, '')}:`,
			);

			return `${intro}${details}`;
		};

		return {
			...notification,
			title: translateText(notification.title),
			message: translateNotificationMessage(notification.message),
		};
	};
	const translatableAttributes = ['aria-label', 'title', 'placeholder', 'data-empty-message', 'data-notification-label', 'data-confirm-title', 'data-confirm-message'];
	const translateTextNode = (node) => {
		const source = node.nodeValue;
		const text = source.trim();
		let translation = lookupTranslation(text);
		if (!translation) {
			const welcome = text.match(/^Selamat datang,\s*(.+)\.$/);
			const year = text.match(/^Tahun\s+(.+)$/);
			const week = text.match(/^Minggu ke-(\d+)$/);
			const distributedCourses = text.match(/^(\d+)\s+mata kuliah tersebar$/i);
			const financeCategoryUpload = text.match(/^Upload selesai: (\d+) data diperbarui, (\d+) NIM tidak ditemukan, (\d+) baris dilewati\.$/);
			const financeIpiUpload = text.match(/^Upload selesai: (\d+) dibuat, (\d+) nominal diperbarui, (\d+) NIM tidak ditemukan, (\d+) baris dilewati\.$/);
			const financeIpiSync = text.match(/^Sinkronisasi selesai: (\d+) dibuat, (\d+) diperbarui, (\d+) lunas dipertahankan, (\d+) tidak cocok\.$/);
			const financeUktGeneration = text.match(/^Generate selesai: (\d+) tagihan dibuat, (\d+) diperbarui, (\d+) dilewati\.$/);
			const lmsManagement = text.match(/^Kelola (.+) pada mata kuliah yang Anda ampu\.$/);
			const lmsClassOption = text.match(/^(.*)\(Kelas (.+)\)$/);
			const lmsClassLabel = text.match(/^Kelas (.+)$/);
			translation = welcome ? `Welcome, ${welcome[1]}.`
				: year ? `Year ${year[1]}`
					: week ? `Week ${week[1]}`
						: distributedCourses ? `${distributedCourses[1]} distributed courses`
							: financeCategoryUpload ? `Upload complete: ${financeCategoryUpload[1]} records updated, ${financeCategoryUpload[2]} student IDs not found, ${financeCategoryUpload[3]} rows skipped.`
								: financeIpiUpload ? `Upload complete: ${financeIpiUpload[1]} records created, ${financeIpiUpload[2]} amounts updated, ${financeIpiUpload[3]} student IDs not found, ${financeIpiUpload[4]} rows skipped.`
									: financeIpiSync ? `Synchronization complete: ${financeIpiSync[1]} records created, ${financeIpiSync[2]} updated, ${financeIpiSync[3]} paid records preserved, ${financeIpiSync[4]} unmatched.`
										: financeUktGeneration ? `Generation complete: ${financeUktGeneration[1]} bills created, ${financeUktGeneration[2]} updated, ${financeUktGeneration[3]} skipped.`
											: lmsClassOption ? `${lmsClassOption[1]}(Class ${lmsClassOption[2]})`
												: lmsClassLabel ? `Class ${lmsClassLabel[1]}`
													: null;
			if (!translation && lmsManagement) {
				const section = lmsManagement[1][0].toUpperCase() + lmsManagement[1].slice(1);
				translation = `Manage ${(uiTranslations[section] || section).toLowerCase()} for the courses you teach.`;
			}
		}
		if (!translation) return;
		node.nodeValue = source.replace(text, translation);
	};
	const translateAttributes = (element) => {
		translatableAttributes.forEach((attribute) => {
			if (!element.hasAttribute(attribute)) return;
			const value = element.getAttribute(attribute);
			const activityCount = value.match(/^(.+): (\d+)$/);
			const translation = lookupTranslation(value)
				|| (activityCount ? `${uiTranslations[activityCount[1]] || activityCount[1]}: ${activityCount[2]}` : null);
			if (translation) element.setAttribute(attribute, translation);
		});
	};
	const translateElement = (element) => {
		if (!(element instanceof Element) || ['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE'].includes(element.tagName)) return;
		const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, {
			acceptNode: (node) => ['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE'].includes(node.parentElement?.tagName)
				? NodeFilter.FILTER_REJECT
				: NodeFilter.FILTER_ACCEPT,
		});
		while (walker.nextNode()) translateTextNode(walker.currentNode);
		translateAttributes(element);
		element.querySelectorAll('*').forEach(translateAttributes);
	};
	const title = document.querySelector('title');
	if (title) {
		const [titleLabel, ...titleSuffix] = title.textContent.split(' | ');
		const translatedFullTitle = uiTranslations[title.textContent];
		const translatedTitle = uiTranslations[titleLabel];
		if (translatedFullTitle) title.textContent = translatedFullTitle;
		else if (translatedTitle) title.textContent = [translatedTitle, ...titleSuffix].join(' | ');
	}
	translateElement(document.body);
	new MutationObserver((records) => {
		records.forEach((record) => {
			if (record.type === 'characterData') translateTextNode(record.target);
			if (record.type === 'attributes') translateElement(record.target);
			record.addedNodes?.forEach((node) => {
				if (node.nodeType === Node.ELEMENT_NODE) translateElement(node);
				if (node.nodeType === Node.TEXT_NODE) translateTextNode(node);
			});
		});
	}).observe(document.body, {
		subtree: true,
		childList: true,
		characterData: true,
		attributes: true,
		attributeFilter: translatableAttributes,
	});
	document.dispatchEvent(new Event('simak:translations-loaded'));
};
if (window.simakLocale === 'en' && window.simakTranslationsUrl) {
	fetch(window.simakTranslationsUrl, { headers: { Accept: 'application/json' } })
		.then((response) => {
			if (!response.ok) throw new Error(`Translation request failed with status ${response.status}.`);
			return response.json();
		})
		.then((translations) => translatePage(translations))
		.catch((error) => console.error('The English interface translations could not be loaded.', error));
}

document.querySelectorAll('[data-thesis-role-tabs]').forEach((tablistRoot) => {
	const tabs = [...tablistRoot.querySelectorAll('[data-thesis-role-tab]')];
	const panels = [...tablistRoot.querySelectorAll('[data-thesis-role-panel]')];
	const activateTab = (tab) => {
		tabs.forEach((item) => {
			const active = item === tab;
			item.setAttribute('aria-selected', String(active));
			item.tabIndex = active ? 0 : -1;
		});
		panels.forEach((panel) => {
			panel.hidden = panel.dataset.thesisRolePanel !== tab.dataset.thesisRoleTab;
		});
		window.setTimeout(() => DataTable.tables({ api: true }).columns.adjust(), 0);
	};

	tabs.forEach((tab, index) => {
		tab.addEventListener('click', () => activateTab(tab));
		tab.addEventListener('keydown', (event) => {
			const targetIndex = event.key === 'ArrowRight'
				? (index + 1) % tabs.length
				: event.key === 'ArrowLeft'
					? (index - 1 + tabs.length) % tabs.length
					: event.key === 'Home'
						? 0
						: event.key === 'End'
							? tabs.length - 1
							: -1;
			if (targetIndex < 0) return;
			event.preventDefault();
			tabs[targetIndex].focus();
			activateTab(tabs[targetIndex]);
		});
	});
});

document.querySelectorAll('[data-title-validation-status]').forEach((statusSelect) => {
	const form = statusSelect.closest('form');
	const assignmentFields = [...(form?.querySelectorAll('[data-title-assignment-fields]') || [])];
	const updateAssignmentFields = () => {
		const accepted = statusSelect.value === '1';
		assignmentFields.forEach((field) => {
			field.hidden = !accepted;
			const select = field.querySelector('select');
			if (select) select.required = accepted && select.name === 'pembimbing1';
		});
	};
	statusSelect.addEventListener('change', updateAssignmentFields);
	updateAssignmentFields();
});

const iconNodes = {
	'arrow-left': ArrowLeft,
	award: Award,
	'badge-check': BadgeCheck,
	'book-open': BookOpen,
	'book-open-check': BookOpenCheck,
	'building-2': Building2,
	'calendar-days': CalendarDays,
	'chart-no-axes-column-increasing': ChartNoAxesColumnIncreasing,
	'circle-check': CircleCheck,
	'circle-minus': CircleMinus,
	'circle-x': CircleX,
	'clipboard-check': ClipboardCheck,
	'clipboard-list': ClipboardList,
	'clipboard-plus': ClipboardPlus,
	database: Database,
	eye: Eye,
	'file-down': FileDown,
	'file-spreadsheet': FileSpreadsheet,
	'file-text': FileText,
	'graduation-cap': GraduationCap,
	'heart-handshake': HeartHandshake,
	'key-round': KeyRound,
	'layout-dashboard': LayoutDashboard,
	'list-checks': ListChecks,
	'lock-open': LockOpen,
	'log-out': LogOut,
	'map-pinned': MapPinned,
	pencil: Pencil,
	plus: Plus,
	printer: Printer,
	'refresh-cw': RefreshCw,
	'rotate-ccw': RotateCcw,
	save: Save,
	'scroll-text': ScrollText,
	search: Search,
	settings: Settings,
	'shield-check': ShieldCheck,
	'trash-2': Trash2,
	upload: Upload,
	'user-plus': UserPlus,
	'user-round-pen': UserRoundPen,
	'wallet-cards': WalletCards,
	x: X,
};

function renderIcons(root = document) {
	root.querySelectorAll('[data-lucide]').forEach((placeholder) => {
		const iconNode = iconNodes[placeholder.dataset.lucide];

		if (iconNode) {
			placeholder.replaceWith(createElement(iconNode, {
				width: 16,
				height: 16,
				'aria-hidden': 'true',
				focusable: 'false',
			}));
		}
	});
}

if (document.body.dataset.routeName?.startsWith('ekurikulum.')) {
	document.querySelectorAll('.simak-content .button').forEach((button) => {
		if (button.querySelector('[data-lucide], svg')) return;
		const label = button.textContent.trim().toLowerCase();
		const icon = label.match(/tambah|buat|add/) ? 'plus'
			: label.match(/ubah|edit/) ? 'pencil'
				: label.match(/simpan|save|impor/) ? 'save'
					: label.match(/hapus|delete/) ? 'trash-2'
						: label.match(/unduh|download|template/) ? 'file-down'
							: label.match(/cetak|pdf/) ? 'printer'
								: label.match(/kembali|batal/) ? 'arrow-left'
									: label.match(/final|sahkan/) ? 'circle-check'
										: label.match(/kelola|susun/) ? 'list-checks'
											: null;
		if (!icon) return;
		const placeholder = document.createElement('span');
		placeholder.dataset.lucide = icon;
		placeholder.setAttribute('aria-hidden', 'true');
		button.prepend(placeholder);
	});
}

renderIcons();
const initializeLmsDataTables = (root = document) => {
	root.querySelectorAll('table[data-datatable^="lms-"]').forEach((table) => {
		const isExamQuestionPicker = table.dataset.datatable === 'lms-exam-question-picker';
		const columnDefs = isExamQuestionPicker
			? [{ targets: 0, orderable: false, searchable: false }]
			: [{ targets: -1, orderable: false, searchable: false }];
		const filterColumn = table.dataset.datatableFilterColumn;
		if (filterColumn !== undefined) {
			columnDefs.push({ targets: Number(filterColumn), visible: false, searchable: true, orderable: false });
		}
		const dataTable = table.dataset.datatableInitialized === 'true'
			? new DataTable(table)
			: new DataTable(table, {
				pageLength: 15,
				lengthMenu: [10, 15, 25, 50, 100],
				order: [],
				columnDefs,
				layout: {
					topStart: 'pageLength',
					topEnd: 'search',
					bottomStart: 'info',
					bottomEnd: 'paging',
				},
				language: {
					emptyTable: 'Belum ada data',
					info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
					infoEmpty: 'Tidak ada data',
					lengthMenu: '_MENU_ per halaman',
					search: 'Cari:',
					searchPlaceholder: 'Cari data',
					zeroRecords: 'Tidak ada data yang cocok',
					paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
				},
				drawCallback() {
					renderIcons(this.api().table().container());
				},
			});
		table.dataset.datatableInitialized = 'true';
		if (filterColumn !== undefined) {
			const filterValue = table.dataset.datatableFilterValue || '';
			dataTable.column(Number(filterColumn)).search(filterValue ? `^${filterValue}$` : '', true, false).draw();
		}
	});
};
initializeLmsDataTables();
document.addEventListener('simak:render-icons', () => renderIcons());

const isEkurikulumPage = document.body.dataset.routeName?.startsWith('ekurikulum.');
const initializeSearchableSelects = (root = document) => {
	root.querySelectorAll('select[data-searchable-select]:not([data-select2])').forEach((element) => {
		if (element.tomselect) return;
		const dialog = element.closest('dialog');
		const isLmsCreateDialog = dialog?.id === 'lms-create-modal';
		const useControlWrapper = element.dataset.tomSelectDropdownParent === 'wrapper';
		if ((dialog && !dialog.open) || element.closest('[hidden]')) return;
		new TomSelect(element, {
			create: false,
			maxOptions: 1000,
			allowEmptyOption: true,
			plugins: element.closest('.lms-dialog') ? [] : ['clear_button'],
			dropdownParent: useControlWrapper ? null : (isLmsCreateDialog ? dialog : (element.closest('.lms-dialog') ? null : dialog || 'body')),
			onDropdownOpen: () => {
				if (isLmsCreateDialog) {
					const control = element.tomselect?.control;
					const dropdown = element.tomselect?.dropdown;
					if (!control || !dropdown || !dialog) return;
					control.scrollIntoView({block: 'nearest'});
					window.requestAnimationFrame(() => {
						const controlRect = control.getBoundingClientRect();
						const dialogRect = dialog.getBoundingClientRect();
						const spaceAbove = controlRect.top - dialogRect.top - 12;
						const spaceBelow = dialogRect.bottom - controlRect.bottom - 12;
						const openAbove = spaceBelow < Math.min(dropdown.offsetHeight, 240) && spaceAbove > spaceBelow;
						const availableSpace = Math.max(80, openAbove ? spaceAbove : spaceBelow);
						const dropdownContent = dropdown.querySelector('.ts-dropdown-content');
						dropdown.style.position = 'absolute';
						dropdown.style.top = openAbove
							? `${Math.max(0, controlRect.top - dialogRect.top - Math.min(dropdown.offsetHeight, availableSpace) - 6)}px`
							: `${controlRect.bottom - dialogRect.top + 6}px`;
						dropdown.style.left = `${controlRect.left - dialogRect.left}px`;
						dropdown.style.width = `${controlRect.width}px`;
						dropdown.style.zIndex = '20';
						if (dropdownContent) dropdownContent.style.maxHeight = `${Math.min(240, availableSpace)}px`;
					});
				} else if (!isLmsCreateDialog) {
					element.closest('.lms-dialog-body')?.classList.add('lms-dropdown-open');
				}
			},
			onDropdownClose: () => {
				if (!isLmsCreateDialog) element.closest('.lms-dialog-body')?.classList.remove('lms-dropdown-open');
			},
		});
	});
};
const initializeSelect2 = async (root = document) => {
	const elements = [...root.querySelectorAll('select[data-select2]')];
	if (!elements.length) return;
	const [{ default: $ }, { default: select2 }] = await Promise.all([
		import('jquery'),
		import('select2'),
		import('select2/dist/css/select2.css'),
	]);
	select2(window, $);
	elements.forEach((element) => {
		if ($(element).hasClass('select2-hidden-accessible')) return;
		const dialog = element.closest('dialog');
		if (dialog && !dialog.open) return;
		const closedDetails = element.closest('details:not([open])');
		if (closedDetails) {
			closedDetails.addEventListener('toggle', () => initializeSelect2(closedDetails), { once: true });
			return;
		}
		$(element).select2({
			width: '100%',
			placeholder: element.options[0]?.text ?? '',
			allowClear: !element.required,
			dropdownParent: dialog ? $(dialog) : $(document.body),
		});
	});
};
document.addEventListener('simak:initialize-searchable-selects', (event) => {
	const root = event.detail?.root ?? document;
	initializeSearchableSelects(root);
	initializeLmsDataTables(root);
});
document.addEventListener('simak:initialize-select2', (event) => {
	initializeSelect2(event.detail?.root ?? document);
});

if (isEkurikulumPage) {
	const makeIconButton = (label, icon, className = 'button button-secondary') => {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = `${className} ekur-icon-button`;
		button.setAttribute('aria-label', label);
		button.title = label;
		const placeholder = document.createElement('span');
		placeholder.dataset.lucide = icon;
		placeholder.setAttribute('aria-hidden', 'true');
		button.append(placeholder);
		return button;
	};
	const makeDialog = (title, contentNodes, trigger) => {
		const dialog = document.createElement('dialog');
		const dialogId = `ekur-crud-dialog-${document.querySelectorAll('.ekur-crud-dialog').length + 1}`;
		const titleId = `${dialogId}-title`;
		dialog.id = dialogId;
		dialog.className = 'ekur-dialog ekur-crud-dialog';
		dialog.setAttribute('aria-labelledby', titleId);

		const header = document.createElement('div');
		header.className = 'ekur-dialog-header';
		const heading = document.createElement('div');
		const eyebrow = document.createElement('p');
		eyebrow.className = 'eyebrow';
		eyebrow.textContent = 'Kelola kurikulum dan RPS';
		const titleNode = document.createElement('h2');
		titleNode.id = titleId;
		titleNode.textContent = title;
		heading.append(eyebrow, titleNode);
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'button button-secondary ekur-dialog-close';
		close.textContent = 'Tutup';
		close.setAttribute('aria-label', 'Tutup dialog');
		close.dataset.dialogClose = '';
		header.append(heading, close);

		const body = document.createElement('div');
		body.className = 'ekur-modal-body';
		contentNodes.forEach((node) => body.append(node));
		const footer = document.createElement('div');
		footer.className = 'ekur-modal-actions';
		const cancel = document.createElement('button');
		cancel.type = 'button';
		cancel.className = 'button button-secondary';
		cancel.dataset.dialogClose = '';
		cancel.textContent = 'Batal';
		footer.append(cancel);
		const forms = [...body.querySelectorAll('form')];
		forms.forEach((form, formIndex) => {
			if (!form.id) form.id = `${dialogId}-form-${formIndex + 1}`;
			form.querySelectorAll('button[type="submit"], button:not([type])').forEach((submitButton) => {
				const originalParent = submitButton.parentElement;
				submitButton.type = 'submit';
				submitButton.setAttribute('form', form.id);
				footer.append(submitButton);
				if (originalParent && originalParent.children.length === 0 && !originalParent.textContent.trim()) {
					originalParent.remove();
				}
			});
		});
		dialog.append(header, body, footer);
		document.body.append(dialog);
		trigger.dataset.dialogOpen = dialogId;
		renderIcons(dialog);
		return dialog;
	};

	document.querySelectorAll('.simak-content select:not([data-select2])').forEach((select) => {
		select.dataset.searchableSelect = 'true';
	});

	document.querySelectorAll('.simak-content details').forEach((details) => {
		const summary = details.querySelector(':scope > summary');
		const forms = [...details.querySelectorAll('form')];
		if (!summary || !forms.length || /excel/i.test(summary.textContent)) return;

		const label = summary.textContent.trim() || 'Kelola data';
		const icon = /tambah|buat|add/i.test(label) ? 'plus' : 'pencil';
		const trigger = makeIconButton(label, icon, summary.className || 'button button-secondary');
		const deleteForms = forms.filter((form) => form.querySelector('input[name="_method"][value="delete"]') || form.method.toLowerCase() === 'delete');
		deleteForms.forEach((form) => form.remove());
		const contentNodes = [...details.childNodes].filter((node) => node !== summary && (node.nodeType !== Node.TEXT_NODE || node.textContent.trim()));
		makeDialog(label, contentNodes, trigger);
		if (deleteForms.length) {
			const actions = document.createElement('span');
			actions.className = 'ekur-row-actions';
			actions.append(trigger);
			deleteForms.forEach((form) => {
				form.classList.add('ekur-inline-delete-form');
				const button = form.querySelector('button[type="submit"], button:not([type])');
				if (button) {
					const confirmation = form.getAttribute('onsubmit')?.match(/confirm\((['"])(.*?)\1\)/)?.[2];
					const buttonLabel = confirmation || `Hapus ${label.toLowerCase()}`;
					button.type = 'submit';
					button.classList.add('button-danger', 'button-compact', 'ekur-icon-button');
					button.setAttribute('aria-label', buttonLabel);
					button.title = buttonLabel;
					const placeholder = document.createElement('span');
					placeholder.dataset.lucide = 'trash-2';
					placeholder.setAttribute('aria-hidden', 'true');
					button.replaceChildren(placeholder);
				}
				actions.append(form);
			});
			details.replaceWith(actions);
		} else {
			details.replaceWith(trigger);
		}
	});

	document.querySelectorAll('.simak-content form[data-ekur-modal-form]').forEach((form) => {
		if (form.closest('dialog') || form.closest('details')) return;
		const label = form.dataset.ekurModalForm;
		const trigger = makeIconButton(label, 'pencil');
		form.before(trigger);
		makeDialog(label, [form], trigger);
	});

	document.querySelectorAll('.simak-content form, .ekur-crud-dialog form').forEach((form) => {
		if (form.closest('dialog') || /finalisasi|sahkan/i.test(form.textContent)) return;
		form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
			const label = button.getAttribute('aria-label') || button.textContent.trim() || 'Simpan';
			const icon = button.classList.contains('button-danger') ? 'trash-2' : 'save';
			button.classList.add('ekur-icon-button', 'button-compact');
			button.setAttribute('aria-label', label);
			button.title = label;
			const placeholder = document.createElement('span');
			placeholder.dataset.lucide = icon;
			placeholder.setAttribute('aria-hidden', 'true');
			button.replaceChildren(placeholder);
		});
	});

	const confirmationDialog = document.createElement('dialog');
	confirmationDialog.id = 'ekur-confirm-dialog';
	confirmationDialog.className = 'ekur-dialog ekur-confirm-dialog';
	confirmationDialog.setAttribute('aria-labelledby', 'ekur-confirm-title');
	const confirmationHeader = document.createElement('div');
	confirmationHeader.className = 'ekur-dialog-header';
	const confirmationHeading = document.createElement('div');
	const confirmationEyebrow = document.createElement('p');
	confirmationEyebrow.className = 'eyebrow';
	confirmationEyebrow.textContent = 'Konfirmasi tindakan';
	const confirmationTitle = document.createElement('h2');
	confirmationTitle.id = 'ekur-confirm-title';
	confirmationTitle.textContent = 'Lanjutkan tindakan ini?';
	confirmationHeading.append(confirmationEyebrow, confirmationTitle);
	const confirmationClose = document.createElement('button');
	confirmationClose.type = 'button';
	confirmationClose.className = 'button button-secondary ekur-dialog-close';
	confirmationClose.textContent = 'Tutup';
	confirmationClose.setAttribute('aria-label', 'Tutup dialog');
	confirmationClose.dataset.dialogClose = '';
	confirmationHeader.append(confirmationHeading, confirmationClose);
	const confirmationMessage = document.createElement('p');
	confirmationMessage.className = 'ekur-confirm-message';
	const confirmationActions = document.createElement('div');
	confirmationActions.className = 'ekur-modal-actions';
	const confirmationCancel = document.createElement('button');
	confirmationCancel.type = 'button';
	confirmationCancel.className = 'button button-secondary';
	confirmationCancel.dataset.dialogClose = '';
	confirmationCancel.textContent = 'Batal';
	const confirmationProceed = document.createElement('button');
	confirmationProceed.type = 'button';
	confirmationProceed.className = 'button button-primary';
	confirmationProceed.textContent = 'Ya, lanjutkan';
	confirmationProceed.setAttribute('aria-label', 'Konfirmasi tindakan');
	confirmationActions.append(confirmationCancel, confirmationProceed);
	confirmationDialog.append(confirmationHeader, confirmationMessage, confirmationActions);
	document.body.append(confirmationDialog);
	renderIcons(confirmationDialog);

	let pendingConfirmation = null;
	confirmationProceed.addEventListener('click', () => {
		if (!pendingConfirmation) return;
		const { form, submitter } = pendingConfirmation;
		pendingConfirmation = null;
		confirmationDialog.close();
		form.dataset.confirmed = 'true';
		if (submitter) form.requestSubmit(submitter);
		else form.requestSubmit();
	});
	confirmationDialog.addEventListener('close', () => {
		pendingConfirmation = null;
	});
	document.querySelectorAll('.simak-content form[onsubmit], .ekur-crud-dialog form[onsubmit]').forEach((form) => {
		const match = form.getAttribute('onsubmit')?.match(/confirm\((['"])(.*?)\1\)/);
		if (!match) return;
		form.removeAttribute('onsubmit');
		form.addEventListener('submit', (event) => {
			if (form.dataset.confirmed === 'true') {
				delete form.dataset.confirmed;
				return;
			}
			event.preventDefault();
			confirmationMessage.textContent = match[2];
			confirmationProceed.textContent = /hapus/i.test(match[2]) ? 'Ya, hapus'
				: /finalisasi/i.test(match[2]) ? 'Ya, finalisasi'
					: 'Ya, lanjutkan';
			pendingConfirmation = { form, submitter: event.submitter };
			confirmationDialog.showModal();
		});
	});

	const formDraftPrefix = `ekur-form-draft:${document.body.dataset.userId}:${window.location.pathname}:`;
	const getFormDraftKey = (form) => {
		const identity = [...form.querySelectorAll('input[type="hidden"]')]
			.filter((input) => input.name && !['_token', '_method'].includes(input.name))
			.map((input) => `${input.name}=${input.value}`)
			.join('&');
		return `${formDraftPrefix}${form.action}:${identity}`;
	};
	const clearPendingFormDraft = () => {
		try {
			const submittedKey = window.sessionStorage.getItem(`${formDraftPrefix}pending`);
			if (submittedKey && document.querySelector('.simak-content .alert-success')) {
				window.sessionStorage.removeItem(submittedKey);
			}
			window.sessionStorage.removeItem(`${formDraftPrefix}pending`);
		} catch (error) {
			console.warn('Draf form E-Kurikulum tidak dapat dibersihkan dari penyimpanan sesi.', error);
		}
	};
	clearPendingFormDraft();
	const restoreFormDraft = (form, key) => {
		let draft;
		try {
			draft = JSON.parse(window.sessionStorage.getItem(key) || 'null');
		} catch (error) {
			console.warn('Draf form E-Kurikulum tidak dapat dibaca dari penyimpanan sesi.', error);
			return false;
		}
		if (!Array.isArray(draft)) return false;
		const controlsByName = new Map();
		[...form.elements].forEach((control) => {
			if (!control.name || ['_token', '_method'].includes(control.name) || ['file', 'password'].includes(control.type)) return;
			const controls = controlsByName.get(control.name) || [];
			controls.push(control);
			controlsByName.set(control.name, controls);
		});
		draft.forEach(({ name, value, type }) => {
			const controls = controlsByName.get(name) || [];
			if (!controls.length) return;
			if (type === 'select-multiple') {
				const select = controls[0];
				const values = Array.isArray(value) ? value : [];
				if (select.tomselect) select.tomselect.setValue(values, true);
				else [...select.options].forEach((option) => { option.selected = values.includes(option.value); });
				select.dispatchEvent(new Event('change', { bubbles: true }));
				return;
			}
			if (type === 'checkbox' || type === 'radio') {
				controls.forEach((control) => { control.checked = Array.isArray(value) && value.includes(control.value); });
				return;
			}
			controls[0].value = value;
			controls[0].dispatchEvent(new Event('input', { bubbles: true }));
		});
		return true;
	};
	document.querySelectorAll('.simak-content form, .ekur-crud-dialog form').forEach((form) => {
		if (form.closest('.ekur-upload-dialog') || /finalisasi|sahkan/i.test(form.textContent)) return;
		const key = getFormDraftKey(form);
		const controls = [...form.elements].filter((control) => control.name && !['_token', '_method'].includes(control.name) && !['file', 'password'].includes(control.type));
		const saveDraft = () => {
			const draft = controls.map((control) => ({
				name: control.name,
				type: control.type === 'select-multiple' ? control.type : control.type,
				value: control.type === 'select-multiple'
					? [...control.selectedOptions].map((option) => option.value)
					: control.type === 'checkbox' || control.type === 'radio'
						? controls.filter((item) => item.name === control.name && item.checked).map((item) => item.value)
						: control.value,
			}));
			try {
				window.sessionStorage.setItem(key, JSON.stringify(draft));
			} catch (error) {
				console.warn('Draf form E-Kurikulum tidak dapat disimpan pada sesi ini.', error);
			}
		};
		const restored = restoreFormDraft(form, key);
		if (restored && document.querySelector('.simak-content .alert-danger')) {
			const dialog = form.closest('dialog.ekur-crud-dialog');
			if (dialog) {
				const opener = document.querySelector(`[data-dialog-open="${dialog.id}"]`);
				if (opener) dialog.showModal();
			}
		}
		form.addEventListener('input', saveDraft);
		form.addEventListener('change', saveDraft);
		form.addEventListener('submit', () => {
			saveDraft();
			try {
				window.sessionStorage.setItem(`${formDraftPrefix}pending`, key);
			} catch (error) {
				console.warn('Status draf form E-Kurikulum tidak dapat disimpan pada sesi ini.', error);
			}
		});
	});

	document.querySelectorAll('[data-ekur-upload-form]').forEach((form) => {
		const dropzone = form.querySelector('[data-ekur-dropzone]');
		const input = dropzone?.querySelector('input[type="file"]');
		const filename = dropzone?.querySelector('[data-ekur-file-name]');
		const error = form.querySelector('[data-ekur-upload-error]');
		if (!dropzone || !input || !filename || !error) return;
		const showError = (message = '') => {
			error.textContent = message;
			error.hidden = !message;
		};
		const setFile = (file) => {
			if (!file) return;
			if (!file.name.toLowerCase().endsWith('.xlsx')) {
				input.value = '';
				filename.textContent = 'Belum ada file dipilih';
				showError('Pilih file dengan format .xlsx.');
				return;
			}
			const transfer = new DataTransfer();
			transfer.items.add(file);
			input.files = transfer.files;
			filename.textContent = file.name;
			showError();
		};
		input.addEventListener('change', () => setFile(input.files?.[0]));
		['dragenter', 'dragover'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
			event.preventDefault();
			dropzone.classList.add('is-dragover');
		}));
		['dragleave', 'drop'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
			event.preventDefault();
			dropzone.classList.remove('is-dragover');
		}));
		dropzone.addEventListener('drop', (event) => setFile(event.dataTransfer?.files?.[0]));
		form.addEventListener('submit', (event) => {
			if (!input.files?.length) {
				event.preventDefault();
				showError('Pilih file template .xlsx terlebih dahulu.');
			}
		});
	});

	renderIcons();
	initializeSelect2();
	initializeSearchableSelects();
}

document.querySelectorAll('[data-step-wizard]').forEach((wizard) => {
	const steps = [...wizard.querySelectorAll('[data-wizard-step]')];
	const panels = [...document.querySelectorAll('[data-wizard-panel]')]
		.filter((panel) => panel.dataset.wizardOwner === wizard.dataset.wizardId);
	const progress = wizard.querySelector('[data-wizard-progress]');
	const progressLabel = wizard.querySelector('[data-wizard-progress-label]');
	const storageKey = `ekur-step:${wizard.dataset.wizardId}`;
	if (!steps.length || panels.length !== steps.length) return;

	const activateStep = (stepId, scroll = false) => {
		const stepIndex = steps.findIndex((step) => step.dataset.wizardStep === stepId);
		if (stepIndex < 0 || steps[stepIndex].dataset.wizardLocked === 'true') return;
		const step = steps[stepIndex];
		steps.forEach((item) => {
			item.setAttribute('aria-current', String(item === step));
		});
		panels.forEach((panel) => {
			panel.hidden = panel.dataset.wizardPanel !== stepId;
		});
		initializeSearchableSelects(panels[stepIndex]);
		if (progress) progress.style.width = `${((stepIndex + 1) / steps.length) * 100}%`;
		if (progressLabel) progressLabel.textContent = `Langkah ${stepIndex + 1} dari ${steps.length}`;
		try {
			window.sessionStorage.setItem(storageKey, stepId);
		} catch {
			// The wizard still works for the current navigation when storage is unavailable.
		}
		if (scroll) {
			history.replaceState(null, '', `#${stepId}`);
			document.getElementById(stepId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	steps.forEach((step) => {
		step.addEventListener('click', (event) => {
			event.preventDefault();
			if (step.dataset.wizardLocked === 'true') return;
			activateStep(step.dataset.wizardStep, true);
		});
	});
	panels.forEach((panel, index) => {
		const navigation = document.createElement('nav');
		navigation.className = 'ekur-wizard-navigation';
		navigation.setAttribute('aria-label', 'Navigasi wizard');
		if (index > 0) {
			const previous = document.createElement('button');
			previous.type = 'button';
			previous.className = 'button button-secondary';
			previous.textContent = `← ${steps[index - 1].querySelector('strong').textContent}`;
			previous.addEventListener('click', () => activateStep(steps[index - 1].dataset.wizardStep, true));
			navigation.append(previous);
		} else {
			navigation.append(document.createElement('span'));
		}
		if (index < steps.length - 1) {
			const next = document.createElement('button');
			next.type = 'button';
			next.className = 'button button-primary';
			next.textContent = steps[index + 1].dataset.wizardLocked === 'true'
				? 'Tahap berikutnya terkunci'
				: `${steps[index + 1].querySelector('strong').textContent} →`;
			next.disabled = steps[index + 1].dataset.wizardLocked === 'true';
			next.addEventListener('click', () => activateStep(steps[index + 1].dataset.wizardStep, true));
			navigation.append(next);
		} else {
			navigation.append(document.createElement('span'));
		}
		panel.append(navigation);
	});

	let initialStep = window.location.hash.slice(1);
	if (!steps.some((step) => step.dataset.wizardStep === initialStep && step.dataset.wizardLocked !== 'true')) {
		try {
			initialStep = window.sessionStorage.getItem(storageKey) || '';
		} catch {
			initialStep = '';
		}
	}
	const firstUnlockedStep = steps.find((step) => step.dataset.wizardLocked !== 'true') || steps[0];
	activateStep(
		steps.some((step) => step.dataset.wizardStep === initialStep && step.dataset.wizardLocked !== 'true')
			? initialStep
			: firstUnlockedStep.dataset.wizardStep
	);
});

document.querySelectorAll('[data-rps-code-form]').forEach((form) => {
	const source = form.querySelector('[data-rps-code-source]');
	const target = form.querySelector(`[data-rps-generated-code="${form.dataset.rpsCodeForm}"]`);
	if (!source || !target) return;

	const updateGeneratedCode = () => {
		const selected = [...source.selectedOptions][0];
		if (!selected) {
			target.value = '';
			return;
		}
		const prefix = form.dataset.rpsCodeForm === 'cpmk' ? 'CPMK' : 'SUB';
		const digits = (selected.dataset.code || '').replace(/\D/g, '');
		target.value = `${prefix}${digits}${selected.dataset.next || '1'}`;
	};

	source.addEventListener('change', updateGeneratedCode);
});

document.querySelectorAll('[data-rps-assessment-form]').forEach((form) => {
	const totalLabel = form.querySelector('[data-assessment-total]');
	const saveButton = [...document.querySelectorAll('[data-assessment-save]')].find((button) => button.form === form);
	const inputs = [...form.querySelectorAll('input[name^="nilai["]')];
	const updateTotal = () => {
		const total = inputs.reduce((sum, input) => sum + (Number(input.value) || 0), 0);
		if (totalLabel) {
			totalLabel.textContent = `${total.toFixed(2)}%`;
			totalLabel.dataset.valid = String(Math.abs(total - 100) < .005);
		}
		if (saveButton) saveButton.disabled = Math.abs(total - 100) >= .005;
	};
	form.addEventListener('input', updateTotal);
	updateTotal();
});

document.querySelectorAll('[data-curriculum-map]').forEach((svg) => {
	const nodes = [...svg.querySelectorAll('[data-map-node]')];
	const edges = [...svg.querySelectorAll('[data-map-edge]')];
	const nodeById = new Map(nodes.map((node) => [node.dataset.nodeId, node]));
	const storageKey = svg.dataset.mapStorageKey;
	const status = svg.closest('.ekur-relationship-graph')?.querySelector('[data-map-download-status]');
	let drag = null;

	const setStatus = (message) => {
		if (status) status.textContent = message;
	};
	const nodePosition = (node) => {
		const match = node.getAttribute('transform')?.match(/translate\(([-\d.]+)[ ,]+([-\d.]+)\)/);
		return match ? { x: Number(match[1]), y: Number(match[2]) } : { x: 0, y: 0 };
	};
	if (storageKey) {
		try {
			const savedPositions = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
			const viewBox = svg.viewBox.baseVal;
			nodes.forEach((node) => {
				const position = savedPositions[node.dataset.nodeId];
				if (Number.isFinite(position?.x) && Number.isFinite(position?.y)) {
					const x = Math.min(viewBox.width - 158, Math.max(0, position.x));
					const y = Math.min(viewBox.height - 42, Math.max(28, position.y));
					node.setAttribute('transform', `translate(${x} ${y})`);
				}
			});
		} catch {
			setStatus('Posisi peta sebelumnya tidak dapat dimuat dari penyimpanan browser.');
		}
	}
	const updateEdges = () => {
		edges.forEach((edge) => {
			const source = nodeById.get(edge.dataset.source);
			const target = nodeById.get(edge.dataset.target);
			if (!source || !target) return;
			const sourcePosition = nodePosition(source);
			const targetPosition = nodePosition(target);
			const sourceX = sourcePosition.x + 158;
			const sourceY = sourcePosition.y + 21;
			const targetX = targetPosition.x;
			const targetY = targetPosition.y + 21;
			const bend = Math.max(28, Math.abs(targetX - sourceX) * .4);
			edge.setAttribute('d', `M${sourceX} ${sourceY} C${sourceX + bend} ${sourceY}, ${targetX - bend} ${targetY}, ${targetX} ${targetY}`);
		});
	};
	const savePositions = () => {
		if (!storageKey) return;
		try {
			const positions = Object.fromEntries(nodes.map((node) => [node.dataset.nodeId, nodePosition(node)]));
			window.localStorage.setItem(storageKey, JSON.stringify(positions));
		} catch {
			setStatus('Posisi peta berubah, tetapi tidak dapat disimpan di penyimpanan browser.');
		}
	};

	nodes.forEach((node) => node.addEventListener('pointerdown', (event) => {
		if (event.button !== 0) return;
		const position = nodePosition(node);
		const bounds = svg.getBoundingClientRect();
		const viewBox = svg.viewBox.baseVal;
		drag = {
			node,
			pointerId: event.pointerId,
			clientX: event.clientX,
			clientY: event.clientY,
			startX: position.x,
			startY: position.y,
			scaleX: viewBox.width / bounds.width,
			scaleY: viewBox.height / bounds.height,
		};
		node.setPointerCapture(event.pointerId);
		node.classList.add('is-dragging');
		event.preventDefault();
	}));

	svg.addEventListener('pointermove', (event) => {
		if (!drag || event.pointerId !== drag.pointerId) return;
		const maxX = svg.viewBox.baseVal.width - 158;
		const maxY = svg.viewBox.baseVal.height - 42;
		const x = Math.min(maxX, Math.max(0, drag.startX + (event.clientX - drag.clientX) * drag.scaleX));
		const y = Math.min(maxY, Math.max(28, drag.startY + (event.clientY - drag.clientY) * drag.scaleY));
		drag.node.setAttribute('transform', `translate(${x} ${y})`);
		updateEdges();
	});

	const endDrag = (event) => {
		if (!drag || event.pointerId !== drag.pointerId) return;
		drag.node.classList.remove('is-dragging');
		drag = null;
		savePositions();
	};
	svg.addEventListener('pointerup', endDrag);
	svg.addEventListener('pointercancel', endDrag);
	updateEdges();

	const toolbar = svg.closest('.ekur-relationship-graph')?.querySelector('[data-map-download]');
	toolbar?.addEventListener('click', () => {
		const viewBox = svg.viewBox.baseVal;
		const scale = 2;
		const clone = svg.cloneNode(true);
		clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
		clone.setAttribute('width', String(viewBox.width * scale));
		clone.setAttribute('height', String(viewBox.height * scale));
		const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
		style.textContent = '.ekur-relationship-heading{fill:#4d6651;font:800 9px sans-serif}.ekur-relationship-edge{fill:none;stroke:#829f83;stroke-width:1.5;opacity:.75}.ekur-relationship-edge.is-direct{stroke:#4c82a3;stroke-dasharray:5 4}.ekur-relationship-edge.is-prerequisite{stroke:#bd8b36;stroke-dasharray:3 4}.ekur-relationship-node rect{fill:#fff;stroke:#d9e5d7;stroke-width:1.2}.ekur-relationship-code{fill:#28543a;font:800 8px sans-serif}.ekur-relationship-name{fill:#6d7b6e;font:7px sans-serif}';
		clone.insertBefore(style, clone.firstChild);
		let finalSourceUrl;
		try {
			finalSourceUrl = URL.createObjectURL(new Blob(
				[new XMLSerializer().serializeToString(clone)],
				{ type: 'image/svg+xml;charset=utf-8' },
			));
		} catch (error) {
			setStatus(`PNG tidak dapat dibuat: ${error instanceof Error ? error.message : 'kesalahan serialisasi SVG.'}`);
			return;
		}
		toolbar.disabled = true;
		setStatus('Menyiapkan unduhan PNG…');
		const image = new Image();
		image.onload = () => {
			const canvas = document.createElement('canvas');
			canvas.width = viewBox.width * scale;
			canvas.height = viewBox.height * scale;
			const context = canvas.getContext('2d');
			if (!context) {
				URL.revokeObjectURL(finalSourceUrl);
				toolbar.disabled = false;
				setStatus('PNG tidak dapat dibuat karena canvas tidak tersedia.');
				return;
			}
			context.fillStyle = '#ffffff';
			context.fillRect(0, 0, canvas.width, canvas.height);
			context.drawImage(image, 0, 0, canvas.width, canvas.height);
			canvas.toBlob((blob) => {
				URL.revokeObjectURL(finalSourceUrl);
				toolbar.disabled = false;
				if (!blob) {
					setStatus('PNG tidak dapat dibuat. Silakan coba lagi.');
					return;
				}
				const imageUrl = URL.createObjectURL(blob);
				const link = document.createElement('a');
				link.href = imageUrl;
				link.download = `${toolbar.dataset.mapFilename || 'Peta-Kurikulum'}.png`;
				link.click();
				window.setTimeout(() => URL.revokeObjectURL(imageUrl), 1000);
				setStatus('Peta berhasil diunduh sebagai PNG.');
			}, 'image/png');
		};
		image.onerror = () => {
			URL.revokeObjectURL(finalSourceUrl);
			toolbar.disabled = false;
			setStatus('PNG tidak dapat dibuat dari peta ini. Silakan coba lagi.');
		};
		image.src = finalSourceUrl;
	});
});

document.addEventListener('click', (event) => {
	const opener = event.target.closest('[data-dialog-open]');
	if (opener) {
		const dialog = document.getElementById(opener.dataset.dialogOpen);
		dialog?.showModal();
		if (dialog) {
			initializeSearchableSelects(dialog);
			if (dialog.id === 'graduation-document-upload-dialog') {
				const form = dialog.querySelector('[data-graduation-upload-form]');
				const input = dialog.querySelector('#graduation-document-file');
				const fileName = dialog.querySelector('[data-graduation-file-name]');
				const title = dialog.querySelector('[data-graduation-document-label]');
				const skripsiFields = dialog.querySelector('[data-graduation-skripsi-fields]');
				const isSkripsi = opener.dataset.graduationDocument === 'skripsi';
				if (form && input && fileName && title && skripsiFields) {
					form.action = opener.dataset.uploadUrl;
					form.elements.document_type.value = opener.dataset.graduationDocument || '';
					title.textContent = opener.dataset.documentLabel || 'Dokumen wisuda';
					input.accept = opener.dataset.documentType === 'photo'
						? '.jpg,.jpeg,.png,image/jpeg,image/png'
						: '.pdf,application/pdf';
					input.value = '';
					fileName.textContent = 'Belum ada file dipilih';
					delete dialog.querySelector('[data-graduation-dropzone]').dataset.invalidFile;
					skripsiFields.hidden = !isSkripsi;
				skripsiFields.querySelectorAll('input, textarea').forEach((field) => { field.required = isSkripsi; });
				}
			}
			document.dispatchEvent(new CustomEvent('simak:initialize-kkn-candidate-table', { detail: { root: dialog } }));
		}
		return;
	}

	const closer = event.target.closest('[data-dialog-close]');
	closer?.closest('dialog')?.close();
});

document.querySelectorAll('dialog[data-open-on-load="true"]').forEach((dialog) => dialog.showModal());

document.querySelectorAll('[data-finance-table]').forEach((table) => {
	const module = table.dataset.financeTable;
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);
	const cell = (value) => escapeHtml(value || '-');
	const columns = module === 'categories'
		? [
			{ data: 'angkatan', render: cell },
			{ data: 'nim', render: cell },
			{ data: 'nama_mahasiswa', render: cell },
			{ data: 'nama_program_studi', render: cell },
			{ data: 'nama_status_mahasiswa', render: cell },
			{ data: 'kategori_ukt', render: (value) => cell(value || 'Belum diunggah') },
			{ data: 'nominal_ukt', render: (value, type) => type === 'display' && value
				? `Rp ${new Intl.NumberFormat('id-ID').format(Number(value))}` : (value || '-') },
		]
		: [
			{ data: 'angkatan', render: cell },
			{
				data: null,
				render: (_value, type, record) => type === 'display'
					? `<strong>${cell(record.nama_mahasiswa)}</strong><br><span class="finance-muted">${cell(record.nim)}</span>`
					: `${record.nama_mahasiswa} ${record.nim}`,
			},
			{
				data: null,
				render: (_value, type, record) => type === 'display'
					? `${cell(record.nama_fakultas)}<br><span class="finance-muted">${cell(record.nama_program_studi)}${record.nama_jenjang_pendidikan ? ` (${cell(record.nama_jenjang_pendidikan)})` : ''}</span>`
					: `${record.nama_fakultas || ''} ${record.nama_program_studi || ''} ${record.nama_jenjang_pendidikan || ''}`,
			},
			...(module === 'ukt' ? [
				{ data: 'tahun', render: cell },
				{ data: 'kategori_ukt', render: cell },
			] : []),
			{
				data: 'payment_reference',
				render: (value, type, record) => type === 'display'
					? (value ? `<span class="finance-muted">${cell(record.payment_method || 'Transaksi')}</span><br>${cell(value)}` : '<span class="finance-muted">Belum checkout</span>')
					: (value || ''),
			},
			{ data: 'nominal', render: (value, type, record) => type === 'display'
				? `<span>Tagihan: Rp ${new Intl.NumberFormat('id-ID').format(Number(value || 0))}</span>${Number(record.service_fee || 0) > 0
					? `<br><span class="finance-muted">Biaya layanan Midtrans: Rp ${new Intl.NumberFormat('id-ID').format(Number(record.service_fee))}</span><br><strong>Total Midtrans: Rp ${new Intl.NumberFormat('id-ID').format(Number(record.total_amount || 0))}</strong>`
					: ''}` : Number(value || 0) },
			{
				data: 'lunas',
				render: (value, type) => type === 'display'
					? `<span class="finance-status ${Number(value) === 1 ? 'is-paid' : ''}">${Number(value) === 1 ? 'Lunas' : 'Belum lunas'}</span>`
					: Number(value),
			},
			{
				data: 'tanggal_bayar',
				render: (value, type, record) => type === 'display'
					? (Number(record.lunas) === 1 ? cell(value) : '-')
					: (Number(record.lunas) === 1 ? value || '' : ''),
			},
			{
				data: 'midtrans_status',
				render: (value, type) => type === 'display'
					? (value ? `<span class="finance-muted">${cell(value)}</span>` : '-')
					: (value || ''),
			},
		];
	if (table.dataset.canMutate === 'true' && module !== 'categories') {
		columns.push({ data: 'actions', orderable: false, searchable: false, render: (value) => value || '' });
	}
	new DataTable(table, {
		serverSide: true,
		processing: true,
		deferRender: true,
		ajax: table.dataset.ajaxUrl,
		columns,
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, 'asc']],
		layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' },
		language: {
			processing: 'Memuat data…',
			emptyTable: 'Belum ada data',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
			infoEmpty: 'Tidak ada data',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari NIM atau nama',
			zeroRecords: 'Tidak ada data yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
		drawCallback() {
			renderIcons(this.api().table().container());
		},
	});
});

document.querySelectorAll('[data-finance-dropzone]').forEach((dropzone) => {
	const input = dropzone.querySelector('input[type="file"]');
	const filename = dropzone.querySelector('[data-finance-file-name]');
	if (!input || !filename) return;
	const allowedExtensions = input.accept.split(',').map((extension) => extension.trim().replace(/^\./, '').toLowerCase());
	const setFile = (file) => {
		if (!file) return;
		const extension = file.name.split('.').pop()?.toLowerCase();
		if (!allowedExtensions.includes(extension)) {
			input.value = '';
			filename.textContent = 'Belum ada file dipilih';
			dropzone.dataset.invalidFile = 'true';
			return;
		}
		const transfer = new DataTransfer();
		transfer.items.add(file);
		input.files = transfer.files;
		filename.textContent = file.name;
		delete dropzone.dataset.invalidFile;
	};
	input.addEventListener('change', () => setFile(input.files?.[0]));
	['dragenter', 'dragover'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
		event.preventDefault();
		dropzone.classList.add('is-dragover');
	}));
	['dragleave', 'drop'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
		event.preventDefault();
		dropzone.classList.remove('is-dragover');
	}));
	dropzone.addEventListener('drop', (event) => setFile(event.dataTransfer?.files?.[0]));
	dropzone.closest('form')?.addEventListener('submit', (event) => {
		if (dropzone.dataset.invalidFile === 'true') {
			event.preventDefault();
			input.focus();
		}
	});
});

document.querySelectorAll('[data-graduation-dropzone]').forEach((dropzone) => {
	const input = dropzone.querySelector('input[type="file"]');
	const filename = dropzone.querySelector('[data-graduation-file-name]');
	if (!input || !filename) return;
	const setFile = (file) => {
		if (!file) return;
		const allowedTypes = input.accept.includes('image/')
			? ['image/jpeg', 'image/png']
			: ['application/pdf'];
		if (!allowedTypes.includes(file.type) || file.size > 1024 * 1024) {
			input.value = '';
			filename.textContent = 'File tidak sesuai tipe atau melebihi 1 MB';
			dropzone.dataset.invalidFile = 'true';
			return;
		}
		const transfer = new DataTransfer();
		transfer.items.add(file);
		input.files = transfer.files;
		filename.textContent = file.name;
		delete dropzone.dataset.invalidFile;
	};
	input.addEventListener('change', () => setFile(input.files?.[0]));
	['dragenter', 'dragover'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
		event.preventDefault();
		dropzone.classList.add('is-dragover');
	}));
	['dragleave', 'drop'].forEach((eventName) => dropzone.addEventListener(eventName, (event) => {
		event.preventDefault();
		dropzone.classList.remove('is-dragover');
	}));
	dropzone.addEventListener('drop', (event) => setFile(event.dataTransfer?.files?.[0]));
	dropzone.closest('form')?.addEventListener('submit', (event) => {
		if (dropzone.dataset.invalidFile === 'true') {
			event.preventDefault();
			input.focus();
		}
	});
});

const financeConfirmDialog = document.querySelector('[data-finance-confirm-dialog]');
if (financeConfirmDialog instanceof HTMLDialogElement) {
	const title = financeConfirmDialog.querySelector('#finance-confirm-title');
	const message = financeConfirmDialog.querySelector('#finance-confirm-message');
	const submitButton = financeConfirmDialog.querySelector('[data-finance-confirm-submit]');
	let pendingForm = null;
	let approvedForm = null;
	let returnFocus = null;

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || !form.matches('[data-finance-confirm]')) return;
		if (form === approvedForm) {
			approvedForm = null;
			return;
		}

		event.preventDefault();
		pendingForm = form;
		returnFocus = event.submitter;
		title.textContent = form.dataset.confirmTitle || 'Konfirmasi tindakan';
		message.textContent = form.dataset.confirmMessage || 'Lanjutkan tindakan ini?';
		financeConfirmDialog.showModal();
		submitButton.focus();
	});

	const cancelConfirmation = () => {
		pendingForm = null;
		financeConfirmDialog.close();
		returnFocus?.focus();
	};

	financeConfirmDialog.querySelectorAll('[data-finance-confirm-cancel]').forEach((button) => {
		button.addEventListener('click', cancelConfirmation);
	});
	submitButton?.addEventListener('click', () => {
		if (!pendingForm) return;
		approvedForm = pendingForm;
		pendingForm = null;
		financeConfirmDialog.close();
		approvedForm.requestSubmit();
		returnFocus?.focus();
	});
	financeConfirmDialog.addEventListener('cancel', () => {
		pendingForm = null;
		returnFocus?.focus();
	});
}

document.addEventListener('click', (event) => {
	const button = event.target.closest('[data-finance-edit]');
	if (!button) return;
	const form = document.querySelector('[data-finance-edit-form]');
	const title = document.querySelector('#finance-payment-edit-title');
	const student = document.querySelector('[data-finance-edit-student]');
	const amount = form?.elements.namedItem('nominal');
	if (!(form instanceof HTMLFormElement) || !(amount instanceof HTMLInputElement)) return;
	const isEnglish = window.simakLocale === 'en';
	const name = button.dataset.studentName || '';
	form.action = button.dataset.updateUrl;
	title.textContent = `${isEnglish ? 'Edit amount' : 'Ubah nominal'} · ${name}`;
	student.textContent = `${isEnglish ? 'Student ID' : 'NIM'} ${button.dataset.studentNim || ''} · ${isEnglish ? 'Enter a whole amount in rupiah.' : 'Nilai wajib berupa rupiah bulat.'}`;
	amount.value = button.dataset.amount || '';
});

document.addEventListener('click', (event) => {
	const button = event.target.closest('[data-finance-manual-paid]');
	if (!button) return;
	const form = document.querySelector('[data-finance-manual-paid-form]');
	const student = document.querySelector('[data-finance-manual-paid-student]');
	if (!(form instanceof HTMLFormElement)) return;
	form.action = button.dataset.updateUrl;
	form.reset();
	student.textContent = `${button.dataset.studentName || ''} · NIM ${button.dataset.studentNim || ''}`;
});

document.querySelectorAll('[data-kkn-period-edit]').forEach((button) => {
	button.addEventListener('click', () => {
		const form = document.getElementById('kkn-period-edit-form');
		if (!form) return;
		form.action = button.dataset.updateUrl;
		form.elements.period_id.value = button.dataset.periodId;
		form.elements.tanggal_mulai.value = button.dataset.startDate;
		form.elements.tanggal_akhir.value = button.dataset.endDate;
	});
});

document.querySelectorAll('[data-kkn-location-edit]').forEach((button) => {
	button.addEventListener('click', () => {
		const form = document.getElementById('kkn-location-edit-form');
		if (!form) return;
		form.action = button.dataset.updateUrl;
		form.elements.location_id.value = button.dataset.locationId;
		form.elements.nama_lokasi.value = button.dataset.name;
		form.elements.kuota.value = button.dataset.quota;
		form.elements.aktif.value = button.dataset.active;
	});
});

const initializeKknProposalForm = (form) => {
	if (!form) return;
	const storageKey = form.dataset.kknSelectionKey || `kkn-proposals:${form.dataset.kknYear}`;
	let selected = form._simakSelectedStudents;
	if (!selected) {
		let canPersist = true;
		try {
			selected = new Set(JSON.parse(sessionStorage.getItem(storageKey) || '[]'));
		} catch {
			selected = new Set();
			canPersist = false;
		}
		form._simakSelectedStudents = selected;
		form._simakCanPersistSelection = canPersist;
	}
	form.querySelectorAll('input[name="students[]"]').forEach((checkbox) => {
		checkbox.checked = !checkbox.disabled && selected.has(checkbox.value);
	});
	if (form.dataset.selectionInitialized === 'true') return;
	form.dataset.selectionInitialized = 'true';
	form.addEventListener('change', (event) => {
		const checkbox = event.target.closest('input[name="students[]"]');
		if (!checkbox || !form.contains(checkbox)) return;
		if (checkbox.checked) selected.add(checkbox.value);
		else selected.delete(checkbox.value);
		if (!form._simakCanPersistSelection) return;
		try {
			sessionStorage.setItem(storageKey, JSON.stringify([...selected]));
		} catch {
			form._simakCanPersistSelection = false;
		}
	});
	form.addEventListener('submit', (event) => {
		form.querySelectorAll('input[name="students[]"]').forEach((checkbox) => {
			if (checkbox.checked) selected.add(checkbox.value);
			checkbox.disabled = true;
		});
		if (selected.size === 0) {
			event.preventDefault();
			form.querySelectorAll('input[name="students[]"]').forEach((checkbox) => { checkbox.disabled = false; });
			return;
		}
		selected.forEach((studentId) => {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'students[]';
			input.value = studentId;
			form.append(input);
		});
		if (form._simakCanPersistSelection) sessionStorage.removeItem(storageKey);
	});
};

document.querySelectorAll('[data-kkn-proposal-form]').forEach(initializeKknProposalForm);

document.querySelectorAll('[data-kkn-location-form] select[name="id_lokasi"]').forEach((select) => {
	let savedValue = select.value;
	select.addEventListener('change', () => {
		const form = select.form;
		if (!select.value || !form || select.value === savedValue || select.dataset.saving === 'true') return;
		const body = new FormData(form);
		select.dataset.saving = 'true';
		select.tomselect?.disable();
		(async () => {
			try {
				const response = await fetch(form.action, {
					method: 'POST',
					body,
					credentials: 'same-origin',
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
				});
				let payload;
				try {
					payload = await response.json();
				} catch {
					throw new Error('Respons penyimpanan lokasi tidak valid.');
				}
				if (!response.ok) {
					const errors = payload && typeof payload === 'object' ? payload.errors : null;
					const message = Object.values(errors || {}).flat().join(' ')
						|| payload?.message
						|| `Lokasi peserta gagal diperbarui (HTTP ${response.status}).`;
					throw new Error(message);
				}
				savedValue = select.value;
				document.dispatchEvent(new CustomEvent('simak:notification', {
					detail: { message: payload?.message || 'Lokasi peserta berhasil diperbarui.' },
				}));
			} catch (error) {
				if (select.tomselect) select.tomselect.setValue(savedValue, true);
				else select.value = savedValue;
				document.dispatchEvent(new CustomEvent('simak:notification', {
					detail: { message: error.message, variant: 'error' },
				}));
			} finally {
				delete select.dataset.saving;
				select.tomselect?.enable();
			}
		})();
	});
});


const hasCompatibleSimpleDataTableStructure = (table) => {
	const headerRows = Array.from(table.tHead?.rows ?? []);
	if (headerRows.length === 0) return false;
	const headerColumns = Array.from(headerRows[headerRows.length - 1].cells)
		.reduce((total, cell) => total + cell.colSpan, 0);
	if (headerColumns === 0) return false;

	return Array.from(table.tBodies).every((body) => Array.from(body.rows).every((row) =>
		row.cells.length === headerColumns
		&& Array.from(row.cells).every((cell) => cell.colSpan === 1 && cell.rowSpan === 1)));
};

document.querySelectorAll('[data-simple-search-table]').forEach((table) => {
	if (!hasCompatibleSimpleDataTableStructure(table)) return;
	new DataTable(table, {
		paging: false,
		info: false,
		lengthChange: false,
		order: [],
		layout: { topStart: null, topEnd: 'search', bottomStart: null, bottomEnd: null },
		language: {
			search: 'Cari:',
			searchPlaceholder: table.dataset.searchPlaceholder || 'Cari pada daftar yang ditampilkan',
			zeroRecords: 'Tidak ada baris yang cocok',
		},
		drawCallback() {
			renderIcons(this.api().table().container());
		},
	});
});

const initializeKknCandidateTable = (root = document) => {
	const table = root.querySelector('[data-kkn-candidate-table]');
	if (!table || table.dataset.datatableInitialized === 'true') return;
	const dialog = table.closest('dialog');
	if (dialog && !dialog.open) return;
	table.dataset.datatableInitialized = 'true';
	const searchForm = dialog?.querySelector('[data-candidate-live-search]');
	const searchState = searchForm?.querySelector('input[name="search"]');
	const dataTable = new DataTable(table, {
		paging: false,
		info: false,
		lengthChange: false,
		order: [],
		layout: { topStart: null, topEnd: 'search', bottomStart: null, bottomEnd: null },
		language: {
			search: 'Cari nama/NPM:',
			searchPlaceholder: 'Ketik nama atau NPM',
			zeroRecords: 'Tidak ada mahasiswa yang cocok.',
		},
	});
	table._simakCandidateDataTable = dataTable;
	const tableSearch = dataTable.table().container().querySelector('.dt-search input');
	if (tableSearch && searchForm && searchState) {
		let searchTimer;
		tableSearch.setAttribute('form', searchForm.id);
		tableSearch.value = searchState.value;
		tableSearch.addEventListener('input', () => {
			window.clearTimeout(searchTimer);
			searchState.value = tableSearch.value.trim();
			searchTimer = window.setTimeout(() => searchForm.requestSubmit(), 350);
		});
		if (searchState.value !== '') dataTable.search(searchState.value).draw();
	}
	window.requestAnimationFrame(() => dataTable.columns.adjust());
};

const candidateDialogRequests = new WeakMap();

const refreshCandidateDialog = async (dialog, url) => {
	candidateDialogRequests.get(dialog)?.abort();
	const controller = new AbortController();
	candidateDialogRequests.set(dialog, controller);
	dialog.setAttribute('aria-busy', 'true');

	try {
		const response = await fetch(url, {
			credentials: 'same-origin',
			signal: controller.signal,
			headers: {
				Accept: 'text/html',
				'X-Requested-With': 'XMLHttpRequest',
			},
		});
		if (!response.ok) throw new Error('Data calon peserta gagal dimuat.');

		const page = new DOMParser().parseFromString(await response.text(), 'text/html');
		const updatedResults = page.querySelector(`#${dialog.id} [data-candidate-results]`);
		const currentResults = dialog.querySelector('[data-candidate-results]');
		if (!updatedResults || !currentResults) throw new Error('Data calon peserta tidak ditemukan.');

		const table = dialog.querySelector('[data-kkn-candidate-table]');
		table?._simakCandidateDataTable?.destroy();
		currentResults.replaceWith(updatedResults);
		window.history.replaceState({}, '', response.url);
		initializeKknProposalForm(dialog.querySelector('[data-kkn-proposal-form]'));
		initializeKknCandidateTable(dialog);
		renderIcons(dialog);
	} catch (error) {
		if (error.name !== 'AbortError') {
			document.dispatchEvent(new CustomEvent('simak:notification', {
				detail: { message: error.message, variant: 'error' },
			}));
		}
	} finally {
		if (candidateDialogRequests.get(dialog) === controller) {
			candidateDialogRequests.delete(dialog);
			dialog.removeAttribute('aria-busy');
		}
	}
};

document.addEventListener('submit', (event) => {
	const form = event.target.closest('[data-candidate-live-search]');
	if (!form) return;
	event.preventDefault();
	const dialog = form.closest('dialog');
	if (!dialog) return;
	const url = new URL(form.action, window.location.href);
	new FormData(form).forEach((value, key) => url.searchParams.set(key, value));
	url.searchParams.set('create', '1');
	refreshCandidateDialog(dialog, url);
});

document.addEventListener('click', (event) => {
	const link = event.target.closest('dialog .kkn-pagination a');
	if (!link) return;
	event.preventDefault();
	const dialog = link.closest('dialog');
	if (dialog) refreshCandidateDialog(dialog, link.href);
});

document.addEventListener('simak:initialize-kkn-candidate-table', (event) => {
	initializeKknCandidateTable(event.detail?.root ?? document);
});
initializeKknCandidateTable();

const akmFeederTable = document.querySelector('[data-akm-feeder-table]');
if (akmFeederTable) {
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);
	const text = (value) => escapeHtml(value || '-');
	const statusOptions = JSON.parse(akmFeederTable.dataset.statusOptions || '[]');
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
	const dataTable = new DataTable(akmFeederTable, {
		serverSide: true,
		processing: true,
		deferRender: true,
		ajax: akmFeederTable.dataset.ajaxUrl,
		columns: [
			{ data: null, orderable: false, searchable: false, render: (_value, _type, _record, meta) => meta.settings._iDisplayStart + meta.row + 1 },
			{ data: 'nim', render: text },
			{ data: 'nama_mahasiswa', render: text },
			{ data: 'namatahun', render: text },
			{
				data: 'status_simak',
				render: (value, type, record) => {
					if (type !== 'display') return value || '';
					const options = statusOptions.map((option) => `<option value="${escapeHtml(option.code)}" ${String(option.code) === String(value) ? 'selected' : ''}>${escapeHtml(option.code)} · ${escapeHtml(option.label)}</option>`).join('');
					return `<select data-akm-status data-searchable-select data-id-ipmhs="${escapeHtml(record.id_ipmhs)}" data-saved-value="${escapeHtml(value)}" aria-label="Status SIAKAD ${escapeHtml(record.nama_mahasiswa)}">${options}</select>`;
				},
			},
			{ data: 'status_feeder', render: (value) => text(value || 'Tidak ditemukan / tidak tersedia') },
		],
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[2, 'asc']],
		layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' },
		language: {
			processing: 'Membandingkan status Feeder…',
			emptyTable: 'Tidak ada data AKM pada kalender ini.',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ mahasiswa',
			infoEmpty: 'Tidak ada mahasiswa',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari NPM atau nama mahasiswa',
			zeroRecords: 'Tidak ada mahasiswa yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
		drawCallback() {
			initializeSearchableSelects(this.api().table().container());
			renderIcons(this.api().table().container());
		},
	});
	akmFeederTable.addEventListener('xhr.dt', (_event, _settings, json) => {
		if (json?.feederError) {
			document.dispatchEvent(new CustomEvent('simak:notification', {
				detail: { message: json.feederError, variant: 'error' },
			}));
		}
	});
	akmFeederTable.addEventListener('change', async (event) => {
		const select = event.target instanceof Element ? event.target.closest('[data-akm-status]') : null;
		if (!select || select.dataset.saving) return;
		const previousValue = select.dataset.savedValue ?? select.value;
		select.dataset.saving = 'true';
		select.tomselect?.disable();
		const formData = new FormData();
		formData.append('_token', csrfToken);
		formData.append('program', akmFeederTable.dataset.program);
		formData.append('calendar', akmFeederTable.dataset.calendar);
		formData.append('id_ipmhs', select.dataset.idIpmhs);
		formData.append('status', select.value);
		try {
			const response = await fetch(akmFeederTable.dataset.updateUrl, {
				method: 'POST',
				body: formData,
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			});
			const payload = await response.json();
			if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'Status AKM gagal diperbarui.');
			select.dataset.savedValue = select.value;
			document.dispatchEvent(new CustomEvent('simak:notification', { detail: { message: payload.message } }));
			dataTable.ajax.reload(null, false);
		} catch (error) {
			if (select.tomselect) select.tomselect.setValue(previousValue, true);
			else select.value = previousValue;
			document.dispatchEvent(new CustomEvent('simak:notification', { detail: { message: error.message, variant: 'error' } }));
		} finally {
			delete select.dataset.saving;
			select.tomselect?.enable();
		}
	});
}

const camabaTable = document.querySelector('[data-camaba-table]');
if (camabaTable) {
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);
	const cell = (value) => escapeHtml(value || '-');
	new DataTable(camabaTable, {
		serverSide: true,
		processing: true,
		deferRender: true,
		ajax: camabaTable.dataset.ajaxUrl,
		columns: [
			{ data: 'nim', render: cell },
			{ data: 'nama_mahasiswa', render: cell },
			{ data: 'no_peserta', render: cell },
			{ data: 'nama_program_studi', render: cell },
			{
				data: 'status',
				render: (value, type, record) => type === 'display'
					? `<span class="camaba-status ${record.verified ? 'is-verified' : ''}">${cell(value)}</span>` : value,
			},
			{
				data: 'verify_url',
				orderable: false,
				searchable: false,
				render: (url, type, record) => type !== 'display' || record.verified ? ''
					: `<a class="table-icon-button" href="${escapeHtml(url)}" title="Verifikasi CAMABA" aria-label="Verifikasi ${cell(record.nama_mahasiswa)}"><span data-lucide="badge-check"></span></a>`,
			},
		],
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[1, 'asc']],
		layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' },
		language: {
			processing: 'Memuat data CAMABA…',
			emptyTable: 'Belum ada data CAMABA pada periode ini.',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ calon mahasiswa',
			infoEmpty: 'Tidak ada calon mahasiswa',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari NPM, nama, nomor peserta, atau prodi',
			zeroRecords: 'Tidak ada CAMABA yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
		drawCallback() {
			renderIcons(this.api().table().container());
		},
	});
}

const camabaCountry = document.querySelector('[data-camaba-country]');
const camabaRegion = document.querySelector('[data-camaba-region]');
if (camabaCountry && camabaRegion) {
	camabaCountry.addEventListener('change', async () => {
		const country = camabaCountry.value;
		if (!country) return;
		try {
			const response = await fetch(`${camabaRegion.dataset.optionsUrl}?country=${encodeURIComponent(country)}`, {
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			});
			const regions = await response.json();
			if (!response.ok) throw new Error(regions.message || 'Daftar wilayah gagal dimuat.');
			if (camabaRegion.tomselect) camabaRegion.tomselect.destroy();
			camabaRegion.replaceChildren(new Option('Pilih wilayah', ''));
			regions.forEach((region) => camabaRegion.add(new Option(region.name, region.id)));
			initializeSearchableSelects(camabaRegion.closest('fieldset') || document);
		} catch (error) {
			document.dispatchEvent(new CustomEvent('simak:notification', {
				detail: { message: error.message, variant: 'error' },
			}));
		}
	});
}

document.querySelector('[data-confirm-camaba]')?.form?.addEventListener('submit', (event) => {
	if (!window.confirm('Kirim biodata dan riwayat pendidikan ke Feeder, lalu buat akun mahasiswa SIAKAD?')) event.preventDefault();
});

const graduationPeriodTable = document.querySelector('[data-graduation-period-table]');
if (graduationPeriodTable) {
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);
	const text = (value) => escapeHtml(value || '-');
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
	const canManage = graduationPeriodTable.dataset.canManage === 'true';
	new DataTable(graduationPeriodTable, {
		serverSide: true,
		processing: true,
		deferRender: true,
		ajax: graduationPeriodTable.dataset.ajaxUrl,
		columns: [
			{ data: 'nama_periode', render: text },
			{
				data: null,
				render: (_value, type, record) => type === 'display'
					? `${text(record.tanggal_mulai)} s.d. ${text(record.tanggal_akhir)}`
					: record.tanggal_mulai,
			},
			{ data: 'kuota_wisuda', render: text },
			{
				data: 'aktif',
				render: (value, type) => type === 'display'
					? `<span class="finance-status ${value === 'Y' ? 'is-paid' : ''}">${value === 'Y' ? 'Aktif' : 'Tidak aktif'}</span>`
					: (value === 'Y' ? 'Aktif' : 'Tidak aktif'),
			},
			{
				data: null,
				orderable: false,
				searchable: false,
				render: (_value, type, record) => type !== 'display' ? '' : !canManage ? '' : `<div class="finance-table-actions">
					<button class="table-icon-button" type="button" data-graduation-edit data-update-url="${escapeHtml(record.update_url)}" data-name="${escapeHtml(record.nama_periode)}" data-start="${escapeHtml(record.tanggal_mulai)}" data-end="${escapeHtml(record.tanggal_akhir)}" data-quota="${escapeHtml(record.kuota_wisuda)}" title="Ubah periode" aria-label="Ubah periode ${escapeHtml(record.nama_periode)}"><span data-lucide="pencil"></span></button>
					${record.aktif === 'Y' ? '' : `<form method="POST" action="${escapeHtml(record.activate_url)}" onsubmit="return window.confirm('Aktifkan periode wisuda? Periode aktif lain akan dinonaktifkan. Lanjutkan?')"><input type="hidden" name="_token" value="${escapeHtml(csrfToken)}"><button class="table-icon-button" type="submit" title="Aktifkan periode" aria-label="Aktifkan periode ${escapeHtml(record.nama_periode)}"><span data-lucide="circle-check"></span></button></form>`}
				</div>`,
			},
		],
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[1, 'desc']],
		layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' },
		language: {
			processing: 'Memuat data…',
			emptyTable: 'Belum ada periode wisuda.',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ periode',
			infoEmpty: 'Tidak ada periode wisuda',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari nama periode, tanggal, kuota, atau status',
			zeroRecords: 'Tidak ada periode wisuda yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
		drawCallback() {
			renderIcons(this.api().table().container());
		},
	});
	graduationPeriodTable.addEventListener('click', (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-graduation-edit]') : null;
		if (!button) return;
		const dialog = document.getElementById('graduation-period-edit');
		const form = dialog?.querySelector('[data-graduation-edit-form]');
		if (!dialog || !form) return;
		form.action = button.dataset.updateUrl;
		form.elements.id_periode.value = button.dataset.updateUrl.split('/').pop();
		form.elements.nama_periode.value = button.dataset.name || '';
		form.elements.tanggal_mulai.value = button.dataset.start || '';
		form.elements.tanggal_akhir.value = button.dataset.end || '';
		form.elements.kuota_wisuda.value = button.dataset.quota || '';
		dialog.showModal();
	});
	if (document.querySelector('#graduation-period-edit[data-open-on-load="true"]')) {
		document.getElementById('graduation-period-edit')?.showModal();
	}
}

const simakUsersTable = document.querySelector('[data-simak-users-table]');
if (simakUsersTable) {
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);
	const text = (value) => escapeHtml(value || '-');
	new DataTable(simakUsersTable, {
		serverSide: true,
		processing: true,
		deferRender: true,
		ajax: simakUsersTable.dataset.ajaxUrl,
		columns: [
			{ data: 'username', render: text },
			{ data: 'nama_lengkap', render: text },
			{ data: 'email', render: text },
			{ data: 'no_telp', render: text },
			{ data: 'scope', render: text },
			{
				data: 'blokir',
				render: (value, type) => type === 'display'
					? `<span class="status-chip ${value === 'N' ? 'active' : ''}">${value === 'N' ? 'Aktif' : 'Diblokir'}</span>`
					: (value === 'N' ? 'Aktif' : 'Diblokir'),
			},
			{
				data: 'edit_url',
				orderable: false,
				searchable: false,
				render: (url, type, record) => type === 'display'
					? `<button class="table-icon-button" type="button" data-simak-user-edit data-edit-url="${escapeHtml(url)}" data-user-title="${escapeHtml(simakUsersTable.dataset.userTitle)}" title="Ubah pengguna" aria-label="Ubah akun ${escapeHtml(record.username)}"><span data-lucide="pencil"></span></button>`
					: '',
			},
		],
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [[0, 'asc']],
		layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' },
		language: {
			processing: 'Memuat data…',
			emptyTable: 'Belum ada akun pada kategori ini.',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ akun',
			infoEmpty: 'Tidak ada akun',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari username, nama, email, prodi, atau fakultas',
			zeroRecords: 'Tidak ada akun yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
		drawCallback() {
			renderIcons(this.api().table().container());
		},
	});
}

const simakUserModal = document.querySelector('[data-simak-user-modal]');
if (simakUserModal) {
	const dialog = simakUserModal.querySelector('[data-simak-user-dialog]');
	const content = simakUserModal.querySelector('[data-simak-user-content]');
	const title = simakUserModal.querySelector('[data-simak-user-modal-title]');
	const errorBox = simakUserModal.querySelector('[data-simak-user-error]');
	let returnFocus = null;
	let previousBodyOverflow = '';
	let controls = [];

	const destroyControls = () => {
		controls.forEach((control) => control.destroy());
		controls = [];
	};
	const closeModal = () => {
		simakUserModal.hidden = true;
		document.body.style.overflow = previousBodyOverflow;
		destroyControls();
		returnFocus?.focus();
	};
	const syncFacultyField = () => {
		const level = content.querySelector('[data-account-level]');
		const field = content.querySelector('[data-account-faculty-field]');
		const faculty = content.querySelector('[data-account-faculty]');
		if (!level || !field || !faculty) return;
		const required = level.value === 'pimfak';
		field.hidden = !required;
		faculty.required = required;
		if (!required) faculty.tomselect?.clear();
	};
	const openModal = async (trigger) => {
		returnFocus = trigger;
		previousBodyOverflow = document.body.style.overflow;
		title.textContent = `${trigger.hasAttribute('data-simak-user-create') ? 'Tambah' : 'Ubah'} ${trigger.dataset.userTitle || 'Pengguna'}`;
		content.innerHTML = '<p class="subtitle">Memuat form akun…</p>';
		errorBox.hidden = true;
		errorBox.textContent = '';
		simakUserModal.hidden = false;
		document.body.style.overflow = 'hidden';
		dialog.focus();

		try {
			const url = trigger.dataset.createUrl || trigger.dataset.editUrl;
			const response = await fetch(url, {
				headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
			});
			if (!response.ok) throw new Error(`Form akun gagal dimuat (${response.status}).`);
			content.innerHTML = await response.text();
			controls = [...content.querySelectorAll('[data-searchable-select]')].map((element) => new TomSelect(element, {
				create: false,
				maxOptions: 100,
				allowEmptyOption: true,
				plugins: ['clear_button'],
			}));
			content.querySelector('[data-account-level]')?.addEventListener('change', syncFacultyField);
			syncFacultyField();
			renderIcons(content);
			content.querySelector('input:not([disabled])')?.focus();
		} catch (error) {
			errorBox.textContent = error.message;
			errorBox.hidden = false;
		}
	};

	document.addEventListener('click', (event) => {
		const trigger = event.target instanceof Element
			? event.target.closest('[data-simak-user-create], [data-simak-user-edit]')
			: null;
		if (trigger) openModal(trigger);
	});
	simakUserModal.addEventListener('click', (event) => {
		const button = event.target instanceof Element ? event.target.closest('[data-simak-user-close]') : null;
		if (button) closeModal();
	});
	simakUserModal.addEventListener('mousedown', (event) => {
		if (event.target === simakUserModal) closeModal();
	});
	simakUserModal.addEventListener('submit', async (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || ! form.matches('[data-simak-user-form]')) return;
		event.preventDefault();
		errorBox.hidden = true;
		const submitButton = form.querySelector('[type="submit"]');
		submitButton.disabled = true;
		try {
			const response = await fetch(form.action, {
				method: 'POST',
				body: new FormData(form),
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			});
			const payload = await response.json();
			if (!response.ok) {
				const message = Object.values(payload.errors || {}).flat().join(' ') || payload.message || `Simpan akun gagal (${response.status}).`;
				throw new Error(message);
			}
			closeModal();
			document.dispatchEvent(new CustomEvent('simak:notification', { detail: { message: payload.message } }));
			window.location.reload();
		} catch (error) {
			errorBox.textContent = error.message;
			errorBox.hidden = false;
			submitButton.disabled = false;
		}
	});
	document.addEventListener('keydown', (event) => {
		if (simakUserModal.hidden) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			closeModal();
		} else if (event.key === 'Tab') {
			const focusable = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled)')]
				.filter((element) => element.getClientRects().length > 0);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
				event.preventDefault();
				last?.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first?.focus();
			}
		}
	});
}

const studentActivityModal = document.querySelector('[data-student-activity-modal]');
if (studentActivityModal) {
	const dialog = studentActivityModal.querySelector('[role="dialog"]');
	const openButton = document.querySelector('[data-student-activity-open]');
	let returnFocus = null;
	let previousBodyOverflow = '';

	const closeModal = () => {
		studentActivityModal.hidden = true;
		document.body.style.overflow = previousBodyOverflow;
		returnFocus?.focus();
	};
	const openModal = () => {
		returnFocus = document.activeElement;
		previousBodyOverflow = document.body.style.overflow;
		studentActivityModal.hidden = false;
		document.body.style.overflow = 'hidden';
		dialog?.focus();
	};

	openButton?.addEventListener('click', openModal);
	studentActivityModal.querySelectorAll('[data-student-activity-close]').forEach((button) => {
		button.addEventListener('click', closeModal);
	});
	studentActivityModal.addEventListener('click', (event) => {
		if (event.target === studentActivityModal) closeModal();
	});
	document.addEventListener('keydown', (event) => {
		if (studentActivityModal.hidden) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			closeModal();
		} else if (event.key === 'Tab') {
			const focusable = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled)')]
				.filter((element) => element.getClientRects().length > 0);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last?.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first?.focus();
			}
		}
	});
	if (studentActivityModal.dataset.openOnLoad === 'true') openModal();
}

document.addEventListener('simak:notification', (event) => {
	const { message, variant = 'success' } = event.detail || {};
	if (!message) return;

	const toast = document.createElement('div');
	toast.className = `flash-toast${variant === 'error' ? ' is-error' : ''}`;
	toast.setAttribute('role', variant === 'error' ? 'alert' : 'status');
	toast.setAttribute('aria-live', variant === 'error' ? 'assertive' : 'polite');
	toast.innerHTML = `<span class="flash-mark" aria-hidden="true">${variant === 'error' ? '!' : 'OK'}</span>`;
	const text = document.createElement('p');
	text.className = 'flash-message';
	text.textContent = message;
	toast.append(text);
	document.body.append(toast);

	let timeout = window.setTimeout(() => {
		toast.classList.add('is-leaving');
		window.setTimeout(() => toast.remove(), 220);
	}, 2000);
	toast.addEventListener('mouseenter', () => window.clearTimeout(timeout));
	toast.addEventListener('mouseleave', () => {
		window.clearTimeout(timeout);
		timeout = window.setTimeout(() => {
			toast.classList.add('is-leaving');
			window.setTimeout(() => toast.remove(), 220);
		}, 2000);
	});
});

initializeSearchableSelects();

const accountLevel = document.querySelector('[data-account-level]');
const accountFacultyField = document.querySelector('[data-account-faculty-field]');
const accountFaculty = document.querySelector('[data-account-faculty]');
if (accountLevel && accountFacultyField && accountFaculty) {
	const syncFacultyScope = () => {
		const isFacultyLeader = accountLevel.value === 'pimfak';
		accountFacultyField.hidden = !isFacultyLeader;
		accountFaculty.required = isFacultyLeader;
		if (!isFacultyLeader) accountFaculty.tomselect?.clear();
	};

	accountLevel.addEventListener('change', syncFacultyScope);
	syncFacultyScope();
}

document.querySelectorAll('form[data-autosave-form]').forEach((form) => {
	const select = form.querySelector('[data-autosave-select]');
	if (!select) return;
	select.dataset.previousValue = select.value;

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const previousValue = select.dataset.previousValue;
		const formData = new FormData(form);
		select.disabled = true;
		select.tomselect?.disable();
		form.setAttribute('aria-busy', 'true');

		try {
			const response = await fetch(form.action, {
				method: form.method || 'POST',
				body: formData,
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			});
			if (!response.ok) {
				let payload = {};
				try {
					payload = await response.json();
				} catch {}
				const validationMessage = Object.values(payload.errors || {}).flat()[0];
				throw new Error(validationMessage || payload.message || `Perubahan gagal disimpan (${response.status}).`);
			}

			select.dataset.previousValue = select.value;
			document.dispatchEvent(new CustomEvent('simak:notification', {
				detail: { message: 'Perubahan berhasil disimpan.' },
			}));
		} catch (error) {
			select.value = previousValue;
			select.tomselect?.setValue(previousValue, true);
			document.dispatchEvent(new CustomEvent('simak:notification', {
				detail: { message: error.message, variant: 'error' },
			}));
		} finally {
			select.disabled = false;
			select.tomselect?.enable();
			form.removeAttribute('aria-busy');
		}
	});
});

const attendancePendingModal = document.querySelector('[data-attendance-pending-modal]');
if (attendancePendingModal) {
	const dialog = attendancePendingModal.querySelector('[role="dialog"]');
	const description = attendancePendingModal.querySelector('[data-attendance-pending-description]');
	const state = attendancePendingModal.querySelector('[data-attendance-pending-state]');
	const list = attendancePendingModal.querySelector('[data-attendance-pending-list]');
	let returnFocus = null;

	const closeModal = () => {
		attendancePendingModal.hidden = true;
		returnFocus?.focus();
	};

	document.addEventListener('click', async (event) => {
		const trigger = event.target instanceof Element ? event.target.closest('[data-attendance-pending-open]') : null;
		if (!trigger) return;

		returnFocus = trigger;
		attendancePendingModal.hidden = false;
		description.textContent = `${trigger.dataset.courseName} · Kelas ${trigger.dataset.courseClass}`;
		state.textContent = 'Memuat daftar mahasiswa...';
		list.replaceChildren();
		dialog.focus();

		try {
			const response = await fetch(trigger.dataset.pendingUrl, {
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			});
			if (!response.ok) throw new Error('Daftar mahasiswa belum dapat dimuat.');
			const payload = await response.json();
			const students = payload.students || [];
			state.textContent = students.length ? `${students.length} mahasiswa belum disahkan oleh prodi.` : 'Semua mahasiswa sudah disahkan oleh prodi.';
			students.forEach((student) => {
				const item = document.createElement('li');
				item.textContent = `${student.nim} · ${student.nama_mahasiswa}`;
				list.append(item);
			});
		} catch (error) {
			state.textContent = error.message;
		}
	});

	attendancePendingModal.querySelectorAll('[data-attendance-pending-close]').forEach((button) => {
		button.addEventListener('click', closeModal);
	});
	attendancePendingModal.addEventListener('click', (event) => {
		if (event.target === attendancePendingModal) closeModal();
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !attendancePendingModal.hidden) closeModal();
	});
}

const gradeSpreadsheetRoot = document.querySelector('[data-grade-spreadsheet]');
if (gradeSpreadsheetRoot) {
	import('./grade-spreadsheet.jsx')
		.then(({ mountGradeSpreadsheet }) => mountGradeSpreadsheet(gradeSpreadsheetRoot))
		.catch((error) => {
			console.error('Gagal memuat grid nilai mahasiswa.', error);
			gradeSpreadsheetRoot.setAttribute('role', 'alert');
			gradeSpreadsheetRoot.textContent = 'Grid input nilai gagal dimuat. Muat ulang halaman atau hubungi administrator.';
		});
}

const academicDashboardRoot = document.querySelector('[data-academic-dashboard]');
if (academicDashboardRoot) {
	import('./academic-dashboard.jsx')
		.then(({ mountAcademicDashboard }) => mountAcademicDashboard(academicDashboardRoot))
		.catch((error) => {
			console.error('Gagal memuat Dashboard Akademik.', error);
			academicDashboardRoot.querySelector('[data-academic-dashboard-mount]').textContent = 'Dashboard belum dapat dimuat. Muat ulang halaman atau hubungi administrator.';
		});
}

const edomResultsRoot = document.querySelector('[data-edom-results]');
if (edomResultsRoot) {
	import('./edom-results.jsx')
		.then(({ mountSurveyEdomResults }) => mountSurveyEdomResults(edomResultsRoot))
		.catch((error) => {
			console.error('Gagal memuat hasil Survey EDOM.', error);
		edomResultsRoot.querySelector('[data-edom-results-mount]').textContent = 'Hasil EDOM belum dapat dimuat. Muat ulang halaman atau hubungi administrator.';
		});
}

const thesisChatRoot = document.querySelector('[data-thesis-chat]');
if (thesisChatRoot) {
	import('./thesis-chat.jsx')
		.then(({ mountThesisChat }) => mountThesisChat(thesisChatRoot))
		.catch((error) => {
			console.error('Gagal memuat ruang bimbingan.', error);
			thesisChatRoot.querySelector('[data-thesis-chat-mount]').textContent = 'Ruang bimbingan belum dapat dimuat. Muat ulang halaman atau hubungi administrator.';
		});
}

const scheduleCopyModal = document.querySelector('[data-schedule-copy-modal]');
if (scheduleCopyModal) {
	const scheduleCopyDialog = scheduleCopyModal.querySelector('[role="dialog"]');
	const scheduleCopyForm = scheduleCopyModal.querySelector('[data-schedule-copy-form]');
	const scheduleCopySource = scheduleCopyForm?.elements.namedItem('source_calendar');
	const scheduleCopyTarget = scheduleCopyForm?.elements.namedItem('target_calendar');
	const scheduleCopyError = scheduleCopyModal.querySelector('[data-schedule-copy-error]');
	const closeScheduleCopy = () => {
		scheduleCopyModal.hidden = true;
		scheduleCopyError.hidden = true;
	};

	document.querySelector('[data-schedule-copy-open]')?.addEventListener('click', () => {
		scheduleCopyModal.hidden = false;
		scheduleCopyDialog?.focus();
	});
	scheduleCopyModal.querySelectorAll('[data-schedule-copy-close]').forEach((button) => {
		button.addEventListener('click', closeScheduleCopy);
	});
	scheduleCopyModal.addEventListener('mousedown', (event) => {
		if (event.target === scheduleCopyModal) closeScheduleCopy();
	});
	scheduleCopyForm?.addEventListener('submit', (event) => {
		if (scheduleCopySource?.value !== '' && scheduleCopySource?.value === scheduleCopyTarget?.value) {
			event.preventDefault();
			scheduleCopyError.hidden = false;
			scheduleCopyTarget.tomselect?.focus();
		}
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !scheduleCopyModal.hidden) closeScheduleCopy();
	});
}

const courseRemovalConfirmModal = document.querySelector('[data-course-removal-modal]');
if (courseRemovalConfirmModal) {
	const dialog = courseRemovalConfirmModal.querySelector('[role="dialog"]');
	const title = courseRemovalConfirmModal.querySelector('[data-course-removal-confirm-title]');
	const message = courseRemovalConfirmModal.querySelector('[data-course-removal-confirm-message]');
	const cancelButton = courseRemovalConfirmModal.querySelector('[data-course-removal-confirm-cancel]');
	const submitButton = courseRemovalConfirmModal.querySelector('[data-course-removal-confirm-submit]');
	const closeButton = courseRemovalConfirmModal.querySelector('[data-course-removal-confirm-close]');
	let pendingForm = null;
	let returnFocus = null;

	const closeModal = () => {
		courseRemovalConfirmModal.hidden = true;
		pendingForm = null;
		returnFocus?.focus();
	};

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || ! form.matches('[data-course-removal-confirm]')) return;
		if (form.dataset.confirmed === 'true') {
			delete form.dataset.confirmed;
			return;
		}

		event.preventDefault();
		pendingForm = form;
		returnFocus = event.submitter;
		title.textContent = form.dataset.confirmTitle || 'Konfirmasi Penghapusan';
		message.textContent = form.dataset.confirmMessage || 'Lanjutkan penghapusan mata kuliah ini?';
		courseRemovalConfirmModal.hidden = false;
		cancelButton.focus();
	});

	const cancelConfirm = () => closeModal();
	cancelButton.addEventListener('click', cancelConfirm);
	closeButton.addEventListener('click', cancelConfirm);
	courseRemovalConfirmModal.addEventListener('mousedown', (event) => {
		if (event.target === courseRemovalConfirmModal) cancelConfirm();
	});
	submitButton.addEventListener('click', () => {
		if (!pendingForm) return;
		const form = pendingForm;
		form.dataset.confirmed = 'true';
		courseRemovalConfirmModal.hidden = true;
		form.requestSubmit();
	});
	document.addEventListener('keydown', (event) => {
		if (courseRemovalConfirmModal.hidden) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			cancelConfirm();
		} else if (event.key === 'Tab') {
			const focusable = [cancelButton, submitButton, closeButton].filter((element) => element.getClientRects().length > 0);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}
	});
}

const studentStatusCalendar = document.querySelector('[data-student-status-calendar]');
const studentStatusYear = document.querySelector('[data-student-status-year]');
if (studentStatusCalendar && studentStatusYear) {
	const updateSelectedYear = () => {
		studentStatusYear.value = studentStatusCalendar.selectedOptions[0]?.dataset.year || '';
	};
	studentStatusCalendar.addEventListener('change', updateSelectedYear);
	updateSelectedYear();
}

const studentStatusConfirmModal = document.querySelector('[data-student-status-modal]');
if (studentStatusConfirmModal) {
	const title = studentStatusConfirmModal.querySelector('[data-student-status-confirm-title]');
	const message = studentStatusConfirmModal.querySelector('[data-student-status-confirm-message]');
	const cancelButton = studentStatusConfirmModal.querySelector('[data-student-status-confirm-cancel]');
	const submitButton = studentStatusConfirmModal.querySelector('[data-student-status-confirm-submit]');
	const closeButton = studentStatusConfirmModal.querySelector('[data-student-status-confirm-close]');
	let pendingForm = null;
	let returnFocus = null;

	const closeModal = () => {
		studentStatusConfirmModal.hidden = true;
		pendingForm = null;
		returnFocus?.focus();
	};

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || ! form.matches('[data-student-status-confirm]')) return;
		if (form.dataset.confirmed === 'true') {
			delete form.dataset.confirmed;
			return;
		}

		event.preventDefault();
		pendingForm = form;
		returnFocus = event.submitter;
		title.textContent = form.dataset.confirmTitle || 'Konfirmasi Hapus Data';
		message.textContent = form.dataset.confirmMessage || 'Hapus riwayat status mahasiswa ini?';
		studentStatusConfirmModal.hidden = false;
		cancelButton.focus();
	});

	const cancelConfirm = () => closeModal();
	cancelButton.addEventListener('click', cancelConfirm);
	closeButton.addEventListener('click', cancelConfirm);
	studentStatusConfirmModal.addEventListener('mousedown', (event) => {
		if (event.target === studentStatusConfirmModal) cancelConfirm();
	});
	submitButton.addEventListener('click', () => {
		if (!pendingForm) return;
		const form = pendingForm;
		form.dataset.confirmed = 'true';
		studentStatusConfirmModal.hidden = true;
		form.requestSubmit();
	});
	document.addEventListener('keydown', (event) => {
		if (studentStatusConfirmModal.hidden) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			cancelConfirm();
		} else if (event.key === 'Tab') {
			const focusable = [cancelButton, submitButton, closeButton].filter((element) => element.getClientRects().length > 0);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}
	});
}

const accountMenu = document.querySelector('[data-account-menu]');
if (accountMenu) {
	const accountTrigger = accountMenu.querySelector('summary');
	document.addEventListener('click', (event) => {
		if (!accountMenu.contains(event.target)) accountMenu.open = false;
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && accountMenu.open) {
			accountMenu.open = false;
			accountTrigger?.focus();
		}
	});
}

const profileEditLink = document.querySelector('[data-account-edit]');
const profileModal = document.querySelector('[data-profile-modal]');
if (profileEditLink && profileModal) {
	const profileDialog = profileModal.querySelector('[role="dialog"]');
	const profileContent = profileModal.querySelector('[data-profile-modal-content]');
	const closeProfileButton = profileModal.querySelector('[data-profile-close]');
	let profileReturnFocus = null;
	let previousBodyOverflow = '';

	const closeProfileModal = () => {
		profileModal.hidden = true;
		profileContent.replaceChildren();
		document.body.style.overflow = previousBodyOverflow;
		profileReturnFocus?.focus();
	};

	const showProfileError = (message) => {
		profileContent.querySelector('[data-profile-error]')?.remove();
		const error = document.createElement('div');
		error.className = 'calendar-modal-error';
		error.dataset.profileError = '';
		error.setAttribute('role', 'alert');
		error.textContent = message;
		profileContent.prepend(error);
	};

	profileEditLink.addEventListener('click', async (event) => {
		event.preventDefault();
		profileReturnFocus = accountMenu.querySelector('summary');
		accountMenu.open = false;
		profileModal.hidden = false;
		previousBodyOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		profileContent.textContent = 'Memuat formulir profil…';
		closeProfileButton.focus();

		try {
			const response = await fetch(profileEditLink.href, {
				headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
			});
			if (!response.ok) throw new Error(`Form profil tidak dapat dimuat (${response.status}).`);
			const documentContent = new DOMParser().parseFromString(await response.text(), 'text/html');
			const form = documentContent.querySelector('.profile-edit-form');
			if (!form) throw new Error('Form profil tidak ditemukan.');
			profileContent.replaceChildren(document.importNode(form, true));
			const modalForm = profileContent.querySelector('.profile-edit-form');
			modalForm.addEventListener('submit', async (submitEvent) => {
				submitEvent.preventDefault();
				const submitButton = modalForm.querySelector('[type="submit"]');
				submitButton.disabled = true;
				try {
					const saveResponse = await fetch(modalForm.action, {
						method: 'POST',
						body: new FormData(modalForm),
						headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
					});
					const result = await saveResponse.json();
					if (!saveResponse.ok) {
						const messages = Object.values(result.errors || {}).flat();
						throw new Error(messages.join(' ') || result.message || `Gagal menyimpan profil (${saveResponse.status}).`);
					}
					window.location.reload();
				} catch (error) {
					showProfileError(error.message);
					submitButton.disabled = false;
				}
			});
			modalForm.querySelector('input:not([disabled])')?.focus();
		} catch (error) {
			showProfileError(error.message);
		}
	});

	closeProfileButton.addEventListener('click', closeProfileModal);
	profileModal.addEventListener('click', (event) => {
		if (event.target === profileModal) closeProfileModal();
	});
	document.addEventListener('keydown', (event) => {
		if (!profileModal.hidden && event.key === 'Escape') {
			closeProfileModal();
			return;
		}
		if (!profileModal.hidden && event.key === 'Tab') {
			const focusable = [...profileDialog.querySelectorAll('button:not(:disabled), input:not(:disabled), a[href]')]
				.filter((element) => element.getClientRects().length > 0);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last?.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first?.focus();
			}
		}
	});
}

const calendarTable = document.querySelector('[data-datatable="academic-calendar"]');

if (calendarTable) {
	const actionRoot = document.getElementById('calendar-actions-root');
	const calendarLabel = actionRoot?.dataset.calendarLabel || calendarTable.dataset.calendarLabel || 'Kalender Akademik';
	const exportName = calendarLabel === 'Kurikulum SP' ? 'kurikulum-sp' : 'kalender-akademik';
	const dataTable = new DataTable(calendarTable, {
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [],
		columnDefs: [{ targets: '.no-export', orderable: false, searchable: false }],
		layout: {
			topStart: {
				buttons: [
					{
						extend: 'excelHtml5',
						text: '<span data-lucide="file-spreadsheet" aria-hidden="true"></span><span>Excel</span>',
						className: 'dt-export-button',
						titleAttr: 'Unduh halaman ini sebagai Excel',
						filename: exportName,
						exportOptions: { columns: ':not(.no-export)', modifier: { page: 'current', search: 'applied' } },
					},
					{
						extend: 'csvHtml5',
						text: '<span data-lucide="file-down" aria-hidden="true"></span><span>CSV</span>',
						className: 'dt-export-button',
						titleAttr: 'Unduh halaman ini sebagai CSV',
						filename: exportName,
						exportOptions: { columns: ':not(.no-export)', modifier: { page: 'current', search: 'applied' } },
					},
				],
			},
			topEnd: 'search',
			bottomStart: 'info',
			bottomEnd: 'paging',
		},
		language: {
			emptyTable: `Belum ada data ${calendarLabel}`,
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ periode',
			infoEmpty: `Tidak ada data ${calendarLabel}`,
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Tahun akademik atau program studi',
			zeroRecords: `Tidak ada data ${calendarLabel} yang cocok`,
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
	});

	renderIcons(dataTable.buttons().container()[0]);

	const canEdit = actionRoot?.dataset.canEdit === 'true';
	const canActivate = actionRoot?.dataset.canActivate === 'true';
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);
	const formatDate = (value) => value || '-';
	const actionMarkup = (calendar) => {
		if (!canEdit && !canActivate) return '';

		const id = escapeHtml(calendar.id_kalender);
		const edit = canEdit
			? `<button class="table-icon-button" type="button" data-calendar-edit data-calendar-id="${id}" data-calendar-name="${escapeHtml(calendar.namatahun || calendar.tahun)}" data-tgl-mulai-krs="${escapeHtml(calendar.tgl_mulai_krs)}" data-tgl-akhir-krs="${escapeHtml(calendar.tgl_akhir_krs)}" data-tgl-mulai-isinilai="${escapeHtml(calendar.tgl_mulai_isinilai)}" data-tgl-akhir-isinilai="${escapeHtml(calendar.tgl_akhir_isinilai)}" aria-label="Ubah tanggal kalender" title="Ubah tanggal kalender"><span data-lucide="pencil" aria-hidden="true"></span></button>`
			: '';
		const active = calendar.status === 'Y';
		const toggle = canActivate
			? `<button class="table-icon-button activate" type="button" data-calendar-toggle data-calendar-id="${id}" data-calendar-active="${active}" aria-label="${active ? 'Nonaktifkan kalender' : 'Aktifkan kalender'}" title="${active ? 'Nonaktifkan kalender' : 'Aktifkan kalender'}"><span data-lucide="${active ? 'circle-minus' : 'circle-check'}" aria-hidden="true"></span></button>`
			: '';

		return `<div class="row-actions">${edit}${toggle}</div>`;
	};
	const statusMarkup = (active) => `<span class="status-chip ${active ? 'active' : ''}">${active ? 'Aktif' : 'Tidak aktif'}</span>`;
	const rowData = (calendar) => [
		`<span class="period">${escapeHtml(calendar.namatahun || calendar.tahun)}</span>`,
		`${escapeHtml(calendar.nama_program_studi || 'Prodi tidak ditemukan')}<span class="subtext">${escapeHtml(calendar.nama_jenjang_pendidikan)}</span>`,
		escapeHtml(calendar.periode),
		`${escapeHtml(formatDate(calendar.tgl_mulai_krs))}<span class="subtext">s.d. ${escapeHtml(formatDate(calendar.tgl_akhir_krs))}</span>`,
		`${escapeHtml(formatDate(calendar.tgl_mulai_isinilai))}<span class="subtext">s.d. ${escapeHtml(formatDate(calendar.tgl_akhir_isinilai))}</span>`,
		statusMarkup(calendar.status === 'Y'),
		...(canEdit || canActivate ? [actionMarkup(calendar)] : []),
	];
	const findRow = (id) => dataTable.row((index, data, node) => node?.dataset.calendarRow === String(id));

	window.simakCalendarTable = {
		addCalendars(calendars) {
			const addedRows = [];
			calendars.forEach((calendar) => {
				const existing = findRow(calendar.id_kalender);
				if (existing.any()) {
					existing.data(rowData(calendar));
				} else {
					addedRows.push({
						id: calendar.id_kalender,
						row: dataTable.row.add(rowData(calendar)),
					});
				}
			});
			dataTable.draw(false);
			addedRows.forEach(({ id, row }) => {
				const node = row.node();
				if (node) {
					node.dataset.calendarRow = id;
					renderIcons(node);
				}
			});
		},
		updateCalendar(calendar) {
			const row = findRow(calendar.id_kalender);
			if (!row.any()) return;

			row.data(rowData(calendar)).draw(false);
			const node = row.node();
			if (node) {
				node.dataset.calendarRow = calendar.id_kalender;
				renderIcons(node);
			}
		},
		updateStatuses(statuses) {
			statuses.forEach(({ id, active }) => {
				const row = findRow(id);
				const node = row.node();
				if (!node) return;

				const statusCell = node.querySelector('[data-calendar-status]') || node.cells[5];
				if (statusCell) statusCell.innerHTML = statusMarkup(active);

				const toggle = node.querySelector('[data-calendar-toggle]');
				if (toggle) {
					toggle.dataset.calendarActive = String(active);
					toggle.setAttribute('aria-label', active ? 'Nonaktifkan kalender' : 'Aktifkan kalender');
					toggle.title = active ? 'Nonaktifkan kalender' : 'Aktifkan kalender';
					const icon = document.createElement('span');
					icon.dataset.lucide = active ? 'circle-minus' : 'circle-check';
					icon.setAttribute('aria-hidden', 'true');
					toggle.replaceChildren(icon);
					renderIcons(toggle);
				}

				row.invalidate('dom');
			});
			dataTable.draw(false);
		},
	};

	if (actionRoot) {
		import('./calendar-actions.jsx')
			.then(({ mountCalendarActions }) => mountCalendarActions(actionRoot))
			.catch((error) => {
				console.error('Gagal memuat interaksi kalender.', error);
				actionRoot.setAttribute('role', 'alert');
				actionRoot.textContent = 'Fitur interaktif kalender gagal dimuat. Muat ulang halaman atau hubungi administrator.';
			});
	}
}

const reenrollmentConfirmModal = document.querySelector('[data-reenrollment-confirm-modal]');
if (reenrollmentConfirmModal) {
	const confirmDialog = reenrollmentConfirmModal.querySelector('[role="dialog"]');
	const confirmTitle = reenrollmentConfirmModal.querySelector('[data-reenrollment-confirm-title]');
	const confirmMessage = reenrollmentConfirmModal.querySelector('[data-reenrollment-confirm-message]');
	const cancelButton = reenrollmentConfirmModal.querySelector('[data-reenrollment-confirm-cancel]');
	const submitButton = reenrollmentConfirmModal.querySelector('[data-reenrollment-confirm-submit]');
	const closeButton = reenrollmentConfirmModal.querySelector('[data-reenrollment-confirm-close]');
	let pendingForm = null;
	let returnFocus = null;

	const closeConfirmModal = () => {
		reenrollmentConfirmModal.hidden = true;
		pendingForm = null;
		returnFocus?.focus();
	};

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || ! form.matches('[data-reenrollment-confirm]')) return;
		if (form.dataset.confirmed === 'true') {
			delete form.dataset.confirmed;
			return;
		}

		event.preventDefault();
		pendingForm = form;
		returnFocus = event.submitter;
		confirmTitle.textContent = form.dataset.confirmTitle || 'Konfirmasi Registrasi Ulang';
		confirmMessage.textContent = form.dataset.confirmMessage || 'Lanjutkan tindakan ini?';
		reenrollmentConfirmModal.hidden = false;
		cancelButton.focus();
	});

	const cancelConfirm = () => closeConfirmModal();
	cancelButton.addEventListener('click', cancelConfirm);
	closeButton.addEventListener('click', cancelConfirm);
	reenrollmentConfirmModal.addEventListener('click', (event) => {
		if (event.target === reenrollmentConfirmModal) cancelConfirm();
	});
	submitButton.addEventListener('click', () => {
		if (!pendingForm) return;
		const form = pendingForm;
		form.dataset.confirmed = 'true';
		reenrollmentConfirmModal.hidden = true;
		form.requestSubmit();
	});
	document.addEventListener('keydown', (event) => {
		if (reenrollmentConfirmModal.hidden) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			cancelConfirm();
		} else if (event.key === 'Tab') {
			const focusable = [cancelButton, submitButton, closeButton].filter((element) => element.getClientRects().length > 0);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}
	});
}

const scheduleConfirmModal = document.querySelector('[data-schedule-confirm-modal]');
if (scheduleConfirmModal) {
	const confirmTitle = scheduleConfirmModal.querySelector('[data-schedule-confirm-title]');
	const confirmMessage = scheduleConfirmModal.querySelector('[data-schedule-confirm-message]');
	const cancelButton = scheduleConfirmModal.querySelector('[data-schedule-confirm-cancel]');
	const submitButton = scheduleConfirmModal.querySelector('[data-schedule-confirm-submit]');
	const closeButton = scheduleConfirmModal.querySelector('[data-schedule-confirm-close]');
	let pendingForm = null;
	let returnFocus = null;

	const closeConfirmModal = () => {
		scheduleConfirmModal.hidden = true;
		pendingForm = null;
		returnFocus?.focus();
	};

	document.addEventListener('submit', (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || ! form.matches('[data-schedule-confirm]')) return;
		if (form.matches('[data-short-semester-delete]')) return;
		if (form.dataset.confirmed === 'true') {
			delete form.dataset.confirmed;
			return;
		}

		event.preventDefault();
		pendingForm = form;
		returnFocus = event.submitter;
		confirmTitle.textContent = form.dataset.confirmTitle || 'Konfirmasi Jadwal';
		confirmMessage.textContent = form.dataset.confirmMessage || 'Lanjutkan tindakan ini?';
		scheduleConfirmModal.hidden = false;
		cancelButton.focus();
	});

	const cancelConfirm = () => closeConfirmModal();
	cancelButton.addEventListener('click', cancelConfirm);
	closeButton.addEventListener('click', cancelConfirm);
	scheduleConfirmModal.addEventListener('click', (event) => {
		if (event.target === scheduleConfirmModal) cancelConfirm();
	});
	submitButton.addEventListener('click', () => {
		if (!pendingForm) return;
		const form = pendingForm;
		form.dataset.confirmed = 'true';
		scheduleConfirmModal.hidden = true;
		form.requestSubmit();
	});
	document.addEventListener('keydown', (event) => {
		if (scheduleConfirmModal.hidden) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			cancelConfirm();
		} else if (event.key === 'Tab') {
			const focusable = [cancelButton, submitButton, closeButton].filter((element) => element.getClientRects().length > 0);
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}
	});
}

const shortSemesterProgressModal = document.querySelector('[data-short-semester-progress-modal]');
if (shortSemesterProgressModal) {
	const progressTitle = shortSemesterProgressModal.querySelector('#short-semester-progress-title');
	const progressMessage = shortSemesterProgressModal.querySelector('#short-semester-progress-message');
	const progressTrack = shortSemesterProgressModal.querySelector('.schedule-progress-track');
	const progressBar = shortSemesterProgressModal.querySelector('[data-short-semester-progress-bar]');
	const progressValue = shortSemesterProgressModal.querySelector('[data-short-semester-progress-value]');
	const progressErrors = shortSemesterProgressModal.querySelector('[data-short-semester-progress-errors]');
	const progressClose = shortSemesterProgressModal.querySelector('[data-short-semester-progress-close]');
	progressClose?.addEventListener('click', () => {
		shortSemesterProgressModal.hidden = true;
		window.location.reload();
	});

	const setProgress = (current, total) => {
		const percent = total === 0 ? 100 : Math.round((current / total) * 100);
		progressBar.style.width = `${percent}%`;
		progressValue.textContent = `${percent}%`;
		progressTrack.setAttribute('aria-valuenow', String(percent));
	};

	const showShortSemesterProgress = (title, messageOverride) => {
		progressTitle.textContent = title;
		progressMessage.textContent = messageOverride;
		progressErrors.innerHTML = '';
		progressErrors.hidden = true;
		if (progressClose) progressClose.hidden = true;
		progressTrack.classList.add('is-pending');
		setProgress(0, 100);
		shortSemesterProgressModal.hidden = false;
	};

	document.addEventListener('submit', async (event) => {
		const form = event.target;
		if (!(form instanceof HTMLFormElement) || !form.matches('[data-short-semester-delete]')) return;
		event.preventDefault();
		const submitButton = form.querySelector('[type="submit"]');
		if (submitButton) submitButton.disabled = true;
		showShortSemesterProgress(
			'Menghapus Jadwal SP',
			'Menghapus dosen pengampu di Feeder, lalu menghapus kelas dan jadwal SIAKAD...'
		);
		try {
			const response = await fetch(form.action, {
				method: form.method || 'POST',
				body: new FormData(form),
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			});
			const payload = await response.json();
			if (!response.ok) {
				throw new Error(payload.message || 'Tindakan pada Jadwal SP gagal.');
			}
			const totalSteps = Math.max(1, (Array.isArray(payload.teacher_ids) ? payload.teacher_ids.length : 0) + 1);
			progressTrack.classList.remove('is-pending');
			setProgress(totalSteps, totalSteps);
			progressMessage.textContent = payload.message || 'Proses Jadwal SP selesai.';
			if (progressClose) progressClose.hidden = true;
			window.setTimeout(() => window.location.assign(payload.redirect_url || form.action), 1000);
		} catch (error) {
			progressTrack.classList.remove('is-pending');
			progressMessage.textContent = error.message;
			const item = document.createElement('li');
			item.textContent = error.message;
			progressErrors.append(item);
			progressErrors.hidden = false;
			if (progressClose) progressClose.hidden = false;
			if (submitButton) submitButton.disabled = false;
		}
	});
}

const shortSemesterStudentsModal = document.querySelector('[data-short-semester-students-modal]');
if (shortSemesterStudentsModal) {
	const studentBody = shortSemesterStudentsModal.querySelector('[data-short-semester-student-body]');
	const studentSearch = shortSemesterStudentsModal.querySelector('#short-semester-student-search');
	const submitButton = shortSemesterStudentsModal.querySelector('[data-short-semester-students-submit]');
	const closeButton = shortSemesterStudentsModal.querySelector('[data-short-semester-students-close]');
	const createModalPager = (body, controls, status, pageSizeSelect, previousButton, nextButton) => {
		let rows = [];
		let filteredRows = [];
		let currentPage = 0;
		let pageSize = Number(pageSizeSelect.value) || 10;
		let emptyMessage = 'Tidak ada data';

		const render = () => {
			const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
			currentPage = Math.min(currentPage, pageCount - 1);
			if (!filteredRows.length) {
				const row = document.createElement('tr');
				const cell = document.createElement('td');
				cell.colSpan = 4;
				cell.className = 'short-semester-student-empty';
				cell.textContent = rows.length ? 'Tidak ada data yang cocok dengan pencarian.' : emptyMessage;
				row.append(cell);
				body.replaceChildren(row);
				status.textContent = rows.length ? 'Tidak ada hasil' : emptyMessage;
				controls.hidden = true;
				return;
			}
			const start = currentPage * pageSize;
			body.replaceChildren(...filteredRows.slice(start, start + pageSize));
			status.textContent = filteredRows.length
				? `Menampilkan ${start + 1}-${Math.min(start + pageSize, filteredRows.length)} dari ${filteredRows.length} data`
				: 'Tidak ada data';
			controls.hidden = filteredRows.length <= pageSize;
			previousButton.disabled = currentPage === 0;
			nextButton.disabled = currentPage >= pageCount - 1;
		};

		const setRows = (nextRows, emptyMessage) => {
			rows = nextRows;
			filteredRows = rows;
			currentPage = 0;
			emptyMessage = emptyMessage;
			render();
		};

		const filter = (value) => {
			const normalized = value.trim().toLowerCase();
			filteredRows = normalized
				? rows.filter((row) => row.textContent.toLowerCase().includes(normalized))
				: rows;
			currentPage = 0;
			render();
		};

		previousButton.addEventListener('click', () => {
			currentPage = Math.max(0, currentPage - 1);
			render();
		});
		nextButton.addEventListener('click', () => {
			currentPage += 1;
			render();
		});
		pageSizeSelect.addEventListener('change', () => {
			pageSize = Number(pageSizeSelect.value) || 10;
			currentPage = 0;
			render();
		});

		return { filter, setRows };
	};
	const studentPager = createModalPager(
		studentBody,
		shortSemesterStudentsModal.querySelector('[data-short-semester-student-pagination]'),
		shortSemesterStudentsModal.querySelector('[data-short-semester-student-page-status]'),
		shortSemesterStudentsModal.querySelector('[data-short-semester-student-page-size]'),
		shortSemesterStudentsModal.querySelector('[data-short-semester-student-previous]'),
		shortSemesterStudentsModal.querySelector('[data-short-semester-student-next]'),
	);
	let currentScheduleId = null;
	const showStudentMessage = (message) => {
		studentPager.setRows([], message);
	};

	const showStudentRows = (students) => {
		if (!students.length) {
			showStudentMessage('Tidak ada mahasiswa yang memenuhi syarat untuk ditambahkan.');
			return;
		}

		const rows = [];
		students.forEach((student) => {
			const row = document.createElement('tr');
			const checkCell = document.createElement('td');
			const checkbox = document.createElement('input');
			checkbox.type = 'checkbox';
			checkbox.value = student.id_mahasiswa;
			checkbox.dataset.shortSemesterStudentCheck = '';
			checkCell.append(checkbox);
			row.append(checkCell);
			[student.nim, student.nama_mahasiswa, student.nama_status_mahasiswa || student.id_status_mahasiswa]
				.forEach((value) => {
					const cell = document.createElement('td');
					cell.textContent = value || '-';
					row.append(cell);
				});
			rows.push(row);
		});
		studentPager.setRows(rows, 'Tidak ada mahasiswa yang memenuhi syarat untuk ditambahkan.');
	};

	const openStudentModal = async (scheduleId) => {
		currentScheduleId = scheduleId;
		showStudentMessage('Memuat daftar mahasiswa...');
		shortSemesterStudentsModal.hidden = false;
		studentSearch.value = '';
		try {
			const response = await fetch(`/jadwal-sp/${scheduleId}/peserta`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
			const payload = await response.json();
			if (!response.ok) throw new Error(payload.message || 'Gagal memuat data mahasiswa.');
			showStudentRows(payload.students || []);
		} catch (error) {
			showStudentMessage(error.message);
		}
	};

	const closeStudentModal = () => {
		shortSemesterStudentsModal.hidden = true;
		currentScheduleId = null;
		studentSearch.value = '';
	};

	document.addEventListener('click', async (event) => {
		const trigger = event.target.closest('[data-short-semester-add-participants]');
		if (!trigger) return;
		event.preventDefault();
		const scheduleId = trigger.dataset.scheduleId;
		if (scheduleId) await openStudentModal(scheduleId);
	});

	studentSearch.addEventListener('input', () => {
		studentPager.filter(studentSearch.value);
	});

	closeButton.addEventListener('click', closeStudentModal);
	shortSemesterStudentsModal.addEventListener('click', (event) => {
		if (event.target === shortSemesterStudentsModal) closeStudentModal();
	});

	const progressTo = (current, total) => {
		const modal = document.querySelector('[data-short-semester-progress-modal]');
		if (!modal) return;
		const track = modal.querySelector('.schedule-progress-track');
		const bar = modal.querySelector('[data-short-semester-progress-bar]');
		const value = modal.querySelector('[data-short-semester-progress-value]');
		if (!track || !bar || !value) return;
		const percent = total === 0 ? 100 : Math.round((current / total) * 100);
		bar.style.width = `${percent}%`;
		value.textContent = `${percent}%`;
		track.setAttribute('aria-valuenow', String(percent));
	};

	submitButton.addEventListener('click', async () => {
		if (!currentScheduleId) return;
		const selectedStudents = [...studentBody.querySelectorAll('[data-short-semester-student-check]:checked')].map((checkbox) => checkbox.value);
		const scheduleId = currentScheduleId;
		if (!selectedStudents.length) {
			alert('Pilih minimal satu mahasiswa untuk ditambahkan ke kelas SP.');
			return;
		}

		const modal = document.querySelector('[data-short-semester-progress-modal]');
		const title = modal?.querySelector('#short-semester-progress-title');
		const message = modal?.querySelector('#short-semester-progress-message');
		const errors = modal?.querySelector('[data-short-semester-progress-errors]');
		if (modal && title && message && errors) {
			title.textContent = 'Menambah Peserta Kelas SP';
			message.textContent = 'Memproses peserta satu per satu agar tidak melebihi batas waktu server Feeder...';
			errors.innerHTML = '';
			errors.hidden = true;
			const progressClose = modal.querySelector('[data-short-semester-progress-close]');
			if (progressClose) progressClose.hidden = true;
			modal.hidden = false;
		}
		closeStudentModal();

		let processed = 0;
		const total = selectedStudents.length;
		const failures = [];
		progressTo(0, total);
		for (const studentId of selectedStudents) {
			const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
			try {
				const response = await fetch(`/jadwal-sp/${scheduleId}/peserta/${studentId}`, {
					method: 'POST',
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': csrfToken,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({}),
				});
				const payload = await response.json().catch(() => ({}));
				if (!response.ok) failures.push(payload.message || `Gagal menambahkan mahasiswa ${studentId}.`);
			} catch (error) {
				failures.push(error.message || `Gagal menambahkan mahasiswa ${studentId}.`);
			}
			processed += 1;
			progressTo(processed, total);
			if (modal && message) message.textContent = `${processed}/${total} mahasiswa selesai diproses.`;
		}

		if (modal && title && message && errors) {
			title.textContent = 'Peserta Kelas SP Selesai';
			const successful = total - failures.length;
			message.textContent = `${successful} berhasil, ${failures.length} gagal dari ${total} mahasiswa.`;
			if (failures.length) {
				errors.replaceChildren(...failures.map((failure) => {
					const item = document.createElement('li');
					item.textContent = failure;
					return item;
				}));
				errors.hidden = false;
				const progressClose = modal.querySelector('[data-short-semester-progress-close]');
				if (progressClose) progressClose.hidden = false;
				return;
			}
		}
		window.setTimeout(() => {
			if (modal) modal.hidden = true;
			window.location.reload();
		}, 1800);
	});

	const participantsModal = document.querySelector('[data-short-semester-participants-modal]');
	if (participantsModal) {
		const participantBody = participantsModal.querySelector('[data-short-semester-participant-body]');
		const participantSearch = participantsModal.querySelector('#short-semester-participant-search');
		const participantsClose = participantsModal.querySelector('[data-short-semester-participants-close]');
		const participantPager = createModalPager(
			participantBody,
			participantsModal.querySelector('[data-short-semester-participant-pagination]'),
			participantsModal.querySelector('[data-short-semester-participant-page-status]'),
			participantsModal.querySelector('[data-short-semester-participant-page-size]'),
			participantsModal.querySelector('[data-short-semester-participant-previous]'),
			participantsModal.querySelector('[data-short-semester-participant-next]'),
		);
		let participantsScheduleId = null;
		const showParticipantMessage = (message) => {
			participantPager.setRows([], message);
		};

		const showParticipants = (participants) => {
			if (!participants.length) {
				showParticipantMessage('Belum ada peserta pada kelas ini.');
				return;
			}
			const rows = [];
			participants.forEach((participant) => {
				const row = document.createElement('tr');
				[participant.nim || '-', participant.nama_mahasiswa || '-', participant.nama_status_mahasiswa || participant.id_status_mahasiswa || '-']
					.forEach((value) => {
						const cell = document.createElement('td');
						cell.textContent = value;
						row.append(cell);
					});
				const actionCell = document.createElement('td');
				const deleteButton = document.createElement('button');
				deleteButton.type = 'button';
				deleteButton.className = 'table-icon-button reset';
				deleteButton.dataset.shortSemesterDeleteParticipant = '';
				deleteButton.dataset.studentId = participant.id_mahasiswa;
				deleteButton.setAttribute('aria-label', `Hapus peserta ${participant.nama_mahasiswa || participant.nim}`);
				deleteButton.title = 'Hapus peserta';
				deleteButton.append(createElement(Trash2, {
					width: 16,
					height: 16,
					'aria-hidden': 'true',
					focusable: 'false',
				}));
				actionCell.append(deleteButton);
				row.append(actionCell);
				rows.push(row);
			});
			participantPager.setRows(rows, 'Belum ada peserta pada kelas ini.');
		};

		const closeParticipantsModal = () => {
			participantsModal.hidden = true;
			participantsScheduleId = null;
			participantSearch.value = '';
		};

		document.addEventListener('click', async (event) => {
			const trigger = event.target.closest('[data-short-semester-view-participants]');
			if (!trigger) return;
			event.preventDefault();
			participantsScheduleId = trigger.dataset.scheduleId;
			showParticipantMessage('Memuat daftar peserta...');
			participantsModal.hidden = false;
			participantSearch.value = '';
			try {
				const response = await fetch(`/jadwal-sp/${participantsScheduleId}/peserta/terdaftar`, {
					headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
				});
				const payload = await response.json();
				if (!response.ok) throw new Error(payload.message || 'Gagal memuat daftar peserta.');
				showParticipants(payload.participants || []);
			} catch (error) {
				showParticipantMessage(error.message);
			}
		});

		participantSearch.addEventListener('input', () => {
			participantPager.filter(participantSearch.value);
		});

		participantsClose.addEventListener('click', closeParticipantsModal);
		participantsModal.addEventListener('click', (event) => {
			if (event.target === participantsModal) closeParticipantsModal();
		});

		participantBody.addEventListener('click', async (event) => {
			const button = event.target.closest('[data-short-semester-delete-participant]');
			if (!button || !participantsScheduleId) return;
			const studentId = button.dataset.studentId;
			if (!window.confirm('Hapus mahasiswa ini dari peserta kelas SP?')) return;
			button.disabled = true;
			const modal = document.querySelector('[data-short-semester-progress-modal]');
			const title = modal?.querySelector('#short-semester-progress-title');
			const message = modal?.querySelector('#short-semester-progress-message');
			const errors = modal?.querySelector('[data-short-semester-progress-errors]');
			if (modal && title && message && errors) {
				title.textContent = 'Menghapus Peserta Kelas SP';
				message.textContent = 'Menghapus satu peserta dari Feeder dan KRS SIAKAD...';
				errors.replaceChildren();
				errors.hidden = true;
				const progressClose = modal.querySelector('[data-short-semester-progress-close]');
				if (progressClose) progressClose.hidden = true;
				modal.hidden = false;
			}
			progressTo(0, 1);
			try {
				const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
				const response = await fetch(`/jadwal-sp/${participantsScheduleId}/peserta/${studentId}`, {
					method: 'DELETE',
					headers: {
						Accept: 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': csrfToken,
					},
				});
				const payload = await response.json().catch(() => ({}));
				if (!response.ok) throw new Error(payload.message || 'Gagal menghapus peserta kelas SP.');
				progressTo(1, 1);
				if (title) title.textContent = 'Peserta Kelas SP Dihapus';
				if (message) message.textContent = payload.message || 'Peserta berhasil dihapus.';
				const progressClose = modal?.querySelector('[data-short-semester-progress-close]');
				if (progressClose) progressClose.hidden = true;
				window.setTimeout(() => {
					if (modal) modal.hidden = true;
					window.location.reload();
				}, 1000);
			} catch (error) {
				if (modal && message && errors) {
					message.textContent = error.message;
					const item = document.createElement('li');
					item.textContent = error.message;
					errors.replaceChildren(item);
					errors.hidden = false;
				}
				const progressClose = modal?.querySelector('[data-short-semester-progress-close]');
				if (progressClose) progressClose.hidden = false;
				button.disabled = false;
			}
		});
	}
}

const courseGradesTable = document.querySelector('[data-datatable="course-grades"]');
if (courseGradesTable) {
	new DataTable(courseGradesTable, {
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [],
		columnDefs: [{ targets: '.no-export', orderable: false, searchable: false }],
		layout: {
			topStart: 'pageLength',
			topEnd: 'search',
			bottomStart: 'info',
			bottomEnd: 'paging',
		},
		language: {
			emptyTable: 'Belum ada jadwal kuliah',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ jadwal',
			infoEmpty: 'Tidak ada jadwal kuliah',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari mata kuliah atau kelas',
			zeroRecords: 'Tidak ada jadwal yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
	});
}

document.querySelectorAll('[data-datatable="thesis-list"]').forEach((table) => {
	const thesisExportFilename = table.dataset.thesisExportFilename || 'data-e-skripsi';
	const dataTable = new DataTable(table, {
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [],
		columnDefs: [{ targets: '.no-export', orderable: false, searchable: false }],
		drawCallback() {
			renderIcons(this.api().table().container());
		},
		layout: {
			topStart: {
				buttons: [
					{
						extend: 'excelHtml5',
						text: '<span data-lucide="file-spreadsheet" aria-hidden="true"></span><span>Excel</span>',
						className: 'dt-export-button',
						titleAttr: 'Unduh data sebagai Excel',
						filename: thesisExportFilename,
						exportOptions: { columns: ':not(.no-export)', modifier: { page: 'all', search: 'applied' } },
					},
					{
						extend: 'csvHtml5',
						text: '<span data-lucide="file-down" aria-hidden="true"></span><span>CSV</span>',
						className: 'dt-export-button',
						titleAttr: 'Unduh data sebagai CSV',
						filename: thesisExportFilename,
						exportOptions: { columns: ':not(.no-export)', modifier: { page: 'all', search: 'applied' } },
					},
				],
			},
			topEnd: 'search',
			bottomStart: 'info',
			bottomEnd: 'paging',
		},
		language: {
			emptyTable: 'Belum ada data',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
			infoEmpty: 'Tidak ada data',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari data',
			zeroRecords: 'Tidak ada data yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
	});
	renderIcons(dataTable.buttons().container()[0]);
});

const masterActionRoot = document.getElementById('master-data-actions-root');
const masterDataTable = document.querySelector('[data-datatable="master-data"]');

if (masterDataTable) {
	const masterColumns = JSON.parse(masterDataTable.dataset.masterColumns || '[]');
	const canEditMasterData = masterActionRoot?.dataset.canEdit === 'true';
	const canResetPassword = masterActionRoot?.dataset.canResetPassword === 'true';
	const canManageCourseEvaluations = masterActionRoot?.dataset.canCourseEvaluations === 'true';
	const hasActionColumn = Boolean(masterDataTable.tHead?.querySelector('th.no-export'));
	const escapeMasterHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);
	const columns = masterColumns.map((field) => ({
		data: `values.${field}`,
		className: ['status', 'nama_status_mahasiswa', 'status_perguruan_tinggi', 'semester', 'nama_jenjang_pendidikan'].includes(field)
			? 'dt-body-center dt-head-center'
			: 'dt-body-left dt-head-left',
		defaultContent: '-',
		render(value, type) {
			return type === 'display' ? escapeMasterHtml(value || '-') : value;
		},
	}));

	if (hasActionColumn) {
		columns.push({
			data: null,
			orderable: false,
			searchable: false,
			className: 'no-export',
			render(_value, type, record) {
				if (type !== 'display') return '';

				const edit = canEditMasterData
					? `<button class="table-icon-button" type="button" data-master-edit data-master-id="${escapeMasterHtml(record.id)}" aria-label="Ubah data" title="Ubah data"><span data-lucide="pencil" aria-hidden="true"></span></button>`
					: '';
				const evaluations = canManageCourseEvaluations
					? `<button class="table-icon-button" type="button" data-master-evaluations data-master-id="${escapeMasterHtml(record.id)}" data-master-name="${escapeMasterHtml(record.values.nama_mata_kuliah || '')}" aria-label="Komponen evaluasi" title="Komponen evaluasi"><span data-lucide="list-checks" aria-hidden="true"></span></button>`
					: '';
				const name = record.values.nama_dosen || record.values.nama || record.values.nama_mahasiswa || 'pengguna';
				const credential = record.values.nidn || record.values.kdasisten || record.values.nim || '';
				const reset = canResetPassword && credential.trim() !== ''
					? `<button class="table-icon-button reset" type="button" data-master-reset data-master-id="${escapeMasterHtml(record.id)}" data-master-name="${escapeMasterHtml(name)}" data-master-credential="${escapeMasterHtml(credential)}" aria-label="Reset password" title="Reset password"><span data-lucide="key-round" aria-hidden="true"></span></button>`
					: '';

				return `<div class="row-actions">${edit}${evaluations}${reset}</div>`;
			},
		});
	}

	const masterTable = new DataTable(masterDataTable, {
		serverSide: true,
		processing: true,
		deferRender: true,
		ajax: masterDataTable.dataset.ajaxUrl,
		columns,
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: masterActionRoot?.dataset.title === 'Bobot Nilai' ? [[0, 'asc']] : [],
		createdRow(row, record) {
			row.dataset.masterRow = record.id;
		},
		drawCallback() {
			renderIcons(this.api().table().container());
		},
		layout: {
			topStart: {
				buttons: [
					{
						extend: 'excelHtml5',
						text: '<span data-lucide="file-spreadsheet" aria-hidden="true"></span><span>Excel</span>',
						className: 'dt-export-button',
						titleAttr: 'Unduh data sebagai Excel',
						filename: 'data-master-simak',
						exportOptions: { columns: ':not(.no-export)', modifier: { page: 'all', search: 'applied' } },
					},
					{
						extend: 'csvHtml5',
						text: '<span data-lucide="file-down" aria-hidden="true"></span><span>CSV</span>',
						className: 'dt-export-button',
						titleAttr: 'Unduh data sebagai CSV',
						filename: 'data-master-simak',
						exportOptions: { columns: ':not(.no-export)', modifier: { page: 'all', search: 'applied' } },
					},
				],
			},
			topEnd: 'search',
			bottomStart: 'info',
			bottomEnd: 'paging',
		},
		language: {
			processing: 'Memuat data…',
			emptyTable: 'Belum ada data',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
			infoEmpty: 'Tidak ada data',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Cari data',
			zeroRecords: 'Tidak ada data yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
	});
	renderIcons(masterTable.buttons().container()[0]);

	window.simakMasterTable = {
		save() {
			masterTable.ajax.reload(null, false);
		},
	};

}

if (masterActionRoot) {
	import('./master-data-actions.jsx')
		.then(({ mountMasterDataActions }) => mountMasterDataActions(masterActionRoot))
		.catch((error) => {
			console.error('Gagal memuat interaksi data master.', error);
			masterActionRoot.setAttribute('role', 'alert');
			masterActionRoot.textContent = 'Fitur interaktif data master gagal dimuat. Muat ulang halaman atau hubungi administrator.';
		});
}

if (document.getElementById('dashboard-visualization-data')) {
	import('./dashboard.js')
		.catch((error) => {
			console.error('Gagal memuat visualisasi dashboard.', error);
			const chart = document.getElementById('student-intake-chart');
			const map = document.getElementById('student-distribution-map');
			[chart, map].filter(Boolean).forEach((element) => {
				element.setAttribute('role', 'alert');
				element.textContent = 'Visualisasi tidak dapat dimuat. Muat ulang halaman atau hubungi administrator.';
			});
		});
}

const krsOfferingsTable = document.querySelector('[data-datatable="krs-offerings"]');
if (krsOfferingsTable) {
	const offeringsTable = new DataTable(krsOfferingsTable, {
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [],
		layout: {
			topStart: 'pageLength',
			topEnd: 'search',
			bottomStart: 'info',
			bottomEnd: 'paging',
		},
		language: {
			emptyTable: 'Belum ada jadwal mata kuliah',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ jadwal',
			infoEmpty: 'Tidak ada jadwal mata kuliah',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Kode atau nama mata kuliah, kelas',
			zeroRecords: 'Tidak ada jadwal yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
	});

	const creditTotal = document.querySelector('[data-krs-credit-total]');
	const creditLimit = Number(document.querySelector('[data-krs-credit-limit]')?.textContent || 0);
	const creditWarning = document.querySelector('[data-krs-credit-warning]');
	const programButton = document.querySelector('[data-krs-program-submit]');
	const updateCreditTotal = () => {
		const allCheckboxes = offeringsTable.rows({ page: 'all', search: 'none' }).nodes().toArray()
			.flatMap((row) => [...row.querySelectorAll('[data-krs-schedule-checkbox]')]);
		const courseSelections = new Map();
		allCheckboxes.forEach((checkbox) => {
			checkbox.closest('tr')?.classList.toggle('krs-selected-row', checkbox.checked);
			const courseId = checkbox.dataset.courseId;
			if (!courseSelections.has(courseId)) courseSelections.set(courseId, []);
			courseSelections.get(courseId).push(checkbox);
		});
		courseSelections.forEach((checkboxes) => {
			const checkedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
			checkboxes.forEach((checkbox) => {
				checkbox.disabled = (checkbox.dataset.capacityFull === 'true' && !checkbox.checked)
					|| (checkedCount > 0 && !checkbox.checked);
			});
		});

		const selected = new Map();
		allCheckboxes.filter((checkbox) => checkbox.checked).forEach((checkbox) => {
			selected.set(checkbox.value, Number(checkbox.dataset.sks || 0));
		});
		const total = [...selected.values()].reduce((sum, credits) => sum + credits, 0);
		if (creditTotal) creditTotal.textContent = total.toFixed(2);
		const exceedsLimit = total > creditLimit;
		if (creditWarning) {
			creditWarning.textContent = exceedsLimit
				? `Jumlah pilihan ${total.toFixed(2)} SKS melebihi batas maksimum ${creditLimit} SKS. Hapus pilihan mata kuliah agar dapat menyimpan KRS.`
				: '';
			creditWarning.hidden = !exceedsLimit;
		}
		if (programButton) programButton.disabled = exceedsLimit || selected.size === 0;
	};
	krsOfferingsTable.addEventListener('change', (event) => {
		if (event.target.matches('[data-krs-schedule-checkbox]')) updateCreditTotal();
	});
	offeringsTable.on('draw', updateCreditTotal);
	updateCreditTotal();

	const programForm = document.getElementById('krs-program-form');
	programForm?.addEventListener('submit', () => {
		programForm.querySelectorAll('[data-krs-selected-hidden]').forEach((input) => input.remove());
		offeringsTable.rows({ page: 'all', search: 'none' }).nodes().toArray().forEach((row) => {
			row.querySelectorAll('[data-krs-schedule-checkbox]:checked').forEach((checkbox) => {
				const input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'schedule_ids[]';
				input.value = checkbox.value;
				input.dataset.krsSelectedHidden = 'true';
				programForm.append(input);
			});
		});
	});
}

const krsStudentsTable = document.querySelector('[data-datatable="krs-students"]');
if (krsStudentsTable) {
	new DataTable(krsStudentsTable, {
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [],
		layout: {
			topStart: 'pageLength',
			topEnd: 'search',
			bottomStart: 'info',
			bottomEnd: 'paging',
		},
		language: {
			emptyTable: 'Belum ada mahasiswa PA',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ mahasiswa',
			infoEmpty: 'Tidak ada mahasiswa PA',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Nama atau NPM mahasiswa',
			zeroRecords: 'Tidak ada mahasiswa yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
	});
}

const monitoringKrsTable = document.querySelector('[data-datatable="monitoring-krs"]');
const monitoringProgramSelect = document.getElementById('monitoring-program');
const monitoringCalendarSelect = document.getElementById('monitoring-calendar');

monitoringProgramSelect?.addEventListener('change', () => {
	monitoringCalendarSelect.value = '';
});

if (monitoringKrsTable) {
	const escapeMonitoringHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);

	new DataTable(monitoringKrsTable, {
		serverSide: true,
		processing: true,
		deferRender: true,
		ajax: monitoringKrsTable.dataset.ajaxUrl,
		columns: [
			{ data: 'nim', defaultContent: '-', className: 'dt-body-left dt-head-left', render: (value, type) => type === 'display' ? escapeMonitoringHtml(value || '-') : value },
			{ data: 'nama_mahasiswa', defaultContent: '-', className: 'dt-body-left dt-head-left', render: (value, type) => type === 'display' ? escapeMonitoringHtml(value || '-') : value },
			{ data: 'nama_program_studi', defaultContent: '-', className: 'dt-body-left dt-head-left', render: (value, type) => type === 'display' ? escapeMonitoringHtml(value || '-') : value },
			{ data: 'nama_status_mahasiswa', defaultContent: '-', className: 'dt-body-center dt-head-center', render: (value, type) => type === 'display' ? escapeMonitoringHtml(value || '-') : value },
			{
				data: 'krs_status',
				defaultContent: '-',
				className: 'dt-body-left dt-head-left',
				render(value, type, record) {
					return type === 'display'
						? `<span class="monitoring-krs-status ${escapeMonitoringHtml(record.status_class)}">${escapeMonitoringHtml(value || '-')}</span>`
						: value;
				},
			},
			{ data: 'sks_diprogram', defaultContent: '0', orderable: false, className: 'dt-body-center dt-head-center' },
			{
				data: null,
				orderable: false,
				searchable: false,
				className: 'no-export dt-body-center dt-head-center',
				render(_value, type, record) {
					if (type !== 'display') return '';
					if (record.print_url) {
						return `<a class="table-icon-button" href="${escapeMonitoringHtml(record.print_url)}" target="_blank" rel="noopener" aria-label="Cetak KRS" title="Cetak KRS"><span data-lucide="printer" aria-hidden="true"></span></a>`;
					}
					return '<span class="subtitle"><span data-lucide="eye" aria-hidden="true"></span> View Only</span>';
				},
			},
		],
		pageLength: 25,
		lengthMenu: [10, 25, 50, 100],
		order: [],
		columnDefs: [{ targets: '.no-export', orderable: false, searchable: false }],
		layout: {
			topStart: 'pageLength',
			topEnd: 'search',
			bottomStart: 'info',
			bottomEnd: 'paging',
		},
		language: {
			emptyTable: 'Belum ada mahasiswa pada prodi ini',
			info: 'Menampilkan _START_–_END_ dari _TOTAL_ mahasiswa',
			infoEmpty: 'Tidak ada mahasiswa',
			lengthMenu: '_MENU_ per halaman',
			search: 'Cari:',
			searchPlaceholder: 'Nama atau NPM mahasiswa',
			processing: 'Memuat data mahasiswa...',
			zeroRecords: 'Tidak ada mahasiswa yang cocok',
			paginate: { next: 'Selanjutnya', previous: 'Sebelumnya' },
		},
		drawCallback() {
			renderIcons(this.api().table().container());
		},
	});
}

const transferCourseInputs = [...document.querySelectorAll('[data-transfer-credit]')];
if (transferCourseInputs.length > 0) {
	const creditTotal = document.getElementById('transfer-credit-total');
	const maximumCredits = Number(creditTotal?.dataset.maximumCredits || 0);
	const updateTransferCredits = () => {
		let credits = 0;
		transferCourseInputs.forEach((input) => {
			if (input.checked) credits += Number(input.dataset.transferCredit || 0);
			input.closest('[data-transfer-course-row]')?.classList.toggle('transfer-selected-row', input.checked);
		});
		if (creditTotal) {
			creditTotal.textContent = String(credits);
			creditTotal.classList.toggle('transfer-credit-warning', credits > maximumCredits);
		}
	};
	transferCourseInputs.forEach((input) => input.addEventListener('change', updateTransferCredits));
	updateTransferCredits();
}

const krsPackageModal = document.querySelector('[data-krs-package-modal]');
if (krsPackageModal) {
	const packageDialog = krsPackageModal.querySelector('[role="dialog"]');
	const closePackageModal = () => {
		krsPackageModal.hidden = true;
	};
	document.querySelector('[data-krs-package-open]')?.addEventListener('click', () => {
		krsPackageModal.hidden = false;
		packageDialog?.focus();
	});
	krsPackageModal.querySelectorAll('[data-krs-package-close]').forEach((button) => {
		button.addEventListener('click', closePackageModal);
	});
	krsPackageModal.addEventListener('mousedown', (event) => {
		if (event.target === krsPackageModal) closePackageModal();
	});
}

const krsConfirmModal = document.querySelector('[data-krs-confirm-modal]');
if (krsConfirmModal) {
	const dialog = krsConfirmModal.querySelector('[role="dialog"]');
	const title = krsConfirmModal.querySelector('[data-krs-confirm-title]');
	const message = krsConfirmModal.querySelector('[data-krs-confirm-message]');
	let targetForm = null;
	const closeModal = () => {
		krsConfirmModal.hidden = true;
		targetForm = null;
	};

	document.addEventListener('click', (event) => {
		const button = event.target instanceof Element
			? event.target.closest('[data-krs-confirm-open]')
			: null;
		if (!button) return;

		event.preventDefault();
		targetForm = document.getElementById(button.dataset.krsForm);
		if (!targetForm) {
			console.error(`Form KRS tidak ditemukan: ${button.dataset.krsForm || '(kosong)'}`);
			return;
		}
		title.textContent = button.dataset.krsTitle || 'Konfirmasi KRS';
		message.textContent = button.dataset.krsMessage || 'Lanjutkan proses ini?';
		krsConfirmModal.hidden = false;
		dialog?.focus();
	});
	krsConfirmModal.querySelectorAll('[data-krs-confirm-close], [data-krs-confirm-cancel]').forEach((button) => {
		button.addEventListener('click', closeModal);
	});
	krsConfirmModal.addEventListener('mousedown', (event) => {
		if (event.target === krsConfirmModal) closeModal();
	});
	krsConfirmModal.querySelector('[data-krs-confirm-submit]')?.addEventListener('click', () => {
		if (!targetForm) return;
		const form = targetForm;
		closeModal();
		form.requestSubmit();
	});
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && !krsConfirmModal.hidden) closeModal();
	});
}
