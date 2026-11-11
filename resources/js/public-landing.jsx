import React, { startTransition, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import './page-loader.js';
import './auth-notification.js';
import '../css/public-landing.css';
import '../css/page-loader.css';

import UtilityBar from './components/UtilityBar';
import Header from './components/Header';
import Footer from './components/Footer';
import HomeView from './components/HomeView';
import AcademicView from './components/AcademicView';
import AboutView from './components/AboutView';
import AcademicDetailView from './components/AcademicDetailView';
import StructureView from './components/StructureView';
import AdmissionsView from './components/AdmissionsView';
import AccreditationView from './components/AccreditationView';
import PaymentView from './components/PaymentView';
import ContactView from './components/ContactView';
import PrivacyView from './components/PrivacyView';
import TermsView from './components/TermsView';
import AccessibilityView from './components/AccessibilityView';

const translations = {
	id: {
		navDashboard: 'Dashboard',
		navFaculties: 'Fakultas',
		navPrograms: 'Program Studi',
		openMenu: 'Buka menu',
		closeMenu: 'Tutup menu',
		login: 'Login SIAKAD',
		openDashboard: 'Buka dashboard',
		heroEyebrow: 'ASEAN University International · Study From Anywhere',
		faculties: 'Dashboard Fakultas',
		programs: 'Dashboard Program Studi',
		faculty: 'Dashboard fakultas',
		program: 'Dashboard program studi',
		university: 'Dashboard Universitas',
		back: 'Kembali',
		snapshot: 'Ringkasan akademik',
		programCount: 'Program studi',
		students: 'Mahasiswa',
		studentStatusScope: 'Status A, N, C, dan M',
		lecturers: 'Dosen',
		dtps: 'DTPS',
		dtpsTitle: 'Sebaran DTPS per program studi',
		dtpsCopy: 'Dosen unik yang tercantum pada jadwal mengajar seluruh semester, dikelompokkan menurut homebase.',
		dtpsHomebase: 'Homebase',
		dtpsNonHomebase: 'Non-homebase',
		dtpsTotal: 'Total DTPS',
		dtpsDetails: 'Rincian DTPS',
		dtpsName: 'Nama dosen',
		dtpsCourses: 'Mata kuliah (tahun terakhir mengajar)',
		dtpsLatest: 'Tahun akademik terakhir',
		dtpsLoading: 'Memuat rincian dosen…',
		dtpsEmpty: 'Tidak ada dosen pada kategori ini.',
		dtpsAll: 'Semua kategori',
		closeDtps: 'Tutup rincian DTPS',
		courses: 'Mata kuliah',
		classes: 'Kelas kuliah',
		krs: 'Data KRS',
		proposals: 'Usulan judul',
		theses: 'Skripsi aktif',
		exams: 'Ujian skripsi',
		unitChart: 'Sebaran data per unit',
		unitCopy: 'Perbandingan mahasiswa, dosen, dan mata kuliah pada cakupan ini.',
		moduleChart: 'Aktivitas modul akademik',
		moduleCopy: 'Jumlah data agregat di layanan akademik, E-Skripsi, dan E-Kurikulum.',
		statusChartTooltip: 'Grafik membandingkan jumlah data untuk setiap status yang tercatat.',
		thesisChart: 'Tahapan E-Skripsi',
		proposalStatus: 'Status usulan judul',
		thesisStatus: 'Status skripsi',
		intakeChart: 'Tren mahasiswa per angkatan',
		intakeCopy: 'Jumlah data mahasiswa berdasarkan tahun pada empat digit awal periode masuk, tanpa filter status mahasiswa.',
		intakeTotal: 'Seluruh mahasiswa',
		intakeTooltipHint: 'Arahkan kursor atau fokuskan titik pada grafik untuk melihat jumlah mahasiswa per tahun.',
		intakeCount: 'mahasiswa',
		studentMap: 'Peta sebaran mahasiswa',
		studentMapCopy: 'Terpetakan {mapped} dari {total} mahasiswa status A, N, C, dan M; {missing} belum memiliki data provinsi.',
		mapNoData: 'Belum ada data wilayah mahasiswa untuk ditampilkan.',
		mapStudents: 'mahasiswa',
		status: 'Status',
		records: 'data',
		noData: 'Belum ada data untuk divisualisasikan.',
		visitors: 'Kunjungan situs',
		visitorDescription: 'Statistik dan tren kunjungan browser ke portal SIAKAD.',
		visitorsToday: 'Hari ini',
		visitorsMonth: 'Bulan ini',
		visitorsYear: 'Tahun ini',
		visitorsTotal: 'Browser unik',
		visitorTodayDetail: 'Browser unik hari ini',
		visitorMonthDetail: 'Browser unik bulan berjalan',
		visitorYearDetail: 'Browser unik tahun berjalan',
		visitorTotalDetail: 'Browser unik sejak pencatatan',
		visitorTrend: 'Grafik analitis trafik kunjungan',
		visitorTrendCopy: 'Frekuensi browser unik yang mengakses halaman ini dalam rentang harian, bulanan, dan tahunan.',
		visitorDaily: 'Harian',
		visitorMonthly: 'Bulanan',
		visitorYearly: 'Tahunan',
		uniqueVisitors: 'browser unik',
		loadingDashboard: 'Memuat ringkasan akademik…',
	},
};

const palette = [
	'#0A2E52', '#C9962E', '#10B981', '#6366F1', '#EC4899', '#8B5CF6',
	'#14B8A6', '#F59E0B', '#3B82F6', '#EF4444',
];

const unitMetricKeys = ['students', 'lecturers', 'courses'];

const proposalLabels = {
	pengajuan: { id: 'Diajukan' },
	diterima: { id: 'Diterima' },
	ditolak: { id: 'Ditolak' },
};

const thesisLabels = {
	bimbingan: { id: 'Bimbingan' },
	lulus: { id: 'Lulus' },
	mengulang: { id: 'Mengulang' },
};

function formatNumber(value) {
	if (value === null || value === undefined) return '—';
	return Number(value).toLocaleString('id-ID');
}

function formatVisitorLabel(label, period, language, tooltip = false) {
	if (!label) return '—';
	if (period === 'yearly') return label;
	if (period === 'monthly') {
		const [year, month] = label.split('-');
		const date = new Date(Number(year), Number(month) - 1, 1);
		return date.toLocaleDateString(language === 'id' ? 'id-ID' : 'en-US', {
			month: tooltip ? 'long' : 'short',
			year: 'numeric',
		});
	}
	const [year, month, day] = label.split('-');
	const date = new Date(Number(year), Number(month) - 1, Number(day));
	return date.toLocaleDateString(language === 'id' ? 'id-ID' : 'en-US', {
		day: 'numeric',
		month: tooltip ? 'long' : 'short',
	});
}

function MetricCards({ data, language }) {
	const text = translations[language] || translations.id;
	return (
		<div className="metric-grid">
			{data.metrics.map(({ key, value }, index) => (
				<article className="metric-card reveal" key={key} data-reveal style={{ '--metric-color': palette[index % palette.length] }}>
					<span className="metric-icon" aria-hidden="true">
						{['\u2302', '\u25c9', '\u2733', '\u25a4', '\u25a6', '\u25a7', '\u2301', '\u25c8', '\u25a3', '\u25eb'][index]}
					</span>
					<span className="metric-label">{text[key === 'programs' ? 'programCount' : key]}</span>
					<strong className="metric-value">{formatNumber(value)}</strong>
					{key === 'students' && <small className="metric-detail">{text.studentStatusScope}</small>}
					<span className="metric-accent" />
				</article>
			))}
		</div>
	);
}

function UnitChart({ rows, pageType, language }) {
	const text = translations[language] || translations.id;
	const metricKey = pageType === 'program' ? null : 'students';
	const sortedRows = useMemo(() => [...rows].sort((a, b) => Number(b[metricKey || 'students']) - Number(a[metricKey || 'students'])), [rows, metricKey]);
	const maxValue = Math.max(1, ...sortedRows.flatMap((row) => unitMetricKeys.map((key) => Number(row[key] || 0))));
	const chartDetails = sortedRows.map((row) => `${row.name}: ${unitMetricKeys.map((key) => `${text[key]} ${formatNumber(row[key])}`).join(' · ')}`).join('\n');

	return (
		<section className="chart-panel reveal" data-reveal data-chart-tooltip={chartDetails || text.noData} aria-description={text.unitCopy}>
			<div className="panel-heading">
				<div>
					<span className="section-kicker">PERBANDINGAN</span>
					<h2>{text.unitChart}</h2>
					<p>{text.unitCopy}</p>
				</div>
				<span className="panel-icon" aria-hidden="true">&#9672;</span>
			</div>
			<div className="chart-legend">
				{unitMetricKeys.map((key, index) => (
					<span key={key}>
						<i style={{ '--legend-color': palette[index] }} />
						{text[key]}
					</span>
				))}
			</div>
			{sortedRows.length ? (
				<div className="unit-chart-list" role="region" aria-label={text.unitChart} tabIndex="0">
					{sortedRows.map((row) => (
						<a className="unit-chart-row reveal" data-reveal href={row.url} key={row.id}>
							<span className="unit-chart-name">
								<strong>{row.name}</strong>
								<small>{row.faculty || (row.programCount !== undefined ? `${formatNumber(row.programCount)} ${text.programCount.toLowerCase()}` : '')}</small>
							</span>
							<div className="unit-chart-bars">
								{unitMetricKeys.map((key, index) => (
									<span className="unit-chart-track" key={key} title={`${row.name} · ${text[key]}: ${formatNumber(row[key])}`} aria-label={`${row.name} · ${text[key]}: ${formatNumber(row[key])}`}>
										<i style={{ width: `${Math.max(row[key] ? 1.5 : 0, Number(row[key] || 0) / maxValue * 100)}%`, backgroundColor: palette[index] }} />
									</span>
								))}
							</div>
							<strong className="unit-chart-value">{formatNumber(row.students)}</strong>
							<span className="unit-chart-open" aria-hidden="true">&#8599;</span>
						</a>
					))}
				</div>
			) : (
				<p className="empty-state">{text.noData}</p>
			)}
		</section>
	);
}

function ModuleChart({ metrics, language }) {
	const text = translations[language] || translations.id;
	const maxValue = Math.max(1, ...metrics.map(({ value }) => Number(value || 0)));
	const chartDetails = metrics.map(({ key, value }) => `${text[key === 'programs' ? 'programCount' : key]}: ${formatNumber(value)}`).join('\n');

	return (
		<section className="chart-panel reveal" data-reveal data-chart-tooltip={chartDetails || text.noData} aria-description={text.moduleCopy}>
			<div className="panel-heading">
				<div>
					<span className="section-kicker">LAYANAN SIAKAD</span>
					<h2>{text.moduleChart}</h2>
					<p>{text.moduleCopy}</p>
				</div>
				<span className="panel-icon" aria-hidden="true">&#9638;</span>
			</div>
			<div className="module-chart">
				{metrics.map(({ key, value }, index) => (
					<div className="module-row" key={key}>
						<div className="module-label">
							<span>{text[key]}</span>
							<strong>{formatNumber(value)}</strong>
						</div>
						<div className="module-track" title={`${text[key]}: ${formatNumber(value)} ${text.records}`} aria-label={`${text[key]}: ${formatNumber(value)} ${text.records}`}>
							<i style={{ width: `${Math.max(value ? 1.5 : 0, Number(value || 0) / maxValue * 100)}%`, backgroundColor: palette[(index + 2) % palette.length] }} />
						</div>
					</div>
				))}
			</div>
		</section>
	);
}

function StatusChart({ rows, title, language, labels = {} }) {
	const text = translations[language] || translations.id;
	const maxValue = Math.max(1, ...rows.map((row) => row.value));
	const chartDetails = rows.map((row) => {
		const translated = labels[row.label];
		const label = translated?.[language] || translated?.id || `${text.status} ${row.label}`;
		return `${label}: ${formatNumber(row.value)}`;
	}).join('\n');

	return (
		<section className="chart-panel status-panel reveal" data-reveal data-chart-tooltip={chartDetails || text.noData} aria-description={text.statusChartTooltip}>
			<div className="panel-heading">
				<div>
					<span className="section-kicker">{text.status}</span>
					<h2>{title}</h2>
				</div>
				<span className="panel-icon" aria-hidden="true">&#9676;</span>
			</div>
			{rows.length ? (
				<div className="status-chart">
					{rows.map((row, index) => {
						const translated = labels[row.label];
						const label = translated?.[language] || translated?.id || `${text.status} ${row.label}`;
						return (
							<div className="status-row" key={row.label}>
								<div className="status-label">
									<span>{label}</span>
									<strong>{formatNumber(row.value)}</strong>
								</div>
								<div className="status-track" title={`${label}: ${formatNumber(row.value)} ${text.records}`} aria-label={`${label}: ${formatNumber(row.value)} ${text.records}`}>
									<i style={{ width: `${Math.max(row.value ? 2 : 0, row.value / maxValue * 100)}%`, backgroundColor: palette[(index + 3) % palette.length] }} />
								</div>
							</div>
						);
					})}
				</div>
			) : (
				<p className="empty-state">{text.noData}</p>
			)}
		</section>
	);
}

function DtpsDistribution({ rows, language, onOpen }) {
	const text = translations[language] || translations.id;
	const sortedRows = [...rows].sort((a, b) => Number(b.dtps || 0) - Number(a.dtps || 0) || a.name.localeCompare(b.name));
	const chartDetails = sortedRows.map((row) => `${row.name}: ${text.dtpsTotal} ${formatNumber(row.dtps)} · ${text.dtpsHomebase} ${formatNumber(row.dtpsHomebase)} · ${text.dtpsNonHomebase} ${formatNumber(row.dtpsNonHomebase)}`).join('\n');

	return (
		<section className="chart-panel dtps-distribution reveal" data-reveal aria-labelledby="dtps-heading" data-chart-tooltip={chartDetails || text.noData} aria-description={text.dtpsCopy}>
			<div className="panel-heading">
				<div>
					<span className="section-kicker">{text.dtps}</span>
					<h2 id="dtps-heading">{text.dtpsTitle}</h2>
					<p>{text.dtpsCopy}</p>
				</div>
				<span className="panel-icon" aria-hidden="true">&#9817;</span>
			</div>
			{sortedRows.length ? (
				<div className="dtps-grid">
					{sortedRows.map((row) => (
						<div className="dtps-card" key={row.id}>
							<div>
								<strong title={row.name}>{row.name}</strong>
								<div style={{ fontSize: '11px', color: 'var(--muted)', marginTop: '2px' }}>{row.faculty}</div>
							</div>
							<div className="dtps-counts">
								<button type="button" className="dtps-pill dtps-pill-total" onClick={(e) => onOpen(row, 'all', e)}>
									{text.dtpsTotal}: {formatNumber(row.dtps)}
								</button>
								<button type="button" className="dtps-pill dtps-pill-home" onClick={(e) => onOpen(row, 'homebase', e)}>
									{text.dtpsHomebase}: {formatNumber(row.dtpsHomebase)}
								</button>
								<button type="button" className="dtps-pill dtps-pill-non" onClick={(e) => onOpen(row, 'non_homebase', e)}>
									{text.dtpsNonHomebase}: {formatNumber(row.dtpsNonHomebase)}
								</button>
							</div>
						</div>
					))}
				</div>
			) : (
				<p className="empty-state">{text.noData}</p>
			)}
		</section>
	);
}

function DtpsDetailsDialog({ selection, details, loading, error, language, onClose }) {
	if (!selection) return null;
	const text = translations[language] || translations.id;
	const category = selection.category === 'homebase' ? text.dtpsHomebase
		: selection.category === 'non_homebase' ? text.dtpsNonHomebase : text.dtpsAll;

	return (
		<div className="dtps-dialog-backdrop" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
			<section className="dtps-dialog" role="dialog" aria-modal="true" aria-labelledby="dtps-details-title">
				<header className="dtps-dialog-header">
					<div>
						<h3>{selection.name}</h3>
						<p>{text.dtpsDetails} · {category}</p>
					</div>
					<button type="button" onClick={onClose} aria-label={text.closeDtps} className="hdr-search-close">×</button>
				</header>
				<div className="dtps-dialog-body">
					{loading ? (
						<p style={{ textAlign: 'center', padding: '20px', color: 'var(--muted)' }} role="status">{text.dtpsLoading}</p>
					) : error ? (
						<p style={{ color: '#b91c1c', padding: '16px' }} role="alert">{error}</p>
					) : !details?.lecturers?.length ? (
						<p style={{ textAlign: 'center', padding: '20px', color: 'var(--muted)' }}>{text.dtpsEmpty}</p>
					) : (
						<table className="dtps-dialog-table">
							<thead>
								<tr>
									<th>{text.dtpsName}</th>
									<th>{text.dtpsCourses}</th>
									<th>{text.dtpsLatest}</th>
								</tr>
							</thead>
							<tbody>
								{details.lecturers.map((lecturer) => (
									<tr key={lecturer.id}>
										<td><strong>{lecturer.name || '—'}</strong><div style={{ fontSize: '11px', color: 'var(--muted)' }}>NIDN: {lecturer.nidn || '—'}</div></td>
										<td>
											{lecturer.courses?.length ? (
												<ul style={{ paddingLeft: '16px' }}>
													{lecturer.courses.map((c, i) => (
														<li key={i}>{c.name} ({c.academic_year || '-'})</li>
													))}
												</ul>
											) : '—'}
										</td>
										<td>{lecturer.latest_academic_year || '—'}</td>
									</tr>
								))}
							</tbody>
						</table>
					)}
				</div>
				<footer className="dtps-dialog-footer">
					<button type="button" className="btn btn-line" onClick={onClose}>Tutup</button>
				</footer>
			</section>
		</div>
	);
}

function StudentDistributionMap({ rows, studentTotal, language }) {
	const text = translations[language] || translations.id;
	const mappedTotal = rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);
	const mapSummary = text.studentMapCopy
		.replace('{mapped}', formatNumber(mappedTotal))
		.replace('{total}', formatNumber(studentTotal))
		.replace('{missing}', formatNumber(Math.max(0, studentTotal - mappedTotal)));

	const chartDetails = rows.map((row) => {
		const units = (row.units || []).map((unit) => `${unit.label}: ${formatNumber(unit.total)}`).join(' · ');
		return `${row.province}: ${formatNumber(row.total)} ${text.mapStudents}${units ? ` · ${units}` : ''}`;
	}).join('\n');

	const mapElement = useRef(null);
	const mapInstance = useRef(null);
	const markerLayer = useRef(null);

	const formatBreakdown = (row) => {
		const source = row.units ?? row.faculties ?? [];
		const entries = Array.isArray(source)
			? source
			: Object.entries(source).map(([key, value]) => (value && typeof value === 'object'
				? { ...value, key, label: value.label ?? value.name ?? key, total: value.total ?? value.count }
				: { key, label: key, total: value }));

		return entries.flatMap((entry) => {
			if (Array.isArray(entry)) {
				const [label, total] = entry;
				entry = { label, total };
			} else if (typeof entry === 'string') {
				return [];
			}
			const candidate = entry?.label ?? entry?.name ?? entry?.key;
			const label = typeof candidate === 'string' ? candidate.trim() : '';
			const total = Number(entry?.total ?? entry?.count);
			if (!label || !Number.isFinite(total) || total <= 0) return [];
			return [{ label, total }];
		});
	};

	useEffect(() => {
		if (!mapElement.current) return;
		const map = L.map(mapElement.current, { scrollWheelZoom: false }).setView([1.5709922, 127.8087685], 4);
		mapInstance.current = map;
		markerLayer.current = L.layerGroup().addTo(map);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			maxZoom: 18,
		}).addTo(map);
		window.requestAnimationFrame(() => map.invalidateSize());
		return () => {
			map.remove();
			mapInstance.current = null;
			markerLayer.current = null;
		};
	}, []);

	useEffect(() => {
		const map = mapInstance.current;
		const layer = markerLayer.current;
		if (!map || !layer) return;
		layer.clearLayers();
		const validRows = rows.filter((row) => Number.isFinite(Number(row.latitude))
			&& Number.isFinite(Number(row.longitude))
			&& Number(row.latitude) !== 0
			&& Number(row.longitude) !== 0);
		const maxTotal = Math.max(1, ...validRows.map((row) => Number(row.total) || 0));
		const bounds = [];

		validRows.forEach((row) => {
			const latitude = Number(row.latitude);
			const longitude = Number(row.longitude);
			const total = Number(row.total) || 0;
			const marker = L.circleMarker([latitude, longitude], {
				radius: Math.min(24, Math.max(7, 8 + Math.sqrt(total / maxTotal) * 16)),
				color: '#fff',
				weight: 2,
				fillColor: '#c9962e',
				fillOpacity: 0.85,
			}).addTo(layer);

			const popup = document.createElement('div');
			const heading = document.createElement('strong');
			heading.textContent = row.province;
			const count = document.createElement('p');
			count.textContent = `${formatNumber(total)} ${text.mapStudents}`;
			popup.append(heading, count);
			const breakdown = formatBreakdown(row);
			breakdown.forEach(({ label, total: unitTotal }) => {
				const item = document.createElement('div');
				item.textContent = `${label}: ${formatNumber(unitTotal)}`;
				popup.append(item);
			});

			const tooltip = document.createElement('div');
			const tooltipHeading = document.createElement('strong');
			tooltipHeading.textContent = row.province;
			const tooltipCount = document.createElement('div');
			tooltipCount.textContent = `${formatNumber(total)} ${text.mapStudents}`;
			tooltip.append(tooltipHeading, tooltipCount);
			breakdown.forEach(({ label, total: unitTotal }) => {
				const item = document.createElement('div');
				item.textContent = `${label}: ${formatNumber(unitTotal)}`;
				tooltip.append(item);
			});

			marker.bindTooltip(tooltip, { direction: 'top', offset: [0, -8], opacity: 1 });
			marker.bindPopup(popup);
			bounds.push([latitude, longitude]);
		});

		if (bounds.length > 1) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 7 });
		else if (bounds.length === 1) map.setView(bounds[0], 5);
		else map.setView([1.5709922, 127.8087685], 4);
		window.requestAnimationFrame(() => map.invalidateSize());
	}, [rows, text.mapStudents]);

	return (
		<section className="chart-panel student-map-panel reveal" data-reveal aria-labelledby="student-map-heading" data-chart-tooltip={chartDetails || text.mapNoData} aria-description={mapSummary}>
			<div className="panel-heading">
				<div>
					<span className="section-kicker">DOMISILI</span>
					<h2 id="student-map-heading">{text.studentMap}</h2>
					<p>{mapSummary}</p>
				</div>
				<span className="panel-icon" aria-hidden="true">&#9678;</span>
			</div>
			<div ref={mapElement} className="student-distribution-map" role="application" aria-label={text.studentMap} />
			{rows.length === 0 && <p className="empty-state">{text.mapNoData}</p>}
		</section>
	);
}

