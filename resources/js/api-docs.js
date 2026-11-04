import { createIcons, FileDown, KeyRound, Search, Send } from 'lucide';
import { requestForms, requestFormVariants, requestPathOptions } from './api-request-forms';
import { responseExamples, writeResponseExamples } from './api-response-examples';

createIcons({ icons: { FileDown, KeyRound, Search, Send } });

const root = document.body;
const operations = JSON.parse(document.getElementById('ws-operations-data')?.textContent || '[]');
const operationList = document.querySelector('[data-operation-list]');
const operationEmpty = document.querySelector('[data-operation-empty]');
const searchInput = document.querySelector('[data-operation-search]');
const ssoMenuButton = document.querySelector('[data-sso-menu]');
const ssoPanel = document.querySelector('[data-sso-panel]');
const selectedTitle = document.querySelector('[data-operation-title]');
const selectedMethod = document.querySelector('[data-selected-method]');
const selectedPath = document.querySelector('[data-selected-path]');
const pathFields = document.querySelector('[data-path-fields]');
const requestTitle = document.querySelector('[data-request-title]');
const requestFields = document.querySelector('[data-request-fields]');
const requestFormStatus = document.querySelector('[data-request-form-status]');
const referenceContent = document.querySelector('[data-reference-content]');
const responseOutput = document.querySelector('[data-response-output]');
const responseStatus = document.querySelector('[data-response-status]');
const responseTime = document.querySelector('[data-response-time]');
const requestStatus = document.querySelector('[data-request-status]');
const authPanel = document.querySelector('[data-auth-panel]');
const tokenForm = document.querySelector('[data-token-form]');
const tokenUsername = document.querySelector('[data-token-username]');
const tokenPassword = document.querySelector('[data-token-password]');
const tokenStatus = document.querySelector('[data-token-status]');
const tokenIssue = document.querySelector('[data-token-issue]');
const tokenClear = document.querySelector('[data-token-clear]');
const ssoLogin = document.querySelector('[data-sso-login]');
const ssoPassword = document.querySelector('[data-sso-password]');
const ssoSubmit = document.querySelector('[data-sso-login-submit]');
const ssoStatus = document.querySelector('[data-sso-status]');
const phpExample = document.querySelector('[data-code-example]');
const tokenKey = 'simak.api.v1.token';
const expiryKey = 'simak.api.v1.expires';
let selectedOperation = operations[0] || null;
let methodFilter = 'ALL';
let codeTab = 'curl';
let referenceTab = 'parameters';
const methodLabels = { GET: 'GET', POST: 'POST', PUT: 'PUT', PATCH: 'PATCH', DELETE: 'DELETE' };
const modeLabels = { sandbox: 'Sandbox', production: 'Production' };

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
	'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
})[character]);

const getToken = () => {
	const expires = Number(sessionStorage.getItem(expiryKey) || 0);
	if (expires <= Date.now()) {
		sessionStorage.removeItem(tokenKey);
		sessionStorage.removeItem(expiryKey);
		return '';
	}
	return sessionStorage.getItem(tokenKey) || '';
};

const syncTokenStatus = () => {
	const token = getToken();
	tokenForm.hidden = Boolean(token);
	tokenClear.hidden = !token;
	tokenStatus.textContent = token ? 'Token aktif di tab ini. Nilai token tidak ditampilkan.' : 'Belum ada token di tab ini.';
	tokenStatus.classList.toggle('is-empty', !token);
};

const operationPath = () => {
	if (!selectedOperation) return '/api/v1';
	let path = selectedOperation.path;
	selectedOperation.parameters.forEach((name) => {
		const value = pathFields.querySelector(`[data-path-value="${CSS.escape(name)}"]`)?.value || '';
		if (value !== '') path = path.replace(`{${name}}`, encodeURIComponent(value));
	});
	return `${root.dataset.apiBase}${path}`;
};

const requestForm = () => {
	if (!selectedOperation) return [];
	const variants = requestFormVariants[selectedOperation.name];
	if (variants) {
		const parameter = selectedOperation.name.startsWith('simak-users.') ? 'type' : 'entity';
		const value = pathFields.querySelector(`[data-path-value="${CSS.escape(parameter)}"]`)?.value || '';
		return variants[value] ?? null;
	}
	if (Object.hasOwn(requestForms, selectedOperation.name)) return requestForms[selectedOperation.name];
	return ['GET', 'DELETE'].includes(selectedOperation.method) ? [] : null;
};

