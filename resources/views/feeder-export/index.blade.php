@extends('layouts.simak')

@section('title', $reportPage ? 'Pelaporan Export ke Feeder' : 'Export ke Feeder')

@section('content')
<style>
    .feeder-export-wrap { display: grid; gap: 18px; max-width: 1280px; margin: 0 auto; }
    .feeder-export-card { padding: 22px; border: 1px solid #e3e9e0; border-radius: 14px; background: #fff; box-shadow: 0 8px 24px rgba(35, 70, 43, .06); }
    .feeder-export-card h1, .feeder-export-card h2 { margin: 0 0 8px; color: #213b2a; }
    .feeder-export-card h1 { font-size: 23px; }
    .feeder-export-card h2 { font-size: 17px; }
    .feeder-export-muted { color: #657365; font-size: 13px; line-height: 1.6; }
    .feeder-export-warning { margin: 14px 0; padding: 13px 15px; border: 1px solid #f0d77a; border-radius: 9px; background: #fff9dc; color: #674f00; font-size: 13px; line-height: 1.55; }
    .feeder-export-info { margin: 14px 0 18px; padding: 13px 15px; border: 1px solid #b8d7ed; border-radius: 9px; background: #eff8ff; color: #234c68; font-size: 13px; line-height: 1.55; }
    .feeder-export-info p { margin: 4px 0 0; }
    .feeder-export-add { margin: 16px 0 22px; border: 1px solid #dbe6d9; border-radius: 10px; }
    .feeder-export-add summary { padding: 13px 15px; color: #315b3b; font-size: 13px; font-weight: 700; cursor: pointer; }
    .feeder-export-add[open] summary { border-bottom: 1px solid #e5ebe4; }
    .feeder-export-add-content { padding: 14px; }
    .feeder-export-program-select { min-width: min(100%, 300px); }
    .feeder-export-program-select + .ts-wrapper { min-width: min(100%, 300px); }
    .feeder-export-program-select + .ts-wrapper .ts-control { min-height: 40px; border-color: #cad6ca; border-radius: 7px; background: #fff; color: #25382b; }
    .feeder-export-field .ts-wrapper .ts-control { min-height: 40px; border-color: #cad6ca; border-radius: 7px; background: #fff; color: #25382b; }
    .feeder-export-list-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 22px 0 12px; }
    .feeder-export-list-head h2 { margin: 0; }
    .feeder-export-list-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    .feeder-export-dialog { position: fixed; inset: 0; width: min(820px, calc(100vw - 32px)); max-width: none; max-height: calc(100dvh - 40px); margin: auto; box-sizing: border-box; overflow-y: auto; padding: 0; border: 0; border-radius: 10px; box-shadow: 0 22px 70px rgba(15, 23, 42, .3); }
    .feeder-export-dialog::backdrop { background: rgba(15, 23, 42, .48); }
    .feeder-export-dialog-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 17px 20px; border-bottom: 1px solid #e5ebe4; }
    .feeder-export-dialog-header h2 { margin: 0; color: #31536b; font-size: 14px; text-transform: uppercase; }
    .feeder-export-dialog-close { border: 0; background: transparent; color: #63716a; font-size: 24px; line-height: 1; cursor: pointer; }
    .feeder-export-dialog-body { padding: 22px 26px; }
    .feeder-export-dialog-actions { display: flex; justify-content: flex-end; gap: 8px; padding: 13px 18px; border-top: 1px solid #e5ebe4; }
    .feeder-export-dialog .ts-wrapper { width: 100%; }
    .feeder-export-pagination { display: flex; justify-content: flex-end; margin-top: 10px; }
    .feeder-export-actions { display: grid; justify-items: center; gap: 6px; }
    .feeder-export-form { display: flex; flex-wrap: wrap; align-items: end; gap: 12px; }
    .feeder-export-field { display: grid; min-width: min(100%, 260px); gap: 6px; color: #526252; font-size: 12px; font-weight: 700; }
    .feeder-export-field select { min-height: 40px; padding: 8px 10px; border: 1px solid #cad6ca; border-radius: 7px; background: #fff; color: #25382b; }
    .feeder-export-button { display: inline-flex; min-height: 38px; align-items: center; justify-content: center; padding: 8px 13px; border: 1px solid #356b45; border-radius: 7px; background: #356b45; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; }
    .feeder-export-button:hover { background: #214f36; }
    .feeder-export-button:disabled { opacity: .55; cursor: not-allowed; }
    .feeder-export-button.secondary { border-color: #cad6ca; background: #fff; color: #315b3b; }
    .feeder-export-table-wrap { overflow-x: auto; }
    .feeder-export-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .feeder-export-table th, .feeder-export-table td { padding: 10px 9px; border-bottom: 1px solid #e5ebe4; text-align: left; vertical-align: middle; }
    .feeder-export-table th { background: #f5f8f1; color: #425743; font-size: 11px; text-transform: uppercase; }
    .feeder-export-status { display: inline-flex; padding: 4px 8px; border-radius: 99px; background: #fff0ed; color: #9d3829; font-size: 10px; font-weight: 800; white-space: nowrap; }
    .feeder-export-status.done { background: #e9f5e8; color: #27613b; }
    .feeder-export-status.partial { background: #fff4cc; color: #805b00; }
    .feeder-export-alert { display: none; margin-top: 13px; padding: 11px 13px; border-radius: 8px; font-size: 13px; line-height: 1.5; }
    .feeder-export-alert.visible { display: block; }
    .feeder-export-alert.error { border: 1px solid #f0b9af; background: #fff2ef; color: #8e281b; }
    .feeder-export-alert.success { border: 1px solid #b8d8bd; background: #eff9ef; color: #225d34; }
    .feeder-export-progress { display: none; margin-top: 18px; padding: 15px; border: 1px solid #dce7da; border-radius: 10px; background: #fbfdf9; }
    .feeder-export-progress.visible { display: block; }
    .feeder-export-progress-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 9px; color: #294633; font-size: 12px; font-weight: 700; }
    .feeder-export-progress-track { overflow: hidden; height: 14px; border-radius: 99px; background: #e8eee6; }
    .feeder-export-progress-bar { position: relative; width: 0; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #356b45, #8cb64e); transition: width .35s ease; }
    .feeder-export-progress-bar::after { position: absolute; inset: 0; background: linear-gradient(100deg, transparent 20%, rgba(255,255,255,.55) 50%, transparent 80%); content: ""; transform: translateX(-100%); }
    .feeder-export-progress.active .feeder-export-progress-bar::after { animation: feeder-export-shimmer 1.4s ease-in-out infinite; }
    .feeder-export-progress-head span:first-child { display: inline-flex; align-items: center; gap: 8px; }
    .feeder-export-spinner { display: none; width: 14px; height: 14px; flex: 0 0 14px; border: 2px solid #cbd9c7; border-top-color: #356b45; border-radius: 50%; animation: feeder-export-spin .8s linear infinite; }
    .feeder-export-progress.active .feeder-export-spinner { display: inline-block; }
    .feeder-export-progress-summary { margin: 10px 0 0; }
    .feeder-export-action-progress { margin: 0; }
    .feeder-export-action-progress .feeder-export-progress-head { margin-bottom: 8px; }
    .feeder-export-action-progress .feeder-export-progress-track { height: 10px; }
    [hidden] { display: none !important; }
    @keyframes feeder-export-spin { to { transform: rotate(360deg); } }
    @keyframes feeder-export-shimmer { to { transform: translateX(100%); } }
    @media (prefers-reduced-motion: reduce) {
        .feeder-export-progress-bar { transition: none; }
        .feeder-export-progress.active .feeder-export-spinner { animation-duration: 2s; }
        .feeder-export-progress.active .feeder-export-progress-bar::after { animation-duration: 3s; }
    }
    .feeder-export-failures { display: grid; gap: 8px; margin: 12px 0 0; padding: 0; list-style: none; }
    .feeder-export-failures li { padding: 10px; border-left: 3px solid #c44e3f; border-radius: 4px; background: #fff3f1; color: #5d2b25; font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
    .feeder-export-empty { padding: 18px; border: 1px dashed #cbd7ca; border-radius: 9px; color: #657365; text-align: center; font-size: 13px; }
    @media (max-width: 640px) {
        .feeder-export-card { padding: 16px; }
        .feeder-export-form { align-items: stretch; }
        .feeder-export-field, .feeder-export-button { width: 100%; }
        .feeder-export-table { min-width: 780px; }
    }
</style>

<div class="feeder-export-wrap" id="feeder-export"
     data-start-url="{{ route('feeder-export.jobs.start') }}"
     data-create-url="{{ route('feeder-export.curricula.store') }}"
     data-update-template="{{ route('feeder-export.curricula.sync', ['curriculum' => '__CURRICULUM__']) }}"
     data-step-template="{{ route('feeder-export.jobs.step', ['job' => '__JOB__']) }}"
     data-status-template="{{ route('feeder-export.jobs.status', ['job' => '__JOB__']) }}"
     data-retry-template="{{ route('feeder-export.jobs.retry', ['job' => '__JOB__']) }}">
    <section class="feeder-export-card">
        <h1>{{ $reportPage ? 'Pelaporan Export ke Feeder' : 'Export ke Feeder' }}</h1>
        <div class="feeder-export-info" role="note">
            <strong>Informasi proses ekspor</strong>
            <p>{{ $reportPage ? 'Proses ekspor mengirim data SIAKAD ke PDDikti Feeder satu data per permintaan. Setiap panggilan ke Feeder memiliki batas waktu 5 detik untuk mengurangi risiko batas waktu PHP, termasuk pada ekspor dosen pengampu yang memerlukan lebih dari satu metode. Pastikan data akademik sudah final sebelum ekspor.' : 'Pilih program studi dan kalender, lalu laporkan data SIAKAD ke PDDikti Feeder. Proses dijalankan satu data per permintaan dengan batas waktu 5 detik untuk setiap panggilan Feeder, untuk mengurangi risiko batas waktu PHP. Pastikan data akademik sudah final sebelum ekspor.' }}</p>
        </div>

        @if (session('status'))
            <div class="feeder-export-alert success visible" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="feeder-export-alert error visible" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($reportPage)
            <a class="feeder-export-button secondary" href="{{ route('feeder-export.index', ['prodi' => $programId]) }}">Kembali ke daftar kurikulum</a>
            <p class="feeder-export-muted"><strong>Program studi:</strong> {{ $program->nama_program_studi }} ({{ $program->nama_jenjang_pendidikan }})</p>
        @else
            @if (auth()->user()->role === 'admin')
                <form method="GET" action="{{ route('feeder-export.index') }}" class="feeder-export-form">
                    <label class="feeder-export-field">
                        <span>Program studi</span>
                        <select name="prodi" class="feeder-export-program-select" data-searchable-select="filter" required onchange="this.form.submit()">
                            <option value="">Pilih program studi</option>
                            @foreach ($programs as $item)
                                <option value="{{ $item->id_prodi }}" @selected($programId === (string) $item->id_prodi)>
                                    {{ $item->nama_program_studi }} ({{ $item->nama_jenjang_pendidikan }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <noscript><button class="feeder-export-button" type="submit">Pilih</button></noscript>
                </form>
            @elseif ($program)
                <p class="feeder-export-muted"><strong>Program studi:</strong> {{ $program->nama_program_studi }} ({{ $program->nama_jenjang_pendidikan }})</p>
            @endif
        @endif

        @if ($programId !== '' && ! $reportPage)
            <div class="feeder-export-warning" role="note">
                <strong>Perhatian:</strong> ekspor ini mengirim data ke server eksternal Feeder. Ikuti urutan ekspor pada tabel. Data mata kuliah, kelas, dan peserta umumnya dilaporkan setelah KRS final; data dosen pengampu, nilai, dan aktivitas mahasiswa setelah nilai final. Ekspor aktivitas mahasiswa dan dosen pembimbing tetap dilakukan realtime dari menu Aktivitas Mahasiswa, mengikuti SIAKAD legacy.
            </div>

            <div class="feeder-export-list-head">
                <h2>Kurikulum yang tersedia</h2>
                <div class="feeder-export-list-actions">
                    @if (auth()->user()->role === 'admin')
                        <button type="button" class="feeder-export-button secondary" data-open-dialog="feeder-sync-curriculum-dialog">Sinkron kurikulum</button>
                    @endif
                    <button type="button" class="feeder-export-button" data-open-dialog="feeder-add-curriculum-dialog">Pilih kurikulum</button>
                </div>
            </div>
            @if ($curricula->isEmpty())
                <div class="feeder-export-empty">Belum ada kurikulum yang tercatat untuk program studi ini.</div>
            @else
                <div class="feeder-export-table-wrap">
                    <table class="feeder-export-table" data-simple-search-table>
                        <thead>
                            <tr>
                                <th rowspan="2">No.</th>
                                <th rowspan="2">Nama kurikulum</th>
                                <th rowspan="2">Prodi</th>
                                <th rowspan="2">Mulai berlaku</th>
                                <th colspan="3">Aturan jumlah SKS</th>
                                <th colspan="2">Jumlah SKS mata kuliah</th>
                                <th rowspan="2">Proses</th>
                            </tr>
                            <tr><th>Lulus</th><th>Wajib</th><th>Pilihan</th><th>Wajib</th><th>Pilihan</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($curricula as $curriculum)
                                <tr>
                                    <td>
                                        {{ $curricula->firstItem() + $loop->index }}
                                    </td>
                                    <td>{{ $curriculum->nama_kurikulum }}</td>
                                    <td>{{ $curriculum->nama_program_studi ?: $program->nama_program_studi }}</td>
                                    <td>{{ $curriculum->semester_mulai_berlaku }} ({{ $curriculum->id_semester }})</td>
                                    <td>{{ $curriculum->jumlah_sks_lulus ?? '-' }}</td>
                                    <td>{{ $curriculum->jumlah_sks_wajib ?? $curriculum->jumlah_sks_mata_kuliah_wajib ?? '-' }}</td>
                                    <td>{{ $curriculum->jumlah_sks_pilihan ?? $curriculum->jumlah_sks_mata_kuliah_pilihan ?? '-' }}</td>
                                    <td>{{ $curriculum->jumlah_sks_mata_kuliah_wajib ?? $curriculum->jumlah_sks_wajib ?? '-' }}</td>
                                    <td>{{ $curriculum->jumlah_sks_mata_kuliah_pilihan ?? $curriculum->jumlah_sks_pilihan ?? '-' }}</td>
                                    <td>
                                        <div class="feeder-export-actions">
                                            <button type="button" class="feeder-export-button secondary"
                                                    data-edit-curriculum="{{ $curriculum->id_kurikulum }}"
                                                    data-selected-calendar="{{ $calendars->firstWhere('tahun', $curriculum->id_semester)->id_kalender ?? '' }}">
                                                Edit
                                            </button>
                                            <a class="feeder-export-button" href="{{ route('feeder-export.reports', ['curriculum' => $curriculum->id_kurikulum]) }}">Pelaporan</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="feeder-export-pagination">{{ $curricula->links('vendor.pagination.datatables') }}</div>
            @endif
            <dialog id="feeder-add-curriculum-dialog" class="feeder-export-dialog" aria-labelledby="feeder-add-curriculum-title">
                <div class="feeder-export-dialog-header">
                    <h2 id="feeder-add-curriculum-title">Tambah data pelaporan kurikulum</h2>
                    <button type="button" class="feeder-export-dialog-close" data-close-dialog aria-label="Tutup">&times;</button>
                </div>
                <form id="create-curriculum-form">
                    @csrf
                    <div class="feeder-export-dialog-body">
                        <input type="hidden" name="program_id" value="{{ $programId }}">
                        <label class="feeder-export-field">
                            <span>Tahun akademik *</span>
                            <select name="calendar_id" data-searchable-select data-placeholder="Pilih tahun akademik" required>
                                <option value="">Pilih tahun akademik</option>
                                @foreach ($calendars as $calendar)
                                    <option value="{{ $calendar->id_kalender }}">{{ $calendar->namatahun ?: ($calendar->tahun.' '.$calendar->periode) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="feeder-export-alert" data-create-message role="status"></div>
                        <div class="feeder-export-progress feeder-export-action-progress" data-action-progress data-progress-title="Menambahkan kurikulum" aria-live="polite" hidden>
                            <div class="feeder-export-progress-head">
                                <span><i class="feeder-export-spinner" aria-hidden="true"></i><span data-action-progress-label>Menyiapkan proses...</span></span>
                                <span><span data-action-progress-value>0</span>%</span>
                            </div>
                            <div class="feeder-export-progress-track" role="progressbar" aria-label="Progres tambah kurikulum" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="feeder-export-progress-bar" data-action-progress-bar></div>
                            </div>
                        </div>
                    </div>
                    <div class="feeder-export-dialog-actions">
                        <button type="button" class="feeder-export-button secondary" data-close-dialog>Batal</button>
                        <button class="feeder-export-button" type="submit">Tambah kurikulum</button>
                    </div>
                </form>
            </dialog>
            <dialog id="feeder-edit-curriculum-dialog" class="feeder-export-dialog" aria-labelledby="feeder-edit-curriculum-title">
                <div class="feeder-export-dialog-header">
                    <h2 id="feeder-edit-curriculum-title">Edit data pelaporan kurikulum</h2>
                    <button type="button" class="feeder-export-dialog-close" data-close-dialog aria-label="Tutup">&times;</button>
                </div>
                <form id="edit-curriculum-form">
                    @csrf
                    <div class="feeder-export-dialog-body">
                        <input type="hidden" name="program_id" value="{{ $programId }}">
                        <label class="feeder-export-field">
                            <span>Tahun akademik *</span>
                            <select name="calendar_id" data-searchable-select data-placeholder="Pilih tahun akademik" required>
                                <option value="">Pilih tahun akademik</option>
                                @foreach ($calendars as $calendar)
                                    <option value="{{ $calendar->id_kalender }}">{{ $calendar->namatahun ?: ($calendar->tahun.' '.$calendar->periode) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="feeder-export-alert" data-edit-message role="status"></div>
                        <div class="feeder-export-progress feeder-export-action-progress" data-action-progress data-progress-title="Memperbarui kurikulum" aria-live="polite" hidden>
                            <div class="feeder-export-progress-head">
                                <span><i class="feeder-export-spinner" aria-hidden="true"></i><span data-action-progress-label>Menyiapkan proses...</span></span>
                                <span><span data-action-progress-value>0</span>%</span>
                            </div>
                            <div class="feeder-export-progress-track" role="progressbar" aria-label="Progres update kurikulum" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                <div class="feeder-export-progress-bar" data-action-progress-bar></div>
                            </div>
                        </div>
                    </div>
                    <div class="feeder-export-dialog-actions">
                        <button type="button" class="feeder-export-button secondary" data-close-dialog>Batal</button>
                        <button class="feeder-export-button" type="submit">UPDATE KURIKULUM</button>
                    </div>
                </form>
            </dialog>
            @if (auth()->user()->role === 'admin')
                <dialog id="feeder-sync-curriculum-dialog" class="feeder-export-dialog" aria-labelledby="feeder-sync-curriculum-title">
                    <div class="feeder-export-dialog-header">
                        <h2 id="feeder-sync-curriculum-title">Sinkron perubahan kurikulum</h2>
                        <button type="button" class="feeder-export-dialog-close" data-close-dialog aria-label="Tutup">&times;</button>
                    </div>
                    <form id="sync-curriculum-form">
                        @csrf
                        <div class="feeder-export-dialog-body">
                            <input type="hidden" name="program_id" value="{{ $programId }}">
                            <input type="hidden" name="admin_sync" value="1">
                            <label class="feeder-export-field">
                                <span>Kurikulum *</span>
                                <select name="curriculum_id" data-searchable-select data-placeholder="Pilih kurikulum" required>
                                    <option value="">Pilih kurikulum</option>
                                    @foreach ($curriculumOptions as $option)
                                        <option value="{{ $option->id_kurikulum }}">{{ $option->nama_kurikulum }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="feeder-export-field">
                                <span>Tahun akademik *</span>
                                <select name="calendar_id" data-searchable-select data-placeholder="Pilih tahun akademik" required>
                                    <option value="">Pilih tahun akademik</option>
                                    @foreach ($calendars as $calendar)
                                        <option value="{{ $calendar->id_kalender }}">{{ $calendar->namatahun ?: ($calendar->tahun.' '.$calendar->periode) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="feeder-export-alert" data-sync-message role="status"></div>
                            <div class="feeder-export-progress feeder-export-action-progress" data-action-progress data-progress-title="Menyinkronkan kurikulum" aria-live="polite" hidden>
                                <div class="feeder-export-progress-head">
                                    <span><i class="feeder-export-spinner" aria-hidden="true"></i><span data-action-progress-label>Menyiapkan proses...</span></span>
                                    <span><span data-action-progress-value>0</span>%</span>
                                </div>
                                <div class="feeder-export-progress-track" role="progressbar" aria-label="Progres sinkronisasi kurikulum" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                    <div class="feeder-export-progress-bar" data-action-progress-bar></div>
                                </div>
                            </div>
                        </div>
                        <div class="feeder-export-dialog-actions">
                            <button type="button" class="feeder-export-button secondary" data-close-dialog>Batal</button>
                            <button class="feeder-export-button" type="submit">Sinkron kurikulum</button>
                        </div>
                    </form>
                </dialog>
            @endif
        @elseif (! $reportPage)
            <div class="feeder-export-empty">Pilih program studi untuk melihat kalender dan data pelaporan.</div>
        @endif
    </section>

    @if ($reportPage && $selectedCurriculum)
        <section class="feeder-export-card">
            <h2>Pelaporan: {{ $selectedCurriculum->nama_kurikulum }}</h2>
            <div class="feeder-export-info" role="note">
                <strong>Informasi pelaporan</strong>
                <p>Jumlah calon data dihitung dari seluruh data SIAKAD yang sesuai, tanpa batas jumlah ekspor. Satu kali klik akan memproses semua data secara bertahap sampai selesai; data yang tidak berubah dan sudah berhasil akan dilewati, sedangkan data gagal atau berubah akan diproses kembali. Setiap request mengirim satu data agar aman dari batas waktu PHP. Jika jumlahnya 0, tombol ekspor tidak tersedia dan data internal perlu diperiksa.</p>
            </div>

            @if ($exports->isEmpty())
                <div class="feeder-export-empty">Belum ada antrian ekspor untuk kurikulum ini. Sinkronkan kurikulum dengan kalender yang benar untuk menyiapkan daftar ekspor.</div>
            @else
                <div class="feeder-export-table-wrap" style="margin-top:16px">
                    <table class="feeder-export-table" data-simple-search-table>
                        <thead><tr><th>No.</th><th>Data</th><th>Data tersedia di SIAKAD</th><th>Berhasil</th><th>Gagal</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @foreach ($exports as $export)
                                @php($dataCount = $counts[$export->data] ?? null)
                                <tr data-export-row="{{ $export->id_export }}">
                                    <td>{{ $exports->firstItem() + $loop->index }}</td>
                                    <td>
                                        <strong>{{ $types[$export->data] ?? $export->data }}</strong>
                                        @if ($export->data === 'DosenPembimbing')
                                            <div class="feeder-export-muted">Diproses realtime melalui Aktivitas Mahasiswa.</div>
                                        @endif
                                    </td>
                                    <td>{{ $dataCount['realtime'] ?? false ? 'Realtime' : ($dataCount['count'] ?? 'Tidak tersedia') }}</td>
                                    <td data-export-success>{{ $export->data === 'DosenPembimbing' ? '-' : ($detailCounts[$export->id_export]['Y'] ?? 0) }}</td>
                                    <td data-export-failure>{{ $export->data === 'DosenPembimbing' ? '-' : ($detailCounts[$export->id_export]['N'] ?? 0) }}</td>
                                    <td><span class="feeder-export-status {{ $export->status === 'Y' ? 'done' : ($export->status === 'P' ? 'partial' : '') }}" data-export-status>{{ $export->status === 'Y' ? 'Sudah diekspor' : ($export->status === 'P' ? 'Sebagian Tereksport' : 'Belum diekspor') }}</span></td>
                                    <td data-export-time>{{ $export->waktu ?: '-' }}</td>
                                    <td>
                                        <button type="button" class="feeder-export-button start-export"
                                                data-export-id="{{ $export->id_export }}"
                                                data-export-type="{{ $export->data }}"
                                                data-export-status-value="{{ $export->status }}"
                                                @disabled($export->data !== 'DosenPembimbing' && (int) ($dataCount['count'] ?? 0) === 0)>
                                            {{ $export->data === 'DosenPembimbing' ? 'Catat selesai' : (in_array($export->status, ['Y', 'P'], true) ? 'Ekspor ulang' : 'Mulai ekspor') }}
                                        </button>
                                        @if ($export->data !== 'DosenPembimbing' && (int) ($dataCount['count'] ?? 0) === 0)
                                            <div class="feeder-export-muted">Data kosong</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="feeder-export-pagination">{{ $exports->links('vendor.pagination.datatables') }}</div>
            @endif

            <div class="feeder-export-progress" data-progress-box aria-live="polite">
                <div class="feeder-export-progress-head">
                    <span><i class="feeder-export-spinner" aria-hidden="true"></i><span data-progress-label>Menyiapkan ekspor...</span></span>
                    <span><span data-progress-value>0</span>%</span>
                </div>
                <div class="feeder-export-progress-track" role="progressbar" aria-label="Progress ekspor" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="feeder-export-progress-bar" data-progress-bar></div>
                </div>
                <p class="feeder-export-muted feeder-export-progress-summary" data-progress-summary></p>
                <div class="feeder-export-alert" data-job-message role="status"></div>
                <ul class="feeder-export-failures" data-failure-list></ul>
                <button type="button" class="feeder-export-button secondary" data-resume-job hidden>Lanjutkan ekspor</button>
                <button type="button" class="feeder-export-button" data-retry-job hidden>Coba ulang data yang gagal</button>
            </div>
        </section>
    @endif
</div>

<script>
(() => {
    const root = document.getElementById('feeder-export');
    if (!root) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const alert = (element, message, type = 'error') => {
        element.textContent = message;
        element.className = `feeder-export-alert visible ${type}`;
    };
    const notifyFeederError = (message) => {
        window.simakFeederExportNotifications ||= [];
        window.simakFeederExportNotifications.push(message);
        document.dispatchEvent(new CustomEvent('simak:feeder-export-error', { detail: { message } }));
    };
    const request = async (url, data = null, method = 'POST') => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            ...(data === null ? {} : { body: JSON.stringify(data) }),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message ?? `Permintaan gagal (HTTP ${response.status}).`);
        return result;
    };
    const actionProgress = (form) => {
        const box = form?.querySelector('[data-action-progress]');
        if (!box) return { start() {}, complete() {}, fail() {} };

        const bar = box.querySelector('[data-action-progress-bar]');
        const track = box.querySelector('[role="progressbar"]');
        const value = box.querySelector('[data-action-progress-value]');
        const label = box.querySelector('[data-action-progress-label]');
        let startedAt = 0;
        let timer = null;
        const setValue = (progress) => {
            const bounded = Math.max(0, Math.min(100, Math.round(progress)));
            bar.style.width = `${bounded}%`;
            value.textContent = String(bounded);
            track.setAttribute('aria-valuenow', String(bounded));
        };
        const stop = () => {
            if (timer !== null) window.clearInterval(timer);
            timer = null;
            box.classList.remove('active');
        };
        const setFormBusy = (busy) => {
            form.querySelectorAll('[data-close-dialog]').forEach((button) => {
                button.disabled = busy;
            });
        };

        return {
            start() {
                stop();
                setFormBusy(true);
                startedAt = Date.now();
                box.hidden = false;
                box.classList.add('visible', 'active');
                label.textContent = 'Menyiapkan data SIAKAD...';
                setValue(0);
                timer = window.setInterval(() => {
                    const elapsed = Date.now() - startedAt;
                    const progress = Math.min(92, elapsed / 12000 * 92);
                    setValue(progress);
                    label.textContent = progress < 25
                        ? 'Menyiapkan data SIAKAD...'
                        : progress < 75 ? 'Menghubungi dan menunggu Feeder...' : 'Menyimpan hasil ke SIAKAD...';
                }, 100);
            },
            async complete() {
                stop();
                setValue(100);
                label.textContent = `${box.dataset.progressTitle} selesai.`;
                await new Promise((resolve) => window.setTimeout(resolve, 450));
            },
            fail(message) {
                stop();
                setFormBusy(false);
                label.textContent = message || 'Proses gagal. Periksa pesan kesalahan.';
            },
        };
    };

    const openDialog = (dialog) => {
        if (!dialog || dialog.open) return;
        dialog.showModal();
        document.dispatchEvent(new CustomEvent('simak:initialize-searchable-selects', { detail: { root: dialog } }));
    };
    const setSelectValue = (select, value) => {
        if (select.tomselect) {
            select.tomselect.setValue(value);
            return;
        }
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };
    document.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.dataset.openDialog === 'feeder-add-curriculum-dialog') {
                const calendarSelect = document.querySelector('#create-curriculum-form [name="calendar_id"]');
                setSelectValue(calendarSelect, '');
            }
            openDialog(document.getElementById(button.dataset.openDialog));
        });
    });
    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
    document.querySelectorAll('.feeder-export-dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog && !dialog.querySelector('[data-action-progress].active')) dialog.close();
        });
        dialog.addEventListener('cancel', (event) => {
            if (dialog.querySelector('[data-action-progress].active')) event.preventDefault();
        });
    });

    const editForm = document.getElementById('edit-curriculum-form');
    const editDialog = document.getElementById('feeder-edit-curriculum-dialog');
    const editCalendar = editForm?.querySelector('[name="calendar_id"]');
    document.querySelectorAll('[data-edit-curriculum]').forEach((button) => {
        button.addEventListener('click', () => {
            editForm.dataset.curriculum = button.dataset.editCurriculum;
            setSelectValue(editCalendar, button.dataset.selectedCalendar);
            openDialog(editDialog);
        });
    });
    editForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submitButton = editForm.querySelector('[type="submit"]');
        const message = editForm.querySelector('[data-edit-message]');
        const progress = actionProgress(editForm);
        submitButton.disabled = true;
        message.className = 'feeder-export-alert';
        progress.start();
        try {
            const data = Object.fromEntries(new FormData(editForm).entries());
            const url = root.dataset.updateTemplate.replace('__CURRICULUM__', encodeURIComponent(editForm.dataset.curriculum));
            const result = await request(url, data);
            alert(message, result.message, 'success');
            await progress.complete();
            window.setTimeout(() => window.location.reload(), 700);
        } catch (error) {
            alert(message, error.message);
            progress.fail(error.message);
            notifyFeederError(error.message);
            submitButton.disabled = false;
        }
    });

    const createForm = document.getElementById('create-curriculum-form');
    createForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = createForm.querySelector('button[type="submit"]');
        const message = document.querySelector('[data-create-message]');
        const progress = actionProgress(createForm);
        button.disabled = true;
        message.className = 'feeder-export-alert';
        progress.start();
        try {
            const data = Object.fromEntries(new FormData(createForm).entries());
            const result = await request(root.dataset.createUrl, data);
            await progress.complete();
            window.location.assign(result.redirect);
        } catch (error) {
            alert(message, error.message);
            progress.fail(error.message);
            notifyFeederError(error.message);
            button.disabled = false;
        }
    });

    const syncForm = document.getElementById('sync-curriculum-form');
    syncForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = syncForm.querySelector('button[type="submit"]');
        const message = syncForm.querySelector('[data-sync-message]');
        const curriculumId = syncForm.querySelector('[name="curriculum_id"]')?.value;
        const progress = actionProgress(syncForm);
        button.disabled = true;
        message.className = 'feeder-export-alert';
        try {
            if (!curriculumId) throw new Error('Pilih kurikulum yang akan disinkronkan.');
            progress.start();
            const data = Object.fromEntries(new FormData(syncForm).entries());
            const url = root.dataset.updateTemplate.replace('__CURRICULUM__', encodeURIComponent(curriculumId));
            const result = await request(url, data);
            alert(message, result.message, 'success');
            await progress.complete();
            window.setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            alert(message, error.message);
            progress.fail(error.message);
            notifyFeederError(error.message);
            button.disabled = false;
        }
    });

    const progressBox = document.querySelector('[data-progress-box]');
    if (!progressBox) return;
    const progressBar = progressBox.querySelector('[data-progress-bar]');
    const progressTrack = progressBox.querySelector('[role="progressbar"]');
    const progressValue = progressBox.querySelector('[data-progress-value]');
    const progressLabel = progressBox.querySelector('[data-progress-label]');
    const progressSummary = progressBox.querySelector('[data-progress-summary]');
    const jobMessage = progressBox.querySelector('[data-job-message]');
    const failureList = progressBox.querySelector('[data-failure-list]');
    const resumeButton = progressBox.querySelector('[data-resume-job]');
    const retryButton = progressBox.querySelector('[data-retry-job]');
    const storageKey = 'simak-feeder-export-job';
    let running = false;

    const addFailure = (failure) => {
        if (!failure) return;
        const item = document.createElement('li');
        const title = document.createElement('strong');
        title.textContent = failure.identity || `ID SIAKAD ${failure.source_id}`;
        const details = document.createElement('div');
        details.textContent = `Error ${failure.error_code}: ${failure.error_desc}`;
        item.append(title, details);
        failureList.append(item);
    };

    const showFailures = (failures) => {
        failureList.replaceChildren();
        failures.forEach(addFailure);
    };

    const renderExportStatus = (row, code) => {
        const status = row?.querySelector('[data-export-status]');
        const button = row?.querySelector('.start-export');
        if (!status || !button) return;

        const isDone = code === 'Y';
        const isPartial = code === 'P';
        status.textContent = isDone ? 'Sudah diekspor' : (isPartial ? 'Sebagian Tereksport' : 'Belum diekspor');
        status.classList.toggle('done', isDone);
        status.classList.toggle('partial', isPartial);
        button.dataset.exportStatusValue = code;
        if (button.dataset.exportType !== 'DosenPembimbing') {
            button.textContent = ['Y', 'P'].includes(code) ? 'Ekspor ulang' : 'Mulai ekspor';
        }
    };

    const updateProgress = (state) => {
        const progress = Number(state.progress ?? 0);
        progressBar.style.width = `${progress}%`;
        progressValue.textContent = String(progress);
        progressTrack.setAttribute('aria-valuenow', String(progress));
        progressLabel.textContent = state.label
            ? `Ekspor ${state.label}${state.complete ? ' selesai' : ''}`
            : 'Ekspor ke Feeder';
        progressBox.classList.toggle('active', !state.complete);
        progressSummary.textContent = state.complete
            ? `${state.processed ?? 0} dari ${state.total ?? 0} data selesai; ${state.success_count ?? 0} berhasil dikirim, ${state.skipped_count ?? 0} dilewati, ${state.failure_count ?? 0} gagal.`
            : `Memeriksa dan mengirim data ke Feeder... ${state.processed ?? 0} dari ${state.total ?? 0} data; ${state.success_count ?? 0} berhasil dikirim, ${state.skipped_count ?? 0} dilewati, ${state.failure_count ?? 0} gagal.`;
        const row = document.querySelector(`[data-export-row="${CSS.escape(state.export_id ?? '')}"]`);
        if (row && state.stored_success_count !== undefined) {
            row.querySelector('[data-export-success]').textContent = String(state.stored_success_count);
            row.querySelector('[data-export-failure]').textContent = String(state.stored_failure_count ?? 0);
        }
        if (state.failures?.length) showFailures(state.failures);
        else if (state.failure) addFailure(state.failure);
        progressBox.classList.add('visible');
    };

    const jobUrl = (template, jobId) => template.replace('__JOB__', encodeURIComponent(jobId));
    const runJob = async (jobId) => {
        if (running) return;
        running = true;
        localStorage.setItem(storageKey, jobId);
        resumeButton.hidden = true;
        retryButton.hidden = true;
        jobMessage.className = 'feeder-export-alert';
        progressBox.classList.add('visible');
        progressBox.classList.add('active');
        progressLabel.textContent = 'Memulihkan status ekspor...';
        try {
            let state = await request(jobUrl(root.dataset.statusTemplate, jobId), null, 'GET');
            updateProgress(state);
            while (!state.complete) {
                state = await request(jobUrl(root.dataset.stepTemplate, jobId), {});
                updateProgress(state);
            }
            running = false;
            if (state.failure_count > 0) {
                alert(jobMessage, `Ekspor selesai dengan ${state.failure_count} data gagal. Rincian error Feeder ditampilkan di bawah.`, 'error');
                notifyFeederError(state.failures.map((failure) =>
                    `${failure.identity || `ID SIAKAD ${failure.source_id}`} — Error ${failure.error_code}: ${failure.error_desc}`
                ).join('\n'));
                retryButton.hidden = false;
                retryButton.dataset.jobId = jobId;
                localStorage.setItem(storageKey, jobId);
            } else {
                alert(jobMessage, 'Semua data sudah berhasil; data yang tidak berubah dilewati.', 'success');
                localStorage.removeItem(storageKey);
            }
            const row = document.querySelector(`[data-export-row]`);
            const targetId = state.export_id || progressBox.dataset.exportId;
            const target = targetId ? document.querySelector(`[data-export-row="${CSS.escape(targetId)}"]`) : row;
            if (target) {
                renderExportStatus(target, state.export_status
                    ?? (state.failure_count === 0 ? 'Y' : (state.success_count > 0 ? 'P' : 'N')));
                target.querySelector('[data-export-time]').textContent = state.export_time || 'Selesai';
            }
        } catch (error) {
            running = false;
            progressBox.classList.remove('active');
            progressLabel.textContent = 'Koneksi terhenti; ekspor dapat dilanjutkan.';
            alert(jobMessage, `${error.message} Job tetap tersimpan; lanjutkan kembali setelah koneksi/server siap.`);
            notifyFeederError(error.message);
            resumeButton.hidden = false;
            resumeButton.dataset.jobId = jobId;
            localStorage.setItem(storageKey, jobId);
        }
    };

    document.querySelectorAll('.start-export').forEach((button) => {
        button.addEventListener('click', async () => {
            if (['Y', 'P'].includes(button.dataset.exportStatusValue)
                && !window.confirm('Data ini sudah pernah diekspor, seluruhnya atau sebagian. Ekspor ulang dapat menghasilkan penolakan duplikasi dari Feeder. Lanjutkan?')) return;
            button.disabled = true;
            progressBox.dataset.exportId = button.dataset.exportId;
            progressBox.classList.add('visible');
            progressBox.classList.add('active');
            showFailures([]);
            progressBar.style.width = '0%';
            progressValue.textContent = '0';
            progressTrack.setAttribute('aria-valuenow', '0');
            progressLabel.textContent = 'Menyiapkan data ekspor...';
            progressSummary.textContent = 'Menghitung data SIAKAD yang akan dikirim ke Feeder...';
            try {
                const result = await request(root.dataset.startUrl, { export_id: button.dataset.exportId });
                updateProgress(result);
                if (result.complete) {
                    alert(jobMessage, result.message, 'success');
                    const row = button.closest('[data-export-row]');
                    renderExportStatus(row, result.export_status ?? 'Y');
                    row.querySelector('[data-export-time]').textContent = result.export_time || 'Selesai';
                    return;
                }
                await runJob(result.job_id);
            } catch (error) {
                progressBox.classList.remove('active');
                progressLabel.textContent = 'Proses ekspor gagal dimulai.';
                alert(jobMessage, error.message);
                notifyFeederError(error.message);
            } finally {
                button.disabled = false;
            }
        });
    });

    resumeButton.addEventListener('click', () => runJob(resumeButton.dataset.jobId));
    retryButton.addEventListener('click', async () => {
        retryButton.disabled = true;
        try {
            const result = await request(jobUrl(root.dataset.retryTemplate, retryButton.dataset.jobId), {});
            showFailures([]);
            await runJob(result.job_id);
        } catch (error) {
            alert(jobMessage, error.message);
            notifyFeederError(error.message);
        } finally {
            retryButton.disabled = false;
        }
    });

    const savedJob = localStorage.getItem(storageKey);
    if (savedJob) {
        progressBox.classList.add('visible', 'active');
        progressLabel.textContent = 'Memulihkan status ekspor...';
        request(jobUrl(root.dataset.statusTemplate, savedJob), null, 'GET').then((state) => {
            progressBox.dataset.exportId = state.export_id ?? '';
            updateProgress(state);
            if (state.complete && state.failure_count > 0) {
                alert(jobMessage, `Ekspor selesai dengan ${state.failure_count} data gagal.`, 'error');
                notifyFeederError(state.failures.map((failure) =>
                    `${failure.identity || `ID SIAKAD ${failure.source_id}`} — Error ${failure.error_code}: ${failure.error_desc}`
                ).join('\n'));
                retryButton.hidden = false;
                retryButton.dataset.jobId = savedJob;
            } else if (state.complete) {
                localStorage.removeItem(storageKey);
            } else {
                resumeButton.hidden = false;
                resumeButton.dataset.jobId = savedJob;
                alert(jobMessage, 'Ada ekspor yang belum selesai. Lanjutkan untuk meneruskan proses.', 'error');
            }
            if (state.complete) {
                const targetId = state.export_id;
                const target = targetId ? document.querySelector(`[data-export-row="${CSS.escape(targetId)}"]`) : null;
                renderExportStatus(target, state.export_status
                    ?? (state.failure_count === 0 ? 'Y' : (state.success_count > 0 ? 'P' : 'N')));
                if (target) target.querySelector('[data-export-time]').textContent = state.export_time || 'Selesai';
            }
        }).catch((error) => {
            localStorage.removeItem(storageKey);
            alert(jobMessage, error.message);
            notifyFeederError(error.message);
        });
    }
})();
</script>
@endsection