function StudentIntakeChart({ intake, language }) {
	const text = translations[language] || translations.id;
	const [activePoint, setActivePoint] = useState(null);
	const width = 760;
	const height = 280;
	const padding = { top: 24, right: 24, bottom: 42, left: 68 };
	const plotWidth = width - padding.left - padding.right;
	const plotHeight = height - padding.top - padding.bottom;
	const series = intake.series || [];
	const values = series.flatMap((line) => line.values.map(Number));
	const maxValue = Math.max(1, ...values);
	const upperBound = Math.ceil(maxValue / 5) * 5 || 1;
	const lines = series.map((line, lineIndex) => {
		const points = line.values.map((value, index) => ({
			x: padding.left + (intake.years.length === 1 ? plotWidth / 2 : index / (intake.years.length - 1) * plotWidth),
			y: padding.top + (1 - Number(value) / upperBound) * plotHeight,
			value: Number(value),
			year: intake.years[index],
			label: line.id === 'scope' ? text.intakeTotal : line.label,
			color: palette[lineIndex % palette.length],
			key: `${line.id}-${intake.years[index]}`,
		}));
		return { ...line, color: palette[lineIndex % palette.length], points };
	});
	const chartDetails = lines.map((line) => `${line.id === 'scope' ? text.intakeTotal : line.label}: ${line.points.map((point) => `${point.year} ${formatNumber(point.value)}`).join(' · ')}`).join('\n');
	const ticks = [0, upperBound / 4, upperBound / 2, upperBound * 3 / 4, upperBound];

	return (
		<section className="chart-panel student-intake-panel reveal" data-reveal aria-labelledby="student-intake-heading" data-chart-tooltip={chartDetails || text.noData} aria-description={text.intakeCopy}>
			<div className="panel-heading">
				<div>
					<span className="section-kicker">ANGKATAN</span>
					<h2 id="student-intake-heading">{text.intakeChart}</h2>
					<p>{text.intakeCopy}</p>
				</div>
				<span className="panel-icon" aria-hidden="true">&#9672;</span>
			</div>
			<div className="chart-legend">
				{lines.map((line) => (
					<span key={line.id}><i style={{ '--legend-color': line.color }} />{line.id === 'scope' ? text.intakeTotal : line.label}</span>
				))}
			</div>
			<div className="visitor-chart-scroll">
				<div className="visitor-chart-plot">
					<svg className="visitor-chart-svg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label={text.intakeChart}>
						{ticks.map((tick) => {
							const y = padding.top + (1 - tick / upperBound) * plotHeight;
							return (
								<g key={tick}>
									<line x1={padding.left} y1={y} x2={width - padding.right} y2={y} className="visitor-chart-grid" />
									<text x={padding.left - 10} y={y + 4} textAnchor="end" className="visitor-chart-axis-label">{formatNumber(tick)}</text>
								</g>
							);
						})}
						{lines.map((line) => (
							<g key={line.id}>
								<path d={line.points.map((point, index) => `${index ? 'L' : 'M'} ${point.x} ${point.y}`).join(' ')} fill="none" stroke={line.color} strokeWidth="2.5" />
								{line.points.map((point) => (
									<circle
										key={point.key}
										cx={point.x}
										cy={point.y}
										r={activePoint?.key === point.key ? 6 : 4}
										fill={line.color}
										tabIndex="0"
										role="img"
										aria-label={`${point.label}, ${point.year}: ${formatNumber(point.value)} ${text.intakeCount}`}
										onMouseEnter={() => setActivePoint(point)}
										onMouseLeave={() => setActivePoint(null)}
									/>
								))}
							</g>
						))}
						{intake.years.map((year, index) => (
							<text key={year} x={padding.left + (intake.years.length === 1 ? plotWidth / 2 : index / (intake.years.length - 1) * plotWidth)} y={height - 14} textAnchor="middle" className="visitor-chart-axis-label">{year}</text>
						))}
					</svg>
					{activePoint && (
						<div style={{ position: 'absolute', left: `${activePoint.x}px`, top: `${activePoint.y - 40}px`, background: 'rgba(7, 30, 56, .9)', color: '#fff', padding: '6px 12px', borderRadius: '4px', fontSize: '11px', pointerEvents: 'none' }}>
							<strong>{activePoint.label}</strong>: {activePoint.year} · {formatNumber(activePoint.value)} {text.intakeCount}
						</div>
					)}
				</div>
			</div>
			<p style={{ marginTop: '12px', fontSize: '11px', color: 'var(--muted)' }}>{text.intakeTooltipHint}</p>
		</section>
	);
}

