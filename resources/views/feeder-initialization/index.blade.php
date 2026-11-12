@extends('layouts.simak')

@section('title', 'Inisialisasi Data Feeder | SIAKAD')
@section('topbar_title', 'Inisialisasi Data Feeder')

@section('content')
    @php
        $wizardSteps = [
            'connection' => 'Koneksi',
            'references' => 'Referensi',
            'university' => 'Master PT',
            'programs' => 'Program Studi',
            'lecturers' => 'Dosen',
            'students' => 'Mahasiswa',
            'courses' => 'Mata Kuliah',
            'grades' => 'Bobot Nilai',
            'universities' => 'Seluruh PT',
            'all_programs' => 'Seluruh Prodi',
            'calendar' => 'Kalender Akademik',
            'student_grades' => 'Nilai Mahasiswa',
            'complete' => 'Selesai',
        ];
    @endphp

    <div class="page-heading">
        <div>
            <p class="eyebrow">Administrasi</p>
            <h1>Inisialisasi Data dari Feeder</h1>
            <p class="subtitle">Ikuti setiap tab secara berurutan. Impor berjalan dalam batch terpisah; pemrosesan riwayat mahasiswa dan nilai dibatasi maksimal dua permintaan Feeder per batch agar lebih efisien tanpa membuat satu request terlalu panjang. Tahap yang sudah selesai dapat diulang untuk menyegarkan data. Nilai Mahasiswa diimpor pada tahap 12.</p>
        </div>
    </div>

    @if ($job)
        <section class="panel" id="feeder-job"
            data-step-url="{{ route('feeder-initialization.step') }}"
            data-next-url="{{ route('feeder-initialization.next') }}"
            data-stage-url="{{ route('feeder-initialization.stage') }}"
            data-restart-url="{{ route('feeder-initialization.restart') }}"
            data-csrf="{{ csrf_token() }}"
            data-job='@json($job)'>
            <nav class="feeder-tabs" aria-label="Tahapan wizard inisialisasi">
                @foreach ($wizardSteps as $step => $label)
                    <button class="feeder-tab" type="button" data-tab-stage="{{ $step }}" @if ($job['stage'] === $step) aria-current="step" @endif>
                        <span class="feeder-tab-number">{{ $loop->iteration }}</span>
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="feeder-job-heading">
                <div>
                    <p class="eyebrow">Tahap aktif</p>
                    <h2 id="feeder-stage">{{ $job['stage_label'] }}</h2>
                </div>
                <strong id="feeder-percent" aria-live="polite">{{ $job['progress'] }}%</strong>
            </div>
            <div class="feeder-progress-track" role="progressbar" aria-label="Progres impor Feeder"
                aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $job['progress'] }}">
                <div class="feeder-progress-value" id="feeder-progress-value" style="width: {{ $job['progress'] }}%"></div>
            </div>
            <p class="subtitle" id="feeder-stage-guidance"></p>
            <p class="subtitle" id="feeder-batch-message" aria-live="polite">{{ $job['last_batch'] }}</p>
            <p class="subtitle" id="feeder-batch-duration" aria-live="polite"></p>
            <p class="feeder-job-count">Data diproses: <strong id="feeder-record-count">{{ number_format($job['records'], 0, ',', '.') }}</strong></p>

            <div class="module-alert" id="feeder-error" role="alert" hidden></div>
            <div class="module-alert feeder-gate" id="feeder-faculty-hint" role="note" hidden>
                <p class="subtitle">Impor Master PT selesai. Isi data Fakultas terlebih dahulu, lalu kembali ke tab ini. Tombol Berikutnya akan aktif setelah setidaknya satu Fakultas tersedia.</p>
                <a class="button" href="{{ route('master-data.fakultas.index') }}" target="_blank" rel="noopener">Buka Master Fakultas</a>
                <button class="button" type="button" id="feeder-check-faculty">Periksa Fakultas</button>
            </div>
            <div class="module-alert feeder-gate" id="feeder-program-hint" role="note" hidden>
                <p class="subtitle" id="feeder-program-hint-text"></p>
                <a class="button" href="{{ route('master-data.program-studi.index') }}" target="_blank" rel="noopener">Atur Fakultas di Master Program Studi</a>
                <button class="button" type="button" id="feeder-check-programs">Periksa pemetaan Prodi</button>
            </div>

            <div class="form-actions feeder-actions">
                <button class="button button-primary" type="button" id="feeder-process">Mulai impor tahap ini</button>
                <button class="button" type="button" id="feeder-restart">Ulangi tahapan ini</button>
                <button class="button button-primary" type="button" id="feeder-next" hidden>Berikutnya</button>
            </div>
        </section>
    @endif

    @if (! $job || $job['complete'])
        <section class="panel">
            <div class="module-alert" role="note">
                <p class="subtitle">Masukkan endpoint WebService Feeder beserta kredensialnya. Kredensial disimpan sementara secara terenkripsi dan baru disimpan ke konfigurasi perguruan tinggi setelah profil Master PT berhasil diimpor.</p>
            </div>
            @if ($errors->any())
                <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
            @endif
            <form id="feeder-start-form" method="POST" action="{{ route('feeder-initialization.start') }}">
                @csrf
                <div class="field-grid" style="margin-top:20px">
                    <div>
                        <label for="feeder-url">URL WebService Feeder</label>
                        <input id="feeder-url" name="url" type="url" maxlength="200" required
                            value="{{ old('url', $settings->alamat_feeder ?? '') }}"
                            placeholder="Contoh: http://alamat-server/WS/ atau https://domain-kampus/WS/">
                    </div>
                    <div>
                        <label for="feeder-username">Username Feeder</label>
                        <input id="feeder-username" name="username" maxlength="100" required autocomplete="username"
                            value="{{ old('username', $settings->user_feeder ?? '') }}"
                            placeholder="Masukkan username WebService Feeder">
                    </div>
                    <div>
                        <label for="feeder-password">Password Feeder</label>
                        <input id="feeder-password" name="password" type="password" maxlength="100" required autocomplete="new-password"
                            placeholder="Masukkan password WebService Feeder">
                    </div>
                </div>
                <div class="form-actions">
                    <button class="button button-primary" type="submit" id="feeder-start-button">Uji koneksi Feeder</button>
                </div>
            </form>
        </section>
    @endif

    @if ($job)
        <script>
            (() => {
                const panel = document.getElementById('feeder-job');
                if (!panel) return;
                const csrf = panel.dataset.csrf;
                const processButton = document.getElementById('feeder-process');
                const restartButton = document.getElementById('feeder-restart');
                const nextButton = document.getElementById('feeder-next');
                const checkFacultyButton = document.getElementById('feeder-check-faculty');
                const checkProgramsButton = document.getElementById('feeder-check-programs');
                const stage = document.getElementById('feeder-stage');
                const guidance = document.getElementById('feeder-stage-guidance');
                const percent = document.getElementById('feeder-percent');
                const progressTrack = panel.querySelector('[role="progressbar"]');
                const progressValue = document.getElementById('feeder-progress-value');
                const message = document.getElementById('feeder-batch-message');
                const batchDuration = document.getElementById('feeder-batch-duration');
                const count = document.getElementById('feeder-record-count');
                const errorBox = document.getElementById('feeder-error');
                const facultyHint = document.getElementById('feeder-faculty-hint');
                const programHint = document.getElementById('feeder-program-hint');
                const programHintText = document.getElementById('feeder-program-hint-text');
                const tabs = [...panel.querySelectorAll('[data-tab-stage]')];
                const importedStages = ['references', 'university', 'programs', 'lecturers', 'students', 'courses', 'grades', 'universities', 'all_programs', 'calendar', 'student_grades'];
                const translate = (text) => window.simakTranslate?.(text) || text;
                const guidanceByStage = {
                    connection: 'Kredensial sudah diverifikasi ke WebService Feeder. Tekan Berikutnya untuk memulai impor.',
                    references: 'Impor data referensi Feeder. Tahap ini tidak mengubah data lokal yang tidak ditemukan.',
                    university: 'Impor profil Master PT. Setelah selesai, pastikan Fakultas telah tersedia sebelum melanjutkan.',
                    programs: 'Impor Program Studi. Setelah selesai, tetapkan Fakultas untuk seluruh Program Studi sebelum melanjutkan.',
                    lecturers: 'Impor dosen dan penugasan. NIDN menggunakan NIDN, lalu NIP, lalu NUPTK sebagai fallback. Password di-hash dari identifier yang dipilih. Relasi penugasan yang belum terpetakan dilewati.',
                    students: 'Impor mahasiswa dan riwayat pendidikan berdasarkan Program Studi yang sudah dipetakan.',
                    courses: 'Impor Mata Kuliah per Program Studi beserta relasi perguruan tinggi, Fakultas, dan Program Studi.',
                    grades: 'Impor Bobot Nilai per Program Studi.',
                    universities: 'Impor daftar seluruh perguruan tinggi.',
                    all_programs: 'Impor daftar Program Studi dari seluruh perguruan tinggi.',
                    calendar: 'Impor kalender akademik dari periode perkuliahan Feeder dan hubungkan dengan PT, Fakultas, serta Program Studi lokal.',
                    student_grades: 'Ambil seluruh nilai Feeder untuk mahasiswa berstatus A, C, M, atau N. KRS yang cocok diperbarui, KRS baru ditambahkan, dan pengesahan Admin hanya dibuat jika belum ada.',
                    complete: 'Seluruh tahap inisialisasi selesai.',
                };
                let state = JSON.parse(panel.dataset.job);
                let busy = false;

                const render = (nextState) => {
                    state = {...state, ...nextState};
                    stage.textContent = translate(state.stage_label || state.stage);
                    guidance.textContent = translate(guidanceByStage[state.stage] || '');
                    percent.textContent = `${state.progress}%`;
                    progressTrack.setAttribute('aria-valuenow', state.progress);
                    progressValue.style.width = `${state.progress}%`;
                    count.textContent = new Intl.NumberFormat(window.simakLocale === 'en' ? 'en-US' : 'id-ID').format(state.records || 0);
                    if (state.last_batch) message.textContent = translate(state.last_batch);
                    if (Number.isFinite(Number(state.batch_duration_ms)) && Number(state.batch_duration_ms) > 0) {
                        batchDuration.textContent = translate(`Waktu batch server terakhir: ${(Number(state.batch_duration_ms) / 1000).toFixed(2)} detik.`);
                    }

                    const activeIndex = tabs.findIndex((tab) => tab.dataset.tabStage === state.stage);
                    const completedStages = Array.isArray(state.completed_stages) ? state.completed_stages : [];
                    tabs.forEach((tab, index) => {
                        tab.toggleAttribute('aria-current', index === activeIndex);
                        if (index === activeIndex) tab.setAttribute('aria-current', 'step');
                        else tab.removeAttribute('aria-current');
                        const completed = completedStages.includes(tab.dataset.tabStage)
                            || (tab.dataset.tabStage === state.stage && state.stage_complete);
                        tab.classList.toggle('is-complete', completed);
                        tab.disabled = tab.dataset.tabStage === 'complete'
                            || (!completed && tab.dataset.tabStage !== state.stage);
                    });

                    const canImport = importedStages.includes(state.stage);
                    processButton.hidden = !canImport || state.stage_complete;
                    processButton.disabled = busy || !canImport || state.stage_complete;
                    processButton.textContent = Number(state.stage_records) > 0
                        ? 'Lanjutkan impor tahap ini'
                        : 'Mulai impor tahap ini';
                    restartButton.hidden = !canImport;
                    restartButton.disabled = busy || !canImport;
                    nextButton.hidden = state.complete;
                    nextButton.disabled = busy || !state.ready_for_next;
                    facultyHint.hidden = !(state.stage === 'university' && state.stage_complete && state.faculty_count === 0);
                    programHint.hidden = !(state.stage === 'programs' && state.stage_complete && state.unassigned_programs > 0);
                    if (!programHint.hidden) {
                        programHintText.textContent = translate(`Masih ada ${state.unassigned_programs} Program Studi tanpa Fakultas yang valid. Tetapkan Fakultas untuk semuanya di modul Master Program Studi, lalu kembali ke wizard.`);
                    }
                };
                document.addEventListener('simak:translations-loaded', () => render(state));

                const request = async (url, payload = {}) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(payload),
                        credentials: 'same-origin',
                    });
                    const data = await response.json();
                    render(data);
                    if (!response.ok) throw new Error(data.message || 'Permintaan wizard gagal diproses.');
                    return data;
                };

                const refreshStatus = async () => {
                    if (busy) return;
                    busy = true;
                    errorBox.hidden = true;
                    render({});
                    try {
                        const response = await fetch('{{ route('feeder-initialization.status') }}', {
                            headers: {'Accept': 'application/json'},
                            credentials: 'same-origin',
                        });
                        const data = await response.json();
                        render(data);
                        if (!response.ok) throw new Error(data.message || 'Status prasyarat tidak dapat diperiksa.');
                    } catch (error) {
                        errorBox.textContent = translate(error.message);
                        errorBox.hidden = false;
                    } finally {
                        busy = false;
                        render({});
                    }
                };

                const processStage = async () => {
                    if (busy) return;
                    busy = true;
                    errorBox.hidden = true;
                    const processingStage = state.stage;
                    render({});
                    try {
                        while (state.stage === processingStage && !state.stage_complete) {
                            const nextState = await request(panel.dataset.stepUrl);
                            if (nextState.stage !== processingStage || nextState.stage_complete) break;
                            await new Promise((resolve) => window.setTimeout(resolve, 120));
                        }
                    } catch (error) {
                        errorBox.textContent = translate(error.message);
                        errorBox.hidden = false;
                    } finally {
                        busy = false;
                        render({});
                    }
                };

                const restartStage = async () => {
                    if (busy || !importedStages.includes(state.stage)) return;
                    if (!window.confirm(translate('Ulangi seluruh impor pada tahap ini? Data yang ada akan diperbarui dan data baru akan ditambahkan.'))) return;
                    busy = true;
                    errorBox.hidden = true;
                    render({});
                    try {
                        await request(panel.dataset.restartUrl);
                        const processingStage = state.stage;
                        while (state.stage === processingStage && !state.stage_complete) {
                            const nextState = await request(panel.dataset.stepUrl);
                            if (nextState.stage !== processingStage || nextState.stage_complete) break;
                            await new Promise((resolve) => window.setTimeout(resolve, 120));
                        }
                    } catch (error) {
                        errorBox.textContent = translate(error.message);
                        errorBox.hidden = false;
                    } finally {
                        busy = false;
                        render({});
                    }
                };

                processButton.addEventListener('click', processStage);
                restartButton.addEventListener('click', restartStage);
                tabs.forEach((tab) => tab.addEventListener('click', async () => {
                    if (busy || tab.disabled || tab.dataset.tabStage === state.stage) return;
                    busy = true;
                    errorBox.hidden = true;
                    render({});
                    try {
                        await request(panel.dataset.stageUrl, {stage: tab.dataset.tabStage});
                    } catch (error) {
                        errorBox.textContent = translate(error.message);
                        errorBox.hidden = false;
                    } finally {
                        busy = false;
                        render({});
                    }
                }));
                checkFacultyButton.addEventListener('click', refreshStatus);
                checkProgramsButton.addEventListener('click', refreshStatus);
                nextButton.addEventListener('click', async () => {
                    if (busy || nextButton.disabled) return;
                    busy = true;
                    errorBox.hidden = true;
                    render({});
                    try {
                        await request(panel.dataset.nextUrl);
                    } catch (error) {
                        errorBox.textContent = translate(error.message);
                        errorBox.hidden = false;
                    } finally {
                        busy = false;
                        render({});
                    }
                });
                render(state);
            })();
        </script>
    @endif

    @if (! $job || $job['complete'])
        <script>
            (() => {
                const form = document.getElementById('feeder-start-form');
                if (!form) return;
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const button = document.getElementById('feeder-start-button');
                    button.disabled = true;
                    button.textContent = translate('Menguji koneksi Feeder...');
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                            },
                            body: new FormData(form),
                            credentials: 'same-origin',
                        });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Koneksi Feeder gagal diuji.');
                        window.location.reload();
                    } catch (error) {
                        window.alert(translate(error.message));
                        button.disabled = false;
                        button.textContent = 'Uji koneksi Feeder';
                    }
                });
            })();
        </script>
    @endif
@endsection
