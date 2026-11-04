@extends('layouts.simak')

@section('title', 'Kelola Kurikulum | SIAKAD')
@section('topbar_title', 'Kelola Kurikulum OBE')

@section('content')
    @php
        $aspects = ['Sikap', 'Pengetahuan', 'Keterampilan Umum', 'Keterampilan Khusus'];
        $mapProfiles = $profileMaps->groupBy('id_cpl');
        $mapCpls = $cplMaps->groupBy('id_bk');
        $mapCourseBks = $courseBkMaps->groupBy('id_mk_draft');
        $mapCourseCpls = $courseCplMaps->groupBy('id_mk_draft');
        $base = ['curriculum' => $curriculum->id_kur];
        $graphColumns = [
            ['label' => 'Profil Lulusan', 'nodes' => $profiles->map(fn ($item) => ['id' => (string) $item->id_pl, 'code' => $item->kode_pl, 'name' => $item->profil_lulusan])->all()],
            ['label' => 'CPL', 'nodes' => $cpls->map(fn ($item) => ['id' => (string) $item->id_cpl, 'code' => $item->kode_cpl, 'name' => $item->deskripsi_cpl])->all()],
            ['label' => 'Bahan Kajian', 'nodes' => $bks->map(fn ($item) => ['id' => (string) $item->id_bk, 'code' => $item->kode_bk, 'name' => $item->nama_bk])->all()],
            ['label' => 'Mata Kuliah', 'nodes' => $courses->map(fn ($item) => ['id' => (string) $item->id_mk_draft, 'code' => $item->kode_mk_live ?: $item->kode_mk, 'name' => $item->nama_mk])->all()],
        ];
        $graphPositions = [];
        foreach ($graphColumns as $columnIndex => $column) {
            foreach ($column['nodes'] as $nodeIndex => $node) {
                $graphPositions[$columnIndex][$node['id']] = ['x' => 18 + ($columnIndex * 238), 'y' => 32 + ($nodeIndex * 78)];
            }
        }
        $graphHeight = max(230, 48 + max(1, ...array_map('count', array_column($graphColumns, 'nodes'))) * 78);
        $mapProfileNodes = $profiles->map(fn ($item) => ['id' => 'pl-'.$item->id_pl, 'code' => $item->kode_pl, 'name' => $item->profil_lulusan])->all();
        $mapCplNodes = $cpls->map(fn ($item) => ['id' => 'cpl-'.$item->id_cpl, 'code' => $item->kode_cpl, 'name' => $item->deskripsi_cpl])->all();
        $mapBkNodes = $bks->map(fn ($item) => ['id' => 'bk-'.$item->id_bk, 'code' => $item->kode_bk, 'name' => $item->nama_bk])->all();
        $mapCourseNodes = $courses->map(fn ($item) => ['id' => 'mk-'.$item->id_mk_draft, 'code' => $item->kode_mk_live ?: $item->kode_mk, 'name' => $item->nama_mk])->all();
        $mapProfessionNodes = $professions->map(fn ($item) => ['id' => 'profession-'.$item->id_profesi, 'code' => 'PROFESI', 'name' => $item->nama_profesi])->all();
        $mapProfileEdges = $profileMaps->map(fn ($edge) => ['source' => 'pl-'.$edge->id_pl, 'target' => 'cpl-'.$edge->id_cpl])->all();
        $mapCplBkEdges = $cplMaps->map(fn ($edge) => ['source' => 'cpl-'.$edge->id_cpl, 'target' => 'bk-'.$edge->id_bk])->all();
        $mapBkCourseEdges = $courseBkMaps->map(fn ($edge) => ['source' => 'bk-'.$edge->id_bk, 'target' => 'mk-'.$edge->id_mk_draft])->all();
        $mapCplCourseEdges = $courseCplMaps->map(fn ($edge) => ['source' => 'cpl-'.$edge->id_cpl, 'target' => 'mk-'.$edge->id_mk_draft, 'class' => 'is-direct'])->all();
        $mapPrerequisiteEdges = [];
        foreach ($courses as $course) {
            foreach (array_filter(array_map('trim', explode(',', (string) $course->prasyarat_mk))) as $prerequisiteId) {
                $mapPrerequisiteEdges[] = ['source' => 'mk-'.$prerequisiteId, 'target' => 'mk-'.$course->id_mk_draft, 'class' => 'is-prerequisite'];
            }
        }
        $mapStages = [
            'profil' => ['columns' => [['label' => 'Profil Lulusan', 'nodes' => $mapProfileNodes]], 'edges' => []],
            'profesi' => ['columns' => [['label' => 'Profil Lulusan', 'nodes' => $mapProfileNodes], ['label' => 'Profesi', 'nodes' => $mapProfessionNodes]], 'edges' => []],
            'cpl' => ['columns' => [['label' => 'Profil Lulusan', 'nodes' => $mapProfileNodes], ['label' => 'CPL', 'nodes' => $mapCplNodes]], 'edges' => $mapProfileEdges],
            'bk' => ['columns' => [['label' => 'Profil Lulusan', 'nodes' => $mapProfileNodes], ['label' => 'CPL', 'nodes' => $mapCplNodes], ['label' => 'Bahan Kajian', 'nodes' => $mapBkNodes]], 'edges' => [...$mapProfileEdges, ...$mapCplBkEdges]],
            'mk' => ['columns' => [['label' => 'Profil Lulusan', 'nodes' => $mapProfileNodes], ['label' => 'CPL', 'nodes' => $mapCplNodes], ['label' => 'Bahan Kajian', 'nodes' => $mapBkNodes], ['label' => 'Mata Kuliah', 'nodes' => $mapCourseNodes]], 'edges' => [...$mapProfileEdges, ...$mapCplBkEdges, ...$mapBkCourseEdges, ...$mapCplCourseEdges]],
            'sebaran' => ['columns' => [['label' => 'Profil Lulusan', 'nodes' => $mapProfileNodes], ['label' => 'CPL', 'nodes' => $mapCplNodes], ['label' => 'Bahan Kajian', 'nodes' => $mapBkNodes], ['label' => 'Mata Kuliah / Prasyarat', 'nodes' => $mapCourseNodes]], 'edges' => [...$mapProfileEdges, ...$mapCplBkEdges, ...$mapBkCourseEdges, ...$mapCplCourseEdges, ...$mapPrerequisiteEdges]],
            'final' => ['columns' => [['label' => 'Profil Lulusan', 'nodes' => $mapProfileNodes], ['label' => 'CPL', 'nodes' => $mapCplNodes], ['label' => 'Bahan Kajian', 'nodes' => $mapBkNodes], ['label' => 'Mata Kuliah', 'nodes' => $mapCourseNodes]], 'edges' => [...$mapProfileEdges, ...$mapCplBkEdges, ...$mapBkCourseEdges, ...$mapCplCourseEdges, ...$mapPrerequisiteEdges]],
        ];
    @endphp
    <div class="page-heading ekur-page-heading">
        <div class="ekur-page-heading-copy">
            <p class="eyebrow">E-Kurikulum OBE · {{ $curriculum->nama_program_studi }}</p>
            <h1 class="ekur-page-title">{{ $curriculum->nama_kur }}</h1>
            <div class="module-alert" role="note"><p class="subtitle">Tahun {{ $curriculum->tahun }} · {{ $curriculum->status_kur === 'Final' ? 'Final / Disahkan' : 'Draf / Penyusunan' }}</p></div>
        </div>
        <div class="d-flex gap-2">
            <a class="button button-secondary" href="{{ route('ekurikulum.curricula.index') }}"><span data-lucide="arrow-left" aria-hidden="true"></span> Kembali</a>
            @if ($curriculum->status_kur === 'Final') <a class="button button-primary" href="{{ route('ekurikulum.rps.show', $curriculum->id_kur) }}"><span data-lucide="file-spreadsheet" aria-hidden="true"></span> Kelola RPS</a> @endif
        </div>
    </div>
    @if (session('status') && session('status') !== 'RPS hanya dapat disusun setelah kurikulum difinalisasi.') <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif

    @if ($canEdit)
        <details class="table-wrap mb-4">
            <summary class="button button-secondary">Opsi Excel kurikulum (opsional)</summary>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                <p class="subtext mb-0">Penginputan dan pengelolaan utama dilakukan langsung pada tahap wizard di bawah.</p>
                <a class="button button-secondary" href="{{ route('ekurikulum.template', 'curriculum') }}" data-page-loader-skip>Unduh template Excel</a>
                <a class="button button-secondary" href="{{ route('ekurikulum.curricula.export', $curriculum->id_kur) }}" data-page-loader-skip>Ekspor data kurikulum</a>
                <button class="button button-secondary" type="button" data-dialog-open="ekur-curriculum-import-dialog">Unggah Excel</button>
            </div>
        </details>
        <dialog class="ekur-dialog ekur-upload-dialog" id="ekur-curriculum-import-dialog" aria-labelledby="ekur-curriculum-import-title">
            <div class="ekur-dialog-header">
                <div><p class="eyebrow">Impor data opsional</p><h2 id="ekur-curriculum-import-title">Unggah template kurikulum</h2><p class="subtext">Gunakan file Excel yang diunduh dari template kurikulum.</p></div>
                <button class="ekur-dialog-close" type="button" data-dialog-close aria-label="Tutup dialog">Tutup</button>
            </div>
            <form method="post" action="{{ route('ekurikulum.import', [$curriculum->id_kur, 'curriculum']) }}" enctype="multipart/form-data" data-ekur-upload-form>
                @csrf
                <label class="ekur-dropzone" data-ekur-dropzone>
                    <input class="ekur-dropzone-input" type="file" name="file" accept=".xlsx" required aria-label="Pilih template kurikulum">
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
    @else
        <div class="mb-4"><a class="button button-secondary" href="{{ route('ekurikulum.curricula.export', $curriculum->id_kur) }}" data-page-loader-skip>Ekspor data kurikulum</a></div>
    @endif

    @php
        $wizardSteps = [
            'profil' => ['Profil Lulusan', 'Tetapkan profil dan kompetensi lulusan'],
            'profesi' => ['Profesi', 'Hubungkan profil dengan bidang kerja'],
            'cpl' => ['CPL', 'Susun capaian pembelajaran lulusan'],
            'bk' => ['Bahan Kajian', 'Kelompokkan dasar keilmuan kurikulum'],
            'mk' => ['Mata Kuliah', 'Rancang dan petakan mata kuliah'],
            'sebaran' => ['Sebaran Semester', 'Atur beban belajar tiap semester'],
            'final' => ['Finalisasi', 'Periksa kelengkapan dan sahkan draf'],
        ];
    @endphp
    <div class="ekur-wizard" data-step-wizard data-wizard-id="curriculum-{{ $curriculum->id_kur }}">
        <div class="ekur-wizard-heading">
            <div><p class="eyebrow">Penyusunan bertahap</p><h2>Wizard Kurikulum OBE</h2><p class="subtext">Ikuti tahapan dari profil lulusan hingga finalisasi. Perubahan tersimpan pada masing-masing langkah.</p></div>
            <span class="ekur-wizard-progress-label" data-wizard-progress-label></span>
        </div>
        <div class="ekur-wizard-progress" aria-hidden="true"><span data-wizard-progress></span></div>
        <nav class="ekur-wizard-steps" aria-label="Tahapan penyusunan kurikulum">
            @foreach ($wizardSteps as $stepId => [$stepTitle, $stepDescription])
                <a class="ekur-wizard-step {{ ($workflow[$stepId]['locked'] ?? false) ? 'is-locked' : '' }}" href="#{{ $stepId }}" data-wizard-step="{{ $stepId }}" data-wizard-locked="{{ ($workflow[$stepId]['locked'] ?? false) ? 'true' : 'false' }}" title="{{ $workflow[$stepId]['message'] ?? '' }}" aria-disabled="{{ ($workflow[$stepId]['locked'] ?? false) ? 'true' : 'false' }}" aria-current="false">
                    <span class="ekur-wizard-number">{{ $loop->iteration }}</span>
                    <span class="ekur-wizard-step-copy"><strong>{{ $stepTitle }}</strong><small>{{ ($workflow[$stepId]['locked'] ?? false) ? 'Terkunci · '.$workflow[$stepId]['message'] : $stepDescription }}</small></span>
                </a>
            @endforeach
        </nav>
    </div>

    <section class="table-wrap mb-4" id="profil" data-wizard-panel="profil" data-wizard-owner="curriculum-{{ $curriculum->id_kur }}">
        <h2 class="h4">1. Profil Lulusan</h2>
        @if ($canEdit)
            <details class="mb-3"><summary class="button button-primary">Tambah profil lulusan</summary>
                <form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'profiles']) }}" class="row g-3 mt-2">
                    @csrf
                    <div class="col-md-3"><label class="form-label">Kode PL (otomatis)</label><input class="form-control" name="kode_pl" value="{{ $nextCodes['profiles'] }}" readonly required></div>
                    <div class="col-md-9"><label class="form-label">Profil lulusan (ringkas)</label><input class="form-control" name="profil_lulusan" required></div>
                    <div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control" name="deskripsi_pl" rows="3" required></textarea></div>
                    <div class="col-md-6"><label class="form-label">Aspek</label><select class="form-control" name="aspek[]" multiple required>@foreach ($aspects as $aspect)<option value="{{ $aspect }}">{{ $aspect }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Referensi</label><input class="form-control" name="referensi" maxlength="150"></div>
                    <div class="col-12"><button class="button button-primary">Simpan PL</button></div>
                </form>
            </details>
        @endif
        <div class="table-responsive"><table class="table" data-simple-search-table>
            <thead><tr><th>Kode</th><th>Profil</th><th>Aspek</th><th>Deskripsi</th><th>Referensi</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead>
            <tbody>
            @forelse ($profiles as $profile)
                <tr><td>{{ $profile->kode_pl }}</td><td>{{ $profile->profil_lulusan }}</td><td>{{ $profile->aspek ?: '-' }}</td><td>{{ $profile->deskripsi_pl }}</td><td>{{ $profile->referensi ?: '-' }}</td>
                    @if ($canEdit)<td><details><summary class="button button-secondary">Ubah</summary>
                        <form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'profiles', 'record' => $profile->id_pl]) }}" class="mt-3">
                            @csrf @method('put')
                            <label class="form-label">Kode PL (otomatis)</label><input class="form-control mb-2" value="{{ $profile->kode_pl }}" readonly>
                            <label class="form-label">Profil ringkas</label><input class="form-control mb-2" name="profil_lulusan" value="{{ $profile->profil_lulusan }}" required>
                            <label class="form-label">Deskripsi</label><textarea class="form-control mb-2" name="deskripsi_pl" required>{{ $profile->deskripsi_pl }}</textarea>
                            <label class="form-label">Aspek</label><select class="form-control mb-2" name="aspek[]" multiple required>@foreach ($aspects as $aspect)<option value="{{ $aspect }}" @selected(in_array($aspect, explode(', ', (string) $profile->aspek), true))>{{ $aspect }}</option>@endforeach</select>
                            <label class="form-label">Referensi</label><input class="form-control mb-2" name="referensi" value="{{ $profile->referensi }}">
                            <button class="button button-primary">Simpan</button>
                        </form>
                        <form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'profiles', 'record' => $profile->id_pl]) }}" class="mt-2" onsubmit="return confirm('Hapus profil lulusan ini?')">@csrf @method('delete')<button class="button button-danger">Hapus</button></form>
                    </details></td>@endif
                </tr>
            @empty<tr><td colspan="{{ $canEdit ? 6 : 5 }}">Belum ada profil lulusan.</td></tr>@endforelse
            </tbody>
        </table></div>
        @include('ekurikulum.partials.relationship-map', ['mapId' => $curriculum->id_kur.'-profil', 'mapTitle' => 'Peta Profil Lulusan', 'mapDescription' => 'Profil lulusan yang sedang menjadi dasar kurikulum.', 'mapFilename' => 'Peta-PL-'.$curriculum->id_kur, 'mapColumns' => $mapStages['profil']['columns'], 'mapEdges' => $mapStages['profil']['edges']])
    </section>

    <section class="table-wrap mb-4" id="profesi" data-wizard-panel="profesi" data-wizard-owner="curriculum-{{ $curriculum->id_kur }}">
        <h2 class="h4">Profesi</h2>
        @if ($canEdit)
            <details class="mb-3"><summary class="button button-primary">Tambah profesi</summary><form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'professions']) }}" class="row g-3 mt-2">@csrf
                <div class="col-md-6"><label class="form-label">Nama profesi</label><input class="form-control" name="nama_profesi" maxlength="255" required></div><div class="col-md-6"><label class="form-label">Referensi</label><input class="form-control" name="referensi" maxlength="150"></div><div class="col-12"><button class="button button-primary">Simpan profesi</button></div>
            </form></details>
        @endif
        <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Nama profesi</th><th>Referensi</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead><tbody>
            @forelse ($professions as $profession)<tr><td>{{ $profession->nama_profesi }}</td><td>{{ $profession->referensi ?: '-' }}</td>@if ($canEdit)<td>
                <details><summary class="button button-secondary">Ubah</summary><form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'professions', 'record' => $profession->id_profesi]) }}" class="mt-3">@csrf @method('put')
                    <label class="form-label">Nama profesi</label><input class="form-control mb-2" name="nama_profesi" value="{{ $profession->nama_profesi }}" required><label class="form-label">Referensi</label><input class="form-control mb-2" name="referensi" value="{{ $profession->referensi }}"><button class="button button-primary">Simpan</button>
                </form><form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'professions', 'record' => $profession->id_profesi]) }}" class="mt-2" onsubmit="return confirm('Hapus profesi ini?')">@csrf @method('delete')<button class="button button-danger">Hapus</button></form></details>
            </td>@endif</tr>@empty<tr><td colspan="{{ $canEdit ? 3 : 2 }}">Belum ada data profesi.</td></tr>@endforelse
        </tbody></table></div>
        @include('ekurikulum.partials.relationship-map', ['mapId' => $curriculum->id_kur.'-profesi', 'mapTitle' => 'Peta Profil Lulusan dan Profesi', 'mapDescription' => 'Simpul profesi ditampilkan sebagai referensi terpisah; schema legacy tidak menyimpan relasi langsung profesi ke PL.', 'mapFilename' => 'Peta-PL-Profesi-'.$curriculum->id_kur, 'mapColumns' => $mapStages['profesi']['columns'], 'mapEdges' => $mapStages['profesi']['edges']])
    </section>

    <section class="table-wrap mb-4" id="cpl" data-wizard-panel="cpl" data-wizard-owner="curriculum-{{ $curriculum->id_kur }}">
        <h2 class="h4">2. Capaian Pembelajaran Lulusan (CPL)</h2>
        @if ($workflow['cpl']['locked'])<div class="alert alert-warning" role="note">{{ $workflow['cpl']['message'] }}</div>@endif
        @if ($canEdit)
            <details class="mb-3"><summary class="button button-primary">Tambah CPL</summary><form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'cpls']) }}" class="row g-3 mt-2">@csrf
                <div class="col-md-3"><label class="form-label">Kode CPL (otomatis)</label><input class="form-control" name="kode_cpl" value="{{ $nextCodes['cpls'] }}" readonly required></div><div class="col-md-4"><label class="form-label">Aspek</label><select class="form-control" name="aspek" required>@foreach ($aspects as $aspect)<option value="{{ $aspect }}">{{ $aspect }}</option>@endforeach</select></div><div class="col-12"><label class="form-label">Deskripsi CPL</label><textarea class="form-control" name="deskripsi_cpl" required></textarea></div><div class="col-md-6"><label class="form-label">Profil lulusan yang didukung</label><select class="form-control" name="id_pl[]" multiple required>@foreach ($profiles as $profile)<option value="{{ $profile->id_pl }}">{{ $profile->kode_pl }} — {{ $profile->profil_lulusan }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Referensi</label><input class="form-control" name="referensi" maxlength="150"></div><div class="col-12"><button class="button button-primary">Simpan CPL</button></div>
            </form></details>
        @endif
        <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Kode</th><th>Aspek</th><th>Deskripsi</th><th>Profil lulusan</th><th>Referensi</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead><tbody>
            @forelse ($cpls as $cpl)
                @php
                    $profileIds = $mapProfiles->get($cpl->id_cpl, collect())->pluck('id_pl');
                    $relatedProfiles = $profiles->whereIn('id_pl', $profileIds)->pluck('kode_pl')->all();
                @endphp
                <tr><td>{{ $cpl->kode_cpl }}</td><td>{{ $cpl->aspek }}</td><td>{{ $cpl->deskripsi_cpl }}</td><td>{{ $relatedProfiles ? implode(', ', $relatedProfiles) : '-' }}</td><td>{{ $cpl->referensi ?: '-' }}</td>
                    @if ($canEdit)<td><details><summary class="button button-secondary">Ubah</summary><form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'cpls', 'record' => $cpl->id_cpl]) }}" class="mt-3">@csrf @method('put')
                        <label class="form-label">Kode CPL (otomatis)</label><input class="form-control mb-2" value="{{ $cpl->kode_cpl }}" readonly><label class="form-label">Aspek</label><select class="form-control mb-2" name="aspek" required>@foreach ($aspects as $aspect)<option value="{{ $aspect }}" @selected($cpl->aspek === $aspect)>{{ $aspect }}</option>@endforeach</select><label class="form-label">Deskripsi</label><textarea class="form-control mb-2" name="deskripsi_cpl" required>{{ $cpl->deskripsi_cpl }}</textarea><label class="form-label">Profil lulusan</label><select class="form-control mb-2" name="id_pl[]" multiple required>@foreach ($profiles as $profile)<option value="{{ $profile->id_pl }}" @selected(in_array($profile->id_pl, $mapProfiles->get($cpl->id_cpl, collect())->pluck('id_pl')->all()))>{{ $profile->kode_pl }} — {{ $profile->profil_lulusan }}</option>@endforeach</select><label class="form-label">Referensi</label><input class="form-control mb-2" name="referensi" value="{{ $cpl->referensi }}"><button class="button button-primary">Simpan</button>
                    </form><form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'cpls', 'record' => $cpl->id_cpl]) }}" class="mt-2" onsubmit="return confirm('Hapus CPL ini?')">@csrf @method('delete')<button class="button button-danger">Hapus</button></form></details></td>@endif
                </tr>
            @empty<tr><td colspan="{{ $canEdit ? 6 : 5 }}">Belum ada CPL.</td></tr>@endforelse
        </tbody></table></div>
        @include('ekurikulum.partials.relationship-map', ['mapId' => $curriculum->id_kur.'-cpl', 'mapTitle' => 'Peta Profil Lulusan ke CPL', 'mapDescription' => 'Relasi PL → CPL yang sudah disimpan.', 'mapFilename' => 'Peta-PL-CPL-'.$curriculum->id_kur, 'mapColumns' => $mapStages['cpl']['columns'], 'mapEdges' => $mapStages['cpl']['edges']])
    </section>

    <section class="table-wrap mb-4" id="bk" data-wizard-panel="bk" data-wizard-owner="curriculum-{{ $curriculum->id_kur }}">
        <h2 class="h4">3. Bahan Kajian</h2>
        @if ($workflow['bk']['locked'])<div class="alert alert-warning" role="note">{{ $workflow['bk']['message'] }}</div>@endif
        @if ($canEdit)
            <details class="mb-3"><summary class="button button-primary">Tambah bahan kajian</summary><form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'bks']) }}" class="row g-3 mt-2">@csrf
                <div class="col-md-3"><label class="form-label">Kode BK (otomatis)</label><input class="form-control" name="kode_bk" value="{{ $nextCodes['bks'] }}" readonly required></div><div class="col-md-9"><label class="form-label">Nama bahan kajian</label><input class="form-control" name="nama_bk" maxlength="150" required></div><div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control" name="deskripsi_bk"></textarea></div><div class="col-md-6"><label class="form-label">CPL terkait</label><select class="form-control" name="id_cpl[]" multiple required>@foreach ($cpls as $cpl)<option value="{{ $cpl->id_cpl }}">{{ $cpl->kode_cpl }} — {{ $cpl->deskripsi_cpl }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Kompetensi</label><select class="form-control" name="kompetensi"><option>Utama</option><option>Pendukung</option><option>Umum</option></select></div><div class="col-md-3"><label class="form-label">Referensi</label><input class="form-control" name="referensi" maxlength="150"></div><div class="col-12"><button class="button button-primary">Simpan BK</button></div>
            </form></details>
        @endif
        <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Kode</th><th>Nama bahan kajian</th><th>Kompetensi</th><th>CPL</th><th>Referensi</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead><tbody>
            @forelse ($bks as $bk)
                @php
                    $relatedCpls = $mapCpls->get($bk->id_bk, collect());
                    $relatedCplCodes = $cpls->whereIn('id_cpl', $relatedCpls->pluck('id_cpl'))->pluck('kode_cpl');
                @endphp
                <tr><td>{{ $bk->kode_bk }}</td><td>{{ $bk->nama_bk }}<span class="subtext">{{ $bk->deskripsi_bk }}</span></td><td>{{ $bk->kompetensi }}</td><td>{{ $relatedCplCodes->implode(', ') ?: '-' }}</td><td>{{ $bk->referensi ?: '-' }}</td>
                    @if ($canEdit)<td><details><summary class="button button-secondary">Ubah</summary><form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'bks', 'record' => $bk->id_bk]) }}" class="mt-3">@csrf @method('put')
                        <label class="form-label">Kode BK (otomatis)</label><input class="form-control mb-2" value="{{ $bk->kode_bk }}" readonly><label class="form-label">Nama</label><input class="form-control mb-2" name="nama_bk" value="{{ $bk->nama_bk }}" required><label class="form-label">Deskripsi</label><textarea class="form-control mb-2" name="deskripsi_bk">{{ $bk->deskripsi_bk }}</textarea><label class="form-label">CPL</label><select class="form-control mb-2" name="id_cpl[]" multiple required>@foreach ($cpls as $cpl)<option value="{{ $cpl->id_cpl }}" @selected($relatedCpls->contains('id_cpl', $cpl->id_cpl))>{{ $cpl->kode_cpl }}</option>@endforeach</select><label class="form-label">Kompetensi</label><select class="form-control mb-2" name="kompetensi">@foreach (['Utama','Pendukung','Umum'] as $competency)<option @selected($bk->kompetensi === $competency)>{{ $competency }}</option>@endforeach</select><label class="form-label">Referensi</label><input class="form-control mb-2" name="referensi" value="{{ $bk->referensi }}"><button class="button button-primary">Simpan</button>
                    </form><form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'bks', 'record' => $bk->id_bk]) }}" class="mt-2" onsubmit="return confirm('Hapus bahan kajian ini?')">@csrf @method('delete')<button class="button button-danger">Hapus</button></form></details></td>@endif
                </tr>
            @empty<tr><td colspan="{{ $canEdit ? 6 : 5 }}">Belum ada bahan kajian.</td></tr>@endforelse
        </tbody></table></div>
        @include('ekurikulum.partials.relationship-map', ['mapId' => $curriculum->id_kur.'-bk', 'mapTitle' => 'Peta PL, CPL, dan Bahan Kajian', 'mapDescription' => 'Relasi PL → CPL → BK yang sudah disimpan.', 'mapFilename' => 'Peta-PL-CPL-BK-'.$curriculum->id_kur, 'mapColumns' => $mapStages['bk']['columns'], 'mapEdges' => $mapStages['bk']['edges']])
    </section>

    <section class="table-wrap mb-4" id="mk" data-wizard-panel="mk" data-wizard-owner="curriculum-{{ $curriculum->id_kur }}">
        <h2 class="h4">4. Draf Mata Kuliah</h2>
        @if ($workflow['mk']['locked'])<div class="alert alert-warning" role="note">{{ $workflow['mk']['message'] }}</div>@endif
        @if ($canEdit)
            <details class="mb-3"><summary class="button button-primary">Tambah mata kuliah draf</summary><form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'courses']) }}" class="row g-3 mt-2">@csrf
                <div class="col-md-3"><label class="form-label">Kode draf (otomatis)</label><input class="form-control" name="kode_mk" value="{{ $nextCodes['courses'] }}" readonly required></div><div class="col-md-3"><label class="form-label">Kode MK aktif (opsional)</label><input class="form-control" name="kode_mk_live" maxlength="20"></div><div class="col-md-6"><label class="form-label">Nama mata kuliah</label><input class="form-control" name="nama_mk" maxlength="150" required></div>
                <div class="col-md-2"><label class="form-label">Total SKS</label><input class="form-control" type="number" name="sks" min="0" max="99" required></div><div class="col-md-2"><label class="form-label">SKS teori</label><input class="form-control" type="number" name="sks_teori" min="0" max="99" required></div><div class="col-md-2"><label class="form-label">SKS praktik</label><input class="form-control" type="number" name="sks_praktek" min="0" max="99" required></div><div class="col-md-3"><label class="form-label">Kategori MK</label><input class="form-control" name="kategori_mk" maxlength="100"></div><div class="col-md-3"><label class="form-label">Capstone</label><select class="form-control" name="is_capstone"><option>Tidak</option><option>Ya</option></select></div>
                <div class="col-md-8"><label class="form-label">Bahan kajian</label><select class="form-control" name="id_bk[]" multiple required>@foreach ($bks as $bk)<option value="{{ $bk->id_bk }}">{{ $bk->kode_bk }} — {{ $bk->nama_bk }}</option>@endforeach</select></div><div class="col-12"><button class="button button-primary">Simpan mata kuliah</button></div>
            </form></details>
        @endif
        <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Semester</th><th>Kode</th><th>Nama mata kuliah</th><th>SKS</th><th>Jenis</th><th>Bahan kajian</th>@if ($canEdit)<th>Aksi</th>@endif</tr></thead><tbody>
            @forelse ($courses as $course)
                @php
                    $relatedBks = $mapCourseBks->get($course->id_mk_draft, collect());
                    $relatedBkCodes = $bks->whereIn('id_bk', $relatedBks->pluck('id_bk'))->pluck('kode_bk');
                @endphp
                <tr><td>{{ $course->semester ?: '-' }}</td><td>{{ $course->kode_mk_live ?: $course->kode_mk }}</td><td>{{ $course->nama_mk }}</td><td>{{ $course->sks }}</td><td>{{ $course->jenis_mk ?: 'Wajib' }}</td><td>{{ $relatedBkCodes->implode(', ') ?: '-' }}</td>
                    @if ($canEdit)<td><details><summary class="button button-secondary">Ubah</summary><form method="post" action="{{ route('ekurikulum.data.update', [...$base, 'entity' => 'courses', 'record' => $course->id_mk_draft]) }}" class="mt-3">@csrf @method('put')
                        <label class="form-label">Kode draf (otomatis)</label><input class="form-control mb-2" value="{{ $course->kode_mk }}" readonly><label class="form-label">Kode MK aktif</label><input class="form-control mb-2" name="kode_mk_live" value="{{ $course->kode_mk_live }}"><label class="form-label">Nama</label><input class="form-control mb-2" name="nama_mk" value="{{ $course->nama_mk }}" required><label class="form-label">Total SKS</label><input class="form-control mb-2" type="number" name="sks" value="{{ $course->sks }}" required><label class="form-label">SKS teori</label><input class="form-control mb-2" type="number" name="sks_teori" value="{{ $course->sks_teori }}" required><label class="form-label">SKS praktik</label><input class="form-control mb-2" type="number" name="sks_praktek" value="{{ $course->sks_praktek }}" required><label class="form-label">Kategori</label><input class="form-control mb-2" name="kategori_mk" value="{{ $course->kategori_mk }}"><label class="form-label">Capstone</label><input class="form-control mb-2" name="is_capstone" value="{{ $course->is_capstone }}" required><label class="form-label">Bahan kajian</label><select class="form-control mb-2" name="id_bk[]" multiple required>@foreach ($bks as $bk)<option value="{{ $bk->id_bk }}" @selected($relatedBks->contains('id_bk', $bk->id_bk))>{{ $bk->kode_bk }} — {{ $bk->nama_bk }}</option>@endforeach</select><button class="button button-primary">Simpan</button>
                    </form><form method="post" action="{{ route('ekurikulum.data.delete', [...$base, 'entity' => 'courses', 'record' => $course->id_mk_draft]) }}" class="mt-2" onsubmit="return confirm('Hapus mata kuliah ini?')">@csrf @method('delete')<button class="button button-danger">Hapus</button></form></details></td>@endif
                </tr>
            @empty<tr><td colspan="{{ $canEdit ? 7 : 6 }}">Belum ada mata kuliah draf.</td></tr>@endforelse
        </tbody></table></div>
        @include('ekurikulum.partials.relationship-map', ['mapId' => $curriculum->id_kur.'-mk', 'mapTitle' => 'Peta PL, CPL, BK, dan Mata Kuliah', 'mapDescription' => 'Termasuk relasi langsung CPL → MK yang diturunkan dari pilihan BK.', 'mapFilename' => 'Peta-PL-CPL-BK-MK-'.$curriculum->id_kur, 'mapColumns' => $mapStages['mk']['columns'], 'mapEdges' => $mapStages['mk']['edges']])
    </section>

    <section class="table-wrap mb-4" id="sebaran" data-wizard-panel="sebaran" data-wizard-owner="curriculum-{{ $curriculum->id_kur }}">
        <h2 class="h4">5. Sebaran semester dan prasyarat</h2>
        @if ($workflow['sebaran']['locked'])<div class="alert alert-warning" role="note">{{ $workflow['sebaran']['message'] }}</div>@endif
        @if ($canEdit)
            <form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'distribution']) }}" data-ekur-modal-form="Atur sebaran semester">
                @csrf
                <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Mata kuliah</th><th>Semester</th><th>Jenis</th><th>Prasyarat</th></tr></thead><tbody>
                    @forelse ($courses as $course)
                        @php $selectedPrerequisites = array_filter(explode(',', (string) $course->prasyarat_mk)); @endphp
                        <tr><td>{{ $course->kode_mk_live ?: $course->kode_mk }} — {{ $course->nama_mk }}</td>
                            <td><select class="form-control" name="semester[{{ $course->id_mk_draft }}]">@for ($semester = 0; $semester <= 8; $semester++)<option value="{{ $semester }}" @selected((int) $course->semester === $semester)>{{ $semester === 0 ? 'Belum ditetapkan' : $semester }}</option>@endfor</select></td>
                            <td><select class="form-control" name="jenis_mk[{{ $course->id_mk_draft }}]">@foreach (['Wajib','Pilihan'] as $kind)<option @selected(($course->jenis_mk ?: 'Wajib') === $kind)>{{ $kind }}</option>@endforeach</select></td>
                            <td><select class="form-control" name="prasyarat[{{ $course->id_mk_draft }}][]" multiple>@foreach ($courses as $prerequisite)@if ($prerequisite->id_mk_draft !== $course->id_mk_draft)<option value="{{ $prerequisite->id_mk_draft }}" @selected(in_array((string) $prerequisite->id_mk_draft, $selectedPrerequisites, true))>{{ $prerequisite->kode_mk_live ?: $prerequisite->kode_mk }}</option>@endif @endforeach</select></td>
                        </tr>
                    @empty<tr><td colspan="4">Tambahkan mata kuliah terlebih dahulu.</td></tr>@endforelse
                </tbody></table></div>
                <button class="button button-primary" type="submit">Simpan sebaran</button>
            </form>
        @else
            <div class="table-responsive"><table class="table" data-simple-search-table><thead><tr><th>Mata kuliah</th><th>Semester</th><th>Jenis</th></tr></thead><tbody>@foreach ($courses as $course)<tr><td>{{ $course->kode_mk_live ?: $course->kode_mk }} — {{ $course->nama_mk }}</td><td>{{ $course->semester ?: '-' }}</td><td>{{ $course->jenis_mk ?: 'Wajib' }}</td></tr>@endforeach</tbody></table></div>
        @endif
        @include('ekurikulum.partials.relationship-map', ['mapId' => $curriculum->id_kur.'-sebaran', 'mapTitle' => 'Peta Sebaran dan Prasyarat', 'mapDescription' => 'Relasi kurikulum beserta prasyarat mata kuliah.', 'mapFilename' => 'Peta-Sebaran-'.$curriculum->id_kur, 'mapColumns' => $mapStages['sebaran']['columns'], 'mapEdges' => $mapStages['sebaran']['edges']])
    </section>

    <section class="table-wrap mb-4" id="final" data-wizard-panel="final" data-wizard-owner="curriculum-{{ $curriculum->id_kur }}">
        <h2 class="h4">6. Finalisasi kurikulum</h2>
        <div class="alert alert-info" role="status"><p class="mb-0">Status saat ini: <strong>{{ $curriculum->status_kur }}</strong></p></div>
        @if ($canEdit)
            <div class="alert alert-info" role="note"><p class="mb-0">Finalisasi mengunci data profil lulusan, CPL, bahan kajian, mata kuliah, dan sebaran secara permanen; hanya perangkat RPS yang tetap dapat disusun.</p></div>
            @unless ($workflow['finalizable'])
                <div class="alert alert-warning" role="status">Kurikulum belum memenuhi seluruh syarat penguncian dan pemetaan untuk difinalisasi.</div>
            @endunless
            <form method="post" action="{{ route('ekurikulum.data.store', [...$base, 'entity' => 'finalize']) }}" onsubmit="return confirm('Finalisasi bersifat permanen. Setelah disahkan, data struktural kurikulum tidak dapat diubah kembali. Lanjutkan?')">@csrf<button class="button button-primary" type="submit" @disabled(!$workflow['finalizable'])>Sahkan kurikulum secara permanen</button></form>
        @endif
        @include('ekurikulum.partials.relationship-map', ['mapId' => $curriculum->id_kur.'-final', 'mapTitle' => 'Peta Final Integrasi Kurikulum', 'mapDescription' => 'Ringkasan seluruh relasi dan prasyarat yang akan dikunci.', 'mapFilename' => 'Peta-Final-'.$curriculum->id_kur, 'mapColumns' => $mapStages['final']['columns'], 'mapEdges' => $mapStages['final']['edges']])
    </section>
@endsection
