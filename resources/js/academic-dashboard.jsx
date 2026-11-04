import React, { startTransition, useDeferredValue, useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

const palette = ['#32754d', '#c49a32', '#4b7ca8', '#b7654d', '#278f89', '#8767a7', '#778d46', '#c27a31'];
const progressSeries = [
	['Registrasi ulang', 'registration_rate', '#c49a32'],
	['Isi KRS', 'krs_rate', '#4b7ca8'],
	['Disahkan prodi', 'approval_rate', '#32754d'],
	['Input nilai', 'grade_rate', '#b7654d'],
];

function tooltipPosition(event) {
	const element = event.currentTarget;
	const bounds = element.ownerSVGElement?.parentElement?.getBoundingClientRect();
	const rect = element.getBoundingClientRect();
	if (!bounds) return { x: rect.left + rect.width / 2, y: rect.top };
	return { x: rect.left - bounds.left + rect.width / 2, y: rect.top - bounds.top };
}

function LoadingState() {
	return <div className="academic-loading" role="status"><span className="academic-loading-mark" aria-hidden="true"></span>Memuat ringkasan akademik…</div>;
}

function ProgressChart({ rows }) {
	const [tooltip, setTooltip] = useState(null);
	const isEnglish = window.simakLocale === 'en';
	if (!rows.length) return <div className="academic-empty">Belum ada data proses akademik untuk cakupan ini.</div>;

	const width = 920;
	const labelWidth = 280;
	const chartWidth = width - labelWidth - 35;
	const rowHeight = 76;
	const height = 42 + rows.length * rowHeight;

	return <div className="academic-chart-scroll">
		<svg className="academic-chart" style={{ minWidth: `${width}px`, height: `${height}px` }} viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Perbandingan capaian proses akademik per program studi">
			{[0, 25, 50, 75, 100].map((tick) => {
				const x = labelWidth + chartWidth * tick / 100;
				return <g key={tick}>
					<line x1={x} y1="22" x2={x} y2={height - 10} className="academic-chart-grid" />
					<text x={x} y="14" textAnchor="middle" className="academic-chart-label">{tick}%</text>
				</g>;
			})}
			{rows.map((row, rowIndex) => {
				const top = 35 + rowIndex * rowHeight;
				return <g key={row.id}>
					<text x="8" y={top + 14} className="academic-progress-label">{row.name}</text>
					<text x="8" y={top + 30} className="academic-chart-label">{row.faculty}</text>
					{progressSeries.map(([label, field, color], seriesIndex) => {
						const y = top + 4 + seriesIndex * 16;
						const value = Number(row[field] || 0);
						const translatedLabel = isEnglish ? {
							'Registrasi ulang': 'Re-enrollment',
							'Isi KRS': 'Study plan completion',
							'Disahkan prodi': 'Program approval',
							'Input nilai': 'Grade entry',
						}[label] : label;
						const tooltipText = `${row.name} · ${row.faculty} · ${translatedLabel}: ${value}%`;
						return <g key={field}>
							<rect x={labelWidth} y={y} width={chartWidth} height="8" rx="4" fill="#edf1e9" />
							<rect x={labelWidth} y={y} width={chartWidth * value / 100} height="8" rx="4" fill={color} />
							<rect
								x={labelWidth}
								y={y - 4}
								width={chartWidth}
								height="16"
								fill="transparent"
								tabIndex="0"
								role="img"
								aria-label={tooltipText}
								onMouseEnter={(event) => setTooltip({ text: tooltipText, ...tooltipPosition(event) })}
								onMouseLeave={() => setTooltip(null)}
								onFocus={(event) => setTooltip({ text: tooltipText, ...tooltipPosition(event) })}
								onBlur={() => setTooltip(null)}
							/>
						</g>;
					})}
				</g>;
			})}
		</svg>
		{tooltip && <div className="academic-chart-tooltip" role="tooltip" style={{ left: `${tooltip.x}px`, top: `${tooltip.y}px`, transform: tooltip.y < 72 ? 'translate(-50%, 10px)' : 'translate(-50%, calc(-100% - 10px))' }}>{tooltip.text}</div>}
		<div className="academic-chart-legend" aria-label="Legenda capaian proses">
			{progressSeries.map(([label, _field, color]) => <span key={label}><i className="academic-chart-swatch" style={{ background: color }}></i>{label}</span>)}
		</div>
	</div>;
}

function TrendChart({ rows, years, metric, setMetric }) {
	const [visibleIds, setVisibleIds] = useState(() => rows.map((row) => row.id));
	const [tooltip, setTooltip] = useState(null);
	const isEnglish = window.simakLocale === 'en';
	useEffect(() => setVisibleIds(rows.map((row) => row.id)), [rows]);
	const visibleRows = rows.filter((row) => visibleIds.includes(row.id));
	const width = Math.max(760, 110 + years.length * 135);
	const height = 320;
	const padding = { top: 24, right: 24, bottom: 42, left: 54 };
	const plotWidth = width - padding.left - padding.right;
	const plotHeight = height - padding.top - padding.bottom;
	const yFor = (value) => padding.top + (1 - Math.min(4, Math.max(0, value)) / 4) * plotHeight;

	if (!rows.length) return <div className="academic-empty">Belum ada riwayat AKM empat tahun pada cakupan ini.</div>;

	return <>
		<div className="academic-section-head">
			<div>
				<h2>Tren rata-rata {metric.toUpperCase()} per program studi</h2>
				<p>Rata-rata dihitung dari record AKM pada tahun akademik bersangkutan.</p>
			</div>
			<div className="academic-metric-toggle" role="group" aria-label="Metrik tren">
				{['ips', 'ipk'].map((value) => <button key={value} type="button" aria-pressed={metric === value} onClick={() => setMetric(value)}>{value.toUpperCase()}</button>)}
			</div>
		</div>
		<div className="academic-chart-scroll">
			<svg className="academic-chart" style={{ minWidth: `${width}px`, height: `${height}px` }} viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`Tren rata-rata ${metric.toUpperCase()} empat tahun per program studi`}>
				{[0, 1, 2, 3, 4].map((tick) => {
					const y = yFor(tick);
					return <g key={tick}>
						<line x1={padding.left} y1={y} x2={width - padding.right} y2={y} className="academic-chart-grid" />
						<text x={padding.left - 10} y={y + 4} textAnchor="end" className="academic-chart-label">{tick.toFixed(1)}</text>
					</g>;
				})}
				{years.map((year, index) => {
					const x = padding.left + (years.length <= 1 ? 0 : index / (years.length - 1) * plotWidth);
					return <text key={year} x={x} y={height - 12} textAnchor="middle" className="academic-chart-label">{year}</text>;
				})}
				{visibleRows.map((row) => {
					const originalIndex = rows.findIndex((item) => item.id === row.id);
					const color = palette[originalIndex % palette.length];
					const points = years.map((year, index) => {
						const value = row.values[year]?.[metric];
						if (value === null || value === undefined) return null;
						const x = padding.left + (years.length <= 1 ? 0 : index / (years.length - 1) * plotWidth);
						return { x, y: yFor(Number(value)), year, value };
					}).filter(Boolean);
					return <g key={row.id}>
						{points.length > 1 && <polyline points={points.map((point) => `${point.x},${point.y}`).join(' ')} stroke={color} className="academic-chart-line" />}
						{points.map((point) => {
							const tooltipText = `${row.name} · ${isEnglish ? 'Academic year' : 'Tahun akademik'} ${point.year}: ${Number(point.value).toFixed(2)} ${metric.toUpperCase()}`;
							return <circle
								key={point.year}
								cx={point.x}
								cy={point.y}
								r="5"
								fill={color}
								className="academic-chart-point"
								tabIndex="0"
								role="img"
								aria-label={tooltipText}
								onMouseEnter={(event) => setTooltip({ text: tooltipText, ...tooltipPosition(event) })}
								onMouseLeave={() => setTooltip(null)}
								onFocus={(event) => setTooltip({ text: tooltipText, ...tooltipPosition(event) })}
								onBlur={() => setTooltip(null)}
							/>;
						})}
					</g>;
				})}
			</svg>
			{tooltip && <div className="academic-chart-tooltip" role="tooltip" style={{ left: `${tooltip.x}px`, top: `${tooltip.y}px`, transform: tooltip.y < 72 ? 'translate(-50%, 10px)' : 'translate(-50%, calc(-100% - 10px))' }}>{tooltip.text}</div>}
		</div>
		<div className="academic-chart-legend" aria-label="Program studi pada grafik">
			{rows.map((row, index) => {
				const visible = visibleIds.includes(row.id);
				return <button key={row.id} type="button" aria-pressed={visible} onClick={() => setVisibleIds((current) => visible
					? current.filter((id) => id !== row.id)
					: [...current, row.id])}>
					<i className="academic-chart-swatch" style={{ background: palette[index % palette.length] }}></i>{row.name}
				</button>;
			})}
		</div>
	</>;
}