function VisitorTrendChart({ visitors, language }) {
	const [period, setPeriod] = useState('daily');
	const [activeIndex, setActiveIndex] = useState(null);
	const text = translations[language] || translations.id;
	const rows = visitors.series?.[period] || [];
	const width = Math.max(700, rows.length * 92);
	const height = 280;
	const padding = { top: 24, right: 24, bottom: 42, left: 48 };
	const plotWidth = width - padding.left - padding.right;
	const plotHeight = height - padding.top - padding.bottom;
	const maxValue = Math.max(1, ...rows.map((row) => Number(row.value || 0)));
	const step = maxValue <= 5 ? 1 : Math.pow(10, Math.floor(Math.log10(maxValue)));
	const upperBound = Math.max(step, Math.ceil(maxValue / step) * step);
	const ticks = Array.from({ length: 5 }, (_, index) => Math.round(upperBound * index / 4));

	const points = rows.map((row, index) => ({
		...row,
		x: padding.left + (rows.length <= 1 ? plotWidth / 2 : index / (rows.length - 1) * plotWidth),
		y: padding.top + (1 - Number(row.value || 0) / upperBound) * plotHeight,
	}));

	const linePath = points.map((point, index) => {
		if (index === 0) return `M ${point.x} ${point.y}`;
		const previous = points[index - 1];
		const control = (point.x - previous.x) / 2;
		return `C ${previous.x + control} ${previous.y}, ${point.x - control} ${point.y}, ${point.x} ${point.y}`;
	}).join(' ');

	const baseline = padding.top + plotHeight;
	const areaPath = points.length ? `${linePath} L ${points[points.length - 1].x} ${baseline} L ${points[0].x} ${baseline} Z` : '';
	const activePoint = activeIndex === null ? null : points[activeIndex];
	const periodOptions = [
		['daily', text.visitorDaily],
		['monthly', text.visitorMonthly],
		['yearly', text.visitorYearly],
	];

	const chartDetails = rows.map((row) => `${formatVisitorLabel(row.label, period, language, true)}: ${formatNumber(row.value)} ${text.uniqueVisitors}`).join('\n');

	return (
		<section className="visitor-trend reveal" data-reveal aria-labelledby="visitor-trend-heading" data-chart-tooltip={chartDetails || text.noData} aria-description={text.visitorTrendCopy}>
			<div className="visitor-trend-heading">
				<div>
					<span className="section-kicker">ANALITIK PENGUNJUNG</span>
					<h3 id="visitor-trend-heading">{text.visitorTrend}</h3>
					<p>{text.visitorTrendCopy}</p>
				</div>
				<div className="visitor-periods" role="group" aria-label={text.visitorTrend}>
					{periodOptions.map(([key, label]) => (
						<button
							className={period === key ? 'is-active' : ''}
							type="button"
							aria-pressed={period === key}
							key={key}
							onClick={() => { setPeriod(key); setActiveIndex(null); }}
						>
							{label}
						</button>
					))}
				</div>
			</div>
			<div className="visitor-chart-scroll">
				<div className="visitor-chart-plot">
					<svg className="visitor-chart-svg" viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`${text.visitorTrend} · ${periodOptions.find(([key]) => key === period)?.[1]}`}>
						<defs>
							<linearGradient id="visitor-area-gradient" x1="0" x2="0" y1="0" y2="1">
								<stop offset="0%" stopColor="#c9962e" stopOpacity=".28" />
								<stop offset="100%" stopColor="#c9962e" stopOpacity=".02" />
							</linearGradient>
						</defs>
						{ticks.map((tick) => {
							const y = padding.top + (1 - tick / upperBound) * plotHeight;
							return (
								<g key={tick}>
									<line x1={padding.left} y1={y} x2={width - padding.right} y2={y} className="visitor-chart-grid" />
									<text x={padding.left - 10} y={y + 4} textAnchor="end" className="visitor-chart-axis-label">{formatNumber(tick)}</text>
								</g>
							);
						})}
						{areaPath && <path d={areaPath} fill="url(#visitor-area-gradient)" />}
						{linePath && <path d={linePath} fill="none" stroke="#c9962e" strokeWidth="2.5" />}
						{points.map((point, index) => (
							<g key={point.label}>
								<circle
									cx={point.x}
									cy={point.y}
									r={activeIndex === index ? 6 : 4}
									fill="#0A2E52"
									stroke="#c9962e"
									strokeWidth="2"
									tabIndex="0"
									role="img"
									aria-label={`${formatVisitorLabel(point.label, period, language, true)}: ${formatNumber(point.value)} ${text.uniqueVisitors}`}
									onMouseEnter={() => setActiveIndex(index)}
									onMouseLeave={() => setActiveIndex(null)}
								/>
								<text x={point.x} y={height - 14} textAnchor="middle" className="visitor-chart-axis-label">{formatVisitorLabel(point.label, period, language)}</text>
							</g>
						))}
					</svg>
					{activePoint && (
						<div style={{ position: 'absolute', left: `${activePoint.x}px`, top: `${activePoint.y - 42}px`, background: 'rgba(7, 30, 56, .92)', color: '#fff', padding: '6px 12px', borderRadius: '4px', fontSize: '11px', pointerEvents: 'none' }}>
							<strong>{formatVisitorLabel(activePoint.label, period, language, true)}</strong>
							<div>{formatNumber(activePoint.value)} {text.uniqueVisitors}</div>
						</div>
					)}
				</div>
			</div>
			<p style={{ marginTop: '12px', fontSize: '11px', color: 'var(--muted)' }}>
				Browser yang sama dihitung satu kali pada tiap periode. Penghapusan cookie atau penggunaan perangkat lain dapat memengaruhi hitungan.
			</p>
		</section>
	);
}

