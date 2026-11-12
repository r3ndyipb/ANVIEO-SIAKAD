import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const dataRoot = document.getElementById('dashboard-visualization-data');

if (dataRoot) {
	const isEnglish = window.simakLocale === 'en';
	const numberLocale = isEnglish ? 'en-US' : 'id-ID';
	const mapCopy = isEnglish
		? {
			students: 'students',
			total: 'Total',
			noFacultyDetails: 'No faculty breakdown available',
			emptyMap: 'The map will appear when active students have residence data.',
		}
		: {
			students: 'mahasiswa',
			total: 'Total',
			noFacultyDetails: 'Belum ada rincian fakultas',
			emptyMap: 'Peta akan tampil setelah data mahasiswa aktif dengan wilayah asal tersedia.',
		};
	const years = JSON.parse(dataRoot.dataset.years || '[]').map(Number);
	const series = JSON.parse(dataRoot.dataset.series || '[]');
	const distribution = JSON.parse(dataRoot.dataset.distribution || '[]');
	const chartRoot = document.getElementById('student-intake-chart');
	const mapRoot = document.getElementById('student-distribution-map');
	const colors = [
		'#2563EB', // Royal Blue
		'#C9962E', // AUI Gold
		'#059669', // Emerald
		'#7C3AED', // Violet
		'#EA580C', // Amber Orange
		'#0D9488', // Teal
		'#DB2777', // Rose Pink
		'#4F46E5', // Indigo
		'#D97706', // Warm Amber
		'#0284C7', // Sky Blue
		'#16A34A', // Green
		'#9333EA', // Purple
	];
	const svgNamespace = 'http://www.w3.org/2000/svg';

	const svgElement = (name, attributes = {}) => {
		const element = document.createElementNS(svgNamespace, name);
		Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
		return element;
	};

	let seriesToggleMap = [];
	let focusedSeriesId = null;

	const drawChart = () => {
		if (!chartRoot) return;

		const activeSeries = series.filter((item) => item.values && item.values.some((v) => Number(v) > 0))
			.sort((a, b) => {
				const sumA = (a.values || []).reduce((s, v) => s + (Number(v) || 0), 0);
				const sumB = (b.values || []).reduce((s, v) => s + (Number(v) || 0), 0);
				return sumB - sumA;
			});

		const displaySeries = activeSeries.length > 0 ? activeSeries : series;

		if (!displaySeries.length || displaySeries.every((s) => (s.values || []).every((v) => Number(v) === 0))) {
			chartRoot.innerHTML = `
				<div class="dashboard-chart-empty">
					<svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.45; margin-bottom: 8px;">
						<path d="M3 3v18h18"></path>
						<path d="m19 9-5 5-4-4-3 3"></path>
					</svg>
					<p>${isEnglish ? 'No student intake data recorded in the last six years.' : 'Belum ada data angkatan mahasiswa dalam enam tahun terakhir.'}</p>
				</div>
			`;
			return;
		}

		const containerWidth = chartRoot.clientWidth || 800;
		const width = Math.max(680, containerWidth);
		const height = 370;
		const padding = { top: 32, right: 36, bottom: 44, left: 56 };
		const plotWidth = width - padding.left - padding.right;
		const plotHeight = height - padding.top - padding.bottom;

		const maxDataValue = Math.max(1, ...displaySeries.flatMap((item) => (item.values || [0]).map(Number)));
		let tickStep = 1;
		let axisMaximum = 4;
		if (maxDataValue <= 4) {
			tickStep = 1;
			axisMaximum = 4;
		} else if (maxDataValue <= 10) {
			tickStep = 2;
			axisMaximum = 10;
		} else if (maxDataValue <= 20) {
			tickStep = 5;
			axisMaximum = 20;
		} else if (maxDataValue <= 50) {
			tickStep = 10;
			axisMaximum = 50;
		} else if (maxDataValue <= 100) {
			tickStep = 25;
			axisMaximum = 100;
		} else {
			const rawStep = maxDataValue / 4;
			const pow10 = Math.pow(10, Math.floor(Math.log10(rawStep)));
			const norm = rawStep / pow10;
			if (norm <= 1.5) tickStep = 1 * pow10;
			else if (norm <= 2.5) tickStep = 2 * pow10;
			else if (norm <= 5) tickStep = 5 * pow10;
			else tickStep = 10 * pow10;
			axisMaximum = Math.max(tickStep * 4, Math.ceil(maxDataValue / tickStep) * tickStep);
		}
		const ticksCount = Math.max(1, Math.round(axisMaximum / tickStep));

		const getX = (index) => {
			if (years.length <= 1) return padding.left + plotWidth / 2;
			return padding.left + (index / (years.length - 1)) * plotWidth;
		};

		const getY = (val) => {
			const num = Number(val) || 0;
			const clamped = Math.max(0, Math.min(axisMaximum, num));
			return padding.top + plotHeight - (clamped / axisMaximum) * plotHeight;
		};

		const getCurvedPath = (pts) => {
			if (pts.length === 0) return '';
			if (pts.length === 1) return `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;
			let d = `M ${pts[0].x.toFixed(1)} ${pts[0].y.toFixed(1)}`;
			for (let i = 0; i < pts.length - 1; i++) {
				const p0 = pts[i];
				const p1 = pts[i + 1];
				const dx = (p1.x - p0.x) * 0.42;
				const cp1x = p0.x + dx;
				const cp1y = p0.y;
				const cp2x = p1.x - dx;
				const cp2y = p1.y;
				d += ` C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)}, ${cp2x.toFixed(1)} ${cp2y.toFixed(1)}, ${p1.x.toFixed(1)} ${p1.y.toFixed(1)}`;
			}
			return d;
		};

		const getCurvedAreaPath = (pts, bottomY) => {
			if (pts.length < 2) return '';
			const linePath = getCurvedPath(pts);
			const lastPt = pts[pts.length - 1];
			const firstPt = pts[0];
			return `${linePath} L ${lastPt.x.toFixed(1)} ${bottomY.toFixed(1)} L ${firstPt.x.toFixed(1)} ${bottomY.toFixed(1)} Z`;
		};

		const svg = svgElement('svg', {
			viewBox: `0 0 ${width} ${height}`,
			role: 'group',
			'aria-labelledby': 'student-intake-svg-title student-intake-svg-description',
		});

		const plot = document.createElement('div');
		plot.className = 'dashboard-chart-plot';

		const title = svgElement('title', { id: 'student-intake-svg-title' });
		title.textContent = isEnglish ? 'New student trend by study program' : 'Tren mahasiswa baru per program studi';
		svg.append(title);

		const description = svgElement('desc', { id: 'student-intake-svg-description' });
		description.textContent = isEnglish
			? `Student count by cohort from ${years[0]} to ${years.at(-1)}.`
			: `Jumlah mahasiswa berdasarkan angkatan dari ${years[0]} sampai ${years.at(-1)}.`;
		svg.append(description);

		const defs = svgElement('defs');
		displaySeries.forEach((item, index) => {
			const color = colors[index % colors.length];
			const grad = svgElement('linearGradient', {
				id: `intake-grad-${index}`,
				x1: '0',
				y1: '0',
				x2: '0',
				y2: '1',
			});
			const stop1 = svgElement('stop', { offset: '0%', 'stop-color': color, 'stop-opacity': '0.24' });
			const stop2 = svgElement('stop', { offset: '100%', 'stop-color': color, 'stop-opacity': '0.01' });
			grad.append(stop1, stop2);
			defs.append(grad);
		});
		svg.append(defs);

		const gridGroup = svgElement('g', { class: 'dashboard-chart-grid-group' });
		for (let tick = 0; tick <= ticksCount; tick += 1) {
			const value = axisMaximum - tick * tickStep;
			const y = padding.top + (tick / ticksCount) * plotHeight;
			gridGroup.append(svgElement('line', {
				x1: padding.left,
				y1: y,
				x2: width - padding.right,
				y2: y,
				class: 'dashboard-chart-grid',
			}));
			const label = svgElement('text', {
				x: padding.left - 12,
				y: y + 4,
				'text-anchor': 'end',
				class: 'dashboard-chart-label y-axis-label',
			});
			label.textContent = value.toLocaleString(numberLocale);
			gridGroup.append(label);
		}

		const yearLabels = [];
		years.forEach((year, index) => {
			const x = getX(index);
			gridGroup.append(svgElement('line', {
				x1: x,
				y1: padding.top,
				x2: x,
				y2: padding.top + plotHeight,
				class: 'dashboard-chart-grid-vertical',
			}));
			const label = svgElement('text', {
				x,
				y: height - 14,
				'text-anchor': 'middle',
				class: 'dashboard-chart-label x-axis-label',
			});
			label.textContent = String(year);
			gridGroup.append(label);
			yearLabels.push(label);
		});
		svg.append(gridGroup);

		const cursorLine = svgElement('line', {
			x1: 0,
			y1: padding.top,
			x2: 0,
			y2: padding.top + plotHeight,
			class: 'dashboard-chart-cursor-line',
			style: 'display: none;',
		});
		svg.append(cursorLine);

		const linesGroup = svgElement('g', { 'aria-label': isEnglish ? 'Study program data lines' : 'Garis data per program studi' });
		const tooltip = document.createElement('div');
		tooltip.className = 'dashboard-chart-tooltip';
		tooltip.id = 'student-intake-tooltip';
		tooltip.setAttribute('role', 'tooltip');
		tooltip.hidden = true;

		const highlightSeries = (seriesId) => {
			focusedSeriesId = seriesId;
			linesGroup.querySelectorAll('.dashboard-chart-series-group').forEach((grp) => {
				const isTarget = grp.getAttribute('data-series-id') === seriesId;
				if (isTarget) {
					grp.classList.add('is-focused');
					grp.classList.remove('is-dimmed');
					linesGroup.appendChild(grp);
				} else {
					grp.classList.add('is-dimmed');
					grp.classList.remove('is-focused');
				}
			});
			if (legend) {
				legend.querySelectorAll('.dashboard-chart-legend-btn').forEach((btn) => {
					if (btn.getAttribute('data-series-id') === seriesId) {
						btn.classList.add('is-hovered');
					} else {
						btn.classList.remove('is-hovered');
					}
				});
			}
		};

		const unhighlightSeries = () => {
			focusedSeriesId = null;
			linesGroup.querySelectorAll('.dashboard-chart-series-group').forEach((grp) => {
				grp.classList.remove('is-focused', 'is-dimmed');
			});
			if (legend) {
				legend.querySelectorAll('.dashboard-chart-legend-btn').forEach((btn) => {
					btn.classList.remove('is-hovered');
				});
			}
		};

		const showTooltip = (point, item, color) => {
			highlightSeries(item.id);

			cursorLine.setAttribute('x1', point.x);
			cursorLine.setAttribute('x2', point.x);
			cursorLine.style.display = '';

			yearLabels.forEach((lbl, idx) => {
				if (idx === point.pointIndex) {
					lbl.classList.add('is-active-year');
				} else {
					lbl.classList.remove('is-active-year');
				}
			});

			tooltip.innerHTML = `
				<div class="dashboard-chart-tooltip-header">
					<span class="dashboard-chart-swatch" style="background-color: ${color};"></span>
					<strong>${escapeHtml(item.label)}</strong>
				</div>
				<div class="dashboard-chart-tooltip-body">
					<span class="dashboard-chart-tooltip-year">${isEnglish ? 'Cohort' : 'Angkatan'} ${point.year}</span>
					<div class="dashboard-chart-tooltip-value">
						<span class="value-num">${point.value.toLocaleString(numberLocale)}</span>
						<span class="value-unit">${mapCopy.students}</span>
					</div>
				</div>
			`;
			tooltip.hidden = false;

			const plotRect = plot.getBoundingClientRect();
			const svgRect = svg.getBoundingClientRect();
			const x = (point.x / width) * svgRect.width + (svgRect.left - plotRect.left);
			const y = (point.y / height) * svgRect.height + (svgRect.top - plotRect.top);

			const tipWidth = tooltip.offsetWidth || 210;
			const tipHeight = tooltip.offsetHeight || 72;
			const clampedX = Math.max(tipWidth / 2 + 8, Math.min(plotRect.width - tipWidth / 2 - 8, x));
			tooltip.style.left = `${clampedX}px`;

			if (y < tipHeight + 24) {
				tooltip.style.top = `${y + 16}px`;
				tooltip.style.transform = 'translate(-50%, 0)';
			} else {
				tooltip.style.top = `${y - 12}px`;
				tooltip.style.transform = 'translate(-50%, -100%)';
			}
		};

		const hideTooltip = () => {
			tooltip.hidden = true;
			cursorLine.style.display = 'none';
			yearLabels.forEach((lbl) => lbl.classList.remove('is-active-year'));
			unhighlightSeries();
		};

		const legendContainer = document.createElement('div');
		legendContainer.className = 'dashboard-chart-legend-container';

		const legendToolbar = document.createElement('div');
		legendToolbar.className = 'dashboard-chart-legend-toolbar';

		const totalStudentsCount = displaySeries.reduce((tot, s) => tot + (s.values || []).reduce((sum, v) => sum + (Number(v) || 0), 0), 0);
		const summaryInfo = document.createElement('span');
		summaryInfo.className = 'dashboard-chart-legend-info';
		summaryInfo.textContent = `${displaySeries.length} ${isEnglish ? 'Programs' : 'Program Studi'} • Total ${totalStudentsCount.toLocaleString(numberLocale)} ${mapCopy.students}`;

		const toolbarActions = document.createElement('div');
		toolbarActions.className = 'dashboard-chart-legend-actions';

		const btnShowAll = document.createElement('button');
		btnShowAll.type = 'button';
		btnShowAll.className = 'dashboard-chart-action-btn';
		btnShowAll.textContent = isEnglish ? 'Select All' : 'Pilih Semua';

		const btnHideAll = document.createElement('button');
		btnHideAll.type = 'button';
		btnHideAll.className = 'dashboard-chart-action-btn';
		btnHideAll.textContent = isEnglish ? 'Deselect All' : 'Sembunyikan Semua';

		toolbarActions.append(btnShowAll, btnHideAll);
		legendToolbar.append(summaryInfo, toolbarActions);

		const legend = document.createElement('div');
		legend.className = 'dashboard-chart-legend';

		seriesToggleMap = [];

		displaySeries.forEach((item, index) => {
			const color = colors[index % colors.length];
			const points = (item.values || []).map((value, pointIndex) => ({
				x: getX(pointIndex),
				y: getY(value),
				value: Number(value) || 0,
				year: years[pointIndex],
				pointIndex,
			}));

			const group = svgElement('g', {
				'data-series-id': item.id,
				class: 'dashboard-chart-series-group',
			});

			const areaD = getCurvedAreaPath(points, padding.top + plotHeight);
			const areaPath = svgElement('path', {
				d: areaD,
				fill: `url(#intake-grad-${index})`,
				class: 'dashboard-chart-area',
				style: 'opacity: 0;',
			});

			const lineD = getCurvedPath(points);
			const path = svgElement('path', {
				d: lineD,
				stroke: color,
				fill: 'none',
				class: 'dashboard-chart-line',
			});

			group.append(areaPath, path);

			path.addEventListener('mouseenter', () => highlightSeries(item.id));
			path.addEventListener('mouseleave', () => {
				if (tooltip.hidden) unhighlightSeries();
			});

			points.forEach((point) => {
				const circle = svgElement('circle', {
					cx: point.x,
					cy: point.y,
					r: 4.5,
					fill: color,
					class: 'dashboard-chart-point',
					tabindex: 0,
					role: 'img',
					'aria-describedby': tooltip.id,
					'aria-label': `${item.label}, ${isEnglish ? 'cohort' : 'angkatan'} ${point.year}: ${point.value.toLocaleString(numberLocale)} ${mapCopy.students}`,
				});
				const pointTitle = svgElement('title');
				pointTitle.textContent = `${item.label} · ${point.year}: ${point.value.toLocaleString(numberLocale)} ${mapCopy.students}`;
				circle.append(pointTitle);
				circle.addEventListener('mouseenter', () => showTooltip(point, item, color));
				circle.addEventListener('focus', () => showTooltip(point, item, color));
				circle.addEventListener('mouseleave', () => {
					if (!circle.matches(':focus')) hideTooltip();
				});
				circle.addEventListener('blur', hideTooltip);
				group.append(circle);
			});

			linesGroup.append(group);

			const toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'dashboard-chart-legend-btn';
			toggle.setAttribute('data-series-id', item.id);
			toggle.setAttribute('aria-pressed', 'true');
			toggle.setAttribute('aria-label', `${isEnglish ? 'Show/hide' : 'Tampilkan/sembunyikan'} ${item.label}`);

			const swatch = document.createElement('span');
			swatch.className = 'dashboard-chart-swatch';
			swatch.style.backgroundColor = color;

			const label = document.createElement('span');
			label.className = 'dashboard-chart-legend-label';
			label.textContent = item.label;
			label.title = item.label;

			const totalBadge = document.createElement('span');
			totalBadge.className = 'dashboard-chart-legend-badge';
			const itemTotal = (item.values || []).reduce((sum, v) => sum + (Number(v) || 0), 0);
			totalBadge.textContent = `${itemTotal.toLocaleString(numberLocale)}`;

			toggle.append(swatch, label, totalBadge);

			toggle.addEventListener('click', () => {
				const isCurrentlyShown = toggle.getAttribute('aria-pressed') === 'true';
				const nextState = !isCurrentlyShown;
				toggle.setAttribute('aria-pressed', String(nextState));
				group.style.display = nextState ? '' : 'none';
				if (!nextState && focusedSeriesId === item.id) {
					hideTooltip();
				}
			});

			toggle.addEventListener('mouseenter', () => {
				if (toggle.getAttribute('aria-pressed') === 'true') {
					highlightSeries(item.id);
				}
			});

			toggle.addEventListener('mouseleave', () => {
				if (tooltip.hidden) unhighlightSeries();
			});

			legend.append(toggle);

			seriesToggleMap.push({ item, group, toggle });
		});

		btnShowAll.addEventListener('click', () => {
			seriesToggleMap.forEach(({ group, toggle }) => {
				toggle.setAttribute('aria-pressed', 'true');
				group.style.display = '';
			});
			unhighlightSeries();
		});

		btnHideAll.addEventListener('click', () => {
			seriesToggleMap.forEach(({ group, toggle }) => {
				toggle.setAttribute('aria-pressed', 'false');
				group.style.display = 'none';
			});
			hideTooltip();
		});

		svg.append(linesGroup);
		plot.append(svg, tooltip);

		plot.addEventListener('mouseleave', (event) => {
			if (!event.relatedTarget || !plot.contains(event.relatedTarget)) hideTooltip();
		});

		legendContainer.append(legendToolbar, legend);
		chartRoot.replaceChildren(plot, legendContainer);
	};
	const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',
	})[character]);

	const drawMap = () => {
		if (!distribution.length) {
			const emptyMessage = document.createElement('div');
			emptyMessage.className = 'map-empty';
			emptyMessage.textContent = mapCopy.emptyMap;
			mapRoot.replaceChildren(emptyMessage);
			return;
		}

		const map = L.map(mapRoot, { scrollWheelZoom: false }).setView([-2.5489, 118.0149], 5);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
			maxZoom: 18,
		}).addTo(map);

		const markers = [];
		distribution.forEach((province) => {
			const latitude = Number(province.latitude);
			const longitude = Number(province.longitude);
			if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

			const rawFaculties = province.faculties || [];
			const facultyEntries = Array.isArray(rawFaculties)
				? rawFaculties.map((faculty) => [faculty?.label ?? faculty?.name ?? '', faculty?.total ?? faculty?.count])
				: Object.entries(rawFaculties).map(([label, value]) => [
					value && typeof value === 'object' ? (value.label ?? value.name ?? label) : label,
					value && typeof value === 'object' ? (value.total ?? value.count) : value,
				]);
			const details = facultyEntries
				.filter(([faculty, total]) => typeof faculty === 'string' && faculty.trim() !== ''
					&& Number.isFinite(Number(total)) && Number(total) > 0)
				.map(([faculty, total]) => `<li>${escapeHtml(faculty)}: ${Number(total).toLocaleString(numberLocale)}</li>`)
				.join('');
			const parsedTotal = Number(province.total);
			const total = Number.isFinite(parsedTotal) && parsedTotal >= 0 ? parsedTotal : 0;
			const popup = `<div class="map-popup"><strong>${escapeHtml(province.province)}</strong><ul>${details || `<li>${mapCopy.noFacultyDetails}</li>`}</ul><div class="map-popup-total">${mapCopy.total}: ${total.toLocaleString(numberLocale)} ${mapCopy.students}</div></div>`;
			const marker = L.circleMarker([latitude, longitude], {
				radius: Math.min(28, Math.max(8, 6 + Math.sqrt(total) * 1.5)),
				color: '#fffdf0',
				weight: 2,
				fillColor: '#34764d',
				fillOpacity: 0.82,
			}).bindPopup(popup).bindTooltip(`${province.province}: ${total.toLocaleString(numberLocale)} ${mapCopy.students}`);
			marker.addTo(map);
			markers.push(marker);
		});

		if (markers.length > 1) {
			map.fitBounds(L.featureGroup(markers).getBounds().pad(0.18), { maxZoom: 7 });
		} else if (markers.length === 1) {
			map.setView(markers[0].getLatLng(), 6);
		}
		window.setTimeout(() => map.invalidateSize(), 100);
	};

	try {
		drawChart();
		drawMap();
	} catch (error) {
		console.error('Gagal menampilkan visualisasi dashboard.', error);
		const notice = document.createElement('p');
		notice.className = 'dashboard-empty';
		notice.setAttribute('role', 'alert');
		notice.textContent = isEnglish
			? 'Dashboard visualizations could not be loaded. Reload the page or contact the administrator.'
			: 'Visualisasi dashboard tidak dapat dimuat. Muat ulang halaman atau hubungi administrator.';
		chartRoot.replaceChildren(notice);
	}
}