const requestData = (validate = true) => {
	const data = {};
	const schema = requestForm();
	if (schema === null) {
		if (validate) throw new Error('Static form belum tersedia untuk operasi ini. Request dinonaktifkan.');
		return data;
	}
	for (const row of requestFields.querySelectorAll('[data-request-field]')) {
		const { name, type, required } = JSON.parse(row.dataset.formField);
		const input = row.querySelector('[data-field-value]');
		const rawValue = input.value;
		if (rawValue === '') {
			if (required && validate) throw new Error(`Nilai ${name} wajib diisi.`);
			continue;
		}

		if (type === 'number') {
			const value = Number(rawValue);
			if (!Number.isFinite(value)) {
				if (validate) throw new Error(`Nilai ${name} harus berupa angka.`);
				continue;
			}
			data[name] = value;
		} else if (type === 'boolean') {
			if (rawValue !== 'true' && rawValue !== 'false') {
				if (validate) throw new Error(`Pilih nilai true atau false untuk ${name}.`);
				continue;
			}
			data[name] = rawValue === 'true';
		} else if (type === 'list') {
			const values = rawValue.split(/\r?\n/).map((value) => value.trim()).filter(Boolean);
			if (required && values.length === 0 && validate) throw new Error(`Nilai ${name} wajib diisi.`);
			if (values.length > 0) data[name] = values;
		} else if (type === 'pairs') {
			const values = {};
			for (const line of rawValue.split(/\r?\n/).map((value) => value.trim()).filter(Boolean)) {
				const separator = line.indexOf('=');
				if (separator < 1) {
					if (validate) throw new Error(`Setiap baris ${name} harus menggunakan format ID=nilai.`);
					continue;
				}
				const key = line.slice(0, separator).trim();
				const value = line.slice(separator + 1).trim();
				if (Object.hasOwn(values, key)) {
					if (validate) throw new Error(`ID ${key} pada ${name} digunakan lebih dari sekali.`);
					continue;
				}
				values[key] = /^-?\d+(\.\d+)?$/.test(value) ? Number(value) : value;
			}
			if (Object.keys(values).length > 0) data[name] = values;
		} else if (type === 'gradeRows') {
			const values = {};
			for (const line of rawValue.split(/\r?\n/).map((value) => value.trim()).filter(Boolean)) {
				const [id, score, credits, grade] = line.split('|').map((value) => value.trim());
				if (!id || score === undefined || credits === undefined || grade === undefined || Object.hasOwn(values, id)) {
					if (validate) throw new Error('Setiap baris grades harus berformat ID KRS|nilai|SKS|nilai huruf.');
					continue;
				}
				values[id] = { nilai: score, sks_asal: credits, nilai_huruf_asal: grade };
			}
			if (Object.keys(values).length > 0) data[name] = values;
		} else if (type === 'nestedLists') {
			const values = {};
			for (const line of rawValue.split(/\r?\n/).map((value) => value.trim()).filter(Boolean)) {
				const separator = line.indexOf('=');
				if (separator < 1) {
					if (validate) throw new Error(`Setiap baris ${name} harus menggunakan format ID=daftar nilai.`);
					continue;
				}
				const key = line.slice(0, separator).trim();
				if (Object.hasOwn(values, key)) {
					if (validate) throw new Error(`ID ${key} pada ${name} digunakan lebih dari sekali.`);
					continue;
				}
				values[key] = line.slice(separator + 1).split(',').map((value) => value.trim()).filter(Boolean);
			}
			if (Object.keys(values).length > 0) data[name] = values;
		} else if (type === 'assessmentRows') {
			const values = {};
			const scoreFields = ['partisipasi', 'project', 'tugas', 'kuis', 'uts', 'uas'];
			for (const line of rawValue.split(/\r?\n/).map((value) => value.trim()).filter(Boolean)) {
				const [id, ...scores] = line.split('|').map((value) => value.trim());
				if (!id || scores.length !== scoreFields.length || Object.hasOwn(values, id)) {
					if (validate) throw new Error('Setiap baris nilai harus berformat ID|partisipasi|project|tugas|kuis|uts|uas.');
					continue;
				}
				values[id] = Object.fromEntries(scores.flatMap((score, index) => {
					if (score === '') return [];
					if (!Number.isFinite(Number(score))) {
						if (validate) throw new Error(`Nilai ${scoreFields[index]} harus berupa angka.`);
						return [];
					}
					return [[scoreFields[index], Number(score)]];
				}));
			}
			if (Object.keys(values).length > 0) data[name] = values;
		} else {
			data[name] = rawValue;
		}
	}

	return data;
};

