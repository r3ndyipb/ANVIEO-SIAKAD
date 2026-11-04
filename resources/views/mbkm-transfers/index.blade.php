@extends('layouts.simak')

@section('title', 'Transfer Nilai MBKM | SIAKAD')
@section('topbar_title', 'Transfer Nilai MBKM')

@push('styles')
    <style>
        .mbkm-search, .mbkm-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: end; gap: 12px; }
        .mbkm-search { grid-template-columns: minmax(220px, 380px) auto; max-width: 620px; margin: 16px 0 20px; }
        .mbkm-search > button { width: auto; white-space: nowrap; }
        .mbkm-form-grid { margin-top: 14px; }
        .mbkm-field { display: flex; flex-direction: column; gap: 7px; }
        .mbkm-field label { color: #43543c; font-size: 11px; font-weight: 800; }
        .mbkm-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin: 18px 0; }
        .mbkm-summary > div { padding: 13px 15px; border: 1px solid #e3e9e0; border-radius: 7px; background: #fbfdfb; }
        .mbkm-summary small, .mbkm-summary strong { display: block; }
        .mbkm-summary small { margin-bottom: 6px; color: #71805e; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .mbkm-summary strong { color: #315b3b; font-size: 15px; }
        .mbkm-instructions { margin: 14px 0; padding: 14px 17px; border: 1px solid #eadba5; border-radius: 7px; background: #fffae9; color: #695426; }
        .mbkm-instructions ul { margin: 8px 0 0; padding-left: 20px; }
        .mbkm-form-panel { margin: 18px 0; padding: 18px; border: 1px solid #e3e9e0; border-radius: 7px; background: #fff; }
        .mbkm-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        @media (max-width: 640px) { .mbkm-search { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Transfer Nilai MBKM</h1>
            <div class="module-alert" role="note"><p class="subtitle">Konversi mata kuliah pertukaran pelajar atau kegiatan MBKM ke KRS semester aktif.</p></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form class="mbkm-search" method="GET" action="{{ route('mbkm-transfers.index') }}">
        <div class="mbkm-field">
            <label for="mbkm-search">NPM mahasiswa</label>
            <input id="mbkm-search" type="search" name="search" maxlength="24" required value="{{ $filters['search'] ?? '' }}" placeholder="Masukkan NPM">
        </div>
        <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari Mahasiswa</button>
    </form>

    @if (filled($filters['search'] ?? null) && ! $student)
        <div class="master-empty-state"><strong>Mahasiswa tidak ditemukan</strong>NPM tidak ditemukan dalam cakupan akun ini.</div>
    @endif

    @if ($student)
        <section class="panel">
            <div class="page-heading">
                <div>
                    <h2>{{ $student->nama_mahasiswa }}</h2>
                    <p class="subtitle">{{ $student->nim }} · {{ $student->nama_program_studi ?: '-' }}</p>
                </div>
            </div>

            @if (! $calendar)
                <div class="master-empty-state"><strong>Kalender aktif belum tersedia</strong>Program studi mahasiswa belum memiliki kalender aktif.</div>
            @else
                <div class="mbkm-summary">
                    <div><small>Tahun Akademik</small><strong>{{ $calendar->namatahun ?: $calendar->tahun }}</strong></div>
                    <div><small>Registrasi Ulang</small><strong>{{ $registered ? 'Sudah' : 'Belum' }}</strong></div>
                    <div>
                        <small>Status MBKM</small>
                        <strong>{{ $isMbkm ? 'Kampus Merdeka' : 'Belum ditetapkan' }}</strong>
                        @if ($canRegisterMbkm)
                            <form method="POST" action="{{ route('mbkm-transfers.register', $student->id_mahasiswa) }}" style="margin-top:8px" onsubmit="return window.confirm('Daftarkan {{ $student->nama_mahasiswa }} sebagai mahasiswa MBKM pada {{ $calendar->namatahun ?: $calendar->tahun }}?')">
                                @csrf
                                <button class="button button-primary" type="submit"><span data-lucide="badge-check"></span> Daftarkan Status MBKM</button>
                            </form>
                        @elseif (! $registered)
                            <small>Registrasi ulang diperlukan</small>
                        @endif
                        @if ($canRemoveMbkm)
                            <form method="POST" action="{{ route('mbkm-transfers.unregister', $student->id_mahasiswa) }}" style="margin-top:8px" onsubmit="return window.confirm('Hapus status MBKM {{ $student->nama_mahasiswa }} pada {{ $calendar->namatahun ?: $calendar->tahun }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="button button-small" type="submit"><span data-lucide="circle-minus"></span> Hapus Status MBKM</button>
                            </form>
                        @elseif ($isMbkm && $mbkmCourses->isNotEmpty())
                            <small>Status tidak dapat dihapus karena KRS MBKM sudah diisi.</small>
                        @endif
                    </div>
                    <div><small>SKS Reguler</small><strong>{{ number_format($regularCredits, 2) }} SKS</strong></div>
                    <div><small>SKS MBKM</small><strong>{{ number_format($mbkmCourses->sum(fn ($course) => (float) $course->sks_mata_kuliah), 2) }} SKS</strong></div>
                    <div><small>Total SKS</small><strong>{{ number_format($totalCredits, 2) }} / 24 SKS</strong></div>
                </div>

                <div class="mbkm-instructions" role="note">
                    <strong>Ketentuan</strong>
                    <ul>
                        <li>Mahasiswa harus sudah registrasi ulang dan berstatus MBKM pada kalender aktif.</li>
                        <li>Untuk konversi non-pertukaran, kegiatan MBKM mahasiswa pada kalender ini harus sudah tersedia.</li>
                        <li>Total SKS reguler dan konversi dalam semester ini tidak boleh melebihi 24 SKS.</li>
                    </ul>
                </div>

                @if ($registered && $isMbkm)
                    <div class="mbkm-form-panel">
                        <h2>Pertukaran Pelajar</h2>
                        <form method="POST" action="{{ route('mbkm-transfers.store', $student->id_mahasiswa) }}">
                            @csrf
                            <input type="hidden" name="mode" value="exchange">
                            <div class="mbkm-form-grid">
                                <div class="mbkm-field">
                                    <label for="mbkm-origin-pt">Perguruan tinggi asal</label>
                                    <select id="mbkm-origin-pt" name="id_perguruan_tinggi_asal" required data-searchable-select="filter">
                                        <option value="">Pilih perguruan tinggi</option>
                                        @foreach ($originInstitutions as $institution)
                                            <option value="{{ $institution->id_perguruan_tinggi }}">{{ $institution->kode_perguruan_tinggi }} · {{ $institution->nama_perguruan_tinggi }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mbkm-field"><label for="mbkm-origin-code">Kode mata kuliah asal</label><input id="mbkm-origin-code" name="kode_mk_asal" maxlength="200" required></div>
                                <div class="mbkm-field"><label for="mbkm-origin-name">Nama mata kuliah asal</label><input id="mbkm-origin-name" name="nama_mk_asal" maxlength="200" required></div>
                                <div class="mbkm-field"><label for="mbkm-origin-credits">SKS mata kuliah asal</label><input id="mbkm-origin-credits" name="sks_asal" type="number" min="0" max="99" step="1" required></div>
                                <div class="mbkm-field"><label for="mbkm-origin-grade">Nilai huruf asal</label><input id="mbkm-origin-grade" name="nilai_huruf_asal" maxlength="4" required></div>
                                <div class="mbkm-field">
                                    <label for="mbkm-course-exchange">Mata kuliah diakui</label>
                                    <select id="mbkm-course-exchange" name="id_matkul" required data-searchable-select="filter">
                                        <option value="">Pilih mata kuliah</option>
                                        @foreach ($courses as $course)
                                            <option value="{{ $course->id_matkul }}">{{ $course->kode_mata_kuliah }} · {{ $course->nama_mata_kuliah }} ({{ $course->sks_mata_kuliah }} SKS)</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mbkm-field"><label for="mbkm-score-exchange">Nilai angka diakui (0–100)</label><input id="mbkm-score-exchange" name="nilai_angka" type="number" min="0" max="100" step="0.01" required></div>
                            </div>
                            <div class="mbkm-actions" style="margin-top:14px"><button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan Konversi</button></div>
                        </form>
                    </div>

                    <div class="mbkm-form-panel">
                        <h2>Non-Pertukaran Pelajar</h2>
                        @if ($activities->isEmpty())
                            <div class="master-empty-state"><strong>Kegiatan MBKM belum tersedia</strong>Tambahkan aktivitas dan peran mahasiswa pada kalender aktif sebelum mengonversi nilai.</div>
                        @else
                            <form method="POST" action="{{ route('mbkm-transfers.store', $student->id_mahasiswa) }}">
                                @csrf
                                <input type="hidden" name="mode" value="non_exchange">
                                <div class="mbkm-form-grid">
                                    <div class="mbkm-field">
                                        <label for="mbkm-activity">Kegiatan mahasiswa</label>
                                        <select id="mbkm-activity" name="id_kegiatan" required data-searchable-select="filter">
                                            <option value="">Pilih kegiatan</option>
                                            @foreach ($activities as $activity)
                                                <option value="{{ $activity->id_kegiatan }}">{{ $activity->judul }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mbkm-field">
                                        <label for="mbkm-course-non-exchange">Mata kuliah diakui</label>
                                        <select id="mbkm-course-non-exchange" name="id_matkul" required data-searchable-select="filter">
                                            <option value="">Pilih mata kuliah</option>
                                            @foreach ($courses as $course)
                                                <option value="{{ $course->id_matkul }}">{{ $course->kode_mata_kuliah }} · {{ $course->nama_mata_kuliah }} ({{ $course->sks_mata_kuliah }} SKS)</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mbkm-field"><label for="mbkm-score-non-exchange">Nilai angka diakui (0–100)</label><input id="mbkm-score-non-exchange" name="nilai_angka" type="number" min="0" max="100" step="0.01" required></div>
                                </div>
                                <div class="mbkm-actions" style="margin-top:14px"><button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan Konversi</button></div>
                            </form>
                        @endif
                    </div>
                @elseif (! $registered)
                    <div class="calendar-modal-error" role="alert">Mahasiswa belum melakukan registrasi ulang pada kalender aktif.</div>
                @else
                    <div class="calendar-modal-error" role="alert">Status mahasiswa belum ditetapkan sebagai mahasiswa MBKM pada kalender aktif.</div>
                @endif

                <h2>Mata Kuliah Konversi Semester Ini</h2>
                @if ($mbkmCourses->isNotEmpty())
                    <div class="table-wrap">
                        <table data-simple-search-table>
                            <thead><tr><th>Kode MK</th><th>Mata Kuliah</th><th>Jenis MBKM</th><th>Asal/Kegiatan</th><th>Nilai Asal</th><th>Nilai Diakui</th><th>SKS</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @foreach ($mbkmCourses as $course)
                                    <tr>
                                        <td>{{ $course->kode_mata_kuliah ?: '-' }}</td>
                                        <td>{{ $course->nama_mata_kuliah ?: '-' }}</td>
                                        <td>{{ $course->id_kegiatan ? 'Non-Pertukaran Pelajar' : 'Pertukaran Pelajar' }}</td>
                                        <td>{{ $course->id_kegiatan ? ($course->activity_title ?: '-') : ($course->nama_mk_asal ?: '-') }}</td>
                                        <td>{{ $course->nilai_huruf_asal ?: '-' }}</td>
                                        <td>{{ $course->nilai_huruf ?: '-' }} · {{ number_format((float) $course->nilai_angka, 2) }}</td>
                                        <td>{{ number_format((float) $course->sks_mata_kuliah, 2) }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('mbkm-transfers.destroy', ['student' => $student->id_mahasiswa, 'krs' => $course->id_krs]) }}" onsubmit="return window.confirm('Hapus konversi {{ addslashes($course->nama_mata_kuliah) }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="table-icon-button reset" type="submit" title="Hapus konversi" aria-label="Hapus konversi {{ $course->nama_mata_kuliah }}"><span data-lucide="trash-2"></span></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="master-empty-state"><strong>Belum ada mata kuliah konversi</strong>Konversi MBKM semester ini akan tampil di sini.</div>
                @endif
            @endif
        </section>
    @endif
@endsection