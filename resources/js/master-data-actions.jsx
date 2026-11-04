import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import Swal from 'sweetalert2';
import TomSelect from 'tom-select';

function showNotification(message, variant = 'success') {
	document.dispatchEvent(new CustomEvent('simak:notification', { detail: { message, variant } }));
}

function showFeederError(message) {
	Swal.fire({
		icon: 'error',
		title: 'Sinkronisasi Feeder gagal',
		text: message,
		confirmButtonText: 'Tutup',
	});
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
		...(method === 'GET' ? {} : { body: JSON.stringify(payload) }),
	});
	const responseText = await response.text();
	let result;

	try {
		result = JSON.parse(responseText);
	} catch {
		throw new Error('Server mengembalikan respons yang tidak dapat dibaca.');
	}

	if (!response.ok) {
		const messages = result?.errors ? Object.values(result.errors).flat().join(' ') : result?.message;
		throw new Error(messages || `Permintaan gagal (${response.status}).`);
	}

	return result;
}

function MasterDataActions({ root }) {
	const createFields = useMemo(() => JSON.parse(root.dataset.createFields || '[]'), [root]);
	const editFields = useMemo(() => JSON.parse(root.dataset.editFields || '[]'), [root]);
	const syncFields = useMemo(() => JSON.parse(root.dataset.syncFields || '[]'), [root]);
	const [dialog, setDialog] = useState(null);
	const [values, setValues] = useState({});
	const [busy, setBusy] = useState(false);
	const [syncing, setSyncing] = useState(false);
	const [error, setError] = useState('');
	const [evaluations, setEvaluations] = useState([]);
	const [evaluationsLoading, setEvaluationsLoading] = useState(false);
	const dialogRef = useRef(null);
	const returnFocusRef = useRef(null);

	useEffect(() => {
		const handleClick = async (event) => {
			const target = event.target instanceof Element ? event.target : null;
			const createButton = target?.closest('[data-master-create]');
			const editButton = target?.closest('[data-master-edit]');
			const resetButton = target?.closest('[data-master-reset]');
			const syncButton = target?.closest('[data-master-sync]');
			const evaluationsButton = target?.closest('[data-master-evaluations]');
			const deleteEvaluationButton = target?.closest('[data-evaluation-delete]');

			if (deleteEvaluationButton && root.dataset.canDeleteCourseEvaluations === 'true') {
				setError('');
				setDialog({
					type: 'confirm-evaluation-delete',
					id: deleteEvaluationButton.dataset.courseId,
					evaluationId: deleteEvaluationButton.dataset.evaluationId,
					name: deleteEvaluationButton.dataset.evaluationName,
				});
			} else if (evaluationsButton && root.dataset.canCourseEvaluations === 'true') {
				returnFocusRef.current = evaluationsButton;
				setEvaluations([]);
				setError('');
				setDialog({
					type: 'evaluations',
					id: evaluationsButton.dataset.masterId,
					name: evaluationsButton.dataset.masterName,
				});
			} else if (resetButton && root.dataset.canResetPassword === 'true') {
				returnFocusRef.current = resetButton;
				setValues({});
				setError('');
				setDialog({
					type: 'reset',
					id: resetButton.dataset.masterId,
					name: resetButton.dataset.masterName,
					credential: resetButton.dataset.masterCredential,
				});
			} else if (syncButton && root.dataset.canSync === 'true') {
				if (syncButton.hasAttribute('data-sync-dialog')) {
					returnFocusRef.current = syncButton;
					setValues({});
					setError('');
					setDialog({ type: 'sync' });
					return;
				}

				if (syncing) return;
				returnFocusRef.current = syncButton;
				setValues({});
				setError('');
				setDialog({ type: 'confirm-sync' });
			} else if (createButton && root.dataset.canCreate === 'true') {
				returnFocusRef.current = createButton;
				setValues({});
				setError('');
				setDialog({ type: 'create' });
			} else if (editButton && root.dataset.canEdit === 'true') {
				returnFocusRef.current = editButton;
				setError('');
				setBusy(true);
				try {
					const url = root.dataset.editUrlTemplate.replace('__RECORD__', encodeURIComponent(editButton.dataset.masterId));
					const result = await sendRequest(url, 'GET', {});
					setValues(result.record.values || {});
					setDialog({ type: 'edit', id: editButton.dataset.masterId });
				} catch (requestError) {
					showNotification(requestError.message, 'error');
				} finally {
					setBusy(false);
				}
			}
		};

		document.addEventListener('click', handleClick);
		return () => document.removeEventListener('click', handleClick);
	}, [root, syncing]);

	useEffect(() => {
		if (dialog?.type !== 'evaluations') return undefined;

		let active = true;
		setEvaluationsLoading(true);
		const url = root.dataset.evaluationsUrlTemplate.replace('__RECORD__', encodeURIComponent(dialog.id));
		fetch(url, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		})
			.then(async (response) => {
				const result = await response.json();
				if (!response.ok) throw new Error(result.message || `Gagal memuat evaluasi (${response.status}).`);
				return result;
			})
			.then((result) => {
				if (active) setEvaluations(result.data || []);
			})
			.catch((requestError) => {
				if (!active) return;
				setError(requestError.message);
				showNotification(requestError.message, 'error');
			})
			.finally(() => {
				if (active) setEvaluationsLoading(false);
			});

		return () => { active = false; };
	}, [dialog?.type, dialog?.id, root]);

	useEffect(() => {
		document.dispatchEvent(new Event('simak:render-icons'));
	}, [dialog?.type, evaluations]);

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
			}

			if (event.key === 'Tab') {
				const focusable = [...(dialogRef.current?.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled)') || [])]
					.filter((element) => element.getClientRects().length > 0);
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

	const setField = useCallback((name, value) => setValues((current) => ({ ...current, [name]: value })), []);
	const fields = dialog?.type === 'create' ? createFields : (dialog?.type === 'sync' ? syncFields : (dialog?.type === 'edit' ? editFields : []));

	useEffect(() => {
		if (!dialog) return undefined;

		const controls = [...(dialogRef.current?.querySelectorAll('[data-searchable-select]') || [])]
			.map((element) => new TomSelect(element, {
				create: false,
				maxOptions: 100,
				allowEmptyOption: element.dataset.searchableSelect === 'filter',
				plugins: element.dataset.searchableSelect === 'filter' ? ['clear_button'] : [],
				...(element.dataset.searchableSelect === 'remote' ? {
					placeholder: element.dataset.placeholder || undefined,
					preload: element.dataset.fieldOptions === 'student-regions' ? 'focus' : false,
					shouldLoad: (query) => element.dataset.fieldOptions === 'student-regions'
						? Boolean(dialogRef.current?.querySelector('#master-id_negara')?.value)
						: query.length >= 3,
					load: async (query, callback) => {
						const regionSearch = element.dataset.fieldOptions === 'student-regions';
						if ((!regionSearch && query.length < 3) || (regionSearch && !dialogRef.current?.querySelector('#master-id_negara')?.value)) {
							callback();
							return;
						}

						try {
							const originSearch = ['origin-universities', 'origin-programs'].includes(element.dataset.fieldOptions);
							const searchUrl = originSearch
								? root.dataset.originSearchUrl
								: (regionSearch ? root.dataset.regionsSearchUrl : root.dataset.lecturerSearchUrl);
							const url = new URL(searchUrl, window.location.origin);
							url.searchParams.set('search', query);
							if (root.dataset.module === 'mahasiswa') {
								url.searchParams.set('module', 'mahasiswa');
							}
							if (originSearch) {
								url.searchParams.set('type', element.dataset.fieldOptions === 'origin-universities' ? 'university' : 'program');
								if (element.dataset.fieldOptions === 'origin-programs') {
									const universityId = dialogRef.current
										?.querySelector('#master-id_perguruan_tinggi_asal')?.value || '';
									if (!universityId) {
										callback();
										return;
									}
									url.searchParams.set('id_perguruan_tinggi', universityId);
								}
							}
							if (regionSearch) {
								url.searchParams.set('id_negara', dialogRef.current
									?.querySelector('#master-id_negara')?.value || '');
							}
							const response = await fetch(url, {
								headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
							});
							const result = await response.json();
							if (!response.ok) {
								throw new Error(result.message || `Pencarian pilihan gagal (${response.status}).`);
							}
							callback(result.data);
						} catch (requestError) {
							setError(requestError.message);
							showNotification(requestError.message, 'error');
							callback();
						}
					},
				} : {}),
			}));

		return () => controls.forEach((control) => control.destroy());
	}, [dialog?.type, fields, setField]);

	const submit = async (event) => {
		event.preventDefault();
		if (!dialog) return;
		if (dialog.type === 'reset') {
			setBusy(true);
			setError('');
			try {
				const url = root.dataset.resetUrlTemplate.replace('__RECORD__', encodeURIComponent(dialog.id));
				const result = await sendRequest(url, 'POST', {});
				window.simakMasterTable?.save();
				showNotification(result.message);
				setDialog(null);
			} catch (requestError) {
				setError(requestError.message);
				showNotification(requestError.message, 'error');
			} finally {
				setBusy(false);
			}
			return;
		}

		if (dialog.type === 'confirm-evaluation-delete') {
			setBusy(true);
			setError('');
			try {
				const url = root.dataset.evaluationDeleteUrlTemplate
					.replace('__RECORD__', encodeURIComponent(dialog.id))
					.replace('__EVALUATION__', encodeURIComponent(dialog.evaluationId));
				const result = await sendRequest(url, 'DELETE', {});
				showNotification(result.message);
				setDialog({ type: 'evaluations', id: dialog.id, name: dialog.name });
			} catch (requestError) {
				setError(requestError.message);
				showNotification(requestError.message, 'error');
			} finally {
				setBusy(false);
			}
			return;
		}

		if (dialog.type === 'confirm-sync') {
			setBusy(true);
			setError('');
			try {
				const result = await sendRequest(root.dataset.syncUrl, 'POST', dialog.payload || {});
				showNotification(result.message);
				setDialog(null);
				window.setTimeout(() => window.location.reload(), 2200);
			} catch (requestError) {
				setError(requestError.message);
				showFeederError(requestError.message);
			} finally {
				setBusy(false);
			}
			return;
		}

		if (dialog.type === 'sync' && root.dataset.title !== 'Dosen' && root.dataset.module !== 'mahasiswa') {
			const payload = { ...values };
			setDialog({ type: 'confirm-sync', payload });
			setError('');
			return;
		}

		setBusy(true);
		setError('');
		try {
			if (dialog.type === 'sync') {
				const payload = { ...values };
				if (root.dataset.module === 'mahasiswa' && root.dataset.syncProgramId) {
					payload.id_prodi = root.dataset.syncProgramId;
				}
				const result = await sendRequest(root.dataset.syncUrl, 'POST', payload);
				showNotification(result.message);
				setDialog(null);
				window.setTimeout(() => window.location.reload(), 1200);
				return;
			}

			const url = dialog.type === 'create'
				? root.dataset.storeUrl
				: root.dataset.updateUrlTemplate.replace('__RECORD__', encodeURIComponent(dialog.id));
			const result = await sendRequest(url, dialog.type === 'create' ? 'POST' : 'PUT', values);
			window.simakMasterTable?.save(result.record, dialog.type === 'create');
			showNotification(result.message);
			setDialog(null);
		} catch (requestError) {
			setError(requestError.message);
			if (dialog.type === 'sync') showFeederError(requestError.message);
			else showNotification(requestError.message, 'error');
		} finally {
			setBusy(false);
		}
	};
	const dialogTitle = ({
		'confirm-sync': 'Konfirmasi sinkronisasi',
		'confirm-evaluation-delete': 'Hapus komponen evaluasi',
		evaluations: 'Komponen evaluasi',
		sync: `Sinkron ${root.dataset.title} dari Feeder`,
		reset: 'Konfirmasi reset password',
	})[dialog?.type] || `${dialog?.type === 'create' ? 'Tambah' : 'Ubah'} ${root.dataset.title}`;
	const dialogCopy = dialog?.type === 'confirm-sync'
		? (dialog.payload && Object.keys(dialog.payload).length
			? (root.dataset.title === 'Dosen'
				? `Kirim NIDN ${dialog.payload.nidn} ke Feeder untuk disinkronkan?`
				: (root.dataset.title === 'Mahasiswa'
					? `Kirim NPM ${dialog.payload.npm} ke Feeder untuk disinkronkan?`
					: `Kirim kode mata kuliah ${dialog.payload.kode_mata_kuliah} ke Feeder untuk program studi yang dipilih?`))
			: `Sinkronkan data ${root.dataset.title} dari Feeder sekarang? Data di SIAKAD yang dikelola Feeder akan diperbarui.`)
		: (dialog?.type === 'confirm-evaluation-delete'
			? `Hapus komponen evaluasi ${dialog.name || 'ini'} dari Feeder?`
			: (dialog?.type === 'evaluations'
				? `Daftar komponen evaluasi untuk ${dialog.name || 'mata kuliah ini'}.`
				: (dialog?.type === 'reset'
					? `Reset password ${dialog.name} ke nilai default ${dialog.credential}?`
					: (dialog?.type === 'sync'
						? (root.dataset.title === 'Mahasiswa'
							? 'Masukkan NPM dan program studi yang sesuai. Anda akan diminta mengonfirmasi sebelum permintaan dikirim.'
							: 'Masukkan kode mata kuliah dan program studi yang sesuai. Anda akan diminta mengonfirmasi sebelum permintaan dikirim.')
						: 'Perubahan disimpan pada data SIAKAD setelah validasi berhasil.'))));
	const submitLabel = dialog?.type === 'sync'
		? 'Lanjutkan'
		: (dialog?.type === 'confirm-sync'
			? 'Sinkronkan'
			: (dialog?.type === 'confirm-evaluation-delete'
				? 'Hapus komponen'
				: (dialog?.type === 'reset' ? 'Reset password' : 'Simpan')));

	return (
		<>
			{dialog && (
				<div className="calendar-modal-backdrop" onMouseDown={(event) => {
					if (event.target === event.currentTarget) closeDialog();
				}}>
					<section
						className="calendar-modal master-data-modal"
						role="dialog"
						aria-modal="true"
						aria-labelledby="master-data-modal-title"
						tabIndex="-1"
						ref={dialogRef}
					>
						<div className="calendar-modal-head">
							<div>
								<h2 id="master-data-modal-title">{dialogTitle}</h2>
								<p className="calendar-modal-copy">{dialogCopy}</p>
							</div>
							<button className="calendar-modal-close" type="button" aria-label="Tutup dialog" onClick={closeDialog} disabled={busy}>×</button>
						</div>
						<form onSubmit={submit}>
							{dialog.type === 'evaluations' ? (
								<div className="master-evaluations">
									{evaluationsLoading ? <p>Memuat komponen evaluasi…</p> : (
										evaluations.length ? (
											<table>
												<thead><tr><th>Komponen evaluasi</th><th>Bobot</th><th>Aksi</th></tr></thead>
												<tbody>{evaluations.map((evaluation) => (
													<tr key={evaluation.id_rencana_evaluasi}>
														<td>{evaluation.nama_evaluasi || '-'}</td>
														<td>{evaluation.bobot_evaluasi ?? '-'}</td>
														<td>
															{root.dataset.canDeleteCourseEvaluations === 'true' && (
															<button
																className="table-icon-button reset"
																type="button"
																data-evaluation-delete
																data-course-id={dialog.id}
																data-evaluation-id={evaluation.id_rencana_evaluasi}
																data-evaluation-name={evaluation.nama_evaluasi || ''}
																aria-label={`Hapus ${evaluation.nama_evaluasi || 'komponen evaluasi'}`}
																title="Hapus komponen evaluasi"
															><span data-lucide="trash-2" aria-hidden="true"></span></button>
															)}
														</td>
													</tr>
												))}</tbody>
											</table>
										) : <p>Belum ada komponen evaluasi.</p>
									)}
								</div>
							) : (['reset', 'confirm-sync', 'confirm-evaluation-delete'].includes(dialog.type) ? (
								<div className="calendar-confirm-detail">
									{dialog.type === 'reset'
										? 'Kata sandi akan dikembalikan ke nomor/kode akun yang terdaftar di SIAKAD.'
										: (dialog.type === 'confirm-evaluation-delete'
											? 'Komponen evaluasi akan dihapus dari Feeder.'
											: 'Pastikan data yang akan diselaraskan sudah benar sebelum melanjutkan.')}
								</div>
							) : <div className="field-grid master-data-field-grid">
								{fields.map((field) => (
									<div key={field.name}>
										{field.type === 'checkboxes' ? (
											<>
												<span className="master-checkbox-heading" id={`master-${field.name}-label`}>
													{field.label}{field.required ? ' *' : ''}
												</span>
												<div className="master-checkbox-list" role="group" aria-labelledby={`master-${field.name}-label`}>
													{Object.entries(field.options || {}).length > 0 ? Object.entries(field.options).map(([value, label]) => (
														<label className="master-checkbox-option" key={value} htmlFor={`master-${field.name}-${value}`}>
															<input
																id={`master-${field.name}-${value}`}
																type="checkbox"
																value={value}
																checked={Array.isArray(values[field.name]) && values[field.name].includes(value)}
																onChange={(event) => {
																	const selected = Array.isArray(values[field.name]) ? values[field.name] : [];
																	setField(field.name, event.target.checked
																		? [...selected, value]
																		: selected.filter((item) => item !== value));
																}}
															/>
															<span>{label}</span>
														</label>
													)) : <span className="master-checkbox-empty">Referensi kebutuhan khusus belum tersedia.</span>}
												</div>
												<small className="master-checkbox-help">Kosongkan pilihan jika tidak ada kebutuhan khusus.</small>
											</>
										) : (
											<>
										<label htmlFor={`master-${field.name}`}>{field.label}{field.required ? ' *' : ''}</label>
										{field.type === 'select' ? (
											<select
												id={`master-${field.name}`}
												key={field.name}
												disabled={field.disabled === true || (field.options === 'student-regions' && !values.id_negara)}
												data-searchable-select={field.name === 'id_negara'
													? undefined
													: (['lecturers', 'origin-universities', 'origin-programs', 'student-regions'].includes(field.options) ? 'remote' : '')}
												data-field-options={typeof field.options === 'string' ? field.options : ''}
												data-placeholder={field.options === 'student-regions'
													? (values.id_negara ? 'Pilih atau cari wilayah' : 'Pilih negara terlebih dahulu')
													: (['lecturers', 'origin-universities', 'origin-programs'].includes(field.options)
														? 'Cari / ketik 3 karakter'
														: (field.name === 'status' && root.dataset.title === 'Fakultas' ? 'Pilih Status Fakultas' : undefined))}
												value={values[field.name] ?? ''}
												onChange={(event) => {
													setField(field.name, event.target.value);
													if (field.name === 'id_perguruan_tinggi_asal') setField('id_prodi_asal', '');
												if (field.name === 'id_negara') {
													setField('id_wilayah', '');
													const regionControl = dialogRef.current?.querySelector('#master-id_wilayah')?.tomselect;
													regionControl?.clear(true);
													regionControl?.clearOptions();
												}
												}}
												required={field.required === true}
											>
												<option value="">
													{field.options === 'student-regions'
														? (values.id_negara ? 'Pilih atau cari wilayah' : 'Pilih negara terlebih dahulu')
														: (['lecturers', 'origin-universities', 'origin-programs'].includes(field.options)
														? (field.options === 'origin-programs' && !values.id_perguruan_tinggi_asal
															? 'Pilih perguruan tinggi asal terlebih dahulu'
															: 'Cari / ketik 3 karakter')
														: (field.name === 'status' && root.dataset.title === 'Fakultas'
															? 'Pilih Status Fakultas'
															: `Pilih ${field.label.toLowerCase()}`))}
												</option>
												{['lecturers', 'origin-universities', 'origin-programs', 'student-regions'].includes(field.options) && values[field.name] ? (
													<option value={values[field.name]}>
														{values[`${field.name}_label`] || `${field.label} terpilih`}
													</option>
												) : null}
												{Object.entries(typeof field.options === 'object' ? field.options : {}).map(([value, label]) => (
													<option key={value} value={value}>{label}</option>
												))}
											</select>
										) : (
											<input
												id={`master-${field.name}`}
												type={['decimal', 'currency'].includes(field.type) ? 'number' : (field.type || 'text')}
												value={values[field.name] ?? ''}
												onChange={(event) => setField(field.name, event.target.value)}
												required={field.required === true}
												minLength={field.min}
												maxLength={field.max}
												max={field.type === 'currency' ? 999999999 : (field.type === 'number' ? 9999 : undefined)}
												step={field.type === 'decimal' ? '0.01' : (field.type === 'currency' ? '1' : undefined)}
												autoComplete={field.type === 'password' ? 'new-password' : 'off'}
											/>
										)}
											</>
										)}
									</div>
								))}
							</div>)}
							{error && <p className="calendar-modal-error" role="alert">{error}</p>}
							<div className="calendar-modal-actions">
								<button className="button" type="button" onClick={closeDialog} disabled={busy}>Batal</button>
								{dialog.type !== 'evaluations' && <button className="button button-primary" type="submit" disabled={busy}>
									{busy
										? (['sync', 'confirm-sync', 'confirm-evaluation-delete'].includes(dialog.type) ? 'Menghubungkan Feeder…' : (dialog.type === 'reset' ? 'Mereset password…' : 'Menyimpan…'))
										: submitLabel}
								</button>}
							</div>
						</form>
					</section>
				</div>
			)}
		</>
	);
}

export function mountMasterDataActions(root) {
	createRoot(root).render(<MasterDataActions root={root} />);
}