const renderRequestFields = () => {
	const schema = requestForm();
	const sendButton = document.querySelector('[data-send-request]');
	const isQuery = ['GET', 'DELETE'].includes(selectedOperation?.method);
	requestTitle.textContent = isQuery ? 'Query Parameters' : 'Request Body';
	requestFields.replaceChildren();
	sendButton.disabled = schema === null;
	if (schema === null) {
		requestFormStatus.textContent = 'Static form untuk operasi ini belum tersedia. Request dinonaktifkan agar payload tidak salah.';
		return;
	}
	if (schema.length === 0) {
		requestFormStatus.textContent = isQuery ? 'No query parameters required.' : 'No request body required.';
		return;
	}
	requestFormStatus.textContent = '';

	for (const definition of schema) {
		const row = document.createElement('div');
		row.className = 'ws-request-field';
		row.dataset.requestField = '';
		row.dataset.formField = JSON.stringify(definition);

		const label = document.createElement('label');
		label.className = 'ws-field';
		const labelText = document.createElement('span');
		labelText.textContent = `${definition.label}${definition.required ? ' *' : ' (Opsional)'}`;
		const fieldName = document.createElement('code');
		fieldName.textContent = definition.name;
		label.append(labelText, fieldName);

		let input;
		if (definition.type === 'select') {
			input = document.createElement('select');
			const blank = document.createElement('option');
			blank.value = '';
			blank.textContent = 'Pilih nilai';
			input.append(blank);
			for (const [value, text] of definition.options || []) {
				const option = document.createElement('option');
				option.value = value;
				option.textContent = text;
				input.append(option);
			}
		} else if (['textarea', 'list', 'pairs', 'gradeRows', 'nestedLists', 'assessmentRows'].includes(definition.type)) {
			input = document.createElement('textarea');
			input.rows = definition.type === 'textarea' ? 3 : 5;
			input.spellcheck = false;
		} else {
			input = document.createElement('input');
			input.type = ['text', 'number', 'date', 'time', 'email', 'password'].includes(definition.type) ? definition.type : 'text';
			if (definition.type === 'number') input.step = 'any';
		}
		input.dataset.fieldValue = '';
		input.required = Boolean(definition.required);
		input.placeholder = definition.placeholder || '';
		input.addEventListener('input', renderPhpExample);
		input.addEventListener('change', renderPhpExample);
		row.append(label, input);
		requestFields.append(row);
	}
};

const requestUrl = (method, data) => {
	const url = operationPath();
	if (!['GET', 'DELETE'].includes(method) || Object.keys(data).length === 0) return url;
	const query = new URLSearchParams();
	Object.entries(data).forEach(([key, value]) => query.set(key, typeof value === 'string' ? value : JSON.stringify(value)));
	return `${url}?${query.toString()}`;
};

