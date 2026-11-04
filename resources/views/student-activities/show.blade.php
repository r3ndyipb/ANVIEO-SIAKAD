@extends('layouts.simak')

@section('title', 'Detail Aktivitas Mahasiswa | SIAKAD')
@section('topbar_title', 'Aktivitas Mahasiswa')

@push('styles')
    <style>
        .activity-detail-grid, .activity-role-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; }
        .activity-panel { margin: 16px 0; padding: 16px; border: 1px solid #e3e9e0; border-radius: 7px; background: #fff; }
        .activity-panel h2 { margin: 0 0 14px; color: #315b3b; font-size: 16px; }
        .activity-panel label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .activity-panel form { margin: 0; }
        .activity-field { margin-bottom: 12px; }
        .activity-role-actions { display: flex; align-items: center; gap: 7px; }
        .activity-role-actions form { margin: 0; }
        @media (max-width: 640px) { .activity-detail-grid, .activity-role-grid { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ $activity->namatahun }}</p>
            <h1>{{ $activity->judul }}</h1>
            <p class="subtitle">{{ $activity->nama_program_studi }} · {{ $activity->jenis_anggota === '0' ? 'Personal' : 'Kelompok' }}</p>
        </div>
    </div>
    @if ($errors->any()) <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div> @endif
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    <div class="student-activity-actions" style="margin-bottom:14px">
        <a class="button" href="{{ route('student-activities.index', ['program' => $activity->id_prodi]) }}"><span data-lucide="arrow-left"></span> Kembali</a>
    </div>

    <section class="activity-panel">
        <h2>Ubah Aktivitas</h2>
        <form method="POST" action="{{ route('student-activities.update', $activity->id_kegiatan) }}">
            @csrf
            @method('PUT')
            <div class="activity-detail-grid">
                <div class="activity-field"><label for="activity-kind">Jenis kegiatan</label><select id="activity-kind" name="jenis_kegiatan" required data-searchable-select="filter">
                    @foreach (DB::connection('simak_main')->table('s1m4k_kodereferensi')->where('keterangan', 'GetJenisAktivitasMahasiswa')->orderBy('ket_referensi')->get(['kode_referensi', 'ket_referensi', 'untuk_kampus_merdeka']) as $kind)
                        <option value="{{ $kind->kode_referensi }}" @selected((string) $activity->jenis_kegiatan === (string) $kind->kode_referensi)>{{ $kind->ket_referensi }}{{ $kind->untuk_kampus_merdeka === '1' ? ' (Kampus Merdeka)' : '' }}</option>
                    @endforeach
                </select></div>
                <div class="activity-field"><label for="activity-program-mbkm">Program aktivitas Feeder</label><select id="activity-program-mbkm" name="program_mbkm" required>
                    <option value="">Pilih program aktivitas</option>
                    <option value="0" @selected((string) old('program_mbkm', $activity->program_mbkm) === '0')>Mandiri</option>
                    <option value="1" @selected((string) old('program_mbkm', $activity->program_mbkm) === '1')>Flagship</option>
                </select></div>
                <div class="activity-field"><label for="activity-title">Judul</label><input id="activity-title" name="judul" value="{{ $activity->judul }}" required maxlength="2000"></div>
                <div class="activity-field"><label for="activity-location">Lokasi</label><input id="activity-location" name="lokasi" value="{{ $activity->lokasi }}" maxlength="100"></div>
                <div class="activity-field"><label for="activity-sk">Nomor SK</label><input id="activity-sk" name="no_sk" value="{{ $activity->no_sk }}" maxlength="50"></div>
                <div class="activity-field"><label for="activity-sk-date">Tanggal SK</label><input id="activity-sk-date" type="date" name="tgl_sk" value="{{ $activity->tgl_sk }}"></div>
                <div class="activity-field"><label for="activity-members">Jenis anggota</label><select id="activity-members" name="jenis_anggota"><option value="0" @selected($activity->jenis_anggota === '0')>Personal</option><option value="1" @selected($activity->jenis_anggota === '1')>Kelompok</option></select></div>
                <div class="activity-field"><label for="activity-note">Keterangan</label><textarea id="activity-note" name="ket" rows="2">{{ $activity->ket }}</textarea></div>
            </div>
            <button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan ke Feeder</button>
        </form>
    </section>

    <section class="activity-panel">
        <h2>Peserta Kegiatan</h2>
        <form method="POST" action="{{ route('student-activities.participants.store', $activity->id_kegiatan) }}">
            @csrf
            <div class="activity-role-grid">
                <div class="activity-field"><label for="activity-student">Mahasiswa</label><select id="activity-student" name="id_registrasi_mahasiswa" required data-searchable-select="filter"><option value="">Pilih mahasiswa</option>@foreach ($students as $student)<option value="{{ $student->id_registrasi_mahasiswa }}">{{ $student->nim }} · {{ $student->nama_mahasiswa }}</option>@endforeach</select></div>
                @if ($activity->jenis_anggota === '1')
                    <div class="activity-field"><label for="activity-member-role">Peran</label><select id="activity-member-role" name="jenis_peran"><option value="1">Ketua</option><option value="2">Anggota</option></select></div>
                @else
                    <input type="hidden" name="jenis_peran" value="3">
                @endif
                <div class="activity-field"><button class="button button-primary" type="submit"><span data-lucide="user-plus"></span> Tambah Peserta</button></div>
            </div>
        </form>
        @if ($participants->isNotEmpty())
            <div class="table-wrap"><table data-simple-search-table><thead><tr><th>NPM</th><th>Nama</th><th>Peran</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($participants as $participant)
                    <tr><td>{{ $participant->nim }}</td><td>{{ $participant->nama_mahasiswa }}</td><td>{{ ['1' => 'Ketua', '2' => 'Anggota', '3' => 'Personal'][$participant->jenis_peran] ?? '-' }}</td><td class="activity-role-actions"><form method="POST" action="{{ route('student-activities.roles.destroy', ['activity' => $activity->id_kegiatan, 'role' => $participant->id_peran, 'type' => 'peserta']) }}" onsubmit="return window.confirm('Hapus peserta ini dari Feeder?')">@csrf @method('DELETE')<button class="table-icon-button reset" type="submit" title="Hapus peserta" aria-label="Hapus peserta {{ $participant->nama_mahasiswa }}"><span data-lucide="trash-2"></span></button></form></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>

    @foreach ([['pembimbing' => 'Pembimbing Kegiatan', 'items' => $advisors, 'field' => 'pembimbing_ke', 'route' => 'student-activities.advisors.store'], ['penguji' => 'Penguji Kegiatan', 'items' => $examiners, 'field' => 'penguji_ke', 'route' => 'student-activities.examiners.store']] as $section)
        @php($roleType = array_key_first(array_diff_key($section, ['items' => true, 'field' => true, 'route' => true])))
        <section class="activity-panel">
            <h2>{{ $section[$roleType] }}</h2>
            <form method="POST" action="{{ route($section['route'], $activity->id_kegiatan) }}">
                @csrf
                <div class="activity-role-grid">
                    <div class="activity-field"><label for="{{ $roleType }}-lecturer">Dosen</label><select id="{{ $roleType }}-lecturer" name="id_dosen" required data-searchable-select="filter"><option value="">Pilih dosen</option>@foreach ($lecturers as $lecturer)<option value="{{ $lecturer->id_dosen }}">{{ \App\Support\LecturerName::format($lecturer->nama_dosen, $lecturer->gelar_depan, $lecturer->gelar_belakang) }} · {{ $lecturer->nidn }}</option>@endforeach</select></div>
                    <div class="activity-field"><label for="{{ $roleType }}-category">Kategori kegiatan</label><select id="{{ $roleType }}-category" name="id_kategori" required data-searchable-select="filter"><option value="">Pilih kategori</option>@foreach ($categories as $category)<option value="{{ $category->kode_referensi }}">{{ $category->ket_referensi }}</option>@endforeach</select></div>
                    <div class="activity-field"><label for="{{ $roleType }}-number">{{ ucfirst($roleType) }} ke</label><input id="{{ $roleType }}-number" name="{{ $section['field'] }}" type="number" min="1" max="9" step="1" required></div>
                    <div class="activity-field"><button class="button button-primary" type="submit"><span data-lucide="user-plus"></span> Tambah {{ ucfirst($roleType) }}</button></div>
                </div>
            </form>
            @php($roleItems = $section['items'])
            @if ($roleItems->isNotEmpty())
                <div class="table-wrap"><table data-simple-search-table><thead><tr><th>NIDN</th><th>Nama Dosen</th><th>Urutan</th><th>Kategori</th><th>Aksi</th></tr></thead><tbody>
                    @foreach ($roleItems as $roleItem)
                        <tr><td>{{ $roleItem->nidn }}</td><td>{{ \App\Support\LecturerName::format($roleItem->nama_dosen, $roleItem->gelar_depan, $roleItem->gelar_belakang) }}</td><td>{{ $roleType === 'pembimbing' ? $roleItem->pembimbing_ke : $roleItem->penguji_ke }}</td><td>{{ $roleItem->category_name }}</td><td><form method="POST" action="{{ route('student-activities.roles.destroy', ['activity' => $activity->id_kegiatan, 'role' => $roleItem->id_peran, 'type' => $roleType]) }}" onsubmit="return window.confirm('Hapus peran dosen ini dari Feeder?')">@csrf @method('DELETE')<button class="table-icon-button reset" type="submit" title="Hapus peran" aria-label="Hapus peran {{ \App\Support\LecturerName::format($roleItem->nama_dosen, $roleItem->gelar_depan, $roleItem->gelar_belakang) }}"><span data-lucide="trash-2"></span></button></form></td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </section>
    @endforeach
@endsection