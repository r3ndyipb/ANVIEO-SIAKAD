import React, { startTransition, useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import TomSelect from 'tom-select';

function LoadingState() {
	return <div className="edom-loading" role="status"><span className="edom-loading-mark" aria-hidden="true"></span>Memuat hasil survey…</div>;
}

function SurveyEdomResults({ root, programs, calendarsByProgram }) {
	const showProgramFilter = root.dataset.showProgramFilter === 'true';
	const [programId, setProgramId] = useState(root.dataset.selectedProgram || '');
	const [calendarId, setCalendarId] = useState(root.dataset.selectedCalendar || '');
	const [result, setResult] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState('');
	const activeRequest = useRef(null);
	const requestSequence = useRef(0);
	const programSelect = useRef(null);
	const calendarSelect = useRef(null);
	const calendars = calendarsByProgram[programId] || [];
	const program = programs.find((item) => item.id === programId);
	const calendar = calendars.find((item) => item.id === calendarId);

	useEffect(() => {
		document.dispatchEvent(new Event('simak:render-icons'));
	}, [result]);

	useEffect(() => {
		const programControl = showProgramFilter && programSelect.current
			? new TomSelect(programSelect.current, {
				create: false,
				maxOptions: 1000,
				allowEmptyOption: true,
				plugins: ['clear_button'],
				onChange: changeProgram,
			})
			: null;
		const calendarControl = calendarSelect.current
			? new TomSelect(calendarSelect.current, {
				create: false,
				maxOptions: 1000,
				allowEmptyOption: true,
				plugins: ['clear_button'],
				onChange: (value) => {
					requestSequence.current++;
					activeRequest.current?.abort();
					activeRequest.current = null;
					setCalendarId(value);
					setResult(null);
					setError('');
					setLoading(false);
				},
			})
			: null;

		return () => {
			programControl?.destroy();
			calendarControl?.destroy();
		};
	}, [showProgramFilter]);

	useEffect(() => {
		const control = programSelect.current?.tomselect;
		if (!control) return;

		control.clear(true);
		control.clearOptions();
		control.addOption({ value: '', text: 'Pilih program studi' });
		programs.forEach((item) => control.addOption({ value: item.id, text: item.label }));
		control.setValue(root.dataset.selectedProgram || '', true);
	}, [programs, root]);

	useEffect(() => {
		const control = calendarSelect.current?.tomselect;
		if (!control) return;

		control.clear(true);
		control.clearOptions();
		control.addOption({
			value: '',
			text: programId ? 'Pilih tahun akademik' : 'Pilih prodi terlebih dahulu',
		});
		calendars.forEach((item) => control.addOption({
			value: item.id,
			text: `${item.label}${item.active ? ' · Aktif' : ''}`,
		}));
		control.setValue(
			programId === root.dataset.selectedProgram ? root.dataset.selectedCalendar || '' : '',
			true,
		);
		if (programId && calendars.length > 0) control.enable();
		else control.disable();
	}, [calendars, programId, root]);

	const changeProgram = (value) => {
		requestSequence.current++;
		activeRequest.current?.abort();
		activeRequest.current = null;
		setProgramId(value);
		setCalendarId('');
		setResult(null);
		setError('');
		setLoading(false);
	};

	const showResults = async (event) => {
		event.preventDefault();
		if (!programId || !calendars.some((item) => item.id === calendarId)) {
			setResult(null);
			setError('Pilih kalender akademik yang sesuai dengan program studi.');
			return;
		}
		activeRequest.current?.abort();
		const controller = new AbortController();
		activeRequest.current = controller;
		const currentRequest = ++requestSequence.current;
		setLoading(true);
		setError('');
		const url = new URL(root.dataset.dataUrl, window.location.origin);
		url.searchParams.set('program', programId);
		url.searchParams.set('calendar', calendarId);
		try {
			const response = await fetch(url, {
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
				signal: controller.signal,
			});
			const payload = await response.json();
			if (!response.ok) {
				const message = response.status === 404
					? 'Program studi dan kalender akademik tidak cocok. Pilih ulang kedua filter.'
					: payload.message || `Hasil EDOM gagal dimuat (${response.status}).`;
				throw new Error(message);
			}
			if (currentRequest !== requestSequence.current) return;
			startTransition(() => setResult(payload));
		} catch (requestError) {
			if (requestError.name !== 'AbortError' && currentRequest === requestSequence.current) {
				setError(requestError.message);
				setResult(null);
			}
		} finally {
			if (currentRequest === requestSequence.current) {
				activeRequest.current = null;
				setLoading(false);
			}
		}
	};

	return <>
		<form className="edom-filters" onSubmit={showResults}>
			{showProgramFilter ? <div>
				<label htmlFor="edom-program">Program studi</label>
				<select id="edom-program" ref={programSelect} defaultValue="" required>
					<option value="">Pilih program studi</option>
				</select>
			</div> : <div>
				<label htmlFor="edom-program-fixed">Program studi</label>
				<input id="edom-program-fixed" value={program?.label || 'Program studi akun'} disabled />
			</div>}
			<div>
				<label htmlFor="edom-calendar">Tahun akademik</label>
				<select id="edom-calendar" ref={calendarSelect} defaultValue="" required>
					<option value="">{programId ? 'Pilih tahun akademik' : 'Pilih prodi terlebih dahulu'}</option>
				</select>
			</div>
			<button className="button button-primary" type="submit" disabled={!programId || !calendarId || loading}>Tampilkan</button>
		</form>

		{error && <div className="calendar-modal-error" role="alert">{error}</div>}
		{loading && <LoadingState />}

		{!programs.length ? <div className="edom-empty">Cakupan program studi akun ini belum ditetapkan atau tidak memiliki jadwal mengajar.</div>
			: (!programId ? <div className="edom-empty">Pilih program studi untuk melihat hasil EDOM.</div>
				: (!calendars.length ? <div className="edom-empty">Belum ada kalender akademik untuk program studi ini.</div>
					: (!calendarId ? <div className="edom-empty">Pilih kalender akademik untuk melihat hasil EDOM.</div> : null)))}

		{result && <>
			{result.question_count === 0 ? <div className="edom-empty">Instrumen EDOM belum tersedia, sehingga hasil belum dapat dihitung.</div>
				: (!result.results.length ? <div className="edom-empty">Belum ada dosen atau asisten yang dapat ditampilkan pada cakupan ini.</div>
					: (result.results.every((lecturer) => lecturer.respondent_count === 0)
						? <div className="edom-empty">Belum ada jawaban survey EDOM untuk kalender ini.</div>
						: <>
							<div className="page-heading" style={{ marginTop: 8 }}>
								<p className="subtitle">{result.program} · {result.calendar} · {Number(result.question_count).toLocaleString('id-ID')} pertanyaan</p>
							</div>
							<section className="edom-teacher-grid" aria-label="Ringkasan EDOM per dosen">
								{result.results.map((lecturer) => <article className="edom-teacher" key={lecturer.id}>
									<div className="edom-teacher-head">
										<div>
											<div className="edom-teacher-name">{lecturer.name || 'Nama belum tersedia'}</div>
											<div className="edom-teacher-stats">
												<span>MK {lecturer.course_count}</span>
												<span>Kelas {lecturer.class_count}</span>
												<span>Responden {lecturer.respondent_count}</span>
											</div>
										</div>
										{lecturer.respondent_count > 0 && <a className="edom-download" href={lecturer.export_url} title="Unduh detail EDOM anonim" aria-label={`Unduh detail EDOM anonim ${lecturer.name}`}><span data-lucide="file-down"></span></a>}
									</div>
									{lecturer.aspects.map((aspect) => <div className="edom-aspect" key={aspect.id}>
										<div className="edom-aspect-head"><span>{aspect.label}</span><strong>{Number(aspect.percentage).toFixed(1)}%</strong></div>
										<div className="edom-aspect-track" role="img" aria-label={`${aspect.label} ${aspect.percentage} persen`}>
											<div className="edom-aspect-fill" style={{ width: `${Math.min(100, Number(aspect.percentage))}%`, background: aspect.color }}></div>
										</div>
										<small className="edom-aspect-count">{Number(aspect.responses).toLocaleString('id-ID')} jawaban aspek</small>
									</div>)}
								</article>)}
							</section>
						</>))}
		</>}
	</>;
}

export function mountSurveyEdomResults(root) {
	const parse = (selector) => {
		try {
			return JSON.parse(root.querySelector(selector)?.textContent || '{}');
		} catch {
			return {};
		}
	};
	const programs = parse('[data-edom-programs]');
	const calendars = parse('[data-edom-calendars]');
	const mount = root.querySelector('[data-edom-results-mount]');
	if (mount) createRoot(mount).render(<SurveyEdomResults root={root} programs={programs} calendarsByProgram={calendars} />);
}