const renderPhpExample = () => {
	const method = selectedOperation?.method || 'GET';
	let data = {};
	try { data = requestData(false); } catch {}
	const url = requestUrl(method, data);
	const body = JSON.stringify(data, null, 2);
	const bytes = new TextEncoder().encode(body);
	const bodyBase64 = btoa(Array.from(bytes, (byte) => String.fromCharCode(byte)).join(''));
	if (codeTab === 'curl') {
		phpExample.textContent = `// Simpan token di secret store; ambil melalui POST /api/v1/auth/token.\n$token = getenv('SIAKAD_WS_TOKEN');\n${['POST', 'PUT', 'PATCH'].includes(method) ? `$payload = base64_decode('${bodyBase64}', true);\n` : ''}$ch = curl_init('${url}');\ncurl_setopt_array($ch, [\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_CUSTOMREQUEST => '${method}',\n    CURLOPT_HTTPHEADER => [\n        'Accept: application/json',\n        'Content-Type: application/json',\n        'Authorization: Bearer ' . $token,\n    ],\n${['POST', 'PUT', 'PATCH'].includes(method) ? '    CURLOPT_POSTFIELDS => $payload,\n' : ''}]);\n$response = curl_exec($ch);\n$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);\ncurl_close($ch);\necho $status . PHP_EOL . $response;`;
		return;
	}

	phpExample.textContent = `// Simpan token di secret store; ambil melalui POST /api/v1/auth/token.\n$token = getenv('SIAKAD_WS_TOKEN');\n${['POST', 'PUT', 'PATCH'].includes(method) ? `$payload = base64_decode('${bodyBase64}', true);\n` : ''}$options = [\n    'http' => [\n        'method' => '${method}',\n        'header' => "Accept: application/json\\r\\nContent-Type: application/json\\r\\nAuthorization: Bearer " . $token,\n${['POST', 'PUT', 'PATCH'].includes(method) ? "        'content' => $payload,\n" : ''}        'ignore_errors' => true,\n    ],\n];\n$response = file_get_contents('${url}', false, stream_context_create($options));\necho $http_response_header[0] . PHP_EOL . $response;`;
};

const schemaTypeLabel = (definition) => {
	const labels = {
		text: 'string', email: 'string (email)', password: 'string', number: 'number', date: 'string (date)',
		time: 'string (time)', select: `string (${(definition.options || []).map(([value]) => value).join(' | ')})`,
		list: 'array<string>', pairs: 'object', nestedLists: 'object<string, array<string>>',
		gradeRows: 'object', assessmentRows: 'object', textarea: 'string',
	};
	return labels[definition.type] || 'string';
};

const exampleValue = (definition) => {
	if (definition.placeholder?.startsWith('Contoh:')) return definition.placeholder.slice('Contoh:'.length).trim();
	if (definition.type === 'number') return 1;
	if (definition.type === 'date') return '2026-01-01';
	if (definition.type === 'time') return '09:00';
	if (definition.type === 'email') return 'nama@example.test';
	if (definition.type === 'password') return '<password>';
	if (definition.type === 'select') return definition.options?.[0]?.[0] ?? '<value>';
	if (definition.type === 'list') return ['<id>'];
	if (definition.type === 'pairs') return { '<key>': '<value>' };
	if (definition.type === 'nestedLists') return { '<key>': ['<value>'] };
	if (definition.type === 'gradeRows') return { '<id_krs>': { nilai: '80', sks_asal: '3', nilai_huruf_asal: 'A' } };
	if (definition.type === 'assessmentRows') return { '<id>': { partisipasi: 80, project: 80, tugas: 80, kuis: 80, uts: 80, uas: 80 } };
	if (definition.type === 'textarea') return '<text>';
	return definition.placeholder || '<string>';
};

const sandboxResponseExample = () => {
	const method = selectedOperation?.method || 'GET';
	const body = requestForm() === null ? {} : Object.fromEntries((requestForm() || []).filter((item) => item.required).map((item) => [item.name, exampleValue(item)]));
	const response = method === 'GET'
		? responseExamples[selectedOperation?.name] || { data: [{ id: 'sandbox-001', nama: 'Contoh Data Sandbox' }], meta: { total: 1 } }
		: method === 'POST'
			? { pesan: 'Simulasi insert berhasil. Tidak ada data yang disimpan.', data: { id: 'sandbox-created-001', ...body } }
			: ['PUT', 'PATCH'].includes(method)
				? { pesan: 'Simulasi update berhasil. Tidak ada data Production yang berubah.', data: { id: 'sandbox-001', ...body } }
				: { pesan: 'Simulasi delete berhasil. Tidak ada data yang dihapus.' };
	return {
		mode: 'sandbox',
		status_http: method === 'POST' ? 201 : 200,
		permintaan: { metode: method, jalur: requestUrl(method, body), body: ['GET', 'DELETE'].includes(method) ? {} : body },
		respons: response,
	};
};

