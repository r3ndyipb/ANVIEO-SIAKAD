@extends('layouts.simak')

@section('title', 'Tim Kurikulum | SIAKAD')
@section('topbar_title', 'Manajemen Tim Kurikulum')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">E-Kurikulum OBE</p>
            <h1>Manajemen Tim Kurikulum</h1>
            <div class="module-alert" role="note"><p class="subtitle">Akun tim kurikulum untuk program studi Anda.</p></div>
        </div>
        <button class="button button-primary" type="button" data-dialog-open="create-team-dialog">
            <span data-lucide="plus" aria-hidden="true"></span> Tambah anggota
        </button>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <dialog class="ekur-dialog" id="create-team-dialog" data-open-on-load="{{ request()->query('modal') === 'create' ? 'true' : 'false' }}">
        <div class="ekur-dialog-header">
            <div><p class="eyebrow">Akun Program Studi</p><h2>Tambah anggota tim</h2></div>
            <button class="ekur-dialog-close" type="button" data-dialog-close aria-label="Tutup dialog">Tutup</button>
        </div>
        <form method="post" action="{{ route('ekurikulum.teams.store') }}" class="row g-3">
            @csrf
            <div class="col-md-6"><label class="form-label" for="team-username">Username</label><input class="form-control" id="team-username" name="username" maxlength="100" value="{{ request()->query('modal') === 'create' ? old('username') : '' }}" required></div>
            <div class="col-md-6"><label class="form-label" for="team-name">Nama lengkap</label><input class="form-control" id="team-name" name="nama_lengkap" maxlength="100" value="{{ request()->query('modal') === 'create' ? old('nama_lengkap') : '' }}" required></div>
            <div class="col-md-6"><label class="form-label" for="team-email">Email</label><input class="form-control" id="team-email" type="email" name="email" maxlength="60" value="{{ request()->query('modal') === 'create' ? old('email') : '' }}" required></div>
            <div class="col-md-6"><label class="form-label" for="team-password">Password awal</label><input class="form-control" id="team-password" type="password" name="password" maxlength="100" required></div>
            <div class="col-md-6"><label class="form-label" for="team-phone">Nomor telepon</label><input class="form-control" id="team-phone" name="no_telp" maxlength="20" value="{{ request()->query('modal') === 'create' ? old('no_telp') : '' }}"></div>
            <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                <button class="button button-secondary" type="button" data-dialog-close>Batal</button>
                <button class="button button-primary" type="submit"><span data-lucide="save" aria-hidden="true"></span> Simpan anggota</button>
            </div>
        </form>
    </dialog>

    <div class="table-wrap">
        <table id="ekur-team-table" data-datatable="ekur-team">
            <thead><tr><th>Username</th><th>Nama lengkap</th><th>Program studi</th><th>Email</th><th>Telepon</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($teams as $team)
                    <tr>
                        <td>{{ $team->username }}</td>
                        <td>{{ $team->nama_lengkap }}</td>
                        <td>{{ $team->nama_program_studi ?: '-' }}</td>
                        <td>{{ $team->email }}</td>
                        <td>{{ $team->no_telp ?: '-' }}</td>
                        <td><span class="ekur-status-badge {{ $team->blokir === 'Y' ? 'is-blocked' : 'is-active' }}">{{ $team->blokir === 'Y' ? 'Diblokir' : 'Aktif' }}</span></td>
                        <td>
                            <button class="button button-secondary button-compact" type="button" data-dialog-open="edit-team-{{ $team->id_admin }}">
                                <span data-lucide="pencil" aria-hidden="true"></span> Ubah
                            </button>
                            <dialog class="ekur-dialog" id="edit-team-{{ $team->id_admin }}" data-open-on-load="{{ request()->query('modal') === 'edit' && (string) request()->query('account') === (string) $team->id_admin ? 'true' : 'false' }}">
                                <div class="ekur-dialog-header">
                                    <div><p class="eyebrow">Akun Tim Kurikulum</p><h2>Ubah {{ $team->nama_lengkap }}</h2></div>
                                    <button class="ekur-dialog-close" type="button" data-dialog-close aria-label="Tutup dialog">Tutup</button>
                                </div>
                                <form method="post" action="{{ route('ekurikulum.teams.update', $team->id_admin) }}" class="row g-3">
                                    @csrf
                                    @method('put')
                                    <div class="col-12"><label class="form-label">Username</label><input class="form-control" value="{{ $team->username }}" readonly></div>
                                    <div class="col-md-6"><label class="form-label">Nama lengkap</label><input class="form-control" name="nama_lengkap" maxlength="100" value="{{ request()->query('account') == $team->id_admin ? old('nama_lengkap', $team->nama_lengkap) : $team->nama_lengkap }}" required></div>
                                    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" maxlength="60" value="{{ request()->query('account') == $team->id_admin ? old('email', $team->email) : $team->email }}" required></div>
                                    <div class="col-md-6"><label class="form-label">Nomor telepon</label><input class="form-control" name="no_telp" maxlength="20" value="{{ request()->query('account') == $team->id_admin ? old('no_telp', $team->no_telp) : $team->no_telp }}"></div>
                                    <div class="col-md-6"><label class="form-label">Password baru (opsional)</label><input class="form-control" type="password" name="password" maxlength="100"></div>
                                    <div class="col-md-6"><label class="form-label">Status akun</label>
                                        <select class="form-control" name="blokir" required>
                                            <option value="N" @selected((request()->query('account') == $team->id_admin ? old('blokir', $team->blokir) : $team->blokir) === 'N')>Aktif</option>
                                            <option value="Y" @selected((request()->query('account') == $team->id_admin ? old('blokir', $team->blokir) : $team->blokir) === 'Y')>Diblokir</option>
                                        </select>
                                    </div>
                                    <div class="col-12 d-flex flex-wrap justify-content-end gap-2">
                                        <button class="button button-secondary" type="button" data-dialog-close>Batal</button>
                                        <button class="button button-primary" type="submit"><span data-lucide="save" aria-hidden="true"></span> Simpan perubahan</button>
                                    </div>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">Belum ada anggota tim kurikulum.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $teams->links() }}</div>
    </div>
@endsection