function DashboardContent({ data, pageType, language }) {
	const text = translations[language] || translations.id;
	const dtpsTriggerRef = useRef(null);
	const [dtpsSelection, setDtpsSelection] = useState(null);
	const [dtpsDetails, setDtpsDetails] = useState(null);
	const [dtpsLoading, setDtpsLoading] = useState(false);
	const [dtpsError, setDtpsError] = useState('');

	useEffect(() => {
		const elements = [...document.querySelectorAll('[data-reveal]')];
		if (!('IntersectionObserver' in window)) {
			elements.forEach((element) => element.classList.add('is-visible'));
			return undefined;
		}
		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => entry.target.classList.toggle('is-visible', entry.isIntersecting));
		}, { threshold: 0.12, rootMargin: '0px 0px -28px 0px' });
		elements.forEach((element, index) => {
			element.style.setProperty('--reveal-delay', `${Math.min(index % 5, 4) * 55}ms`);
			observer.observe(element);
		});
		return () => observer.disconnect();
	}, [data, pageType]);

	useEffect(() => {
		if (!dtpsSelection) return undefined;
		const abortController = new AbortController();
		const url = new URL(dtpsSelection.url, window.location.origin);
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
	}, [dtpsSelection]);

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

	if (!data) {
		return (
			<main id="dashboard" className="landing-main">
				<div className="dashboard-loading" role="status" aria-live="polite">
					<span className="dashboard-loading-flash" aria-hidden="true"><i /><i /><i /><i /><i /></span>
					<strong>{text.loadingDashboard}</strong>
				</div>
			</main>
		);
	}

	const isUnitPage = ['faculty', 'program'].includes(pageType);
	const unitTitle = pageType === 'faculty' ? text.faculty : text.program;

	return (
		<main id="dashboard" className="landing-main">
			<section className="overview-head reveal" data-reveal>
				<div>
					<span className="section-kicker">{text.snapshot}</span>
					<h2>{data.scopeName || text.university}</h2>
				</div>
				{data.parentUrl && <a className="back-link" href={data.parentUrl}>&larr; {text.back} · {data.parentName}</a>}
			</section>

			<MetricCards data={data} language={language} />

			<div className="charts-grid">
				<UnitChart rows={data.unitRows} pageType={pageType} language={language} />
				<ModuleChart metrics={data.metrics} language={language} />
				<StatusChart rows={data.proposalStatuses} title={text.proposalStatus} labels={proposalLabels} language={language} />
				<StatusChart rows={data.thesisStatuses} title={text.thesisStatus} labels={thesisLabels} language={language} />
			</div>

			<DtpsDistribution
				rows={data.programRows || []}
				language={language}
				onOpen={(row, category, event) => {
					dtpsTriggerRef.current = event.currentTarget;
					setDtpsSelection({ url: row.dtpsDetailsUrl, name: row.name, category });
				}}
			/>

			<StudentDistributionMap rows={data.studentDistribution || []} studentTotal={Number(data.studentTotal) || 0} language={language} />

			{data.studentIntake?.available && <StudentIntakeChart intake={data.studentIntake} language={language} />}

			{isUnitPage && <p className="scope-caption">{unitTitle} · {data.scopeName}</p>}

			<DtpsDetailsDialog
				selection={dtpsSelection}
				details={dtpsDetails}
				loading={dtpsLoading}
				error={dtpsError}
				language={language}
				onClose={closeDtpsDetails}
			/>

			<section className="visitor-section" aria-labelledby="visitor-heading">
				<div className="visitor-section-heading reveal" data-reveal>
					<span className="visitor-mark" aria-hidden="true">&#9673;</span>
					<div>
						<span className="section-kicker">AKSES PUBLIK</span>
						<h2 id="visitor-heading">{text.visitors}</h2>
						<p>{text.visitorDescription}</p>
					</div>
				</div>
				<div className="visitor-stats-grid">
					{[
						['today', text.visitorsToday, text.visitorTodayDetail, '\u265f', '#12467a'],
						['month', text.visitorsMonth, text.visitorMonthDetail, '\u25a6', '#c9962e'],
						['year', text.visitorsYear, text.visitorYearDetail, '\u25a5', '#269778'],
						['total', text.visitorsTotal, text.visitorTotalDetail, '\u25ce', '#9b55ca'],
					].map(([key, label, detail, icon, color]) => (
						<article className="visitor-stat-card reveal" data-reveal key={key} style={{ '--visitor-color': color }}>
							<span className="visitor-stat-icon" aria-hidden="true">{icon}</span>
							<span className="visitor-stat-label">{label}</span>
							<strong>{formatNumber(data.visitors[key])}</strong>
							<small>{detail}</small>
						</article>
					))}
				</div>
				<VisitorTrendChart visitors={data.visitors} language={language} />
			</section>
		</main>
	);
}

