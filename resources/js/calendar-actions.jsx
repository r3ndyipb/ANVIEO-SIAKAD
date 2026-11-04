import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

function showNotification(message, variant = 'success') {
	document.dispatchEvent(new CustomEvent('simak:notification', { detail: { message, variant } }));
}

const dateFields = [
	['tgl_mulai_krs', 'Mulai input KRS'],
	['tgl_akhir_krs', 'Selesai input KRS'],
	['tgl_mulai_isinilai', 'Mulai input nilai'],
	['tgl_akhir_isinilai', 'Selesai input nilai'],
];

function blankDates() {
	return Object.fromEntries(dateFields.map(([field]) => [field, '']));
}

function datasetField(field) {
	return field.replace(/_([a-z])/g, (_, letter) => letter.toUpperCase());
}

function errorMessage(payload, response) {
	if (payload?.errors) {
		return Object.values(payload.errors).flat().join(' ');
	}

	return payload?.message || `Permintaan gagal (${response.status}). Silakan coba lagi.`;
}

async function sendRequest(url, method, payload) {
	const response = await fetch(url, {
		method,
		headers: {
			Accept: 'application/json',
			'Content-Type': 'application/json',
			'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
			'X-Requested-With': 'XMLHttpRequest',
		},
		body: JSON.stringify(payload),
	});
	const responseText = await response.text();
	let result;

	try {
		result = JSON.parse(responseText);
	} catch {
		throw new Error('Server mengembalikan respons yang tidak dapat dibaca. Perubahan belum dikonfirmasi.');
	}

	if (!response.ok) {
		throw new Error(errorMessage(result, response));
	}

	return result;
}

