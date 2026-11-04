@extends('layouts.simak')

@section('title', 'Detail Verifikasi Judul | SIAKAD')
@section('topbar_title', 'Detail Verifikasi Judul')

@push('styles')
    <style>
        .thesis-title-alert { margin: 12px 0; padding: 12px 15px; border: 1px solid #e8c9bf; border-left: 4px solid #aa5038; border-radius: 6px; background: #fff8f5; color: #713b2c; }
        .thesis-title-alert ul { margin: 0; padding-left: 20px; }
        .thesis-title-form textarea { width: 100%; min-height: 130px; padding: 12px; border: 1px solid #c8cc9d; border-radius: 5px; background: #fffef6; color: #293c2c; font: inherit; font-size: 12px; resize: vertical; }
        .thesis-title-proposal-copy { white-space: pre-wrap; overflow-wrap: anywhere; }
        .thesis-title-form [data-title-assignment-fields][hidden] { display: none !important; }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Tesis & Disertasi</p>
            <h1>Verifikasi Usulan Judul</h1>
            <p class="subtitle">Tinjau identitas mahasiswa dan isi proposal sebelum menyimpan keputusan.</p>
        </div>
        <a class="button" href="{{ route('graduate_theses.titles.validation.index', ['program' => $proposal->id_prodi]) }}">Kembali ke Daftar</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="thesis-title-alert" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="thesis-section">
        <h2>Identitas Mahasiswa</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                    <tr><th>NPM</th><td>{{ $proposal->nim }}</td><th>Nama</th><td>{{ $proposal->nama_mahasiswa }}</td></tr>
                    <tr><th>Program Studi</th><td>{{ $proposal->nama_program_studi ?: '—' }}</td><th>Fakultas</th><td>{{ $proposal->nama_fakultas ?: '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $proposal->email ?: '—' }}</td><th>Tanggal Usulan</th><td>{{ $proposal->tanggal_usul }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="thesis-section">
        <h2>Usulan Proposal</h2>
        <dl class="master-identity-grid">
            <div><dt>Judul</dt><dd>{{ $proposal->judul }}</dd></div>
            <div><dt>Deskripsi</dt><dd class="thesis-title-proposal-copy">{{ $proposal->deskripsi }}</dd></div>
            <div><dt>Status saat ini</dt><dd>{{ $proposal->status_label }}</dd></div>
            <div><dt>Komentar sebelumnya</dt><dd class="thesis-title-proposal-copy">{{ $proposal->komentar ?: '—' }}</dd></div>
        </dl>
    </section>

    @if ($canVerify)
        <section class="thesis-section">
            <h2>Hasil Verifikasi</h2>
            <form class="thesis-title-form" method="POST" action="{{ route('graduate_theses.titles.validation.update', $proposal->id) }}">
                @csrf
                @method('PUT')
                <div class="field-grid">
                    <div>
                        <label for="title-validation-status">Keputusan</label>
                        <select id="title-validation-status" name="status" required data-title-validation-status>
                            <option value="">Pilih keputusan</option>
                            <option value="1" @selected((string) old('status', $proposal->status) === '1')>Proposal diterima</option>
                            <option value="2" @selected((string) old('status', $proposal->status) === '2')>Proposal ditolak / perlu revisi</option>
                        </select>
                    </div>
                    <div data-title-assignment-fields @if ((string) old('status', $proposal->status) !== '1') hidden @endif>
                        <label for="title-validation-pembimbing1">Dosen Pembimbing 1 *</label>
                        <select id="title-validation-pembimbing1" name="pembimbing1" @required((string) old('status', $proposal->status) === '1') data-searchable-select="filter">
                            <option value="">Pilih Dosen Pembimbing 1</option>
                            @foreach ($lecturers as $lecturer)
                                @php
                                    $lecturerName = \App\Support\LecturerName::format(
                                        ucwords(mb_strtolower($lecturer->nama_dosen)),
                                        $lecturer->gelar_depan,
                                        $lecturer->gelar_belakang,
                                    );
                                @endphp
                                <option value="{{ $lecturer->id_dosen }}" @selected((string) old('pembimbing1', data_get($assignments, 'pembimbing1', '')) === (string) $lecturer->id_dosen)>{{ $lecturerName }}</option>
                            @endforeach
                        </select>
                    </div>
                    @foreach ([
                        'pembimbing2' => 'Dosen Pembimbing 2 (opsional)',
                        'penguji1' => 'Dosen Penguji 1 (opsional)',
                        'penguji2' => 'Dosen Penguji 2 (opsional)',
                        'penguji3' => 'Dosen Penguji 3 (opsional)',
                    ] as $field => $label)
                        <div data-title-assignment-fields @if ((string) old('status', $proposal->status) !== '1') hidden @endif>
                            <label for="title-validation-{{ $field }}">{{ $label }}</label>
                            <select id="title-validation-{{ $field }}" name="{{ $field }}" data-searchable-select="filter">
                                <option value="">Tidak ditetapkan</option>
                                @foreach ($lecturers as $lecturer)
                                    @php
                                        $lecturerName = \App\Support\LecturerName::format(
                                            ucwords(mb_strtolower($lecturer->nama_dosen)),
                                            $lecturer->gelar_depan,
                                            $lecturer->gelar_belakang,
                                        );
                                    @endphp
                                    <option value="{{ $lecturer->id_dosen }}" @selected((string) old($field, data_get($assignments, $field, '')) === (string) $lecturer->id_dosen)>{{ $lecturerName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div style="grid-column:1 / -1">
                        <label for="title-validation-comment">Komentar / catatan prodi</label>
                        <textarea id="title-validation-comment" name="komentar" rows="5" maxlength="10000" required>{{ old('komentar', $proposal->komentar) }}</textarea>
                    </div>
                </div>
                <p class="subtitle">Jika usulan diterima, Pembimbing 1 wajib dipilih. Pembimbing 2 dan penguji dapat dikosongkan.</p>
                <div class="form-actions"><button class="button button-primary" type="submit">Simpan Verifikasi</button></div>
            </form>
        </section>
    @else
        <div class="master-empty-state">Akun Anda memiliki akses lihat saja. Keputusan verifikasi hanya dapat disimpan oleh admin atau pengelola program studi.</div>
    @endif
@endsection
