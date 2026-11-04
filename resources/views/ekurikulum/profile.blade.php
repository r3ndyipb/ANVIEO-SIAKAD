@extends('layouts.simak')

@section('title', 'Profil Program Studi | SIAKAD')
@section('topbar_title', 'Profil Program Studi')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Kurikulum OBE</p>
            <h1>Profil Program Studi</h1>
            <div class="module-alert" role="note"><p class="subtitle">Identitas, visi keilmuan, dan misi program studi.</p></div>
        </div>
    </div>
    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div> @endif

    @if (count($programs) > 1)
        <form method="get" action="{{ route('ekurikulum.profile.index') }}" class="table-wrap mb-4">
            <label class="form-label" for="profile-program">Program studi</label>
            <select class="form-control" id="profile-program" name="prodi" onchange="this.form.submit()">
                <option value="">Pilih program studi</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id_prodi }}" @selected((string) $program->id_prodi === $selectedProgram)>{{ $program->nama_program_studi }} ({{ $program->nama_jenjang_pendidikan }})</option>
                @endforeach
            </select>
        </form>
    @endif

    @if ($profile)
        <div class="table-wrap">
            <table class="table table-bordered">
                <tbody>
                    <tr><th>Universitas</th><td>{{ $profile->nama_perguruan_tinggi ?: $siteConfiguration['institution_name'] }}</td></tr>
                    <tr><th>Fakultas</th><td>{{ $profile->nama_fakultas ?: '-' }}</td></tr>
                    <tr><th>Program studi</th><td>{{ $profile->nama_program_studi }} ({{ $profile->nama_jenjang_pendidikan }})</td></tr>
                    <tr><th>Izin pendirian</th><td>{{ $profile->izin_pendirian ?: '-' }}</td></tr>
                    <tr><th>Peringkat akreditasi</th><td>{{ $profile->peringkat_akreditasi ?: '-' }}</td></tr>
                    <tr><th>Gelar lulusan</th><td>{{ $profile->gelar_lulusan ?: '-' }}</td></tr>
                    <tr><th>Visi keilmuan</th><td>{!! nl2br(e($profile->visi_keilmuan ?: '-')) !!}</td></tr>
                    <tr><th>Misi program studi</th><td>{!! nl2br(e($profile->misi_prodi ?: '-')) !!}</td></tr>
                </tbody>
            </table>
            @if ($canEdit)
                <button class="button button-primary" type="button" data-dialog-open="edit-program-profile"><span data-lucide="pencil" aria-hidden="true"></span> Edit profil prodi</button>
                <dialog class="ekur-dialog" id="edit-program-profile" data-open-on-load="{{ request()->query('modal') === 'edit' ? 'true' : 'false' }}">
                    <div class="ekur-dialog-header">
                        <div><p class="eyebrow">E-Kurikulum OBE</p><h2>Edit Profil Program Studi</h2></div>
                        <button class="ekur-dialog-close" type="button" data-dialog-close aria-label="Tutup dialog">Tutup</button>
                    </div>
                    <form method="post" action="{{ route('ekurikulum.profile.update') }}" class="row g-3">
                        @csrf
                        @method('put')
                        <div class="col-md-6"><label class="form-label" for="profile-permit">Izin pendirian (SK)</label><input class="form-control" id="profile-permit" name="izin_pendirian" maxlength="255" value="{{ old('izin_pendirian', $profile->izin_pendirian) }}"></div>
                        <div class="col-md-6"><label class="form-label" for="profile-accreditation">Peringkat akreditasi</label><input class="form-control" id="profile-accreditation" name="peringkat_akreditasi" maxlength="255" value="{{ old('peringkat_akreditasi', $profile->peringkat_akreditasi) }}"></div>
                        <div class="col-md-6"><label class="form-label" for="profile-degree">Gelar lulusan</label><input class="form-control" id="profile-degree" name="gelar_lulusan" maxlength="255" value="{{ old('gelar_lulusan', $profile->gelar_lulusan) }}"></div>
                        <div class="col-12 profile-textarea-field">
                            <label class="form-label" for="profile-vision">Visi keilmuan</label>
                            <textarea class="form-control profile-textarea" id="profile-vision" name="visi_keilmuan" rows="5" placeholder="Tuliskan visi keilmuan program studi...">{{ old('visi_keilmuan', $profile->visi_keilmuan) }}</textarea>
                            <small>Gambarkan arah dan keunggulan keilmuan program studi.</small>
                        </div>
                        <div class="col-12 profile-textarea-field">
                            <label class="form-label" for="profile-mission">Misi program studi</label>
                            <textarea class="form-control profile-textarea" id="profile-mission" name="misi_prodi" rows="6" placeholder="Tuliskan misi program studi...">{{ old('misi_prodi', $profile->misi_prodi) }}</textarea>
                            <small>Jelaskan langkah utama untuk mewujudkan visi program studi.</small>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button class="button button-secondary" type="button" data-dialog-close>Batal</button>
                            <button class="button button-primary" type="submit"><span data-lucide="save" aria-hidden="true"></span> Simpan perubahan</button>
                        </div>
                    </form>
                </dialog>
            @endif
        </div>
    @else
        <div class="table-wrap" role="status">Pilih program studi untuk melihat profilnya.</div>
    @endif
@endsection