function DtpsDetailsDialog({ selection, details, loading, error, onClose }) {
	if (!selection) return null;
	return <div className="academic-dtps-backdrop" onMouseDown={(event) => {
		if (event.target === event.currentTarget) onClose();
	}}>
		<section className="academic-dtps-dialog" role="dialog" aria-modal="true" aria-labelledby="academic-dtps-title" onKeyDown={(event) => {
			if (event.key === 'Tab') {
				event.preventDefault();
				event.currentTarget.querySelector('button')?.focus();
			}
		}}>
			<header>
				<div><span>Rincian DTPS · {selection.categoryLabel}</span><h2 id="academic-dtps-title">{selection.programName}</h2></div>
				<button type="button" autoFocus onClick={onClose} aria-label="Tutup rincian DTPS">×</button>
			</header>
			{loading ? <div className="academic-empty" role="status">Memuat rincian dosen…</div>
				: error ? <div className="calendar-modal-error" role="alert">{error}</div>
					: !details?.lecturers?.length ? <div className="academic-empty">Tidak ada dosen pada kategori ini.</div>
						: <div className="academic-table-wrap">
							<table className="academic-table academic-dtps-table">
								<thead><tr><th>Nama dosen</th><th>Mata kuliah (tahun terakhir mengajar)</th><th>Tahun akademik terakhir</th></tr></thead>
								<tbody>{details.lecturers.map((lecturer) => <tr key={lecturer.id}>
									<td><strong>{lecturer.name || '—'}</strong></td>
									<td>{lecturer.courses?.length ? <ul>{lecturer.courses.map((course) => <li key={`${course.name}-${course.academic_year}`}>{course.name} ({course.academic_year || '-'})</li>)}</ul> : '-'}</td>
									<td>{lecturer.latest_academic_year || '-'}</td>
								</tr>)}</tbody>
							</table>
						</div>}
		</section>
	</div>;
}

