@extends('layouts.simak')

@section('title', 'Registrasi Ulang | SIAKAD')
@section('topbar_title', 'Registrasi Ulang')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Registrasi Ulang</h1>
            <div class="module-alert" role="note"><p class="subtitle">Cari mahasiswa untuk melihat registrasi pada kalender akademik aktif program studinya.</p></div>
        </div>
    </div>

    <form class="reenrollment-search" method="POST" action="{{ route('re-enrollment.search') }}">
        @csrf
        <div>
            <label for="reenrollment-npm">NPM Mahasiswa</label>
            <input id="reenrollment-npm" name="npm" value="{{ old('npm', $npm) }}" maxlength="24" required autocomplete="off">
            @error('npm')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <button class="button button-primary" type="submit">Cari Mahasiswa</button>
    </form>

    @error('registration')<p class="field-error" role="alert">{{ $message }}</p>@enderror

    @if ($notFound)
        <div class="master-empty-state" role="status">
            <strong>Mahasiswa tidak ditemukan</strong>
            Periksa NPM yang dimasukkan, lalu coba kembali.
        </div>
    @elseif ($student)
        <section class="reenrollment-result" aria-label="Hasil pencarian mahasiswa">
            <div class="reenrollment-student">
                <strong>{{ $student->nama_mahasiswa }}</strong>
                <span>NPM: {{ $student->nim }}</span>
                <span>Program studi: {{ $student->nama_program_studi ?? 'Program studi tidak ditemukan' }}</span>
            </div>

            @if ($calendars->isEmpty())
                <p class="reenrollment-empty">Tidak ada kalender akademik aktif untuk program studi mahasiswa ini.</p>
            @else
                <div class="table-wrap">
                    <table data-simple-search-table>
                        <thead>
                            <tr>
                                <th>NPM</th>
                                <th>Nama Mahasiswa</th>
                                <th>Tahun Akademik Aktif</th>
                                <th>Program Studi</th>
                                <th>Status Registrasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($calendars as $calendar)
                                <tr>
                                    <td>{{ $student->nim }}</td>
                                    <td>{{ $student->nama_mahasiswa }}</td>
                                    <td>{{ $calendar->namatahun }}</td>
                                    <td>{{ $calendar->nama_program_studi }}</td>
                                    <td>
                                        @if ($calendar->registered)
                                            <span class="reenrollment-status is-registered">Telah teregistrasi<small>Petugas: {{ $calendar->registered_by }}</small></span>
                                        @else
                                            <span class="reenrollment-status">Belum teregistrasi</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="reenrollment-action">
                                            @if ($calendar->registered)
                                                <form method="POST" action="{{ route('re-enrollment.cancel', $calendar->id_kalender) }}" data-reenrollment-confirm data-confirm-title="Batalkan Registrasi" data-confirm-message="Batalkan registrasi {{ $student->nim }} pada {{ $calendar->namatahun }}?">
                                                    @csrf
                                                    <input type="hidden" name="id_mahasiswa" value="{{ $student->id_mahasiswa }}">
                                                    <button class="button button-danger button-small" type="submit">Batal Registrasi</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('re-enrollment.register', $calendar->id_kalender) }}" data-reenrollment-confirm data-confirm-title="Konfirmasi Registrasi Ulang" data-confirm-message="Registrasikan {{ $student->nim }} pada {{ $calendar->namatahun }}?">
                                                    @csrf
                                                    <input type="hidden" name="id_mahasiswa" value="{{ $student->id_mahasiswa }}">
                                                    <button class="button button-primary button-small" type="submit">Registrasi</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <div class="calendar-modal-backdrop" data-reenrollment-confirm-modal hidden>
        <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="reenrollment-confirm-title" aria-describedby="reenrollment-confirm-message" tabindex="-1">
            <div class="calendar-modal-head">
                <div>
                    <h2 id="reenrollment-confirm-title" data-reenrollment-confirm-title>Konfirmasi Registrasi Ulang</h2>
                    <p class="calendar-modal-copy reenrollment-confirm-copy" id="reenrollment-confirm-message" data-reenrollment-confirm-message></p>
                </div>
                <button class="calendar-modal-close" type="button" data-reenrollment-confirm-close aria-label="Tutup dialog">×</button>
            </div>
            <div class="calendar-modal-actions">
                <button class="button" type="button" data-reenrollment-confirm-cancel>Batal</button>
                <button class="button button-primary" type="button" data-reenrollment-confirm-submit>Ya, lanjutkan</button>
            </div>
        </section>
    </div>
@endsection