function CalendarActions({ root, periodOptions }) {
	const [dialog, setDialog] = useState(null);
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState('');
	const [progress, setProgress] = useState(null);
	const [period, setPeriod] = useState('');
	const [dates, setDates] = useState(blankDates);
	const dialogRef = useRef(null);
	const returnFocusRef = useRef(null);
	const canCreate = root.dataset.canCreate === 'true';
	const canEdit = root.dataset.canEdit === 'true';
	const calendarLabel = root.dataset.calendarLabel || 'Kalender Akademik';
	const shortSemester = calendarLabel === 'Kurikulum SP';

	useEffect(() => {
		const handleClick = (event) => {
			const target = event.target instanceof Element ? event.target : null;
			const createButton = target?.closest('[data-calendar-create]');
			const editButton = target?.closest('[data-calendar-edit]');
			const toggleButton = target?.closest('[data-calendar-toggle]');

			if (createButton && canCreate) {
				returnFocusRef.current = createButton;
				setPeriod('');
				setDates(blankDates());
				setError('');
				setProgress(null);
				setDialog({ type: 'create' });
				return;
			}

			if (editButton && canEdit) {
				returnFocusRef.current = editButton;
				setDates(Object.fromEntries(dateFields.map(([field]) => [field, editButton.dataset[datasetField(field)] || ''])));
				setError('');
				setDialog({
					type: 'edit',
					id: editButton.dataset.calendarId,
					name: editButton.dataset.calendarName,
				});
				return;
			}

			if (toggleButton) {
				const active = toggleButton.dataset.calendarActive === 'true';
				returnFocusRef.current = toggleButton;
				setError('');
				setDialog({
					type: 'status',
					id: toggleButton.dataset.calendarId,
					name: toggleButton.closest('tr')?.querySelector('.period')?.textContent?.trim() || 'Kalender Akademik',
					active: !active,
				});
			}
		};

		document.addEventListener('click', handleClick);
		return () => document.removeEventListener('click', handleClick);
	}, [canCreate, canEdit]);

	useEffect(() => {
		if (!dialog) {
			returnFocusRef.current?.focus();
			return;
		}

		dialogRef.current?.focus();
		const handleKeyDown = (event) => {
			if (event.key === 'Escape' && !busy) {
				setDialog(null);
				setError('');
				return;
			}

			if (event.key === 'Tab') {
				const focusable = [...(dialogRef.current?.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled)') || [])];
				const first = focusable[0];
				const last = focusable[focusable.length - 1];

				if (event.shiftKey && (document.activeElement === first || document.activeElement === dialogRef.current)) {
					event.preventDefault();
					last?.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first?.focus();
				}
			}
		};

		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [dialog, busy]);

	const closeDialog = () => {
		if (!busy) {
			setDialog(null);
			setError('');
		}
	};

	const updateDate = (field, value) => {
		setDates((current) => ({ ...current, [field]: value }));
	};

	const submitForm = async (event) => {
		event.preventDefault();
		if (!dialog || !['create', 'edit'].includes(dialog.type)) {
			return;
		}

		setBusy(true);
		setError('');
		setProgress(null);
		if (shortSemester && dialog.type === 'create') {
			setProgress({ processed: 0, total: 0, complete: false });
		}

		try {
			if (dialog.type === 'create') {
				if (shortSemester) {
					const totals = {
						curricula_created: 0,
						curricula_updated: 0,
						calendars_created: 0,
						calendars_updated: 0,
					};
					let offset = 0;
					let result;

					do {
						result = await sendRequest(root.dataset.createUrl, 'POST', {
							periode: period,
							...dates,
							program_offset: offset,
						});
						window.simakCalendarTable?.addCalendars(result.calendars);
						Object.keys(totals).forEach((key) => {
							totals[key] += result.counts?.[key] || 0;
						});

						if (!result.progress || typeof result.progress.processed !== 'number'
							|| typeof result.progress.total !== 'number'
							|| typeof result.progress.complete !== 'boolean') {
							throw new Error('Server tidak mengembalikan progres pemrosesan periode SP.');
						}

						setProgress(result.progress);
						if (!result.progress.complete && result.progress.processed <= offset) {
							throw new Error('Progres pemrosesan periode SP terhenti. Silakan coba lagi.');
						}
						offset = result.progress.processed;
					} while (!result.progress.complete);

					showNotification(
						`Periode SP selesai diproses: kurikulum ${totals.curricula_created} baru/${totals.curricula_updated} diperbarui; kalender SP ${totals.calendars_created} baru/${totals.calendars_updated} diperbarui.`,
					);
				} else {
					const result = await sendRequest(root.dataset.createUrl, 'POST', { periode: period, ...dates });
					window.simakCalendarTable?.addCalendars(result.calendars);
					showNotification(result.message);
				}
			} else {
				const url = root.dataset.updateUrlTemplate.replace('__CALENDAR__', encodeURIComponent(dialog.id));
				const result = await sendRequest(url, 'PUT', dates);
				window.simakCalendarTable?.updateCalendar(result.calendar);
				showNotification(result.message);
			}

			setDialog(null);
		} catch (requestError) {
			setError(requestError.message);
			showNotification(requestError.message, 'error');
		} finally {
			setBusy(false);
		}
	};

	const confirmStatus = async () => {
		if (!dialog || dialog.type !== 'status') {
			return;
		}

		setBusy(true);
		setError('');

		try {
			const url = root.dataset.statusUrlTemplate.replace('__CALENDAR__', encodeURIComponent(dialog.id));
			const result = await sendRequest(url, 'POST', { active: dialog.active });
			window.simakCalendarTable?.updateStatuses(result.statuses);
			showNotification(result.message);
			setDialog(null);
		} catch (requestError) {
			setError(requestError.message);
			showNotification(requestError.message, 'error');
		} finally {
			setBusy(false);
		}
	};

	return (
		<>
			{dialog && (
				<div
					className="calendar-modal-backdrop"
					onMouseDown={(event) => {
						if (event.target === event.currentTarget) closeDialog();
					}}
				>
					<section
						className="calendar-modal"
						role="dialog"
						aria-modal="true"
						aria-labelledby="calendar-modal-title"
						tabIndex="-1"
						ref={dialogRef}
					>
						<div className="calendar-modal-head">
							<div>
								<h2 id="calendar-modal-title">
									{dialog.type === 'create' && `Buat ${calendarLabel}`}
									{dialog.type === 'edit' && `Ubah Tanggal ${calendarLabel}`}
									{dialog.type === 'status' && (dialog.active ? `Aktifkan ${calendarLabel}` : `Nonaktifkan ${calendarLabel}`)}
								</h2>
								<p className="calendar-modal-copy">
									{dialog.type === 'create' && `${shortSemester ? 'Periode SP' : 'Kalender'} dibuat untuk semua program studi aktif yang belum memiliki periode tersebut.`}
									{dialog.type === 'edit' && `Periode: ${dialog.name}. Tahun akademik dan program studi tidak dapat diubah.`}
									{dialog.type === 'status' && (dialog.active
										? `${shortSemester ? 'Periode SP' : 'Kalender'} aktif lain pada program studi yang sama akan dinonaktifkan.`
										: `Semua ${shortSemester ? 'periode SP' : 'kalender'} pada program studi ini akan menjadi nonaktif.`)}
								</p>
							</div>
							<button className="calendar-modal-close" type="button" aria-label="Tutup dialog" onClick={closeDialog} disabled={busy}>×</button>
						</div>

						{dialog.type === 'status' ? (
							<>
								<div className="calendar-confirm-detail"><strong>{dialog.name}</strong><br />Perubahan status akan disimpan langsung setelah dikonfirmasi.</div>
								{error && <p className="calendar-modal-error" role="alert">{error}</p>}
								<div className="calendar-modal-actions">
									<button className="button" type="button" onClick={closeDialog} disabled={busy}>Batal</button>
									<button
										className={`button ${dialog.active ? 'button-primary' : 'button-danger'}`}
										type="button"
										onClick={confirmStatus}
										disabled={busy}
									>{busy ? 'Menyimpan…' : 'Ya, konfirmasi'}</button>
								</div>
							</>
						) : (
							<form onSubmit={submitForm}>
								{dialog.type === 'create' && (
									<div className="form-section">
										<label htmlFor="calendar-period">{shortSemester ? 'Periode Semester Pendek' : 'Periode akademik'}</label>
										<select id="calendar-period" value={period} onChange={(event) => setPeriod(event.target.value)} required>
											<option value="">Pilih periode</option>
											{Object.entries(periodOptions).map(([value, option]) => (
												<option value={value} key={value}>{option.label}</option>
											))}
										</select>
									</div>
								)}
								<div className="field-grid">
									{dateFields.map(([field, label]) => (
										<div key={field}>
											<label htmlFor={`calendar-${field}`}>{label}</label>
											<input
												id={`calendar-${field}`}
												type="date"
												value={dates[field]}
												onChange={(event) => updateDate(field, event.target.value)}
												required
											/>
										</div>
									))}
								</div>
								{shortSemester && dialog.type === 'create' && (busy || progress) && (() => {
									const total = progress?.total || 0;
									const processed = progress?.processed || 0;
									const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
									const active = busy && !progress?.complete;

									return (
										<div className={`calendar-process-progress${active ? ' is-active' : ''}`} role="status" aria-live="polite">
											<div className="calendar-process-progress-head">
												<span><i className="calendar-process-spinner" aria-hidden="true" />
													{total > 0
														? progress.complete
															? `Selesai memproses ${processed} dari ${total} program studi.`
															: busy
																? `Memproses program studi ${Math.min(processed + 1, total)} dari ${total}…`
																: `Proses terhenti setelah ${processed} dari ${total} program studi.`
														: 'Menyiapkan proses sinkronisasi...'}
												</span>
												<strong>{percent}%</strong>
											</div>
											<div className="calendar-process-track" role="progressbar" aria-label="Progres pembuatan periode SP"
												aria-valuemin="0" aria-valuemax="100" aria-valuenow={percent}>
												<div className="calendar-process-bar" style={{ width: `${percent}%` }} />
											</div>
											<p>{processed} dari {total} program studi selesai diproses.</p>
										</div>
									);
								})()}
								{error && <p className="calendar-modal-error" role="alert">{error}</p>}
								<div className="calendar-modal-actions">
									<button className="button" type="button" onClick={closeDialog} disabled={busy}>Batal</button>
									<button className="button button-primary" type="submit" disabled={busy}>
										{busy
											? (shortSemester && progress
												? `Memproses ${progress.processed}/${progress.total}…`
												: 'Menyimpan…')
											: dialog.type === 'create' ? (shortSemester ? 'Buat periode SP' : 'Buat kalender') : 'Simpan tanggal'}
									</button>
								</div>
							</form>
						)}
					</section>
				</div>
			)}
		</>
	);
}

export function mountCalendarActions(root) {
	const periodOptions = JSON.parse(root.dataset.periodOptions || '{}');
	createRoot(root).render(<CalendarActions root={root} periodOptions={periodOptions} />);
}
