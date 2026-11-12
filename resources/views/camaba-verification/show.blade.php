@extends('layouts.simak')

@section('title', 'Verifikasi CAMABA | SIAKAD')
@section('topbar_title', 'Verifikasi CAMABA')



@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">Verifikasi CAMABA</p><h1>{{ $record->nama_mahasiswa }}</h1><p class="subtitle">{{ $record->nim }} · {{ $record->no_peserta }}</p></div>
    </div>
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="module-alert" role="note"><p>Verifikasi akan mengirim biodata dan riwayat pendidikan ke Feeder, lalu membuat akun mahasiswa pada data SIAKAD.</p></div>

    <form method="POST" action="{{ route('camaba-verification.verify', $record->id) }}" class="panel">
        @csrf
        <fieldset class="camaba-form-section">
            <legend>Identitas mahasiswa</legend>
            <div class="camaba-form-grid">
                <label>NPM<input name="nim" value="{{ old('nim', $record->nim) }}" maxlength="24" required></label>
                <label>Nama mahasiswa<input name="nama_mahasiswa" value="{{ old('nama_mahasiswa', $record->nama_mahasiswa) }}" maxlength="50" required></label>
                <label>Jenis kelamin<select name="jenis_kelamin" data-searchable-select required><option value="">Pilih</option><option value="L" @selected(old('jenis_kelamin', $record->jenis_kelamin) === 'L')>Laki-laki</option><option value="P" @selected(old('jenis_kelamin', $record->jenis_kelamin) === 'P')>Perempuan</option></select></label>
                <label>Agama<select name="id_agama" data-searchable-select required><option value="">Pilih agama</option>@foreach ($references->get('GetAgama', collect()) as $item)<option value="{{ $item->kode_referensi }}" @selected((string) old('id_agama', $record->id_agama) === (string) $item->kode_referensi)>{{ $item->ket_referensi }}</option>@endforeach</select></label>
                <label>NIK<input name="nik" value="{{ old('nik', $record->nik) }}" maxlength="16" inputmode="numeric"></label>
                <label>NISN<input name="nisn" value="{{ old('nisn', $record->nisn) }}" maxlength="50"></label>
                <label>Tempat lahir<input name="tempat_lahir" value="{{ old('tempat_lahir', $record->tempat_lahir) }}" maxlength="32" required></label>
                <label>Tanggal lahir<input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', str_replace('/', '-', $record->tanggal_lahir)) }}" required></label>
                <label>Nama ibu kandung<input name="nama_ibu" value="{{ old('nama_ibu', $record->nama_ibu) }}" maxlength="100" required></label>
                <label>Email<input type="email" name="email" value="{{ old('email', $record->email) }}" maxlength="60" required></label>
                <label>Telepon<input name="telepon" value="{{ old('telepon', $record->telepon) }}" maxlength="20"></label>
                <label>Handphone<input name="handphone" value="{{ old('handphone', $record->handphone) }}" maxlength="20" required></label>
                <label>Penerima KPS<select name="penerima_kps" data-searchable-select required><option value="0" @selected((string) old('penerima_kps', $record->penerima_kps) === '0')>Tidak</option><option value="1" @selected((string) old('penerima_kps', $record->penerima_kps) === '1')>Ya</option></select></label>
            </div>
        </fieldset>
        <fieldset class="camaba-form-section">
            <legend>Alamat</legend>
            <div class="camaba-form-grid">
                <label>Negara<select name="id_negara" data-searchable-select data-camaba-country required><option value="">Pilih negara</option>@foreach ($references->get('GetNegara', collect()) as $item)<option value="{{ $item->kode_referensi }}" @selected((string) old('id_negara', $record->id_negara ?: 'ID') === (string) $item->kode_referensi)>{{ $item->ket_referensi }}</option>@endforeach</select></label>
                <label>Wilayah / kecamatan<select name="id_wilayah" data-searchable-select data-camaba-region data-options-url="{{ route('camaba-verification.regions') }}" required><option value="">Pilih wilayah</option>@foreach ($regions as $item)<option value="{{ $item->kode_referensi }}" @selected((string) old('id_wilayah', '') === (string) $item->kode_referensi)>{{ trim(($item->ket_referensi2 ?? '').' - '.($item->nama_provinsi ?? ''), ' -') }}</option>@endforeach</select></label>
                <label>Kelurahan<input name="kelurahan" value="{{ old('kelurahan', $record->kelurahan) }}" maxlength="60" required></label>
            </div>
        </fieldset>
        <fieldset class="camaba-form-section">
            <legend>Riwayat pendaftaran</legend>
            <div class="camaba-form-grid">
                <label>Jenis pendaftaran<select name="id_jenis_daftar" data-searchable-select required><option value="">Pilih jenis</option>@foreach ($references->get('GetJenisPendaftaran', collect()) as $item)<option value="{{ $item->kode_referensi }}" @selected((string) old('id_jenis_daftar', $record->id_jenis_daftar) === (string) $item->kode_referensi)>{{ $item->ket_referensi }}</option>@endforeach</select></label>
                <label>Jalur pendaftaran<select name="id_jalur_daftar" data-searchable-select required><option value="">Pilih jalur</option>@foreach ($references->get('GetJalurMasuk', collect()) as $item)<option value="{{ $item->kode_referensi }}" @selected((string) old('id_jalur_daftar', $record->id_jalur_daftar) === (string) $item->kode_referensi)>{{ $item->ket_referensi }}</option>@endforeach</select></label>
                <label>Periode masuk<input name="id_periode_masuk" value="{{ old('id_periode_masuk', $record->id_periode_masuk) }}" maxlength="5" placeholder="YYYYS" required></label>
                <label>Tanggal daftar<input type="date" name="tanggal_daftar" value="{{ old('tanggal_daftar', $record->tanggal_daftar) }}" required></label>
                <label>Pembiayaan awal<select name="id_pembiayaan" data-searchable-select required><option value="">Pilih pembiayaan</option>@foreach ($references->get('GetPembiayaan', collect()) as $item)<option value="{{ $item->kode_referensi }}" @selected((string) old('id_pembiayaan', $record->id_pembiayaan) === (string) $item->kode_referensi)>{{ $item->ket_referensi }}</option>@endforeach</select></label>
                <label>Biaya masuk kuliah<input type="number" name="biaya_masuk_kuliah" value="{{ old('biaya_masuk_kuliah', $record->biaya_masuk_kuliah) }}" min="0" max="2147483647"></label>
                <label>Program studi<select name="id_prodi" data-searchable-select required><option value="">Pilih program studi</option>@foreach ($programs as $program)<option value="{{ $program->id_prodi }}" @selected((string) old('id_prodi', $record->id_prodi) === (string) $program->id_prodi)>{{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}</option>@endforeach</select></label>
            </div>
        </fieldset>
        <div class="camaba-form-actions"><a class="button button-secondary" href="{{ route('camaba-verification.index', ['period' => $record->id_periode_masuk]) }}"><span data-lucide="arrow-left"></span> Kembali</a><button class="button button-primary" type="submit" data-confirm-camaba><span data-lucide="badge-check"></span> Simpan dan verifikasi</button></div>
    </form>
@endsection