const renderReference = () => {
	const schema = requestForm();
	const method = selectedOperation?.method || 'GET';
	referenceContent.replaceChildren();
	if (referenceTab === 'parameters') {
		const rows = [];
		selectedOperation?.parameters.forEach((name) => rows.push({ name, location: 'path', type: 'string', required: 'Ya', description: 'Path parameter pada endpoint.' }));
		if (schema !== null) {
			(schema || []).forEach((item) => rows.push({
				name: item.name,
				location: ['GET', 'DELETE'].includes(method) ? 'query' : 'body',
				type: schemaTypeLabel(item),
				required: item.required ? 'Ya' : 'Tidak',
				description: item.label,
			}));
		}
		if (rows.length === 0) {
			referenceContent.textContent = 'No parameters untuk operation ini.';
			return;
		}
		const table = document.createElement('table');
		table.className = 'ws-reference-table';
		const head = document.createElement('thead');
		const headerRow = document.createElement('tr');
		for (const title of ['Name', 'Location', 'Type', 'Required', 'Description']) {
			const cell = document.createElement('th');
			cell.textContent = title;
			headerRow.append(cell);
		}
		head.append(headerRow);
		const body = document.createElement('tbody');
		for (const item of rows) {
			const row = document.createElement('tr');
			for (const value of [item.name, item.location, item.type, item.required, item.description]) {
				const cell = document.createElement('td');
				cell.textContent = value;
				row.append(cell);
			}
			body.append(row);
		}
		table.append(head, body);
		referenceContent.append(table);
		return;
	}

	if (referenceTab === 'request') {
		const note = document.createElement('p');
		note.className = 'ws-reference-copy';
		if (schema === null) {
			note.textContent = 'Static form belum tersedia untuk operation ini. Request dinonaktifkan agar tidak mengirim payload yang belum diverifikasi.';
			referenceContent.append(note);
			return;
		}
		if (['GET', 'DELETE'].includes(method)) {
			note.textContent = schema.length === 0
				? 'No query parameters required. Path parameters tetap harus diisi jika tersedia.'
				: 'Isi query parameters pada form di atas. Nilai akan dikirim melalui URL, bukan Request Body.';
			referenceContent.append(note);
			const example = document.createElement('pre');
			example.className = 'ws-reference-json';
			example.textContent = requestUrl(method, Object.fromEntries((schema || []).filter((item) => item.required).map((item) => [item.name, exampleValue(item)])));
			referenceContent.append(example);
			return;
		}
		note.textContent = 'Contoh menggunakan placeholder sesuai tipe. Field opsional dapat dihilangkan dari payload.';
		referenceContent.append(note);
		const example = document.createElement('pre');
		example.className = 'ws-reference-json';
		example.textContent = schema === null ? '{}' : JSON.stringify(Object.fromEntries((schema || []).filter((item) => item.required).map((item) => [item.name, exampleValue(item)])), null, 2);
		referenceContent.append(example);
		return;
	}

	const note = document.createElement('p');
	note.className = 'ws-reference-copy';
	const responseExample = responseExamples[selectedOperation?.name];
	const writeResponseExample = writeResponseExamples[selectedOperation?.name];
	const redirectResponseExample = selectedOperation?.response_kind === 'redirect';
	if (responseExample || writeResponseExample) {
		note.textContent = 'Contoh struktur Response Production dari controller SIAKAD. Nilai di bawah sintetis, bukan data database; Response aktual muncul setelah Request dijalankan.';
	} else if (redirectResponseExample) {
		note.textContent = 'Controller mengembalikan redirect; Gateway menormalkannya menjadi Response JSON berikut. Isi pesan mengikuti hasil operation.';
	} else {
		note.textContent = 'Ini contoh Response Sandbox berbasis fixture, bukan data SIAKAD. Response Production dapat berbeda sesuai controller dan data aktual; lihat panel Response setelah Request dijalankan.';
	}
	referenceContent.append(note);
	const example = document.createElement('pre');
	example.className = 'ws-reference-json';
	example.textContent = responseExample
		? JSON.stringify({ status_http: 200, content_type: 'application/json', body: responseExample }, null, 2)
		: writeResponseExample
			? JSON.stringify(writeResponseExample, null, 2)
			: redirectResponseExample
				? JSON.stringify({ status_http: 200, content_type: 'application/json', body: { pesan: 'Operasi berhasil.', berhasil: true } }, null, 2)
				: JSON.stringify(sandboxResponseExample(), null, 2);
	referenceContent.append(example);
	if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
		const sandboxNote = document.createElement('p');
		sandboxNote.className = 'ws-reference-copy';
		sandboxNote.textContent = 'Sandbox fixture (tidak menulis data SIAKAD):';
		referenceContent.append(sandboxNote);
		const sandboxExample = document.createElement('pre');
		sandboxExample.className = 'ws-reference-json';
		sandboxExample.textContent = JSON.stringify(sandboxResponseExample(), null, 2);
		referenceContent.append(sandboxExample);
	}
	const responseRows = [
		['200', 'Request berhasil; Production mengembalikan JSON dari controller SIAKAD.'],
		['201', 'Data berhasil dibuat, bila operasi mengembalikan status Created.'],
		['204', 'Request berhasil tanpa Response body.'],
		['401', 'Token atau akun admin_ws tidak valid/aktif.'],
		['403', 'Aksi tidak diizinkan untuk akun atau operasi ini.'],
		['404', 'Endpoint atau record tidak ditemukan.'],
		['409', 'Konflik dengan status atau data yang sudah ada.'],
		['422', 'Validasi Request gagal; Response memuat pesan dan field yang bermasalah.'],
		['429', 'Batas Request terlampaui; perhatikan Retry-After.'],
		['500', 'Operation gagal diproses; detail sensitif tidak dikembalikan.'],
	];
	const table = document.createElement('table');
	table.className = 'ws-reference-table';
	const head = document.createElement('thead');
	const headerRow = document.createElement('tr');
	for (const title of ['HTTP status', 'Response']) {
		const cell = document.createElement('th');
		cell.textContent = title;
		headerRow.append(cell);
	}
	head.append(headerRow);
	const body = document.createElement('tbody');
	for (const [status, description] of responseRows) {
		const row = document.createElement('tr');
		for (const value of [status, description]) {
			const cell = document.createElement('td');
			cell.textContent = value;
			row.append(cell);
		}
		body.append(row);
	}
	table.append(head, body);
	referenceContent.append(table);
	const productionNote = document.createElement('p');
	productionNote.className = 'ws-reference-copy';
	productionNote.textContent = 'Production response fields mengikuti operation SIAKAD yang dipilih. Kirim request Production yang berizin untuk melihat payload aktual pada panel Response; contoh di atas hanya fixture Sandbox.';
	referenceContent.append(productionNote);
};

