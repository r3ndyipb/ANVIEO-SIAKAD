import React, { useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { DataGrid, renderTextEditor } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';

const components = [
	['nilai_partisipatif', 'Partisipatif'],
	['nilai_proyek', 'Proyek'],
	['nilai_quiz', 'Quiz'],
	['nilai_tugas', 'Tugas'],
	['nilai_uts', 'UTS'],
	['nilai_uas', 'UAS'],
];
const componentFields = components.map(([field]) => field);

function scoreValue(value) {
	const parsed = Number(String(value ?? '').trim().replace(',', '.'));
	return Number.isFinite(parsed) ? parsed : 0;
}

function rowTotal(row, enabledFields, componentWeights) {
	return enabledFields.reduce((total, field) => total + scoreValue(row[field]) * (Number(componentWeights[field]) || 0) / 100, 0);
}

function gradeLetter(total, ranges) {
	const rounded = Math.round(total);
	let letter = '-';
	ranges.forEach((range) => {
		if (rounded >= Number(range.bobot_minimum) && rounded <= Number(range.bobot_maksimum)) {
			letter = range.nilai_huruf;
		}
	});
	return letter;
}

function GradeSpreadsheet({ root, initialRows, gradeRanges, enabledFields, componentWeights }) {
	const [rows, setRows] = useState(initialRows);
	const [error, setError] = useState('');
	const [activeCell, setActiveCell] = useState(null);
	const [saveConfirmOpen, setSaveConfirmOpen] = useState(false);
	const [saveProgress, setSaveProgress] = useState(null);
	const gridRef = useRef(null);
	const formRef = useRef(null);
	const unlockFormRef = useRef(null);
	const allowSubmitRef = useRef(false);
	const saveInProgressRef = useRef(false);
	const canEdit = root.dataset.canEdit === 'true';
	const unlockOnly = root.dataset.unlockOnly === 'true';
	const sequentialSave = root.dataset.sequentialSave === 'true';
	const requireSaveConfirmation = root.dataset.confirmSave === 'true';
	const confirmOnce = root.dataset.confirmOnce === 'true';
	const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

	const columns = useMemo(() => [
		{
			key: 'number', name: 'No', width: 58, frozen: 'start',
			renderCell: ({ rowIdx }) => rowIdx + 1,
		},
		{ key: 'nim', name: 'NPM', width: 118, frozen: 'start' },
		{ key: 'nama_mahasiswa', name: 'Nama Mahasiswa', width: 220, frozen: 'start' },
		...components.map(([field, label]) => ({
			key: field,
			name: `${label} (${Number(componentWeights[field] || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })}%)`,
			width: 116,
			editable: canEdit && enabledFields.includes(field),
			renderEditCell: renderTextEditor,
			cellClass: canEdit && enabledFields.includes(field) ? 'grade-grid-editable' : 'grade-grid-disabled',
		})),
		{
			key: 'nilai_angka', name: 'Nilai Angka', width: 108,
			renderCell: ({ row }) => <strong className={canEdit && rowTotal(row, enabledFields, componentWeights) > 100 ? 'grade-grid-over' : ''}>
				{canEdit ? rowTotal(row, enabledFields, componentWeights).toFixed(2) : row.nilai_angka || '0.00'}
			</strong>,
		},
		{
			key: 'nilai_huruf', name: 'Nilai Huruf', width: 92,
			renderCell: ({ row }) => <strong>{canEdit ? gradeLetter(rowTotal(row, enabledFields, componentWeights), gradeRanges) : row.nilai_huruf || '-'}</strong>,
		},
	], [canEdit, componentWeights, enabledFields, gradeRanges]);

	const handleRowsChange = (nextRows) => {
		setRows(nextRows);
		setError('');
	};
	const saveSpGradesSequentially = async () => {
		if (saveInProgressRef.current) return;
		saveInProgressRef.current = true;
		const total = rows.length;
		const failures = [];
		let processed = 0;
		setSaveProgress({ phase: 'saving', processed, total, failures: [] });
		for (const row of rows) {
			const url = root.dataset.updateUrl.replace('__KRS__', encodeURIComponent(row.id_krs));
			const componentsForStudent = Object.fromEntries(enabledFields.map((field) => [field, row[field] ?? '']));
			try {
				const response = await fetch(url, {
					method: 'PUT',
					headers: {
						Accept: 'application/json',
						'Content-Type': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
						'X-CSRF-TOKEN': csrfToken,
					},
					body: JSON.stringify({ program: root.dataset.program, components: componentsForStudent }),
				});
				const payload = await response.json().catch(() => ({}));
				if (!response.ok) {
					failures.push(`${row.nim} · ${row.nama_mahasiswa}: ${payload.message || 'Feeder menolak pembaruan nilai.'}`);
				}
			} catch (requestError) {
				failures.push(`${row.nim} · ${row.nama_mahasiswa}: ${requestError.message || 'Gagal menghubungi server.'}`);
			}
			processed += 1;
			setSaveProgress((current) => ({ ...current, processed, failures: [...failures] }));
		}
		saveInProgressRef.current = false;
		setSaveProgress((current) => ({ ...current, phase: failures.length ? 'failed' : 'complete', failures }));
		if (!failures.length) {
			window.setTimeout(() => window.location.assign(root.dataset.cancelUrl), 1200);
		}
	};
	const handleExcelPaste = (event) => {
		const clipboard = event.clipboardData?.getData('text/plain');
		if (!clipboard || !activeCell || !enabledFields.includes(activeCell.columnKey)) return;

		event.preventDefault();
		event.stopPropagation();
		const pastedRows = clipboard.replace(/\r/g, '').split('\n');
		if (pastedRows.at(-1)?.trim() === '') pastedRows.pop();
		const startFieldIndex = componentFields.indexOf(activeCell.columnKey);
		setRows((currentRows) => currentRows.map((row, rowIndex) => {
			const pastedRow = pastedRows[rowIndex - activeCell.rowIdx];
			if (pastedRow === undefined) return row;
			const updated = { ...row };
			pastedRow.split('\t').forEach((value, offset) => {
				const field = componentFields[startFieldIndex + offset];
				if (!field || !enabledFields.includes(field)) return;
				const cleaned = value.trim().replace(',', '.');
				updated[field] = cleaned === '-' || !Number.isFinite(Number(cleaned)) ? '' : cleaned;
			});
			return updated;
		}));
		setError('');
	};

	const handleSubmit = (event) => {
		const invalidRowIndex = rows.findIndex((row) => rowTotal(row, enabledFields, componentWeights) > 100);
		if (invalidRowIndex !== -1) {
			event.preventDefault();
			setError('Akumulasi nilai komponen per mahasiswa tidak boleh melebihi 100.');
			const firstEditableColumn = columns.findIndex((column) => enabledFields.includes(column.key));
			gridRef.current?.setActivePosition({ idx: firstEditableColumn, rowIdx: invalidRowIndex }, { enableEditor: true });
			return;
		}
		if (requireSaveConfirmation && !allowSubmitRef.current) {
			event.preventDefault();
			setSaveConfirmOpen(true);
			return;
		}
		allowSubmitRef.current = false;
		if (sequentialSave) {
			event.preventDefault();
			saveSpGradesSequentially();
		}
	};
	const confirmSave = () => {
		allowSubmitRef.current = true;
		setSaveConfirmOpen(false);
		formRef.current?.requestSubmit();
	};
	const confirmUnlock = () => unlockFormRef.current?.requestSubmit();

	if (unlockOnly) {
		return <>
			<form ref={unlockFormRef} method="POST" action={root.dataset.unlockUrl}>
				<input type="hidden" name="_token" value={csrfToken} />
				<input type="hidden" name="program" value={root.dataset.program} />
			</form>
			<div className="grade-confirm-backdrop">
				<section className="grade-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="grade-unlock-confirm-title" aria-describedby="grade-unlock-confirm-message">
					<div className="calendar-modal-head">
						<div>
							<h2 id="grade-unlock-confirm-title">Konfirmasi Buka Kunci Nilai</h2>
							<p className="calendar-modal-copy" id="grade-unlock-confirm-message">
								Buka kembali input nilai untuk semua mahasiswa pada kelas {root.dataset.classLabel}?
							</p>
						</div>
					</div>
					<div className="calendar-modal-actions">
						<a className="button" href={root.dataset.cancelUrl}>Batal</a>
						<button className="button button-primary" type="button" autoFocus onClick={confirmUnlock}>
							Buka Kembali Nilai
						</button>
					</div>
				</section>
			</div>
		</>;
	}

	return <>
		<form id="grade-entry-form" ref={formRef} method="POST" action={root.dataset.updateUrl} onSubmit={handleSubmit}>
			<input type="hidden" name="_token" value={csrfToken} />
			<input type="hidden" name="_method" value="PUT" />
			<input type="hidden" name="program" value={root.dataset.program} />
			{rows.map((row) => <React.Fragment key={row.id_krs}>
				{enabledFields.map((field) => <input
					key={`${row.id_krs}-${field}`}
					type="hidden"
					name={`grades[${row.id_krs}][${field}]`}
					value={row[field] ?? ''}
				/>)}
			</React.Fragment>)}
		</form>
		<div className="grade-grid-shell" onPasteCapture={handleExcelPaste}>
			<DataGrid
				ref={gridRef}
				aria-label="Input nilai mahasiswa"
				className="grade-spreadsheet"
				columns={columns}
				rows={rows}
				enableVirtualization={false}
				style={{ width: '100%', minWidth: 0, height: '100%' }}
				onRowsChange={handleRowsChange}
				onCellClick={(args) => {
					if (enabledFields.includes(args.column.key)) args.setActivePosition(true);
				}}
				onActivePositionChange={({ rowIdx, column }) => {
					if (rowIdx >= 0 && column) setActiveCell({ rowIdx, columnKey: column.key });
				}}
				onFill={({ columnKey, sourceRow, targetRow }) => enabledFields.includes(columnKey)
					? { ...targetRow, [columnKey]: sourceRow[columnKey] }
					: targetRow}
				rowHeight={40}
				headerRowHeight={44}
				noRowsFallback={<div className="grade-grid-empty">Belum ada mahasiswa yang memilih kelas ini.</div>}
			/>
		</div>
		{error && <div className="calendar-modal-error" role="alert">{error}</div>}
		{canEdit && <div className="form-actions">
			<button className="button button-primary" type="submit" form="grade-entry-form" disabled={rows.length === 0}>
				<span data-lucide="save"></span> Simpan Nilai
			</button>
		</div>}
		{saveProgress && <div className="grade-confirm-backdrop">
			<section className="grade-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="sp-grade-progress-title" aria-describedby="sp-grade-progress-message">
				<h2 id="sp-grade-progress-title">
					{saveProgress.phase === 'failed' ? 'Pembaruan Nilai SP Selesai dengan Kegagalan' : saveProgress.phase === 'complete' ? 'Pembaruan Nilai SP Selesai' : 'Menyimpan Nilai SP'}
				</h2>
				<p id="sp-grade-progress-message" aria-live="polite">
					{saveProgress.phase === 'saving'
						? `${saveProgress.processed} dari ${saveProgress.total} mahasiswa telah diproses satu per satu.`
						: `${saveProgress.total - saveProgress.failures.length} berhasil, ${saveProgress.failures.length} gagal dari ${saveProgress.total} mahasiswa.`}
				</p>
				<div className="grade-progress-track" role="progressbar" aria-label="Progres pembaruan nilai SP" aria-valuemin="0" aria-valuemax="100" aria-valuenow={saveProgress.total ? Math.round(saveProgress.processed / saveProgress.total * 100) : 100}>
					<span style={{ width: `${saveProgress.total ? Math.round(saveProgress.processed / saveProgress.total * 100) : 100}%` }} />
				</div>
				<strong>{saveProgress.total ? Math.round(saveProgress.processed / saveProgress.total * 100) : 100}%</strong>
				{saveProgress.failures.length > 0 && <ul className="grade-progress-errors" role="alert">
					{saveProgress.failures.map((failure, index) => <li key={`${index}-${failure}`}>{failure}</li>)}
				</ul>}
				{saveProgress.phase === 'failed' && <div className="calendar-modal-actions">
					<button className="button button-primary" type="button" onClick={() => window.location.assign(root.dataset.cancelUrl)}>Tutup</button>
				</div>}
			</section>
		</div>}
		{saveConfirmOpen && <div className="grade-confirm-backdrop" onMouseDown={(event) => {
			if (event.target === event.currentTarget) setSaveConfirmOpen(false);
		}}>
			<section className="grade-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="grade-confirm-title" aria-describedby="grade-confirm-message" onKeyDown={(event) => {
				if (event.key === 'Escape') setSaveConfirmOpen(false);
			}}>
				<div className="calendar-modal-head">
					<div>
						<h2 id="grade-confirm-title">Konfirmasi Simpan Nilai</h2>
						<p className="calendar-modal-copy" id="grade-confirm-message">
							{confirmOnce
								? 'Dosen hanya dapat menyimpan nilai satu kali. Simpan nilai seluruh mahasiswa pada kelas ini?'
								: 'Simpan nilai seluruh mahasiswa pada kelas ini?'}
						</p>
					</div>
				</div>
				<div className="calendar-modal-actions">
					<button className="button" type="button" onClick={() => setSaveConfirmOpen(false)}>Batal</button>
					<button className="button button-primary" type="button" onClick={confirmSave}>Simpan Nilai</button>
				</div>
			</section>
		</div>}
	</>;
}

export function mountGradeSpreadsheet(root) {
	const parseJson = (selector, fallback) => {
		try {
			return JSON.parse(root.querySelector(selector)?.textContent || JSON.stringify(fallback));
		} catch {
			return fallback;
		}
	};
	const initialRows = parseJson('[data-grade-rows]', []);
	const gradeRanges = parseJson('[data-grade-ranges]', []);
	const enabledFields = parseJson('[data-enabled-fields]', []);
	const componentWeights = parseJson('[data-component-weights]', {});
	createRoot(root).render(<GradeSpreadsheet
		root={root}
		initialRows={initialRows}
		gradeRanges={gradeRanges}
		enabledFields={enabledFields}
		componentWeights={componentWeights}
	/>);
}