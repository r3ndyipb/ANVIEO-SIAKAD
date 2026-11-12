@extends('layouts.simak')

@section('title', 'Aktivitas Mahasiswa | SIAKAD')
@section('topbar_title', 'Aktivitas Mahasiswa')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Aktivitas Mahasiswa</h1>
            <div class="module-alert" role="note"><p class="subtitle">Kegiatan, peserta, pembimbing, dan penguji disinkronkan dengan Feeder pada kalender aktif.</p></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($user->role === 'admin')
        <form class="student-activity-filter" method="GET" action="{{ route('student-activities.index') }}">
            <div class="student-activity-field">
                <label for="student-activity-program">Program studi</label>
                <select id="student-activity-program" name="program" data-searchable-select="filter" required onchange="this.form.submit()">
                    <option value="">Pilih program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected((string) $programId === (string) $program->id_prodi)>
                            {{ $program->nama_program_studi }} {{ $program->nama_jenjang_pendidikan ? '('.$program->nama_jenjang_pendidikan.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($calendar)
                <span class="status-chip active">{{ $calendar->namatahun ?: $calendar->tahun }}</span>
            @endif
        </form>
    @elseif ($calendar)
        <p class="subtitle">{{ $calendar->namatahun ?: $calendar->tahun }}</p>
    @endif

    @if ($user->role === 'admin' && $programId === '')
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih program studi untuk melihat kegiatan pada kalender aktif.</div>
    @elseif ($programId !== '' && ! $calendar)
        <div class="master-empty-state"><strong>Kalender aktif belum tersedia</strong>Program studi ini belum memiliki kalender aktif.</div>
    @else
        <div class="student-activity-notice" role="note"><strong>Sinkronisasi Feeder aktif.</strong> Perubahan kegiatan, peserta, pembimbing, dan penguji akan dikirim ke Feeder terlebih dahulu. Jika Feeder gagal, data SIAKAD tidak diubah.</div>

        <div class="page-actions">
            <button class="button button-primary" type="button" data-student-activity-open><span data-lucide="plus"></span> Tambah Aktivitas</button>
        </div>
        <div class="calendar-modal-backdrop student-activity-modal-backdrop" data-student-activity-modal data-open-on-load="{{ $errors->any() ? 'true' : 'false' }}" hidden>
            <section class="calendar-modal student-activity-modal" role="dialog" aria-modal="true" aria-labelledby="student-activity-modal-title" tabindex="-1">
                <div class="calendar-modal-head">
                    <div><h2 id="student-activity-modal-title">Tambah Aktivitas</h2><p class="calendar-modal-copy">Aktivitas akan disimpan di SIAKAD setelah berhasil dikirim ke Feeder.</p></div>
                    <button class="calendar-modal-close" type="button" data-student-activity-close aria-label="Tutup modal"><span data-lucide="x"></span></button>
                </div>
                <form method="POST" action="{{ route('student-activities.store') }}">
                    @csrf
                    <input type="hidden" name="program" value="{{ $programId }}">
                    <div class="student-activity-grid">
                        <div class="student-activity-field">
                            <label for="activity-kind">Jenis kegiatan</label>
                            <select id="activity-kind" name="jenis_kegiatan" required data-searchable-select="filter">
                                <option value="">Pilih jenis kegiatan</option>
                                @foreach (DB::connection('simak_main')->table('s1m4k_kodereferensi')->where('keterangan', 'GetJenisAktivitasMahasiswa')->orderBy('ket_referensi')->get(['kode_referensi', 'ket_referensi', 'untuk_kampus_merdeka']) as $kind)
                                    <option value="{{ $kind->kode_referensi }}" @selected(old('jenis_kegiatan') === $kind->kode_referensi)>{{ $kind->ket_referensi }}{{ $kind->untuk_kampus_merdeka === '1' ? ' (Kampus Merdeka)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="student-activity-field">
                            <label for="activity-program-mbkm">Program aktivitas Feeder</label>
                            <select id="activity-program-mbkm" name="program_mbkm" required>
                                <option value="">Pilih program aktivitas</option>
                                <option value="0" @selected(old('program_mbkm') === '0')>Mandiri</option>
                                <option value="1" @selected(old('program_mbkm') === '1')>Flagship</option>
                            </select>
                        </div>
                        <div class="student-activity-field student-status-form-field-wide">
                            <label for="activity-title">Judul kegiatan</label>
                            <input id="activity-title" name="judul" required maxlength="2000" value="{{ old('judul') }}">
                        </div>
                        <div class="student-activity-field"><label for="activity-location">Lokasi</label><input id="activity-location" name="lokasi" maxlength="100" value="{{ old('lokasi') }}"></div>
                        <div class="student-activity-field"><label for="activity-sk">Nomor SK</label><input id="activity-sk" name="no_sk" maxlength="50" value="{{ old('no_sk') }}"></div>
                        <div class="student-activity-field"><label for="activity-sk-date">Tanggal SK</label><input id="activity-sk-date" type="date" name="tgl_sk" value="{{ old('tgl_sk') }}"></div>
                        <div class="student-activity-field">
                            <label for="activity-membership">Jenis anggota</label>
                            <select id="activity-membership" name="jenis_anggota" required>
                                <option value="0" @selected(old('jenis_anggota', '0') === '0')>Personal</option>
                                <option value="1" @selected(old('jenis_anggota') === '1')>Kelompok</option>
                            </select>
                        </div>
                        <div class="student-activity-field student-status-form-field-wide">
                            <label for="activity-note">Keterangan</label>
                            <textarea id="activity-note" name="ket" rows="2" maxlength="2000">{{ old('ket') }}</textarea>
                        </div>
                    </div>
                    <div class="calendar-modal-actions">
                        <button class="button" type="button" data-student-activity-close>Batal</button>
                        <button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan ke Feeder</button>
                    </div>
                </form>
            </section>
        </div>

        @if ($activities->isNotEmpty())
            <div class="table-wrap">
                <table data-datatable="student-activities">
                    <thead><tr><th>No</th><th>Judul</th><th>Jenis</th><th>Program Feeder</th><th>Program Studi</th><th>Proses</th></tr></thead>
                    <tbody>
                        @foreach ($activities as $activity)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><a href="{{ route('student-activities.show', $activity->id_kegiatan) }}">{{ $activity->judul }}</a></td>
                                <td>{{ $activity->jenis_label ?: '-' }}</td>
                                <td>{{ (string) $activity->program_mbkm === '0' ? 'Mandiri' : ((string) $activity->program_mbkm === '1' ? 'Flagship' : 'Belum ditentukan') }}</td>
                                <td>{{ $activity->nama_program_studi }}</td>
                                <td class="student-activity-actions">
                                    <a class="table-icon-button" href="{{ route('student-activities.show', $activity->id_kegiatan) }}" title="Detail kegiatan" aria-label="Detail kegiatan {{ $activity->judul }}"><span data-lucide="eye"></span></a>
                                    <form method="POST" action="{{ route('student-activities.destroy', $activity->id_kegiatan) }}" onsubmit="return window.confirm('Hapus aktivitas {{ addslashes($activity->judul) }} dari Feeder dan SIAKAD?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="table-icon-button reset" type="submit" title="Hapus aktivitas" aria-label="Hapus aktivitas {{ $activity->judul }}"><span data-lucide="trash-2"></span></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="master-empty-state"><strong>Aktivitas belum tersedia</strong>Belum ada aktivitas pada kalender aktif program studi ini.</div>
        @endif
    @endif
@endsection