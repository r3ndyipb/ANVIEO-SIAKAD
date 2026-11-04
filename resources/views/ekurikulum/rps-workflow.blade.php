@extends('layouts.simak')

@section('title', 'Perangkat RPS | SIAKAD')
@section('topbar_title', 'Perangkat RPS Kurikulum OBE')

@section('content')
    @php
        $base = ['curriculum' => $curriculum->id_kur];
        $assessmentFields = ['partisipasi' => 'Partisipasi', 'project' => 'Proyek', 'tugas' => 'Tugas', 'kuis' => 'Kuis', 'uts' => 'UTS', 'uas' => 'UAS'];
        $lecturerLabels = $lecturers->mapWithKeys(function ($lecturer): array {
            $label = \App\Support\LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang);

            return array_filter([
                (string) $lecturer->id_dosen => $label,
                filled($lecturer->nidn) ? (string) $lecturer->nidn : null => $label,
            ], fn ($value, $key) => $key !== null, ARRAY_FILTER_USE_BOTH);
        });
        $mapColumns = [['label' => 'CPL', 'nodes' => []], ['label' => 'CPMK', 'nodes' => []], ['label' => 'Sub-CPMK', 'nodes' => []]];
        $mapEdges = [];
        $cpmkNextByCpl = [];
        $subcpmkNextByCpmk = [];
        foreach ($rpsItems as $item) {
            $course = $item['course'];
            $data = $item['data'];
            $code = $course->kode_mk_live ?: $course->kode_mk;
            $courseCplIds = $data['courseCplMaps']->pluck('id_cpl')->map('strval')->all();
            foreach ($cpls->whereIn('id_cpl', $courseCplIds) as $cpl) {
                $mapColumns[0]['nodes'][] = ['id' => 'cpl-'.$course->id_mk_draft.'-'.$cpl->id_cpl, 'code' => $cpl->kode_cpl, 'name' => $code.' · '.$cpl->deskripsi_cpl];
            }
            foreach ($data['cpmks'] as $cpmk) {
                $nodeId = 'cpmk-'.$course->id_mk_draft.'-'.$cpmk->id_cpmk;
                $mapColumns[1]['nodes'][] = ['id' => $nodeId, 'code' => $cpmk->kode_cpmk, 'name' => $code.' · '.$cpmk->deskripsi_cpmk];
                foreach ($data['cpmkMaps']->where('id_cpmk', $cpmk->id_cpmk) as $mapping) {
                    $mapEdges[] = ['source' => 'cpl-'.$course->id_mk_draft.'-'.$mapping->id_cpl, 'target' => $nodeId];
                    $lastDigit = substr((string) $cpmk->kode_cpmk, -1);
                    $next = ctype_digit($lastDigit) ? (int) $lastDigit + 1 : 1;
                    $cpmkNextByCpl[(string) $mapping->id_cpl] = max($cpmkNextByCpl[(string) $mapping->id_cpl] ?? 1, $next);
                }
            }
            foreach ($data['subcpmks'] as $subcpmk) {
                $nodeId = 'sub-'.$course->id_mk_draft.'-'.$subcpmk->id_subcpmk;
                $mapColumns[2]['nodes'][] = ['id' => $nodeId, 'code' => $subcpmk->kode_subcpmk, 'name' => $code.' · '.$subcpmk->deskripsi_subcpmk];
                foreach ($data['subcpmkMaps']->where('id_subcpmk', $subcpmk->id_subcpmk) as $mapping) {
                    $mapEdges[] = ['source' => 'cpmk-'.$course->id_mk_draft.'-'.$mapping->id_cpmk, 'target' => $nodeId];
                    $lastDigit = substr((string) $subcpmk->kode_subcpmk, -1);
                    $next = ctype_digit($lastDigit) ? (int) $lastDigit + 1 : 1;
                    $subcpmkNextByCpmk[(string) $mapping->id_cpmk] = max($subcpmkNextByCpmk[(string) $mapping->id_cpmk] ?? 1, $next);
                }
            }
        }
        $cpmkMapColumns = array_slice($mapColumns, 0, 2);
        $cpmkMapEdges = array_values(array_filter($mapEdges, fn ($edge) => str_starts_with($edge['target'], 'cpmk-')));
        $subcpmkMapColumns = array_slice($mapColumns, 1, 2);
        $subcpmkMapEdges = array_values(array_filter($mapEdges, fn ($edge) => str_starts_with($edge['target'], 'sub-')));
        $assessmentMapColumns = [['label' => 'Sub-CPMK', 'nodes' => []], ['label' => 'Bobot Penilaian', 'nodes' => []]];
        $assessmentMapEdges = [];
        $completenessMapColumns = [['label' => 'Mata Kuliah', 'nodes' => []], ['label' => 'Kelengkapan RPS', 'nodes' => []], ['label' => 'Materi Mingguan', 'nodes' => []]];
        $completenessMapEdges = [];
        foreach ($rpsItems as $item) {
            $course = $item['course'];
            $data = $item['data'];
            $code = $course->kode_mk_live ?: $course->kode_mk;
            $courseNodeId = 'rps-course-'.$course->id_mk_draft;
            $completenessNodeId = 'rps-completeness-'.$course->id_mk_draft;
            $completenessMapColumns[0]['nodes'][] = ['id' => $courseNodeId, 'code' => $code, 'name' => $course->nama_mk];
            $completenessMapColumns[1]['nodes'][] = [
                'id' => $completenessNodeId,
                'code' => $data['completeness'] ? 'Tersimpan' : 'Belum ada',
                'name' => 'Otorisasi dan informasi RPS',
            ];
            $completenessMapEdges[] = ['source' => $courseNodeId, 'target' => $completenessNodeId];
            foreach ($data['subcpmks'] as $subcpmk) {
                $subNodeId = 'sub-'.$course->id_mk_draft.'-'.$subcpmk->id_subcpmk;
                $weights = $data['assessments']->get($subcpmk->id_subcpmk);
                $weightSummary = collect($assessmentFields)->map(function ($label, $field) use ($weights) {
                    return $label.' '.($weights?->{$field} ?? 0).'%';
                })->implode(' · ');
                $assessmentNodeId = 'assessment-'.$course->id_mk_draft.'-'.$subcpmk->id_subcpmk;
                $assessmentMapColumns[0]['nodes'][] = ['id' => $subNodeId, 'code' => $subcpmk->kode_subcpmk, 'name' => $code.' · '.$subcpmk->deskripsi_subcpmk];
                $assessmentMapColumns[1]['nodes'][] = ['id' => $assessmentNodeId, 'code' => 'Bobot '.($weights ? 'diatur' : 'belum diatur'), 'name' => $weightSummary];
                $assessmentMapEdges[] = ['source' => $subNodeId, 'target' => $assessmentNodeId];
            }
            foreach ($data['materials'] as $material) {
                $materialNodeId = 'rps-material-'.$material->id_materi;
                $completenessMapColumns[2]['nodes'][] = [
                    'id' => $materialNodeId,
                    'code' => $material->minggu_ke,
                    'name' => $material->materi_pembelajaran,
                ];
                $completenessMapEdges[] = ['source' => $completenessNodeId, 'target' => $materialNodeId];
            }
        }
        $stepLocks = [
            'rps-cpmk' => $rpsItems->isEmpty() || $rpsItems->every(fn ($item) => $item['workflow']['cpmks']['locked']),
            'rps-subcpmk' => $rpsItems->isEmpty() || $rpsItems->every(fn ($item) => $item['workflow']['subcpmks']['locked']),
            'rps-assessment' => $rpsItems->isEmpty() || $rpsItems->every(fn ($item) => $item['workflow']['assessments']['locked']),
            'rps-completeness' => $rpsItems->isEmpty() || $rpsItems->every(fn ($item) => $item['workflow']['completeness']['locked']),
        ];
    @endphp

    <div class="page-heading ekur-page-heading">
        <div class="ekur-page-heading-copy"><p class="eyebrow">E-Kurikulum OBE · {{ $curriculum->nama_program_studi }}</p><h1 class="ekur-page-title">Perangkat RPS</h1><div class="module-alert" role="note"><p class="subtitle">{{ $curriculum->nama_kur }} · Tahun {{ $curriculum->tahun }}</p></div></div>
        <a class="button button-secondary" href="{{ route('ekurikulum.curricula.show', $curriculum->id_kur) }}"><span data-lucide="arrow-left" aria-hidden="true"></span> Kembali ke kurikulum</a>
    </div>
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif

    <div class="alert alert-info mb-4" role="note"><strong>{{ $rpsItems->count() }} mata kuliah tersebar</strong><p class="subtext mb-0">Input, perubahan, dan penghapusan RPS dilakukan pada tahapan wizard di bawah.</p></div>
    @if ($canEdit)
        <details class="table-wrap mb-4">
            <summary class="button button-secondary">Opsi Excel RPS (opsional)</summary>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                <a class="button button-secondary" href="{{ route('ekurikulum.template', 'rps') }}" data-page-loader-skip><span data-lucide="file-down" aria-hidden="true"></span> Unduh template</a>
                <button class="button button-secondary" type="button" data-dialog-open="ekur-rps-import-dialog">Unggah Excel</button>
            </div>
        </details>
        <dialog class="ekur-dialog ekur-upload-dialog" id="ekur-rps-import-dialog" aria-labelledby="ekur-rps-import-title">
            <div class="ekur-dialog-header">
                <div><p class="eyebrow">Impor data opsional</p><h2 id="ekur-rps-import-title">Unggah template RPS</h2><p class="subtext">Gunakan file Excel yang diunduh dari template RPS.</p></div>
                <button class="ekur-dialog-close" type="button" data-dialog-close aria-label="Tutup dialog">Tutup</button>
            </div>
            <form method="post" action="{{ route('ekurikulum.import', [$curriculum->id_kur, 'rps']) }}" enctype="multipart/form-data" data-ekur-upload-form>
                @csrf
                <label class="ekur-dropzone" data-ekur-dropzone>
                    <input class="ekur-dropzone-input" type="file" name="file" accept=".xlsx" required aria-label="Pilih template RPS">
                    <span class="ekur-dropzone-icon" data-lucide="file-down" aria-hidden="true"></span>
                    <strong>Seret file Excel ke area ini</strong>
                    <span>atau klik untuk memilih file .xlsx</span>
                    <small data-ekur-file-name>Belum ada file dipilih</small>
                </label>
                <p class="ekur-upload-error" data-ekur-upload-error role="alert" hidden></p>
                <div class="ekur-modal-actions">
                    <button class="button button-secondary" type="button" data-dialog-close>Batal</button>
                    <button class="button button-primary" type="submit">Unggah data</button>
                </div>
            </form>
        </dialog>
    @endif

    @if ($rpsItems->isEmpty())
        <div class="table-wrap" role="status">Belum ada mata kuliah yang memiliki sebaran semester. Atur sebaran pada halaman kurikulum terlebih dahulu.</div>
    @else
        <div class="ekur-wizard" data-step-wizard data-wizard-id="rps-{{ $curriculum->id_kur }}" data-wizard-type="rps">
            <div class="ekur-wizard-heading"><div><p class="eyebrow">Penyusunan bertahap</p><h2>Wizard Rencana Pembelajaran Semester</h2><p class="subtext">Setiap tahap mengelompokkan mata kuliah sesuai alur kerja SIAKAD.</p></div><span class="ekur-wizard-progress-label" data-wizard-progress-label></span></div>
            <div class="ekur-wizard-progress" aria-hidden="true"><span data-wizard-progress></span></div>
            <nav class="ekur-wizard-steps" aria-label="Tahapan penyusunan RPS">
                @foreach ([
                    'rps-info' => ['Info Sebaran MK', 'Tinjau sebaran dan prasyarat'],
                    'rps-cpmk' => ['Pemetaan CPMK', 'Petakan capaian mata kuliah ke CPL'],
                    'rps-subcpmk' => ['Struktur Sub-CPMK', 'Turunkan CPMK per mata kuliah'],
                    'rps-assessment' => ['Metode Penilaian', 'Atur bobot setiap Sub-CPMK'],
                    'rps-completeness' => ['Kelengkapan RPS', 'Lengkapi otorisasi dan materi'],
                ] as $stepId => [$stepTitle, $stepDescription])
                    @php $locked = $stepLocks[$stepId] ?? false; @endphp
                    <a class="ekur-wizard-step {{ $locked ? 'is-locked' : '' }}" href="#{{ $stepId }}" data-wizard-step="{{ $stepId }}" data-wizard-locked="{{ $locked ? 'true' : 'false' }}" aria-disabled="{{ $locked ? 'true' : 'false' }}" aria-current="false"><span class="ekur-wizard-number">{{ $loop->iteration }}</span><span class="ekur-wizard-step-copy"><strong>{{ $stepTitle }}</strong><small>{{ $stepDescription }}</small></span></a>
                @endforeach
            </nav>
        </div>

        <section class="table-wrap mb-4" id="rps-info" data-wizard-panel="rps-info" data-wizard-owner="rps-{{ $curriculum->id_kur }}">
            <h2 class="h4">1. Info Sebaran MK</h2>
            <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>No</th><th>Kode MK</th><th>Nama MK</th><th>SKS</th><th>Semester</th><th>Prasyarat</th><th>Proses</th></tr></thead><tbody>
                @foreach ($rpsItems as $item)
                    @php $course = $item['course']; $prerequisites = array_filter(array_map('trim', explode(',', (string) $course->prasyarat_mk))); $prerequisiteCodes = $courses->whereIn('id_mk_draft', $prerequisites)->map(fn ($row) => $row->kode_mk_live ?: $row->kode_mk)->implode(', '); @endphp
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $course->kode_mk_live ?: $course->kode_mk }}</td><td>{{ $course->nama_mk }}</td><td>{{ $course->sks }}</td><td>{{ $course->semester }}</td><td>{{ $prerequisiteCodes ?: 'Tanpa prasyarat' }}</td><td class="rps-process-actions"><a class="button button-secondary button-compact" href="{{ route('ekurikulum.rps.export', [$curriculum->id_kur, $course->id_mk_draft]) }}" data-page-loader-skip>Excel</a><a class="button button-secondary button-compact" href="{{ route('ekurikulum.rps.print', [$curriculum->id_kur, $course->id_mk_draft]) }}" target="_blank" rel="noopener">Cetak</a></td></tr>
                @endforeach
            </tbody></table></div>
            @include('ekurikulum.partials.relationship-map', ['mapId' => 'rps-'.$curriculum->id_kur.'-integration', 'mapTitle' => 'Peta Integrasi RPS', 'mapDescription' => 'CPL → CPMK → Sub-CPMK untuk seluruh mata kuliah.', 'mapFilename' => 'Peta-RPS-'.$curriculum->id_kur, 'mapColumns' => $mapColumns, 'mapEdges' => $mapEdges])
        </section>

        <section class="table-wrap mb-4" id="rps-cpmk" data-wizard-panel="rps-cpmk" data-wizard-owner="rps-{{ $curriculum->id_kur }}">
            <h2 class="h4">2. Pemetaan CPMK</h2><div class="alert alert-info">Setiap CPL yang dibebankan pada mata kuliah harus tercakup oleh CPMK.</div>
            @foreach ($rpsItems as $item)
                @php $course = $item['course']; $data = $item['data']; $workflow = $item['workflow']; $courseCpls = $cpls->whereIn('id_cpl', $data['courseCplMaps']->pluck('id_cpl')->all()); @endphp
                <section class="rps-course-block"><div class="rps-course-heading"><div><strong>{{ $course->kode_mk_live ?: $course->kode_mk }} · {{ $course->nama_mk }}</strong><span class="subtext">Semester {{ $course->semester }} · {{ $course->sks }} SKS</span></div>@if ($workflow['cpmks']['locked'])<span class="ekur-status-badge is-blocked">Terkunci · CPL belum dibebankan</span>@endif</div>
                    @if ($canEdit && !$workflow['cpmks']['locked'])
                        <details class="mb-3">
                            <summary class="button button-primary button-compact">Tambah CPMK</summary>
                            <form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'cpmks']) }}" class="row g-3 mt-2" data-rps-code-form="cpmk">
                                @csrf
                                <input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}">
                                <div class="col-md-3"><label class="form-label">Kode CPMK (otomatis)</label><input class="form-control" data-rps-generated-code="cpmk" name="kode_cpmk" maxlength="15" placeholder="Otomatis saat CPL dipilih" readonly></div>
                                <div class="col-md-9"><label class="form-label">Rumusan CPMK</label><input class="form-control" name="deskripsi_cpmk" required></div>
                                <div class="col-12"><label class="form-label">CPL yang dibebankan</label><select class="form-control" data-rps-code-source="cpl" name="id_cpl[]" multiple required>
                                    @foreach ($courseCpls as $cpl)<option value="{{ $cpl->id_cpl }}" data-code="{{ $cpl->kode_cpl }}" data-next="{{ $cpmkNextByCpl[(string) $cpl->id_cpl] ?? 1 }}">{{ $cpl->kode_cpl }} · {{ $cpl->deskripsi_cpl }}</option>@endforeach
                                </select><small class="subtext">Kode mengikuti CPL pertama yang dipilih.</small></div>
                                <div class="col-12"><button class="button button-primary">Simpan CPMK</button></div>
                            </form>
                        </details>
                    @endif
                    <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Kode</th><th>Rumusan CPMK</th><th>CPL</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead><tbody>
                        @forelse ($data['cpmks'] as $cpmk)@php $mappedCplIds = $data['cpmkMaps']->where('id_cpmk', $cpmk->id_cpmk)->pluck('id_cpl')->all(); $lockedCpmk = $data['subcpmkMaps']->contains('id_cpmk', $cpmk->id_cpmk); @endphp<tr><td>{{ $cpmk->kode_cpmk }}</td><td>{{ $cpmk->deskripsi_cpmk }}</td><td>{{ $courseCpls->whereIn('id_cpl', $mappedCplIds)->pluck('kode_cpl')->implode(', ') ?: 'Belum dipetakan' }}</td>@if ($canEdit)<td>@if ($lockedCpmk)<span class="ekur-status-badge is-blocked">Terkunci · digunakan Sub-CPMK</span>@else                        <details><summary class="button button-secondary button-compact">Ubah</summary><form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'cpmks', 'record' => $cpmk->id_cpmk]) }}" class="mt-2">@csrf @method('put')<input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}"><label class="form-label">Kode CPMK (otomatis)</label><input class="form-control mb-2" value="{{ $cpmk->kode_cpmk }}" readonly><label class="form-label">Rumusan</label><textarea class="form-control mb-2" name="deskripsi_cpmk" required>{{ $cpmk->deskripsi_cpmk }}</textarea><label class="form-label">CPL</label><select class="form-control mb-2" name="id_cpl[]" multiple required>@foreach ($courseCpls as $cpl)<option value="{{ $cpl->id_cpl }}" @selected(in_array($cpl->id_cpl, $mappedCplIds))>{{ $cpl->kode_cpl }}</option>@endforeach</select><button class="button button-primary button-compact">Simpan</button></form></details><form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'cpmks', 'record' => $cpmk->id_cpmk, 'mk' => $course->id_mk_draft]) }}" class="mt-2" onsubmit="return confirm('Hapus CPMK ini?')">@csrf @method('delete')<button class="button button-danger button-compact">Hapus</button></form>@endif</td>@endif</tr>
                        @empty<tr><td colspan="{{ $canEdit ? 4 : 3 }}">Belum ada CPMK.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @endforeach
            @include('ekurikulum.partials.relationship-map', ['mapId' => 'rps-'.$curriculum->id_kur.'-cpmk', 'mapTitle' => 'Peta Pemetaan CPMK', 'mapDescription' => 'Hubungan CPL yang dibebankan dengan CPMK tiap mata kuliah.', 'mapFilename' => 'Peta-RPS-CPMK-'.$curriculum->id_kur, 'mapColumns' => $cpmkMapColumns, 'mapEdges' => $cpmkMapEdges])
        </section>

        <section class="table-wrap mb-4" id="rps-subcpmk" data-wizard-panel="rps-subcpmk" data-wizard-owner="rps-{{ $curriculum->id_kur }}">
            <h2 class="h4">3. Struktur Sub-CPMK</h2><div class="alert alert-info">Setiap CPMK harus diturunkan menjadi Sub-CPMK pada mata kuliah yang sama.</div>
            @foreach ($rpsItems as $item)
                @php $course = $item['course']; $data = $item['data']; $workflow = $item['workflow']; @endphp
                <section class="rps-course-block"><div class="rps-course-heading"><div><strong>{{ $course->kode_mk_live ?: $course->kode_mk }} · {{ $course->nama_mk }}</strong><span class="subtext">Semester {{ $course->semester }}</span></div>@if ($workflow['subcpmks']['locked'])<span class="ekur-status-badge is-blocked">Terkunci · pemetaan CPMK belum lengkap</span>@endif</div>
                    @if ($canEdit && !$workflow['subcpmks']['locked'])
                        <details class="mb-3">
                            <summary class="button button-primary button-compact">Tambah Sub-CPMK</summary>
                            <form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'subcpmks']) }}" class="row g-3 mt-2" data-rps-code-form="subcpmk">
                                @csrf
                                <input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}">
                                <div class="col-md-3"><label class="form-label">Kode Sub-CPMK (otomatis)</label><input class="form-control" data-rps-generated-code="subcpmk" name="kode_subcpmk" maxlength="10" placeholder="Otomatis saat CPMK dipilih" readonly></div>
                                <div class="col-md-9"><label class="form-label">Rumusan Sub-CPMK</label><input class="form-control" name="deskripsi_subcpmk" required></div>
                                <div class="col-12"><label class="form-label">CPMK yang dituju</label><select class="form-control" data-rps-code-source="cpmk" name="id_cpmk[]" multiple required>
                                    @foreach ($data['cpmks'] as $cpmk)<option value="{{ $cpmk->id_cpmk }}" data-code="{{ $cpmk->kode_cpmk }}" data-next="{{ $subcpmkNextByCpmk[(string) $cpmk->id_cpmk] ?? 1 }}">{{ $cpmk->kode_cpmk }} · {{ $cpmk->deskripsi_cpmk }}</option>@endforeach
                                </select><small class="subtext">Kode mengikuti CPMK pertama yang dipilih dan menyesuaikan batas kode 10 karakter.</small></div>
                                <div class="col-12"><button class="button button-primary">Simpan Sub-CPMK</button></div>
                            </form>
                        </details>
                    @endif
                    <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Kode</th><th>Rumusan Sub-CPMK</th><th>CPMK</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead><tbody>
                        @forelse ($data['subcpmks'] as $subcpmk)@php $mappedCpmkIds = $data['subcpmkMaps']->where('id_subcpmk', $subcpmk->id_subcpmk)->pluck('id_cpmk')->all(); $lockedSub = $data['assessments']->has($subcpmk->id_subcpmk); @endphp<tr><td>{{ $subcpmk->kode_subcpmk }}</td><td>{{ $subcpmk->deskripsi_subcpmk }}</td><td>{{ $data['cpmks']->whereIn('id_cpmk', $mappedCpmkIds)->pluck('kode_cpmk')->implode(', ') ?: 'Belum dipetakan' }}</td>@if ($canEdit)<td>@if ($lockedSub)<span class="ekur-status-badge is-blocked">Terkunci · sudah dinilai</span>@else<details><summary class="button button-secondary button-compact">Ubah</summary><form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'subcpmks', 'record' => $subcpmk->id_subcpmk]) }}" class="mt-2">@csrf @method('put')<input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}"><label class="form-label">Kode Sub-CPMK (otomatis)</label><input class="form-control mb-2" value="{{ $subcpmk->kode_subcpmk }}" readonly><label class="form-label">Rumusan</label><textarea class="form-control mb-2" name="deskripsi_subcpmk" required>{{ $subcpmk->deskripsi_subcpmk }}</textarea><label class="form-label">CPMK</label><select class="form-control mb-2" name="id_cpmk[]" multiple required>@foreach ($data['cpmks'] as $cpmk)<option value="{{ $cpmk->id_cpmk }}" @selected(in_array($cpmk->id_cpmk, $mappedCpmkIds))>{{ $cpmk->kode_cpmk }}</option>@endforeach</select><button class="button button-primary button-compact">Simpan</button></form></details><form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'subcpmks', 'record' => $subcpmk->id_subcpmk, 'mk' => $course->id_mk_draft]) }}" class="mt-2" onsubmit="return confirm('Hapus Sub-CPMK ini?')">@csrf @method('delete')<button class="button button-danger button-compact">Hapus</button></form>@endif</td>@endif</tr>
                        @empty<tr><td colspan="{{ $canEdit ? 4 : 3 }}">Belum ada Sub-CPMK.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @endforeach
            @include('ekurikulum.partials.relationship-map', ['mapId' => 'rps-'.$curriculum->id_kur.'-subcpmk', 'mapTitle' => 'Peta Struktur Sub-CPMK', 'mapDescription' => 'Hubungan CPMK dengan Sub-CPMK tiap mata kuliah.', 'mapFilename' => 'Peta-RPS-Sub-CPMK-'.$curriculum->id_kur, 'mapColumns' => $subcpmkMapColumns, 'mapEdges' => $subcpmkMapEdges])
        </section>

        <section class="table-wrap mb-4" id="rps-assessment" data-wizard-panel="rps-assessment" data-wizard-owner="rps-{{ $curriculum->id_kur }}">
            <h2 class="h4">4. Metode Penilaian</h2><p class="subtext">Total bobot per mata kuliah harus 100%.</p>
            @foreach ($rpsItems as $item)
                @php $course = $item['course']; $data = $item['data']; $workflow = $item['workflow']; @endphp
                <section class="rps-course-block"><div class="rps-course-heading"><div><strong>{{ $course->kode_mk_live ?: $course->kode_mk }} · {{ $course->nama_mk }}</strong><span class="subtext">Total bobot: {{ number_format((float) $data['total_assessment_weight'], 2) }}%</span></div>@if ($workflow['assessments']['locked'])<span class="ekur-status-badge is-blocked">Terkunci · Sub-CPMK belum lengkap</span>@endif</div>
                    @if ($canEdit && !$workflow['assessments']['locked'])<form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'assessments']) }}" data-rps-assessment-form data-ekur-modal-form="Matriks penilaian · {{ $course->kode_mk_live ?: $course->kode_mk }}">@csrf<input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}"><div class="table-responsive"><table class="table"><thead><tr><th>Sub-CPMK</th>@foreach ($assessmentFields as $label)<th>{{ $label }} (%)</th>@endforeach</tr></thead><tbody>
                        @foreach ($data['subcpmks'] as $subcpmk)@php $weights = $data['assessments']->get($subcpmk->id_subcpmk); @endphp<tr><td>{{ $subcpmk->kode_subcpmk }} · {{ $subcpmk->deskripsi_subcpmk }}</td>@foreach ($assessmentFields as $field => $label)<td><input class="form-control" type="number" step="0.01" min="0" max="100" name="nilai[{{ $subcpmk->id_subcpmk }}][{{ $field }}]" value="{{ $weights?->{$field} ?? 0 }}" aria-label="{{ $label }} {{ $subcpmk->kode_subcpmk }}"></td>@endforeach</tr>@endforeach
                    </tbody></table></div><p class="rps-assessment-total">Total bobot <strong data-assessment-total>{{ number_format((float) $data['total_assessment_weight'], 2) }}%</strong></p><button class="button button-primary button-compact" data-assessment-save>Simpan matriks penilaian</button></form>
                    @else<div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Sub-CPMK</th>@foreach ($assessmentFields as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>@foreach ($data['subcpmks'] as $subcpmk)@php $weights = $data['assessments']->get($subcpmk->id_subcpmk); @endphp<tr><td>{{ $subcpmk->kode_subcpmk }}</td>@foreach ($assessmentFields as $field => $label)<td>{{ $weights?->{$field} ?? 0 }}%</td>@endforeach</tr>@endforeach</tbody></table></div>@endif
                </section>
            @endforeach
            @include('ekurikulum.partials.relationship-map', ['mapId' => 'rps-'.$curriculum->id_kur.'-assessment', 'mapTitle' => 'Peta Metode Penilaian', 'mapDescription' => 'Bobot penilaian pada setiap Sub-CPMK.', 'mapFilename' => 'Peta-RPS-Penilaian-'.$curriculum->id_kur, 'mapColumns' => $assessmentMapColumns, 'mapEdges' => $assessmentMapEdges])
        </section>

        <section class="table-wrap mb-4" id="rps-completeness" data-wizard-panel="rps-completeness" data-wizard-owner="rps-{{ $curriculum->id_kur }}">
            <h2 class="h4">5. Kelengkapan dan Rencana Mingguan</h2>
            @foreach ($rpsItems as $item)
                @php $course = $item['course']; $data = $item['data']; $workflow = $item['workflow']; $complete = $data['completeness']; $selectedLecturers = array_filter(explode(',', (string) ($complete->dosen_pengampu ?? ''))); @endphp
                <section class="rps-course-block"><div class="rps-course-heading"><div><strong>{{ $course->kode_mk_live ?: $course->kode_mk }} · {{ $course->nama_mk }}</strong><span class="subtext">Semester {{ $course->semester }}</span></div><div class="d-flex flex-wrap align-items-center gap-2">@if ($workflow['completeness']['locked'])<span class="ekur-status-badge is-blocked">Terkunci · relasi CPMK belum lengkap</span>@endif @if ($complete)<a class="button button-secondary button-compact" href="{{ route('ekurikulum.rps.print', [$curriculum->id_kur, $course->id_mk_draft]) }}" target="_blank" rel="noopener">Cetak RPS</a>@endif</div></div>
                    @if ($canEdit && !$workflow['completeness']['locked'])<form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'completeness']) }}" class="row g-3" data-ekur-modal-form="Kelengkapan RPS · {{ $course->kode_mk_live ?: $course->kode_mk }}">@csrf<input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}"><div class="col-md-6"><label class="form-label">Rumpun MK</label><input class="form-control" name="rumpun_mk" value="{{ $complete->rumpun_mk ?? '' }}"></div><div class="col-12"><label class="form-label">Deskripsi singkat</label><textarea class="form-control" name="deskripsi_singkat">{{ $complete->deskripsi_singkat ?? '' }}</textarea></div><div class="col-md-6"><label class="form-label">Dosen pengampu</label><select class="form-control" name="dosen_pengampu[]" multiple required>@foreach ($lecturers as $lecturer)<option value="{{ $lecturer->id_dosen }}" @selected(in_array((string) $lecturer->id_dosen, $selectedLecturers, true) || in_array((string) $lecturer->nidn, $selectedLecturers, true))>{{ $lecturerLabels[(string) $lecturer->id_dosen] }} ({{ $lecturer->nidn }})</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Bahan kajian</label><textarea class="form-control" name="bahan_kajian">{{ $complete->bahan_kajian ?? '' }}</textarea></div>@foreach (['dsn_pengembang' => 'Dosen pengembang', 'koor_rmk' => 'Koordinator RMK', 'koor_prodi' => 'Koordinator prodi', 'wd1' => 'Wakil dekan I'] as $field => $label)@php $selectedAuthority = (string) ($complete->{$field} ?? ''); @endphp<div class="col-md-3"><label class="form-label">{{ $label }}</label><select class="form-control" name="{{ $field }}"><option value="">- Pilih Dosen -</option>@foreach ($lecturers as $lecturer)<option value="{{ $lecturer->id_dosen }}" @selected($selectedAuthority === (string) $lecturer->id_dosen || $selectedAuthority === (string) $lecturer->nidn)>{{ $lecturerLabels[(string) $lecturer->id_dosen] }} ({{ $lecturer->nidn }})</option>@endforeach</select></div>@endforeach<div class="col-md-6"><label class="form-label">Pustaka utama</label><textarea class="form-control" name="pustaka_utama">{{ $complete->pustaka_utama ?? '' }}</textarea></div><div class="col-md-6"><label class="form-label">Pustaka pendukung</label><textarea class="form-control" name="pustaka_pendukung">{{ $complete->pustaka_pendukung ?? '' }}</textarea></div><div class="col-12"><button class="button button-primary button-compact">Simpan kelengkapan</button></div></form>
                    @elseif ($complete)<dl class="row"><dt class="col-md-3">Rumpun MK</dt><dd class="col-md-9">{{ $complete->rumpun_mk ?: '-' }}</dd><dt class="col-md-3">Deskripsi singkat</dt><dd class="col-md-9">{{ $complete->deskripsi_singkat ?: '-' }}</dd><dt class="col-md-3">Dosen pengampu</dt><dd class="col-md-9">{{ collect(explode(',', (string) $complete->dosen_pengampu))->map(fn ($id) => $lecturerLabels[trim($id)] ?? trim($id))->filter()->implode(', ') ?: '-' }}</dd><dt class="col-md-3">Bahan kajian</dt><dd class="col-md-9">{{ $complete->bahan_kajian ?: '-' }}</dd><dt class="col-md-3">Pustaka utama</dt><dd class="col-md-9">{{ $complete->pustaka_utama ?: '-' }}</dd><dt class="col-md-3">Pustaka pendukung</dt><dd class="col-md-9">{{ $complete->pustaka_pendukung ?: '-' }}</dd></dl>@endif
                    <div class="rps-weekly-heading"><h3 class="h5">Rencana pembelajaran per minggu</h3>@if ($canEdit && !$workflow['materials']['locked'])<details><summary class="button button-primary button-compact">Tambah materi</summary><form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'materials']) }}" class="row g-3 mt-2">@csrf<input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}"><div class="col-md-4"><label class="form-label">Minggu ke</label><select class="form-control" name="minggu_ke[]" multiple required>@for ($week = 1; $week <= 16; $week++)<option value="Minggu ke-{{ $week }}">Minggu ke-{{ $week }}</option>@endfor</select></div><div class="col-md-8"><label class="form-label">Sub-CPMK (kemampuan akhir)</label><select class="form-control" name="kemampuan_akhir[]" multiple required>@foreach ($data['subcpmks'] as $subcpmk)<option value="{{ $subcpmk->id_subcpmk }}">{{ $subcpmk->kode_subcpmk }} · {{ $subcpmk->deskripsi_subcpmk }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Indikator</label><textarea class="form-control" name="indikator" required></textarea></div><div class="col-md-6"><label class="form-label">Kriteria dan teknik penilaian</label><textarea class="form-control" name="kriteria_teknik" required></textarea></div><div class="col-md-6"><label class="form-label">Bentuk dan metode pembelajaran</label><textarea class="form-control" name="bentuk_pembelajaran" required></textarea></div><div class="col-md-6"><label class="form-label">Materi pembelajaran</label><textarea class="form-control" name="materi_pembelajaran" required></textarea></div><div class="col-md-8"><label class="form-label">Pustaka acuan</label><select class="form-control" name="pustaka_pilihan[]" multiple required><option value="Pustaka Utama">Pustaka Utama</option><option value="Pustaka Pendukung">Pustaka Pendukung</option></select></div><div class="col-md-4"><label class="form-label">Bobot (%)</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="bobot_penilaian" required></div><div class="col-12"><button class="button button-primary">Simpan materi</button></div></form></details>@endif</div>
                    <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Minggu</th><th>Kemampuan akhir</th><th>Indikator / kriteria</th><th>Bentuk pembelajaran</th><th>Materi</th><th>Pustaka</th><th>Bobot</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead><tbody>
                        @forelse ($data['materials'] as $material)
                            @php
                                $selectedWeeks = array_filter(array_map('trim', explode(',', (string) $material->minggu_ke)));
                                $selectedSubIds = array_filter(array_map('trim', explode(',', (string) $material->kemampuan_akhir)));
                                $selectedLibraries = array_filter(array_map('trim', explode(',', (string) $material->pustaka_pilihan)));
                                $selectedSubLabels = $data['subcpmks']->whereIn('id_subcpmk', $selectedSubIds)->map(fn ($sub) => $sub->kode_subcpmk)->implode(', ');
                                $abilityText = $selectedSubLabels ?: $material->kemampuan_akhir;
                            @endphp
                            <tr><td>{{ $material->minggu_ke }}</td><td>{{ $abilityText }}</td><td>{{ $material->indikator }}<span class="subtext">{{ $material->kriteria_teknik }}</span></td><td>{{ $material->bentuk_pembelajaran }}</td><td>{{ $material->materi_pembelajaran }}</td><td>{{ $material->pustaka_pilihan }}</td><td>{{ $material->bobot_penilaian }}%</td>
                                @if ($canEdit)<td><details><summary class="button button-secondary button-compact">Ubah</summary><form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'materials', 'record' => $material->id_materi]) }}" class="mt-2">@csrf @method('put')<input type="hidden" name="id_mk_draft" value="{{ $course->id_mk_draft }}">
                                    <label class="form-label">Minggu ke</label><select class="form-control mb-2" name="minggu_ke[]" multiple required>@for ($week = 1; $week <= 16; $week++)@php $weekLabel = 'Minggu ke-'.$week; @endphp<option value="{{ $weekLabel }}" @selected(in_array($weekLabel, $selectedWeeks, true))>{{ $weekLabel }}</option>@endfor</select>
                                    <label class="form-label">Sub-CPMK (kemampuan akhir)</label><select class="form-control mb-2" name="kemampuan_akhir[]" multiple required>@foreach ($data['subcpmks'] as $subcpmk)<option value="{{ $subcpmk->id_subcpmk }}" @selected(in_array((string) $subcpmk->id_subcpmk, $selectedSubIds, true))>{{ $subcpmk->kode_subcpmk }} · {{ $subcpmk->deskripsi_subcpmk }}</option>@endforeach</select>
                                    <label class="form-label">Indikator</label><textarea class="form-control mb-2" name="indikator" required>{{ $material->indikator }}</textarea><label class="form-label">Kriteria dan teknik</label><textarea class="form-control mb-2" name="kriteria_teknik" required>{{ $material->kriteria_teknik }}</textarea><label class="form-label">Bentuk dan metode pembelajaran</label><textarea class="form-control mb-2" name="bentuk_pembelajaran" required>{{ $material->bentuk_pembelajaran }}</textarea><label class="form-label">Materi pembelajaran</label><textarea class="form-control mb-2" name="materi_pembelajaran" required>{{ $material->materi_pembelajaran }}</textarea>
                                    <label class="form-label">Pustaka acuan</label><select class="form-control mb-2" name="pustaka_pilihan[]" multiple required><option value="Pustaka Utama" @selected(in_array('Pustaka Utama', $selectedLibraries, true))>Pustaka Utama</option><option value="Pustaka Pendukung" @selected(in_array('Pustaka Pendukung', $selectedLibraries, true))>Pustaka Pendukung</option></select><label class="form-label">Bobot (%)</label><input class="form-control mb-2" type="number" step="0.01" min="0" max="100" name="bobot_penilaian" value="{{ $material->bobot_penilaian }}" required><button class="button button-primary button-compact">Simpan</button>
                                </form><form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'materials', 'record' => $material->id_materi, 'mk' => $course->id_mk_draft]) }}" class="mt-2" onsubmit="return confirm('Hapus materi ini?')">@csrf @method('delete')<button class="button button-danger button-compact">Hapus</button></form></details></td>@endif
                            </tr>
                        @empty<tr><td colspan="{{ $canEdit ? 8 : 7 }}">Belum ada materi mingguan.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @endforeach
            @include('ekurikulum.partials.relationship-map', ['mapId' => 'rps-'.$curriculum->id_kur.'-completeness', 'mapTitle' => 'Peta Kelengkapan RPS', 'mapDescription' => 'Status kelengkapan dan hubungan materi mingguan tiap mata kuliah.', 'mapFilename' => 'Peta-RPS-Kelengkapan-'.$curriculum->id_kur, 'mapColumns' => $completenessMapColumns, 'mapEdges' => $completenessMapEdges])
        </section>
    @endif
@endsection