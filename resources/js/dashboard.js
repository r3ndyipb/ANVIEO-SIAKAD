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
	const colors = ['#33734c', '#d2a92b', '#5379b3', '#bb634b', '#8b65a7', '#3d9a91', '#c26d9c', '#879a41', '#56616e', '#d1842f'];
	const svgNamespace = 'http://www.w3.org/2000/svg';

	const svgElement = (name, attributes = {}) => {
		const element = document.createElementNS(svgNamespace, name);
		Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, String(value)));
		return element;
	};

	const drawChart = () => {
		const visibleSeries = series.filter((item) => item.values.some((value) => value > 0));
		if (!visibleSeries.length) {
			return;
		}

		const width = Math.max(720, Math.min(900, chartRoot.clientWidth));
		const height = 412;
		const padding = { top: 92, right: 22, bottom: 42, left: 54 };
		const plotWidth = width - padding.left - padding.right;
		const plotHeight = height - padding.top - padding.bottom;
		const maxValue = Math.max(1, ...visibleSeries.flatMap((item) => item.values));
		const tickStep = Math.max(1, Math.ceil(maxValue / 4));
		const axisMaximum = tickStep * 4;
		const svg = svgElement('svg', {
			viewBox: `0 0 ${width} ${height}`,
			role: 'group',
			'aria-labelledby': 'student-intake-svg-title student-intake-svg-description',
		});
		const plot = document.createElement('div');
		plot.className = 'dashboard-chart-plot';
		plot.style.width = `${width}px`;
		const title = svgElement('title', { id: 'student-intake-svg-title' });
		title.textContent = isEnglish ? 'New student trend by study program' : 'Tren mahasiswa baru per program studi';
		svg.append(title);
		const description = svgElement('desc', { id: 'student-intake-svg-description' });
		description.textContent = isEnglish
			? `Student count by cohort from ${years[0]} to ${years.at(-1)}.`
			: `Jumlah mahasiswa berdasarkan angkatan dari ${years[0]} sampai ${years.at(-1)}.`;
		svg.append(description);

		for (let tick = 0; tick <= 4; tick += 1) {
			const value = axisMaximum - tick * tickStep;
			const y = padding.top + (tick / 4) * plotHeight;
			svg.append(svgElement('line', {
				x1: padding.left,
				y1: y,
				x2: width - padding.right,
				y2: y,
				class: 'dashboard-chart-grid',
			}));
			const label = svgElement('text', {
				x: padding.left - 10,
				y: y + 4,
				'text-anchor': 'end',
				class: 'dashboard-chart-label',
			});
			label.textContent = value.toLocaleString(numberLocale);
			svg.append(label);
		}

		years.forEach((year, index) => {
			const x = padding.left + (years.length <= 1 ? 0 : (index / (years.length - 1)) * plotWidth);
			const label = svgElement('text', {
				x,
				y: height - 12,
				'text-anchor': 'middle',
				class: 'dashboard-chart-label',
			});
			label.textContent = String(year);
			svg.append(label);
		});

		const linesGroup = svgElement('g', { 'aria-label': isEnglish ? 'Study program data lines' : 'Garis data per program studi' });
		const tooltip = document.createElement('div');
		tooltip.className = 'dashboard-chart-tooltip';
		tooltip.id = 'student-intake-tooltip';
		tooltip.setAttribute('role', 'tooltip');
		tooltip.hidden = true;

		const showTooltip = (point, item, year, color) => {
			const plotRect = plot.getBoundingClientRect();
			const svgRect = svg.getBoundingClientRect();
			const x = (point.x / width) * svgRect.width + (svgRect.left - plotRect.left);
			const y = (point.y / height) * svgRect.height + (svgRect.top - plotRect.top);
			tooltip.replaceChildren();
			const programName = document.createElement('strong');
			programName.textContent = item.label;
			programName.style.color = color;
			const intakeYear = document.createElement('span');
			intakeYear.textContent = `${isEnglish ? 'Cohort' : 'Angkatan'} ${year}`;
			const studentCount = document.createElement('span');
			studentCount.textContent = `${point.value.toLocaleString(numberLocale)} ${mapCopy.students}`;
			tooltip.append(programName, intakeYear, studentCount);
			tooltip.hidden = false;
			tooltip.style.left = `${Math.max(tooltip.offsetWidth / 2 + 8, Math.min(plotRect.width - tooltip.offsetWidth / 2 - 8, x))}px`;
			tooltip.style.top = `${Math.max(tooltip.offsetHeight + 12, y)}px`;
			tooltip.style.transform = 'translate(-50%, calc(-100% - 12px))';
		};
		const hideTooltip = () => { tooltip.hidden = true; };

		const legend = document.createElement('div');
		legend.className = 'dashboard-chart-legend';
		visibleSeries.forEach((item, index) => {
			const color = colors[index % colors.length];
			const points = item.values.map((value, pointIndex) => ({
				x: padding.left + (years.length <= 1 ? 0 : (pointIndex / (years.length - 1)) * plotWidth),
				y: padding.top + plotHeight - (value / axisMaximum) * plotHeight,
				value,
			}));
			const group = svgElement('g', { 'data-series-id': item.id });
			const path = svgElement('path', {
				d: points.map((point, pointIndex) => `${pointIndex === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' '),
				stroke: color,
				class: 'dashboard-chart-line',
			});
			group.append(path);
			points.forEach((point, pointIndex) => {
				const circle = svgElement('circle', {
					cx: point.x,
					cy: point.y,
					r: 4,
					fill: color,
					class: 'dashboard-chart-point',
					tabindex: 0,
					role: 'img',
					'aria-describedby': tooltip.id,
					'aria-label': `${item.label}, ${isEnglish ? 'cohort' : 'angkatan'} ${years[pointIndex]}: ${point.value.toLocaleString(numberLocale)} ${mapCopy.students}`,
				});
				const pointTitle = svgElement('title');
				pointTitle.textContent = `${item.label} · ${years[pointIndex]}: ${point.value.toLocaleString(numberLocale)} ${mapCopy.students}`;
				circle.append(pointTitle);
				circle.addEventListener('mouseenter', () => showTooltip(point, item, years[pointIndex], color));
				circle.addEventListener('focus', () => showTooltip(point, item, years[pointIndex], color));
				circle.addEventListener('mouseleave', () => {
					if (!circle.matches(':focus')) hideTooltip();
				});
				circle.addEventListener('blur', hideTooltip);
				group.append(circle);
			});
			linesGroup.append(group);

			const toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.setAttribute('aria-pressed', 'true');
			toggle.setAttribute('aria-label', `${isEnglish ? 'Show/hide' : 'Tampilkan/sembunyikan'} ${item.label}`);
			const swatch = document.createElement('span');
			swatch.className = 'dashboard-chart-swatch';
			swatch.style.backgroundColor = color;
			const label = document.createElement('span');
			label.textContent = item.label;
			toggle.append(swatch, label);
			toggle.addEventListener('click', () => {
				const shown = toggle.getAttribute('aria-pressed') === 'true';
				toggle.setAttribute('aria-pressed', String(!shown));
				group.style.display = shown ? 'none' : '';
			});
			legend.append(toggle);
		});
		svg.append(linesGroup);
		plot.append(svg, tooltip);
		plot.addEventListener('mouseleave', (event) => {
			if (!event.relatedTarget || !plot.contains(event.relatedTarget)) hideTooltip();
		});
		chartRoot.replaceChildren(plot, legend);
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