const dtpsDialog = document.getElementById('dtps-details-dialog');
if (dtpsDialog) {
	const title = dtpsDialog.querySelector('#dtps-details-title');
	const content = dtpsDialog.querySelector('.dtps-details-content');
	let activeTrigger = null;

	const showMessage = (message, isError = false) => {
		const notice = document.createElement('p');
		notice.className = isError ? 'dtps-details-error' : 'dtps-details-message';
		if (isError) notice.setAttribute('role', 'alert');
		else notice.setAttribute('role', 'status');
		notice.textContent = message;
		content.replaceChildren(notice);
	};

	document.addEventListener('click', async (event) => {
		const trigger = event.target instanceof Element ? event.target.closest('[data-dtps-url]') : null;
		if (!trigger) return;
		activeTrigger = trigger;
		title.textContent = trigger.dataset.dtpsProgram || 'Rincian DTPS';
		dtpsDialog.showModal();
		showMessage('Memuat rincian dosen…');

		try {
			const url = new URL(trigger.dataset.dtpsUrl, window.location.origin);
			url.searchParams.set('category', trigger.dataset.dtpsCategory || 'all');
			const response = await fetch(url, {
				headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			});
			const payload = await response.json();
			if (!response.ok) throw new Error(payload.message || `Rincian DTPS gagal dimuat (${response.status}).`);

			if (!payload.lecturers?.length) {
				showMessage('Tidak ada dosen pada kategori ini.');
				return;
			}

			const table = document.createElement('table');
			table.className = 'dtps-details-table';
			const head = table.createTHead().insertRow();
			['Nama dosen', 'Mata kuliah (tahun terakhir mengajar)', 'Tahun akademik terakhir'].forEach((label) => {
				const cell = document.createElement('th');
				cell.scope = 'col';
				cell.textContent = label;
				head.append(cell);
			});
			const body = table.createTBody();
			payload.lecturers.forEach((lecturer) => {
				const row = body.insertRow();
				const name = row.insertCell();
				name.textContent = lecturer.name || '—';
				const courses = row.insertCell();
				const list = document.createElement('ul');
				(lecturer.courses || []).forEach((course) => {
					const item = document.createElement('li');
					item.textContent = `${course.name} (${course.academic_year || '-'})`;
					list.append(item);
				});
				courses.append(list);
				row.insertCell().textContent = lecturer.latest_academic_year || '-';
			});
			content.replaceChildren(table);
		} catch (error) {
			showMessage(error.message || 'Rincian DTPS gagal dimuat.', true);
		}
	});

	dtpsDialog.querySelector('[data-dtps-close]').addEventListener('click', () => dtpsDialog.close());
	dtpsDialog.addEventListener('close', () => {
		activeTrigger?.focus();
		activeTrigger = null;
	});
}
