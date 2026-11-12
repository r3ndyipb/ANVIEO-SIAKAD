@extends('layouts.simak')

@php($editing = $schedule !== null)
@php($shortSemester = $shortSemester ?? false)
@php($scheduleRoutes = $scheduleRoutes ?? ['index' => 'class-schedules.index', 'store' => 'class-schedules.store'])
@php($scheduleTitle = $shortSemester ? 'Jadwal SP' : 'Jadwal Kuliah')
@section('title', ($editing ? 'Ubah' : 'Tambah').' '.$scheduleTitle.' | SIAKAD')
@section('topbar_title', ($editing ? 'Ubah' : 'Tambah').' '.$scheduleTitle)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>{{ $editing ? 'Ubah '.$scheduleTitle : 'Tambah '.$scheduleTitle }}</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">{{ $programLabel }} · {{ data_get($calendar, 'namatahun') ?: data_get($calendar, 'tahun') }}</p>
            </div>
        </div>
        <a class="button" href="{{ route($scheduleRoutes['index'], ['id_prodi' => $programId, 'id_kalender' => data_get($calendar, 'id_kalender')]) }}">Kembali ke jadwal</a>
    </div>

    @if ($shortSemester)
        @include('components.short-semester-feeder-alert')
    @endif

    @if ($canChooseProgram)
        <form class="schedule-filters" method="GET" action="{{ route($scheduleRoutes['create'] ?? 'class-schedules.create') }}">
            <div>
                <label for="schedule-form-program">Program studi</label>
                <select id="schedule-form-program" name="id_prodi" required data-searchable-select="schedule">
                    @foreach ($programs as $optionProgramId => $optionProgramLabel)
                        <option value="{{ $optionProgramId }}" @selected($programId === (string) $optionProgramId)>{{ $optionProgramLabel }}</option>
                    @endforeach
                </select>
            </div>
            <button class="button" type="submit">Pilih program studi</button>
        </form>
    @endif

    @if ($hasKrs)
        <p class="schedule-krs-notice">Jadwal ini sudah dikontrak matakuliah oleh pada KRS. Mata kuliah, ruang, hari, dan jam dikunci; dosen pengampu, kelas, serta metadata Kampus Merdeka masih bisa diubah.</p>
    @endif

    <form method="POST" action="{{ $editing ? route($shortSemester ? $scheduleRoutes['update'] : 'class-schedules.update', $schedule->id_jadwal) : route($scheduleRoutes['store']) }}" @if ($shortSemester && $editing) data-short-semester-update @elseif ($shortSemester && ! $editing) data-short-semester-create @endif>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="id_prodi" value="{{ $programId }}">

        <section class="form-section">
            <h2>Informasi Perkuliahan</h2>
            <div class="field-grid">
                <div>
                    <label>Program studi</label>
                    <input value="{{ $programLabel }}" disabled>
                </div>
                <div>
                    <label>Tahun akademik</label>
                    <input value="{{ data_get($calendar, 'namatahun') ?: data_get($calendar, 'tahun') }}" disabled>
                </div>
                @foreach ([
                    'id_matkul' => ['label' => 'Mata kuliah', 'options' => $courses, 'required' => true],
                    'id_ruang' => ['label' => 'Ruang', 'options' => $rooms, 'required' => true],
                ] as $field => $definition)
                    <div>
                        <label for="schedule-{{ $field }}">{{ $definition['label'] }} *</label>
                        @if ($hasKrs)
                            <input value="{{ $definition['options'][old($field, $schedule->{$field})] ?? 'Pilihan tersimpan' }}" disabled>
                            <input type="hidden" name="{{ $field }}" value="{{ $schedule->{$field} }}">
                        @else
                            <select id="schedule-{{ $field }}" name="{{ $field }}" required data-searchable-select="schedule">
                                <option value="">Pilih {{ strtolower($definition['label']) }}</option>
                                @foreach ($definition['options'] as $value => $label)
                                    <option value="{{ $value }}" @selected((string) old($field, $schedule->{$field} ?? '') === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="schedule-nama-kelas">Nama kelas *</label>
                    <input id="schedule-nama-kelas" name="nama_kelas" value="{{ old('nama_kelas', $schedule->nama_kelas ?? '') }}" maxlength="{{ $classNameMaxLength }}" required>
                    @error('nama_kelas')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                @foreach ([
                    'hari' => ['label' => 'Hari', 'type' => 'select'],
                    'jammulai' => ['label' => 'Jam mulai', 'type' => 'time'],
                    'jamselesai' => ['label' => 'Jam selesai', 'type' => 'time'],
                ] as $field => $definition)
                    <div>
                        <label for="schedule-{{ $field }}">{{ $definition['label'] }} {{ $definition['type'] === 'select' ? '*' : '*' }}</label>
                        @if ($hasKrs)
                            @if ($field === 'hari')
                                <input value="{{ ucfirst($schedule->hari) }}" disabled>
                            @else
                                <input value="{{ substr((string) $schedule->{$field}, 0, 5) }}" disabled>
                            @endif
                            <input type="hidden" name="{{ $field }}" value="{{ $schedule->{$field} }}">
                        @elseif ($field === 'hari')
                            <select id="schedule-hari" name="hari" required data-searchable-select="schedule">
                                <option value="">Pilih hari</option>
                                @foreach ($days as $value => $label)
                                    <option value="{{ $value }}" @selected(old('hari', $schedule->hari ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="schedule-{{ $field }}" name="{{ $field }}" type="time" value="{{ old($field, substr((string) ($schedule->{$field} ?? ''), 0, 5)) }}" required>
                        @endif
                        @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="form-section">
            <h2>Dosen Pengampu</h2>
            <div class="field-grid">
                @foreach ($teacherFields as $index => $field)
                    <div>
                        <label for="schedule-{{ $field }}">{{ $index === 0 ? 'Pengampu utama' : 'Pengampu '.($index + 1) }}{{ $index === 0 ? ' *' : '' }}</label>
                        <select id="schedule-{{ $field }}" name="{{ $field }}" data-searchable-select="schedule" @required($index === 0)>
                            <option value="">Pilih pengampu</option>
                            @foreach ($index === 0 ? $lecturers : $teachers as $value => $label)
                                <option value="{{ $value }}" @selected((string) old($field, $schedule->{$field} ?? '') === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error($field)<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="form-section">
            <h2>Kampus Merdeka</h2>
            <div class="field-grid">
                <div>
                    <label for="schedule-kampus-merdeka">Kelas Kampus Merdeka</label>
                    <select id="schedule-kampus-merdeka" name="kampus_merdeka" required data-searchable-select="schedule">
                        <option value="0" @selected((string) old('kampus_merdeka', $schedule->kampus_merdeka ?? '0') === '0')>Tidak</option>
                        <option value="1" @selected((string) old('kampus_merdeka', $schedule->kampus_merdeka ?? '0') === '1')>Ya</option>
                    </select>
                </div>
                <div>
                    <label for="schedule-lingkup-km">Lingkup</label>
                    <select id="schedule-lingkup-km" name="lingkup_km" data-searchable-select="schedule">
                        <option value="">Default</option>
                        @foreach ($kmScopes as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('lingkup_km', $schedule->lingkup_km ?? '') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="schedule-mode-km">Mode belajar</label>
                    <select id="schedule-mode-km" name="mode_km" data-searchable-select="schedule">
                        <option value="">Default</option>
                        @foreach ($kmModes as $value => $label)
                            <option value="{{ $value }}" @selected((string) old('mode_km', $schedule->mode_km ?? '') === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="schedule-tanggal-tutup">Tanggal tutup pendaftaran KM</label>
                    <input id="schedule-tanggal-tutup" name="tgl_tutup_daftar_km" type="date" value="{{ old('tgl_tutup_daftar_km', $schedule->tgl_tutup_daftar_km ?? '') }}">
                    @error('tgl_tutup_daftar_km')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="reenrollment-empty">Tanggal tutup wajib diisi jika kelas Kampus Merdeka dipilih.</p>
        </section>

        <div class="form-actions">
            <a class="button" href="{{ route($scheduleRoutes['index'], ['id_prodi' => $programId, 'id_kalender' => data_get($calendar, 'id_kalender')]) }}">Batal</a>
            <button class="button button-primary" type="submit">{{ $editing ? 'Simpan perubahan' : 'Simpan jadwal' }}</button>
        </div>
    </form>

    @if ($shortSemester)
        <div class="calendar-modal-backdrop" data-short-schedule-progress hidden>
            <section class="calendar-modal schedule-progress-modal" role="dialog" aria-modal="true" aria-labelledby="short-schedule-progress-title" aria-describedby="short-schedule-progress-message">
                <h2 id="short-schedule-progress-title">Mengirim Jadwal SP</h2>
                <p id="short-schedule-progress-message" aria-live="polite">Memvalidasi prodi, kalender SP aktif, dan kurikulum terkait; lalu mengirim kelas ke Feeder dan menyimpan jadwal di SIAKAD...</p>
                <div class="schedule-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <span data-short-schedule-progress-bar></span>
                </div>
                <strong data-short-schedule-progress-value>0%</strong>
                <ul data-short-schedule-progress-errors hidden></ul>
            </section>
        </div>
        @push('scripts')
            <script>
                (() => {
                    const forms = document.querySelectorAll('[data-short-semester-create], [data-short-semester-update]');
                    if (!forms.length) return;

                    const modal = document.querySelector('[data-short-schedule-progress]');
                    const message = document.querySelector('#short-schedule-progress-message');
                    const progress = document.querySelector('.schedule-progress-track');
                    const bar = document.querySelector('[data-short-schedule-progress-bar]');
                    const value = document.querySelector('[data-short-schedule-progress-value]');
                    const errors = document.querySelector('[data-short-schedule-progress-errors]');
                    const syncUrlTemplate = @json(route('short-semester-schedules.teachers.sync', ['scheduleId' => '__SCHEDULE__', 'teacherId' => '__TEACHER__']));

                    const setProgress = (current, total) => {
                        const percent = total === 0 ? 100 : Math.round((current / total) * 100);
                        bar.style.width = `${percent}%`;
                        value.textContent = `${percent}%`;
                        progress.setAttribute('aria-valuenow', String(percent));
                    };

                    const showError = (text) => {
                        const item = document.createElement('li');
                        item.textContent = text;
                        errors.append(item);
                        errors.hidden = false;
                    };

                    forms.forEach((form) => {
                        form.addEventListener('submit', async (event) => {
                            event.preventDefault();
                            const submitButton = form.querySelector('[type="submit"]');
                            const isCreate = form.matches('[data-short-semester-create]');
                            let retrySafe = true;
                            submitButton.disabled = true;
                            modal.hidden = false;
                            progress.classList.add('is-pending');
                            errors.innerHTML = '';
                            errors.hidden = true;
                            message.textContent = isCreate
                                ? 'Memvalidasi prodi, kalender SP aktif, dan kurikulum terkait; lalu mengirim kelas ke Feeder dan menyimpan jadwal di SIAKAD...'
                                : 'Memvalidasi perubahan jadwal SP, lalu memperbarui kelas Feeder dan dosen pengampu satu per satu...';
                            try {
                                const response = await fetch(form.action, {
                                    method: form.method || 'POST',
                                    body: new FormData(form),
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                });
                                const payload = await response.json();
                                if (!response.ok) {
                                    if (payload.feeder_class_id) {
                                        retrySafe = false;
                                        message.textContent = 'Kelas sudah dibuat di Feeder, tetapi jadwal SIAKAD gagal disimpan. Periksa kelas di Feeder sebelum tindakan lanjutan.';
                                    }
                                    throw new Error(payload.message || (isCreate ? 'Jadwal SP gagal dibuat.' : 'Jadwal SP gagal diperbarui.'));
                                }

                                const teacherIds = Array.isArray(payload.teacher_ids) ? payload.teacher_ids : [];
                                const totalSteps = Math.max(1, teacherIds.length + 1);
                                progress.classList.remove('is-pending');
                                setProgress(1, totalSteps);
                                message.textContent = payload.message || (isCreate ? 'Kelas Feeder berhasil dibuat dan jadwal tersimpan di SIAKAD.' : 'Jadwal SP berhasil diperbarui di Feeder dan SIAKAD.');

                                if (isCreate) {
                                    let failed = 0;
                                    for (let index = 0; index < teacherIds.length; index += 1) {
                                        const teacherId = String(teacherIds[index]);
                                        message.textContent = `Mengirim dosen ${index + 1} dari ${teacherIds.length} ke Feeder...`;
                                        const url = syncUrlTemplate
                                            .replace('__SCHEDULE__', encodeURIComponent(payload.schedule_id))
                                            .replace('__TEACHER__', encodeURIComponent(teacherId));
                                        try {
                                            const syncResponse = await fetch(url, {
                                                method: 'POST',
                                                headers: {
                                                    Accept: 'application/json',
                                                    'X-Requested-With': 'XMLHttpRequest',
                                                    'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value,
                                                },
                                            });
                                            const synced = await syncResponse.json();
                                            if (!syncResponse.ok) throw new Error(synced.message || `Pengiriman dosen ${teacherId} gagal.`);
                                        } catch (error) {
                                            failed += 1;
                                            showError(error.message);
                                        }
                                        setProgress(index + 2, totalSteps);
                                    }

                                    message.textContent = failed === 0
                                        ? 'Jadwal dan semua dosen pengampu berhasil dikirim.'
                                        : `${failed} dosen gagal dikirim. Jadwal dan ID kelas Feeder sudah tersimpan; dosen yang gagal dapat dicoba lagi.`;
                                }

                                setProgress(totalSteps, totalSteps);
                                window.setTimeout(() => window.location.assign(payload.redirect_url || form.action), 1000);
                            } catch (error) {
                                progress.classList.remove('is-pending');
                                message.textContent = error.message;
                                showError(error.message);
                                if (retrySafe) submitButton.disabled = false;
                            }
                        });
                    });
                })();
            </script>
        @endpush
    @endif
@endsection