@extends('layouts.simak')

@section('title', 'Detail Penugasan Dosen | SIAKAD')
@section('topbar_title', 'Detail Penugasan Dosen')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Tesis & Disertasi · {{ $statusLabel }}</p>
            <h1>Penugasan Dosen</h1>
            <p class="subtitle">{{ $thesis->nim }} · {{ $thesis->nama_mahasiswa }} · {{ $thesis->nama_program_studi }}</p>
        </div>
        <a class="button" href="{{ route('graduate_theses.assignments.index', ['program' => $thesis->id_prodi]) }}">Kembali ke Daftar</a>
    </div>
    @if (session('status'))
        <div class="module-alert" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="thesis-assignment-alert" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="thesis-section">
        <h2>Identitas Mahasiswa dan Tesis & Disertasi</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>NPM</th><td>{{ $thesis->nim }}</td><th>Nama</th><td>{{ $thesis->nama_mahasiswa }}</td></tr>
                    <tr><th>Fakultas</th><td>{{ $thesis->nama_fakultas ?: '—' }}</td><th>Program Studi</th><td>{{ $thesis->nama_program_studi ?: '—' }}</td></tr>
                    <tr><th>Judul Tesis & Disertasi</th><td colspan="3">{{ $thesis->judul }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    @if ($canEdit)
        <section class="thesis-section">
            <h2>Set Dosen Pembimbing dan Penguji</h2>
            <form method="POST" action="{{ route('graduate_theses.assignments.update', $thesis->id) }}">
                @csrf
                @method('PUT')
                <div class="field-grid">
                    @foreach ([
                        'pembimbing1' => ['label' => 'Dosen Pembimbing 1', 'required' => true],
                        'pembimbing2' => ['label' => 'Dosen Pembimbing 2', 'required' => false],
                        'penguji1' => ['label' => 'Dosen Penguji 1', 'required' => false],
                        'penguji2' => ['label' => 'Dosen Penguji 2', 'required' => false],
                        'penguji3' => ['label' => 'Dosen Penguji 3', 'required' => false],
                    ] as $field => $settings)
                        <div>
                            <label for="assignment-{{ $field }}">{{ $settings['label'] }}{{ $settings['required'] ? ' *' : '' }}</label>
                            <select id="assignment-{{ $field }}" name="{{ $field }}" @required($settings['required']) data-searchable-select="filter">
                                <option value="">Pilih {{ $settings['label'] }}</option>
                                @foreach ($lecturers as $lecturer)
                                    @php
                                        $lecturerName = \App\Support\LecturerName::format(
                                            ucwords(mb_strtolower($lecturer->nama_dosen)),
                                            $lecturer->gelar_depan,
                                            $lecturer->gelar_belakang,
                                        );
                                    @endphp
                                    <option value="{{ $lecturer->id_dosen }}" @selected((string) old($field, $thesis->{$field}) === (string) $lecturer->id_dosen)>{{ $lecturerName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                <div class="form-actions"><button class="button button-primary" type="submit">Simpan Penugasan Dosen</button></div>
            </form>
        </section>

        <section class="thesis-section">
            <h2>Revisi Judul Tesis & Disertasi</h2>
            <form method="POST" action="{{ route('graduate_theses.assignments.title.update', $thesis->id) }}">
                @csrf
                @method('PUT')
                <div>
                    <label for="assigned-thesis-title">Judul tesis & disertasi</label>
                    <input id="assigned-thesis-title" name="judul" value="{{ old('judul', $thesis->judul) }}" maxlength="2000" required>
                </div>
                <div class="form-actions"><button class="button" type="submit">Simpan Revisi Judul</button></div>
            </form>
        </section>
    @else
        <section class="thesis-section">
            <h2>Susunan Dosen</h2>
            <div class="table-wrap">
                <table data-simple-search-table>
                    <thead><tr><th>Peran</th><th>Dosen</th></tr></thead>
                    <tbody>
                        @foreach ([
                            'pembimbing1' => 'Pembimbing 1',
                            'pembimbing2' => 'Pembimbing 2',
                            'penguji1' => 'Penguji 1',
                            'penguji2' => 'Penguji 2',
                            'penguji3' => 'Penguji 3',
                        ] as $field => $role)
                            <tr><td>{{ $role }}</td><td>{{ $assignmentNames[$field] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="master-empty-state">Akun Anda memiliki akses lihat saja. Perubahan penugasan hanya dapat dilakukan admin atau pengelola program studi.</div>
        </section>
    @endif
@endsection