const selectOperation = (operation) => {
	selectedOperation = operation;
	selectedTitle.textContent = operation.summary;
	selectedMethod.textContent = operation.method;
	selectedMethod.title = `Metode HTTP ${operation.method}`;
	selectedMethod.dataset.method = operation.method;
	pathFields.replaceChildren();
	operation.parameters.forEach((name) => {
		const label = document.createElement('label');
		label.className = 'ws-field';
		label.textContent = `${name} *`;
		const pathOptions = requestPathOptions[operation.name]?.[name];
		const input = pathOptions ? document.createElement('select') : document.createElement('input');
		input.dataset.pathValue = name;
		input.required = true;
		if (pathOptions) {
			const emptyOption = document.createElement('option');
			emptyOption.value = '';
			emptyOption.textContent = 'Pilih nilai';
			input.append(emptyOption);
			for (const [value, text] of pathOptions) {
				const option = document.createElement('option');
				option.value = value;
				option.textContent = text;
				input.append(option);
			}
		} else {
			input.placeholder = name;
		}
		const updatePath = () => {
			selectedPath.textContent = operationPath();
			if (pathOptions) renderRequestFields();
			renderPhpExample();
			renderReference();
		};
		input.addEventListener('input', updatePath);
		input.addEventListener('change', updatePath);
		label.append(input);
		pathFields.append(label);
	});
	selectedPath.textContent = operationPath();
	renderRequestFields();
	operationList.querySelectorAll('.ws-operation').forEach((button) => {
		button.setAttribute('aria-current', String(button.dataset.operationId === operation.id));
	});
	requestStatus.textContent = '';
	responseStatus.textContent = 'Belum ada Request';
	responseTime.textContent = '';
	responseOutput.textContent = 'Response akan tampil di sini.';
	renderPhpExample();
	renderReference();
};