function AcademicDashboard({ root, calendarOptions }) {
	const dtpsTriggerRef = useRef(null);
	const [selectedYear, setSelectedYear] = useState(root.dataset.selectedYear || '');
	const [activeYear, setActiveYear] = useState(root.dataset.selectedYear || '');
	const [dashboard, setDashboard] = useState(null);
	const [loading, setLoading] = useState(false);
	const [insights, setInsights] = useState(null);
	const [insightsLoading, setInsightsLoading] = useState(false);
	const [insightsError, setInsightsError] = useState('');
	const [error, setError] = useState('');
	const [metric, setMetric] = useState('ips');
	const [dtpsSelection, setDtpsSelection] = useState(null);
	const [dtpsDetails, setDtpsDetails] = useState(null);
	const [dtpsLoading, setDtpsLoading] = useState(false);
	const [dtpsError, setDtpsError] = useState('');
	const deferredSummary = useDeferredValue(dashboard?.programSummary || []);
	const deferredTrends = useDeferredValue(insights?.trendRows || []);

	useEffect(() => {
		if (!dtpsSelection || !activeYear) return undefined;
		const abortController = new AbortController();
		const url = new URL(root.dataset.dtpsDetailsUrl, window.location.origin);
		url.searchParams.set('program', dtpsSelection.programId);
		url.searchParams.set('calendar', activeYear);
		url.searchParams.set('category', dtpsSelection.category);
		setDtpsDetails(null);
		setDtpsError('');
		setDtpsLoading(true);
		fetch(url, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			signal: abortController.signal,
		})
			.then(async (response) => {
				const payload = await response.json();
				if (!response.ok) throw new Error(payload.message || `Rincian DTPS gagal dimuat (${response.status}).`);
				setDtpsDetails(payload);
			})
			.catch((requestError) => {
				if (requestError.name !== 'AbortError') setDtpsError(requestError.message);
			})
			.finally(() => {
				if (!abortController.signal.aborted) setDtpsLoading(false);
			});
		return () => abortController.abort();
	}, [activeYear, dtpsSelection, root]);

	useEffect(() => {
		if (!dtpsSelection) return undefined;
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') setDtpsSelection(null);
		};
		document.addEventListener('keydown', closeOnEscape);
		return () => document.removeEventListener('keydown', closeOnEscape);
	}, [dtpsSelection]);
	const closeDtpsDetails = () => {
		setDtpsSelection(null);
		window.requestAnimationFrame(() => dtpsTriggerRef.current?.focus());
	};

	useEffect(() => {
		if (!activeYear) {
			setDashboard(null);
			setInsights(null);
			return undefined;
		}

		const abortController = new AbortController();
		setLoading(true);
		setError('');
		const url = new URL(root.dataset.dataUrl, window.location.origin);
		url.searchParams.set('calendar', activeYear);
		fetch(url, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			signal: abortController.signal,
		})
			.then(async (response) => {
				const payload = await response.json();
				if (!response.ok) throw new Error(payload.message || `Data dashboard gagal dimuat (${response.status}).`);
				startTransition(() => setDashboard(payload));
			})
			.catch((requestError) => {
				if (requestError.name !== 'AbortError') setError(requestError.message);
			})
			.finally(() => {
				if (!abortController.signal.aborted) setLoading(false);
			});

		return () => abortController.abort();
	}, [activeYear, root]);

	useEffect(() => {
		const year = activeYear;
		if (!year) return undefined;

		const abortController = new AbortController();
		setInsights(null);
		setInsightsError('');
		setInsightsLoading(true);
		const url = new URL(root.dataset.insightsUrl, window.location.origin);
		url.searchParams.set('calendar', year);
		fetch(url, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			signal: abortController.signal,
		})
			.then(async (response) => {
				const payload = await response.json();
				if (!response.ok) throw new Error(payload.message || `Grafik dashboard gagal dimuat (${response.status}).`);
				setInsights(payload);
			})
			.catch((requestError) => {
				if (requestError.name !== 'AbortError') setInsightsError(requestError.message);
			})
			.finally(() => {
				if (!abortController.signal.aborted) setInsightsLoading(false);
			});

		return () => abortController.abort();
	}, [activeYear, root]);

	const submitCalendar = (event) => {
		event.preventDefault();
		setDashboard(null);
		setInsights(null);
		setActiveYear(selectedYear);
	};
	const processExportUrl = (programId, metric) => {
		const url = new URL(root.dataset.exportUrl, window.location.origin);
		url.searchParams.set('program', programId);
		url.searchParams.set('calendar', activeYear);
		url.searchParams.set('metric', metric);
		return url.toString();
	};

	return <>
		<form className="academic-filter" onSubmit={submitCalendar}>
			<div>
				<label htmlFor="academic-calendar">Kalender akademik</label>
				<select id="academic-calendar" value={selectedYear} onChange={(event) => setSelectedYear(event.target.value)} required>
					<option value="">Pilih tahun akademik</option>
					{calendarOptions.map((option) => <option key={option.year} value={option.year}>
						{option.label}{option.active ? ' · Ada kalender aktif' : ''}
					</option>)}
				</select>
			</div>
			<button className="button button-primary" type="submit" disabled={!selectedYear || loading}>Tampilkan</button>
			<p className="academic-filter-note">Data mengikuti cakupan akun dan kalender yang dipilih.</p>
		</form>

		{calendarOptions.length === 0 ? <div className="academic-empty"><strong>Kalender akademik belum tersedia.</strong><br />Cakupan akun ini belum memiliki kalender yang dapat dipantau.</div>
			: (!activeYear ? <div className="academic-empty"><strong>Pilih kalender akademik untuk memulai.</strong><br />Ringkasan dan tren dimuat setelah tahun akademik dipilih.</div>
				: (loading && !dashboard ? <LoadingState /> : null))}
		{error && <div className="calendar-modal-error" role="alert">{error}</div>}

		{dashboard && <div aria-busy={loading}>
			{loading && <LoadingState />}
			<section className="academic-cards" aria-label={`Ringkasan akademik ${dashboard.calendar.label}`}>
				{dashboard.cards.map((card) => <article className="academic-card" key={card.label}>
					<span className="academic-card-label">{card.label}</span>
					<strong className="academic-card-value">{typeof card.value === 'number' ? card.value.toLocaleString('id-ID') : card.value}</strong>
					<small className="academic-card-detail">{card.detail}</small>
				</article>)}
			</section>

			{deferredSummary.length === 0 ? <div className="academic-empty">Belum ada program studi dalam cakupan akun ini.</div> : <>
				<section className="academic-section" aria-labelledby="academic-summary-heading">
					<div className="academic-section-head">
						<div><h2 id="academic-summary-heading">Proses akademik per program studi</h2><p>Registrasi ulang, KRS, dan dosen pengampu dihitung sebagai data unik pada tahun akademik terpilih.</p></div>
						<span className="academic-term-chip">{dashboard.calendar.label}</span>
					</div>
					<div className="academic-table-wrap">
						<table className="academic-table">
							<thead><tr><th>Program studi</th><th>Mahasiswa aktif</th><th>Registrasi ulang</th><th>Sudah isi KRS</th><th>Disahkan prodi</th><th>Nilai diinput</th><th>DTPS</th><th>Homebase</th><th>Non-homebase</th><th>Rata-rata IPS</th><th>Rata-rata IPK</th><th>Rata-rata EDOM</th></tr></thead>
							<tbody>{deferredSummary.map((program) => <tr key={program.id}>
								<td><strong>{program.name}</strong><small>{program.faculty}</small></td>
								<td><a className="academic-number-link" href={processExportUrl(program.id, 'active')} aria-label={`Unduh daftar mahasiswa aktif ${program.name} dalam Excel`}>{Number(program.active_students).toLocaleString('id-ID')}</a></td>
								<td><a className="academic-number-link" href={processExportUrl(program.id, 'registered')} aria-label={`Unduh daftar registrasi ulang ${program.name} dalam Excel`}>{Number(program.registered_students).toLocaleString('id-ID')}</a><small className="subtext">{program.registration_rate}%</small></td>
								<td><a className="academic-number-link" href={processExportUrl(program.id, 'krs')} aria-label={`Unduh daftar mahasiswa sudah isi KRS ${program.name} dalam Excel`}>{Number(program.krs_students).toLocaleString('id-ID')}</a><small className="subtext">{program.krs_rate}%</small></td>
								<td><a className="academic-number-link" href={processExportUrl(program.id, 'approved')} aria-label={`Unduh daftar KRS disahkan prodi ${program.name} dalam Excel`}>{Number(program.approved_students).toLocaleString('id-ID')}</a><small className="subtext">{program.approval_rate}%</small></td>
								<td><a className="academic-number-link" href={processExportUrl(program.id, 'grades')} aria-label={`Unduh daftar mahasiswa dengan nilai diinput ${program.name} dalam Excel`}>{Number(program.grades_entered).toLocaleString('id-ID')}</a><small className="subtext">{program.grade_rate}%</small></td>
								<td><button className="academic-dtps-trigger" type="button" onClick={(event) => { dtpsTriggerRef.current = event.currentTarget; setDtpsSelection({ programId: program.id, programName: program.name, category: 'all', categoryLabel: 'Semua kategori' }); }}>{Number(program.dtps).toLocaleString('id-ID')}</button></td>
								<td><button className="academic-dtps-trigger" type="button" onClick={(event) => { dtpsTriggerRef.current = event.currentTarget; setDtpsSelection({ programId: program.id, programName: program.name, category: 'homebase', categoryLabel: 'Homebase' }); }}>{Number(program.dtps_homebase).toLocaleString('id-ID')}</button></td>
								<td><button className="academic-dtps-trigger" type="button" onClick={(event) => { dtpsTriggerRef.current = event.currentTarget; setDtpsSelection({ programId: program.id, programName: program.name, category: 'non_homebase', categoryLabel: 'Non-homebase' }); }}>{Number(program.dtps_non_homebase).toLocaleString('id-ID')}</button></td>
								<td>{program.average_ips === null ? '-' : Number(program.average_ips).toFixed(2)}</td>
								<td>{program.average_ipk === null ? '-' : Number(program.average_ipk).toFixed(2)}</td>
								<td>{program.average_edom_score === null ? '-' : `${Number(program.average_edom_score).toFixed(1)}%`}<small className="subtext">{Number(program.edom_responses).toLocaleString('id-ID')} jawaban</small></td>
							</tr>)}</tbody>
						</table>
					</div>
				</section>

				<section className="academic-section" aria-labelledby="academic-top-edom-heading">
					<div className="academic-section-head">
						<div><h2 id="academic-top-edom-heading">Top 10 Survey EDOM berdasarkan dosen</h2><p>Urutan berdasarkan rata-rata skor jawaban pada kalender yang dipilih.</p></div>
						<span className="academic-term-chip">{dashboard.calendar.label}</span>
					</div>
					{insightsError && <div className="calendar-modal-error" role="alert">{insightsError}</div>}
					{insightsLoading && <LoadingState />}
					{insights?.topEdomLecturers.length > 0 && <div className="academic-edom-chart" role="img" aria-label="Grafik peringkat rata-rata skor Survey EDOM dosen">
						{insights.topEdomLecturers.map((lecturer, index) => <div className="academic-edom-row" key={lecturer.id}>
							<span className="academic-edom-label">{index + 1}. {lecturer.name}</span>
							<span className="academic-edom-track"><i className="academic-edom-bar" style={{ width: `${Math.max(0, Math.min(100, Number(lecturer.average_score) || 0))}%` }}></i></span>
							<strong className="academic-edom-value">{Number(lecturer.average_score).toFixed(1)}%</strong>
						</div>)}
					</div>}
					<div className="academic-table-wrap">
						{insights && insights.topEdomLecturers.length === 0 ? <div className="academic-empty">Belum ada jawaban Survey EDOM untuk kalender ini.</div> : insights ? (
							<table className="academic-table">
								<thead><tr><th>Peringkat</th><th>Dosen</th><th>Rata-rata capaian</th><th>Jawaban</th><th>Mata kuliah</th><th>Kelas</th><th>Responden</th></tr></thead>
								<tbody>{insights.topEdomLecturers.map((lecturer, index) => <tr key={lecturer.id}>
									<td>{index + 1}</td>
									<td><strong>{lecturer.name}</strong></td>
									<td className="academic-rate">{Number(lecturer.average_score).toFixed(1)}%<div className="academic-rate-track"><div className="academic-rate-bar is-blue" style={{ width: `${Math.min(100, lecturer.average_score)}%` }}></div></div></td>
									<td>{Number(lecturer.response_count).toLocaleString('id-ID')}</td>
									<td>{Number(lecturer.course_count).toLocaleString('id-ID')}</td>
									<td>{Number(lecturer.class_count).toLocaleString('id-ID')}</td>
									<td>{Number(lecturer.respondent_count).toLocaleString('id-ID')}</td>
								</tr>)}</tbody>
							</table>
						) : null}
					</div>
				</section>

				<section className="academic-section" aria-labelledby="academic-progress-heading">
					<div className="academic-section-head"><div><h2 id="academic-progress-heading">Capaian proses</h2><p>Tingkat registrasi ulang, pengisian KRS, pengesahan, dan input nilai per program studi.</p></div></div>
					<ProgressChart rows={deferredSummary} />
				</section>

				<section className="academic-section" aria-labelledby="academic-trend-heading">
					<div className="academic-section-head"><div><h2 id="academic-trend-heading">Tren akademik per program studi</h2><p>Rata-rata dari record AKM pada seluruh kalender di setiap tahun.</p></div><span className="academic-term-chip">{insights ? `${insights.trendYears[0]}–${insights.trendYears.at(-1)}` : 'Memuat tren'}</span></div>
					{insights ? <TrendChart rows={deferredTrends} years={insights.trendYears} metric={metric} setMetric={setMetric} /> : <LoadingState />}
				</section>
			</>}
		</div>}
		<DtpsDetailsDialog
			selection={dtpsSelection}
			details={dtpsDetails}
			loading={dtpsLoading}
			error={dtpsError}
			onClose={closeDtpsDetails}
		/>
	</>;
}

export function mountAcademicDashboard(root) {
	let calendarOptions = [];
	try {
		calendarOptions = JSON.parse(root.querySelector('[data-academic-calendar-options]')?.textContent || '[]');
	} catch {
		calendarOptions = [];
	}
	const mount = root.querySelector('[data-academic-dashboard-mount]');
	if (mount) createRoot(mount).render(<AcademicDashboard root={root} calendarOptions={calendarOptions} />);
}