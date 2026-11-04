@extends('layouts.simak')

@section('title', 'Registrasi Ulang | SIAKAD')
@section('topbar_title', 'Registrasi Ulang')

@push('styles')
    <style>
        .reenrollment-search { display: grid; grid-template-columns: minmax(0, 380px) max-content; align-items: end; gap: 10px; margin-bottom: 26px; }
        .reenrollment-search .button { justify-self: start; }
        .reenrollment-search label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .reenrollment-search input { width: 100%; min-height: 42px; padding: 0 11px; border: 1px solid #c8cc9d; border-radius: 5px; background: #fffef6; color: #293c2c; font: inherit; font-size: 12px; }
        .reenrollment-search input:focus { outline: 3px solid rgba(221,187,52,.28); border-color: #8a9e3e; }
        .reenrollment-result { margin-top: 22px; }
        .reenrollment-student { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(140px, .8fr); gap: 8px 18px; margin-bottom: 18px; padding: 15px 17px; border-left: 3px solid #6f9e73; background: #f4f8f2; }
        .reenrollment-student strong { color: #2d5137; font-size: 13px; }
        .reenrollment-student span { color: #68745e; font-size: 11px; }
        .reenrollment-state { color: #6d775e; font-size: 12px; }
        .reenrollment-status { display: inline-block; color: #68745e; font-size: 11px; line-height: 1.5; }
        .reenrollment-status.is-registered { color: #315b3b; font-weight: 700; }
        .reenrollment-status small { display: block; color: #77806b; font-size: 10px; font-weight: 400; }
        .reenrollment-action { display: flex; flex-wrap: wrap; gap: 6px; }
        .reenrollment-action form { margin: 0; }
        .reenrollment-empty { padding: 18px 0; color: #6d775e; font-size: 12px; }
        .reenrollment-confirm-copy { margin: 0; color: #4c5e4b; font-size: 12px; line-height: 1.6; }
        [data-reenrollment-confirm-modal][hidden] { display: none; }
        @media (max-width: 620px) {
            .reenrollment-search { grid-template-columns: 1fr; }
            .reenrollment-search .button { justify-self: start; }
            .reenrollment-student { grid-template-columns: 1fr; }
        }
    </style>
@endpush

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