const renderOperations = () => {
	const query = searchInput.value.trim().toLowerCase();
	const matchesSso = (methodFilter === 'ALL' || methodFilter === 'POST')
		&& 'sso login simak /auth/sso'.includes(query);
	ssoMenuButton.hidden = !matchesSso;
	const visible = operations.filter((operation) => {
		const matchesMethod = methodFilter === 'ALL' || operation.method === methodFilter;
		const matchesQuery = `${operation.summary} ${operation.name} ${operation.path}`.toLowerCase().includes(query);
		return matchesMethod && matchesQuery;
	});
	operationList.replaceChildren();
	visible.forEach((operation) => {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'ws-operation';
		button.dataset.operationId = operation.id;
		button.setAttribute('aria-current', String(selectedOperation?.id === operation.id));
		const method = document.createElement('span');
		method.className = 'ws-method';
		method.dataset.method = operation.method;
		method.textContent = methodLabels[operation.method] || operation.method;
		method.title = `Metode HTTP ${operation.method}`;
		const name = document.createElement('span');
		name.className = 'ws-operation-name';
		name.textContent = operation.summary;
		button.append(method, name);
		button.addEventListener('click', () => selectOperation(operation));
		operationList.append(button);
	});
	operationEmpty.hidden = visible.length !== 0;
};

ssoMenuButton.addEventListener('click', () => {
	ssoPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
	ssoLogin.focus({ preventScroll: true });
});

