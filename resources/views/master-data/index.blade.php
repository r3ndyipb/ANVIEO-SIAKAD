@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Data Master</p>
            <h1>{{ $title }}</h1>
            @if ($note)
                <div class="module-alert" role="note"><p class="subtitle">{{ $note }}</p></div>
            @endif
        </div>
        <div class="page-actions">
            @if ($module === 'mahasiswa' && in_array(auth()->user()->role, ['admin', 'userprodi'], true))
                <a class="button button-secondary" href="{{ route('master-data.mahasiswa.export', request()->query()) }}" data-page-loader-skip>
                    <span data-lucide="download" aria-hidden="true"></span> Unduh Excel mahasiswa
                </a>
            @endif
            @if ($canSync)
                <button
                    class="button"
                    type="button"
                    data-master-sync
                    data-sync-url="{{ route('master-data.'.$module.'.sync') }}"
                    @if ($canSyncByIdentity) data-sync-dialog @endif
                >
                    {{ $module === 'mahasiswa'
                        ? 'Sinkron Feeder by NPM'
                        : ($module === 'mata-kuliah' ? 'Sinkron Feeder by Kode MK' : 'Sinkronkan dari Feeder') }}
                </button>
            @endif
            @if ($canCreate)
                <button class="button button-primary" type="button" data-master-create>Tambah data</button>
            @endif
        </div>
    </div>

    @if ($showProgramFilter || $showStatusFilter)
        <form class="master-data-filters" method="GET" action="{{ route('master-data.'.$module.'.index') }}">
            @if ($showProgramFilter)
                <label for="master-filter-program">Program studi</label>
                <select id="master-filter-program" name="prodi" data-searchable-select="filter">
                    <option value="">Semua program studi dalam cakupan akses</option>
                    @foreach ($programOptions as $id => $name)
                        <option value="{{ $id }}" @selected($selectedProgram === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            @endif
            @if ($showStatusFilter)
                <label for="master-filter-status">Status mahasiswa</label>
                <select id="master-filter-status" name="status" data-searchable-select="filter">
                    <option value="">Semua status</option>
                    @foreach ($statusOptions as $id => $name)
                        <option value="{{ $id }}" @selected($selectedStatus === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            @endif
            <button class="button" type="submit">Terapkan filter</button>
        </form>
    @endif

    @if ($module === 'perguruan-tinggi')
        @forelse ($records as $record)
            <section class="master-identity-card" aria-label="Identitas perguruan tinggi">
                <div class="master-identity-heading">
                    <p class="eyebrow">Identitas institusi</p>
                    <h2>{{ filled($record->nama_perguruan_tinggi ?? null) ? $record->nama_perguruan_tinggi : '-' }}</h2>
                </div>
                <dl class="master-identity-grid">
                    @foreach ($columns as $field => $label)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>{{ filled($record->{$field} ?? null) ? $record->{$field} : '-' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @empty
            <div class="master-empty-state">Data identitas perguruan tinggi belum tersedia.</div>
        @endforelse
    @else
        @php
            $tableColumns = $canEdit || $canResetPassword || $canManageCourseEvaluations ? [...$columns, '__actions' => 'Aksi'] : $columns;
        @endphp
        <div class="table-wrap">
            <table id="master-data-table" data-datatable="master-data" data-master-columns='@json(array_keys($columns))' data-ajax-url="{{ route('master-data.'.$module.'.data', request()->query()) }}">
                <thead>
                    <tr>
                        @foreach ($tableColumns as $field => $label)
                            <th @if ($field === '__actions') class="no-export" @endif>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    @endif

    @if ($canCreate || $canEdit || $canResetPassword || $canSync || $canManageCourseEvaluations)
        <div
            id="master-data-actions-root"
            data-module="{{ $module }}"
            data-title="{{ $title }}"
            data-store-url="{{ route('master-data.'.$module.'.store') }}"
            data-update-url-template="{{ route('master-data.'.$module.'.update', '__RECORD__') }}"
            data-edit-url-template="{{ route('master-data.'.$module.'.edit-data', '__RECORD__') }}"
            data-columns='@json($columns)'
            data-create-fields='@json($createFields)'
            data-edit-fields='@json($editFields)'
            data-can-create="{{ $canCreate ? 'true' : 'false' }}"
            data-can-edit="{{ $canEdit ? 'true' : 'false' }}"
            data-can-course-evaluations="{{ $canManageCourseEvaluations ? 'true' : 'false' }}"
            data-can-delete-course-evaluations="{{ $canDeleteCourseEvaluations ? 'true' : 'false' }}"
            @if ($canManageCourseEvaluations)
                data-evaluations-url-template="{{ route('master-data.mata-kuliah.evaluations', '__RECORD__') }}"
                data-evaluation-delete-url-template="{{ route('master-data.mata-kuliah.evaluations.delete', ['record' => '__RECORD__', 'evaluation' => '__EVALUATION__']) }}"
            @endif
            data-can-reset-password="{{ $canResetPassword ? 'true' : 'false' }}"
            data-can-reset-password="{{ $canResetPassword ? 'true' : 'false' }}"
            @if ($canResetPassword) data-reset-url-template="{{ route('master-data.'.$module.'.reset-password', '__RECORD__') }}" @endif
            data-can-sync="{{ $canSync ? 'true' : 'false' }}"
            data-can-sync-by-identity="{{ $canSyncByIdentity ? 'true' : 'false' }}"
            data-sync-fields='@json($syncFields)'
            @if ($syncProgramId) data-sync-program-id="{{ $syncProgramId }}" @endif
            @if (in_array($module, ['fakultas', 'program-studi'], true)) data-lecturer-search-url="{{ route('master-data.'.$module.'.lecturers') }}" @elseif ($module === 'mahasiswa' && $canEdit) data-lecturer-search-url="{{ route('master-data.fakultas.lecturers') }}" @endif
            @if ($module === 'mahasiswa' && $canEdit) data-origin-search-url="{{ route('master-data.mahasiswa.origins') }}" @endif
            @if ($module === 'mahasiswa' && $canEdit) data-regions-search-url="{{ route('master-data.mahasiswa.regions') }}" @endif
            @if ($canSync) data-sync-url="{{ route('master-data.'.$module.'.sync') }}" @endif
        ></div>
    @endif
@endsection