/* ============================================================
   MAIN PUBLIC LANDING ROOT COMPONENT
   ============================================================ */
function PublicLanding({ root, data }) {
	const pageType = root.dataset.pageType;
	const initialView = useMemo(() => {
		if (pageType === 'payment') return 'payment';
		if (['privacy', 'terms', 'accessibility', 'about', 'structure', 'admissions', 'academic', 'accreditation', 'contact'].includes(pageType)) {
			return pageType;
		}
		if (typeof window !== 'undefined') {
			const hash = window.location.hash.replace('#', '').toLowerCase();
			if (hash === 'payment-information' || hash === 'payment') return 'payment';
			if (hash === 'detail') return 'detail';
			if (['privacy', 'terms', 'accessibility', 'about', 'structure', 'admissions', 'academic', 'accreditation', 'contact'].includes(hash)) {
				return hash;
			}
			const path = window.location.pathname.replace(/^\/|\/$/g, '').toLowerCase();
			if (path === 'payment-information' || path === 'payment') return 'payment';
			if (['privacy', 'terms', 'accessibility', 'about', 'structure', 'admissions', 'academic', 'accreditation', 'contact'].includes(path)) {
				return path;
			}
		}
		return pageType || 'home';
	}, [pageType]);

	const [currentView, setCurrentView] = useState(initialView);

	const navigateView = useCallback((view) => {
		setCurrentView(view);
		if (view === 'payment') {
			window.history.pushState({ view }, '', '/payment-information');
		} else if (view === 'detail') {
			window.history.pushState({ view: 'detail' }, '', '/academic#detail');
		} else if (['privacy', 'terms', 'accessibility', 'about', 'structure', 'admissions', 'academic', 'accreditation', 'contact'].includes(view)) {
			window.history.pushState({ view }, '', `/${view}`);
		} else if (view === 'home') {
			window.history.pushState({ view: 'home' }, '', data.homeUrl || '/');
		}
		window.scrollTo({ top: 0, behavior: 'instant' });
	}, [data.homeUrl]);

	useEffect(() => {
		const handlePopState = () => {
			const path = window.location.pathname.replace(/^\/|\/$/g, '').toLowerCase();
			const hash = window.location.hash.replace('#', '').toLowerCase();
			if (hash === 'payment-information' || hash === 'payment' || path === 'payment-information' || path === 'payment') {
				setCurrentView('payment');
				return;
			}
			if (hash === 'detail') {
				setCurrentView('detail');
				return;
			}
			if (['privacy', 'terms', 'accessibility', 'about', 'structure', 'admissions', 'academic', 'accreditation', 'contact'].includes(hash)) {
				setCurrentView(hash);
			} else if (['privacy', 'terms', 'accessibility', 'about', 'structure', 'admissions', 'academic', 'accreditation', 'contact'].includes(path)) {
				setCurrentView(path);
			} else {
				setCurrentView(pageType || 'home');
			}
		};
		window.addEventListener('popstate', handlePopState);
		window.addEventListener('hashchange', handlePopState);
		return () => {
			window.removeEventListener('popstate', handlePopState);
			window.removeEventListener('hashchange', handlePopState);
		};
	}, [pageType]);

	const [dashboard, setDashboard] = useState(null);
	const [dashboardError, setDashboardError] = useState('');
	const isAcademicDashboard = ['faculties', 'faculty', 'programs', 'program'].includes(currentView);

	useEffect(() => {
		if (!isAcademicDashboard) return;
		const abortController = new AbortController();
		fetch(data.dataUrl, { headers: { Accept: 'application/json' }, signal: abortController.signal })
			.then(async (response) => {
				const payload = await response.json();
				if (!response.ok) throw new Error(payload.message || 'Ringkasan akademik gagal dimuat.');
				startTransition(() => setDashboard(payload));
			})
			.catch((error) => {
				if (error.name !== 'AbortError') setDashboardError(error.message);
			});
		return () => abortController.abort();
	}, [data.dataUrl, isAcademicDashboard]);

	const pageData = dashboard ? { ...data, ...dashboard } : null;

	return (
		<div className="public-page">
			<UtilityBar onNavigate={navigateView} />
			<Header currentView={currentView} onNavigate={navigateView} />

			{currentView === 'home' && <HomeView onNavigate={navigateView} />}
			{currentView === 'detail' && <AcademicDetailView onNavigate={navigateView} />}
			{currentView === 'academic' && <AcademicView onNavigate={navigateView} />}
			{currentView === 'about' && <AboutView onNavigate={navigateView} />}
			{currentView === 'structure' && <StructureView onNavigate={navigateView} />}
			{currentView === 'admissions' && <AdmissionsView onNavigate={navigateView} />}
			{currentView === 'accreditation' && <AccreditationView onNavigate={navigateView} />}
			{currentView === 'payment' && <PaymentView onNavigate={navigateView} />}
			{currentView === 'contact' && <ContactView onNavigate={navigateView} />}
			{currentView === 'privacy' && <PrivacyView onNavigate={navigateView} />}
			{currentView === 'terms' && <TermsView onNavigate={navigateView} />}
			{currentView === 'accessibility' && <AccessibilityView onNavigate={navigateView} />}

			{isAcademicDashboard && (
				<>
					<div className="inner-hero">
						<div
							className="inner-hero-backdrop"
							style={{ '--inner-hero-image': `url("${data.heroImages?.[0] || '/img/Perayaan%20Wisuda%20di%20Bawah%20Menara%20Kota.png'}")` }}
						/>
						<div className="inner-hero-copy">
							<span>SIAKAD</span>
							<h1>{currentView === 'faculty' ? 'Fakultas' : currentView === 'program' ? 'Program Studi' : data.pageTitle}</h1>
							<p>{data.scopeName}</p>
						</div>
					</div>
					{dashboardError ? (
						<main className="landing-main">
							<p className="empty-state" role="alert">{dashboardError}</p>
						</main>
					) : (
						<DashboardContent data={pageData} pageType={currentView} language="id" />
					)}
				</>
			)}

			<Footer onNavigate={navigateView} />
		</div>
	);
}

const root = document.getElementById('public-landing');
const dataElement = document.getElementById('public-landing-data');
if (root && dataElement) {
	createRoot(root).render(<PublicLanding root={root} data={JSON.parse(dataElement.textContent)} />);
}