const sendRequest = async () => {
	if (!selectedOperation) return;
	const mode = root.dataset.apiMode || 'sandbox';
	let body;
	try { body = requestData(); } catch (error) {
		requestStatus.textContent = error.message;
		return;
	}
	if (mode === 'production' && !getToken()) {
		requestStatus.textContent = 'Ambil token admin_ws terlebih dahulu.';
		return;
	}
	if (mode === 'production' && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(selectedOperation.method)
		&& !window.confirm(`Anda akan mengirim Request ${selectedOperation.method} ke data Production melalui ${selectedOperation.summary}. Lanjutkan hanya dengan otorisasi dan data yang telah diperiksa.`)) return;

	const started = performance.now();
	requestStatus.textContent = 'Sending Request…';
	try {
		let response;
		const url = requestUrl(selectedOperation.method, body);
		if (mode === 'sandbox') {
			response = await fetch(root.dataset.sandboxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
				body: JSON.stringify({
					operation: selectedOperation.name,
					method: selectedOperation.method,
					path: requestUrl(selectedOperation.method, body),
					body: ['GET', 'DELETE'].includes(selectedOperation.method) ? {} : body,
				}),
			});
		} else {
			response = await fetch(url, {
				method: selectedOperation.method,
				headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${getToken()}` },
				...(['POST', 'PUT', 'PATCH'].includes(selectedOperation.method) ? { body: JSON.stringify(body) } : {}),
			});
		}
		const text = await response.text();
		let result;
		try { result = JSON.parse(text); } catch { result = { raw: text.slice(0, 20000) }; }
		responseStatus.textContent = `HTTP ${response.status} · ${modeLabels[mode] || mode}`;
		responseTime.textContent = `${Math.round(performance.now() - started)} ms`;
		responseOutput.textContent = JSON.stringify(result, null, 2);
		requestStatus.textContent = response.ok ? 'Request selesai.' : 'Request ditolak atau gagal.';
		if (response.status === 401 && mode === 'production') {
			sessionStorage.removeItem(tokenKey);
			sessionStorage.removeItem(expiryKey);
			syncTokenStatus();
		}
	} catch {
		requestStatus.textContent = 'Gagal menghubungi API.';
		responseStatus.textContent = 'Network Error';
		responseOutput.textContent = 'Request tidak dapat diselesaikan. Periksa koneksi dan konfigurasi alamat API.';
	}
};

document.querySelectorAll('[data-method-filter]').forEach((button) => button.addEventListener('click', () => {
	document.querySelectorAll('[data-method-filter]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
	methodFilter = button.dataset.methodFilter;
	renderOperations();
}));
searchInput.addEventListener('input', renderOperations);
document.querySelector('[data-send-request]').addEventListener('click', sendRequest);

document.querySelectorAll('[data-mode]').forEach((button) => button.addEventListener('click', () => {
	const production = button.dataset.mode === 'production';
	root.dataset.apiMode = production ? 'production' : 'sandbox';
	document.querySelectorAll('[data-mode]').forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
	authPanel.hidden = !production;
	requestStatus.textContent = production ? 'Production aktif. Perubahan memerlukan konfirmasi.' : 'Sandbox aktif dan terisolasi.';
}));

tokenIssue.addEventListener('click', async () => {
	const username = tokenUsername.value.trim();
	const password = tokenPassword.value;
	if (!username || !password) {
		tokenStatus.textContent = 'Username dan password admin_ws wajib diisi.';
		tokenStatus.classList.add('is-empty');
		return;
	}
	tokenIssue.disabled = true;
	try {
		const response = await fetch(root.dataset.tokenUrl, {
			method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify({ username, password }),
		});
		const result = await response.json();
		tokenPassword.value = '';
		if (!response.ok || typeof result.akses_token !== 'string') throw new Error(result.pesan || 'Kredensial API tidak valid.');
		sessionStorage.setItem(tokenKey, result.akses_token);
		sessionStorage.setItem(expiryKey, String(Date.now() + Number(result.kedaluwarsa_dalam) * 1000));
		tokenStatus.textContent = `Token aktif · kedaluwarsa dalam ${Math.round(Number(result.kedaluwarsa_dalam) / 60)} menit.`;
		tokenStatus.classList.remove('is-empty');
		syncTokenStatus();
	} catch (error) {
		tokenStatus.textContent = error.message;
		tokenStatus.classList.add('is-empty');
	} finally {
		tokenIssue.disabled = false;
	}
});

tokenClear.addEventListener('click', () => {
	sessionStorage.removeItem(tokenKey);
	sessionStorage.removeItem(expiryKey);
	syncTokenStatus();
});

ssoSubmit.addEventListener('click', async () => {
	const login = ssoLogin.value.trim();
	const password = ssoPassword.value;
	if (!login || !password) {
		ssoStatus.textContent = 'Identitas login dan password SIAKAD wajib diisi.';
		ssoStatus.classList.add('is-empty');
		return;
	}

	ssoSubmit.disabled = true;
	ssoStatus.textContent = 'Memverifikasi akun SIAKAD…';
	const started = performance.now();
	try {
		const response = await fetch(root.dataset.ssoUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
			body: JSON.stringify({ login, password }),
		});
		const result = await response.json();
		ssoPassword.value = '';
		responseStatus.textContent = `HTTP ${response.status} · SSO Login`;
		responseTime.textContent = `${Math.round(performance.now() - started)} ms`;
		responseOutput.textContent = JSON.stringify(result, null, 2);
		if (!response.ok) throw new Error(result.message || 'Identitas atau kata sandi tidak valid.');
		ssoStatus.textContent = 'Login berhasil. Detail pengguna ditampilkan pada Response.';
		ssoStatus.classList.remove('is-empty');
	} catch (error) {
		ssoStatus.textContent = error.message || 'Gagal menghubungi endpoint SSO.';
		ssoStatus.classList.add('is-empty');
	} finally {
		ssoSubmit.disabled = false;
	}
});

document.querySelectorAll('[data-code-tab]').forEach((button) => button.addEventListener('click', () => {
	codeTab = button.dataset.codeTab;
	document.querySelectorAll('[data-code-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab === button)));
	renderPhpExample();
}));

document.querySelectorAll('[data-reference-tab]').forEach((button) => button.addEventListener('click', () => {
	referenceTab = button.dataset.referenceTab;
	document.querySelectorAll('[data-reference-tab]').forEach((tab) => tab.setAttribute('aria-selected', String(tab === button)));
	renderReference();
}));

document.querySelectorAll('[data-mode]').forEach((button) => {
	button.disabled = false;
});
syncTokenStatus();
renderOperations();
if (selectedOperation) selectOperation(selectedOperation);